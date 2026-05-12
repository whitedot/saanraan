<?php

declare(strict_types=1);

function toy_banner_clean_single_line(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function toy_banner_clean_text(string $value, int $maxLength): string
{
    $value = trim($value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function toy_banner_clean_url(string $value): string
{
    $value = trim($value);
    if ($value === '' || toy_is_safe_relative_url($value) || toy_is_http_url($value)) {
        return $value;
    }

    return '';
}

function toy_banner_clean_image_url(string $value): string
{
    $value = trim($value);
    if ($value === '' || toy_is_safe_relative_url($value) || toy_is_http_url($value)) {
        return $value;
    }

    return '';
}

function toy_banner_image_upload_max_bytes(): int
{
    return 5242880;
}

function toy_banner_format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return number_format(max(0, $bytes)) . ' bytes';
}

function toy_banner_image_upload_was_provided(mixed $file): bool
{
    return is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

function toy_banner_image_format_for_mime(string $mimeType): string
{
    return match (strtolower(trim($mimeType))) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => '',
    };
}

function toy_banner_image_mime_is_allowed(string $mimeType): bool
{
    return in_array(strtolower(trim($mimeType)), ['image/jpeg', 'image/png', 'image/webp'], true);
}

function toy_banner_upload_image(array $file): ?array
{
    if (!toy_banner_image_upload_was_provided($file)) {
        return null;
    }

    $validated = toy_upload_validate_file($file, [
        'max_bytes' => toy_banner_image_upload_max_bytes(),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
    ]);

    $targetFormat = toy_banner_image_format_for_mime((string) $validated['mime_type']);
    if ($targetFormat === '') {
        throw new RuntimeException('허용되지 않은 이미지 형식입니다.');
    }

    $datePath = date('Y/m');
    $directory = TOY_ROOT . '/storage/tmp/banner-images/' . $datePath;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('배너 이미지 저장 디렉터리를 만들 수 없습니다.');
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

    $storedMimeType = toy_upload_detect_mime($targetPath);
    if (!toy_banner_image_mime_is_allowed($storedMimeType)) {
        @unlink($targetPath);
        throw new RuntimeException('저장된 이미지 MIME을 확인할 수 없습니다.');
    }

    $storageKey = 'banner/images/' . $datePath . '/' . $storedName;
    $stored = toy_storage_put_file($targetPath, $storageKey, [
        'content_type' => $storedMimeType,
    ]);
    @unlink($targetPath);

    $storageReference = toy_storage_reference((string) $stored['driver'], $storageKey);
    $publicUrl = (string) ($stored['url'] ?? '');

    return [
        'driver' => (string) $stored['driver'],
        'key' => $storageKey,
        'path' => (string) ($stored['path'] ?? ''),
        'storage_key' => $storageKey,
        'url' => $publicUrl !== '' ? $publicUrl : '/banner/image?file=' . rawurlencode($storageReference),
    ];
}

function toy_banner_image_storage_path(string $storageKey): ?string
{
    $storageKey = trim($storageKey);
    if (preg_match('#\A\d{4}/\d{2}/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $storageKey) !== 1) {
        return null;
    }

    $storageRoot = realpath(TOY_ROOT . '/storage/banner/images');
    if (!is_string($storageRoot) || !is_dir($storageRoot)) {
        return null;
    }

    $candidate = $storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
    $realPath = realpath($candidate);
    if (!is_string($realPath) || !is_file($realPath)) {
        return null;
    }

    $storageRootPrefix = rtrim($storageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($realPath, $storageRootPrefix)) {
        return null;
    }

    return $realPath;
}

function toy_banner_image_storage_reference(string $reference): ?array
{
    $storage = toy_storage_parse_reference($reference);
    if (!is_array($storage)) {
        $legacyKey = 'banner/images/' . ltrim($reference, '/');
        $storage = toy_storage_parse_reference($legacyKey);
    }

    if (!is_array($storage)) {
        return null;
    }

    $key = (string) $storage['key'];
    if (preg_match('#\Abanner/images/\d{4}/\d{2}/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $key) !== 1) {
        return null;
    }

    return $storage;
}

function toy_banner_delete_uploaded_image(array $uploadedImage): void
{
    $driver = (string) ($uploadedImage['driver'] ?? '');
    $key = (string) ($uploadedImage['key'] ?? '');
    if ($driver !== '' && $key !== '') {
        toy_storage_delete($driver, $key);
        return;
    }

    if (is_string($uploadedImage['path'] ?? null) && (string) $uploadedImage['path'] !== '') {
        @unlink((string) $uploadedImage['path']);
    }
}

function toy_banner_clean_admin_datetime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
    return $date instanceof DateTimeImmutable ? $date->format('Y-m-d H:i:00') : null;
}

function toy_banner_admin_datetime_value(mixed $value): string
{
    if (!is_string($value) || $value === '') {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
    return $date instanceof DateTimeImmutable ? $date->format('Y-m-d\TH:i') : '';
}

function toy_banner_builtin_targets(): array
{
    return [
        [
            'module_key' => 'core',
            'point_key' => 'site.home',
            'slot_key' => 'before_content',
            'label' => 'core / 홈 / 본문 위',
            'source' => 'core',
        ],
        [
            'module_key' => 'core',
            'point_key' => 'site.home',
            'slot_key' => 'after_content',
            'label' => 'core / 홈 / 본문 아래',
            'source' => 'core',
        ],
    ];
}

function toy_banner_available_targets(PDO $pdo): array
{
    $targets = toy_banner_builtin_targets();

    foreach (toy_enabled_module_contract_files($pdo, 'extension-points.php', []) as $moduleKey => $file) {
        $points = toy_load_module_contract_file($moduleKey, $file);
        if (!is_array($points)) {
            continue;
        }

        foreach ($points as $point) {
            if (!is_array($point) || empty($point['output'])) {
                continue;
            }

            $pointKey = (string) ($point['point_key'] ?? '');
            if (preg_match('/\A[a-z0-9][a-z0-9_.-]{0,119}\z/', $pointKey) !== 1) {
                continue;
            }

            $pointLabel = (string) ($point['label'] ?? $pointKey);
            $slots = isset($point['slots']) && is_array($point['slots']) ? $point['slots'] : [];
            foreach ($slots as $slot) {
                if (!is_array($slot) || (string) ($slot['kind'] ?? '') !== 'content') {
                    continue;
                }

                $slotKey = (string) ($slot['slot_key'] ?? '');
                if (preg_match('/\A[a-z0-9][a-z0-9_.-]{0,79}\z/', $slotKey) !== 1) {
                    continue;
                }

                $targets[] = [
                    'module_key' => $moduleKey,
                    'point_key' => $pointKey,
                    'slot_key' => $slotKey,
                    'label' => $moduleKey . ' / ' . $pointLabel . ' / ' . (string) ($slot['label'] ?? $slotKey),
                    'source' => 'extension-points.php',
                ];
            }
        }
    }

    return $targets;
}

function toy_banner_target_option_value(array $target): string
{
    return (string) $target['module_key'] . '|' . (string) $target['point_key'] . '|' . (string) $target['slot_key'];
}

function toy_banner_public_target_option_value(): string
{
    return '__public__';
}

function toy_banner_is_public_target_option(string $option): bool
{
    return $option === toy_banner_public_target_option_value();
}

function toy_banner_find_target(array $targets, string $option): ?array
{
    foreach ($targets as $target) {
        if (toy_banner_target_option_value($target) === $option) {
            return $target;
        }
    }

    return null;
}

function toy_banner_target_from_option(string $option): ?array
{
    $parts = explode('|', $option);
    if (count($parts) !== 3) {
        return null;
    }

    return toy_banner_target_from_row([
        'module_key' => $parts[0],
        'point_key' => $parts[1],
        'slot_key' => $parts[2],
    ]);
}

function toy_banner_target_labels(array $targets): array
{
    $labels = [];

    foreach ($targets as $target) {
        $labels[toy_banner_target_option_value($target)] = (string) ($target['label'] ?? toy_banner_target_option_value($target));
    }

    return $labels;
}

function toy_banner_target_from_row(array $row, string $label = '저장된 출력 위치'): ?array
{
    $moduleKey = (string) ($row['module_key'] ?? '');
    $pointKey = (string) ($row['point_key'] ?? '');
    $slotKey = (string) ($row['slot_key'] ?? '');

    if (
        !toy_is_safe_module_key($moduleKey)
        || preg_match('/\A[a-z0-9][a-z0-9_.-]{0,119}\z/', $pointKey) !== 1
        || preg_match('/\A[a-z0-9][a-z0-9_.-]{0,79}\z/', $slotKey) !== 1
    ) {
        return null;
    }

    return [
        'module_key' => $moduleKey,
        'point_key' => $pointKey,
        'slot_key' => $slotKey,
        'label' => $label . ' / ' . $moduleKey . ' / ' . $pointKey . ' / ' . $slotKey,
        'source' => 'stored',
    ];
}

function toy_banner_link_attributes(string $url): string
{
    $url = toy_banner_clean_url($url);
    if ($url === '') {
        return '';
    }

    $attributes = ' href="' . toy_e($url) . '"';
    if (toy_is_http_url($url)) {
        $attributes .= ' target="_blank" rel="noopener noreferrer"';
    }

    return $attributes;
}

function toy_banner_click_url(int $bannerId): string
{
    return '/banner/click?id=' . rawurlencode((string) $bannerId);
}

function toy_banner_click_link_attributes(int $bannerId, string $url): string
{
    $url = toy_banner_clean_url($url);
    if ($bannerId <= 0 || $url === '') {
        return '';
    }

    $attributes = ' href="' . toy_e(toy_url(toy_banner_click_url($bannerId))) . '"';
    if (toy_is_http_url($url)) {
        $attributes .= ' target="_blank" rel="noopener noreferrer"';
    }

    return $attributes;
}

function toy_banner_click_subject(): string
{
    $accountId = $_SESSION['toy_account_id'] ?? null;
    if (is_int($accountId) || is_string($accountId)) {
        $accountId = (int) $accountId;
        if ($accountId > 0) {
            return 'account:' . (string) $accountId;
        }
    }

    $sessionId = session_id();
    if (is_string($sessionId) && $sessionId !== '') {
        return 'session:' . hash('sha256', $sessionId);
    }

    return 'guest:' . toy_client_ip() . '|' . hash('sha256', toy_client_user_agent());
}

function toy_banner_record_click(PDO $pdo, array $config, int $bannerId): bool
{
    if ($bannerId <= 0) {
        return false;
    }

    try {
        $clickKeyHash = toy_hmac_hash('banner-click|' . (string) $bannerId . '|' . toy_banner_click_subject(), $config);
        $now = toy_now();

        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO toy_banner_clicks
                (banner_id, click_key_hash, clicked_at)
             VALUES
                (:banner_id, :click_key_hash, :clicked_at)'
        );
        $stmt->execute([
            'banner_id' => $bannerId,
            'click_key_hash' => $clickKeyHash,
            'clicked_at' => $now,
        ]);

        if ($stmt->rowCount() < 1) {
            return false;
        }

        $stmt = $pdo->prepare(
            'UPDATE toy_banners
             SET click_count = click_count + 1
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $bannerId,
        ]);

        return true;
    } catch (Throwable $exception) {
        toy_log_exception($exception, 'banner_click_record_failed');
        return false;
    }
}

function toy_banner_click_target(PDO $pdo, int $bannerId): ?array
{
    if ($bannerId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT b.id, b.link_url
         FROM toy_banners b
         WHERE b.id = :id
           AND b.status = 'enabled'
           AND b.link_url <> ''
           AND (b.starts_at IS NULL OR b.starts_at <= :now_start)
           AND (b.ends_at IS NULL OR b.ends_at >= :now_end)
         LIMIT 1"
    );
    $now = toy_now();
    $stmt->execute([
        'id' => $bannerId,
        'now_start' => $now,
        'now_end' => $now,
    ]);

    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    $linkUrl = toy_banner_clean_url((string) $row['link_url']);
    if ($linkUrl === '') {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'link_url' => $linkUrl,
    ];
}

function toy_banner_redirect_to_link(string $url): void
{
    $url = toy_banner_clean_url($url);
    if ($url === '') {
        toy_render_error(404, '배너 링크를 찾을 수 없습니다.');
    }

    if (toy_is_safe_relative_url($url)) {
        toy_redirect($url);
    }

    toy_enforce_request_contract('before_redirect');
    header('Location: ' . $url, true, 302);
    toy_finish_response();
}

function toy_banner_link_type_label(string $url): string
{
    $url = toy_banner_clean_url($url);
    if ($url === '') {
        return '-';
    }

    return toy_is_http_url($url) ? '외부 링크' : '내부 링크';
}

function toy_banner_public_banners(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        "SELECT b.id, b.title, b.status, b.starts_at, b.ends_at, b.sort_order
         FROM toy_banners b
         WHERE NOT EXISTS (
             SELECT 1
             FROM toy_banner_targets t
             WHERE t.banner_id = b.id
         )
         ORDER BY b.sort_order ASC, b.id DESC"
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

function toy_banner_public_banner_option_labels(PDO $pdo): array
{
    $labels = [];
    foreach (toy_banner_public_banners($pdo) as $banner) {
        $labels[(int) $banner['id']] = (string) $banner['title'];
    }

    return $labels;
}

function toy_banner_render_item(array $banner): string
{
    $content = '';
    $imageUrl = toy_banner_clean_image_url((string) ($banner['image_url'] ?? ''));
    if ($imageUrl !== '') {
        $imageSrc = toy_is_http_url($imageUrl) ? $imageUrl : toy_url($imageUrl);
        $content .= '<img src="' . toy_e($imageSrc) . '" alt="' . toy_e((string) $banner['title']) . '">';
    }
    $content .= '<strong>' . toy_e((string) $banner['title']) . '</strong>';
    if ((string) ($banner['body_text'] ?? '') !== '') {
        $content .= '<span>' . nl2br(toy_e((string) $banner['body_text'])) . '</span>';
    }

    $linkAttributes = toy_banner_click_link_attributes((int) $banner['id'], (string) ($banner['link_url'] ?? ''));
    if ($linkAttributes !== '') {
        $content = '<a' . $linkAttributes . '>' . $content . '</a>';
    }

    return '<aside class="toy-banner" data-banner-id="' . toy_e((string) $banner['id']) . '">' . $content . '</aside>';
}

function toy_banner_render_public_banner(PDO $pdo, int $bannerId): string
{
    if ($bannerId <= 0) {
        return '';
    }

    $stmt = $pdo->prepare(
        "SELECT b.id, b.title, b.body_text, b.link_url, b.image_url
         FROM toy_banners b
         WHERE b.id = :id
           AND b.status = 'enabled'
           AND (b.starts_at IS NULL OR b.starts_at <= :now_start)
           AND (b.ends_at IS NULL OR b.ends_at >= :now_end)
           AND NOT EXISTS (
               SELECT 1
               FROM toy_banner_targets t
               WHERE t.banner_id = b.id
           )
         LIMIT 1"
    );
    $now = toy_now();
    $stmt->execute([
        'id' => $bannerId,
        'now_start' => $now,
        'now_end' => $now,
    ]);

    $banner = $stmt->fetch();
    return is_array($banner) ? toy_banner_render_item($banner) : '';
}

function toy_banner_render_slot(PDO $pdo, array $context): string
{
    $moduleKey = (string) ($context['module_key'] ?? '');
    $pointKey = (string) ($context['point_key'] ?? '');
    $slotKey = (string) ($context['slot_key'] ?? '');
    $subjectId = (string) ($context['subject_id'] ?? '');

    $stmt = $pdo->prepare(
        "SELECT b.id, b.title, b.body_text, b.link_url, b.image_url
         FROM toy_banners b
         INNER JOIN toy_banner_targets t ON t.banner_id = b.id
         WHERE b.status = 'enabled'
           AND (b.starts_at IS NULL OR b.starts_at <= :now_start)
           AND (b.ends_at IS NULL OR b.ends_at >= :now_end)
           AND t.module_key = :module_key
           AND t.point_key = :point_key
           AND t.slot_key = :slot_key
           AND (t.match_type = 'all' OR (t.match_type = 'exact' AND t.subject_id = :subject_id))
         ORDER BY b.sort_order ASC, b.id DESC
         LIMIT 5"
    );
    $now = toy_now();
    $stmt->execute([
        'now_start' => $now,
        'now_end' => $now,
        'module_key' => $moduleKey,
        'point_key' => $pointKey,
        'slot_key' => $slotKey,
        'subject_id' => $subjectId,
    ]);

    $html = '';
    foreach ($stmt->fetchAll() as $banner) {
        $html .= toy_banner_render_item($banner);
    }

    return $html;
}
