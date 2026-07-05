<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/helpers/common.php';
require_once SR_ROOT . '/modules/notification/helpers/admin-notifications.php';
require_once SR_ROOT . '/modules/notification/helpers/deliveries.php';

function sr_notification_clean_single_line(string $value, int $maxLength): string
{
    return sr_clean_single_line($value, $maxLength);
}

function sr_notification_clean_text(string $value, int $maxLength): string
{
    return sr_clean_text($value, $maxLength);
}

function sr_notification_body_format(string $value): string
{
    return in_array($value, ['plain', 'html', 'markdown'], true) ? $value : 'plain';
}

function sr_notification_body_html(array $notification): string
{
    $globalPdo = $GLOBALS['pdo'] ?? null;
    return sr_body_text_html($notification, false, $globalPdo instanceof PDO ? $globalPdo : null, 'plain');
}

function sr_notification_table_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];

    $cacheKey = spl_object_id($pdo) . '.' . $table . '.' . $column;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $stmt = $pdo->query('SELECT ' . $column . ' FROM ' . $table . ' LIMIT 0');
        $cache[$cacheKey] = $stmt !== false;
    } catch (Throwable) {
        $cache[$cacheKey] = false;
    }

    return $cache[$cacheKey];
}

function sr_notification_event_columns_available(PDO $pdo): bool
{
    return sr_notification_table_has_column($pdo, 'sr_notifications', 'source_module_key')
        && sr_notification_table_has_column($pdo, 'sr_notifications', 'event_key')
        && sr_notification_table_has_column($pdo, 'sr_notifications', 'metadata_json');
}

function sr_notification_event_select_sql(PDO $pdo, string $alias = 'n'): string
{
    if (!sr_notification_event_columns_available($pdo)) {
        return ", '' AS source_module_key, '' AS event_key, NULL AS metadata_json";
    }

    $aliasPrefix = $alias !== '' ? $alias . '.' : '';
    return ', ' . $aliasPrefix . 'source_module_key, ' . $aliasPrefix . 'event_key, ' . $aliasPrefix . 'metadata_json';
}

