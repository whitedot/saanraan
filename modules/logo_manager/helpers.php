<?php

declare(strict_types=1);

function sr_logo_manager_default_position_options(): array
{
    return [
        'admin.sidebar' => [
            'label' => sr_t('logo_manager::position.admin_sidebar.label'),
            'hint' => sr_t('logo_manager::position.admin_sidebar.hint'),
            'max_bytes' => 2097152,
            'surface' => 'admin',
        ],
        'public.header.desktop' => [
            'label' => sr_t('logo_manager::position.public_header_desktop.label'),
            'hint' => sr_t('logo_manager::position.public_header_desktop.hint'),
            'max_bytes' => 3145728,
            'surface' => 'public',
        ],
        'public.header.mobile' => [
            'label' => sr_t('logo_manager::position.public_header_mobile.label'),
            'hint' => sr_t('logo_manager::position.public_header_mobile.hint'),
            'max_bytes' => 2097152,
            'surface' => 'public',
        ],
        'public.favicon' => [
            'label' => sr_t('logo_manager::position.favicon.label'),
            'hint' => sr_t('logo_manager::position.favicon.hint'),
            'max_bytes' => 1048576,
            'surface' => 'global',
        ],
    ];
}

function sr_logo_manager_position_options(?PDO $pdo = null): array
{
    $options = sr_logo_manager_default_position_options();
    if (!$pdo instanceof PDO) {
        return $options;
    }

    foreach (sr_enabled_module_contract_files($pdo, 'logo-positions.php', ['logo_manager']) as $moduleKey => $file) {
        $positions = sr_load_module_contract_file($moduleKey, $file);
        if (is_callable($positions)) {
            $positions = $positions($pdo);
        }
        if (!is_array($positions)) {
            continue;
        }

        foreach ($positions as $position) {
            if (!is_array($position)) {
                continue;
            }
            $positionKey = sr_logo_manager_clean_position_key((string) ($position['position_key'] ?? ''));
            if ($positionKey === '') {
                continue;
            }

            $label = sr_logo_manager_clean_single_line((string) ($position['label'] ?? $positionKey), 120);
            $hint = sr_logo_manager_clean_single_line((string) ($position['hint'] ?? ''), 180);
            $maxBytes = (int) ($position['max_bytes'] ?? 3145728);
            $options[$positionKey] = [
                'label' => $label !== '' ? $label : $positionKey,
                'hint' => $hint,
                'max_bytes' => max(1024, min(10485760, $maxBytes)),
                'surface' => sr_logo_manager_clean_single_line((string) ($position['surface'] ?? 'public'), 40),
                'module_key' => $moduleKey,
            ];
        }
    }

    uasort($options, static function (array $left, array $right): int {
        return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
    });

    return $options;
}

function sr_logo_manager_clean_position_key(string $positionKey): string
{
    $positionKey = strtolower(trim($positionKey));
    return preg_match('/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*){1,5}\z/', $positionKey) === 1 ? $positionKey : '';
}

function sr_logo_manager_position_key(string $positionKey, ?PDO $pdo = null): string
{
    $positionKey = sr_logo_manager_clean_position_key($positionKey);
    $options = sr_logo_manager_position_options($pdo);
    return $positionKey !== '' && isset($options[$positionKey]) ? $positionKey : 'public.header.desktop';
}

function sr_logo_manager_position_label(string $positionKey, ?PDO $pdo = null): string
{
    $options = sr_logo_manager_position_options($pdo);
    $positionKey = sr_logo_manager_position_key($positionKey, $pdo);

    return (string) ($options[$positionKey]['label'] ?? $positionKey);
}

function sr_logo_manager_status_label(string $status): string
{
    return match ($status) {
        'active' => '사용',
        'archived' => '보관',
        'disabled' => '중지',
        default => $status,
    };
}

function sr_logo_manager_table_exists(PDO $pdo): bool
{
    static $cache = [];
    $cacheKey = method_exists($pdo, 'srTablePrefix') ? $pdo->srTablePrefix() : 'sr_';
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $pdo->query('SELECT 1 FROM sr_logo_manager_logos LIMIT 1');
        $cache[$cacheKey] = true;
    } catch (Throwable) {
        $cache[$cacheKey] = false;
    }

    return $cache[$cacheKey];
}

