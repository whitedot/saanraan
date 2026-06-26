<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/helpers/common.php';

require_once SR_ROOT . '/modules/popup_layer/helpers/body-files.php';

function sr_popup_layer_available_targets(PDO $pdo): array
{
    $targets = [];
    foreach (sr_enabled_module_contract_files($pdo, 'extension-points.php', ['popup_layer']) as $moduleKey => $file) {
        $modulePoints = sr_load_module_contract_file($moduleKey, $file);
        if (is_callable($modulePoints)) {
            $modulePoints = $modulePoints($pdo);
        }

        if (!is_array($modulePoints)) {
            continue;
        }

        foreach ($modulePoints as $point) {
            if (!is_array($point)) {
                continue;
            }

            $pointKey = (string) ($point['point_key'] ?? '');
            if (!sr_popup_layer_is_safe_key($pointKey, 120)) {
                continue;
            }

            if (($point['surface'] ?? 'public') !== 'public' || ($point['output'] ?? true) === false) {
                continue;
            }

            $pointLabel = sr_popup_layer_clean_single_line((string) ($point['label'] ?? $pointKey), 120);
            $slots = sr_popup_layer_normalize_slots($point['slots'] ?? []);
            $slot = $slots[0] ?? null;
            if (is_array($slot)) {
                $targets[] = [
                    'module_key' => $moduleKey,
                    'module_label' => sr_popup_layer_module_label($moduleKey),
                    'point_key' => $pointKey,
                    'point_label' => $pointLabel,
                    'slot_key' => (string) $slot['slot_key'],
                    'slot_label' => '화면',
                ];
            }
        }
    }

    return $targets;
}

function sr_popup_layer_normalize_slots(mixed $slots): array
{
    if (!is_array($slots) || $slots === []) {
        return [
            [
                'slot_key' => sr_popup_layer_default_slot_key(),
                'slot_label' => '화면',
                'slot_kind' => 'content',
            ],
        ];
    }

    $normalized = [];
    foreach ($slots as $slot) {
        if (!is_array($slot)) {
            continue;
        }

        $slotKey = (string) ($slot['slot_key'] ?? '');
        if (!sr_popup_layer_is_safe_key($slotKey, 80)) {
            continue;
        }

        $slotKind = sr_popup_layer_clean_slot_kind((string) ($slot['kind'] ?? 'content'));
        if ($slotKind !== 'content') {
            continue;
        }

        $slotLabel = sr_popup_layer_clean_single_line((string) ($slot['label'] ?? $slotKey), 80);
        $normalized[$slotKey] = [
            'slot_key' => $slotKey,
            'slot_label' => $slotLabel !== '' ? $slotLabel : $slotKey,
            'slot_kind' => $slotKind,
        ];
    }

    return array_values($normalized);
}

function sr_popup_layer_clean_slot_kind(string $value): string
{
    $value = preg_replace('/[^a-z0-9_.-]/', '', strtolower(trim($value)));
    $value = is_string($value) ? $value : '';

    return substr($value, 0, 40);
}

function sr_popup_layer_module_label(string $moduleKey): string
{
    $metadata = sr_module_metadata($moduleKey);
    $name = (string) ($metadata['name'] ?? '');

    return $name !== '' ? $name : $moduleKey;
}

function sr_popup_layer_target_option_value(array $target): string
{
    return (string) $target['module_key'] . '|' . (string) $target['point_key'] . '|' . (string) $target['slot_key'];
}

function sr_popup_layer_public_target_option_value(): string
{
    return '__public__';
}

function sr_popup_layer_is_public_target_option(string $option): bool
{
    return $option === sr_popup_layer_public_target_option_value();
}

function sr_popup_layer_target_option_label(array $target): string
{
    return (string) $target['module_label'] . ' / ' . (string) $target['point_label'];
}

function sr_popup_layer_target_detail_label(array $target): string
{
    return (string) $target['point_label'];
}

function sr_popup_layer_target_service_key(array $target): string
{
    $moduleKey = (string) ($target['module_key'] ?? '');
    return sr_is_safe_module_key($moduleKey) ? $moduleKey : '';
}

