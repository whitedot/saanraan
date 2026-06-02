<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/notification/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/notifications/settings', 'view');

$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_notification_settings($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/notifications/settings', 'edit');

    $intent = sr_post_string('intent', 40);
    if ($intent !== 'save_settings') {
        $errors[] = '요청한 작업을 처리할 수 없습니다.';
    }

    $existingSettings = $settings;
    $emailSmtpPassword = sr_post_string('email_smtp_password', 255);
    $emailHttpApiBearerToken = sr_post_string('email_http_api_bearer_token', 255);
    $settings = [
        'email_channel_enabled' => ($_POST['email_channel_enabled'] ?? '') === '1',
        'email_transport' => sr_post_string('email_transport', 30),
        'email_from_email' => sr_normalize_identifier(sr_post_string('email_from_email', 255)),
        'email_from_name' => sr_notification_clean_setting_value(sr_post_string('email_from_name', 120), 120),
        'email_smtp_host' => sr_notification_clean_setting_value(sr_post_string('email_smtp_host', 255), 255),
        'email_smtp_port' => (int) sr_post_string('email_smtp_port', 10),
        'email_smtp_encryption' => sr_post_string('email_smtp_encryption', 20),
        'email_smtp_username' => sr_notification_clean_setting_value(sr_post_string('email_smtp_username', 255), 255),
        'email_smtp_password' => $emailSmtpPassword !== '' ? $emailSmtpPassword : (string) ($existingSettings['email_smtp_password'] ?? ''),
        'email_timeout_seconds' => (int) sr_post_string('email_timeout_seconds', 10),
        'email_http_api_endpoint' => sr_notification_clean_setting_value(sr_post_string('email_http_api_endpoint', 255), 255),
        'email_http_api_bearer_token' => $emailHttpApiBearerToken !== '' ? $emailHttpApiBearerToken : (string) ($existingSettings['email_http_api_bearer_token'] ?? ''),
    ];

    if (!array_key_exists($settings['email_transport'], sr_notification_email_transport_options())) {
        $errors[] = '메일 발송 방식을 선택하세요.';
    }
    if ($settings['email_from_email'] !== '' && !filter_var($settings['email_from_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = '발신 이메일 주소가 올바르지 않습니다.';
    }
    if ($settings['email_smtp_port'] < 1 || $settings['email_smtp_port'] > 65535) {
        $errors[] = 'SMTP 포트는 1부터 65535 사이로 입력하세요.';
    }
    if (!array_key_exists($settings['email_smtp_encryption'], sr_notification_email_encryption_options())) {
        $errors[] = 'SMTP 암호화 방식을 선택하세요.';
    }
    if ($settings['email_timeout_seconds'] < 3 || $settings['email_timeout_seconds'] > 30) {
        $errors[] = '메일 타임아웃은 3초부터 30초 사이로 입력하세요.';
    }

    if ($settings['email_channel_enabled'] && $settings['email_transport'] === 'smtp') {
        if ($settings['email_from_email'] === '') {
            $errors[] = 'SMTP 발송에는 발신 이메일이 필요합니다.';
        }
        if ($settings['email_smtp_host'] === '') {
            $errors[] = 'SMTP 호스트를 입력하세요.';
        }
    }
    if ($settings['email_channel_enabled'] && $settings['email_transport'] === 'http_api') {
        if ($settings['email_from_email'] === '') {
            $errors[] = 'HTTP API 발송에는 발신 이메일이 필요합니다.';
        }
        if ($settings['email_http_api_endpoint'] === '' || !sr_mail_http_api_endpoint_is_allowed($settings['email_http_api_endpoint'])) {
            $errors[] = '메일 HTTP API endpoint는 공개 HTTPS URL이어야 합니다.';
        }
    }

    if ($errors === []) {
        sr_notification_save_settings($pdo, $settings);
        $settings = sr_notification_settings($pdo);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'notification.settings.updated',
            'target_type' => 'module',
            'target_id' => 'notification',
            'result' => 'success',
            'message' => 'Notification settings updated.',
            'metadata' => [
                'email_channel_enabled' => (bool) $settings['email_channel_enabled'],
                'email_transport' => (string) $settings['email_transport'],
            ],
        ]);
        $notice = '알림 환경설정을 저장했습니다.';
    }

    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), '/admin/notifications/settings');
}

include SR_ROOT . '/modules/notification/views/admin-notification-settings.php';
