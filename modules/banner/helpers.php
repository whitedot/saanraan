<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/helpers/common.php';
require_once dirname(__DIR__, 2) . '/core/helpers/upload.php';

function sr_banner_clean_single_line(string $value, int $maxLength): string
{
    return sr_clean_single_line($value, $maxLength);
}

function sr_banner_clean_text(string $value, int $maxLength): string
{
    return sr_clean_text($value, $maxLength);
}

function sr_banner_content_type_values(): array
{
    return ['text', 'image', 'html'];
}

function sr_banner_content_type(string $value): string
{
    $value = strtolower(trim($value));
    return in_array($value, sr_banner_content_type_values(), true) ? $value : 'text';
}

function sr_banner_content_type_label(string $value): string
{
    return match (sr_banner_content_type($value)) {
        'image' => '이미지',
        'html' => 'HTML 코드',
        default => '텍스트',
    };
}

function sr_banner_clean_html_code(string $value, int $maxLength = 10000): string
{
    $value = sr_clean_text($value, $maxLength);
    return str_replace("\0", '', $value);
}

function sr_banner_html_code_is_safe_to_store(string $value): bool
{
    return $value === '' || preg_match('/<\?(?:php|=)?/i', $value) !== 1;
}

function sr_banner_content_select_sql(PDO $pdo, string $alias = 'b'): string
{
    $alias = preg_match('/\A[a-z][a-z0-9_]*\z/i', $alias) === 1 ? $alias : 'b';
    return $alias . '.content_type AS content_type, ' . $alias . '.body_text, ' . $alias . '.html_code AS html_code';
}

function sr_banner_clean_url(string $value): string
{
    $value = trim($value);
    if ($value === '' || sr_is_safe_relative_url($value) || sr_is_http_url($value)) {
        return $value;
    }

    return '';
}

function sr_banner_clean_image_url(string $value): string
{
    $value = trim($value);
    if ($value === '' || sr_is_safe_relative_url($value) || sr_is_http_url($value)) {
        return $value;
    }

    return '';
}

function sr_banner_image_upload_max_bytes(): int
{
    return 5242880;
}

function sr_banner_format_bytes(int $bytes): string
{
    return sr_format_bytes($bytes);
}

function sr_banner_image_upload_was_provided(mixed $file): bool
{
    return sr_upload_was_provided($file);
}

function sr_banner_image_format_for_mime(string $mimeType): string
{
    return sr_image_format_for_mime($mimeType);
}

function sr_banner_image_mime_is_allowed(string $mimeType): bool
{
    return sr_image_mime_is_allowed($mimeType);
}

function sr_banner_upload_image(array $file): ?array
{
    if (!sr_banner_image_upload_was_provided($file)) {
        return null;
    }

    $validated = sr_upload_validate_file($file, [
        'max_bytes' => sr_banner_image_upload_max_bytes(),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
    ]);

    $targetFormat = sr_banner_image_format_for_mime((string) $validated['mime_type']);
    if ($targetFormat === '') {
        throw new RuntimeException('허용되지 않은 이미지 형식입니다.');
    }

    $datePath = date('Y/m');
    $directory = SR_ROOT . '/storage/tmp/banner-images/' . $datePath;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('배너 이미지 저장 디렉터리를 만들 수 없습니다.');
    }

    $storedName = sr_upload_random_filename($targetFormat);
    $targetPath = sr_upload_safe_target_path($directory, $storedName);
    sr_upload_assert_target_path_writable($targetPath);

    if (!sr_upload_reencode_image((string) $validated['tmp_name'], $targetPath, $targetFormat, [
        'max_pixels' => 25000000,
        'quality' => 85,
    ])) {
        throw new RuntimeException('이미지 재인코딩에 실패했습니다.');
    }

    $storedMimeType = sr_upload_detect_mime($targetPath);
    if (!sr_banner_image_mime_is_allowed($storedMimeType)) {
        @unlink($targetPath);
        throw new RuntimeException('저장된 이미지 MIME을 확인할 수 없습니다.');
    }

    $storageKey = 'banner/images/' . $datePath . '/' . $storedName;
    $stored = sr_storage_put_file($targetPath, $storageKey, [
        'content_type' => $storedMimeType,
    ]);
    @unlink($targetPath);

    $storageReference = sr_storage_reference((string) $stored['driver'], $storageKey);
    $publicUrl = (string) ($stored['url'] ?? '');

    return [
        'driver' => (string) $stored['driver'],
        'key' => $storageKey,
        'path' => (string) ($stored['path'] ?? ''),
        'storage_key' => $storageKey,
        'url' => $publicUrl !== '' ? $publicUrl : '/banner/image?file=' . rawurlencode($storageReference),
    ];
}