function sr_logo_manager_clean_single_line(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function sr_logo_manager_clean_url(string $value): string
{
    $value = trim($value);
    if ($value === '' || sr_is_safe_relative_url($value) || sr_is_http_url($value)) {
        return $value;
    }

    return '';
}

function sr_logo_manager_format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return number_format(max(0, $bytes)) . ' bytes';
}

function sr_logo_manager_upload_was_provided(mixed $file): bool
{
    return is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

function sr_logo_manager_image_format_for_mime(string $mimeType): string
{
    return match (strtolower(trim($mimeType))) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        default => '',
    };
}

function sr_logo_manager_image_mime_is_allowed(string $mimeType): bool
{
    return in_array(strtolower(trim($mimeType)), ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'], true);
}

function sr_logo_manager_svg_upload_mime_is_allowed(string $mimeType): bool
{
    return in_array(strtolower(trim($mimeType)), ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain'], true);
}

function sr_logo_manager_upload_max_bytes(string $positionKey, ?PDO $pdo = null): int
{
    $options = sr_logo_manager_position_options($pdo);
    $positionKey = sr_logo_manager_position_key($positionKey, $pdo);

    return (int) ($options[$positionKey]['max_bytes'] ?? 3145728);
}

function sr_logo_manager_upload_image(array $file, string $positionKey, ?PDO $pdo = null): array
{
    $positionKey = sr_logo_manager_position_key($positionKey, $pdo);
    $validated = sr_upload_validate_file($file, [
        'max_bytes' => sr_logo_manager_upload_max_bytes($positionKey, $pdo),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'svg'],
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml', 'text/xml', 'application/xml', 'text/plain'],
    ]);

    if ((string) $validated['extension'] === 'svg') {
        return sr_logo_manager_upload_svg_image($validated);
    }

    $sourcePath = (string) $validated['tmp_name'];
    $targetFormat = sr_logo_manager_image_format_for_mime((string) $validated['mime_type']);
    if ($targetFormat === '' || $targetFormat === 'svg') {
        throw new RuntimeException('허용되지 않은 로고 이미지 형식입니다.');
    }

    $dimensions = @getimagesize($sourcePath);
    if (!is_array($dimensions) || (int) ($dimensions[0] ?? 0) < 1 || (int) ($dimensions[1] ?? 0) < 1) {
        throw new RuntimeException('이미지 크기를 확인할 수 없습니다.');
    }
    if ((int) $dimensions[0] * (int) $dimensions[1] > 25000000) {
        throw new RuntimeException('이미지 픽셀 수가 너무 큽니다.');
    }

    $datePath = date('Y/m');
    $directory = SR_ROOT . '/storage/tmp/logo-manager-images/' . $datePath;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('로고 이미지 임시 디렉터리를 만들 수 없습니다.');
    }

    $storedName = sr_upload_random_filename($targetFormat);
    $targetPath = sr_upload_safe_target_path($directory, $storedName);
    sr_upload_assert_target_path_writable($targetPath);

    $storedSourcePath = $sourcePath;
    $reencoded = sr_upload_reencode_image($sourcePath, $targetPath, $targetFormat, [
        'max_pixels' => 25000000,
        'quality' => 88,
    ]);
    if ($reencoded) {
        $storedSourcePath = $targetPath;
    }

    $storedMimeType = sr_upload_detect_mime($storedSourcePath);
    if (!sr_logo_manager_image_mime_is_allowed($storedMimeType)) {
        @unlink($targetPath);
        throw new RuntimeException('저장할 이미지 MIME을 확인할 수 없습니다.');
    }

    $storedDimensions = @getimagesize($storedSourcePath);
    if (is_array($storedDimensions)) {
        $dimensions = $storedDimensions;
    }

    $storageKey = 'logo_manager/images/' . $datePath . '/' . $storedName;
    $stored = sr_storage_put_file($storedSourcePath, $storageKey, [
        'content_type' => $storedMimeType,
    ]);
    $checksum = hash_file('sha256', $storedSourcePath);
    $sizeBytes = filesize($storedSourcePath);
    @unlink($targetPath);

    $storageReference = sr_storage_reference((string) $stored['driver'], $storageKey);
    $publicUrl = (string) ($stored['url'] ?? '');

    return [
        'driver' => (string) $stored['driver'],
        'storage_key' => $storageKey,
        'public_url' => $publicUrl !== '' ? $publicUrl : '/logo-manager/image?file=' . rawurlencode($storageReference),
        'mime_type' => $storedMimeType,
        'size_bytes' => is_int($sizeBytes) ? $sizeBytes : 0,
        'width' => (int) ($dimensions[0] ?? 0),
        'height' => (int) ($dimensions[1] ?? 0),
        'checksum_sha256' => is_string($checksum) ? $checksum : '',
        'reencoded' => $reencoded,
        'original_name' => (string) ($validated['original_name'] ?? ''),
    ];
}

