<?php

declare(strict_types=1);

function sr_storage_default_driver(?array $config = null): string
{
    $config = is_array($config) ? $config : sr_runtime_config();
    $storage = isset($config['storage']) && is_array($config['storage']) ? $config['storage'] : [];
    $driver = strtolower(trim((string) ($storage['default'] ?? 'local')));

    return in_array($driver, ['local', 's3'], true) ? $driver : 'local';
}

function sr_storage_key_is_safe(string $key): bool
{
    if ($key === '' || strlen($key) > 512 || str_contains($key, "\0") || str_starts_with($key, '/') || str_contains($key, '//')) {
        return false;
    }

    foreach (explode('/', $key) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..' || preg_match('/\A[A-Za-z0-9._=-]{1,160}\z/', $segment) !== 1) {
            return false;
        }
    }

    return true;
}

function sr_storage_reference(string $driver, string $key): string
{
    $driver = strtolower(trim($driver));
    if (!in_array($driver, ['local', 's3'], true) || !sr_storage_key_is_safe($key)) {
        throw new InvalidArgumentException('Storage reference is invalid.');
    }

    return $driver . ':' . $key;
}

function sr_storage_parse_reference(string $reference, string $fallbackDriver = 'local'): ?array
{
    $reference = trim($reference);
    $fallbackDriver = in_array($fallbackDriver, ['local', 's3'], true) ? $fallbackDriver : 'local';
    if (preg_match('/\A(local|s3):(.+)\z/', $reference, $matches) === 1) {
        $driver = (string) $matches[1];
        $key = (string) $matches[2];
    } else {
        $driver = $fallbackDriver;
        $key = $reference;
    }

    if (!sr_storage_key_is_safe($key)) {
        return null;
    }

    return [
        'driver' => $driver,
        'key' => $key,
    ];
}

function sr_storage_put_file(string $sourcePath, string $key, array $options = []): array
{
    if (!is_file($sourcePath) || !sr_storage_key_is_safe($key)) {
        throw new RuntimeException('저장할 파일 또는 저장 key가 올바르지 않습니다.');
    }

    $config = isset($options['config']) && is_array($options['config']) ? $options['config'] : sr_runtime_config();
    $driver = strtolower(trim((string) ($options['driver'] ?? sr_storage_default_driver($config))));
    if ($driver === 's3') {
        return sr_storage_s3_put_file($config, $sourcePath, $key, $options);
    }

    return sr_storage_local_put_file($sourcePath, $key, $options);
}

function sr_storage_copy(string $driver, string $sourceKey, string $targetKey, array $options = []): array
{
    $driver = strtolower(trim($driver));
    if (!in_array($driver, ['local', 's3'], true) || !sr_storage_key_is_safe($sourceKey) || !sr_storage_key_is_safe($targetKey)) {
        throw new RuntimeException('복사할 저장소 key가 올바르지 않습니다.');
    }

    if (empty($options['overwrite']) && sr_storage_head($driver, $targetKey, isset($options['config']) && is_array($options['config']) ? $options['config'] : null) !== null) {
        throw new RuntimeException('같은 저장소 key가 이미 존재합니다.');
    }

    if ($driver === 's3') {
        throw new RuntimeException('현재 저장소 driver에서는 첨부파일 포함 복사를 지원하지 않습니다.');
    }

    $sourcePath = sr_storage_local_path($sourceKey);
    if (!is_string($sourcePath) || !is_file($sourcePath)) {
        throw new RuntimeException('원본 파일을 찾을 수 없습니다.');
    }

    return sr_storage_local_put_file($sourcePath, $targetKey, $options);
}

function sr_storage_delete(string $driver, string $key, ?array $config = null): bool
{
    if (!sr_storage_key_is_safe($key)) {
        return false;
    }

    $driver = strtolower(trim($driver));
    if ($driver === 's3') {
        return sr_storage_s3_delete($config ?? sr_runtime_config(), $key);
    }

    $path = sr_storage_local_path($key);
    return is_string($path) && is_file($path) ? @unlink($path) : true;
}

function sr_storage_head(string $driver, string $key, ?array $config = null): ?array
{
    if (!sr_storage_key_is_safe($key)) {
        return null;
    }

    $driver = strtolower(trim($driver));
    if ($driver === 's3') {
        return sr_storage_s3_head($config ?? sr_runtime_config(), $key);
    }

    $path = sr_storage_local_path($key);
    if (!is_string($path) || !is_file($path)) {
        return null;
    }

    return [
        'content_length' => (int) filesize($path),
        'content_type' => sr_upload_detect_mime($path),
        'metadata' => [
            'sha256' => hash_file('sha256', $path) ?: '',
        ],
    ];
}

function sr_storage_public_url(string $driver, string $key, ?array $config = null): string
{
    if ($driver !== 's3' || !sr_storage_key_is_safe($key)) {
        return '';
    }

    $config = $config ?? sr_runtime_config();
    if (sr_storage_s3_config_errors($config) !== []) {
        return '';
    }

    $s3 = sr_storage_s3_config($config);
    $baseUrl = rtrim((string) ($s3['public_base_url'] ?? ''), '/');
    if ($baseUrl === '' || !sr_is_http_url($baseUrl)) {
        return '';
    }

    return $baseUrl . '/' . sr_storage_uri_encode($key);
}

