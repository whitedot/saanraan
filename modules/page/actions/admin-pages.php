<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/page/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$notice = $_SESSION['sr_page_admin_notice'] ?? '';
unset($_SESSION['sr_page_admin_notice']);
$errors = [];
$pageAdminPage = isset($pageAdminPage) ? (string) $pageAdminPage : 'list';
$editPage = null;
$values = [];

if ($pageAdminPage === 'form') {
    $pageId = (int) sr_get_string('id', 20);
    if ($pageId > 0) {
        $editPage = sr_page_by_id($pdo, $pageId);
        if (!is_array($editPage)) {
            sr_render_error(404, '수정할 페이지를 찾을 수 없습니다.');
        }
    }
} else {
    $filters = sr_page_admin_filters();
    $pages = sr_page_admin_list($pdo, $filters);
}

include SR_ROOT . '/modules/page/views/admin-pages.php';
