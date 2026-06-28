<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/helpers/common.php';
require_once dirname(__DIR__, 2) . '/core/helpers/upload.php';

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
        'public.app_icon' => [
            'label' => sr_t('logo_manager::position.app_icon.label'),
            'hint' => sr_t('logo_manager::position.app_icon.hint'),
            'max_bytes' => 2097152,
            'surface' => 'global',
        ],
    ];
}

function sr_logo_manager_position_options(?PDO $pdo = null): array
{
    $cache = $GLOBALS['sr_logo_manager_position_options_runtime_cache'] ?? [];
    if (!is_array($cache)) {
        $cache = [];
    }

    $locale = function_exists('sr_locale') ? sr_locale() : 'ko';
    $contractVersion = defined('SR_MODULE_CONTRACT_VERSION') ? SR_MODULE_CONTRACT_VERSION : 'contract-unknown';
    $cacheKey = ($pdo instanceof PDO ? (string) spl_object_id($pdo) : 'no-pdo') . ':' . $locale . ':' . $contractVersion;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $options = sr_logo_manager_default_position_options();
    if (!$pdo instanceof PDO) {
        $cache[$cacheKey] = $options;
        $GLOBALS['sr_logo_manager_position_options_runtime_cache'] = $cache;
        return $cache[$cacheKey];
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

    $cache[$cacheKey] = $options;
    $GLOBALS['sr_logo_manager_position_options_runtime_cache'] = $cache;

    return $cache[$cacheKey];
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

function sr_logo_manager_clean_usage_provider_key(string $providerKey): string
{
    $providerKey = strtolower(trim($providerKey));
    if ($providerKey === 'all') {
        return 'all';
    }
    if (function_exists('sr_is_safe_module_key')) {
        return sr_is_safe_module_key($providerKey) ? $providerKey : '';
    }

    return preg_match('/\A[a-z][a-z0-9_]{0,39}\z/', $providerKey) === 1 ? $providerKey : '';
}

function sr_logo_manager_usage_slot_options(): array
{
    return [
        'top' => '상단',
        'bottom' => '하단',
    ];
}

function sr_logo_manager_clean_usage_slot_key(string $slotKey): string
{
    $slotKey = strtolower(trim($slotKey));
    return isset(sr_logo_manager_usage_slot_options()[$slotKey]) ? $slotKey : '';
}

function sr_logo_manager_layout_usage_options(?PDO $pdo = null): array
{
    $options = [
        'all' => [
            'provider_key' => 'all',
            'label' => sr_t('logo_manager::ui.usage.all'),
        ],
    ];
    if ($pdo instanceof PDO && function_exists('sr_public_layout_options')) {
        foreach (sr_public_layout_options($pdo, true) as $layoutOption) {
            if (!is_array($layoutOption)) {
                continue;
            }

            $providerKey = sr_logo_manager_clean_usage_provider_key((string) ($layoutOption['provider_module_key'] ?? ''));
            if ($providerKey === '' || isset($options[$providerKey])) {
                continue;
            }

            $label = sr_logo_manager_clean_single_line((string) ($layoutOption['provider_label'] ?? $providerKey), 80);
            $options[$providerKey] = [
                'provider_key' => $providerKey,
                'label' => $label !== '' ? $label : $providerKey,
            ];
        }
    }

    if (!isset($options['core'])) {
        $options['core'] = [
            'provider_key' => 'core',
            'label' => sr_t('public_layout.common.provider'),
        ];
    }

    return $options;
}

function sr_logo_manager_position_uses_layout_targets(string $positionKey, array $positionOptions): bool
{
    $positionKey = sr_logo_manager_clean_position_key($positionKey);
    if ($positionKey === '' || !isset($positionOptions[$positionKey])) {
        return false;
    }

    return (string) ($positionOptions[$positionKey]['surface'] ?? '') === 'public';
}

function sr_logo_manager_usage_targets_from_input(mixed $rawTargets, array $usageOptions, array $slotOptions, ?bool &$hasInvalid = null): array
{
    $hasInvalid = false;
    if ($rawTargets === null || $rawTargets === '') {
        return [];
    }
    if (!is_array($rawTargets)) {
        $hasInvalid = true;
        return [];
    }

    $targets = [];
    $seen = [];
    foreach ($rawTargets as $rawProviderKey => $rawSlots) {
        $providerKey = sr_logo_manager_clean_usage_provider_key((string) $rawProviderKey);
        if ($providerKey === '' || !isset($usageOptions[$providerKey])) {
            $hasInvalid = true;
            continue;
        }

        if (!is_array($rawSlots)) {
            $hasInvalid = true;
            continue;
        }

        foreach ($rawSlots as $rawSlotKey) {
            $slotKey = sr_logo_manager_clean_usage_slot_key((string) $rawSlotKey);
            if ($slotKey === '' || !isset($slotOptions[$slotKey])) {
                $hasInvalid = true;
                continue;
            }

            $targetKey = $providerKey . ':' . $slotKey;
            if (isset($seen[$targetKey])) {
                continue;
            }
            $seen[$targetKey] = true;
            $targets[] = [
                'layout_provider_key' => $providerKey,
                'slot_key' => $slotKey,
            ];
        }
    }

    return $targets;
}

function sr_logo_manager_favicon_position_key(): string
{
    return 'public.favicon';
}

function sr_logo_manager_app_icon_position_key(): string
{
    return 'public.app_icon';
}

function sr_logo_manager_public_symbol_position_key(): string
{
    return sr_logo_manager_app_icon_position_key();
}

function sr_logo_manager_use_as_public_symbol_value(string $positionKey, mixed $value): int
{
    if ($positionKey !== sr_logo_manager_public_symbol_position_key()) {
        return 0;
    }

    return (string) $value === '1' ? 1 : 0;
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

function sr_logo_manager_icon_variants_table_exists(PDO $pdo): bool
{
    static $cache = [];
    $cacheKey = method_exists($pdo, 'srTablePrefix') ? $pdo->srTablePrefix() : 'sr_';
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $pdo->query('SELECT 1 FROM sr_logo_manager_icon_variants LIMIT 1');
        $cache[$cacheKey] = true;
    } catch (Throwable) {
        $cache[$cacheKey] = false;
    }

    return $cache[$cacheKey];
}

function sr_logo_manager_usage_targets_table_exists(PDO $pdo): bool
{
    static $cache = [];
    $cacheKey = method_exists($pdo, 'srTablePrefix') ? $pdo->srTablePrefix() : 'sr_';
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $pdo->query('SELECT 1 FROM sr_logo_manager_logo_usage_targets LIMIT 1');
        $cache[$cacheKey] = true;
    } catch (Throwable) {
        $cache[$cacheKey] = false;
    }

    return $cache[$cacheKey];
}

function sr_logo_manager_save_usage_targets(PDO $pdo, int $logoId, array $targets, string $now): void
{
    if ($logoId < 1) {
        throw new InvalidArgumentException('로고 배치 ID가 올바르지 않습니다.');
    }
    if (!sr_logo_manager_usage_targets_table_exists($pdo)) {
        throw new RuntimeException('로고 사용처 업데이트를 먼저 적용하세요.');
    }

    $stmt = $pdo->prepare('DELETE FROM sr_logo_manager_logo_usage_targets WHERE logo_id = :logo_id');
    $stmt->execute(['logo_id' => $logoId]);

    if ($targets === []) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_logo_manager_logo_usage_targets
            (logo_id, layout_provider_key, slot_key, created_at)
         VALUES
            (:logo_id, :layout_provider_key, :slot_key, :created_at)'
    );
    foreach ($targets as $target) {
        $providerKey = sr_logo_manager_clean_usage_provider_key((string) ($target['layout_provider_key'] ?? ''));
        $slotKey = sr_logo_manager_clean_usage_slot_key((string) ($target['slot_key'] ?? ''));
        if ($providerKey === '' || $slotKey === '') {
            continue;
        }

        $stmt->execute([
            'logo_id' => $logoId,
            'layout_provider_key' => $providerKey,
            'slot_key' => $slotKey,
            'created_at' => $now,
        ]);
    }
}

