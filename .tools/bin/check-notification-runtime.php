#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/version.php';
require_once $root . '/core/helpers/runtime.php';
require_once $root . '/core/helpers/settings.php';
require_once $root . '/core/helpers/delivery-templates.php';

if (!function_exists('sr_now')) {
    function sr_now(): string
    {
        return '2026-06-11 12:00:00';
    }
}

if (!function_exists('sr_e')) {
    function sr_e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('sr_body_text_html')) {
    function sr_body_text_html(array $row, string $bodyKey = 'body_text', string $formatKey = 'body_format'): string
    {
        return nl2br(sr_e((string) ($row[$bodyKey] ?? '')), false);
    }
}

if (!function_exists('sr_sanitize_rich_text_html')) {
    function sr_sanitize_rich_text_html(string $html): string
    {
        return strip_tags($html, '<p><br><strong><em><a>');
    }
}

if (!function_exists('sr_set_runtime_config')) {
    function sr_set_runtime_config(array $config): void
    {
        $GLOBALS['sr_runtime_config'] = $config;
    }
}

if (!function_exists('sr_runtime_config')) {
    function sr_runtime_config(): array
    {
        return is_array($GLOBALS['sr_runtime_config'] ?? null) ? $GLOBALS['sr_runtime_config'] : [];
    }
}

if (!function_exists('sr_app_key')) {
    function sr_app_key(array $config): string
    {
        return (string) ($config['app_key'] ?? '');
    }
}

if (!function_exists('sr_is_safe_relative_url')) {
    function sr_is_safe_relative_url(string $url): bool
    {
        return $url !== '' && str_starts_with($url, '/') && !str_starts_with($url, '//');
    }
}

if (!function_exists('sr_is_http_url')) {
    function sr_is_http_url(string $url): bool
    {
        return preg_match('#\Ahttps?://#i', $url) === 1;
    }
}

if (!function_exists('sr_url')) {
    function sr_url(string $path): string
    {
        return $path;
    }
}

if (!function_exists('sr_is_safe_module_key')) {
    function sr_is_safe_module_key(string $moduleKey): bool
    {
        return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $moduleKey) === 1;
    }
}

if (!function_exists('sr_normalize_identifier')) {
    function sr_normalize_identifier(string $value): string
    {
        return strtolower(trim($value));
    }
}

if (!function_exists('sr_module_settings')) {
    function sr_module_settings(PDO $pdo, string $moduleKey): array
    {
        global $notificationRuntimeSettings;

        $settings = [
            'email_channel_enabled' => true,
            'external_push_enabled' => true,
            'slack_webhook_enabled' => true,
            'slack_member_push_enabled' => true,
            'slack_webhook_url' => 'https://hooks.slack.com/services/T000/B000/fixture',
            'slack_channel_label' => '운영 알림',
            'discord_webhook_enabled' => true,
            'discord_member_push_enabled' => true,
            'discord_webhook_url' => 'https://discord.com/api/webhooks/fixture/token',
            'discord_channel_label' => '운영 Discord',
            'telegram_bot_enabled' => true,
            'telegram_member_push_enabled' => true,
            'telegram_bot_token' => '123456789:ABCdef_ghi-jklmnopqrstuvwxyz123456',
            'telegram_chat_id' => '@saanraan_ops',
            'telegram_channel_label' => '운영 Telegram',
            'external_push_failure_policy' => 'retry',
        ];
        if (isset($notificationRuntimeSettings) && is_array($notificationRuntimeSettings)) {
            $settings = array_merge($settings, $notificationRuntimeSettings);
        }

        return $settings;
    }
}

function sr_fixture_notification_case_key_for_event(string $eventKey): string
{
    return $eventKey === 'blocked.event' ? 'blocked_case' : '';
}

function sr_fixture_notification_case_settings_from_value(mixed $value): array
{
    return [
        'blocked_case' => [
            'event_key' => 'blocked.event',
            'enabled' => false,
            'channels' => ['site'],
        ],
    ];
}

function sr_fixture_settings(PDO $pdo): array
{
    return [
        'notification_cases' => [
            'blocked_case' => [
                'event_key' => 'blocked.event',
                'enabled' => false,
                'channels' => ['site'],
            ],
        ],
    ];
}

if (!function_exists('sr_admin_normalize_permission_action')) {
    function sr_admin_normalize_permission_action(string $value): string
    {
        return in_array($value, ['view', 'edit', 'delete'], true) ? $value : 'view';
    }
}

if (!function_exists('sr_admin_normalize_permission_path')) {
    function sr_admin_normalize_permission_path(string $value): string
    {
        return str_starts_with($value, '/admin') ? $value : '';
    }
}

require_once $root . '/modules/notification/helpers.php';
sr_set_runtime_config(['app_key' => str_repeat('n', 32)]);

$errors = [];

function sr_notification_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_notification_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_notification_runtime_error($message);
    }
}

function sr_notification_runtime_pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec(
        'CREATE TABLE sr_modules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_key TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT \'enabled\'
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_module_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_id INTEGER NOT NULL,
            setting_key TEXT NOT NULL,
            setting_value TEXT NOT NULL,
            value_type TEXT NOT NULL DEFAULT \'string\'
        )'
    );
    $pdo->exec("INSERT INTO sr_modules (id, module_key, status) VALUES (1, 'notification', 'enabled')");
    $pdo->exec(
        "INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type) VALUES
            (1, 'external_push_enabled', '1', 'bool'),
            (1, 'slack_webhook_enabled', '1', 'bool'),
            (1, 'slack_member_push_enabled', '1', 'bool'),
            (1, 'slack_webhook_url', 'https://hooks.slack.com/services/T000/B000/fixture', 'string'),
            (1, 'discord_webhook_enabled', '1', 'bool'),
            (1, 'discord_member_push_enabled', '1', 'bool'),
            (1, 'discord_webhook_url', 'https://discord.com/api/webhooks/fixture/token', 'string'),
            (1, 'discord_channel_label', '운영 Discord', 'string'),
            (1, 'telegram_bot_enabled', '1', 'bool'),
            (1, 'telegram_member_push_enabled', '1', 'bool'),
            (1, 'telegram_bot_token', '123456789:ABCdef_ghi-jklmnopqrstuvwxyz123456', 'string'),
            (1, 'telegram_chat_id', '@saanraan_ops', 'string'),
            (1, 'telegram_channel_label', '운영 Telegram', 'string')"
    );
    $pdo->exec(
        'CREATE TABLE sr_member_accounts (
            id INTEGER PRIMARY KEY,
            email TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT \'active\'
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NULL,
            audience TEXT NOT NULL DEFAULT \'account\',
            title TEXT NOT NULL,
            body_text TEXT NULL,
            body_format TEXT NOT NULL DEFAULT \'plain\',
            link_url TEXT NOT NULL DEFAULT \'\',
            source_module_key TEXT NOT NULL DEFAULT \'\',
            event_key TEXT NOT NULL DEFAULT \'\',
            metadata_json TEXT NULL,
            status TEXT NOT NULL DEFAULT \'active\',
            read_at TEXT NULL,
            created_by_account_id INTEGER NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_notification_deliveries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            notification_id INTEGER NOT NULL,
            channel TEXT NOT NULL,
            recipient TEXT NOT NULL DEFAULT \'\',
            status TEXT NOT NULL DEFAULT \'queued\',
            provider_message_id TEXT NOT NULL DEFAULT \'\',
            error_message TEXT NOT NULL DEFAULT \'\',
            attempted_at TEXT NULL,
            locked_at TEXT NULL,
            locked_by TEXT NOT NULL DEFAULT \'\',
            attempt_count INTEGER NOT NULL DEFAULT 0,
            next_attempt_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_notification_push_endpoints (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            provider_key TEXT NOT NULL,
            recipient_type TEXT NOT NULL DEFAULT \'personal\',
            endpoint_ciphertext TEXT NOT NULL,
            endpoint_fingerprint TEXT NOT NULL,
            recipient_label TEXT NOT NULL DEFAULT \'\',
            recipient_masked TEXT NOT NULL DEFAULT \'\',
            status TEXT NOT NULL DEFAULT \'active\',
            key_version TEXT NOT NULL DEFAULT \'v1\',
            verified_at TEXT NULL,
            disabled_at TEXT NULL,
            last_used_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE(provider_key, endpoint_fingerprint)
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_notification_reads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            notification_id INTEGER NOT NULL,
            account_id INTEGER NOT NULL,
            read_at TEXT NOT NULL,
            UNIQUE(notification_id, account_id)
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_notification_event_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_key TEXT NOT NULL,
            event_key TEXT NOT NULL,
            title_template TEXT NOT NULL,
            body_template TEXT NULL,
            link_template TEXT NOT NULL DEFAULT \'\',
            channels_json TEXT NULL,
            status TEXT NOT NULL DEFAULT \'active\',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE(module_key, event_key)
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_delivery_template_overrides (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            template_key TEXT NOT NULL,
            owner_module TEXT NOT NULL,
            category TEXT NOT NULL,
            subject_template TEXT NOT NULL DEFAULT \'\',
            body_template TEXT NULL,
            link_template TEXT NOT NULL DEFAULT \'\',
            channels_json TEXT NULL,
            status TEXT NOT NULL DEFAULT \'active\',
            updated_by_account_id INTEGER NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE(template_key)
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_admin_notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            body_text TEXT NULL,
            severity TEXT NOT NULL DEFAULT \'info\',
            source_module_key TEXT NOT NULL DEFAULT \'\',
            event_key TEXT NOT NULL DEFAULT \'\',
            target_type TEXT NOT NULL DEFAULT \'\',
            target_id TEXT NOT NULL DEFAULT \'\',
            action_url TEXT NOT NULL DEFAULT \'\',
            permission_path TEXT NOT NULL DEFAULT \'\',
            permission_action TEXT NOT NULL DEFAULT \'view\',
            status TEXT NOT NULL DEFAULT \'open\',
            dedupe_key TEXT NOT NULL DEFAULT \'\',
            occurrence_count INTEGER NOT NULL DEFAULT 1,
            created_by_account_id INTEGER NULL,
            processed_by_account_id INTEGER NULL,
            processed_at TEXT NULL,
            archived_at TEXT NULL,
            last_occurred_at TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE(dedupe_key)
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_admin_notification_reads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            notification_id INTEGER NOT NULL,
            account_id INTEGER NOT NULL,
            read_at TEXT NULL,
            acknowledged_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            UNIQUE(notification_id, account_id)
        )'
    );
    $pdo->exec(
        "INSERT INTO sr_member_accounts (id, email, status) VALUES
            (7, 'member7@example.test', 'active'),
            (8, 'member8@example.test', 'active')"
    );
    $pdo->exec(
        "INSERT INTO sr_notification_event_templates
            (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
         VALUES
            ('community', 'comment.mention', '댓글에서 {member_name}님이 언급했습니다.', '본문: {comment_excerpt}', '/community/post?id={post_id}', '[\"site\",\"email\"]', 'active', '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
            ('community', 'disabled.event', '비활성 알림', '비활성', '/disabled', '[\"site\"]', 'disabled', '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
            ('fixture', 'blocked.event', '설정 비활성 알림', '설정 비활성', '/blocked', '[\"site\"]', 'active', '2026-06-11 00:00:00', '2026-06-11 00:00:00')"
    );

    return $pdo;
}

function sr_notification_runtime_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function sr_notification_runtime_file(string $path): string
{
    global $root;

    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content)) {
        sr_notification_runtime_error('notification runtime fixture cannot read file: ' . $path);
        return '';
    }

    return $content;
}

$pdo = sr_notification_runtime_pdo();

$notificationId = sr_notification_create_account_event($pdo, [
    'account_id' => 7,
    'module_key' => 'community',
    'event_key' => 'comment.mention',
    'metadata' => [
        'member_name' => '홍길동',
        'comment_excerpt' => '테스트 댓글',
        'post_id' => '42',
    ],
    'created_by_account_id' => 8,
]);
sr_notification_runtime_assert(is_int($notificationId) && $notificationId > 0, 'notification runtime fixture must create account event notification.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT title FROM sr_notifications WHERE id = :id', ['id' => $notificationId]) === '댓글에서 홍길동님이 언급했습니다.', 'notification runtime fixture must render title template metadata.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT source_module_key FROM sr_notifications WHERE id = :id', ['id' => $notificationId]) === 'community', 'notification runtime fixture must store account event module key.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT event_key FROM sr_notifications WHERE id = :id', ['id' => $notificationId]) === 'comment.mention', 'notification runtime fixture must store account event key.');
sr_notification_runtime_assert(str_contains((string) sr_notification_runtime_scalar($pdo, 'SELECT metadata_json FROM sr_notifications WHERE id = :id', ['id' => $notificationId]), '홍길동'), 'notification runtime fixture must store account event metadata for generated titles.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT body_text FROM sr_notifications WHERE id = :id', ['id' => $notificationId]) === '본문: 테스트 댓글', 'notification runtime fixture must render body template metadata.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT link_url FROM sr_notifications WHERE id = :id', ['id' => $notificationId]) === '/community/post?id=42', 'notification runtime fixture must render link template metadata.');
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_notification_deliveries WHERE notification_id = :id AND channel = \'email\' AND recipient = \'member7@example.test\' AND status = \'queued\'', ['id' => $notificationId]) === 1, 'notification runtime fixture must queue email delivery for account event.');

