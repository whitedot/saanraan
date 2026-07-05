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

function sr_community_body_file_storage_driver(string $driver): string
{
    $driver = strtolower(trim($driver));

    return in_array($driver, ['local', 's3'], true) ? $driver : 'local';
}

function sr_community_body_file_driver_query(string $driver): string
{
    $driver = sr_community_body_file_storage_driver($driver);

    return $driver === 'local' ? '' : '&d=' . rawurlencode($driver);
}

function sr_community_body_file_tmp_proxy_url(string $token, string $fileName, string $driver = 'local'): string
{
    return sr_url('/community/body-file?tmp=' . rawurlencode($token) . '&file=' . rawurlencode($fileName) . sr_community_body_file_driver_query($driver));
}

function sr_community_body_file_post_proxy_url(int $postId, string $fileName, string $driver = 'local'): string
{
    return sr_url('/community/body-file?post_id=' . rawurlencode((string) $postId) . '&file=' . rawurlencode($fileName) . sr_community_body_file_driver_query($driver));
}

function sr_community_body_file_thumbnail_url_from_ref(array $ref): string
{
    $type = (string) ($ref['type'] ?? '');
    $fileName = sr_community_body_file_clean_name((string) ($ref['file'] ?? ''));
    $driver = sr_community_body_file_storage_driver((string) ($ref['driver'] ?? 'local'));
    if ($fileName === '') {
        return '';
    }

    if ($type === 'tmp') {
        $token = (string) ($ref['token'] ?? '');
        if (preg_match('/\A[a-f0-9]{32}\z/', $token) !== 1) {
            return '';
        }
        return sr_community_body_file_tmp_proxy_url($token, $fileName, $driver) . '&thumb=1';
    }

    if ($type === 'post') {
        $postId = (int) ($ref['post_id'] ?? 0);
        if ($postId < 1) {
            return '';
        }
        return sr_community_body_file_post_proxy_url($postId, $fileName, $driver) . '&thumb=1';
    }

    return '';
}