function sr_popup_layer_target_service_label(string $serviceKey): string
{
    if ($serviceKey === sr_popup_layer_public_target_option_value()) {
        return '공용';
    }

    $labels = [
        'core' => '홈',
        'content' => '콘텐츠',
        'community' => '커뮤니티',
        'member' => '회원',
        'quiz' => '퀴즈·테스트',
        'survey' => '설문·여론조사',
    ];
    if (isset($labels[$serviceKey])) {
        return $labels[$serviceKey];
    }

    return sr_popup_layer_module_label($serviceKey);
}

function sr_popup_layer_subject_target_type_for_target(?array $target): string
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

    if ($moduleKey === 'quiz' && $pointKey === 'quiz.view') {
        return 'quiz';
    }

    if ($moduleKey === 'survey' && $pointKey === 'survey.view') {
        return 'survey';
    }

    return '';
}

function sr_popup_layer_subject_target_contract_helper_path(string $moduleKey, array $target): string
{
    $helpers = (string) ($target['helpers'] ?? '');
    if ($helpers === '' || preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helpers) !== 1) {
        return '';
    }

    $path = SR_ROOT . '/modules/' . $moduleKey . '/' . $helpers;
    return is_file($path) ? $path : '';
}

function sr_popup_layer_subject_target_contracts(PDO $pdo): array
{
    $contracts = [];
    foreach (sr_enabled_module_contract_files($pdo, 'coupon-targets.php', ['popup_layer']) as $moduleKey => $file) {
        $contractTargets = sr_load_module_contract_file($moduleKey, $file);
        if (!is_array($contractTargets)) {
            continue;
        }

        foreach ($contractTargets as $target) {
            if (!is_array($target)) {
                continue;
            }

            $targetType = (string) ($target['target_type'] ?? '');
            $label = sr_popup_layer_clean_single_line((string) ($target['label'] ?? ''), 80);
            if ($targetType === '' || $label === '' || preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $targetType) !== 1) {
                continue;
            }

            $helperPath = sr_popup_layer_subject_target_contract_helper_path($moduleKey, $target);
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

function sr_popup_layer_subject_search_types(PDO $pdo, array $availableTargets): array
{
    $contracts = sr_popup_layer_subject_target_contracts($pdo);
    $types = [];

    foreach ($availableTargets as $target) {
        $targetType = sr_popup_layer_subject_target_type_for_target($target);
        if ($targetType !== '' && isset($contracts[$targetType])) {
            $types[$targetType] = (string) ($contracts[$targetType]['label'] ?? $targetType);
        }
    }

    return $types;
}

function sr_popup_layer_subject_target_type_map(PDO $pdo, array $availableTargets): array
{
    $contracts = sr_popup_layer_subject_target_contracts($pdo);
    $map = [];

    foreach ($availableTargets as $target) {
        $targetType = sr_popup_layer_subject_target_type_for_target($target);
        if ($targetType !== '' && isset($contracts[$targetType])) {
            $map[sr_popup_layer_target_option_value($target)] = $targetType;
        }
    }

    return $map;
}

function sr_popup_layer_subject_search(PDO $pdo, string $referenceType, string $keyword, int $limit = 20, array $options = []): array
{
    $contracts = sr_popup_layer_subject_target_contracts($pdo);
    $target = $contracts[$referenceType] ?? null;
    $searchFunction = is_array($target) ? (string) ($target['search_function'] ?? '') : '';
    if ($searchFunction === '' || !function_exists($searchFunction)) {
        return [];
    }

    try {
        $reflection = new ReflectionFunction($searchFunction);
        $results = $reflection->getNumberOfParameters() >= 5
            ? $searchFunction($pdo, $referenceType, sr_popup_layer_clean_single_line($keyword, 120), max(1, min(30, $limit)), $options)
            : $searchFunction($pdo, $referenceType, sr_popup_layer_clean_single_line($keyword, 120), max(1, min(30, $limit)));
        return is_array($results) ? $results : [];
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'popup_layer_subject_search_' . $referenceType);
        return [];
    }
}

function sr_popup_layer_subject_health(PDO $pdo, string $referenceType, string $subjectId): array
{
    $contracts = sr_popup_layer_subject_target_contracts($pdo);
    $target = $contracts[$referenceType] ?? null;
    $healthFunction = is_array($target) ? (string) ($target['health_function'] ?? '') : '';
    if ($healthFunction === '' || !function_exists($healthFunction)) {
        return ['status' => 'unknown', 'message' => '대상 검증 계약을 찾을 수 없습니다.'];
    }

    try {
        $result = $healthFunction($pdo, $referenceType, $subjectId);
        return is_array($result) ? $result : ['status' => 'unknown', 'message' => '대상 검증 결과가 올바르지 않습니다.'];
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'popup_layer_subject_health_' . $referenceType);
        return ['status' => 'unknown', 'message' => '대상 검증 중 오류가 발생했습니다.'];
    }
}

