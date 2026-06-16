<?php

require_once dirname(__DIR__, 2) . '/core/helpers/common.php';

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
    return is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

function sr_quiz_cover_image_format_for_mime(string $mimeType): string
{
    return sr_image_format_for_mime($mimeType);
}

function sr_quiz_cover_image_mime_is_allowed(string $mimeType): bool
{
    return in_array(strtolower(trim($mimeType)), ['image/jpeg', 'image/png', 'image/webp'], true);
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

function sr_quiz_delete_cover_image_storage(PDO $pdo, string $url, int $exceptQuizId = 0): array
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

    $deleted = sr_storage_delete($driver, $key);

    return [
        'attempted' => true,
        'deleted' => $deleted,
        'failed' => !$deleted,
        'reference' => $reference,
    ];
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

function sr_quiz_attempt_limit_policies(): array
{
    return ['unlimited', 'per_quiz_once', 'per_period'];
}

function sr_quiz_attempt_limit_policy_label(string $policy): string
{
    return [
        'unlimited' => '제한 없음',
        'per_quiz_once' => '회원당 1회',
        'per_period' => '기간당 1회',
    ][$policy] ?? $policy;
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

function sr_quiz_scoring_models(): array
{
    return ['correct_answer', 'total_score', 'category_score'];
}

function sr_quiz_scoring_model_label(string $model): string
{
    return [
        'correct_answer' => '정답 통과',
        'total_score' => '총점 결과',
        'category_score' => '카테고리 진단',
    ][$model] ?? $model;
}

function sr_quiz_question_types(): array
{
    return ['single_choice', 'multiple_choice'];
}

function sr_quiz_question_type_label(string $type): string
{
    return [
        'single_choice' => '단일 선택',
        'multiple_choice' => '복수 선택',
    ][$type] ?? $type;
}

function sr_quiz_reward_providers(): array
{
    return ['ledger_asset', 'coupon'];
}

function sr_quiz_default_reward_providers(): array
{
    return ['none', ...sr_quiz_reward_providers()];
}

function sr_quiz_reward_provider_label(string $provider): string
{
    return [
        'none' => '지급안함',
        'ledger_asset' => '포인트/금액',
        'coupon' => '쿠폰 발급',
    ][$provider] ?? $provider;
}

function sr_quiz_reward_dedupe_scopes(): array
{
    return ['per_quiz', 'per_source', 'per_attempt'];
}

function sr_quiz_reward_dedupe_scope_label(string $scope): string
{
    return [
        'per_quiz' => '퀴즈당 1회',
        'per_source' => '출처당 1회',
        'per_attempt' => '응시마다',
    ][$scope] ?? $scope;
}

function sr_quiz_truthy(mixed $value): bool
{
    return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
}

function sr_quiz_default_settings(): array
{
    return [
        'layout_key' => 'quiz.basic',
        'skin_key' => 'basic',
        'layout_primary_menu_key' => 'header',
        'layout_secondary_menu_key' => '',
        'layout_tertiary_menu_key' => '',
        'layout_quaternary_menu_key' => '',
        'layout_quinary_menu_key' => '',
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
        'reaction_preset_key' => '',
        'reaction_comment_preset_key' => '',
        'public_list_limit' => 50,
    ];
}

function sr_quiz_skin_options(): array
{
    return [
        'basic' => '기본형',
        'card' => '카드형',
        'focus' => '집중형',
    ];
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
        'secondary' => 'layout_secondary_menu_key',
        'tertiary' => 'layout_tertiary_menu_key',
        'quaternary' => 'layout_quaternary_menu_key',
        'quinary' => 'layout_quinary_menu_key',
    ];
}

function sr_quiz_clean_layout_menu_key(string $value): string
{
    $value = strtolower(trim($value));
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $value) === 1 ? $value : '';
}

function sr_quiz_normalize_settings(array $settings): array
{
    $defaults = sr_quiz_default_settings();
    $normalized = array_merge($defaults, $settings);

    $normalized['layout_key'] = sr_public_layout_normalize_key((string) ($normalized['layout_key'] ?? $defaults['layout_key']));
    $normalized['skin_key'] = sr_quiz_skin_key((string) ($normalized['skin_key'] ?? $defaults['skin_key']));
    foreach (sr_quiz_layout_menu_slots() as $settingKey) {
        $normalized[$settingKey] = sr_quiz_clean_layout_menu_key((string) ($normalized[$settingKey] ?? ''));
    }
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
    $normalized['reaction_preset_key'] = function_exists('sr_reaction_setting_preset_key') && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? sr_reaction_setting_preset_key($GLOBALS['pdo'], $normalized['reaction_preset_key'] ?? '') : sr_quiz_clean_key((string) ($normalized['reaction_preset_key'] ?? ''), 80);
    $normalized['reaction_comment_preset_key'] = function_exists('sr_reaction_setting_preset_key') && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? sr_reaction_setting_preset_key($GLOBALS['pdo'], $normalized['reaction_comment_preset_key'] ?? '') : sr_quiz_clean_key((string) ($normalized['reaction_comment_preset_key'] ?? ''), 80);
    $normalized['public_list_limit'] = max(1, min(100, (int) $normalized['public_list_limit']));

    return $normalized;
}

function sr_quiz_settings(PDO $pdo): array
{
    $settings = sr_quiz_normalize_settings(sr_module_settings($pdo, 'quiz'));
    if (!isset(sr_public_layout_options($pdo)[$settings['layout_key']])) {
        $settings['layout_key'] = sr_public_layout_key(null, $pdo);
    }

    return $settings;
}