function sr_banner_image_storage_path(string $storageKey): ?string
{
    $storageKey = trim($storageKey);
    if (preg_match('#\A\d{4}/\d{2}/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $storageKey) !== 1) {
        return null;
    }

    $storageRoot = realpath(SR_ROOT . '/storage/banner/images');
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

function sr_banner_image_storage_reference(string $reference): ?array
{
    $storage = sr_storage_parse_reference($reference);
    if (!is_array($storage) || !sr_banner_image_storage_key_is_valid((string) $storage['key'])) {
        $legacyKey = 'banner/images/' . ltrim($reference, '/');
        $storage = sr_storage_parse_reference($legacyKey);
    }

    if (!is_array($storage)) {
        return null;
    }

    if (!sr_banner_image_storage_key_is_valid((string) $storage['key'])) {
        return null;
    }

    return $storage;
}

function sr_banner_image_storage_key_is_valid(string $key): bool
{
    return preg_match('#\Abanner/images/\d{4}/\d{2}/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $key) === 1;
}

function sr_banner_delete_uploaded_image(array $uploadedImage): void
{
    $driver = (string) ($uploadedImage['driver'] ?? '');
    $key = (string) ($uploadedImage['key'] ?? '');
    if ($driver !== '' && $key !== '') {
        sr_storage_delete($driver, $key);
        return;
    }

    if (is_string($uploadedImage['path'] ?? null) && (string) $uploadedImage['path'] !== '') {
        @unlink((string) $uploadedImage['path']);
    }
}

function sr_banner_clean_admin_datetime(string $value): ?string
{
    return sr_clean_admin_datetime($value, false);
}

function sr_banner_admin_datetime_value(mixed $value): string
{
    if (!is_string($value) || $value === '') {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
    return $date instanceof DateTimeImmutable ? $date->format('Y-m-d\TH:i') : '';
}

function sr_banner_builtin_targets(): array
{
    return [
        [
            'module_key' => 'core',
            'point_key' => 'site.layout',
            'slot_key' => 'before_layout',
            'placement_kind' => 'inline',
            'label' => 'core / 전체 / 상단',
            'source' => 'core',
        ],
        [
            'module_key' => 'core',
            'point_key' => 'site.layout',
            'slot_key' => 'after_layout',
            'placement_kind' => 'inline',
            'label' => 'core / 전체 / 하단',
            'source' => 'core',
        ],
    ];
}

function sr_banner_layout_targets(PDO $pdo): array
{
    $targets = [];
    $seen = [];
    foreach (sr_public_layout_options($pdo) as $layoutOption) {
        if (!is_array($layoutOption)) {
            continue;
        }

        $providerModuleKey = (string) ($layoutOption['provider_module_key'] ?? '');
        if ($providerModuleKey === 'core' || !sr_is_safe_module_key($providerModuleKey) || isset($seen[$providerModuleKey])) {
            continue;
        }

        $seen[$providerModuleKey] = true;
        $providerLabel = sr_banner_target_service_label($providerModuleKey);
        $targets[] = [
            'module_key' => $providerModuleKey,
            'point_key' => $providerModuleKey . '.layout',
            'slot_key' => 'before_layout',
            'placement_kind' => 'inline',
            'label' => $providerLabel . ' / 레이아웃 / 상단',
            'source' => 'layout-options.php',
        ];
        $targets[] = [
            'module_key' => $providerModuleKey,
            'point_key' => $providerModuleKey . '.layout',
            'slot_key' => 'before_footer',
            'placement_kind' => 'inline',
            'label' => $providerLabel . ' / 레이아웃 / 하단',
            'source' => 'layout-options.php',
        ];
    }

    return $targets;
}

function sr_banner_available_targets(PDO $pdo): array
{
    $targets = sr_banner_builtin_targets();
    foreach (sr_banner_layout_targets($pdo) as $target) {
        $targets[] = $target;
    }

    foreach (sr_enabled_module_contract_files($pdo, 'extension-points.php', []) as $moduleKey => $file) {
        $points = sr_load_module_contract_file($moduleKey, $file);
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
            if (!sr_module_contract_key_has_module_prefix((string) $moduleKey, $pointKey)) {
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

                $placementKind = sr_banner_placement_kind((string) ($slot['banner_kind'] ?? $slot['placement_kind'] ?? 'inline'));
                $targets[] = [
                    'module_key' => $moduleKey,
                    'point_key' => $pointKey,
                    'slot_key' => $slotKey,
                    'placement_kind' => $placementKind,
                    'label' => $moduleKey . ' / ' . $pointLabel . ' / ' . (string) ($slot['label'] ?? $slotKey) . ' (' . sr_banner_placement_kind_label($placementKind) . ')',
                    'source' => 'extension-points.php',
                ];
            }
        }
    }

    return $targets;
}

function sr_banner_target_option_value(array $target): string
{
    return (string) $target['module_key'] . '|' . (string) $target['point_key'] . '|' . (string) $target['slot_key'];
}

function sr_banner_target_admin_label(array $target): string
{
    $pointKey = (string) ($target['point_key'] ?? '');
    $slotKey = (string) ($target['slot_key'] ?? '');

    $pointLabels = [
        'site.home' => '홈',
        'site.layout' => '전체',
        'content.layout' => '콘텐츠 레이아웃',
        'community.layout' => '커뮤니티 레이아웃',
        'quiz.layout' => '퀴즈·테스트 레이아웃',
        'survey.layout' => '설문·여론조사 레이아웃',
        'content.view' => '콘텐츠 상세',
        'community.board.list' => '게시판 목록',
        'community.post.view' => '게시글 보기',
        'community.post.form' => '게시글 작성',
        'member.login' => '로그인',
        'member.register' => '회원가입',
    ];
    $slotLabels = [
        'before_layout' => '상단',
        'after_layout' => '하단',
        'before_footer' => '하단',
        'before_content' => '본문 위',
        'after_content' => '본문 아래',
        'before_list' => '목록 위',
        'after_list' => '목록 아래',
        'before_comments' => '댓글 위',
        'after_comments' => '댓글 아래',
        'before_form' => '폼 위',
        'after_form' => '폼 아래',
    ];

    $pointLabel = (string) ($pointLabels[$pointKey] ?? sr_banner_clean_single_line((string) ($target['point_label'] ?? $pointKey), 80));
    $slotLabel = (string) ($slotLabels[$slotKey] ?? sr_banner_clean_single_line((string) ($target['slot_label'] ?? $slotKey), 80));
    $label = trim($pointLabel . ' - ' . $slotLabel);

    return $label !== '-' && $label !== '' ? $label : sr_banner_target_option_value($target);
}

function sr_banner_target_service_key(array $target): string
{
    $moduleKey = (string) ($target['module_key'] ?? '');
    return sr_is_safe_module_key($moduleKey) ? $moduleKey : '';
}

function sr_banner_target_service_label(string $serviceKey): string
{
    if ($serviceKey === sr_banner_public_target_option_value()) {
        return '공용';
    }

    $labels = [
        'core' => '전체',
        'content' => '콘텐츠',
        'community' => '커뮤니티',
        'quiz' => '퀴즈·테스트',
        'survey' => '설문·여론조사',
        'member' => '회원',
    ];
    if (isset($labels[$serviceKey])) {
        return $labels[$serviceKey];
    }

    $metadata = sr_module_metadata($serviceKey);
    $name = (string) ($metadata['name'] ?? '');
    return $name !== '' ? $name : $serviceKey;
}

function sr_banner_target_service_options(array $targets, bool $includePublic = true): array
{
    $options = [];
    if ($includePublic) {
        $options[sr_banner_public_target_option_value()] = sr_banner_target_service_label(sr_banner_public_target_option_value());
    }

    foreach ($targets as $target) {
        $serviceKey = sr_banner_target_service_key($target);
        if ($serviceKey !== '' && !isset($options[$serviceKey])) {
            $options[$serviceKey] = sr_banner_target_service_label($serviceKey);
        }
    }

    return $options;
}

function sr_banner_selected_target_service_key(string $targetOption): string
{
    if (sr_banner_is_public_target_option($targetOption)) {
        return sr_banner_public_target_option_value();
    }

    $target = sr_banner_target_from_option($targetOption);
    return is_array($target) ? sr_banner_target_service_key($target) : '';
}

function sr_banner_normalize_posted_target_option(array $targets, string $serviceKey, string $detailOption, string $legacyOption): array
{
    $serviceKey = trim($serviceKey);
    $detailOption = trim($detailOption);
    $legacyOption = trim($legacyOption);

    if ($serviceKey === '' && $detailOption === '') {
        $detailOption = $legacyOption;
        $serviceKey = sr_banner_selected_target_service_key($detailOption);
    }

    if ($serviceKey === sr_banner_public_target_option_value()) {
        return [
            'option' => sr_banner_public_target_option_value(),
            'is_public' => true,
            'target' => null,
            'error' => '',
        ];
    }

    if (!sr_is_safe_module_key($serviceKey)) {
        return [
            'option' => $detailOption,
            'is_public' => false,
            'target' => null,
            'error' => '노출 위치 서비스를 선택하세요.',
        ];
    }

    $target = sr_banner_find_target($targets, $detailOption);
    if ($target === null || sr_banner_target_service_key($target) !== $serviceKey) {
        return [
            'option' => $detailOption,
            'is_public' => false,
            'target' => null,
            'error' => '선택한 서비스에 속한 상세 노출 위치를 선택하세요.',
        ];
    }

    return [
        'option' => sr_banner_target_option_value($target),
        'is_public' => false,
        'target' => $target,
        'error' => '',
    ];
}

function sr_banner_public_target_option_value(): string
{
    return '__public__';
}

function sr_banner_is_public_target_option(string $option): bool
{
    return $option === sr_banner_public_target_option_value();
}

function sr_banner_find_target(array $targets, string $option): ?array
{
    foreach ($targets as $target) {
        if (sr_banner_target_option_value($target) === $option) {
            return $target;
        }
    }

    return null;
}

function sr_banner_target_from_option(string $option): ?array
{
    $parts = explode('|', $option);
    if (count($parts) !== 3) {
        return null;
    }

    return sr_banner_target_from_row([
        'module_key' => $parts[0],
        'point_key' => $parts[1],
        'slot_key' => $parts[2],
    ]);
}

function sr_banner_target_labels(array $targets): array
{
    $labels = [];

    foreach ($targets as $target) {
        $labels[sr_banner_target_option_value($target)] = sr_banner_target_admin_label($target);
    }

    return $labels;
}

function sr_banner_placement_kind_values(): array
{
    return ['public', 'inline', 'compact', 'sidebar', 'hero', 'wide'];
}

function sr_banner_placement_kind(string $placementKind): string
{
    $placementKind = strtolower(trim($placementKind));
    return in_array($placementKind, sr_banner_placement_kind_values(), true) ? $placementKind : 'inline';
}

function sr_banner_placement_kind_label(string $placementKind): string
{
    $labels = [
        'public' => '공용',
        'inline' => '본문형',
        'compact' => '좁은 영역',
        'sidebar' => '사이드바',
        'hero' => '히어로',
        'wide' => '와이드',
    ];

    return (string) ($labels[sr_banner_placement_kind($placementKind)] ?? $placementKind);
}

function sr_banner_target_placement_kind(?array $target, bool $isPublicBanner = false): string
{
    if ($isPublicBanner || $target === null) {
        return 'public';
    }

    return sr_banner_placement_kind((string) ($target['placement_kind'] ?? 'inline'));
}

function sr_banner_subject_target_type_for_target(?array $target): string
{
    if ($target === null) {
        return '';
    }

    $moduleKey = (string) ($target['module_key'] ?? '');
    $pointKey = (string) ($target['point_key'] ?? '');

    if ($moduleKey === 'content' && $pointKey === 'content.view') {
        return 'content';
    }

    if ($moduleKey === 'community' && in_array($pointKey, ['community.board.list', 'community.post.form'], true)) {
        return 'community_board';
    }

    if ($moduleKey === 'community' && $pointKey === 'community.post.view') {
        return 'community_post';
    }

    return '';
}

function sr_banner_subject_target_contract_helper_path(string $moduleKey, array $target): string
{
    $helpers = (string) ($target['helpers'] ?? '');
    if ($helpers === '' || preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helpers) !== 1) {
        return '';
    }

    $path = SR_ROOT . '/modules/' . $moduleKey . '/' . $helpers;
    return is_file($path) ? $path : '';
}

function sr_banner_subject_target_contracts(PDO $pdo): array
{
    $contracts = [];
    foreach (sr_enabled_module_contract_files($pdo, 'coupon-targets.php', ['banner']) as $moduleKey => $file) {
        $contractTargets = sr_load_module_contract_file($moduleKey, $file);
        if (!is_array($contractTargets)) {
            continue;
        }

        foreach ($contractTargets as $target) {
            if (!is_array($target)) {
                continue;
            }

            $targetType = (string) ($target['target_type'] ?? '');
            $label = sr_banner_clean_single_line((string) ($target['label'] ?? ''), 80);
            if ($targetType === '' || $label === '' || preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $targetType) !== 1) {
                continue;
            }
            if (isset($contracts[$targetType])) {
                continue;
            }

            $helperPath = sr_banner_subject_target_contract_helper_path($moduleKey, $target);
            if ($helperPath !== '') {
                require_once $helperPath;
            }

            $target['module_key'] = $moduleKey;
            $target['label'] = $label;
            $contracts[$targetType] = $target;
        }
    }

    return $contracts;
}

function sr_banner_subject_search_types(PDO $pdo, array $availableTargets): array
{
    $contracts = sr_banner_subject_target_contracts($pdo);
    $types = [];

    foreach ($availableTargets as $target) {
        $targetType = sr_banner_subject_target_type_for_target($target);
        if ($targetType !== '' && isset($contracts[$targetType])) {
            $types[$targetType] = (string) ($contracts[$targetType]['label'] ?? $targetType);
        }
    }

    return $types;
}

function sr_banner_subject_target_type_map(PDO $pdo, array $availableTargets): array
{
    $contracts = sr_banner_subject_target_contracts($pdo);
    $map = [];

    foreach ($availableTargets as $target) {
        $targetType = sr_banner_subject_target_type_for_target($target);
        if ($targetType !== '' && isset($contracts[$targetType])) {
            $map[sr_banner_target_option_value($target)] = $targetType;
        }
    }

    return $map;
}

function sr_banner_subject_search(PDO $pdo, string $referenceType, string $keyword, int $limit = 20, array $options = []): array
{
    $contracts = sr_banner_subject_target_contracts($pdo);
    $target = $contracts[$referenceType] ?? null;
    $searchFunction = is_array($target) ? (string) ($target['search_function'] ?? '') : '';
    if ($searchFunction === '' || !function_exists($searchFunction)) {
        return [];
    }

    try {
        $reflection = new ReflectionFunction($searchFunction);
        $results = $reflection->getNumberOfParameters() >= 5
            ? $searchFunction($pdo, $referenceType, sr_banner_clean_single_line($keyword, 120), max(1, min(30, $limit)), $options)
            : $searchFunction($pdo, $referenceType, sr_banner_clean_single_line($keyword, 120), max(1, min(30, $limit)));
        return is_array($results) ? $results : [];
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'banner_subject_search_' . $referenceType);
        return [];
    }
}