$pdo->exec("INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type) VALUES (1, 'email_channel_enabled', '0', 'bool')");
$GLOBALS['sr_module_settings_cache_token_notification'] = (int) ($GLOBALS['sr_module_settings_cache_token_notification'] ?? 0) + 1;
$emailDisabledNotificationId = sr_notification_create_account_event($pdo, [
    'account_id' => 77,
    'module_key' => 'community',
    'event_key' => 'comment.mention',
    'metadata' => [
        'member_name' => '홍길동',
        'comment_excerpt' => '이메일 비활성 댓글',
        'post_id' => '43',
    ],
]);
sr_notification_runtime_assert(is_int($emailDisabledNotificationId) && $emailDisabledNotificationId > 0, 'notification runtime fixture must still create notification row when email channel is disabled.');
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_notification_deliveries WHERE notification_id = :id AND channel = \'email\'', ['id' => $emailDisabledNotificationId]) === 0, 'notification runtime fixture must not queue email delivery when email channel is disabled.');
$pdo->exec("UPDATE sr_module_settings SET setting_value = '1' WHERE module_id = 1 AND setting_key = 'email_channel_enabled'");
$GLOBALS['sr_module_settings_cache_token_notification'] = (int) ($GLOBALS['sr_module_settings_cache_token_notification'] ?? 0) + 1;

$pdo->exec("UPDATE sr_notification_event_templates SET title_template = '수정된 제목: {member_name}' WHERE module_key = 'community' AND event_key = 'comment.mention'");
$storedEventNotification = $pdo->query('SELECT * FROM sr_notifications WHERE id = ' . (string) $notificationId)->fetch();
sr_notification_runtime_assert(is_array($storedEventNotification) && sr_notification_title_from_row($pdo, $storedEventNotification) === '댓글에서 홍길동님이 언급했습니다.', 'notification runtime fixture must keep account event title snapshots after template changes.');
$pdo->exec("UPDATE sr_notification_event_templates SET title_template = '댓글에서 {member_name}님이 언급했습니다.' WHERE module_key = 'community' AND event_key = 'comment.mention'");

$summary = sr_notification_public_header_summary($pdo, 7, 5);
sr_notification_runtime_assert((int) ($summary['unread'] ?? 0) === 1, 'notification runtime fixture must count unread account notifications.');
sr_notification_runtime_assert(count((array) ($summary['items'] ?? [])) === 1, 'notification runtime fixture must return unread account notification item.');
sr_notification_runtime_assert(sr_notification_mark_read($pdo, (int) $notificationId, 7), 'notification runtime fixture must mark account notification read.');
$summary = sr_notification_public_header_summary($pdo, 7, 5);
sr_notification_runtime_assert((int) ($summary['unread'] ?? 0) === 0, 'notification runtime fixture must remove read account notification from unread summary.');

$allNotificationId = sr_notification_create($pdo, [
    'audience' => 'all',
    'title' => '전체 알림',
    'body_text' => '전체 대상',
    'link_url' => '/notice',
    'channels' => ['site'],
]);
sr_notification_runtime_assert(sr_notification_mark_read($pdo, $allNotificationId, 7), 'notification runtime fixture must mark all-audience notification read for one account.');
sr_notification_runtime_assert(sr_notification_mark_read($pdo, $allNotificationId, 7), 'notification runtime fixture must allow idempotent all-audience read marking.');
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_notification_reads WHERE notification_id = :id AND account_id = 7', ['id' => $allNotificationId]) === 1, 'notification runtime fixture must keep one all-audience read row per account.');
$summary7 = sr_notification_public_header_summary($pdo, 7, 5);
$summary8 = sr_notification_public_header_summary($pdo, 8, 5);
sr_notification_runtime_assert((int) ($summary7['unread'] ?? 0) === 0, 'notification runtime fixture must hide read all-audience notification for the reader.');
sr_notification_runtime_assert((int) ($summary8['unread'] ?? 0) === 1, 'notification runtime fixture must keep all-audience notification unread for other accounts.');

$sameLinkNotificationId = sr_notification_create($pdo, [
    'account_id' => 7,
    'audience' => 'account',
    'title' => '쿠폰 이용권 알림 1',
    'body_text' => '쿠폰이 사용되었습니다.',
    'link_url' => '/account/coupons',
    'channels' => ['site'],
]);
$sameLinkSecondNotificationId = sr_notification_create($pdo, [
    'account_id' => 7,
    'audience' => 'account',
    'title' => '쿠폰 이용권 알림 2',
    'body_text' => '쿠폰 상태가 변경되었습니다.',
    'link_url' => '/account/coupons',
    'channels' => ['site'],
]);
$sameLinkAllNotificationId = sr_notification_create($pdo, [
    'audience' => 'all',
    'title' => '전체 쿠폰 안내',
    'body_text' => '쿠폰 정책이 변경되었습니다.',
    'link_url' => '/account/coupons',
    'channels' => ['site'],
]);
$differentLinkNotificationId = sr_notification_create($pdo, [
    'account_id' => 7,
    'audience' => 'account',
    'title' => '다른 목적지 알림',
    'body_text' => '포인트가 변경되었습니다.',
    'link_url' => '/account/points',
    'channels' => ['site'],
]);
sr_notification_runtime_assert(sr_notification_mark_read($pdo, $sameLinkNotificationId, 7), 'notification runtime fixture must mark a same-link notification read.');
$sameLinkReadAt = (string) sr_notification_runtime_scalar($pdo, 'SELECT read_at FROM sr_notifications WHERE id = :id', ['id' => $sameLinkNotificationId]);
$sameLinkSecondReadAt = (string) sr_notification_runtime_scalar($pdo, 'SELECT read_at FROM sr_notifications WHERE id = :id', ['id' => $sameLinkSecondNotificationId]);
sr_notification_runtime_assert($sameLinkReadAt !== '', 'notification runtime fixture must mark the selected same-link notification read.');
sr_notification_runtime_assert($sameLinkSecondReadAt === $sameLinkReadAt, 'notification runtime fixture must mark unread account notifications with the same link read together.');
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_notification_reads WHERE notification_id = :id AND account_id = 7', ['id' => $sameLinkAllNotificationId]) === 1, 'notification runtime fixture must mark unread all-audience notifications with the same link read for the reader.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT COALESCE(read_at, \'\') FROM sr_notifications WHERE id = :id', ['id' => $differentLinkNotificationId]) === '', 'notification runtime fixture must leave notifications with different links unread.');

$readAction = sr_notification_runtime_file('modules/notification/actions/account-notification-read.php');
if ($readAction !== '') {
    sr_notification_runtime_assert(
        str_contains($readAction, "sr_get_string_without_truncation('token', 32) ?? ''"),
        'notification read action must reject overlong read tokens instead of truncating them.'
    );
    sr_notification_runtime_assert(
        !str_contains($readAction, "sr_get_string('token'"),
        'notification read action must not use truncating token lookup.'
    );
}

