<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/helpers/common.php';
require_once dirname(__DIR__, 2) . '/core/helpers/upload.php';
require_once SR_ROOT . '/modules/survey/helpers/comments.php';
require_once SR_ROOT . '/modules/survey/helpers/responses.php';

function sr_survey_key_is_valid(string $key): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,63}\z/', $key) === 1;
}

function sr_survey_key_is_reserved(string $key): bool
{
    return in_array($key, ['comment', 'ui_kit', 'admin'], true);
}

function sr_survey_internal_return_path(string $value): string
{
    $value = trim($value);
    if ($value === '' || $value[0] !== '/' || str_starts_with($value, '//')) {
        return '';
    }

    if (preg_match('/[\r\n]/', $value) === 1) {
        return '';
    }

    return substr($value, 0, 255);
}

function sr_survey_clean_key(string $value, int $maxLength = 64): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_]/', '', $value) ?? '';
    $value = preg_replace('/\A[^a-z]+/', '', $value) ?? '';

    return substr($value, 0, $maxLength);
}

function sr_survey_clean_text(string $value, int $maxLength): string
{
    return sr_clean_text($value, $maxLength);
}

function sr_survey_clean_single_line(string $value, int $maxLength): string
{
    return sr_clean_single_line($value, $maxLength);
}

function sr_survey_clean_cover_image_url(string $value): string
{
    $value = sr_survey_clean_single_line($value, 255);
    if ($value === '') {
        return '';
    }
    if (sr_is_http_url($value) || sr_is_safe_relative_url($value)) {
        return $value;
    }

    return '';
}

function sr_survey_cover_image_html(array $survey, string $className, string $alt = ''): string
{
    $imageUrl = sr_survey_clean_cover_image_url((string) ($survey['cover_image_url'] ?? ''));
    if ($imageUrl === '') {
        return '';
    }
    $src = sr_is_http_url($imageUrl) ? $imageUrl : sr_url($imageUrl);

    return '<img class="' . sr_e($className) . '" src="' . sr_e($src) . '" alt="' . sr_e($alt) . '" loading="lazy" decoding="async">';
}

function sr_survey_cover_image_upload_max_bytes(): int
{
    return 5242880;
}

