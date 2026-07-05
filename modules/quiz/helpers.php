<?php

require_once dirname(__DIR__, 2) . '/core/helpers/common.php';
require_once dirname(__DIR__, 2) . '/core/helpers/upload.php';
require_once SR_ROOT . '/core/helpers/url-embed.php';
require_once SR_ROOT . '/modules/quiz/helpers/admin.php';
require_once SR_ROOT . '/modules/quiz/helpers/attempts.php';
require_once SR_ROOT . '/modules/quiz/helpers/comments.php';
require_once SR_ROOT . '/modules/quiz/helpers/rewards.php';

function sr_quiz_key_is_valid(string $key): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,63}\z/', $key) === 1;
}

function sr_quiz_key_is_reserved(string $key): bool
{
    return in_array($key, ['comment', 'ui_kit', 'admin'], true);
}

function sr_quiz_clean_key(string $value, int $maxLength = 64): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_]/', '', $value) ?? '';
    $value = preg_replace('/\A[^a-z]+/', '', $value) ?? '';

    return substr($value, 0, $maxLength);
}

function sr_quiz_internal_return_path(string $value): string
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

function sr_quiz_clean_text(string $value, int $maxLength): string
{
    return sr_clean_text($value, $maxLength);
}

function sr_quiz_clean_single_line(string $value, int $maxLength): string
{
    return sr_clean_single_line($value, $maxLength);
}

function sr_quiz_clean_cover_image_url(string $value): string
{
    $value = sr_quiz_clean_single_line($value, 255);
    if ($value === '') {
        return '';
    }
    if (sr_is_http_url($value) || sr_is_safe_relative_url($value)) {
        return $value;
    }

    return '';
}

function sr_quiz_cover_image_html(array $quiz, string $className, string $alt = ''): string
{
    $imageUrl = sr_quiz_clean_cover_image_url((string) ($quiz['cover_image_url'] ?? ''));
    if ($imageUrl === '') {
        return '';
    }
    $src = sr_is_http_url($imageUrl) ? $imageUrl : sr_url($imageUrl);

    return '<img class="' . sr_e($className) . '" src="' . sr_e($src) . '" alt="' . sr_e($alt) . '" loading="lazy" decoding="async">';
}

function sr_quiz_cover_image_upload_max_bytes(): int
{
    return 5242880;
}

function sr_quiz_cover_image_upload_was_provided(mixed $file): bool
{
    return sr_upload_was_provided($file);
}

function sr_quiz_cover_image_format_for_mime(string $mimeType): string
{
    return sr_image_format_for_mime($mimeType);
}

function sr_quiz_cover_image_mime_is_allowed(string $mimeType): bool
{
    return sr_image_mime_is_allowed($mimeType);
}

function sr_quiz_upload_cover_image(array $file): ?array
{
    if (!sr_quiz_cover_image_upload_was_provided($file)) {
        return null;
    }

    $validated = sr_upload_validate_file($file, [
        'max_bytes' => sr_quiz_cover_image_upload_max_bytes(),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
    ]);

    $targetFormat = sr_quiz_cover_image_format_for_mime((string) $validated['mime_type']);
    if ($targetFormat === '') {
        throw new RuntimeException('허용되지 않은 커버 이미지 형식입니다.');
    }

    $datePath = date('Y/m');
    $directory = SR_ROOT . '/storage/tmp/quiz-cover-images/' . $datePath;
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
    if (!sr_quiz_cover_image_mime_is_allowed($storedMimeType)) {
        @unlink($targetPath);
        throw new RuntimeException('저장된 커버 이미지 MIME을 확인할 수 없습니다.');
    }

    $storageKey = 'quiz/cover-images/' . $datePath . '/' . $storedName;
    $stored = sr_storage_put_file($targetPath, $storageKey, [
        'content_type' => $storedMimeType,
    ]);
    @unlink($targetPath);

    $storageReference = sr_storage_reference((string) $stored['driver'], $storageKey);

    return [
        'driver' => (string) $stored['driver'],
        'key' => $storageKey,
        'url' => '/quiz/cover-image?file=' . rawurlencode($storageReference),
    ];
}

