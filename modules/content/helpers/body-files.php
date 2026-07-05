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

function sr_content_body_file_upload_token(): string
{
    if (empty($_SESSION['sr_content_body_upload_token']) || !is_string($_SESSION['sr_content_body_upload_token'])) {
        $_SESSION['sr_content_body_upload_token'] = bin2hex(random_bytes(16));
    }

    return (string) $_SESSION['sr_content_body_upload_token'];
}

function sr_content_body_file_token_is_valid(string $token): bool
{
    return preg_match('/\A[a-f0-9]{32}\z/', $token) === 1
        && is_string($_SESSION['sr_content_body_upload_token'] ?? null)
        && hash_equals((string) $_SESSION['sr_content_body_upload_token'], $token);
}

function sr_content_body_file_clean_name(string $name): string
{
    $name = basename($name);
    return preg_match('/\A[A-Za-z0-9._=-]{1,160}\z/', $name) === 1 ? $name : '';
}

function sr_content_body_file_tmp_key(string $token, string $fileName): string
{
    if (!sr_content_body_file_token_is_valid($token)) {
        return '';
    }

    $fileName = sr_content_body_file_clean_name($fileName);
    return $fileName !== '' ? 'content/body/tmp/' . $token . '/' . $fileName : '';
}

function sr_content_body_file_content_key(int $contentId, string $fileName): string
{
    $fileName = sr_content_body_file_clean_name($fileName);
    return $contentId > 0 && $fileName !== '' ? 'content/body/' . $contentId . '/' . $fileName : '';
}

function sr_content_body_file_tmp_proxy_url(string $token, string $fileName): string
{
    return sr_url('/content/body-file?tmp=' . rawurlencode($token) . '&file=' . rawurlencode($fileName));
}

function sr_content_body_file_content_proxy_url(int $contentId, string $fileName): string
{
    return sr_url('/content/body-file?content_id=' . rawurlencode((string) $contentId) . '&file=' . rawurlencode($fileName));
}

function sr_content_body_file_thumbnail_url_from_ref(array $ref): string
{
    $type = (string) ($ref['type'] ?? '');
    $fileName = sr_content_body_file_clean_name((string) ($ref['file'] ?? ''));
    if ($fileName === '') {
        return '';
    }

    if ($type === 'tmp') {
        $token = (string) ($ref['token'] ?? '');
        if (preg_match('/\A[a-f0-9]{32}\z/', $token) !== 1) {
            return '';
        }
        return sr_content_body_file_tmp_proxy_url($token, $fileName) . '&thumb=1';
    }

    if ($type === 'content') {
        $contentId = (int) ($ref['content_id'] ?? 0);
        if ($contentId < 1) {
            return '';
        }
        return sr_content_body_file_content_proxy_url($contentId, $fileName) . '&thumb=1';
    }

    return '';
}

function sr_content_body_file_thumbnail_html(string $html): string
{
    if ($html === '' || !class_exists('DOMDocument')) {
        return $html;
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML('<?xml encoding="UTF-8"><div id="sr-content-body-thumbnail-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
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
        $ref = sr_content_body_file_ref_from_url($originalSrc);
        if ($ref === null) {
            continue;
        }

        $thumbnailUrl = sr_content_body_file_thumbnail_url_from_ref($ref);
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
            $image->setAttribute('data-content-image-layer-body', '1');
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

    $root = $document->getElementById('sr-content-body-thumbnail-root');
    if (!$root instanceof DOMElement) {
        return $html;
    }

    $output = '';
    foreach ($root->childNodes as $child) {
        $output .= $document->saveHTML($child);
    }

    return $output !== '' ? $output : $html;
}

function sr_content_upload_body_file(PDO $pdo, int $accountId, array $file, string $token): array
{
    unset($pdo, $accountId);
    if (!sr_content_body_file_token_is_valid($token)) {
        throw new RuntimeException('본문 이미지 업로드 토큰이 올바르지 않습니다.');
    }

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
    if (!in_array($storedMimeType, sr_content_body_file_allowed_mime_types(), true)) {
        throw new RuntimeException('저장된 본문 이미지 metadata를 확인할 수 없습니다.');
    }

    $storageKey = sr_content_body_file_tmp_key($token, $storedName);
    if ($storageKey === '') {
        throw new RuntimeException('본문 이미지 저장 key가 올바르지 않습니다.');
    }

    $stored = sr_storage_put_file((string) $validated['tmp_name'], $storageKey, [
        'driver' => 'local',
        'content_type' => $storedMimeType,
    ]);

    return [
        'url' => sr_content_body_file_tmp_proxy_url($token, $storedName),
        'storage_driver' => (string) $stored['driver'],
        'storage_key' => $storageKey,
        'width' => (int) ($imageSize[0] ?? 0),
        'height' => (int) ($imageSize[1] ?? 0),
    ];
}

function sr_content_body_file_refs_from_html(string $html): array
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

    $refs = [];
    foreach ($document->getElementsByTagName('img') as $image) {
        if (!$image instanceof DOMElement) {
            continue;
        }
        $ref = sr_content_body_file_ref_from_url($image->getAttribute('src'));
        if ($ref !== null) {
            $refs[] = $ref;
        }
    }

    return $refs;
}

function sr_content_body_file_ref_from_url(string $url): ?array
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    if ($url === '') {
        return null;
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
        return null;
    }

    parse_str($query, $params);
    $fileName = sr_content_body_file_clean_name((string) ($params['file'] ?? ''));
    if ($fileName === '') {
        return null;
    }

    $tmpToken = (string) ($params['tmp'] ?? '');
    if ($tmpToken !== '') {
        return preg_match('/\A[a-f0-9]{32}\z/', $tmpToken) === 1
            ? ['type' => 'tmp', 'token' => $tmpToken, 'file' => $fileName]
            : null;
    }

    $contentId = (int) ($params['content_id'] ?? 0);
    return $contentId > 0 ? ['type' => 'content', 'content_id' => $contentId, 'file' => $fileName] : null;
}

