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

function toy_community_attachment_read_board(PDO $pdo, int $attachmentId): ?array
{
    if ($attachmentId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT b.id, b.board_group_id, b.status, b.read_policy
         FROM toy_community_attachments a
         INNER JOIN toy_community_posts p ON p.id = a.post_id
         INNER JOIN toy_community_boards b ON b.id = p.board_id
         WHERE a.id = :id
           AND a.status = 'active'
           AND p.status = 'published'
           AND b.status = 'enabled'
         LIMIT 1"
    );
    $stmt->execute(['id' => $attachmentId]);
    $board = $stmt->fetch();

    return is_array($board) ? $board : null;
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

    if ((int) ($settings['attachment_max_count'] ?? 1) < 1) {
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
    $storedMimeType = toy_upload_detect_mime($targetPath);
    $checksum = hash_file('sha256', $targetPath);
    $sizeBytes = filesize($targetPath);
    if (!is_array($imageInfo) || !toy_community_attachment_mime_is_allowed($storedMimeType) || !is_string($checksum) || !is_int($sizeBytes)) {
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
        'mime_type' => $storedMimeType,
        'size_bytes' => $sizeBytes,
        'checksum_sha256' => $checksum,
        'width' => (int) $imageInfo[0],
        'height' => (int) $imageInfo[1],
    ]);
}

function toy_community_upload_post_files(PDO $pdo, int $postId, int $uploaderAccountId, array $files, array $settings = []): array
{
    $uploadedIds = [];
    $maxCount = min(5, max(0, (int) ($settings['file_attachment_max_count'] ?? 3)));
    if ($maxCount < 1) {
        return [];
    }

    $selectedFiles = [];
    foreach (toy_community_normalize_upload_files($files) as $file) {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $selectedFiles[] = $file;
    }

    if (count($selectedFiles) > $maxCount) {
        throw new RuntimeException('첨부 파일 개수가 허용 범위를 초과했습니다.');
    }

    foreach ($selectedFiles as $file) {
        $uploadedIds[] = toy_community_upload_post_file($pdo, $postId, $uploaderAccountId, $file, $settings);
    }

    return $uploadedIds;
}

function toy_community_upload_post_file(PDO $pdo, int $postId, int $uploaderAccountId, array $file, array $settings = []): int
{
    if ($postId < 1 || $uploaderAccountId < 1) {
        throw new RuntimeException('첨부 대상이 올바르지 않습니다.');
    }

    $maxBytes = min(20971520, max(1024, (int) ($settings['file_attachment_max_bytes'] ?? 5242880)));
    $allowedExtensions = toy_community_normalize_file_extensions(is_array($settings['file_allowed_extensions'] ?? null) ? $settings['file_allowed_extensions'] : ['pdf', 'txt', 'csv', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'hwp']);
    if ($allowedExtensions === []) {
        throw new RuntimeException('허용된 첨부 파일 확장자가 없습니다.');
    }

    $validated = toy_upload_validate_file($file, [
        'max_bytes' => $maxBytes,
        'allowed_extensions' => $allowedExtensions,
        'allowed_mime_types' => toy_community_file_mime_types_for_extensions($allowedExtensions),
    ]);

    $directory = TOY_ROOT . '/storage/community/attachments/' . date('Y/m');
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('첨부 저장 디렉터리를 만들 수 없습니다.');
    }

    $storedName = toy_upload_random_filename((string) $validated['extension']);
    $targetPath = toy_upload_safe_target_path($directory, $storedName);
    toy_upload_assert_target_path_writable($targetPath);
    if (!move_uploaded_file((string) $validated['tmp_name'], $targetPath)) {
        throw new RuntimeException('첨부 파일을 저장할 수 없습니다.');
    }

    $storedMimeType = toy_upload_detect_mime($targetPath);
    $checksum = hash_file('sha256', $targetPath);
    $sizeBytes = filesize($targetPath);
    if (!toy_community_attachment_mime_is_allowed($storedMimeType) || !is_string($checksum) || !is_int($sizeBytes)) {
        @unlink($targetPath);
        throw new RuntimeException('저장된 첨부 파일 metadata를 확인할 수 없습니다.');
    }

    $storagePath = ltrim(str_replace(TOY_ROOT . DIRECTORY_SEPARATOR, '', $targetPath), DIRECTORY_SEPARATOR);
    return toy_community_create_attachment($pdo, [
        'post_id' => $postId,
        'uploader_account_id' => $uploaderAccountId,
        'original_name' => (string) $validated['original_name'],
        'stored_name' => $storedName,
        'storage_path' => $storagePath,
        'mime_type' => $storedMimeType,
        'size_bytes' => $sizeBytes,
        'checksum_sha256' => $checksum,
        'width' => null,
        'height' => null,
    ]);
}

