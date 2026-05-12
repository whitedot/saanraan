<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_require_login($pdo);
toy_require_csrf();

$commentIdValue = toy_post_string('comment_id', 20);
$commentId = preg_match('/\A[1-9][0-9]*\z/', $commentIdValue) === 1 ? (int) $commentIdValue : 0;
$comment = toy_community_admin_comment_by_id($pdo, $commentId);
if (!is_array($comment)) {
    toy_render_error(404, '댓글을 찾을 수 없습니다.');
}

$post = toy_community_post_for_read($pdo, (int) $comment['post_id'], $account);
if (!is_array($post)) {
    toy_render_error(404, '게시글을 찾을 수 없습니다.');
}

if (!toy_community_account_can_delete_comment($comment, $account)) {
    toy_render_error(403, '이 댓글을 삭제할 수 없습니다.');
}

toy_community_update_comment_status($pdo, $commentId, 'deleted');
$levelSnapshot = toy_community_recalculate_account_level($pdo, (int) $comment['author_account_id'], null, 'comment_deleted');
toy_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'community.comment.deleted_by_author',
    'target_type' => 'community_comment',
    'target_id' => (string) $commentId,
    'result' => 'success',
    'message' => 'Community comment deleted by author.',
    'metadata' => [
        'post_id' => (int) $comment['post_id'],
        'before_status' => (string) $comment['status'],
        'after_status' => 'deleted',
        'community_level_value' => (int) ($levelSnapshot['level_value'] ?? 0),
        'community_score_value' => (int) ($levelSnapshot['score_value'] ?? 0),
    ],
]);
$_SESSION['toy_community_comment_notice'] = '댓글을 삭제했습니다.';
toy_redirect('/community/post?id=' . (string) $comment['post_id'] . '#comments');