function sr_popup_layer_target_service_options(array $targets, bool $includePublic = true): array
{
    $options = [];
    if ($includePublic) {
        $options[sr_popup_layer_public_target_option_value()] = sr_popup_layer_target_service_label(sr_popup_layer_public_target_option_value());
    }

    foreach ($targets as $target) {
        $serviceKey = sr_popup_layer_target_service_key($target);
        if ($serviceKey !== '' && !isset($options[$serviceKey])) {
            $options[$serviceKey] = sr_popup_layer_target_service_label($serviceKey);
        }
    }

    return $options;
}

function sr_popup_layer_selected_target_service_key(string $targetOption): string
{
    if (sr_popup_layer_is_public_target_option($targetOption)) {
        return sr_popup_layer_public_target_option_value();
    }

    $parts = explode('|', $targetOption);
    return count($parts) === 3 && sr_is_safe_module_key((string) $parts[0]) ? (string) $parts[0] : '';
}

function sr_popup_layer_target_from_row(array $row, string $label = '선언이 사라진 노출 위치'): ?array
{
    $moduleKey = (string) ($row['module_key'] ?? '');
    $pointKey = (string) ($row['point_key'] ?? '');
    $slotKey = (string) ($row['slot_key'] ?? '');

    if (!sr_is_safe_module_key($moduleKey) || !sr_popup_layer_is_safe_key($pointKey, 120) || !sr_popup_layer_is_safe_key($slotKey, 80)) {
        return null;
    }

    return [
        'module_key' => $moduleKey,
        'module_label' => sr_popup_layer_target_service_label($moduleKey),
        'point_key' => $pointKey,
        'point_label' => $label . ' / ' . $pointKey,
        'slot_key' => $slotKey,
        'slot_label' => $slotKey,
    ];
}