function sr_logo_manager_upload_svg_image(array $validated): array
{
    if (!sr_logo_manager_svg_upload_mime_is_allowed((string) ($validated['mime_type'] ?? ''))) {
        throw new RuntimeException('허용되지 않은 SVG MIME입니다.');
    }

    $sourcePath = (string) ($validated['tmp_name'] ?? '');
    $svg = sr_logo_manager_sanitize_svg_file($sourcePath);
    $dimensions = $svg['dimensions'];
    if ((int) $dimensions['width'] * (int) $dimensions['height'] > 25000000) {
        throw new RuntimeException('SVG 이미지 크기가 너무 큽니다.');
    }

    $datePath = date('Y/m');
    $directory = SR_ROOT . '/storage/tmp/logo-manager-images/' . $datePath;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('로고 이미지 임시 디렉터리를 만들 수 없습니다.');
    }

    $storedName = sr_upload_random_filename('svg');
    $targetPath = sr_upload_safe_target_path($directory, $storedName);
    sr_upload_assert_target_path_writable($targetPath);
    if (file_put_contents($targetPath, $svg['content']) === false) {
        throw new RuntimeException('SVG 파일을 저장할 수 없습니다.');
    }

    $storageKey = 'logo_manager/images/' . $datePath . '/' . $storedName;
    $stored = sr_storage_put_file($targetPath, $storageKey, [
        'content_type' => 'image/svg+xml',
    ]);
    $checksum = hash_file('sha256', $targetPath);
    $sizeBytes = filesize($targetPath);
    @unlink($targetPath);

    $storageReference = sr_storage_reference((string) $stored['driver'], $storageKey);
    $publicUrl = (string) ($stored['url'] ?? '');

    return [
        'driver' => (string) $stored['driver'],
        'storage_key' => $storageKey,
        'public_url' => $publicUrl !== '' ? $publicUrl : '/logo-manager/image?file=' . rawurlencode($storageReference),
        'mime_type' => 'image/svg+xml',
        'size_bytes' => is_int($sizeBytes) ? $sizeBytes : 0,
        'width' => (int) $dimensions['width'],
        'height' => (int) $dimensions['height'],
        'checksum_sha256' => is_string($checksum) ? $checksum : '',
        'reencoded' => true,
        'original_name' => (string) ($validated['original_name'] ?? ''),
    ];
}

function sr_logo_manager_sanitize_svg_file(string $path): array
{
    if (!class_exists('DOMDocument')) {
        throw new RuntimeException('SVG 검증에 필요한 DOM 확장이 설치되어 있지 않습니다.');
    }

    $content = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($content) || $content === '') {
        throw new RuntimeException('SVG 파일을 읽을 수 없습니다.');
    }
    if (strlen($content) > 5242880 || str_contains($content, "\0")) {
        throw new RuntimeException('SVG 파일 내용이 올바르지 않습니다.');
    }
    if (preg_match('/<!DOCTYPE|<!ENTITY/i', $content) === 1) {
        throw new RuntimeException('DOCTYPE 또는 ENTITY가 포함된 SVG는 업로드할 수 없습니다.');
    }

    $previous = libxml_use_internal_errors(true);
    libxml_clear_errors();
    $dom = new DOMDocument('1.0', 'UTF-8');
    $loaded = $dom->loadXML($content, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded || !$dom->documentElement instanceof DOMElement) {
        throw new RuntimeException('SVG XML을 해석할 수 없습니다.');
    }

    $root = $dom->documentElement;
    if (strtolower($root->localName) !== 'svg') {
        throw new RuntimeException('SVG 루트 요소를 확인할 수 없습니다.');
    }

    sr_logo_manager_sanitize_svg_node($root);
    if (!$dom->documentElement instanceof DOMElement || strtolower($dom->documentElement->localName) !== 'svg') {
        throw new RuntimeException('SVG 루트 요소를 확인할 수 없습니다.');
    }

    $dimensions = sr_logo_manager_svg_dimensions($dom->documentElement);
    if ($dimensions['width'] < 1 || $dimensions['height'] < 1) {
        throw new RuntimeException('SVG 이미지 크기를 확인할 수 없습니다.');
    }

    $sanitized = $dom->saveXML($dom->documentElement);
    if (!is_string($sanitized) || $sanitized === '') {
        throw new RuntimeException('SVG 정리본을 만들 수 없습니다.');
    }

    return [
        'content' => $sanitized . "\n",
        'dimensions' => $dimensions,
    ];
}

