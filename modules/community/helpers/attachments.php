<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_community_attachment_for_read(PDO $pdo, int $attachmentId, ?array $account): ?array
{
    if ($attachmentId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, post_id, uploader_account_id, original_name, stored_name, storage_path, storage_driver, storage_key, mime_type, size_bytes, checksum_sha256, width, height, status, created_at
         FROM sr_community_attachments
         WHERE id = :id
           AND status = 'active'
         LIMIT 1"
    );
    $stmt->execute(['id' => $attachmentId]);
    $attachment = $stmt->fetch();
    if (!is_array($attachment)) {
        return null;
    }

    $post = sr_community_post_for_read($pdo, (int) $attachment['post_id'], $account);
    if (!is_array($post)) {
        return null;
    }
    if (!sr_community_account_can_view_post_body($pdo, $post, $account)) {
        return null;
    }

    $attachment['post'] = $post;
    return $attachment;
}

function sr_community_attachment_read_board(PDO $pdo, int $attachmentId): ?array
{
    if ($attachmentId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT b.id, b.board_group_id, b.status, b.read_policy
         FROM sr_community_attachments a
         INNER JOIN sr_community_posts p ON p.id = a.post_id
         INNER JOIN sr_community_boards b ON b.id = p.board_id
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

function sr_community_post_attachments(PDO $pdo, int $postId): array
{
    if ($postId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT id, post_id, uploader_account_id, original_name, stored_name, storage_driver, storage_key, mime_type, size_bytes, checksum_sha256, width, height, status, created_at
         FROM sr_community_attachments
         WHERE post_id = :post_id
           AND status = 'active'
         ORDER BY id ASC
         LIMIT 20"
    );
    $stmt->execute(['post_id' => $postId]);

    return $stmt->fetchAll();
}

function sr_community_attachment_by_id(PDO $pdo, int $attachmentId): ?array
{
    if ($attachmentId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, post_id, uploader_account_id, original_name, stored_name, storage_path, storage_driver, storage_key, mime_type, size_bytes, checksum_sha256, width, height, status, created_at
         FROM sr_community_attachments
         WHERE id = :id
         LIMIT 1"
    );
    $stmt->execute(['id' => $attachmentId]);
    $attachment = $stmt->fetch();

    return is_array($attachment) ? $attachment : null;
}

function sr_community_first_public_image_attachment_id(PDO $pdo, int $postId): int
{
    if ($postId < 1) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "SELECT id, mime_type, status
         FROM sr_community_attachments
         WHERE post_id = :post_id
           AND status = 'active'
           AND mime_type IN ('image/jpeg', 'image/png', 'image/webp')
         ORDER BY id ASC
         LIMIT 1"
    );
    $stmt->execute(['post_id' => $postId]);
    $attachment = $stmt->fetch();

    return is_array($attachment) ? (int) ($attachment['id'] ?? 0) : 0;
}

function sr_community_post_list_thumbnail_url(PDO $pdo, array $post, array $board, array $settings): string
{
    if ((int) ($post['list_image_attachment_id'] ?? 0) < 1 || !sr_community_post_allows_public_list_thumbnail($pdo, $post, $board, $settings)) {
        return '';
    }

    $attachmentId = (int) $post['list_image_attachment_id'];
    $publicUrl = sr_url('/community/attachment?id=' . rawurlencode((string) $attachmentId));
    if ((string) ($post['list_image_storage_key'] ?? '') === '' && (string) ($post['list_image_source_path'] ?? '') === '') {
        $attachment = sr_community_attachment_by_id($pdo, $attachmentId);
        if (is_array($attachment)
            && (int) ($attachment['post_id'] ?? 0) === (int) ($post['id'] ?? 0)
            && (string) ($attachment['status'] ?? '') === 'active'
            && sr_community_attachment_is_image($attachment)
        ) {
            $post['list_image_storage_driver'] = sr_community_attachment_storage_driver($attachment);
            $post['list_image_storage_key'] = sr_community_attachment_storage_key($attachment);
            $post['list_image_mime_type'] = (string) ($attachment['mime_type'] ?? '');
            $post['list_image_size_bytes'] = (int) ($attachment['size_bytes'] ?? 0);
            $post['list_image_checksum_sha256'] = (string) ($attachment['checksum_sha256'] ?? '');
            $post['list_image_width'] = (int) ($attachment['width'] ?? 0);
            $post['list_image_height'] = (int) ($attachment['height'] ?? 0);
            $post['list_image_source_path'] = sr_community_attachment_file_path($attachment) ?? '';
        }
    }

    return sr_thumbnail_public_url($pdo, [
        'public' => true,
        'module_key' => 'community',
        'storage_driver' => (string) ($post['list_image_storage_driver'] ?? 'local'),
        'storage_key' => (string) ($post['list_image_storage_key'] ?? ''),
        'mime_type' => (string) ($post['list_image_mime_type'] ?? ''),
        'size_bytes' => (int) ($post['list_image_size_bytes'] ?? 0),
        'checksum_sha256' => (string) ($post['list_image_checksum_sha256'] ?? ''),
        'width' => (int) ($post['list_image_width'] ?? 0),
        'height' => (int) ($post['list_image_height'] ?? 0),
        'source_path' => (string) ($post['list_image_source_path'] ?? ''),
        'public_url' => $publicUrl,
    ], sr_community_post_list_thumbnail_options($post));
}

function sr_community_post_view_image_thumbnail_url(PDO $pdo, array $attachment, array $board, array $settings): string
{
    if ((int) ($attachment['id'] ?? 0) < 1 || !sr_community_attachment_is_image($attachment)) {
        return '';
    }

    $publicUrl = sr_community_attachment_public_url($attachment);
    if ($publicUrl === '') {
        return '';
    }

    $thumbnailEnabled = sr_community_effective_thumbnail_setting($pdo, $board, 'thumbnail_enabled', $settings) === '1';
    if (!$thumbnailEnabled) {
        return $publicUrl;
    }

    $criterion = sr_community_effective_thumbnail_setting($pdo, $board, 'thumbnail_criterion', $settings);
    if ($criterion === 'bytes') {
        $sourceBytes = (int) ($attachment['size_bytes'] ?? 0);
        $minBytes = (int) sr_community_effective_thumbnail_setting($pdo, $board, 'thumbnail_min_bytes', $settings);
        if ($minBytes > 0 && $sourceBytes < $minBytes) {
            return $publicUrl;
        }
    } else {
        $sourceWidth = (int) ($attachment['width'] ?? 0);
        $minWidth = (int) sr_community_effective_thumbnail_setting($pdo, $board, 'thumbnail_min_width', $settings);
        if ($sourceWidth > 0 && $sourceWidth < $minWidth) {
            return $publicUrl;
        }
    }

    return sr_thumbnail_public_url($pdo, [
        'public' => true,
        'module_key' => 'community',
        'storage_driver' => (string) ($attachment['storage_driver'] ?? 'local'),
        'storage_key' => (string) ($attachment['storage_key'] ?? ''),
        'mime_type' => (string) ($attachment['mime_type'] ?? ''),
        'size_bytes' => (int) ($attachment['size_bytes'] ?? 0),
        'checksum_sha256' => (string) ($attachment['checksum_sha256'] ?? ''),
        'width' => (int) ($attachment['width'] ?? 0),
        'height' => (int) ($attachment['height'] ?? 0),
        'public_url' => $publicUrl,
    ], sr_community_post_view_image_thumbnail_options($attachment));
}

function sr_community_attachment_public_url(array $attachment): string
{
    $attachmentId = (int) ($attachment['id'] ?? 0);
    if ($attachmentId < 1) {
        return '';
    }

    return sr_url('/community/attachment?id=' . rawurlencode((string) $attachmentId));
}

function sr_community_post_list_thumbnail_options(array $post): array
{
    $sourceWidth = (int) ($post['list_image_width'] ?? 0);
    $sourceHeight = (int) ($post['list_image_height'] ?? 0);
    $targetWidth = $sourceWidth > 0 ? min(320, $sourceWidth) : 320;
    $targetHeight = 180;
    if ($sourceWidth > 0 && $sourceHeight > 0) {
        $targetHeight = max(1, min(2000, (int) round($sourceHeight * ($targetWidth / $sourceWidth))));
    }

    return [
        'width' => $targetWidth,
        'height' => $targetHeight,
        'mode' => 'contain',
        'quality' => 82,
        'format' => 'source',
        'require_cache' => true,
    ];
}

function sr_community_post_view_image_thumbnail_options(array $attachment): array
{
    $sourceWidth = (int) ($attachment['width'] ?? 0);
    $sourceHeight = (int) ($attachment['height'] ?? 0);
    $targetWidth = $sourceWidth > 0 ? min(640, $sourceWidth) : 640;
    $targetHeight = 360;
    if ($sourceWidth > 0 && $sourceHeight > 0) {
        $targetHeight = max(1, min(2000, (int) round($sourceHeight * ($targetWidth / $sourceWidth))));
    }

    return [
        'width' => $targetWidth,
        'height' => $targetHeight,
        'mode' => 'contain',
        'quality' => 82,
        'format' => 'source',
    ];
}

function sr_community_post_allows_public_list_thumbnail(PDO $pdo, array $post, array $board, array $settings): bool
{
    if ((string) ($board['read_policy'] ?? '') !== 'public' && (string) ($board['effective_read_policy'] ?? '') !== 'public') {
        return false;
    }
    if ((int) ($post['is_secret'] ?? 0) === 1) {
        return false;
    }

    $paidReadConfig = sr_community_asset_event_config($pdo, $board, $settings, 'paid_read', 'once');
    return !sr_community_asset_event_required($paidReadConfig);
}

function sr_community_upload_post_image(PDO $pdo, int $postId, int $uploaderAccountId, array $file, array $settings = []): ?int
{
    if ($postId < 1 || $uploaderAccountId < 1 || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int) ($settings['attachment_max_count'] ?? 1) < 1) {
        return null;
    }

    $maxBytes = min(10485760, max(1, (int) ($settings['attachment_max_bytes'] ?? 2097152)));
    $validated = sr_upload_validate_file($file, [
        'max_bytes' => $maxBytes,
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
    ]);

    $targetFormat = sr_community_attachment_format_for_mime((string) $validated['mime_type']);
    if ($targetFormat === '') {
        throw new RuntimeException(sr_t('community::runtime.image_type_forbidden'));
    }

    $directory = SR_ROOT . '/storage/tmp/community-attachments/' . date('Y/m');
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException(sr_t('community::runtime.attachment_directory_failed'));
    }

    $storedName = sr_upload_random_filename($targetFormat);
    $targetPath = sr_upload_safe_target_path($directory, $storedName);
    sr_upload_assert_target_path_writable($targetPath);

    if (!sr_upload_reencode_image((string) $validated['tmp_name'], $targetPath, $targetFormat, [
        'max_pixels' => 25000000,
        'quality' => 85,
    ])) {
        throw new RuntimeException(sr_t('community::runtime.image_reencode_failed'));
    }

    $imageInfo = getimagesize($targetPath);
    $storedMimeType = sr_upload_detect_mime($targetPath);
    $checksum = hash_file('sha256', $targetPath);
    $sizeBytes = filesize($targetPath);
    if (!is_array($imageInfo) || !sr_community_attachment_mime_is_allowed($storedMimeType) || !is_string($checksum) || !is_int($sizeBytes)) {
        @unlink($targetPath);
        throw new RuntimeException(sr_t('community::runtime.image_metadata_invalid'));
    }

    $storageKey = 'community/attachments/' . date('Y/m') . '/' . $storedName;
    $stored = sr_storage_put_file($targetPath, $storageKey, [
        'content_type' => $storedMimeType,
    ]);
    @unlink($targetPath);

    try {
        return sr_community_create_attachment($pdo, [
            'post_id' => $postId,
            'uploader_account_id' => $uploaderAccountId,
            'original_name' => (string) $validated['original_name'],
            'stored_name' => $storedName,
            'storage_path' => (string) ($stored['path'] ?? ''),
            'storage_driver' => (string) $stored['driver'],
            'storage_key' => $storageKey,
            'mime_type' => $storedMimeType,
            'size_bytes' => $sizeBytes,
            'checksum_sha256' => $checksum,
            'width' => (int) $imageInfo[0],
            'height' => (int) $imageInfo[1],
        ]);
    } catch (Throwable $exception) {
        sr_storage_delete((string) $stored['driver'], $storageKey);
        throw $exception;
    }
}

