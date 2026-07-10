#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

if (!function_exists('sr_t')) {
    function sr_t(string $key): string
    {
        return $key;
    }
}

if (!function_exists('sr_now')) {
    function sr_now(): string
    {
        return '2026-06-10 12:00:00';
    }
}

if (!function_exists('sr_log_exception')) {
    function sr_log_exception(Throwable $exception, string $context = ''): void
    {
    }
}

if (!function_exists('sr_enabled_module_contract_files')) {
    function sr_enabled_module_contract_files(PDO $pdo, string $contractFile, array $excludeModuleKeys = []): array
    {
        return [];
    }
}

if (!function_exists('sr_load_module_contract_file')) {
    function sr_load_module_contract_file(string $moduleKey, string $file): mixed
    {
        return null;
    }
}

if (!function_exists('sr_is_safe_relative_url')) {
    function sr_is_safe_relative_url(string $url): bool
    {
        return $url !== '' && str_starts_with($url, '/') && !str_starts_with($url, '//');
    }
}

if (!function_exists('sr_is_http_url')) {
    function sr_is_http_url(string $url): bool
    {
        return preg_match('#\Ahttps?://#i', $url) === 1;
    }
}

if (!function_exists('sr_url')) {
    function sr_url(string $path): string
    {
        return $path;
    }
}

if (!function_exists('sr_e')) {
    function sr_e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('sr_storage_public_url')) {
    function sr_storage_public_url(string $driver, string $key, ?array $config = null): string
    {
        return '';
    }
}

if (!function_exists('sr_storage_reference')) {
    function sr_storage_reference(string $driver, string $key): string
    {
        return $driver . ':' . $key;
    }
}

require_once 'modules/logo_manager/helpers.php';

$errors = [];

foreach ([
    'modules/logo_manager/updates/2026.06.001.sql',
    'modules/logo_manager/updates/2026.06.002.sql',
    'modules/logo_manager/updates/2026.06.003.sql',
    'modules/logo_manager/updates/2026.06.004.sql',
] as $legacyUpdatePath) {
    if (is_file($legacyUpdatePath)) {
        $errors[] = 'logo manager update-only compatibility file must stay removed: ' . $legacyUpdatePath;
    }
}

$logoManagerSources = array_merge(
    ['modules/logo_manager/install.sql', 'modules/logo_manager/helpers.php', 'modules/logo_manager/actions/admin-logo-manager.php'],
    glob('modules/logo_manager/updates/*.sql') ?: []
);
foreach ($logoManagerSources as $sourcePath) {
    $source = file_get_contents($sourcePath);
    if (!is_string($source)) {
        $errors[] = 'logo manager source must be readable: ' . $sourcePath;
        continue;
    }

    foreach (['sr_logo_manager_assignments', 'sr_logo_manager_assets'] as $legacyTable) {
        if (str_contains($source, $legacyTable)) {
            $errors[] = 'logo manager legacy table reference must stay removed from ' . $sourcePath . ': ' . $legacyTable;
        }
    }
}

function sr_logo_manager_favicon_check_add_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_logo_manager_favicon_check_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_logo_manager_favicon_check_add_error($message);
    }
}

function sr_logo_manager_favicon_check_assert_no_icon_tag(string $html, string $message): void
{
    sr_logo_manager_favicon_check_assert($html === '', $message . ' must not render icon links');
    sr_logo_manager_favicon_check_assert(!str_contains($html, 'rel="icon"'), $message . ' must not render favicon link');
    sr_logo_manager_favicon_check_assert(!str_contains($html, 'rel="apple-touch-icon"'), $message . ' must not render app icon link');
    sr_logo_manager_favicon_check_assert(!str_contains($html, '/uploads/'), $message . ' stale uploaded URL must not render');
}

