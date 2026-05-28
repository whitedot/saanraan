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
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content', 'edit');
sr_require_csrf();

$pageId = (int) sr_post_string('content_id', 20);
$values = sr_content_input_values($pdo);
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
$errors = sr_content_validate_input($pdo, $values, $pageId, $publicBannerIds, $publicPopupLayerIds);
if ($pageId > 0 && !is_array(sr_content_by_id($pdo, $pageId))) {
    $errors[] = '수정할 콘텐츠를 찾을 수 없습니다.';
}
$errors = array_merge($errors, sr_content_validate_file_request($pdo, $pageId, $values));

if ($errors !== []) {
    $values['content_file_link_ids'] = sr_content_file_link_ids_from_post('content_file_link_ids');
    $_SESSION['sr_content_admin_errors'] = $errors;
    $_SESSION['sr_content_admin_values'] = $values;
    sr_redirect($pageId > 0 ? '/admin/content/edit?id=' . (string) $pageId : '/admin/content/new');
}

$beforeAssetSettings = $pageId > 0 ? sr_content_asset_settings_from_storage_for_audit($pdo, $pageId) : [];
$savedPageId = sr_content_save($pdo, $values, (int) $account['id'], $pageId);
try {
    sr_content_save_files_from_request($pdo, $savedPageId, (int) $account['id'], $values);
} catch (Throwable $exception) {
    if (function_exists('sr_log_exception')) {
        sr_log_exception($exception, 'content_file_save_failed');
    }

    $_SESSION['sr_content_admin_errors'] = ['콘텐츠는 저장했지만 파일 저장에 실패했습니다: ' . $exception->getMessage()];
    sr_redirect('/admin/content/edit?id=' . (string) $savedPageId);
}
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'admin',
    'event_type' => $pageId > 0 ? 'content.updated' : 'content.created',
    'target_type' => 'content',
    'target_id' => (string) $savedPageId,
    'result' => 'success',
    'message' => $pageId > 0 ? 'Content updated.' : 'Content created.',
    'metadata' => [
        'slug' => (string) $values['slug'],
        'status' => (string) $values['status'],
        'content_group_id' => (int) ($values['content_group_id'] ?? 0),
        'layout_key' => (string) ($values['layout_key'] ?? ''),
    ],
]);
if ($pageId > 0) {
    sr_admin_audit_asset_settings_update($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => 'content.asset_settings.updated',
        'target_type' => 'content',
        'target_id' => (string) $savedPageId,
        'asset_settings_scope' => 'content',
        'before_asset_settings' => $beforeAssetSettings,
        'after_asset_settings' => sr_content_asset_settings_from_storage_for_audit($pdo, $savedPageId),
        'message' => 'Content asset settings updated.',
        'metadata' => [
            'slug' => (string) $values['slug'],
        ],
    ]);
}

$_SESSION['sr_content_admin_notice'] = $pageId > 0 ? '콘텐츠를 저장했습니다.' : '콘텐츠를 만들었습니다.';
sr_redirect($pageId > 0 ? '/admin/content/edit?id=' . (string) $savedPageId : '/admin/content');
