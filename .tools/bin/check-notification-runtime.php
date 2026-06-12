#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

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
        return ['email_channel_enabled' => true];
    }
}

require_once $root . '/modules/notification/helpers.php';

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
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
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
        "INSERT INTO sr_member_accounts (id, email, status) VALUES
            (7, 'member7@example.test', 'active'),
            (8, 'member8@example.test', 'active')"
    );
    $pdo->exec(
        "INSERT INTO sr_notification_event_templates
            (module_key, event_key, title_template, body_template, link_template, channels_json, status, created_at, updated_at)
         VALUES
            ('community', 'comment.mention', '댓글에서 {member_name}님이 언급했습니다.', '본문: {comment_excerpt}', '/community/post?id={post_id}', '[\"site\",\"email\"]', 'active', '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
            ('community', 'disabled.event', '비활성 알림', '비활성', '/disabled', '[\"site\"]', 'disabled', '2026-06-11 00:00:00', '2026-06-11 00:00:00')"
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
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT body_text FROM sr_notifications WHERE id = :id', ['id' => $notificationId]) === '본문: 테스트 댓글', 'notification runtime fixture must render body template metadata.');
sr_notification_runtime_assert((string) sr_notification_runtime_scalar($pdo, 'SELECT link_url FROM sr_notifications WHERE id = :id', ['id' => $notificationId]) === '/community/post?id=42', 'notification runtime fixture must render link template metadata.');
sr_notification_runtime_assert((int) sr_notification_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_notification_deliveries WHERE notification_id = :id AND channel = \'email\' AND recipient = \'member7@example.test\' AND status = \'queued\'', ['id' => $notificationId]) === 1, 'notification runtime fixture must queue email delivery for account event.');

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

$adminAction = file_get_contents($root . '/modules/notification/actions/admin-notifications.php');
sr_notification_runtime_assert(is_string($adminAction) && str_contains($adminAction, "\$allowedDeliveryStatuses = ['queued', 'sent', 'failed', 'canceled'];"), 'notification delivery admin action must allow queued/sent/failed/canceled statuses.');
sr_notification_runtime_assert(is_string($adminAction) && str_contains($adminAction, 'UPDATE sr_notification_deliveries'), 'notification delivery admin action must update delivery status rows.');
sr_notification_runtime_assert(is_string($adminAction) && str_contains($adminAction, "\$intent === 'delivery_status'"), 'notification delivery admin action must expose delivery status updates.');

if ($errors !== []) {
    fwrite(STDERR, "notification runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "notification runtime checks completed.\n";