function sr_quiz_cover_image_storage_key_is_valid(string $key): bool
{
    return preg_match('#\Aquiz/cover-images/\d{4}/\d{2}/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $key) === 1;
}

function sr_quiz_cover_image_storage_reference(string $reference): ?array
{
    $storage = sr_storage_parse_reference($reference);
    if (!is_array($storage) || !sr_quiz_cover_image_storage_key_is_valid((string) $storage['key'])) {
        return null;
    }

    return $storage;
}

function sr_quiz_cover_image_storage_reference_from_url(string $url): ?array
{
    $url = sr_quiz_clean_cover_image_url($url);
    if ($url === '') {
        return null;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return null;
    }

    $path = (string) ($parts['path'] ?? '');
    $proxyPath = (string) (parse_url(sr_url('/quiz/cover-image'), PHP_URL_PATH) ?: '/quiz/cover-image');
    if ($path !== '/quiz/cover-image' && $path !== $proxyPath) {
        $config = sr_runtime_config();
        $s3 = sr_storage_s3_config($config);
        $baseUrl = rtrim((string) ($s3['public_base_url'] ?? ''), '/');
        if ($baseUrl === '' || !sr_is_http_url($baseUrl) || !str_starts_with($url, $baseUrl . '/')) {
            return null;
        }

        $key = rawurldecode(substr($url, strlen($baseUrl) + 1));
        if (!sr_quiz_cover_image_storage_key_is_valid($key)) {
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

    return sr_quiz_cover_image_storage_reference($reference);
}

function sr_quiz_cover_image_reference_count(PDO $pdo, string $url, int $exceptQuizId = 0): int
{
    $url = sr_quiz_clean_cover_image_url($url);
    if ($url === '') {
        return 0;
    }

    $sql = 'SELECT COUNT(*) FROM sr_quiz_sets WHERE cover_image_url = :cover_image_url';
    $params = ['cover_image_url' => $url];
    if ($exceptQuizId > 0) {
        $sql .= ' AND id <> :quiz_id';
        $params['quiz_id'] = $exceptQuizId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function sr_quiz_delete_cover_image_storage(PDO $pdo, string $url, int $exceptQuizId = 0, int $pendingFailureId = 0): array
{
    $storage = sr_quiz_cover_image_storage_reference_from_url($url);
    if (!is_array($storage)) {
        return ['attempted' => false, 'deleted' => false, 'failed' => false, 'reference' => ''];
    }

    $driver = (string) ($storage['driver'] ?? 'local');
    $key = (string) ($storage['key'] ?? '');
    $reference = $driver . ':' . $key;
    if ($key === '' || sr_quiz_cover_image_reference_count($pdo, $url, $exceptQuizId) > 0) {
        return ['attempted' => false, 'deleted' => false, 'failed' => false, 'reference' => $reference];
    }

    $failureId = $pendingFailureId > 0
        ? $pendingFailureId
        : sr_quiz_record_storage_cleanup_pending($pdo, 'quiz_cover_image', $exceptQuizId, $driver, $key);
    $deleted = sr_storage_delete($driver, $key);
    sr_quiz_update_storage_cleanup_result(
        $pdo,
        $failureId,
        $deleted,
        $deleted ? '' : '퀴즈 커버 이미지 저장소 정리에 실패했습니다.'
    );

    return [
        'attempted' => true,
        'deleted' => $deleted,
        'failed' => !$deleted,
        'reference' => $reference,
    ];
}

function sr_quiz_record_storage_cleanup_pending(PDO $pdo, string $sourceType, int $sourceId, string $driver, string $key): int
{
    if ($key === '' || !sr_storage_key_is_safe($key)) {
        return 0;
    }

    $driver = in_array($driver, ['local', 's3'], true) ? $driver : 'local';
    $now = sr_now();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO sr_quiz_storage_cleanup_failures
                (source_type, source_id, storage_driver, storage_key, status, attempt_count, last_error, created_at, updated_at)
             VALUES
                (:source_type, :source_id, :storage_driver, :storage_key, 'pending', 0, '', :created_at, :updated_at)"
        );
        $stmt->execute([
            'source_type' => sr_quiz_clean_key($sourceType),
            'source_id' => max(0, $sourceId),
            'storage_driver' => $driver,
            'storage_key' => $key,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $pdo->lastInsertId();
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'quiz_storage_cleanup_pending_record_failed');
        return 0;
    }
}

function sr_quiz_update_storage_cleanup_result(PDO $pdo, int $failureId, bool $deleted, string $errorMessage): void
{
    if ($failureId < 1) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            "UPDATE sr_quiz_storage_cleanup_failures
             SET status = :status,
                 attempt_count = attempt_count + 1,
                 last_error = :last_error,
                 updated_at = :updated_at
             WHERE id = :id"
        );
        $stmt->execute([
            'status' => $deleted ? 'cleaned' : 'pending',
            'last_error' => $deleted ? '' : sr_quiz_clean_text($errorMessage, 1000),
            'updated_at' => sr_now(),
            'id' => $failureId,
        ]);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'quiz_storage_cleanup_result_update_failed');
    }
}

function sr_quiz_statuses(): array
{
    return ['draft', 'active', 'paused', 'archived'];
}

function sr_quiz_status_label(string $status): string
{
    return [
        'draft' => '초안',
        'active' => '공개',
        'paused' => '중지',
        'archived' => '보관',
    ][$status] ?? $status;
}

function sr_quiz_modes(): array
{
    return ['scored', 'diagnostic'];
}

function sr_quiz_mode_label(string $mode): string
{
    return [
        'scored' => '점수형',
        'diagnostic' => '진단형',
    ][$mode] ?? $mode;
}

function sr_quiz_default_settings(): array
{
    return [
        'layout_key' => 'quiz.basic',
        'theme_key' => 'basic',
        'skin_key' => 'basic',
        'layout_primary_menu_key' => 'header',
        'layout_extra_menu_keys_json' => [],
        'default_status' => 'draft',
        'default_quiz_mode' => 'scored',
        'default_scoring_model' => 'correct_answer',
        'default_pass_score' => 1,
        'default_question_choice_count' => 4,
        'default_question_score' => 1,
        'default_attempt_limit_policy' => 'unlimited',
        'default_attempt_limit_period_seconds' => '',
        'default_reward_enabled' => true,
        'default_reward_provider' => 'ledger_asset',
        'default_reward_module' => '',
        'default_reward_coupon_definition_id' => '',
        'default_reward_amount' => '',
        'default_reward_dedupe_scope' => 'per_quiz',
        'default_cta_label' => '퀴즈 풀기',
        'embed_enabled' => true,
        'identity_view_required' => false,
        'identity_view_adult_required' => false,
        'reaction_preset_key' => '',
        'reaction_comment_preset_key' => '',
        'public_list_limit' => 50,
    ];
}

function sr_quiz_layout_required_targets(): array
{
    return ['quiz.home', 'quiz.view', 'quiz.result'];
}

function sr_quiz_layout_options(PDO $pdo, bool $includeInstalledModules = false): array
{
    return sr_public_layout_options_for_targets($pdo, sr_quiz_layout_required_targets(), $includeInstalledModules);
}

function sr_quiz_fallback_layout_key(PDO $pdo, ?array $site = null): string
{
    $options = sr_quiz_layout_options($pdo);
    $siteLayoutKey = sr_public_layout_key($site, $pdo);
    if (isset($options[$siteLayoutKey])) {
        return $siteLayoutKey;
    }
    if (isset($options['quiz.basic'])) {
        return 'quiz.basic';
    }

    return sr_public_layout_default_key();
}

function sr_quiz_skin_option_definitions(): array
{
    $definitions = [];
    foreach ([
        'basic' => '기본형',
        'card' => '카드형',
        'focus' => '집중형',
    ] as $skinKey => $label) {
        $views = [];
        foreach (sr_quiz_skin_views() as $view) {
            $views[$view] = SR_ROOT . '/modules/quiz/skins/' . $skinKey . '/' . $view . '.php';
        }
        $definitions[$skinKey] = [
            'skin_key' => $skinKey,
            'label' => $label,
            'source_type' => 'built_in_module',
            'source_key' => 'quiz',
            'module_key' => 'quiz',
            'contract_version' => '1.0',
            'views' => $views,
            'stylesheets' => [],
            'actions' => [],
        ];
    }

    return $definitions;
}

function sr_quiz_skin_options(): array
{
    $options = [];
    foreach (sr_quiz_skin_option_definitions() as $skinKey => $definition) {
        $options[(string) $skinKey] = (string) ($definition['label'] ?? $skinKey);
    }

    return $options;
}

function sr_quiz_skin_views(): array
{
    return ['home', 'view', 'result'];
}

function sr_quiz_skin_key(string $value): string
{
    $value = strtolower(trim($value));
    return isset(sr_quiz_skin_options()[$value]) ? $value : 'basic';
}

function sr_quiz_clean_optional_skin_key(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }

    return isset(sr_quiz_skin_options()[$value]) ? $value : '';
}

function sr_quiz_optional_option_key_from_post(string $value, array $options): string
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }

    return isset($options[$value]) ? $value : '__invalid__';
}

function sr_quiz_clean_option_key(string $value): string
{
    $value = strtolower(trim($value));
    return preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $value) === 1 ? $value : '';
}

function sr_quiz_layout_menu_slots(): array
{
    return [
        'primary' => 'layout_primary_menu_key',
    ];
}

function sr_quiz_clean_layout_menu_key(string $value): string
{
    $value = strtolower(trim($value));
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $value) === 1 ? $value : '';
}

function sr_quiz_clean_layout_extra_menu_area_key(string $value): string
{
    $value = strtolower(trim($value));
    return preg_match('/\A(?:[a-f0-9]{12}|[a-z][a-z0-9_]{0,59})\z/', $value) === 1 ? $value : '';
}

function sr_quiz_layout_extra_menu_label(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, 80) : substr($value, 0, 80);
}

function sr_quiz_layout_extra_menu_hash_key(array $usedKeys = [], string $seed = ''): string
{
    $used = array_fill_keys(array_map('strval', $usedKeys), true);
    if ($seed !== '') {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $key = substr(hash('sha256', 'quiz.layout_extra_menu|' . $seed . '|' . (string) $attempt), 0, 12);
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

function sr_quiz_layout_extra_menu_items_from_value(mixed $value): array
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
            $areaKey = sr_quiz_clean_layout_extra_menu_area_key((string) ($item['area_key'] ?? ($item['key'] ?? ($item['slot_key'] ?? ''))));
            $label = sr_quiz_layout_extra_menu_label((string) ($item['label'] ?? ($item['name'] ?? '')));
            $menuKey = sr_quiz_clean_layout_menu_key((string) ($item['menu_key'] ?? ''));
        } else {
            $label = '';
            $menuKey = sr_quiz_clean_layout_menu_key((string) $item);
        }
        if ($menuKey === '') {
            continue;
        }
        if ($areaKey === '' || isset($items[$areaKey])) {
            $areaKey = sr_quiz_layout_extra_menu_hash_key(array_keys($items), (string) $itemIndex . '|' . $menuKey);
        }
        $items[$areaKey] = [
            'area_key' => $areaKey,
            'label' => $label,
            'menu_key' => $menuKey,
        ];
    }

    return array_values($items);
}

