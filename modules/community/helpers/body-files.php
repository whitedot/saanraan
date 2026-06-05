<?php

declare(strict_types=1);

function sr_community_body_file_allowed_extensions(): array
{
    return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
}

function sr_community_body_file_allowed_mime_types(): array
{
    return ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
}

function sr_community_body_file_upload_max_bytes(): int
{
    return 5 * 1024 * 1024;
}

function sr_community_body_file_temporary_ttl_seconds(): int
{
    return 86400;
}

function sr_community_body_file_upload_url(array $board, int $postId = 0): string
{
    $url = '/community/body-files/upload?board_key=' . rawurlencode((string) ($board['board_key'] ?? ''));
    if ($postId > 0) {
        $url .= '&post_id=' . rawurlencode((string) $postId);
    }

    return sr_url($url);
}

function sr_community_body_file_upload_token(): string
{
    if (empty($_SESSION['sr_community_body_upload_token']) || !is_string($_SESSION['sr_community_body_upload_token'])) {
        $_SESSION['sr_community_body_upload_token'] = bin2hex(random_bytes(16));
    }

    return (string) $_SESSION['sr_community_body_upload_token'];
}

function sr_community_body_file_token_is_valid(string $token): bool
{
    return preg_match('/\A[a-f0-9]{32}\z/', $token) === 1
        && is_string($_SESSION['sr_community_body_upload_token'] ?? null)
        && hash_equals((string) $_SESSION['sr_community_body_upload_token'], $token);
}

function sr_community_body_file_clean_name(string $name): string
{
    $name = basename($name);
    return preg_match('/\A[A-Za-z0-9._=-]{1,160}\z/', $name) === 1 ? $name : '';
}

function sr_community_body_file_post_shard(int $postId): string
{
    $postIdText = str_pad((string) max(0, $postId), 6, '0', STR_PAD_LEFT);
    return substr($postIdText, -6, 3) . '/' . substr($postIdText, -3);
}

function sr_community_body_file_tmp_key(string $token, string $fileName): string
{
    if (!sr_community_body_file_token_is_valid($token)) {
        return '';
    }

    $fileName = sr_community_body_file_clean_name($fileName);
    return $fileName !== '' ? 'community/body/tmp/' . $token . '/' . $fileName : '';
}

function sr_community_body_file_post_key(int $postId, string $fileName): string
{
    $fileName = sr_community_body_file_clean_name($fileName);
    return $postId > 0 && $fileName !== '' ? 'community/body/' . sr_community_body_file_post_shard($postId) . '/' . $postId . '/' . $fileName : '';
}

function sr_community_body_file_tmp_proxy_url(string $token, string $fileName): string
{
    return sr_url('/community/body-file?tmp=' . rawurlencode($token) . '&file=' . rawurlencode($fileName));
}

function sr_community_body_file_post_proxy_url(int $postId, string $fileName): string
{
    return sr_url('/community/body-file?post_id=' . rawurlencode((string) $postId) . '&file=' . rawurlencode($fileName));
}

function sr_community_upload_body_file(PDO $pdo, int $accountId, array $board, array $file, string $token, ?array $post = null): array
{
    if (!sr_community_body_file_token_is_valid($token)) {
        throw new RuntimeException('본문 이미지 업로드 토큰이 올바르지 않습니다.');
    }
    $isAdminWriter = function_exists('sr_admin_has_permission') && sr_admin_has_permission($pdo, $accountId, '/admin/community/posts', 'edit');
    if (is_array($post)) {
        if ((int) ($post['board_id'] ?? 0) !== (int) ($board['id'] ?? 0)
            || (!$isAdminWriter && !sr_community_account_can_edit_post($post, ['id' => $accountId]))
        ) {
            throw new RuntimeException('게시글 편집 권한을 확인할 수 없습니다.');
        }
    } elseif (!sr_community_account_can_write_board($pdo, $board, ['id' => $accountId], $isAdminWriter)) {
        throw new RuntimeException('게시글 작성 권한을 확인할 수 없습니다.');
    }

    $validated = sr_upload_validate_file($file, [
        'max_bytes' => sr_community_body_file_upload_max_bytes(),
        'allowed_extensions' => sr_community_body_file_allowed_extensions(),
        'allowed_mime_types' => sr_community_body_file_allowed_mime_types(),
    ]);

    $imageSize = @getimagesize((string) $validated['tmp_name']);
    if (!is_array($imageSize)) {
        throw new RuntimeException('본문 이미지를 확인할 수 없습니다.');
    }

    $storedName = sr_upload_random_filename((string) $validated['extension']);
    $storedMimeType = sr_upload_detect_mime((string) $validated['tmp_name']);
    if (!in_array($storedMimeType, sr_community_body_file_allowed_mime_types(), true)) {
        throw new RuntimeException('저장된 본문 이미지 metadata를 확인할 수 없습니다.');
    }

    $storageKey = sr_community_body_file_tmp_key($token, $storedName);
    if ($storageKey === '') {
        throw new RuntimeException('본문 이미지 저장 key가 올바르지 않습니다.');
    }

    sr_storage_put_file((string) $validated['tmp_name'], $storageKey, [
        'driver' => 'local',
        'content_type' => $storedMimeType,
    ]);

    return [
        'url' => sr_community_body_file_tmp_proxy_url($token, $storedName),
        'width' => (int) ($imageSize[0] ?? 0),
        'height' => (int) ($imageSize[1] ?? 0),
    ];
}

