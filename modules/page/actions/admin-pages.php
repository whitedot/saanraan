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

$notice = $_SESSION['sr_page_admin_notice'] ?? '';
unset($_SESSION['sr_page_admin_notice']);
$errors = [];
$pageAdminPage = isset($pageAdminPage) ? (string) $pageAdminPage : 'list';
$editPage = null;
$pageFiles = [];
$values = [];
$publicBanners = function_exists('sr_banner_public_banners') && sr_module_enabled($pdo, 'banner')
    ? sr_banner_public_banners($pdo)
    : [];
$publicPopupLayers = function_exists('sr_popup_layer_public_layers') && sr_module_enabled($pdo, 'popup_layer')
    ? sr_popup_layer_public_layers($pdo)
    : [];
$assetModuleOptions = sr_page_asset_module_options($pdo);
$publicLayoutOptions = sr_public_layout_options($pdo);

if ($pageAdminPage === 'form') {
    $pageId = (int) sr_get_string('id', 20);
    if ($pageId > 0) {
        $editPage = sr_page_by_id($pdo, $pageId);
        if (!is_array($editPage)) {
            sr_render_error(404, '수정할 페이지를 찾을 수 없습니다.');
        }
        $pageFiles = sr_page_files_for_page($pdo, $pageId);
    }
} else {
    $filters = sr_page_admin_filters();
    $pageStatusCounts = sr_page_admin_status_counts($pdo);
    $pages = sr_page_admin_list($pdo, $filters);
}

include SR_ROOT . '/modules/page/views/admin-pages.php';