function sr_popup_layer_normalize_posted_target_option(array $targets, string $serviceKey, string $detailOption, string $legacyOption): array
{
    $serviceKey = trim($serviceKey);
    $detailOption = trim($detailOption);
    $legacyOption = trim($legacyOption);

    if ($serviceKey === '' && $detailOption === '') {
        $detailOption = $legacyOption;
        $serviceKey = sr_popup_layer_selected_target_service_key($detailOption);
    }

    if ($serviceKey === sr_popup_layer_public_target_option_value()) {
        return [
            'option' => sr_popup_layer_public_target_option_value(),
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

    $target = sr_popup_layer_find_target($targets, $detailOption);
    if ($target === null || sr_popup_layer_target_service_key($target) !== $serviceKey) {
        return [
            'option' => $detailOption,
            'is_public' => false,
            'target' => null,
            'error' => '선택한 서비스에 속한 화면을 선택하세요.',
        ];
    }

    return [
        'option' => sr_popup_layer_target_option_value($target),
        'is_public' => false,
        'target' => $target,
        'error' => '',
    ];
}

function sr_popup_layer_find_target(array $targets, string $optionValue): ?array
{
    foreach ($targets as $target) {
        if (sr_popup_layer_target_option_value($target) === $optionValue) {
            return $target;
        }
    }

    return null;
}

function sr_popup_layer_public_layers(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        "SELECT p.id, p.title, p.status, p.starts_at, p.ends_at, p.dismiss_cookie_days, p.updated_at
         FROM sr_popup_layers p
         WHERE NOT EXISTS (
             SELECT 1
             FROM sr_popup_layer_targets t
             WHERE t.popup_layer_id = p.id
         )
         ORDER BY p.id DESC"
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_popup_layer_settings(PDO $pdo): array
{
    $metadata = sr_module_metadata('popup_layer');
    $defaults = isset($metadata['settings']) && is_array($metadata['settings']) ? $metadata['settings'] : [];

    return array_merge(sr_popup_layer_default_settings(), $defaults, sr_module_settings($pdo, 'popup_layer'));
}

function sr_popup_layer_coupon_helpers_available(PDO $pdo): bool
{
    if (!function_exists('sr_module_enabled') || !sr_module_enabled($pdo, 'coupon')) {
        return false;
    }
    $helper = SR_ROOT . '/modules/coupon/helpers.php';
    if (!is_file($helper)) {
        return false;
    }
    require_once $helper;

    return function_exists('sr_coupon_claim_tables_available')
        && function_exists('sr_coupon_public_claim_campaign')
        && (!function_exists('sr_coupon_usage_enabled') || sr_coupon_usage_enabled($pdo))
        && sr_coupon_claim_tables_available($pdo);
}

function sr_popup_layer_coupon_claim_campaign_options(PDO $pdo): array
{
    if (!sr_popup_layer_coupon_helpers_available($pdo)) {
        return [];
    }

    $stmt = $pdo->query(
        "SELECT c.campaign_key, c.title, c.status, c.visibility, c.exposure_surfaces_json, d.title AS coupon_title
         FROM sr_coupon_claim_campaigns c
         INNER JOIN sr_coupon_definitions d ON d.id = c.coupon_definition_id
         WHERE c.claim_type = 'free'
         ORDER BY c.id DESC
         LIMIT 300"
    );

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $surfaces = function_exists('sr_coupon_claim_surfaces_from_value')
            ? sr_coupon_claim_surfaces_from_value($row['exposure_surfaces_json'] ?? '')
            : [];
        if (!in_array('popup_layer', $surfaces, true)) {
            continue;
        }
        if ((string) ($row['visibility'] ?? '') !== 'public') {
            continue;
        }
        $rows[] = $row;
    }

    return $rows;
}

function sr_popup_layer_coupon_claim_campaign_label(array $campaign): string
{
    $title = sr_popup_layer_clean_single_line((string) ($campaign['title'] ?? ''), 120);
    $couponTitle = sr_popup_layer_clean_single_line((string) ($campaign['coupon_title'] ?? ''), 120);
    $key = sr_popup_layer_clean_single_line((string) ($campaign['campaign_key'] ?? ''), 60);
    $parts = [];
    if ($title !== '') {
        $parts[] = $title;
    }
    if ($couponTitle !== '') {
        $parts[] = $couponTitle;
    }
    if ($key !== '') {
        $parts[] = $key;
    }

    return implode(' / ', $parts);
}

function sr_popup_layer_validate_coupon_claim_campaign(PDO $pdo, string $campaignKey): string
{
    $campaignKey = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($campaignKey))) ?? '';
    $campaignKey = substr($campaignKey, 0, 60);
    if ($campaignKey === '') {
        return '';
    }
    if (!sr_popup_layer_coupon_helpers_available($pdo) || !function_exists('sr_coupon_claim_campaign_by_key') || !function_exists('sr_coupon_claim_surfaces_from_value')) {
        throw new InvalidArgumentException('쿠폰 발급 캠페인 업데이트를 먼저 적용하세요.');
    }

    $campaign = sr_coupon_claim_campaign_by_key($pdo, $campaignKey);
    if (!is_array($campaign)) {
        throw new InvalidArgumentException('연결할 쿠폰 발급 캠페인을 찾을 수 없습니다.');
    }
    if ((string) ($campaign['claim_type'] ?? '') !== 'free') {
        throw new InvalidArgumentException('팝업레이어 CTA는 무료 발급 캠페인만 연결할 수 있습니다.');
    }
    if ((string) ($campaign['visibility'] ?? '') !== 'public') {
        throw new InvalidArgumentException('팝업레이어 CTA는 공개 캠페인만 연결할 수 있습니다.');
    }
    if (!in_array('popup_layer', sr_coupon_claim_surfaces_from_value($campaign['exposure_surfaces_json'] ?? ''), true)) {
        throw new InvalidArgumentException('쿠폰 발급 캠페인의 노출 위치에 팝업레이어를 포함해야 합니다.');
    }

    return $campaignKey;
}