function sr_survey_reward_table_available(PDO $pdo, string $tableName): bool
{
    if (!in_array($tableName, ['sr_survey_reward_policies', 'sr_survey_forms'], true)) {
        return false;
    }

    try {
        $pdo->query('SELECT 1 FROM ' . $tableName . ' LIMIT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

function sr_survey_cover_image_upload_was_provided(mixed $file): bool
{
    return sr_upload_was_provided($file);
}

function sr_survey_cover_image_format_for_mime(string $mimeType): string
{
    return sr_image_format_for_mime($mimeType);
}

function sr_survey_cover_image_mime_is_allowed(string $mimeType): bool
{
    return sr_image_mime_is_allowed($mimeType);
}

function sr_survey_upload_cover_image(array $file): ?array
{
    if (!sr_survey_cover_image_upload_was_provided($file)) {
        return null;
    }

    $validated = sr_upload_validate_file($file, [
        'max_bytes' => sr_survey_cover_image_upload_max_bytes(),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
    ]);

    $targetFormat = sr_survey_cover_image_format_for_mime((string) $validated['mime_type']);
    if ($targetFormat === '') {
        throw new RuntimeException('허용되지 않은 커버 이미지 형식입니다.');
    }

    $datePath = date('Y/m');
    $directory = SR_ROOT . '/storage/tmp/survey-cover-images/' . $datePath;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('커버 이미지 저장 디렉터리를 만들 수 없습니다.');
    }

    $storedName = sr_upload_random_filename($targetFormat);
    $targetPath = sr_upload_safe_target_path($directory, $storedName);
    sr_upload_assert_target_path_writable($targetPath);

    if (!sr_upload_reencode_image((string) $validated['tmp_name'], $targetPath, $targetFormat, [
        'max_pixels' => 25000000,
        'quality' => 86,
    ])) {
        throw new RuntimeException('커버 이미지 재인코딩에 실패했습니다.');
    }

    $storedMimeType = sr_upload_detect_mime($targetPath);
    if (!sr_survey_cover_image_mime_is_allowed($storedMimeType)) {
        @unlink($targetPath);
        throw new RuntimeException('저장된 커버 이미지 MIME을 확인할 수 없습니다.');
    }

    $storageKey = 'survey/cover-images/' . $datePath . '/' . $storedName;
    $stored = sr_storage_put_file($targetPath, $storageKey, [
        'content_type' => $storedMimeType,
    ]);
    @unlink($targetPath);

    $storageReference = sr_storage_reference((string) $stored['driver'], $storageKey);

    return [
        'driver' => (string) $stored['driver'],
        'key' => $storageKey,
        'url' => '/survey/cover-image?file=' . rawurlencode($storageReference),
    ];
}

function sr_survey_cover_image_storage_key_is_valid(string $key): bool
{
    return preg_match('#\Asurvey/cover-images/\d{4}/\d{2}/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $key) === 1;
}

function sr_survey_cover_image_storage_reference(string $reference): ?array
{
    $storage = sr_storage_parse_reference($reference);
    if (!is_array($storage) || !sr_survey_cover_image_storage_key_is_valid((string) $storage['key'])) {
        return null;
    }

    return $storage;
}

function sr_survey_cover_image_storage_reference_from_url(string $url): ?array
{
    $url = sr_survey_clean_cover_image_url($url);
    if ($url === '') {
        return null;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return null;
    }

    $path = (string) ($parts['path'] ?? '');
    $proxyPath = (string) (parse_url(sr_url('/survey/cover-image'), PHP_URL_PATH) ?: '/survey/cover-image');
    if ($path !== '/survey/cover-image' && $path !== $proxyPath) {
        $config = sr_runtime_config();
        $s3 = sr_storage_s3_config($config);
        $baseUrl = rtrim((string) ($s3['public_base_url'] ?? ''), '/');
        if ($baseUrl === '' || !sr_is_http_url($baseUrl) || !str_starts_with($url, $baseUrl . '/')) {
            return null;
        }

        $key = rawurldecode(substr($url, strlen($baseUrl) + 1));
        if (!sr_survey_cover_image_storage_key_is_valid($key)) {
            return null;
        }

        return ['driver' => 's3', 'key' => $key];
    }

    $query = [];
    parse_str((string) ($parts['query'] ?? ''), $query);
    $reference = is_string($query['file'] ?? null) ? (string) $query['file'] : '';
    if ($reference === '') {
        return null;
    }

    return sr_survey_cover_image_storage_reference($reference);
}

function sr_survey_cover_image_reference_count(PDO $pdo, string $url, int $exceptSurveyId = 0): int
{
    $url = sr_survey_clean_cover_image_url($url);
    if ($url === '') {
        return 0;
    }

    $sql = 'SELECT COUNT(*) FROM sr_survey_forms WHERE cover_image_url = :cover_image_url';
    $params = ['cover_image_url' => $url];
    if ($exceptSurveyId > 0) {
        $sql .= ' AND id <> :survey_id';
        $params['survey_id'] = $exceptSurveyId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function sr_survey_delete_cover_image_storage(PDO $pdo, string $url, int $exceptSurveyId = 0, int $pendingFailureId = 0): array
{
    $storage = sr_survey_cover_image_storage_reference_from_url($url);
    if (!is_array($storage)) {
        return ['attempted' => false, 'deleted' => false, 'failed' => false, 'reference' => ''];
    }

    $driver = (string) ($storage['driver'] ?? 'local');
    $key = (string) ($storage['key'] ?? '');
    $reference = $driver . ':' . $key;
    if ($key === '' || sr_survey_cover_image_reference_count($pdo, $url, $exceptSurveyId) > 0) {
        return ['attempted' => false, 'deleted' => false, 'failed' => false, 'reference' => $reference];
    }

    $failureId = $pendingFailureId > 0
        ? $pendingFailureId
        : sr_survey_record_storage_cleanup_pending($pdo, 'survey_cover_image', $exceptSurveyId, $driver, $key);
    $deleted = sr_storage_delete($driver, $key);
    sr_survey_update_storage_cleanup_result(
        $pdo,
        $failureId,
        $deleted,
        $deleted ? '' : '설문 커버 이미지 저장소 정리에 실패했습니다.'
    );

    return [
        'attempted' => true,
        'deleted' => $deleted,
        'failed' => !$deleted,
        'reference' => $reference,
    ];
}

function sr_survey_record_storage_cleanup_pending(PDO $pdo, string $sourceType, int $sourceId, string $driver, string $key): int
{
    if ($key === '' || !sr_storage_key_is_safe($key)) {
        return 0;
    }

    $driver = in_array($driver, ['local', 's3'], true) ? $driver : 'local';
    $now = sr_now();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO sr_survey_storage_cleanup_failures
                (source_type, source_id, storage_driver, storage_key, status, attempt_count, last_error, created_at, updated_at)
             VALUES
                (:source_type, :source_id, :storage_driver, :storage_key, 'pending', 0, '', :created_at, :updated_at)"
        );
        $stmt->execute([
            'source_type' => sr_survey_clean_key($sourceType),
            'source_id' => max(0, $sourceId),
            'storage_driver' => $driver,
            'storage_key' => $key,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $pdo->lastInsertId();
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'survey_storage_cleanup_pending_record_failed');
        return 0;
    }
}

function sr_survey_update_storage_cleanup_result(PDO $pdo, int $failureId, bool $deleted, string $errorMessage): void
{
    if ($failureId < 1) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            "UPDATE sr_survey_storage_cleanup_failures
             SET status = :status,
                 attempt_count = attempt_count + 1,
                 last_error = :last_error,
                 updated_at = :updated_at
             WHERE id = :id"
        );
        $stmt->execute([
            'status' => $deleted ? 'cleaned' : 'pending',
            'last_error' => $deleted ? '' : sr_survey_clean_text($errorMessage, 1000),
            'updated_at' => sr_now(),
            'id' => $failureId,
        ]);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'survey_storage_cleanup_result_update_failed');
    }
}

function sr_survey_statuses(): array
{
    return ['draft', 'active', 'paused', 'archived'];
}

function sr_survey_status_label(string $status): string
{
    return [
        'draft' => '초안',
        'active' => '공개',
        'paused' => '중지',
        'archived' => '보관',
    ][$status] ?? $status;
}

function sr_survey_admin_status_class(string $status): string
{
    return match ($status) {
        'active', 'approved', 'accepted' => 'is-success',
        'paused', 'needs_fix', 'flagged' => 'is-warning',
        'draft', 'unchecked' => 'is-danger',
        default => 'is-danger',
    };
}

function sr_survey_question_types(): array
{
    return ['single_choice', 'multiple_choice', 'short_text', 'long_text', 'number', 'rating', 'scale'];
}

function sr_survey_question_type_label(string $type): string
{
    return [
        'single_choice' => '단일 선택',
        'multiple_choice' => '복수 선택',
        'text' => '짧은 답변',
        'short_text' => '짧은 답변',
        'long_text' => '긴 답변',
        'number' => '숫자',
        'rating' => '별점',
        'scale' => '만족도/척도',
    ][$type] ?? $type;
}

function sr_survey_response_limit_policies(): array
{
    return ['per_survey_once', 'per_period', 'unlimited'];
}

function sr_survey_response_limit_policy_label(string $policy): string
{
    return [
        'per_survey_once' => '회원당 1회',
        'per_period' => '기간당 1회',
        'unlimited' => '제한 없음',
    ][$policy] ?? $policy;
}

function sr_survey_quality_statuses(): array
{
    return ['accepted', 'flagged', 'excluded'];
}

function sr_survey_quality_status_label(string $status): string
{
    return [
        'accepted' => '포함',
        'flagged' => '검토',
        'excluded' => '제외',
    ][$status] ?? $status;
}

function sr_survey_qa_statuses(): array
{
    return ['unchecked', 'needs_fix', 'approved'];
}

function sr_survey_qa_status_label(string $status): string
{
    return [
        'unchecked' => '미점검',
        'needs_fix' => '수정 필요',
        'approved' => '승인',
    ][$status] ?? $status;
}

function sr_survey_normalize_member_group_keys(mixed $groupKeys): array
{
    require_once SR_ROOT . '/modules/member/helpers/groups.php';
    $values = is_array($groupKeys) ? $groupKeys : preg_split('/[\s,]+/', (string) $groupKeys);
    $normalized = [];
    foreach ($values ?: [] as $groupKey) {
        $groupKey = strtolower(trim((string) $groupKey));
        if ($groupKey !== '' && sr_member_group_key_is_valid($groupKey)) {
            $normalized[$groupKey] = true;
        }
    }

    return array_keys($normalized);
}

function sr_survey_member_group_keys_from_json(mixed $json): array
{
    $decoded = json_decode((string) $json, true);
    return sr_survey_normalize_member_group_keys(is_array($decoded) ? $decoded : []);
}

function sr_survey_member_group_keys_json(array $groupKeys): string
{
    $json = json_encode(array_values(sr_survey_normalize_member_group_keys($groupKeys)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : '[]';
}

function sr_survey_default_settings(): array
{
    return [
        'layout_key' => 'survey.basic',
        'theme_key' => 'basic',
        'skin_key' => 'basic',
        'layout_primary_menu_key' => 'header',
        'layout_extra_menu_keys_json' => [],
        'default_status' => 'draft',
        'default_login_required' => 1,
        'default_consent_required' => 0,
        'default_response_limit_policy' => 'per_survey_once',
        'default_response_limit_period_seconds' => 0,
        'internal_embed_enabled' => true,
        'identity_view_required' => false,
        'identity_view_adult_required' => false,
        'business_info_visible' => true,
        'reaction_preset_key' => '',
        'reaction_comment_preset_key' => '',
        'public_list_limit' => 50,
    ];
}

function sr_survey_layout_required_targets(): array
{
    return ['survey.home', 'survey.view', 'survey.complete'];
}

function sr_survey_layout_options(PDO $pdo, bool $includeInstalledModules = false): array
{
    return sr_public_layout_options_for_targets($pdo, sr_survey_layout_required_targets(), $includeInstalledModules);
}

function sr_survey_layout_menu_slots(): array
{
    return [
        'primary' => 'layout_primary_menu_key',
    ];
}

function sr_survey_clean_layout_menu_key(string $value): string
{
    $value = strtolower(trim($value));
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $value) === 1 ? $value : '';
}

function sr_survey_clean_layout_extra_menu_area_key(string $value): string
{
    $value = strtolower(trim($value));
    return preg_match('/\A(?:[a-f0-9]{12}|[a-z][a-z0-9_]{0,59})\z/', $value) === 1 ? $value : '';
}

function sr_survey_layout_extra_menu_label(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, 80) : substr($value, 0, 80);
}

function sr_survey_layout_extra_menu_hash_key(array $usedKeys = [], string $seed = ''): string
{
    $used = array_fill_keys(array_map('strval', $usedKeys), true);
    if ($seed !== '') {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $key = substr(hash('sha256', 'survey.layout_extra_menu|' . $seed . '|' . (string) $attempt), 0, 12);
            if (!isset($used[$key])) {
                return $key;
            }
        }
    }
    do {
        $key = bin2hex(random_bytes(6));
    } while (isset($used[$key]));

    return $key;
}

