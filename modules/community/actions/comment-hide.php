<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$commentIdValue = sr_post_string('comment_id', 20);
$commentId = preg_match('/\A[1-9][0-9]*\z/', $commentIdValue) === 1 ? (int) $commentIdValue : 0;
$commentPageValue = sr_post_string('comment_page', 20);
$commentPageNumber = preg_match('/\A[1-9][0-9]*\z/', $commentPageValue) === 1 ? (int) $commentPageValue : 1;
$comment = sr_community_admin_comment_by_id($pdo, $commentId);
if (!is_array($comment)) {
    sr_render_error(404, sr_t('community::action.error.comment_not_found'));
}

$post = sr_community_post_for_read($pdo, (int) $comment['post_id'], $account);
if (!is_array($post)) {
    sr_render_error(404, sr_t('community::action.error.post_not_found'));
}

if (!sr_community_account_can_hide_comment($pdo, $comment, $post, $account)) {
    sr_render_error(403, '댓글을 숨길 권한이 없습니다.');
}

$isAdminCommentHide = sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/comments', 'edit')
    || sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/comments', 'delete')
    || sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'edit')
    || sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'delete');
sr_community_update_comment_status($pdo, $commentId, 'hidden', [
    'hidden_reason' => 'moderation',
    'hidden_note' => 'Hidden from public board view by board staff.',
    'hidden_by_account_id' => (int) $account['id'],
]);
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => $isAdminCommentHide ? 'admin' : 'community_board_manager',
    'event_type' => $isAdminCommentHide ? 'community.comment.hidden_by_admin' : 'community.comment.hidden_by_board_manager',
    'target_type' => 'community_comment',
    'target_id' => (string) $commentId,
    'result' => 'success',
    'message' => 'Community comment hidden by manager.',
    'metadata' => [
        'post_id' => (int) $comment['post_id'],
        'before_status' => (string) $comment['status'],
        'after_status' => 'hidden',
    ],
]);
$_SESSION['sr_community_comment_notice'] = '댓글을 숨겼습니다.';
sr_redirect(sr_community_comment_page_path((int) $comment['post_id'], $commentPageNumber));
