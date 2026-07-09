<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

$account = sr_member_require_login($pdo);
$memberSettings = sr_member_settings($pdo);

if (sr_request_method() !== 'POST') {
    sr_render_error(405, sr_t('member::action.request.method_not_allowed'));
}

sr_require_csrf();

if (!empty($memberSettings['email_verification_enabled']) && $account['email_verified_at'] === null) {
    $throttle = sr_member_email_verification_throttle_status($pdo, (int) $account['id']);

    if (!empty($throttle['limited'])) {
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
        $token = sr_member_create_email_verification($pdo, $config, (int) $account['id'], (string) $account['email']);
        $verificationUrl = sr_absolute_url($site, '/email/verify?token=' . rawurlencode($token));
        $mailSent = sr_delivery_template_send_mail($pdo, $site, 'member.email_verification', (string) $account['email'], [
            'site_name' => (string) ($site['site_name'] ?? $site['name'] ?? 'saanraan'),
            'verification_url' => $verificationUrl,
            'expires_minutes' => '30',
        ]);
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
}

sr_redirect('/account');