function sr_community_body_file_thumbnail_html(string $html): string
{
    if ($html === '' || !class_exists('DOMDocument')) {
        return $html;
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML('<?xml encoding="UTF-8"><div id="sr-community-body-thumbnail-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return $html;
    }

    $changed = false;
    foreach ($document->getElementsByTagName('img') as $image) {
        if (!$image instanceof DOMElement) {
            continue;
        }

        $originalSrc = $image->getAttribute('src');
        $ref = sr_community_body_file_ref_from_url($originalSrc);
        if ($ref === null) {
            continue;
        }

        $thumbnailUrl = sr_community_body_file_thumbnail_url_from_ref($ref);
        if ($thumbnailUrl === '') {
            continue;
        }

        $hasLinkAncestor = false;
        for ($parent = $image->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode) {
            if (strtolower($parent->tagName) === 'a') {
                $hasLinkAncestor = true;
                break;
            }
        }

        $image->setAttribute('data-sr-original-src', $originalSrc);
        if (!$hasLinkAncestor) {
            $image->setAttribute('data-community-image-layer-body', '1');
            $image->setAttribute('role', 'button');
            $image->setAttribute('aria-label', '이미지 확대 보기');
            if (!$image->hasAttribute('tabindex')) {
                $image->setAttribute('tabindex', '0');
            }
        }
        $image->setAttribute('src', $thumbnailUrl);
        if (!$image->hasAttribute('loading')) {
            $image->setAttribute('loading', 'lazy');
        }
        $changed = true;
    }

    if (!$changed) {
        return $html;
    }

    $root = $document->getElementById('sr-community-body-thumbnail-root');
    if (!$root instanceof DOMElement) {
        return $html;
    }

    $output = '';
    foreach ($root->childNodes as $child) {
        $output .= $document->saveHTML($child);
    }

    return $output !== '' ? $output : $html;
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

    $storageDriver = sr_storage_default_driver();
    sr_storage_put_file((string) $validated['tmp_name'], $storageKey, [
        'driver' => $storageDriver,
        'content_type' => $storedMimeType,
    ]);

    return [
        'url' => sr_community_body_file_tmp_proxy_url($token, $storedName, $storageDriver),
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
        return preg_match('/\A[a-f0-9]{32}\z/', $tmpToken) === 1 ? [
            'type' => 'tmp',
            'token' => $tmpToken,
            'file' => $fileName,
            'driver' => sr_community_body_file_storage_driver((string) ($params['d'] ?? 'local')),
        ] : null;
    }
    $postId = (int) ($params['post_id'] ?? 0);
    return $postId > 0 ? [
        'type' => 'post',
        'post_id' => $postId,
        'file' => $fileName,
        'driver' => sr_community_body_file_storage_driver((string) ($params['d'] ?? 'local')),
    ] : null;
}

function sr_community_finalize_body_files(PDO $pdo, int $postId, string $html, int $accountId, bool $cleanupUnreferenced = true, ?array &$createdFiles = null, ?array &$finalizedTmpFiles = null): string
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
        $driver = sr_community_body_file_storage_driver((string) ($ref['driver'] ?? 'local'));
        $sourceKey = sr_community_body_file_tmp_key($token, $fileName);
        $targetKey = sr_community_body_file_post_key($postId, $fileName);
        if ($sourceKey === '' || $targetKey === '') {
            continue;
        }
        try {
            sr_storage_copy($driver, $sourceKey, $targetKey, ['overwrite' => true]);
        } catch (Throwable $exception) {
            sr_community_record_storage_cleanup_failure($pdo, 'body_file_finalize', $postId, $driver, $sourceKey, $exception->getMessage());
            throw new RuntimeException('본문 이미지 저장을 완료할 수 없습니다.');
        }
        if (is_array($createdFiles)) {
            $createdFiles[] = ['driver' => $driver, 'key' => $targetKey];
        }
        if (is_array($finalizedTmpFiles)) {
            $finalizedTmpFiles[] = ['driver' => $driver, 'key' => $sourceKey];
        }
        $oldUrl = sr_community_body_file_tmp_proxy_url($token, $fileName, $driver);
        $newUrl = sr_community_body_file_post_proxy_url($postId, $fileName, $driver);
        $replacements[$oldUrl] = $newUrl;
        $replacements[sr_e($oldUrl)] = sr_e($newUrl);
        if ($driver === 'local') {
            $legacyOldUrl = sr_community_body_file_tmp_proxy_url($token, $fileName);
            $legacyNewUrl = sr_community_body_file_post_proxy_url($postId, $fileName);
            $replacements[$legacyOldUrl] = $legacyNewUrl;
            $replacements[sr_e($legacyOldUrl)] = sr_e($legacyNewUrl);
        }
    }

    if ($replacements !== []) {
        $html = strtr($html, $replacements);
    }
    if ($cleanupUnreferenced) {
        sr_community_cleanup_unreferenced_body_files($pdo, $postId, $html);
    }
    return $html;
}

function sr_community_clone_body_files(PDO $pdo, int $sourcePostId, int $targetPostId, string $html, ?array &$createdFiles = null): string
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
        $driver = sr_community_body_file_storage_driver((string) ($ref['driver'] ?? 'local'));
        try {
            sr_storage_copy($driver, $sourceKey, $targetKey, ['overwrite' => true]);
        } catch (Throwable $exception) {
            sr_community_record_storage_cleanup_failure($pdo, 'body_file_clone', $targetPostId, $driver, $sourceKey, $exception->getMessage());
            throw new RuntimeException('게시글 본문 이미지 복사를 완료할 수 없습니다.');
        }
        if (is_array($createdFiles)) {
            $createdFiles[] = ['driver' => $driver, 'key' => $targetKey];
        }
        $oldUrl = sr_community_body_file_post_proxy_url($sourcePostId, $fileName, $driver);
        $newUrl = sr_community_body_file_post_proxy_url($targetPostId, $fileName, $driver);
        $replacements[$oldUrl] = $newUrl;
        $replacements[sr_e($oldUrl)] = sr_e($newUrl);
        if ($driver === 'local') {
            $legacyOldUrl = sr_community_body_file_post_proxy_url($sourcePostId, $fileName);
            $legacyNewUrl = sr_community_body_file_post_proxy_url($targetPostId, $fileName);
            $replacements[$legacyOldUrl] = $legacyNewUrl;
            $replacements[sr_e($legacyOldUrl)] = sr_e($legacyNewUrl);
        }
    }

    return $replacements === [] ? $html : strtr($html, $replacements);
}

