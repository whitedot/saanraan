<?php

declare(strict_types=1);

function toy_upload_error_message(int $errorCode): string
{
    $messages = [
        UPLOAD_ERR_OK => '업로드가 완료되었습니다.',
        UPLOAD_ERR_INI_SIZE => '업로드 파일이 서버 허용 용량을 초과했습니다.',
        UPLOAD_ERR_FORM_SIZE => '업로드 파일이 폼 허용 용량을 초과했습니다.',
        UPLOAD_ERR_PARTIAL => '업로드가 중간에 중단되었습니다.',
        UPLOAD_ERR_NO_FILE => '업로드 파일이 없습니다.',
        UPLOAD_ERR_NO_TMP_DIR => '임시 업로드 디렉터리가 없습니다.',
        UPLOAD_ERR_CANT_WRITE => '업로드 파일을 저장할 수 없습니다.',
        UPLOAD_ERR_EXTENSION => '서버 확장이 업로드를 중단했습니다.',
    ];

    return $messages[$errorCode] ?? '알 수 없는 업로드 오류입니다.';
}

function toy_upload_filename(string $filename): string
{
    $filename = str_replace(['\\', '/'], '-', $filename);
    $filename = preg_replace('/[\x00-\x1F\x7F]+/', '-', $filename);
    $filename = is_string($filename) ? $filename : '';
    $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename);
    $filename = is_string($filename) ? preg_replace('/-+/', '-', $filename) : '';
    $filename = is_string($filename) ? trim($filename, '.-_') : '';

    if ($filename === '') {
        return 'upload.bin';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($filename, 0, 120);
    }

    return substr($filename, 0, 120);
}

function toy_upload_extension(string $filename): string
{
    $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    if (preg_match('/\A[a-z0-9]{1,16}\z/', $extension) !== 1) {
        return '';
    }

    return $extension;
}

function toy_upload_normalize_extensions(array $extensions): array
{
    $normalized = [];
    foreach ($extensions as $extension) {
        if (!is_string($extension) && !is_int($extension)) {
            continue;
        }

        $extension = strtolower(ltrim((string) $extension, '.'));
        if (preg_match('/\A[a-z0-9]{1,16}\z/', $extension) !== 1) {
            continue;
        }

        $normalized[$extension] = true;
    }

    return array_keys($normalized);
}

function toy_upload_is_executable_extension(string $extension): bool
{
    $extension = strtolower(ltrim($extension, '.'));
    return in_array($extension, [
        'asp',
        'aspx',
        'bat',
        'cgi',
        'cmd',
        'com',
        'exe',
        'hta',
        'htaccess',
        'html',
        'js',
        'jsp',
        'phtml',
        'phar',
        'php',
        'php3',
        'php4',
        'php5',
        'php7',
        'php8',
        'pl',
        'py',
        'shtml',
        'sh',
    ], true);
}

function toy_upload_normalize_mime_types(array $mimeTypes): array
{
    $normalized = [];
    foreach ($mimeTypes as $mimeType) {
        if (!is_string($mimeType)) {
            continue;
        }

        $mimeType = strtolower(trim($mimeType));
        if (preg_match('/\A[a-z0-9][a-z0-9.+-]*\/[a-z0-9][a-z0-9.+-]*\z/', $mimeType) !== 1) {
            continue;
        }

        $normalized[$mimeType] = true;
    }

    return array_keys($normalized);
}

function toy_upload_detect_mime(string $path): string
{
    if (!is_file($path)) {
        return '';
    }

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mimeType = finfo_file($finfo, $path);
            finfo_close($finfo);
            if (is_string($mimeType) && $mimeType !== '') {
                return strtolower($mimeType);
            }
        }
    }

    if (function_exists('mime_content_type')) {
        $mimeType = mime_content_type($path);
        if (is_string($mimeType) && $mimeType !== '') {
            return strtolower($mimeType);
        }
    }

    return '';
}

function toy_upload_random_filename(string $extension = ''): string
{
    $extension = toy_upload_extension('upload.' . ltrim($extension, '.'));
    if ($extension !== '' && toy_upload_is_executable_extension($extension)) {
        throw new InvalidArgumentException('Executable upload extension is not allowed.');
    }

    $filename = bin2hex(random_bytes(16));
    return $extension === '' ? $filename : $filename . '.' . $extension;
}