function sr_logo_manager_favicon_check_pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->sqliteCreateFunction('TIMESTAMPDIFF', static function (string $unit, ?string $start, ?string $end): int {
        if ($start === null || $end === null) {
            return 0;
        }

        $startTime = strtotime($start);
        $endTime = strtotime($end);
        if ($startTime === false || $endTime === false) {
            return 0;
        }

        return max(0, $endTime - $startTime);
    }, 3);

    $pdo->exec(
        "CREATE TABLE sr_logo_manager_logos (
            id INTEGER PRIMARY KEY,
            position_key TEXT NOT NULL,
            title TEXT NOT NULL,
            alt_text TEXT DEFAULT '',
            link_url TEXT DEFAULT '',
            use_as_public_symbol INTEGER DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'active',
            starts_at TEXT NULL,
            ends_at TEXT NULL,
            SECOND TEXT DEFAULT 'SECOND',
            sort_order INTEGER NOT NULL DEFAULT 100,
            storage_driver TEXT NOT NULL DEFAULT 'local',
            storage_key TEXT DEFAULT '',
            public_url TEXT DEFAULT '',
            mime_type TEXT DEFAULT 'image/png',
            width INTEGER DEFAULT 0,
            height INTEGER DEFAULT 0,
            updated_at TEXT DEFAULT '2026-06-10 12:00:00'
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_logo_manager_icon_variants (
            id INTEGER PRIMARY KEY,
            logo_id INTEGER NOT NULL,
            variant_key TEXT NOT NULL,
            purpose TEXT NOT NULL,
            width INTEGER NOT NULL,
            height INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT 'active',
            storage_driver TEXT NOT NULL DEFAULT 'local',
            storage_key TEXT DEFAULT '',
            public_url TEXT DEFAULT '',
            mime_type TEXT DEFAULT 'image/png',
            updated_at TEXT DEFAULT '2026-06-10 12:00:00'
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_logo_manager_logo_usage_targets (
            id INTEGER PRIMARY KEY,
            logo_id INTEGER NOT NULL,
            layout_provider_key TEXT NOT NULL,
            slot_key TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT '2026-06-10 12:00:00'
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_modules (
            id INTEGER PRIMARY KEY,
            module_key TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_module_settings (
            id INTEGER PRIMARY KEY,
            module_id INTEGER NOT NULL,
            setting_key TEXT NOT NULL,
            setting_value TEXT NOT NULL,
            value_type TEXT NOT NULL DEFAULT 'string',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )"
    );
    $pdo->exec("INSERT INTO sr_modules (id, module_key) VALUES (1, 'logo_manager')");

    return $pdo;
}

function sr_logo_manager_favicon_check_insert_logo(PDO $pdo, array $row): void
{
    $defaults = [
        'position_key' => 'public.favicon',
        'title' => 'Test favicon',
        'alt_text' => '',
        'link_url' => '',
        'use_as_public_symbol' => 0,
        'status' => 'active',
        'starts_at' => null,
        'ends_at' => null,
        'sort_order' => 100,
        'storage_driver' => 'local',
        'storage_key' => '',
        'public_url' => '',
        'mime_type' => 'image/png',
        'width' => 0,
        'height' => 0,
        'updated_at' => '2026-06-10 12:00:00',
    ];
    $row = array_merge($defaults, $row);
    $stmt = $pdo->prepare(
        'INSERT INTO sr_logo_manager_logos
            (id, position_key, title, alt_text, link_url, use_as_public_symbol, status, starts_at, ends_at, sort_order, storage_driver, storage_key, public_url, mime_type, width, height, updated_at)
         VALUES
            (:id, :position_key, :title, :alt_text, :link_url, :use_as_public_symbol, :status, :starts_at, :ends_at, :sort_order, :storage_driver, :storage_key, :public_url, :mime_type, :width, :height, :updated_at)'
    );
    $stmt->execute($row);
}

function sr_logo_manager_favicon_check_insert_variant(PDO $pdo, array $row): void
{
    $defaults = [
        'variant_key' => 'favicon_32',
        'purpose' => 'favicon',
        'width' => 32,
        'height' => 32,
        'status' => 'active',
        'storage_driver' => 'local',
        'storage_key' => '',
        'public_url' => '',
        'mime_type' => 'image/png',
        'updated_at' => '2026-06-10 12:00:00',
    ];
    $row = array_merge($defaults, $row);
    $stmt = $pdo->prepare(
        'INSERT INTO sr_logo_manager_icon_variants
            (id, logo_id, variant_key, purpose, width, height, status, storage_driver, storage_key, public_url, mime_type, updated_at)
         VALUES
            (:id, :logo_id, :variant_key, :purpose, :width, :height, :status, :storage_driver, :storage_key, :public_url, :mime_type, :updated_at)'
    );
    $stmt->execute($row);
}

function sr_logo_manager_favicon_check_insert_usage(PDO $pdo, int $logoId, string $providerKey, string $slotKey): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO sr_logo_manager_logo_usage_targets
            (logo_id, layout_provider_key, slot_key, created_at)
         VALUES
            (:logo_id, :layout_provider_key, :slot_key, :created_at)'
    );
    $stmt->execute([
        'logo_id' => $logoId,
        'layout_provider_key' => $providerKey,
        'slot_key' => $slotKey,
        'created_at' => '2026-06-10 12:00:00',
    ]);
}

