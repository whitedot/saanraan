<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

$errors = [];
$notice = '';
$resetUrl = '';
$showResetUrl = false;
$email = '';
$flash = isset($_SESSION['sr_member_password_reset_request_flash']) && is_array($_SESSION['sr_member_password_reset_request_flash'])
    ? $_SESSION['sr_member_password_reset_request_flash']
    : [];
unset($_SESSION['sr_member_password_reset_request_flash']);
$errors = isset($flash['errors']) && is_array($flash['errors']) ? array_values(array_map('strval', $flash['errors'])) : [];
$notice = (string) ($flash['notice'] ?? '');
$resetUrl = (string) ($flash['reset_url'] ?? '');
$showResetUrl = !empty($flash['show_reset_url']);
$email = (string) ($flash['email'] ?? '');
$memberSettings = sr_member_settings($pdo);
$emailDeliveryAvailable = sr_member_email_delivery_available($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    if (!$emailDeliveryAvailable) {
        $errors[] = sr_t('member::action.email_delivery.unavailable');
    } else {
        $email = sr_post_string_without_truncation('email', 255);
        if ($email === null) {
            $errors[] = sr_t('member::action.register.email_too_long');
            $email = '';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = sr_t('member::action.register.email_invalid');
        }
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
            $mailSent = sr_member_send_delivery_template_mail($pdo, $site, 'member.password_reset', (string) $activeAccount['email'], [
                'site_name' => (string) ($site['site_name'] ?? $site['name'] ?? 'saanraan'),
                'reset_url' => $resetUrl,
                'expires_minutes' => '30',
            ]);
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

        $notice = sr_t('member::action.password_reset.sent_notice');
    }

    $_SESSION['sr_member_password_reset_request_flash'] = [
        'errors' => $errors,
        'notice' => $notice,
        'reset_url' => $resetUrl,
        'show_reset_url' => $showResetUrl,
        'email' => $email,
    ];
    sr_redirect('/password/reset');
}

$memberSkinView = sr_member_skin_view(sr_member_skin_key($memberSettings), 'password-reset-request');
include $memberSkinView;
