<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$postIdValue = toy_get_string('id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$post = toy_community_public_post($pdo, $postId);
if (!is_array($post)) {
    toy_render_error(404, '게시글을 찾을 수 없습니다.');
}

$settings = toy_module_settings($pdo, 'community');
$commentsPerPage = max(1, min(100, (int) ($settings['comments_per_page'] ?? 50)));
$comments = toy_community_public_comments($pdo, (int) $post['id'], $commentsPerPage);
$account = toy_member_current_account($pdo);
$canComment = is_array($account) && toy_community_account_can_comment_post($pdo, $post, $account);
$isScrapped = is_array($account) && toy_community_account_has_scrap($pdo, (int) $account['id'], (int) $post['id']);
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