$pdo = sr_logo_manager_favicon_check_pdo();
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 1,
    'use_as_public_symbol' => 0,
    'public_url' => '/uploads/favicon-active.png',
]);
$html = sr_logo_manager_favicon_link_tag($pdo);
sr_logo_manager_favicon_check_assert(str_contains($html, 'rel="icon"'), 'active public.favicon logo must render icon link');
sr_logo_manager_favicon_check_assert(str_contains($html, 'rel="apple-touch-icon"'), 'active public.favicon logo must render apple touch icon link');
sr_logo_manager_favicon_check_assert(str_contains($html, '/uploads/favicon-active.png'), 'active favicon link must contain active logo URL');
sr_logo_manager_favicon_check_assert(str_contains($html, '/uploads/favicon-active.png'), 'favicon links must not require use_as_public_symbol');
sr_logo_manager_favicon_check_assert(str_contains($html, '?v='), 'active favicon links must include cache-busting version query');
sr_logo_manager_favicon_check_assert(
    sr_logo_manager_url_with_cache_version('/uploads/favicon-active.png#icon', 'review') === '/uploads/favicon-active.png?v=review#icon',
    'cache-busting version query must be inserted before URL fragments'
);
sr_logo_manager_favicon_check_assert(
    sr_logo_manager_url_with_cache_version('/uploads/favicon-active.png?size=32#icon', 'review') === '/uploads/favicon-active.png?size=32&v=review#icon',
    'cache-busting version query must preserve existing query strings before URL fragments'
);

$pdo = sr_logo_manager_favicon_check_pdo();
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 101,
    'position_key' => 'public.app_icon',
    'use_as_public_symbol' => 1,
    'public_url' => '/uploads/app-icon-active.png',
]);
$html = sr_logo_manager_favicon_link_tag($pdo);
sr_logo_manager_favicon_check_assert_no_icon_tag($html, 'active public.app_icon logo');
$symbolLogo = sr_logo_manager_public_symbol_logo($pdo);
sr_logo_manager_favicon_check_assert(is_array($symbolLogo), 'active public.app_icon logo with symbol flag must be public symbol candidate');
sr_logo_manager_favicon_check_assert(
    is_array($symbolLogo) && (string) ($symbolLogo['public_url'] ?? '') === '/uploads/app-icon-active.png',
    'public symbol helper must return public.app_icon logo instead of favicon logo'
);

$pdo = sr_logo_manager_favicon_check_pdo();
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 102,
    'use_as_public_symbol' => 1,
    'public_url' => '/uploads/favicon-symbol-legacy.png',
]);
sr_logo_manager_favicon_check_assert(
    sr_logo_manager_public_symbol_logo($pdo) === null,
    'legacy public.favicon symbol flag must not be used as public app icon symbol'
);

$pdo = sr_logo_manager_favicon_check_pdo();
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 2,
    'status' => 'disabled',
    'use_as_public_symbol' => 1,
    'public_url' => '/uploads/favicon-disabled.png',
]);
$html = sr_logo_manager_favicon_link_tag($pdo);
sr_logo_manager_favicon_check_assert_no_icon_tag($html, 'disabled public.favicon logo');

$pdo = sr_logo_manager_favicon_check_pdo();
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 3,
    'ends_at' => '2026-06-09 23:59:59',
    'public_url' => '/uploads/favicon-past.png',
]);
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 4,
    'starts_at' => '2026-06-11 00:00:00',
    'public_url' => '/uploads/favicon-future.png',
]);
$html = sr_logo_manager_favicon_link_tag($pdo);
sr_logo_manager_favicon_check_assert_no_icon_tag($html, 'past or future public.favicon logos');

