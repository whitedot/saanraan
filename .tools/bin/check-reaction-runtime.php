#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/reaction/helpers.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(
    'CREATE TABLE sr_site_settings (
        setting_key TEXT NOT NULL PRIMARY KEY,
        setting_value TEXT NOT NULL,
        value_type TEXT NOT NULL
    );
    CREATE TABLE sr_modules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_key TEXT NOT NULL,
        version TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL
    );
    CREATE TABLE sr_reaction_definitions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reaction_key TEXT NOT NULL UNIQUE,
        label TEXT NOT NULL,
        icon_type TEXT NOT NULL DEFAULT \'emoji\',
        icon_value TEXT NOT NULL DEFAULT \'\',
        color_hex TEXT NOT NULL DEFAULT \'\',
        color_swatch TEXT NOT NULL DEFAULT \'\',
        description TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'active\',
        sort_order INTEGER NOT NULL DEFAULT 100,
        is_seed INTEGER NOT NULL DEFAULT 0,
        created_by_account_id INTEGER NULL,
        updated_by_account_id INTEGER NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );
    CREATE TABLE sr_reaction_presets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        preset_key TEXT NOT NULL UNIQUE,
        label TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'active\',
        selection_policy TEXT NOT NULL DEFAULT \'single\',
        visible_key_limit INTEGER NOT NULL DEFAULT 6,
        sort_order INTEGER NOT NULL DEFAULT 100,
        created_by_account_id INTEGER NULL,
        updated_by_account_id INTEGER NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );
    CREATE TABLE sr_reaction_preset_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        preset_key TEXT NOT NULL,
        reaction_key TEXT NOT NULL,
        sort_order INTEGER NOT NULL DEFAULT 100,
        is_public INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE (preset_key, reaction_key)
    );
    CREATE TABLE sr_reaction_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        target_module TEXT NOT NULL,
        target_type TEXT NOT NULL,
        target_id TEXT NOT NULL,
        reaction_key TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE (account_id, target_module, target_type, target_id)
    );'
);

$now = sr_now();
$pdo->exec(
    "INSERT INTO sr_site_settings (setting_key, setting_value, value_type) VALUES ('reaction_default_preset_key', 'emotions', 'string');
     INSERT INTO sr_modules (module_key, version, status) VALUES ('reaction', '2026.06.001', 'enabled');
     INSERT INTO sr_reaction_definitions (reaction_key, label, status, created_at, updated_at) VALUES
        ('like', '좋아요', 'active', '$now', '$now'),
        ('sad', '슬퍼요', 'active', '$now', '$now'),
        ('disabled', '중지됨', 'disabled', '$now', '$now');
     INSERT INTO sr_reaction_presets (preset_key, label, status, selection_policy, visible_key_limit, created_at, updated_at)
        VALUES ('emotions', '감정형', 'active', 'single', 6, '$now', '$now');
     INSERT INTO sr_reaction_preset_items (preset_key, reaction_key, sort_order, is_public, created_at, updated_at) VALUES
        ('emotions', 'like', 10, 1, '$now', '$now'),
        ('emotions', 'sad', 20, 1, '$now', '$now'),
        ('emotions', 'disabled', 30, 1, '$now', '$now');"
);

$target = [
    'status' => 'active',
    'can_view' => true,
    'can_write' => true,
    'owner_account_id' => 7,
    'recipient_account_id' => 7,
    'preset_key' => 'emotions',
    'label' => '테스트 글',
    'public_url' => '/test/1',
];

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

$first = sr_reaction_write($pdo, 3, 'community', 'post', '1', 'like', 'apply', ['resolved_target' => $target]);
$assert($first['ok'] === true && $first['operation'] === 'apply' && $first['my_reaction_key'] === 'like', 'first apply should create a like reaction.');
$assert((int) ($first['counts']['like'] ?? 0) === 1, 'first apply should count one like.');

$repeat = sr_reaction_write($pdo, 3, 'community', 'post', '1', 'like', 'apply', ['resolved_target' => $target]);
$assert($repeat['ok'] === true && $repeat['operation'] === 'noop' && $repeat['changed'] === false, 'same apply should be idempotent.');

$change = sr_reaction_write($pdo, 3, 'community', 'post', '1', 'sad', 'apply', ['resolved_target' => $target]);
$assert($change['ok'] === true && $change['operation'] === 'change' && $change['my_reaction_key'] === 'sad', 'different apply should replace the row.');
$assert((int) ($change['counts']['sad'] ?? 0) === 1 && (int) ($change['counts']['like'] ?? 0) === 0, 'change should move the count.');

$toggle = sr_reaction_write($pdo, 3, 'community', 'post', '1', 'sad', 'toggle', ['resolved_target' => $target]);
$assert($toggle['ok'] === true && $toggle['operation'] === 'cancel' && $toggle['my_reaction_key'] === '', 'same toggle should cancel.');

$self = sr_reaction_write($pdo, 7, 'community', 'post', '1', 'like', 'apply', ['resolved_target' => $target]);
$assert($self['ok'] === false && $self['error'] === 'self_reaction_not_allowed', 'owner should not react to own target.');

$privateTarget = array_merge($target, ['status' => 'private', 'can_view' => false, 'can_write' => false]);
$blocked = sr_reaction_write($pdo, 3, 'community', 'post', '2', 'like', 'apply', ['resolved_target' => $privateTarget]);
$assert($blocked['ok'] === false && $blocked['error'] === 'target_not_writable', 'private target should block new write.');
$assert(
    sr_reaction_create_account_event($pdo, 7, 3, $privateTarget, 'like') === false,
    'non-writable target should not create a reaction notification.'
);

$notificationDisabledTarget = array_merge($target, ['notification_enabled' => false]);
$assert(
    sr_reaction_create_account_event($pdo, 7, 3, $notificationDisabledTarget, 'like') === false,
    'target contract should be able to disable reaction notifications.'
);

$disabled = sr_reaction_write($pdo, 3, 'community', 'post', '3', 'disabled', 'apply', ['resolved_target' => $target]);
$assert($disabled['ok'] === false && $disabled['error'] === 'reaction_not_allowed', 'disabled reaction key should block new write.');

if ($errors !== []) {
    fwrite(STDERR, "reaction runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "reaction runtime checks completed.\n";