function sr_survey_layout_extra_menu_items_from_value(mixed $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
    }
    if (!is_array($value)) {
        return [];
    }

    $items = [];
    foreach (array_values($value) as $itemIndex => $item) {
        $areaKey = '';
        $menuKey = '';
        if (is_array($item)) {
            $areaKey = sr_survey_clean_layout_extra_menu_area_key((string) ($item['area_key'] ?? ($item['key'] ?? ($item['slot_key'] ?? ''))));
            $label = sr_survey_layout_extra_menu_label((string) ($item['label'] ?? ($item['name'] ?? '')));
            $menuKey = sr_survey_clean_layout_menu_key((string) ($item['menu_key'] ?? ''));
        } else {
            $label = '';
            $menuKey = sr_survey_clean_layout_menu_key((string) $item);
        }
        if ($menuKey === '') {
            continue;
        }
        if ($areaKey === '' || isset($items[$areaKey])) {
            $areaKey = sr_survey_layout_extra_menu_hash_key(array_keys($items), (string) $itemIndex . '|' . $menuKey);
        }
        $items[$areaKey] = [
            'area_key' => $areaKey,
            'label' => $label,
            'menu_key' => $menuKey,
        ];
    }

    return array_values($items);
}

function sr_survey_layout_extra_menu_items_from_pair_values(mixed $areaKeys, mixed $labels, mixed $menuKeys): array
{
    $areaKeys = is_array($areaKeys) ? array_values($areaKeys) : [];
    $labels = is_array($labels) ? array_values($labels) : [];
    $menuKeys = is_array($menuKeys) ? array_values($menuKeys) : [];
    $items = [];
    foreach ($menuKeys as $index => $menuKeyValue) {
        $menuKey = sr_survey_clean_layout_menu_key((string) $menuKeyValue);
        if ($menuKey === '') {
            continue;
        }
        $areaKey = sr_survey_clean_layout_extra_menu_area_key((string) ($areaKeys[$index] ?? ''));
        if ($areaKey === '' || isset($items[$areaKey])) {
            $areaKey = sr_survey_layout_extra_menu_hash_key(array_keys($items));
        }
        $label = sr_survey_layout_extra_menu_label((string) ($labels[$index] ?? ''));
        if (!isset($items[$areaKey])) {
            $items[$areaKey] = [
                'area_key' => $areaKey,
                'label' => $label,
                'menu_key' => $menuKey,
            ];
        }
    }

    return array_values($items);
}

function sr_survey_layout_extra_menu_keys_from_value(mixed $value): array
{
    return array_values(array_map(static fn (array $item): string => (string) $item['menu_key'], sr_survey_layout_extra_menu_items_from_value($value)));
}

function sr_survey_layout_extra_menu_items_from_settings(array $settings): array
{
    $items = [];
    foreach (sr_survey_layout_extra_menu_items_from_value($settings['layout_extra_menu_keys_json'] ?? []) as $item) {
        $items[(string) $item['area_key']] = $item;
    }
    foreach (['layout_secondary_menu_key', 'layout_tertiary_menu_key', 'layout_quaternary_menu_key', 'layout_quinary_menu_key'] as $legacySettingKey) {
        $menuKey = sr_survey_clean_layout_menu_key((string) ($settings[$legacySettingKey] ?? ''));
        $areaKey = str_replace('layout_', '', str_replace('_menu_key', '', $legacySettingKey));
        if ($menuKey !== '' && !isset($items[$areaKey])) {
            $items[$areaKey] = [
                'area_key' => $areaKey,
                'label' => '',
                'menu_key' => $menuKey,
            ];
        }
    }

    return array_values($items);
}

function sr_survey_layout_extra_menu_keys_from_settings(array $settings): array
{
    return array_values(array_map(static fn (array $item): string => (string) $item['menu_key'], sr_survey_layout_extra_menu_items_from_settings($settings)));
}

function sr_survey_layout_extra_menu_keys_json(mixed $value): string
{
    $json = json_encode(sr_survey_layout_extra_menu_items_from_value($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : '[]';
}

function sr_survey_fallback_layout_key(PDO $pdo, ?array $site = null): string
{
    $options = sr_survey_layout_options($pdo);
    $siteLayoutKey = sr_public_layout_key($site, $pdo);
    if (isset($options[$siteLayoutKey])) {
        return $siteLayoutKey;
    }
    if (isset($options['survey.basic'])) {
        return 'survey.basic';
    }

    return sr_public_layout_default_key();
}

function sr_survey_skin_option_definitions(): array
{
    $definitions = [];
    foreach ([
        'basic' => '기본형',
    ] as $skinKey => $label) {
        $views = [];
        foreach (sr_survey_skin_views() as $view) {
            $views[$view] = SR_ROOT . '/modules/survey/skins/' . $skinKey . '/' . $view . '.php';
        }
        $definitions[$skinKey] = [
            'skin_key' => $skinKey,
            'label' => $label,
            'source_type' => 'built_in_module',
            'source_key' => 'survey',
            'module_key' => 'survey',
            'contract_version' => '1.0',
            'views' => $views,
            'stylesheets' => [],
            'actions' => [],
        ];
    }

    return $definitions;
}

function sr_survey_skin_options(): array
{
    $options = [];
    foreach (sr_survey_skin_option_definitions() as $skinKey => $definition) {
        $options[(string) $skinKey] = (string) ($definition['label'] ?? $skinKey);
    }

    return $options;
}

function sr_survey_skin_views(): array
{
    return ['home', 'view', 'complete'];
}

function sr_survey_skin_key(string $value): string
{
    $value = strtolower(trim($value));
    return isset(sr_survey_skin_options()[$value]) ? $value : 'basic';
}

function sr_survey_clean_optional_skin_key(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }

    return isset(sr_survey_skin_options()[$value]) ? $value : '';
}

function sr_survey_optional_option_key_from_post(string $value, array $options): string
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }

    return isset($options[$value]) ? $value : '__invalid__';
}

function sr_survey_theme_options(): array
{
    return sr_view_theme_options(SR_ROOT . '/modules/survey/theme', ['home.php', 'view.php', 'complete.php', 'ui-kit.php'], '기본 설문 테마', 'survey_view_theme', false);
}

function sr_survey_theme_key(string $themeKey): string
{
    return sr_view_theme_key($themeKey, sr_survey_theme_options());
}

function sr_survey_theme_view_file(array $settings, string $view): ?string
{
    $allowedViews = array_fill_keys(array_merge(sr_survey_skin_views(), ['ui-kit']), true);
    if (!isset($allowedViews[$view])) {
        return null;
    }

    return sr_view_theme_file(SR_ROOT . '/modules/survey/theme', sr_survey_theme_key((string) ($settings['theme_key'] ?? '')), $view . '.php');
}

