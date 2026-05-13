<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

$errors = [];
$notice = '';
$resetUrl = '';
$showResetUrl = false;
$email = '';
$memberSettings = sr_member_settings($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $email = sr_post_string_without_truncation('email', 255);
    if ($email === null) {
        $errors[] = '이메일은 255자 이하로 입력하세요.';
        $email = '';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '이메일 형식이 올바르지 않습니다.';
    }

    if ($errors === []) {
        $account = sr_member_find_by_email($pdo, $config, $email);
        $activeAccount = $account !== null && $account['status'] === 'active' ? $account : null;
        $throttle = sr_member_password_reset_throttle_status($pdo, $activeAccount !== null ? (int) $activeAccount['id'] : null);

        if (!empty($throttle['limited'])) {
            sr_member_log_auth($pdo, $activeAccount !== null ? (int) $activeAccount['id'] : null, 'password_reset_request_blocked', 'failure');
            sr_audit_log($pdo, [
                'actor_account_id' => $activeAccount !== null ? (int) $activeAccount['id'] : null,
                'actor_type' => 'member',
                'event_type' => 'member.password_reset.blocked',
                'target_type' => 'member_account',
                'target_id' => $activeAccount !== null ? (string) $activeAccount['id'] : '',
                'result' => 'failure',
                'message' => 'Member password reset request blocked by throttle.',
            ]);
        } elseif ($activeAccount !== null) {
            $token = sr_member_create_password_reset($pdo, $config, (int) $activeAccount['id']);
            $resetUrl = sr_absolute_url($site, '/password/reset/confirm?token=' . rawurlencode($token));
            $showResetUrl = !empty($config['debug']) && sr_is_local_host((string) ($site['base_url'] ?? ''));
            $mailSent = sr_send_mail(
                $site,
                (string) $activeAccount['email'],
                '비밀번호 재설정 안내',
                "아래 링크를 열어 비밀번호를 재설정하세요.\n\n" . $resetUrl
            );
            if (!$mailSent) {
                sr_member_log_auth($pdo, (int) $activeAccount['id'], 'password_reset_mail_failed', 'failure');
            }
            sr_member_log_auth($pdo, (int) $activeAccount['id'], 'password_reset_request', 'success');
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $activeAccount['id'],
                'actor_type' => 'member',
                'event_type' => 'member.password_reset.requested',
                'target_type' => 'member_account',
                'target_id' => (string) $activeAccount['id'],
                'result' => 'success',
                'message' => 'Member password reset requested.',
                'metadata' => [
                    'mail_sent' => $mailSent,
                ],
            ]);
        } else {
            sr_member_log_auth($pdo, null, 'password_reset_request', 'failure');
        }

        $notice = '입력한 이메일로 비밀번호 재설정 안내를 보냈습니다.';
    }
}

$memberSkinView = sr_member_skin_view(sr_member_skin_key($memberSettings), 'password-reset-request');
include $memberSkinView;