function sr_banner_subject_health(PDO $pdo, string $referenceType, string $subjectId): array
{
    $contracts = sr_banner_subject_target_contracts($pdo);
    $target = $contracts[$referenceType] ?? null;
    $healthFunction = is_array($target) ? (string) ($target['health_function'] ?? '') : '';
    if ($healthFunction === '' || !function_exists($healthFunction)) {
        return ['status' => 'unknown', 'message' => '대상 검증 계약을 찾을 수 없습니다.'];
    }

    try {
        $result = $healthFunction($pdo, $referenceType, $subjectId);
        return is_array($result) ? $result : ['status' => 'unknown', 'message' => '대상 검증 결과가 올바르지 않습니다.'];
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'banner_subject_health_' . $referenceType);
        return ['status' => 'unknown', 'message' => '대상 검증 중 오류가 발생했습니다.'];
    }
}

function sr_banner_target_for_context(PDO $pdo, array $context): ?array
{
    $moduleKey = (string) ($context['module_key'] ?? '');
    $pointKey = (string) ($context['point_key'] ?? '');
    $slotKey = (string) ($context['slot_key'] ?? '');

    foreach (sr_banner_available_targets($pdo) as $target) {
        if (
            (string) ($target['module_key'] ?? '') === $moduleKey
            && (string) ($target['point_key'] ?? '') === $pointKey
            && (string) ($target['slot_key'] ?? '') === $slotKey
        ) {
            return $target;
        }
    }

    return sr_banner_target_from_row([
        'module_key' => $moduleKey,
        'point_key' => $pointKey,
        'slot_key' => $slotKey,
    ]);
}

