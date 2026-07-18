<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
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
if (!is_array($quiz) || (string) ($quiz['status'] ?? '') !== 'active' || !sr_quiz_public_window_is_open($quiz)) {
    sr_render_error(404, '퀴즈를 찾을 수 없습니다.');
}
if ((int) ($quiz['comments_enabled'] ?? 0) !== 1) {
    sr_render_error(403, '이 퀴즈의 댓글을 수정할 수 없습니다.');
}
if (!sr_quiz_account_can_edit_comment($comment, $account)) {
    sr_render_error(403, '댓글을 수정할 권한이 없습니다.');
}

$quizSettings = sr_quiz_settings($pdo);
$quizSettings['comment_editor_key'] = sr_editor_normalize_key((string) ($quiz['comment_editor_key'] ?? 'inherit'), true);
$values = sr_quiz_comment_input_values($pdo, $quizSettings);
if ((int) ($quiz['secret_comments_enabled'] ?? 0) !== 1) {
    $values['is_secret'] = (int) ($comment['is_secret'] ?? 0) === 1 ? 1 : 0;
}
$errors = sr_quiz_validate_comment_input($values);
$commentPage = sr_quiz_comment_page_for_comment($pdo, (int) $comment['quiz_id'], $commentId, 20);
$redirectUrl = '/quiz/' . rawurlencode((string) ($quiz['quiz_key'] ?? '')) . '?result=1' . ($commentPage > 1 ? '&comment_page=' . rawurlencode((string) $commentPage) : '') . '#quiz-comment-' . (string) $commentId;
if ($errors !== []) {
    $_SESSION['sr_quiz_comment_errors'] = $errors;
    sr_redirect($redirectUrl);
}

sr_quiz_update_comment_content($pdo, $commentId, $values);
$mentionNotificationResult = (int) ($values['is_secret'] ?? 0) === 1
    ? ['mention_candidate_count' => 0, 'mention_notification_count' => 0, 'mention_account_hashes' => []]
    : sr_quiz_create_comment_mention_notifications(
        $pdo,
        $quiz,
        $commentId,
        (string) $values['body_text'],
        (int) $account['id'],
        [(int) $comment['author_account_id']],
        (string) ($comment['body_text'] ?? '')
    );
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'quiz.comment.updated_by_author',
    'target_type' => 'quiz_comment',
    'target_id' => (string) $commentId,
    'result' => 'success',
    'message' => 'Quiz comment updated by author.',
    'metadata' => [
        'quiz_id' => (int) $comment['quiz_id'],
        'is_secret' => (int) ($values['is_secret'] ?? 0) === 1,
        'mention_candidate_count' => (int) ($mentionNotificationResult['mention_candidate_count'] ?? 0),
        'mention_notification_count' => (int) ($mentionNotificationResult['mention_notification_count'] ?? 0),
        'mention_account_hashes' => $mentionNotificationResult['mention_account_hashes'] ?? [],
    ],
]);

$_SESSION['sr_quiz_comment_notice'] = '댓글을 수정했습니다.';
sr_redirect($redirectUrl);