function sr_logo_manager_sanitize_svg_node(DOMNode $node): void
{
    if ($node instanceof DOMElement) {
        $blockedElements = ['script', 'foreignobject', 'iframe', 'object', 'embed', 'audio', 'video', 'canvas'];
        if (in_array(strtolower($node->localName), $blockedElements, true)) {
            if ($node->parentNode instanceof DOMNode) {
                $node->parentNode->removeChild($node);
            }
            return;
        }

        if (strtolower($node->localName) === 'style' && !sr_logo_manager_svg_style_is_safe($node->textContent)) {
            if ($node->parentNode instanceof DOMNode) {
                $node->parentNode->removeChild($node);
            }
            return;
        }

        sr_logo_manager_sanitize_svg_attributes($node);
    }

    $children = [];
    foreach ($node->childNodes as $child) {
        $children[] = $child;
    }

    foreach ($children as $child) {
        sr_logo_manager_sanitize_svg_node($child);
    }
}

function sr_logo_manager_sanitize_svg_attributes(DOMElement $element): void
{
    $remove = [];
    foreach ($element->attributes as $attribute) {
        $name = strtolower($attribute->name);
        $localName = strtolower($attribute->localName);
        $value = trim($attribute->value);

        if (str_starts_with($localName, 'on') || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            $remove[] = $attribute->name;
            continue;
        }

        if (in_array($localName, ['href', 'src'], true) && !sr_logo_manager_svg_reference_is_safe($value)) {
            $remove[] = $attribute->name;
            continue;
        }

        if ($localName === 'style' && !sr_logo_manager_svg_style_is_safe($value)) {
            $remove[] = $attribute->name;
            continue;
        }

        if (preg_match('/javascript\s*:|data\s*:|vbscript\s*:/i', $value) === 1) {
            $remove[] = $attribute->name;
            continue;
        }

        if (preg_match_all('/url\(([^)]*)\)/i', $value, $matches) > 0) {
            foreach ($matches[1] as $match) {
                if (!sr_logo_manager_svg_reference_is_safe(trim((string) $match, " \t\n\r\0\x0B'\""))) {
                    $remove[] = $attribute->name;
                    break;
                }
            }
        }

        if ($name === 'xmlns:xlink' || $name === 'xmlns') {
            continue;
        }
    }

    foreach (array_unique($remove) as $name) {
        $element->removeAttribute($name);
    }
}

function sr_logo_manager_svg_reference_is_safe(string $value): bool
{
    $value = trim($value);
    return $value === '' || preg_match('/\A#[A-Za-z][A-Za-z0-9_.:-]*\z/', $value) === 1;
}

function sr_logo_manager_svg_style_is_safe(string $value): bool
{
    if (preg_match('/@import|expression\s*\(|javascript\s*:|data\s*:|vbscript\s*:/i', $value) === 1) {
        return false;
    }

    if (preg_match_all('/url\(([^)]*)\)/i', $value, $matches) > 0) {
        foreach ($matches[1] as $match) {
            if (!sr_logo_manager_svg_reference_is_safe(trim((string) $match, " \t\n\r\0\x0B'\""))) {
                return false;
            }
        }
    }

    return true;
}

function sr_logo_manager_svg_dimensions(DOMElement $root): array
{
    $width = sr_logo_manager_svg_dimension_value($root->getAttribute('width'));
    $height = sr_logo_manager_svg_dimension_value($root->getAttribute('height'));
    if ($width > 0 && $height > 0) {
        return ['width' => $width, 'height' => $height];
    }

    $viewBox = preg_split('/[\s,]+/', trim($root->getAttribute('viewBox')));
    if (is_array($viewBox) && count($viewBox) === 4 && is_numeric($viewBox[2]) && is_numeric($viewBox[3])) {
        return [
            'width' => max(0, (int) ceil((float) $viewBox[2])),
            'height' => max(0, (int) ceil((float) $viewBox[3])),
        ];
    }

    return ['width' => 0, 'height' => 0];
}