function sr_community_cleanup_storage_file_refs(PDO $pdo, array $files, string $sourceType, int $sourceId, string $errorMessage): void
{
    $seen = [];
    foreach ($files as $file) {
        if (!is_array($file)) {
            continue;
        }
        $driver = (string) ($file['driver'] ?? '');
        $key = (string) ($file['key'] ?? '');
        if ($driver === '' || $key === '') {
            continue;
        }
        $dedupeKey = $driver . ':' . $key;
        if (isset($seen[$dedupeKey])) {
            continue;
        }
        $seen[$dedupeKey] = true;
        if (!sr_storage_delete($driver, $key)) {
            sr_community_record_storage_cleanup_failure($pdo, $sourceType, $sourceId, $driver, $key, $errorMessage);
        } else {
            sr_thumbnail_delete_variants([
                'storage_driver' => $driver,
                'storage_key' => $key,
            ]);
        }
    }
}

function sr_community_cleanup_unreferenced_body_files(PDO $pdo, int $postId, string $html, string $previousHtml = ''): void
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

    if ($previousHtml !== '') {
        $seen = [];
        foreach (sr_community_body_file_refs_from_html($previousHtml) as $ref) {
            if ((string) ($ref['type'] ?? '') !== 'post' || (int) ($ref['post_id'] ?? 0) !== $postId) {
                continue;
            }
            $fileName = (string) ($ref['file'] ?? '');
            if ($fileName === '' || isset($kept[$fileName])) {
                continue;
            }
            $driver = sr_community_body_file_storage_driver((string) ($ref['driver'] ?? 'local'));
            $key = sr_community_body_file_post_key($postId, $fileName);
            $dedupeKey = $driver . ':' . $key;
            if ($key === '' || isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;
            if (!sr_storage_delete($driver, $key)) {
                sr_community_record_storage_cleanup_failure($pdo, 'body_file_unreferenced', $postId, $driver, $key, '본문에서 제거된 이미지 저장소 정리에 실패했습니다.');
            } else {
                sr_thumbnail_delete_variants([
                    'storage_driver' => $driver,
                    'storage_key' => $key,
                ]);
            }
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
        } elseif ($key !== '') {
            sr_thumbnail_delete_variants([
                'storage_driver' => 'local',
                'storage_key' => $key,
            ]);
        }
    }
}

function sr_community_cleanup_body_file_refs_for_deleted_post(PDO $pdo, int $postId, string $bodyText): int
{
    if ($postId < 1 || $bodyText === '') {
        return 0;
    }

    $deleted = 0;
    $seen = [];
    foreach (sr_community_body_file_refs_from_html($bodyText) as $ref) {
        if ((string) ($ref['type'] ?? '') !== 'post' || (int) ($ref['post_id'] ?? 0) !== $postId) {
            continue;
        }
        $driver = sr_community_body_file_storage_driver((string) ($ref['driver'] ?? 'local'));
        $key = sr_community_body_file_post_key($postId, (string) ($ref['file'] ?? ''));
        $dedupeKey = $driver . ':' . $key;
        if ($key === '' || isset($seen[$dedupeKey])) {
            continue;
        }
        $seen[$dedupeKey] = true;
        if (sr_storage_delete($driver, $key)) {
            sr_thumbnail_delete_variants([
                'storage_driver' => $driver,
                'storage_key' => $key,
            ]);
            $deleted++;
        } else {
            sr_community_record_storage_cleanup_failure($pdo, 'body_file_post_delete', $postId, $driver, $key, '게시글 삭제 후 본문 이미지 저장소 정리에 실패했습니다.');
        }
    }

    return $deleted;
}