function sr_community_body_file_refs_from_html(string $html): array
{
    if ($html === '' || !class_exists('DOMDocument')) {
        return [];
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return [];
    }

    $refs = [];
    foreach ($document->getElementsByTagName('img') as $image) {
        if ($image instanceof DOMElement) {
            $ref = sr_community_body_file_ref_from_url($image->getAttribute('src'));
            if ($ref !== null) {
                $refs[] = $ref;
            }
        }
    }

    return $refs;
}

function sr_community_body_file_ref_from_url(string $url): ?array
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
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
    if ($path !== '/community/body-file') {
        return null;
    }

    parse_str($query, $params);
    $fileName = sr_community_body_file_clean_name((string) ($params['file'] ?? ''));
    if ($fileName === '') {
        return null;
    }
    $tmpToken = (string) ($params['tmp'] ?? '');
    if ($tmpToken !== '') {
        return preg_match('/\A[a-f0-9]{32}\z/', $tmpToken) === 1 ? ['type' => 'tmp', 'token' => $tmpToken, 'file' => $fileName] : null;
    }
    $postId = (int) ($params['post_id'] ?? 0);
    return $postId > 0 ? ['type' => 'post', 'post_id' => $postId, 'file' => $fileName] : null;
}

function sr_community_finalize_body_files(PDO $pdo, int $postId, string $html, int $accountId, bool $cleanupUnreferenced = true): string
{
    unset($accountId);
    if ($postId < 1 || $html === '') {
        return $html;
    }

    $replacements = [];
    foreach (sr_community_body_file_refs_from_html($html) as $ref) {
        if ((string) ($ref['type'] ?? '') !== 'tmp') {
            continue;
        }
        $token = (string) ($ref['token'] ?? '');
        $fileName = (string) ($ref['file'] ?? '');
        if (!sr_community_body_file_token_is_valid($token)) {
            continue;
        }
        $sourceKey = sr_community_body_file_tmp_key($token, $fileName);
        $targetKey = sr_community_body_file_post_key($postId, $fileName);
        if ($sourceKey === '' || $targetKey === '') {
            continue;
        }
        try {
            sr_storage_copy('local', $sourceKey, $targetKey, ['overwrite' => true]);
            sr_storage_delete('local', $sourceKey);
        } catch (Throwable $exception) {
            sr_community_record_storage_cleanup_failure($pdo, 'body_file_finalize', $postId, 'local', $sourceKey, $exception->getMessage());
            throw new RuntimeException('본문 이미지 저장을 완료할 수 없습니다.');
        }
        $oldUrl = sr_community_body_file_tmp_proxy_url($token, $fileName);
        $newUrl = sr_community_body_file_post_proxy_url($postId, $fileName);
        $replacements[$oldUrl] = $newUrl;
        $replacements[sr_e($oldUrl)] = sr_e($newUrl);
    }

    if ($replacements !== []) {
        $html = strtr($html, $replacements);
    }
    if ($cleanupUnreferenced) {
        sr_community_cleanup_unreferenced_body_files($pdo, $postId, $html);
    }
    return $html;
}

function sr_community_clone_body_files(PDO $pdo, int $sourcePostId, int $targetPostId, string $html): string
{
    if ($sourcePostId < 1 || $targetPostId < 1 || $sourcePostId === $targetPostId || $html === '') {
        return $html;
    }

    $replacements = [];
    foreach (sr_community_body_file_refs_from_html($html) as $ref) {
        if ((string) ($ref['type'] ?? '') !== 'post' || (int) ($ref['post_id'] ?? 0) !== $sourcePostId) {
            continue;
        }
        $fileName = (string) ($ref['file'] ?? '');
        $sourceKey = sr_community_body_file_post_key($sourcePostId, $fileName);
        $targetKey = sr_community_body_file_post_key($targetPostId, $fileName);
        if ($sourceKey === '' || $targetKey === '') {
            continue;
        }
        try {
            sr_storage_copy('local', $sourceKey, $targetKey, ['overwrite' => true]);
        } catch (Throwable $exception) {
            sr_community_record_storage_cleanup_failure($pdo, 'body_file_clone', $targetPostId, 'local', $sourceKey, $exception->getMessage());
            throw new RuntimeException('게시글 본문 이미지 복사를 완료할 수 없습니다.');
        }
        $oldUrl = sr_community_body_file_post_proxy_url($sourcePostId, $fileName);
        $newUrl = sr_community_body_file_post_proxy_url($targetPostId, $fileName);
        $replacements[$oldUrl] = $newUrl;
        $replacements[sr_e($oldUrl)] = sr_e($newUrl);
    }

    return $replacements === [] ? $html : strtr($html, $replacements);
}