$invalidLinkRejected = false;
try {
    sr_notification_create($pdo, [
        'account_id' => 7,
        'audience' => 'account',
        'title' => '잘못된 링크',
        'link_url' => 'javascript:alert(1)',
        'channels' => ['site'],
    ]);
} catch (InvalidArgumentException) {
    $invalidLinkRejected = true;
}
sr_notification_runtime_assert($invalidLinkRejected, 'notification runtime fixture must reject unsafe notification links.');

$disabled = sr_notification_create_account_event($pdo, [
    'account_id' => 7,
    'module_key' => 'community',
    'event_key' => 'disabled.event',
]);
sr_notification_runtime_assert($disabled === null, 'notification runtime fixture must not create disabled event template notifications.');
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, "SELECT COUNT(*) FROM sr_notifications WHERE source_module_key = 'community' AND event_key = 'disabled.event'") === 0, 'disabled event template notifications must not leave notification rows.');

$settingsBlocked = sr_notification_create_account_event($pdo, [
    'account_id' => 7,
    'module_key' => 'fixture',
    'event_key' => 'blocked.event',
]);
sr_notification_runtime_assert($settingsBlocked === null, 'notification runtime fixture must not create module-disabled notification case events.');
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, "SELECT COUNT(*) FROM sr_notifications WHERE source_module_key = 'fixture' AND event_key = 'blocked.event'") === 0, 'module-disabled notification cases must not leave notification rows.');

$unknownChannelRejected = false;
try {
    sr_notification_create($pdo, [
        'account_id' => 7,
        'audience' => 'account',
        'title' => '채널 없음',
        'channels' => ['unknown'],
    ]);
} catch (InvalidArgumentException) {
    $unknownChannelRejected = true;
}
sr_notification_runtime_assert($unknownChannelRejected, 'notification runtime fixture must reject unknown delivery channels.');

sr_notification_runtime_assert(in_array('slack_webhook', sr_notification_allowed_channels(), true), 'notification runtime fixture must expose slack_webhook as an allowed delivery channel.');
sr_notification_runtime_assert(in_array('discord_webhook', sr_notification_allowed_channels(), true), 'notification runtime fixture must expose discord_webhook as an allowed delivery channel.');
sr_notification_runtime_assert(in_array('telegram_bot', sr_notification_allowed_channels(), true), 'notification runtime fixture must expose telegram_bot as an allowed delivery channel.');
sr_notification_runtime_assert(!in_array('slack_webhook', sr_notification_create_channels($pdo), true), 'notification runtime fixture must keep external push out of generic notification create channels.');
sr_notification_runtime_assert(!in_array('discord_webhook', sr_notification_create_channels($pdo), true), 'notification runtime fixture must keep Discord external push out of generic notification create channels.');
sr_notification_runtime_assert(!in_array('telegram_bot', sr_notification_create_channels($pdo), true), 'notification runtime fixture must keep Telegram external push out of member notification create channels.');
$memberSlackRejected = false;
try {
    sr_notification_create($pdo, [
        'account_id' => 7,
        'audience' => 'account',
        'title' => '회원 Slack endpoint 없음',
        'channels' => ['slack_webhook'],
        'recipient' => '운영 알림',
    ]);
} catch (InvalidArgumentException) {
    $memberSlackRejected = true;
}
sr_notification_runtime_assert($memberSlackRejected, 'notification runtime fixture must reject slack_webhook member delivery without an active personal endpoint.');
foreach (['discord_webhook', 'telegram_bot'] as $memberExternalChannel) {
    $memberExternalRejected = false;
    try {
        sr_notification_create($pdo, [
            'account_id' => 7,
            'audience' => 'account',
            'title' => '회원 외부 푸시 차단',
            'channels' => [$memberExternalChannel],
            'recipient' => '운영 알림',
        ]);
    } catch (InvalidArgumentException) {
        $memberExternalRejected = true;
    }
    sr_notification_runtime_assert($memberExternalRejected, 'notification runtime fixture must reject admin external push for member notifications: ' . $memberExternalChannel);
}
sr_notification_runtime_assert(sr_notification_webhook_url_is_allowed('https://hooks.slack.com/services/T000/B000/fixture'), 'notification runtime fixture must allow HTTPS Slack webhook URLs.');
sr_notification_runtime_assert(!sr_notification_webhook_url_is_allowed('http://hooks.slack.com/services/T000/B000/fixture'), 'notification runtime fixture must reject non-HTTPS webhook URLs.');
sr_notification_runtime_assert(sr_notification_member_push_endpoint_is_allowed('slack_webhook', 'https://hooks.slack.com/services/T000/B000/member'), 'notification runtime fixture must allow member Slack webhook endpoint URLs.');
sr_notification_runtime_assert(sr_notification_member_push_endpoint_is_allowed('discord_webhook', 'https://discord.com/api/webhooks/member/token'), 'notification runtime fixture must allow member Discord webhook endpoint URLs.');
sr_notification_runtime_assert(!sr_notification_member_push_endpoint_is_allowed('slack_webhook', 'http://hooks.slack.com/services/T000/B000/member'), 'notification runtime fixture must reject non-HTTPS member Slack webhook endpoint URLs.');
sr_notification_runtime_assert(sr_notification_telegram_bot_token_is_allowed('123456789:ABCdef_ghi-jklmnopqrstuvwxyz123456'), 'notification runtime fixture must allow Telegram bot token format.');
sr_notification_runtime_assert(!sr_notification_telegram_bot_token_is_allowed('telegram_fixture'), 'notification runtime fixture must reject malformed Telegram bot tokens.');
sr_notification_runtime_assert(!sr_notification_telegram_bot_token_is_allowed('123456789:' . str_repeat('A', 206)), 'notification runtime fixture must reject Telegram bot tokens that would exceed endpoint length limits.');
sr_notification_runtime_assert(sr_notification_telegram_chat_id_is_allowed('@saanraan_ops'), 'notification runtime fixture must allow Telegram channel chat IDs.');
sr_notification_runtime_assert(sr_notification_telegram_chat_id_is_allowed('-1001234567890'), 'notification runtime fixture must allow numeric Telegram group chat IDs.');
sr_notification_runtime_assert(!sr_notification_telegram_chat_id_is_allowed('-'), 'notification runtime fixture must reject malformed Telegram chat IDs.');
sr_notification_runtime_assert(sr_notification_secret_display('https://hooks.slack.com/services/T000/B000/fixture') === '********', 'notification runtime fixture must mask stored webhook URLs.');
sr_notification_runtime_assert(sr_notification_secret_crypto_available(), 'notification runtime fixture must allow member push endpoint encryption when app_key is configured.');
$memberProviderSettings = sr_notification_settings($pdo);
$memberProviderSettings['slack_webhook_enabled'] = false;
$memberProviderSettings['slack_member_push_enabled'] = true;
sr_notification_runtime_assert(sr_notification_member_external_provider_is_ready('slack_webhook', $memberProviderSettings), 'notification runtime fixture must allow member Slack endpoints independently from admin Slack delivery.');
$memberProviderSettings['slack_member_push_enabled'] = false;
sr_notification_runtime_assert(!sr_notification_member_external_provider_is_ready('slack_webhook', $memberProviderSettings), 'notification runtime fixture must hide member Slack endpoints when member Slack is disabled.');
$memberProviderSettings['telegram_bot_enabled'] = false;
$memberProviderSettings['telegram_member_push_enabled'] = true;
sr_notification_runtime_assert(sr_notification_member_external_provider_is_ready('telegram_bot', $memberProviderSettings), 'notification runtime fixture must allow member Telegram endpoints independently from admin Telegram delivery.');
$notificationSecretPurpose = 'notification-push-endpoint|telegram_bot';
$notificationCiphertext = sr_notification_secret_encrypt('123456789', $notificationSecretPurpose);
sr_notification_runtime_assert(str_starts_with($notificationCiphertext, 'sr2:sodium:') || str_starts_with($notificationCiphertext, 'sr2:openssl:'), 'notification runtime fixture must use the app-key-bound sr2 secret envelope.');
sr_notification_runtime_assert(sr_notification_secret_decrypt($notificationCiphertext, $notificationSecretPurpose) === '123456789', 'notification runtime fixture must decrypt sr2 secrets with the configured app_key.');
$notificationFingerprint = sr_notification_secret_fingerprint('123456789', $notificationSecretPurpose);
sr_set_runtime_config(['app_key' => str_repeat('m', 32)]);
sr_notification_runtime_assert(sr_notification_secret_decrypt($notificationCiphertext, $notificationSecretPurpose) === null, 'notification runtime fixture must not decrypt secrets after app_key changes.');
sr_notification_runtime_assert(sr_notification_secret_fingerprint('123456789', $notificationSecretPurpose) !== $notificationFingerprint, 'notification runtime fixture must bind secret fingerprints to app_key.');
sr_set_runtime_config([]);
sr_notification_runtime_assert(!sr_notification_secret_crypto_available(), 'notification runtime fixture must fail secret crypto availability without app_key.');
sr_notification_runtime_assert(sr_notification_secret_encrypt('123456789', $notificationSecretPurpose) === '', 'notification runtime fixture must fail secret encryption without app_key.');
sr_notification_runtime_assert(sr_notification_secret_fingerprint('123456789', $notificationSecretPurpose) === '', 'notification runtime fixture must not create fallback fingerprints without app_key.');
sr_set_runtime_config(['app_key' => str_repeat('n', 32)]);

