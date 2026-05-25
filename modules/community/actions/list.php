<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$boardKey = sr_get_string('key', 60);
$board = sr_community_board_by_key($pdo, $boardKey);
if (!is_array($board) || (string) $board['status'] !== 'enabled') {
    sr_render_error(404, sr_t('community::action.error.board_not_found'));
}
$account = sr_member_current_account($pdo);
if (!is_array($account) && sr_community_board_requires_login($board)) {
    $account = sr_member_require_login($pdo);
}
if (!sr_community_account_can_read_board($pdo, $board, is_array($account) ? $account : null)) {
    sr_render_error(403, sr_t('community::action.error.board_view_forbidden'));
}
$isAdminWriter = is_array($account) && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'edit');
$canViewMemberIdentifiers = sr_community_admin_can_view_member_identifiers($pdo, is_array($account) ? $account : null);
$canWriteBoard = is_array($account) && sr_community_account_can_write_board($pdo, $board, $account, $isAdminWriter);

$settings = sr_community_settings($pdo);
if (is_array($account)) {
    sr_community_require_member_nickname($pdo, $account, $settings, (string) ($_SERVER['REQUEST_URI'] ?? '/community'));
}
$postsPerPage = max(1, min(100, (int) ($settings['posts_per_page'] ?? 20)));
$keywordValue = sr_get_string_without_truncation('q', 100);
$keyword = is_string($keywordValue) ? trim($keywordValue) : '';
$pageValue = sr_get_string('page', 20);
$page = preg_match('/\A[1-9][0-9]*\z/', $pageValue) === 1 ? (int) $pageValue : 1;
$postCount = sr_community_board_post_count($pdo, (int) $board['id'], $keyword);
$totalPages = max(1, (int) ceil($postCount / $postsPerPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$posts = sr_community_board_posts($pdo, (int) $board['id'], $postsPerPage, ($page - 1) * $postsPerPage, $keyword);
$boardNotice = '';
if (isset($_SESSION['sr_community_board_notice']) && is_string($_SESSION['sr_community_board_notice'])) {
    $boardNotice = $_SESSION['sr_community_board_notice'];
}
unset($_SESSION['sr_community_board_notice']);
$skinKey = sr_community_board_skin_key($pdo, $board);
$skinView = sr_community_skin_view($skinKey, 'list');

include $skinView;