function sr_survey_normalize_settings(array $settings): array
{
    if (array_key_exists('embed_enabled', $settings) && !array_key_exists('internal_embed_enabled', $settings)) {
        $settings['internal_embed_enabled'] = $settings['embed_enabled'];
    }
    $defaults = sr_survey_default_settings();
    $normalized = array_merge($defaults, $settings);
    $normalized['layout_key'] = sr_public_layout_normalize_key((string) ($normalized['layout_key'] ?? $defaults['layout_key']));
    $normalized['theme_key'] = sr_survey_theme_key((string) ($normalized['theme_key'] ?? $defaults['theme_key']));
    $normalized['skin_key'] = sr_survey_skin_key((string) ($normalized['skin_key'] ?? $defaults['skin_key']));
    foreach (sr_survey_layout_menu_slots() as $settingKey) {
        $normalized[$settingKey] = sr_survey_clean_layout_menu_key((string) ($normalized[$settingKey] ?? ''));
    }
    $normalized['layout_extra_menu_keys_json'] = sr_survey_layout_extra_menu_items_from_settings($normalized);
    $normalized['default_status'] = in_array((string) $normalized['default_status'], sr_survey_statuses(), true) ? (string) $normalized['default_status'] : (string) $defaults['default_status'];
    $normalized['default_login_required'] = !empty($normalized['default_login_required']) ? 1 : 0;
    $normalized['default_consent_required'] = !empty($normalized['default_consent_required']) ? 1 : 0;
    $normalized['default_response_limit_policy'] = in_array((string) $normalized['default_response_limit_policy'], sr_survey_response_limit_policies(), true) ? (string) $normalized['default_response_limit_policy'] : (string) $defaults['default_response_limit_policy'];
    $normalized['default_response_limit_period_seconds'] = max(0, (int) $normalized['default_response_limit_period_seconds']);
    $normalized['internal_embed_enabled'] = !empty($normalized['internal_embed_enabled']);
    unset($normalized['embed_enabled']);
    $normalized['identity_view_required'] = !empty($normalized['identity_view_required']);
    $normalized['identity_view_adult_required'] = !empty($normalized['identity_view_adult_required']);
    $normalized['business_info_visible'] = !array_key_exists('business_info_visible', $normalized) || !empty($normalized['business_info_visible']);
    $reactionModuleEnabled = isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && sr_module_enabled($GLOBALS['pdo'], 'reaction');
    $normalized['reaction_preset_key'] = $reactionModuleEnabled && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($GLOBALS['pdo'], $normalized['reaction_preset_key'] ?? '') : sr_survey_clean_key((string) ($normalized['reaction_preset_key'] ?? ''), 80);
    $normalized['reaction_comment_preset_key'] = $reactionModuleEnabled && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($GLOBALS['pdo'], $normalized['reaction_comment_preset_key'] ?? '') : sr_survey_clean_key((string) ($normalized['reaction_comment_preset_key'] ?? ''), 80);
    $normalized['public_list_limit'] = max(1, min(100, (int) $normalized['public_list_limit']));

    return $normalized;
}

function sr_survey_settings(PDO $pdo): array
{
    $settings = sr_survey_normalize_settings(sr_module_settings($pdo, 'survey'));
    if (!isset(sr_survey_layout_options($pdo)[$settings['layout_key']])) {
        $settings['layout_key'] = sr_survey_fallback_layout_key($pdo, null);
    }
    if (!isset(sr_survey_theme_options()[$settings['theme_key']])) {
        $settings['theme_key'] = sr_survey_theme_key('');
    }

    return $settings;
}

function sr_survey_settings_from_post(): array
{
    $skinKey = sr_survey_clean_key(sr_post_string('skin_key', 40), 40);
    $themeKey = sr_view_theme_post_key(sr_post_string('theme_key', 80));
    $settings = sr_survey_normalize_settings([
        'layout_key' => sr_public_layout_normalize_key(sr_post_string('layout_key', 80)),
        'theme_key' => $themeKey,
        'skin_key' => $skinKey,
        'layout_primary_menu_key' => sr_survey_clean_layout_menu_key(sr_post_string('layout_primary_menu_key', 60)),
        'layout_extra_menu_keys_json' => sr_survey_layout_extra_menu_items_from_pair_values($_POST['layout_extra_menu_area_keys'] ?? [], $_POST['layout_extra_menu_labels'] ?? [], $_POST['layout_extra_menu_keys'] ?? []),
        'default_status' => sr_post_string('default_status', 20),
        'default_login_required' => ($_POST['default_login_required'] ?? '') === '1',
        'default_consent_required' => ($_POST['default_consent_required'] ?? '') === '1',
        'default_response_limit_policy' => sr_survey_clean_key(sr_post_string('default_response_limit_policy', 30), 30),
        'default_response_limit_period_seconds' => sr_post_string('default_response_limit_period_seconds', 20),
        'internal_embed_enabled' => ($_POST['internal_embed_enabled'] ?? '') === '1',
        'identity_view_required' => ($_POST['identity_view_required'] ?? '') === '1',
        'identity_view_adult_required' => ($_POST['identity_view_adult_required'] ?? '') === '1',
        'business_info_visible' => ($_POST['business_info_visible'] ?? '') === '1',
        'reaction_preset_key' => isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && sr_module_enabled($GLOBALS['pdo'], 'reaction') && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($GLOBALS['pdo'], sr_post_string('reaction_preset_key', 80)) : sr_survey_clean_key(sr_post_string('reaction_preset_key', 80), 80),
        'reaction_comment_preset_key' => isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && sr_module_enabled($GLOBALS['pdo'], 'reaction') && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($GLOBALS['pdo'], sr_post_string('reaction_comment_preset_key', 80)) : sr_survey_clean_key(sr_post_string('reaction_comment_preset_key', 80), 80),
        'public_list_limit' => sr_post_string('public_list_limit', 20),
    ]);
    $settings['theme_key'] = $themeKey;
    $settings['skin_key'] = $skinKey;

    return $settings;
}

function sr_survey_settings_validation_errors(PDO $pdo, array $settings): array
{
    $errors = [];
    if (!isset(sr_survey_layout_options($pdo)[(string) ($settings['layout_key'] ?? '')])) {
        $errors[] = '설문 공개 레이아웃 값이 올바르지 않습니다.';
    }
    if (!isset(sr_survey_theme_options()[(string) ($settings['theme_key'] ?? '')])) {
        $errors[] = '설문 공개 테마 값이 올바르지 않습니다.';
    }
    if (!isset(sr_survey_skin_options()[(string) ($settings['skin_key'] ?? '')])) {
        $errors[] = '설문 스킨 값이 올바르지 않습니다.';
    }
    $siteMenuOptions = [];
    if (sr_module_enabled($pdo, 'site_menu') && is_file(SR_ROOT . '/modules/site_menu/helpers.php')) {
        require_once SR_ROOT . '/modules/site_menu/helpers.php';
        $siteMenuOptions = sr_site_menu_options($pdo);
    }
    foreach (array_merge([(string) ($settings['layout_primary_menu_key'] ?? '')], sr_survey_layout_extra_menu_keys_from_value($settings['layout_extra_menu_keys_json'] ?? [])) as $menuKey) {
        if ($menuKey !== '' && !isset($siteMenuOptions[$menuKey])) {
            $errors[] = '설문 레이아웃 사이트 메뉴 값이 올바르지 않습니다.';
            break;
        }
    }
    if ((string) ($settings['default_response_limit_policy'] ?? '') === 'per_period' && (int) ($settings['default_response_limit_period_seconds'] ?? 0) < 1) {
        $errors[] = '기본 응답 제한이 기간당 1회이면 제한 기간을 1초 이상 입력해야 합니다.';
    }
    $identityVerificationModuleAvailable = sr_module_enabled($pdo, 'identity_verification')
        && is_file(SR_ROOT . '/modules/identity_verification/helpers.php');
    if ($identityVerificationModuleAvailable) {
        require_once SR_ROOT . '/modules/identity_verification/helpers.php';
    }
    $identityViewAvailable = $identityVerificationModuleAvailable
        && function_exists('sr_identity_verification_available')
        && sr_identity_verification_available($pdo, 'survey.view');
    $identityViewAdultAvailable = $identityVerificationModuleAvailable
        && function_exists('sr_identity_verification_available')
        && sr_identity_verification_available($pdo, 'survey.view.adult');
    if (!$identityViewAvailable && !empty($settings['identity_view_required'])) {
        $errors[] = '설문 참여 본인확인을 사용하려면 본인확인 사용을 켜고 설문 참여 목적을 지원하는 제공자를 설정하세요.';
    }
    if (!$identityViewAdultAvailable && !empty($settings['identity_view_adult_required'])) {
        $errors[] = '설문 참여 성인 본인확인을 사용하려면 본인확인 사용, 생년월일 사용, 설문 성인 참여 목적 제공자를 설정하세요.';
    }
    $reactionAvailable = sr_module_enabled($pdo, 'reaction')
        && is_file(SR_ROOT . '/modules/reaction/helpers.php');
    if (!$reactionAvailable && ((string) ($settings['reaction_preset_key'] ?? '') !== '' || (string) ($settings['reaction_comment_preset_key'] ?? '') !== '')) {
        $errors[] = '설문 리액션 기본값을 사용하려면 리액션 모듈을 먼저 설치하고 활성화하세요.';
    }

    return $errors;
}

