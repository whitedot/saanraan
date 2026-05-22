<?php

declare(strict_types=1);

function sr_logo_manager_usage_options(): array
{
    return [
        'admin_sidebar' => [
            'label' => sr_t('logo_manager::usage.admin_sidebar.label'),
            'hint' => sr_t('logo_manager::usage.admin_sidebar.hint'),
            'max_bytes' => 2097152,
        ],
        'public_header' => [
            'label' => sr_t('logo_manager::usage.public_header.label'),
            'hint' => sr_t('logo_manager::usage.public_header.hint'),
            'max_bytes' => 3145728,
        ],
        'mobile' => [
            'label' => sr_t('logo_manager::usage.mobile.label'),
            'hint' => sr_t('logo_manager::usage.mobile.hint'),
            'max_bytes' => 2097152,
        ],
        'favicon' => [
            'label' => sr_t('logo_manager::usage.favicon.label'),
            'hint' => sr_t('logo_manager::usage.favicon.hint'),
            'max_bytes' => 1048576,
        ],
        'og_image' => [
            'label' => sr_t('logo_manager::usage.og_image.label'),
            'hint' => sr_t('logo_manager::usage.og_image.hint'),
            'max_bytes' => 5242880,
        ],
    ];
}

function sr_logo_manager_usage_key(string $usageKey): string
{
    $usageKey = strtolower(trim($usageKey));
    return isset(sr_logo_manager_usage_options()[$usageKey]) ? $usageKey : 'public_header';
}

function sr_logo_manager_usage_label(string $usageKey): string
{
    $options = sr_logo_manager_usage_options();
    $usageKey = sr_logo_manager_usage_key($usageKey);

    return (string) ($options[$usageKey]['label'] ?? $usageKey);
}

function sr_logo_manager_status_label(string $status): string
{
    return match ($status) {
        'active' => '사용',
        'archived' => '보관',
        'disabled' => '중지',
        default => $status,
    };
}

function sr_logo_manager_clean_single_line(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function sr_logo_manager_clean_url(string $value): string
{
    $value = trim($value);
    if ($value === '' || sr_is_safe_relative_url($value) || sr_is_http_url($value)) {
        return $value;
    }

    return '';
}

function sr_logo_manager_format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return number_format(max(0, $bytes)) . ' bytes';
}

function sr_logo_manager_upload_was_provided(mixed $file): bool
{
    return is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

function sr_logo_manager_image_format_for_mime(string $mimeType): string
{
    return match (strtolower(trim($mimeType))) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => '',
    };
}

function sr_logo_manager_image_mime_is_allowed(string $mimeType): bool
{
    return in_array(strtolower(trim($mimeType)), ['image/jpeg', 'image/png', 'image/webp'], true);
}

function sr_logo_manager_upload_max_bytes(string $usageKey): int
{
    $options = sr_logo_manager_usage_options();
    $usageKey = sr_logo_manager_usage_key($usageKey);

    return (int) ($options[$usageKey]['max_bytes'] ?? 3145728);
}

