<?php

declare(strict_types=1);

function sr_popup_layer_body_file_allowed_extensions(): array
{
    return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
}

function sr_popup_layer_body_file_allowed_mime_types(): array
{
    return ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
}

function sr_popup_layer_body_file_upload_max_bytes(): int
{
    return 5 * 1024 * 1024;
}

function sr_popup_layer_body_file_temporary_ttl_seconds(): int
{
    return 86400;
}

function sr_popup_layer_body_file_upload_url(): string
{
    return sr_url('/admin/popup-layers/body-files/upload');
}

function sr_popup_layer_body_file_upload_token(): string
{
    if (empty($_SESSION['sr_popup_layer_body_upload_token']) || !is_string($_SESSION['sr_popup_layer_body_upload_token'])) {
        $_SESSION['sr_popup_layer_body_upload_token'] = bin2hex(random_bytes(16));
    }

    return (string) $_SESSION['sr_popup_layer_body_upload_token'];
}

function sr_popup_layer_body_file_token_is_valid(string $token): bool
{
    return preg_match('/\A[a-f0-9]{32}\z/', $token) === 1
        && is_string($_SESSION['sr_popup_layer_body_upload_token'] ?? null)
        && hash_equals((string) $_SESSION['sr_popup_layer_body_upload_token'], $token);
}

function sr_popup_layer_body_file_clean_name(string $name): string
{
    $name = basename($name);
    return preg_match('/\A[A-Za-z0-9._=-]{1,160}\z/', $name) === 1 ? $name : '';
}

function sr_popup_layer_body_file_tmp_key(string $token, string $fileName): string
{
    if (!sr_popup_layer_body_file_token_is_valid($token)) {
        return '';
    }
    $fileName = sr_popup_layer_body_file_clean_name($fileName);
    return $fileName !== '' ? 'popup_layer/body/tmp/' . $token . '/' . $fileName : '';
}

function sr_popup_layer_body_file_layer_key(int $popupLayerId, string $fileName): string
{
    $fileName = sr_popup_layer_body_file_clean_name($fileName);
    return $popupLayerId > 0 && $fileName !== '' ? 'popup_layer/body/' . $popupLayerId . '/' . $fileName : '';
}

function sr_popup_layer_body_file_tmp_proxy_url(string $token, string $fileName): string
{
    return sr_url('/popup-layer/body-file?tmp=' . rawurlencode($token) . '&file=' . rawurlencode($fileName));
}

function sr_popup_layer_body_file_layer_proxy_url(int $popupLayerId, string $fileName): string
{
    return sr_url('/popup-layer/body-file?popup_layer_id=' . rawurlencode((string) $popupLayerId) . '&file=' . rawurlencode($fileName));
}

function sr_popup_layer_upload_body_file(PDO $pdo, int $accountId, array $file, string $token): array
{
    if (!sr_admin_has_permission($pdo, $accountId, '/admin/popup-layers', 'edit')) {
        throw new RuntimeException('팝업레이어 편집 권한이 필요합니다.');
    }
    if (!sr_popup_layer_body_file_token_is_valid($token)) {
        throw new RuntimeException('본문 이미지 업로드 토큰이 올바르지 않습니다.');
    }

    $validated = sr_upload_validate_file($file, [
        'max_bytes' => sr_popup_layer_body_file_upload_max_bytes(),
        'allowed_extensions' => sr_popup_layer_body_file_allowed_extensions(),
        'allowed_mime_types' => sr_popup_layer_body_file_allowed_mime_types(),
    ]);
    $imageSize = @getimagesize((string) $validated['tmp_name']);
    if (!is_array($imageSize)) {
        throw new RuntimeException('본문 이미지를 확인할 수 없습니다.');
    }
    $storedName = sr_upload_random_filename((string) $validated['extension']);
    $storedMimeType = sr_upload_detect_mime((string) $validated['tmp_name']);
    if (!in_array($storedMimeType, sr_popup_layer_body_file_allowed_mime_types(), true)) {
        throw new RuntimeException('저장된 본문 이미지 metadata를 확인할 수 없습니다.');
    }
    $storageKey = sr_popup_layer_body_file_tmp_key($token, $storedName);
    if ($storageKey === '') {
        throw new RuntimeException('본문 이미지 저장 key가 올바르지 않습니다.');
    }
    sr_storage_put_file((string) $validated['tmp_name'], $storageKey, [
        'driver' => 'local',
        'content_type' => $storedMimeType,
    ]);

    return [
        'url' => sr_popup_layer_body_file_tmp_proxy_url($token, $storedName),
        'width' => (int) ($imageSize[0] ?? 0),
        'height' => (int) ($imageSize[1] ?? 0),
    ];
}

function sr_popup_layer_body_file_refs_from_html(string $html): array
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
            $ref = sr_popup_layer_body_file_ref_from_url($image->getAttribute('src'));
            if ($ref !== null) {
                $refs[] = $ref;
            }
        }
    }
    return $refs;
}