function sr_survey_public_layout_context(array $settings, array $context = []): array
{
    $layoutKey = sr_public_layout_normalize_key((string) ($settings['layout_key'] ?? ''));
    if ($layoutKey !== '') {
        $context['layout_key'] = $layoutKey;
    }
    $themeKey = sr_survey_theme_key((string) ($settings['theme_key'] ?? ''));
    if ($themeKey !== '') {
        $context['theme_key'] = $themeKey;
    }
    $context['consumer_domain'] = 'survey';
    $context['style_profile'] = 'module';
    $context['module_home_url'] = sr_url('/survey');
    $context['module_label'] = '설문';
    $context['module_menu_label'] = '설문 메뉴';
    $context['business_info_visible'] = !array_key_exists('business_info_visible', $settings) || !empty($settings['business_info_visible']);

    $stylesheets = is_array($context['stylesheets'] ?? null) ? $context['stylesheets'] : [];
    $stylesheets[] = sr_public_layout_module_theme_asset_url('survey', $themeKey, 'reset.css');
    $stylesheets[] = sr_public_layout_module_theme_asset_url('survey', $themeKey, 'common.css');
    $stylesheets[] = sr_public_layout_module_theme_asset_url('survey', $themeKey, 'module.css');
    $themeStylesheet = sr_module_view_theme_stylesheet_url('survey', $themeKey);
    if ($themeStylesheet !== '') {
        $stylesheets[] = $themeStylesheet;
    }
    $skinKey = sr_survey_skin_key((string) ($settings['skin_key'] ?? 'basic'));
    $includeSkinAssets = !array_key_exists('include_skin_assets', $context) || !empty($context['include_skin_assets']);
    unset($context['include_skin_assets']);
    if ($includeSkinAssets) {
        $skinDefinitions = sr_survey_skin_option_definitions();
        foreach ((array) ($skinDefinitions[$skinKey]['stylesheets'] ?? []) as $skinStylesheet) {
            if (is_string($skinStylesheet)) {
                $stylesheets[] = $skinStylesheet;
            }
        }
    }
    $context['stylesheets'] = array_values(array_unique($stylesheets));
    $scripts = is_array($context['scripts'] ?? null) ? $context['scripts'] : [];
    $scripts[] = '/modules/survey/assets/module.js';
    $context['scripts'] = array_values(array_unique($scripts));
    $bodyClass = sr_ui_icon_class_attr((string) ($context['body_class'] ?? ''));
    $context['body_class'] = $includeSkinAssets ? trim($bodyClass . ' survey-skin-' . $skinKey) : $bodyClass;
    if ($themeKey !== sr_public_theme_default_key()) {
        $context['body_class'] = trim((string) $context['body_class'] . ' survey-view-theme-' . $themeKey);
    }

    $siteMenus = ['primary' => sr_survey_clean_layout_menu_key((string) ($settings['layout_primary_menu_key'] ?? 'header'))];
    $context['site_menus'] = array_merge(is_array($context['site_menus'] ?? null) ? $context['site_menus'] : [], $siteMenus);
    $context['site_extra_menus'] = sr_survey_layout_extra_menu_items_from_settings($settings);

    return $context;
}

function sr_survey_ui_kit_layout_context(array $settings, array $context = []): array
{
    $context = sr_survey_public_layout_context($settings, $context);
    $themeKey = sr_survey_theme_key((string) ($settings['theme_key'] ?? ''));
    $stylesheets = is_array($context['stylesheets'] ?? null) ? $context['stylesheets'] : [];
    $stylesheets[] = sr_public_layout_module_theme_asset_url('survey', $themeKey, 'common.css');
    $stylesheets[] = sr_public_layout_module_theme_asset_url('survey', $themeKey, 'ui-kit-layout.css');
    $context['stylesheets'] = array_values(array_unique($stylesheets));

    return $context;
}

function sr_survey_display_settings_for_survey(array $settings, array $survey): array
{
    $settings = sr_survey_normalize_settings($settings);
    $skinKey = sr_survey_clean_optional_skin_key((string) ($survey['skin_key'] ?? ''));
    if ($skinKey !== '') {
        $settings['skin_key'] = $skinKey;
    }

    return $settings;
}

function sr_survey_enforce_identity_view_policy(PDO $pdo, array $survey, array $settings, ?array &$currentAccount, bool $canPreviewAsAdmin): void
{
    if ($canPreviewAsAdmin || (empty($settings['identity_view_required']) && empty($settings['identity_view_adult_required']))) {
        return;
    }

    $currentAccount = sr_member_require_login($pdo);
    if (!sr_module_enabled($pdo, 'identity_verification') || !is_file(SR_ROOT . '/modules/identity_verification/helpers.php')) {
        sr_render_error(403, '이 설문에 참여하려면 본인확인이 필요합니다. 본인확인 기능을 사용할 수 없어 참여할 수 없습니다.');
    }

    require_once SR_ROOT . '/modules/identity_verification/helpers.php';
    $returnUrl = '/survey/' . (string) ($survey['survey_key'] ?? '');
    $accountId = (int) ($currentAccount['id'] ?? 0);
    if (!empty($settings['identity_view_required'])) {
        $policy = sr_identity_verification_requirement_policy($pdo, $accountId, 'survey.view', 'required', $returnUrl);
        if (empty($policy['satisfied'])) {
            sr_render_error(403, '이 설문에 참여하려면 본인확인이 필요합니다. 본인확인을 완료한 뒤 다시 열어 주세요.');
        }
    }
    if (!empty($settings['identity_view_adult_required'])) {
        $available = sr_identity_verification_available($pdo, 'survey.view.adult');
        if (!$available || !sr_identity_verification_account_satisfies_adult($pdo, $accountId, 'survey.view.adult')) {
            sr_render_error(403, '이 설문에 참여하려면 성인 본인확인이 필요합니다. 성인 본인확인을 완료한 뒤 다시 열어 주세요.');
        }
    }
}

function sr_survey_skin_view_file(array $settings, string $view): string
{
    if (!in_array($view, sr_survey_skin_views(), true)) {
        throw new InvalidArgumentException('Unknown survey skin view.');
    }

    $skinKey = sr_survey_skin_key((string) ($settings['skin_key'] ?? 'basic'));
    $definitions = sr_survey_skin_option_definitions();
    $file = (string) ($definitions[$skinKey]['views'][$view] ?? '');
    if ($skinKey !== 'basic' && is_file($file)) {
        return $file;
    }

    $fallback = (string) ($definitions['basic']['views'][$view] ?? (SR_ROOT . '/modules/survey/skins/basic/' . $view . '.php'));
    if ($skinKey !== 'basic' || !is_file($file)) {
        error_log('survey_skin_fallback module=survey skin_key=' . $skinKey . ' view=' . $view . ' fallback_file=' . $fallback);
    }
    if (!is_file($fallback)) {
        throw new RuntimeException('Default survey skin view is missing: ' . $view);
    }

    return $fallback;
}

function sr_survey_public_view_file(PDO $pdo, array $settings, string $view): string
{
    if (!in_array($view, sr_survey_skin_views(), true)) {
        throw new InvalidArgumentException('Unknown survey public view.');
    }

    $themeFile = sr_survey_theme_view_file($settings, $view);
    return $themeFile !== null ? $themeFile : sr_survey_skin_view_file($settings, $view);
}