function sr_logo_manager_usage_targets_by_logo_ids(PDO $pdo, array $logoIds): array
{
    $logoIds = array_values(array_unique(array_filter(array_map('intval', $logoIds), static fn (int $logoId): bool => $logoId > 0)));
    if ($logoIds === [] || !sr_logo_manager_usage_targets_table_exists($pdo)) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($logoIds), '?'));
    $stmt = $pdo->prepare(
        'SELECT logo_id, layout_provider_key, slot_key
         FROM sr_logo_manager_logo_usage_targets
         WHERE logo_id IN (' . $placeholders . ')
         ORDER BY layout_provider_key ASC, slot_key ASC'
    );
    $stmt->execute($logoIds);

    $targets = [];
    foreach ($stmt->fetchAll() as $row) {
        $logoId = (int) ($row['logo_id'] ?? 0);
        if ($logoId < 1) {
            continue;
        }
        $targets[$logoId][] = [
            'layout_provider_key' => (string) ($row['layout_provider_key'] ?? ''),
            'slot_key' => (string) ($row['slot_key'] ?? ''),
        ];
    }

    return $targets;
}

function sr_logo_manager_usage_summary(array $targets, array $usageOptions, array $slotOptions, bool $legacyTopFallback = false): string
{
    if ($targets === []) {
        return $legacyTopFallback ? '상단 전체(기존)' : '지정 없음';
    }

    $labels = [];
    foreach ($targets as $target) {
        $providerKey = sr_logo_manager_clean_usage_provider_key((string) ($target['layout_provider_key'] ?? ''));
        $slotKey = sr_logo_manager_clean_usage_slot_key((string) ($target['slot_key'] ?? ''));
        if ($providerKey === '' || $slotKey === '') {
            continue;
        }

        $providerLabel = (string) ($usageOptions[$providerKey]['label'] ?? $providerKey);
        $slotLabel = (string) ($slotOptions[$slotKey] ?? $slotKey);
        $labels[] = $providerLabel . ' ' . $slotLabel;
    }

    return $labels === [] ? ($legacyTopFallback ? '상단 전체(기존)' : '지정 없음') : implode(', ', $labels);
}