function sr_banner_target_from_row(array $row, string $label = '저장된 노출 위치'): ?array
{
    $moduleKey = (string) ($row['module_key'] ?? '');
    $pointKey = (string) ($row['point_key'] ?? '');
    $slotKey = (string) ($row['slot_key'] ?? '');

    if (
        !sr_is_safe_module_key($moduleKey)
        || preg_match('/\A[a-z0-9][a-z0-9_.-]{0,119}\z/', $pointKey) !== 1
        || preg_match('/\A[a-z0-9][a-z0-9_.-]{0,79}\z/', $slotKey) !== 1
    ) {
        return null;
    }

    return [
        'module_key' => $moduleKey,
        'point_key' => $pointKey,
        'slot_key' => $slotKey,
        'placement_kind' => sr_banner_placement_kind((string) ($row['placement_kind'] ?? 'inline')),
        'label' => $label . ' / ' . $moduleKey . ' / ' . $pointKey . ' / ' . $slotKey,
        'source' => 'stored',
    ];
}

function sr_banner_click_url(int $bannerId): string
{
    return '/banner/click?id=' . rawurlencode((string) $bannerId);
}

function sr_banner_click_link_attributes(int $bannerId, string $url): string
{
    $url = sr_banner_clean_url($url);
    if ($bannerId <= 0 || $url === '') {
        return '';
    }

    $attributes = ' href="' . sr_e(sr_url(sr_banner_click_url($bannerId))) . '"';
    if (sr_is_http_url($url)) {
        $attributes .= ' target="_blank" rel="noopener noreferrer"';
    }

    return $attributes;
}