function sr_popup_layer_coupon_cta(array $popup): array
{
    return is_array($popup['coupon_cta'] ?? null) ? $popup['coupon_cta'] : [];
}

function sr_popup_layer_default_settings(): array
{
    return [
        'popup_layer_skin_key' => 'basic',
        'popup_layer_default_status' => 'draft',
        'popup_layer_default_target_option' => sr_popup_layer_public_target_option_value(),
        'popup_layer_default_match_type' => 'all',
        'popup_layer_default_dismiss_cookie_days' => 1,
        'popup_layer_editor' => 'textarea',
    ];
}

function sr_popup_layer_default_status(array $settings): string
{
    $status = (string) ($settings['popup_layer_default_status'] ?? 'draft');

    return in_array($status, ['draft', 'enabled', 'disabled'], true) ? $status : 'draft';
}

function sr_popup_layer_default_target_option(array $settings, array $availableTargets): string
{
    $targetOption = (string) ($settings['popup_layer_default_target_option'] ?? sr_popup_layer_public_target_option_value());
    if (sr_popup_layer_is_public_target_option($targetOption)) {
        return sr_popup_layer_public_target_option_value();
    }

    return sr_popup_layer_find_target($availableTargets, $targetOption) !== null ? $targetOption : sr_popup_layer_public_target_option_value();
}

function sr_popup_layer_default_match_type(array $settings): string
{
    $matchType = (string) ($settings['popup_layer_default_match_type'] ?? 'all');

    return in_array($matchType, ['all', 'exact'], true) ? $matchType : 'all';
}

function sr_popup_layer_default_dismiss_cookie_days(array $settings): int
{
    return max(0, min(365, (int) ($settings['popup_layer_default_dismiss_cookie_days'] ?? 1)));
}

function sr_popup_layer_editor_key(PDO $pdo, array $settings): string
{
    return sr_editor_effective_key($pdo, (string) ($settings['popup_layer_editor'] ?? 'textarea'));
}

function sr_popup_layer_skin_options(): array
{
    return sr_filter_view_options([
        'basic' => [
            'label' => sr_t('popup_layer::skin.basic'),
            'views' => [
                'layer' => SR_ROOT . '/modules/popup_layer/skins/basic/layer.php',
            ],
        ],
    ], ['layer'], 'popup layer skin');
}

function sr_popup_layer_skin_key(array $settings): string
{
    $skinKey = (string) ($settings['popup_layer_skin_key'] ?? 'basic');

    return isset(sr_popup_layer_skin_options()[$skinKey]) ? $skinKey : 'basic';
}

function sr_popup_layer_skin_view(string $skinKey, string $viewKey): string
{
    $options = sr_popup_layer_skin_options();
    $view = (string) ($options[$skinKey]['views'][$viewKey] ?? $options['basic']['views'][$viewKey] ?? '');

    if (is_file($view)) {
        return $view;
    }

    $fallback = (string) ($options['basic']['views'][$viewKey] ?? '');
    if (is_file($fallback)) {
        return $fallback;
    }

    throw new RuntimeException('기본 팝업레이어 스킨 view 파일이 누락되었습니다.');
}

function sr_popup_layer_save_skin_key(PDO $pdo, string $skinKey): void
{
    sr_popup_layer_save_settings($pdo, [
        'popup_layer_skin_key' => $skinKey,
    ]);
}