$pdo = sr_logo_manager_favicon_check_pdo();
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 5,
    'sort_order' => 10,
    'public_url' => '/uploads/favicon-default.png',
]);
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 6,
    'starts_at' => '2026-06-10 00:00:00',
    'ends_at' => '2026-06-10 23:59:59',
    'sort_order' => 99,
    'public_url' => '/uploads/favicon-window.png',
]);
$html = sr_logo_manager_favicon_link_tag($pdo);
sr_logo_manager_favicon_check_assert(str_contains($html, '/uploads/favicon-window.png'), 'current dated favicon candidate must outrank default candidate');
sr_logo_manager_favicon_check_assert(!str_contains($html, '/uploads/favicon-default.png'), 'only the selected active favicon candidate should render');
sr_logo_manager_favicon_check_assert(str_contains($html, '?v='), 'selected priority favicon candidate must include cache-busting version query');

$pdo = sr_logo_manager_favicon_check_pdo();
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 60,
    'starts_at' => '2026-06-10 00:00:00',
    'ends_at' => '2026-06-10 23:59:59',
    'sort_order' => 1,
    'public_url' => '/uploads/favicon-wide-window.png',
]);
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 61,
    'starts_at' => '2026-06-10 10:00:00',
    'ends_at' => '2026-06-10 14:00:00',
    'sort_order' => 99,
    'public_url' => '/uploads/favicon-short-window.png',
]);
$html = sr_logo_manager_favicon_link_tag($pdo);
sr_logo_manager_favicon_check_assert(str_contains($html, '/uploads/favicon-short-window.png'), 'shorter dated favicon window must outrank lower sort order wider window');
sr_logo_manager_favicon_check_assert(!str_contains($html, '/uploads/favicon-wide-window.png'), 'wider dated favicon window must not render when shorter window is active');

$pdo = sr_logo_manager_favicon_check_pdo();
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 62,
    'starts_at' => '2026-06-10 00:00:00',
    'ends_at' => '2026-06-10 23:59:59',
    'sort_order' => 10,
    'public_url' => '/uploads/favicon-sort-low.png',
]);
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 63,
    'starts_at' => '2026-06-10 00:00:00',
    'ends_at' => '2026-06-10 23:59:59',
    'sort_order' => 20,
    'public_url' => '/uploads/favicon-sort-high.png',
]);
$html = sr_logo_manager_favicon_link_tag($pdo);
sr_logo_manager_favicon_check_assert(str_contains($html, '/uploads/favicon-sort-low.png'), 'lower sort order favicon must win when date window length is equal');
sr_logo_manager_favicon_check_assert(!str_contains($html, '/uploads/favicon-sort-high.png'), 'higher sort order favicon must not render when equal window lower sort candidate exists');

$pdo = sr_logo_manager_favicon_check_pdo();
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 64,
    'starts_at' => '2026-06-10 00:00:00',
    'ends_at' => '2026-06-10 23:59:59',
    'sort_order' => 10,
    'public_url' => '/uploads/favicon-start-early.png',
]);
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 65,
    'starts_at' => '2026-06-10 01:00:00',
    'ends_at' => '2026-06-11 00:59:59',
    'sort_order' => 10,
    'public_url' => '/uploads/favicon-start-late.png',
]);
$html = sr_logo_manager_favicon_link_tag($pdo);
sr_logo_manager_favicon_check_assert(str_contains($html, '/uploads/favicon-start-late.png'), 'later start favicon must win when date window length and sort order are equal');
sr_logo_manager_favicon_check_assert(!str_contains($html, '/uploads/favicon-start-early.png'), 'earlier start favicon must not render when equal window later start candidate exists');

