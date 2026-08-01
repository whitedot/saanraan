#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/notification/public-notification-summary.php';

sr_set_runtime_config([
    'app_key' => 'notification-summary-fixture-key',
    'base_url' => 'http://localhost',
]);

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(
    'CREATE TABLE sr_notifications (
        id INTEGER PRIMARY KEY,
        account_id INTEGER NULL,
        audience TEXT NOT NULL,
        title TEXT NOT NULL,
        body_text TEXT NOT NULL,
        body_format TEXT NOT NULL,
        link_url TEXT NOT NULL,
        source_module_key TEXT NOT NULL,
        event_key TEXT NOT NULL,
        metadata_json TEXT NULL,
        read_at TEXT NULL,
        created_at TEXT NOT NULL
    );
    CREATE TABLE sr_notification_reads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        notification_id INTEGER NOT NULL,
        account_id INTEGER NOT NULL,
        read_at TEXT NULL
    );'
);
$pdo->exec(
    "INSERT INTO sr_notifications
        (id, account_id, audience, title, body_text, body_format, link_url, source_module_key, event_key, metadata_json, read_at, created_at)
     VALUES
        (1, 7, 'account', '계정 알림', '계정 본문', 'plain', '/account/messages', 'message', 'message.received', '{}', NULL, '2026-08-01 10:00:00'),
        (2, NULL, 'all', '', '전체 본문', 'plain', 'https://example.test/notice', 'core', 'site.notice', '{}', NULL, '2026-08-01 09:00:00'),
        (3, 7, 'account', '읽은 알림', '읽은 본문', 'plain', '', 'core', 'read', '{}', '2026-08-01 08:00:00', '2026-08-01 08:00:00');
     INSERT INTO sr_notification_reads (notification_id, account_id, read_at)
        VALUES (2, 8, '2026-08-01 11:00:00');"
);

$summary = sr_notification_public_header_summary($pdo, 7, 5);
$otherSummary = sr_notification_public_header_summary($pdo, 8, 5);
$guestSummary = sr_notification_public_header_summary($pdo, 0, 5);
$assert(
    ($summary['unread'] ?? null) === 2
        && count((array) ($summary['items'] ?? [])) === 2
        && ($summary['items'][0]['title'] ?? '') === '알림',
    'public notification summary must own account/global unread filtering and the empty-title fallback.'
);
$assert(
    ($otherSummary['unread'] ?? null) === 0 && ($otherSummary['items'] ?? null) === [],
    'public notification summary must respect account-specific reads for global notifications.'
);
$assert(
    $guestSummary === ['unread' => 0, 'items' => []],
    'public notification summary must keep the guest fallback empty.'
);

$markReadAttributes = sr_notification_item_link_attributes((array) $summary['items'][1], 7, true);
$externalAttributes = sr_notification_item_link_attributes((array) $summary['items'][0], 7, false);
$assert(
    str_contains($markReadAttributes, '/account/notifications/read?')
        && str_contains($markReadAttributes, 'token='),
    'public notification links must use the provider-owned signed mark-read route.'
);
$assert(
    str_contains($externalAttributes, 'target="_blank"')
        && str_contains($externalAttributes, 'rel="noopener noreferrer"'),
    'public external notification links must keep safe target attributes.'
);
$assert(
    str_contains(sr_notification_time_html('2026-08-01 10:00:00'), '<time'),
    'public notification time renderer must preserve machine-readable time markup.'
);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "public layout notification summary checks completed.\n");