function sr_popup_layer_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'popup_layer' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('팝업레이어 모듈이 등록되어 있지 않습니다.');
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
    if (array_key_exists('popup_layer_skin_key', $settings)) {
        $rows[] = ['popup_layer_skin_key', sr_popup_layer_skin_key(['popup_layer_skin_key' => (string) $settings['popup_layer_skin_key']]), 'string'];
    }
    if (array_key_exists('popup_layer_default_status', $settings)) {
        $rows[] = ['popup_layer_default_status', sr_popup_layer_default_status($settings), 'string'];
    }
    if (array_key_exists('popup_layer_default_target_option', $settings)) {
        $rows[] = ['popup_layer_default_target_option', (string) $settings['popup_layer_default_target_option'], 'string'];
    }
    if (array_key_exists('popup_layer_default_match_type', $settings)) {
        $rows[] = ['popup_layer_default_match_type', sr_popup_layer_default_match_type($settings), 'string'];
    }
    if (array_key_exists('popup_layer_default_dismiss_cookie_days', $settings)) {
        $rows[] = ['popup_layer_default_dismiss_cookie_days', (string) sr_popup_layer_default_dismiss_cookie_days($settings), 'int'];
    }
    if (array_key_exists('popup_layer_editor', $settings)) {
        $rows[] = ['popup_layer_editor', sr_editor_normalize_key((string) $settings['popup_layer_editor']), 'string'];
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
    sr_clear_module_settings_cache('popup_layer');
}

function sr_popup_layer_render_stack(array $popups, string $skinKey = 'basic'): string
{
    if ($popups === []) {
        return '';
    }

    $skinKey = sr_popup_layer_skin_key(['popup_layer_skin_key' => $skinKey]);
    $view = sr_popup_layer_skin_view($skinKey, 'layer');
    if ($view === '') {
        return '';
    }

    ob_start();
    include $view;
    return (string) ob_get_clean();
}

function sr_popup_layer_render_basic_stack(array $popups): string
{
    $cookiePath = sr_base_path();
    $cookiePath = $cookiePath === '' ? '/' : $cookiePath;
    $html = ['<div class="sr-popup-layer-stack" data-sr-popup-layer-stack data-cookie-path="' . sr_e($cookiePath) . '">'];
    foreach ($popups as $popup) {
        $cookieDays = max(0, min(365, (int) $popup['dismiss_cookie_days']));
        $html[] = '<section class="sr-popup-layer" data-sr-popup-layer data-popup-id="' . sr_e((string) $popup['id']) . '" data-cookie-days="' . sr_e((string) $cookieDays) . '">';
        $html[] = '<h2>' . sr_e((string) $popup['title']) . '</h2>';
        $bodyText = (string) ($popup['body_text'] ?? '');
        $bodyHtml = (string) ($popup['body_format'] ?? 'plain') === 'html' ? sr_sanitize_rich_text_html($bodyText) : nl2br(sr_e($bodyText), false);
        $html[] = '<div class="sr-popup-layer-body">' . $bodyHtml . '</div>';
        $html[] = '<div class="sr-popup-layer-actions">';
        $couponCta = sr_popup_layer_coupon_cta($popup);
        if ($couponCta !== []) {
            $html[] = '<a class="sr-popup-layer-coupon-cta" href="' . sr_e((string) ($couponCta['url'] ?? '')) . '" data-sr-popup-layer-coupon-cta>' . sr_e((string) ($couponCta['label'] ?? '쿠폰 받기')) . '</a>';
        }
        $html[] = '<button class="sr-popup-layer-close" type="button" data-sr-popup-layer-close>닫기</button>';
        if ($cookieDays > 0) {
            $html[] = '<button class="sr-popup-layer-dismiss" type="button" data-sr-popup-layer-dismiss>' . sr_e((string) $cookieDays) . '일 동안 보지 않기</button>';
        }
        $html[] = '</div>';
        $html[] = '</section>';
    }
    $html[] = '</div>';
    $html[] = sr_popup_layer_close_script();
    return implode("\n", $html);
}

