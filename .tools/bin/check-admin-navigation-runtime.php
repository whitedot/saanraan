#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/admin/helpers.php';
require_once $root . '/modules/admin/helpers/navigation.php';

$errors = [];

function sr_admin_navigation_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_admin_navigation_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_admin_navigation_runtime_error($message);
    }
}

function sr_admin_navigation_runtime_pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec(
        'CREATE TABLE sr_modules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_key TEXT NOT NULL,
            status TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_admin_account_roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            role_key TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_admin_account_permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            menu_path TEXT NOT NULL,
            action_key TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        "INSERT INTO sr_modules (module_key, status)
         VALUES
            ('member', 'enabled'),
            ('member_oauth', 'enabled'),
            ('identity_verification', 'enabled'),
            ('message', 'enabled'),
            ('community', 'enabled'),
            ('seo', 'enabled'),
            ('notification', 'enabled')"
    );
    $pdo->exec(
        "INSERT INTO sr_admin_account_roles (account_id, role_key, created_at)
         VALUES (1, 'owner', '2026-06-11 00:00:00')"
    );
    $pdo->exec(
        "INSERT INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at)
         VALUES
            (2, '/admin/seo', 'view', '2026-06-11 00:00:00'),
            (2, '/admin/seo', 'edit', '2026-06-11 00:00:00'),
            (3, '/admin/operations', 'view', '2026-06-11 00:00:00'),
            (3, '/admin/storage-cache', 'view', '2026-06-11 00:00:00')"
    );

    return $pdo;
}

$pdo = sr_admin_navigation_runtime_pdo();
$groups = sr_admin_navigation_groups($pdo);
$allItems = [];
foreach ($groups as $group) {
    foreach ((array) ($group['items'] ?? []) as $item) {
        if (is_array($item)) {
            $allItems[(string) ($item['path'] ?? '')] = $item;
        }
    }
}

sr_admin_navigation_runtime_assert(isset($allItems['/admin']), 'Admin navigation runtime fixture must include built-in dashboard.');
sr_admin_navigation_runtime_assert(isset($allItems['/admin/seo']), 'Admin navigation runtime fixture must include SEO menu item with GET route.');
sr_admin_navigation_runtime_assert(isset($allItems['/admin/storage-cache']), 'Admin navigation runtime fixture must include storage cache menu item with GET route.');
sr_admin_navigation_runtime_assert(isset($allItems['/admin/notifications']), 'Admin navigation runtime fixture must include notification menu item with GET route.');
sr_admin_navigation_runtime_assert(isset($allItems['/admin/notification-deliveries']), 'Admin navigation runtime fixture must include notification deliveries menu item with GET route.');
sr_admin_navigation_runtime_assert(!isset($allItems['/admin/notifications/new']), 'Admin navigation runtime fixture must not expose non-menu GET routes.');
foreach ($allItems as $path => $item) {
    if (preg_match('/\A\/admin(?:\/[a-z0-9][a-z0-9_-]*)*\z/', $path) !== 1) {
        sr_admin_navigation_runtime_error('Admin navigation runtime fixture exposed unsafe menu path: ' . $path);
    }
}

