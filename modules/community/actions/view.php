<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$postIdValue = toy_get_string('id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$account = toy_member_current_account($pdo);
$post = toy_community_post_for_read($pdo, $postId, is_array($account) ? $account : null);
if (!is_array($post)) {
    $rawPost = toy_community_admin_post_by_id($pdo, $postId);
    if (is_array($rawPost)) {
        $board = toy_community_board_by_id($pdo, (int) $rawPost['board_id']);
        if (is_array($board) && toy_community_board_requires_login($board) && !is_array($account)) {
            $account = toy_member_require_login($pdo);
            $post = toy_community_post_for_read($pdo, $postId, $account);
        }
        if (!is_array($post)
            && is_array($board)
            && (string) $rawPost['status'] === 'published'
            && (string) $board['status'] === 'enabled'
            && !toy_community_account_can_read_board($pdo, $board, is_array($account) ? $account : null)
        ) {
            toy_render_error(403, '이 게시글을 볼 수 없습니다.');
        }
    }
}
if (!is_array($post)) {
    toy_render_error(404, '게시글을 찾을 수 없습니다.');
}
toy_community_increment_post_view_count($pdo, (int) $post['id']);
$post['view_count'] = (int) $post['view_count'] + 1;

$settings = toy_module_settings($pdo, 'community');
$commentsPerPage = max(1, min(100, (int) ($settings['comments_per_page'] ?? 50)));
$comments = toy_community_public_comments($pdo, (int) $post['id'], $commentsPerPage);
$attachments = toy_community_post_attachments($pdo, (int) $post['id']);
$canComment = is_array($account) && toy_community_account_can_comment_post($pdo, $post, $account);
$isScrapped = is_array($account) && toy_community_account_has_scrap($pdo, (int) $account['id'], (int) $post['id']);
$reportReasonKeys = toy_community_report_reason_keys();
$postNotice = '';
if (isset($_SESSION['toy_community_post_notice']) && is_string($_SESSION['toy_community_post_notice'])) {
    $postNotice = $_SESSION['toy_community_post_notice'];
}
if (isset($_SESSION['toy_community_scrap_notice']) && is_string($_SESSION['toy_community_scrap_notice'])) {
    $postNotice = $_SESSION['toy_community_scrap_notice'];
}
unset($_SESSION['toy_community_post_notice'], $_SESSION['toy_community_scrap_notice']);
$reportErrors = [];
$reportNotice = '';
if (isset($_SESSION['toy_community_report_errors']) && is_array($_SESSION['toy_community_report_errors'])) {
    foreach ($_SESSION['toy_community_report_errors'] as $error) {
        if (is_string($error) && $error !== '') {
            $reportErrors[] = $error;
        }
    }
}
if (isset($_SESSION['toy_community_report_notice']) && is_string($_SESSION['toy_community_report_notice'])) {
    $reportNotice = $_SESSION['toy_community_report_notice'];
}
unset($_SESSION['toy_community_report_errors'], $_SESSION['toy_community_report_notice']);
$commentErrors = [];
$commentBody = '';
if (isset($_SESSION['toy_community_comment_errors']) && is_array($_SESSION['toy_community_comment_errors'])) {
    foreach ($_SESSION['toy_community_comment_errors'] as $error) {
        if (is_string($error) && $error !== '') {
            $commentErrors[] = $error;
        }
    }
}
if (isset($_SESSION['toy_community_comment_body']) && is_string($_SESSION['toy_community_comment_body'])) {
    $commentBody = $_SESSION['toy_community_comment_body'];
}
unset($_SESSION['toy_community_comment_errors'], $_SESSION['toy_community_comment_body']);
$skinKey = toy_community_skin_key();
$skinView = toy_community_skin_view($skinKey, 'post');

include $skinView;