function sr_logo_manager_svg_dimension_value(string $value): int
{
    $value = trim($value);
    if (preg_match('/\A([0-9]+(?:\.[0-9]+)?)(?:px|pt|pc|mm|cm|in)?\z/i', $value, $matches) !== 1) {
        return 0;
    }

    return max(0, (int) ceil((float) $matches[1]));
}

function sr_logo_manager_image_storage_key_is_valid(string $key): bool
{
    return preg_match('#\Alogo_manager/images/\d{4}/\d{2}/[a-f0-9]{32}\.(?:jpg|png|webp|svg)\z#', $key) === 1;
}

function sr_logo_manager_image_storage_reference(string $reference): ?array
{
    $storage = sr_storage_parse_reference($reference);
    if (!is_array($storage) || !sr_logo_manager_image_storage_key_is_valid((string) $storage['key'])) {
        return null;
    }

    return $storage;
}

function sr_logo_manager_logo_url(array $logo): string
{
    $publicUrl = trim((string) ($logo['public_url'] ?? ''));
    if ($publicUrl !== '' && (sr_is_safe_relative_url($publicUrl) || sr_is_http_url($publicUrl))) {
        return $publicUrl;
    }

    $driver = (string) ($logo['storage_driver'] ?? 'local');
    $key = (string) ($logo['storage_key'] ?? '');
    if (!in_array($driver, ['local', 's3'], true) || !sr_logo_manager_image_storage_key_is_valid($key)) {
        return '';
    }

    $publicUrl = sr_storage_public_url($driver, $key);
    if ($publicUrl !== '') {
        return $publicUrl;
    }

    return '/logo-manager/image?file=' . rawurlencode(sr_storage_reference($driver, $key));
}

function sr_logo_manager_url_for_output(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    return sr_is_http_url($url) ? $url : sr_url($url);
}

function sr_logo_manager_clean_admin_datetime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
    return $date instanceof DateTimeImmutable ? $date->format('Y-m-d H:i:00') : null;
}

function sr_logo_manager_admin_datetime_value(mixed $value): string
{
    if (!is_string($value) || $value === '') {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
    return $date instanceof DateTimeImmutable ? $date->format('Y-m-d\TH:i') : '';
}

function sr_logo_manager_duration_label(mixed $startsAt, mixed $endsAt): string
{
    if (!is_string($startsAt) || $startsAt === '' || !is_string($endsAt) || $endsAt === '') {
        return sr_t('logo_manager::ui.duration.always_or_open');
    }

    try {
        $start = new DateTimeImmutable($startsAt);
        $end = new DateTimeImmutable($endsAt);
    } catch (Throwable) {
        return sr_t('logo_manager::ui.duration.unknown');
    }

    $seconds = $end->getTimestamp() - $start->getTimestamp();
    if ($seconds < 0) {
        return sr_t('logo_manager::ui.duration.invalid');
    }
    if ($seconds === 0) {
        return '0' . sr_t('logo_manager::ui.duration.second');
    }

    $days = intdiv($seconds, 86400);
    $seconds %= 86400;
    $hours = intdiv($seconds, 3600);
    $seconds %= 3600;
    $minutes = intdiv($seconds, 60);
    $seconds %= 60;

    $parts = [];
    if ($days > 0) {
        $parts[] = (string) $days . sr_t('logo_manager::ui.duration.day');
    }
    if ($hours > 0) {
        $parts[] = (string) $hours . sr_t('logo_manager::ui.duration.hour');
    }
    if ($minutes > 0 && count($parts) < 2) {
        $parts[] = (string) $minutes . sr_t('logo_manager::ui.duration.minute');
    }
    if ($seconds > 0 && $parts === []) {
        $parts[] = (string) $seconds . sr_t('logo_manager::ui.duration.second');
    }

    return implode(' ', array_slice($parts, 0, 2));
}

function sr_logo_manager_active_logo(PDO $pdo, string $positionKey, ?string $now = null): ?array
{
    $positionKey = sr_logo_manager_position_key($positionKey, $pdo);
    if (!sr_logo_manager_table_exists($pdo)) {
        return null;
    }

    $now = $now !== null ? $now : sr_now();
    try {
        $stmt = $pdo->prepare(
            "SELECT id, position_key, title, alt_text, link_url, starts_at, ends_at, sort_order,
                    storage_driver, storage_key, public_url, mime_type, width, height
             FROM sr_logo_manager_logos
             WHERE position_key = :position_key
               AND status = 'active'
               AND (starts_at IS NULL OR starts_at <= :now_start)
               AND (ends_at IS NULL OR ends_at >= :now_end)
             ORDER BY CASE WHEN starts_at IS NULL AND ends_at IS NULL THEN 1 ELSE 0 END ASC,
                      CASE WHEN starts_at IS NOT NULL AND ends_at IS NOT NULL THEN 0 ELSE 1 END ASC,
                      CASE
                          WHEN starts_at IS NOT NULL AND ends_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, starts_at, ends_at)
                          ELSE 2147483647
                      END ASC,
                      sort_order ASC,
                      starts_at DESC,
                      id DESC
             LIMIT 1"
        );
        $stmt->execute([
            'position_key' => $positionKey,
            'now_start' => $now,
            'now_end' => $now,
        ]);

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'logo_manager_active_logo_failed');
        return null;
    }
}

