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
            (3, '/admin/operations', 'view', '2026-06-11 00:00:00')"
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
sr_admin_navigation_runtime_assert(isset($moduleGroups['notification']), 'Admin navigation runtime fixture must expose notification module menu group.');
sr_admin_navigation_runtime_assert((int) ($moduleGroups['seo']['order'] ?? 0) === 50, 'Admin navigation runtime fixture must use SEO module admin menu order.');
$notificationMenuPaths = array_map(
    static fn (array $item): string => (string) ($item['path'] ?? ''),
    array_values(array_filter((array) ($moduleGroups['notification']['items'] ?? []), 'is_array'))
);
sr_admin_navigation_runtime_assert(
    array_search('/admin/admin-notifications', $notificationMenuPaths, true) === array_search('/admin/notifications/settings', $notificationMenuPaths, true) - 1,
    'Admin navigation runtime fixture must place admin notifications directly before notification settings.'
);

sr_admin_navigation_runtime_assert(sr_admin_first_permitted_menu_path($pdo, 2) === '/admin/seo', 'Admin navigation runtime fixture must return first permitted module menu for limited admin.');
sr_admin_navigation_runtime_assert(sr_admin_first_permitted_menu_path($pdo, 3) === '/admin/operations', 'Admin navigation runtime fixture must return first permitted built-in menu for limited admin.');
sr_admin_navigation_runtime_assert(sr_admin_first_permitted_menu_path($pdo, 4) === '', 'Admin navigation runtime fixture must return empty first menu for accounts without permissions.');
sr_admin_navigation_runtime_assert(sr_admin_has_permission($pdo, 1, '/admin/settings', 'delete'), 'Admin navigation runtime fixture must allow owner permission across admin menus.');
sr_admin_navigation_runtime_assert(sr_admin_has_permission($pdo, 2, '/admin/seo', 'edit'), 'Admin navigation runtime fixture must allow explicitly granted edit permission.');
sr_admin_navigation_runtime_assert(!sr_admin_has_permission($pdo, 2, '/admin/seo', 'delete'), 'Admin navigation runtime fixture must not infer ungranted delete permission.');
sr_admin_navigation_runtime_assert(!sr_admin_has_permission($pdo, 2, '/admin/../unsafe', 'view'), 'Admin navigation runtime fixture must reject unsafe permission paths.');

if ($errors !== []) {
    fwrite(STDERR, "admin navigation runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "admin navigation runtime checks completed.\n";
