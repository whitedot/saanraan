<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

$next = sr_member_login_next_path();
$account = sr_member_current_account($pdo);
if ($account !== null) {
    if (sr_request_method() === 'POST') {
        sr_require_csrf();
        $next = sr_member_safe_next_path(sr_post_string_without_truncation('next', 1024) ?? '');
    }
    sr_redirect($next);
}

$errors = [];
$notice = '';
$identifier = '';
$memberSettings = sr_member_settings($pdo);

if (!empty($_SESSION['sr_member_login_notice']) && is_string($_SESSION['sr_member_login_notice'])) {
    $notice = $_SESSION['sr_member_login_notice'];
    unset($_SESSION['sr_member_login_notice']);
}

if ($notice === '' && sr_get_string('password_reset', 10) === '1') {
    $notice = sr_t('member::action.login.password_reset_notice');
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $identifier = sr_post_string_without_truncation('identifier', 255);
    if ($identifier === null) {
        $identifier = '';
    }

    $password = sr_post_string('password', 255);
    $next = sr_member_safe_next_path(sr_post_string_without_truncation('next', 1024) ?? '');
    $account = sr_member_find_by_identifier($pdo, $config, $identifier, sr_member_email_login_enabled($memberSettings));
    $throttle = sr_member_login_throttle_status($pdo, $account !== null ? (int) $account['id'] : null);
    $passwordVerified = false;

    if (!empty($throttle['limited'])) {
        sr_member_log_auth($pdo, $account !== null ? (int) $account['id'] : null, 'login_blocked', 'failure');
        sr_audit_log($pdo, [
            'actor_account_id' => $account !== null ? (int) $account['id'] : null,
            'actor_type' => 'member',
            'event_type' => 'member.login.blocked',
            'target_type' => 'member_account',
            'target_id' => $account !== null ? (string) $account['id'] : '',
            'result' => 'failure',
            'message' => 'Member login blocked by throttle.',
        ]);
        $errors[] = sr_t('member::action.login.throttled');
    } elseif (
        ($passwordVerified = sr_member_verify_login_password($account, $password))
        && sr_member_email_verification_blocks_login($memberSettings, $account)
    ) {
        $verificationThrottle = sr_member_email_verification_throttle_status($pdo, (int) $account['id']);
        if (!empty($verificationThrottle['limited'])) {
            sr_member_log_auth($pdo, (int) $account['id'], 'email_verification_request_blocked', 'failure');
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'member',
                'event_type' => 'member.email_verification.blocked',
                'target_type' => 'member_account',
                'target_id' => (string) $account['id'],
                'result' => 'failure',
                'message' => 'Member email verification request blocked by throttle.',
            ]);
        } else {
            $verificationToken = sr_member_create_email_verification($pdo, $config, (int) $account['id'], (string) $account['email']);
            $verificationUrl = sr_absolute_url($site, '/email/verify?token=' . rawurlencode($verificationToken));
            $mailSent = sr_send_mail(
                $site,
                (string) $account['email'],
                sr_t('member::action.email_verification.subject'),
                sr_t('member::action.email_verification.body', ['url' => $verificationUrl])
            );
            $showVerificationUrl = !empty($config['debug']) && sr_is_local_host((string) ($site['base_url'] ?? ''));
            if ($showVerificationUrl) {
                $_SESSION['sr_debug_email_verification_url'] = $verificationUrl;
            } else {
                unset($_SESSION['sr_debug_email_verification_url']);
            }
            if (!$mailSent) {
                sr_member_log_auth($pdo, (int) $account['id'], 'email_verification_mail_failed', 'failure');
            }
            sr_member_log_auth($pdo, (int) $account['id'], 'email_verification_request', 'success');
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'member',
                'event_type' => 'member.email_verification.requested',
                'target_type' => 'member_account',
                'target_id' => (string) $account['id'],
                'result' => 'success',
                'message' => 'Member email verification requested.',
                'metadata' => [
                    'mail_sent' => $mailSent,
                ],
            ]);
        }
        sr_member_log_auth($pdo, (int) $account['id'], 'login_email_unverified', 'failure');
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'member.login.email_unverified',
            'target_type' => 'member_account',
            'target_id' => (string) $account['id'],
            'result' => 'failure',
            'message' => 'Member login blocked until email verification.',
        ]);
        $errors[] = sr_t('member::action.login.email_unverified');
    } elseif ($passwordVerified) {
        sr_member_rehash_login_password_if_needed($pdo, (int) $account['id'], $password, (string) $account['password_hash']);
        if (sr_member_login($pdo, $account)) {
            sr_member_group_evaluate_account($pdo, (int) $account['id']);
            sr_member_log_auth($pdo, (int) $account['id'], 'login', 'success');
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'member',
                'event_type' => 'member.login',
                'target_type' => 'member_account',
                'target_id' => (string) $account['id'],
                'result' => 'success',
                'message' => 'Member login succeeded.',
            ]);
            sr_redirect($next);
        }

        sr_member_log_auth($pdo, (int) $account['id'], 'login_session_failed', 'failure');
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'member.login.session_failed',
            'target_type' => 'member_account',
            'target_id' => (string) $account['id'],
            'result' => 'failure',
            'message' => 'Member login session could not be created.',
        ]);
        $errors[] = sr_t('member::action.login.session_failed');
    } else {
        sr_member_log_auth($pdo, $account !== null ? (int) $account['id'] : null, 'login', 'failure');
        sr_audit_log($pdo, [
            'actor_account_id' => $account !== null ? (int) $account['id'] : null,
            'actor_type' => 'member',
            'event_type' => 'member.login',
            'target_type' => 'member_account',
            'target_id' => $account !== null ? (string) $account['id'] : '',
            'result' => 'failure',
            'message' => 'Member login failed.',
        ]);
        $errors[] = sr_t('member::action.login.invalid');
    }
}

$memberSkinView = sr_member_skin_view(sr_member_skin_key($memberSettings), 'login');
include $memberSkinView;
