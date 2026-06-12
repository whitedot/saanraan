#!/usr/bin/env php
<?php

declare(strict_types=1);

define('SR_ROOT', dirname(__DIR__, 2));
chdir(SR_ROOT);

require_once SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/site_menu/helpers.php';

$errors = [];

function sr_site_menu_check_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_site_menu_check_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_site_menu_check_error($message);
    }
}

$options = [];
foreach (['content', 'quiz', 'survey', 'community'] as $moduleKey) {
    $metadata = sr_module_metadata($moduleKey);
    $serviceDomain = is_array($metadata['service_domain'] ?? null) ? $metadata['service_domain'] : [];
    $mainPage = is_array($serviceDomain['main_page'] ?? null) ? $serviceDomain['main_page'] : [];
    $options[$moduleKey] = [
        'label' => (string) ($mainPage['label'] ?? $moduleKey),
        'path' => (string) ($mainPage['path'] ?? ''),
    ];
}

$items = sr_site_menu_seed_default_header_menu_items($options, ['survey', 'quiz', 'community', 'content']);
$labels = array_map('strval', array_column($items, 'label'));
$expected = ['홈', '콘텐츠 메인', '커뮤니티 홈', '퀴즈 메인', '설문 메인'];
if ($labels !== $expected) {
    sr_site_menu_check_error('Site menu seed order must follow admin service menu order: ' . implode(' > ', $labels));
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(
    'CREATE TABLE sr_site_menus (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        menu_key TEXT NOT NULL UNIQUE,
        label TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT \'enabled\',
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )'
);
$pdo->exec(
    'CREATE TABLE sr_site_menu_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        menu_id INTEGER NOT NULL,
        parent_id INTEGER NULL,
        label TEXT NOT NULL,
        url TEXT NOT NULL,
        icon_name TEXT NOT NULL DEFAULT \'\',
        target TEXT NOT NULL DEFAULT \'self\',
        status TEXT NOT NULL DEFAULT \'enabled\',
        sort_order INTEGER NOT NULL DEFAULT 100,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )'
);
$pdo->exec(
    "INSERT INTO sr_site_menus (id, menu_key, label, status, created_at, updated_at) VALUES
        (1, 'header', '상단 메뉴', 'enabled', '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
        (2, 'footer', '하단 메뉴', 'disabled', '2026-06-11 00:00:00', '2026-06-11 00:00:00')"
);
$pdo->exec(
    "INSERT INTO sr_site_menu_items (id, menu_id, parent_id, label, url, target, status, sort_order, created_at, updated_at) VALUES
        (1, 1, NULL, '홈', '/', 'self', 'enabled', 10, '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
        (2, 1, NULL, '커뮤니티', '/community/board?key=free', 'self', 'enabled', 20, '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
        (3, 1, 2, '글 보기', '/community/post?id=42', 'self', 'enabled', 10, '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
        (4, 1, 3, '외부 문서', 'https://example.test/docs', 'blank', 'enabled', 10, '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
        (5, 1, 4, '너무 깊은 항목', '/too-deep', 'self', 'enabled', 10, '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
        (6, 1, NULL, '비활성', '/disabled', 'self', 'disabled', 30, '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
        (7, 2, NULL, '숨김 메뉴', '/hidden', 'self', 'enabled', 10, '2026-06-11 00:00:00', '2026-06-11 00:00:00')"
);
$pdo->exec("CREATE TABLE sr_community_boards (id INTEGER PRIMARY KEY, board_key TEXT NOT NULL)");
$pdo->exec("CREATE TABLE sr_community_posts (id INTEGER PRIMARY KEY, board_id INTEGER NOT NULL)");
$pdo->exec("INSERT INTO sr_community_boards (id, board_key) VALUES (1, 'free')");
$pdo->exec("INSERT INTO sr_community_posts (id, board_id) VALUES (42, 1)");

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/community/post?id=42';
$html = sr_site_menu_render($pdo, 'header', 'navigation');
sr_site_menu_check_assert(str_contains($html, 'class="sr-site-menu sr-site-menu-header sr-site-menu-slot-navigation"'), 'Site menu render runtime fixture must include menu and slot classes.');
sr_site_menu_check_assert(str_contains($html, 'href="/"'), 'Site menu render runtime fixture must render root link.');
sr_site_menu_check_assert(str_contains($html, 'href="/community/board?key=free"'), 'Site menu render runtime fixture must render relative links.');
sr_site_menu_check_assert(substr_count($html, 'aria-current="page"') >= 2, 'Site menu render runtime fixture must mark current post and matching community board.');
sr_site_menu_check_assert(str_contains($html, 'target="_blank" rel="noopener noreferrer"'), 'Site menu render runtime fixture must protect blank external links.');
sr_site_menu_check_assert(!str_contains($html, '너무 깊은 항목'), 'Site menu render runtime fixture must stop at depth 3.');
sr_site_menu_check_assert(!str_contains($html, '비활성'), 'Site menu render runtime fixture must skip disabled items.');
sr_site_menu_check_assert(sr_site_menu_render($pdo, 'footer', 'secondary_navigation') === '', 'Site menu render runtime fixture must skip disabled menus.');

$_SERVER['REQUEST_URI'] = '/content/example';
sr_site_menu_check_assert(sr_site_menu_item_href('/login') === '/login?next=%2Fcontent%2Fexample', 'Site menu login link must include safe current next path.');
$_SERVER['REQUEST_URI'] = '/login';
sr_site_menu_check_assert(sr_site_menu_item_href('/login') === '/login', 'Site menu login link must not include next on login page.');
sr_site_menu_check_assert(sr_site_menu_clean_url('javascript:alert(1)') === '', 'Site menu URL cleaner must reject unsafe pseudo URLs.');
sr_site_menu_check_assert(sr_site_menu_item_href('javascript:alert(1)') === '#', 'Site menu href helper must fail closed for unsafe URLs.');

if ($errors !== []) {
    fwrite(STDERR, "site menu checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "site menu seed order checks completed.\n";
