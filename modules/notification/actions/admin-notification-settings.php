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
    if ($intent === 'test_email') {
        $recipient = sr_normalize_identifier(sr_post_string('test_email_recipient', 255));
        $testErrors = [];
        $testNotice = '';

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $testErrors[] = '테스트 메일을 받을 이메일 주소가 올바르지 않습니다.';
        }
        if ((string) ($settings['email_transport'] ?? '') !== 'smtp') {
            $testErrors[] = '테스트 메일은 발송 방식이 SMTP일 때 보낼 수 있습니다.';
        }
        if ((string) ($settings['email_from_email'] ?? '') === '' || !filter_var((string) ($settings['email_from_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            $testErrors[] = 'SMTP 테스트 발송에는 올바른 발신 이메일이 필요합니다.';
        }
        if (trim((string) ($settings['email_smtp_host'] ?? '')) === '') {
            $testErrors[] = 'SMTP 호스트를 먼저 저장하세요.';
        }
        if ((int) ($settings['email_smtp_port'] ?? 0) < 1 || (int) ($settings['email_smtp_port'] ?? 0) > 65535) {
            $testErrors[] = 'SMTP 포트는 1부터 65535 사이여야 합니다.';
        }
        if (!array_key_exists((string) ($settings['email_smtp_encryption'] ?? ''), sr_notification_email_encryption_options())) {
            $testErrors[] = 'SMTP 암호화 방식을 먼저 저장하세요.';
        }

        if ($testErrors === []) {
            $smtpError = '';
            $sent = sr_send_smtp_mail(
                is_array($site ?? null) ? $site : null,
                sr_notification_mail_config_from_settings($settings),
                $recipient,
                '[Saanraan] SMTP 테스트 메일',
                "SMTP 테스트 메일입니다.\n\n이 메일을 받았다면 현재 저장된 SMTP 설정으로 메일 발송이 가능합니다.",
                $smtpError
            );

            if ($sent) {
                $testNotice = '테스트 메일을 발송했습니다.';
            } else {
                $smtpError = trim($smtpError);
                $testErrors[] = $smtpError !== ''
                    ? '테스트 메일을 발송하지 못했습니다. 원인: ' . $smtpError
                    : '테스트 메일을 발송하지 못했습니다. SMTP 설정과 서버 로그를 확인해 주세요.';
                sr_log_exception(
                    new RuntimeException('SMTP test email failed: ' . ($smtpError !== '' ? $smtpError : 'unknown')),
                    'notification_smtp_test_email_failed'
                );
            }

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'notification.settings.test_email_sent',
                'target_type' => 'module',
                'target_id' => 'notification',
                'result' => $sent ? 'success' : 'failure',
                'message' => $sent ? 'Notification SMTP test email sent.' : 'Notification SMTP test email failed.',
                'metadata' => [
                    'email_transport' => (string) ($settings['email_transport'] ?? ''),
                    'recipient_domain' => (string) substr(strrchr($recipient, '@') ?: '', 1),
                ],
            ]);
        }

        sr_admin_redirect_with_result(sr_admin_action_result($testErrors, $testNotice), '/admin/notifications/settings');
    }

    if ($intent !== 'save_settings') {
        $errors[] = '요청한 작업을 처리할 수 없습니다.';
    }

    $existingSettings = $settings;
    $emailSmtpPasswordInput = sr_post_string_without_truncation('email_smtp_password', 255);
    $emailHttpApiBearerTokenInput = sr_post_string_without_truncation('email_http_api_bearer_token', 255);
    $emailSmtpPassword = is_string($emailSmtpPasswordInput) ? trim($emailSmtpPasswordInput) : '';
    $emailHttpApiBearerToken = is_string($emailHttpApiBearerTokenInput) ? trim($emailHttpApiBearerTokenInput) : '';
    $slackWebhookUrlInput = sr_post_string_without_truncation('slack_webhook_url', 255);
    $discordWebhookUrlInput = sr_post_string_without_truncation('discord_webhook_url', 255);
    $telegramBotTokenInput = sr_post_string_without_truncation('telegram_bot_token', 255);
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
        'external_push_enabled' => ($_POST['external_push_enabled'] ?? '') === '1',
        'slack_webhook_enabled' => ($_POST['slack_webhook_enabled'] ?? '') === '1',
        'slack_webhook_url' => $slackWebhookUrlInput !== null && trim($slackWebhookUrlInput) !== '' ? trim($slackWebhookUrlInput) : (string) ($existingSettings['slack_webhook_url'] ?? ''),
        'slack_channel_label' => sr_notification_clean_setting_value(sr_post_string('slack_channel_label', 80), 80),
        'discord_webhook_enabled' => ($_POST['discord_webhook_enabled'] ?? '') === '1',
        'discord_webhook_url' => $discordWebhookUrlInput !== null && trim($discordWebhookUrlInput) !== '' ? trim($discordWebhookUrlInput) : (string) ($existingSettings['discord_webhook_url'] ?? ''),
        'discord_channel_label' => sr_notification_clean_setting_value(sr_post_string('discord_channel_label', 80), 80),
        'telegram_bot_enabled' => ($_POST['telegram_bot_enabled'] ?? '') === '1',
        'telegram_bot_token' => $telegramBotTokenInput !== null && trim($telegramBotTokenInput) !== '' ? trim($telegramBotTokenInput) : (string) ($existingSettings['telegram_bot_token'] ?? ''),
        'telegram_chat_id' => sr_notification_clean_setting_value(sr_post_string('telegram_chat_id', 120), 120),
        'telegram_channel_label' => sr_notification_clean_setting_value(sr_post_string('telegram_channel_label', 80), 80),
        'external_push_failure_policy' => sr_post_string('external_push_failure_policy', 20),
        'delivery_web_runner_enabled' => ($_POST['delivery_web_runner_enabled'] ?? '') === '1',
        'delivery_web_runner_interval_seconds' => (int) sr_post_string('delivery_web_runner_interval_seconds', 10),
        'delivery_web_runner_batch_size' => (int) sr_post_string('delivery_web_runner_batch_size', 10),
        'delivery_manual_batch_size' => (int) sr_post_string('delivery_manual_batch_size', 10),
        'delivery_cli_batch_size' => (int) sr_post_string('delivery_cli_batch_size', 10),
        'delivery_max_attempts' => (int) sr_post_string('delivery_max_attempts', 10),
        'delivery_lock_timeout_seconds' => (int) sr_post_string('delivery_lock_timeout_seconds', 10),
    ];

    if (!array_key_exists($settings['email_transport'], sr_notification_email_transport_options())) {
        $errors[] = '메일 발송 방식을 선택하세요.';
    }
    if ($emailSmtpPasswordInput === null) {
        $errors[] = 'SMTP 비밀번호는 255자 이내로 입력하세요.';
    }
    if ($emailHttpApiBearerTokenInput === null) {
        $errors[] = '메일 API 인증 토큰은 255자 이내로 입력하세요.';
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
    if ($settings['email_timeout_seconds'] < 3 || $settings['email_timeout_seconds'] > 180) {
        $errors[] = '메일 타임아웃은 3초부터 180초 사이로 입력하세요.';
    }
    if ($settings['delivery_web_runner_interval_seconds'] < 10 || $settings['delivery_web_runner_interval_seconds'] > 3600) {
        $errors[] = '웹 자동 실행 간격은 10초부터 3600초 사이로 입력하세요.';
    }
    if ($settings['delivery_web_runner_batch_size'] < 1 || $settings['delivery_web_runner_batch_size'] > 5) {
        $errors[] = '웹 실행당 발송 수는 1부터 5 사이로 입력하세요.';
    }
    if ($settings['delivery_manual_batch_size'] < 1 || $settings['delivery_manual_batch_size'] > 50) {
        $errors[] = '수동 실행당 발송 수는 1부터 50 사이로 입력하세요.';
    }
    if ($settings['delivery_cli_batch_size'] < 1 || $settings['delivery_cli_batch_size'] > 100) {
        $errors[] = '명령줄 실행당 발송 수는 1부터 100 사이로 입력하세요.';
    }
    if ($settings['delivery_max_attempts'] < 1 || $settings['delivery_max_attempts'] > 20) {
        $errors[] = '최대 재시도 횟수는 1부터 20 사이로 입력하세요.';
    }
    if ($settings['delivery_lock_timeout_seconds'] < 30 || $settings['delivery_lock_timeout_seconds'] > 3600) {
        $errors[] = '처리 점유 만료 시간은 30초부터 3600초 사이로 입력하세요.';
    }
    if ($slackWebhookUrlInput === null) {
        $errors[] = 'Slack 수신 URL은 255자 이내로 입력하세요.';
    }
    if ($discordWebhookUrlInput === null) {
        $errors[] = 'Discord 수신 URL은 255자 이내로 입력하세요.';
    }
    if ($telegramBotTokenInput === null) {
        $errors[] = 'Telegram 봇 토큰은 255자 이내로 입력하세요.';
    }
    if (!in_array($settings['external_push_failure_policy'], ['retry', 'dead'], true)) {
        $errors[] = '외부 푸시 실패 정책을 선택하세요.';
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
            $errors[] = '메일 API 발송에는 발신 이메일이 필요합니다.';
        }
        if ($settings['email_http_api_endpoint'] === '' || !sr_mail_http_api_endpoint_is_allowed($settings['email_http_api_endpoint'])) {
            $errors[] = '메일 API 전송 URL은 공개 HTTPS URL이어야 합니다.';
        }
    }
    if ($settings['external_push_enabled']) {
        $enabledProviderCount = 0;
        if ($settings['slack_webhook_enabled']) {
            $enabledProviderCount++;
        }
        if ($settings['discord_webhook_enabled']) {
            $enabledProviderCount++;
        }
        if ($settings['telegram_bot_enabled']) {
            $enabledProviderCount++;
        }
        if ($enabledProviderCount < 1) {
            $errors[] = '외부 푸시 사용 시 발송 채널을 하나 이상 켜세요.';
        }
        if ($settings['slack_webhook_enabled'] && $settings['slack_webhook_url'] !== '' && $settings['slack_channel_label'] === '') {
            $errors[] = 'Slack 채널 표시명을 입력하세요.';
        }
        if ($settings['slack_webhook_enabled'] && $settings['slack_webhook_url'] !== '' && !sr_notification_webhook_url_is_allowed((string) $settings['slack_webhook_url'])) {
            $errors[] = 'Slack 수신 URL은 HTTPS URL이어야 합니다.';
        }
        if ($settings['discord_webhook_enabled'] && $settings['discord_webhook_url'] !== '' && $settings['discord_channel_label'] === '') {
            $errors[] = 'Discord 채널 표시명을 입력하세요.';
        }
        if ($settings['discord_webhook_enabled'] && $settings['discord_webhook_url'] !== '' && !sr_notification_webhook_url_is_allowed((string) $settings['discord_webhook_url'])) {
            $errors[] = 'Discord 수신 URL은 HTTPS URL이어야 합니다.';
        }
        if ($settings['telegram_bot_enabled'] && $settings['telegram_chat_id'] !== '' && $settings['telegram_channel_label'] === '') {
            $errors[] = 'Telegram 채널 표시명을 입력하세요.';
        }
        if ($settings['telegram_bot_enabled'] && !sr_notification_telegram_bot_token_is_allowed((string) $settings['telegram_bot_token'])) {
            $errors[] = 'Telegram 봇 토큰 형식이 올바르지 않습니다.';
        }
        if ($settings['telegram_bot_enabled'] && $settings['telegram_chat_id'] !== '' && !sr_notification_telegram_chat_id_is_allowed((string) $settings['telegram_chat_id'])) {
            $errors[] = 'Telegram 대화방 ID 형식이 올바르지 않습니다.';
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
                'external_push_enabled' => (bool) $settings['external_push_enabled'],
                'slack_webhook_enabled' => (bool) $settings['slack_webhook_enabled'],
                'discord_webhook_enabled' => (bool) $settings['discord_webhook_enabled'],
                'telegram_bot_enabled' => (bool) $settings['telegram_bot_enabled'],
                'external_push_failure_policy' => (string) $settings['external_push_failure_policy'],
                'delivery_web_runner_enabled' => (bool) $settings['delivery_web_runner_enabled'],
                'delivery_max_attempts' => (int) $settings['delivery_max_attempts'],
            ],
        ]);
        $notice = '알림 환경설정을 저장했습니다.';
    }

    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), '/admin/notifications/settings');
}

include SR_ROOT . '/modules/notification/views/admin-notification-settings.php';
