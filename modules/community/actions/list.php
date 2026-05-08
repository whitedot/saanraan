<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$boardKey = toy_get_string('key', 60);
$board = toy_community_board_by_key($pdo, $boardKey);
if (!is_array($board) || (string) $board['status'] !== 'enabled') {
    toy_render_error(404, '게시판을 찾을 수 없습니다.');
}
$account = toy_member_current_account($pdo);
if (!is_array($account) && toy_community_board_requires_login($board)) {
    $account = toy_member_require_login($pdo);
}
if (!toy_community_account_can_read_board($pdo, $board, is_array($account) ? $account : null)) {
    toy_render_error(403, '이 게시판을 볼 수 없습니다.');
}

$settings = toy_module_settings($pdo, 'community');
$postsPerPage = max(1, min(100, (int) ($settings['posts_per_page'] ?? 20)));
$keywordValue = toy_get_string_without_truncation('q', 100);
$keyword = is_string($keywordValue) ? trim($keywordValue) : '';
$pageValue = toy_get_string('page', 20);
$page = preg_match('/\A[1-9][0-9]*\z/', $pageValue) === 1 ? (int) $pageValue : 1;
$postCount = toy_community_public_post_count($pdo, (int) $board['id'], $keyword);
$totalPages = max(1, (int) ceil($postCount / $postsPerPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$posts = toy_community_public_posts($pdo, (int) $board['id'], $postsPerPage, ($page - 1) * $postsPerPage, $keyword);
$boardNotice = '';
if (isset($_SESSION['toy_community_board_notice']) && is_string($_SESSION['toy_community_board_notice'])) {
    $boardNotice = $_SESSION['toy_community_board_notice'];
}
unset($_SESSION['toy_community_board_notice']);
$skinKey = toy_community_skin_key();
$skinView = toy_community_skin_view($skinKey, 'list');

include $skinView;