$memberTelegramEndpointId = sr_notification_save_member_push_endpoint($pdo, [
    'account_id' => 7,
    'provider_key' => 'telegram_bot',
    'endpoint' => '123456789',
    'recipient_label' => '개인 Telegram',
]);
sr_notification_runtime_assert($memberTelegramEndpointId > 0, 'notification runtime fixture must save member Telegram push endpoint.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT endpoint_ciphertext FROM sr_notification_push_endpoints WHERE id = :id', ['id' => $memberTelegramEndpointId]) !== '123456789', 'notification runtime fixture must not store member push endpoint plaintext.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT recipient_masked FROM sr_notification_push_endpoints WHERE id = :id', ['id' => $memberTelegramEndpointId]) === '1234***', 'notification runtime fixture must store only masked member push endpoint label.');
sr_notification_runtime_assert(in_array('telegram_bot', sr_notification_member_external_channels($pdo, 7), true), 'notification runtime fixture must expose configured member Telegram push channel when endpoint exists.');
sr_notification_runtime_assert(sr_notification_member_push_active_count($pdo, 7, 'telegram_bot') === 1, 'notification runtime fixture must count active member push endpoints.');
sr_notification_runtime_assert(count(sr_notification_member_push_endpoint_rows($pdo, 7)) === 1, 'notification runtime fixture must list member push endpoint rows without plaintext.');
$memberSlackEndpointId = sr_notification_save_member_push_endpoint($pdo, [
    'account_id' => 7,
    'provider_key' => 'slack_webhook',
    'endpoint' => 'https://hooks.slack.com/services/T000/B000/member',
    'recipient_label' => '개인 Slack',
]);
$memberDiscordEndpointId = sr_notification_save_member_push_endpoint($pdo, [
    'account_id' => 7,
    'provider_key' => 'discord_webhook',
    'endpoint' => 'https://discord.com/api/webhooks/member/token',
    'recipient_label' => '개인 Discord',
]);
sr_notification_runtime_assert($memberSlackEndpointId > 0, 'notification runtime fixture must save member Slack push endpoint.');
sr_notification_runtime_assert($memberDiscordEndpointId > 0, 'notification runtime fixture must save member Discord push endpoint.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT endpoint_ciphertext FROM sr_notification_push_endpoints WHERE id = :id', ['id' => $memberSlackEndpointId]) !== 'https://hooks.slack.com/services/T000/B000/member', 'notification runtime fixture must not store member Slack webhook plaintext.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT recipient_masked FROM sr_notification_push_endpoints WHERE id = :id', ['id' => $memberDiscordEndpointId]) === 'https://discord.com/[masked]', 'notification runtime fixture must mask member Discord webhook endpoint.');
sr_notification_runtime_assert(in_array('slack_webhook', sr_notification_member_external_channels($pdo, 7), true), 'notification runtime fixture must expose configured member Slack push channel when endpoint exists.');
sr_notification_runtime_assert(in_array('discord_webhook', sr_notification_member_external_channels($pdo, 7), true), 'notification runtime fixture must expose configured member Discord push channel when endpoint exists.');
$pdo->exec("UPDATE sr_module_settings SET setting_value = '0' WHERE module_id = 1 AND setting_key = 'slack_member_push_enabled'");
$GLOBALS['sr_module_settings_cache_token_notification'] = (int) ($GLOBALS['sr_module_settings_cache_token_notification'] ?? 0) + 1;
$memberExternalDisabledNotificationId = sr_notification_create($pdo, [
    'account_id' => 7,
    'title' => '회원 외부채널 중지 테스트',
    'body_text' => '회원 외부채널 중지 테스트',
    'channels' => ['slack_webhook'],
]);
sr_notification_runtime_assert(is_int($memberExternalDisabledNotificationId) && $memberExternalDisabledNotificationId > 0, 'notification runtime fixture must still create notification row when selected member external channel is disabled.');
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_notification_deliveries WHERE notification_id = :id AND channel = \'slack_webhook\'', ['id' => $memberExternalDisabledNotificationId]) === 0, 'notification runtime fixture must not queue member external delivery when member external channel is disabled.');
$pdo->exec("UPDATE sr_module_settings SET setting_value = '1' WHERE module_id = 1 AND setting_key = 'slack_member_push_enabled'");
$GLOBALS['sr_module_settings_cache_token_notification'] = (int) ($GLOBALS['sr_module_settings_cache_token_notification'] ?? 0) + 1;
$pdo->prepare(
    "UPDATE sr_notification_event_templates
     SET title_template = :title_template,
         body_template = :body_template,
         link_template = :link_template,
         channels_json = :channels_json,
         status = 'active',
         updated_at = :updated_at
     WHERE module_key = 'community'
       AND event_key = 'comment.mention'"
)->execute([
    'title_template' => '댓글 받아라~ {member_name}',
    'body_template' => "{member_name}님이 회원님의 게시글에 댓글을 남겼습니다. {comment_excerpt}\n\n{link_url}",
    'link_template' => '{link_url}',
    'channels_json' => '["site","slack_webhook"]',
    'updated_at' => sr_now(),
]);
$overrideNotificationId = sr_notification_create_account_event($pdo, [
    'account_id' => 7,
    'module_key' => 'community',
    'event_key' => 'comment.mention',
    'metadata' => [
        'member_name' => '홍길동',
        'comment_excerpt' => 'ㅋㅋㅋㅋ',
        'post_id' => '40040',
        'link_url' => '/community/post?id=40040',
    ],
]);
sr_notification_runtime_assert(is_int($overrideNotificationId) && $overrideNotificationId > 0, 'notification runtime fixture must create account event notification from module event template.');
$overrideDelivery = $pdo->query(
    'SELECT d.id, d.recipient, d.channel, n.title, n.body_text, n.body_format, n.link_url
     FROM sr_notification_deliveries d
     INNER JOIN sr_notifications n ON n.id = d.notification_id
     WHERE d.notification_id = ' . (string) (int) $overrideNotificationId . " AND d.channel = 'slack_webhook'
     LIMIT 1"
)->fetch();
sr_notification_runtime_assert(is_array($overrideDelivery), 'notification runtime fixture must queue Slack delivery from module notification event channels.');
if (is_array($overrideDelivery)) {
    $overridePayload = sr_notification_member_external_push_payload('slack_webhook', $overrideDelivery, ['site_name' => '산란', 'base_url' => 'https://dev.saanraan.test'], 'https://hooks.slack.com/services/T000/B000/member');
    sr_notification_runtime_assert(str_contains((string) ($overridePayload['text'] ?? ''), '댓글 받아라~ 홍길동'), 'notification runtime fixture must include module template title in member external push payload.');
    sr_notification_runtime_assert(str_contains((string) ($overridePayload['text'] ?? ''), '홍길동님이 회원님의 게시글에 댓글을 남겼습니다. ㅋㅋㅋㅋ'), 'notification runtime fixture must include module template body in member external push payload.');
    sr_notification_runtime_assert(str_contains((string) ($overridePayload['text'] ?? ''), 'https://dev.saanraan.test/community/post?id=40040'), 'notification runtime fixture must promote body-relative notification links to absolute URLs for external push payloads.');
    sr_notification_runtime_assert(substr_count((string) ($overridePayload['text'] ?? ''), 'community/post?id=40040') === 1, 'notification runtime fixture must not append a duplicate link when the module template body already includes it.');
    sr_notification_runtime_assert(!str_contains((string) ($overridePayload['text'] ?? ''), '사이트에서 내용을 확인해 주세요.'), 'notification runtime fixture must not replace module template body with generic member external push text.');
}
$pdo->prepare('DELETE FROM sr_notification_deliveries WHERE notification_id = :notification_id')->execute(['notification_id' => (int) $overrideNotificationId]);
$memberSlackNotificationId = sr_notification_create($pdo, [
    'account_id' => 7,
    'audience' => 'account',
    'title' => '회원 Slack 푸시 알림',
    'body_text' => 'Slack 푸시에 보낼 본문입니다.',
    'link_url' => '/account/notifications',
    'channels' => ['site', 'slack_webhook'],
]);
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_notification_deliveries WHERE notification_id = :id AND channel = \'slack_webhook\' AND recipient = :recipient', ['id' => $memberSlackNotificationId, 'recipient' => 'endpoint:' . (string) $memberSlackEndpointId]) === 1, 'notification runtime fixture must queue member Slack push delivery by endpoint reference.');
$memberDiscordPayload = sr_notification_member_external_push_payload('discord_webhook', [
    'title' => '회원 Discord 푸시 알림',
    'body_text' => 'Discord 푸시 본문',
    'link_url' => '/account/notifications',
], ['site_name' => '산란'], 'https://discord.com/api/webhooks/member/token');
sr_notification_runtime_assert(isset($memberDiscordPayload['content']) && !isset($memberDiscordPayload['text']), 'notification runtime fixture must use Discord content payload for member push.');
sr_notification_runtime_assert(str_contains((string) ($memberDiscordPayload['content'] ?? ''), 'Discord 푸시 본문'), 'notification runtime fixture must copy member notification body into Discord member push payload.');
$claimedMemberSlack = sr_notification_claim_delivery($pdo, 'fixture-member-slack-lock', '2026-06-11 12:03:00', 300, ['slack_webhook']);
sr_notification_runtime_assert(is_array($claimedMemberSlack) && (string) ($claimedMemberSlack['title'] ?? '') === '회원 Slack 푸시 알림', 'notification runtime fixture must claim member Slack push delivery with account notification title.');
$pdo->prepare("UPDATE sr_notification_push_endpoints SET status = 'disabled', disabled_at = '2026-06-11 12:03:30' WHERE id = :id")->execute(['id' => $memberSlackEndpointId]);
$memberSlackResult = sr_notification_process_delivery($pdo, ['site_name' => '산란'], $claimedMemberSlack, sr_notification_settings($pdo), '2026-06-11 12:04:00', 5);
sr_notification_runtime_assert(($memberSlackResult['skipped'] ?? 0) === 1, 'notification runtime fixture must skip queued member Slack push after endpoint is disabled.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT status FROM sr_notification_deliveries WHERE id = :id', ['id' => (int) ($claimedMemberSlack['id'] ?? 0)]) === 'canceled', 'notification runtime fixture must cancel queued member Slack push after endpoint is disabled.');
$duplicateEndpointRejected = false;
try {
    sr_notification_save_member_push_endpoint($pdo, [
        'account_id' => 8,
        'provider_key' => 'telegram_bot',
        'endpoint' => '123456789',
        'recipient_label' => '다른 계정 Telegram',
    ]);
} catch (InvalidArgumentException) {
    $duplicateEndpointRejected = true;
}
sr_notification_runtime_assert($duplicateEndpointRejected, 'notification runtime fixture must reject duplicate member push endpoints owned by another account.');
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, 'SELECT account_id FROM sr_notification_push_endpoints WHERE id = :id', ['id' => $memberTelegramEndpointId]) === 7, 'notification runtime fixture must not move endpoint ownership on duplicate connection.');