function sr_community_upload_post_files(PDO $pdo, int $postId, int $uploaderAccountId, array $files, array $settings = []): array
{
    $uploadedIds = [];
    $maxCount = min(5, max(0, (int) ($settings['file_attachment_max_count'] ?? 3)));
    if ($maxCount < 1) {
        return [];
    }

    $selectedFiles = [];
    foreach (sr_community_normalize_upload_files($files) as $file) {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $selectedFiles[] = $file;
    }

    if (count($selectedFiles) > $maxCount) {
        throw new RuntimeException(sr_t('community::runtime.attachment_count_exceeded'));
    }

    foreach ($selectedFiles as $file) {
        $uploadedIds[] = sr_community_upload_post_file($pdo, $postId, $uploaderAccountId, $file, $settings);
    }

    return $uploadedIds;
}

function sr_community_upload_post_file(PDO $pdo, int $postId, int $uploaderAccountId, array $file, array $settings = []): int
{
    if ($postId < 1 || $uploaderAccountId < 1) {
        throw new RuntimeException(sr_t('community::runtime.attachment_target_invalid'));
    }

    $maxBytes = min(20971520, max(1024, (int) ($settings['file_attachment_max_bytes'] ?? 5242880)));
    $allowedExtensions = sr_community_normalize_file_extensions(is_array($settings['file_allowed_extensions'] ?? null) ? $settings['file_allowed_extensions'] : ['pdf', 'txt', 'csv', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'hwp']);
    if ($allowedExtensions === []) {
        throw new RuntimeException(sr_t('community::runtime.attachment_extension_missing'));
    }

    $validated = sr_upload_validate_file($file, [
        'max_bytes' => $maxBytes,
        'allowed_extensions' => $allowedExtensions,
        'allowed_mime_types' => sr_community_file_mime_types_for_extensions($allowedExtensions),
    ]);

    $storedName = sr_upload_random_filename((string) $validated['extension']);
    $storedMimeType = sr_upload_detect_mime((string) $validated['tmp_name']);
    $checksum = (string) $validated['checksum'];
    $sizeBytes = filesize((string) $validated['tmp_name']);
    if (!sr_community_attachment_mime_is_allowed($storedMimeType) || !is_string($checksum) || !is_int($sizeBytes)) {
        throw new RuntimeException(sr_t('community::runtime.attachment_metadata_invalid'));
    }

    $storageKey = 'community/attachments/' . date('Y/m') . '/' . $storedName;
    $stored = sr_storage_put_file((string) $validated['tmp_name'], $storageKey, [
        'content_type' => $storedMimeType,
    ]);

    try {
        return sr_community_create_attachment($pdo, [
            'post_id' => $postId,
            'uploader_account_id' => $uploaderAccountId,
            'original_name' => (string) $validated['original_name'],
            'stored_name' => $storedName,
            'storage_path' => (string) ($stored['path'] ?? ''),
            'storage_driver' => (string) $stored['driver'],
            'storage_key' => $storageKey,
            'mime_type' => $storedMimeType,
            'size_bytes' => $sizeBytes,
            'checksum_sha256' => $checksum,
            'width' => null,
            'height' => null,
        ]);
    } catch (Throwable $exception) {
        sr_storage_delete((string) $stored['driver'], $storageKey);
        throw $exception;
    }
}