function sr_survey_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'survey' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('Survey module was not found.');
    }
    $settings = sr_survey_normalize_settings($settings);
    $now = sr_now();
    $save = $pdo->prepare(
        'INSERT INTO sr_module_settings
            (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    foreach ($settings as $key => $value) {
        $valueType = $key === 'layout_extra_menu_keys_json' ? 'json' : (is_bool($value) ? 'bool' : (is_int($value) ? 'int' : 'string'));
        $settingValue = $key === 'layout_extra_menu_keys_json'
            ? sr_survey_layout_extra_menu_keys_json($value)
            : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        $save->execute([
            'module_id' => (int) $module['id'],
            'setting_key' => (string) $key,
            'setting_value' => $settingValue,
            'value_type' => $valueType,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    sr_clear_module_settings_cache('survey');
}

function sr_survey_coupon_definition_reference_count(PDO $pdo, array $target, array $context): int
{
    return count(sr_survey_coupon_definition_reference_rows($pdo, $target, $context));
}

function sr_survey_coupon_definition_reference_rows(PDO $pdo, array $target, array $context): array
{
    $definitionId = (int) ($target['target_id'] ?? 0);
    if ($definitionId < 1) {
        return [];
    }
    if (!sr_survey_reward_table_available($pdo, 'sr_survey_reward_policies') || !sr_survey_reward_table_available($pdo, 'sr_survey_forms')) {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT rp.id AS reward_policy_id, rp.status AS reward_policy_status, s.id AS survey_id, s.survey_key, s.title, s.status AS survey_status, s.updated_at
             FROM sr_survey_reward_policies rp
             INNER JOIN sr_survey_forms s ON s.id = rp.survey_id
             WHERE rp.reward_provider = \'coupon\'
               AND rp.reward_code = :definition_id
               AND s.deleted_at IS NULL
             ORDER BY s.updated_at DESC, s.id DESC'
        );
        $stmt->execute(['definition_id' => (string) $definitionId]);
    } catch (Throwable) {
        return [];
    }

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'consumer_module_key' => 'survey',
            'reference_type' => 'survey_reward_coupon',
            'reference_id' => (string) (int) ($row['reward_policy_id'] ?? 0),
            'survey_id' => (int) ($row['survey_id'] ?? 0),
            'target_type' => 'coupon_definition',
            'target_id' => (string) $definitionId,
            'title' => (string) ($row['title'] ?? ''),
            'policy_status' => (string) ($row['survey_status'] ?? ''),
            'summary' => '설문 보상: ' . (string) ($row['survey_key'] ?? ''),
            'admin_url' => '/admin/surveys?mode=edit&id=' . rawurlencode((string) (int) ($row['survey_id'] ?? 0)),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'metadata' => [
                'reward_policy_status' => (string) ($row['reward_policy_status'] ?? ''),
            ],
        ];
    }

    return $rows;
}

function sr_survey_coupon_definition_reference_health(PDO $pdo, array $target, array $row, array $context): array
{
    $surveyId = (int) ($row['survey_id'] ?? 0);
    if ($surveyId < 1) {
        return ['status' => 'unknown', 'message' => '설문 참조를 확인할 수 없습니다.'];
    }

    $surveyStatus = (string) ($row['policy_status'] ?? '');
    $metadata = is_array($row['metadata'] ?? null) ? $row['metadata'] : [];
    $rewardPolicyStatus = (string) ($metadata['reward_policy_status'] ?? '');
    if ($surveyStatus !== 'active' || $rewardPolicyStatus !== 'active') {
        return [
            'status' => 'disabled_target',
            'policy_status' => $surveyStatus,
            'message' => '설문 또는 보상 정책이 사용 상태가 아닙니다.',
        ];
    }

    $definitionId = (int) ($target['target_id'] ?? $row['target_id'] ?? 0);
    if ($definitionId > 0 && sr_module_enabled($pdo, 'coupon') && is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        require_once SR_ROOT . '/modules/coupon/helpers.php';
        if (function_exists('sr_coupon_definition_by_id') && function_exists('sr_coupon_definition_allows_issue')) {
            $definition = sr_coupon_definition_by_id($pdo, $definitionId);
            if (!is_array($definition)) {
                return ['status' => 'missing_target', 'message' => '보상 쿠폰 정의를 찾을 수 없습니다.'];
            }
            if (!sr_coupon_definition_allows_issue((string) ($definition['status'] ?? ''))) {
                return [
                    'status' => 'disabled_target',
                    'policy_status' => $surveyStatus,
                    'message' => '보상 쿠폰이 지급 가능한 상태가 아닙니다.',
                ];
            }
        }
    }

    return ['status' => 'ok', 'policy_status' => $surveyStatus];
}

function sr_survey_coupon_definition_reference_admin_url(array $row, array $context): string
{
    $surveyId = (int) ($row['survey_id'] ?? 0);

    return $surveyId > 0 ? '/admin/surveys?mode=edit&id=' . rawurlencode((string) $surveyId) : '/admin/surveys';
}

function sr_survey_member_group_reference_count(PDO $pdo, array $target, array $context): int
{
    return count(sr_survey_member_group_reference_rows($pdo, $target, $context));
}

function sr_survey_member_group_reference_rows(PDO $pdo, array $target, array $context): array
{
    $targetKey = (string) ($target['target_key'] ?? '');
    if ($targetKey === '') {
        require_once SR_ROOT . '/modules/member/helpers/groups.php';
        $groupId = (int) ($target['target_id'] ?? 0);
        if ($groupId > 0 && sr_member_groups_table_exists($pdo)) {
            $stmt = $pdo->prepare('SELECT group_key FROM sr_member_groups WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $groupId]);
            $targetKey = (string) $stmt->fetchColumn();
        }
    }
    $targetKey = strtolower(trim($targetKey));
    if ($targetKey === '') {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT id, survey_key, title, status, member_group_keys_json, updated_at
         FROM sr_survey_forms
         WHERE member_group_keys_json IS NOT NULL
           AND member_group_keys_json <> \'\'
           AND deleted_at IS NULL
           AND INSTR(member_group_keys_json, :target_key_json) > 0
         ORDER BY updated_at DESC, id DESC
         LIMIT 500'
    );
    $stmt->execute(['target_key_json' => '"' . $targetKey . '"']);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $groupKeys = sr_survey_member_group_keys_from_json($row['member_group_keys_json'] ?? '[]');
        if (!in_array($targetKey, $groupKeys, true)) {
            continue;
        }
        $rows[] = [
            'consumer_module_key' => 'survey',
            'reference_type' => 'survey_member_group_target',
            'reference_id' => 'survey_form:' . (string) (int) ($row['id'] ?? 0),
            'target_type' => 'member_group',
            'target_id' => (string) ($target['target_id'] ?? ''),
            'target_key' => $targetKey,
            'title' => (string) ($row['title'] ?? ''),
            'policy_status' => (string) ($row['status'] ?? ''),
            'summary' => '설문 참여 대상: ' . (string) ($row['survey_key'] ?? ''),
            'admin_url' => '/admin/surveys?mode=edit&id=' . rawurlencode((string) (int) ($row['id'] ?? 0)),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    return $rows;
}

function sr_survey_member_group_reference_health(PDO $pdo, array $target, array $row, array $context): array
{
    $status = (string) ($row['policy_status'] ?? '');

    return $status === 'active'
        ? ['status' => 'ok', 'policy_status' => $status]
        : ['status' => 'disabled_target', 'policy_status' => $status, 'message' => '설문이 공개 상태가 아닙니다.'];
}

function sr_survey_member_group_reference_admin_url(array $row, array $context): string
{
    return (string) ($row['admin_url'] ?? '/admin/surveys');
}

function sr_survey_reward_providers(): array
{
    return ['ledger_asset', 'coupon'];
}

function sr_survey_reward_provider_label(string $provider): string
{
    return [
        'ledger_asset' => '포인트/금액',
        'coupon' => '쿠폰 발급',
    ][$provider] ?? $provider;
}

function sr_survey_reward_dedupe_scopes(): array
{
    return ['per_survey', 'per_response'];
}

function sr_survey_reward_dedupe_scope_label(string $scope): string
{
    return [
        'per_survey' => '설문당 1회',
        'per_response' => '응답마다',
    ][$scope] ?? $scope;
}

function sr_survey_clean_admin_datetime(string $value): ?string
{
    return sr_clean_admin_datetime($value);
}

function sr_survey_datetime_local_value(mixed $value): string
{
    return sr_datetime_local_value($value);
}

function sr_survey_time_html(string $value): string
{
    return sr_relative_time_html($value);
}

function sr_survey_public_window_is_open(array $survey, ?string $now = null): bool
{
    $now = $now ?? sr_now();
    $startsAt = trim((string) ($survey['starts_at'] ?? ''));
    $endsAt = trim((string) ($survey['ends_at'] ?? ''));

    return ($startsAt === '' || $startsAt <= $now) && ($endsAt === '' || $endsAt >= $now);
}

function sr_survey_public_form_count(PDO $pdo): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM sr_survey_forms
         WHERE status = 'active'
           AND deleted_at IS NULL
           AND public_listed = 1
           AND (starts_at IS NULL OR starts_at <= :now_start)
           AND (ends_at IS NULL OR ends_at >= :now_end)"
    );
    $now = sr_now();
    $stmt->execute(['now_start' => $now, 'now_end' => $now]);

    return max(0, (int) $stmt->fetchColumn());
}

function sr_survey_public_forms(PDO $pdo, int $limit = 50, int $offset = 0): array
{
    $stmt = $pdo->prepare(
        "SELECT id, survey_key, title, description, cover_image_url, updated_at
         FROM sr_survey_forms
         WHERE status = 'active'
           AND deleted_at IS NULL
           AND public_listed = 1
           AND (starts_at IS NULL OR starts_at <= :now_start)
           AND (ends_at IS NULL OR ends_at >= :now_end)
         ORDER BY updated_at DESC, id DESC
         LIMIT :limit_value OFFSET :offset_value"
    );
    $now = sr_now();
    $stmt->bindValue('now_start', $now);
    $stmt->bindValue('now_end', $now);
    $stmt->bindValue('limit_value', max(1, min(100, $limit)), PDO::PARAM_INT);
    $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_survey_coupon_target_search(PDO $pdo, string $targetType, string $keyword, int $limit = 20): array
{
    if ($targetType !== 'survey') {
        return [];
    }

    $keyword = trim(preg_replace('/\s+/', ' ', $keyword) ?? '');
    $keyword = function_exists('mb_substr') ? mb_substr($keyword, 0, 120) : substr($keyword, 0, 120);
    $limit = max(1, min(30, $limit));
    $keywordLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
    $idValue = preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1 ? (int) $keyword : 0;
    $where = $keyword === '' ? '1 = 1' : "(id = :id OR title LIKE :keyword_like ESCAPE '\\\\' OR survey_key LIKE :keyword_like ESCAPE '\\\\')";

    $stmt = $pdo->prepare(
        'SELECT id, survey_key, title, status, updated_at
         FROM sr_survey_forms
         WHERE deleted_at IS NULL
           AND ' . $where . '
         ORDER BY id DESC
         LIMIT ' . $limit
    );
    $stmt->execute($keyword === '' ? [] : ['id' => $idValue, 'keyword_like' => $keywordLike]);

    return array_map(static function (array $row): array {
        return [
            'reference_type' => 'survey',
            'reference_id' => (string) (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'reason' => '설문 #' . (string) (int) ($row['id'] ?? 0),
            'member_name' => '(' . (string) ($row['survey_key'] ?? '') . ')',
            'member_email' => '',
            'created_at' => '상태: ' . (string) ($row['status'] ?? ''),
        ];
    }, $stmt->fetchAll());
}

function sr_survey_homepage_candidates(PDO $pdo): array
{
    return [
        [
            'module_key' => 'survey',
            'label' => '설문·여론조사',
            'path' => '/survey',
            'detail' => '/survey',
            'available' => true,
        ],
    ];
}

function sr_survey_homepage_path_is_available(PDO $pdo, string $homePath): ?bool
{
    if ($homePath === '/survey') {
        return true;
    }

    if (str_starts_with($homePath, '/survey/')) {
        $surveyKey = rawurldecode(substr($homePath, strlen('/survey/')));
        $survey = sr_survey_by_key($pdo, $surveyKey);
        return is_array($survey)
            && (string) ($survey['status'] ?? '') === 'active'
            && (int) ($survey['public_listed'] ?? 1) === 1
            && (int) ($survey['login_required'] ?? 1) !== 1
            && sr_survey_member_group_keys_from_json($survey['member_group_keys_json'] ?? '[]') === []
            && sr_survey_public_window_is_open($survey);
    }

    return null;
}

function sr_survey_by_key(PDO $pdo, string $surveyKey): ?array
{
    if (!sr_survey_key_is_valid($surveyKey)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM sr_survey_forms WHERE survey_key = :survey_key AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['survey_key' => $surveyKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_survey_should_count_view(int $surveyId): bool
{
    if ($surveyId < 1) {
        return false;
    }

    $sessionKey = 'sr_survey_viewed_forms';
    $viewed = isset($_SESSION[$sessionKey]) && is_array($_SESSION[$sessionKey]) ? $_SESSION[$sessionKey] : [];
    $surveyKey = (string) $surveyId;
    if (isset($viewed[$surveyKey])) {
        return false;
    }

    $viewed[$surveyKey] = time();
    if (count($viewed) > 500) {
        asort($viewed);
        $viewed = array_slice($viewed, -500, null, true);
    }
    $_SESSION[$sessionKey] = $viewed;

    return true;
}

function sr_survey_increment_view_count(PDO $pdo, int $surveyId): void
{
    if ($surveyId < 1) {
        return;
    }

    $stmt = $pdo->prepare('UPDATE sr_survey_forms SET view_count = view_count + 1 WHERE id = :id');
    $stmt->execute(['id' => $surveyId]);
}

function sr_survey_admin_survey_sort_options(): array
{
    return [
        'survey_key' => ['columns' => ['s.survey_key', 's.id']],
        'title' => ['columns' => ['s.title', 's.id']],
        'status' => ['columns' => ['s.status', 's.id']],
        'qa_status' => ['columns' => ['s.qa_status', 's.id']],
        'response_count' => ['columns' => ['response_count', 's.id']],
        'view_count' => ['columns' => ['s.view_count', 's.id']],
        'reward_enabled' => ['columns' => ['s.reward_enabled', 's.id']],
        'updated_at' => ['columns' => ['s.updated_at', 's.id']],
    ];
}

function sr_survey_admin_survey_default_sort(): array
{
    return sr_admin_sort_default('updated_at', 'desc');
}

function sr_survey_by_id(PDO $pdo, int $surveyId): ?array
{
    if ($surveyId < 1) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM sr_survey_forms WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $surveyId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_survey_admin_survey_options(PDO $pdo, int $includeSurveyId = 0, int $limit = 300): array
{
    $limit = max(1, min(1000, $limit));
    $stmt = $pdo->query(
        'SELECT id, survey_key, title
         FROM sr_survey_forms
         WHERE deleted_at IS NULL
         ORDER BY updated_at DESC, id DESC
         LIMIT ' . $limit
    );
    $options = $stmt !== false ? $stmt->fetchAll() : [];
    if ($includeSurveyId < 1) {
        return $options;
    }
    foreach ($options as $option) {
        if ((int) ($option['id'] ?? 0) === $includeSurveyId) {
            return $options;
        }
    }

    $currentStmt = $pdo->prepare('SELECT id, survey_key, title FROM sr_survey_forms WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $currentStmt->execute(['id' => $includeSurveyId]);
    $current = $currentStmt->fetch();
    if (is_array($current)) {
        $options[] = $current;
    }

    return $options;
}

function sr_survey_questions_with_choices(PDO $pdo, int $surveyId): array
{
    $stmt = $pdo->prepare('SELECT * FROM sr_survey_questions WHERE survey_id = :survey_id ORDER BY sort_order ASC, id ASC');
    $stmt->execute(['survey_id' => $surveyId]);
    $questions = $stmt->fetchAll();
    $choiceStmt = $pdo->prepare('SELECT * FROM sr_survey_choices WHERE question_id = :question_id ORDER BY sort_order ASC, id ASC');
    foreach ($questions as $index => $question) {
        $choiceStmt->execute(['question_id' => (int) ($question['id'] ?? 0)]);
        $questions[$index]['choices'] = $choiceStmt->fetchAll();
    }

    return $questions;
}

function sr_survey_statistics_summary(PDO $pdo, int $surveyId): array
{
    $summary = ['total_count' => 0, 'accepted_count' => 0, 'flagged_count' => 0, 'excluded_count' => 0, 'anonymous_count' => 0];
    if ($surveyId < 1) {
        return $summary;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total_count,
                SUM(CASE WHEN quality_status = 'accepted' THEN 1 ELSE 0 END) AS accepted_count,
                SUM(CASE WHEN quality_status = 'flagged' THEN 1 ELSE 0 END) AS flagged_count,
                SUM(CASE WHEN quality_status = 'excluded' THEN 1 ELSE 0 END) AS excluded_count,
                SUM(CASE WHEN account_id IS NULL THEN 1 ELSE 0 END) AS anonymous_count
         FROM sr_survey_responses
         WHERE survey_id = :survey_id
           AND is_test = 0"
    );
    $stmt->execute(['survey_id' => $surveyId]);
    $row = $stmt->fetch();

    return is_array($row) ? array_merge($summary, $row) : $summary;
}

function sr_survey_statistics_choice_counts(PDO $pdo, int $surveyId): array
{
    if ($surveyId < 1) {
        return [];
    }

    $choiceResponseStats = [];
    $stmt = $pdo->prepare(
        "SELECT r.id AS response_id, a.question_key, a.choice_key
         FROM sr_survey_response_answers a
         INNER JOIN sr_survey_responses r ON r.id = a.response_id
         WHERE r.survey_id = :survey_id
           AND r.quality_status <> 'excluded'
           AND r.is_test = 0
           AND a.choice_key IS NOT NULL
           AND a.choice_key <> ''"
    );
    $stmt->execute(['survey_id' => $surveyId]);
    foreach ($stmt->fetchAll() as $row) {
        $responseId = (int) ($row['response_id'] ?? 0);
        $questionKey = (string) ($row['question_key'] ?? '');
        foreach (array_filter(array_map('trim', explode(',', (string) ($row['choice_key'] ?? '')))) as $choiceKey) {
            if ($responseId > 0 && $questionKey !== '' && $choiceKey !== '') {
                $choiceResponseStats[$questionKey][$choiceKey][$responseId] = true;
            }
        }
    }

    $choiceStats = [];
    foreach ($choiceResponseStats as $questionKey => $choiceRows) {
        foreach ($choiceRows as $choiceKey => $responseIds) {
            $choiceStats[$questionKey][$choiceKey] = count($responseIds);
        }
    }

    return $choiceStats;
}

function sr_survey_statistics_number_stats(PDO $pdo, int $surveyId): array
{
    if ($surveyId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT a.question_key, COUNT(a.answer_number) AS answer_count, AVG(a.answer_number) AS average_value, MIN(a.answer_number) AS min_value, MAX(a.answer_number) AS max_value
         FROM sr_survey_response_answers a
         INNER JOIN sr_survey_responses r ON r.id = a.response_id
         WHERE r.survey_id = :survey_id
           AND r.quality_status <> 'excluded'
           AND r.is_test = 0
           AND a.answer_number IS NOT NULL
         GROUP BY a.question_key"
    );
    $stmt->execute(['survey_id' => $surveyId]);

    $numberStats = [];
    foreach ($stmt->fetchAll() as $row) {
        $numberStats[(string) ($row['question_key'] ?? '')] = $row;
    }

    return $numberStats;
}

function sr_survey_admin_export_limits(): array
{
    return [
        'raw' => 5000,
        'analysis' => 20000,
        'codebook' => 10000,
    ];
}

function sr_survey_csv_cell(mixed $value): string
{
    $value = (string) $value;
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
        return "'" . $value;
    }

    return $value;
}

function sr_survey_csv_row($output, array $row): void
{
    fputcsv($output, array_map('sr_survey_csv_cell', $row));
}

function sr_survey_admin_export_response_filter(int $surveyId, string $qualityFilter, bool $includeTest): array
{
    $where = ['s.deleted_at IS NULL'];
    $params = [];
    if ($surveyId > 0) {
        $where[] = 'r.survey_id = :survey_id';
        $params['survey_id'] = $surveyId;
    }
    if ($qualityFilter !== '' && in_array($qualityFilter, sr_survey_quality_statuses(), true)) {
        $where[] = 'r.quality_status = :quality_status';
        $params['quality_status'] = $qualityFilter;
    }
    if (!$includeTest) {
        $where[] = 'r.is_test = 0';
    }

    return ['where_sql' => implode(' AND ', $where), 'params' => $params];
}

function sr_survey_admin_export_codebook_rows(PDO $pdo, int $surveyId, int $limit): array
{
    $where = ['s.deleted_at IS NULL'];
    $params = [];
    if ($surveyId > 0) {
        $where[] = 's.id = :survey_id';
        $params['survey_id'] = $surveyId;
    }

    $stmt = $pdo->prepare(
        'SELECT s.id AS survey_id, s.survey_key, s.title, s.questionnaire_version,
                q.question_key, q.question_type, q.prompt, q.required, q.analysis_note, q.number_min, q.number_max, q.scale_points, q.nonresponse_policy,
                c.choice_key, c.label AS choice_label, c.is_other, c.is_nonresponse
         FROM sr_survey_forms s
         INNER JOIN sr_survey_questions q ON q.survey_id = s.id
         LEFT JOIN sr_survey_choices c ON c.question_id = q.id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY s.id ASC, q.sort_order ASC, q.id ASC, c.sort_order ASC, c.id ASC
         LIMIT ' . (string) max(1, min(10000, $limit))
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_survey_admin_export_analysis_rows(PDO $pdo, int $surveyId, string $qualityFilter, bool $includeTest, int $limit): array
{
    $filter = sr_survey_admin_export_response_filter($surveyId, $qualityFilter, $includeTest);
    $stmt = $pdo->prepare(
        'SELECT r.id, r.survey_id, s.survey_key, r.quality_status, r.submitted_at,
                a.question_key, a.choice_key, a.answer_text, a.answer_number, a.other_text
         FROM sr_survey_responses r
         INNER JOIN sr_survey_forms s ON s.id = r.survey_id
         INNER JOIN sr_survey_response_answers a ON a.response_id = r.id
         WHERE ' . (string) $filter['where_sql'] . '
           AND r.quality_status <> \'excluded\'
         ORDER BY r.submitted_at DESC, r.id DESC, a.id ASC
         LIMIT ' . (string) max(1, min(20000, $limit))
    );
    $stmt->execute((array) $filter['params']);

    return $stmt->fetchAll();
}

function sr_survey_admin_export_raw_rows(PDO $pdo, int $surveyId, string $qualityFilter, bool $includeTest, int $limit): array
{
    $filter = sr_survey_admin_export_response_filter($surveyId, $qualityFilter, $includeTest);
    $stmt = $pdo->prepare(
        'SELECT r.id, r.survey_id, s.survey_key, s.title, r.account_id, r.status, r.quality_status, r.is_test, r.submitted_at, r.rewarded_at,
                r.answer_snapshot_json, r.consent_snapshot_json, r.metadata_snapshot_json
         FROM sr_survey_responses r
         INNER JOIN sr_survey_forms s ON s.id = r.survey_id
         WHERE ' . (string) $filter['where_sql'] . '
         ORDER BY r.submitted_at DESC, r.id DESC
         LIMIT ' . (string) max(1, min(5000, $limit))
    );
    $stmt->execute((array) $filter['params']);

    return $stmt->fetchAll();
}