function toy_upload_validate_file(array $file, array $options = []): array
{
    foreach (['error', 'name', 'tmp_name', 'size'] as $key) {
        if (isset($file[$key]) && is_array($file[$key])) {
            throw new RuntimeException('여러 파일 업로드 배열은 모듈에서 개별 파일로 분리한 뒤 검증해야 합니다.');
        }
    }

    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        throw new RuntimeException(toy_upload_error_message($errorCode));
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_file($tmpName)) {
        throw new RuntimeException('업로드된 임시 파일을 찾을 수 없습니다.');
    }

    $requireUploadedFile = (bool) ($options['require_uploaded_file'] ?? true);
    if ($requireUploadedFile && !is_uploaded_file($tmpName)) {
        throw new RuntimeException('정상적인 HTTP 업로드 파일이 아닙니다.');
    }

    $actualSize = filesize($tmpName);
    if (!is_int($actualSize) || $actualSize < 0) {
        throw new RuntimeException('업로드 파일 크기를 확인할 수 없습니다.');
    }

    $maxBytes = (int) ($options['max_bytes'] ?? 0);
    if ($maxBytes > 0 && $actualSize > $maxBytes) {
        throw new RuntimeException('업로드 파일 크기가 허용 범위를 초과했습니다.');
    }

    $originalName = toy_upload_filename((string) ($file['name'] ?? ''));
    $extension = toy_upload_extension($originalName);
    if ($extension === '') {
        throw new RuntimeException('업로드 파일 확장자를 확인할 수 없습니다.');
    }

    if (toy_upload_is_executable_extension($extension)) {
        throw new RuntimeException('실행 가능한 파일 형식은 업로드할 수 없습니다.');
    }

    $allowedExtensions = toy_upload_normalize_extensions(is_array($options['allowed_extensions'] ?? null) ? $options['allowed_extensions'] : []);
    if ($allowedExtensions !== [] && !in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('허용되지 않은 파일 확장자입니다.');
    }

    $mimeType = toy_upload_detect_mime($tmpName);
    $allowedMimeTypes = toy_upload_normalize_mime_types(is_array($options['allowed_mime_types'] ?? null) ? $options['allowed_mime_types'] : []);
    if ($allowedMimeTypes !== []) {
        if ($mimeType === '') {
            throw new RuntimeException('업로드 파일 MIME을 확인할 수 없습니다.');
        }

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new RuntimeException('허용되지 않은 파일 MIME입니다.');
        }
    }

    $checksum = hash_file('sha256', $tmpName);
    if (!is_string($checksum)) {
        throw new RuntimeException('업로드 파일 checksum을 계산할 수 없습니다.');
    }

    return [
        'tmp_name' => $tmpName,
        'original_name' => $originalName,
        'extension' => $extension,
        'size' => $actualSize,
        'mime_type' => $mimeType,
        'checksum' => $checksum,
    ];
}

function toy_upload_safe_target_path(string $directory, string $filename): string
{
    $realDirectory = realpath($directory);
    if (!is_string($realDirectory) || !is_dir($realDirectory)) {
        throw new RuntimeException('업로드 저장 디렉터리를 찾을 수 없습니다.');
    }

    $safeFilename = toy_upload_filename($filename);
    if ($safeFilename !== $filename || str_contains($safeFilename, '/') || str_contains($safeFilename, '\\')) {
        throw new RuntimeException('업로드 저장 파일명이 올바르지 않습니다.');
    }

    $extension = toy_upload_extension($safeFilename);
    if ($extension !== '' && toy_upload_is_executable_extension($extension)) {
        throw new RuntimeException('실행 가능한 저장 파일명은 사용할 수 없습니다.');
    }

    return $realDirectory . DIRECTORY_SEPARATOR . $safeFilename;
}

function toy_upload_move_uploaded_file(array $file, string $directory, string $filename, array $options = []): array
{
    $validated = toy_upload_validate_file($file, $options);
    $targetPath = toy_upload_safe_target_path($directory, $filename);
    toy_upload_assert_target_path_writable($targetPath, !empty($options['overwrite']));

    if (!move_uploaded_file((string) $validated['tmp_name'], $targetPath)) {
        throw new RuntimeException('업로드 파일을 저장할 수 없습니다.');
    }

    $validated['stored_path'] = $targetPath;
    $validated['stored_name'] = basename($targetPath);
    return $validated;
}

function toy_upload_assert_target_path_writable(string $targetPath, bool $overwrite = false): void
{
    $directory = dirname($targetPath);
    if (!is_dir($directory) || !is_writable($directory)) {
        throw new RuntimeException('업로드 저장 디렉터리에 쓸 수 없습니다.');
    }

    if (is_link($targetPath)) {
        throw new RuntimeException('업로드 저장 대상이 심볼릭 링크입니다.');
    }

    if (is_dir($targetPath)) {
        throw new RuntimeException('업로드 저장 대상이 디렉터리입니다.');
    }

    if (file_exists($targetPath) && !$overwrite) {
        throw new RuntimeException('같은 이름의 업로드 파일이 이미 있습니다.');
    }

    if (file_exists($targetPath) && (!is_file($targetPath) || !is_writable($targetPath))) {
        throw new RuntimeException('업로드 저장 대상 파일에 쓸 수 없습니다.');
    }
}

function toy_download_token_create(array $config, string $purpose, string $subject, int $ttlSeconds, ?int $now = null): array
{
    $ttlSeconds = max(60, min(86400, $ttlSeconds));
    $now = $now ?? time();
    $expiresAt = $now + $ttlSeconds;
    $token = bin2hex(random_bytes(32));

    return [
        'token' => $token,
        'token_hash' => toy_download_token_hash($config, $token, $purpose, $subject, $expiresAt),
        'expires_at' => $expiresAt,
    ];
}

