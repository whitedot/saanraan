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

$siteMenuProvider = require SR_ROOT . '/modules/site_menu/site-menu-provider.php';
sr_site_menu_check_assert(is_array($siteMenuProvider), 'Site menu provider contract must return an array.');
foreach (['helpers', 'options_function', 'tree_function', 'render_function'] as $providerKey) {
    sr_site_menu_check_assert(
        is_string($siteMenuProvider[$providerKey] ?? null) && trim((string) $siteMenuProvider[$providerKey]) !== '',
        'Site menu provider contract must declare ' . $providerKey . '.'
    );
}
$siteMenuMetadata = sr_module_metadata('site_menu');
$siteMenuProvides = is_array($siteMenuMetadata['contracts']['provides'] ?? null) ? $siteMenuMetadata['contracts']['provides'] : [];
sr_site_menu_check_assert(in_array('site-menu-provider.php', $siteMenuProvides, true), 'Site menu module metadata must provide the site menu provider contract.');
$coreSettingsSource = (string) file_get_contents(SR_ROOT . '/core/helpers/settings.php');
sr_site_menu_check_assert(str_contains($coreSettingsSource, 'function sr_module_contract_invoke('), 'Core must expose a guarded module contract invocation helper.');
sr_site_menu_check_assert(str_contains($coreSettingsSource, 'module_contract_helper_load_failed_'), 'Core contract function resolution must isolate provider helper load failures.');
sr_site_menu_check_assert(str_contains($coreSettingsSource, '...array_values($arguments)'), 'Core contract invocation must pass consumer arguments positionally.');
$siteMenuHelpersSource = (string) file_get_contents(SR_ROOT . '/modules/site_menu/helpers.php');
sr_site_menu_check_assert(str_contains($siteMenuHelpersSource, 'site_menu_link_suggestions_failed_'), 'Site menu link providers must isolate callable failures.');

class SrSiteMenuCheckPdo extends PDO
{
    public int $siteMenuTreePrepareCount = 0;

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'FROM sr_site_menus m') && str_contains($query, 'JOIN sr_site_menu_items i')) {
            $this->siteMenuTreePrepareCount++;
        }

        return parent::prepare($query, $options);
    }
}

$options = [];
foreach (['content', 'community', 'quiz', 'survey'] as $moduleKey) {
    $metadata = sr_module_metadata($moduleKey);
    $serviceDomain = is_array($metadata['service_domain'] ?? null) ? $metadata['service_domain'] : [];
    $mainPage = is_array($serviceDomain['main_page'] ?? null) ? $serviceDomain['main_page'] : [];
    $options[$moduleKey] = [
        'label' => (string) ($mainPage['label'] ?? $moduleKey),
        'menu_label' => (string) ($mainPage['menu_label'] ?? $mainPage['label'] ?? $moduleKey),
        'path' => (string) ($mainPage['path'] ?? ''),
    ];
}

$items = sr_site_menu_seed_default_header_menu_items($options, ['survey', 'quiz', 'community', 'content']);
$labels = array_map('strval', array_column($items, 'label'));
$expected = ['Home', 'Contents', 'Community', 'Quiz', 'Survey'];
if ($labels !== $expected) {
    sr_site_menu_check_error('Site menu seed labels must use English menu metadata in service main order: ' . implode(' > ', $labels));
}
$mainPageLabels = array_values(array_map(static fn (array $option): string => (string) ($option['label'] ?? ''), $options));
sr_site_menu_check_assert($mainPageLabels === ['콘텐츠', '커뮤니티', '퀴즈·테스트', '설문·여론조사'], 'English site menu seed labels must not change module main-page labels.');

