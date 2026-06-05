<?php

declare(strict_types=1);

function sr_content_body_file_allowed_extensions(): array
{
    return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
}

function sr_content_body_file_allowed_mime_types(): array
{
    return ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
}

function sr_content_body_file_upload_max_bytes(): int
{
    return 5 * 1024 * 1024;
}

function sr_content_body_file_temporary_ttl_seconds(): int
{
    return 86400;
}

function sr_content_body_file_upload_url(): string
{
    return sr_url('/admin/content/body-files/upload');
}

function sr_content_body_file_proxy_path(int $fileId): string
{
    return '/content/body-file?id=' . rawurlencode((string) $fileId);
}

function sr_content_body_file_proxy_url(int $fileId): string
{
    return sr_url(sr_content_body_file_proxy_path($fileId));
}

function sr_content_body_file_by_id(PDO $pdo, int $fileId): ?array
{
    if ($fileId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_content_body_files WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $fileId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_body_file_public_by_id(PDO $pdo, int $fileId): ?array
{
    if ($fileId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT f.*, c.slug, c.title AS content_title, c.status AS content_status,
                c.asset_access_enabled, c.asset_module, c.asset_access_amount,
                c.asset_access_amounts_json, c.asset_access_group_policies_json,
                c.asset_access_policy_set_id, c.asset_charge_policy
         FROM sr_content_body_files f
         INNER JOIN sr_content_body_file_refs r ON r.file_id = f.id AND r.status = 'active'
         INNER JOIN sr_content_items c ON c.id = r.content_id
         WHERE f.id = :id
           AND f.status = 'attached'
         ORDER BY c.status = 'published' DESC, c.id ASC
         LIMIT 1"
    );
    $stmt->execute(['id' => $fileId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_upload_body_file(PDO $pdo, int $accountId, array $file): array
{
    $validated = sr_upload_validate_file($file, [
        'max_bytes' => sr_content_body_file_upload_max_bytes(),
        'allowed_extensions' => sr_content_body_file_allowed_extensions(),
        'allowed_mime_types' => sr_content_body_file_allowed_mime_types(),
    ]);

    $imageSize = @getimagesize((string) $validated['tmp_name']);
    if (!is_array($imageSize)) {
        throw new RuntimeException('본문 이미지를 확인할 수 없습니다.');
    }

    $storedName = sr_upload_random_filename((string) $validated['extension']);
    $storedMimeType = sr_upload_detect_mime((string) $validated['tmp_name']);
    $sizeBytes = filesize((string) $validated['tmp_name']);
    if (!in_array($storedMimeType, sr_content_body_file_allowed_mime_types(), true) || !is_int($sizeBytes)) {
        throw new RuntimeException('저장된 본문 이미지 metadata를 확인할 수 없습니다.');
    }

    $storageKey = 'content/body/' . date('Y/m') . '/' . $storedName;
    $stored = sr_storage_put_file((string) $validated['tmp_name'], $storageKey, [
        'content_type' => $storedMimeType,
    ]);

    try {
        $now = sr_now();
        $expiresAt = date('Y-m-d H:i:s', time() + sr_content_body_file_temporary_ttl_seconds());
        $stmt = $pdo->prepare(
            "INSERT INTO sr_content_body_files
                (content_id, uploader_account_id, original_name, stored_name, storage_path, storage_driver, storage_key, public_url, mime_type, size_bytes, checksum_sha256, width, height, status, expires_at, created_at, updated_at)
             VALUES
                (0, :uploader_account_id, :original_name, :stored_name, :storage_path, :storage_driver, :storage_key, '', :mime_type, :size_bytes, :checksum_sha256, :width, :height, 'temporary', :expires_at, :created_at, :updated_at)"
        );
        $stmt->execute([
            'uploader_account_id' => $accountId,
            'original_name' => (string) $validated['original_name'],
            'stored_name' => $storedName,
            'storage_path' => (string) ($stored['path'] ?? ''),
            'storage_driver' => (string) $stored['driver'],
            'storage_key' => $storageKey,
            'mime_type' => $storedMimeType,
            'size_bytes' => $sizeBytes,
            'checksum_sha256' => (string) $validated['checksum'],
            'width' => (int) ($imageSize[0] ?? 0),
            'height' => (int) ($imageSize[1] ?? 0),
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $fileId = (int) $pdo->lastInsertId();
        $publicUrl = sr_content_body_file_proxy_url($fileId);
        $pdo->prepare('UPDATE sr_content_body_files SET public_url = :public_url WHERE id = :id')->execute([
            'public_url' => $publicUrl,
            'id' => $fileId,
        ]);

        return [
            'id' => $fileId,
            'url' => $publicUrl,
            'storage_driver' => (string) $stored['driver'],
            'storage_key' => $storageKey,
            'width' => (int) ($imageSize[0] ?? 0),
            'height' => (int) ($imageSize[1] ?? 0),
        ];
    } catch (Throwable $exception) {
        if (!sr_storage_delete((string) $stored['driver'], $storageKey)) {
            sr_content_record_storage_cleanup_failure($pdo, 'body_file_upload_rollback', 0, (string) $stored['driver'], $storageKey, '본문 이미지 DB 기록 실패 후 저장소 정리에 실패했습니다.');
        }
        throw $exception;
    }
}

function sr_content_body_file_ids_from_html(string $html): array
{
    if ($html === '' || !class_exists('DOMDocument')) {
        return [];
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML('<?xml encoding="UTF-8"><div id="sr-content-body-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return [];
    }

    $ids = [];
    foreach ($document->getElementsByTagName('img') as $image) {
        if (!$image instanceof DOMElement) {
            continue;
        }
        $id = sr_content_body_file_id_from_url($image->getAttribute('src'));
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}

function sr_content_body_file_id_from_url(string $url): int
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    if ($url === '') {
        return 0;
    }

    $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
    $query = (string) (parse_url($url, PHP_URL_QUERY) ?? '');
    if ($path === '' && str_starts_with($url, '/')) {
        $parts = explode('?', $url, 2);
        $path = $parts[0];
        $query = $parts[1] ?? '';
    }

    $basePath = sr_base_path();
    if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
        $path = substr($path, strlen($basePath));
    }
    if ($path !== '/content/body-file') {
        return 0;
    }

    parse_str($query, $params);
    $id = (int) ($params['id'] ?? 0);

    return $id > 0 ? $id : 0;
}

function sr_content_reconcile_body_files(PDO $pdo, int $contentId, string $html, int $accountId, string $now): void
{
    if ($contentId < 1) {
        return;
    }

    $fileIds = sr_content_body_file_ids_from_html($html);
    $active = [];
    if ($fileIds !== []) {
        $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT id
             FROM sr_content_body_files
             WHERE id IN (" . $placeholders . ")
               AND status IN ('temporary', 'attached', 'orphan_candidate')"
        );
        $stmt->execute($fileIds);
        foreach ($stmt->fetchAll() as $row) {
            $active[(int) ($row['id'] ?? 0)] = true;
        }
    }

    $stmt = $pdo->prepare(
        "UPDATE sr_content_body_file_refs
         SET status = 'inactive', updated_at = :updated_at
         WHERE content_id = :content_id
           AND slot_key = 'body'"
    );
    $stmt->execute([
        'updated_at' => $now,
        'content_id' => $contentId,
    ]);

    if ($active !== []) {
        $stmt = $pdo->prepare(
            "INSERT INTO sr_content_body_file_refs
                (content_id, file_id, slot_key, status, created_at, updated_at)
             VALUES
                (:content_id, :file_id, 'body', 'active', :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                status = 'active',
                updated_at = VALUES(updated_at)"
        );
        foreach (array_keys($active) as $fileId) {
            $stmt->execute([
                'content_id' => $contentId,
                'file_id' => (int) $fileId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $placeholders = implode(',', array_fill(0, count($active), '?'));
        $params = array_merge([$contentId, $accountId, $now, $now], array_keys($active));
        $pdo->prepare(
            "UPDATE sr_content_body_files
             SET content_id = CASE WHEN content_id = 0 THEN ? ELSE content_id END,
                 uploader_account_id = CASE WHEN uploader_account_id = 0 THEN ? ELSE uploader_account_id END,
                 status = 'attached',
                 attached_at = COALESCE(attached_at, ?),
                 expires_at = NULL,
                 updated_at = ?
             WHERE id IN (" . $placeholders . ")"
        )->execute($params);
    }

    sr_content_mark_unreferenced_body_files($pdo, $contentId, $now);
}

function sr_content_mark_unreferenced_body_files(PDO $pdo, int $contentId, string $now): void
{
    $stmt = $pdo->prepare(
        "UPDATE sr_content_body_files f
         SET f.status = 'orphan_candidate',
             f.expires_at = COALESCE(f.expires_at, :expires_at),
             f.updated_at = :updated_at
         WHERE f.content_id = :content_id
           AND f.status = 'attached'
           AND NOT EXISTS (
               SELECT 1
               FROM sr_content_body_file_refs r
               WHERE r.file_id = f.id
                 AND r.status = 'active'
           )"
    );
    $stmt->execute([
        'expires_at' => date('Y-m-d H:i:s', time() + sr_content_body_file_temporary_ttl_seconds()),
        'updated_at' => $now,
        'content_id' => $contentId,
    ]);
}

function sr_content_can_access_body_file(PDO $pdo, array $file, ?array $account): bool
{
    if ((string) ($file['content_status'] ?? '') !== 'published') {
        return is_array($account) && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/content', 'view');
    }

    if (!sr_content_asset_access_required($file)) {
        return true;
    }

    if (!is_array($account)) {
        return false;
    }

    $access = sr_content_charge_view_access($pdo, $file, (int) $account['id'], false);
    return !empty($access['allowed']);
}

function sr_content_send_body_file(PDO $pdo, int $fileId): void
{
    $file = sr_content_body_file_public_by_id($pdo, $fileId);
    $account = function_exists('sr_member_current_account') ? sr_member_current_account($pdo) : null;
    if (!is_array($file)) {
        $temporaryFile = sr_content_body_file_by_id($pdo, $fileId);
        if (
            is_array($temporaryFile)
            && in_array((string) ($temporaryFile['status'] ?? ''), ['temporary', 'orphan_candidate'], true)
            && is_array($account)
            && (int) ($temporaryFile['uploader_account_id'] ?? 0) === (int) ($account['id'] ?? 0)
            && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/content', 'edit')
        ) {
            $file = $temporaryFile;
        } else {
            sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
        }
    }

    if (isset($file['content_status']) && !sr_content_can_access_body_file($pdo, $file, is_array($account) ? $account : null)) {
        sr_render_error(403, '본문 이미지에 접근할 수 없습니다.');
    }

    $driver = strtolower((string) ($file['storage_driver'] ?? 'local'));
    $key = (string) ($file['storage_key'] ?? '');
    $mimeType = (string) ($file['mime_type'] ?? '');
    $recordedSize = (int) ($file['size_bytes'] ?? 0);
    $recordedChecksum = (string) ($file['checksum_sha256'] ?? '');
    if (!in_array($mimeType, sr_content_body_file_allowed_mime_types(), true) || !sr_storage_key_is_safe($key)) {
        sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
    }

    $head = sr_storage_head($driver, $key);
    if (!is_array($head) || $recordedSize < 1 || (int) ($head['content_length'] ?? 0) !== $recordedSize) {
        sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
    }
    $actualChecksum = (string) (($head['metadata']['sha256'] ?? '') ?: '');
    if (preg_match('/\A[a-f0-9]{64}\z/', $recordedChecksum) !== 1 || $actualChecksum === '' || !hash_equals($recordedChecksum, $actualChecksum)) {
        sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
    }

    if ($driver === 's3') {
        $url = sr_storage_signed_url('s3', $key, 300, [
            'response-content-type' => $mimeType,
        ]);
        if ($url === '') {
            sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
        }
        header('Cache-Control: private, max-age=300');
        sr_redirect_external($url);
    }

    $path = sr_storage_local_path($key);
    if (!is_string($path)) {
        sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string) $recordedSize);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=300');
    readfile($path);
    sr_finish_response();
}

function sr_content_body_file_rows_for_content_delete(PDO $pdo, array $contentIds): array
{
    $contentIds = array_values(array_filter(array_map('intval', $contentIds), static fn (int $contentId): bool => $contentId > 0));
    if ($contentIds === [] || !sr_content_optional_table_exists($pdo, 'sr_content_body_files')) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($contentIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT DISTINCT f.*
         FROM sr_content_body_files f
         WHERE (
                f.content_id IN (" . $placeholders . ")
                OR EXISTS (
                    SELECT 1
                    FROM sr_content_body_file_refs r
                    WHERE r.file_id = f.id
                      AND r.content_id IN (" . $placeholders . ")
                )
           )
           AND NOT EXISTS (
                SELECT 1
                FROM sr_content_body_file_refs outside_ref
                WHERE outside_ref.file_id = f.id
                  AND outside_ref.status = 'active'
                  AND outside_ref.content_id NOT IN (" . $placeholders . ")
           )"
    );
    $stmt->execute(array_merge($contentIds, $contentIds, $contentIds));

    return $stmt->fetchAll();
}

function sr_content_cleanup_expired_body_files(PDO $pdo, int $limit = 10): array
{
    $limit = max(1, min(50, $limit));
    $now = sr_now();
    $stmt = $pdo->prepare(
        "SELECT *
         FROM sr_content_body_files
         WHERE status IN ('temporary', 'orphan_candidate', 'delete_pending', 'delete_failed')
           AND (expires_at IS NULL OR expires_at <= :now_value)
         ORDER BY updated_at ASC, id ASC
         LIMIT :limit_value"
    );
    $stmt->bindValue('now_value', $now);
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $deleted = 0;
    $failed = 0;
    foreach ($stmt->fetchAll() as $file) {
        $fileId = (int) ($file['id'] ?? 0);
        $driver = strtolower((string) ($file['storage_driver'] ?? 'local'));
        $driver = in_array($driver, ['local', 's3'], true) ? $driver : 'local';
        $key = (string) ($file['storage_key'] ?? '');
        if ($fileId < 1 || !sr_storage_key_is_safe($key)) {
            continue;
        }

        $pdo->prepare(
            "UPDATE sr_content_body_files
             SET status = 'delete_pending',
                 attempt_count = attempt_count + 1,
                 last_attempted_at = :last_attempted_at,
                 updated_at = :updated_at
             WHERE id = :id
               AND status IN ('temporary', 'orphan_candidate', 'delete_pending', 'delete_failed')"
        )->execute([
            'last_attempted_at' => $now,
            'updated_at' => $now,
            'id' => $fileId,
        ]);

        if (sr_storage_delete($driver, $key)) {
            $pdo->prepare(
                "UPDATE sr_content_body_files
                 SET status = 'deleted',
                     last_error = NULL,
                     updated_at = :updated_at
                 WHERE id = :id"
            )->execute([
                'updated_at' => $now,
                'id' => $fileId,
            ]);
            $deleted++;
            continue;
        }

        $failed++;
        $error = '본문 이미지 저장소 정리에 실패했습니다.';
        $pdo->prepare(
            "UPDATE sr_content_body_files
             SET status = 'delete_failed',
                 last_error = :last_error,
                 last_attempted_at = :last_attempted_at,
                 updated_at = :updated_at
             WHERE id = :id"
        )->execute([
            'last_error' => $error,
            'last_attempted_at' => $now,
            'updated_at' => $now,
            'id' => $fileId,
        ]);
        sr_content_record_storage_cleanup_failure($pdo, 'body_file_cleanup', $fileId, $driver, $key, $error);
    }

    return [
        'deleted' => $deleted,
        'failed' => $failed,
    ];
}
