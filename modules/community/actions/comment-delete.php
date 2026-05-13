<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$commentIdValue = sr_post_string('comment_id', 20);
$commentId = preg_match('/\A[1-9][0-9]*\z/', $commentIdValue) === 1 ? (int) $commentIdValue : 0;
$comment = sr_community_admin_comment_by_id($pdo, $commentId);
if (!is_array($comment)) {
    sr_render_error(404, '댓글을 찾을 수 없습니다.');
}

$post = sr_community_post_for_read($pdo, (int) $comment['post_id'], $account);
if (!is_array($post)) {
    sr_render_error(404, '게시글을 찾을 수 없습니다.');
}

if (!sr_community_account_can_delete_comment($comment, $account)) {
    sr_render_error(403, '이 댓글을 삭제할 수 없습니다.');
}

sr_community_update_comment_status($pdo, $commentId, 'deleted');
$levelSnapshot = sr_community_maybe_recalculate_account_level($pdo, (int) $comment['author_account_id'], null, 'comment_deleted');
sr_audit_log($pdo, [
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
$_SESSION['sr_community_comment_notice'] = '댓글을 삭제했습니다.';
sr_redirect('/community/post?id=' . (string) $comment['post_id'] . '#comments');