$pdo = sr_logo_manager_favicon_check_pdo();
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 80,
    'position_key' => 'public.header.desktop',
    'starts_at' => '2026-06-10 00:00:00',
    'ends_at' => '2026-06-10 23:59:59',
    'sort_order' => 1,
    'public_url' => '/uploads/logo-all-short.png',
]);
sr_logo_manager_favicon_check_insert_usage($pdo, 80, 'all', 'top');
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 81,
    'position_key' => 'public.header.desktop',
    'starts_at' => '2026-06-01 00:00:00',
    'ends_at' => '2026-06-30 23:59:59',
    'sort_order' => 99,
    'public_url' => '/uploads/logo-content-direct-long.png',
]);
sr_logo_manager_favicon_check_insert_usage($pdo, 81, 'content', 'top');
$contentLogo = sr_logo_manager_active_logo($pdo, 'public.header.desktop', null, [
    'layout_provider_key' => 'content',
    'slot_key' => 'top',
]);
sr_logo_manager_favicon_check_assert(
    is_array($contentLogo) && (string) ($contentLogo['public_url'] ?? '') === '/uploads/logo-content-direct-long.png',
    'module-specific logo usage must outrank all-layout usage even when the all-layout period is shorter'
);
$communityLogo = sr_logo_manager_active_logo($pdo, 'public.header.desktop', null, [
    'layout_provider_key' => 'community',
    'slot_key' => 'top',
]);
sr_logo_manager_favicon_check_assert(
    is_array($communityLogo) && (string) ($communityLogo['public_url'] ?? '') === '/uploads/logo-all-short.png',
    'all-layout logo usage must apply to layout providers without a direct logo'
);

$pdo = sr_logo_manager_favicon_check_pdo();
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 82,
    'position_key' => 'public.header.desktop',
    'starts_at' => '2026-06-10 00:00:00',
    'ends_at' => '2026-06-10 23:59:59',
    'sort_order' => 99,
    'public_url' => '/uploads/logo-content-short.png',
]);
sr_logo_manager_favicon_check_insert_usage($pdo, 82, 'content', 'top');
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 83,
    'position_key' => 'public.header.desktop',
    'starts_at' => '2026-06-01 00:00:00',
    'ends_at' => '2026-06-30 23:59:59',
    'sort_order' => 1,
    'public_url' => '/uploads/logo-content-wide.png',
]);
sr_logo_manager_favicon_check_insert_usage($pdo, 83, 'content', 'top');
$contentLogo = sr_logo_manager_active_logo($pdo, 'public.header.desktop', null, [
    'layout_provider_key' => 'content',
    'slot_key' => 'top',
]);
sr_logo_manager_favicon_check_assert(
    is_array($contentLogo) && (string) ($contentLogo['public_url'] ?? '') === '/uploads/logo-content-short.png',
    'shorter usage period must outrank lower sort order within the same usage priority'
);

$pdo = sr_logo_manager_favicon_check_pdo();
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 7,
    'public_url' => '/uploads/favicon-original.png',
]);
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 8,
    'status' => 'disabled',
    'public_url' => '/uploads/favicon-disabled.png',
]);
sr_logo_manager_favicon_check_insert_variant($pdo, [
    'id' => 1,
    'logo_id' => 7,
    'public_url' => '/uploads/favicon-32.png',
]);
sr_logo_manager_favicon_check_insert_variant($pdo, [
    'id' => 2,
    'logo_id' => 8,
    'public_url' => '/uploads/favicon-disabled-32.png',
]);
$html = sr_logo_manager_favicon_link_tag($pdo);
sr_logo_manager_favicon_check_assert(str_contains($html, '/uploads/favicon-32.png'), 'active favicon variant must render when available');
sr_logo_manager_favicon_check_assert(str_contains($html, '/uploads/favicon-32.png?v='), 'active favicon variant URL must include cache-busting version query');
sr_logo_manager_favicon_check_assert(!str_contains($html, '/uploads/favicon-original.png'), 'active variant set must replace original favicon fallback');
sr_logo_manager_favicon_check_assert(!str_contains($html, '/uploads/favicon-disabled'), 'disabled logo variants must not render through favicon link tag');

$pdo = sr_logo_manager_favicon_check_pdo();
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 9,
    'public_url' => '/uploads/favicon-transition.png',
]);
$html = sr_logo_manager_favicon_link_tag($pdo);
sr_logo_manager_favicon_check_assert(str_contains($html, '/uploads/favicon-transition.png'), 'active favicon must render before disabling');
$activeHtml = $html;
$pdo->exec("UPDATE sr_logo_manager_logos SET status = 'disabled', updated_at = '2026-06-10 12:05:00' WHERE id = 9");
$html = sr_logo_manager_favicon_link_tag($pdo);
sr_logo_manager_favicon_check_assert_no_icon_tag($html, 'favicon disabled after active state transition');
sr_logo_manager_favicon_check_assert($html !== $activeHtml, 'favicon disabled state transition must change rendered head links');

