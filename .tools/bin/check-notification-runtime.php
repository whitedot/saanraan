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
$notificationHelpers = file_get_contents($root . '/modules/notification/helpers.php');
sr_notification_runtime_assert(is_string($adminAction) && str_contains($adminAction, "\$allowedDeliveryStatuses = ['queued', 'sent', 'failed', 'canceled'];"), 'notification delivery admin action must allow queued/sent/failed/canceled statuses.');
sr_notification_runtime_assert(is_string($adminAction) && str_contains($adminAction, "\$intent === 'delivery_status'"), 'notification delivery admin action must expose delivery status updates.');
sr_notification_runtime_assert(is_string($adminAction) && str_contains($adminAction, 'sr_notification_update_delivery_status($pdo, $deliveryId, $status, sr_now())'), 'notification delivery admin action must use the shared status update helper.');
sr_notification_runtime_assert(is_string($adminAction) && str_contains($adminAction, "'before_status' => \$beforeStatus"), 'notification delivery audit log must include before_status.');
sr_notification_runtime_assert(is_string($adminAction) && str_contains($adminAction, "'operation' => \$operation"), 'notification delivery audit log must include operation.');
sr_notification_runtime_assert(is_string($adminView) && str_contains($adminView, 'sr_notification_delivery_status_transition($deliveryStatus, $status)'), 'notification delivery admin view must only render allowed transition buttons.');
sr_notification_runtime_assert(
    is_string($notificationHelpers)
        && str_contains($notificationHelpers, 'DELETE FROM sr_admin_notification_reads WHERE notification_id = :notification_id'),
    'admin notification duplicate reopen must clear per-account read rows so the topbar unread badge returns.'
);
sr_notification_runtime_assert(
    is_string($notificationHelpers)
        && str_contains($notificationHelpers, 'function sr_notification_admin_mark_unread(')
        && str_contains($notificationHelpers, 'AND account_id = :account_id'),
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

if ($errors !== []) {
    fwrite(STDERR, "notification runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "notification runtime checks completed.\n";