function sr_popup_layer_render_public_layer(PDO $pdo, int $popupLayerId): string
{
    if ($popupLayerId <= 0) {
        return '';
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        "SELECT p.id, p.title, p.body_text, p.body_format, p.coupon_claim_campaign_key, p.skin_key, p.dismiss_cookie_days
         FROM sr_popup_layers p
         WHERE p.id = :id
           AND p.status = 'enabled'
           AND (p.starts_at IS NULL OR p.starts_at <= :now_start)
           AND (p.ends_at IS NULL OR p.ends_at >= :now_end)
           AND NOT EXISTS (
               SELECT 1
               FROM sr_popup_layer_targets t
               WHERE t.popup_layer_id = p.id
           )
         LIMIT 1"
    );
    $stmt->execute([
        'id' => $popupLayerId,
        'now_start' => $now,
        'now_end' => $now,
    ]);

    $row = $stmt->fetch();
    if (!is_array($row)) {
        return '';
    }

    $id = (int) ($row['id'] ?? 0);
    if ($id <= 0 || isset($_COOKIE[sr_popup_layer_cookie_name($id)])) {
        return '';
    }

    $skinKey = sr_popup_layer_skin_key(['popup_layer_skin_key' => (string) ($row['skin_key'] ?? 'basic')]);
    $popup = sr_popup_layer_prepare_render_popup($pdo, [
            'id' => $id,
            'title' => (string) ($row['title'] ?? ''),
            'body_text' => (string) ($row['body_text'] ?? ''),
            'body_format' => (string) ($row['body_format'] ?? 'plain'),
            'coupon_claim_campaign_key' => (string) ($row['coupon_claim_campaign_key'] ?? ''),
            'skin_key' => $skinKey,
            'dismiss_cookie_days' => (int) ($row['dismiss_cookie_days'] ?? 1),
        ]);

    return sr_popup_layer_render_stack([$popup], $skinKey);
}

function sr_popup_layer_render(PDO $pdo, array $context): string
{
    $moduleKey = (string) ($context['module_key'] ?? '');
    $pointKey = (string) ($context['point_key'] ?? '');
    $slotKey = (string) ($context['slot_key'] ?? sr_popup_layer_default_slot_key());
    $subjectId = sr_popup_layer_clean_subject_id((string) ($context['subject_id'] ?? ''));

    if (
        !sr_is_safe_module_key($moduleKey)
        || !sr_popup_layer_is_safe_key($pointKey, 120)
        || !sr_popup_layer_is_safe_key($slotKey, 80)
    ) {
        return '';
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        "SELECT p.id, p.title, p.body_text, p.body_format, p.coupon_claim_campaign_key, p.skin_key, p.dismiss_cookie_days
         FROM sr_popup_layers p
         INNER JOIN sr_popup_layer_targets t ON t.popup_layer_id = p.id
         WHERE p.status = 'enabled'
           AND (p.starts_at IS NULL OR p.starts_at <= :now_start)
           AND (p.ends_at IS NULL OR p.ends_at >= :now_end)
           AND t.module_key = :module_key
           AND t.point_key = :point_key
           AND t.slot_key = :slot_key
           AND (
                t.match_type = 'all'
                OR (t.match_type = 'exact' AND t.subject_id = :subject_id)
           )
         ORDER BY p.id DESC"
    );
    $stmt->execute([
        'now_start' => $now,
        'now_end' => $now,
        'module_key' => $moduleKey,
        'point_key' => $pointKey,
        'slot_key' => $slotKey,
        'subject_id' => $subjectId,
    ]);

    $popups = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $cookieName = sr_popup_layer_cookie_name($id);
        if (isset($_COOKIE[$cookieName])) {
            continue;
        }

        $popups[] = sr_popup_layer_prepare_render_popup($pdo, [
            'id' => $id,
            'title' => (string) ($row['title'] ?? ''),
            'body_text' => (string) ($row['body_text'] ?? ''),
            'body_format' => (string) ($row['body_format'] ?? 'plain'),
            'coupon_claim_campaign_key' => (string) ($row['coupon_claim_campaign_key'] ?? ''),
            'skin_key' => sr_popup_layer_skin_key(['popup_layer_skin_key' => (string) ($row['skin_key'] ?? 'basic')]),
            'dismiss_cookie_days' => (int) ($row['dismiss_cookie_days'] ?? 1),
        ]);
    }

    $html = '';
    $popupsBySkin = [];
    foreach ($popups as $popup) {
        $popupsBySkin[(string) $popup['skin_key']][] = $popup;
    }
    foreach ($popupsBySkin as $skinKey => $skinPopups) {
        $html .= sr_popup_layer_render_stack($skinPopups, $skinKey);
    }

    return $html;
}

