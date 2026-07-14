#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];
$source = static function (string $file) use ($root, &$errors): string {
    $contents = file_get_contents($root . '/' . $file);
    if (!is_string($contents)) {
        $errors[] = 'cannot read member communication source: ' . $file;
        return '';
    }

    return $contents;
};
$assertContains = static function (string $file, array $markers) use ($source, &$errors): void {
    $contents = $source($file);
    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $errors[] = $file . ' missing pagination marker: ' . $marker;
        }
    }
};

$assertContains('modules/notification/actions/account-notifications.php', [
    "sr_get_string('page'",
    "SELECT COUNT(*)' . \$notificationFromSql",
    'LIMIT :notification_limit OFFSET :notification_offset',
    '$notificationPaginationBasePath',
    'sr_redirect($notificationListPath)',
]);
$assertContains('modules/notification/views/account-notifications.php', [
    'id="notification-list"',
    'sr_public_pagination_html($notificationPagination',
    "\$filters['status'] !== 'read'",
    'sr_url($notificationListPath)',
]);
if (str_contains($source('modules/notification/actions/account-notifications.php'), 'LIMIT 100')) {
    $errors[] = 'member notification list still stops at 100 rows';
}

$assertContains('modules/message/actions/messages.php', [
    "sr_get_string('page'",
    'sr_message_box_count(',
    '$messagePagination',
    '$messagePerPage, ($messagePage - 1) * $messagePerPage',
]);
$assertContains('modules/message/helpers.php', [
    'function sr_message_box_count(',
    'int $limit = 50, int $offset = 0',
    'LIMIT :limit_value OFFSET :offset_value',
]);
$assertContains('modules/message/views/messages.php', [
    'id="message-list"',
    'name="return_page"',
    'sr_public_pagination_html($messagePagination',
]);
$assertContains('modules/message/actions/message-delete.php', [
    "sr_post_string('return_page'",
    "'/messages?box=sent' . \$returnPageQuery",
    "'/messages' . (\$returnPage > 1",
]);

require_once $root . '/modules/message/helpers.php';
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE sr_member_accounts (id INTEGER PRIMARY KEY, display_name TEXT, status TEXT)');
$pdo->exec("INSERT INTO sr_member_accounts (id, display_name, status) VALUES (1, 'one', 'active'), (2, 'two', 'active')");
$pdo->exec(
    'CREATE TABLE sr_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sender_account_id INTEGER NOT NULL,
        recipient_account_id INTEGER NOT NULL,
        status TEXT NOT NULL,
        read_at TEXT NULL,
        sender_deleted_at TEXT NULL,
        recipient_deleted_at TEXT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )'
);
$insert = $pdo->prepare(
    "INSERT INTO sr_messages
     (sender_account_id, recipient_account_id, status, read_at, sender_deleted_at, recipient_deleted_at, created_at, updated_at)
     VALUES (:sender_account_id, :recipient_account_id, 'sent', NULL, NULL, NULL, '2026-01-01 00:00:00', '2026-01-01 00:00:00')"
);
for ($rowNumber = 1; $rowNumber <= 45; $rowNumber++) {
    $insert->execute(['sender_account_id' => 1, 'recipient_account_id' => 2]);
}
for ($rowNumber = 1; $rowNumber <= 45; $rowNumber++) {
    $insert->execute(['sender_account_id' => 2, 'recipient_account_id' => 1]);
}

if (sr_message_box_count($pdo, 1, 'sent') !== 45 || sr_message_box_count($pdo, 1, 'inbox') !== 45) {
    $errors[] = 'message box counts must include every visible sent and received row';
}
$sentFinalPage = sr_message_box($pdo, 1, 'sent', 20, 40);
if (count($sentFinalPage) !== 5 || (int) ($sentFinalPage[0]['id'] ?? 0) !== 5 || (int) ($sentFinalPage[4]['id'] ?? 0) !== 1) {
    $errors[] = 'sent message pagination must expose the final partial page';
}
$inboxSecondPage = sr_message_box($pdo, 1, 'inbox', 20, 20);
if (count($inboxSecondPage) !== 20 || (int) ($inboxSecondPage[0]['id'] ?? 0) !== 70 || (int) ($inboxSecondPage[19]['id'] ?? 0) !== 51) {
    $errors[] = 'inbox pagination must return the requested ordered slice';
}

if ($errors !== []) {
    fwrite(STDERR, "member communication pagination checks failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "member communication pagination checks completed.\n";