function sr_notification_metadata_json(array $metadata): string
{
    $cleanMetadata = [];
    foreach ($metadata as $key => $value) {
        if (!is_string($key) || preg_match('/\A[a-zA-Z0-9_.-]{1,80}\z/', $key) !== 1) {
            continue;
        }
        if (is_scalar($value) || $value === null) {
            $cleanMetadata[$key] = $value === null ? '' : (string) $value;
        }
    }

    if ($cleanMetadata === []) {
        return '';
    }

    $json = json_encode($cleanMetadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : '';
}

function sr_notification_metadata_from_row(array $notification): array
{
    $metadataJson = (string) ($notification['metadata_json'] ?? '');
    if (trim($metadataJson) === '') {
        return [];
    }

    $decoded = json_decode($metadataJson, true);
    if (!is_array($decoded)) {
        return [];
    }

    $metadata = [];
    foreach ($decoded as $key => $value) {
        if (is_string($key) && (is_scalar($value) || $value === null)) {
            $metadata[$key] = $value === null ? '' : (string) $value;
        }
    }

    return $metadata;
}

function sr_notification_title_from_row(PDO $pdo, array $notification): string
{
    $moduleKey = (string) ($notification['source_module_key'] ?? '');
    $eventKey = (string) ($notification['event_key'] ?? '');
    if ($moduleKey !== '' && $eventKey !== '') {
        $template = sr_notification_event_template($pdo, $moduleKey, $eventKey);
        $titleTemplate = is_array($template) ? (string) ($template['title_template'] ?? '') : '';
        if ($titleTemplate !== '') {
            $title = sr_notification_clean_single_line(sr_notification_render_template($titleTemplate, sr_notification_metadata_from_row($notification)), 160);
            if ($title !== '') {
                return $title;
            }
        }
    }

    $title = sr_notification_clean_single_line((string) ($notification['title'] ?? ''), 160);
    return $title !== '' ? $title : '알림';
}

function sr_notification_apply_rendered_titles(PDO $pdo, array $notifications): array
{
    foreach ($notifications as $index => $notification) {
        if (is_array($notification)) {
            $notifications[$index]['title'] = sr_notification_title_from_row($pdo, $notification);
        }
    }

    return $notifications;
}

function sr_notification_time_html(string $value): string
{
    return sr_relative_time_html($value);
}

function sr_notification_clean_link_url(string $value): string
{
    $value = trim($value);
    if ($value === '' || sr_is_safe_relative_url($value) || sr_is_http_url($value)) {
        return $value;
    }

    return '';
}

function sr_notification_read_token(int $notificationId, int $accountId): string
{
    if ($notificationId <= 0 || $accountId <= 0) {
        return '';
    }

    try {
        return substr(sr_hmac_hash('notification-read|' . $accountId . '|' . $notificationId, sr_runtime_config()), 0, 32);
    } catch (Throwable) {
        return '';
    }
}

function sr_notification_read_token_is_valid(int $notificationId, int $accountId, string $token): bool
{
    if ($token === '' || preg_match('/\A[a-f0-9]{32}\z/', $token) !== 1) {
        return false;
    }

    $expected = sr_notification_read_token($notificationId, $accountId);
    return $expected !== '' && hash_equals($expected, $token);
}

function sr_notification_read_redirect_url(int $notificationId, int $accountId): string
{
    if ($notificationId <= 0) {
        return sr_url('/account/notifications');
    }

    $query = 'id=' . rawurlencode((string) $notificationId);
    $token = sr_notification_read_token($notificationId, $accountId);
    if ($token === '') {
        return sr_url('/account/notifications');
    }

    $query .= '&token=' . rawurlencode($token);
    return sr_url('/account/notifications/read?' . $query);
}

function sr_notification_link_attributes(string $url, int $notificationId = 0, bool $markRead = false, int $accountId = 0): string
{
    $url = sr_notification_clean_link_url($url);
    $canMarkRead = $markRead && $notificationId > 0 && $accountId > 0 && sr_notification_read_token($notificationId, $accountId) !== '';
    if ($url === '' && !$canMarkRead) {
        return '';
    }

    $href = $url === '' ? sr_url('/account/notifications') : (sr_is_http_url($url) ? $url : sr_url($url));
    if ($canMarkRead) {
        $href = sr_notification_read_redirect_url($notificationId, $accountId);
    }

    $attributes = ' href="' . sr_e($href) . '"';
    if (!$markRead && sr_is_http_url($url)) {
        $attributes .= ' target="_blank" rel="noopener noreferrer"';
    }

    return $attributes;
}

function sr_notification_item_link_attributes(array $notification, int $accountId, bool $markRead = false): string
{
    return sr_notification_link_attributes(
        (string) ($notification['link_url'] ?? ''),
        (int) ($notification['id'] ?? 0),
        $markRead,
        $accountId
    );
}

function sr_notification_readable_notification(PDO $pdo, int $notificationId, int $accountId): ?array
{
    if ($notificationId <= 0 || $accountId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, audience, link_url
         FROM sr_notifications
         WHERE id = :id
           AND (account_id = :account_id OR audience = 'all')
         LIMIT 1"
    );
    $stmt->execute([
        'id' => $notificationId,
        'account_id' => $accountId,
    ]);
    $notification = $stmt->fetch();

    return is_array($notification) ? $notification : null;
}

function sr_notification_mark_read(PDO $pdo, int $notificationId, int $accountId): bool
{
    $notification = sr_notification_readable_notification($pdo, $notificationId, $accountId);
    if ($notification === null) {
        return false;
    }

    $now = sr_now();
    $linkUrl = sr_notification_clean_link_url((string) ($notification['link_url'] ?? ''));
    if ($linkUrl !== '') {
        sr_notification_mark_matching_link_read($pdo, $linkUrl, $accountId, $now);
        return true;
    }

    if ((string) $notification['audience'] === 'all') {
        $driver = '';
        try {
            $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable) {
            $driver = '';
        }

        $upsertClause = 'ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)';
        if ($driver === 'sqlite') {
            $upsertClause = 'ON CONFLICT(notification_id, account_id) DO UPDATE SET read_at = excluded.read_at';
        }

        $stmt = $pdo->prepare(
            'INSERT INTO sr_notification_reads (notification_id, account_id, read_at)
             VALUES (:notification_id, :account_id, :read_at)
             ' . $upsertClause
        );
        $stmt->execute([
            'notification_id' => $notificationId,
            'account_id' => $accountId,
            'read_at' => $now,
        ]);

        return true;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_notifications
         SET read_at = :read_at, updated_at = :updated_at
         WHERE id = :id AND account_id = :account_id'
    );
    $stmt->execute([
        'read_at' => $now,
        'updated_at' => $now,
        'id' => $notificationId,
        'account_id' => $accountId,
    ]);

    return true;
}

function sr_notification_mark_matching_link_read(PDO $pdo, string $linkUrl, int $accountId, string $readAt): void
{
    if ($accountId <= 0 || $linkUrl === '') {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_notifications
         SET read_at = :read_at, updated_at = :updated_at
         WHERE account_id = :account_id
           AND link_url = :link_url
           AND read_at IS NULL'
    );
    $stmt->execute([
        'read_at' => $readAt,
        'updated_at' => $readAt,
        'account_id' => $accountId,
        'link_url' => $linkUrl,
    ]);

    $stmt = $pdo->prepare(
        'SELECT n.id
         FROM sr_notifications n
         LEFT JOIN sr_notification_reads r ON r.notification_id = n.id AND r.account_id = :read_account_id
         WHERE n.audience = \'all\'
           AND n.link_url = :link_url
           AND r.id IS NULL'
    );
    $stmt->execute([
        'read_account_id' => $accountId,
        'link_url' => $linkUrl,
    ]);

    $notificationIds = [];
    foreach ($stmt->fetchAll() as $row) {
        if (is_array($row)) {
            $notificationIds[] = (int) ($row['id'] ?? 0);
        }
    }
    if ($notificationIds === []) {
        return;
    }

    $driver = '';
    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable) {
        $driver = '';
    }

    $upsertClause = 'ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)';
    if ($driver === 'sqlite') {
        $upsertClause = 'ON CONFLICT(notification_id, account_id) DO UPDATE SET read_at = excluded.read_at';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_notification_reads (notification_id, account_id, read_at)
         VALUES (:notification_id, :account_id, :read_at)
         ' . $upsertClause
    );
    foreach ($notificationIds as $matchingNotificationId) {
        if ($matchingNotificationId <= 0) {
            continue;
        }
        $stmt->execute([
            'notification_id' => $matchingNotificationId,
            'account_id' => $accountId,
            'read_at' => $readAt,
        ]);
    }
}