function sr_logo_manager_delete_storage_references(array $references): array
{
    $deletedCount = 0;
    $failed = [];
    $seen = [];

    foreach ($references as $reference) {
        if (!is_array($reference)) {
            continue;
        }
        $driver = (string) ($reference['storage_driver'] ?? 'local');
        $key = (string) ($reference['storage_key'] ?? '');
        $referenceKey = $driver . ':' . $key;
        if (isset($seen[$referenceKey])) {
            continue;
        }
        $seen[$referenceKey] = true;
        if (!in_array($driver, ['local', 's3'], true) || !sr_logo_manager_image_storage_key_is_valid($key)) {
            continue;
        }

        if (sr_storage_delete($driver, $key)) {
            $deletedCount++;
        } else {
            $failed[] = $referenceKey;
        }
    }

    return [
        'deleted_count' => $deletedCount,
        'failed' => $failed,
    ];
}

function sr_logo_manager_favicon_reset_setting_key(): string
{
    return 'favicon_reset_at';
}

function sr_logo_manager_favicon_reset_marker(PDO $pdo): string
{
    try {
        $stmt = $pdo->prepare(
            'SELECT s.setting_value
             FROM sr_module_settings s
             INNER JOIN sr_modules m ON m.id = s.module_id
             WHERE m.module_key = :module_key
               AND s.setting_key = :setting_key
             LIMIT 1'
        );
        $stmt->execute([
            'module_key' => 'logo_manager',
            'setting_key' => sr_logo_manager_favicon_reset_setting_key(),
        ]);

        return trim((string) ($stmt->fetchColumn() ?: ''));
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'logo_manager_favicon_reset_marker_failed');
        return '';
    }
}