function sr_logo_manager_upload_image(array $file, string $usageKey): array
{
    $usageKey = sr_logo_manager_usage_key($usageKey);
    $validated = sr_upload_validate_file($file, [
        'max_bytes' => sr_logo_manager_upload_max_bytes($usageKey),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
    ]);

    $sourcePath = (string) $validated['tmp_name'];
    $targetFormat = sr_logo_manager_image_format_for_mime((string) $validated['mime_type']);
    if ($targetFormat === '') {
        throw new RuntimeException('허용되지 않은 로고 이미지 형식입니다.');
    }

    $dimensions = @getimagesize($sourcePath);
    if (!is_array($dimensions) || (int) ($dimensions[0] ?? 0) < 1 || (int) ($dimensions[1] ?? 0) < 1) {
        throw new RuntimeException('이미지 크기를 확인할 수 없습니다.');
    }
    if ((int) $dimensions[0] * (int) $dimensions[1] > 25000000) {
        throw new RuntimeException('이미지 픽셀 수가 너무 큽니다.');
    }

    $datePath = date('Y/m');
    $directory = SR_ROOT . '/storage/tmp/logo-manager-images/' . $datePath;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('로고 이미지 임시 디렉터리를 만들 수 없습니다.');
    }

    $storedName = sr_upload_random_filename($targetFormat);
    $targetPath = sr_upload_safe_target_path($directory, $storedName);
    sr_upload_assert_target_path_writable($targetPath);

    $storedSourcePath = $sourcePath;
    $reencoded = sr_upload_reencode_image($sourcePath, $targetPath, $targetFormat, [
        'max_pixels' => 25000000,
        'quality' => 88,
    ]);
    if ($reencoded) {
        $storedSourcePath = $targetPath;
    }

    $storedMimeType = sr_upload_detect_mime($storedSourcePath);
    if (!sr_logo_manager_image_mime_is_allowed($storedMimeType)) {
        @unlink($targetPath);
        throw new RuntimeException('저장할 이미지 MIME을 확인할 수 없습니다.');
    }

    $storedDimensions = @getimagesize($storedSourcePath);
    if (is_array($storedDimensions)) {
        $dimensions = $storedDimensions;
    }

    $storageKey = 'logo_manager/images/' . $datePath . '/' . $storedName;
    $stored = sr_storage_put_file($storedSourcePath, $storageKey, [
        'content_type' => $storedMimeType,
    ]);
    $checksum = hash_file('sha256', $storedSourcePath);
    $sizeBytes = filesize($storedSourcePath);
    @unlink($targetPath);

    $storageReference = sr_storage_reference((string) $stored['driver'], $storageKey);
    $publicUrl = (string) ($stored['url'] ?? '');

    return [
        'driver' => (string) $stored['driver'],
        'storage_key' => $storageKey,
        'public_url' => $publicUrl !== '' ? $publicUrl : '/logo-manager/image?file=' . rawurlencode($storageReference),
        'mime_type' => $storedMimeType,
        'size_bytes' => is_int($sizeBytes) ? $sizeBytes : 0,
        'width' => (int) ($dimensions[0] ?? 0),
        'height' => (int) ($dimensions[1] ?? 0),
        'checksum_sha256' => is_string($checksum) ? $checksum : '',
        'reencoded' => $reencoded,
        'original_name' => (string) ($validated['original_name'] ?? ''),
    ];
}

function sr_logo_manager_image_storage_key_is_valid(string $key): bool
{
    return preg_match('#\Alogo_manager/images/\d{4}/\d{2}/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $key) === 1;
}

function sr_logo_manager_image_storage_reference(string $reference): ?array
{
    $storage = sr_storage_parse_reference($reference);
    if (!is_array($storage) || !sr_logo_manager_image_storage_key_is_valid((string) $storage['key'])) {
        return null;
    }

    return $storage;
}

function sr_logo_manager_asset_url(array $asset): string
{
    $publicUrl = trim((string) ($asset['public_url'] ?? ''));
    if ($publicUrl !== '' && (sr_is_safe_relative_url($publicUrl) || sr_is_http_url($publicUrl))) {
        return $publicUrl;
    }

    $driver = (string) ($asset['storage_driver'] ?? 'local');
    $key = (string) ($asset['storage_key'] ?? '');
    if (!in_array($driver, ['local', 's3'], true) || !sr_logo_manager_image_storage_key_is_valid($key)) {
        return '';
    }

    $publicUrl = sr_storage_public_url($driver, $key);
    if ($publicUrl !== '') {
        return $publicUrl;
    }

    return '/logo-manager/image?file=' . rawurlencode(sr_storage_reference($driver, $key));
}

function sr_logo_manager_url_for_output(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    return sr_is_http_url($url) ? $url : sr_url($url);
}

function sr_logo_manager_clean_admin_datetime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
    return $date instanceof DateTimeImmutable ? $date->format('Y-m-d H:i:00') : null;
}

