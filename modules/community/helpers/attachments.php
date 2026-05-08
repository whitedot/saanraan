<?php

declare(strict_types=1);

function toy_community_attachment_for_read(PDO $pdo, int $attachmentId, ?array $account): ?array
{
    if ($attachmentId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, post_id, uploader_account_id, original_name, stored_name, storage_path, mime_type, size_bytes, checksum_sha256, width, height, status, created_at
         FROM toy_community_attachments
         WHERE id = :id
           AND status = 'active'
         LIMIT 1"
    );
    $stmt->execute(['id' => $attachmentId]);
    $attachment = $stmt->fetch();
    if (!is_array($attachment)) {
        return null;
    }

    $post = toy_community_post_for_read($pdo, (int) $attachment['post_id'], $account);
    if (!is_array($post)) {
        return null;
    }

    $attachment['post'] = $post;
    return $attachment;
}

function toy_community_post_attachments(PDO $pdo, int $postId): array
{
    if ($postId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT id, post_id, uploader_account_id, original_name, stored_name, mime_type, size_bytes, width, height, status, created_at
         FROM toy_community_attachments
         WHERE post_id = :post_id
           AND status = 'active'
           AND mime_type IN ('image/jpeg', 'image/png', 'image/webp')
         ORDER BY id ASC
         LIMIT 20"
    );
    $stmt->execute(['post_id' => $postId]);

    return $stmt->fetchAll();
}

function toy_community_upload_post_image(PDO $pdo, int $postId, int $uploaderAccountId, array $file, array $settings = []): ?int
{
    if ($postId < 1 || $uploaderAccountId < 1 || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $maxBytes = min(10485760, max(1, (int) ($settings['attachment_max_bytes'] ?? 2097152)));
    $validated = toy_upload_validate_file($file, [
        'max_bytes' => $maxBytes,
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
    ]);

    $targetFormat = toy_community_attachment_format_for_mime((string) $validated['mime_type']);
    if ($targetFormat === '') {
        throw new RuntimeException('허용되지 않은 이미지 형식입니다.');
    }

    $directory = TOY_ROOT . '/storage/community/attachments/' . date('Y/m');
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('첨부 저장 디렉터리를 만들 수 없습니다.');
    }

    $storedName = toy_upload_random_filename($targetFormat);
    $targetPath = toy_upload_safe_target_path($directory, $storedName);
    toy_upload_assert_target_path_writable($targetPath);

    if (!toy_upload_reencode_image((string) $validated['tmp_name'], $targetPath, $targetFormat, [
        'max_pixels' => 25000000,
        'quality' => 85,
    ])) {
        throw new RuntimeException('이미지 재인코딩에 실패했습니다.');
    }

    $imageInfo = getimagesize($targetPath);
    $checksum = hash_file('sha256', $targetPath);
    $sizeBytes = filesize($targetPath);
    if (!is_array($imageInfo) || !is_string($checksum) || !is_int($sizeBytes)) {
        @unlink($targetPath);
        throw new RuntimeException('저장된 이미지 metadata를 확인할 수 없습니다.');
    }

    $storagePath = ltrim(str_replace(TOY_ROOT . DIRECTORY_SEPARATOR, '', $targetPath), DIRECTORY_SEPARATOR);
    return toy_community_create_attachment($pdo, [
        'post_id' => $postId,
        'uploader_account_id' => $uploaderAccountId,
        'original_name' => (string) $validated['original_name'],
        'stored_name' => $storedName,
        'storage_path' => $storagePath,
        'mime_type' => (string) $validated['mime_type'],
        'size_bytes' => $sizeBytes,
        'checksum_sha256' => $checksum,
        'width' => (int) $imageInfo[0],
        'height' => (int) $imageInfo[1],
    ]);
}

function toy_community_attachment_format_for_mime(string $mimeType): string
{
    return match (strtolower(trim($mimeType))) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => '',
    };
}

function toy_community_create_attachment(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare(
        "INSERT INTO toy_community_attachments
            (post_id, uploader_account_id, original_name, stored_name, storage_path, mime_type, size_bytes, checksum_sha256, width, height, status, created_at)
         VALUES
            (:post_id, :uploader_account_id, :original_name, :stored_name, :storage_path, :mime_type, :size_bytes, :checksum_sha256, :width, :height, 'active', :created_at)"
    );
    $stmt->execute([
        'post_id' => (int) $data['post_id'],
        'uploader_account_id' => (int) $data['uploader_account_id'],
        'original_name' => (string) $data['original_name'],
        'stored_name' => (string) $data['stored_name'],
        'storage_path' => (string) $data['storage_path'],
        'mime_type' => (string) $data['mime_type'],
        'size_bytes' => (int) $data['size_bytes'],
        'checksum_sha256' => (string) $data['checksum_sha256'],
        'width' => (int) $data['width'],
        'height' => (int) $data['height'],
        'created_at' => toy_now(),
    ]);

    return (int) $pdo->lastInsertId();
}

function toy_community_attachment_mime_is_allowed(string $mimeType): bool
{
    return in_array(strtolower(trim($mimeType)), [
        'image/jpeg',
        'image/png',
        'image/webp',
    ], true);
}

function toy_community_attachment_file_path(array $attachment): ?string
{
    $storageRoot = realpath(TOY_ROOT . '/storage');
    if (!is_string($storageRoot) || !is_dir($storageRoot)) {
        return null;
    }

    $storagePath = (string) ($attachment['storage_path'] ?? '');
    if ($storagePath === '' || str_contains($storagePath, "\0")) {
        return null;
    }

    $candidate = str_starts_with($storagePath, DIRECTORY_SEPARATOR)
        ? $storagePath
        : TOY_ROOT . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $storagePath), DIRECTORY_SEPARATOR);
    $realPath = realpath($candidate);
    if (!is_string($realPath) && !str_starts_with($storagePath, DIRECTORY_SEPARATOR)) {
        $fallback = $storageRoot . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $storagePath), DIRECTORY_SEPARATOR);
        $realPath = realpath($fallback);
    }
    if (!is_string($realPath) || !is_file($realPath)) {
        return null;
    }

    $storagePrefix = rtrim($storageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($realPath, $storagePrefix)) {
        return null;
    }

    return $realPath;
}