function sr_logo_manager_default_logo(PDO $pdo, string $positionKey): ?array
{
    $positionKey = sr_logo_manager_position_key($positionKey, $pdo);
    if (!sr_logo_manager_table_exists($pdo)) {
        return null;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT id, position_key, title, alt_text, link_url, starts_at, ends_at, sort_order,
                    storage_driver, storage_key, public_url, mime_type, width, height
             FROM sr_logo_manager_logos
             WHERE position_key = :position_key
               AND status = 'active'
               AND starts_at IS NULL
               AND ends_at IS NULL
             ORDER BY sort_order ASC, id DESC
             LIMIT 1"
        );
        $stmt->execute(['position_key' => $positionKey]);

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'logo_manager_default_logo_failed');
        return null;
    }
}

function sr_logo_manager_render_logo(PDO $pdo, string $positionKey, ?array $site = null, array $attributes = []): string
{
    $logo = sr_logo_manager_active_logo($pdo, $positionKey);
    if (!is_array($logo)) {
        return '';
    }

    $src = sr_logo_manager_logo_url([
        'public_url' => $logo['public_url'] ?? '',
        'storage_driver' => $logo['storage_driver'] ?? 'local',
        'storage_key' => $logo['storage_key'] ?? '',
    ]);
    if ($src === '') {
        return '';
    }

    if (array_key_exists('alt', $attributes)) {
        $alt = sr_logo_manager_clean_single_line((string) $attributes['alt'], 160);
    } else {
        $alt = trim((string) ($logo['alt_text'] ?? ''));
        if ($alt === '') {
            $alt = is_array($site) ? trim((string) ($site['site_name'] ?? $site['name'] ?? '')) : '';
        }
    }

    $class = sr_logo_manager_clean_single_line((string) ($attributes['class'] ?? 'site-logo-image'), 120);
    $width = (int) ($logo['width'] ?? 0);
    $height = (int) ($logo['height'] ?? 0);

    $html = '<img class="' . sr_e($class) . '" src="' . sr_e(sr_logo_manager_url_for_output($src)) . '" alt="' . sr_e($alt) . '"';
    if ($width > 0 && $height > 0) {
        $html .= ' width="' . sr_e((string) $width) . '" height="' . sr_e((string) $height) . '"';
    }
    $html .= ' loading="eager" decoding="async">';

    return $html;
}

function sr_logo_manager_active_url(PDO $pdo, string $positionKey): string
{
    $logo = sr_logo_manager_active_logo($pdo, $positionKey);
    return is_array($logo) ? sr_logo_manager_logo_url($logo) : '';
}

function sr_logo_manager_favicon_link_tag(PDO $pdo): string
{
    $url = sr_logo_manager_active_url($pdo, 'public.favicon');
    if ($url === '') {
        return '';
    }

    $href = sr_e(sr_logo_manager_url_for_output($url));
    return '<link rel="icon" href="' . $href . '">' . PHP_EOL
        . '<link rel="apple-touch-icon" href="' . $href . '">';
}
