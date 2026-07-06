#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/admin/helpers.php';
require_once $root . '/modules/member/helpers.php';
require_once $root . '/modules/message/helpers.php';

$errors = [];

function sr_message_policy_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_message_policy_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_message_policy_error($message);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec("CREATE TABLE sr_admin_account_roles (account_id INTEGER NOT NULL, role_key TEXT NOT NULL, created_at TEXT NOT NULL, UNIQUE(account_id, role_key))");
$pdo->exec("CREATE TABLE sr_admin_account_permissions (account_id INTEGER NOT NULL, menu_path TEXT NOT NULL, action_key TEXT NOT NULL, created_at TEXT NOT NULL, UNIQUE(account_id, menu_path, action_key))");
$pdo->exec("CREATE TABLE sr_member_groups (id INTEGER PRIMARY KEY AUTOINCREMENT, group_key TEXT NOT NULL, title TEXT NOT NULL DEFAULT '', description TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'enabled', is_system INTEGER NOT NULL DEFAULT 0, sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
$pdo->exec("CREATE TABLE sr_member_group_memberships (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL, account_id INTEGER NOT NULL, assignment_type TEXT NOT NULL DEFAULT 'manual', source_module_key TEXT NOT NULL DEFAULT '', source_rule_key TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'active', granted_at TEXT NOT NULL, expires_at TEXT NULL, revoked_at TEXT NULL, created_by_account_id INTEGER NULL, updated_at TEXT NOT NULL)");
$pdo->exec("CREATE TABLE sr_member_group_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL, source_module_key TEXT NOT NULL DEFAULT '', rule_key TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'enabled', evaluation_policy TEXT NOT NULL DEFAULT 'grant_only', params_json TEXT NOT NULL DEFAULT '{}', last_evaluated_at TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
$pdo->exec("CREATE TABLE sr_member_group_membership_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL, account_id INTEGER NOT NULL, membership_id INTEGER NULL, event_type TEXT NOT NULL, actor_account_id INTEGER NULL, message TEXT NOT NULL DEFAULT '', metadata_json TEXT NULL, created_at TEXT NOT NULL)");
$pdo->exec("CREATE TABLE sr_message_member_settings (account_id INTEGER PRIMARY KEY, receive_enabled INTEGER NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");

$now = sr_now();
$pdo->prepare('INSERT INTO sr_admin_account_roles (account_id, role_key, created_at) VALUES (:account_id, :role_key, :created_at)')
    ->execute(['account_id' => 1, 'role_key' => 'owner', 'created_at' => $now]);
$pdo->prepare('INSERT INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at) VALUES (:account_id, :menu_path, :action_key, :created_at)')
    ->execute(['account_id' => 2, 'menu_path' => '/admin/message/settings', 'action_key' => 'edit', 'created_at' => $now]);
$pdo->prepare('INSERT INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at) VALUES (:account_id, :menu_path, :action_key, :created_at)')
    ->execute(['account_id' => 3, 'menu_path' => '/admin/community/posts', 'action_key' => 'view', 'created_at' => $now]);
$pdo->prepare('INSERT INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at) VALUES (:account_id, :menu_path, :action_key, :created_at)')
    ->execute(['account_id' => 8, 'menu_path' => '/admin/members', 'action_key' => 'view', 'created_at' => $now]);
$pdo->exec("INSERT INTO sr_member_groups (id, group_key, title, status, created_at, updated_at) VALUES (1, 'vip', 'VIP', 'enabled', '', '')");
$pdo->exec("INSERT INTO sr_member_group_memberships (group_id, account_id, status, granted_at, updated_at) VALUES (1, 5, 'active', '', '')");
$pdo->exec("INSERT INTO sr_message_member_settings (account_id, receive_enabled, created_at, updated_at) VALUES (6, 1, '', ''), (7, 0, '', '')");

$restrictedSettings = [
    'message_enabled' => true,
    'send_policy' => 'group',
    'send_group_keys' => ['vip'],
    'receive_policy' => 'all',
    'receive_group_keys' => [],
    'default_member_receive_enabled' => true,
];
$disabledSettings = [
    'message_enabled' => true,
    'send_policy' => 'disabled',
    'send_group_keys' => [],
    'receive_policy' => 'all',
    'receive_group_keys' => [],
    'default_member_receive_enabled' => true,
];
$optInSettings = [
    'message_enabled' => true,
    'send_policy' => 'all',
    'send_group_keys' => [],
    'receive_policy' => 'opt_in',
    'receive_group_keys' => [],
    'default_member_receive_enabled' => true,
];
$openSettings = [
    'message_enabled' => true,
    'send_policy' => 'all',
    'send_group_keys' => [],
    'receive_policy' => 'all',
    'receive_group_keys' => [],
    'default_member_receive_enabled' => true,
];
$receiveGroupSettings = [
    'message_enabled' => true,
    'send_policy' => 'all',
    'send_group_keys' => [],
    'receive_policy' => 'group',
    'receive_group_keys' => ['vip'],
    'default_member_receive_enabled' => true,
];

sr_message_policy_assert(
    sr_message_account_can_send($pdo, ['id' => 1], $restrictedSettings),
    'Owner manager must bypass message send group conditions.'
);
sr_message_policy_assert(
    sr_message_account_can_send($pdo, ['id' => 2], $restrictedSettings),
    'Staff with message edit permission must bypass message send group conditions.'
);
sr_message_policy_assert(
    sr_message_account_can_send($pdo, ['id' => 8], $restrictedSettings),
    'Staff with member list view permission must bypass message send group conditions.'
);
sr_message_policy_assert(
    !sr_message_account_can_send($pdo, ['id' => 3], $restrictedSettings),
    'Staff without message write permission must not bypass message send conditions.'
);
sr_message_policy_assert(
    !sr_message_account_can_send($pdo, ['id' => 4], $restrictedSettings),
    'Regular member outside allowed groups must not send when send_policy=group.'
);
sr_message_policy_assert(
    sr_message_account_can_send($pdo, ['id' => 5], $restrictedSettings),
    'Regular member in an allowed group must send when send_policy=group.'
);
sr_message_policy_assert(
    sr_message_account_can_send($pdo, ['id' => 1], $disabledSettings),
    'Manager bypass must still allow operational sends when send_policy=disabled.'
);
sr_message_policy_assert(
    sr_message_account_can_send($pdo, ['id' => 6], $openSettings),
    'Regular member with receive setting enabled must send when send_policy=all.'
);
sr_message_policy_assert(
    !sr_message_account_can_send($pdo, ['id' => 7], $openSettings),
    'Regular member with receive setting disabled must not send messages.'
);
sr_message_policy_assert(
    !sr_message_account_can_send($pdo, ['id' => 6], $receiveGroupSettings),
    'Regular member outside receive-allowed groups must not send messages.'
);
sr_message_policy_assert(
    sr_message_account_can_send($pdo, ['id' => 5], $receiveGroupSettings),
    'Regular member inside receive-allowed groups must send when send_policy=all.'
);
sr_message_policy_assert(
    sr_message_account_can_receive($pdo, ['id' => 6, 'status' => 'active'], ['id' => 4], $optInSettings),
    'Opt-in recipient with receive setting enabled must be receivable.'
);
sr_message_policy_assert(
    !sr_message_account_can_receive($pdo, ['id' => 7, 'status' => 'active'], ['id' => 4], $optInSettings),
    'Opt-in recipient with receive setting disabled must not be receivable.'
);
sr_message_policy_assert(
    sr_message_account_can_receive($pdo, ['id' => 7, 'status' => 'active'], ['id' => 2], $optInSettings),
    'Message staff bypass must override member receive opt-out for active accounts.'
);

if ($errors !== []) {
    fwrite(STDERR, "message policy checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "message policy checks completed.\n";