function sr_community_normalize_upload_files(array $files): array
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

function sr_community_attachment_format_for_mime(string $mimeType): string
{
    return match (strtolower(trim($mimeType))) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => '',
    };
}

function sr_community_create_attachment(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare(
        "INSERT INTO sr_community_attachments
            (post_id, uploader_account_id, original_name, stored_name, storage_path, storage_driver, storage_key, mime_type, size_bytes, checksum_sha256, width, height, status, created_at)
         VALUES
            (:post_id, :uploader_account_id, :original_name, :stored_name, :storage_path, :storage_driver, :storage_key, :mime_type, :size_bytes, :checksum_sha256, :width, :height, 'active', :created_at)"
    );
    $stmt->execute([
        'post_id' => (int) $data['post_id'],
        'uploader_account_id' => (int) $data['uploader_account_id'],
        'original_name' => (string) $data['original_name'],
        'stored_name' => (string) $data['stored_name'],
        'storage_path' => (string) $data['storage_path'],
        'storage_driver' => (string) ($data['storage_driver'] ?? 'local'),
        'storage_key' => (string) ($data['storage_key'] ?? ''),
        'mime_type' => (string) $data['mime_type'],
        'size_bytes' => (int) $data['size_bytes'],
        'checksum_sha256' => (string) $data['checksum_sha256'],
        'width' => isset($data['width']) ? (int) $data['width'] : null,
        'height' => isset($data['height']) ? (int) $data['height'] : null,
        'created_at' => sr_now(),
    ]);

    $attachmentId = (int) $pdo->lastInsertId();
    if ($attachmentId > 0 && function_exists('sr_community_feed_cache_mark_all_stale')) {
        sr_community_feed_cache_mark_all_stale($pdo, 'attachment_created');
    }

    return $attachmentId;
}

function sr_community_update_post_attachments_status(PDO $pdo, int $postId, string $status, bool $deleteFiles = true): int
{
    if ($postId < 1 || !in_array($status, ['active', 'hidden', 'deleted'], true)) {
        return 0;
    }

    if ($status === 'deleted') {
        return sr_community_redact_deleted_post_attachments($pdo, $postId, $deleteFiles);
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_community_attachments
         SET status = :status
         WHERE post_id = :post_id
           AND status <> :current_status'
    );
    $stmt->execute([
        'status' => $status,
        'current_status' => $status,
        'post_id' => $postId,
    ]);

    $updatedCount = $stmt->rowCount();
    if ($updatedCount > 0 && function_exists('sr_community_feed_cache_mark_all_stale')) {
        sr_community_feed_cache_mark_all_stale($pdo, 'attachment_status_changed');
    }

    return $updatedCount;
}

function sr_community_post_attachment_storage_refs(PDO $pdo, int $postId): array
{
    if ($postId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT id, storage_driver, storage_key
         FROM sr_community_attachments
         WHERE post_id = :post_id
           AND status <> 'deleted'"
    );
    $stmt->execute(['post_id' => $postId]);

    return $stmt->fetchAll();
}

function sr_community_cleanup_attachment_storage_refs(PDO $pdo, array $attachments, string $sourceType = 'attachment_post_delete'): void
{
    foreach ($attachments as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }
        $driver = sr_community_attachment_storage_driver($attachment);
        $key = sr_community_attachment_storage_key($attachment);
        sr_thumbnail_delete_variants([
            'module_key' => 'community',
            'storage_driver' => $driver,
            'storage_key' => $key,
        ]);
        if ($key !== '' && !sr_storage_delete($driver, $key)) {
            sr_community_record_storage_cleanup_failure(
                $pdo,
                $sourceType,
                (int) ($attachment['id'] ?? 0),
                $driver,
                $key,
                '게시글 삭제 후 첨부파일 저장소 정리에 실패했습니다.'
            );
        }
    }
}

function sr_community_redact_deleted_post_attachments(PDO $pdo, int $postId, bool $deleteFiles = true): int
{
    if ($postId < 1) {
        return 0;
    }

    $attachments = sr_community_post_attachment_storage_refs($pdo, $postId);
    if ($attachments === []) {
        return 0;
    }

    if ($deleteFiles) {
        sr_community_cleanup_attachment_storage_refs($pdo, $attachments);
    }

    $update = $pdo->prepare(
        "UPDATE sr_community_attachments
         SET original_name = :original_name,
             stored_name = '',
             storage_path = '',
             storage_key = '',
             status = 'deleted'
         WHERE post_id = :post_id
           AND status <> 'deleted'"
    );
    $update->execute([
        'original_name' => sr_t('community::redaction.deleted_attachment_name'),
        'post_id' => $postId,
    ]);

    $updatedCount = $update->rowCount();
    if ($updatedCount > 0 && function_exists('sr_community_feed_cache_mark_all_stale')) {
        sr_community_feed_cache_mark_all_stale($pdo, 'attachment_status_changed');
    }

    return $updatedCount;
}

function sr_community_restore_hidden_post_attachments(PDO $pdo, int $postId): int
{
    if ($postId < 1) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "UPDATE sr_community_attachments
         SET status = 'active'
         WHERE post_id = :post_id
           AND status = 'hidden'"
    );
    $stmt->execute(['post_id' => $postId]);

    $updatedCount = $stmt->rowCount();
    if ($updatedCount > 0 && function_exists('sr_community_feed_cache_mark_all_stale')) {
        sr_community_feed_cache_mark_all_stale($pdo, 'attachment_status_changed');
    }

    return $updatedCount;
}

function sr_community_attachment_mime_is_allowed(string $mimeType): bool
{
    return in_array(strtolower(trim($mimeType)), array_merge([
        'image/jpeg',
        'image/png',
        'image/webp',
    ], sr_community_file_mime_types_for_extensions(array_keys(sr_community_file_extension_mime_map()))), true);
}

function sr_community_attachment_is_image(array $attachment): bool
{
    return in_array(strtolower((string) ($attachment['mime_type'] ?? '')), [
        'image/jpeg',
        'image/png',
        'image/webp',
    ], true);
}

function sr_community_file_extension_mime_map(): array
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

function sr_community_file_mime_types_for_extensions(array $extensions): array
{
    $map = sr_community_file_extension_mime_map();
    $mimeTypes = [];
    foreach (sr_community_normalize_file_extensions($extensions) as $extension) {
        foreach ($map[$extension] ?? [] as $mimeType) {
            $mimeTypes[$mimeType] = true;
        }
    }

    return array_keys($mimeTypes);
}

function sr_community_format_bytes(int $bytes): string
{
    return sr_format_bytes($bytes);
}

function sr_community_attachment_download_logs_table_exists(PDO $pdo): bool
{
    static $existsByPdo = [];

    $cacheKey = (string) spl_object_id($pdo);
    if (array_key_exists($cacheKey, $existsByPdo)) {
        return $existsByPdo[$cacheKey];
    }

    try {
        if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'sr_community_attachment_download_logs'");
            $existsByPdo[$cacheKey] = $stmt !== false && $stmt->fetchColumn() !== false;
            return $existsByPdo[$cacheKey];
        }

        $prefix = $pdo instanceof SrPrefixedPDO ? $pdo->srTablePrefix() : 'sr_';
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name'
        );
        $stmt->execute(['table_name' => $prefix . 'community_attachment_download_logs']);
        $existsByPdo[$cacheKey] = (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $exception) {
        $existsByPdo[$cacheKey] = false;
    }

    return $existsByPdo[$cacheKey];
}

