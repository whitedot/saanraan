<?php

declare(strict_types=1);

function toy_seo_sitemap_entries(PDO $pdo, ?array $site): array
{
    $settings = toy_seo_settings($pdo);
    $entries = [];
    if (!empty($settings['sitemap_include_home'])) {
        $homeUrl = toy_seo_sitemap_absolute_url($site, '/');
        if ($homeUrl !== '') {
            $entries[] = [
                'loc' => $homeUrl,
                'priority' => '1.0',
            ];
        }
    }

    foreach (toy_enabled_module_contract_files($pdo, 'sitemap.php', ['seo']) as $sitemapFile) {
        $moduleEntries = include $sitemapFile;
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

            $normalized = toy_seo_normalize_sitemap_entry($site, $entry);
            if ($normalized !== null) {
                $entries[] = $normalized;
            }
        }
    }

    return toy_seo_unique_sitemap_entries($entries);
}

function toy_seo_default_settings(): array
{
    $metadata = toy_module_metadata('seo');
    $settings = isset($metadata['settings']) && is_array($metadata['settings']) ? $metadata['settings'] : [];

    return [
        'title_suffix' => is_string($settings['title_suffix'] ?? null) ? (string) $settings['title_suffix'] : '',
        'default_description' => is_string($settings['default_description'] ?? null) ? (string) $settings['default_description'] : '',
        'default_og_image' => is_string($settings['default_og_image'] ?? null) ? (string) $settings['default_og_image'] : '',
        'sitemap_include_home' => (bool) ($settings['sitemap_include_home'] ?? true),
        'robots_disallow_paths' => is_string($settings['robots_disallow_paths'] ?? null) ? (string) $settings['robots_disallow_paths'] : '',
    ];
}

function toy_seo_settings(PDO $pdo): array
{
    $settings = toy_seo_default_settings();
    $stored = toy_module_settings($pdo, 'seo');

    foreach ($settings as $key => $default) {
        if (array_key_exists($key, $stored)) {
            $settings[$key] = $stored[$key];
        }
    }

    $settings['title_suffix'] = toy_seo_clean_single_line((string) $settings['title_suffix'], 80);
    $settings['default_description'] = toy_seo_clean_single_line((string) $settings['default_description'], 255);
    $settings['default_og_image'] = toy_seo_clean_single_line((string) $settings['default_og_image'], 255);
    $settings['sitemap_include_home'] = (bool) $settings['sitemap_include_home'];
    $settings['robots_disallow_paths'] = toy_seo_clean_textarea((string) $settings['robots_disallow_paths'], 2000);

    return $settings;
}

function toy_seo_clean_single_line(string $value, int $maxLength): string
{
    $value = trim(str_replace(["\r", "\n"], ' ', $value));
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function toy_seo_clean_textarea(string $value, int $maxLength): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    if (function_exists('mb_substr')) {
        return trim(mb_substr($value, 0, $maxLength));
    }

    return trim(substr($value, 0, $maxLength));
}

function toy_seo_disallow_paths(string $value): array
{
    $paths = [];
    foreach (explode("\n", $value) as $line) {
        $path = trim($line);
        if (!toy_is_safe_relative_url($path)) {
            continue;
        }

        $paths[$path] = true;
    }

    return array_keys($paths);
}

function toy_seo_sitemap_absolute_url(?array $site, string $url): string
{
    if (toy_is_http_url($url)) {
        return $url;
    }

    if (!toy_is_safe_relative_url($url)) {
        return '';
    }

    $baseUrl = is_array($site) ? (string) ($site['base_url'] ?? '') : '';
    if ($baseUrl === '' || !toy_is_http_url($baseUrl)) {
        $baseUrl = toy_current_base_url();
    }

    if ($baseUrl === '' || !toy_is_http_url($baseUrl)) {
        return '';
    }

    return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
}

function toy_seo_normalize_sitemap_entry(?array $site, array $entry): ?array
{
    $loc = toy_seo_sitemap_absolute_url($site, (string) ($entry['loc'] ?? ''));
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

function toy_seo_unique_sitemap_entries(array $entries): array
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

function toy_seo_sitemap_xml(array $entries): string
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
        $lines[] = '        <loc>' . toy_seo_xml_e((string) $entry['loc']) . '</loc>';
        foreach (['lastmod', 'changefreq', 'priority'] as $key) {
            if (!empty($entry[$key])) {
                $lines[] = '        <' . $key . '>' . toy_seo_xml_e((string) $entry[$key]) . '</' . $key . '>';
            }
        }
        $lines[] = '    </url>';
    }

    $lines[] = '</urlset>';

    return implode("\n", $lines) . "\n";
}

function toy_seo_robots_txt(?array $site, array $settings = []): string
{
    $settings = array_merge(toy_seo_default_settings(), $settings);
    $lines = [
        'User-agent: *',
    ];

    foreach (toy_seo_disallow_paths((string) ($settings['robots_disallow_paths'] ?? '')) as $path) {
        $lines[] = 'Disallow: ' . $path;
    }

    $sitemapUrl = toy_seo_sitemap_absolute_url($site, '/sitemap.xml');
    if ($sitemapUrl !== '') {
        $lines[] = 'Sitemap: ' . $sitemapUrl;
    }

    return implode("\n", $lines) . "\n";
}

function toy_seo_xml_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}