function toy_download_token_hash(array $config, string $token, string $purpose, string $subject, int $expiresAt): string
{
    if (preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) {
        throw new InvalidArgumentException('Download token format is invalid.');
    }

    $purpose = toy_download_token_purpose($purpose);
    $subject = toy_download_token_subject($subject);
    if ($expiresAt < 1) {
        throw new InvalidArgumentException('Download token expiration is invalid.');
    }

    $payload = json_encode([
        'purpose' => $purpose,
        'subject' => $subject,
        'expires_at' => $expiresAt,
        'token' => $token,
    ], JSON_UNESCAPED_SLASHES);
    if (!is_string($payload)) {
        throw new RuntimeException('Download token payload cannot be encoded.');
    }

    return toy_hmac_hash('download-token|' . $payload, $config);
}

function toy_download_token_verify(array $config, string $token, string $expectedHash, string $purpose, string $subject, int $expiresAt, ?int $now = null): bool
{
    $now = $now ?? time();
    if ($expiresAt < $now || $expectedHash === '') {
        return false;
    }

    try {
        $actualHash = toy_download_token_hash($config, $token, $purpose, $subject, $expiresAt);
    } catch (Throwable $exception) {
        return false;
    }

    return hash_equals($expectedHash, $actualHash);
}

function toy_download_token_purpose(string $purpose): string
{
    $purpose = trim($purpose);
    if (preg_match('/\A[A-Za-z0-9._:-]{1,80}\z/', $purpose) !== 1) {
        throw new InvalidArgumentException('Download token purpose is invalid.');
    }

    return $purpose;
}

function toy_download_token_subject(string $subject): string
{
    $subject = trim($subject);
    if ($subject === '' || strlen($subject) > 255 || preg_match('/[\x00-\x1F\x7F]/', $subject) === 1) {
        throw new InvalidArgumentException('Download token subject is invalid.');
    }

    return $subject;
}

function toy_upload_reencode_image(string $sourcePath, string $targetPath, string $targetFormat, array $options = []): bool
{
    $targetFormat = strtolower(ltrim($targetFormat, '.'));
    if (!in_array($targetFormat, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return false;
    }

    $imageInfo = @getimagesize($sourcePath);
    if (!is_array($imageInfo)) {
        return false;
    }

    $width = (int) ($imageInfo[0] ?? 0);
    $height = (int) ($imageInfo[1] ?? 0);
    $maxPixels = max(1, (int) ($options['max_pixels'] ?? 25000000));
    if ($width < 1 || $height < 1 || $width * $height > $maxPixels) {
        return false;
    }

    if (class_exists('Imagick') && toy_upload_reencode_image_with_imagick($sourcePath, $targetPath, $targetFormat, $options)) {
        return true;
    }

    return toy_upload_reencode_image_with_gd($sourcePath, $targetPath, $targetFormat, $options);
}

function toy_upload_reencode_image_with_imagick(string $sourcePath, string $targetPath, string $targetFormat, array $options): bool
{
    try {
        $image = new Imagick($sourcePath);
        if ($image->getNumberImages() > 1) {
            $image->setIteratorIndex(0);
        }

        $image->stripImage();
        $image->setImageFormat($targetFormat === 'jpg' ? 'jpeg' : $targetFormat);
        if (in_array($targetFormat, ['jpg', 'jpeg', 'webp'], true)) {
            $image->setImageCompressionQuality(max(1, min(100, (int) ($options['quality'] ?? 85))));
        }

        $result = $image->writeImage($targetPath);
        $image->clear();
        $image->destroy();
        return $result;
    } catch (Throwable $exception) {
        return false;
    }
}

function toy_upload_reencode_image_with_gd(string $sourcePath, string $targetPath, string $targetFormat, array $options): bool
{
    if (!extension_loaded('gd')) {
        return false;
    }

    $imageInfo = @getimagesize($sourcePath);
    if (!is_array($imageInfo)) {
        return false;
    }

    $mimeType = strtolower((string) ($imageInfo['mime'] ?? ''));
    if ($mimeType === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
        $image = @imagecreatefromjpeg($sourcePath);
    } elseif ($mimeType === 'image/png' && function_exists('imagecreatefrompng')) {
        $image = @imagecreatefrompng($sourcePath);
    } elseif ($mimeType === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $image = @imagecreatefromwebp($sourcePath);
    } else {
        return false;
    }

    if (!$image instanceof GdImage) {
        return false;
    }

    if (function_exists('imagepalettetotruecolor')) {
        imagepalettetotruecolor($image);
    }
    imagealphablending($image, false);
    imagesavealpha($image, true);

    if (in_array($targetFormat, ['jpg', 'jpeg'], true) && function_exists('imagejpeg')) {
        $result = imagejpeg($image, $targetPath, max(1, min(100, (int) ($options['quality'] ?? 85))));
    } elseif ($targetFormat === 'png' && function_exists('imagepng')) {
        $result = imagepng($image, $targetPath);
    } elseif ($targetFormat === 'webp' && function_exists('imagewebp')) {
        $result = imagewebp($image, $targetPath, max(1, min(100, (int) ($options['quality'] ?? 85))));
    } else {
        $result = false;
    }

    imagedestroy($image);
    return $result;
}
