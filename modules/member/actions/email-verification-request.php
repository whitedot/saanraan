<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';

$account = toy_member_require_login($pdo);
$memberSettings = toy_member_settings($pdo);

if (toy_request_method() !== 'POST') {
    toy_render_error(405, '허용되지 않는 요청입니다.');
}

toy_require_csrf();

if (!empty($memberSettings['email_verification_enabled']) && $account['email_verified_at'] === null) {
    $throttle = toy_member_email_verification_throttle_status($pdo, (int) $account['id']);

    if (!empty($throttle['limited'])) {
        toy_member_log_auth($pdo, (int) $account['id'], 'email_verification_request_blocked', 'failure');
        toy_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'member.email_verification.blocked',
            'target_type' => 'member_account',
            'target_id' => (string) $account['id'],
            'result' => 'failure',
            'message' => 'Member email verification request blocked by throttle.',
        ]);
    } else {
        $token = toy_member_create_email_verification($pdo, $config, (int) $account['id'], (string) $account['email']);
        $verificationUrl = toy_absolute_url($site, '/email/verify?token=' . rawurlencode($token));
        $mailSent = toy_send_mail(
            $site,
            (string) $account['email'],
            '이메일 인증 안내',
            "아래 링크를 열어 이메일 인증을 완료하세요.\n\n" . $verificationUrl
        );
        $showVerificationUrl = !empty($config['debug']) && toy_is_local_host((string) ($site['base_url'] ?? ''));
        if ($showVerificationUrl) {
            $_SESSION['toy_debug_email_verification_url'] = $verificationUrl;
        } else {
            unset($_SESSION['toy_debug_email_verification_url']);
        }
        if (!$mailSent) {
            toy_member_log_auth($pdo, (int) $account['id'], 'email_verification_mail_failed', 'failure');
        }
        toy_member_log_auth($pdo, (int) $account['id'], 'email_verification_request', 'success');
        toy_audit_log($pdo, [
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

toy_redirect('/account');
