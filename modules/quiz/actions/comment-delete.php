<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$commentIdValue = sr_post_string('comment_id', 20);
$commentId = preg_match('/\A[1-9][0-9]*\z/', $commentIdValue) === 1 ? (int) $commentIdValue : 0;
$comment = sr_quiz_comment_by_id($pdo, $commentId);
if (!is_array($comment)) {
    sr_render_error(404, '댓글을 찾을 수 없습니다.');
}

$quiz = sr_quiz_by_id($pdo, (int) $comment['quiz_id']);
if (!is_array($quiz)) {
    sr_render_error(404, '퀴즈를 찾을 수 없습니다.');
}
if (!sr_quiz_account_can_delete_comment($comment, $account, $pdo)) {
    sr_render_error(403, '댓글을 삭제할 권한이 없습니다.');
}

sr_quiz_update_comment_status($pdo, $commentId, 'deleted');
$isAuthorDelete = (int) ($comment['author_account_id'] ?? 0) === (int) ($account['id'] ?? 0);
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => $isAuthorDelete ? 'member' : 'admin',
    'event_type' => $isAuthorDelete ? 'quiz.comment.deleted_by_author' : 'quiz.comment.deleted_by_manager',
    'target_type' => 'quiz_comment',
    'target_id' => (string) $commentId,
    'result' => 'success',
    'message' => 'Quiz comment deleted.',
    'metadata' => [
        'quiz_id' => (int) $comment['quiz_id'],
        'before_status' => (string) ($comment['status'] ?? ''),
        'after_status' => 'deleted',
    ],
]);

$_SESSION['sr_quiz_comment_notice'] = '댓글을 삭제했습니다.';
sr_redirect('/quiz/' . rawurlencode((string) ($quiz['quiz_key'] ?? '')) . '#quiz-comments');
