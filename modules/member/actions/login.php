<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

$account = sr_member_current_account($pdo);
if ($account !== null) {
    if (sr_request_method() === 'POST') {
        sr_require_csrf();
    }
    sr_redirect('/account');
}

$errors = [];
$notice = '';
$identifier = '';
$next = sr_member_safe_next_path(sr_get_string('next', 255));
$memberSettings = sr_member_settings($pdo);

if (!empty($_SESSION['sr_member_login_notice']) && is_string($_SESSION['sr_member_login_notice'])) {
    $notice = $_SESSION['sr_member_login_notice'];
    unset($_SESSION['sr_member_login_notice']);
}

if ($notice === '' && sr_get_string('password_reset', 10) === '1') {
    $notice = '비밀번호를 재설정했습니다. 새 비밀번호로 로그인하세요.';
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $identifier = sr_post_string_without_truncation('identifier', 255);
    if ($identifier === null) {
        $identifier = '';
    }

    $password = sr_post_string('password', 255);
    $next = sr_member_safe_next_path(sr_post_string('next', 255));
    $account = sr_member_find_by_identifier($pdo, $config, $identifier);
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
        $errors[] = '로그인 시도가 많습니다. 잠시 후 다시 시도하세요.';
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
                '이메일 인증 안내',
                "아래 링크를 열어 이메일 인증을 완료하세요.\n\n" . $verificationUrl
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
        $errors[] = '이메일 인증을 완료한 뒤 로그인할 수 있습니다. 인증 안내 메일을 다시 확인하세요.';
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
        $errors[] = '로그인 세션을 만들 수 없습니다. 잠시 후 다시 시도하세요.';
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
        $errors[] = '로그인 정보가 올바르지 않습니다.';
    }
}

$memberSkinView = sr_member_skin_view(sr_member_skin_key($memberSettings), 'login');
include $memberSkinView;
