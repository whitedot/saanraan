#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/message/public-message-summary.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(
    'CREATE TABLE sr_modules (
        id INTEGER PRIMARY KEY,
        module_key TEXT NOT NULL UNIQUE,
        version TEXT NOT NULL,
        status TEXT NOT NULL
    );
    CREATE TABLE sr_module_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_id INTEGER NOT NULL,
        setting_key TEXT NOT NULL,
        setting_value TEXT,
        value_type TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );
    CREATE TABLE sr_messages (
        id INTEGER PRIMARY KEY,
        recipient_account_id INTEGER NOT NULL,
        recipient_deleted_at TEXT NULL,
        read_at TEXT NULL
    );'
);
$pdo->exec(
    "INSERT INTO sr_modules (id, module_key, version, status) VALUES (1, 'message', 'test', 'enabled');
     INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type, created_at, updated_at)
        VALUES (1, 'message_enabled', '0', 'bool', '2026-08-01 00:00:00', '2026-08-01 00:00:00');
     INSERT INTO sr_messages (id, recipient_account_id, recipient_deleted_at, read_at) VALUES
        (1, 7, NULL, NULL),
        (2, 7, NULL, '2026-08-01 00:00:00'),
        (3, 7, '2026-08-01 00:00:00', NULL),
        (4, 8, NULL, NULL);"
);

$disabled = sr_message_public_summary_context($pdo, 7);
$assert(
    $disabled === ['enabled' => false, 'unread_count' => 0],
    'disabled message policy must hide the public menu and skip its unread result.'
);

$pdo->exec("UPDATE sr_module_settings SET setting_value = '1' WHERE setting_key = 'message_enabled'");
sr_clear_module_settings_cache('message');
$enabled = sr_message_public_summary_context($pdo, 7);
$guest = sr_message_public_summary_context($pdo, 0);
$assert(
    $enabled === ['enabled' => true, 'unread_count' => 1],
    'enabled message policy must expose only active unread messages for the current account.'
);
$assert(
    $guest === ['enabled' => true, 'unread_count' => 0],
    'message summary must not query an unread result for an invalid account id.'
);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "public layout message summary checks completed.\n");