function sr_community_attachment_download_log_columns(PDO $pdo): array
{
    static $columnsByPdo = [];

    $cacheKey = (string) spl_object_id($pdo);
    if (array_key_exists($cacheKey, $columnsByPdo)) {
        return $columnsByPdo[$cacheKey];
    }

    $columns = [];
    try {
        if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $rows = $pdo->query('PRAGMA table_info(sr_community_attachment_download_logs)')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $name = (string) ($row['name'] ?? '');
                if ($name !== '') {
                    $columns[$name] = true;
                }
            }
        } else {
            $prefix = $pdo instanceof SrPrefixedPDO ? $pdo->srTablePrefix() : 'sr_';
            $stmt = $pdo->prepare(
                'SELECT COLUMN_NAME
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name'
            );
            $stmt->execute(['table_name' => $prefix . 'community_attachment_download_logs']);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $columnName) {
                $columnName = (string) $columnName;
                if ($columnName !== '') {
                    $columns[$columnName] = true;
                }
            }
        }
    } catch (Throwable $exception) {
        $columns = [];
    }

    $columnsByPdo[$cacheKey] = $columns;
    return $columns;
}

function sr_community_attachment_download_log_has_columns(PDO $pdo, array $columnNames): bool
{
    $columns = sr_community_attachment_download_log_columns($pdo);
    foreach ($columnNames as $columnName) {
        if (!isset($columns[(string) $columnName])) {
            return false;
        }
    }

    return true;
}

function sr_community_attachment_download_log_refund_columns_exist(PDO $pdo): bool
{
    return sr_community_attachment_download_log_has_columns($pdo, [
        'refund_status',
        'refund_transaction_ids_json',
        'refund_note',
        'refunded_by_account_id',
        'refunded_at',
        'access_revoked_at',
    ]);
}

function sr_community_asset_log_columns(PDO $pdo): array
{
    static $columnsByPdo = [];

    $cacheKey = (string) spl_object_id($pdo);
    if (array_key_exists($cacheKey, $columnsByPdo)) {
        return $columnsByPdo[$cacheKey];
    }

    $columns = [];
    try {
        if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $rows = $pdo->query('PRAGMA table_info(sr_community_asset_logs)')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $name = (string) ($row['name'] ?? '');
                if ($name !== '') {
                    $columns[$name] = true;
                }
            }
        } else {
            $prefix = $pdo instanceof SrPrefixedPDO ? $pdo->srTablePrefix() : 'sr_';
            $stmt = $pdo->prepare(
                'SELECT COLUMN_NAME
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name'
            );
            $stmt->execute(['table_name' => $prefix . 'community_asset_logs']);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $columnName) {
                $columnName = (string) $columnName;
                if ($columnName !== '') {
                    $columns[$columnName] = true;
                }
            }
        }
    } catch (Throwable $exception) {
        $columns = [];
    }

    $columnsByPdo[$cacheKey] = $columns;
    return $columns;
}

function sr_community_record_attachment_download(PDO $pdo, array $attachment, ?int $accountId, array $accessResult = []): void
{
    if (!sr_community_attachment_download_logs_table_exists($pdo)) {
        return;
    }

    $post = is_array($attachment['post'] ?? null) ? $attachment['post'] : [];
    $accessLogIds = [];
    foreach ((array) ($accessResult['access_log_ids'] ?? []) as $accessLogId) {
        $accessLogId = (int) $accessLogId;
        if ($accessLogId > 0) {
            $accessLogIds[$accessLogId] = $accessLogId;
        }
    }
    $processedLogs = is_array($accessResult['processed_logs'] ?? null) ? $accessResult['processed_logs'] : [];
    if ($processedLogs === [] && is_array($accessResult['logs'] ?? null)) {
        $processedLogs = $accessResult['logs'];
    }
    foreach ($processedLogs as $processedLog) {
        $dedupeKey = is_array($processedLog) ? (string) ($processedLog['dedupe_key'] ?? '') : '';
        if ($dedupeKey === '') {
            continue;
        }
        try {
            $assetLog = function_exists('sr_community_asset_log') ? sr_community_asset_log($pdo, $dedupeKey) : null;
            $assetLogId = is_array($assetLog) ? (int) ($assetLog['id'] ?? 0) : 0;
            if ($assetLogId > 0) {
                $accessLogIds[$assetLogId] = $assetLogId;
            }
        } catch (Throwable $exception) {
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'community_attachment_download_asset_log_link_failed');
            }
        }
    }
    $accessLogIdsJson = json_encode(array_values($accessLogIds), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $downloadType = !empty($accessResult['paid']) || $accessLogIds !== [] ? 'paid' : 'free';
    $amount = $downloadType === 'paid' && $accessLogIds !== [] ? (int) ($accessResult['amount'] ?? 0) : 0;

    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_attachment_download_logs
            (board_id, post_id, attachment_id, account_id, download_type, charge_policy, asset_module, amount, asset_access_log_ids_json, post_title_snapshot, attachment_original_name_snapshot, created_at)
         VALUES
            (:board_id, :post_id, :attachment_id, :account_id, :download_type, :charge_policy, :asset_module, :amount, :asset_access_log_ids_json, :post_title_snapshot, :attachment_original_name_snapshot, :created_at)'
    );
    $stmt->execute([
        'board_id' => (int) ($post['board_id'] ?? 0),
        'post_id' => (int) ($attachment['post_id'] ?? $post['id'] ?? 0),
        'attachment_id' => (int) ($attachment['id'] ?? 0),
        'account_id' => $accountId !== null && $accountId > 0 ? $accountId : null,
        'download_type' => $downloadType,
        'charge_policy' => (string) ($accessResult['charge_policy'] ?? 'once'),
        'asset_module' => (string) ($accessResult['asset_module'] ?? ''),
        'amount' => $amount,
        'asset_access_log_ids_json' => is_string($accessLogIdsJson) ? $accessLogIdsJson : '[]',
        'post_title_snapshot' => sr_clean_single_line((string) ($post['title'] ?? ''), 160),
        'attachment_original_name_snapshot' => sr_clean_single_line((string) ($attachment['original_name'] ?? ''), 160),
        'created_at' => sr_now(),
    ]);
}

