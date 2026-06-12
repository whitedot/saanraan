#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

if (!function_exists('sr_module_metadata')) {
    function sr_module_metadata(string $moduleKey): array
    {
        if ($moduleKey !== 'seo') {
            return [];
        }

        return [
            'settings' => [
                'title_suffix' => '',
                'default_description' => '',
                'default_og_image' => '',
                'sitemap_include_home' => true,
                'robots_disallow_paths' => "/admin\n/account\n//bad\njavascript:bad\n/admin",
            ],
        ];
    }
}

if (!function_exists('sr_module_settings')) {
    function sr_module_settings(PDO $pdo, string $moduleKey): array
    {
        return $moduleKey === 'seo'
            ? [
                'sitemap_include_home' => true,
                'robots_disallow_paths' => "/admin\n/account\n//bad\njavascript:bad\n/admin",
            ]
            : [];
    }
}

if (!function_exists('sr_enabled_module_contract_files')) {
    function sr_enabled_module_contract_files(PDO $pdo, string $contractFile, array $excludedModuleKeys = []): array
    {
        return $contractFile === 'sitemap.php'
            ? ['fixture' => 'sitemap.php']
            : [];
    }
}

if (!function_exists('sr_load_module_contract_file')) {
    function sr_load_module_contract_file(string $moduleKey, string $file): mixed
    {
        if ($moduleKey !== 'fixture' || $file !== 'sitemap.php') {
            return null;
        }

        return static function (PDO $pdo, ?array $site): array {
            return [
                ['loc' => '/content/alpha', 'lastmod' => '2026-06-10', 'changefreq' => 'daily', 'priority' => '0.8'],
                ['loc' => '/content/alpha', 'priority' => '0.2'],
                ['loc' => 'https://external.example/item', 'lastmod' => 'bad-date', 'changefreq' => 'often', 'priority' => '2.5'],
                ['loc' => '//evil.example/path'],
                ['loc' => "/bad\npath"],
                ['loc' => '/content/beta', 'priority' => '-1'],
            ];
        };
    }
}

if (!function_exists('sr_is_http_url')) {
    function sr_is_http_url(string $url): bool
    {
        return preg_match('#\Ahttps?://#i', trim($url)) === 1;
    }
}

if (!function_exists('sr_is_safe_relative_url')) {
    function sr_is_safe_relative_url(string $url): bool
    {
        return $url !== ''
            && str_starts_with($url, '/')
            && !str_starts_with($url, '//')
            && !str_contains($url, "\\")
            && !preg_match('/[\x00-\x1F\x7F]/', $url);
    }
}

if (!function_exists('sr_current_base_url')) {
    function sr_current_base_url(): string
    {
        return '';
    }
}

require_once $root . '/modules/seo/helpers.php';

$errors = [];

function sr_seo_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_seo_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_seo_runtime_error($message);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$site = ['base_url' => 'https://example.test/base'];

$entries = sr_seo_sitemap_entries($pdo, $site);
$locs = array_map(static fn (array $entry): string => (string) ($entry['loc'] ?? ''), $entries);
sr_seo_runtime_assert($locs === [
    'https://example.test/base/',
    'https://example.test/base/content/alpha',
    'https://external.example/item',
    'https://example.test/base/content/beta',
], 'SEO sitemap runtime fixture must include home, normalize URLs, dedupe locs, and drop unsafe paths.');

$alpha = $entries[1] ?? [];
sr_seo_runtime_assert(($alpha['lastmod'] ?? '') === '2026-06-10', 'SEO sitemap runtime fixture must preserve valid lastmod.');
sr_seo_runtime_assert(($alpha['changefreq'] ?? '') === 'daily', 'SEO sitemap runtime fixture must preserve valid changefreq.');
sr_seo_runtime_assert(($alpha['priority'] ?? '') === '0.8', 'SEO sitemap runtime fixture must normalize numeric priority.');

$external = $entries[2] ?? [];
sr_seo_runtime_assert(!array_key_exists('lastmod', $external), 'SEO sitemap runtime fixture must drop invalid lastmod.');
sr_seo_runtime_assert(!array_key_exists('changefreq', $external), 'SEO sitemap runtime fixture must drop invalid changefreq.');
sr_seo_runtime_assert(($external['priority'] ?? '') === '1.0', 'SEO sitemap runtime fixture must clamp high priority.');

$beta = $entries[3] ?? [];
sr_seo_runtime_assert(($beta['priority'] ?? '') === '0.0', 'SEO sitemap runtime fixture must clamp low priority.');

$xml = sr_seo_sitemap_xml($entries);
sr_seo_runtime_assert(str_starts_with($xml, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"), 'SEO sitemap XML must include XML declaration.');
sr_seo_runtime_assert(str_contains($xml, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'), 'SEO sitemap XML must include sitemap namespace.');
sr_seo_runtime_assert(str_contains($xml, '<loc>https://example.test/base/content/alpha</loc>'), 'SEO sitemap XML must include normalized loc.');

$robots = sr_seo_robots_txt($site, sr_seo_settings($pdo));
sr_seo_runtime_assert(str_starts_with($robots, "User-agent: *\n"), 'SEO robots runtime fixture must start with user-agent.');
sr_seo_runtime_assert(substr_count($robots, "Disallow: /admin\n") === 1, 'SEO robots runtime fixture must dedupe disallow paths.');
sr_seo_runtime_assert(str_contains($robots, "Disallow: /account\n"), 'SEO robots runtime fixture must include safe disallow paths.');
sr_seo_runtime_assert(!str_contains($robots, '//bad'), 'SEO robots runtime fixture must drop unsafe protocol-relative paths.');
sr_seo_runtime_assert(!str_contains($robots, 'javascript:'), 'SEO robots runtime fixture must drop unsafe pseudo URLs.');
sr_seo_runtime_assert(str_contains($robots, "Sitemap: https://example.test/base/sitemap.xml\n"), 'SEO robots runtime fixture must include sitemap URL.');

$sitemapAction = file_get_contents($root . '/modules/seo/actions/sitemap.php');
$robotsAction = file_get_contents($root . '/modules/seo/actions/robots.php');
sr_seo_runtime_assert(is_string($sitemapAction) && str_contains($sitemapAction, 'sr_site_member_only_enabled($site ?? null)'), 'SEO sitemap action must fail closed for member-only sites.');
sr_seo_runtime_assert(is_string($robotsAction) && str_contains($robotsAction, "Disallow: /"), 'SEO robots action must disallow all paths for member-only sites.');

if ($errors !== []) {
    fwrite(STDERR, "SEO runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "SEO runtime checks completed.\n";