function sr_logo_manager_mark_favicon_reset(PDO $pdo, string $now): void
{
    $now = trim($now) !== '' ? $now : sr_now();
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'logo_manager' LIMIT 1");
    $stmt->execute();
    $moduleId = (int) ($stmt->fetchColumn() ?: 0);
    if ($moduleId < 1) {
        throw new RuntimeException('로고 매니저 모듈 정보를 찾을 수 없습니다.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_module_settings
            (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'module_id' => $moduleId,
        'setting_key' => sr_logo_manager_favicon_reset_setting_key(),
        'setting_value' => $now,
        'value_type' => 'string',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    if (function_exists('sr_clear_module_settings_cache')) {
        sr_clear_module_settings_cache('logo_manager');
    }
}

function sr_logo_manager_site_setting_reference_count(PDO $pdo, array $target, array $context): int
{
    return count(sr_logo_manager_site_setting_reference_rows($pdo, $target, $context));
}

function sr_logo_manager_site_setting_reference_rows(PDO $pdo, array $target, array $context): array
{
    if ((string) ($target['target_key'] ?? '') !== 'site.name' || !sr_logo_manager_table_exists($pdo)) {
        return [];
    }

    $oldValue = trim((string) ($context['old_value'] ?? ''));
    if ($oldValue === '') {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT id, position_key, alt_text, status, updated_at
         FROM sr_logo_manager_logos
         WHERE alt_text LIKE :old_value ESCAPE \'\\\\\'
         ORDER BY id DESC'
    );
    $stmt->execute([
        'old_value' => '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $oldValue) . '%',
    ]);

    return array_map(static function (array $row): array {
        return [
            'consumer_module_key' => 'logo_manager',
            'reference_type' => 'logo_manager_site_setting_text',
            'reference_id' => 'logo:' . (string) (int) ($row['id'] ?? 0),
            'title' => '로고 대체 텍스트 / ' . (string) ($row['position_key'] ?? ''),
            'target_type' => 'site_setting',
            'target_id' => '0',
            'target_key' => 'site.name',
            'policy_status' => (string) ($row['status'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'message' => '이전 사이트명이 대체 텍스트에 직접 포함되어 있습니다.',
        ];
    }, $stmt->fetchAll());
}

function sr_logo_manager_site_setting_reference_health(PDO $pdo, array $target, array $row, array $context): array
{
    return ['status' => 'stale', 'policy_status' => (string) ($row['policy_status'] ?? '')];
}

function sr_logo_manager_site_setting_reference_admin_url(array $row, array $context): string
{
    return '/admin/logo-manager';
}

function sr_logo_manager_clean_single_line(string $value, int $maxLength): string
{
    return sr_clean_single_line($value, $maxLength);
}

function sr_logo_manager_clean_url(string $value): string
{
    $value = trim($value);
    if ($value === '' || sr_is_safe_relative_url($value) || sr_is_http_url($value)) {
        return $value;
    }

    return '';
}

function sr_logo_manager_clean_sort_order(string $value): ?int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    if (strlen($value) > 20 || preg_match('/\A-?[0-9]+\z/', $value) !== 1) {
        return null;
    }

    return max(-100000, min(100000, (int) $value));
}

function sr_logo_manager_upload_was_provided(mixed $file): bool
{
    return sr_upload_was_provided($file);
}

function sr_logo_manager_image_format_for_mime(string $mimeType): string
{
    return sr_image_format_for_mime($mimeType, true);
}

function sr_logo_manager_image_mime_is_allowed(string $mimeType): bool
{
    return sr_image_mime_is_allowed($mimeType, true);
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
    if (!sr_write_file_atomically($targetPath, (string) $svg['content'])) {
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
    return preg_match('#\Alogo_manager/images/\d{4}/\d{2}/[a-f0-9]{32}\.(?:jpg|png|webp|svg)\z#', $key) === 1
        || preg_match('#\Alogo_manager/icon-variants/\d{4}/\d{2}/[a-f0-9]{32}\.png\z#', $key) === 1;
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
    return sr_clean_admin_datetime($value, false);
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

function sr_logo_manager_icon_size_options(): array
{
    return [
        'favicon_16' => ['label' => 'Favicon 16x16', 'purpose' => 'favicon', 'width' => 16, 'height' => 16],
        'favicon_32' => ['label' => 'Favicon 32x32', 'purpose' => 'favicon', 'width' => 32, 'height' => 32],
        'favicon_48' => ['label' => 'Favicon 48x48', 'purpose' => 'favicon', 'width' => 48, 'height' => 48],
        'apple_touch_180' => ['label' => 'Apple touch 180x180', 'purpose' => 'apple_touch', 'width' => 180, 'height' => 180],
        'pwa_192' => ['label' => 'Android/PWA 192x192', 'purpose' => 'pwa', 'width' => 192, 'height' => 192],
        'pwa_512' => ['label' => 'Android/PWA 512x512', 'purpose' => 'pwa', 'width' => 512, 'height' => 512],
    ];
}

function sr_logo_manager_default_icon_variant_keys(): array
{
    return ['favicon_16', 'favicon_32', 'favicon_48', 'apple_touch_180', 'pwa_192', 'pwa_512'];
}

function sr_logo_manager_icon_variant_keys_from_post(mixed $rawKeys): array
{
    $options = sr_logo_manager_icon_size_options();
    $keys = is_array($rawKeys) ? $rawKeys : [];
    $selected = [];
    foreach ($keys as $rawKey) {
        $key = (string) $rawKey;
        if (isset($options[$key])) {
            $selected[$key] = true;
        }
    }

    return array_keys($selected);
}

function sr_logo_manager_icon_variants_by_logo(PDO $pdo, int $logoId, string $status = 'active'): array
{
    if ($logoId < 1 || !sr_logo_manager_icon_variants_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_logo_manager_icon_variants
         WHERE logo_id = :logo_id
           AND status = :status
         ORDER BY purpose ASC, width ASC, id DESC'
    );
    $stmt->execute([
        'logo_id' => $logoId,
        'status' => $status,
    ]);

    return $stmt->fetchAll();
}

function sr_logo_manager_icon_variant_url(array $variant): string
{
    $publicUrl = trim((string) ($variant['public_url'] ?? ''));
    if ($publicUrl !== '' && (sr_is_safe_relative_url($publicUrl) || sr_is_http_url($publicUrl))) {
        return $publicUrl;
    }

    $driver = (string) ($variant['storage_driver'] ?? 'local');
    $key = (string) ($variant['storage_key'] ?? '');
    if (!in_array($driver, ['local', 's3'], true) || !sr_logo_manager_image_storage_key_is_valid($key)) {
        return '';
    }

    $publicUrl = sr_storage_public_url($driver, $key);
    if ($publicUrl !== '') {
        return $publicUrl;
    }

    return '/logo-manager/image?file=' . rawurlencode(sr_storage_reference($driver, $key));
}

function sr_logo_manager_generate_icon_variants(PDO $pdo, array $logo, array $variantKeys, array $options = []): array
{
    if (!sr_logo_manager_icon_variants_table_exists($pdo)) {
        throw new RuntimeException('아이콘 세트 업데이트를 먼저 적용하세요.');
    }

    $logoId = (int) ($logo['id'] ?? 0);
    if ($logoId < 1 || (string) ($logo['position_key'] ?? '') !== sr_logo_manager_favicon_position_key()) {
        throw new RuntimeException('파비콘 용도 로고에서만 아이콘 세트를 생성할 수 있습니다.');
    }
    if ((string) ($logo['mime_type'] ?? '') === 'image/svg+xml') {
        throw new RuntimeException('SVG 원본은 공유호스팅 환경에서 PNG 파생 아이콘 생성을 지원하지 않습니다.');
    }

    $sourceDriver = (string) ($logo['storage_driver'] ?? 'local');
    $sourceKey = (string) ($logo['storage_key'] ?? '');
    if ($sourceDriver !== 'local') {
        throw new RuntimeException('현재는 로컬 저장소의 원본 로고만 파생 아이콘 생성을 지원합니다.');
    }

    $sourcePath = sr_storage_local_path($sourceKey);
    if (!is_string($sourcePath) || !is_file($sourcePath)) {
        throw new RuntimeException('원본 로고 파일을 찾을 수 없습니다.');
    }

    $sizeOptions = sr_logo_manager_icon_size_options();
    $variantKeys = array_values(array_filter($variantKeys, static fn (string $key): bool => isset($sizeOptions[$key])));
    if ($variantKeys === []) {
        throw new RuntimeException('생성할 아이콘 크기를 선택하세요.');
    }

    $fitMode = in_array((string) ($options['fit_mode'] ?? 'contain'), ['contain', 'cover'], true) ? (string) $options['fit_mode'] : 'contain';
    $backgroundColor = sr_logo_manager_icon_background_color((string) ($options['background_color'] ?? 'transparent'));
    $batchKey = bin2hex(random_bytes(12));
    $datePath = date('Y/m');
    $directory = SR_ROOT . '/storage/tmp/logo-manager-icons/' . $datePath;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('아이콘 임시 디렉터리를 만들 수 없습니다.');
    }

    $generated = [];
    foreach ($variantKeys as $variantKey) {
        $variant = $sizeOptions[$variantKey];
        $storedName = sr_upload_random_filename('png');
        $targetPath = sr_upload_safe_target_path($directory, $storedName);
        sr_upload_assert_target_path_writable($targetPath);

        if (!sr_logo_manager_resize_icon_png($sourcePath, $targetPath, (int) $variant['width'], (int) $variant['height'], $fitMode, $backgroundColor)) {
            @unlink($targetPath);
            throw new RuntimeException('아이콘 이미지를 생성할 수 없습니다. GD 또는 Imagick 이미지 처리 확장을 확인하세요.');
        }

        $storageKey = 'logo_manager/icon-variants/' . $datePath . '/' . $storedName;
        $stored = sr_storage_put_file($targetPath, $storageKey, ['content_type' => 'image/png']);
        $checksum = hash_file('sha256', $targetPath);
        $sizeBytes = filesize($targetPath);
        @unlink($targetPath);

        $storageReference = sr_storage_reference((string) $stored['driver'], $storageKey);
        $publicUrl = (string) ($stored['url'] ?? '');
        $generated[] = [
            'logo_id' => $logoId,
            'batch_key' => $batchKey,
            'variant_key' => $variantKey,
            'purpose' => (string) $variant['purpose'],
            'width' => (int) $variant['width'],
            'height' => (int) $variant['height'],
            'storage_driver' => (string) $stored['driver'],
            'storage_key' => $storageKey,
            'public_url' => $publicUrl !== '' ? $publicUrl : '/logo-manager/image?file=' . rawurlencode($storageReference),
            'mime_type' => 'image/png',
            'size_bytes' => is_int($sizeBytes) ? $sizeBytes : 0,
            'checksum_sha256' => is_string($checksum) ? $checksum : '',
        ];
    }

    return $generated;
}

function sr_logo_manager_save_icon_variants(PDO $pdo, int $accountId, int $logoId, array $variants, bool $activate): int
{
    if ($variants === []) {
        return 0;
    }

    $now = sr_now();
    $pdo->beginTransaction();
    try {
        if ($activate) {
            $disable = $pdo->prepare("UPDATE sr_logo_manager_icon_variants SET status = 'disabled', updated_at = :updated_at WHERE logo_id = :logo_id AND status = 'active'");
            $disable->execute(['updated_at' => $now, 'logo_id' => $logoId]);
        }

        $insert = $pdo->prepare(
            'INSERT INTO sr_logo_manager_icon_variants
                (logo_id, batch_key, variant_key, purpose, width, height, storage_driver, storage_key, public_url, mime_type,
                 size_bytes, checksum_sha256, status, created_by_account_id, created_at, updated_at)
             VALUES
                (:logo_id, :batch_key, :variant_key, :purpose, :width, :height, :storage_driver, :storage_key, :public_url, :mime_type,
                 :size_bytes, :checksum_sha256, :status, :created_by_account_id, :created_at, :updated_at)'
        );
        foreach ($variants as $variant) {
            $insert->execute([
                'logo_id' => $logoId,
                'batch_key' => (string) $variant['batch_key'],
                'variant_key' => (string) $variant['variant_key'],
                'purpose' => (string) $variant['purpose'],
                'width' => (int) $variant['width'],
                'height' => (int) $variant['height'],
                'storage_driver' => (string) $variant['storage_driver'],
                'storage_key' => (string) $variant['storage_key'],
                'public_url' => (string) $variant['public_url'],
                'mime_type' => (string) $variant['mime_type'],
                'size_bytes' => (int) $variant['size_bytes'],
                'checksum_sha256' => (string) $variant['checksum_sha256'],
                'status' => $activate ? 'active' : 'disabled',
                'created_by_account_id' => $accountId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return count($variants);
}

function sr_logo_manager_icon_background_color(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === '' || $value === 'transparent') {
        return 'transparent';
    }

    return preg_match('/\A#[0-9a-f]{6}\z/', $value) === 1 ? $value : 'transparent';
}

function sr_logo_manager_resize_icon_png(string $sourcePath, string $targetPath, int $width, int $height, string $fitMode, string $backgroundColor): bool
{
    if (!extension_loaded('gd')) {
        return false;
    }

    $info = @getimagesize($sourcePath);
    if (!is_array($info)) {
        return false;
    }

    $mime = strtolower((string) ($info['mime'] ?? ''));
    if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
        $source = @imagecreatefromjpeg($sourcePath);
    } elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
        $source = @imagecreatefrompng($sourcePath);
    } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $source = @imagecreatefromwebp($sourcePath);
    } else {
        return false;
    }
    if (!$source instanceof GdImage) {
        return false;
    }

    $sourceWidth = max(1, (int) ($info[0] ?? 0));
    $sourceHeight = max(1, (int) ($info[1] ?? 0));
    $canvas = imagecreatetruecolor($width, $height);
    if (!$canvas instanceof GdImage) {
        imagedestroy($source);
        return false;
    }

    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    if ($backgroundColor === 'transparent') {
        $fill = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    } else {
        $red = hexdec(substr($backgroundColor, 1, 2));
        $green = hexdec(substr($backgroundColor, 3, 2));
        $blue = hexdec(substr($backgroundColor, 5, 2));
        $fill = imagecolorallocatealpha($canvas, $red, $green, $blue, 0);
    }
    imagefill($canvas, 0, 0, $fill);

    $scale = $fitMode === 'cover'
        ? max($width / $sourceWidth, $height / $sourceHeight)
        : min($width / $sourceWidth, $height / $sourceHeight);
    $targetWidth = max(1, (int) round($sourceWidth * $scale));
    $targetHeight = max(1, (int) round($sourceHeight * $scale));
    $targetX = (int) floor(($width - $targetWidth) / 2);
    $targetY = (int) floor(($height - $targetHeight) / 2);

    imagealphablending($canvas, true);
    $copied = imagecopyresampled($canvas, $source, $targetX, $targetY, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
    imagesavealpha($canvas, true);
    $written = $copied && function_exists('imagepng') && imagepng($canvas, $targetPath);
    imagedestroy($source);
    imagedestroy($canvas);

    return $written;
}

function sr_logo_manager_active_logo(PDO $pdo, string $positionKey, ?string $now = null, array $usageTarget = []): ?array
{
    $positionKey = sr_logo_manager_position_key($positionKey, $pdo);
    if (!sr_logo_manager_table_exists($pdo)) {
        return null;
    }

    $now = $now !== null ? $now : sr_now();
    $usageProviderKey = sr_logo_manager_clean_usage_provider_key((string) ($usageTarget['layout_provider_key'] ?? $usageTarget['provider_key'] ?? ''));
    $usageSlotKey = sr_logo_manager_clean_usage_slot_key((string) ($usageTarget['slot_key'] ?? $usageTarget['usage_slot_key'] ?? ''));
    $hasUsageTarget = $usageProviderKey !== '' && $usageSlotKey !== '';
    if ($hasUsageTarget && !sr_logo_manager_usage_targets_table_exists($pdo) && $usageSlotKey === 'bottom') {
        return null;
    }

    try {
        $params = [
            'position_key' => $positionKey,
            'now_start' => $now,
            'now_end' => $now,
        ];
        $selectUsageRankSql = '';
        $whereUsageSql = '';
        $orderUsageSql = '';
        if ($hasUsageTarget && sr_logo_manager_usage_targets_table_exists($pdo)) {
            $allowUntargetedFallback = array_key_exists('allow_untargeted_fallback', $usageTarget)
                ? !empty($usageTarget['allow_untargeted_fallback'])
                : $usageSlotKey === 'top';
            $selectUsageRankSql = ",
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM sr_logo_manager_logo_usage_targets lm_usage_match
                            WHERE lm_usage_match.logo_id = sr_logo_manager_logos.id
                              AND lm_usage_match.layout_provider_key = :usage_provider_key_rank
                              AND lm_usage_match.slot_key = :usage_slot_key_rank
                        ) THEN 0
                        WHEN EXISTS (
                            SELECT 1
                            FROM sr_logo_manager_logo_usage_targets lm_usage_all_rank
                            WHERE lm_usage_all_rank.logo_id = sr_logo_manager_logos.id
                              AND lm_usage_all_rank.layout_provider_key = 'all'
                              AND lm_usage_all_rank.slot_key = :usage_slot_key_all_rank
                        ) THEN 1
                        WHEN :allow_untargeted_fallback_rank = 1 THEN 2
                        ELSE 1
                    END AS usage_rank";
            $whereUsageSql = "
               AND (
                    EXISTS (
                        SELECT 1
                        FROM sr_logo_manager_logo_usage_targets lm_usage_filter
                        WHERE lm_usage_filter.logo_id = sr_logo_manager_logos.id
                          AND lm_usage_filter.layout_provider_key IN (:usage_provider_key_filter, 'all')
                          AND lm_usage_filter.slot_key = :usage_slot_key_filter
                    )
                    OR (
                        :allow_untargeted_fallback = 1
                        AND NOT EXISTS (
                            SELECT 1
                            FROM sr_logo_manager_logo_usage_targets lm_usage_any
                            WHERE lm_usage_any.logo_id = sr_logo_manager_logos.id
                        )
                    )
               )";
            $orderUsageSql = 'usage_rank ASC,';
            $params['usage_provider_key_rank'] = $usageProviderKey;
            $params['usage_slot_key_rank'] = $usageSlotKey;
            $params['usage_slot_key_all_rank'] = $usageSlotKey;
            $params['allow_untargeted_fallback_rank'] = $allowUntargetedFallback ? 1 : 0;
            $params['usage_provider_key_filter'] = $usageProviderKey;
            $params['usage_slot_key_filter'] = $usageSlotKey;
            $params['allow_untargeted_fallback'] = $allowUntargetedFallback ? 1 : 0;
        }

        $stmt = $pdo->prepare(
            "SELECT id, position_key, title, alt_text, link_url, use_as_public_symbol, starts_at, ends_at, sort_order,
                    storage_driver, storage_key, public_url, mime_type, width, height" . $selectUsageRankSql . "
             FROM sr_logo_manager_logos
             WHERE position_key = :position_key
               AND status = 'active'
               AND (starts_at IS NULL OR starts_at <= :now_start)
               AND (ends_at IS NULL OR ends_at >= :now_end)" . $whereUsageSql . "
             ORDER BY " . $orderUsageSql . " CASE WHEN starts_at IS NULL AND ends_at IS NULL THEN 1 ELSE 0 END ASC,
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
        $stmt->execute($params);

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'logo_manager_active_logo_failed');
        return null;
    }
}

function sr_logo_manager_render_logo(PDO $pdo, string $positionKey, ?array $site = null, array $attributes = []): string
{
    $usageTarget = [];
    $layoutProviderKey = sr_logo_manager_clean_usage_provider_key((string) ($attributes['layout_provider_key'] ?? $attributes['provider_key'] ?? ''));
    $usageSlotKey = sr_logo_manager_clean_usage_slot_key((string) ($attributes['usage_slot_key'] ?? $attributes['slot_key'] ?? ''));
    if ($layoutProviderKey !== '' && $usageSlotKey !== '') {
        $usageTarget = [
            'layout_provider_key' => $layoutProviderKey,
            'slot_key' => $usageSlotKey,
        ];
        if (array_key_exists('allow_untargeted_fallback', $attributes)) {
            $usageTarget['allow_untargeted_fallback'] = !empty($attributes['allow_untargeted_fallback']);
        }
    }

    $logo = sr_logo_manager_active_logo($pdo, $positionKey, null, $usageTarget);
    if (!is_array($logo) && isset($attributes['fallback_position_key'])) {
        $fallbackPositionKey = sr_logo_manager_clean_position_key((string) $attributes['fallback_position_key']);
        if ($fallbackPositionKey !== '' && $fallbackPositionKey !== sr_logo_manager_clean_position_key($positionKey)) {
            $logo = sr_logo_manager_active_logo($pdo, $fallbackPositionKey, null, $usageTarget);
        }
    }
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

function sr_logo_manager_render_public_symbol_logo(PDO $pdo, ?array $site = null, array $attributes = []): string
{
    $logo = sr_logo_manager_public_symbol_logo($pdo);
    if (!is_array($logo)) {
        return '';
    }

    $src = sr_logo_manager_logo_url($logo);
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

function sr_logo_manager_public_symbol_logo(PDO $pdo, ?string $now = null): ?array
{
    if (!sr_logo_manager_table_exists($pdo)) {
        return null;
    }

    $now = $now !== null ? $now : sr_now();
    try {
        $stmt = $pdo->prepare(
            "SELECT id, position_key, title, alt_text, link_url, use_as_public_symbol, starts_at, ends_at, sort_order,
                    storage_driver, storage_key, public_url, mime_type, width, height
             FROM sr_logo_manager_logos
             WHERE position_key = :position_key
               AND use_as_public_symbol = 1
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
            'position_key' => sr_logo_manager_public_symbol_position_key(),
            'now_start' => $now,
            'now_end' => $now,
        ]);

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'logo_manager_public_symbol_logo_failed');
        return null;
    }
}

function sr_logo_manager_public_symbol_url(PDO $pdo): string
{
    $logo = sr_logo_manager_public_symbol_logo($pdo);
    return is_array($logo) ? sr_logo_manager_logo_url($logo) : '';
}

function sr_logo_manager_favicon_cache_version(PDO $pdo): string
{
    if (!sr_logo_manager_table_exists($pdo)) {
        return '0';
    }

    $versionParts = [sr_logo_manager_favicon_reset_marker($pdo)];
    try {
        $stmt = $pdo->prepare(
            'SELECT MAX(updated_at) AS updated_at, MAX(id) AS max_id
             FROM sr_logo_manager_logos
             WHERE position_key = :position_key'
        );
        $stmt->execute(['position_key' => sr_logo_manager_favicon_position_key()]);
        $row = $stmt->fetch();
        if (is_array($row)) {
            $versionParts[] = (string) ($row['updated_at'] ?? '');
            $versionParts[] = (string) ($row['max_id'] ?? '');
        }
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'logo_manager_favicon_logo_version_failed');
    }

    if (sr_logo_manager_icon_variants_table_exists($pdo)) {
        try {
            $stmt = $pdo->prepare(
                'SELECT MAX(v.updated_at) AS updated_at, MAX(v.id) AS max_id
                 FROM sr_logo_manager_icon_variants v
                 INNER JOIN sr_logo_manager_logos l ON l.id = v.logo_id
                 WHERE l.position_key = :position_key'
            );
            $stmt->execute(['position_key' => sr_logo_manager_favicon_position_key()]);
            $row = $stmt->fetch();
            if (is_array($row)) {
                $versionParts[] = (string) ($row['updated_at'] ?? '');
                $versionParts[] = (string) ($row['max_id'] ?? '');
            }
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'logo_manager_favicon_variant_version_failed');
        }
    }

    $versionSource = trim(implode('|', array_filter($versionParts, static fn (string $part): bool => $part !== '')));
    if ($versionSource === '') {
        return '0';
    }

    return substr(hash('sha256', $versionSource), 0, 16);
}

function sr_logo_manager_url_with_cache_version(string $url, string $version): string
{
    $url = trim($url);
    $version = preg_replace('/[^A-Za-z0-9._-]/', '', trim($version)) ?? '';
    if ($url === '' || $version === '' || str_starts_with($url, 'data:')) {
        return $url;
    }

    $fragment = '';
    $fragmentPosition = strpos($url, '#');
    if ($fragmentPosition !== false) {
        $fragment = substr($url, $fragmentPosition);
        $url = substr($url, 0, $fragmentPosition);
    }

    $separator = str_contains($url, '?') ? '&' : '?';
    return $url . $separator . 'v=' . rawurlencode($version) . $fragment;
}

function sr_logo_manager_favicon_link_tag(PDO $pdo): string
{
    $cacheVersion = sr_logo_manager_favicon_cache_version($pdo);
    $logo = sr_logo_manager_active_logo($pdo, sr_logo_manager_favicon_position_key());
    if (!is_array($logo)) {
        return '';
    }

    $variants = sr_logo_manager_icon_variants_by_logo($pdo, (int) ($logo['id'] ?? 0));
    if ($variants !== []) {
        $html = [];
        foreach ($variants as $variant) {
            $url = sr_logo_manager_icon_variant_url($variant);
            if ($url === '') {
                continue;
            }
            $url = sr_logo_manager_url_with_cache_version($url, $cacheVersion);
            $purpose = (string) ($variant['purpose'] ?? '');
            $sizes = (string) (int) ($variant['width'] ?? 0) . 'x' . (string) (int) ($variant['height'] ?? 0);
            $href = sr_e(sr_logo_manager_url_for_output($url));
            if ($purpose === 'apple_touch') {
                $html[] = '<link rel="apple-touch-icon" sizes="' . sr_e($sizes) . '" href="' . $href . '">';
            } else {
                $html[] = '<link rel="icon" type="image/png" sizes="' . sr_e($sizes) . '" href="' . $href . '">';
            }
        }
        if ($html !== []) {
            return implode(PHP_EOL, $html);
        }
    }

    $url = sr_logo_manager_logo_url($logo);
    if ($url === '') {
        return '';
    }

    $url = sr_logo_manager_url_with_cache_version($url, $cacheVersion);
    $href = sr_e(sr_logo_manager_url_for_output($url));
    return '<link rel="icon" href="' . $href . '">' . PHP_EOL
        . '<link rel="apple-touch-icon" href="' . $href . '">';
}