function sr_popup_layer_prepare_render_popup(PDO $pdo, array $popup): array
{
    $campaignKey = (string) ($popup['coupon_claim_campaign_key'] ?? '');
    if ($campaignKey === '' || !sr_popup_layer_coupon_helpers_available($pdo)) {
        return $popup;
    }

    $accountId = 0;
    if (function_exists('sr_member_current_account') && isset($_SESSION) && is_array($_SESSION)) {
        try {
            $account = sr_member_current_account($pdo);
            $accountId = is_array($account) ? (int) ($account['id'] ?? 0) : 0;
        } catch (Throwable) {
            $accountId = 0;
        }
    }
    $campaign = sr_coupon_public_claim_campaign($pdo, $campaignKey, $accountId, ['popup_layer']);
    if (!is_array($campaign)) {
        return $popup;
    }

    $campaignUrl = '/coupons?campaign=' . rawurlencode((string) ($campaign['campaign_key'] ?? ''));
    $state = is_array($campaign['claim_state'] ?? null) ? $campaign['claim_state'] : [];
    $label = $accountId > 0 ? '쿠폰 받기' : '로그인하고 받기';
    $url = $accountId > 0 ? $campaignUrl : '/login?return_to=' . rawurlencode($campaignUrl);
    if ($accountId > 0 && empty($state['claimable'])) {
        $label = (string) ($state['message'] ?? '쿠폰 확인');
        $url = $campaignUrl;
    }

    $popup['coupon_cta'] = [
        'campaign_key' => (string) ($campaign['campaign_key'] ?? ''),
        'label' => $label,
        'url' => $url,
    ];

    return $popup;
}

function sr_popup_layer_cookie_name(int $popupId): string
{
    return 'sr_popup_layer_' . $popupId . '_dismissed';
}

function sr_popup_layer_default_slot_key(): string
{
    return 'overlay';
}

function sr_popup_layer_close_script(): string
{
    static $printed = false;
    if ($printed) {
        return '';
    }

    $printed = true;

    return '<script src="' . sr_e(sr_url('/modules/popup_layer/assets/saanraan-popup-layer.js')) . '" defer></script>';
}

function sr_popup_layer_clean_single_line(string $value, int $maxLength): string
{
    return sr_clean_single_line($value, $maxLength);
}

function sr_popup_layer_clean_text(string $value, int $maxLength): string
{
    return sr_clean_text($value, $maxLength);
}

function sr_popup_layer_clean_admin_datetime(string $value): ?string
{
    return sr_clean_admin_datetime($value, false);
}

function sr_popup_layer_admin_datetime_value(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    if (preg_match('/\A(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})/', $value, $matches) !== 1) {
        return '';
    }

    return $matches[1] . 'T' . $matches[2];
}

function sr_popup_layer_time_html(string $value): string
{
    return sr_relative_time_html($value);
}

function sr_popup_layer_clean_subject_id(string $value): string
{
    $value = sr_popup_layer_clean_single_line($value, 80);
    if ($value === '') {
        return '';
    }

    return preg_match('/\A[a-zA-Z0-9_.:-]+\z/', $value) === 1 ? $value : '';
}

function sr_popup_layer_is_safe_key(string $value, int $maxLength): bool
{
    if ($value === '' || strlen($value) > $maxLength) {
        return false;
    }

    return preg_match('/\A[a-z0-9][a-z0-9_.-]*\z/', $value) === 1;
}