function sr_quiz_layout_extra_menu_items_from_pair_values(mixed $areaKeys, mixed $labels, mixed $menuKeys): array
{
    $areaKeys = is_array($areaKeys) ? array_values($areaKeys) : [];
    $labels = is_array($labels) ? array_values($labels) : [];
    $menuKeys = is_array($menuKeys) ? array_values($menuKeys) : [];
    $items = [];
    foreach ($menuKeys as $index => $menuKeyValue) {
        $menuKey = sr_quiz_clean_layout_menu_key((string) $menuKeyValue);
        if ($menuKey === '') {
            continue;
        }
        $areaKey = sr_quiz_clean_layout_extra_menu_area_key((string) ($areaKeys[$index] ?? ''));
        if ($areaKey === '' || isset($items[$areaKey])) {
            $areaKey = sr_quiz_layout_extra_menu_hash_key(array_keys($items));
        }
        $label = sr_quiz_layout_extra_menu_label((string) ($labels[$index] ?? ''));
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

function sr_quiz_layout_extra_menu_keys_from_value(mixed $value): array
{
    return array_values(array_map(static fn (array $item): string => (string) $item['menu_key'], sr_quiz_layout_extra_menu_items_from_value($value)));
}

function sr_quiz_layout_extra_menu_items_from_settings(array $settings): array
{
    $items = [];
    foreach (sr_quiz_layout_extra_menu_items_from_value($settings['layout_extra_menu_keys_json'] ?? []) as $item) {
        $items[(string) $item['area_key']] = $item;
    }
    foreach (['layout_secondary_menu_key', 'layout_tertiary_menu_key', 'layout_quaternary_menu_key', 'layout_quinary_menu_key'] as $legacySettingKey) {
        $menuKey = sr_quiz_clean_layout_menu_key((string) ($settings[$legacySettingKey] ?? ''));
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

function sr_quiz_layout_extra_menu_keys_from_settings(array $settings): array
{
    return array_values(array_map(static fn (array $item): string => (string) $item['menu_key'], sr_quiz_layout_extra_menu_items_from_settings($settings)));
}

function sr_quiz_layout_extra_menu_keys_json(mixed $value): string
{
    $json = json_encode(sr_quiz_layout_extra_menu_items_from_value($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : '[]';
}

function sr_quiz_theme_options(): array
{
    return sr_view_theme_options(SR_ROOT . '/modules/quiz/theme', ['home.php', 'view.php', 'result.php', 'ui-kit.php'], '기본 퀴즈 테마', 'quiz_view_theme', false);
}

function sr_quiz_theme_key(string $themeKey): string
{
    return sr_view_theme_key($themeKey, sr_quiz_theme_options());
}

function sr_quiz_theme_view_file(array $settings, string $view): ?string
{
    $allowedViews = array_fill_keys(array_merge(sr_quiz_skin_views(), ['ui-kit']), true);
    if (!isset($allowedViews[$view])) {
        return null;
    }

    return sr_view_theme_file(SR_ROOT . '/modules/quiz/theme', sr_quiz_theme_key((string) ($settings['theme_key'] ?? '')), $view . '.php');
}

function sr_quiz_normalize_settings(array $settings): array
{
    $defaults = sr_quiz_default_settings();
    $normalized = array_merge($defaults, $settings);

    $normalized['layout_key'] = sr_public_layout_normalize_key((string) ($normalized['layout_key'] ?? $defaults['layout_key']));
    $normalized['theme_key'] = sr_quiz_theme_key((string) ($normalized['theme_key'] ?? $defaults['theme_key']));
    $normalized['skin_key'] = sr_quiz_skin_key((string) ($normalized['skin_key'] ?? $defaults['skin_key']));
    foreach (sr_quiz_layout_menu_slots() as $settingKey) {
        $normalized[$settingKey] = sr_quiz_clean_layout_menu_key((string) ($normalized[$settingKey] ?? ''));
    }
    $normalized['layout_extra_menu_keys_json'] = sr_quiz_layout_extra_menu_items_from_settings($normalized);
    $normalized['default_status'] = in_array((string) $normalized['default_status'], sr_quiz_statuses(), true)
        ? (string) $normalized['default_status']
        : (string) $defaults['default_status'];
    $normalized['default_quiz_mode'] = in_array((string) $normalized['default_quiz_mode'], sr_quiz_modes(), true)
        ? (string) $normalized['default_quiz_mode']
        : (string) $defaults['default_quiz_mode'];
    $normalized['default_scoring_model'] = in_array((string) $normalized['default_scoring_model'], sr_quiz_scoring_models(), true)
        ? (string) $normalized['default_scoring_model']
        : (string) $defaults['default_scoring_model'];
    $normalized['default_pass_score'] = max(0, min(100000, (int) $normalized['default_pass_score']));
    $normalized['default_question_choice_count'] = max(2, min(10, (int) $normalized['default_question_choice_count']));
    $normalized['default_question_score'] = max(0, min(10000, (int) $normalized['default_question_score']));
    $normalized['default_attempt_limit_policy'] = in_array((string) $normalized['default_attempt_limit_policy'], sr_quiz_attempt_limit_policies(), true)
        ? (string) $normalized['default_attempt_limit_policy']
        : (string) $defaults['default_attempt_limit_policy'];
    $normalized['default_attempt_limit_period_seconds'] = (string) $normalized['default_attempt_limit_policy'] === 'per_period'
        ? (trim((string) $normalized['default_attempt_limit_period_seconds']) === '' ? '' : (string) max(1, (int) $normalized['default_attempt_limit_period_seconds']))
        : '';
    $normalized['default_reward_provider'] = in_array((string) $normalized['default_reward_provider'], sr_quiz_default_reward_providers(), true)
        ? (string) $normalized['default_reward_provider']
        : (string) $defaults['default_reward_provider'];
    $normalized['default_reward_enabled'] = $normalized['default_reward_provider'] !== 'none';
    $normalized['default_reward_module'] = sr_quiz_clean_key((string) $normalized['default_reward_module'], 40);
    $normalized['default_reward_coupon_definition_id'] = (string) max(0, (int) $normalized['default_reward_coupon_definition_id']);
    if ($normalized['default_reward_coupon_definition_id'] === '0') {
        $normalized['default_reward_coupon_definition_id'] = '';
    }
    $normalized['default_reward_amount'] = (string) max(0, min(1000000000, (int) $normalized['default_reward_amount']));
    if ($normalized['default_reward_amount'] === '0') {
        $normalized['default_reward_amount'] = '';
    }
    $normalized['default_reward_dedupe_scope'] = in_array((string) $normalized['default_reward_dedupe_scope'], sr_quiz_reward_dedupe_scopes(), true)
        ? (string) $normalized['default_reward_dedupe_scope']
        : (string) $defaults['default_reward_dedupe_scope'];
    if ($normalized['default_reward_provider'] === 'none') {
        $normalized['default_reward_module'] = '';
        $normalized['default_reward_coupon_definition_id'] = '';
        $normalized['default_reward_amount'] = '';
    }
    $normalized['default_cta_label'] = sr_quiz_clean_single_line((string) $normalized['default_cta_label'], 120);
    if ($normalized['default_cta_label'] === '') {
        $normalized['default_cta_label'] = (string) $defaults['default_cta_label'];
    }
    $normalized['embed_enabled'] = !empty($normalized['embed_enabled']);
    $normalized['identity_view_required'] = !empty($normalized['identity_view_required']);
    $normalized['identity_view_adult_required'] = !empty($normalized['identity_view_adult_required']);
    $reactionModuleEnabled = isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && sr_module_enabled($GLOBALS['pdo'], 'reaction');
    $normalized['reaction_preset_key'] = $reactionModuleEnabled && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($GLOBALS['pdo'], $normalized['reaction_preset_key'] ?? '') : sr_quiz_clean_key((string) ($normalized['reaction_preset_key'] ?? ''), 80);
    $normalized['reaction_comment_preset_key'] = $reactionModuleEnabled && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($GLOBALS['pdo'], $normalized['reaction_comment_preset_key'] ?? '') : sr_quiz_clean_key((string) ($normalized['reaction_comment_preset_key'] ?? ''), 80);
    $normalized['public_list_limit'] = max(1, min(100, (int) $normalized['public_list_limit']));

    return $normalized;
}

function sr_quiz_settings(PDO $pdo): array
{
    $settings = sr_quiz_normalize_settings(sr_module_settings($pdo, 'quiz'));
    if (!isset(sr_quiz_layout_options($pdo)[$settings['layout_key']])) {
        $settings['layout_key'] = sr_quiz_fallback_layout_key($pdo, null);
    }
    if (!isset(sr_quiz_theme_options()[$settings['theme_key']])) {
        $settings['theme_key'] = sr_quiz_theme_key('');
    }

    return $settings;
}

function sr_quiz_settings_from_post(): array
{
    $skinKey = sr_quiz_clean_option_key(sr_post_string('skin_key', 40));
    $themeKey = sr_view_theme_post_key(sr_post_string('theme_key', 80));
    $rewardProvider = sr_quiz_clean_key(sr_post_string('default_reward_provider', 30), 30);
    $rewardDedupeScope = sr_quiz_clean_key(sr_post_string('default_reward_dedupe_scope', 20), 20);
    $settings = sr_quiz_normalize_settings([
        'layout_key' => sr_public_layout_normalize_key(sr_post_string('layout_key', 80)),
        'theme_key' => $themeKey,
        'skin_key' => $skinKey,
        'layout_primary_menu_key' => sr_quiz_clean_layout_menu_key(sr_post_string('layout_primary_menu_key', 60)),
        'layout_extra_menu_keys_json' => sr_quiz_layout_extra_menu_items_from_pair_values($_POST['layout_extra_menu_area_keys'] ?? [], $_POST['layout_extra_menu_labels'] ?? [], $_POST['layout_extra_menu_keys'] ?? []),
        'default_status' => sr_post_string('default_status', 20),
        'default_quiz_mode' => sr_post_string('default_quiz_mode', 30),
        'default_scoring_model' => sr_post_string('default_scoring_model', 40),
        'default_pass_score' => sr_post_string('default_pass_score', 20),
        'default_question_choice_count' => sr_post_string('default_question_choice_count', 20),
        'default_question_score' => sr_post_string('default_question_score', 20),
        'default_attempt_limit_policy' => sr_post_string('default_attempt_limit_policy', 30),
        'default_attempt_limit_period_seconds' => sr_post_string('default_attempt_limit_period_seconds', 20),
        'default_reward_enabled' => true,
        'default_reward_provider' => $rewardProvider,
        'default_reward_module' => sr_quiz_clean_key(sr_post_string('default_reward_module', 40), 40),
        'default_reward_coupon_definition_id' => sr_post_string('default_reward_coupon_definition_id', 20),
        'default_reward_amount' => sr_post_string('default_reward_amount', 20),
        'default_reward_dedupe_scope' => $rewardDedupeScope,
        'default_cta_label' => sr_quiz_clean_single_line(sr_post_string('default_cta_label', 120), 120),
        'embed_enabled' => ($_POST['embed_enabled'] ?? '') === '1',
        'identity_view_required' => ($_POST['identity_view_required'] ?? '') === '1',
        'identity_view_adult_required' => ($_POST['identity_view_adult_required'] ?? '') === '1',
        'reaction_preset_key' => isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && sr_module_enabled($GLOBALS['pdo'], 'reaction') && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($GLOBALS['pdo'], sr_post_string('reaction_preset_key', 80)) : sr_quiz_clean_key(sr_post_string('reaction_preset_key', 80), 80),
        'reaction_comment_preset_key' => isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && sr_module_enabled($GLOBALS['pdo'], 'reaction') && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($GLOBALS['pdo'], sr_post_string('reaction_comment_preset_key', 80)) : sr_quiz_clean_key(sr_post_string('reaction_comment_preset_key', 80), 80),
        'public_list_limit' => sr_post_string('public_list_limit', 20),
    ]);
    $settings['theme_key'] = $themeKey;
    $settings['skin_key'] = $skinKey;
    $settings['default_reward_provider'] = $rewardProvider;
    if ($rewardProvider !== 'none') {
        $settings['default_reward_dedupe_scope'] = $rewardDedupeScope;
    }

    return $settings;
}

function sr_quiz_settings_validation_errors(PDO $pdo, array $settings, array $assetOptions): array
{
    $errors = [];
    if (!isset(sr_quiz_layout_options($pdo)[(string) ($settings['layout_key'] ?? '')])) {
        $errors[] = '퀴즈 공개 레이아웃 값이 올바르지 않습니다.';
    }
    if (!isset(sr_quiz_theme_options()[(string) ($settings['theme_key'] ?? '')])) {
        $errors[] = '퀴즈 공개 테마 값이 올바르지 않습니다.';
    }
    if (!isset(sr_quiz_skin_options()[(string) ($settings['skin_key'] ?? '')])) {
        $errors[] = '퀴즈 스킨 값이 올바르지 않습니다.';
    }
    $siteMenuOptions = [];
    if (sr_module_enabled($pdo, 'site_menu') && is_file(SR_ROOT . '/modules/site_menu/helpers.php')) {
        require_once SR_ROOT . '/modules/site_menu/helpers.php';
        $siteMenuOptions = sr_site_menu_options($pdo);
    }
    foreach (array_merge([(string) ($settings['layout_primary_menu_key'] ?? '')], sr_quiz_layout_extra_menu_keys_from_value($settings['layout_extra_menu_keys_json'] ?? [])) as $menuKey) {
        if ($menuKey !== '' && !isset($siteMenuOptions[$menuKey])) {
            $errors[] = '퀴즈 레이아웃 사이트 메뉴 값이 올바르지 않습니다.';
            break;
        }
    }
    if ((string) ($settings['default_attempt_limit_policy'] ?? '') === 'per_period' && (int) ($settings['default_attempt_limit_period_seconds'] ?? 0) < 1) {
        $errors[] = '기본 응시 제한이 기간당 1회이면 제한 기간을 1초 이상 입력해야 합니다.';
    }
    $identityVerificationAvailable = sr_module_enabled($pdo, 'identity_verification')
        && is_file(SR_ROOT . '/modules/identity_verification/helpers.php');
    if ($identityVerificationAvailable) {
        require_once SR_ROOT . '/modules/identity_verification/helpers.php';
    }
    if (!$identityVerificationAvailable) {
        if (!empty($settings['identity_view_required']) || !empty($settings['identity_view_adult_required'])) {
            $errors[] = '퀴즈 본인확인 설정을 사용하려면 본인확인 모듈을 먼저 설치하고 활성화하세요.';
        }
    } elseif (function_exists('sr_identity_verification_adult_setting_errors')) {
        $errors = array_merge($errors, sr_identity_verification_adult_setting_errors($pdo, !empty($settings['identity_view_adult_required']), '퀴즈 성인 본인확인'));
    } elseif (!empty($settings['identity_view_adult_required'])) {
        $errors[] = '퀴즈 성인 본인확인을 사용하려면 본인확인 모듈을 활성화해야 합니다.';
    }
    $reactionAvailable = sr_module_enabled($pdo, 'reaction')
        && is_file(SR_ROOT . '/modules/reaction/helpers.php');
    if (!$reactionAvailable && ((string) ($settings['reaction_preset_key'] ?? '') !== '' || (string) ($settings['reaction_comment_preset_key'] ?? '') !== '')) {
        $errors[] = '퀴즈 리액션 기본값을 사용하려면 리액션 모듈을 먼저 설치하고 활성화하세요.';
    }
    $provider = (string) ($settings['default_reward_provider'] ?? '');
    if ($provider === 'none') {
        return array_values(array_unique($errors));
    }
    if (!in_array((string) ($settings['default_reward_dedupe_scope'] ?? ''), sr_quiz_reward_dedupe_scopes(), true)) {
        $errors[] = '기본 중복 지급 기준 값이 올바르지 않습니다.';
    }
    if ($provider === 'ledger_asset') {
        $moduleKey = (string) ($settings['default_reward_module'] ?? '');
        if ($moduleKey === '' || !isset($assetOptions[$moduleKey])) {
            $errors[] = '기본 보상 자산을 선택하세요.';
        }
        if ((int) ($settings['default_reward_amount'] ?? 0) < 1) {
            $errors[] = '기본 보상 금액은 1 이상이어야 합니다.';
        }
    } elseif ($provider === 'coupon') {
        if (!sr_quiz_reward_coupon_definition_is_available($pdo, (int) ($settings['default_reward_coupon_definition_id'] ?? 0))) {
            $errors[] = '기본 보상으로 사용할 수 있는 쿠폰을 선택하세요.';
        }
    } else {
        $errors[] = '기본 보상 종류 값이 올바르지 않습니다.';
    }

    return array_values(array_unique($errors));
}

function sr_quiz_public_layout_context(array $settings, array $context = []): array
{
    $layoutKey = sr_public_layout_normalize_key((string) ($settings['layout_key'] ?? ''));
    if ($layoutKey !== '') {
        $context['layout_key'] = $layoutKey;
    }
    $themeKey = sr_quiz_theme_key((string) ($settings['theme_key'] ?? ''));
    if ($themeKey !== '') {
        $context['theme_key'] = $themeKey;
    }
    $context['consumer_domain'] = 'quiz';
    $context['style_profile'] = 'module';
    $context['module_home_url'] = sr_url('/quiz');
    $context['module_label'] = '퀴즈';
    $context['module_menu_label'] = '퀴즈 메뉴';

    $stylesheets = is_array($context['stylesheets'] ?? null) ? $context['stylesheets'] : [];
    $stylesheets[] = sr_public_layout_module_theme_asset_url('quiz', $themeKey, 'reset.css');
    $stylesheets[] = sr_public_layout_module_theme_asset_url('quiz', $themeKey, 'ui-kit.css');
    $stylesheets[] = sr_public_layout_module_theme_asset_url('quiz', $themeKey, 'module.css');
    $themeStylesheet = sr_module_view_theme_stylesheet_url('quiz', $themeKey);
    if ($themeStylesheet !== '') {
        $stylesheets[] = $themeStylesheet;
    }
    $skinKey = sr_quiz_skin_key((string) ($settings['skin_key'] ?? 'basic'));
    $includeSkinAssets = !array_key_exists('include_skin_assets', $context) || !empty($context['include_skin_assets']);
    unset($context['include_skin_assets']);
    if ($includeSkinAssets) {
        $stylesheets[] = sr_public_layout_module_theme_asset_url('quiz', $themeKey, 'skin.css');
        $skinDefinitions = sr_quiz_skin_option_definitions();
        foreach ((array) ($skinDefinitions[$skinKey]['stylesheets'] ?? []) as $skinStylesheet) {
            if (is_string($skinStylesheet)) {
                $stylesheets[] = $skinStylesheet;
            }
        }
    }
    $context['stylesheets'] = array_values(array_unique($stylesheets));
    $scripts = is_array($context['scripts'] ?? null) ? $context['scripts'] : [];
    $scripts[] = '/modules/quiz/assets/module.js';
    $context['scripts'] = array_values(array_unique($scripts));
    $bodyClass = sr_ui_icon_class_attr((string) ($context['body_class'] ?? ''));
    $context['body_class'] = $includeSkinAssets
        ? trim($bodyClass . ' sr-quiz-skin-' . $skinKey . ' quiz-skin-' . $skinKey)
        : $bodyClass;
    if ($themeKey !== sr_public_theme_default_key()) {
        $context['body_class'] = trim((string) $context['body_class'] . ' quiz-view-theme-' . $themeKey);
    }

    $siteMenus = ['primary' => sr_quiz_clean_layout_menu_key((string) ($settings['layout_primary_menu_key'] ?? 'header'))];
    $context['site_menus'] = array_merge(is_array($context['site_menus'] ?? null) ? $context['site_menus'] : [], $siteMenus);
    $context['site_extra_menus'] = sr_quiz_layout_extra_menu_items_from_settings($settings);

    return $context;
}

function sr_quiz_ui_kit_layout_context(array $settings, array $context = []): array
{
    $context = sr_quiz_public_layout_context($settings, $context);
    $themeKey = sr_quiz_theme_key((string) ($settings['theme_key'] ?? ''));
    $stylesheets = is_array($context['stylesheets'] ?? null) ? $context['stylesheets'] : [];
    $stylesheets[] = sr_public_layout_module_theme_asset_url('quiz', $themeKey, 'ui-kit.css');
    $stylesheets[] = sr_public_layout_module_theme_asset_url('quiz', $themeKey, 'ui-kit-layout.css');
    $context['stylesheets'] = array_values(array_unique($stylesheets));

    return $context;
}

function sr_quiz_display_settings_for_quiz(array $settings, array $quiz): array
{
    $settings = sr_quiz_normalize_settings($settings);
    $skinKey = sr_quiz_clean_optional_skin_key((string) ($quiz['skin_key'] ?? ''));
    if ($skinKey !== '') {
        $settings['skin_key'] = $skinKey;
    }

    return $settings;
}

function sr_quiz_enforce_identity_view_policy(PDO $pdo, array $quiz, array $settings, ?array &$currentAccount, bool $canPreviewAsAdmin): void
{
    if ($canPreviewAsAdmin || (empty($settings['identity_view_required']) && empty($settings['identity_view_adult_required']))) {
        return;
    }

    $currentAccount = sr_member_require_login($pdo);
    if (!sr_module_enabled($pdo, 'identity_verification') || !is_file(SR_ROOT . '/modules/identity_verification/helpers.php')) {
        sr_render_error(403, '이 퀴즈에 참여하려면 본인확인이 필요합니다. 본인확인 기능을 사용할 수 없어 참여할 수 없습니다.');
    }

    require_once SR_ROOT . '/modules/identity_verification/helpers.php';
    $returnUrl = '/quiz/' . (string) ($quiz['quiz_key'] ?? '');
    $accountId = (int) ($currentAccount['id'] ?? 0);
    if (!empty($settings['identity_view_required'])) {
        $policy = sr_identity_verification_requirement_policy($pdo, $accountId, 'quiz.view', 'required', $returnUrl);
        if (empty($policy['satisfied'])) {
            sr_render_error(403, '이 퀴즈에 참여하려면 본인확인이 필요합니다. 본인확인을 완료한 뒤 다시 열어 주세요.');
        }
    }
    if (!empty($settings['identity_view_adult_required'])) {
        $available = sr_identity_verification_available($pdo, 'quiz.view.adult');
        if (!$available || !sr_identity_verification_account_satisfies_adult($pdo, $accountId, 'quiz.view.adult')) {
            sr_render_error(403, '이 퀴즈에 참여하려면 성인 본인확인이 필요합니다. 성인 본인확인을 완료한 뒤 다시 열어 주세요.');
        }
    }
}

function sr_quiz_skin_view_file(array $settings, string $view): string
{
    if (!in_array($view, sr_quiz_skin_views(), true)) {
        throw new InvalidArgumentException('Unknown quiz skin view.');
    }

    $skinKey = sr_quiz_skin_key((string) ($settings['skin_key'] ?? 'basic'));
    $definitions = sr_quiz_skin_option_definitions();
    $file = (string) ($definitions[$skinKey]['views'][$view] ?? '');
    if ($skinKey !== 'basic' && is_file($file)) {
        return $file;
    }

    $fallback = (string) ($definitions['basic']['views'][$view] ?? (SR_ROOT . '/modules/quiz/skins/basic/' . $view . '.php'));
    if ($skinKey !== 'basic' || !is_file($file)) {
        error_log('quiz_skin_fallback module=quiz skin_key=' . $skinKey . ' view=' . $view . ' fallback_file=' . $fallback);
    }
    if (!is_file($fallback)) {
        throw new RuntimeException('Default quiz skin view is missing: ' . $view);
    }

    return $fallback;
}

function sr_quiz_public_view_file(PDO $pdo, array $settings, string $view): string
{
    if (!in_array($view, sr_quiz_skin_views(), true)) {
        throw new InvalidArgumentException('Unknown quiz public view.');
    }

    $themeFile = sr_quiz_theme_view_file($settings, $view);
    return $themeFile !== null ? $themeFile : sr_quiz_skin_view_file($settings, $view);
}

function sr_quiz_include_public_view(PDO $pdo, array $settings, string $view): void
{
    $site = is_array($GLOBALS['sr_runtime_site'] ?? null) ? $GLOBALS['sr_runtime_site'] : null;

    include sr_quiz_public_view_file($pdo, $settings, $view);
}

function sr_quiz_render_skin(PDO $pdo, array $settings, string $view): void
{
    $site = is_array($GLOBALS['sr_runtime_site'] ?? null) ? $GLOBALS['sr_runtime_site'] : null;

    include sr_quiz_skin_view_file($settings, $view);
}

function sr_quiz_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'quiz' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('Quiz module was not found.');
    }

    $settings = sr_quiz_normalize_settings($settings);
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
            ? sr_quiz_layout_extra_menu_keys_json($value)
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
    sr_clear_module_settings_cache('quiz');
}

function sr_quiz_clean_admin_datetime(string $value): ?string
{
    return sr_clean_admin_datetime($value);
}

function sr_quiz_datetime_local_value(mixed $value): string
{
    return sr_datetime_local_value($value);
}

function sr_quiz_time_html(string $value): string
{
    return sr_relative_time_html($value);
}

function sr_quiz_admin_status_class(string $status): string
{
    return match ($status) {
        'active', 'submitted', 'scored', 'rewarded', 'granted' => 'is-normal',
        'draft', 'paused', 'pending' => 'is-blocked',
        default => 'is-left',
    };
}

function sr_quiz_admin_request_array(string $name): array
{
    $value = $_GET[$name] ?? [];
    if (is_string($value)) {
        $value = trim($value) === '' ? [] : [$value];
    }
    if (!is_array($value)) {
        return [];
    }

    $values = [];
    foreach ($value as $item) {
        $item = trim((string) $item);
        if ($item !== '') {
            $values[$item] = true;
        }
    }

    return array_keys($values);
}

function sr_quiz_admin_filter_values(array $values, array $allowedValues): array
{
    $allowed = array_fill_keys(array_map('strval', $allowedValues), true);
    $filtered = [];
    foreach ($values as $value) {
        $value = (string) $value;
        if (isset($allowed[$value])) {
            $filtered[$value] = true;
        }
    }

    return array_keys($filtered);
}

function sr_quiz_member_group_keys_from_value(mixed $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $value = $decoded;
        } else {
            $value = preg_split('/[\s,]+/', $value) ?: [];
        }
    }
    if (!is_array($value)) {
        return [];
    }

    $keys = [];
    foreach ($value as $groupKey) {
        $groupKey = sr_quiz_clean_key((string) $groupKey, 64);
        if ($groupKey !== '' && function_exists('sr_member_group_key_is_valid') && sr_member_group_key_is_valid($groupKey)) {
            $keys[$groupKey] = true;
        }
    }

    return array_keys($keys);
}

function sr_quiz_member_groups_for_admin(PDO $pdo): array
{
    if (!function_exists('sr_member_groups')) {
        return [];
    }

    return array_values(array_filter(sr_member_groups($pdo), static function (array $group): bool {
        return (string) ($group['status'] ?? '') === 'enabled';
    }));
}

function sr_quiz_key_exists(PDO $pdo, string $quizKey, int $excludeId = 0): bool
{
    if (!sr_quiz_key_is_valid($quizKey)) {
        return false;
    }

    $sql = 'SELECT 1 FROM sr_quiz_sets WHERE quiz_key = :quiz_key';
    $params = ['quiz_key' => $quizKey];
    if ($excludeId > 0) {
        $sql .= ' AND id <> :id';
        $params['id'] = $excludeId;
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

function sr_quiz_public_quizzes(PDO $pdo, ?int $limit = null): array
{
    if ($limit === null) {
        $settings = sr_quiz_settings($pdo);
        $limit = (int) ($settings['public_list_limit'] ?? 50);
    }

    $stmt = $pdo->prepare(
        "SELECT id, quiz_key, title, description, cover_image_url, created_at
         FROM sr_quiz_sets
         WHERE status = 'active'
           AND deleted_at IS NULL
           AND (starts_at IS NULL OR starts_at <= :now_start)
           AND (ends_at IS NULL OR ends_at >= :now_end)
         ORDER BY created_at DESC, id DESC
         LIMIT :limit_value"
    );
    $now = sr_now();
    $stmt->bindValue('now_start', $now);
    $stmt->bindValue('now_end', $now);
    $stmt->bindValue('limit_value', max(1, min(100, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_quiz_coupon_target_search(PDO $pdo, string $targetType, string $keyword, int $limit = 20): array
{
    if ($targetType !== 'quiz') {
        return [];
    }

    $keyword = trim(preg_replace('/\s+/', ' ', $keyword) ?? '');
    $keyword = function_exists('mb_substr') ? mb_substr($keyword, 0, 120) : substr($keyword, 0, 120);
    $limit = max(1, min(30, $limit));
    $keywordLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
    $idValue = preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1 ? (int) $keyword : 0;
    $where = $keyword === '' ? '1 = 1' : "(id = :id OR title LIKE :keyword_like ESCAPE '\\\\' OR quiz_key LIKE :keyword_like ESCAPE '\\\\')";

    $stmt = $pdo->prepare(
        'SELECT id, quiz_key, title, status, updated_at
         FROM sr_quiz_sets
         WHERE deleted_at IS NULL
           AND ' . $where . '
         ORDER BY id DESC
         LIMIT ' . $limit
    );
    $stmt->execute($keyword === '' ? [] : ['id' => $idValue, 'keyword_like' => $keywordLike]);

    return array_map(static function (array $row): array {
        return [
            'reference_type' => 'quiz',
            'reference_id' => (string) (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'reason' => '퀴즈 #' . (string) (int) ($row['id'] ?? 0),
            'member_name' => '(' . (string) ($row['quiz_key'] ?? '') . ')',
            'member_email' => '',
            'created_at' => '상태: ' . (string) ($row['status'] ?? ''),
        ];
    }, $stmt->fetchAll());
}

function sr_quiz_by_key(PDO $pdo, string $quizKey): ?array
{
    if (!sr_quiz_key_is_valid($quizKey)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_sets
         WHERE quiz_key = :quiz_key
           AND deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute(['quiz_key' => $quizKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_quiz_should_count_view(int $quizId): bool
{
    if ($quizId < 1) {
        return false;
    }

    $sessionKey = 'sr_quiz_viewed_sets';
    $viewed = isset($_SESSION[$sessionKey]) && is_array($_SESSION[$sessionKey]) ? $_SESSION[$sessionKey] : [];
    $quizKey = (string) $quizId;
    if (isset($viewed[$quizKey])) {
        return false;
    }

    $viewed[$quizKey] = time();
    if (count($viewed) > 500) {
        asort($viewed);
        $viewed = array_slice($viewed, -500, null, true);
    }
    $_SESSION[$sessionKey] = $viewed;

    return true;
}

function sr_quiz_increment_view_count(PDO $pdo, int $quizId): void
{
    if ($quizId < 1) {
        return;
    }

    $stmt = $pdo->prepare('UPDATE sr_quiz_sets SET view_count = view_count + 1 WHERE id = :id');
    $stmt->execute(['id' => $quizId]);
}

function sr_quiz_by_id(PDO $pdo, int $quizId): ?array
{
    if ($quizId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_sets
         WHERE id = :id
           AND deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute(['id' => $quizId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_quiz_admin_quiz_filters_from_request(): array
{
    $rewardEnabled = sr_get_string('reward_enabled', 20);
    if (!in_array($rewardEnabled, ['enabled', 'disabled'], true)) {
        $rewardEnabled = '';
    }

    return [
        'q' => sr_quiz_clean_single_line(sr_get_string('q', 120), 120),
        'status' => sr_quiz_admin_filter_values(sr_quiz_admin_request_array('status'), sr_quiz_statuses()),
        'quiz_mode' => sr_quiz_admin_filter_values(sr_quiz_admin_request_array('quiz_mode'), sr_quiz_modes()),
        'reward_enabled' => $rewardEnabled,
        'deleted' => sr_get_string('deleted', 1) === '1',
    ];
}

function sr_quiz_admin_quiz_sort_options(): array
{
    return [
        'quiz_key' => ['columns' => ['q.quiz_key', 'q.id']],
        'title' => ['columns' => ['q.title', 'q.id']],
        'status' => ['columns' => ['q.status', 'q.id']],
        'question_count' => ['columns' => ['question_count', 'q.id']],
        'source_count' => ['columns' => ['source_count', 'q.id']],
        'attempt_count' => ['columns' => ['attempt_count', 'q.id']],
        'passed_count' => ['columns' => ['passed_count', 'q.id']],
        'view_count' => ['columns' => ['q.view_count', 'q.id']],
        'reward_enabled' => ['columns' => ['q.reward_enabled', 'q.id']],
        'updated_at' => ['columns' => ['q.updated_at', 'q.id']],
    ];
}

function sr_quiz_admin_quiz_default_sort(): array
{
    return sr_admin_sort_default('updated_at', 'desc');
}

function sr_quiz_admin_quiz_where_sql(array $filters, array &$params): string
{
    $where = !empty($filters['deleted']) ? ['q.deleted_at IS NOT NULL'] : ['q.deleted_at IS NULL'];
    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $keywordColumns = ['q.quiz_key', 'q.title', 'q.description'];
        $keywordWhere = [];
        foreach ($keywordColumns as $index => $column) {
            $paramKey = 'quiz_q_' . (string) $index;
            $keywordWhere[] = $column . ' LIKE :' . $paramKey;
            $params[$paramKey] = '%' . $keyword . '%';
        }
        $where[] = '(' . implode(' OR ', $keywordWhere) . ')';
    }

    $statuses = sr_quiz_admin_filter_values((array) ($filters['status'] ?? []), sr_quiz_statuses());
    if ($statuses !== []) {
        $placeholders = [];
        foreach ($statuses as $index => $status) {
            $key = 'quiz_status_' . (string) $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $status;
        }
        $where[] = 'q.status IN (' . implode(', ', $placeholders) . ')';
    }

    $quizModes = sr_quiz_admin_filter_values((array) ($filters['quiz_mode'] ?? []), sr_quiz_modes());
    if ($quizModes !== []) {
        $placeholders = [];
        foreach ($quizModes as $index => $quizMode) {
            $key = 'quiz_mode_' . (string) $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $quizMode;
        }
        $where[] = 'q.quiz_mode IN (' . implode(', ', $placeholders) . ')';
    }

    $rewardEnabled = (string) ($filters['reward_enabled'] ?? '');
    if ($rewardEnabled === 'enabled') {
        $where[] = 'q.reward_enabled = 1';
    } elseif ($rewardEnabled === 'disabled') {
        $where[] = 'q.reward_enabled = 0';
    }

    return ' WHERE ' . implode(' AND ', $where);
}

function sr_quiz_admin_quiz_count(PDO $pdo, array $filters = []): int
{
    $params = [];
    $whereSql = sr_quiz_admin_quiz_where_sql($filters, $params);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sr_quiz_sets q' . $whereSql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function sr_quiz_admin_quizzes(PDO $pdo, array $filters = [], int $limit = 100, int $offset = 0, array $sort = []): array
{
    $params = [];
    $whereSql = sr_quiz_admin_quiz_where_sql($filters, $params);
    $sortOptions = sr_quiz_admin_quiz_sort_options();
    $defaultSort = sr_quiz_admin_quiz_default_sort();
    $orderSql = sr_admin_sort_order_sql($sortOptions, $sort !== [] ? $sort : $defaultSort, $defaultSort);
    if ($orderSql === '') {
        $orderSql = ' ORDER BY q.updated_at DESC, q.id DESC';
    } else {
        $orderSql .= ', q.id DESC';
    }
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);

    $stmt = $pdo->prepare(
        'SELECT q.id, q.quiz_key, q.title, q.status, q.quiz_mode, q.scoring_model, q.pass_score,
                q.starts_at, q.ends_at, q.attempt_limit_policy, q.attempt_limit_period_seconds,
                q.member_group_keys_json, q.view_count, q.reward_enabled, q.updated_at, q.deleted_at,
                COUNT(DISTINCT qs.id) AS question_count,
                COUNT(DISTINCT rr.id) AS result_rule_count,
                COUNT(DISTINCT rp.id) AS reward_policy_count,
                COUNT(DISTINCT src.id) AS source_count,
                COALESCE(qa.attempt_count, 0) AS attempt_count,
                COALESCE(qa.passed_count, 0) AS passed_count
         FROM sr_quiz_sets q
         LEFT JOIN sr_quiz_questions qs ON qs.quiz_id = q.id
         LEFT JOIN sr_quiz_result_rules rr ON rr.quiz_id = q.id
         LEFT JOIN sr_quiz_reward_policies rp ON rp.quiz_id = q.id AND rp.status = \'active\'
         LEFT JOIN sr_quiz_sources src ON src.quiz_id = q.id AND src.status = \'active\'
         LEFT JOIN (
             SELECT quiz_id,
                    COUNT(*) AS attempt_count,
                    SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) AS passed_count
             FROM sr_quiz_attempts
             WHERE submitted_at IS NOT NULL
             GROUP BY quiz_id
         ) qa ON qa.quiz_id = q.id
         ' . $whereSql . '
         GROUP BY q.id, q.quiz_key, q.title, q.status, q.quiz_mode, q.scoring_model, q.pass_score,
                  q.starts_at, q.ends_at, q.attempt_limit_policy, q.attempt_limit_period_seconds,
                  q.member_group_keys_json, q.view_count, q.reward_enabled, q.updated_at, q.deleted_at, qa.attempt_count, qa.passed_count
         ' . $orderSql . '
         LIMIT ' . (string) $limit . ' OFFSET ' . (string) $offset
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_quiz_admin_attempt_filters_from_request(): array
{
    $passed = sr_get_string('passed', 20);
    if (!in_array($passed, ['yes', 'no'], true)) {
        $passed = '';
    }

    return [
        'q' => sr_quiz_clean_single_line(sr_get_string('q', 120), 120),
        'status' => sr_quiz_admin_filter_values(sr_quiz_admin_request_array('status'), ['submitted', 'scored', 'rewarded', 'failed']),
        'grant_status' => sr_quiz_admin_filter_values(sr_quiz_admin_request_array('grant_status'), ['none', 'pending', 'granted', 'failed']),
        'passed' => $passed,
    ];
}

function sr_quiz_admin_attempt_sort_options(): array
{
    return [
        'updated_at' => ['columns' => ['a.updated_at', 'a.id']],
        'submitted_at' => ['columns' => ['a.submitted_at', 'a.id']],
        'quiz' => ['columns' => ['q.title', 'q.quiz_key', 'a.id']],
        'account_id' => ['columns' => ['a.account_id', 'a.id']],
        'status' => ['columns' => ['a.status', 'a.id']],
        'total_score' => ['columns' => ['a.total_score', 'a.id']],
        'reward' => ['columns' => ['reward_status_text', 'a.id']],
    ];
}

function sr_quiz_admin_attempt_default_sort(): array
{
    return sr_admin_sort_default('updated_at', 'desc');
}

function sr_quiz_admin_attempt_where_sql(array $filters, array &$params): string
{
    $where = ['1 = 1'];
    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $qAccountId = (int) ($filters['q_account_id'] ?? 0);
        if (preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1) {
            $where[] = 'a.id = :attempt_q_id';
            $params['attempt_q_id'] = (int) $keyword;
        } else {
            $keywordColumns = ['q.quiz_key', 'q.title', 'a.source_title_snapshot'];
            $keywordWhere = [];
            foreach ($keywordColumns as $index => $column) {
                $paramKey = 'attempt_q_' . (string) $index;
                $keywordWhere[] = $column . ' LIKE :' . $paramKey;
                $params[$paramKey] = '%' . $keyword . '%';
            }
            if ($qAccountId > 0) {
                $keywordWhere[] = 'a.account_id = :attempt_q_account_id';
                $params['attempt_q_account_id'] = $qAccountId;
            }
            $where[] = '(' . implode(' OR ', $keywordWhere) . ')';
        }
    }

    $statuses = sr_quiz_admin_filter_values((array) ($filters['status'] ?? []), ['submitted', 'scored', 'rewarded', 'failed']);
    if ($statuses !== []) {
        $placeholders = [];
        foreach ($statuses as $index => $status) {
            $key = 'attempt_status_' . (string) $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $status;
        }
        $where[] = 'a.status IN (' . implode(', ', $placeholders) . ')';
    }

    $grantStatuses = sr_quiz_admin_filter_values((array) ($filters['grant_status'] ?? []), ['none', 'pending', 'granted', 'failed']);
    if ($grantStatuses !== []) {
        $grantWhere = [];
        foreach ($grantStatuses as $grantStatus) {
            if ($grantStatus === 'none') {
                $grantWhere[] = 'COALESCE(rg.grant_count, 0) = 0';
                continue;
            }
            $grantWhere[] = 'COALESCE(rg.' . $grantStatus . '_count, 0) > 0';
        }
        $where[] = '(' . implode(' OR ', $grantWhere) . ')';
    }

    $passed = (string) ($filters['passed'] ?? '');
    if ($passed === 'yes') {
        $where[] = 'a.passed = 1';
    } elseif ($passed === 'no') {
        $where[] = '(a.passed IS NULL OR a.passed = 0)';
    }

    return ' WHERE ' . implode(' AND ', $where);
}

function sr_quiz_admin_attempt_reward_join_sql(): string
{
    return ' LEFT JOIN (
                SELECT attempt_id,
                       COUNT(*) AS grant_count,
                       SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) AS pending_count,
                       SUM(CASE WHEN status = \'granted\' THEN 1 ELSE 0 END) AS granted_count,
                       SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) AS failed_count,
                       GROUP_CONCAT(DISTINCT reward_module ORDER BY reward_module SEPARATOR \', \') AS reward_modules,
                       SUM(COALESCE(reward_amount, 0)) AS reward_amount_total,
                       MAX(error_message) AS error_message,
                       MAX(granted_at) AS granted_at,
                       MAX(failed_at) AS failed_at,
                       GROUP_CONCAT(DISTINCT status ORDER BY status SEPARATOR \',\') AS reward_status_text
                FROM sr_quiz_reward_grants
                GROUP BY attempt_id
            ) rg ON rg.attempt_id = a.id';
}

function sr_quiz_admin_attempt_count(PDO $pdo, array $filters = []): int
{
    $params = [];
    $whereSql = sr_quiz_admin_attempt_where_sql($filters, $params);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sr_quiz_attempts a
         INNER JOIN sr_quiz_sets q ON q.id = a.quiz_id'
         . sr_quiz_admin_attempt_reward_join_sql()
         . $whereSql
    );
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function sr_quiz_admin_attempts(PDO $pdo, array $filters = [], int $limit = 100, int $offset = 0, array $sort = []): array
{
    $params = [];
    $whereSql = sr_quiz_admin_attempt_where_sql($filters, $params);
    $sortOptions = sr_quiz_admin_attempt_sort_options();
    $defaultSort = sr_quiz_admin_attempt_default_sort();
    $orderSql = sr_admin_sort_order_sql($sortOptions, $sort !== [] ? $sort : $defaultSort, $defaultSort);
    if ($orderSql === '') {
        $orderSql = ' ORDER BY a.updated_at DESC, a.id DESC';
    } else {
        $orderSql .= ', a.id DESC';
    }
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);

    $stmt = $pdo->prepare(
        'SELECT a.id, a.status, a.account_id, a.source_module, a.source_type, a.source_id,
                a.source_title_snapshot, a.total_score, a.passed, a.result_snapshot_json, a.submitted_at, a.updated_at,
                q.quiz_key, q.title,
                COALESCE(rg.grant_count, 0) AS grant_count,
                COALESCE(rg.pending_count, 0) AS pending_count,
                COALESCE(rg.granted_count, 0) AS granted_count,
                COALESCE(rg.failed_count, 0) AS failed_count,
                rg.reward_modules, rg.reward_amount_total, rg.error_message, rg.granted_at, rg.failed_at,
                COALESCE(rg.reward_status_text, \'\') AS reward_status_text
         FROM sr_quiz_attempts a
         INNER JOIN sr_quiz_sets q ON q.id = a.quiz_id'
         . sr_quiz_admin_attempt_reward_join_sql()
         . $whereSql
         . $orderSql . '
         LIMIT ' . (string) $limit . ' OFFSET ' . (string) $offset
    );
    $stmt->execute($params);

    $rows = $stmt->fetchAll();
    foreach ($rows as $index => $row) {
        $snapshot = json_decode((string) ($row['result_snapshot_json'] ?? ''), true);
        if (!is_array($snapshot)) {
            $snapshot = [];
        }
        $rows[$index]['result_key'] = (string) ($snapshot['result_key'] ?? '');
        $rows[$index]['result_title'] = (string) ($snapshot['title'] ?? '');
        $rows[$index]['result_summary'] = (string) ($snapshot['summary'] ?? '');
    }

    return $rows;
}

function sr_quiz_admin_reward_grants_for_attempts(PDO $pdo, array $attemptIds, array $assetOptions = []): array
{
    $ids = [];
    foreach ($attemptIds as $attemptId) {
        $attemptId = (int) $attemptId;
        if ($attemptId > 0) {
            $ids[$attemptId] = $attemptId;
        }
    }
    if ($ids === []) {
        return [];
    }

    $placeholders = [];
    $params = [];
    foreach (array_values($ids) as $index => $attemptId) {
        $key = 'attempt_id_' . (string) $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $attemptId;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_reward_grants
         WHERE attempt_id IN (' . implode(', ', $placeholders) . ')
         ORDER BY attempt_id ASC, id ASC'
    );
    $stmt->execute($params);

    $grants = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ($assetOptions !== []) {
            $row['reclaim_status'] = sr_quiz_reward_grant_reclaim_status($pdo, $row, $assetOptions);
        }
        $attemptId = (int) ($row['attempt_id'] ?? 0);
        if ($attemptId < 1) {
            continue;
        }
        $grants[$attemptId][] = $row;
    }

    return $grants;
}

function sr_quiz_admin_quiz_by_id(PDO $pdo, int $quizId): ?array
{
    if ($quizId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_quiz_sets WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $quizId]);
    $quiz = $stmt->fetch();
    if (!is_array($quiz)) {
        return null;
    }

    $questionStmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_questions
         WHERE quiz_id = :quiz_id
         ORDER BY sort_order ASC, id ASC'
    );
    $questionStmt->execute(['quiz_id' => $quizId]);
    $questions = $questionStmt->fetchAll();

    $choiceStmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_choices
         WHERE question_id = :question_id
         ORDER BY sort_order ASC, id ASC'
    );
    foreach ($questions as $index => $question) {
        $choiceStmt->execute(['question_id' => (int) ($question['id'] ?? 0)]);
        $questions[$index]['choices'] = $choiceStmt->fetchAll();
    }
    $quiz['questions'] = $questions;

    $policyStmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_reward_policies
         WHERE quiz_id = :quiz_id
         ORDER BY sort_order ASC, id ASC
         LIMIT 1'
    );
    $policyStmt->execute(['quiz_id' => $quizId]);
    $policy = $policyStmt->fetch();
    $quiz['reward_policy'] = is_array($policy) ? $policy : null;

    $sourceStmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_sources
         WHERE quiz_id = :quiz_id
           AND status = \'active\'
         ORDER BY sort_order ASC, id ASC'
    );
    $sourceStmt->execute(['quiz_id' => $quizId]);
    $quiz['sources'] = $sourceStmt->fetchAll();

    $ruleStmt = $pdo->prepare(
        'SELECT r.result_key, r.title, r.summary, rr.rule_type, rr.min_score, rr.max_score, rr.category_key, rr.threshold_value, rr.priority
         FROM sr_quiz_result_rules rr
         INNER JOIN sr_quiz_results r ON r.id = rr.result_id
         WHERE rr.quiz_id = :quiz_id
         ORDER BY rr.priority DESC, r.sort_order ASC, r.id ASC'
    );
    $ruleStmt->execute(['quiz_id' => $quizId]);
    $quiz['result_rules'] = $ruleStmt->fetchAll();

    return $quiz;
}