function toy_community_normalize_upload_files(array $files): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [$files];
    }

    $normalized = [];
    $count = count($files['name']);
    for ($index = 0; $index < $count; $index++) {
        $normalized[] = [
            'name' => $files['name'][$index] ?? '',
            'type' => is_array($files['type'] ?? null) ? ($files['type'][$index] ?? '') : '',
            'tmp_name' => is_array($files['tmp_name'] ?? null) ? ($files['tmp_name'][$index] ?? '') : '',
            'error' => is_array($files['error'] ?? null) ? ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE,
            'size' => is_array($files['size'] ?? null) ? ($files['size'][$index] ?? 0) : 0,
        ];
    }

    return $normalized;
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
        'width' => isset($data['width']) ? (int) $data['width'] : null,
        'height' => isset($data['height']) ? (int) $data['height'] : null,
        'created_at' => toy_now(),
    ]);

    return (int) $pdo->lastInsertId();
}

function toy_community_update_post_attachments_status(PDO $pdo, int $postId, string $status): int
{
    if ($postId < 1 || !in_array($status, ['active', 'hidden', 'deleted'], true)) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'UPDATE toy_community_attachments
         SET status = :status
         WHERE post_id = :post_id
           AND status <> :current_status'
    );
    $stmt->execute([
        'status' => $status,
        'current_status' => $status,
        'post_id' => $postId,
    ]);

    return $stmt->rowCount();
}

function toy_community_restore_hidden_post_attachments(PDO $pdo, int $postId): int
{
    if ($postId < 1) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "UPDATE toy_community_attachments
         SET status = 'active'
         WHERE post_id = :post_id
           AND status = 'hidden'"
    );
    $stmt->execute(['post_id' => $postId]);

    return $stmt->rowCount();
}

function toy_community_attachment_mime_is_allowed(string $mimeType): bool
{
    return in_array(strtolower(trim($mimeType)), array_merge([
        'image/jpeg',
        'image/png',
        'image/webp',
    ], toy_community_file_mime_types_for_extensions(array_keys(toy_community_file_extension_mime_map()))), true);
}

function toy_community_attachment_is_image(array $attachment): bool
{
    return in_array(strtolower((string) ($attachment['mime_type'] ?? '')), [
        'image/jpeg',
        'image/png',
        'image/webp',
    ], true);
}

function toy_community_file_extension_mime_map(): array
{
    return [
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'hwp' => ['application/x-hwp', 'application/haansofthwp'],
    ];
}

function toy_community_file_mime_types_for_extensions(array $extensions): array
{
    $map = toy_community_file_extension_mime_map();
    $mimeTypes = [];
    foreach (toy_community_normalize_file_extensions($extensions) as $extension) {
        foreach ($map[$extension] ?? [] as $mimeType) {
            $mimeTypes[$mimeType] = true;
        }
    }

    return array_keys($mimeTypes);
}

function toy_community_format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return number_format(max(0, $bytes)) . ' bytes';
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