for ($endpointIndex = 0; $endpointIndex < 4; $endpointIndex++) {
    sr_notification_save_member_push_endpoint($pdo, [
        'account_id' => 7,
        'provider_key' => 'telegram_bot',
        'endpoint' => (string) (223456789 + $endpointIndex),
        'recipient_label' => '개인 Telegram ' . (string) $endpointIndex,
    ]);
}
$endpointLimitRejected = false;
try {
    sr_notification_save_member_push_endpoint($pdo, [
        'account_id' => 7,
        'provider_key' => 'telegram_bot',
        'endpoint' => '323456789',
        'recipient_label' => '초과 Telegram',
    ]);
} catch (InvalidArgumentException) {
    $endpointLimitRejected = true;
}
sr_notification_runtime_assert($endpointLimitRejected, 'notification runtime fixture must enforce member push endpoint count limit.');

$memberPushNotificationId = sr_notification_create($pdo, [
    'account_id' => 7,
    'audience' => 'account',
    'title' => '회원 푸시 알림',
    'body_text' => '외부 푸시에 보낼 본문입니다.',
    'link_url' => '/account/notifications',
    'channels' => ['site', 'telegram_bot'],
]);
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_notification_deliveries WHERE notification_id = :id AND channel = \'telegram_bot\' AND recipient = :recipient', ['id' => $memberPushNotificationId, 'recipient' => 'endpoint:' . (string) $memberTelegramEndpointId]) === 1, 'notification runtime fixture must queue member push delivery by endpoint reference.');
$memberPushPayload = sr_notification_member_external_push_payload('telegram_bot', [
    'title' => '회원 푸시 알림',
    'body_text' => '외부 푸시 본문',
    'link_url' => '/account/notifications',
], ['site_name' => '산란'], '123456789');
sr_notification_runtime_assert(str_contains((string) ($memberPushPayload['text'] ?? ''), '외부 푸시 본문'), 'notification runtime fixture must copy member notification body into external push payload.');

$claimedMemberPush = sr_notification_claim_delivery($pdo, 'fixture-member-lock', '2026-06-11 12:05:00', 300, ['telegram_bot']);
sr_notification_runtime_assert(is_array($claimedMemberPush) && (string) ($claimedMemberPush['title'] ?? '') === '회원 푸시 알림', 'notification runtime fixture must claim member push delivery with account notification title.');
$pdo->prepare("UPDATE sr_notification_push_endpoints SET status = 'disabled', disabled_at = '2026-06-11 12:06:00' WHERE id = :id")->execute(['id' => $memberTelegramEndpointId]);
$memberPushResult = sr_notification_process_delivery($pdo, ['site_name' => '산란'], $claimedMemberPush, sr_notification_settings($pdo), '2026-06-11 12:07:00', 5);
sr_notification_runtime_assert(($memberPushResult['skipped'] ?? 0) === 1, 'notification runtime fixture must skip queued member push after endpoint is disabled.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT status FROM sr_notification_deliveries WHERE id = :id', ['id' => (int) ($claimedMemberPush['id'] ?? 0)]) === 'canceled', 'notification runtime fixture must cancel queued member push after endpoint is disabled.');
$pdo->prepare("UPDATE sr_notification_push_endpoints SET status = 'active', disabled_at = NULL WHERE id = :id")->execute(['id' => $memberTelegramEndpointId]);
sr_notification_runtime_assert(sr_notification_disable_member_push_endpoint($pdo, 7, $memberTelegramEndpointId, '2026-06-11 12:08:00'), 'notification runtime fixture must disable member push endpoint through helper.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT endpoint_ciphertext FROM sr_notification_push_endpoints WHERE id = :id', ['id' => $memberTelegramEndpointId]) === '', 'notification runtime fixture must clear member push endpoint ciphertext when disabled.');
sr_notification_runtime_assert(!sr_notification_disable_member_push_endpoint($pdo, 7, $memberTelegramEndpointId, '2026-06-11 12:09:00'), 'notification runtime fixture must not disable already disabled member push endpoints again.');
$notificationPrivacyExporter = require $root . '/modules/notification/privacy-export.php';
$notificationPrivacyExport = $notificationPrivacyExporter($pdo, 7);
$exportedEndpointRecipients = [];
foreach ((array) ($notificationPrivacyExport['deliveries'] ?? []) as $exportedDelivery) {
    if ((string) ($exportedDelivery['channel'] ?? '') === 'telegram_bot') {
        $exportedEndpointRecipients[] = (string) ($exportedDelivery['recipient'] ?? '');
    }
}
sr_notification_runtime_assert(in_array('1234***', $exportedEndpointRecipients, true), 'notification runtime fixture must export member push delivery recipient as masked label.');
sr_notification_runtime_assert(!in_array('endpoint:' . (string) $memberTelegramEndpointId, $exportedEndpointRecipients, true), 'notification runtime fixture must not export member push delivery endpoint references.');

$pdo->exec(
    "INSERT INTO sr_notification_event_templates
        (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
     VALUES
        ('community', 'push.optional', '선택 푸시', '본문', '/community', '[\"site\",\"telegram_bot\"]', 'active', '2026-06-11 00:00:00', '2026-06-11 00:00:00')"
);
$optionalPushNotificationId = sr_notification_create_account_event($pdo, [
    'account_id' => 8,
    'module_key' => 'community',
    'event_key' => 'push.optional',
]);
sr_notification_runtime_assert(is_int($optionalPushNotificationId) && $optionalPushNotificationId > 0, 'notification runtime fixture must create event notification even when optional member push endpoint is missing.');
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_notification_deliveries WHERE notification_id = :id AND channel = \'telegram_bot\'', ['id' => $optionalPushNotificationId]) === 0, 'notification runtime fixture must skip optional member push channel when account has no endpoint.');

$memberDiscordOnlyEndpointId = sr_notification_save_member_push_endpoint($pdo, [
    'account_id' => 8,
    'provider_key' => 'discord_webhook',
    'endpoint' => 'https://discord.com/api/webhooks/member8/token',
    'recipient_label' => '개인 Discord',
]);
sr_notification_runtime_assert($memberDiscordOnlyEndpointId > 0, 'notification runtime fixture must save a second member Discord push endpoint.');
$pdo->exec(
    "INSERT INTO sr_notification_event_templates
        (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
     VALUES
        ('community', 'push.site_only', '사이트 알림', '본문', '/community', '[\"site\"]', 'active', '2026-06-11 00:00:00', '2026-06-11 00:00:00')"
);
$siteOnlyPushNotificationId = sr_notification_create_account_event($pdo, [
    'account_id' => 8,
    'module_key' => 'community',
    'event_key' => 'push.site_only',
]);
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_notification_deliveries WHERE notification_id = :id AND channel = \'discord_webhook\' AND recipient = :recipient', ['id' => $siteOnlyPushNotificationId, 'recipient' => 'endpoint:' . (string) $memberDiscordOnlyEndpointId]) === 1, 'notification runtime fixture must queue connected member Discord push for site-only account notifications.');
$pdo->exec(
    "INSERT INTO sr_notification_event_templates
        (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
     VALUES
        ('community', 'push.email_only', '이메일 알림', '본문', '/community', '[\"email\"]', 'active', '2026-06-11 00:00:00', '2026-06-11 00:00:00')"
);
$emailOnlyPushNotificationId = sr_notification_create_account_event($pdo, [
    'account_id' => 8,
    'module_key' => 'community',
    'event_key' => 'push.email_only',
]);
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_notification_deliveries WHERE notification_id = :id AND channel = \'discord_webhook\' AND recipient = :recipient', ['id' => $emailOnlyPushNotificationId, 'recipient' => 'endpoint:' . (string) $memberDiscordOnlyEndpointId]) === 1, 'notification runtime fixture must queue connected member Discord push for account notifications without site channel.');
$directSiteOnlyNotificationId = sr_notification_create($pdo, [
    'account_id' => 8,
    'audience' => 'account',
    'title' => '직접 사이트 알림',
    'body_text' => '본문',
    'link_url' => '/account/notifications',
    'channels' => ['site'],
]);
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_notification_deliveries WHERE notification_id = :id AND channel = \'discord_webhook\' AND recipient = :recipient', ['id' => $directSiteOnlyNotificationId, 'recipient' => 'endpoint:' . (string) $memberDiscordOnlyEndpointId]) === 1, 'notification runtime fixture must queue connected member Discord push for direct site-only account notifications.');
$directEmailOnlyNotificationId = sr_notification_create($pdo, [
    'account_id' => 8,
    'audience' => 'account',
    'title' => '직접 이메일 알림',
    'body_text' => '본문',
    'link_url' => '/account/notifications',
    'channels' => ['email'],
]);
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_notification_deliveries WHERE notification_id = :id AND channel = \'discord_webhook\' AND recipient = :recipient', ['id' => $directEmailOnlyNotificationId, 'recipient' => 'endpoint:' . (string) $memberDiscordOnlyEndpointId]) === 1, 'notification runtime fixture must queue connected member Discord push for direct account notifications without site channel.');

$cleanupEndpointId = sr_notification_save_member_push_endpoint($pdo, [
    'account_id' => 8,
    'provider_key' => 'telegram_bot',
    'endpoint' => '987654321',
    'recipient_label' => '정리 Telegram',
]);
$notificationPrivacyCleanup = require $root . '/modules/notification/privacy-cleanup.php';
$cleanupResult = $notificationPrivacyCleanup($pdo, 8, ['event_type' => 'withdrawal']);
sr_notification_runtime_assert((int) ($cleanupResult['notification_push_endpoint_disabled_count'] ?? 0) === 2, 'notification privacy cleanup must disable account push endpoints.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT endpoint_ciphertext FROM sr_notification_push_endpoints WHERE id = :id', ['id' => $cleanupEndpointId]) === '', 'notification privacy cleanup must clear member push endpoint ciphertext.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT endpoint_ciphertext FROM sr_notification_push_endpoints WHERE id = :id', ['id' => $memberDiscordOnlyEndpointId]) === '', 'notification privacy cleanup must clear member Discord push endpoint ciphertext.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT status FROM sr_notification_push_endpoints WHERE id = :id', ['id' => $cleanupEndpointId]) === 'disabled', 'notification privacy cleanup must tombstone member push endpoint rows.');