$pdo = new SrSiteMenuCheckPdo('sqlite::memory:');
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
        (2, 'footer', '하단 메뉴', 'disabled', '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
        (3, 'empty_menu', '빈 메뉴', 'enabled', '2026-06-11 00:00:00', '2026-06-11 00:00:00')"
);
$pdo->exec(
    "INSERT INTO sr_site_menu_items (id, menu_id, parent_id, label, url, target, status, sort_order, created_at, updated_at) VALUES
        (1, 1, NULL, '홈', '/', 'self', 'enabled', 10, '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
        (2, 1, NULL, '커뮤니티', '/community/board?key=free', 'self', 'enabled', 20, '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
        (3, 1, 2, '글 보기', '/community/post?id=42', 'self', 'enabled', 10, '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
        (4, 1, 3, '외부 문서', 'https://example.test/docs', 'blank', 'enabled', 10, '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
        (5, 1, 4, '너무 깊은 항목', '/too-deep', 'self', 'enabled', 10, '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
        (6, 1, NULL, '비활성', '/disabled', 'self', 'disabled', 30, '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
        (7, 2, NULL, '숨김 메뉴', '/hidden', 'self', 'enabled', 10, '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
        (8, 1, NULL, '퀴즈', '/quiz', 'self', 'enabled', 40, '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
        (9, 1, NULL, '퀴즈 확장', '/quiz-extra', 'self', 'enabled', 50, '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
        (10, 1, NULL, '링크 없는 묶음', '', 'self', 'enabled', 60, '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
        (11, 1, 10, '묶음 하위', '/group-child', 'self', 'enabled', 10, '2026-06-11 00:00:00', '2026-06-11 00:00:00'),
        (12, 1, NULL, '링크 없는 항목', '', 'self', 'enabled', 70, '2026-06-11 00:00:00', '2026-06-11 00:00:00')"
);
$pdo->exec("CREATE TABLE sr_community_boards (id INTEGER PRIMARY KEY, board_key TEXT NOT NULL)");
$pdo->exec("CREATE TABLE sr_community_posts (id INTEGER PRIMARY KEY, board_id INTEGER NOT NULL)");
$pdo->exec("CREATE TABLE sr_modules (id INTEGER PRIMARY KEY, module_key TEXT NOT NULL, status TEXT NOT NULL)");
$pdo->exec("INSERT INTO sr_community_boards (id, board_key) VALUES (1, 'free')");
$pdo->exec("INSERT INTO sr_community_posts (id, board_id) VALUES (42, 1)");
$pdo->exec("INSERT INTO sr_modules (id, module_key, status) VALUES (1, 'site_menu', 'enabled')");
sr_site_menu_clear_cache();

$contractTree = sr_module_contract_invoke(
    $pdo,
    'site_menu',
    'site-menu-provider.php',
    'tree_function',
    ['selected_menu' => 'header'],
    []
);
sr_site_menu_check_assert(
    is_array($contractTree) && (string) ($contractTree['menu_key'] ?? '') === 'header',
    'Site menu provider invocation must treat consumer argument arrays as positional values.'
);
sr_site_menu_clear_cache('header');

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/community/post?id=42';
$pdo->siteMenuTreePrepareCount = 0;
$html = sr_site_menu_render($pdo, 'header', 'navigation');
$cachedHtml = sr_site_menu_render($pdo, 'header', 'primary_navigation');
sr_site_menu_check_assert(str_contains($html, 'class="sr-site-menu sr-site-menu-header sr-site-menu-slot-navigation"'), 'Site menu render runtime fixture must include menu and slot classes.');
sr_site_menu_check_assert(str_contains($cachedHtml, 'class="sr-site-menu sr-site-menu-header sr-site-menu-slot-primary-navigation"'), 'Site menu render runtime fixture must render cached tree with request-specific slot class.');
sr_site_menu_check_assert(str_contains($html, 'href="/"'), 'Site menu render runtime fixture must render root link.');
sr_site_menu_check_assert(str_contains($html, 'href="/community/board?key=free"'), 'Site menu render runtime fixture must render relative links.');
sr_site_menu_check_assert(str_contains($html, 'sr-site-menu-item-has-children'), 'Site menu render runtime fixture must mark items with children.');
sr_site_menu_check_assert(str_contains($html, 'aria-haspopup="true"'), 'Site menu render runtime fixture must expose child menu popup semantics.');
sr_site_menu_check_assert(str_contains($html, 'aria-expanded="false"'), 'Site menu render runtime fixture must expose collapsed child menu state.');
sr_site_menu_check_assert(str_contains($html, 'aria-controls="sr-site-menu-submenu-2"'), 'Site menu render runtime fixture must connect child menu controls.');
sr_site_menu_check_assert(str_contains($html, 'id="sr-site-menu-submenu-2"'), 'Site menu render runtime fixture must render stable child menu id.');
sr_site_menu_check_assert(str_contains($html, '<button type="button" class="sr-site-menu-link"'), 'Site menu render runtime fixture must render URL-less parents without anchor links.');
sr_site_menu_check_assert(str_contains($html, '<span class="sr-site-menu-link"><span class="sr-site-menu-link-label">링크 없는 항목</span></span>'), 'Site menu render runtime fixture must render URL-less leaves without anchor links.');
sr_site_menu_check_assert(!str_contains($html, '<a href="#">'), 'Site menu render runtime fixture must not create placeholder hrefs for URL-less items.');
sr_site_menu_check_assert(substr_count($html, 'aria-current="page"') >= 2, 'Site menu render runtime fixture must mark current post and matching community board.');
sr_site_menu_check_assert(str_contains($html, 'target="_blank" rel="noopener noreferrer"'), 'Site menu render runtime fixture must protect blank external links.');
sr_site_menu_check_assert(!str_contains($html, '너무 깊은 항목'), 'Site menu render runtime fixture must stop at depth 3.');
sr_site_menu_check_assert(!str_contains($html, '비활성'), 'Site menu render runtime fixture must skip disabled items.');
sr_site_menu_check_assert($pdo->siteMenuTreePrepareCount === 1, 'Site menu runtime cache must reuse the enabled item tree within the same request.');
sr_site_menu_clear_runtime_cache('header');
sr_site_menu_render($pdo, 'header', 'navigation');
sr_site_menu_check_assert($pdo->siteMenuTreePrepareCount === 1, 'Site menu persistent cache must reuse the enabled item tree after runtime cache clear.');
$staleHeaderGeneration = sr_public_data_cache_generation(
    sr_site_menu_tree_cache_namespace(),
    'header',
    sr_site_menu_tree_cache_schema()
);
sr_site_menu_clear_cache('header');
sr_site_menu_check_assert(
    !sr_public_data_cache_write(
        sr_site_menu_tree_cache_namespace(),
        'header',
        sr_site_menu_tree_cache_schema(),
        ['menu_key' => 'header', 'label' => '무효화 전 메뉴', 'enabled' => true, 'items' => []],
        $staleHeaderGeneration
    ),
    'Site menu cache must reject a stale writer after entry invalidation.'
);
sr_site_menu_render($pdo, 'header', 'navigation');
sr_site_menu_check_assert($pdo->siteMenuTreePrepareCount === 2, 'Site menu entry generation invalidation must force the next tree lookup.');
sr_public_data_cache_write(sr_site_menu_tree_cache_namespace(), 'header', sr_site_menu_tree_cache_schema(), ['menu_key' => 'wrong']);
sr_site_menu_clear_runtime_cache('header');
sr_site_menu_render($pdo, 'header', 'navigation');
sr_site_menu_check_assert($pdo->siteMenuTreePrepareCount === 3, 'Site menu renderer must reject an invalid persistent tree and reload it from the database.');
sr_site_menu_clear_cache('header');
$brokenCachePath = sr_public_data_cache_path(sr_site_menu_tree_cache_namespace(), 'header', sr_site_menu_tree_cache_schema());
if (!is_dir(dirname($brokenCachePath))) {
    mkdir(dirname($brokenCachePath), 0755, true);
}
file_put_contents($brokenCachePath, "{\n");
sr_site_menu_render($pdo, 'header', 'navigation');
sr_site_menu_check_assert($pdo->siteMenuTreePrepareCount === 4, 'Site menu renderer must discard a structurally invalid cache file and reload it from the database.');
sr_site_menu_clear_cache('header');
sr_site_menu_render($pdo, 'header', 'navigation');
sr_site_menu_check_assert($pdo->siteMenuTreePrepareCount === 5, 'Site menu full cache clear must force the next tree lookup.');
sr_site_menu_check_assert(sr_site_menu_render($pdo, 'footer', 'secondary_navigation') === '', 'Site menu render runtime fixture must skip disabled menus.');
$emptyTree = sr_site_menu_tree($pdo, 'empty_menu');
sr_site_menu_check_assert(($emptyTree['enabled'] ?? false) === true, 'Site menu tree helper must keep enabled menu state when a menu has no enabled items.');
sr_site_menu_check_assert(($emptyTree['label'] ?? '') === '빈 메뉴', 'Site menu tree cache must retain the published menu label.');
sr_site_menu_check_assert(sr_site_menu_render($pdo, 'empty_menu', 'navigation') === '', 'Site menu render runtime fixture must render empty enabled menus as empty output.');

$_SERVER['REQUEST_URI'] = '/quiz/qa260611p1530_category';
$quizSectionHtml = sr_site_menu_render($pdo, 'header', 'navigation');
sr_site_menu_check_assert(str_contains($quizSectionHtml, '<li class="sr-site-menu-item sr-site-menu-item-depth-1 is-current"><a href="/quiz" aria-current="page"'), 'Site menu section root item must be current for a child path.');
sr_site_menu_check_assert(!str_contains($quizSectionHtml, '<li class="sr-site-menu-item sr-site-menu-item-depth-1 is-current"><a href="/quiz-extra" aria-current="page"'), 'Site menu section current matching must not cross path segment boundaries.');

$_SERVER['REQUEST_URI'] = '/content/example';
sr_site_menu_check_assert(sr_site_menu_item_href('/login') === '/login?next=%2Fcontent%2Fexample', 'Site menu login link must include safe current next path.');
$_SERVER['REQUEST_URI'] = '/login';
sr_site_menu_check_assert(sr_site_menu_item_href('/login') === '/login', 'Site menu login link must not include next on login page.');
sr_site_menu_check_assert(sr_site_menu_clean_url('javascript:alert(1)') === '', 'Site menu URL cleaner must reject unsafe pseudo URLs.');
sr_site_menu_check_assert(sr_site_menu_clean_url('') === '', 'Site menu URL cleaner must allow empty optional URLs.');
sr_site_menu_check_assert(sr_site_menu_item_href('javascript:alert(1)') === '#', 'Site menu href helper must fail closed for unsafe URLs.');

$publicLayoutCss = (string) file_get_contents(SR_ROOT . '/assets/layout.css');
$communityLayoutCss = (string) file_get_contents(SR_ROOT . '/modules/community/theme/basic/assets/layout.css');
sr_site_menu_check_assert(!is_file(SR_ROOT . '/modules/site_menu/assets/module.css'), 'Site menu module must not own a public stylesheet.');
sr_site_menu_check_assert(!is_file(SR_ROOT . '/modules/site_menu/updates/2026.04.003.sql'), 'Site menu version-only update must stay removed from the current fresh-install baseline.');
sr_site_menu_check_assert(str_contains($publicLayoutCss, '.public-layout-nav .sr-site-menu-item.is-site-menu-open > .sr-site-menu-list'), 'Public layout CSS must support site menu dropdown open state.');
sr_site_menu_check_assert(str_contains($communityLayoutCss, '.community-layout-nav .sr-site-menu-list-depth-3'), 'Community layout CSS must cover site menu depth 3 menus.');
sr_site_menu_check_assert(str_contains($publicLayoutCss, '@media (max-width: 767px)'), 'Public layout CSS must include mobile site menu rules.');

$commonUiJs = (string) file_get_contents(SR_ROOT . '/assets/common-ui.js');
sr_site_menu_check_assert(str_contains($commonUiJs, 'is-site-menu-open'), 'Common UI script must manage site menu open state.');
sr_site_menu_check_assert(str_contains($commonUiJs, '(pointer: coarse)'), 'Common UI script must support touch pointer first-tap expansion.');
sr_site_menu_check_assert(str_contains($commonUiJs, "event.key === 'Escape'"), 'Common UI script must close site menus on Escape.');

$siteMenuAction = (string) file_get_contents(SR_ROOT . '/modules/site_menu/actions/admin-site-menus.php');
$siteMenuAdminView = (string) file_get_contents(SR_ROOT . '/modules/site_menu/views/admin-site-menus.php');
$siteMenuKo = (string) file_get_contents(SR_ROOT . '/modules/site_menu/lang/ko.php');
$siteMenuInstallSql = (string) file_get_contents(SR_ROOT . '/modules/site_menu/install.sql');
$siteMenuDraftUpdateSql = (string) file_get_contents(SR_ROOT . '/modules/site_menu/updates/2026.06.003.sql');
sr_site_menu_check_assert(!str_contains($siteMenuAction, 'item_url_duplicate'), 'Site menu admin action must allow multiple items with the same URL.');
sr_site_menu_check_assert(!str_contains($siteMenuAction, 'item_url_schema_update_required'), 'Site menu admin action must not keep legacy URL unique-index guidance.');
sr_site_menu_check_assert(!str_contains($siteMenuAction, 'sr_site_menu_admin_is_legacy_url_unique_violation'), 'Site menu admin action must not special-case the removed URL unique index.');
foreach (array_merge([
    'modules/site_menu/install.sql',
    'modules/site_menu/updates/2026.04.002.sql',
    'modules/site_menu/actions/admin-site-menus.php',
    'modules/site_menu/lang/ko.php',
], glob(SR_ROOT . '/modules/site_menu/updates/*.sql') ?: []) as $siteMenuSourcePath) {
    $siteMenuSource = (string) file_get_contents($siteMenuSourcePath);
    sr_site_menu_check_assert(!str_contains($siteMenuSource, 'uq_sr_site_menu_items_menu_url'), 'Site menu must not reference the removed URL unique index: ' . $siteMenuSourcePath);
    sr_site_menu_check_assert(!str_contains($siteMenuSource, 'site_menu_items_menu_url'), 'Site menu must not keep removed URL unique-index error handling: ' . $siteMenuSourcePath);
}
sr_site_menu_check_assert(!is_file(SR_ROOT . '/modules/site_menu/updates/2026.06.002.sql'), 'Site menu legacy URL unique-index drop update must stay removed.');
sr_site_menu_check_assert(str_contains($siteMenuInstallSql, 'sr_site_menu_draft_menus'), 'Site menu install schema must create draft menu tables.');
sr_site_menu_check_assert(str_contains($siteMenuInstallSql, "VALUES ('header', 'Header Menu'"), 'Fresh site menu install must use the English header menu label.');
sr_site_menu_check_assert(str_contains($siteMenuDraftUpdateSql, 'site_menu_draft_menus'), 'Site menu update must create draft menu tables for existing installations.');
sr_site_menu_check_assert(str_contains($siteMenuAction, 'publish_site_menus'), 'Site menu admin action must publish draft menus explicitly.');
sr_site_menu_check_assert(str_contains($siteMenuAction, 'DELETE FROM sr_site_menus'), 'Site menu publish action must replace public menu rows from drafts.');
sr_site_menu_check_assert(str_contains($siteMenuAction, 'if (!sr_site_menu_clear_cache())'), 'Site menu publish action must report cache generation invalidation failures.');
sr_site_menu_check_assert(str_contains($siteMenuKo, "'action.admin.cache_invalidation_failed'"), 'Site menu must translate the cache invalidation failure notice.');
sr_site_menu_check_assert(str_contains($siteMenuAdminView, 'sr_admin_form_label_help_html($modalId . \'_menu_key\''), 'Site menu identifier field must expose detailed operator help.');
sr_site_menu_check_assert(str_contains($siteMenuAdminView, '식별값을 바꿔도 다른 설정에 저장된 선택값은 자동으로 바뀌지 않습니다.'), 'Site menu identifier help must warn that dependent layout selections are not renamed automatically.');
sr_site_menu_check_assert(str_contains($siteMenuAdminView, '초안 순서 저장') && str_contains($siteMenuAdminView, '공개 반영'), 'Site menu order help must distinguish draft order saving from publishing.');
sr_site_menu_check_assert(str_contains($siteMenuKo, "'ui.menu.key.20cd5d6a' => '메뉴 식별값'"), 'Site menu operator copy must use an easy Korean label for the menu key.');

if ($errors !== []) {
    sr_site_menu_clear_cache();
    fwrite(STDERR, "site menu checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

sr_site_menu_clear_cache();
echo "site menu seed order checks completed.\n";
