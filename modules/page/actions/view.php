<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/page/helpers.php';
require_once SR_ROOT . '/modules/member/helpers.php';
if (is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}

$slug = sr_page_slug_from_request_path();
$page = $slug !== '' ? sr_page_published_by_slug($pdo, $slug) : null;
if (!is_array($page)) {
    sr_render_error(404, '요청한 페이지를 찾을 수 없습니다.');
}
$page = sr_page_with_effective_settings($pdo, $page);

$pageAccess = ['allowed' => true, 'charged' => false, 'message' => ''];
if (sr_page_asset_access_required($page)) {
    $account = sr_member_require_login($pdo);
    $pageAccess = sr_page_charge_view_access($pdo, $page, (int) $account['id']);
}

$pageFiles = sr_page_files_for_page($pdo, (int) $page['id']);
$pageActionNotice = $_SESSION['sr_page_action_notice'] ?? '';
$pageActionErrors = $_SESSION['sr_page_action_errors'] ?? [];
unset($_SESSION['sr_page_action_notice'], $_SESSION['sr_page_action_errors']);

include SR_ROOT . '/modules/page/views/page.php';
