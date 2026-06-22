<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/helpers/common.php';

function sr_seo_sitemap_entries(PDO $pdo, ?array $site): array
{
    $settings = sr_seo_settings($pdo);
    $entries = [];
    if (!empty($settings['sitemap_include_home'])) {
        $homeUrl = sr_seo_sitemap_absolute_url($site, '/');
        if ($homeUrl !== '') {
            $entries[] = [
                'loc' => $homeUrl,
                'priority' => '1.0',
            ];
        }
    }

    foreach (sr_enabled_module_contract_files($pdo, 'sitemap.php', ['seo']) as $moduleKey => $sitemapFile) {
        $moduleEntries = sr_load_module_contract_file($moduleKey, $sitemapFile);
        if (is_callable($moduleEntries)) {
            $moduleEntries = $moduleEntries($pdo, $site);
        }

        if (!is_array($moduleEntries)) {
            continue;
        }

        foreach ($moduleEntries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $normalized = sr_seo_normalize_sitemap_entry($site, $entry);
            if ($normalized !== null) {
                $entries[] = $normalized;
            }
        }
    }

    return sr_seo_unique_sitemap_entries($entries);
}

function sr_seo_default_settings(): array
{
    $metadata = sr_module_metadata('seo');
    $settings = isset($metadata['settings']) && is_array($metadata['settings']) ? $metadata['settings'] : [];

    return [
        'sitemap_include_home' => (bool) ($settings['sitemap_include_home'] ?? true),
        'robots_disallow_paths' => is_string($settings['robots_disallow_paths'] ?? null) ? (string) $settings['robots_disallow_paths'] : '',
    ];
}

function sr_seo_install_default_title_suffix(PDO $pdo, ?string $siteName = null): void
{
    if (!function_exists('sr_site_setting') || !function_exists('sr_site_title_suffix') || !function_exists('sr_save_site_setting')) {
        return;
    }

    $titleSuffix = sr_seo_clean_single_line((string) ($siteName ?? sr_site_setting($pdo, 'site.name', '')), 80);
    if ($titleSuffix === '' || sr_site_title_suffix($pdo) !== '') {
        return;
    }

    sr_save_site_setting($pdo, 'site.title_suffix', $titleSuffix, 'string');
}

function sr_seo_settings(PDO $pdo): array
{
    $settings = sr_seo_default_settings();
    $stored = sr_module_settings($pdo, 'seo');

    foreach ($settings as $key => $default) {
        if (array_key_exists($key, $stored)) {
            $settings[$key] = $stored[$key];
        }
    }

    $settings['sitemap_include_home'] = (bool) $settings['sitemap_include_home'];
    $settings['robots_disallow_paths'] = sr_seo_clean_textarea((string) $settings['robots_disallow_paths'], 2000);

    return $settings;
}

function sr_seo_apply_public_defaults(PDO $pdo, array $seo): array
{
    return $seo;
}

function sr_seo_clean_single_line(string $value, int $maxLength): string
{
    return sr_clean_single_line($value, $maxLength);
}

function sr_seo_clean_textarea(string $value, int $maxLength): string
{
    return sr_clean_text($value, $maxLength);
}

function sr_seo_disallow_paths(string $value): array
{
    $paths = [];
    foreach (explode("\n", $value) as $line) {
        $path = trim($line);
        if (!sr_is_safe_relative_url($path)) {
            continue;
        }

        $paths[$path] = true;
    }

    return array_keys($paths);
}

function sr_seo_sitemap_absolute_url(?array $site, string $url): string
{
    if (sr_is_http_url($url)) {
        return $url;
    }

    if (!sr_is_safe_relative_url($url)) {
        return '';
    }

    $baseUrl = is_array($site) ? (string) ($site['base_url'] ?? '') : '';
    if ($baseUrl === '' || !sr_is_http_url($baseUrl)) {
        $baseUrl = sr_current_base_url();
    }

    if ($baseUrl === '' || !sr_is_http_url($baseUrl)) {
        return '';
    }

    return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
}

function sr_seo_normalize_sitemap_entry(?array $site, array $entry): ?array
{
    $loc = sr_seo_sitemap_absolute_url($site, (string) ($entry['loc'] ?? ''));
    if ($loc === '' || strlen($loc) > 2048) {
        return null;
    }

    $normalized = ['loc' => $loc];

    $lastmod = (string) ($entry['lastmod'] ?? '');
    if ($lastmod !== '' && preg_match('/\A\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}:\d{2}(?:[+-]\d{2}:\d{2}|Z)?)?\z/', $lastmod) === 1) {
        $normalized['lastmod'] = $lastmod;
    }

    $changefreq = (string) ($entry['changefreq'] ?? '');
    if (in_array($changefreq, ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'], true)) {
        $normalized['changefreq'] = $changefreq;
    }

    if (isset($entry['priority']) && is_numeric($entry['priority'])) {
        $priority = max(0.0, min(1.0, (float) $entry['priority']));
        $normalized['priority'] = number_format($priority, 1, '.', '');
    }

    return $normalized;
}

function sr_seo_unique_sitemap_entries(array $entries): array
{
    $seen = [];
    $unique = [];

    foreach ($entries as $entry) {
        $loc = (string) ($entry['loc'] ?? '');
        if ($loc === '' || isset($seen[$loc])) {
            continue;
        }

        $seen[$loc] = true;
        $unique[] = $entry;
    }

    return $unique;
}

function sr_seo_sitemap_xml(array $entries): string
{
    $lines = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    ];

    foreach ($entries as $entry) {
        if (!is_array($entry) || empty($entry['loc'])) {
            continue;
        }

        $lines[] = '    <url>';
        $lines[] = '        <loc>' . sr_seo_xml_e((string) $entry['loc']) . '</loc>';
        foreach (['lastmod', 'changefreq', 'priority'] as $key) {
            if (!empty($entry[$key])) {
                $lines[] = '        <' . $key . '>' . sr_seo_xml_e((string) $entry[$key]) . '</' . $key . '>';
            }
        }
        $lines[] = '    </url>';
    }

    $lines[] = '</urlset>';

    return implode("\n", $lines) . "\n";
}

function sr_seo_robots_txt(?array $site, array $settings = []): string
{
    $settings = array_merge(sr_seo_default_settings(), $settings);
    $lines = [
        'User-agent: *',
    ];

    foreach (sr_seo_disallow_paths((string) ($settings['robots_disallow_paths'] ?? '')) as $path) {
        $lines[] = 'Disallow: ' . $path;
    }

    $sitemapUrl = sr_seo_sitemap_absolute_url($site, '/sitemap.xml');
    if ($sitemapUrl !== '') {
        $lines[] = 'Sitemap: ' . $sitemapUrl;
    }

    return implode("\n", $lines) . "\n";
}

function sr_seo_xml_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}