function sr_notification_mark_read_redirect_link(PDO $pdo, int $notificationId, int $accountId, string $token): string
{
    if (!sr_notification_read_token_is_valid($notificationId, $accountId, $token)) {
        return '';
    }

    $notification = sr_notification_readable_notification($pdo, $notificationId, $accountId);
    if ($notification === null) {
        return '';
    }

    if (!sr_notification_mark_read($pdo, $notificationId, $accountId)) {
        return '';
    }

    return sr_notification_clean_link_url((string) ($notification['link_url'] ?? ''));
}

function sr_notification_public_header_summary(PDO $pdo, int $accountId, int $limit = 5): array
{
    if ($accountId <= 0) {
        return ['unread' => 0, 'items' => []];
    }

    $limit = max(1, min(10, $limit));

    try {
        $eventSelect = sr_notification_event_select_sql($pdo, 'n');
        $stmt = $pdo->prepare(
            "SELECT n.id, n.title, n.body_text, n.body_format, n.link_url" . $eventSelect . ",
                    CASE WHEN COALESCE(n.read_at, r.read_at) IS NULL THEN 'unread' ELSE 'read' END AS status,
                    COALESCE(n.read_at, r.read_at) AS read_at,
                    n.created_at
             FROM sr_notifications n
             LEFT JOIN sr_notification_reads r ON r.notification_id = n.id AND r.account_id = :read_account_id
             WHERE (n.account_id = :account_id OR n.audience = 'all')
               AND COALESCE(n.read_at, r.read_at) IS NULL
             ORDER BY n.id DESC
             LIMIT " . $limit
        );
        $stmt->execute([
            'read_account_id' => $accountId,
            'account_id' => $accountId,
        ]);
        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $items[] = $row;
            }
        }
        $items = sr_notification_apply_rendered_titles($pdo, $items);

        $stmt = $pdo->prepare(
            "SELECT SUM(CASE WHEN COALESCE(n.read_at, r.read_at) IS NULL THEN 1 ELSE 0 END) AS unread_count
             FROM sr_notifications n
             LEFT JOIN sr_notification_reads r ON r.notification_id = n.id AND r.account_id = :read_account_id
             WHERE n.account_id = :account_id OR n.audience = 'all'"
        );
        $stmt->execute([
            'read_account_id' => $accountId,
            'account_id' => $accountId,
        ]);
        $summary = $stmt->fetch();
    } catch (Throwable) {
        return ['unread' => 0, 'items' => []];
    }

    return [
        'unread' => is_array($summary) ? (int) ($summary['unread_count'] ?? 0) : 0,
        'items' => $items,
    ];
}

function sr_notification_allowed_channels(): array
{
    return ['site', 'email', 'slack_webhook', 'discord_webhook', 'telegram_bot', 'sms', 'alimtalk'];
}

function sr_notification_default_settings(): array
{
    return [
        'email_channel_enabled' => true,
        'email_transport' => 'php_mail',
        'email_from_email' => '',
        'email_from_name' => '',
        'email_smtp_host' => '',
        'email_smtp_port' => 587,
        'email_smtp_encryption' => 'tls',
        'email_smtp_username' => '',
        'email_smtp_password' => '',
        'email_timeout_seconds' => 10,
        'email_http_api_endpoint' => '',
        'email_http_api_bearer_token' => '',
        'external_push_enabled' => false,
        'slack_webhook_enabled' => true,
        'slack_webhook_url' => '',
        'slack_channel_label' => '운영 알림',
        'discord_webhook_enabled' => false,
        'discord_webhook_url' => '',
        'discord_channel_label' => '운영 알림',
        'telegram_bot_enabled' => false,
        'telegram_bot_token' => '',
        'telegram_chat_id' => '',
        'telegram_channel_label' => '운영 알림',
        'external_push_failure_policy' => 'retry',
        'delivery_web_runner_enabled' => true,
        'delivery_web_runner_interval_seconds' => 60,
        'delivery_web_runner_batch_size' => 1,
        'delivery_manual_batch_size' => 10,
        'delivery_cli_batch_size' => 20,
        'delivery_max_attempts' => 5,
        'delivery_lock_timeout_seconds' => 300,
    ];
}

