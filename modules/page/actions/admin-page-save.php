<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/page/helpers.php';
if (is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);
sr_require_csrf();

$pageId = (int) sr_post_string('page_id', 20);
$values = sr_page_input_values();
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
$errors = sr_page_validate_input($pdo, $values, $pageId, $publicBannerIds, $publicPopupLayerIds);
if ($pageId > 0 && !is_array(sr_page_by_id($pdo, $pageId))) {
    $errors[] = '수정할 페이지를 찾을 수 없습니다.';
}

if ($errors !== []) {
    $_SESSION['sr_page_admin_errors'] = $errors;
    $_SESSION['sr_page_admin_values'] = $values;
    sr_redirect($pageId > 0 ? '/admin/pages/edit?id=' . (string) $pageId : '/admin/pages/new');
}

$savedPageId = sr_page_save($pdo, $values, (int) $account['id'], $pageId);
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'admin',
    'event_type' => $pageId > 0 ? 'page.updated' : 'page.created',
    'target_type' => 'page',
    'target_id' => (string) $savedPageId,
    'result' => 'success',
    'message' => $pageId > 0 ? 'Page updated.' : 'Page created.',
    'metadata' => [
        'slug' => (string) $values['slug'],
        'status' => (string) $values['status'],
    ],
]);

$_SESSION['sr_page_admin_notice'] = $pageId > 0 ? '페이지를 저장했습니다.' : '페이지를 만들었습니다.';
sr_redirect('/admin/pages/edit?id=' . (string) $savedPageId);