function sr_storage_signed_url(string $driver, string $key, int $ttlSeconds = 300, array $options = [], ?array $config = null): string
{
    if ($driver !== 's3' || !sr_storage_key_is_safe($key)) {
        return '';
    }

    try {
        return sr_storage_s3_presigned_url($config ?? sr_runtime_config(), $key, $ttlSeconds, $options);
    } catch (Throwable $exception) {
        return '';
    }
}

function sr_storage_copy_to_temp_file(string $driver, string $key, array $options = []): ?array
{
    if (!sr_storage_key_is_safe($key)) {
        return null;
    }

    $driver = strtolower(trim($driver));
    $maxBytes = max(1, (int) ($options['max_bytes'] ?? 20971520));
    if ($driver === 'local') {
        $path = sr_storage_local_path($key);
        if (!is_string($path)) {
            return null;
        }
        $contentLength = (int) filesize($path);
        if ($contentLength < 1 || $contentLength > $maxBytes) {
            return null;
        }

        return [
            'path' => $path,
            'content_type' => sr_upload_detect_mime($path),
            'content_length' => $contentLength,
            'version_marker' => 'local:' . (string) (@filemtime($path) ?: '0') . ':' . (string) (@filesize($path) ?: '0'),
            'cleanup' => false,
        ];
    }

    if ($driver !== 's3') {
        return null;
    }

    $config = isset($options['config']) && is_array($options['config']) ? $options['config'] : sr_runtime_config();
    $head = sr_storage_s3_head($config, $key);
    if ($head === null || (int) ($head['content_length'] ?? 0) < 1 || (int) ($head['content_length'] ?? 0) > $maxBytes) {
        return null;
    }

    try {
        $headMetadata = isset($head['metadata']) && is_array($head['metadata']) ? $head['metadata'] : [];
        $downloadOptions = [];
        if (trim((string) ($headMetadata['version_id'] ?? '')) !== '') {
            $downloadOptions['versionId'] = trim((string) $headMetadata['version_id']);
        }
        $downloadUrl = sr_storage_s3_presigned_url($config, $key, 300, $downloadOptions);
    } catch (Throwable $exception) {
        return null;
    }
    $download = sr_storage_http_download_to_temp_file($downloadUrl, $maxBytes);
    if ($download === null) {
        return null;
    }
    return [
        'path' => (string) $download['path'],
        'content_type' => (string) ($download['headers']['content-type'] ?? $head['content_type'] ?? ''),
        'content_length' => (int) ($download['content_length'] ?? 0),
        'version_marker' => sr_storage_s3_version_marker($headMetadata, $head),
        'cleanup' => true,
    ];
}

function sr_storage_local_put_file(string $sourcePath, string $key, array $options = []): array
{
    $targetPath = SR_ROOT . '/storage/' . str_replace('/', DIRECTORY_SEPARATOR, $key);
    $directory = dirname($targetPath);
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('로컬 저장 디렉터리를 만들 수 없습니다. storage 디렉터리 쓰기 권한을 확인해 주세요.');
    }

    $targetPath = sr_upload_safe_target_path($directory, basename($key));
    sr_upload_assert_target_path_writable($targetPath, !empty($options['overwrite']));
    if (!copy($sourcePath, $targetPath)) {
        throw new RuntimeException('로컬 저장소에 파일을 저장할 수 없습니다.');
    }

    return [
        'driver' => 'local',
        'key' => $key,
        'path' => $targetPath,
        'url' => '',
    ];
}