function sr_quiz_settings_from_post(): array
{
    $skinKey = sr_quiz_clean_option_key(sr_post_string('skin_key', 40));
    $rewardProvider = sr_quiz_clean_key(sr_post_string('default_reward_provider', 30), 30);
    $rewardDedupeScope = sr_quiz_clean_key(sr_post_string('default_reward_dedupe_scope', 20), 20);
    $settings = sr_quiz_normalize_settings([
        'layout_key' => sr_public_layout_normalize_key(sr_post_string('layout_key', 80)),
        'skin_key' => $skinKey,
        'layout_primary_menu_key' => sr_quiz_clean_layout_menu_key(sr_post_string('layout_primary_menu_key', 60)),
        'layout_secondary_menu_key' => sr_quiz_clean_layout_menu_key(sr_post_string('layout_secondary_menu_key', 60)),
        'layout_tertiary_menu_key' => sr_quiz_clean_layout_menu_key(sr_post_string('layout_tertiary_menu_key', 60)),
        'layout_quaternary_menu_key' => sr_quiz_clean_layout_menu_key(sr_post_string('layout_quaternary_menu_key', 60)),
        'layout_quinary_menu_key' => sr_quiz_clean_layout_menu_key(sr_post_string('layout_quinary_menu_key', 60)),
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
        'reaction_preset_key' => function_exists('sr_reaction_setting_preset_key') && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? sr_reaction_setting_preset_key($GLOBALS['pdo'], sr_post_string('reaction_preset_key', 80)) : sr_quiz_clean_key(sr_post_string('reaction_preset_key', 80), 80),
        'reaction_comment_preset_key' => function_exists('sr_reaction_setting_preset_key') && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? sr_reaction_setting_preset_key($GLOBALS['pdo'], sr_post_string('reaction_comment_preset_key', 80)) : sr_quiz_clean_key(sr_post_string('reaction_comment_preset_key', 80), 80),
        'public_list_limit' => sr_post_string('public_list_limit', 20),
    ]);
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
    if (!isset(sr_public_layout_options($pdo)[(string) ($settings['layout_key'] ?? '')])) {
        $errors[] = '퀴즈 공개 레이아웃 값이 올바르지 않습니다.';
    }
    if (!isset(sr_quiz_skin_options()[(string) ($settings['skin_key'] ?? '')])) {
        $errors[] = '퀴즈 스킨 값이 올바르지 않습니다.';
    }
    $siteMenuOptions = [];
    if (sr_module_enabled($pdo, 'site_menu') && is_file(SR_ROOT . '/modules/site_menu/helpers.php')) {
        require_once SR_ROOT . '/modules/site_menu/helpers.php';
        $siteMenuOptions = sr_site_menu_options($pdo);
    }
    foreach (sr_quiz_layout_menu_slots() as $menuSettingKey) {
        $menuKey = (string) ($settings[$menuSettingKey] ?? '');
        if ($menuKey !== '' && !isset($siteMenuOptions[$menuKey])) {
            $errors[] = '퀴즈 레이아웃 사이트 메뉴 값이 올바르지 않습니다.';
            break;
        }
    }
    if ((string) ($settings['default_attempt_limit_policy'] ?? '') === 'per_period' && (int) ($settings['default_attempt_limit_period_seconds'] ?? 0) < 1) {
        $errors[] = '기본 응시 제한이 기간당 1회이면 제한 기간을 1초 이상 입력해야 합니다.';
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
    $context['style_profile'] = 'module';

    $stylesheets = is_array($context['stylesheets'] ?? null) ? $context['stylesheets'] : [];
    $stylesheets[] = '/modules/quiz/assets/reset.css';
    $stylesheets[] = '/modules/quiz/assets/ui-kit.css';
    $layoutStylesheet = sr_public_layout_module_stylesheet($layoutKey);
    if ($layoutStylesheet !== '') {
        $stylesheets[] = $layoutStylesheet;
    }
    $stylesheets[] = '/modules/quiz/assets/module.css';
    $stylesheets[] = '/modules/quiz/assets/skin.css';
    $context['stylesheets'] = array_values(array_unique($stylesheets));
    $scripts = is_array($context['scripts'] ?? null) ? $context['scripts'] : [];
    $scripts[] = '/modules/quiz/assets/module.js';
    $context['scripts'] = array_values(array_unique($scripts));
    $skinKey = sr_quiz_skin_key((string) ($settings['skin_key'] ?? 'basic'));
    $bodyClass = sr_ui_icon_class_attr((string) ($context['body_class'] ?? ''));
    $context['body_class'] = trim($bodyClass . ' sr-quiz-skin-' . $skinKey . ' quiz-skin-' . $skinKey);

    $siteMenus = [];
    foreach (sr_quiz_layout_menu_slots() as $slotKey => $settingKey) {
        $siteMenus[$slotKey] = sr_quiz_clean_layout_menu_key((string) ($settings[$settingKey] ?? ($slotKey === 'primary' ? 'header' : '')));
    }
    $context['site_menus'] = array_merge(is_array($context['site_menus'] ?? null) ? $context['site_menus'] : [], $siteMenus);

    return $context;
}

function sr_quiz_ui_kit_layout_context(array $settings, array $context = []): array
{
    $context = sr_quiz_public_layout_context($settings, $context);
    $stylesheets = is_array($context['stylesheets'] ?? null) ? $context['stylesheets'] : [];
    $stylesheets[] = '/modules/quiz/assets/ui-kit.css';
    $stylesheets[] = '/modules/quiz/assets/ui-kit-layout.css';
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

function sr_quiz_skin_view_file(array $settings, string $view): string
{
    if (!in_array($view, sr_quiz_skin_views(), true)) {
        throw new InvalidArgumentException('Unknown quiz skin view.');
    }

    $skinKey = sr_quiz_skin_key((string) ($settings['skin_key'] ?? 'basic'));
    $file = SR_ROOT . '/modules/quiz/skins/' . $skinKey . '/' . $view . '.php';
    if ($skinKey !== 'basic' && is_file($file)) {
        return $file;
    }

    $fallback = SR_ROOT . '/modules/quiz/skins/basic/' . $view . '.php';
    if ($skinKey !== 'basic' || !is_file($file)) {
        error_log('quiz_skin_fallback module=quiz skin_key=' . $skinKey . ' view=' . $view . ' fallback_file=' . $fallback);
    }
    if (!is_file($fallback)) {
        throw new RuntimeException('Default quiz skin view is missing: ' . $view);
    }

    return $fallback;
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
        $valueType = is_bool($value) ? 'bool' : (is_int($value) ? 'int' : 'string');
        $save->execute([
            'module_id' => (int) $module['id'],
            'setting_key' => (string) $key,
            'setting_value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
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

function sr_quiz_attempt_status_label(string $status): string
{
    return [
        'submitted' => '제출',
        'scored' => '채점 완료',
        'rewarded' => '보상 완료',
        'failed' => '실패',
    ][$status] ?? $status;
}

function sr_quiz_reward_grant_status_label(string $status): string
{
    return [
        'pending' => '대기',
        'granted' => '지급',
        'failed' => '실패',
        'duplicate' => '중복',
        'cancelled' => '취소',
    ][$status] ?? $status;
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

function sr_quiz_public_window_is_open(array $quiz, ?string $now = null): bool
{
    $now = $now ?? sr_now();
    $startsAt = trim((string) ($quiz['starts_at'] ?? ''));
    $endsAt = trim((string) ($quiz['ends_at'] ?? ''));

    return ($startsAt === '' || $startsAt <= $now)
        && ($endsAt === '' || $endsAt >= $now);
}

function sr_quiz_account_can_attempt(PDO $pdo, array $quiz, int $accountId): array
{
    if ($accountId < 1) {
        return ['allowed' => false, 'message' => '로그인 후 퀴즈를 풀 수 있습니다.'];
    }
    if ((string) ($quiz['status'] ?? '') !== 'active' || !sr_quiz_public_window_is_open($quiz)) {
        return ['allowed' => false, 'message' => '현재 응시할 수 없는 퀴즈입니다.'];
    }

    $requiredGroupKeys = sr_quiz_member_group_keys_from_value($quiz['member_group_keys_json'] ?? '');
    if ($requiredGroupKeys !== [] && (!function_exists('sr_member_account_in_any_group') || !sr_member_account_in_any_group($pdo, $accountId, $requiredGroupKeys))) {
        return ['allowed' => false, 'message' => '응시 권한이 없는 퀴즈입니다.'];
    }

    $policy = (string) ($quiz['attempt_limit_policy'] ?? 'unlimited');
    if (!in_array($policy, sr_quiz_attempt_limit_policies(), true)) {
        $policy = 'unlimited';
    }
    if ($policy === 'unlimited') {
        return ['allowed' => true, 'message' => ''];
    }

    $params = [
        'quiz_id' => (int) ($quiz['id'] ?? 0),
        'account_id' => $accountId,
    ];
    $sql = 'SELECT COUNT(*) FROM sr_quiz_attempts WHERE quiz_id = :quiz_id AND account_id = :account_id AND submitted_at IS NOT NULL';
    if ($policy === 'per_period') {
        $seconds = (int) ($quiz['attempt_limit_period_seconds'] ?? 0);
        if ($seconds < 1) {
            return ['allowed' => false, 'message' => '응시 제한 기간 설정을 확인해야 합니다.'];
        }
        $since = date('Y-m-d H:i:s', max(0, time() - $seconds));
        $sql .= ' AND submitted_at >= :since';
        $params['since'] = $since;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ((int) $stmt->fetchColumn() > 0) {
        return ['allowed' => false, 'message' => '응시 제한에 따라 다시 제출할 수 없습니다.'];
    }

    return ['allowed' => true, 'message' => ''];
}

function sr_quiz_lock_quiz_for_attempt(PDO $pdo, int $quizId): ?array
{
    if ($quizId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_sets
         WHERE id = :id
           AND deleted_at IS NULL
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute(['id' => $quizId]);
    $quiz = $stmt->fetch();

    return is_array($quiz) ? $quiz : null;
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

function sr_quiz_comments_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_quiz_comments LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_quiz_comment_statuses(): array
{
    return ['published', 'hidden', 'deleted'];
}

function sr_quiz_comment_status_label(string $status): string
{
    return [
        'published' => '게시',
        'hidden' => '숨김',
        'deleted' => '삭제',
    ][$status] ?? $status;
}

function sr_quiz_comment_author_public_name_snapshot(PDO $pdo, int $accountId): string
{
    $name = trim(sr_member_public_name_for_account_id($pdo, $accountId, '회원'));

    return function_exists('mb_substr') ? mb_substr($name, 0, 120) : substr($name, 0, 120);
}

function sr_quiz_comment_thread_columns_exist(PDO $pdo): bool
{
    static $existsByConnection = [];
    $key = (string) spl_object_id($pdo);
    if (array_key_exists($key, $existsByConnection)) {
        return $existsByConnection[$key];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME IN (\'parent_comment_id\', \'thread_root_id\', \'depth\')'
        );
        $stmt->execute(['table_name' => 'sr_quiz_comments']);
        $existsByConnection[$key] = (int) $stmt->fetchColumn() === 3;
    } catch (Throwable $exception) {
        $existsByConnection[$key] = false;
    }

    return $existsByConnection[$key];
}

function sr_quiz_comments(PDO $pdo, int $quizId, int $limit = 100): array
{
    if ($quizId < 1 || !sr_quiz_comments_table_exists($pdo)) {
        return [];
    }

    $orderSql = sr_quiz_comment_thread_columns_exist($pdo)
        ? 'COALESCE(c.thread_root_id, c.id) ASC, c.depth ASC, c.id ASC'
        : 'c.id ASC';
    $stmt = $pdo->prepare(
        "SELECT c.*, a.display_name AS author_display_name, a.status AS author_account_status
         FROM sr_quiz_comments c
         LEFT JOIN sr_member_accounts a ON a.id = c.author_account_id
         WHERE c.quiz_id = :quiz_id
           AND c.status = 'published'
         ORDER BY " . $orderSql . "
         LIMIT :limit_value"
    );
    $stmt->bindValue('quiz_id', $quizId, PDO::PARAM_INT);
    $stmt->bindValue('limit_value', max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    $comments = [];
    foreach ($stmt->fetchAll() as $comment) {
        $snapshot = trim((string) ($comment['author_public_name_snapshot'] ?? ''));
        $comment['author_public_name'] = !in_array((string) ($comment['author_account_status'] ?? ''), ['withdrawn', 'anonymized'], true) && $snapshot !== ''
            ? $snapshot
            : sr_member_public_name([
                'display_name' => (string) ($comment['author_display_name'] ?? ''),
                'status' => (string) ($comment['author_account_status'] ?? ''),
            ], sr_member_settings($pdo), '회원');
        $comments[] = $comment;
    }

    return $comments;
}

function sr_quiz_comment_input_values(): array
{
    $parentCommentIdValue = sr_post_string('parent_comment_id', 20);

    return [
        'body_text' => sr_post_string_without_truncation('body_text', 5000),
        'is_secret' => sr_post_string('is_secret', 10) === '1' ? 1 : 0,
        'parent_comment_id' => preg_match('/\A[1-9][0-9]*\z/', $parentCommentIdValue) === 1 ? (int) $parentCommentIdValue : 0,
    ];
}

function sr_quiz_validate_comment_input(array $values): array
{
    if (!is_string($values['body_text'] ?? null)) {
        return ['댓글은 5000자 이하로 입력해 주세요.'];
    }
    if (trim((string) $values['body_text']) === '') {
        return ['댓글 내용을 입력하세요.'];
    }

    return [];
}

function sr_quiz_validate_comment_parent(PDO $pdo, int $quizId, array $values): array
{
    $parentCommentId = (int) ($values['parent_comment_id'] ?? 0);
    if ($parentCommentId < 1) {
        return ['parent_comment' => null, 'errors' => []];
    }
    if (!sr_quiz_comment_thread_columns_exist($pdo)) {
        return ['parent_comment' => null, 'errors' => ['답글 기능을 사용할 수 없습니다. 업데이트를 먼저 적용해 주세요.']];
    }

    $parentComment = sr_quiz_comment_by_id($pdo, $parentCommentId);
    if (!is_array($parentComment) || (int) ($parentComment['quiz_id'] ?? 0) !== $quizId || (string) ($parentComment['status'] ?? '') !== 'published') {
        return ['parent_comment' => null, 'errors' => ['답글을 작성할 댓글을 찾을 수 없습니다.']];
    }
    if ((int) ($parentComment['depth'] ?? 1) >= 3) {
        return ['parent_comment' => null, 'errors' => ['답글은 3단계까지만 작성할 수 있습니다.']];
    }

    return ['parent_comment' => $parentComment, 'errors' => []];
}

function sr_quiz_create_comment(PDO $pdo, int $quizId, int $authorAccountId, array $values): int
{
    if (!sr_quiz_comments_table_exists($pdo)) {
        throw new RuntimeException('quiz_comments_not_installed');
    }

    $now = sr_now();
    $threadColumnSql = sr_quiz_comment_thread_columns_exist($pdo) ? 'parent_comment_id, thread_root_id, depth, ' : '';
    $threadValueSql = $threadColumnSql !== '' ? ':parent_comment_id, :thread_root_id, :depth, ' : '';
    $parentComment = is_array($values['parent_comment'] ?? null) ? $values['parent_comment'] : null;
    $parentCommentId = is_array($parentComment) ? (int) ($parentComment['id'] ?? 0) : 0;
    $depth = is_array($parentComment) ? min(3, max(2, (int) ($parentComment['depth'] ?? 1) + 1)) : 1;
    $threadRootId = is_array($parentComment) ? (int) (($parentComment['thread_root_id'] ?? 0) ?: ($parentComment['id'] ?? 0)) : null;
    $stmt = $pdo->prepare(
        'INSERT INTO sr_quiz_comments
            (quiz_id, ' . $threadColumnSql . 'author_account_id, author_public_name_snapshot, body_text, is_secret, status, created_at, updated_at)
         VALUES
            (:quiz_id, ' . $threadValueSql . ':author_account_id, :author_public_name_snapshot, :body_text, :is_secret, :status, :created_at, :updated_at)'
    );
    $params = [
        'quiz_id' => $quizId,
        'author_account_id' => $authorAccountId,
        'author_public_name_snapshot' => sr_quiz_comment_author_public_name_snapshot($pdo, $authorAccountId),
        'body_text' => trim((string) $values['body_text']),
        'is_secret' => (int) ($values['is_secret'] ?? 0) === 1 ? 1 : 0,
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if ($threadColumnSql !== '') {
        $params['parent_comment_id'] = $parentCommentId > 0 ? $parentCommentId : null;
        $params['thread_root_id'] = $threadRootId;
        $params['depth'] = $depth;
    }
    $stmt->execute($params);

    $commentId = (int) $pdo->lastInsertId();
    if ($threadColumnSql !== '' && $parentCommentId < 1) {
        $stmt = $pdo->prepare(
            'UPDATE sr_quiz_comments
             SET thread_root_id = :thread_root_id
             WHERE id = :id'
        );
        $stmt->execute([
            'thread_root_id' => $commentId,
            'id' => $commentId,
        ]);
    }

    return $commentId;
}

function sr_quiz_comment_by_id(PDO $pdo, int $commentId): ?array
{
    if ($commentId < 1 || !sr_quiz_comments_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_comments
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $commentId]);
    $comment = $stmt->fetch();

    return is_array($comment) ? $comment : null;
}

function sr_quiz_account_can_edit_comment(array $comment, array $account): bool
{
    return (int) ($account['id'] ?? 0) > 0
        && (int) ($comment['author_account_id'] ?? 0) === (int) $account['id']
        && (string) ($comment['status'] ?? '') === 'published';
}

function sr_quiz_account_has_result(PDO $pdo, int $quizId, int $accountId): bool
{
    return sr_quiz_latest_attempt_result($pdo, $quizId, $accountId) !== null;
}

function sr_quiz_account_can_manage_comments(PDO $pdo, ?array $account): bool
{
    return is_array($account)
        && (int) ($account['id'] ?? 0) > 0
        && function_exists('sr_admin_has_permission')
        && (sr_admin_has_permission($pdo, (int) $account['id'], '/admin/quiz/comments', 'view')
            || sr_admin_has_permission($pdo, (int) $account['id'], '/admin/quiz', 'edit'));
}

function sr_quiz_account_can_view_comment_body(array $comment, ?array $account, PDO $pdo): bool
{
    if ((int) ($comment['is_secret'] ?? 0) !== 1) {
        return true;
    }
    if (!is_array($account)) {
        return false;
    }

    return (int) ($account['id'] ?? 0) === (int) ($comment['author_account_id'] ?? 0)
        || sr_quiz_account_owns_comment_target($pdo, $comment, (int) ($account['id'] ?? 0))
        || sr_quiz_account_can_manage_comments($pdo, $account);
}

function sr_quiz_account_owns_comment_target(PDO $pdo, array $comment, int $accountId): bool
{
    $quizId = (int) ($comment['quiz_id'] ?? 0);
    if ($quizId < 1 || $accountId < 1) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT created_by_account_id
         FROM sr_quiz_sets
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $quizId]);

    return (int) $stmt->fetchColumn() === $accountId;
}

function sr_quiz_account_can_delete_comment(array $comment, array $account, PDO $pdo): bool
{
    return sr_quiz_account_can_edit_comment($comment, $account) || sr_quiz_account_can_manage_comments($pdo, $account);
}

function sr_quiz_update_comment_content(PDO $pdo, int $commentId, array $values): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_quiz_comments
         SET body_text = :body_text,
             is_secret = :is_secret,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'body_text' => trim((string) $values['body_text']),
        'is_secret' => (int) ($values['is_secret'] ?? 0) === 1 ? 1 : 0,
        'updated_at' => sr_now(),
        'id' => $commentId,
    ]);
}

function sr_quiz_update_comment_status(PDO $pdo, int $commentId, string $status): void
{
    if (!in_array($status, sr_quiz_comment_statuses(), true)) {
        return;
    }
    if ($status === 'deleted') {
        sr_quiz_delete_comment_redacted($pdo, $commentId);
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_quiz_comments
         SET status = :status,
             deleted_at = CASE WHEN :status_deleted = \'deleted\' THEN :deleted_at ELSE deleted_at END,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $now = sr_now();
    $stmt->execute([
        'status' => $status,
        'status_deleted' => $status,
        'deleted_at' => $now,
        'updated_at' => $now,
        'id' => $commentId,
    ]);
}

function sr_quiz_delete_comment_redacted(PDO $pdo, int $commentId): void
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        "UPDATE sr_quiz_comments
         SET author_public_name_snapshot = '',
             body_text = :body_text,
             status = 'deleted',
             deleted_at = :deleted_at,
             updated_at = :updated_at
         WHERE id = :id"
    );
    $stmt->execute([
        'body_text' => '삭제된 댓글입니다.',
        'deleted_at' => $now,
        'updated_at' => $now,
        'id' => $commentId,
    ]);
}

function sr_quiz_notification_event_function(PDO $pdo): string
{
    return sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_account_event_function');
}

function sr_quiz_create_account_event_notification(PDO $pdo, int $accountId, string $eventKey, array $metadata, ?int $createdByAccountId = null): bool
{
    $createAccountEventFunction = sr_quiz_notification_event_function($pdo);
    if ($accountId < 1 || $createAccountEventFunction === '') {
        return false;
    }

    try {
        return $createAccountEventFunction($pdo, [
            'account_id' => $accountId,
            'module_key' => 'quiz',
            'event_key' => $eventKey,
            'created_by_account_id' => $createdByAccountId,
            'metadata' => $metadata,
        ]) !== null;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'quiz_notification_event_create');
    }

    return false;
}

function sr_quiz_mentioned_account_ids(PDO $pdo, string $bodyText, array $excludeAccountIds = []): array
{
    return sr_member_mention_account_ids($pdo, sr_runtime_config(), $bodyText, $excludeAccountIds);
}

function sr_quiz_create_comment_mention_notifications(
    PDO $pdo,
    array $quiz,
    int $commentId,
    string $bodyText,
    int $createdByAccountId,
    array $excludeAccountIds = [],
    ?string $previousBodyText = null
): array {
    $result = [
        'mention_candidate_count' => 0,
        'mention_notification_count' => 0,
        'mention_account_hashes' => [],
    ];
    $quizId = (int) ($quiz['id'] ?? 0);
    if ($quizId < 1 || $commentId < 1) {
        return $result;
    }

    $excludeAccountIds[] = $createdByAccountId;
    $mentionedAccountIds = sr_quiz_mentioned_account_ids($pdo, $bodyText, $excludeAccountIds);
    if ($previousBodyText !== null) {
        $previousAccountIds = sr_quiz_mentioned_account_ids($pdo, $previousBodyText, $excludeAccountIds);
        $previousMap = array_fill_keys(array_map('intval', $previousAccountIds), true);
        $mentionedAccountIds = array_values(array_filter($mentionedAccountIds, static function (int $accountId) use ($previousMap): bool {
            return !isset($previousMap[$accountId]);
        }));
    }

    $result['mention_candidate_count'] = count($mentionedAccountIds);
    $config = sr_runtime_config();
    $metadata = [
        'quiz_id' => $quizId,
        'comment_id' => $commentId,
        'member_name' => sr_member_public_name_for_account_id($pdo, $createdByAccountId, '회원'),
        'link_url' => '/quiz/' . rawurlencode((string) ($quiz['quiz_key'] ?? '')) . '?result=1#quiz-comments',
        'created_at' => sr_now(),
    ];
    foreach ($mentionedAccountIds as $accountId) {
        $result['mention_account_hashes'][] = sr_member_public_account_hash($config, (int) $accountId);
    }
    foreach ($mentionedAccountIds as $accountId) {
        if (sr_quiz_create_account_event_notification($pdo, (int) $accountId, 'comment.mention', $metadata, $createdByAccountId)) {
            $result['mention_notification_count']++;
        }
    }

    return $result;
}

function sr_quiz_admin_comment_filters_from_request(): array
{
    return [
        'q' => sr_quiz_clean_single_line(sr_get_string('q', 120), 120),
        'status' => sr_quiz_clean_key(sr_get_string('status', 20), 20),
        'secret' => sr_quiz_clean_key(sr_get_string('secret', 10), 10),
    ];
}

function sr_quiz_admin_comments(PDO $pdo, array $filters = [], int $limit = 100): array
{
    if (!sr_quiz_comments_table_exists($pdo)) {
        return [];
    }

    $where = ['1 = 1'];
    $params = [];
    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $where[] = '(q.quiz_key LIKE :keyword OR q.title LIKE :keyword OR c.body_text LIKE :keyword OR c.author_public_name_snapshot LIKE :keyword)';
        $params['keyword'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
    }
    $status = (string) ($filters['status'] ?? '');
    if ($status !== '' && in_array($status, sr_quiz_comment_statuses(), true)) {
        $where[] = 'c.status = :status';
        $params['status'] = $status;
    }
    $secret = (string) ($filters['secret'] ?? '');
    if ($secret === 'yes' || $secret === 'no') {
        $where[] = 'c.is_secret = :is_secret';
        $params['is_secret'] = $secret === 'yes' ? 1 : 0;
    }

    $stmt = $pdo->prepare(
        'SELECT c.*, q.quiz_key, q.title AS quiz_title
         FROM sr_quiz_comments c
         INNER JOIN sr_quiz_sets q ON q.id = c.quiz_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY c.created_at DESC, c.id DESC
         LIMIT ' . (string) max(1, min(200, $limit))
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_quiz_questions_with_choices(PDO $pdo, int $quizId): array
{
    if ($quizId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_questions
         WHERE quiz_id = :quiz_id
         ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute(['quiz_id' => $quizId]);
    $questions = $stmt->fetchAll();
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

    return $questions;
}

function sr_quiz_active_reward_policy(PDO $pdo, int $quizId): ?array
{
    if ($quizId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_reward_policies
         WHERE quiz_id = :quiz_id
           AND status = \'active\'
         ORDER BY sort_order ASC, id ASC
         LIMIT 1'
    );
    $stmt->execute(['quiz_id' => $quizId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_quiz_selected_choice_ids_from_post(): array
{
    $answers = $_POST['answers'] ?? [];
    if (!is_array($answers)) {
        return [];
    }

    $selected = [];
    foreach ($answers as $questionId => $choiceValue) {
        $questionId = (int) $questionId;
        if (is_array($choiceValue)) {
            $choiceIds = [];
            foreach ($choiceValue as $choiceId) {
                $choiceId = (int) $choiceId;
                if ($choiceId > 0) {
                    $choiceIds[$choiceId] = true;
                }
            }
            $selected[$questionId] = array_keys($choiceIds);
        } else {
            $choiceId = (int) $choiceValue;
            $selected[$questionId] = $choiceId > 0 ? [$choiceId] : [];
        }
    }

    return $selected;
}

function sr_quiz_source_context_from_request(): array
{
    $sourceModule = sr_quiz_clean_key(sr_post_string('source_module', 40), 40);
    $sourceType = sr_quiz_clean_key(sr_post_string('source_type', 40), 40);
    $sourceId = (int) sr_post_string('source_id', 20);

    if ($sourceModule === '' || $sourceType === '' || $sourceId < 1) {
        return [
            'source_module' => null,
            'source_type' => null,
            'source_id' => null,
        ];
    }

    return [
        'source_module' => $sourceModule,
        'source_type' => $sourceType,
        'source_id' => $sourceId,
    ];
}

function sr_quiz_valid_source_context(PDO $pdo, int $quizId, array $sourceContext): array
{
    $sourceModule = (string) ($sourceContext['source_module'] ?? '');
    $sourceType = (string) ($sourceContext['source_type'] ?? '');
    $sourceId = (int) ($sourceContext['source_id'] ?? 0);

    if ($quizId < 1 || $sourceModule === '' || $sourceType === '' || $sourceId < 1) {
        return [
            'source_module' => null,
            'source_type' => null,
            'source_id' => null,
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_quiz_sources
         WHERE quiz_id = :quiz_id
           AND source_module = :source_module
           AND source_type = :source_type
           AND source_id = :source_id
           AND status = \'active\'
         LIMIT 1'
    );
    $stmt->execute([
        'quiz_id' => $quizId,
        'source_module' => $sourceModule,
        'source_type' => $sourceType,
        'source_id' => $sourceId,
    ]);

    if (!is_array($stmt->fetch()) || !sr_quiz_source_context_is_accessible($pdo, $sourceModule, $sourceType, $sourceId)) {
        return [
            'source_module' => null,
            'source_type' => null,
            'source_id' => null,
        ];
    }

    return [
        'source_module' => $sourceModule,
        'source_type' => $sourceType,
        'source_id' => $sourceId,
    ];
}

function sr_quiz_source_context_is_accessible(PDO $pdo, string $sourceModule, string $sourceType, int $sourceId): bool
{
    if ($sourceModule === 'content' && $sourceType === 'content_item') {
        if (!sr_module_enabled($pdo, 'content') || !is_file(SR_ROOT . '/modules/content/helpers.php')) {
            return false;
        }

        require_once SR_ROOT . '/modules/content/helpers.php';
        $page = sr_content_by_id($pdo, $sourceId);
        if (!is_array($page) || (string) ($page['status'] ?? '') !== 'published') {
            return false;
        }

        if (!sr_content_asset_access_required($page)) {
            return true;
        }

        $account = function_exists('sr_member_current_account') ? sr_member_current_account($pdo) : null;
        if (!is_array($account) || (int) ($account['id'] ?? 0) < 1) {
            return false;
        }

        $assetModules = sr_content_asset_module_keys_from_value($page['asset_module'] ?? '');

        return sr_content_once_access_already_granted($pdo, $assetModules, (int) $account['id'], $sourceId);
    }

    if ($sourceModule === 'community' && $sourceType === 'community_post') {
        if (!sr_module_enabled($pdo, 'community') || !is_file(SR_ROOT . '/modules/community/helpers.php')) {
            return false;
        }
        require_once SR_ROOT . '/modules/community/helpers.php';
        $account = function_exists('sr_member_current_account') ? sr_member_current_account($pdo) : null;
        $post = sr_community_post_for_read($pdo, $sourceId, is_array($account) ? $account : null);

        return is_array($post);
    }

    return false;
}

function sr_quiz_submit_attempt(PDO $pdo, array $quiz, array $questions, int $accountId, array $selectedChoiceIds, array $assetOptions): array
{
    $quizId = (int) ($quiz['id'] ?? 0);
    if ($quizId < 1 || $accountId < 1) {
        throw new InvalidArgumentException('Quiz and account are required.');
    }
    $attemptAccess = sr_quiz_account_can_attempt($pdo, $quiz, $accountId);
    if (empty($attemptAccess['allowed'])) {
        throw new RuntimeException((string) ($attemptAccess['message'] ?? 'Quiz attempt is not allowed.'));
    }

    $now = sr_now();
    $passScore = 0;
    $passed = false;
    $rewardGrant = null;
    $rewardError = '';

    $pdo->beginTransaction();
    try {
        $lockedQuiz = sr_quiz_lock_quiz_for_attempt($pdo, $quizId);
        if (!is_array($lockedQuiz)) {
            throw new RuntimeException('현재 응시할 수 없는 퀴즈입니다.');
        }
        $attemptAccess = sr_quiz_account_can_attempt($pdo, $lockedQuiz, $accountId);
        if (empty($attemptAccess['allowed'])) {
            throw new RuntimeException((string) ($attemptAccess['message'] ?? 'Quiz attempt is not allowed.'));
        }
        $quiz = $lockedQuiz;
        $questions = sr_quiz_questions_with_choices($pdo, $quizId);
        if ($questions === []) {
            throw new RuntimeException('응시 가능한 문제가 없습니다.');
        }
        $scoredAnswers = sr_quiz_score_answers($questions, $selectedChoiceIds);
        $totalScore = (int) $scoredAnswers['total_score'];
        $categoryScores = (array) $scoredAnswers['category_scores'];
        $answerRows = (array) $scoredAnswers['answer_rows'];
        $allRequiredAnswered = !empty($scoredAnswers['all_required_answered']);
        $passScore = $quiz['pass_score'] === null ? 0 : (int) $quiz['pass_score'];
        $scoringModel = (string) ($quiz['scoring_model'] ?? 'correct_answer');
        if (!in_array($scoringModel, sr_quiz_scoring_models(), true)) {
            $scoringModel = 'correct_answer';
        }
        $selectedResult = sr_quiz_select_result($pdo, $quizId, $scoringModel, $totalScore, $categoryScores);
        $displayScore = sr_quiz_attempt_display_score($scoringModel, $totalScore, $categoryScores, $selectedResult);
        $passed = $allRequiredAnswered && ($scoringModel === 'category_score' || $totalScore >= $passScore);

        $returnUrl = sr_quiz_internal_return_path(sr_post_string('return_to', 255));
        $sourceContext = sr_quiz_valid_source_context($pdo, $quizId, sr_quiz_source_context_from_request());
        $sourceSnapshot = sr_quiz_source_snapshot($pdo, $sourceContext, $returnUrl);
        $stmt = $pdo->prepare(
            'INSERT INTO sr_quiz_attempts
                (quiz_id, account_id, status, source_module, source_type, source_id, return_url, started_at, submitted_at, scored_at,
                 source_title_snapshot, source_url_snapshot, total_score, passed, selected_result_id, answer_snapshot_json, scoring_snapshot_json, result_snapshot_json, user_agent_hash, ip_hash, created_at, updated_at)
             VALUES
                (:quiz_id, :account_id, \'scored\', :source_module, :source_type, :source_id, :return_url, :started_at, :submitted_at, :scored_at,
                 :source_title_snapshot, :source_url_snapshot, :total_score, :passed, :selected_result_id, :answer_snapshot_json, :scoring_snapshot_json, :result_snapshot_json, :user_agent_hash, :ip_hash, :created_at, :updated_at)'
        );
        $answerSnapshotJson = json_encode($selectedChoiceIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $scoringSnapshotJson = json_encode([
            'quiz_key' => (string) ($quiz['quiz_key'] ?? ''),
            'scoring_model' => $scoringModel,
            'pass_score' => $passScore,
            'total_score' => $totalScore,
            'category_scores' => $categoryScores,
            'passed' => $passed,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $resultSnapshotJson = json_encode($selectedResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt->execute([
            'quiz_id' => $quizId,
            'account_id' => $accountId,
            'source_module' => $sourceContext['source_module'],
            'source_type' => $sourceContext['source_type'],
            'source_id' => $sourceContext['source_id'],
            'return_url' => $returnUrl,
            'started_at' => $now,
            'submitted_at' => $now,
            'scored_at' => $now,
            'source_title_snapshot' => $sourceSnapshot['title'],
            'source_url_snapshot' => $sourceSnapshot['url'],
            'total_score' => $totalScore,
            'passed' => $passed ? 1 : 0,
            'selected_result_id' => isset($selectedResult['id']) ? (int) $selectedResult['id'] : null,
            'answer_snapshot_json' => is_string($answerSnapshotJson) ? $answerSnapshotJson : '{}',
            'scoring_snapshot_json' => is_string($scoringSnapshotJson) ? $scoringSnapshotJson : '{}',
            'result_snapshot_json' => is_string($resultSnapshotJson) ? $resultSnapshotJson : '{}',
            'user_agent_hash' => hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
            'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '')),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $attemptId = (int) $pdo->lastInsertId();

        $answerStmt = $pdo->prepare(
            'INSERT INTO sr_quiz_attempt_answers
                (attempt_id, question_id, question_key, choice_id, choice_key, answer_snapshot_json, score_awarded, category_scores_json, created_at)
             VALUES
                (:attempt_id, :question_id, :question_key, :choice_id, :choice_key, :answer_snapshot_json, :score_awarded, :category_scores_json, :created_at)'
        );
        foreach ($answerRows as $answerRow) {
            $question = (array) $answerRow['question'];
            $selectedChoices = (array) ($answerRow['choices'] ?? []);
            $firstChoice = is_array($selectedChoices[0] ?? null) ? $selectedChoices[0] : [];
            $choiceLabels = [];
            $choiceKeys = [];
            foreach ($selectedChoices as $selectedChoice) {
                if (is_array($selectedChoice)) {
                    $choiceLabels[] = (string) ($selectedChoice['label'] ?? '');
                    $choiceKeys[] = (string) ($selectedChoice['choice_key'] ?? '');
                }
            }
            $snapshotJson = json_encode([
                'question_prompt' => (string) ($question['prompt'] ?? ''),
                'choice_labels' => $choiceLabels,
                'choice_keys' => $choiceKeys,
                'selected_choice_ids' => (array) ($answerRow['selected_choice_ids'] ?? []),
                'correct_choice_ids' => (array) ($answerRow['correct_choice_ids'] ?? []),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $answerCategoryJson = json_encode(sr_quiz_answer_category_scores($selectedChoices), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $answerStmt->execute([
                'attempt_id' => $attemptId,
                'question_id' => (int) ($question['id'] ?? 0),
                'question_key' => (string) ($question['question_key'] ?? ''),
                'choice_id' => isset($firstChoice['id']) ? (int) $firstChoice['id'] : null,
                'choice_key' => count(array_filter($choiceKeys)) === 1 ? (string) array_values(array_filter($choiceKeys))[0] : null,
                'answer_snapshot_json' => is_string($snapshotJson) ? $snapshotJson : '{}',
                'score_awarded' => (int) $answerRow['score_awarded'],
                'category_scores_json' => is_string($answerCategoryJson) ? $answerCategoryJson : '{}',
                'created_at' => $now,
            ]);
        }

        sr_quiz_save_attempt_result_scores($pdo, $attemptId, $selectedResult, $categoryScores, $now);

        if ($passed && (int) ($quiz['reward_enabled'] ?? 0) === 1) {
            $rewardPolicy = sr_quiz_active_reward_policy($pdo, $quizId);
            if (is_array($rewardPolicy)) {
                $rewardGrant = sr_quiz_issue_reward_grant($pdo, $quiz, $attemptId, $accountId, $rewardPolicy, $assetOptions, $now, $sourceContext);
                $rewardError = (string) ($rewardGrant['error_message'] ?? '');
            }
        }

        $pdo->commit();

        return [
            'attempt_id' => $attemptId,
            'total_score' => $totalScore,
            'display_score' => $displayScore,
            'category_scores' => $categoryScores,
            'passed' => $passed,
            'selected_result' => $selectedResult,
            'reward_grant' => $rewardGrant,
            'reward_error' => $rewardError,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_quiz_latest_attempt_result(PDO $pdo, int $quizId, int $accountId): ?array
{
    if ($quizId < 1 || $accountId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, total_score, passed, scoring_snapshot_json, result_snapshot_json
         FROM sr_quiz_attempts
         WHERE quiz_id = :quiz_id
           AND account_id = :account_id
           AND submitted_at IS NOT NULL
         ORDER BY submitted_at DESC, id DESC
         LIMIT 1'
    );
    $stmt->execute([
        'quiz_id' => $quizId,
        'account_id' => $accountId,
    ]);
    $attempt = $stmt->fetch();
    if (!is_array($attempt)) {
        return null;
    }

    $scoringSnapshot = json_decode((string) ($attempt['scoring_snapshot_json'] ?? ''), true);
    $resultSnapshot = json_decode((string) ($attempt['result_snapshot_json'] ?? ''), true);
    $categoryScores = is_array($scoringSnapshot) && is_array($scoringSnapshot['category_scores'] ?? null)
        ? (array) $scoringSnapshot['category_scores']
        : [];

    $selectedResult = is_array($resultSnapshot) ? $resultSnapshot : [];
    $totalScore = (int) ($attempt['total_score'] ?? 0);
    $scoringModel = is_array($scoringSnapshot) ? (string) ($scoringSnapshot['scoring_model'] ?? 'correct_answer') : 'correct_answer';
    if (!in_array($scoringModel, sr_quiz_scoring_models(), true)) {
        $scoringModel = 'correct_answer';
    }

    return [
        'attempt_id' => (int) ($attempt['id'] ?? 0),
        'total_score' => $totalScore,
        'display_score' => sr_quiz_attempt_display_score($scoringModel, $totalScore, $categoryScores, $selectedResult),
        'category_scores' => $categoryScores,
        'passed' => (int) ($attempt['passed'] ?? 0) === 1,
        'selected_result' => $selectedResult,
        'reward_grant' => null,
        'reward_error' => '',
    ];
}

function sr_quiz_attempt_display_score(string $scoringModel, int $totalScore, array $categoryScores, array $selectedResult): int
{
    if ($scoringModel !== 'category_score') {
        return $totalScore;
    }

    $categoryKey = (string) ($selectedResult['category_key'] ?? '');
    if ($categoryKey !== '' && isset($categoryScores[$categoryKey])) {
        return (int) $categoryScores[$categoryKey];
    }

    if ($categoryScores === []) {
        return 0;
    }

    return max(array_map('intval', array_values($categoryScores)));
}

function sr_quiz_answer_category_scores(array $choices): array
{
    $scores = [];
    foreach ($choices as $choice) {
        if (!is_array($choice)) {
            continue;
        }
        $categoryKey = (string) ($choice['category_key'] ?? '');
        $categoryWeight = (int) ($choice['category_weight'] ?? 0);
        if ($categoryKey !== '' && $categoryWeight !== 0) {
            $scores[$categoryKey] = (int) ($scores[$categoryKey] ?? 0) + $categoryWeight;
        }
    }

    return $scores;
}

function sr_quiz_score_answers(array $questions, array $selectedChoiceIds): array
{
    $totalScore = 0;
    $categoryScores = [];
    $answerRows = [];
    $allRequiredAnswered = true;
    foreach ($questions as $question) {
        $questionId = (int) ($question['id'] ?? 0);
        $questionType = (string) ($question['question_type'] ?? 'single_choice');
        if (!in_array($questionType, sr_quiz_question_types(), true)) {
            $questionType = 'single_choice';
        }
        $selectedIds = $selectedChoiceIds[$questionId] ?? [];
        if (!is_array($selectedIds)) {
            $selectedIds = [(int) $selectedIds];
        }
        $selectedIds = array_values(array_filter(array_unique(array_map('intval', $selectedIds)), static fn (int $choiceId): bool => $choiceId > 0));
        if ($questionType === 'single_choice' && count($selectedIds) > 1) {
            $selectedIds = [(int) $selectedIds[0]];
        }
        $selectedChoices = [];
        $correctIds = [];
        foreach ((array) ($question['choices'] ?? []) as $choice) {
            $choiceId = (int) ($choice['id'] ?? 0);
            if ((int) ($choice['is_correct'] ?? 0) === 1) {
                $correctIds[] = $choiceId;
            }
            if (in_array($choiceId, $selectedIds, true)) {
                $selectedChoices[] = $choice;
                $categoryKey = (string) ($choice['category_key'] ?? '');
                $categoryWeight = (int) ($choice['category_weight'] ?? 0);
                if ($categoryKey !== '' && $categoryWeight !== 0) {
                    $categoryScores[$categoryKey] = (int) ($categoryScores[$categoryKey] ?? 0) + $categoryWeight;
                }
            }
        }
        if ((int) ($question['required'] ?? 1) === 1 && $selectedChoices === []) {
            $allRequiredAnswered = false;
        }
        sort($selectedIds);
        sort($correctIds);
        $scoreAwarded = $selectedIds !== [] && $selectedIds === $correctIds ? (int) ($question['score_value'] ?? 0) : 0;
        $totalScore += $scoreAwarded;
        $answerRows[] = [
            'question' => $question,
            'choices' => $selectedChoices,
            'selected_choice_ids' => $selectedIds,
            'correct_choice_ids' => $correctIds,
            'score_awarded' => $scoreAwarded,
        ];
    }

    return [
        'total_score' => $totalScore,
        'category_scores' => $categoryScores,
        'answer_rows' => $answerRows,
        'all_required_answered' => $allRequiredAnswered,
    ];
}

function sr_quiz_select_result(PDO $pdo, int $quizId, string $scoringModel, int $totalScore, array $categoryScores): array
{
    $stmt = $pdo->prepare(
        'SELECT r.*, rr.rule_type, rr.min_score, rr.max_score, rr.category_key, rr.threshold_value, rr.priority
         FROM sr_quiz_result_rules rr
         INNER JOIN sr_quiz_results r ON r.id = rr.result_id
         WHERE rr.quiz_id = :quiz_id
           AND r.status = \'active\'
         ORDER BY rr.priority DESC, r.sort_order ASC, r.id ASC'
    );
    $stmt->execute(['quiz_id' => $quizId]);
    $fallback = [];
    foreach ($stmt->fetchAll() as $row) {
        if ($fallback === []) {
            $fallback = $row;
        }
        $ruleType = (string) ($row['rule_type'] ?? '');
        if ($scoringModel === 'category_score' || $ruleType === 'category_threshold') {
            $categoryKey = (string) ($row['category_key'] ?? '');
            $threshold = $row['threshold_value'] === null ? null : (int) $row['threshold_value'];
            if ($categoryKey !== '' && $threshold !== null && (int) ($categoryScores[$categoryKey] ?? 0) >= $threshold) {
                return sr_quiz_result_snapshot($row);
            }
            continue;
        }

        $min = $row['min_score'] === null ? null : (int) $row['min_score'];
        $max = $row['max_score'] === null ? null : (int) $row['max_score'];
        if (($min === null || $totalScore >= $min) && ($max === null || $totalScore <= $max)) {
            return sr_quiz_result_snapshot($row);
        }
    }

    return $fallback !== [] ? sr_quiz_result_snapshot($fallback) : [];
}

function sr_quiz_result_snapshot(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'result_key' => (string) ($row['result_key'] ?? ''),
        'title' => (string) ($row['title'] ?? ''),
        'summary' => (string) ($row['summary'] ?? ''),
        'rule_type' => (string) ($row['rule_type'] ?? ''),
        'category_key' => (string) ($row['category_key'] ?? ''),
    ];
}

function sr_quiz_save_attempt_result_scores(PDO $pdo, int $attemptId, array $selectedResult, array $categoryScores, string $now): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO sr_quiz_attempt_result_scores
            (attempt_id, result_id, category_key, score_value, is_selected, snapshot_json, created_at)
         VALUES
            (:attempt_id, :result_id, :category_key, :score_value, :is_selected, :snapshot_json, :created_at)'
    );
    $selectedJson = json_encode($selectedResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($selectedResult !== []) {
        $stmt->execute([
            'attempt_id' => $attemptId,
            'result_id' => (int) ($selectedResult['id'] ?? 0),
            'category_key' => (string) ($selectedResult['category_key'] ?? '') ?: null,
            'score_value' => 0,
            'is_selected' => 1,
            'snapshot_json' => is_string($selectedJson) ? $selectedJson : '{}',
            'created_at' => $now,
        ]);
    }
    foreach ($categoryScores as $categoryKey => $scoreValue) {
        $stmt->execute([
            'attempt_id' => $attemptId,
            'result_id' => null,
            'category_key' => (string) $categoryKey,
            'score_value' => (int) $scoreValue,
            'is_selected' => 0,
            'snapshot_json' => '{}',
            'created_at' => $now,
        ]);
    }
}

function sr_quiz_source_snapshot(PDO $pdo, array $sourceContext, string $returnUrl): array
{
    $sourceModule = (string) ($sourceContext['source_module'] ?? '');
    $sourceType = (string) ($sourceContext['source_type'] ?? '');
    $sourceId = (int) ($sourceContext['source_id'] ?? 0);
    $snapshot = ['title' => null, 'url' => $returnUrl !== '' ? $returnUrl : null];

    if ($sourceId < 1) {
        return $snapshot;
    }

    if ($sourceModule === 'content' && $sourceType === 'content_item' && function_exists('sr_content_by_id')) {
        $page = sr_content_by_id($pdo, $sourceId);
        if (is_array($page)) {
            $snapshot['title'] = (string) ($page['title'] ?? '');
        }
    } elseif ($sourceModule === 'community' && $sourceType === 'community_post' && function_exists('sr_community_post_for_read')) {
        $account = function_exists('sr_member_current_account') ? sr_member_current_account($pdo) : null;
        $post = sr_community_post_for_read($pdo, $sourceId, is_array($account) ? $account : null);
        if (is_array($post)) {
            $snapshot['title'] = (string) ($post['title'] ?? '');
        }
    }

    return $snapshot;
}

function sr_quiz_issue_reward_grant(PDO $pdo, array $quiz, int $attemptId, int $accountId, array $policy, array $assetOptions, string $now, array $sourceContext = []): array
{
    $quizId = (int) ($quiz['id'] ?? 0);
    $policyId = (int) ($policy['id'] ?? 0);
    $rewardProvider = (string) ($policy['reward_provider'] ?? 'ledger_asset');
    $rewardModule = (string) ($policy['reward_module'] ?? '');
    $rewardAmount = (int) ($policy['reward_amount'] ?? 0);
    $dedupeScope = (string) ($policy['dedupe_scope'] ?? 'per_quiz');
    if (!in_array($dedupeScope, sr_quiz_reward_dedupe_scopes(), true)) {
        $dedupeScope = 'per_quiz';
    }
    $dedupeKeyParts = [
        'quiz.reward',
        'account',
        (string) $accountId,
        'quiz',
        (string) $quizId,
        'policy',
        (string) $policyId,
        'provider',
        $rewardProvider,
        'module',
        $rewardModule,
    ];
    if ($dedupeScope === 'per_source') {
        $dedupeKeyParts[] = 'source';
        $dedupeKeyParts[] = (string) ($sourceContext['source_module'] ?? '');
        $dedupeKeyParts[] = (string) ($sourceContext['source_type'] ?? '');
        $dedupeKeyParts[] = (string) ($sourceContext['source_id'] ?? '');
    } elseif ($dedupeScope === 'per_attempt') {
        $dedupeKeyParts[] = 'attempt';
        $dedupeKeyParts[] = (string) $attemptId;
    }
    $dedupeKey = implode(':', $dedupeKeyParts);

    $insertVerb = 'INSERT IGNORE';
    try {
        if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $insertVerb = 'INSERT OR IGNORE';
        }
    } catch (Throwable $exception) {
        $insertVerb = 'INSERT IGNORE';
    }
    $stmt = $pdo->prepare(
        $insertVerb . ' INTO sr_quiz_reward_grants
            (quiz_id, attempt_id, reward_policy_id, account_id, reward_provider, reward_module, reward_code, reward_amount,
             source_module, source_type, source_id, dedupe_scope, dedupe_key, status, request_snapshot_json, created_at, updated_at)
         VALUES
            (:quiz_id, :attempt_id, :reward_policy_id, :account_id, :reward_provider, :reward_module, :reward_code, :reward_amount,
             :source_module, :source_type, :source_id, :dedupe_scope, :dedupe_key, \'pending\', :request_snapshot_json, :created_at, :updated_at)'
    );
    $snapshotJson = json_encode([
        'quiz_key' => (string) ($quiz['quiz_key'] ?? ''),
        'reward_provider' => $rewardProvider,
        'reward_module' => $rewardModule,
        'reward_amount' => $rewardAmount,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt->execute([
        'quiz_id' => $quizId,
        'attempt_id' => $attemptId,
        'reward_policy_id' => $policyId,
        'account_id' => $accountId,
        'reward_provider' => $rewardProvider,
        'reward_module' => $rewardModule,
        'reward_code' => (string) ($policy['reward_code'] ?? 'quiz_reward'),
        'reward_amount' => $rewardAmount,
        'source_module' => $sourceContext['source_module'] ?? null,
        'source_type' => $sourceContext['source_type'] ?? null,
        'source_id' => $sourceContext['source_id'] ?? null,
        'dedupe_scope' => $dedupeScope,
        'dedupe_key' => $dedupeKey,
        'request_snapshot_json' => is_string($snapshotJson) ? $snapshotJson : '{}',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $lockClause = '';
    try {
        $lockClause = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    } catch (Throwable $exception) {
        $lockClause = ' FOR UPDATE';
    }
    $grantStmt = $pdo->prepare('SELECT * FROM sr_quiz_reward_grants WHERE dedupe_key = :dedupe_key LIMIT 1' . $lockClause);
    $grantStmt->execute(['dedupe_key' => $dedupeKey]);
    $grant = $grantStmt->fetch();
    if (!is_array($grant)) {
        return [];
    }
    $existingAttemptId = (int) ($grant['attempt_id'] ?? 0);
    if ($existingAttemptId > 0 && $existingAttemptId !== $attemptId && $dedupeScope !== 'per_attempt') {
        $grant['status'] = 'duplicate';
        $grant['error_message'] = '';
        return $grant;
    }
    if ((string) ($grant['status'] ?? '') === 'granted') {
        return $grant;
    }

    $grantId = (int) ($grant['id'] ?? 0);
    sr_quiz_refresh_reward_grant_for_retry($pdo, $grantId, [
        'attempt_id' => $attemptId,
        'reward_policy_id' => $policyId,
        'reward_provider' => $rewardProvider,
        'reward_module' => $rewardModule,
        'reward_code' => (string) ($policy['reward_code'] ?? 'quiz_reward'),
        'reward_amount' => $rewardAmount,
        'source_module' => $sourceContext['source_module'] ?? null,
        'source_type' => $sourceContext['source_type'] ?? null,
        'source_id' => $sourceContext['source_id'] ?? null,
        'dedupe_scope' => $dedupeScope,
        'request_snapshot_json' => is_string($snapshotJson) ? $snapshotJson : '{}',
        'updated_at' => $now,
    ]);
    if ($rewardProvider === 'coupon') {
        return sr_quiz_issue_coupon_reward_grant($pdo, $quiz, $grantId, $attemptId, $accountId, $policy, $now);
    }
    if ($rewardProvider !== 'ledger_asset') {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '지원하지 않는 보상 공급자입니다.', $now);
    }
    if (!isset($assetOptions[$rewardModule])) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '보상 자산 계약을 찾을 수 없습니다.', $now);
    }

    $transactionFunction = (string) ($assetOptions[$rewardModule]['transaction_function'] ?? '');
    if (!function_exists($transactionFunction)) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '보상 자산 거래 함수를 찾을 수 없습니다.', $now);
    }

    try {
        $transactionId = (int) $transactionFunction($pdo, [
            'account_id' => $accountId,
            'amount' => $rewardAmount,
            'transaction_type' => (string) ($assetOptions[$rewardModule]['credit_type'] ?? 'grant'),
            'reason' => '퀴즈 보상: ' . (string) ($quiz['title'] ?? ''),
            'reference_type' => 'quiz_reward',
            'reference_id' => (string) $grantId,
            'created_by_account_id' => null,
        ]);
        $resultJson = json_encode(['transaction_id' => $transactionId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pdo->prepare(
            'UPDATE sr_quiz_reward_grants
             SET status = \'granted\',
                 provider_reference_type = :provider_reference_type,
                 provider_reference_id = :provider_reference_id,
                 result_snapshot_json = :result_snapshot_json,
                 granted_at = :granted_at,
                 updated_at = :updated_at
             WHERE id = :id'
        )->execute([
            'provider_reference_type' => 'quiz_reward',
            'provider_reference_id' => (string) $grantId,
            'result_snapshot_json' => is_string($resultJson) ? $resultJson : '{}',
            'granted_at' => $now,
            'updated_at' => $now,
            'id' => $grantId,
        ]);
        $pdo->prepare('UPDATE sr_quiz_attempts SET rewarded_at = :rewarded_at, updated_at = :updated_at WHERE id = :id')->execute([
            'rewarded_at' => $now,
            'updated_at' => $now,
            'id' => $attemptId,
        ]);
    } catch (Throwable $exception) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500)), $now);
    }

    $grantStmt->execute(['dedupe_key' => $dedupeKey]);
    $grant = $grantStmt->fetch();

    return is_array($grant) ? $grant : [];
}

function sr_quiz_refresh_reward_grant_for_retry(PDO $pdo, int $grantId, array $values): void
{
    if ($grantId < 1) {
        return;
    }

    $pdo->prepare(
        'UPDATE sr_quiz_reward_grants
         SET attempt_id = :attempt_id,
             reward_policy_id = :reward_policy_id,
             reward_provider = :reward_provider,
             reward_module = :reward_module,
             reward_code = :reward_code,
             reward_amount = :reward_amount,
             source_module = :source_module,
             source_type = :source_type,
             source_id = :source_id,
             dedupe_scope = :dedupe_scope,
             request_snapshot_json = :request_snapshot_json,
             status = \'pending\',
             error_message = NULL,
             failed_at = NULL,
             updated_at = :updated_at
         WHERE id = :id
           AND status <> \'granted\''
    )->execute([
        'attempt_id' => (int) ($values['attempt_id'] ?? 0),
        'reward_policy_id' => (int) ($values['reward_policy_id'] ?? 0),
        'reward_provider' => (string) ($values['reward_provider'] ?? ''),
        'reward_module' => (string) ($values['reward_module'] ?? ''),
        'reward_code' => (string) ($values['reward_code'] ?? ''),
        'reward_amount' => $values['reward_amount'] ?? null,
        'source_module' => $values['source_module'] ?? null,
        'source_type' => $values['source_type'] ?? null,
        'source_id' => $values['source_id'] ?? null,
        'dedupe_scope' => (string) ($values['dedupe_scope'] ?? 'per_quiz'),
        'request_snapshot_json' => (string) ($values['request_snapshot_json'] ?? '{}'),
        'updated_at' => (string) ($values['updated_at'] ?? sr_now()),
        'id' => $grantId,
    ]);
}

function sr_quiz_reward_grant_ledger_transaction(PDO $pdo, array $grant, array $assetOptions): ?array
{
    if ((string) ($grant['reward_provider'] ?? '') !== 'ledger_asset') {
        return null;
    }

    $moduleKey = (string) ($grant['reward_module'] ?? '');
    $lookupFunction = (string) ($assetOptions[$moduleKey]['transaction_lookup_function'] ?? '');
    if ($lookupFunction === '' || !function_exists($lookupFunction)) {
        return null;
    }

    $referenceType = (string) ($grant['provider_reference_type'] ?? '');
    $referenceId = (string) ($grant['provider_reference_id'] ?? '');
    if ($referenceType === '' || $referenceId === '') {
        $grantId = (int) ($grant['id'] ?? 0);
        if ($grantId < 1) {
            return null;
        }
        $referenceType = 'quiz_reward';
        $referenceId = (string) $grantId;
    }

    $row = $lookupFunction($pdo, $referenceType, $referenceId);

    return is_array($row) ? $row : null;
}

function sr_quiz_reward_grant_by_id(PDO $pdo, int $grantId): ?array
{
    if ($grantId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_quiz_reward_grants WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $grantId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_quiz_reward_grant_reclaim_status(PDO $pdo, array $grant, array $assetOptions): array
{
    $status = [
        'available' => false,
        'reason' => 'not_reclaimable',
        'module_key' => (string) ($grant['reward_module'] ?? ''),
        'transaction_id' => 0,
        'account_id' => (int) ($grant['account_id'] ?? 0),
        'amount' => 0,
        'remaining_amount' => 0,
        'transaction_type' => '',
        'reference_type' => '',
        'reference_id' => '',
    ];

    $transaction = sr_quiz_reward_grant_ledger_transaction($pdo, $grant, $assetOptions);
    if (!is_array($transaction)) {
        $status['reason'] = 'ledger_transaction_not_found';

        return $status;
    }

    $transactionId = (int) ($transaction['id'] ?? 0);
    $accountId = (int) ($transaction['account_id'] ?? 0);
    $amount = (int) ($transaction['amount'] ?? 0);
    $moduleKey = (string) ($grant['reward_module'] ?? '');
    $status['module_key'] = $moduleKey;
    $status['transaction_id'] = $transactionId;
    $status['account_id'] = $accountId;
    $status['amount'] = $amount;
    $status['transaction_type'] = (string) ($transaction['transaction_type'] ?? '');

    if ($transactionId < 1 || $accountId < 1 || $amount <= 0) {
        $status['reason'] = 'ledger_transaction_not_reclaimable';

        return $status;
    }

    if ($moduleKey !== 'reward') {
        $status['reason'] = 'unsupported_asset_reclaim';

        return $status;
    }

    if (!function_exists('sr_reward_reclaim_remaining_amounts_for_transactions') || !function_exists('sr_reward_reclaim_reference_id')) {
        $status['reason'] = 'reclaim_contract_missing';

        return $status;
    }

    $remainingAmounts = sr_reward_reclaim_remaining_amounts_for_transactions($pdo, [$transaction]);
    $remainingAmount = (int) ($remainingAmounts[$transactionId] ?? ($remainingAmounts[$accountId . ':' . $transactionId] ?? 0));
    $status['remaining_amount'] = max(0, $remainingAmount);
    $status['reference_type'] = 'reclaim';
    $status['reference_id'] = sr_reward_reclaim_reference_id($transactionId);

    if ($status['remaining_amount'] <= 0) {
        $status['reason'] = 'nothing_remaining';

        return $status;
    }

    $status['available'] = true;
    $status['reason'] = '';

    return $status;
}

function sr_quiz_reclaim_reward_grant(PDO $pdo, array $grant, array $assetOptions, int $amount, int $adminAccountId, string $reason = ''): array
{
    if ($amount <= 0) {
        return [
            'ok' => false,
            'errors' => ['회수 금액은 1 이상이어야 합니다.'],
            'transaction_id' => 0,
        ];
    }

    $status = sr_quiz_reward_grant_reclaim_status($pdo, $grant, $assetOptions);
    if (empty($status['available'])) {
        return [
            'ok' => false,
            'errors' => ['회수할 수 있는 퀴즈 보상 원장 거래를 찾을 수 없습니다.'],
            'transaction_id' => 0,
            'reclaim_status' => $status,
        ];
    }

    $remainingAmount = (int) ($status['remaining_amount'] ?? 0);
    if ($amount > $remainingAmount) {
        return [
            'ok' => false,
            'errors' => ['회수 금액이 남은 회수 가능액을 초과했습니다.'],
            'transaction_id' => 0,
            'reclaim_status' => $status,
        ];
    }

    if (!function_exists('sr_reward_validate_reclaim_transaction') || !function_exists('sr_reward_create_transaction')) {
        return [
            'ok' => false,
            'errors' => ['적립금 회수 계약을 찾을 수 없습니다.'],
            'transaction_id' => 0,
            'reclaim_status' => $status,
        ];
    }

    $accountId = (int) ($status['account_id'] ?? 0);
    $referenceType = (string) ($status['reference_type'] ?? '');
    $referenceId = (string) ($status['reference_id'] ?? '');
    $reclaimReason = sr_quiz_clean_text($reason, 255);
    if ($reclaimReason === '') {
        $reclaimReason = '퀴즈 보상 회수: grant #' . (string) (int) ($grant['id'] ?? 0);
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $validationError = sr_reward_validate_reclaim_transaction($pdo, $accountId, -$amount, $referenceType, $referenceId, true);
        if ($validationError !== null) {
            throw new RuntimeException($validationError);
        }

        $transactionId = sr_reward_create_transaction($pdo, [
            'account_id' => $accountId,
            'amount' => -$amount,
            'transaction_type' => 'reclaim',
            'reason' => $reclaimReason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by_account_id' => $adminAccountId > 0 ? $adminAccountId : null,
        ]);

        sr_audit_log($pdo, [
            'actor_account_id' => $adminAccountId,
            'actor_type' => 'admin',
            'event_type' => 'quiz.reward.reclaimed',
            'target_type' => 'quiz_reward_grant',
            'target_id' => (string) (int) ($grant['id'] ?? 0),
            'result' => 'success',
            'message' => 'Quiz reward grant reclaimed.',
            'metadata' => [
                'account_id' => $accountId,
                'amount' => $amount,
                'reward_transaction_id' => (int) ($status['transaction_id'] ?? 0),
                'reclaim_transaction_id' => $transactionId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ],
        ]);

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'ok' => true,
            'errors' => [],
            'transaction_id' => $transactionId,
            'reclaim_status' => $status,
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [
            'ok' => false,
            'errors' => [sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500))],
            'transaction_id' => 0,
            'reclaim_status' => $status,
        ];
    }
}

function sr_quiz_issue_coupon_reward_grant(PDO $pdo, array $quiz, int $grantId, int $attemptId, int $accountId, array $policy, string $now): array
{
    if (!sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '쿠폰 모듈이 활성화되어 있지 않습니다.', $now);
    }
    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_issue_to_account')) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '쿠폰 발급 함수를 찾을 수 없습니다.', $now);
    }

    $definitionId = (int) ($policy['reward_code'] ?? 0);
    if ($definitionId < 1) {
        $definitionId = (int) ($policy['reward_module'] ?? 0);
    }
    if ($definitionId < 1) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '쿠폰 정의 ID를 확인해야 합니다.', $now);
    }
    if (!sr_quiz_reward_coupon_definition_is_available($pdo, $definitionId)) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '사용 가능한 보상 쿠폰이 아닙니다.', $now);
    }

    try {
        $issueId = sr_coupon_issue_to_account($pdo, $definitionId, $accountId, '퀴즈 보상: ' . (string) ($quiz['title'] ?? ''), null, null);
        $resultJson = json_encode(['coupon_issue_id' => $issueId, 'coupon_definition_id' => $definitionId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pdo->prepare(
            'UPDATE sr_quiz_reward_grants
             SET status = \'granted\',
                 provider_reference_type = :provider_reference_type,
                 provider_reference_id = :provider_reference_id,
                 result_snapshot_json = :result_snapshot_json,
                 granted_at = :granted_at,
                 updated_at = :updated_at
             WHERE id = :id'
        )->execute([
            'provider_reference_type' => 'coupon_issue',
            'provider_reference_id' => (string) $issueId,
            'result_snapshot_json' => is_string($resultJson) ? $resultJson : '{}',
            'granted_at' => $now,
            'updated_at' => $now,
            'id' => $grantId,
        ]);
        $pdo->prepare('UPDATE sr_quiz_attempts SET rewarded_at = :rewarded_at, updated_at = :updated_at WHERE id = :id')->execute([
            'rewarded_at' => $now,
            'updated_at' => $now,
            'id' => $attemptId,
        ]);
    } catch (Throwable $exception) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500)), $now);
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_quiz_reward_grants WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $grantId]);
    $grant = $stmt->fetch();

    return is_array($grant) ? $grant : [];
}

function sr_quiz_mark_reward_grant_failed(PDO $pdo, int $grantId, string $message, string $now): array
{
    $pdo->prepare(
        'UPDATE sr_quiz_reward_grants
         SET status = \'failed\',
             error_message = :error_message,
             failed_at = :failed_at,
             updated_at = :updated_at
         WHERE id = :id'
    )->execute([
        'error_message' => $message,
        'failed_at' => $now,
        'updated_at' => $now,
        'id' => $grantId,
    ]);
    $stmt = $pdo->prepare('SELECT * FROM sr_quiz_reward_grants WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $grantId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : [];
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
    $where = ['q.deleted_at IS NULL'];
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
                q.member_group_keys_json, q.view_count, q.reward_enabled, q.updated_at,
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
                  q.member_group_keys_json, q.view_count, q.reward_enabled, q.updated_at, qa.attempt_count, qa.passed_count
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
        $keywordColumns = ['q.quiz_key', 'q.title', 'a.source_title_snapshot', 'CAST(a.id AS CHAR)', 'CAST(a.account_id AS CHAR)'];
        $keywordWhere = [];
        foreach ($keywordColumns as $index => $column) {
            $paramKey = 'attempt_q_' . (string) $index;
            $keywordWhere[] = $column . ' LIKE :' . $paramKey;
            $params[$paramKey] = '%' . $keyword . '%';
        }
        $where[] = '(' . implode(' OR ', $keywordWhere) . ')';
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

function sr_quiz_default_admin_values(?array $settings = null): array
{
    $settings = sr_quiz_normalize_settings(is_array($settings) ? $settings : []);
    $choiceCount = (int) ($settings['default_question_choice_count'] ?? 4);
    $defaultChoices = [];
    for ($index = 0; $index < $choiceCount; $index++) {
        $defaultChoices[] = [
            'choice_key' => '',
            'label' => '',
            'is_correct' => $index === 0 ? 1 : 0,
            'category_key' => '',
            'category_weight' => 0,
        ];
    }

    return [
        'id' => 0,
        'quiz_key' => '',
        'title' => '',
        'description' => '',
        'cover_image_url' => '',
        'skin_key' => '',
        'status' => (string) $settings['default_status'],
        'quiz_mode' => (string) $settings['default_quiz_mode'],
        'scoring_model' => (string) $settings['default_scoring_model'],
        'pass_score' => (int) $settings['default_pass_score'],
        'starts_at' => '',
        'ends_at' => '',
        'attempt_limit_policy' => (string) $settings['default_attempt_limit_policy'],
        'attempt_limit_period_seconds' => (string) $settings['default_attempt_limit_period_seconds'],
        'member_group_keys' => [],
        'comments_enabled' => 0,
        'secret_comments_enabled' => 0,
        'reaction_preset_key' => '',
        'reaction_comment_preset_key' => '',
        'reward_enabled' => !empty($settings['default_reward_enabled']) ? 1 : 0,
        'reward_provider' => (string) $settings['default_reward_provider'],
        'reward_module' => (string) $settings['default_reward_module'],
        'reward_coupon_definition_id' => (string) $settings['default_reward_coupon_definition_id'],
        'reward_amount' => (string) $settings['default_reward_amount'],
        'reward_dedupe_scope' => (string) $settings['default_reward_dedupe_scope'],
        'content_source_ids' => '',
        'community_source_ids' => '',
        'result_rules' => '',
        'questions' => [
            [
                'question_key' => 'q1',
                'question_type' => 'single_choice',
                'prompt' => '',
                'score_value' => (int) $settings['default_question_score'],
                'choices' => $defaultChoices,
            ],
        ],
    ];
}

function sr_quiz_admin_values_from_row(array $quiz): array
{
    $policy = is_array($quiz['reward_policy'] ?? null) ? $quiz['reward_policy'] : [];
    $contentSourceIds = [];
    $communitySourceIds = [];
    foreach ((array) ($quiz['sources'] ?? []) as $source) {
        if ((string) ($source['source_module'] ?? '') === 'content' && (string) ($source['source_type'] ?? '') === 'content_item') {
            $contentSourceIds[] = (string) (int) ($source['source_id'] ?? 0);
        } elseif ((string) ($source['source_module'] ?? '') === 'community' && (string) ($source['source_type'] ?? '') === 'community_post') {
            $communitySourceIds[] = (string) (int) ($source['source_id'] ?? 0);
        }
    }
    $resultRules = [];
    foreach ((array) ($quiz['result_rules'] ?? []) as $rule) {
        $resultRules[] = implode('|', [
            (string) ($rule['result_key'] ?? ''),
            (string) ($rule['title'] ?? ''),
            (string) ($rule['min_score'] ?? ''),
            (string) ($rule['max_score'] ?? ''),
            (string) ($rule['category_key'] ?? ''),
            (string) ($rule['threshold_value'] ?? ''),
            (string) ($rule['summary'] ?? ''),
        ]);
    }
    $questions = [];
    foreach ((array) ($quiz['questions'] ?? []) as $question) {
        $choices = [];
        foreach ((array) ($question['choices'] ?? []) as $choice) {
            $choices[] = [
                'id' => (int) ($choice['id'] ?? 0),
                'choice_key' => (string) ($choice['choice_key'] ?? ''),
                'label' => (string) ($choice['label'] ?? ''),
                'is_correct' => (int) ($choice['is_correct'] ?? 0),
                'category_key' => (string) ($choice['category_key'] ?? ''),
                'category_weight' => (int) ($choice['category_weight'] ?? 0),
            ];
        }
        $questions[] = [
            'id' => (int) ($question['id'] ?? 0),
            'question_key' => (string) ($question['question_key'] ?? ''),
            'question_type' => (string) ($question['question_type'] ?? 'single_choice'),
            'prompt' => (string) ($question['prompt'] ?? ''),
            'score_value' => (int) ($question['score_value'] ?? 0),
            'choices' => $choices,
        ];
    }

    return [
        'id' => (int) ($quiz['id'] ?? 0),
        'quiz_key' => (string) ($quiz['quiz_key'] ?? ''),
        'title' => (string) ($quiz['title'] ?? ''),
        'description' => (string) ($quiz['description'] ?? ''),
        'cover_image_url' => sr_quiz_clean_cover_image_url((string) ($quiz['cover_image_url'] ?? '')),
        'skin_key' => sr_quiz_clean_optional_skin_key((string) ($quiz['skin_key'] ?? '')),
        'status' => (string) ($quiz['status'] ?? 'draft'),
        'quiz_mode' => (string) ($quiz['quiz_mode'] ?? 'scored'),
        'scoring_model' => (string) ($quiz['scoring_model'] ?? 'correct_answer'),
        'pass_score' => (string) ($quiz['pass_score'] ?? ''),
        'starts_at' => sr_quiz_datetime_local_value($quiz['starts_at'] ?? ''),
        'ends_at' => sr_quiz_datetime_local_value($quiz['ends_at'] ?? ''),
        'attempt_limit_policy' => (string) ($quiz['attempt_limit_policy'] ?? 'unlimited'),
        'attempt_limit_period_seconds' => (string) ($quiz['attempt_limit_period_seconds'] ?? ''),
        'member_group_keys' => sr_quiz_member_group_keys_from_value($quiz['member_group_keys_json'] ?? ''),
        'comments_enabled' => (int) ($quiz['comments_enabled'] ?? 0),
        'secret_comments_enabled' => (int) ($quiz['secret_comments_enabled'] ?? 0),
        'reaction_preset_key' => function_exists('sr_reaction_setting_preset_key') && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? sr_reaction_setting_preset_key($GLOBALS['pdo'], $quiz['reaction_preset_key'] ?? '') : '',
        'reaction_comment_preset_key' => function_exists('sr_reaction_setting_preset_key') && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? sr_reaction_setting_preset_key($GLOBALS['pdo'], $quiz['reaction_comment_preset_key'] ?? '') : '',
        'reward_enabled' => (int) ($quiz['reward_enabled'] ?? 0),
        'reward_provider' => (string) ($policy['reward_provider'] ?? 'ledger_asset'),
        'reward_module' => (string) ($policy['reward_module'] ?? ''),
        'reward_coupon_definition_id' => (string) ((string) ($policy['reward_provider'] ?? '') === 'coupon' ? ($policy['reward_code'] ?? '') : ''),
        'reward_amount' => (string) ($policy['reward_amount'] ?? ''),
        'reward_dedupe_scope' => (string) ($policy['dedupe_scope'] ?? 'per_quiz'),
        'content_source_ids' => implode("\n", $contentSourceIds),
        'community_source_ids' => implode("\n", $communitySourceIds),
        'result_rules' => implode("\n", $resultRules),
        'questions' => $questions === [] ? sr_quiz_default_admin_values()['questions'] : $questions,
    ];
}

function sr_quiz_admin_values_from_post(): array
{
    $skinKey = sr_quiz_optional_option_key_from_post(sr_post_string('skin_key', 40), sr_quiz_skin_options());

    $resultRuleKeys = $_POST['result_rule_key'] ?? [];
    $resultRuleTitles = $_POST['result_rule_title'] ?? [];
    $resultRuleMinScores = $_POST['result_rule_min_score'] ?? [];
    $resultRuleMaxScores = $_POST['result_rule_max_score'] ?? [];
    $resultRuleCategoryKeys = $_POST['result_rule_category_key'] ?? [];
    $resultRuleThresholdValues = $_POST['result_rule_threshold_value'] ?? [];
    $resultRuleSummaries = $_POST['result_rule_summary'] ?? [];
    $resultRuleLines = [];

    if (is_array($resultRuleKeys)) {
        foreach ($resultRuleKeys as $index => $keyValue) {
            $resultKey = sr_quiz_clean_key((string) $keyValue, 64);
            $title = is_array($resultRuleTitles) && isset($resultRuleTitles[$index])
                ? sr_quiz_clean_single_line((string) $resultRuleTitles[$index], 190)
                : '';
            $minScore = is_array($resultRuleMinScores) && isset($resultRuleMinScores[$index]) ? trim((string) $resultRuleMinScores[$index]) : '';
            $maxScore = is_array($resultRuleMaxScores) && isset($resultRuleMaxScores[$index]) ? trim((string) $resultRuleMaxScores[$index]) : '';
            $categoryKey = is_array($resultRuleCategoryKeys) && isset($resultRuleCategoryKeys[$index])
                ? sr_quiz_clean_key((string) $resultRuleCategoryKeys[$index], 64)
                : '';
            $thresholdValue = is_array($resultRuleThresholdValues) && isset($resultRuleThresholdValues[$index]) ? trim((string) $resultRuleThresholdValues[$index]) : '';
            $summary = is_array($resultRuleSummaries) && isset($resultRuleSummaries[$index])
                ? sr_quiz_clean_text((string) $resultRuleSummaries[$index], 1000)
                : '';

            if ($resultKey === '' && $title === '' && $minScore === '' && $maxScore === '' && $categoryKey === '' && $thresholdValue === '' && $summary === '') {
                continue;
            }

            $resultRuleLines[] = implode('|', [
                $resultKey,
                $title,
                $minScore,
                $maxScore,
                $categoryKey,
                $thresholdValue,
                str_replace(["\r\n", "\r", "\n"], ' ', $summary),
            ]);
        }
    }

    $questionUids = $_POST['question_uid'] ?? [];
    $questionKeys = $_POST['question_key'] ?? [];
    $questionTypes = $_POST['question_type'] ?? [];
    $questionPrompts = $_POST['question_prompt'] ?? [];
    $questionScores = $_POST['question_score'] ?? [];
    $choiceKeys = $_POST['choice_key'] ?? [];
    $choiceLabels = $_POST['choice_label'] ?? [];
    $choiceCategoryKeys = $_POST['choice_category_key'] ?? [];
    $choiceCategoryWeights = $_POST['choice_category_weight'] ?? [];
    $correctChoices = $_POST['correct_choice'] ?? [];
    $questions = [];

    if (!is_array($questionUids)) {
        $questionUids = [];
    }
    foreach ($questionUids as $index => $uidValue) {
        $uid = is_string($uidValue) ? $uidValue : (string) $uidValue;
        $questionKey = is_array($questionKeys) && isset($questionKeys[$index]) ? sr_quiz_clean_key((string) $questionKeys[$index]) : '';
        $questionType = is_array($questionTypes) && isset($questionTypes[$index]) ? (string) $questionTypes[$index] : 'single_choice';
        if (!in_array($questionType, sr_quiz_question_types(), true)) {
            $questionType = 'single_choice';
        }
        $prompt = is_array($questionPrompts) && isset($questionPrompts[$index]) ? sr_quiz_clean_text((string) $questionPrompts[$index], 2000) : '';
        $score = is_array($questionScores) && isset($questionScores[$index]) ? (int) $questionScores[$index] : 1;
        $rowChoiceKeys = is_array($choiceKeys[$uid] ?? null) ? $choiceKeys[$uid] : [];
        $rowChoiceLabels = is_array($choiceLabels[$uid] ?? null) ? $choiceLabels[$uid] : [];
        $rowCategoryKeys = is_array($choiceCategoryKeys[$uid] ?? null) ? $choiceCategoryKeys[$uid] : [];
        $rowCategoryWeights = is_array($choiceCategoryWeights[$uid] ?? null) ? $choiceCategoryWeights[$uid] : [];
        $correctValues = is_array($correctChoices[$uid] ?? null) ? array_map('strval', $correctChoices[$uid]) : [is_array($correctChoices) ? (string) ($correctChoices[$uid] ?? '') : ''];
        $choices = [];
        foreach ($rowChoiceLabels as $choiceIndex => $choiceLabelValue) {
            $choiceLabel = sr_quiz_clean_single_line((string) $choiceLabelValue, 255);
            $choiceKey = isset($rowChoiceKeys[$choiceIndex]) ? sr_quiz_clean_key((string) $rowChoiceKeys[$choiceIndex]) : '';
            $categoryKey = isset($rowCategoryKeys[$choiceIndex]) ? sr_quiz_clean_key((string) $rowCategoryKeys[$choiceIndex]) : '';
            $categoryWeight = isset($rowCategoryWeights[$choiceIndex]) ? (int) $rowCategoryWeights[$choiceIndex] : 0;
            if ($choiceLabel === '' && $choiceKey === '') {
                continue;
            }
            if ($choiceLabel !== '' && $choiceKey === '') {
                $choiceKey = 'c' . (string) ((int) $choiceIndex + 1);
            }
            $choices[] = [
                'choice_key' => $choiceKey,
                'label' => $choiceLabel,
                'is_correct' => in_array((string) $choiceIndex, $correctValues, true) ? 1 : 0,
                'category_key' => $categoryKey,
                'category_weight' => $categoryWeight,
            ];
        }
        if ($prompt === '' && $questionKey === '' && $choices === []) {
            continue;
        }
        if ($prompt !== '' && $questionKey === '') {
            $questionKey = 'q' . (string) ((int) $index + 1);
        }
        $questions[] = [
            'question_key' => $questionKey,
            'question_type' => $questionType,
            'prompt' => $prompt,
            'score_value' => max(0, $score),
            'choices' => $choices,
        ];
    }

    $memberGroupKeys = $_POST['member_group_keys'] ?? [];
    if (!is_array($memberGroupKeys)) {
        $memberGroupKeys = [];
    }

    return [
        'id' => (int) sr_post_string('quiz_id', 20),
        'quiz_key' => sr_quiz_clean_key(sr_post_string('quiz_key', 64)),
        'title' => sr_quiz_clean_single_line(sr_post_string('title', 190), 190),
        'description' => sr_quiz_clean_text(sr_post_string('description', 2000), 2000),
        'cover_image_url' => sr_quiz_clean_cover_image_url(sr_post_string('cover_image_url', 255)),
        'skin_key' => $skinKey,
        'status' => sr_post_string('status', 20),
        'quiz_mode' => sr_post_string('quiz_mode', 30),
        'scoring_model' => sr_post_string('scoring_model', 40),
        'pass_score' => sr_post_string('pass_score', 20),
        'starts_at' => sr_post_string('starts_at', 30),
        'ends_at' => sr_post_string('ends_at', 30),
        'attempt_limit_policy' => sr_post_string('attempt_limit_policy', 30),
        'attempt_limit_period_seconds' => sr_post_string('attempt_limit_period_seconds', 20),
        'member_group_keys' => sr_quiz_member_group_keys_from_value($memberGroupKeys),
        'comments_enabled' => ($_POST['comments_enabled'] ?? '') === '1' ? 1 : 0,
        'secret_comments_enabled' => ($_POST['secret_comments_enabled'] ?? '') === '1' ? 1 : 0,
        'reaction_preset_key' => function_exists('sr_reaction_setting_preset_key') && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? sr_reaction_setting_preset_key($GLOBALS['pdo'], sr_post_string('reaction_preset_key', 80)) : '',
        'reaction_comment_preset_key' => function_exists('sr_reaction_setting_preset_key') && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? sr_reaction_setting_preset_key($GLOBALS['pdo'], sr_post_string('reaction_comment_preset_key', 80)) : '',
        'reward_enabled' => ($_POST['reward_enabled'] ?? '') === '1' ? 1 : 0,
        'reward_provider' => sr_quiz_clean_key(sr_post_string('reward_provider', 30), 30),
        'reward_module' => sr_quiz_clean_key(sr_post_string('reward_module', 40), 40),
        'reward_coupon_definition_id' => sr_post_string('reward_coupon_definition_id', 20),
        'reward_amount' => sr_post_string('reward_amount', 20),
        'reward_dedupe_scope' => sr_quiz_clean_key(sr_post_string('reward_dedupe_scope', 20), 20),
        'content_source_ids' => sr_quiz_clean_text(sr_post_string('content_source_ids', 1000), 1000),
        'community_source_ids' => sr_quiz_clean_text(sr_post_string('community_source_ids', 1000), 1000),
        'result_rules' => sr_quiz_clean_text($resultRuleLines === [] ? sr_post_string('result_rules', 4000) : implode("\n", $resultRuleLines), 4000),
        'questions' => $questions,
    ];
}

function sr_quiz_content_source_ids_from_value(string $value): array
{
    $ids = [];
    foreach (preg_split('/[\s,]+/', $value) ?: [] as $part) {
        $id = (int) trim((string) $part);
        if ($id > 0) {
            $ids[$id] = true;
        }
    }

    return array_keys($ids);
}

function sr_quiz_content_source_ids_exist(PDO $pdo, array $contentIds): array
{
    $contentIds = array_values(array_filter(array_map('intval', $contentIds), static fn (int $id): bool => $id > 0));
    if ($contentIds === []) {
        return [];
    }

    $placeholders = [];
    $params = [];
    foreach ($contentIds as $index => $contentId) {
        $placeholder = ':id_' . (string) $index;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $contentId;
    }
    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_content_items
         WHERE id IN (' . implode(', ', $placeholders) . ')
           AND status <> \'deleted\''
    );
    $stmt->execute($params);
    $found = [];
    foreach ($stmt->fetchAll() as $row) {
        $found[(int) ($row['id'] ?? 0)] = true;
    }

    return array_keys($found);
}

function sr_quiz_community_source_ids_exist(PDO $pdo, array $postIds): array
{
    $postIds = array_values(array_filter(array_map('intval', $postIds), static fn (int $id): bool => $id > 0));
    if ($postIds === []) {
        return [];
    }

    $placeholders = [];
    $params = [];
    foreach ($postIds as $index => $postId) {
        $placeholder = ':id_' . (string) $index;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $postId;
    }
    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_community_posts
         WHERE id IN (' . implode(', ', $placeholders) . ')
           AND status <> \'deleted\''
    );
    $stmt->execute($params);
    $found = [];
    foreach ($stmt->fetchAll() as $row) {
        $found[(int) ($row['id'] ?? 0)] = true;
    }

    return array_keys($found);
}

function sr_quiz_result_rules_from_value(string $value): array
{
    $rules = [];
    foreach (preg_split('/\r\n|\r|\n/', $value) ?: [] as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        $parts = array_pad(array_map('trim', explode('|', $line, 7)), 7, '');
        $resultKey = sr_quiz_clean_key($parts[0], 64);
        $title = sr_quiz_clean_single_line($parts[1], 190);
        if ($resultKey === '' && $title === '') {
            continue;
        }
        $rules[] = [
            'result_key' => $resultKey,
            'title' => $title,
            'min_score' => $parts[2] === '' ? null : (int) $parts[2],
            'max_score' => $parts[3] === '' ? null : (int) $parts[3],
            'category_key' => sr_quiz_clean_key($parts[4], 64),
            'threshold_value' => $parts[5] === '' ? null : (int) $parts[5],
            'summary' => sr_quiz_clean_text($parts[6], 1000),
        ];
    }

    return $rules;
}

function sr_quiz_asset_options(PDO $pdo): array
{
    require_once SR_ROOT . '/modules/member/helpers/assets.php';

    $options = [];
    foreach (sr_member_ledger_asset_definitions($pdo) as $moduleKey => $asset) {
        $transactionFunction = (string) ($asset['transaction_function'] ?? '');
        $lookupFunction = (string) ($asset['transaction_lookup_function'] ?? '');
        if (!function_exists($transactionFunction) || !function_exists($lookupFunction)) {
            continue;
        }
        $options[$moduleKey] = $asset;
    }

    return $options;
}

function sr_quiz_reward_coupon_definitions(PDO $pdo, int $limit = 200): array
{
    $limit = max(1, min(300, $limit));
    if (!sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return [];
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_tables_available') || !sr_coupon_tables_available($pdo)) {
        return [];
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'SELECT id, coupon_key, title, target_type, target_id, max_uses_per_issue, valid_from, valid_until
         FROM sr_coupon_definitions
         WHERE status = \'active\'
           AND (valid_from IS NULL OR valid_from <= :now_from)
           AND (valid_until IS NULL OR valid_until >= :now_until)
         ORDER BY title ASC, coupon_key ASC, id ASC
         LIMIT ' . $limit
    );
    $stmt->execute([
        'now_from' => $now,
        'now_until' => $now,
    ]);

    return $stmt->fetchAll();
}

function sr_quiz_reward_coupon_definition_is_available(PDO $pdo, int $definitionId): bool
{
    if ($definitionId < 1 || !sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return false;
    }

    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_tables_available') || !sr_coupon_tables_available($pdo)) {
        return false;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_coupon_definitions
         WHERE id = :id
           AND status = \'active\'
           AND (valid_from IS NULL OR valid_from <= :now_from)
           AND (valid_until IS NULL OR valid_until >= :now_until)
         LIMIT 1'
    );
    $stmt->execute([
        'id' => $definitionId,
        'now_from' => $now,
        'now_until' => $now,
    ]);

    return is_array($stmt->fetch());
}

function sr_quiz_admin_validation_errors(PDO $pdo, array $values, array $assetOptions): array
{
    $errors = [];
    $quizId = (int) ($values['id'] ?? 0);
    $quizKey = (string) ($values['quiz_key'] ?? '');
    if (!sr_quiz_key_is_valid($quizKey)) {
        $errors[] = '퀴즈 관리용 키는 영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 사용할 수 있습니다.';
    } elseif (sr_quiz_key_is_reserved($quizKey)) {
        $errors[] = '예약된 퀴즈 관리용 키는 사용할 수 없습니다.';
    } elseif (sr_quiz_key_exists($pdo, $quizKey, $quizId)) {
        $errors[] = '이미 사용 중인 퀴즈 관리용 키입니다.';
    }
    if ((string) ($values['title'] ?? '') === '') {
        $errors[] = '퀴즈 제목을 입력하세요.';
    }
    $skinKey = (string) ($values['skin_key'] ?? '');
    if ($skinKey !== '' && !isset(sr_quiz_skin_options()[$skinKey])) {
        $errors[] = '퀴즈 스킨 값이 올바르지 않습니다.';
    }
    if (!in_array((string) ($values['status'] ?? ''), sr_quiz_statuses(), true)) {
        $errors[] = '상태 값이 올바르지 않습니다.';
    }
    if (!in_array((string) ($values['quiz_mode'] ?? ''), sr_quiz_modes(), true)) {
        $errors[] = '퀴즈 모드 값이 올바르지 않습니다.';
    }
    if (!in_array((string) ($values['scoring_model'] ?? ''), sr_quiz_scoring_models(), true)) {
        $errors[] = '채점 모델 값이 올바르지 않습니다.';
    }
    if ((string) ($values['pass_score'] ?? '') !== '' && (int) $values['pass_score'] < 0) {
        $errors[] = '통과 점수는 0 이상이어야 합니다.';
    }
    $startsAtInput = (string) ($values['starts_at'] ?? '');
    $endsAtInput = (string) ($values['ends_at'] ?? '');
    $startsAt = sr_quiz_clean_admin_datetime($startsAtInput);
    $endsAt = sr_quiz_clean_admin_datetime($endsAtInput);
    if (trim($startsAtInput) !== '' && $startsAt === null) {
        $errors[] = '공개 시작일시 형식이 올바르지 않습니다.';
    }
    if (trim($endsAtInput) !== '' && $endsAt === null) {
        $errors[] = '공개 종료일시 형식이 올바르지 않습니다.';
    }
    if ($startsAt !== null && $endsAt !== null && $startsAt > $endsAt) {
        $errors[] = '공개 종료일시는 시작일시 이후여야 합니다.';
    }
    $attemptLimitPolicy = (string) ($values['attempt_limit_policy'] ?? 'unlimited');
    if (!in_array($attemptLimitPolicy, sr_quiz_attempt_limit_policies(), true)) {
        $errors[] = '응시 제한 정책 값이 올바르지 않습니다.';
    }
    if ($attemptLimitPolicy === 'per_period' && (int) ($values['attempt_limit_period_seconds'] ?? 0) < 1) {
        $errors[] = '기간당 1회 제한은 제한 기간을 1초 이상 입력해야 합니다.';
    }
    foreach (sr_quiz_member_group_keys_from_value($values['member_group_keys'] ?? []) as $groupKey) {
        if (!function_exists('sr_member_group_exists') || !sr_member_group_exists($pdo, $groupKey)) {
            $errors[] = '응시 가능 회원 그룹을 찾을 수 없습니다: ' . $groupKey;
        }
    }

    $questions = (array) ($values['questions'] ?? []);
    if ($questions === []) {
        $errors[] = '문제를 1개 이상 입력하세요.';
    }
    $questionKeys = [];
    foreach ($questions as $questionIndex => $question) {
        $number = $questionIndex + 1;
        $questionKey = (string) ($question['question_key'] ?? '');
        $questionType = (string) ($question['question_type'] ?? 'single_choice');
        if (!in_array($questionType, sr_quiz_question_types(), true)) {
            $errors[] = '문제 ' . (string) $number . '의 유형이 올바르지 않습니다.';
        }
        if (!sr_quiz_key_is_valid($questionKey)) {
            $errors[] = '문제 ' . (string) $number . '의 관리용 키가 올바르지 않습니다.';
        } elseif (isset($questionKeys[$questionKey])) {
            $errors[] = '문제 관리용 키가 중복되었습니다: ' . $questionKey;
        }
        $questionKeys[$questionKey] = true;
        if ((string) ($question['prompt'] ?? '') === '') {
            $errors[] = '문제 ' . (string) $number . '의 내용을 입력하세요.';
        }
        $choices = (array) ($question['choices'] ?? []);
        if (count($choices) < 2) {
            $errors[] = '문제 ' . (string) $number . '에는 선택지를 2개 이상 입력하세요.';
        }
        $choiceKeys = [];
        $correctCount = 0;
        foreach ($choices as $choiceIndex => $choice) {
            $choiceNumber = $choiceIndex + 1;
            $choiceKey = (string) ($choice['choice_key'] ?? '');
            if (!sr_quiz_key_is_valid($choiceKey)) {
                $errors[] = '문제 ' . (string) $number . ' 선택지 ' . (string) $choiceNumber . '의 관리용 키가 올바르지 않습니다.';
            } elseif (isset($choiceKeys[$choiceKey])) {
                $errors[] = '문제 ' . (string) $number . '의 선택지 관리용 키가 중복되었습니다: ' . $choiceKey;
            }
            $choiceKeys[$choiceKey] = true;
            if ((string) ($choice['label'] ?? '') === '') {
                $errors[] = '문제 ' . (string) $number . ' 선택지 ' . (string) $choiceNumber . '의 내용을 입력하세요.';
            }
            if ((int) ($choice['is_correct'] ?? 0) === 1) {
                $correctCount++;
            }
        }
        if ($questionType === 'single_choice' && $correctCount !== 1) {
            $errors[] = '문제 ' . (string) $number . '는 정답 선택지를 정확히 1개 지정해야 합니다.';
        } elseif ($questionType === 'multiple_choice' && $correctCount < 1) {
            $errors[] = '문제 ' . (string) $number . '는 정답 선택지를 1개 이상 지정해야 합니다.';
        }
    }

    $resultRuleKeys = [];
    foreach (sr_quiz_result_rules_from_value((string) ($values['result_rules'] ?? '')) as $ruleIndex => $rule) {
        $number = $ruleIndex + 1;
        $resultKey = (string) ($rule['result_key'] ?? '');
        if (!sr_quiz_key_is_valid($resultKey)) {
            $errors[] = '결과 규칙 ' . (string) $number . '의 관리용 키가 올바르지 않습니다.';
        } elseif (isset($resultRuleKeys[$resultKey])) {
            $errors[] = '결과 규칙 관리용 키가 중복되었습니다: ' . $resultKey;
        }
        $resultRuleKeys[$resultKey] = true;
        if ((string) ($rule['title'] ?? '') === '') {
            $errors[] = '결과 규칙 ' . (string) $number . '의 제목을 입력하세요.';
        }
        if (($rule['min_score'] ?? null) !== null && ($rule['max_score'] ?? null) !== null && (int) $rule['min_score'] > (int) $rule['max_score']) {
            $errors[] = '결과 규칙 ' . (string) $number . '의 최대 점수는 최소 점수 이상이어야 합니다.';
        }
    }

    if ((int) ($values['reward_enabled'] ?? 0) === 1) {
        $rewardProvider = (string) ($values['reward_provider'] ?? 'ledger_asset');
        if (!in_array($rewardProvider, sr_quiz_reward_providers(), true)) {
            $errors[] = '보상 공급자 값이 올바르지 않습니다.';
        }
        if (!in_array((string) ($values['reward_dedupe_scope'] ?? 'per_quiz'), sr_quiz_reward_dedupe_scopes(), true)) {
            $errors[] = '보상 중복 제한 값이 올바르지 않습니다.';
        }
        if ($rewardProvider === 'ledger_asset') {
            $rewardModule = (string) ($values['reward_module'] ?? '');
            $rewardAmount = (int) ($values['reward_amount'] ?? 0);
            if ($rewardModule === '' || !isset($assetOptions[$rewardModule])) {
                $errors[] = '보상 자산을 선택하세요.';
            }
            if ($rewardAmount <= 0) {
                $errors[] = '보상 금액은 1 이상이어야 합니다.';
            }
        } elseif ($rewardProvider === 'coupon') {
            $definitionId = (int) ($values['reward_coupon_definition_id'] ?? 0);
            if (!sr_quiz_reward_coupon_definition_is_available($pdo, $definitionId)) {
                $errors[] = '보상으로 사용할 수 있는 쿠폰을 선택하세요.';
            }
        }
    }

    $contentSourceIds = sr_quiz_content_source_ids_from_value((string) ($values['content_source_ids'] ?? ''));
    if ($contentSourceIds !== []) {
        $foundIds = array_fill_keys(sr_quiz_content_source_ids_exist($pdo, $contentSourceIds), true);
        foreach ($contentSourceIds as $contentSourceId) {
            if (!isset($foundIds[$contentSourceId])) {
                $errors[] = '연결 콘텐츠 ID를 찾을 수 없습니다: ' . (string) $contentSourceId;
            }
        }
    }
    $communitySourceIds = sr_quiz_content_source_ids_from_value((string) ($values['community_source_ids'] ?? ''));
    if ($communitySourceIds !== []) {
        if (!sr_module_enabled($pdo, 'community')) {
            $errors[] = '커뮤니티 모듈이 활성화되어 있지 않습니다.';
        } else {
            $foundIds = array_fill_keys(sr_quiz_community_source_ids_exist($pdo, $communitySourceIds), true);
            foreach ($communitySourceIds as $communitySourceId) {
                if (!isset($foundIds[$communitySourceId])) {
                    $errors[] = '연결 커뮤니티 게시글 ID를 찾을 수 없습니다: ' . (string) $communitySourceId;
                }
            }
        }
    }
    foreach (sr_quiz_result_rules_from_value((string) ($values['result_rules'] ?? '')) as $index => $rule) {
        $number = $index + 1;
        if (!sr_quiz_key_is_valid((string) ($rule['result_key'] ?? ''))) {
            $errors[] = '결과 규칙 ' . (string) $number . '의 결과 관리용 키가 올바르지 않습니다.';
        }
        if ((string) ($rule['title'] ?? '') === '') {
            $errors[] = '결과 규칙 ' . (string) $number . '의 제목을 입력하세요.';
        }
    }

    return array_values(array_unique($errors));
}

function sr_quiz_copy_options_from_post(): array
{
    return [
        'source_quiz_id' => (int) sr_post_string('source_quiz_id', 20),
        'quiz_key' => sr_quiz_clean_key(sr_post_string('copy_quiz_key', 64)),
        'title' => sr_quiz_clean_single_line(sr_post_string('copy_title', 190), 190),
        'copy_dates' => ($_POST['copy_dates'] ?? '') === '1',
        'copy_member_groups' => ($_POST['copy_member_groups'] ?? '') === '1',
        'copy_reward_policy' => ($_POST['copy_reward_policy'] ?? '') === '1',
        'copy_sources' => ($_POST['copy_sources'] ?? '') === '1',
        'copy_status' => ($_POST['copy_status'] ?? '') === '1',
    ];
}

function sr_quiz_copy_validation_errors(PDO $pdo, array $options): array
{
    $errors = [];
    $sourceQuizId = (int) ($options['source_quiz_id'] ?? 0);
    if ($sourceQuizId < 1) {
        $errors[] = '복사할 원본 퀴즈를 선택하세요.';
    }
    $quizKey = (string) ($options['quiz_key'] ?? '');
    if (!sr_quiz_key_is_valid($quizKey)) {
        $errors[] = '새 퀴즈 관리용 키는 영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 사용할 수 있습니다.';
    } elseif (sr_quiz_key_exists($pdo, $quizKey, 0)) {
        $errors[] = '이미 사용 중인 퀴즈 관리용 키입니다.';
    }
    if ((string) ($options['title'] ?? '') === '') {
        $errors[] = '새 퀴즈 제목을 입력하세요.';
    }

    return $errors;
}

function sr_quiz_copy_admin_quiz(PDO $pdo, int $sourceQuizId, array $options, int $accountId): array
{
    $warnings = [];
    $skippedSources = [];
    $skippedMemberGroupKeys = [];
    $skippedRewardPolicy = '';
    $newQuizId = 0;
    $newQuizKey = (string) ($options['quiz_key'] ?? '');
    $now = sr_now();

    $pdo->beginTransaction();
    try {
        $sourceStmt = $pdo->prepare(
            'SELECT *
             FROM sr_quiz_sets
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1
             FOR UPDATE'
        );
        $sourceStmt->execute(['id' => $sourceQuizId]);
        $sourceQuiz = $sourceStmt->fetch();
        if (!is_array($sourceQuiz)) {
            throw new RuntimeException('Source quiz was not found.');
        }
        if (!sr_quiz_key_is_valid($newQuizKey) || sr_quiz_key_exists($pdo, $newQuizKey, 0)) {
            throw new RuntimeException('New quiz key is invalid or duplicated.');
        }

        $memberGroupKeys = [];
        if (!empty($options['copy_member_groups'])) {
            foreach (sr_quiz_member_group_keys_from_value($sourceQuiz['member_group_keys_json'] ?? '') as $groupKey) {
                if (function_exists('sr_member_group_exists') && sr_member_group_exists($pdo, $groupKey)) {
                    $memberGroupKeys[] = $groupKey;
                } else {
                    $skippedMemberGroupKeys[] = $groupKey;
                }
            }
            if ($skippedMemberGroupKeys !== []) {
                $warnings[] = '존재하지 않는 회원 그룹 관리용 키는 복사하지 않았습니다.';
            }
        }

        $rewardPolicy = null;
        if (!empty($options['copy_reward_policy']) && (int) ($sourceQuiz['reward_enabled'] ?? 0) === 1) {
            $policyStmt = $pdo->prepare(
                'SELECT *
                 FROM sr_quiz_reward_policies
                 WHERE quiz_id = :quiz_id
                   AND status = \'active\'
                 ORDER BY sort_order ASC, id ASC
                 LIMIT 1'
            );
            $policyStmt->execute(['quiz_id' => $sourceQuizId]);
            $policy = $policyStmt->fetch();
            if (is_array($policy)) {
                $provider = (string) ($policy['reward_provider'] ?? '');
                if ($provider === 'coupon') {
                    if (sr_quiz_reward_coupon_definition_is_available($pdo, (int) ($policy['reward_code'] ?? 0))) {
                        $rewardPolicy = $policy;
                    } else {
                        $skippedRewardPolicy = 'coupon_unavailable';
                    }
                } elseif ($provider === 'ledger_asset') {
                    $assetOptions = sr_quiz_asset_options($pdo);
                    $moduleKey = (string) ($policy['reward_module'] ?? '');
                    if (isset($assetOptions[$moduleKey]) && (int) ($policy['reward_amount'] ?? 0) > 0) {
                        $rewardPolicy = $policy;
                    } else {
                        $skippedRewardPolicy = 'asset_unavailable';
                    }
                } else {
                    $skippedRewardPolicy = 'provider_unsupported';
                }
            } else {
                $skippedRewardPolicy = 'active_policy_missing';
            }
            if ($skippedRewardPolicy !== '') {
                $warnings[] = '보상 정책은 현재 사용할 수 없어 복사하지 않았습니다.';
            }
        }

        $insertQuiz = $pdo->prepare(
            'INSERT INTO sr_quiz_sets
                (quiz_key, title, description, cover_image_url, skin_key, status, quiz_mode, scoring_model, pass_score, starts_at, ends_at,
                 attempt_limit_policy, attempt_limit_period_seconds, member_group_keys_json, comments_enabled, secret_comments_enabled, reward_enabled,
                 created_by_account_id, updated_by_account_id, created_at, updated_at)
             VALUES
                (:quiz_key, :title, :description, :cover_image_url, :skin_key, :status, :quiz_mode, :scoring_model, :pass_score, :starts_at, :ends_at,
                 :attempt_limit_policy, :attempt_limit_period_seconds, :member_group_keys_json, :comments_enabled, :secret_comments_enabled, :reward_enabled,
                 :created_by_account_id, :updated_by_account_id, :created_at, :updated_at)'
        );
        $memberGroupKeysJson = json_encode(array_values($memberGroupKeys), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $insertQuiz->execute([
            'quiz_key' => $newQuizKey,
            'title' => (string) ($options['title'] ?? ''),
            'description' => (string) ($sourceQuiz['description'] ?? ''),
            'cover_image_url' => sr_quiz_clean_cover_image_url((string) ($sourceQuiz['cover_image_url'] ?? '')),
            'skin_key' => sr_quiz_clean_optional_skin_key((string) ($sourceQuiz['skin_key'] ?? '')),
            'status' => !empty($options['copy_status']) ? (string) ($sourceQuiz['status'] ?? 'draft') : 'draft',
            'quiz_mode' => (string) ($sourceQuiz['quiz_mode'] ?? 'scored'),
            'scoring_model' => (string) ($sourceQuiz['scoring_model'] ?? 'correct_answer'),
            'pass_score' => $sourceQuiz['pass_score'] ?? null,
            'starts_at' => !empty($options['copy_dates']) ? ($sourceQuiz['starts_at'] ?? null) : null,
            'ends_at' => !empty($options['copy_dates']) ? ($sourceQuiz['ends_at'] ?? null) : null,
            'attempt_limit_policy' => (string) ($sourceQuiz['attempt_limit_policy'] ?? 'unlimited'),
            'attempt_limit_period_seconds' => $sourceQuiz['attempt_limit_period_seconds'] ?? null,
            'member_group_keys_json' => is_string($memberGroupKeysJson) ? $memberGroupKeysJson : '[]',
            'comments_enabled' => (int) ($sourceQuiz['comments_enabled'] ?? 0),
            'secret_comments_enabled' => (int) ($sourceQuiz['secret_comments_enabled'] ?? 0),
            'reward_enabled' => is_array($rewardPolicy) ? 1 : 0,
            'created_by_account_id' => $accountId,
            'updated_by_account_id' => $accountId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $newQuizId = (int) $pdo->lastInsertId();

        $questionIdMap = [];
        $questionStmt = $pdo->prepare(
            'SELECT *
             FROM sr_quiz_questions
             WHERE quiz_id = :quiz_id
             ORDER BY sort_order ASC, id ASC'
        );
        $questionStmt->execute(['quiz_id' => $sourceQuizId]);
        $insertQuestion = $pdo->prepare(
            'INSERT INTO sr_quiz_questions
                (quiz_id, question_key, question_type, prompt, help_text, required, score_value, sort_order, settings_json, created_at, updated_at)
             VALUES
                (:quiz_id, :question_key, :question_type, :prompt, :help_text, :required, :score_value, :sort_order, :settings_json, :created_at, :updated_at)'
        );
        $insertChoice = $pdo->prepare(
            'INSERT INTO sr_quiz_choices
                (question_id, choice_key, label, description, is_correct, score_value, category_key, category_weight, sort_order, settings_json, created_at, updated_at)
             VALUES
                (:question_id, :choice_key, :label, :description, :is_correct, :score_value, :category_key, :category_weight, :sort_order, :settings_json, :created_at, :updated_at)'
        );
        $choiceStmt = $pdo->prepare(
            'SELECT *
             FROM sr_quiz_choices
             WHERE question_id = :question_id
             ORDER BY sort_order ASC, id ASC'
        );
        foreach ($questionStmt->fetchAll() as $question) {
            $insertQuestion->execute([
                'quiz_id' => $newQuizId,
                'question_key' => (string) ($question['question_key'] ?? ''),
                'question_type' => (string) ($question['question_type'] ?? 'single_choice'),
                'prompt' => (string) ($question['prompt'] ?? ''),
                'help_text' => $question['help_text'] ?? null,
                'required' => (int) ($question['required'] ?? 1),
                'score_value' => (int) ($question['score_value'] ?? 0),
                'sort_order' => (int) ($question['sort_order'] ?? 0),
                'settings_json' => $question['settings_json'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $newQuestionId = (int) $pdo->lastInsertId();
            $questionIdMap[(int) ($question['id'] ?? 0)] = $newQuestionId;
            $choiceStmt->execute(['question_id' => (int) ($question['id'] ?? 0)]);
            foreach ($choiceStmt->fetchAll() as $choice) {
                $insertChoice->execute([
                    'question_id' => $newQuestionId,
                    'choice_key' => (string) ($choice['choice_key'] ?? ''),
                    'label' => (string) ($choice['label'] ?? ''),
                    'description' => $choice['description'] ?? null,
                    'is_correct' => (int) ($choice['is_correct'] ?? 0),
                    'score_value' => (int) ($choice['score_value'] ?? 0),
                    'category_key' => (string) ($choice['category_key'] ?? '') !== '' ? (string) $choice['category_key'] : null,
                    'category_weight' => (int) ($choice['category_weight'] ?? 0),
                    'sort_order' => (int) ($choice['sort_order'] ?? 0),
                    'settings_json' => $choice['settings_json'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $resultIdMap = [];
        $resultStmt = $pdo->prepare(
            'SELECT *
             FROM sr_quiz_results
             WHERE quiz_id = :quiz_id
             ORDER BY sort_order ASC, id ASC'
        );
        $resultStmt->execute(['quiz_id' => $sourceQuizId]);
        $insertResult = $pdo->prepare(
            'INSERT INTO sr_quiz_results
                (quiz_id, result_key, title, summary, body, status, sort_order, created_at, updated_at)
             VALUES
                (:quiz_id, :result_key, :title, :summary, :body, :status, :sort_order, :created_at, :updated_at)'
        );
        foreach ($resultStmt->fetchAll() as $result) {
            $insertResult->execute([
                'quiz_id' => $newQuizId,
                'result_key' => (string) ($result['result_key'] ?? ''),
                'title' => (string) ($result['title'] ?? ''),
                'summary' => $result['summary'] ?? null,
                'body' => $result['body'] ?? null,
                'status' => (string) ($result['status'] ?? 'active'),
                'sort_order' => (int) ($result['sort_order'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $resultIdMap[(int) ($result['id'] ?? 0)] = (int) $pdo->lastInsertId();
        }

        $ruleStmt = $pdo->prepare(
            'SELECT *
             FROM sr_quiz_result_rules
             WHERE quiz_id = :quiz_id
             ORDER BY priority DESC, id ASC'
        );
        $ruleStmt->execute(['quiz_id' => $sourceQuizId]);
        $insertRule = $pdo->prepare(
            'INSERT INTO sr_quiz_result_rules
                (quiz_id, result_id, rule_type, min_score, max_score, category_key, threshold_value, priority, settings_json, created_at, updated_at)
             VALUES
                (:quiz_id, :result_id, :rule_type, :min_score, :max_score, :category_key, :threshold_value, :priority, :settings_json, :created_at, :updated_at)'
        );
        foreach ($ruleStmt->fetchAll() as $rule) {
            $oldResultId = (int) ($rule['result_id'] ?? 0);
            if (!isset($resultIdMap[$oldResultId])) {
                continue;
            }
            $insertRule->execute([
                'quiz_id' => $newQuizId,
                'result_id' => $resultIdMap[$oldResultId],
                'rule_type' => (string) ($rule['rule_type'] ?? 'score_range'),
                'min_score' => $rule['min_score'] ?? null,
                'max_score' => $rule['max_score'] ?? null,
                'category_key' => (string) ($rule['category_key'] ?? '') !== '' ? (string) $rule['category_key'] : null,
                'threshold_value' => $rule['threshold_value'] ?? null,
                'priority' => (int) ($rule['priority'] ?? 0),
                'settings_json' => $rule['settings_json'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (is_array($rewardPolicy)) {
            $insertPolicy = $pdo->prepare(
                'INSERT INTO sr_quiz_reward_policies
                    (quiz_id, status, trigger_type, result_id, reward_provider, reward_module, reward_code, reward_amount, dedupe_scope, sort_order, settings_json, created_at, updated_at)
                 VALUES
                    (:quiz_id, :status, :trigger_type, NULL, :reward_provider, :reward_module, :reward_code, :reward_amount, :dedupe_scope, :sort_order, :settings_json, :created_at, :updated_at)'
            );
            $insertPolicy->execute([
                'quiz_id' => $newQuizId,
                'status' => (string) ($rewardPolicy['status'] ?? 'active'),
                'trigger_type' => (string) ($rewardPolicy['trigger_type'] ?? 'passed'),
                'reward_provider' => (string) ($rewardPolicy['reward_provider'] ?? 'ledger_asset'),
                'reward_module' => (string) ($rewardPolicy['reward_module'] ?? ''),
                'reward_code' => (string) ($rewardPolicy['reward_code'] ?? ''),
                'reward_amount' => $rewardPolicy['reward_amount'] ?? null,
                'dedupe_scope' => (string) ($rewardPolicy['dedupe_scope'] ?? 'per_quiz'),
                'sort_order' => (int) ($rewardPolicy['sort_order'] ?? 0),
                'settings_json' => $rewardPolicy['settings_json'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!empty($options['copy_sources'])) {
            $sourceStmt = $pdo->prepare(
                'SELECT *
                 FROM sr_quiz_sources
                 WHERE quiz_id = :quiz_id
                   AND status = \'active\'
                 ORDER BY sort_order ASC, id ASC'
            );
            $sourceStmt->execute(['quiz_id' => $sourceQuizId]);
            $insertSource = $pdo->prepare(
                'INSERT INTO sr_quiz_sources
                    (quiz_id, source_module, source_type, source_id, status, sort_order, cta_label, created_at, updated_at)
                 VALUES
                    (:quiz_id, :source_module, :source_type, :source_id, \'active\', :sort_order, :cta_label, :created_at, :updated_at)'
            );
            foreach ($sourceStmt->fetchAll() as $source) {
                $sourceModule = (string) ($source['source_module'] ?? '');
                $sourceType = (string) ($source['source_type'] ?? '');
                $sourceId = (int) ($source['source_id'] ?? 0);
                $valid = false;
                if ($sourceModule === 'content' && $sourceType === 'content_item' && sr_module_enabled($pdo, 'content')) {
                    $valid = in_array($sourceId, sr_quiz_content_source_ids_exist($pdo, [$sourceId]), true);
                } elseif ($sourceModule === 'community' && $sourceType === 'community_post' && sr_module_enabled($pdo, 'community')) {
                    $valid = in_array($sourceId, sr_quiz_community_source_ids_exist($pdo, [$sourceId]), true);
                }
                if (!$valid) {
                    $skippedSources[] = [
                        'source_module' => $sourceModule,
                        'source_type' => $sourceType,
                        'source_id' => $sourceId,
                    ];
                    continue;
                }
                $insertSource->execute([
                    'quiz_id' => $newQuizId,
                    'source_module' => $sourceModule,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'sort_order' => (int) ($source['sort_order'] ?? 0),
                    'cta_label' => (string) ($source['cta_label'] ?? '') !== '' ? (string) $source['cta_label'] : '퀴즈 풀기',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            if ($skippedSources !== []) {
                $warnings[] = '삭제되었거나 사용할 수 없는 연결 대상은 복사하지 않았습니다.';
            }
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return [
        'quiz_id' => $newQuizId,
        'quiz_key' => $newQuizKey,
        'warnings' => $warnings,
        'skipped_sources' => $skippedSources,
        'skipped_reward_policy' => $skippedRewardPolicy,
        'skipped_member_group_keys' => $skippedMemberGroupKeys,
    ];
}

function sr_quiz_save_admin_quiz(PDO $pdo, array $values, int $accountId): int
{
    $quizId = (int) ($values['id'] ?? 0);
    $now = sr_now();
    $passScore = (string) ($values['pass_score'] ?? '') === '' ? null : (int) $values['pass_score'];
    $quizMode = in_array((string) ($values['quiz_mode'] ?? 'scored'), sr_quiz_modes(), true) ? (string) $values['quiz_mode'] : 'scored';
    $scoringModel = in_array((string) ($values['scoring_model'] ?? 'correct_answer'), sr_quiz_scoring_models(), true) ? (string) $values['scoring_model'] : 'correct_answer';
    $startsAt = sr_quiz_clean_admin_datetime((string) ($values['starts_at'] ?? ''));
    $endsAt = sr_quiz_clean_admin_datetime((string) ($values['ends_at'] ?? ''));
    $attemptLimitPolicy = (string) ($values['attempt_limit_policy'] ?? 'unlimited');
    if (!in_array($attemptLimitPolicy, sr_quiz_attempt_limit_policies(), true)) {
        $attemptLimitPolicy = 'unlimited';
    }
    $attemptLimitPeriodSeconds = $attemptLimitPolicy === 'per_period'
        ? max(1, (int) ($values['attempt_limit_period_seconds'] ?? 0))
        : null;
    $memberGroupKeysJson = json_encode(sr_quiz_member_group_keys_from_value($values['member_group_keys'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $commentsEnabled = !empty($values['comments_enabled']) ? 1 : 0;
    $secretCommentsEnabled = !empty($values['secret_comments_enabled']) ? 1 : 0;
    $skinKey = sr_quiz_clean_optional_skin_key((string) ($values['skin_key'] ?? ''));
    $settings = sr_quiz_settings($pdo);
    $defaultCtaLabel = (string) ($settings['default_cta_label'] ?? '퀴즈 풀기');

    $pdo->beginTransaction();
    try {
        if ($quizId > 0) {
            $existingStmt = $pdo->prepare(
                'SELECT id
                 FROM sr_quiz_sets
                 WHERE id = :id
                   AND deleted_at IS NULL
                 LIMIT 1
                 FOR UPDATE'
            );
            $existingStmt->execute(['id' => $quizId]);
            if (!is_array($existingStmt->fetch())) {
                throw new RuntimeException('Quiz to update was not found.');
            }

            $stmt = $pdo->prepare(
                'UPDATE sr_quiz_sets
                 SET quiz_key = :quiz_key,
                     title = :title,
                     description = :description,
                     cover_image_url = :cover_image_url,
                     skin_key = :skin_key,
                     status = :status,
                     quiz_mode = :quiz_mode,
                     scoring_model = :scoring_model,
                     pass_score = :pass_score,
                     starts_at = :starts_at,
                     ends_at = :ends_at,
                     attempt_limit_policy = :attempt_limit_policy,
                     attempt_limit_period_seconds = :attempt_limit_period_seconds,
                     member_group_keys_json = :member_group_keys_json,
                     comments_enabled = :comments_enabled,
                     secret_comments_enabled = :secret_comments_enabled,
                     reaction_preset_key = :reaction_preset_key,
                     reaction_comment_preset_key = :reaction_comment_preset_key,
                     reward_enabled = :reward_enabled,
                     updated_by_account_id = :updated_by_account_id,
                     updated_at = :updated_at
                 WHERE id = :id
                   AND deleted_at IS NULL'
            );
            $stmt->execute([
                'quiz_key' => (string) $values['quiz_key'],
                'title' => (string) $values['title'],
                'description' => (string) $values['description'],
                'cover_image_url' => sr_quiz_clean_cover_image_url((string) ($values['cover_image_url'] ?? '')),
                'skin_key' => $skinKey,
                'status' => (string) $values['status'],
                'quiz_mode' => $quizMode,
                'scoring_model' => $scoringModel,
                'pass_score' => $passScore,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'attempt_limit_policy' => $attemptLimitPolicy,
                'attempt_limit_period_seconds' => $attemptLimitPeriodSeconds,
                'member_group_keys_json' => is_string($memberGroupKeysJson) ? $memberGroupKeysJson : '[]',
                'comments_enabled' => $commentsEnabled,
                'secret_comments_enabled' => $secretCommentsEnabled,
                'reaction_preset_key' => function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $values['reaction_preset_key'] ?? '') : '',
                'reaction_comment_preset_key' => function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $values['reaction_comment_preset_key'] ?? '') : '',
                'reward_enabled' => (int) $values['reward_enabled'],
                'updated_by_account_id' => $accountId,
                'updated_at' => $now,
                'id' => $quizId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO sr_quiz_sets
                    (quiz_key, title, description, cover_image_url, skin_key, status, quiz_mode, scoring_model, pass_score, starts_at, ends_at,
                     attempt_limit_policy, attempt_limit_period_seconds, member_group_keys_json, comments_enabled, secret_comments_enabled, reaction_preset_key, reaction_comment_preset_key, reward_enabled,
                     created_by_account_id, updated_by_account_id, created_at, updated_at)
                 VALUES
                    (:quiz_key, :title, :description, :cover_image_url, :skin_key, :status, :quiz_mode, :scoring_model, :pass_score, :starts_at, :ends_at,
                     :attempt_limit_policy, :attempt_limit_period_seconds, :member_group_keys_json, :comments_enabled, :secret_comments_enabled, :reaction_preset_key, :reaction_comment_preset_key, :reward_enabled,
                     :created_by_account_id, :updated_by_account_id, :created_at, :updated_at)'
            );
            $stmt->execute([
                'quiz_key' => (string) $values['quiz_key'],
                'title' => (string) $values['title'],
                'description' => (string) $values['description'],
                'cover_image_url' => sr_quiz_clean_cover_image_url((string) ($values['cover_image_url'] ?? '')),
                'skin_key' => $skinKey,
                'status' => (string) $values['status'],
                'quiz_mode' => $quizMode,
                'scoring_model' => $scoringModel,
                'pass_score' => $passScore,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'attempt_limit_policy' => $attemptLimitPolicy,
                'attempt_limit_period_seconds' => $attemptLimitPeriodSeconds,
                'member_group_keys_json' => is_string($memberGroupKeysJson) ? $memberGroupKeysJson : '[]',
                'comments_enabled' => $commentsEnabled,
                'secret_comments_enabled' => $secretCommentsEnabled,
                'reaction_preset_key' => function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $values['reaction_preset_key'] ?? '') : '',
                'reaction_comment_preset_key' => function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $values['reaction_comment_preset_key'] ?? '') : '',
                'reward_enabled' => (int) $values['reward_enabled'],
                'created_by_account_id' => $accountId,
                'updated_by_account_id' => $accountId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $quizId = (int) $pdo->lastInsertId();
        }

        $pdo->prepare('DELETE FROM sr_quiz_reward_policies WHERE quiz_id = :quiz_id')->execute(['quiz_id' => $quizId]);
        $pdo->prepare('DELETE FROM sr_quiz_sources WHERE quiz_id = :quiz_id')->execute(['quiz_id' => $quizId]);
        $pdo->prepare('DELETE FROM sr_quiz_result_rules WHERE quiz_id = :quiz_id')->execute(['quiz_id' => $quizId]);
        $pdo->prepare('DELETE FROM sr_quiz_results WHERE quiz_id = :quiz_id')->execute(['quiz_id' => $quizId]);
        $questionIds = $pdo->prepare('SELECT id FROM sr_quiz_questions WHERE quiz_id = :quiz_id');
        $questionIds->execute(['quiz_id' => $quizId]);
        foreach ($questionIds->fetchAll() as $questionRow) {
            $pdo->prepare('DELETE FROM sr_quiz_choices WHERE question_id = :question_id')->execute(['question_id' => (int) $questionRow['id']]);
        }
        $pdo->prepare('DELETE FROM sr_quiz_questions WHERE quiz_id = :quiz_id')->execute(['quiz_id' => $quizId]);

        $questionStmt = $pdo->prepare(
            'INSERT INTO sr_quiz_questions
                (quiz_id, question_key, question_type, prompt, required, score_value, sort_order, created_at, updated_at)
             VALUES
                (:quiz_id, :question_key, :question_type, :prompt, 1, :score_value, :sort_order, :created_at, :updated_at)'
        );
        $choiceStmt = $pdo->prepare(
            'INSERT INTO sr_quiz_choices
                (question_id, choice_key, label, is_correct, score_value, category_key, category_weight, sort_order, created_at, updated_at)
             VALUES
                (:question_id, :choice_key, :label, :is_correct, :score_value, :category_key, :category_weight, :sort_order, :created_at, :updated_at)'
        );
        foreach ((array) ($values['questions'] ?? []) as $questionIndex => $question) {
            $questionType = (string) ($question['question_type'] ?? 'single_choice');
            if (!in_array($questionType, sr_quiz_question_types(), true)) {
                $questionType = 'single_choice';
            }
            $questionStmt->execute([
                'quiz_id' => $quizId,
                'question_key' => (string) $question['question_key'],
                'question_type' => $questionType,
                'prompt' => (string) $question['prompt'],
                'score_value' => (int) $question['score_value'],
                'sort_order' => $questionIndex,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $questionId = (int) $pdo->lastInsertId();
            foreach ((array) ($question['choices'] ?? []) as $choiceIndex => $choice) {
                $choiceStmt->execute([
                    'question_id' => $questionId,
                    'choice_key' => (string) $choice['choice_key'],
                    'label' => (string) $choice['label'],
                    'is_correct' => (int) $choice['is_correct'],
                    'score_value' => (int) $choice['is_correct'] === 1 ? (int) $question['score_value'] : 0,
                    'category_key' => (string) ($choice['category_key'] ?? '') !== '' ? (string) $choice['category_key'] : null,
                    'category_weight' => (int) ($choice['category_weight'] ?? 0),
                    'sort_order' => $choiceIndex,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if ((int) ($values['reward_enabled'] ?? 0) === 1) {
            $rewardProvider = (string) ($values['reward_provider'] ?? 'ledger_asset');
            $rewardDedupeScope = in_array((string) ($values['reward_dedupe_scope'] ?? 'per_quiz'), sr_quiz_reward_dedupe_scopes(), true)
                ? (string) $values['reward_dedupe_scope']
                : 'per_quiz';
            $rewardModule = $rewardProvider === 'coupon' ? 'coupon' : (string) $values['reward_module'];
            $rewardCode = $rewardProvider === 'coupon' ? (string) (int) ($values['reward_coupon_definition_id'] ?? 0) : 'quiz_reward';
            $rewardAmount = $rewardProvider === 'coupon' ? 1 : (int) $values['reward_amount'];
            $stmt = $pdo->prepare(
                'INSERT INTO sr_quiz_reward_policies
                    (quiz_id, status, trigger_type, reward_provider, reward_module, reward_code, reward_amount, dedupe_scope, sort_order, created_at, updated_at)
                 VALUES
                    (:quiz_id, \'active\', \'passed\', :reward_provider, :reward_module, :reward_code, :reward_amount, :dedupe_scope, 0, :created_at, :updated_at)'
            );
            $stmt->execute([
                'quiz_id' => $quizId,
                'reward_provider' => $rewardProvider,
                'reward_module' => $rewardModule,
                'reward_code' => $rewardCode,
                'reward_amount' => $rewardAmount,
                'dedupe_scope' => $rewardDedupeScope,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $resultStmt = $pdo->prepare(
            'INSERT INTO sr_quiz_results
                (quiz_id, result_key, title, summary, body, status, sort_order, created_at, updated_at)
             VALUES
                (:quiz_id, :result_key, :title, :summary, NULL, \'active\', :sort_order, :created_at, :updated_at)'
        );
        $ruleStmt = $pdo->prepare(
            'INSERT INTO sr_quiz_result_rules
                (quiz_id, result_id, rule_type, min_score, max_score, category_key, threshold_value, priority, created_at, updated_at)
             VALUES
                (:quiz_id, :result_id, :rule_type, :min_score, :max_score, :category_key, :threshold_value, :priority, :created_at, :updated_at)'
        );
        foreach (sr_quiz_result_rules_from_value((string) ($values['result_rules'] ?? '')) as $ruleIndex => $rule) {
            $resultStmt->execute([
                'quiz_id' => $quizId,
                'result_key' => (string) $rule['result_key'],
                'title' => (string) $rule['title'],
                'summary' => (string) $rule['summary'],
                'sort_order' => $ruleIndex,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $resultId = (int) $pdo->lastInsertId();
            $ruleStmt->execute([
                'quiz_id' => $quizId,
                'result_id' => $resultId,
                'rule_type' => (string) ($rule['category_key'] ?? '') !== '' ? 'category_threshold' : 'score_range',
                'min_score' => $rule['min_score'],
                'max_score' => $rule['max_score'],
                'category_key' => (string) ($rule['category_key'] ?? '') !== '' ? (string) $rule['category_key'] : null,
                'threshold_value' => $rule['threshold_value'],
                'priority' => 1000 - $ruleIndex,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $sourceStmt = $pdo->prepare(
            'INSERT INTO sr_quiz_sources
                (quiz_id, source_module, source_type, source_id, status, sort_order, cta_label, created_at, updated_at)
             VALUES
                (:quiz_id, :source_module, :source_type, :source_id, \'active\', :sort_order, :cta_label, :created_at, :updated_at)'
        );
        foreach (sr_quiz_content_source_ids_from_value((string) ($values['content_source_ids'] ?? '')) as $sourceIndex => $contentId) {
            $sourceStmt->execute([
                'quiz_id' => $quizId,
                'source_module' => 'content',
                'source_type' => 'content_item',
                'source_id' => $contentId,
                'sort_order' => $sourceIndex,
                'cta_label' => $defaultCtaLabel,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        foreach (sr_quiz_content_source_ids_from_value((string) ($values['community_source_ids'] ?? '')) as $sourceIndex => $postId) {
            $sourceStmt->execute([
                'quiz_id' => $quizId,
                'source_module' => 'community',
                'source_type' => 'community_post',
                'source_id' => $postId,
                'sort_order' => $sourceIndex,
                'cta_label' => $defaultCtaLabel,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $pdo->commit();
        return $quizId;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function sr_quiz_soft_delete(PDO $pdo, int $quizId, int $accountId): bool
{
    if ($quizId < 1) {
        return false;
    }

    $now = sr_now();
    $deletedTitle = '삭제된 퀴즈';
    $deletedBody = '삭제된 퀴즈입니다.';
    $oldStmt = $pdo->prepare('SELECT cover_image_url FROM sr_quiz_sets WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $oldStmt->execute(['id' => $quizId]);
    $oldRow = $oldStmt->fetch();
    $oldCoverImageUrl = is_array($oldRow) ? sr_quiz_clean_cover_image_url((string) ($oldRow['cover_image_url'] ?? '')) : '';
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'UPDATE sr_quiz_sets
             SET title = :title,
                 description = :description,
                 cover_image_url = \'\',
                 status = \'archived\',
                 comments_enabled = 0,
                 secret_comments_enabled = 0,
                 reward_enabled = 0,
                 updated_by_account_id = :account_id,
                 updated_at = :updated_at,
                 deleted_at = :deleted_at
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute([
            'title' => $deletedTitle,
            'description' => $deletedBody,
            'account_id' => $accountId,
            'updated_at' => $now,
            'deleted_at' => $now,
            'id' => $quizId,
        ]);
        $deleted = $stmt->rowCount() > 0;
        if (!$deleted) {
            $pdo->rollBack();
            return false;
        }

        $pdo->prepare('UPDATE sr_quiz_questions SET prompt = :prompt, help_text = NULL, settings_json = NULL, updated_at = :updated_at WHERE quiz_id = :quiz_id')->execute([
            'prompt' => $deletedBody,
            'updated_at' => $now,
            'quiz_id' => $quizId,
        ]);
        $driverName = '';
        try {
            $driverName = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable $exception) {
            $driverName = '';
        }
        if ($driverName === 'sqlite') {
            $pdo->prepare(
                'UPDATE sr_quiz_choices
                 SET label = :label,
                     description = NULL,
                     settings_json = NULL,
                     updated_at = :updated_at
                 WHERE question_id IN (SELECT id FROM sr_quiz_questions WHERE quiz_id = :quiz_id)'
            )->execute([
                'label' => '삭제된 선택지',
                'updated_at' => $now,
                'quiz_id' => $quizId,
            ]);
        } else {
            $pdo->prepare(
                'UPDATE sr_quiz_choices c
                 INNER JOIN sr_quiz_questions q ON q.id = c.question_id
                 SET c.label = :label,
                     c.description = NULL,
                     c.settings_json = NULL,
                     c.updated_at = :updated_at
                 WHERE q.quiz_id = :quiz_id'
            )->execute([
                'label' => '삭제된 선택지',
                'updated_at' => $now,
                'quiz_id' => $quizId,
            ]);
        }
        $pdo->prepare('UPDATE sr_quiz_results SET title = :title, summary = \'\', body = \'\', updated_at = :updated_at WHERE quiz_id = :quiz_id')->execute([
            'title' => '삭제된 결과',
            'updated_at' => $now,
            'quiz_id' => $quizId,
        ]);
        $pdo->prepare('UPDATE sr_quiz_comments SET author_public_name_snapshot = \'\', body_text = :body_text, status = \'deleted\', deleted_at = COALESCE(deleted_at, :deleted_at), updated_at = :updated_at WHERE quiz_id = :quiz_id')->execute([
            'body_text' => '삭제된 댓글입니다.',
            'deleted_at' => $now,
            'updated_at' => $now,
            'quiz_id' => $quizId,
        ]);
        $pdo->prepare(
            "UPDATE sr_quiz_attempts
             SET source_title_snapshot = '',
                 source_url_snapshot = '',
                 return_url = '',
                 answer_snapshot_json = '{}',
                 scoring_snapshot_json = '{}',
                 result_snapshot_json = '{}',
                 updated_at = :updated_at
             WHERE quiz_id = :quiz_id"
        )->execute([
            'updated_at' => $now,
            'quiz_id' => $quizId,
        ]);
        if ($driverName === 'sqlite') {
            $pdo->prepare(
                "UPDATE sr_quiz_attempt_answers
                 SET answer_text = NULL,
                     answer_snapshot_json = '{}',
                     category_scores_json = NULL
                 WHERE attempt_id IN (SELECT id FROM sr_quiz_attempts WHERE quiz_id = :quiz_id)"
            )->execute(['quiz_id' => $quizId]);
            $pdo->prepare(
                "UPDATE sr_quiz_attempt_result_scores
                 SET snapshot_json = '{}'
                 WHERE attempt_id IN (SELECT id FROM sr_quiz_attempts WHERE quiz_id = :quiz_id)"
            )->execute(['quiz_id' => $quizId]);
        } else {
            $pdo->prepare(
                "UPDATE sr_quiz_attempt_answers aa
                 INNER JOIN sr_quiz_attempts a ON a.id = aa.attempt_id
                 SET aa.answer_text = NULL,
                     aa.answer_snapshot_json = '{}',
                     aa.category_scores_json = NULL
                 WHERE a.quiz_id = :quiz_id"
            )->execute(['quiz_id' => $quizId]);
            $pdo->prepare(
                "UPDATE sr_quiz_attempt_result_scores ars
                 INNER JOIN sr_quiz_attempts a ON a.id = ars.attempt_id
                 SET ars.snapshot_json = '{}'
                 WHERE a.quiz_id = :quiz_id"
            )->execute(['quiz_id' => $quizId]);
        }
        $pdo->prepare(
            "UPDATE sr_quiz_reward_grants
             SET request_snapshot_json = '{}',
                 result_snapshot_json = '{}',
                 error_message = '',
                 resolution_note = ''
             WHERE quiz_id = :quiz_id"
        )->execute(['quiz_id' => $quizId]);

        $pdo->commit();
        if ($oldCoverImageUrl !== '') {
            sr_quiz_delete_cover_image_storage($pdo, $oldCoverImageUrl, $quizId);
        }
        return true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function sr_quiz_content_quizzes(PDO $pdo, int $contentId): array
{
    if ($contentId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT q.id, q.quiz_key, q.title, q.description, q.member_group_keys_json, s.cta_label
         FROM sr_quiz_sources s
         INNER JOIN sr_quiz_sets q ON q.id = s.quiz_id
         WHERE s.source_module = \'content\'
           AND s.source_type = \'content_item\'
           AND s.source_id = :content_id
           AND s.status = \'active\'
           AND q.status = \'active\'
           AND q.deleted_at IS NULL
           AND (q.starts_at IS NULL OR q.starts_at <= :now_start)
           AND (q.ends_at IS NULL OR q.ends_at >= :now_end)
         ORDER BY s.sort_order ASC, q.updated_at DESC, q.id DESC'
    );
    $stmt->execute([
        'content_id' => $contentId,
        'now_start' => sr_now(),
        'now_end' => sr_now(),
    ]);

    return $stmt->fetchAll();
}

function sr_quiz_community_post_quizzes(PDO $pdo, int $postId): array
{
    if ($postId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT q.id, q.quiz_key, q.title, q.description, q.member_group_keys_json, s.cta_label
         FROM sr_quiz_sources s
         INNER JOIN sr_quiz_sets q ON q.id = s.quiz_id
         WHERE s.source_module = \'community\'
           AND s.source_type = \'community_post\'
           AND s.source_id = :post_id
           AND s.status = \'active\'
           AND q.status = \'active\'
           AND q.deleted_at IS NULL
           AND (q.starts_at IS NULL OR q.starts_at <= :now_start)
           AND (q.ends_at IS NULL OR q.ends_at >= :now_end)
         ORDER BY s.sort_order ASC, q.updated_at DESC, q.id DESC'
    );
    $now = sr_now();
    $stmt->execute([
        'post_id' => $postId,
        'now_start' => $now,
        'now_end' => $now,
    ]);

    return $stmt->fetchAll();
}

function sr_quiz_coupon_definition_reference_count(PDO $pdo, array $target, array $context): int
{
    return count(sr_quiz_coupon_definition_reference_rows($pdo, $target, $context));
}

function sr_quiz_coupon_definition_reference_rows(PDO $pdo, array $target, array $context): array
{
    $definitionId = (int) ($target['target_id'] ?? 0);
    if ($definitionId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT rp.id AS reward_policy_id, rp.status AS reward_policy_status, q.id AS quiz_id, q.quiz_key, q.title, q.status AS quiz_status, q.updated_at
         FROM sr_quiz_reward_policies rp
         INNER JOIN sr_quiz_sets q ON q.id = rp.quiz_id
         WHERE rp.reward_provider = \'coupon\'
           AND rp.reward_code = :definition_id
           AND q.deleted_at IS NULL
         ORDER BY q.updated_at DESC, q.id DESC'
    );
    $stmt->execute(['definition_id' => (string) $definitionId]);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'consumer_module_key' => 'quiz',
            'reference_type' => 'quiz_reward_coupon',
            'reference_id' => (string) (int) ($row['reward_policy_id'] ?? 0),
            'quiz_id' => (int) ($row['quiz_id'] ?? 0),
            'target_type' => 'coupon_definition',
            'target_id' => (string) $definitionId,
            'title' => (string) ($row['title'] ?? ''),
            'status' => (string) ($row['quiz_status'] ?? ''),
            'summary' => '퀴즈 보상: ' . (string) ($row['quiz_key'] ?? ''),
            'admin_url' => '/admin/quiz?mode=edit&id=' . rawurlencode((string) (int) ($row['quiz_id'] ?? 0)),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    return $rows;
}

function sr_quiz_coupon_definition_reference_health(PDO $pdo, array $target, array $row, array $context): array
{
    $quizId = (int) ($row['quiz_id'] ?? $row['reference_id'] ?? 0);
    if ($quizId < 1) {
        return ['status' => 'unknown', 'message' => '퀴즈 참조를 확인할 수 없습니다.'];
    }

    return ['status' => 'ok'];
}

function sr_quiz_coupon_definition_reference_admin_url(array $row, array $context): string
{
    $quizId = (int) ($row['quiz_id'] ?? 0);
    if ($quizId < 1 && preg_match('/\A[1-9][0-9]*\z/', (string) ($row['reference_id'] ?? '')) === 1) {
        $quizId = (int) $row['reference_id'];
    }

    return $quizId > 0 ? '/admin/quiz?mode=edit&id=' . rawurlencode((string) $quizId) : '/admin/quiz';
}
