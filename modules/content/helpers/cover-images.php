<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_content_clean_cover_image_url(string $value): string
{
    $value = trim($value);
    if ($value === '' || sr_is_safe_relative_url($value) || sr_is_http_url($value)) {
        return $value;
    }

    return '';
}

function sr_content_cover_image_upload_max_bytes(): int
{
    return 5242880;
}

function sr_content_cover_image_upload_was_provided(mixed $file): bool
{
    return is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

function sr_content_cover_image_format_for_mime(string $mimeType): string
{
    return sr_image_format_for_mime($mimeType);
}

function sr_content_cover_image_mime_is_allowed(string $mimeType): bool
{
    return in_array(strtolower(trim($mimeType)), ['image/jpeg', 'image/png', 'image/webp'], true);
}

function sr_content_upload_cover_image(array $file): ?array
{
    if (!sr_content_cover_image_upload_was_provided($file)) {
        return null;
    }

    $validated = sr_upload_validate_file($file, [
        'max_bytes' => sr_content_cover_image_upload_max_bytes(),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
    ]);

    $targetFormat = sr_content_cover_image_format_for_mime((string) $validated['mime_type']);
    if ($targetFormat === '') {
        throw new RuntimeException('허용되지 않은 커버 이미지 형식입니다.');
    }

    $datePath = date('Y/m');
    $directory = SR_ROOT . '/storage/tmp/content-cover-images/' . $datePath;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('커버 이미지 저장 디렉터리를 만들 수 없습니다.');
    }

    $storedName = sr_upload_random_filename($targetFormat);
    $targetPath = sr_upload_safe_target_path($directory, $storedName);
    sr_upload_assert_target_path_writable($targetPath);

    if (!sr_upload_reencode_image((string) $validated['tmp_name'], $targetPath, $targetFormat, [
        'max_pixels' => 25000000,
        'quality' => 86,
    ])) {
        throw new RuntimeException('커버 이미지 재인코딩에 실패했습니다.');
    }

    $storedMimeType = sr_upload_detect_mime($targetPath);
    if (!sr_content_cover_image_mime_is_allowed($storedMimeType)) {
        @unlink($targetPath);
        throw new RuntimeException('저장된 커버 이미지 MIME을 확인할 수 없습니다.');
    }

    $storageKey = 'content/cover-images/' . $datePath . '/' . $storedName;
    $stored = sr_storage_put_file($targetPath, $storageKey, [
        'content_type' => $storedMimeType,
    ]);
    @unlink($targetPath);

    $storageReference = sr_storage_reference((string) $stored['driver'], $storageKey);
    $publicUrl = (string) ($stored['url'] ?? '');

    return [
        'driver' => (string) $stored['driver'],
        'key' => $storageKey,
        'path' => (string) ($stored['path'] ?? ''),
        'storage_key' => $storageKey,
        'public_url' => $publicUrl,
        'url' => '/content/cover-image?file=' . rawurlencode($storageReference),
    ];
}

function sr_content_cover_image_storage_key_is_valid(string $key): bool
{
    return preg_match('#\Acontent/cover-images/\d{4}/\d{2}/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $key) === 1;
}

function sr_content_cover_image_storage_reference(string $reference): ?array
{
    $storage = sr_storage_parse_reference($reference);
    if (!is_array($storage) || !sr_content_cover_image_storage_key_is_valid((string) $storage['key'])) {
        return null;
    }

    return $storage;
}

function sr_content_cover_image_storage_reference_from_url(string $url): ?array
{
    $url = sr_content_clean_cover_image_url($url);
    if ($url === '') {
        return null;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return null;
    }

    $path = (string) ($parts['path'] ?? '');
    $proxyPath = (string) (parse_url(sr_url('/content/cover-image'), PHP_URL_PATH) ?: '/content/cover-image');
    if ($path !== '/content/cover-image' && $path !== $proxyPath) {
        $config = sr_runtime_config();
        $s3 = sr_storage_s3_config($config);
        $baseUrl = rtrim((string) ($s3['public_base_url'] ?? ''), '/');
        if ($baseUrl === '' || !sr_is_http_url($baseUrl) || !str_starts_with($url, $baseUrl . '/')) {
            return null;
        }

        $key = rawurldecode(substr($url, strlen($baseUrl) + 1));
        if (!sr_content_cover_image_storage_key_is_valid($key)) {
            return null;
        }

        return ['driver' => 's3', 'key' => $key];
    }

    $query = [];
    parse_str((string) ($parts['query'] ?? ''), $query);
    $reference = is_string($query['file'] ?? null) ? (string) $query['file'] : '';
    if ($reference === '') {
        return null;
    }

    return sr_content_cover_image_storage_reference($reference);
}

function sr_content_cover_image_reference_count(PDO $pdo, string $url, int $exceptContentId = 0): int
{
    $url = sr_content_clean_cover_image_url($url);
    if ($url === '' || !sr_content_optional_table_exists($pdo, 'sr_content_items')) {
        return 0;
    }

    $sql = 'SELECT COUNT(*) FROM sr_content_items WHERE cover_image_url = :cover_image_url';
    $params = ['cover_image_url' => $url];
    if ($exceptContentId > 0) {
        $sql .= ' AND id <> :content_id';
        $params['content_id'] = $exceptContentId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function sr_content_delete_cover_image_storage(PDO $pdo, string $url, int $sourceId, string $sourceType, int $exceptContentId = 0): array
{
    $storage = sr_content_cover_image_storage_reference_from_url($url);
    if (!is_array($storage)) {
        return ['attempted' => false, 'deleted' => false, 'failed' => false, 'reference' => ''];
    }

    $driver = (string) ($storage['driver'] ?? 'local');
    $key = (string) ($storage['key'] ?? '');
    $reference = $driver . ':' . $key;
    if ($key === '' || sr_content_cover_image_reference_count($pdo, $url, $exceptContentId) > 0) {
        return ['attempted' => false, 'deleted' => false, 'failed' => false, 'reference' => $reference];
    }

    if (sr_storage_delete($driver, $key)) {
        return ['attempted' => true, 'deleted' => true, 'failed' => false, 'reference' => $reference];
    }

    sr_content_record_storage_cleanup_failure($pdo, $sourceType, $sourceId, $driver, $key, '콘텐츠 커버 이미지 저장소 정리에 실패했습니다.');
    return ['attempted' => true, 'deleted' => false, 'failed' => true, 'reference' => $reference];
}

function sr_content_cover_image_html(array $content, string $className, string $alt = ''): string
{
    $imageUrl = sr_content_clean_cover_image_url((string) ($content['cover_image_url'] ?? ''));
    $label = $alt !== '' ? $alt : (string) ($content['title'] ?? '');
    if ($imageUrl !== '') {
        $src = sr_is_http_url($imageUrl) ? $imageUrl : sr_url($imageUrl);
        return '<img class="' . sr_e($className) . '" src="' . sr_e($src) . '" alt="' . sr_e($label) . '" loading="lazy">';
    }

    return '<span class="' . sr_e($className . ' content-cover-placeholder') . '" aria-hidden="true"></span>';
}
