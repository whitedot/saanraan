#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/admin/helpers.php';
require_once $root . '/modules/member/helpers.php';
require_once $root . '/modules/community/helpers.php';

$errors = [];

function sr_community_message_policy_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_community_message_policy_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_community_message_policy_error($message);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec(
    "CREATE TABLE sr_admin_account_roles (
        account_id INTEGER NOT NULL,
        role_key TEXT NOT NULL,
        created_at TEXT NOT NULL,
        UNIQUE(account_id, role_key)
    )"
);
$pdo->exec(
    "CREATE TABLE sr_admin_account_permissions (
        account_id INTEGER NOT NULL,
        menu_path TEXT NOT NULL,
        action_key TEXT NOT NULL,
        created_at TEXT NOT NULL,
        UNIQUE(account_id, menu_path, action_key)
    )"
);
$pdo->exec(
    "CREATE TABLE sr_community_levels (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        level_value INTEGER NOT NULL,
        title TEXT NOT NULL DEFAULT '',
        description TEXT NOT NULL DEFAULT '',
        badge_color TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'enabled',
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )"
);
$pdo->exec(
    "CREATE TABLE sr_community_account_levels (
        account_id INTEGER PRIMARY KEY,
        level_value INTEGER NOT NULL DEFAULT 0,
        score_value INTEGER NOT NULL DEFAULT 0,
        post_count INTEGER NOT NULL DEFAULT 0,
        comment_count INTEGER NOT NULL DEFAULT 0,
        evaluated_at TEXT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )"
);
$pdo->exec(
    "CREATE TABLE sr_community_level_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        before_level_value INTEGER NOT NULL DEFAULT 0,
        after_level_value INTEGER NOT NULL DEFAULT 0,
        before_score_value INTEGER NOT NULL DEFAULT 0,
        after_score_value INTEGER NOT NULL DEFAULT 0,
        reason_key TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL
    )"
);
$pdo->exec(
    "CREATE TABLE sr_member_groups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_key TEXT NOT NULL,
        title TEXT NOT NULL DEFAULT '',
        description TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'enabled',
        is_system INTEGER NOT NULL DEFAULT 0,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )"
);
$pdo->exec(
    "CREATE TABLE sr_member_group_memberships (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_id INTEGER NOT NULL,
        account_id INTEGER NOT NULL,
        assignment_type TEXT NOT NULL DEFAULT 'manual',
        source_module_key TEXT NOT NULL DEFAULT '',
        source_rule_key TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'active',
        granted_at TEXT NOT NULL,
        expires_at TEXT NULL,
        revoked_at TEXT NULL,
        created_by_account_id INTEGER NULL,
        updated_at TEXT NOT NULL
    )"
);

$now = sr_now();
$insertRole = $pdo->prepare('INSERT INTO sr_admin_account_roles (account_id, role_key, created_at) VALUES (:account_id, :role_key, :created_at)');
$insertRole->execute(['account_id' => 1, 'role_key' => 'owner', 'created_at' => $now]);

$insertPermission = $pdo->prepare('INSERT INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at) VALUES (:account_id, :menu_path, :action_key, :created_at)');
$insertPermission->execute(['account_id' => 2, 'menu_path' => '/admin/members', 'action_key' => 'view', 'created_at' => $now]);
$insertPermission->execute(['account_id' => 3, 'menu_path' => '/admin/community/posts', 'action_key' => 'view', 'created_at' => $now]);

$insertLevel = $pdo->prepare('INSERT INTO sr_community_account_levels (account_id, level_value, score_value, post_count, comment_count, evaluated_at, created_at, updated_at) VALUES (:account_id, :level_value, 0, 0, 0, :evaluated_at, :created_at, :updated_at)');
$insertLevel->execute(['account_id' => 5, 'level_value' => 3, 'evaluated_at' => $now, 'created_at' => $now, 'updated_at' => $now]);

$restrictedSettings = [
    'message_write_policy' => 'group',
    'message_write_group_keys' => ['vip'],
    'message_write_min_level' => 7,
    'level_enabled' => true,
    'level_max_value' => 10,
];
$memberLevelSettings = [
    'message_write_policy' => 'member',
    'message_write_group_keys' => [],
    'message_write_min_level' => 3,
    'level_enabled' => true,
    'level_max_value' => 10,
];
$disabledSettings = [
    'message_write_policy' => 'disabled',
    'message_write_group_keys' => [],
    'message_write_min_level' => 0,
    'level_enabled' => true,
    'level_max_value' => 10,
];

sr_community_message_policy_assert(
    sr_community_account_can_write_message($pdo, ['id' => 1], $restrictedSettings),
    'Owner manager must bypass message write group and level conditions.'
);
sr_community_message_policy_assert(
    sr_community_account_can_write_message($pdo, ['id' => 2], $restrictedSettings),
    'Staff with /admin/members view permission must bypass message write group and level conditions.'
);
sr_community_message_policy_assert(
    !sr_community_account_can_write_message($pdo, ['id' => 3], $restrictedSettings),
    'Staff without member management permission must not bypass message write conditions.'
);
sr_community_message_policy_assert(
    !sr_community_account_can_write_message($pdo, ['id' => 4], $restrictedSettings),
    'Regular member must not bypass message write group and level conditions.'
);
sr_community_message_policy_assert(
    sr_community_account_can_write_message($pdo, ['id' => 5], $memberLevelSettings),
    'Regular member who satisfies configured min level must be able to send messages.'
);
sr_community_message_policy_assert(
    !sr_community_account_can_write_message($pdo, ['id' => 1], $disabledSettings),
    'Disabled message policy must block owner managers too.'
);
sr_community_message_policy_assert(
    !sr_community_account_can_write_message($pdo, ['id' => 2], $disabledSettings),
    'Disabled message policy must block member-management staff too.'
);

if ($errors !== []) {
    fwrite(STDERR, "community message policy checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "community message policy checks completed.\n";
