<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$commentIdValue = sr_post_string('comment_id', 20);
$commentId = preg_match('/\A[1-9][0-9]*\z/', $commentIdValue) === 1 ? (int) $commentIdValue : 0;
$comment = sr_content_comment_by_id($pdo, $commentId);
if (!is_array($comment)) {
    sr_render_error(404, '댓글을 찾을 수 없습니다.');
}

$page = sr_content_by_id($pdo, (int) $comment['content_id']);
if (!is_array($page) || (string) ($page['status'] ?? '') !== 'published') {
    sr_render_error(404, sr_t('content::action.error.content_not_found'));
}

if (!sr_content_account_can_hide_comment($pdo, $comment, $account)) {
    sr_render_error(403, '댓글을 숨길 권한이 없습니다.');
}

$commentPage = sr_content_comment_page_for_comment($pdo, (int) $comment['content_id'], $commentId, 20);
sr_content_update_comment_status($pdo, $commentId, 'hidden');
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'admin',
    'event_type' => 'content.comment.hidden_by_manager',
    'target_type' => 'content_comment',
    'target_id' => (string) $commentId,
    'result' => 'success',
    'message' => 'Content comment hidden by manager.',
    'metadata' => [
        'content_id' => (int) $comment['content_id'],
        'before_status' => (string) $comment['status'],
        'after_status' => 'hidden',
    ],
]);
$_SESSION['sr_content_comment_notice'] = '댓글을 숨겼습니다.';
sr_redirect(sr_content_path((string) $page['slug']) . ($commentPage > 1 ? '?comment_page=' . rawurlencode((string) $commentPage) : '') . '#content-comments');
