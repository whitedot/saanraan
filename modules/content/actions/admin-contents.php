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
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content', 'view');

$notice = $_SESSION['sr_content_admin_notice'] ?? '';
unset($_SESSION['sr_content_admin_notice']);
$errors = [];
$pageAdminPage = isset($pageAdminPage) ? (string) $pageAdminPage : 'list';
$editPage = null;
$contentFiles = [];
$downloadFiles = [];
$linkedDownloadFileIds = [];
$values = [];
$publicBanners = function_exists('sr_banner_public_banners') && sr_module_enabled($pdo, 'banner')
    ? sr_banner_public_banners($pdo)
    : [];
$publicPopupLayers = function_exists('sr_popup_layer_public_layers') && sr_module_enabled($pdo, 'popup_layer')
    ? sr_popup_layer_public_layers($pdo)
    : [];
$assetModuleOptions = sr_content_asset_module_options($pdo);
$assetPolicySets = sr_content_asset_policy_sets($pdo);
$publicLayoutOptions = sr_public_layout_options($pdo);
$pageGroups = sr_content_groups($pdo);
$memberGroups = function_exists('sr_member_groups') ? sr_member_groups($pdo) : [];
$contentSeriesOptions = sr_content_series_list($pdo);
$currentContentSeriesItem = null;
$pageGroupIds = [];
foreach ($pageGroups as $pageGroup) {
    $pageGroupIds[(int) ($pageGroup['id'] ?? 0)] = true;
}

if ($pageAdminPage === 'form') {
    $downloadFiles = sr_content_all_active_download_files($pdo);
    $pageId = (int) sr_get_string('id', 20);
    if ($pageId > 0) {
        $editPage = sr_content_by_id($pdo, $pageId);
        if (!is_array($editPage)) {
            sr_render_error(404, sr_t('content::action.error.content_edit_not_found'));
        }
        $editPage['setting_sources'] = sr_content_setting_sources($pdo, $pageId);
        $contentFiles = sr_content_files_for_content($pdo, $pageId);
        $linkedDownloadFileIds = sr_content_linked_file_ids($pdo, $pageId);
        $currentContentSeriesItem = sr_content_active_series_item_for_content($pdo, $pageId);
    } else {
        $newContentGroupValue = sr_get_string('content_group_id', 20);
        $newContentGroupId = preg_match('/\A[1-9][0-9]*\z/', $newContentGroupValue) === 1 ? (int) $newContentGroupValue : 0;
        if ($newContentGroupId > 0 && !isset($pageGroupIds[$newContentGroupId])) {
            $newContentGroupId = 0;
        }

        $newContentGroupSettings = $newContentGroupId > 0 ? sr_content_group_settings($pdo, $newContentGroupId) : [];
        $values = sr_content_default_values($pdo, $site ?? null, $newContentGroupSettings);
        $values['content_group_id'] = $newContentGroupId;
    }
} else {
    $filters = sr_content_admin_filters();
    if ((int) ($filters['content_group_id'] ?? 0) > 0 && !is_array(sr_content_group_by_id($pdo, (int) $filters['content_group_id']))) {
        $filters['content_group_id'] = 0;
    }
    $contentSort = sr_content_admin_sort_from_request();
    $pageStatusCounts = sr_content_admin_status_counts($pdo);
    $pagePagination = sr_admin_pagination_from_total($pdo, sr_content_admin_count($pdo, $filters));
    $pages = sr_content_admin_list($pdo, $filters, (int) $pagePagination['per_page'], sr_admin_pagination_offset($pagePagination), $contentSort);
}

include SR_ROOT . '/modules/content/views/admin-contents.php';