function sr_popup_layer_body_file_ref_from_url(string $url): ?array
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
    if ($path !== '/popup-layer/body-file') {
        return null;
    }
    parse_str($query, $params);
    $fileName = sr_popup_layer_body_file_clean_name((string) ($params['file'] ?? ''));
    if ($fileName === '') {
        return null;
    }
    $tmpToken = (string) ($params['tmp'] ?? '');
    if ($tmpToken !== '') {
        return preg_match('/\A[a-f0-9]{32}\z/', $tmpToken) === 1 ? ['type' => 'tmp', 'token' => $tmpToken, 'file' => $fileName] : null;
    }
    $popupLayerId = (int) ($params['popup_layer_id'] ?? 0);
    return $popupLayerId > 0 ? ['type' => 'popup_layer', 'popup_layer_id' => $popupLayerId, 'file' => $fileName] : null;
}

function sr_popup_layer_finalize_body_files(int $popupLayerId, string $html): string
{
    if ($popupLayerId < 1 || $html === '') {
        return $html;
    }
    $replacements = [];
    foreach (sr_popup_layer_body_file_refs_from_html($html) as $ref) {
        if ((string) ($ref['type'] ?? '') !== 'tmp') {
            continue;
        }
        $token = (string) ($ref['token'] ?? '');
        $fileName = (string) ($ref['file'] ?? '');
        if (!sr_popup_layer_body_file_token_is_valid($token)) {
            continue;
        }
        $sourceKey = sr_popup_layer_body_file_tmp_key($token, $fileName);
        $targetKey = sr_popup_layer_body_file_layer_key($popupLayerId, $fileName);
        if ($sourceKey === '' || $targetKey === '') {
            continue;
        }
        try {
            sr_storage_copy('local', $sourceKey, $targetKey, ['overwrite' => true]);
            sr_storage_delete('local', $sourceKey);
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'popup_layer_body_file_finalize_failed');
            throw new RuntimeException('팝업레이어 본문 이미지 저장을 완료할 수 없습니다.');
        }
        $oldUrl = sr_popup_layer_body_file_tmp_proxy_url($token, $fileName);
        $newUrl = sr_popup_layer_body_file_layer_proxy_url($popupLayerId, $fileName);
        $replacements[$oldUrl] = $newUrl;
        $replacements[sr_e($oldUrl)] = sr_e($newUrl);
    }
    if ($replacements !== []) {
        $html = strtr($html, $replacements);
    }
    sr_popup_layer_cleanup_unreferenced_body_files($popupLayerId, $html);
    return $html;
}

function sr_popup_layer_clone_body_files(int $sourcePopupLayerId, int $targetPopupLayerId, string $html): string
{
    if ($sourcePopupLayerId < 1 || $targetPopupLayerId < 1 || $sourcePopupLayerId === $targetPopupLayerId || $html === '') {
        return $html;
    }

    $replacements = [];
    foreach (sr_popup_layer_body_file_refs_from_html($html) as $ref) {
        if ((string) ($ref['type'] ?? '') !== 'popup_layer' || (int) ($ref['popup_layer_id'] ?? 0) !== $sourcePopupLayerId) {
            continue;
        }
        $fileName = (string) ($ref['file'] ?? '');
        $sourceKey = sr_popup_layer_body_file_layer_key($sourcePopupLayerId, $fileName);
        $targetKey = sr_popup_layer_body_file_layer_key($targetPopupLayerId, $fileName);
        if ($sourceKey === '' || $targetKey === '') {
            continue;
        }
        try {
            sr_storage_copy('local', $sourceKey, $targetKey, ['overwrite' => true]);
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'popup_layer_body_file_clone_failed');
            continue;
        }
        $oldUrl = sr_popup_layer_body_file_layer_proxy_url($sourcePopupLayerId, $fileName);
        $newUrl = sr_popup_layer_body_file_layer_proxy_url($targetPopupLayerId, $fileName);
        $replacements[$oldUrl] = $newUrl;
        $replacements[sr_e($oldUrl)] = sr_e($newUrl);
    }

    return $replacements === [] ? $html : strtr($html, $replacements);
}

function sr_popup_layer_cleanup_unreferenced_body_files(int $popupLayerId, string $html): void
{
    if ($popupLayerId < 1) {
        return;
    }
    $kept = [];
    foreach (sr_popup_layer_body_file_refs_from_html($html) as $ref) {
        if ((string) ($ref['type'] ?? '') === 'popup_layer' && (int) ($ref['popup_layer_id'] ?? 0) === $popupLayerId) {
            $kept[(string) $ref['file']] = true;
        }
    }
    $directory = SR_ROOT . '/storage/popup_layer/body/' . (string) $popupLayerId;
    if (!is_dir($directory)) {
        return;
    }
    foreach (scandir($directory) ?: [] as $entry) {
        if (!is_string($entry) || $entry === '.' || $entry === '..' || isset($kept[$entry])) {
            continue;
        }
        $key = sr_popup_layer_body_file_layer_key($popupLayerId, $entry);
        if ($key !== '' && !sr_storage_delete('local', $key)) {
            sr_log_exception(new RuntimeException('팝업레이어 본문에서 제거된 이미지 저장소 정리에 실패했습니다: ' . $key), 'popup_layer_body_file_cleanup_failed');
        }
    }
}