function sr_content_finalize_body_files(PDO $pdo, int $contentId, string $html, int $accountId): string
{
    unset($accountId);
    if ($contentId < 1 || $html === '') {
        return $html;
    }

    $refs = sr_content_body_file_refs_from_html($html);
    $replacements = [];
    foreach ($refs as $ref) {
        if ((string) ($ref['type'] ?? '') !== 'tmp') {
            continue;
        }

        $token = (string) ($ref['token'] ?? '');
        $fileName = (string) ($ref['file'] ?? '');
        if (!sr_content_body_file_token_is_valid($token)) {
            continue;
        }

        $sourceKey = sr_content_body_file_tmp_key($token, $fileName);
        $targetKey = sr_content_body_file_content_key($contentId, $fileName);
        if ($sourceKey === '' || $targetKey === '') {
            continue;
        }

        try {
            sr_storage_copy('local', $sourceKey, $targetKey, ['overwrite' => true]);
            sr_thumbnail_delete_variants([
                'storage_driver' => 'local',
                'storage_key' => $sourceKey,
            ]);
            sr_storage_delete('local', $sourceKey);
        } catch (Throwable $exception) {
            sr_content_record_storage_cleanup_failure($pdo, 'body_file_finalize', $contentId, 'local', $sourceKey, $exception->getMessage());
            throw new RuntimeException('본문 이미지 저장을 완료할 수 없습니다.');
        }

        $oldUrl = sr_content_body_file_tmp_proxy_url($token, $fileName);
        $newUrl = sr_content_body_file_content_proxy_url($contentId, $fileName);
        $replacements[$oldUrl] = $newUrl;
        $replacements[sr_e($oldUrl)] = sr_e($newUrl);
    }

    if ($replacements !== []) {
        $html = strtr($html, $replacements);
    }

    sr_content_cleanup_unreferenced_body_files($pdo, $contentId, $html);
    return $html;
}

function sr_content_cleanup_unreferenced_body_files(PDO $pdo, int $contentId, string $html): void
{
    if ($contentId < 1) {
        return;
    }

    $kept = [];
    foreach (sr_content_body_file_refs_from_html($html) as $ref) {
        if ((string) ($ref['type'] ?? '') === 'content' && (int) ($ref['content_id'] ?? 0) === $contentId) {
            $fileName = sr_content_body_file_clean_name((string) ($ref['file'] ?? ''));
            if ($fileName !== '') {
                $kept[$fileName] = true;
            }
        }
    }

    $directory = SR_ROOT . '/storage/content/body/' . (string) $contentId;
    if (!is_dir($directory)) {
        return;
    }

    foreach (scandir($directory) ?: [] as $entry) {
        if (!is_string($entry) || $entry === '.' || $entry === '..' || isset($kept[$entry])) {
            continue;
        }
        $key = sr_content_body_file_content_key($contentId, $entry);
        if ($key !== '' && !sr_storage_delete('local', $key)) {
            sr_content_record_storage_cleanup_failure($pdo, 'body_file_unreferenced', $contentId, 'local', $key, '본문에서 제거된 이미지 저장소 정리에 실패했습니다.');
        } elseif ($key !== '') {
            sr_thumbnail_delete_variants([
                'storage_driver' => 'local',
                'storage_key' => $key,
            ]);
        }
    }
}