function sr_banner_click_subject(): string
{
    $accountId = $_SESSION['sr_account_id'] ?? null;
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

    return 'guest:' . sr_client_ip() . '|' . hash('sha256', sr_client_user_agent());
}

function sr_banner_record_click(PDO $pdo, array $config, int $bannerId): bool
{
    if ($bannerId <= 0) {
        return false;
    }

    try {
        $clickKeyHash = sr_hmac_hash('banner-click|' . (string) $bannerId . '|' . sr_banner_click_subject(), $config);
        $now = sr_now();

        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO sr_banner_clicks
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
            'UPDATE sr_banners
             SET click_count = click_count + 1
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $bannerId,
        ]);

        return true;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'banner_click_record_failed');
        return false;
    }
}

function sr_banner_click_target(PDO $pdo, int $bannerId): ?array
{
    if ($bannerId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT b.id, b.link_url
         FROM sr_banners b
         WHERE b.id = :id
           AND b.status = 'enabled'
           AND b.link_url <> ''
           AND (b.starts_at IS NULL OR b.starts_at <= :now_start)
           AND (b.ends_at IS NULL OR b.ends_at >= :now_end)
         LIMIT 1"
    );
    $now = sr_now();
    $stmt->execute([
        'id' => $bannerId,
        'now_start' => $now,
        'now_end' => $now,
    ]);

    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    $linkUrl = sr_banner_clean_url((string) $row['link_url']);
    if ($linkUrl === '') {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'link_url' => $linkUrl,
    ];
}

