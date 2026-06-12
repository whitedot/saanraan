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

function sr_logo_manager_favicon_check_assert_disabled_tag(string $html, string $message): void
{
    sr_logo_manager_favicon_check_assert(str_contains($html, 'data:image/svg+xml'), $message . ' disabled data icon must render');
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

$pdo = sr_logo_manager_favicon_check_pdo();
sr_logo_manager_favicon_check_insert_logo($pdo, [
    'id' => 2,
    'status' => 'disabled',
    'use_as_public_symbol' => 1,
    'public_url' => '/uploads/favicon-disabled.png',
]);
$html = sr_logo_manager_favicon_link_tag($pdo);
sr_logo_manager_favicon_check_assert_disabled_tag($html, 'disabled public.favicon logo');
sr_logo_manager_favicon_check_assert(str_contains($html, 'data-sr-logo-manager-version'), 'disabled favicon data icon must include cache-busting version marker');

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
sr_logo_manager_favicon_check_assert_disabled_tag($html, 'past or future public.favicon logos');

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
sr_logo_manager_favicon_check_assert_disabled_tag($html, 'favicon disabled after active state transition');
sr_logo_manager_favicon_check_assert($html !== $activeHtml, 'favicon disabled state transition must change rendered head links');

$pdo = sr_logo_manager_favicon_check_pdo();
$html = sr_logo_manager_favicon_link_tag($pdo);
sr_logo_manager_favicon_check_assert($html === '', 'empty favicon configuration should not render disabled data icon');

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

if ($errors !== []) {
    fwrite(STDERR, "logo manager favicon checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "logo manager favicon checks completed.\n";
