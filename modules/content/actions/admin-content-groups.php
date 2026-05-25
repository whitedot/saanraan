<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';
if (is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content-groups', 'view');

$errors = [];
$notice = $_SESSION['sr_content_group_admin_notice'] ?? '';
unset($_SESSION['sr_content_group_admin_notice']);
$sessionErrors = $_SESSION['sr_content_group_admin_errors'] ?? [];
$sessionValues = $_SESSION['sr_content_group_admin_values'] ?? [];
unset($_SESSION['sr_content_group_admin_errors'], $_SESSION['sr_content_group_admin_values']);
if (is_array($sessionErrors)) {
    $errors = array_merge($errors, array_map('strval', $sessionErrors));
}

$pageGroupsPage = isset($pageGroupsPage) ? (string) $pageGroupsPage : 'list';
if (!in_array($pageGroupsPage, ['list', 'new', 'edit'], true)) {
    $pageGroupsPage = 'list';
}

$allowedGroupStatuses = sr_content_group_statuses();
$assetModuleOptions = sr_content_asset_module_options($pdo);
$publicLayoutOptions = sr_public_layout_options($pdo);
$publicBanners = function_exists('sr_banner_public_banners') && sr_module_enabled($pdo, 'banner')
    ? sr_banner_public_banners($pdo)
    : [];
$publicBannerIds = [];
foreach ($publicBanners as $publicBanner) {
    $publicBannerIds[(int) $publicBanner['id']] = true;
}
$publicPopupLayers = function_exists('sr_popup_layer_public_layers') && sr_module_enabled($pdo, 'popup_layer')
    ? sr_popup_layer_public_layers($pdo)
    : [];
$publicPopupLayerIds = [];
foreach ($publicPopupLayers as $publicPopupLayer) {
    $publicPopupLayerIds[(int) $publicPopupLayer['id']] = true;
}
$editPageGroup = null;
if ($pageGroupsPage === 'edit') {
    $groupId = (int) sr_get_string('id', 20);
    $editPageGroup = sr_content_group_by_id($pdo, $groupId);
    if (!is_array($editPageGroup)) {
        sr_render_error(404, sr_t('content::action.error.content_group_edit_not_found'));
    }
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content-groups', 'edit');
    $intent = sr_post_string('intent', 40);
    $groupId = (int) sr_post_string('group_id', 20);
    $isUpdate = $intent === 'update_group';

    if (!in_array($intent, ['create_group', 'update_group'], true)) {
        $errors[] = '요청한 작업이 올바르지 않습니다.';
    }

    $existing = $isUpdate ? sr_content_group_by_id($pdo, $groupId) : null;
    if ($isUpdate && !is_array($existing)) {
        $errors[] = '수정할 콘텐츠 그룹을 찾을 수 없습니다.';
    }

    $groupKey = $isUpdate && is_array($existing) ? (string) ($existing['group_key'] ?? '') : sr_content_clean_slug(sr_post_string('group_key', 60));
    $title = sr_content_clean_single_line(sr_post_string('title', 120), 120);
    $description = sr_content_clean_text(sr_post_string('description', 2000), 2000);
    $status = sr_post_string('status', 30);
    $sortOrder = sr_admin_post_int_in_range('sort_order', 0, 1000000);
    $groupSettings = [
        'status' => sr_post_string('group_content_status', 30),
        'layout_key' => sr_public_layout_normalize_key(sr_post_string('group_layout_key', 80)),
        'asset_access_enabled' => sr_post_string('group_asset_access_enabled', 1) === '1' ? 1 : 0,
        'asset_module' => sr_content_asset_module_value_from_keys(sr_content_asset_module_keys_from_value($_POST['group_asset_module'] ?? '')),
        'asset_access_amount' => (int) sr_post_string('group_asset_access_amount', 20),
        'asset_charge_policy' => sr_content_clean_slug(sr_post_string('group_asset_charge_policy', 20)),
        'asset_action_enabled' => sr_post_string('group_asset_action_enabled', 1) === '1' ? 1 : 0,
        'asset_action_module' => sr_content_asset_module_value_from_keys(sr_content_asset_module_keys_from_value($_POST['group_asset_action_module'] ?? '')),
        'asset_action_amount' => (int) sr_post_string('group_asset_action_amount', 20),
        'asset_action_direction' => sr_content_clean_slug(sr_post_string('group_asset_action_direction', 20)),
        'asset_action_label' => sr_content_clean_single_line(sr_post_string('group_asset_action_label', 80), 80),
        'file_asset_download_enabled' => sr_post_string('group_file_asset_download_enabled', 1) === '1' ? 1 : 0,
        'file_asset_module' => sr_content_asset_module_value_from_keys(sr_content_asset_module_keys_from_value($_POST['group_file_asset_module'] ?? '')),
        'file_asset_download_amount' => (int) sr_post_string('group_file_asset_download_amount', 20),
        'file_asset_charge_policy' => sr_content_clean_slug(sr_post_string('group_file_asset_charge_policy', 20)),
    ];
    foreach (sr_content_public_display_setting_labels() as $settingKey => $settingLabel) {
        $rawValue = sr_post_string('group_' . $settingKey, 20);
        $groupSettings[$settingKey] = preg_match('/\A[0-9]{1,9}\z/', $rawValue) === 1 ? (int) $rawValue : -1;
    }
    $groupSettings = sr_content_normalize_asset_values($groupSettings, false);
    $groupFileAssetSettings = sr_content_normalize_file_asset_values([
        'asset_download_enabled' => $groupSettings['file_asset_download_enabled'] ?? 0,
        'asset_module' => $groupSettings['file_asset_module'] ?? '',
        'asset_download_amount' => $groupSettings['file_asset_download_amount'] ?? 0,
        'asset_charge_policy' => $groupSettings['file_asset_charge_policy'] ?? 'once',
    ], false);
    $groupSettings['file_asset_download_enabled'] = (int) ($groupFileAssetSettings['asset_download_enabled'] ?? 0);
    $groupSettings['file_asset_module'] = (string) ($groupFileAssetSettings['asset_module'] ?? '');
    $groupSettings['file_asset_download_amount'] = (int) ($groupFileAssetSettings['asset_download_amount'] ?? 0);
    $groupSettings['file_asset_charge_policy'] = (string) ($groupFileAssetSettings['asset_charge_policy'] ?? 'once');

    if (!$isUpdate && !sr_content_group_key_is_valid($groupKey)) {
        $errors[] = '그룹 key는 영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 사용할 수 있습니다.';
    } elseif (!$isUpdate && sr_content_group_key_exists($pdo, $groupKey)) {
        $errors[] = '이미 사용 중인 그룹 key입니다.';
    }

    if ($title === '') {
        $errors[] = '그룹 이름을 입력하세요.';
    }

    if (!in_array($status, $allowedGroupStatuses, true)) {
        $errors[] = '상태 값이 올바르지 않습니다.';
    }

    if (!in_array((string) ($groupSettings['status'] ?? ''), sr_content_allowed_statuses(), true)) {
        $errors[] = '그룹 기본 콘텐츠 상태 값이 올바르지 않습니다.';
    }

    if ((string) ($groupSettings['layout_key'] ?? '') !== '' && !isset($publicLayoutOptions[(string) $groupSettings['layout_key']])) {
        $errors[] = '그룹 기본 콘텐츠 레이아웃 값이 올바르지 않습니다.';
    }

    if ($sortOrder === null) {
        $errors[] = '정렬 순서는 0 이상의 정수여야 합니다.';
        $sortOrder = 0;
    }

    $settingErrors = sr_content_validate_input(
        $pdo,
        array_merge([
            'title' => 'group-setting',
            'content_group_id' => 0,
            'content_group_scope' => 'here_only',
            'slug' => 'group-setting',
            'status' => 'draft',
            'layout_key' => '',
            'body_format' => 'plain',
            'body_text' => '',
            'summary' => '',
            'seo_title' => '',
            'seo_description' => '',
        ], $groupSettings),
        0,
        $publicBannerIds,
        $publicPopupLayerIds
    );
    foreach ($settingErrors as $settingError) {
        if (str_contains($settingError, 'slug')) {
            continue;
        }
        $errors[] = '그룹 기본 설정: ' . $settingError;
    }

    foreach (sr_content_file_asset_validation_errors($pdo, $groupFileAssetSettings, '그룹 기본 새 파일 다운로드') as $settingError) {
        $errors[] = $settingError;
    }

    $values = [
        'id' => $groupId,
        'group_key' => $groupKey,
        'title' => $title,
        'description' => $description,
        'status' => $status,
        'sort_order' => (int) $sortOrder,
        'group_settings' => $groupSettings,
    ];

    if ($errors !== []) {
        $_SESSION['sr_content_group_admin_errors'] = $errors;
        $_SESSION['sr_content_group_admin_values'] = $values;
        sr_redirect($isUpdate && $groupId > 0 ? '/admin/content-groups/edit?id=' . (string) $groupId : '/admin/content-groups/new');
    }

    $beforeAssetSettings = $isUpdate ? sr_content_group_asset_settings_from_storage_for_audit($pdo, $groupId) : [];
    if ($isUpdate) {
        sr_content_update_group($pdo, $groupId, $values);
        $savedGroupId = $groupId;
    } else {
        $savedGroupId = sr_content_create_group($pdo, $values);
    }

    foreach ($groupSettings as $settingKey => $settingValue) {
        if (!in_array((string) $settingKey, sr_content_group_setting_keys(), true)) {
            continue;
        }

        $valueType = is_int($settingValue) ? 'int' : 'string';
        sr_content_set_group_setting($pdo, $savedGroupId, (string) $settingKey, (string) $settingValue, $valueType);
    }

    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => $isUpdate ? 'content_group.updated' : 'content_group.created',
        'target_type' => 'content_group',
        'target_id' => (string) $savedGroupId,
        'result' => 'success',
        'message' => $isUpdate ? 'Content group updated.' : 'Content group created.',
        'metadata' => [
            'group_key' => $groupKey,
            'status' => $status,
        ],
    ]);
    if ($isUpdate) {
        sr_admin_audit_asset_settings_update($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'content_group.asset_settings.updated',
            'target_type' => 'content_group',
            'target_id' => (string) $savedGroupId,
            'asset_settings_scope' => 'content_group',
            'before_asset_settings' => $beforeAssetSettings,
            'after_asset_settings' => sr_content_group_asset_settings_for_audit($groupSettings),
            'message' => 'Content group asset settings updated.',
            'metadata' => [
                'group_key' => $groupKey,
            ],
        ]);
    }

    $_SESSION['sr_content_group_admin_notice'] = $isUpdate ? '콘텐츠 그룹을 저장했습니다.' : '콘텐츠 그룹을 만들었습니다.';
    sr_redirect($isUpdate ? '/admin/content-groups/edit?id=' . (string) $savedGroupId : '/admin/content-groups');
}

$pageGroupFilters = sr_content_admin_group_filters();
$pageGroupStatusCounts = sr_content_admin_group_status_counts($pdo);
$pageGroups = $pageGroupsPage === 'list' ? sr_content_admin_group_list($pdo, $pageGroupFilters) : [];
$values = is_array($sessionValues) ? $sessionValues : [];
$pageGroupSettings = [];
if ($pageGroupsPage === 'edit' && is_array($editPageGroup)) {
    $pageGroupSettings = sr_content_group_settings($pdo, (int) $editPageGroup['id']);
}

include SR_ROOT . '/modules/content/views/admin-content-groups.php';