function sr_banner_redirect_to_link(string $url): void
{
    $url = sr_banner_clean_url($url);
    if ($url === '') {
        sr_render_error(404, sr_t('banner::action.error.link_not_found'));
    }

    if (sr_is_http_url($url)) {
        sr_redirect_external($url);
    }

    sr_redirect($url);
}

function sr_banner_link_type_label(string $url): string
{
    $url = sr_banner_clean_url($url);
    if ($url === '') {
        return '-';
    }

    return sr_is_http_url($url) ? '외부 링크' : '내부 링크';
}

function sr_banner_public_banners(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        "SELECT b.id, b.title, b.status, b.starts_at, b.ends_at, b.sort_order
         FROM sr_banners b
         WHERE NOT EXISTS (
             SELECT 1
             FROM sr_banner_targets t
             WHERE t.banner_id = b.id
         )
         ORDER BY b.sort_order ASC, b.id DESC"
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_banner_settings(PDO $pdo): array
{
    $metadata = sr_module_metadata('banner');
    $defaults = isset($metadata['settings']) && is_array($metadata['settings']) ? $metadata['settings'] : [];

    return array_merge(sr_banner_default_settings(), $defaults, sr_module_settings($pdo, 'banner'));
}

function sr_banner_default_settings(): array
{
    return [
        'banner_skin_key' => 'basic',
        'banner_default_status' => 'draft',
        'banner_default_target_option' => 'core|site.layout|before_layout',
        'banner_default_match_type' => 'all',
        'banner_default_sort_order' => 100,
    ];
}

function sr_banner_default_status(array $settings): string
{
    $status = (string) ($settings['banner_default_status'] ?? 'draft');

    return in_array($status, ['draft', 'enabled', 'disabled'], true) ? $status : 'draft';
}

function sr_banner_default_target_option(array $settings, array $availableTargets): string
{
    $targetOption = (string) ($settings['banner_default_target_option'] ?? sr_banner_public_target_option_value());
    if (sr_banner_is_public_target_option($targetOption)) {
        return sr_banner_public_target_option_value();
    }

    return sr_banner_find_target($availableTargets, $targetOption) !== null ? $targetOption : sr_banner_public_target_option_value();
}

function sr_banner_default_match_type(array $settings): string
{
    $matchType = (string) ($settings['banner_default_match_type'] ?? 'all');

    return in_array($matchType, ['all', 'exact'], true) ? $matchType : 'all';
}

function sr_banner_default_sort_order(array $settings): int
{
    return max(-100000, min(100000, (int) ($settings['banner_default_sort_order'] ?? 100)));
}

function sr_banner_skin_options(): array
{
    return sr_filter_view_options([
        'basic' => [
            'label' => sr_t('banner::skin.basic'),
            'supports' => ['public', 'inline'],
            'views' => [
                'item' => SR_ROOT . '/modules/banner/skins/basic/item.php',
            ],
        ],
    ], ['item'], 'banner skin');
}

function sr_banner_skin_key(array $settings): string
{
    $skinKey = (string) ($settings['banner_skin_key'] ?? 'basic');

    return isset(sr_banner_skin_options()[$skinKey]) ? $skinKey : 'basic';
}

function sr_banner_skin_view(string $skinKey, string $viewKey): string
{
    $options = sr_banner_skin_options();
    $view = (string) ($options[$skinKey]['views'][$viewKey] ?? $options['basic']['views'][$viewKey] ?? '');

    if (is_file($view)) {
        return $view;
    }

    $fallback = (string) ($options['basic']['views'][$viewKey] ?? '');
    if (is_file($fallback)) {
        return $fallback;
    }

    throw new RuntimeException('기본 배너 스킨 view 파일이 누락되었습니다.');
}

function sr_banner_skin_supports(string $skinKey, string $placementKind): bool
{
    $options = sr_banner_skin_options();
    $skinKey = sr_banner_skin_key(['banner_skin_key' => $skinKey]);
    $supports = isset($options[$skinKey]['supports']) && is_array($options[$skinKey]['supports'])
        ? array_values(array_map('strval', $options[$skinKey]['supports']))
        : ['inline'];

    return in_array(sr_banner_placement_kind($placementKind), $supports, true);
}

function sr_banner_skin_key_for_placement(string $skinKey, string $placementKind): ?string
{
    $skinKey = sr_banner_skin_key(['banner_skin_key' => $skinKey]);
    $placementKind = sr_banner_placement_kind($placementKind);
    if (sr_banner_skin_supports($skinKey, $placementKind)) {
        return $skinKey;
    }

    return sr_banner_skin_supports('basic', $placementKind) ? 'basic' : null;
}

function sr_banner_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'banner' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('배너 모듈이 등록되어 있지 않습니다.');
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
    $now = sr_now();
    $rows = [];
    if (array_key_exists('banner_skin_key', $settings)) {
        $rows[] = ['banner_skin_key', sr_banner_skin_key(['banner_skin_key' => (string) $settings['banner_skin_key']]), 'string'];
    }
    if (array_key_exists('banner_default_status', $settings)) {
        $rows[] = ['banner_default_status', sr_banner_default_status($settings), 'string'];
    }
    if (array_key_exists('banner_default_target_option', $settings)) {
        $rows[] = ['banner_default_target_option', (string) $settings['banner_default_target_option'], 'string'];
    }
    if (array_key_exists('banner_default_match_type', $settings)) {
        $rows[] = ['banner_default_match_type', sr_banner_default_match_type($settings), 'string'];
    }
    if (array_key_exists('banner_default_sort_order', $settings)) {
        $rows[] = ['banner_default_sort_order', (string) sr_banner_default_sort_order($settings), 'int'];
    }

    foreach ($rows as $row) {
        $stmt->execute([
            'module_id' => (int) $module['id'],
            'setting_key' => (string) $row[0],
            'setting_value' => (string) $row[1],
            'value_type' => (string) $row[2],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    sr_clear_module_settings_cache('banner');
}

function sr_banner_render_item(array $banner, ?string $skinKey = null): string
{
    $skinKey = sr_banner_skin_key(['banner_skin_key' => $skinKey ?? 'basic']);
    $view = sr_banner_skin_view($skinKey, 'item');
    if ($view === '') {
        return '';
    }

    ob_start();
    include $view;
    return (string) ob_get_clean();
}

function sr_banner_render_basic_item(array $banner): string
{
    $content = '';
    $contentType = sr_banner_content_type((string) ($banner['content_type'] ?? ''));
    $htmlCode = (string) ($banner['html_code'] ?? '');
    if ($contentType === 'html' && $htmlCode !== '') {
        return '<aside class="sr-banner sr-banner-code" data-banner-id="' . sr_e((string) $banner['id']) . '">' . $htmlCode . '</aside>';
    }

    $imageUrl = $contentType === 'image' ? sr_banner_clean_image_url((string) ($banner['image_url'] ?? '')) : '';
    if ($contentType === 'image' && $imageUrl !== '') {
        $imageSrc = sr_is_http_url($imageUrl) ? $imageUrl : sr_url($imageUrl);
        $content .= '<img src="' . sr_e($imageSrc) . '" alt="' . sr_e((string) ($banner['body_text'] ?? '')) . '">';
    } else {
        $content .= '<strong>' . sr_e((string) $banner['title']) . '</strong>';
        if ((string) ($banner['body_text'] ?? '') !== '') {
            $content .= '<span>' . nl2br(sr_e((string) $banner['body_text'])) . '</span>';
        }
    }

    $linkAttributes = sr_banner_click_link_attributes((int) $banner['id'], (string) ($banner['link_url'] ?? ''));
    if ($linkAttributes !== '') {
        $content = '<a' . $linkAttributes . '>' . $content . '</a>';
    }

    return '<aside class="sr-banner" data-banner-id="' . sr_e((string) $banner['id']) . '">' . $content . '</aside>';
}

function sr_banner_render_public_banner(PDO $pdo, int $bannerId): string
{
    if ($bannerId <= 0) {
        return '';
    }

    $stmt = $pdo->prepare(
        "SELECT b.id, b.title, " . sr_banner_content_select_sql($pdo, 'b') . ", b.link_url, b.image_url, b.skin_key
         FROM sr_banners b
         WHERE b.id = :id
           AND b.status = 'enabled'
           AND (b.starts_at IS NULL OR b.starts_at <= :now_start)
           AND (b.ends_at IS NULL OR b.ends_at >= :now_end)
           AND NOT EXISTS (
               SELECT 1
               FROM sr_banner_targets t
               WHERE t.banner_id = b.id
           )
         LIMIT 1"
    );
    $now = sr_now();
    $stmt->execute([
        'id' => $bannerId,
        'now_start' => $now,
        'now_end' => $now,
    ]);

    $banner = $stmt->fetch();
    $skinKey = is_array($banner) ? sr_banner_skin_key_for_placement((string) ($banner['skin_key'] ?? 'basic'), 'public') : null;
    return is_array($banner) && $skinKey !== null ? sr_banner_render_item($banner, $skinKey) : '';
}

function sr_banner_render_slot(PDO $pdo, array $context): string
{
    $moduleKey = (string) ($context['module_key'] ?? '');
    $pointKey = (string) ($context['point_key'] ?? '');
    $slotKey = (string) ($context['slot_key'] ?? '');
    $subjectId = (string) ($context['subject_id'] ?? '');

    $stmt = $pdo->prepare(
        "SELECT b.id, b.title, " . sr_banner_content_select_sql($pdo, 'b') . ", b.link_url, b.image_url, b.skin_key
         FROM sr_banners b
         INNER JOIN sr_banner_targets t ON t.banner_id = b.id
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
    $now = sr_now();
    $stmt->execute([
        'now_start' => $now,
        'now_end' => $now,
        'module_key' => $moduleKey,
        'point_key' => $pointKey,
        'slot_key' => $slotKey,
        'subject_id' => $subjectId,
    ]);

    $html = '';
    $target = sr_banner_target_for_context($pdo, $context);
    $placementKind = sr_banner_target_placement_kind($target);
    foreach ($stmt->fetchAll() as $banner) {
        $requestedSkinKey = sr_banner_skin_key(['banner_skin_key' => (string) ($banner['skin_key'] ?? 'basic')]);
        $skinKey = sr_banner_skin_key_for_placement($requestedSkinKey, $placementKind);
        if ($skinKey === null) {
            error_log('[saanraan] banner skin is not compatible with placement: banner_id=' . (string) ($banner['id'] ?? '') . ' skin_key=' . $requestedSkinKey . ' placement_kind=' . $placementKind);
            continue;
        }
        $html .= sr_banner_render_item($banner, $skinKey);
    }

    return $html;
}

function sr_banner_render_output_slot_cached(PDO $pdo, array $context): string
{
    static $cache = [];

    $cacheKey = implode("\0", [
        (string) spl_object_id($pdo),
        (string) ($context['module_key'] ?? ''),
        (string) ($context['point_key'] ?? ''),
        (string) ($context['slot_key'] ?? ''),
        (string) ($context['subject_id'] ?? ''),
    ]);
    if (!array_key_exists($cacheKey, $cache)) {
        $cache[$cacheKey] = sr_banner_render_slot($pdo, $context);
    }

    return $cache[$cacheKey];
}