function sr_content_cleanup_body_files_for_deleted_content(PDO $pdo, array $contentIds): int
{
    $deleted = 0;
    foreach (array_values(array_filter(array_map('intval', $contentIds), static fn (int $contentId): bool => $contentId > 0)) as $contentId) {
        $directory = SR_ROOT . '/storage/content/body/' . (string) $contentId;
        if (!is_dir($directory)) {
            continue;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if (!is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }
            $key = sr_content_body_file_content_key($contentId, $entry);
            if ($key !== '' && sr_storage_delete('local', $key)) {
                sr_thumbnail_delete_variants([
                    'storage_driver' => 'local',
                    'storage_key' => $key,
                ]);
                $deleted++;
            } elseif ($key !== '') {
                sr_content_record_storage_cleanup_failure($pdo, 'body_file_content_delete', $contentId, 'local', $key, '콘텐츠 삭제 후 본문 이미지 저장소 정리에 실패했습니다.');
            }
        }
        @rmdir($directory);
    }

    return $deleted;
}

function sr_content_cleanup_expired_body_files(PDO $pdo, int $limit = 10): array
{
    $limit = max(1, min(50, $limit));
    $root = SR_ROOT . '/storage/content/body/tmp';
    if (!is_dir($root)) {
        return ['deleted' => 0, 'failed' => 0];
    }

    $deleted = 0;
    $failed = 0;
    $expiresBefore = time() - sr_content_body_file_temporary_ttl_seconds();
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
            $cleanEntry = sr_content_body_file_clean_name($entry);
            if ($cleanEntry === '') {
                continue;
            }
            $path = $directory . '/' . $cleanEntry;
            if (is_file($path) && filemtime($path) > $expiresBefore) {
                continue;
            }
            $key = 'content/body/tmp/' . $token . '/' . $cleanEntry;
            if (sr_storage_delete('local', $key)) {
                sr_thumbnail_delete_variants([
                    'storage_driver' => 'local',
                    'storage_key' => $key,
                ]);
                $deleted++;
            } else {
                $failed++;
                sr_content_record_storage_cleanup_failure($pdo, 'body_file_tmp_cleanup', 0, 'local', $key, '만료된 임시 본문 이미지 정리에 실패했습니다.');
            }
        }
        @rmdir($directory);
    }

    return ['deleted' => $deleted, 'failed' => $failed];
}

function sr_content_send_body_file(PDO $pdo, int $contentId, string $fileName, string $tmpToken = '', bool $thumbnail = false): void
{
    $key = '';
    if ($tmpToken !== '') {
        $account = sr_member_current_account($pdo);
        if (!is_array($account) || !sr_admin_has_permission($pdo, (int) $account['id'], '/admin/content', 'edit') || !sr_content_body_file_token_is_valid($tmpToken)) {
            sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
        }
        $key = sr_content_body_file_tmp_key($tmpToken, $fileName);
    } else {
        $page = sr_content_by_id($pdo, $contentId);
        $account = sr_member_current_account($pdo);
        if (!is_array($page) || !sr_content_can_access_body_file($pdo, $page, is_array($account) ? $account : null)) {
            sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
        }
        $key = sr_content_body_file_content_key($contentId, $fileName);
    }

    if ($key === '' || !sr_storage_key_is_safe($key)) {
        sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
    }

    $head = sr_storage_head('local', $key);
    $mimeType = (string) ($head['content_type'] ?? '');
    if (!is_array($head) || !in_array($mimeType, sr_content_body_file_allowed_mime_types(), true)) {
        sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
    }

    $path = sr_storage_local_path($key);
    if (!is_string($path)) {
        sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
    }

    if ($thumbnail) {
        $thumbnailFile = sr_thumbnail_protected_file([
            'module_key' => 'content_body',
            'storage_driver' => 'local',
            'storage_key' => $key,
            'mime_type' => $mimeType,
            'size_bytes' => (int) ($head['content_length'] ?? 0),
            'source_path' => $path,
        ], [
            'width' => 1280,
            'height' => 1280,
            'mode' => 'contain',
            'quality' => 82,
            'format' => 'source',
            'preserve_aspect' => true,
            'max_source_bytes' => sr_content_body_file_upload_max_bytes(),
        ]);
        if (is_array($thumbnailFile) && is_string($thumbnailFile['path'] ?? null)) {
            sr_send_file_headers((string) $thumbnailFile['content_type'], (int) ($thumbnailFile['content_length'] ?? 0), 'private, max-age=300');
            readfile((string) $thumbnailFile['path']);
            sr_finish_response();
        }
    }

    sr_send_file_headers($mimeType, (int) ($head['content_length'] ?? 0), 'private, max-age=300');
    readfile($path);
    sr_finish_response();
}

function sr_content_can_access_body_file(PDO $pdo, array $page, ?array $account): bool
{
    if ((string) ($page['status'] ?? '') !== 'published') {
        return is_array($account) && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/content', 'view');
    }

    if (!sr_content_asset_access_required($page)) {
        return true;
    }

    if (!is_array($account)) {
        return false;
    }

    $access = sr_content_charge_view_access($pdo, $page, (int) $account['id'], false, '', 0, false);
    return !empty($access['allowed']);
}