function sr_community_attachment_download_log_access_log_ids(array $downloadLog): array
{
    $decoded = json_decode((string) ($downloadLog['asset_access_log_ids_json'] ?? '[]'), true);
    if (!is_array($decoded)) {
        return [];
    }

    $ids = [];
    foreach ($decoded as $value) {
        $id = (int) $value;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}

function sr_community_admin_attachment_download_logs_with_asset_summaries(PDO $pdo, array $downloadLogs): array
{
    if ($downloadLogs === []) {
        return $downloadLogs;
    }

    $idsByLogIndex = [];
    $allIds = [];
    foreach ($downloadLogs as $index => $downloadLog) {
        $ids = sr_community_attachment_download_log_access_log_ids($downloadLog);
        if ($ids === []) {
            continue;
        }
        $idsByLogIndex[(int) $index] = $ids;
        foreach ($ids as $id) {
            $allIds[$id] = $id;
        }
    }
    if ($allIds === []) {
        return $downloadLogs;
    }

    $columns = sr_community_asset_log_columns($pdo);
    foreach (['id', 'account_id', 'asset_module', 'transaction_id', 'reference_type', 'reference_id', 'subject_type', 'subject_id', 'event_key', 'amount'] as $requiredColumn) {
        if (!isset($columns[$requiredColumn])) {
            return $downloadLogs;
        }
    }

    $settlementAmountSelect = isset($columns['settlement_amount']) ? 'settlement_amount' : '0 AS settlement_amount';
    $settlementCurrencySelect = isset($columns['settlement_currency']) ? 'settlement_currency' : "'KRW' AS settlement_currency";
    $settlementKindSelect = isset($columns['settlement_kind']) ? 'settlement_kind' : "'legacy_unknown' AS settlement_kind";
    $snapshotSchemaVersionSelect = isset($columns['snapshot_schema_version']) ? 'snapshot_schema_version' : "'asset_settlement_snapshot_v1' AS snapshot_schema_version";
    $roundingPolicyVersionSelect = isset($columns['rounding_policy_version']) ? 'rounding_policy_version' : "'asset_settlement_rounding_v1' AS rounding_policy_version";

    $placeholders = [];
    $params = [];
    foreach (array_values($allIds) as $index => $id) {
        $key = 'id_' . (string) $index;
        $placeholders[] = ':' . $key;
        $params[$key] = (int) $id;
    }

    $stmt = $pdo->prepare(
        'SELECT id, account_id, asset_module, transaction_id, reference_type, reference_id, subject_type, subject_id, event_key, amount,
                ' . $settlementAmountSelect . ',
                ' . $settlementCurrencySelect . ',
                ' . $settlementKindSelect . ',
                ' . $snapshotSchemaVersionSelect . ',
                ' . $roundingPolicyVersionSelect . '
         FROM sr_community_asset_logs
         WHERE id IN (' . implode(', ', $placeholders) . ')
         ORDER BY id ASC'
    );
    $stmt->execute($params);

    $assetLogsById = [];
    foreach ($stmt->fetchAll() as $assetLog) {
        $assetLogsById[(int) ($assetLog['id'] ?? 0)] = $assetLog;
    }

    foreach ($idsByLogIndex as $index => $ids) {
        $summaryLines = [];
        $downloadLog = $downloadLogs[$index];
        foreach ($ids as $id) {
            $assetLog = $assetLogsById[$id] ?? null;
            if (!is_array($assetLog)) {
                continue;
            }
            if ((int) ($assetLog['account_id'] ?? 0) !== (int) ($downloadLog['account_id'] ?? 0)
                || (string) ($assetLog['reference_type'] ?? '') !== 'community.attachment'
                || (string) ($assetLog['subject_type'] ?? '') !== 'community.attachment'
                || (int) ($assetLog['subject_id'] ?? 0) !== (int) ($downloadLog['attachment_id'] ?? 0)
                || (string) ($assetLog['event_key'] ?? '') !== 'attachment_download'
            ) {
                continue;
            }
            $assetModule = (string) ($assetLog['asset_module'] ?? '');
            $summaryLines[] = trim(implode(' · ', array_filter([
                sr_community_asset_module_labels($assetModule, $pdo) . ' ' . number_format((int) ($assetLog['amount'] ?? 0)),
                '기준 ' . number_format((int) ($assetLog['settlement_amount'] ?? 0)) . ' ' . (string) ($assetLog['settlement_currency'] ?? 'KRW'),
                (string) ($assetLog['settlement_kind'] ?? 'legacy_unknown'),
                'snapshot ' . (string) ($assetLog['snapshot_schema_version'] ?? 'asset_settlement_snapshot_v1'),
                'rounding ' . (string) ($assetLog['rounding_policy_version'] ?? 'asset_settlement_rounding_v1'),
            ], static fn (string $part): bool => $part !== '')));
        }
        $downloadLogs[$index]['asset_log_summary'] = implode("\n", $summaryLines);
    }

    return $downloadLogs;
}

function sr_community_admin_attachment_status_counts(PDO $pdo): array
{
    $counts = ['total' => 0, 'active' => 0, 'hidden' => 0];
    $stmt = $pdo->query(
        "SELECT status, COUNT(*) AS count_value
         FROM sr_community_attachments
         WHERE status IN ('active', 'hidden')
         GROUP BY status"
    );
    foreach ($stmt !== false ? $stmt->fetchAll() : [] as $row) {
        $status = (string) ($row['status'] ?? '');
        $count = (int) ($row['count_value'] ?? 0);
        if (array_key_exists($status, $counts)) {
            $counts[$status] = $count;
            $counts['total'] += $count;
        }
    }

    return $counts;
}

function sr_community_admin_filter_values(string $key, array $allowedValues): array
{
    $raw = $_GET[$key] ?? [];
    if (!is_array($raw)) {
        $raw = (string) $raw === '' ? [] : [(string) $raw];
    }

    $values = [];
    foreach ($raw as $value) {
        $value = (string) $value;
        if (in_array($value, $allowedValues, true)) {
            $values[$value] = $value;
        }
    }

    return array_values($values);
}

function sr_community_admin_attachment_sort_options(): array
{
    return [
        'created_at' => ['label' => '등록일', 'columns' => ['a.created_at', 'a.id']],
        'original_name' => ['label' => '파일명', 'columns' => ['a.original_name', 'a.id']],
        'size_bytes' => ['label' => '크기', 'columns' => ['a.size_bytes', 'a.id']],
        'status' => ['label' => '상태', 'columns' => ['a.status', 'a.id']],
        'board' => ['label' => '게시판', 'columns' => ['b.title', 'a.id']],
        'post' => ['label' => '게시글', 'columns' => ['p.title', 'a.id']],
        'download_count' => ['label' => '다운로드', 'columns' => ['download_count', 'a.id']],
    ];
}

function sr_community_admin_attachment_default_sort(): array
{
    return sr_admin_sort_default('created_at', 'desc');
}

function sr_community_admin_attachment_where_sql(array $filters): array
{
    $conditions = [];
    $params = [];

    $statuses = is_array($filters['status'] ?? null) ? $filters['status'] : [];
    if ($statuses !== []) {
        $placeholders = [];
        foreach (array_values($statuses) as $index => $status) {
            $paramKey = 'status_' . (string) $index;
            $placeholders[] = ':' . $paramKey;
            $params[$paramKey] = (string) $status;
        }
        $conditions[] = 'a.status IN (' . implode(', ', $placeholders) . ')';
    } else {
        $conditions[] = "a.status IN ('active', 'hidden')";
    }

    $boardId = (int) ($filters['board_id'] ?? 0);
    if ($boardId > 0) {
        $conditions[] = 'p.board_id = :board_id';
        $params['board_id'] = $boardId;
    }

    $postId = (int) ($filters['post_id'] ?? 0);
    if ($postId > 0) {
        $conditions[] = 'a.post_id = :post_id';
        $params['post_id'] = $postId;
    }

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $conditions[] = "(a.original_name LIKE :keyword_like ESCAPE '\\\\' OR p.title LIKE :keyword_like ESCAPE '\\\\' OR b.title LIKE :keyword_like ESCAPE '\\\\' OR b.board_key LIKE :keyword_like ESCAPE '\\\\')";
        $params['keyword_like'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    }

    return [
        'sql' => $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions),
        'params' => $params,
    ];
}

function sr_community_admin_attachment_count(PDO $pdo, array $filters): int
{
    $where = sr_community_admin_attachment_where_sql($filters);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sr_community_attachments a
         INNER JOIN sr_community_posts p ON p.id = a.post_id
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         ' . $where['sql']
    );
    $stmt->execute($where['params']);

    return (int) $stmt->fetchColumn();
}