function sr_community_cleanup_unreferenced_body_files(PDO $pdo, int $postId, string $html): void
{
    if ($postId < 1) {
        return;
    }

    $kept = [];
    foreach (sr_community_body_file_refs_from_html($html) as $ref) {
        if ((string) ($ref['type'] ?? '') === 'post' && (int) ($ref['post_id'] ?? 0) === $postId) {
            $kept[(string) $ref['file']] = true;
        }
    }

    $directory = SR_ROOT . '/storage/community/body/' . sr_community_body_file_post_shard($postId) . '/' . (string) $postId;
    if (!is_dir($directory)) {
        return;
    }

    foreach (scandir($directory) ?: [] as $entry) {
        if (!is_string($entry) || $entry === '.' || $entry === '..' || isset($kept[$entry])) {
            continue;
        }
        $key = sr_community_body_file_post_key($postId, $entry);
        if ($key !== '' && !sr_storage_delete('local', $key)) {
            sr_community_record_storage_cleanup_failure($pdo, 'body_file_unreferenced', $postId, 'local', $key, '본문에서 제거된 이미지 저장소 정리에 실패했습니다.');
        }
    }
}

function sr_community_cleanup_body_files_for_deleted_posts(PDO $pdo, array $postIds): int
{
    $deleted = 0;
    foreach (array_values(array_filter(array_map('intval', $postIds), static fn (int $postId): bool => $postId > 0)) as $postId) {
        $directory = SR_ROOT . '/storage/community/body/' . sr_community_body_file_post_shard($postId) . '/' . (string) $postId;
        if (!is_dir($directory)) {
            continue;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if (!is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }
            $key = sr_community_body_file_post_key($postId, $entry);
            if ($key !== '' && sr_storage_delete('local', $key)) {
                $deleted++;
            } elseif ($key !== '') {
                sr_community_record_storage_cleanup_failure($pdo, 'body_file_post_delete', $postId, 'local', $key, '게시글 삭제 후 본문 이미지 저장소 정리에 실패했습니다.');
            }
        }
        @rmdir($directory);
        @rmdir(dirname($directory));
    }

    return $deleted;
}

function sr_community_cleanup_expired_body_files(PDO $pdo, int $limit = 10): array
{
    $limit = max(1, min(50, $limit));
    $root = SR_ROOT . '/storage/community/body/tmp';
    if (!is_dir($root)) {
        return ['deleted' => 0, 'failed' => 0];
    }

    $deleted = 0;
    $failed = 0;
    $expiresBefore = time() - sr_community_body_file_temporary_ttl_seconds();
    foreach (scandir($root) ?: [] as $token) {
        if ($deleted + $failed >= $limit || !is_string($token) || preg_match('/\A[a-f0-9]{32}\z/', $token) !== 1) {
            continue;
        }
        $directory = $root . '/' . $token;
        if (!is_dir($directory) || filemtime($directory) > $expiresBefore) {
            continue;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if ($deleted + $failed >= $limit || !is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }
            $cleanEntry = sr_community_body_file_clean_name($entry);
            if ($cleanEntry === '') {
                continue;
            }
            $key = 'community/body/tmp/' . $token . '/' . $cleanEntry;
            if (sr_storage_delete('local', $key)) {
                $deleted++;
            } else {
                $failed++;
                sr_community_record_storage_cleanup_failure($pdo, 'body_file_tmp_cleanup', 0, 'local', $key, '만료된 임시 본문 이미지 정리에 실패했습니다.');
            }
        }
        @rmdir($directory);
    }

    return ['deleted' => $deleted, 'failed' => $failed];
}

function sr_community_send_body_file(PDO $pdo, int $postId, string $fileName, string $tmpToken = ''): void
{
    if ($tmpToken !== '') {
        $account = sr_member_current_account($pdo);
        if (!is_array($account) || !sr_community_body_file_token_is_valid($tmpToken)) {
            sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
        }
        $key = sr_community_body_file_tmp_key($tmpToken, $fileName);
    } else {
        $account = sr_member_current_account($pdo);
        $post = sr_community_post_for_read($pdo, $postId, is_array($account) ? $account : null);
        if (!is_array($post)) {
            sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
        }
        $key = sr_community_body_file_post_key($postId, $fileName);
    }

    if ($key === '' || !sr_storage_key_is_safe($key)) {
        sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
    }
    $head = sr_storage_head('local', $key);
    $mimeType = (string) ($head['content_type'] ?? '');
    if (!is_array($head) || !in_array($mimeType, sr_community_body_file_allowed_mime_types(), true)) {
        sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
    }
    $path = sr_storage_local_path($key);
    if (!is_string($path)) {
        sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . (string) (int) ($head['content_length'] ?? 0));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=300');
    readfile($path);
    sr_finish_response();
}