$moduleGroups = [];
foreach ($groups as $group) {
    foreach ((array) ($group['module_groups'] ?? []) as $moduleGroup) {
        if (is_array($moduleGroup)) {
            $moduleGroups[(string) ($moduleGroup['module_key'] ?? '')] = $moduleGroup;
        }
    }
}
sr_admin_navigation_runtime_assert(isset($moduleGroups['seo']), 'Admin navigation runtime fixture must expose SEO module menu group.');
sr_admin_navigation_runtime_assert(isset($moduleGroups['member']), 'Admin navigation runtime fixture must expose member module menu group.');
sr_admin_navigation_runtime_assert(isset($moduleGroups['member_oauth']), 'Admin navigation runtime fixture must expose member OAuth module menu group.');
sr_admin_navigation_runtime_assert(isset($moduleGroups['identity_verification']), 'Admin navigation runtime fixture must expose identity verification module menu group.');
sr_admin_navigation_runtime_assert(isset($moduleGroups['message']), 'Admin navigation runtime fixture must expose message module menu group.');
sr_admin_navigation_runtime_assert(isset($moduleGroups['community']), 'Admin navigation runtime fixture must expose community module menu group.');
sr_admin_navigation_runtime_assert(isset($moduleGroups['notification']), 'Admin navigation runtime fixture must expose notification module menu group.');
sr_admin_navigation_runtime_assert((int) ($moduleGroups['seo']['order'] ?? 0) === 50, 'Admin navigation runtime fixture must use SEO module admin menu order.');
sr_admin_navigation_runtime_assert((int) ($moduleGroups['message']['order'] ?? 0) === 13, 'Admin navigation runtime fixture must place message directly after identity verification in the member category.');
$memberGroupKeys = [];
foreach ($groups as $group) {
    if ((string) ($group['category'] ?? '') !== 'member') {
        continue;
    }
    $memberGroupKeys = array_map(
        static fn (array $moduleGroup): string => (string) ($moduleGroup['module_key'] ?? ''),
        array_values(array_filter((array) ($group['module_groups'] ?? []), 'is_array'))
    );
}
sr_admin_navigation_runtime_assert($memberGroupKeys !== [], 'Admin navigation runtime fixture must include a member category.');
$identityMenuPosition = array_search('identity_verification', $memberGroupKeys, true);
$messageMenuPosition = array_search('message', $memberGroupKeys, true);
sr_admin_navigation_runtime_assert(
    is_int($identityMenuPosition) && is_int($messageMenuPosition) && $messageMenuPosition === $identityMenuPosition + 1,
    'Admin navigation runtime fixture must place message after identity verification in the member sidebar category.'
);
$communityBoardMenuItem = [];
foreach ((array) ($moduleGroups['community']['items'] ?? []) as $communityMenuItem) {
    if (!is_array($communityMenuItem) || (string) ($communityMenuItem['path'] ?? '') !== '/admin/community/boards') {
        continue;
    }
    $communityBoardMenuItem = $communityMenuItem;
    break;
}
sr_admin_navigation_runtime_assert($communityBoardMenuItem !== [], 'Admin navigation runtime fixture must expose the community board management menu item.');
sr_admin_navigation_runtime_assert(
    in_array('/admin/community/board-copy-jobs', (array) ($communityBoardMenuItem['active_paths'] ?? []), true),
    'Admin navigation runtime fixture must preserve board copy job active path aliases after menu normalization.'
);
$notificationMenuPaths = array_map(
    static fn (array $item): string => (string) ($item['path'] ?? ''),
    array_values(array_filter((array) ($moduleGroups['notification']['items'] ?? []), 'is_array'))
);
sr_admin_navigation_runtime_assert(
    ($notificationMenuPaths[0] ?? '') === '/admin/notifications/settings',
    'Admin navigation runtime fixture must place notification settings first in the notification submenu.'
);

sr_admin_navigation_runtime_assert(sr_admin_first_permitted_menu_path($pdo, 2) === '/admin/seo', 'Admin navigation runtime fixture must return first permitted module menu for limited admin.');
sr_admin_navigation_runtime_assert(sr_admin_first_permitted_menu_path($pdo, 3) === '/admin/operations', 'Admin navigation runtime fixture must return first permitted built-in menu for limited admin.');
sr_admin_navigation_runtime_assert(sr_admin_first_permitted_menu_path($pdo, 4) === '', 'Admin navigation runtime fixture must return empty first menu for accounts without permissions.');
sr_admin_navigation_runtime_assert(sr_admin_has_permission($pdo, 1, '/admin/settings', 'delete'), 'Admin navigation runtime fixture must allow owner permission across admin menus.');
sr_admin_navigation_runtime_assert(sr_admin_has_permission($pdo, 2, '/admin/seo', 'edit'), 'Admin navigation runtime fixture must allow explicitly granted edit permission.');
sr_admin_navigation_runtime_assert(!sr_admin_has_permission($pdo, 2, '/admin/seo', 'delete'), 'Admin navigation runtime fixture must not infer ungranted delete permission.');
sr_admin_navigation_runtime_assert(sr_admin_has_permission($pdo, 3, '/admin/storage-cache', 'view'), 'Admin navigation runtime fixture must allow storage cache view permission.');
sr_admin_navigation_runtime_assert(!sr_admin_has_permission($pdo, 3, '/admin/storage-cache', 'delete'), 'Admin navigation runtime fixture must not infer storage cache delete permission.');
sr_admin_navigation_runtime_assert(!sr_admin_has_permission($pdo, 2, '/admin/../unsafe', 'view'), 'Admin navigation runtime fixture must reject unsafe permission paths.');
sr_admin_navigation_runtime_assert(
    sr_admin_shell_active_menu_path('/admin/community/board-copy-jobs', [
        [
            'label' => '게시판 관리',
            'path' => '/admin/community/boards',
            'active_paths' => ['/admin/community/board-copy-jobs'],
        ],
    ]) === '/admin/community/boards',
    'Admin shell active menu path should support item active_paths aliases.'
);

$communityAdminMenu = is_file('modules/community/admin-menu.php') ? file_get_contents('modules/community/admin-menu.php') : false;
sr_admin_navigation_runtime_assert(
    is_string($communityAdminMenu) && str_contains($communityAdminMenu, "'active_paths' => ['/admin/community/board-copy-jobs']"),
    'Community admin menu should keep board copy jobs active under board management.'
);

if ($errors !== []) {
    fwrite(STDERR, "admin navigation runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "admin navigation runtime checks completed.\n";