function sr_community_cleanup_body_files_for_deleted_posts(PDO $pdo, array $postIds): int
{
    $deleted = 0;
    foreach (array_values(array_filter(array_map('intval', $postIds), static fn (int $postId): bool => $postId > 0)) as $postId) {
        $bodyText = '';
        try {
            $stmt = $pdo->prepare('SELECT body_text FROM sr_community_posts WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $postId]);
            $bodyText = (string) ($stmt->fetchColumn() ?: '');
        } catch (Throwable) {
            $bodyText = '';
        }
        $deleted += sr_community_cleanup_body_file_refs_for_deleted_post($pdo, $postId, $bodyText);

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
                sr_thumbnail_delete_variants([
                    'storage_driver' => 'local',
                    'storage_key' => $key,
                ]);
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
                sr_thumbnail_delete_variants([
                    'storage_driver' => 'local',
                    'storage_key' => $key,
                ]);
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

function sr_community_send_body_file(PDO $pdo, int $postId, string $fileName, string $tmpToken = '', string $driver = 'local', bool $thumbnail = false): void
{
    $driver = sr_community_body_file_storage_driver($driver);
    if ($tmpToken !== '') {
        $account = sr_member_current_account($pdo);
        if (!is_array($account) || !sr_community_body_file_token_is_valid($tmpToken)) {
            sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
        }
        $key = sr_community_body_file_tmp_key($tmpToken, $fileName);
    } else {
        $account = sr_member_current_account($pdo);
        $post = sr_community_post_for_read($pdo, $postId, is_array($account) ? $account : null);
        if (!is_array($post) || !sr_community_account_can_view_post_body($pdo, $post, is_array($account) ? $account : null)) {
            sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
        }
        $key = sr_community_body_file_post_key($postId, $fileName);
    }

    if ($key === '' || !sr_storage_key_is_safe($key)) {
        sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
    }
    $head = sr_storage_head($driver, $key);
    $mimeType = (string) ($head['content_type'] ?? '');
    if (!is_array($head) || !in_array($mimeType, sr_community_body_file_allowed_mime_types(), true)) {
        sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
    }
    if ($thumbnail) {
        $thumbnailSource = [
            'module_key' => 'community_body',
            'storage_driver' => $driver,
            'storage_key' => $key,
            'mime_type' => $mimeType,
            'size_bytes' => (int) ($head['content_length'] ?? 0),
        ];
        if ($driver === 'local') {
            $sourcePath = sr_storage_local_path($key);
            if (is_string($sourcePath)) {
                $thumbnailSource['source_path'] = $sourcePath;
            }
        }

        $thumbnailFile = sr_thumbnail_protected_file($thumbnailSource, [
            'width' => 1280,
            'height' => 1280,
            'mode' => 'contain',
            'quality' => 82,
            'format' => 'source',
            'preserve_aspect' => true,
            'max_source_bytes' => sr_community_body_file_upload_max_bytes(),
        ]);
        if (is_array($thumbnailFile) && is_string($thumbnailFile['path'] ?? null)) {
            sr_send_file_headers((string) $thumbnailFile['content_type'], (int) ($thumbnailFile['content_length'] ?? 0), 'private, max-age=300');
            readfile((string) $thumbnailFile['path']);
            sr_finish_response();
        }
    }
    if ($driver === 's3') {
        $url = sr_storage_signed_url('s3', $key, 300, [
            'response-content-type' => sr_download_content_type($mimeType),
        ]);
        if ($url === '') {
            sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
        }
        sr_redirect_trusted_external($url);
    }

    $path = sr_storage_local_path($key);
    if (!is_string($path)) {
        sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
    }
    sr_send_file_headers($mimeType, (int) ($head['content_length'] ?? 0), 'private, max-age=300');
    readfile($path);
    sr_finish_response();
}