function sr_popup_layer_cleanup_body_files_for_deleted_layers(array $popupLayerIds): int
{
    $deleted = 0;
    foreach (array_values(array_filter(array_map('intval', $popupLayerIds), static fn (int $popupLayerId): bool => $popupLayerId > 0)) as $popupLayerId) {
        $directory = SR_ROOT . '/storage/popup_layer/body/' . (string) $popupLayerId;
        if (!is_dir($directory)) {
            continue;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if (!is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }
            $key = sr_popup_layer_body_file_layer_key($popupLayerId, $entry);
            if ($key !== '' && sr_storage_delete('local', $key)) {
                $deleted++;
            } elseif ($key !== '') {
                sr_log_exception(new RuntimeException('팝업레이어 삭제 후 본문 이미지 저장소 정리에 실패했습니다: ' . $key), 'popup_layer_body_file_delete_failed');
            }
        }
        @rmdir($directory);
    }
    return $deleted;
}

function sr_popup_layer_cleanup_expired_body_files(PDO $pdo, int $limit = 10): array
{
    unset($pdo);
    $limit = max(1, min(50, $limit));
    $root = SR_ROOT . '/storage/popup_layer/body/tmp';
    if (!is_dir($root)) {
        return ['deleted' => 0, 'failed' => 0];
    }

    $deleted = 0;
    $failed = 0;
    $expiresBefore = time() - sr_popup_layer_body_file_temporary_ttl_seconds();
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
            $cleanEntry = sr_popup_layer_body_file_clean_name($entry);
            if ($cleanEntry === '') {
                continue;
            }
            $path = $directory . '/' . $cleanEntry;
            if (is_file($path) && filemtime($path) > $expiresBefore) {
                continue;
            }
            $key = 'popup_layer/body/tmp/' . $token . '/' . $cleanEntry;
            if (sr_storage_delete('local', $key)) {
                $deleted++;
            } else {
                $failed++;
                sr_log_exception(new RuntimeException('만료된 팝업레이어 임시 본문 이미지 정리에 실패했습니다: ' . $key), 'popup_layer_body_file_tmp_cleanup_failed');
            }
        }
        @rmdir($directory);
    }

    return ['deleted' => $deleted, 'failed' => $failed];
}

function sr_popup_layer_send_body_file(PDO $pdo, int $popupLayerId, string $fileName, string $tmpToken = ''): void
{
    if ($tmpToken !== '') {
        $account = sr_member_current_account($pdo);
        if (!is_array($account) || !sr_admin_has_permission($pdo, (int) $account['id'], '/admin/popup-layers', 'edit') || !sr_popup_layer_body_file_token_is_valid($tmpToken)) {
            sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
        }
        $key = sr_popup_layer_body_file_tmp_key($tmpToken, $fileName);
    } else {
        if (!sr_popup_layer_body_file_access_allowed($pdo, $popupLayerId)) {
            sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
        }
        $key = sr_popup_layer_body_file_layer_key($popupLayerId, $fileName);
    }
    if ($key === '' || !sr_storage_key_is_safe($key)) {
        sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
    }
    $head = sr_storage_head('local', $key);
    $mimeType = (string) ($head['content_type'] ?? '');
    if (!is_array($head) || !in_array($mimeType, sr_popup_layer_body_file_allowed_mime_types(), true)) {
        sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
    }
    $path = sr_storage_local_path($key);
    if (!is_string($path)) {
        sr_render_error(404, '본문 이미지를 찾을 수 없습니다.');
    }
    sr_send_file_headers($mimeType, (int) ($head['content_length'] ?? 0), 'private, max-age=300');
    readfile($path);
    sr_finish_response();
}

function sr_popup_layer_body_file_access_allowed(PDO $pdo, int $popupLayerId): bool
{
    $stmt = $pdo->prepare('SELECT status, starts_at, ends_at FROM sr_popup_layers WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $popupLayerId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return false;
    }
    $now = sr_now();
    if ((string) ($row['status'] ?? '') === 'enabled'
        && ((string) ($row['starts_at'] ?? '') === '' || (string) $row['starts_at'] <= $now)
        && ((string) ($row['ends_at'] ?? '') === '' || (string) $row['ends_at'] >= $now)
    ) {
        return true;
    }
    $account = sr_member_current_account($pdo);
    return is_array($account) && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/popup-layers', 'view');
}