function sr_logo_manager_admin_datetime_value(mixed $value): string
{
    if (!is_string($value) || $value === '') {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
    return $date instanceof DateTimeImmutable ? $date->format('Y-m-d\TH:i') : '';
}

function sr_logo_manager_active_assignment(PDO $pdo, string $usageKey, ?string $now = null): ?array
{
    $usageKey = sr_logo_manager_usage_key($usageKey);
    $now = $now !== null ? $now : sr_now();
    try {
        $stmt = $pdo->prepare(
            "SELECT a.id AS assignment_id, a.usage_key, a.asset_id, a.alt_text AS assignment_alt_text, a.link_url,
                    a.starts_at, a.ends_at, a.sort_order, asset.title, asset.alt_text AS asset_alt_text,
                    asset.storage_driver, asset.storage_key, asset.public_url, asset.mime_type,
                    asset.width, asset.height
             FROM sr_logo_manager_assignments a
             INNER JOIN sr_logo_manager_assets asset ON asset.id = a.asset_id
             WHERE a.usage_key = :usage_key
               AND a.status = 'active'
               AND asset.status = 'active'
               AND (a.starts_at IS NULL OR a.starts_at <= :now_start)
               AND (a.ends_at IS NULL OR a.ends_at >= :now_end)
             ORDER BY a.sort_order ASC, a.starts_at DESC, a.id DESC
             LIMIT 1"
        );
        $stmt->execute([
            'usage_key' => $usageKey,
            'now_start' => $now,
            'now_end' => $now,
        ]);

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'logo_manager_active_assignment_failed');
        return null;
    }
}

function sr_logo_manager_render_logo(PDO $pdo, string $usageKey, ?array $site = null, array $attributes = []): string
{
    $assignment = sr_logo_manager_active_assignment($pdo, $usageKey);
    if (!is_array($assignment)) {
        return '';
    }

    $src = sr_logo_manager_asset_url([
        'public_url' => $assignment['public_url'] ?? '',
        'storage_driver' => $assignment['storage_driver'] ?? 'local',
        'storage_key' => $assignment['storage_key'] ?? '',
    ]);
    if ($src === '') {
        return '';
    }

    if (array_key_exists('alt', $attributes)) {
        $alt = sr_logo_manager_clean_single_line((string) $attributes['alt'], 160);
    } else {
        $alt = trim((string) ($assignment['assignment_alt_text'] ?? ''));
        if ($alt === '') {
            $alt = trim((string) ($assignment['asset_alt_text'] ?? ''));
        }
        if ($alt === '') {
            $alt = is_array($site) ? trim((string) ($site['site_name'] ?? $site['name'] ?? '')) : '';
        }
    }

    $class = sr_logo_manager_clean_single_line((string) ($attributes['class'] ?? 'site-logo-image'), 120);
    $width = (int) ($assignment['width'] ?? 0);
    $height = (int) ($assignment['height'] ?? 0);

    $html = '<img class="' . sr_e($class) . '" src="' . sr_e(sr_logo_manager_url_for_output($src)) . '" alt="' . sr_e($alt) . '"';
    if ($width > 0 && $height > 0) {
        $html .= ' width="' . sr_e((string) $width) . '" height="' . sr_e((string) $height) . '"';
    }
    $html .= ' loading="eager" decoding="async">';

    return $html;
}

function sr_logo_manager_active_url(PDO $pdo, string $usageKey): string
{
    $assignment = sr_logo_manager_active_assignment($pdo, $usageKey);
    return is_array($assignment) ? sr_logo_manager_asset_url($assignment) : '';
}

function sr_logo_manager_favicon_link_tag(PDO $pdo): string
{
    $url = sr_logo_manager_active_url($pdo, 'favicon');
    if ($url === '') {
        return '';
    }

    $href = sr_e(sr_logo_manager_url_for_output($url));
    return '<link rel="icon" href="' . $href . '">' . PHP_EOL
        . '<link rel="apple-touch-icon" href="' . $href . '">';
}

function sr_logo_manager_og_image_url(PDO $pdo): string
{
    return sr_logo_manager_active_url($pdo, 'og_image');
}