$pdo = sr_logo_manager_favicon_check_pdo();
$html = sr_logo_manager_favicon_link_tag($pdo);
sr_logo_manager_favicon_check_assert($html === '', 'empty favicon configuration should not render icon links');

$pdo = sr_logo_manager_favicon_check_pdo();
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 10,
    'position_key' => 'public.header.desktop',
    'title' => 'Desktop public logo',
    'public_url' => '/uploads/public-desktop.png',
    'width' => 320,
    'height' => 80,
]);
$html = sr_logo_manager_render_logo($pdo, 'public.header.mobile', ['site_name' => '산란'], [
    'class' => 'public-layout-brand-logo public-layout-brand-logo-mobile',
    'fallback_position_key' => 'public.header.desktop',
]);
sr_logo_manager_favicon_check_assert(str_contains($html, '/uploads/public-desktop.png'), 'mobile public logo render must fall back to desktop logo when mobile is not configured');
sr_logo_manager_favicon_check_assert(str_contains($html, 'public-layout-brand-logo-mobile'), 'fallback public logo render must preserve requested output class');

$pdo = sr_logo_manager_favicon_check_pdo();
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 11,
    'position_key' => 'public.header.mobile',
    'title' => 'Mobile public logo',
    'public_url' => '/uploads/public-mobile.png',
    'width' => 160,
    'height' => 48,
]);
$html = sr_logo_manager_render_logo($pdo, 'public.header.desktop', ['site_name' => '산란'], [
    'class' => 'public-layout-brand-logo public-layout-brand-logo-desktop',
    'fallback_position_key' => 'public.header.mobile',
]);
sr_logo_manager_favicon_check_assert(str_contains($html, '/uploads/public-mobile.png'), 'desktop public logo render must fall back to mobile logo when desktop is not configured');

$pdo = sr_logo_manager_favicon_check_pdo();
$pdo->exec(
    "INSERT INTO sr_module_settings
        (module_id, setting_key, setting_value, value_type, created_at, updated_at)
     VALUES
        (1, 'favicon_reset_at', '2026-06-10 12:00:00', 'string', '2026-06-10 12:00:00', '2026-06-10 12:00:00')"
);
$html = sr_logo_manager_favicon_link_tag($pdo);
sr_logo_manager_favicon_check_assert_no_icon_tag($html, 'favicon reset marker without configured logos');

$moduleStatus = is_file('docs/module-status.md') ? file_get_contents('docs/module-status.md') : false;
sr_logo_manager_favicon_check_assert(is_string($moduleStatus), 'docs/module-status.md must be readable');
if (is_string($moduleStatus)) {
    foreach ([
        '`logo_manager`',
        'check-logo-manager-favicon.php',
        'favicon head link runtime fixture',
        '아이콘 세트 variant 선택',
        'disabled/기간 필터 fixture',
    ] as $marker) {
        sr_logo_manager_favicon_check_assert(
            str_contains($moduleStatus, $marker),
            'module status must cite logo manager favicon evidence marker: ' . $marker
        );
    }
}

$frontController = is_file('index.php') ? file_get_contents('index.php') : false;
sr_logo_manager_favicon_check_assert(is_string($frontController), 'index.php must be readable');
if (is_string($frontController)) {
    foreach ([
        "\$path === '/favicon.ico'",
        "header('Cache-Control: no-store, no-cache, must-revalidate')",
        "header('Content-Length: 0')",
    ] as $marker) {
        sr_logo_manager_favicon_check_assert(
            str_contains($frontController, $marker),
            'front controller must keep favicon fallback no-store removal marker: ' . $marker
        );
    }
}