function sr_storage_local_path(string $key): ?string
{
    if (!sr_storage_key_is_safe($key)) {
        return null;
    }

    $storageRoot = realpath(SR_ROOT . '/storage');
    if (!is_string($storageRoot) || !is_dir($storageRoot)) {
        return null;
    }

    $candidate = $storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
    $realPath = realpath($candidate);
    if (!is_string($realPath) || !is_file($realPath)) {
        return null;
    }

    $storagePrefix = rtrim($storageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return str_starts_with($realPath, $storagePrefix) ? $realPath : null;
}

function sr_thumbnail_supported(): array
{
    $mimeTypes = [];
    if (extension_loaded('gd')) {
        if (function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) {
            $mimeTypes[] = 'image/jpeg';
        }
        if (function_exists('imagecreatefrompng') && function_exists('imagepng')) {
            $mimeTypes[] = 'image/png';
        }
        if (function_exists('imagecreatefromgif') && function_exists('imagegif')) {
            $mimeTypes[] = 'image/gif';
        }
        if (function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
            $mimeTypes[] = 'image/webp';
        }
    }

    return [
        'gd' => extension_loaded('gd'),
        'imagick' => extension_loaded('imagick'),
        'mime_types' => $mimeTypes,
    ];
}

function sr_thumbnail_normalize_options(array $options): array
{
    $width = max(1, min(2000, (int) ($options['width'] ?? $options['max_width'] ?? 320)));
    $height = max(1, min(2000, (int) ($options['height'] ?? $options['max_height'] ?? 180)));
    $mode = strtolower(trim((string) ($options['mode'] ?? $options['fit'] ?? 'cover')));
    $format = strtolower(trim((string) ($options['format'] ?? 'source')));

    return [
        'width' => $width,
        'height' => $height,
        'mode' => in_array($mode, ['cover', 'contain'], true) ? $mode : 'cover',
        'quality' => max(1, min(95, (int) ($options['quality'] ?? 82))),
        'format' => in_array($format, ['source', 'jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? $format : 'source',
    ];
}

function sr_thumbnail_variant_key(array $options): string
{
    $normalized = sr_thumbnail_normalize_options($options);
    $format = $normalized['format'] === 'jpeg' ? 'jpg' : $normalized['format'];

    return 'w' . (string) $normalized['width']
        . '_h' . (string) $normalized['height']
        . '_' . $normalized['mode']
        . '_q' . (string) $normalized['quality']
        . '_' . $format;
}

function sr_thumbnail_module_key(array $source): string
{
    $moduleKey = strtolower(trim((string) ($source['module_key'] ?? $source['module'] ?? 'common')));
    return preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $moduleKey) === 1 ? $moduleKey : 'common';
}

function sr_thumbnail_source_version(array $source, ?array $head = null, ?string $sourcePath = null): string
{
    foreach (['source_version', 'checksum_sha256', 'checksum'] as $key) {
        $value = strtolower(trim((string) ($source[$key] ?? '')));
        if ($value !== '') {
            return substr(hash('sha256', $key . ':' . $value), 0, 16);
        }
    }

    $head = is_array($head) ? $head : [];
    $metadata = isset($head['metadata']) && is_array($head['metadata']) ? $head['metadata'] : [];
    $versionMarker = trim((string) ($head['version_marker'] ?? ''));
    if ($versionMarker === '') {
        $versionMarker = trim((string) ($metadata['version_id'] ?? ''));
    }
    if ($versionMarker === '') {
        $versionMarker = trim((string) ($metadata['etag'] ?? ''));
    }
    if ($versionMarker === '') {
        $lastModified = trim((string) ($metadata['last_modified'] ?? ''));
        $contentLength = (string) ($head['content_length'] ?? '');
        if ($lastModified !== '' || $contentLength !== '') {
            $versionMarker = $lastModified . ':' . $contentLength;
        }
    }
    if ($versionMarker !== '') {
        return substr(hash('sha256', 'head:' . $versionMarker), 0, 16);
    }

    if (is_string($sourcePath) && is_file($sourcePath)) {
        return substr(hash('sha256', 'local:' . (string) (@filemtime($sourcePath) ?: '0') . ':' . (string) (@filesize($sourcePath) ?: '0')), 0, 16);
    }

    return substr(hash('sha256', 'fallback:' . (string) time()), 0, 16);
}

function sr_thumbnail_public_url(PDO $pdo, array $source, array $options): string
{
    unset($pdo);

    $publicUrl = trim((string) ($source['public_url'] ?? ''));
    $publicSource = !empty($source['public']) || $publicUrl !== '';
    $driver = strtolower(trim((string) ($source['storage_driver'] ?? $source['driver'] ?? 'local')));
    $key = (string) ($source['storage_key'] ?? $source['key'] ?? '');
    $sourcePath = '';
    if ($driver === 'local' && is_string($source['source_path'] ?? null)) {
        $sourcePath = (string) $source['source_path'];
    }
    if (!$publicSource || !in_array($driver, ['local', 's3'], true) || (!sr_storage_key_is_safe($key) && $sourcePath === '')) {
        return $publicUrl;
    }

    $sourceFile = $sourcePath !== ''
        ? sr_thumbnail_local_source_file($sourcePath, (int) ($options['max_source_bytes'] ?? 20971520))
        : sr_storage_copy_to_temp_file($driver, $key, [
            'max_bytes' => (int) ($options['max_source_bytes'] ?? 20971520),
        ]);
    if (!is_array($sourceFile) || !is_string($sourceFile['path'] ?? null)) {
        return $publicUrl;
    }
    $sourcePath = (string) $sourceFile['path'];

    $imageInfo = @getimagesize($sourcePath);
    if (!is_array($imageInfo) || (int) ($imageInfo[0] ?? 0) < 1 || (int) ($imageInfo[1] ?? 0) < 1) {
        if (!empty($sourceFile['cleanup'])) {
            @unlink($sourcePath);
        }
        return $publicUrl;
    }
    $detectedImageMime = strtolower(trim((string) ($imageInfo['mime'] ?? sr_upload_detect_mime($sourcePath))));
    if (!in_array($detectedImageMime, sr_thumbnail_supported()['mime_types'], true)) {
        if (!empty($sourceFile['cleanup'])) {
            @unlink($sourcePath);
        }
        return $publicUrl;
    }
    $mimeType = $detectedImageMime;
    $sourcePixels = (int) ($imageInfo[0] ?? 0) * (int) ($imageInfo[1] ?? 0);
    if ($sourcePixels > max(1, (int) ($options['max_source_pixels'] ?? 40000000))) {
        if (!empty($sourceFile['cleanup'])) {
            @unlink($sourcePath);
        }
        return $publicUrl;
    }

    $normalized = sr_thumbnail_normalize_options($options);
    $format = $normalized['format'] === 'source' ? sr_thumbnail_format_for_mime($mimeType) : $normalized['format'];
    $format = $format === 'jpeg' ? 'jpg' : $format;
    if (!in_array($format, ['jpg', 'png', 'gif', 'webp'], true)) {
        if (!empty($sourceFile['cleanup'])) {
            @unlink($sourcePath);
        }
        return $publicUrl;
    }

    $moduleKey = sr_thumbnail_module_key($source);
    $sourceHash = hash('sha256', $driver . ':' . ($key !== '' ? $key : (string) ($sourceFile['source_id'] ?? $sourcePath)));
    $sourceVersion = sr_thumbnail_source_version($source, $sourceFile, $sourcePath);
    $variantKey = sr_thumbnail_variant_key($normalized);
    $cacheRelative = 'cache/thumbnails/' . $moduleKey . '/' . substr($sourceHash, 0, 2) . '/' . $sourceHash . '_' . $variantKey . '_' . $sourceVersion . '.' . $format;
    $cachePath = SR_ROOT . '/storage/' . str_replace('/', DIRECTORY_SEPARATOR, $cacheRelative);
    if (is_file($cachePath)) {
        if (!empty($sourceFile['cleanup'])) {
            @unlink($sourcePath);
        }
        return sr_thumbnail_public_cache_url($cacheRelative);
    }

    $cacheDir = dirname($cachePath);
    if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
        if (!empty($sourceFile['cleanup'])) {
            @unlink($sourcePath);
        }
        return $publicUrl;
    }
    if (!is_writable($cacheDir)) {
        @chmod($cacheDir, 0775);
    }
    if (!is_writable($cacheDir)) {
        if (!empty($sourceFile['cleanup'])) {
            @unlink($sourcePath);
        }
        return $publicUrl;
    }

    $temporaryPath = $cachePath . '.tmp.' . bin2hex(random_bytes(8));
    if (!sr_thumbnail_create_gd($sourcePath, $temporaryPath, $mimeType, $format, $normalized)
        || @getimagesize($temporaryPath) === false
        || !@rename($temporaryPath, $cachePath)
    ) {
        @unlink($temporaryPath);
        if (!empty($sourceFile['cleanup'])) {
            @unlink($sourcePath);
        }
        return $publicUrl;
    }

    if (!empty($sourceFile['cleanup'])) {
        @unlink($sourcePath);
    }
    @chmod($cachePath, 0664);
    return sr_thumbnail_public_cache_url($cacheRelative);
}

function sr_thumbnail_local_source_file(string $sourcePath, int $maxBytes): ?array
{
    if ($sourcePath === '' || str_contains($sourcePath, "\0")) {
        return null;
    }

    $storageRoot = realpath(SR_ROOT . '/storage');
    $realPath = realpath($sourcePath);
    if (!is_string($storageRoot) || !is_dir($storageRoot) || !is_string($realPath) || !is_file($realPath)) {
        return null;
    }

    $storagePrefix = rtrim($storageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($realPath, $storagePrefix)) {
        return null;
    }

    $contentLength = (int) filesize($realPath);
    if ($contentLength < 1 || $contentLength > max(1, $maxBytes)) {
        return null;
    }

    return [
        'path' => $realPath,
        'content_type' => sr_upload_detect_mime($realPath),
        'content_length' => $contentLength,
        'version_marker' => 'local:' . (string) (@filemtime($realPath) ?: '0') . ':' . (string) (@filesize($realPath) ?: '0'),
        'source_id' => substr($realPath, strlen($storagePrefix)),
        'cleanup' => false,
    ];
}

function sr_thumbnail_public_cache_url(string $cacheRelative): string
{
    $legacyPattern = '#\Acache/thumbnails/[a-f0-9]{2}/[a-f0-9]{64}_[A-Za-z0-9_]+_[0-9]+\.(?:jpe?g|png|gif|webp)\z#i';
    $versionedPattern = '#\Acache/thumbnails/[a-z][a-z0-9_]{1,39}/[a-f0-9]{2}/[a-f0-9]{64}_[A-Za-z0-9_]+_[a-f0-9]{16,64}\.(?:jpe?g|png|gif|webp)\z#i';
    if (preg_match($legacyPattern, $cacheRelative) !== 1 && preg_match($versionedPattern, $cacheRelative) !== 1) {
        return '';
    }

    $path = '/storage/' . str_replace('%2F', '/', rawurlencode($cacheRelative));

    return function_exists('sr_url') ? sr_url($path) : $path;
}

function sr_thumbnail_delete_variants(array $source): void
{
    $driver = strtolower(trim((string) ($source['storage_driver'] ?? $source['driver'] ?? 'local')));
    $key = (string) ($source['storage_key'] ?? $source['key'] ?? '');
    $sourceId = $key;
    if (!sr_storage_key_is_safe($key)) {
        if ($driver !== 'local' || !is_string($source['source_path'] ?? null)) {
            return;
        }
        $sourceFile = sr_thumbnail_local_source_file((string) $source['source_path'], PHP_INT_MAX);
        if (!is_array($sourceFile) || !is_string($sourceFile['source_id'] ?? null)) {
            return;
        }
        $sourceId = (string) $sourceFile['source_id'];
    }
    if ($sourceId === '') {
        return;
    }

    $sourceHash = hash('sha256', $driver . ':' . $sourceId);
    $prefix = substr($sourceHash, 0, 2);
    $legacyDir = SR_ROOT . '/storage/cache/thumbnails/' . $prefix;
    foreach (glob($legacyDir . '/' . $sourceHash . '_*') ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }

    foreach (glob(SR_ROOT . '/storage/cache/thumbnails/*/' . $prefix, GLOB_ONLYDIR) ?: [] as $dir) {
        foreach (glob($dir . '/' . $sourceHash . '_*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}

function sr_thumbnail_format_for_mime(string $mimeType): string
{
    return match (strtolower(trim($mimeType))) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        default => '',
    };
}

function sr_thumbnail_create_gd(string $sourcePath, string $targetPath, string $mimeType, string $format, array $options): bool
{
    if (!extension_loaded('gd')) {
        return false;
    }

    $sourceImage = match ($mimeType) {
        'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : false,
        'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : false,
        'image/gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($sourcePath) : false,
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
        default => false,
    };
    if (!$sourceImage instanceof GdImage) {
        return false;
    }

    $sourceWidth = imagesx($sourceImage);
    $sourceHeight = imagesy($sourceImage);
    $targetWidth = (int) $options['width'];
    $targetHeight = (int) $options['height'];
    if ($sourceWidth < 1 || $sourceHeight < 1 || $targetWidth < 1 || $targetHeight < 1) {
        imagedestroy($sourceImage);
        return false;
    }

    if ((string) $options['mode'] === 'contain') {
        $scale = min($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $copyWidth = max(1, (int) round($sourceWidth * $scale));
        $copyHeight = max(1, (int) round($sourceHeight * $scale));
        $dstX = (int) floor(($targetWidth - $copyWidth) / 2);
        $dstY = (int) floor(($targetHeight - $copyHeight) / 2);
        $srcX = 0;
        $srcY = 0;
        $srcWidth = $sourceWidth;
        $srcHeight = $sourceHeight;
    } else {
        $scale = max($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $srcWidth = max(1, (int) floor($targetWidth / $scale));
        $srcHeight = max(1, (int) floor($targetHeight / $scale));
        $srcX = max(0, (int) floor(($sourceWidth - $srcWidth) / 2));
        $srcY = max(0, (int) floor(($sourceHeight - $srcHeight) / 2));
        $copyWidth = $targetWidth;
        $copyHeight = $targetHeight;
        $dstX = 0;
        $dstY = 0;
    }

    $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
    if (!$targetImage instanceof GdImage) {
        imagedestroy($sourceImage);
        return false;
    }

    imagealphablending($targetImage, false);
    imagesavealpha($targetImage, true);
    $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
    if ($transparent !== false) {
        imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
    }

    $resampled = imagecopyresampled($targetImage, $sourceImage, $dstX, $dstY, $srcX, $srcY, $copyWidth, $copyHeight, $srcWidth, $srcHeight);
    imagedestroy($sourceImage);
    if (!$resampled) {
        imagedestroy($targetImage);
        return false;
    }

    $quality = (int) $options['quality'];
    $saved = match ($format) {
        'jpg' => function_exists('imagejpeg') ? @imagejpeg($targetImage, $targetPath, $quality) : false,
        'png' => function_exists('imagepng') ? @imagepng($targetImage, $targetPath, max(0, min(9, (int) round((100 - $quality) / 11)))) : false,
        'gif' => function_exists('imagegif') ? @imagegif($targetImage, $targetPath) : false,
        'webp' => function_exists('imagewebp') ? @imagewebp($targetImage, $targetPath, $quality) : false,
        default => false,
    };
    imagedestroy($targetImage);

    return $saved && is_file($targetPath);
}

function sr_storage_s3_config(array $config): array
{
    $storage = isset($config['storage']) && is_array($config['storage']) ? $config['storage'] : [];
    $s3 = isset($storage['s3']) && is_array($storage['s3']) ? $storage['s3'] : [];
    $accessKey = sr_storage_env_or_value($s3, 'access_key', 'access_key_env');
    $secretKey = sr_storage_env_or_value($s3, 'secret_key', 'secret_key_env');

    return [
        'bucket' => trim((string) ($s3['bucket'] ?? '')),
        'region' => trim((string) ($s3['region'] ?? 'us-east-1')),
        'endpoint' => rtrim(trim((string) ($s3['endpoint'] ?? '')), '/'),
        'public_base_url' => trim((string) ($s3['public_base_url'] ?? '')),
        'path_style' => !empty($s3['path_style']),
        'access_key' => $accessKey,
        'secret_key' => $secretKey,
    ];
}

function sr_storage_env_or_value(array $settings, string $valueKey, string $envKey): string
{
    $envName = trim((string) ($settings[$envKey] ?? ''));
    if ($envName !== '' && preg_match('/\A[A-Z][A-Z0-9_]{1,80}\z/', $envName) === 1) {
        $envValue = getenv($envName);
        if (is_string($envValue) && $envValue !== '') {
            return $envValue;
        }
    }

    return (string) ($settings[$valueKey] ?? '');
}

function sr_storage_s3_ready(array $config): bool
{
    try {
        $s3 = sr_storage_s3_config($config);
        return sr_storage_s3_bucket_is_valid($s3['bucket'])
            && $s3['region'] !== ''
            && $s3['access_key'] !== ''
            && $s3['secret_key'] !== ''
            && sr_storage_s3_config_errors($config) === [];
    } catch (Throwable $exception) {
        return false;
    }
}

function sr_storage_s3_config_errors(array $config): array
{
    $errors = [];
    $s3 = sr_storage_s3_config($config);
    $env = (string) ($config['env'] ?? 'production');

    if ($s3['bucket'] !== '' && !sr_storage_s3_bucket_is_valid($s3['bucket'])) {
        $errors[] = 'S3 bucket 형식이 올바르지 않습니다.';
    }

    foreach (['endpoint', 'public_base_url'] as $key) {
        $url = (string) $s3[$key];
        if ($url === '') {
            continue;
        }

        if (!sr_is_http_url($url)) {
            $errors[] = 'S3 ' . $key . ' URL이 올바르지 않습니다.';
            continue;
        }

        if ($env === 'production' && strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            $errors[] = '운영 환경의 S3 ' . $key . ' URL은 HTTPS여야 합니다.';
        }
    }

    return $errors;
}

function sr_storage_s3_put_file(array $config, string $sourcePath, string $key, array $options = []): array
{
    $contentType = (string) ($options['content_type'] ?? 'application/octet-stream');
    $body = file_get_contents($sourcePath);
    if (!is_string($body)) {
        throw new RuntimeException('S3에 저장할 파일을 읽을 수 없습니다.');
    }

    $headers = [
        'content-type' => sr_download_content_type($contentType),
        'x-amz-meta-sha256' => hash_file('sha256', $sourcePath) ?: '',
    ];
    $response = sr_storage_s3_request($config, 'PUT', $key, [], $headers, $body);
    if ($response['status'] < 200 || $response['status'] >= 300) {
        throw new RuntimeException('S3 저장에 실패했습니다.');
    }

    return [
        'driver' => 's3',
        'key' => $key,
        'path' => '',
        'url' => sr_storage_public_url('s3', $key, $config),
    ];
}

function sr_storage_s3_head(array $config, string $key): ?array
{
    try {
        $response = sr_storage_s3_request($config, 'HEAD', $key, [], [], '');
    } catch (Throwable $exception) {
        return null;
    }

    if ($response['status'] < 200 || $response['status'] >= 300) {
        return null;
    }

    $headers = $response['headers'];
    return [
        'content_length' => (int) ($headers['content-length'] ?? 0),
        'content_type' => (string) ($headers['content-type'] ?? ''),
        'metadata' => [
            'sha256' => (string) ($headers['x-amz-meta-sha256'] ?? ''),
            'version_id' => (string) ($headers['x-amz-version-id'] ?? ''),
            'etag' => trim((string) ($headers['etag'] ?? ''), '"'),
            'last_modified' => (string) ($headers['last-modified'] ?? ''),
        ],
    ];
}

function sr_storage_s3_version_marker(array $metadata, array $head = []): string
{
    foreach (['version_id', 'etag'] as $key) {
        $value = trim((string) ($metadata[$key] ?? ''));
        if ($value !== '') {
            return $key . ':' . $value;
        }
    }

    $lastModified = trim((string) ($metadata['last_modified'] ?? ''));
    $contentLength = trim((string) ($head['content_length'] ?? ''));
    return 'last_modified_length:' . $lastModified . ':' . $contentLength;
}

function sr_storage_s3_delete(array $config, string $key): bool
{
    try {
        $response = sr_storage_s3_request($config, 'DELETE', $key, [], [], '');
    } catch (Throwable $exception) {
        return false;
    }

    return $response['status'] >= 200 && $response['status'] < 300;
}

function sr_storage_s3_presigned_url(array $config, string $key, int $ttlSeconds, array $options = []): string
{
    $s3 = sr_storage_s3_assert_runtime_config($config);
    $urlParts = sr_storage_s3_object_url_parts($s3, $key);
    $now = gmdate('Ymd\THis\Z');
    $date = substr($now, 0, 8);
    $ttlSeconds = max(60, min(3600, $ttlSeconds));
    $scope = $date . '/' . $s3['region'] . '/s3/aws4_request';

    $query = [
        'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential' => $s3['access_key'] . '/' . $scope,
        'X-Amz-Date' => $now,
        'X-Amz-Expires' => (string) $ttlSeconds,
        'X-Amz-SignedHeaders' => 'host',
    ];
    foreach (['response-content-type', 'response-content-disposition', 'versionId'] as $keyName) {
        if (isset($options[$keyName]) && is_string($options[$keyName]) && $options[$keyName] !== '') {
            $query[$keyName] = $options[$keyName];
        }
    }

    $canonicalRequest = sr_storage_s3_canonical_request('GET', $urlParts['path'], $query, ['host' => $urlParts['host']], 'host', 'UNSIGNED-PAYLOAD');
    $signature = sr_storage_s3_signature($s3['secret_key'], $s3['region'], $date, $now, $canonicalRequest);
    $query['X-Amz-Signature'] = $signature;

    return $urlParts['base_url'] . '?' . sr_storage_s3_canonical_query($query);
}

function sr_storage_s3_request(array $config, string $method, string $key, array $query, array $headers, string $body): array
{
    $s3 = sr_storage_s3_assert_runtime_config($config);
    $urlParts = sr_storage_s3_object_url_parts($s3, $key);
    $payloadHash = hash('sha256', $body);
    $now = gmdate('Ymd\THis\Z');
    $date = substr($now, 0, 8);
    $headers = array_change_key_case($headers, CASE_LOWER);
    $headers['host'] = $urlParts['host'];
    $headers['x-amz-content-sha256'] = $payloadHash;
    $headers['x-amz-date'] = $now;
    ksort($headers, SORT_STRING);

    $signedHeaders = implode(';', array_keys($headers));
    $canonicalRequest = sr_storage_s3_canonical_request($method, $urlParts['path'], $query, $headers, $signedHeaders, $payloadHash);
    $credentialScope = $date . '/' . $s3['region'] . '/s3/aws4_request';
    $signature = sr_storage_s3_signature($s3['secret_key'], $s3['region'], $date, $now, $canonicalRequest);
    $headers['authorization'] = 'AWS4-HMAC-SHA256 Credential=' . $s3['access_key'] . '/' . $credentialScope . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

    $url = $urlParts['base_url'] . ($query === [] ? '' : '?' . sr_storage_s3_canonical_query($query));
    return sr_storage_http_request($method, $url, $headers, $body);
}

function sr_storage_s3_assert_config(array $s3): void
{
    if (!sr_storage_s3_bucket_is_valid($s3['bucket']) || $s3['region'] === '' || $s3['access_key'] === '' || $s3['secret_key'] === '') {
        throw new RuntimeException('S3 저장소 설정을 확인하세요.');
    }
}

function sr_storage_s3_assert_runtime_config(array $config): array
{
    $errors = sr_storage_s3_config_errors($config);
    if ($errors !== []) {
        throw new RuntimeException('S3 저장소 설정을 확인하세요: ' . implode(' ', $errors));
    }

    $s3 = sr_storage_s3_config($config);
    sr_storage_s3_assert_config($s3);

    return $s3;
}

function sr_storage_s3_bucket_is_valid(string $bucket): bool
{
    return preg_match('/\A[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]\z/', $bucket) === 1 && !str_contains($bucket, '..');
}

function sr_storage_s3_object_url_parts(array $s3, string $key): array
{
    $encodedKey = sr_storage_uri_encode($key);
    $endpoint = (string) $s3['endpoint'];
    $bucket = (string) $s3['bucket'];

    if ($endpoint === '') {
        $host = $s3['region'] === 'us-east-1'
            ? $bucket . '.s3.amazonaws.com'
            : $bucket . '.s3.' . $s3['region'] . '.amazonaws.com';
        return [
            'host' => $host,
            'path' => '/' . $encodedKey,
            'base_url' => 'https://' . $host . '/' . $encodedKey,
        ];
    }

    if (!sr_is_http_url($endpoint)) {
        throw new RuntimeException('S3 endpoint URL이 올바르지 않습니다.');
    }

    $scheme = (string) parse_url($endpoint, PHP_URL_SCHEME);
    $host = (string) parse_url($endpoint, PHP_URL_HOST);
    $port = parse_url($endpoint, PHP_URL_PORT);
    $basePath = rtrim((string) parse_url($endpoint, PHP_URL_PATH), '/');
    $portPart = is_int($port) ? ':' . (string) $port : '';
    if (!empty($s3['path_style'])) {
        $path = $basePath . '/' . $bucket . '/' . $encodedKey;
        $urlHost = $host . $portPart;
    } else {
        $path = $basePath . '/' . $encodedKey;
        $urlHost = $bucket . '.' . $host . $portPart;
    }

    return [
        'host' => $urlHost,
        'path' => $path === '' ? '/' : $path,
        'base_url' => $scheme . '://' . $urlHost . ($path === '' ? '/' : $path),
    ];
}

function sr_storage_s3_canonical_request(string $method, string $path, array $query, array $headers, string $signedHeaders, string $payloadHash): string
{
    $canonicalHeaders = '';
    ksort($headers, SORT_STRING);
    foreach ($headers as $name => $value) {
        $canonicalHeaders .= strtolower((string) $name) . ':' . trim(preg_replace('/\s+/', ' ', (string) $value) ?? '') . "\n";
    }

    return strtoupper($method) . "\n"
        . ($path === '' ? '/' : $path) . "\n"
        . sr_storage_s3_canonical_query($query) . "\n"
        . $canonicalHeaders . "\n"
        . $signedHeaders . "\n"
        . $payloadHash;
}

function sr_storage_s3_canonical_query(array $query): string
{
    ksort($query, SORT_STRING);
    $pairs = [];
    foreach ($query as $key => $value) {
        $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
    }

    return implode('&', $pairs);
}

function sr_storage_s3_signature(string $secretKey, string $region, string $date, string $amzDate, string $canonicalRequest): string
{
    $scope = $date . '/' . $region . '/s3/aws4_request';
    $stringToSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
    $dateKey = hash_hmac('sha256', $date, 'AWS4' . $secretKey, true);
    $dateRegionKey = hash_hmac('sha256', $region, $dateKey, true);
    $dateRegionServiceKey = hash_hmac('sha256', 's3', $dateRegionKey, true);
    $signingKey = hash_hmac('sha256', 'aws4_request', $dateRegionServiceKey, true);

    return hash_hmac('sha256', $stringToSign, $signingKey);
}

function sr_storage_uri_encode(string $value): string
{
    return str_replace('%2F', '/', rawurlencode($value));
}

function sr_storage_http_request(string $method, string $url, array $headers, string $body): array
{
    $headerLines = [];
    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }

    if (function_exists('curl_init')) {
        return sr_storage_http_request_with_curl($method, $url, $headerLines, $body);
    }

    return sr_storage_http_request_with_stream($method, $url, $headerLines, $body);
}

function sr_storage_http_download_to_temp_file(string $url, int $maxBytes): ?array
{
    $maxBytes = max(1, $maxBytes);
    $temporary = tempnam(sys_get_temp_dir(), 'sr-storage-');
    if (!is_string($temporary)) {
        return null;
    }

    try {
        $result = function_exists('curl_init')
            ? sr_storage_http_download_to_temp_file_with_curl($url, $temporary, $maxBytes)
            : sr_storage_http_download_to_temp_file_with_stream($url, $temporary, $maxBytes);
        if ($result === null) {
            @unlink($temporary);
        }

        return $result;
    } catch (Throwable $exception) {
        @unlink($temporary);
        return null;
    }
}

function sr_storage_http_download_to_temp_file_with_curl(string $url, string $temporary, int $maxBytes): ?array
{
    $file = fopen($temporary, 'wb');
    if (!is_resource($file)) {
        return null;
    }

    $headers = '';
    $bytes = 0;
    $tooLarge = false;
    $handle = curl_init($url);
    if ($handle === false) {
        fclose($file);
        return null;
    }

    curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($handle, CURLOPT_TIMEOUT, 30);
    curl_setopt($handle, CURLOPT_HEADERFUNCTION, static function ($curl, string $headerLine) use (&$headers): int {
        unset($curl);
        $headers .= $headerLine;
        return strlen($headerLine);
    });
    curl_setopt($handle, CURLOPT_WRITEFUNCTION, static function ($curl, string $chunk) use ($file, &$bytes, &$tooLarge, $maxBytes): int {
        unset($curl);
        $length = strlen($chunk);
        $bytes += $length;
        if ($bytes > $maxBytes) {
            $tooLarge = true;
            return 0;
        }

        $written = fwrite($file, $chunk);
        return is_int($written) ? $written : 0;
    });

    $ok = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    fclose($file);

    if ($ok !== true || $tooLarge || $status < 200 || $status >= 300 || !is_file($temporary)) {
        @unlink($temporary);
        return null;
    }

    return [
        'path' => $temporary,
        'headers' => sr_storage_parse_headers($headers),
        'content_length' => (int) filesize($temporary),
    ];
}

function sr_storage_http_download_to_temp_file_with_stream(string $url, string $temporary, int $maxBytes): ?array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
        ],
    ]);

    set_error_handler(static function (): bool {
        return true;
    });
    $stream = fopen($url, 'rb', false, $context);
    restore_error_handler();
    if (!is_resource($stream)) {
        return null;
    }

    $file = fopen($temporary, 'wb');
    if (!is_resource($file)) {
        fclose($stream);
        return null;
    }

    $bytes = 0;
    while (!feof($stream)) {
        $chunk = fread($stream, 8192);
        if (!is_string($chunk)) {
            fclose($file);
            fclose($stream);
            @unlink($temporary);
            return null;
        }
        if ($chunk === '') {
            continue;
        }
        $bytes += strlen($chunk);
        if ($bytes > $maxBytes || fwrite($file, $chunk) === false) {
            fclose($file);
            fclose($stream);
            @unlink($temporary);
            return null;
        }
    }

    $metadata = stream_get_meta_data($stream);
    fclose($file);
    fclose($stream);
    $responseHeaders = is_array($metadata['wrapper_data'] ?? null) ? $metadata['wrapper_data'] : [];
    $status = 0;
    foreach ($responseHeaders as $header) {
        if (preg_match('/\AHTTP\/\S+\s+(\d{3})\b/', (string) $header, $matches) === 1) {
            $status = (int) $matches[1];
        }
    }

    if ($status < 200 || $status >= 300 || !is_file($temporary)) {
        @unlink($temporary);
        return null;
    }

    return [
        'path' => $temporary,
        'headers' => sr_storage_parse_headers(implode("\r\n", $responseHeaders)),
        'content_length' => (int) filesize($temporary),
    ];
}

function sr_storage_http_request_with_curl(string $method, string $url, array $headerLines, string $body): array
{
    $handle = curl_init($url);
    if ($handle === false) {
        throw new RuntimeException('HTTP 요청을 준비할 수 없습니다.');
    }

    curl_setopt($handle, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($handle, CURLOPT_HTTPHEADER, $headerLines);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_HEADER, true);
    curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($handle, CURLOPT_TIMEOUT, 30);
    if ($method !== 'HEAD') {
        curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
    } else {
        curl_setopt($handle, CURLOPT_NOBODY, true);
    }

    $response = curl_exec($handle);
    if (!is_string($response)) {
        curl_close($handle);
        throw new RuntimeException('HTTP 요청에 실패했습니다.');
    }

    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    curl_close($handle);

    return [
        'status' => $status,
        'headers' => sr_storage_parse_headers(substr($response, 0, $headerSize)),
        'body' => substr($response, $headerSize),
    ];
}

function sr_storage_http_request_with_stream(string $method, string $url, array $headerLines, string $body): array
{
    $context = stream_context_create([
        'http' => [
            'method' => strtoupper($method),
            'timeout' => 30,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'header' => implode("\r\n", $headerLines) . "\r\n",
            'content' => $method === 'HEAD' ? '' : $body,
        ],
    ]);

    set_error_handler(static function (): bool {
        return true;
    });
    $stream = fopen($url, 'r', false, $context);
    restore_error_handler();
    if (!is_resource($stream)) {
        throw new RuntimeException('HTTP 요청에 실패했습니다.');
    }

    $metadata = stream_get_meta_data($stream);
    $responseBody = stream_get_contents($stream);
    fclose($stream);
    $responseHeaders = is_array($metadata['wrapper_data'] ?? null) ? $metadata['wrapper_data'] : [];
    $status = 0;
    foreach ($responseHeaders as $header) {
        if (preg_match('/\AHTTP\/\S+\s+(\d{3})\b/', (string) $header, $matches) === 1) {
            $status = (int) $matches[1];
        }
    }

    return [
        'status' => $status,
        'headers' => sr_storage_parse_headers(implode("\r\n", $responseHeaders)),
        'body' => is_string($responseBody) ? $responseBody : '',
    ];
}

function sr_storage_parse_headers(string $rawHeaders): array
{
    $headers = [];
    foreach (preg_split('/\r\n|\n|\r/', $rawHeaders) ?: [] as $line) {
        if (!is_string($line) || strpos($line, ':') === false) {
            continue;
        }

        [$name, $value] = explode(':', $line, 2);
        $headers[strtolower(trim($name))] = trim($value);
    }

    return $headers;
}