$pdo->exec(
    "INSERT INTO sr_admin_notifications
        (id, title, body_text, severity, source_module_key, event_key, target_type, target_id, action_url,
         permission_path, permission_action, status, dedupe_key, occurrence_count, last_occurred_at, created_at, updated_at)
     VALUES
        (101, '운영 상태 경고', 'delivery 실패가 누적되었습니다.', 'warning', 'notification', 'delivery.failed',
         'notification_delivery', '9', '/admin/notification-deliveries', '', 'view', 'open',
         'fixture-slack-admin-alert', 1, '2026-06-11 12:00:00', '2026-06-11 12:00:00', '2026-06-11 12:00:00')"
);
$adminNotificationId = 101;
sr_notification_runtime_assert(sr_notification_queue_admin_external_deliveries($pdo, $adminNotificationId) === 3, 'notification runtime fixture must queue configured external deliveries for admin notification.');
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_notification_deliveries WHERE notification_id = :id AND channel = \'slack_webhook\' AND recipient = \'운영 알림\'', ['id' => $adminNotificationId]) === 1, 'notification runtime fixture must queue slack_webhook delivery for admin notification.');
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_notification_deliveries WHERE notification_id = :id AND channel = \'discord_webhook\' AND recipient = \'운영 Discord\'', ['id' => $adminNotificationId]) === 1, 'notification runtime fixture must queue discord_webhook delivery for admin notification.');
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_notification_deliveries WHERE notification_id = :id AND channel = \'telegram_bot\' AND recipient = \'운영 Telegram\'', ['id' => $adminNotificationId]) === 1, 'notification runtime fixture must queue telegram_bot delivery for admin notification.');
$pdo->exec("UPDATE sr_module_settings SET setting_value = '0' WHERE module_id = 1 AND setting_key = 'discord_webhook_enabled'");
$GLOBALS['sr_module_settings_cache_token_notification'] = (int) ($GLOBALS['sr_module_settings_cache_token_notification'] ?? 0) + 1;
sr_notification_runtime_assert(sr_notification_queue_admin_external_deliveries($pdo, $adminNotificationId, ['discord_webhook']) === 0, 'notification runtime fixture must not queue explicitly requested disabled external providers.');
$pdo->exec("UPDATE sr_module_settings SET setting_value = '1' WHERE module_id = 1 AND setting_key = 'discord_webhook_enabled'");
$pdo->exec("UPDATE sr_module_settings SET setting_value = 'endpoint:999' WHERE module_id = 1 AND setting_key = 'telegram_channel_label'");
$GLOBALS['sr_module_settings_cache_token_notification'] = (int) ($GLOBALS['sr_module_settings_cache_token_notification'] ?? 0) + 1;
sr_notification_runtime_assert(sr_notification_queue_admin_external_deliveries($pdo, $adminNotificationId, ['telegram_bot']) === 1, 'notification runtime fixture must queue admin Telegram even when label resembles an endpoint reference.');
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_notification_deliveries WHERE notification_id = :id AND channel = \'telegram_bot\' AND recipient = \'endpoint:999\'', ['id' => $adminNotificationId]) === 0, 'notification runtime fixture must not store admin external recipient labels that look like endpoint references.');
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_notification_deliveries WHERE notification_id = :id AND channel = \'telegram_bot\' AND recipient = \'telegram_bot\'', ['id' => $adminNotificationId]) === 1, 'notification runtime fixture must replace endpoint-like admin labels with the provider key.');
$pdo->exec("UPDATE sr_module_settings SET setting_value = '운영 Telegram' WHERE module_id = 1 AND setting_key = 'telegram_channel_label'");
$GLOBALS['sr_module_settings_cache_token_notification'] = (int) ($GLOBALS['sr_module_settings_cache_token_notification'] ?? 0) + 1;

$claimedSlack = sr_notification_claim_delivery($pdo, 'fixture-lock', '2026-06-11 12:10:00', 300, ['slack_webhook']);
sr_notification_runtime_assert(is_array($claimedSlack) && (string) ($claimedSlack['title'] ?? '') === '운영 상태 경고', 'notification runtime fixture must claim slack_webhook delivery with admin notification title.');
$slackDisabledResult = sr_notification_process_delivery($pdo, ['site_name' => '산란'], $claimedSlack, [
    'external_push_enabled' => false,
    'external_push_failure_policy' => 'dead',
    'slack_webhook_url' => '',
    'email_timeout_seconds' => 10,
], '2026-06-11 12:11:00', 5);
sr_notification_runtime_assert(($slackDisabledResult['dead'] ?? 0) === 1, 'notification runtime fixture must dead-letter disabled external push when policy is dead.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT status FROM sr_notification_deliveries WHERE id = :id', ['id' => (int) ($claimedSlack['id'] ?? 0)]) === 'dead', 'notification runtime fixture must persist slack_webhook dead-letter status.');
sr_notification_runtime_assert(!empty(sr_notification_slack_webhook_response_result(['ok' => true, 'status' => 200, 'body' => 'ok'])['ok']), 'notification runtime fixture must accept Slack webhook ok response.');
$deliveryTransportHelpers = sr_notification_runtime_file('modules/notification/helpers/deliveries.php');
sr_notification_runtime_assert(str_contains($deliveryTransportHelpers, 'curl_init') && str_contains($deliveryTransportHelpers, 'allow_url_fopen'), 'notification runtime fixture must allow webhook HTTP delivery through cURL before allow_url_fopen stream fallback.');
sr_notification_runtime_assert(!str_contains($deliveryTransportHelpers, 'sr_json_encode(') && str_contains($deliveryTransportHelpers, 'JSON_INVALID_UTF8_SUBSTITUTE'), 'notification runtime fixture must encode webhook payloads without depending on unavailable helpers.');
sr_notification_runtime_assert(
    str_contains($deliveryTransportHelpers, "in_array(\$method, ['GET', 'POST'], true)")
        && str_contains($deliveryTransportHelpers, 'sr_notification_web_delivery_runner_due($pdo, $settings)'),
    'notification web runner must apply the configured interval to both GET and POST requests.'
);
sr_notification_runtime_assert(str_contains($deliveryTransportHelpers, 'function sr_notification_absolute_link_url(') && !str_contains($deliveryTransportHelpers, 'sr_is_http_url($linkUrl) ? $linkUrl : sr_url($linkUrl)'), 'notification external push payloads must send absolute URLs for relative notification links.');
sr_notification_runtime_assert(!empty(sr_notification_external_push_response_result('discord_webhook', ['ok' => true, 'status' => 204, 'body' => ''])['ok']), 'notification runtime fixture must accept Discord webhook success response.');
sr_notification_runtime_assert((string) (sr_notification_external_push_response_result('telegram_bot', ['ok' => true, 'status' => 200, 'body' => '{"ok":true,"result":{"message_id":77}}'])['provider_message_id'] ?? '') === 'telegram:77', 'notification runtime fixture must accept Telegram bot success response.');
$slackFailure = sr_notification_slack_webhook_response_result(['ok' => true, 'status' => 403, 'body' => 'invalid_auth token=secret']);
sr_notification_runtime_assert(empty($slackFailure['ok']) && !str_contains((string) ($slackFailure['error'] ?? ''), 'secret'), 'notification runtime fixture must sanitize Slack webhook error summaries.');
$discordFailure = sr_notification_external_push_response_result('discord_webhook', ['ok' => true, 'status' => 403, 'body' => 'failed https://discord.com/api/webhooks/fixture/token']);
sr_notification_runtime_assert(empty($discordFailure['ok']) && !str_contains((string) ($discordFailure['error'] ?? ''), 'fixture/token'), 'notification runtime fixture must sanitize Discord webhook URL errors.');
$telegramFailure = sr_notification_external_push_response_result('telegram_bot', ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'failed https://api.telegram.org/bot123456789:ABCdef_ghi-jklmnopqrstuvwxyz123456/sendMessage']);
sr_notification_runtime_assert(empty($telegramFailure['ok']) && !str_contains((string) ($telegramFailure['error'] ?? ''), 'ABCdef'), 'notification runtime fixture must sanitize Telegram bot URL errors.');
$longTelegramFailure = sr_notification_external_push_response_result('telegram_bot', ['ok' => false, 'status' => 0, 'body' => '', 'error' => str_repeat('prefix ', 40) . 'https://api.telegram.org/bot123456789:ABCdef_ghi-jklmnopqrstuvwxyz123456/sendMessage']);
sr_notification_runtime_assert(empty($longTelegramFailure['ok']) && !str_contains((string) ($longTelegramFailure['error'] ?? ''), 'ABCdef'), 'notification runtime fixture must mask long provider errors before truncation.');

sr_notification_runtime_assert(
    sr_notification_delivery_status_transition('failed', 'queued') === ['allowed' => true, 'operation' => 'retry'],
    'notification delivery transition must allow failed retry.'
);
sr_notification_runtime_assert(
    sr_notification_delivery_status_transition('canceled', 'queued') === ['allowed' => true, 'operation' => 'retry'],
    'notification delivery transition must allow canceled retry.'
);
sr_notification_runtime_assert(
    sr_notification_delivery_status_transition('queued', 'canceled') === ['allowed' => true, 'operation' => 'cancel'],
    'notification delivery transition must allow queued cancel.'
);
sr_notification_runtime_assert(
    sr_notification_delivery_status_transition('failed', 'canceled') === ['allowed' => true, 'operation' => 'cancel'],
    'notification delivery transition must allow failed cancel.'
);
sr_notification_runtime_assert(
    sr_notification_delivery_status_transition('queued', 'failed') === ['allowed' => true, 'operation' => 'mark_failed'],
    'notification delivery transition must allow queued manual failure.'
);
sr_notification_runtime_assert(
    sr_notification_delivery_status_transition('failed', 'sent') === ['allowed' => true, 'operation' => 'mark_sent'],
    'notification delivery transition must allow failed manual sent.'
);
sr_notification_runtime_assert(
    sr_notification_delivery_status_transition('sent', 'queued') === ['allowed' => false, 'operation' => ''],
    'notification delivery transition must keep sent terminal.'
);
sr_notification_runtime_assert(
    sr_notification_delivery_status_transition('queued', 'queued') === ['allowed' => false, 'operation' => ''],
    'notification delivery transition must reject no-op changes.'
);
sr_notification_runtime_assert(
    sr_notification_delivery_status_transition('queued', 'unknown') === ['allowed' => false, 'operation' => ''],
    'notification delivery transition must reject unknown target.'
);
sr_notification_runtime_assert(
    sr_notification_delivery_status_transition('dead', 'queued') === ['allowed' => true, 'operation' => 'retry'],
    'notification delivery transition must allow dead-letter retry.'
);

