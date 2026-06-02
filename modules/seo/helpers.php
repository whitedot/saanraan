<?php

declare(strict_types=1);

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
        'title_suffix' => is_string($settings['title_suffix'] ?? null) ? (string) $settings['title_suffix'] : '',
        'default_description' => is_string($settings['default_description'] ?? null) ? (string) $settings['default_description'] : '',
        'default_og_image' => is_string($settings['default_og_image'] ?? null) ? (string) $settings['default_og_image'] : '',
        'sitemap_include_home' => (bool) ($settings['sitemap_include_home'] ?? true),
        'robots_disallow_paths' => is_string($settings['robots_disallow_paths'] ?? null) ? (string) $settings['robots_disallow_paths'] : '',
    ];
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

    $settings['title_suffix'] = sr_seo_clean_single_line((string) $settings['title_suffix'], 80);
    $settings['default_description'] = sr_seo_clean_single_line((string) $settings['default_description'], 255);
    $settings['default_og_image'] = sr_seo_clean_single_line((string) $settings['default_og_image'], 255);
    $settings['sitemap_include_home'] = (bool) $settings['sitemap_include_home'];
    $settings['robots_disallow_paths'] = sr_seo_clean_textarea((string) $settings['robots_disallow_paths'], 2000);

    return $settings;
}

function sr_seo_apply_public_defaults(PDO $pdo, array $seo): array
{
    try {
        if (!sr_module_enabled($pdo, 'seo')) {
            return $seo;
        }

        $settings = sr_seo_settings($pdo);
    } catch (Throwable $exception) {
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'seo_public_defaults_failed');
        }
        return $seo;
    }

    $titleSuffix = (string) ($settings['title_suffix'] ?? '');
    if ($titleSuffix !== '') {
        $title = trim((string) ($seo['title'] ?? ''));
        if ($title !== '' && !str_ends_with($title, ' - ' . $titleSuffix)) {
            $seo['title'] = $title . ' - ' . $titleSuffix;
        }
    }

    $defaultDescription = (string) ($settings['default_description'] ?? '');
    if ($defaultDescription !== '' && trim((string) ($seo['description'] ?? '')) === '') {
        $seo['description'] = $defaultDescription;
    }

    $defaultOgImage = (string) ($settings['default_og_image'] ?? '');
    if ($defaultOgImage !== '') {
        $og = isset($seo['og']) && is_array($seo['og']) ? $seo['og'] : [];
        if (trim((string) ($og['image'] ?? '')) === '') {
            $og['image'] = $defaultOgImage;
            $seo['og'] = $og;
        }
    }

    return $seo;
}

function sr_seo_clean_single_line(string $value, int $maxLength): string
{
    $value = trim(str_replace(["\r", "\n"], ' ', $value));
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function sr_seo_clean_textarea(string $value, int $maxLength): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    if (function_exists('mb_substr')) {
        return trim(mb_substr($value, 0, $maxLength));
    }

    return trim(substr($value, 0, $maxLength));
}

function sr_seo_og_image_upload_max_bytes(): int
{
    return 5242880;
}

function sr_seo_format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return number_format(max(0, $bytes)) . ' bytes';
}

function sr_seo_og_image_upload_was_provided(mixed $file): bool
{
    return is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

function sr_seo_image_format_for_mime(string $mimeType): string
{
    return match (strtolower(trim($mimeType))) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => '',
    };
}

function sr_seo_image_mime_is_allowed(string $mimeType): bool
{
    return in_array(strtolower(trim($mimeType)), ['image/jpeg', 'image/png', 'image/webp'], true);
}

function sr_seo_upload_og_image(array $file): array
{
    $validated = sr_upload_validate_file($file, [
        'max_bytes' => sr_seo_og_image_upload_max_bytes(),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
    ]);

    $sourcePath = (string) $validated['tmp_name'];
    $targetFormat = sr_seo_image_format_for_mime((string) $validated['mime_type']);
    if ($targetFormat === '') {
        throw new RuntimeException('허용되지 않은 OG 이미지 형식입니다.');
    }

    $dimensions = @getimagesize($sourcePath);
    if (!is_array($dimensions) || (int) ($dimensions[0] ?? 0) < 1 || (int) ($dimensions[1] ?? 0) < 1) {
        throw new RuntimeException('이미지 크기를 확인할 수 없습니다.');
    }
    if ((int) $dimensions[0] * (int) $dimensions[1] > 25000000) {
        throw new RuntimeException('이미지 픽셀 수가 너무 큽니다.');
    }

    $datePath = date('Y/m');
    $directory = SR_ROOT . '/storage/tmp/seo-og-images/' . $datePath;
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('OG 이미지 임시 디렉터리를 만들 수 없습니다. storage/tmp 디렉터리 쓰기 권한을 확인해 주세요.');
    }

    $storedName = sr_upload_random_filename($targetFormat);
    $targetPath = sr_upload_safe_target_path($directory, $storedName);
    sr_upload_assert_target_path_writable($targetPath);

    if (!sr_upload_reencode_image($sourcePath, $targetPath, $targetFormat, [
        'max_pixels' => 25000000,
        'quality' => 88,
    ])) {
        throw new RuntimeException('이미지 재인코딩에 실패했습니다.');
    }

    $storedMimeType = sr_upload_detect_mime($targetPath);
    if (!sr_seo_image_mime_is_allowed($storedMimeType)) {
        @unlink($targetPath);
        throw new RuntimeException('저장된 이미지 MIME을 확인할 수 없습니다.');
    }

    $storageKey = 'seo/og-images/' . $datePath . '/' . $storedName;
    $stored = sr_storage_put_file($targetPath, $storageKey, [
        'content_type' => $storedMimeType,
    ]);
    @unlink($targetPath);

    $storageReference = sr_storage_reference((string) $stored['driver'], $storageKey);
    $publicUrl = (string) ($stored['url'] ?? '');

    return [
        'driver' => (string) $stored['driver'],
        'storage_key' => $storageKey,
        'public_url' => $publicUrl !== '' ? $publicUrl : '/seo/image?file=' . rawurlencode($storageReference),
        'mime_type' => $storedMimeType,
    ];
}

function sr_seo_image_storage_key_is_valid(string $key): bool
{
    return preg_match('#\Aseo/og-images/\d{4}/\d{2}/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $key) === 1;
}

function sr_seo_image_storage_reference(string $reference): ?array
{
    $storage = sr_storage_parse_reference($reference);
    if (!is_array($storage) || !sr_seo_image_storage_key_is_valid((string) $storage['key'])) {
        return null;
    }

    return $storage;
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