$adminAction = is_file('modules/logo_manager/actions/admin-logo-manager.php') ? file_get_contents('modules/logo_manager/actions/admin-logo-manager.php') : false;
sr_logo_manager_favicon_check_assert(is_string($adminAction), 'logo manager admin action must be readable');
if (is_string($adminAction)) {
    sr_logo_manager_favicon_check_assert(
        str_contains($adminAction, 'Clear-Site-Data: "cache"'),
        'favicon purge action must ask the browser to clear origin cache'
    );
    foreach ([
        "sr_logo_manager_favicon_position_key() && sr_post_string('also_use_as_app_icon'",
        "sr_logo_manager_app_icon_position_key() && sr_post_string('also_use_as_favicon'",
        '$insertPositionKey === sr_logo_manager_public_symbol_position_key()',
        "'copied_from_position_key' => \$insertPositionKey === \$positionKey ? '' : \$positionKey",
    ] as $marker) {
        sr_logo_manager_favicon_check_assert(
            str_contains($adminAction, $marker),
            'logo manager create action must preserve favicon/app icon copy marker: ' . $marker
        );
    }
}

$adminLogoManagerView = is_file('modules/logo_manager/views/admin-logo-manager.php') ? file_get_contents('modules/logo_manager/views/admin-logo-manager.php') : false;
sr_logo_manager_favicon_check_assert(is_string($adminLogoManagerView), 'logo manager admin view must be readable');
if (is_string($adminLogoManagerView)) {
    foreach ([
        'data-logo-manager-app-icon-copy-row',
        'data-logo-manager-app-icon-copy-switch',
        'name="also_use_as_app_icon"',
        'data-logo-manager-favicon-copy-row',
        'name="also_use_as_favicon"',
        'var symbolEnabled = function ()',
        'appIconCopySwitch.addEventListener',
        'sr_logo_manager_favicon_position_key()',
        'sr_logo_manager_app_icon_position_key()',
        '$logoManagerCanUsePublicSymbol = $logoManagerPositionKey === sr_logo_manager_public_symbol_position_key()',
        'badge-status <?php echo sr_e($logoManagerPublicSymbolStatusClass); ?>',
        "sr_t('logo_manager::ui.public_symbol.not_applicable')",
    ] as $marker) {
        sr_logo_manager_favicon_check_assert(
            str_contains($adminLogoManagerView, $marker),
            'logo manager view must preserve favicon/app icon split marker: ' . $marker
        );
    }
    $appIconCopyRowPosition = strpos($adminLogoManagerView, 'data-logo-manager-app-icon-copy-row');
    $publicSymbolRowPosition = strpos($adminLogoManagerView, 'data-logo-manager-public-symbol-row');
    sr_logo_manager_favicon_check_assert(
        is_int($appIconCopyRowPosition) && is_int($publicSymbolRowPosition) && $appIconCopyRowPosition < $publicSymbolRowPosition,
        'create form must show app icon copy before public symbol option'
    );
}

$logoManagerLang = is_file('modules/logo_manager/lang/ko.php') ? file_get_contents('modules/logo_manager/lang/ko.php') : false;
sr_logo_manager_favicon_check_assert(is_string($logoManagerLang), 'logo manager Korean language file must be readable');
if (is_string($logoManagerLang)) {
    sr_logo_manager_favicon_check_assert(
        str_contains($logoManagerLang, "'ui.public_symbol.label' => '사용자 화면 심볼로도 사용'"),
        'public symbol label must use the requested also-use wording'
    );
    sr_logo_manager_favicon_check_assert(
        str_contains($logoManagerLang, "'ui.public_symbol.not_applicable' => '대상아님'"),
        'non-symbol logo positions must have an explicit not-applicable label'
    );
    sr_logo_manager_favicon_check_assert(
        str_contains($logoManagerLang, "'ui.public_symbol.no' => '사용안함'"),
        'eligible logos with public symbol disabled must have a distinct disabled label'
    );
}