$pdo->exec(
    "INSERT INTO sr_notification_deliveries
        (notification_id, channel, recipient, status, provider_message_id, error_message, attempted_at, created_at, updated_at)
     VALUES
        (1, 'email', 'retry@example.test', 'failed', 'provider-before', 'error-before', '2026-06-11 11:00:00', '2026-06-11 10:00:00', '2026-06-11 11:00:00'),
        (1, 'email', 'sent@example.test', 'queued', 'provider-queued', 'error-queued', NULL, '2026-06-11 10:00:00', '2026-06-11 10:00:00'),
        (1, 'email', 'stale@example.test', 'sent', 'provider-sent', '', '2026-06-11 11:30:00', '2026-06-11 10:00:00', '2026-06-11 11:30:00'),
        (1, 'site', '', 'queued', '', '', NULL, '2026-06-11 10:00:00', '2026-06-11 10:00:00')"
);

$retryDeliveryId = (int) sr_notification_runtime_scalar($pdo, "SELECT id FROM sr_notification_deliveries WHERE recipient = 'retry@example.test'");
$retryResult = sr_notification_update_delivery_status($pdo, $retryDeliveryId, 'queued', '2026-06-11 12:30:00');
sr_notification_runtime_assert(!empty($retryResult['ok']) && ($retryResult['operation'] ?? '') === 'retry', 'notification delivery retry helper must report retry operation.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT status FROM sr_notification_deliveries WHERE id = :id', ['id' => $retryDeliveryId]) === 'queued', 'notification delivery retry helper must requeue failed delivery.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT provider_message_id FROM sr_notification_deliveries WHERE id = :id', ['id' => $retryDeliveryId]) === '', 'notification delivery retry helper must clear provider message id.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT error_message FROM sr_notification_deliveries WHERE id = :id', ['id' => $retryDeliveryId]) === '', 'notification delivery retry helper must clear error message.');
sr_notification_runtime_assert(sr_notification_runtime_scalar($pdo, 'SELECT attempted_at FROM sr_notification_deliveries WHERE id = :id', ['id' => $retryDeliveryId]) === null, 'notification delivery retry helper must clear attempted_at.');

$sentDeliveryId = (int) sr_notification_runtime_scalar($pdo, "SELECT id FROM sr_notification_deliveries WHERE recipient = 'sent@example.test'");
$sentResult = sr_notification_update_delivery_status($pdo, $sentDeliveryId, 'sent', '2026-06-11 12:31:00');
sr_notification_runtime_assert(!empty($sentResult['ok']) && ($sentResult['operation'] ?? '') === 'mark_sent', 'notification delivery status helper must report manual sent operation.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT attempted_at FROM sr_notification_deliveries WHERE id = :id', ['id' => $sentDeliveryId]) === '2026-06-11 12:31:00', 'notification delivery sent helper must set attempted_at.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT error_message FROM sr_notification_deliveries WHERE id = :id', ['id' => $sentDeliveryId]) === '', 'notification delivery sent helper must clear error message.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT provider_message_id FROM sr_notification_deliveries WHERE id = :id', ['id' => $sentDeliveryId]) === 'provider-queued', 'notification delivery sent helper must keep provider message id.');

$staleDeliveryId = (int) sr_notification_runtime_scalar($pdo, "SELECT id FROM sr_notification_deliveries WHERE recipient = 'stale@example.test'");
$staleResult = sr_notification_update_delivery_status_row($pdo, $staleDeliveryId, 'queued', 'failed', '2026-06-11 12:32:00');
sr_notification_runtime_assert(empty($staleResult['ok']) && ($staleResult['error'] ?? '') === 'changed', 'notification delivery helper must reject stale before_status updates.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT status FROM sr_notification_deliveries WHERE id = :id', ['id' => $staleDeliveryId]) === 'sent', 'notification delivery stale helper must not mutate changed row.');

$siteDeliveryId = (int) sr_notification_runtime_scalar($pdo, "SELECT id FROM sr_notification_deliveries WHERE channel = 'site' ORDER BY id DESC LIMIT 1");
$siteResult = sr_notification_update_delivery_status($pdo, $siteDeliveryId, 'queued', '2026-06-11 12:33:00');
sr_notification_runtime_assert(empty($siteResult['ok']) && ($siteResult['error'] ?? '') === 'not_found', 'notification delivery helper must not expose site deliveries for manual status updates.');