function sr_notification_settings(PDO $pdo): array
{
    $settings = array_merge(sr_notification_default_settings(), sr_module_settings($pdo, 'notification'));
    $settings['email_channel_enabled'] = (bool) $settings['email_channel_enabled'];
    $settings['email_transport'] = in_array((string) $settings['email_transport'], ['php_mail', 'smtp', 'http_api'], true) ? (string) $settings['email_transport'] : 'php_mail';
    $settings['email_smtp_port'] = max(1, min(65535, (int) $settings['email_smtp_port']));
    $settings['email_smtp_encryption'] = in_array((string) $settings['email_smtp_encryption'], ['none', 'tls', 'ssl'], true) ? (string) $settings['email_smtp_encryption'] : 'tls';
    $settings['email_timeout_seconds'] = max(3, min(30, (int) $settings['email_timeout_seconds']));
    $settings['external_push_enabled'] = (bool) $settings['external_push_enabled'];
    $settings['slack_webhook_enabled'] = (bool) $settings['slack_webhook_enabled'];
    $settings['slack_webhook_url'] = sr_notification_clean_setting_value((string) $settings['slack_webhook_url'], 255);
    $settings['slack_channel_label'] = sr_notification_clean_setting_value((string) $settings['slack_channel_label'], 80);
    $settings['discord_webhook_enabled'] = (bool) $settings['discord_webhook_enabled'];
    $settings['discord_webhook_url'] = sr_notification_clean_setting_value((string) $settings['discord_webhook_url'], 255);
    $settings['discord_channel_label'] = sr_notification_clean_setting_value((string) $settings['discord_channel_label'], 80);
    $settings['telegram_bot_enabled'] = (bool) $settings['telegram_bot_enabled'];
    $settings['telegram_bot_token'] = sr_notification_clean_setting_value((string) $settings['telegram_bot_token'], 255);
    $settings['telegram_chat_id'] = sr_notification_clean_setting_value((string) $settings['telegram_chat_id'], 120);
    $settings['telegram_channel_label'] = sr_notification_clean_setting_value((string) $settings['telegram_channel_label'], 80);
    $settings['external_push_failure_policy'] = sr_notification_external_failure_policy((string) $settings['external_push_failure_policy']);
    $settings['delivery_web_runner_enabled'] = (bool) $settings['delivery_web_runner_enabled'];
    $settings['delivery_web_runner_interval_seconds'] = max(10, min(3600, (int) $settings['delivery_web_runner_interval_seconds']));
    $settings['delivery_web_runner_batch_size'] = max(1, min(5, (int) $settings['delivery_web_runner_batch_size']));
    $settings['delivery_manual_batch_size'] = max(1, min(50, (int) $settings['delivery_manual_batch_size']));
    $settings['delivery_cli_batch_size'] = max(1, min(100, (int) $settings['delivery_cli_batch_size']));
    $settings['delivery_max_attempts'] = max(1, min(20, (int) $settings['delivery_max_attempts']));
    $settings['delivery_lock_timeout_seconds'] = max(30, min(3600, (int) $settings['delivery_lock_timeout_seconds']));

    return $settings;
}

function sr_notification_email_transport_options(): array
{
    return [
        'php_mail' => 'PHP mail()',
        'smtp' => 'SMTP',
        'http_api' => 'HTTP API',
    ];
}

function sr_notification_email_encryption_options(): array
{
    return [
        'none' => '사용 안 함',
        'tls' => 'STARTTLS',
        'ssl' => 'SSL/TLS',
    ];
}

function sr_notification_clean_setting_value(string $value, int $maxLength): string
{
    return sr_notification_clean_single_line($value, $maxLength);
}