function sr_community_admin_attachments(PDO $pdo, array $filters, int $limit, int $offset, array $sort = []): array
{
    $where = sr_community_admin_attachment_where_sql($filters);
    $downloadJoinSql = sr_community_attachment_download_logs_table_exists($pdo)
        ? 'LEFT JOIN sr_community_attachment_download_logs d ON d.attachment_id = a.id'
        : '';
    $downloadCountSql = sr_community_attachment_download_logs_table_exists($pdo)
        ? 'COUNT(d.id)'
        : '0';
    $stmt = $pdo->prepare(
        'SELECT a.id, a.post_id, a.uploader_account_id, a.original_name, a.mime_type, a.size_bytes, a.width, a.height, a.status, a.created_at,
                p.title AS post_title, p.status AS post_status, p.board_id,
                b.title AS board_title, b.board_key,
                u.display_name AS uploader_display_name,
                ' . $downloadCountSql . ' AS download_count
         FROM sr_community_attachments a
         INNER JOIN sr_community_posts p ON p.id = a.post_id
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         LEFT JOIN sr_member_accounts u ON u.id = a.uploader_account_id
         ' . $downloadJoinSql . '
         ' . $where['sql'] . '
         GROUP BY a.id, a.post_id, a.uploader_account_id, a.original_name, a.mime_type, a.size_bytes, a.width, a.height, a.status, a.created_at,
                  p.title, p.status, p.board_id, b.title, b.board_key, u.display_name
         ' . sr_admin_sort_order_sql(sr_community_admin_attachment_sort_options(), $sort, sr_community_admin_attachment_default_sort()) . '
         LIMIT :limit_value OFFSET :offset_value'
    );
    foreach ($where['params'] as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset_value', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_community_admin_set_attachment_status(PDO $pdo, array $attachmentIds, string $status): array
{
    $status = in_array($status, ['active', 'hidden'], true) ? $status : '';
    $ids = [];
    foreach ($attachmentIds as $attachmentId) {
        $attachmentId = (int) $attachmentId;
        if ($attachmentId > 0) {
            $ids[$attachmentId] = $attachmentId;
        }
    }
    $ids = array_values($ids);
    if ($status === '' || $ids === []) {
        return ['changed' => 0, 'skipped' => 0];
    }

    $changed = 0;
    $skipped = 0;
    $stmt = $pdo->prepare(
        'UPDATE sr_community_attachments
         SET status = :status
         WHERE id = :id
           AND status <> :status'
    );
    foreach ($ids as $attachmentId) {
        $before = sr_community_attachment_by_id($pdo, $attachmentId);
        if (!is_array($before) || (string) ($before['status'] ?? '') === $status) {
            $skipped++;
            continue;
        }
        $stmt->execute([
            'status' => $status,
            'id' => $attachmentId,
        ]);
        $changed += $stmt->rowCount();
    }

    return ['changed' => $changed, 'skipped' => $skipped];
}

function sr_community_admin_attachment_download_log_sort_options(?PDO $pdo = null): array
{
    $options = [
        'created_at' => ['label' => '다운로드 시각', 'columns' => ['d.created_at', 'd.id']],
        'board' => ['label' => '게시판', 'columns' => ['board_title', 'd.id']],
        'post' => ['label' => '게시글', 'columns' => ['post_title', 'd.id']],
        'attachment' => ['label' => '첨부파일', 'columns' => ['attachment_name', 'd.id']],
        'account_id' => ['label' => '회원', 'columns' => ['d.account_id', 'd.id']],
        'download_type' => ['label' => '구분', 'columns' => ['d.download_type', 'd.id']],
        'amount' => ['label' => '금액', 'columns' => ['d.amount', 'd.id']],
    ];
    if ($pdo instanceof PDO && sr_community_attachment_download_log_refund_columns_exist($pdo)) {
        $options['refunded_at'] = ['label' => '환불 시각', 'columns' => ['d.refunded_at', 'd.id']];
    }

    return $options;
}

function sr_community_admin_attachment_download_log_default_sort(): array
{
    return sr_admin_sort_default('created_at', 'desc');
}

function sr_community_admin_attachment_download_log_where_sql(PDO $pdo, array $filters): array
{
    $conditions = [];
    $params = [];

    foreach (['board_id', 'post_id', 'attachment_id', 'account_id'] as $key) {
        $value = (int) ($filters[$key] ?? 0);
        if ($value > 0) {
            $conditions[] = 'd.' . $key . ' = :' . $key;
            $params[$key] = $value;
        }
    }

    $downloadTypes = is_array($filters['download_type'] ?? null) ? $filters['download_type'] : [];
    if ($downloadTypes !== []) {
        $placeholders = [];
        foreach (array_values($downloadTypes) as $index => $downloadType) {
            $paramKey = 'download_type_' . (string) $index;
            $placeholders[] = ':' . $paramKey;
            $params[$paramKey] = (string) $downloadType;
        }
        $conditions[] = 'd.download_type IN (' . implode(', ', $placeholders) . ')';
    }

    $refundStatuses = is_array($filters['refund_status'] ?? null) ? $filters['refund_status'] : [];
    if ($refundStatuses !== []) {
        if (sr_community_attachment_download_log_refund_columns_exist($pdo)) {
            $refundConditions = [];
            foreach (array_values($refundStatuses) as $index => $refundStatus) {
                if ((string) $refundStatus === 'none') {
                    $refundConditions[] = "d.refund_status = ''";
                    continue;
                }
                $paramKey = 'refund_status_' . (string) $index;
                $refundConditions[] = 'd.refund_status = :' . $paramKey;
                $params[$paramKey] = (string) $refundStatus;
            }
            if ($refundConditions !== []) {
                $conditions[] = '(' . implode(' OR ', $refundConditions) . ')';
            }
        } elseif (!in_array('none', array_map('strval', $refundStatuses), true)) {
            $conditions[] = '1 = 0';
        }
    }

    $dateFrom = (string) ($filters['date_from'] ?? '');
    if ($dateFrom !== '') {
        $conditions[] = 'd.created_at >= :date_from';
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }

    $dateTo = (string) ($filters['date_to'] ?? '');
    if ($dateTo !== '') {
        $conditions[] = 'd.created_at <= :date_to';
        $params['date_to'] = $dateTo . ' 23:59:59';
    }

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $conditions[] = "(d.post_title_snapshot LIKE :keyword_like ESCAPE '\\\\' OR d.attachment_original_name_snapshot LIKE :keyword_like ESCAPE '\\\\' OR p.title LIKE :keyword_like ESCAPE '\\\\' OR a.original_name LIKE :keyword_like ESCAPE '\\\\' OR b.title LIKE :keyword_like ESCAPE '\\\\' OR b.board_key LIKE :keyword_like ESCAPE '\\\\' OR u.display_name LIKE :keyword_like ESCAPE '\\\\' OR u.email LIKE :keyword_like ESCAPE '\\\\')";
        $params['keyword_like'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    }

    return [
        'sql' => $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions),
        'params' => $params,
    ];
}

function sr_community_admin_attachment_download_log_count(PDO $pdo, array $filters): int
{
    if (!sr_community_attachment_download_logs_table_exists($pdo)) {
        return 0;
    }

    $where = sr_community_admin_attachment_download_log_where_sql($pdo, $filters);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sr_community_attachment_download_logs d
         LEFT JOIN sr_community_attachments a ON a.id = d.attachment_id
         LEFT JOIN sr_community_posts p ON p.id = d.post_id
         LEFT JOIN sr_community_boards b ON b.id = d.board_id
         LEFT JOIN sr_member_accounts u ON u.id = d.account_id
         ' . $where['sql']
    );
    $stmt->execute($where['params']);

    return (int) $stmt->fetchColumn();
}

