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
$settings = sr_community_settings($pdo);
$boardRequiresVerificationLogin = sr_community_board_requires_verification_login($pdo, $board, $settings, 'enter');
if (!is_array($account) && (sr_community_board_requires_login($board) || $boardRequiresVerificationLogin)) {
    $account = sr_member_require_login($pdo);
}
if (!sr_community_account_can_read_board($pdo, $board, is_array($account) ? $account : null)) {
    sr_render_error(403, sr_t('community::action.error.board_view_forbidden'));
}
$communityBoardIdentityPolicy = sr_community_identity_action_policy(
    $pdo,
    $board,
    is_array($account) ? $account : null,
    'enter',
    '/community/board?key=' . rawurlencode($boardKey),
    $settings
);
if (!empty($communityBoardIdentityPolicy['required']) && empty($communityBoardIdentityPolicy['satisfied'])) {
    sr_render_error(403, sr_community_identity_action_error_message('enter', (string) ($communityBoardIdentityPolicy['purpose'] ?? 'real_name')));
}
$isAdminWriter = is_array($account) && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'edit');
$canManageBoard = is_array($account) && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/boards', 'view');
$canViewMemberIdentifiers = sr_community_admin_can_view_member_identifiers($pdo, is_array($account) ? $account : null);
$canWriteBoard = sr_community_account_can_write_board($pdo, $board, is_array($account) ? $account : null, $isAdminWriter)
    || sr_community_account_can_write_notice($pdo, $board, is_array($account) ? $account : null, $isAdminWriter);
$postsPerPage = sr_community_board_list_per_page($pdo, $board, $settings);
$listDefaultSort = sr_community_board_list_default_sort($pdo, $board);
$listExcerptEnabled = sr_community_board_list_excerpt_enabled($pdo, $board);
$listExcerptLength = sr_community_board_list_excerpt_length($pdo, $board);
$keywordValue = sr_get_string_without_truncation('q', 100);
$keyword = is_string($keywordValue) ? trim($keywordValue) : '';
$searchField = sr_community_board_search_field(sr_get_string('search_field', 20));
$categoryEnabled = sr_community_board_category_enabled($pdo, (int) $board['id']);
$categories = $categoryEnabled ? sr_community_categories($pdo, (int) $board['id'], true) : [];
$categoryKey = $categoryEnabled ? sr_get_string('category', 60) : '';
$selectedCategory = null;
$categoryInvalid = false;
if ($categoryKey !== '') {
    $selectedCategory = sr_community_category_by_key($pdo, (int) $board['id'], $categoryKey);
    $categoryInvalid = !is_array($selectedCategory) || (string) $selectedCategory['status'] !== 'enabled';
}
$selectedCategoryId = is_array($selectedCategory) && !$categoryInvalid ? (int) $selectedCategory['id'] : 0;
$authorHash = strtolower(trim(sr_get_string('author', 40)));
$authorFilterAccount = sr_member_public_account_hash_is_valid($authorHash)
    ? sr_member_public_account_summary_by_hash($pdo, sr_runtime_config(), $authorHash)
    : null;
$authorFilterAccountId = is_array($authorFilterAccount) && !in_array((string) ($authorFilterAccount['status'] ?? ''), ['withdrawn', 'anonymized'], true)
    ? (int) ($authorFilterAccount['id'] ?? 0)
    : 0;
$pageValue = sr_get_string('page', 20);
$page = preg_match('/\A[1-9][0-9]*\z/', $pageValue) === 1 ? (int) $pageValue : 1;
$postCount = $categoryInvalid ? 0 : sr_community_board_post_count($pdo, (int) $board['id'], $keyword, $selectedCategoryId, $authorFilterAccountId, $searchField);
$totalPages = max(1, (int) ceil($postCount / $postsPerPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$posts = $categoryInvalid ? [] : sr_community_board_posts($pdo, (int) $board['id'], $postsPerPage, ($page - 1) * $postsPerPage, $keyword, $selectedCategoryId, $listDefaultSort, $authorFilterAccountId, $searchField);
$boardNotice = '';
if (isset($_SESSION['sr_community_board_notice']) && is_string($_SESSION['sr_community_board_notice'])) {
    $boardNotice = $_SESSION['sr_community_board_notice'];
}
unset($_SESSION['sr_community_board_notice']);
$memberFollowFeedback = isset($_SESSION['sr_member_follow_feedback']) && is_array($_SESSION['sr_member_follow_feedback'])
    ? $_SESSION['sr_member_follow_feedback']
    : ['notice' => '', 'errors' => []];
unset($_SESSION['sr_member_follow_feedback']);
$skinKey = sr_community_board_skin_key($pdo, $board);
$skinView = sr_community_skin_view($skinKey, 'list');

$communityThemeFallbackViewFile = $skinView;
include sr_community_public_view_file($pdo, $settings, 'list.php', $skinView);