function sr_notification_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'notification' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('알림 모듈이 등록되어 있지 않습니다.');
    }

    $rows = [
        ['email_channel_enabled', !empty($settings['email_channel_enabled']) ? '1' : '0', 'bool'],
        ['email_transport', (string) $settings['email_transport'], 'string'],
        ['email_from_email', (string) $settings['email_from_email'], 'string'],
        ['email_from_name', (string) $settings['email_from_name'], 'string'],
        ['email_smtp_host', (string) $settings['email_smtp_host'], 'string'],
        ['email_smtp_port', (string) $settings['email_smtp_port'], 'int'],
        ['email_smtp_encryption', (string) $settings['email_smtp_encryption'], 'string'],
        ['email_smtp_username', (string) $settings['email_smtp_username'], 'string'],
        ['email_smtp_password', (string) $settings['email_smtp_password'], 'string'],
        ['email_timeout_seconds', (string) $settings['email_timeout_seconds'], 'int'],
        ['email_http_api_endpoint', (string) $settings['email_http_api_endpoint'], 'string'],
        ['email_http_api_bearer_token', (string) $settings['email_http_api_bearer_token'], 'string'],
        ['external_push_enabled', !empty($settings['external_push_enabled']) ? '1' : '0', 'bool'],
        ['slack_webhook_enabled', !empty($settings['slack_webhook_enabled']) ? '1' : '0', 'bool'],
        ['slack_webhook_url', (string) $settings['slack_webhook_url'], 'string'],
        ['slack_channel_label', (string) $settings['slack_channel_label'], 'string'],
        ['discord_webhook_enabled', !empty($settings['discord_webhook_enabled']) ? '1' : '0', 'bool'],
        ['discord_webhook_url', (string) $settings['discord_webhook_url'], 'string'],
        ['discord_channel_label', (string) $settings['discord_channel_label'], 'string'],
        ['telegram_bot_enabled', !empty($settings['telegram_bot_enabled']) ? '1' : '0', 'bool'],
        ['telegram_bot_token', (string) $settings['telegram_bot_token'], 'string'],
        ['telegram_chat_id', (string) $settings['telegram_chat_id'], 'string'],
        ['telegram_channel_label', (string) $settings['telegram_channel_label'], 'string'],
        ['external_push_failure_policy', (string) $settings['external_push_failure_policy'], 'string'],
        ['delivery_web_runner_enabled', !empty($settings['delivery_web_runner_enabled']) ? '1' : '0', 'bool'],
        ['delivery_web_runner_interval_seconds', (string) $settings['delivery_web_runner_interval_seconds'], 'int'],
        ['delivery_web_runner_batch_size', (string) $settings['delivery_web_runner_batch_size'], 'int'],
        ['delivery_manual_batch_size', (string) $settings['delivery_manual_batch_size'], 'int'],
        ['delivery_cli_batch_size', (string) $settings['delivery_cli_batch_size'], 'int'],
        ['delivery_max_attempts', (string) $settings['delivery_max_attempts'], 'int'],
        ['delivery_lock_timeout_seconds', (string) $settings['delivery_lock_timeout_seconds'], 'int'],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO sr_module_settings
            (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    $now = sr_now();
    foreach ($rows as $row) {
        $stmt->execute([
            'module_id' => (int) $module['id'],
            'setting_key' => $row[0],
            'setting_value' => $row[1],
            'value_type' => $row[2],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    sr_clear_module_settings_cache('notification');
}

function sr_notification_create_channels(PDO $pdo): array
{
    $settings = sr_notification_settings($pdo);
    $channels = ['site'];
    if (!empty($settings['email_channel_enabled'])) {
        $channels[] = 'email';
    }

    return $channels;
}

function sr_notification_member_external_channels(PDO $pdo, int $accountId): array
{
    if ($accountId <= 0) {
        return [];
    }

    $settings = sr_notification_settings($pdo);
    $channels = [];
    foreach (sr_notification_member_external_channel_keys() as $channel) {
        if (sr_notification_member_external_provider_is_ready($channel, $settings)
            && sr_notification_member_push_endpoints($pdo, $accountId, $channel) !== []
        ) {
            $channels[] = $channel;
        }
    }

    return $channels;
}

function sr_notification_account_event_channels(PDO $pdo, int $accountId, array $channels): array
{
    $channels = sr_notification_normalize_channels($channels);
    if ($channels === []) {
        return ['site'];
    }

    $needsMemberExternalLookup = false;
    foreach ($channels as $channel) {
        if (in_array($channel, sr_notification_member_external_channel_keys(), true)) {
            $needsMemberExternalLookup = true;
            break;
        }
    }
    $memberExternalChannels = $needsMemberExternalLookup ? sr_notification_member_external_channels($pdo, $accountId) : [];
    $filtered = [];
    foreach ($channels as $channel) {
        if (in_array($channel, sr_notification_member_external_channel_keys(), true)) {
            if (in_array($channel, $memberExternalChannels, true)) {
                $filtered[] = $channel;
            }
            continue;
        }
        if (in_array($channel, sr_notification_admin_external_channel_keys(), true)) {
            continue;
        }
        $filtered[] = $channel;
    }

    return $filtered === [] ? ['site'] : array_values(array_unique($filtered));
}

function sr_notification_admin_external_channels(PDO $pdo): array
{
    $settings = sr_notification_settings($pdo);
    if (empty($settings['external_push_enabled'])) {
        return [];
    }

    $channels = [];
    foreach (sr_notification_admin_external_channel_keys() as $channel) {
        if (sr_notification_external_provider_is_ready($channel, $settings)) {
            $channels[] = $channel;
        }
    }

    return $channels;
}

function sr_notification_queue_admin_external_deliveries(PDO $pdo, int $adminNotificationId, array $channels = []): int
{
    if ($adminNotificationId <= 0) {
        return 0;
    }

    $settings = sr_notification_settings($pdo);
    $channels = $channels === [] ? sr_notification_admin_external_channels($pdo) : sr_notification_normalize_channels($channels);
    $channels = array_values(array_filter(
        $channels,
        static fn (string $channel): bool => in_array($channel, sr_notification_admin_external_channel_keys(), true)
            && sr_notification_external_provider_is_ready($channel, $settings)
    ));
    if ($channels === []) {
        return 0;
    }
    $providerOptions = sr_notification_external_provider_options();
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_notification_deliveries
            (notification_id, channel, recipient, status, provider_message_id, error_message, attempted_at, created_at, updated_at)
         VALUES
            (:notification_id, :channel, :recipient, \'queued\', \'\', \'\', NULL, :created_at, :updated_at)'
    );
    $queued = 0;
    foreach ($channels as $channel) {
        $labelSetting = (string) ($providerOptions[$channel]['channel_label_setting'] ?? '');
        $recipient = sr_notification_clean_single_line($labelSetting !== '' ? (string) ($settings[$labelSetting] ?? '') : '', 80);
        if ($recipient === '' || sr_notification_delivery_endpoint_id($recipient) > 0) {
            $recipient = $channel;
        }
        $stmt->execute([
            'notification_id' => $adminNotificationId,
            'channel' => $channel,
            'recipient' => $recipient,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $queued += $stmt->rowCount() > 0 ? 1 : 0;
    }

    return $queued;
}

function sr_notification_normalize_channels(array $channels): array
{
    $allowedChannels = sr_notification_allowed_channels();
    $normalized = [];

    foreach ($channels as $channel) {
        $channel = is_string($channel) ? $channel : '';
        if (in_array($channel, $allowedChannels, true)) {
            $normalized[$channel] = $channel;
        }
    }

    return array_values($normalized);
}

function sr_notification_external_channels(array $channels): array
{
    $externalChannels = [];

    foreach (sr_notification_normalize_channels($channels) as $channel) {
        if ($channel !== 'site') {
            $externalChannels[] = $channel;
        }
    }

    return $externalChannels;
}

function sr_notification_event_template(PDO $pdo, string $moduleKey, string $eventKey): ?array
{
    if (!sr_is_safe_module_key($moduleKey) || preg_match('/\A[a-z0-9_.-]{1,120}\z/', $eventKey) !== 1) {
        return null;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT module_key, event_key, title_template, body_template, link_template, channels_json, status
             FROM sr_notification_event_templates
             WHERE module_key = :module_key AND event_key = :event_key
             LIMIT 1"
        );
        $stmt->execute([
            'module_key' => $moduleKey,
            'event_key' => $eventKey,
        ]);
        $row = $stmt->fetch();
    } catch (Throwable) {
        return null;
    }

    return is_array($row) ? $row : null;
}

function sr_notification_render_template(string $template, array $metadata): string
{
    $values = [];
    foreach ($metadata as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $values['{' . (string) $key . '}'] = (string) $value;
        }
    }

    return strtr($template, $values);
}

function sr_notification_template_channels(?string $channelsJson): array
{
    $decoded = is_string($channelsJson) && trim($channelsJson) !== '' ? json_decode($channelsJson, true) : null;
    if (!is_array($decoded)) {
        return ['site'];
    }

    $channels = [];
    foreach ($decoded as $channel) {
        if (is_string($channel)) {
            $channels[] = $channel;
        }
    }

    $channels = sr_notification_normalize_channels($channels);
    return $channels === [] ? ['site'] : $channels;
}

function sr_notification_create_account_event(PDO $pdo, array $data): ?int
{
    $accountId = (int) ($data['account_id'] ?? 0);
    $moduleKey = (string) ($data['module_key'] ?? '');
    $eventKey = (string) ($data['event_key'] ?? '');
    if ($accountId <= 0 || !sr_is_safe_module_key($moduleKey) || preg_match('/\A[a-z0-9_.-]{1,120}\z/', $eventKey) !== 1) {
        return null;
    }

    $template = sr_notification_event_template($pdo, $moduleKey, $eventKey);
    if (!is_array($template) || (string) ($template['status'] ?? '') !== 'active') {
        return null;
    }

    $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [];
    $title = sr_notification_render_template((string) ($template['title_template'] ?? ''), $metadata);
    $bodyText = sr_notification_render_template((string) ($template['body_template'] ?? ''), $metadata);
    $linkUrl = sr_notification_render_template((string) ($template['link_template'] ?? ''), $metadata);
    $channels = isset($data['channels']) && is_array($data['channels'])
        ? sr_notification_account_event_channels($pdo, $accountId, $data['channels'])
        : sr_notification_template_channels(is_string($template['channels_json'] ?? null) ? (string) $template['channels_json'] : null);
    $channels = sr_notification_account_event_channels($pdo, $accountId, $channels);
    if ($channels === []) {
        $channels = ['site'];
    }

    return sr_notification_create($pdo, [
        'account_id' => $accountId,
        'audience' => 'account',
        'title' => $title,
        'body_text' => $bodyText,
        'link_url' => $linkUrl,
        'channels' => $channels,
        'source_module_key' => $moduleKey,
        'event_key' => $eventKey,
        'metadata' => $metadata,
        'created_by_account_id' => isset($data['created_by_account_id']) ? (int) $data['created_by_account_id'] : null,
    ]);
}

function sr_notification_admin_statuses(): array
{
    return ['active', 'deleted'];
}

function sr_notification_account_email(PDO $pdo, int $accountId): string
{
    if ($accountId <= 0) {
        return '';
    }

    $stmt = $pdo->prepare('SELECT email FROM sr_member_accounts WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $accountId]);
    $row = $stmt->fetch();
    $email = is_array($row) ? sr_normalize_identifier((string) ($row['email'] ?? '')) : '';

    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function sr_notification_all_member_email_recipients(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT email FROM sr_member_accounts WHERE status = 'active' ORDER BY id ASC");
    $recipients = [];
    foreach ($stmt->fetchAll() as $row) {
        $email = sr_normalize_identifier((string) ($row['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $recipients[$email] = $email;
        }
    }

    return array_values($recipients);
}

function sr_notification_email_recipients(PDO $pdo, string $audience, ?int $accountId): array
{
    if ($audience === 'account') {
        $email = sr_notification_account_email($pdo, (int) $accountId);
        return $email === '' ? [] : [$email];
    }

    if ($audience === 'all') {
        return sr_notification_all_member_email_recipients($pdo);
    }

    return [];
}

function sr_notification_admin_notification_query_parts(array $filters): array
{
    $where = [];
    $params = [];

    if (($filters['audience'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('n.audience', 'audience', $filters['audience']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('n.status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $field = (string) ($filters['field'] ?? 'all');
        if ($field === 'title') {
            $where[] = 'n.title LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'body') {
            $where[] = 'n.body_text LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'link') {
            $where[] = 'n.link_url LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'account') {
            $where[] = 'CAST(n.account_id AS CHAR) LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'id') {
            $where[] = 'CAST(n.id AS CHAR) LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } else {
            $where[] = '(n.title LIKE :title_keyword OR n.body_text LIKE :body_keyword OR n.link_url LIKE :link_keyword OR CAST(n.id AS CHAR) LIKE :id_keyword OR CAST(n.account_id AS CHAR) LIKE :account_keyword)';
            $params['title_keyword'] = '%' . $keyword . '%';
            $params['body_keyword'] = '%' . $keyword . '%';
            $params['link_keyword'] = '%' . $keyword . '%';
            $params['id_keyword'] = '%' . $keyword . '%';
            $params['account_keyword'] = '%' . $keyword . '%';
        }
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_notification_admin_notification_count(PDO $pdo, array $filters = []): int
{
    $queryParts = sr_notification_admin_notification_query_parts($filters);
    $sql = 'SELECT COUNT(*) AS count_value FROM sr_notifications n';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_notification_admin_notification_sort_options(): array
{
    return [
        'title' => ['columns' => ['n.title', 'n.id']],
        'audience' => ['columns' => ['n.audience', 'n.id']],
        'status' => ['columns' => ['n.status', 'n.id']],
        'created_at' => ['columns' => ['n.created_at', 'n.id']],
    ];
}

function sr_notification_admin_notification_default_sort(): array
{
    return sr_admin_sort_default('created_at', 'desc');
}

function sr_notification_admin_notifications(PDO $pdo, int $limit = 100, array $filters = [], int $offset = 0, array $sort = []): array
{
    $useLimit = $limit > 0;
    if ($useLimit) {
        $limit = max(1, min(1000, $limit));
    }
    $queryParts = sr_notification_admin_notification_query_parts($filters);
    $where = $queryParts['where'];
    $params = $queryParts['params'];
    $sql = 'SELECT n.id, n.audience, n.account_id, n.title, n.status, n.created_at
            FROM sr_notifications n';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= sr_admin_sort_order_sql(sr_notification_admin_notification_sort_options(), $sort, sr_notification_admin_notification_default_sort());
    if ($useLimit) {
        $sql .= ' LIMIT :limit_value OFFSET :offset_value';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, PDO::PARAM_STR);
    }
    if ($useLimit) {
        $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_notification_admin_status_counts(PDO $pdo, array $allowedStatuses): array
{
    $counts = ['total' => 0];
    foreach ($allowedStatuses as $status) {
        $counts[$status] = 0;
    }

    $stmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_notifications GROUP BY status');
    foreach ($stmt->fetchAll() as $row) {
        $status = (string) ($row['status'] ?? '');
        $count = (int) ($row['count_value'] ?? 0);
        if (array_key_exists($status, $counts)) {
            $counts[$status] = $count;
        }
        $counts['total'] += $count;
    }

    return $counts;
}

function sr_notification_create(PDO $pdo, array $data): int
{
    $audience = (string) ($data['audience'] ?? 'account');
    if (!in_array($audience, ['account', 'all'], true)) {
        throw new InvalidArgumentException('Notification audience is invalid.');
    }

    $accountId = isset($data['account_id']) && (int) $data['account_id'] > 0 ? (int) $data['account_id'] : null;
    if ($audience === 'account' && $accountId === null) {
        throw new InvalidArgumentException('Account notification requires account_id.');
    }

    $title = sr_notification_clean_single_line((string) ($data['title'] ?? ''), 160);
    if ($title === '') {
        throw new InvalidArgumentException('Notification title is required.');
    }

    $bodyFormat = sr_notification_body_format((string) ($data['body_format'] ?? 'plain'));
    $bodyText = $bodyFormat === 'html'
        ? sr_sanitize_rich_text_html(sr_notification_clean_text((string) ($data['body_text'] ?? ''), 5000))
        : sr_notification_clean_text((string) ($data['body_text'] ?? ''), 5000);
    $rawLinkUrl = (string) ($data['link_url'] ?? '');
    $linkUrl = sr_notification_clean_link_url($rawLinkUrl);
    if (trim($rawLinkUrl) !== '' && $linkUrl === '') {
        throw new InvalidArgumentException('Notification link URL is invalid.');
    }

    $channels = isset($data['channels']) && is_array($data['channels'])
        ? sr_notification_normalize_channels($data['channels'])
        : ['site'];
    $recipient = sr_notification_clean_single_line((string) ($data['recipient'] ?? ''), 255);
    if ($channels === []) {
        throw new InvalidArgumentException('Notification requires at least one delivery channel.');
    }
    $externalChannels = sr_notification_external_channels($channels);
    $emailRecipients = in_array('email', $channels, true)
        ? sr_notification_email_recipients($pdo, $audience, $accountId)
        : [];
    if (in_array('email', $channels, true) && $emailRecipients === []) {
        throw new InvalidArgumentException('Email notification delivery requires member email recipients.');
    }
    foreach ($externalChannels as $externalChannel) {
        if (in_array($externalChannel, sr_notification_admin_external_channel_keys(), true)) {
            if (!in_array($externalChannel, sr_notification_member_external_channel_keys(), true)
                || $audience !== 'account'
                || $accountId === null
                || sr_notification_member_push_endpoints($pdo, $accountId, $externalChannel) === []
            ) {
                throw new InvalidArgumentException('Member external push delivery requires an active member endpoint.');
            }
            continue;
        }
        if ($externalChannel !== 'email' && $recipient === '') {
            throw new InvalidArgumentException('External notification delivery requires recipient.');
        }
    }

    $createdByAccountId = isset($data['created_by_account_id']) && (int) $data['created_by_account_id'] > 0
        ? (int) $data['created_by_account_id']
        : null;

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $now = sr_now();
        $columns = ['account_id', 'audience', 'title', 'body_text', 'body_format', 'link_url', 'status', 'read_at', 'created_by_account_id', 'created_at', 'updated_at'];
        $placeholders = [':account_id', ':audience', ':title', ':body_text', ':body_format', ':link_url', ':status', 'NULL', ':created_by_account_id', ':created_at', ':updated_at'];
        $params = [
            'account_id' => $accountId,
            'audience' => $audience,
            'title' => $title,
            'body_text' => $bodyText,
            'body_format' => $bodyFormat,
            'link_url' => $linkUrl,
            'status' => 'active',
            'created_by_account_id' => $createdByAccountId,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if (sr_notification_event_columns_available($pdo)) {
            $columns[] = 'source_module_key';
            $columns[] = 'event_key';
            $columns[] = 'metadata_json';
            $placeholders[] = ':source_module_key';
            $placeholders[] = ':event_key';
            $placeholders[] = ':metadata_json';
            $params['source_module_key'] = (string) ($data['source_module_key'] ?? '');
            $params['event_key'] = (string) ($data['event_key'] ?? '');
            $params['metadata_json'] = is_string($data['metadata_json'] ?? null)
                ? (string) $data['metadata_json']
                : (isset($data['metadata']) && is_array($data['metadata']) ? sr_notification_metadata_json($data['metadata']) : '');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO sr_notifications
                (' . implode(', ', $columns) . ')
             VALUES
                (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);

        $notificationId = (int) $pdo->lastInsertId();
        sr_notification_queue_deliveries($pdo, $notificationId, $channels, $audience, $accountId, $recipient);

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return $notificationId;
}

function sr_notification_queue_deliveries(PDO $pdo, int $notificationId, array $channels, string $audience, ?int $accountId = null, string $recipient = ''): void
{
    $channels = sr_notification_normalize_channels($channels);
    $recipient = sr_notification_clean_single_line($recipient, 255);
    if ($channels === []) {
        throw new InvalidArgumentException('Notification requires at least one delivery channel.');
    }
    $emailRecipients = in_array('email', $channels, true)
        ? sr_notification_email_recipients($pdo, $audience, $accountId)
        : [];
    if (in_array('email', $channels, true) && $emailRecipients === []) {
        throw new InvalidArgumentException('Email notification delivery requires member email recipients.');
    }
    foreach (sr_notification_external_channels($channels) as $externalChannel) {
        if (in_array($externalChannel, sr_notification_admin_external_channel_keys(), true)) {
            if (!in_array($externalChannel, sr_notification_member_external_channel_keys(), true)
                || $audience !== 'account'
                || $accountId === null
                || sr_notification_member_push_endpoints($pdo, $accountId, $externalChannel) === []
            ) {
                throw new InvalidArgumentException('Member external push delivery requires an active member endpoint.');
            }
            continue;
        }
        if ($externalChannel !== 'email' && $recipient === '') {
            throw new InvalidArgumentException('External notification delivery requires recipient.');
        }
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_notification_deliveries
            (notification_id, channel, recipient, status, provider_message_id, error_message, attempted_at, created_at, updated_at)
         VALUES
            (:notification_id, :channel, :recipient, :status, :provider_message_id, :error_message, NULL, :created_at, :updated_at)'
    );

    foreach ($channels as $channel) {
        if ($channel === 'site') {
            continue;
        }
        if ($channel === 'email') {
            $recipients = $emailRecipients;
        } elseif (in_array($channel, sr_notification_member_external_channel_keys(), true)) {
            $recipients = [];
            foreach (sr_notification_member_push_endpoints($pdo, (int) $accountId, $channel) as $endpoint) {
                $endpointId = (int) ($endpoint['id'] ?? 0);
                if ($endpointId > 0) {
                    $recipients[] = 'endpoint:' . (string) $endpointId;
                }
            }
        } else {
            $recipients = [$recipient];
        }
        foreach ($recipients as $deliveryRecipient) {
            $stmt->execute([
                'notification_id' => $notificationId,
                'channel' => $channel,
                'recipient' => $deliveryRecipient,
                'status' => 'queued',
                'provider_message_id' => '',
                'error_message' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