$adminHeader = is_file('modules/admin/themes/basic/layout-header.php') ? file_get_contents('modules/admin/themes/basic/layout-header.php') : false;
sr_logo_manager_favicon_check_assert(is_string($adminHeader), 'admin layout header must be readable');
if (is_string($adminHeader)) {
    sr_logo_manager_favicon_check_assert(
        str_contains($adminHeader, "sr_logo_manager_active_url(\$pdo, 'public.app_icon')"),
        'admin collapsed sidebar brand must read app icon from public.app_icon'
    );
    sr_logo_manager_favicon_check_assert(
        str_contains($adminHeader, '$adminBrandSidebarLogoUrl'),
        'admin sidebar compact brand must keep sidebar logo as fallback when app icon is unavailable'
    );
    $compactIconOffset = strpos($adminHeader, "if (\$adminBrandIconUrl !== '')");
    $compactSidebarLogoOffset = strpos($adminHeader, "elseif (\$adminBrandSidebarLogoUrl !== '')");
    sr_logo_manager_favicon_check_assert(
        is_int($compactIconOffset) && is_int($compactSidebarLogoOffset) && $compactIconOffset < $compactSidebarLogoOffset,
        'admin collapsed sidebar brand must prefer app icon before sidebar logo fallback'
    );
    sr_logo_manager_favicon_check_assert(
        str_contains($adminHeader, "if (\$adminBrandLogoHtml === '')"),
        'admin sidebar brand mark must not add initial/icon fallback classes when sidebar logo exists'
    );
    sr_logo_manager_favicon_check_assert(
        str_contains($adminHeader, '$adminBrandLinkClass')
            && str_contains($adminHeader, 'has-sidebar-logo'),
        'admin sidebar brand link must expose a full-width sidebar-logo state class'
    );
    $mobileToggleOffset = strpos($adminHeader, 'id="btn_gnb_mobile"');
    $desktopToggleOffset = strpos($adminHeader, 'id="btn_gnb"');
    $breadcrumbOffset = strpos($adminHeader, 'class="hd_breadcrumb admin-breadcrumb"');
    sr_logo_manager_favicon_check_assert(
        is_int($mobileToggleOffset) && is_int($desktopToggleOffset) && is_int($breadcrumbOffset)
            && $mobileToggleOffset < $desktopToggleOffset
            && $desktopToggleOffset < $breadcrumbOffset,
        'admin sidebar collapse toggle must render in the topbar before breadcrumb'
    );
}

$adminCss = is_file('modules/admin/assets/admin.css') ? file_get_contents('modules/admin/assets/admin.css') : false;
sr_logo_manager_favicon_check_assert(is_string($adminCss), 'admin shell css must be readable');
if (is_string($adminCss)) {
    foreach ([
        'has-sidebar-logo+.admin-sidebar-brand-name{display:none}',
        '#gnb>h2>a.has-sidebar-logo{justify-content:center;width:100%}',
        '.admin-sidebar-brand-mark.has-sidebar-logo.has-brand-initial,#gnb>h2>a>.admin-sidebar-brand-mark.has-sidebar-logo.has-brand-icon{border:0;border-radius:0;background-color:transparent;color:inherit}',
        'body.admin-sidebar-condensed #gnb>h2>a>.admin-sidebar-brand-mark.has-sidebar-logo{width:calc(var(--spacing) * 10);max-width:calc(var(--spacing) * 10);border:0;border-radius:0;background-color:transparent;color:inherit;overflow:hidden}',
        'body.admin-sidebar-condensed #gnb .admin-sidebar-brand-mark.has-sidebar-logo .admin-sidebar-brand-logo-wrap{display:none}',
        'body.admin-sidebar-condensed #gnb .admin-sidebar-brand-mark.has-sidebar-logo .admin-sidebar-brand-compact{display:flex;height:100%;width:100%}',
        'body.admin-sidebar-condensed #gnb .admin-sidebar-brand-mark.has-sidebar-logo .admin-sidebar-brand-icon{height:100%;width:100%;object-fit:contain}',
        '@media (min-width:1024px){body.admin-sidebar-condensed #btn_gnb_mobile{display:none}}',
    ] as $marker) {
        sr_logo_manager_favicon_check_assert(
            str_contains($adminCss, $marker),
            'admin shell css must preserve sidebar logo display marker: ' . $marker
        );
    }
}

$adminShellScript = is_file('modules/admin/assets/admin-shell.js') ? file_get_contents('modules/admin/assets/admin-shell.js') : false;
sr_logo_manager_favicon_check_assert(is_string($adminShellScript), 'admin shell script must be readable');
if (is_string($adminShellScript)) {
    sr_logo_manager_favicon_check_assert(
        str_contains($adminShellScript, "desktopToggleIcon.textContent = desktopCollapsed ? 'keyboard_double_arrow_right' : 'keyboard_double_arrow_left'"),
        'admin sidebar toggle must switch material icon name instead of relying on CSS rotation'
    );
}

if ($errors !== []) {
    fwrite(STDERR, "logo manager favicon checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "logo manager favicon checks completed.\n";