$adminAction = file_get_contents($root . '/modules/notification/actions/admin-notifications.php');
$adminView = file_get_contents($root . '/modules/notification/views/admin-notifications.php');
$adminNotificationAction = file_get_contents($root . '/modules/notification/actions/admin-admin-notifications.php');
$adminNotificationView = file_get_contents($root . '/modules/notification/views/admin-admin-notifications.php');
$notificationAdminView = file_get_contents($root . '/modules/notification/views/admin-notifications.php');
$notificationHelpers = file_get_contents($root . '/modules/notification/helpers.php');
$notificationDeliveryHelpers = file_get_contents($root . '/modules/notification/helpers/deliveries.php');
$notificationAdminHelpers = file_get_contents($root . '/modules/notification/helpers/admin-notifications.php');
$notificationPrivacyExport = file_get_contents($root . '/modules/notification/privacy-export.php');
$requestBootstrap = sr_notification_runtime_file('core/request-bootstrap.php');
sr_notification_runtime_assert(str_contains($requestBootstrap, "in_array(\$method, ['GET', 'POST'], true)") && str_contains($requestBootstrap, 'sr_notification_register_web_delivery_runner'), 'request bootstrap must register notification web runner for POST requests as well as GET requests.');
http_response_code(200);
$GLOBALS['sr_request_contract'] = ['exit_reason' => 'completed'];
sr_notification_runtime_assert(sr_notification_web_delivery_runner_request_completed(), 'notification web runner must accept a completed successful request.');
$GLOBALS['sr_request_contract'] = ['exit_reason' => 'guard_blocked'];
sr_notification_runtime_assert(!sr_notification_web_delivery_runner_request_completed(), 'notification web runner must reject an auth or CSRF blocked request.');
unset($GLOBALS['sr_request_contract']);
http_response_code(404);
sr_notification_runtime_assert(!sr_notification_web_delivery_runner_request_completed(), 'notification web runner must reject an unresolved route response.');
http_response_code(200);
sr_notification_runtime_assert(
    is_string($notificationDeliveryHelpers)
        && str_contains($notificationDeliveryHelpers, 'sr_notification_web_delivery_runner_lock_acquire($pdo)')
        && str_contains($notificationDeliveryHelpers, 'sr_notification_web_delivery_runner_due($pdo, $settings)'),
    'notification web runner must serialize and recheck the interval after request completion.'
);
sr_notification_runtime_assert(is_string($adminAction) && str_contains($adminAction, '$allowedDeliveryStatuses = sr_notification_delivery_statuses();'), 'notification delivery admin action must use shared delivery statuses.');
sr_notification_runtime_assert(is_string($adminAction) && str_contains($adminAction, "array_merge(['email'], sr_notification_admin_external_channel_keys())"), 'notification delivery admin action must expose all admin external delivery filters.');
sr_notification_runtime_assert(is_string($adminAction) && str_contains($adminAction, 'channel NOT IN ('), 'notification delete action must not delete admin external deliveries with colliding notification ids.');
sr_notification_runtime_assert(is_string($adminAction) && str_contains($adminAction, "\$intent === 'run_deliveries'"), 'notification delivery admin action must expose manual runner.');
sr_notification_runtime_assert(is_string($adminAction) && str_contains($adminAction, "\$intent === 'delivery_status'"), 'notification delivery admin action must expose delivery status updates.');
sr_notification_runtime_assert(is_string($adminAction) && str_contains($adminAction, 'sr_notification_update_delivery_status($pdo, $deliveryId, $status, sr_now())'), 'notification delivery admin action must use the shared status update helper.');
sr_notification_runtime_assert(is_string($adminAction) && !str_contains($adminAction, '이메일 발송 작업'), 'notification delivery admin action must not label shared delivery jobs as email-only jobs.');
sr_notification_runtime_assert(is_string($adminAction) && str_contains($adminAction, "'before_status' => \$beforeStatus"), 'notification delivery audit log must include before_status.');
sr_notification_runtime_assert(is_string($adminAction) && str_contains($adminAction, "'operation' => \$operation"), 'notification delivery audit log must include operation.');
sr_notification_runtime_assert(is_string($adminView) && str_contains($adminView, 'sr_notification_delivery_status_transition($deliveryStatus, $status)'), 'notification delivery admin view must only render allowed transition buttons.');
sr_notification_runtime_assert(is_string($adminView) && str_contains($adminView, '알림 발송 작업 목록 페이지') && !str_contains($adminView, '이메일 발송 작업'), 'notification delivery admin view must label shared delivery jobs as notification delivery jobs.');
sr_notification_runtime_assert(
    is_string($notificationDeliveryHelpers)
        && str_contains($notificationDeliveryHelpers, 'function sr_notification_claim_delivery(')
        && str_contains($notificationDeliveryHelpers, 'function sr_notification_process_delivery(')
        && str_contains($notificationDeliveryHelpers, 'function sr_notification_process_external_push_delivery('),
    'notification delivery runner must use common claim/process helpers with external push dispatch.'
);
sr_notification_runtime_assert(
    is_string($notificationAdminHelpers)
        && str_contains($notificationAdminHelpers, 'sr_notification_queue_admin_external_deliveries($pdo, $id)'),
    'admin notification creation must queue configured external push deliveries.'
);
sr_notification_runtime_assert(
    is_string($notificationDeliveryHelpers)
        && is_string($notificationHelpers)
        && str_contains($notificationDeliveryHelpers, 'function sr_notification_process_immediate_member_external_deliveries(')
        && str_contains($notificationDeliveryHelpers, 'function sr_notification_delete_sent_delivery(')
        && str_contains($notificationDeliveryHelpers, "DELETE FROM sr_notification_deliveries\n         WHERE id = :id\n           AND status = 'sent'")
        && str_contains($notificationHelpers, 'sr_notification_member_external_immediate_delivery_enabled()')
        && str_contains($notificationHelpers, 'sr_notification_process_immediate_member_external_deliveries('),
    'member external push deliveries must be attempted immediately after POST notification commits and successful rows must not remain in the delivery list.'
);
sr_notification_runtime_assert(
    is_string($notificationDeliveryHelpers)
        && str_contains($notificationDeliveryHelpers, '발송 처리 예외: ')
        && str_contains($notificationDeliveryHelpers, 'get_class($exception)')
        && str_contains($notificationDeliveryHelpers, '$exception->getMessage()'),
    'notification delivery runner exceptions must store actionable exception details.'
);
sr_notification_runtime_assert(
    is_string($notificationPrivacyExport)
        && !str_contains($notificationPrivacyExport, 'channel NOT IN (')
        && str_contains($notificationPrivacyExport, 'channel IN (')
        && str_contains($notificationPrivacyExport, 'recipient LIKE ?')
        && str_contains($notificationPrivacyExport, "'endpoint:%'")
        && str_contains($notificationPrivacyExport, "'discord_webhook'")
        && str_contains($notificationPrivacyExport, "'telegram_bot'"),
    'notification privacy export must include only endpoint-reference push deliveries from account external exports.'
);
sr_notification_runtime_assert(
    is_string($notificationAdminHelpers)
        && str_contains($notificationAdminHelpers, 'DELETE FROM sr_admin_notification_reads WHERE notification_id = :notification_id'),
    'admin notification duplicate reopen must clear per-account read rows so the topbar unread badge returns.'
);
$settingsAction = sr_notification_runtime_file('modules/notification/actions/admin-notification-settings.php');
$settingsView = sr_notification_runtime_file('modules/notification/views/admin-notification-settings.php');
$memberAccountView = sr_notification_runtime_file('modules/member/views/account.php');
$accountNotificationsAction = sr_notification_runtime_file('modules/notification/actions/account-notifications.php');
$accountNotificationsView = sr_notification_runtime_file('modules/notification/views/account-notifications.php');
$communityCommentAction = sr_notification_runtime_file('modules/community/actions/comment.php');
$communityNotificationHelpers = sr_notification_runtime_file('modules/community/helpers/notifications.php');
sr_notification_runtime_assert(str_contains($communityCommentAction, '#community-comment-') && !str_contains($communityCommentAction, "'link_url' => '/community/post?id=' . (string) \$postId . '#comments'"), 'community comment created notifications must link to the created comment anchor.');
sr_notification_runtime_assert(str_contains($communityNotificationHelpers, '#community-comment-') && !str_contains($communityNotificationHelpers, "'link_url' => '/community/post?id=' . (string) \$postId . '#comments'"), 'community comment mention notifications must link to the mentioned comment anchor.');
sr_notification_runtime_assert(str_contains($settingsAction, "'external_push_enabled' => (bool) \$settings['external_push_enabled']"), 'notification settings audit metadata must include external push policy without webhook secret.');
$settingsAuditPos = strpos($settingsAction, 'sr_audit_log($pdo, [');
$settingsAuditBlock = $settingsAuditPos === false ? '' : substr($settingsAction, $settingsAuditPos, 1200);
sr_notification_runtime_assert($settingsAuditBlock !== '' && !str_contains($settingsAuditBlock, "'slack_webhook_url' =>"), 'notification settings audit metadata must not include Slack webhook URL.');
sr_notification_runtime_assert($settingsAuditBlock !== '' && !str_contains($settingsAuditBlock, "'discord_webhook_url' =>"), 'notification settings audit metadata must not include Discord webhook URL.');
sr_notification_runtime_assert($settingsAuditBlock !== '' && !str_contains($settingsAuditBlock, "'telegram_bot_token' =>"), 'notification settings audit metadata must not include Telegram bot token.');
sr_notification_runtime_assert(str_contains($settingsView, 'type="password" name="slack_webhook_url"'), 'notification settings view must render Slack webhook URL as a password field.');
sr_notification_runtime_assert(str_contains($settingsView, 'type="password" name="discord_webhook_url"'), 'notification settings view must render Discord webhook URL as a password field.');
sr_notification_runtime_assert(str_contains($settingsView, 'type="password" name="telegram_bot_token"'), 'notification settings view must render Telegram bot token as a password field.');
sr_notification_runtime_assert(str_contains($settingsView, "'slack_member_push_enabled'"), 'notification settings view must render Slack member push enable switch.');
sr_notification_runtime_assert(str_contains($settingsView, "'discord_member_push_enabled'"), 'notification settings view must render Discord member push enable switch.');
sr_notification_runtime_assert(str_contains($settingsView, "'telegram_member_push_enabled'"), 'notification settings view must render Telegram member push enable switch.');
sr_notification_runtime_assert(str_contains($settingsView, 'Telegram 회원 허용') && !str_contains($settingsView, '회원 수신처 허용'), 'notification settings view must use concise member allow labels.');
sr_notification_runtime_assert(str_contains($settingsView, 'sr_notification_secret_display'), 'notification settings view must mask stored Slack webhook URLs.');
sr_notification_runtime_assert(
    str_contains($settingsView, 'data-notification-operational-toggle="slack"')
        && str_contains($settingsView, 'data-notification-operational-required-label="slack"')
        && str_contains($settingsView, 'data-notification-operational-secret="slack"')
        && str_contains($settingsView, 'data-notification-telegram-token-required-label'),
    'notification settings view must show conditional required labels for enabled operational push providers.'
);
sr_notification_runtime_assert(
    str_contains($settingsAction, 'Slack 운영 수신 URL을 입력하세요.')
        && str_contains($settingsAction, 'Discord 운영 수신 URL을 입력하세요.')
        && str_contains($settingsAction, 'Telegram 대화방 ID를 입력하세요.'),
    'notification settings action must server-validate required operational push provider fields.'
);
sr_notification_runtime_assert(
    str_contains($memberAccountView, "sr_module_enabled(\$pdo, 'notification')")
        && str_contains($memberAccountView, "'url' => '/account/notifications'"),
    'member account view must link to account notification settings when notification module is enabled.'
);
sr_notification_runtime_assert(str_contains($accountNotificationsView, "sr_url('/mypage')"), 'member notification settings view must link back to mypage.');
sr_notification_runtime_assert(
    is_string($notificationAdminHelpers)
        && str_contains($notificationAdminHelpers, 'function sr_notification_admin_mark_unread(')
        && str_contains($notificationAdminHelpers, 'AND account_id = :account_id'),
    'admin notification unread helper must clear only the current admin account read row.'
);
sr_notification_runtime_assert(
    is_string($adminNotificationAction)
        && str_contains($adminNotificationAction, "'batch_mark_unread'")
        && str_contains($adminNotificationAction, "'mark_unread'")
        && str_contains($adminNotificationAction, 'sr_notification_admin_mark_unread('),
    'admin notification action must expose single and batch unread transitions.'
);
sr_notification_runtime_assert(
    is_string($adminNotificationView)
        && str_contains($adminNotificationView, 'value="batch_mark_unread"')
        && str_contains($adminNotificationView, 'value="mark_unread"'),
    'admin notification list must render single and batch unread actions.'
);
sr_notification_runtime_assert(
    is_string($notificationAdminView)
        && str_contains($notificationAdminView, '/admin/notification-deliveries?field=notification')
        && str_contains($notificationAdminView, "\$delivery['error_message']"),
    'admin notification views must link notifications to delivery diagnostics and show delivery error messages.'
);
sr_notification_runtime_assert(str_contains($accountNotificationsAction, "'connect_push_endpoint'"), 'member notification account action must expose generic push endpoint connect intent.');
sr_notification_runtime_assert(str_contains($accountNotificationsAction, "'disable_push_endpoint'"), 'member notification account action must expose push endpoint disable intent.');
sr_notification_runtime_assert(str_contains($accountNotificationsAction, 'sr_member_reauth_throttle_status($pdo, (int) $account[\'id\'])'), 'member notification push changes must use reauth throttling.');
sr_notification_runtime_assert(str_contains($accountNotificationsAction, 'password_verify($currentPassword'), 'member notification push changes must verify current password.');
sr_notification_runtime_assert(str_contains($accountNotificationsAction, 'notification.member_push_endpoint.connected'), 'member notification push connect must write audit logs.');
sr_notification_runtime_assert(str_contains($accountNotificationsAction, 'notification.member_push_endpoint.disabled'), 'member notification push disable must write audit logs.');
sr_notification_runtime_assert(str_contains($accountNotificationsAction, "'event_key' => 'member_push_endpoint.connected'"), 'member notification push connect notice must use the notification event template.');
sr_notification_runtime_assert(str_contains($accountNotificationsAction, "'event_key' => 'member_push_endpoint.disabled'"), 'member notification push disable notice must use the notification event template.');
sr_notification_runtime_assert(str_contains(sr_notification_runtime_file('modules/notification/install.sql'), "'notification', 'member_push_endpoint.connected'") && str_contains(sr_notification_runtime_file('modules/notification/install.sql'), '\'["site"]\''), 'member notification push security notice templates must stay in site notifications.');
sr_notification_runtime_assert(str_contains($accountNotificationsView, 'name="telegram_chat_id"'), 'member notification account view must render Telegram chat ID input.');
sr_notification_runtime_assert(str_contains($accountNotificationsView, 'name="provider_key"'), 'member notification account view must submit provider keys for push endpoint connections.');
sr_notification_runtime_assert(str_contains($accountNotificationsView, 'name="endpoint"'), 'member notification account view must render webhook endpoint input for Slack and Discord.');
sr_notification_runtime_assert(str_contains($accountNotificationsView, 'value="disable_push_endpoint"'), 'member notification account view must render endpoint disable form.');
sr_notification_runtime_assert(str_contains($accountNotificationsView, 'autocomplete="current-password"'), 'member notification account view must require current password fields.');

if ($errors !== []) {
    fwrite(STDERR, "notification runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "notification runtime checks completed.\n";