function sr_community_admin_attachment_download_logs(PDO $pdo, array $filters, int $limit, int $offset, array $sort = []): array
{
    if (!sr_community_attachment_download_logs_table_exists($pdo)) {
        return [];
    }

    $where = sr_community_admin_attachment_download_log_where_sql($pdo, $filters);
    $hasRefundColumns = sr_community_attachment_download_log_refund_columns_exist($pdo);
    $refundStatusSelect = $hasRefundColumns ? 'd.refund_status' : "''";
    $refundTransactionIdsSelect = $hasRefundColumns ? 'd.refund_transaction_ids_json' : "'[]'";
    $refundNoteSelect = $hasRefundColumns ? 'd.refund_note' : "''";
    $refundedByAccountIdSelect = $hasRefundColumns ? 'd.refunded_by_account_id' : 'NULL';
    $refundedAtSelect = $hasRefundColumns ? 'd.refunded_at' : 'NULL';
    $accessRevokedAtSelect = $hasRefundColumns ? 'd.access_revoked_at' : 'NULL';
    $refundedByJoinSql = $hasRefundColumns ? 'LEFT JOIN sr_member_accounts rb ON rb.id = d.refunded_by_account_id' : '';
    $refundedByDisplayNameSelect = $hasRefundColumns ? 'rb.display_name' : "''";
    $stmt = $pdo->prepare(
        "SELECT d.id, d.board_id, d.post_id, d.attachment_id, d.account_id, d.download_type, d.charge_policy, d.asset_module, d.amount,
                d.asset_access_log_ids_json, d.created_at,
                " . $refundStatusSelect . " AS refund_status,
                " . $refundTransactionIdsSelect . " AS refund_transaction_ids_json,
                " . $refundNoteSelect . " AS refund_note,
                " . $refundedByAccountIdSelect . " AS refunded_by_account_id,
                " . $refundedAtSelect . " AS refunded_at,
                " . $accessRevokedAtSelect . " AS access_revoked_at,
                " . $refundedByDisplayNameSelect . " AS refunded_by_display_name,
                COALESCE(NULLIF(b.title, ''), '') AS board_title,
                b.board_key,
                COALESCE(NULLIF(p.title, ''), NULLIF(d.post_title_snapshot, '')) AS post_title,
                p.status AS post_status,
                COALESCE(NULLIF(a.original_name, ''), NULLIF(d.attachment_original_name_snapshot, '')) AS attachment_name,
                a.status AS attachment_status,
                u.display_name,
                u.email
         FROM sr_community_attachment_download_logs d
         LEFT JOIN sr_community_attachments a ON a.id = d.attachment_id
         LEFT JOIN sr_community_posts p ON p.id = d.post_id
         LEFT JOIN sr_community_boards b ON b.id = d.board_id
         LEFT JOIN sr_member_accounts u ON u.id = d.account_id
         " . $refundedByJoinSql . "
         " . $where['sql'] . '
         ' . sr_admin_sort_order_sql(sr_community_admin_attachment_download_log_sort_options($pdo), $sort, sr_community_admin_attachment_download_log_default_sort()) . '
         LIMIT :limit_value OFFSET :offset_value'
    );
    foreach ($where['params'] as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset_value', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return sr_community_admin_attachment_download_logs_with_asset_summaries($pdo, $stmt->fetchAll());
}

function sr_community_admin_attachment_download_log_by_id_for_update(PDO $pdo, int $downloadLogId): ?array
{
    if ($downloadLogId <= 0 || !sr_community_attachment_download_logs_table_exists($pdo)) {
        return null;
    }

    $lockClause = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    $stmt = $pdo->prepare('SELECT * FROM sr_community_attachment_download_logs WHERE id = :id LIMIT 1' . $lockClause);
    $stmt->execute(['id' => $downloadLogId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_attachment_download_access_logs_for_refund(PDO $pdo, array $downloadLog): array
{
    $ids = sr_community_attachment_download_log_access_log_ids($downloadLog);
    if ($ids === []) {
        return [];
    }

    $params = [
        'account_id' => (int) ($downloadLog['account_id'] ?? 0),
        'attachment_id' => (int) ($downloadLog['attachment_id'] ?? 0),
    ];
    $placeholders = [];
    foreach ($ids as $index => $id) {
        $key = 'id_' . (string) $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $id;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_community_asset_logs
         WHERE id IN (' . implode(', ', $placeholders) . ')
           AND account_id = :account_id
           AND subject_type = \'community.attachment\'
           AND subject_id = :attachment_id
           AND event_key = \'attachment_download\'
         ORDER BY id ASC'
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_community_revoke_attachment_download_access_entitlement(PDO $pdo, int $accountId, int $attachmentId): int
{
    if ($accountId <= 0 || $attachmentId <= 0 || !sr_community_access_entitlements_table_exists($pdo)) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "DELETE FROM sr_community_access_entitlements
         WHERE account_id = :account_id
           AND subject_type = 'community.attachment'
           AND subject_id = :attachment_id
           AND event_key = 'attachment_download'"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'attachment_id' => $attachmentId,
    ]);

    return $stmt->rowCount();
}

function sr_community_mark_payment_ledger_items_reversed_if_available(PDO $pdo, int $accountId, array $references, string $reason): array
{
    if ($references === [] || !function_exists('sr_module_enabled') || !sr_module_enabled($pdo, 'payment_ledger')) {
        return ['payment_record_ids' => [], 'reversed_item_count' => 0, 'refunded_record_ids' => []];
    }
    if (!is_file(SR_ROOT . '/modules/payment_ledger/helpers.php')) {
        throw new RuntimeException('결제 기록 기반 모듈 helper를 찾을 수 없습니다.');
    }

    require_once SR_ROOT . '/modules/payment_ledger/helpers.php';
    if (!function_exists('sr_payment_ledger_mark_item_references_reversed') || !sr_payment_ledger_tables_available($pdo)) {
        throw new RuntimeException('결제 기록 기반 테이블이 준비되지 않았습니다.');
    }

    return sr_payment_ledger_mark_item_references_reversed($pdo, $accountId, $references, $reason, false);
}

function sr_community_refund_attachment_download(PDO $pdo, int $downloadLogId, int $adminAccountId, string $refundNote, string $refundExpirationPolicy = 'original'): array
{
    $refundNote = sr_clean_single_line($refundNote, 255);
    $refundExpirationPolicy = in_array($refundExpirationPolicy, ['original', 'reset'], true) ? $refundExpirationPolicy : 'original';
    if ($downloadLogId <= 0) {
        return ['ok' => false, 'message' => '환불할 첨부 다운로드 내역을 선택하세요.'];
    }
    if ($adminAccountId <= 0) {
        return ['ok' => false, 'message' => '처리 관리자 정보를 확인할 수 없습니다.'];
    }
    if ($refundNote === '') {
        return ['ok' => false, 'message' => '환불 사유를 입력하세요.'];
    }
    if (!sr_community_attachment_download_log_refund_columns_exist($pdo)) {
        return ['ok' => false, 'message' => '첨부 다운로드 환불 기록 컬럼이 아직 준비되지 않았습니다. 대기 중인 업데이트를 먼저 적용하세요.'];
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $downloadLog = sr_community_admin_attachment_download_log_by_id_for_update($pdo, $downloadLogId);
        if (!is_array($downloadLog)) {
            throw new RuntimeException('환불할 첨부 다운로드 내역을 찾을 수 없습니다.');
        }
        if ((string) ($downloadLog['download_type'] ?? '') !== 'paid') {
            throw new RuntimeException('무료 다운로드는 환불 대상이 아닙니다.');
        }
        if ((string) ($downloadLog['refund_status'] ?? '') !== '') {
            throw new RuntimeException('이미 환불 또는 접근권 회수 처리된 다운로드입니다.');
        }

        $accountId = (int) ($downloadLog['account_id'] ?? 0);
        $attachmentId = (int) ($downloadLog['attachment_id'] ?? 0);
        if ($accountId <= 0 || $attachmentId <= 0) {
            throw new RuntimeException('환불 대상 회원 또는 첨부 정보를 확인할 수 없습니다.');
        }

        $accessLogIds = sr_community_attachment_download_log_access_log_ids($downloadLog);
        $accessLogs = $accessLogIds !== [] ? sr_community_attachment_download_access_logs_for_refund($pdo, $downloadLog) : [];
        if ($accessLogIds !== [] && $accessLogs === []) {
            throw new RuntimeException('연결된 차감 또는 접근권 로그를 찾을 수 없습니다.');
        }

        $refundTransactionIds = [];
        $paymentLedgerReferences = [];
        foreach ($accessLogs as $accessLog) {
            $amount = (int) ($accessLog['amount'] ?? 0);
            $transactionId = (int) ($accessLog['transaction_id'] ?? 0);
            if ($amount <= 0 || $transactionId <= 0) {
                continue;
            }

            $assetModule = (string) ($accessLog['asset_module'] ?? '');
            if (!sr_community_asset_module_is_available($pdo, $assetModule)) {
                throw new RuntimeException('환불할 차감 항목을 사용할 수 없습니다: ' . $assetModule);
            }

            $assetOption = sr_community_asset_modules($pdo)[$assetModule];
            $transactionData = [
                'account_id' => $accountId,
                'amount' => $amount,
                'transaction_type' => (string) ($assetOption['refund_type'] ?? 'refund'),
                'reason' => '커뮤니티 첨부 다운로드 환불: ' . $refundNote,
                'reference_type' => 'refund',
                'reference_id' => $assetModule . '_transaction:' . (string) $transactionId,
                'created_by_account_id' => $adminAccountId,
            ];
            if ($assetModule === 'point') {
                $transactionData['refund_expiration_policy'] = $refundExpirationPolicy;
            }
            $paymentLedgerReferences[] = [
                'item_kind' => 'asset_transaction',
                'owner_module' => $assetModule,
                'reference_type' => $assetModule . '_transaction',
                'reference_id' => (string) $transactionId,
            ];
            $paymentLedgerReferences[] = [
                'item_kind' => 'asset_access_log',
                'owner_module' => 'community',
                'reference_type' => 'community_asset_log',
                'reference_id' => (string) (int) ($accessLog['id'] ?? 0),
            ];
            if ($assetModule === 'point' && function_exists('sr_point_create_refund_transactions')) {
                foreach (sr_point_create_refund_transactions($pdo, $transactionData) as $refundTransactionId) {
                    $refundTransactionIds[] = $assetModule . ':' . (string) $refundTransactionId;
                }
                continue;
            }

            $refundTransactionId = sr_community_create_asset_transaction($pdo, $assetModule, $transactionData);
            $refundTransactionIds[] = $assetModule . ':' . (string) $refundTransactionId;
        }

        $accessRevoked = false;
        if ((string) ($downloadLog['charge_policy'] ?? '') === 'once' || (int) ($downloadLog['amount'] ?? 0) <= 0) {
            $accessRevoked = sr_community_revoke_attachment_download_access_entitlement($pdo, $accountId, $attachmentId) > 0;
        }
        if ($accessRevoked || $refundTransactionIds !== []) {
            $paymentLedgerReferences[] = [
                'item_kind' => 'access_entitlement',
                'owner_module' => 'community',
                'reference_type' => 'community.access_entitlement',
                'reference_id' => 'community.attachment:' . (string) $attachmentId . ':attachment_download',
            ];
        }

        if ($refundTransactionIds === [] && !$accessRevoked) {
            throw new RuntimeException('환불할 원장 거래나 회수할 접근권을 찾을 수 없습니다.');
        }

        $refundTransactionIdsJson = json_encode($refundTransactionIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $now = sr_now();
        $refundStatus = $refundTransactionIds !== [] ? 'refunded' : 'access_revoked';
        $stmt = $pdo->prepare(
            'UPDATE sr_community_attachment_download_logs
             SET refund_status = :refund_status,
                 refund_transaction_ids_json = :refund_transaction_ids_json,
                 refund_note = :refund_note,
                 refunded_by_account_id = :refunded_by_account_id,
                 refunded_at = :refunded_at,
                 access_revoked_at = :access_revoked_at
             WHERE id = :id
               AND refund_status = \'\''
        );
        $stmt->execute([
            'refund_status' => $refundStatus,
            'refund_transaction_ids_json' => is_string($refundTransactionIdsJson) ? $refundTransactionIdsJson : '[]',
            'refund_note' => $refundNote,
            'refunded_by_account_id' => $adminAccountId,
            'refunded_at' => $now,
            'access_revoked_at' => $accessRevoked ? $now : null,
            'id' => $downloadLogId,
        ]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('이미 처리된 다운로드입니다.');
        }
        sr_community_mark_payment_ledger_items_reversed_if_available($pdo, $accountId, $paymentLedgerReferences, '커뮤니티 첨부 다운로드 환불: ' . $refundNote);

        if ($startedTransaction) {
            $pdo->commit();
        }

        try {
            sr_audit_log($pdo, [
                'actor_account_id' => $adminAccountId,
                'actor_type' => 'admin',
                'event_type' => 'community_attachment_download.refunded',
                'target_type' => 'community_attachment_download',
                'target_id' => (string) $downloadLogId,
                'result' => 'success',
                'message' => 'Community attachment download refunded.',
                'metadata' => [
                    'attachment_id' => $attachmentId,
                    'account_id' => $accountId,
                    'refund_status' => $refundStatus,
                    'refund_expiration_policy' => $refundExpirationPolicy,
                    'refund_transaction_ids' => $refundTransactionIds,
                    'access_revoked' => $accessRevoked,
                ],
            ]);
        } catch (Throwable $auditException) {
            if (function_exists('sr_log_exception')) {
                sr_log_exception($auditException, 'community_attachment_download_refund_audit_failed');
            }
        }

        return ['ok' => true, 'message' => $refundStatus === 'refunded' ? '첨부 다운로드 차감을 환불 처리했습니다.' : '첨부 다운로드 접근권을 회수했습니다.'];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'community_attachment_download_refund_failed');
        }

        return ['ok' => false, 'message' => $exception->getMessage()];
    }
}

function sr_community_attachment_file_path(array $attachment): ?string
{
    $driver = (string) ($attachment['storage_driver'] ?? 'local');
    $key = (string) ($attachment['storage_key'] ?? '');
    if ($driver === 'local' && $key !== '') {
        $keyPath = sr_storage_local_path($key);
        if (is_string($keyPath)) {
            return $keyPath;
        }
    }

    $storageRoot = realpath(SR_ROOT . '/storage');
    if (!is_string($storageRoot) || !is_dir($storageRoot)) {
        return null;
    }

    $storagePath = (string) ($attachment['storage_path'] ?? '');
    if ($storagePath === '' || str_contains($storagePath, "\0")) {
        return null;
    }

    $candidate = str_starts_with($storagePath, DIRECTORY_SEPARATOR)
        ? $storagePath
        : SR_ROOT . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $storagePath), DIRECTORY_SEPARATOR);
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

function sr_community_attachment_storage_driver(array $attachment): string
{
    $driver = strtolower((string) ($attachment['storage_driver'] ?? 'local'));
    return in_array($driver, ['local', 's3'], true) ? $driver : 'local';
}

function sr_community_attachment_storage_key(array $attachment): string
{
    $key = (string) ($attachment['storage_key'] ?? '');
    if ($key !== '' && sr_storage_key_is_safe($key)) {
        return $key;
    }

    $storagePath = (string) ($attachment['storage_path'] ?? '');
    if (str_starts_with($storagePath, SR_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR)) {
        $storagePath = substr($storagePath, strlen(SR_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR));
    } elseif (str_starts_with($storagePath, 'storage/')) {
        $storagePath = substr($storagePath, strlen('storage/'));
    }

    $storagePath = str_replace('\\', '/', ltrim($storagePath, '/'));
    return sr_storage_key_is_safe($storagePath) ? $storagePath : '';
}
