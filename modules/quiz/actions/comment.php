<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$quizIdValue = sr_post_string('quiz_id', 20);
$quizId = preg_match('/\A[1-9][0-9]*\z/', $quizIdValue) === 1 ? (int) $quizIdValue : 0;
$quiz = sr_quiz_by_id($pdo, $quizId);
if (!is_array($quiz) || (string) ($quiz['status'] ?? '') !== 'active' || !sr_quiz_public_window_is_open($quiz)) {
    sr_render_error(404, '퀴즈를 찾을 수 없습니다.');
}
if ((int) ($quiz['comments_enabled'] ?? 0) !== 1 || !sr_quiz_comments_table_exists($pdo)) {
    sr_render_error(403, '이 퀴즈에는 댓글을 작성할 수 없습니다.');
}

$values = sr_quiz_comment_input_values();
if ((int) ($quiz['secret_comments_enabled'] ?? 0) !== 1) {
    $values['is_secret'] = 0;
}
$errors = sr_quiz_validate_comment_input($values);
$parentValidation = sr_quiz_validate_comment_parent($pdo, $quizId, $values);
$parentComment = is_array($parentValidation['parent_comment'] ?? null) ? $parentValidation['parent_comment'] : null;
$errors = array_merge($errors, (array) ($parentValidation['errors'] ?? []));
$redirectUrl = '/quiz/' . rawurlencode((string) ($quiz['quiz_key'] ?? '')) . '#quiz-comments';
if ($errors !== []) {
    $_SESSION['sr_quiz_comment_errors'] = $errors;
    $_SESSION['sr_quiz_comment_body'] = is_string($values['body_text'] ?? null) ? (string) $values['body_text'] : '';
    $_SESSION['sr_quiz_comment_is_secret'] = (int) ($values['is_secret'] ?? 0) === 1;
    $_SESSION['sr_quiz_comment_parent_id'] = (int) ($values['parent_comment_id'] ?? 0);
    sr_redirect($redirectUrl);
}

$values['parent_comment'] = $parentComment;
$commentId = sr_quiz_create_comment($pdo, $quizId, (int) $account['id'], $values);
$mentionExcludeAccountIds = [(int) $account['id']];
if (is_array($parentComment) && (int) ($parentComment['author_account_id'] ?? 0) > 0) {
    $mentionExcludeAccountIds[] = (int) $parentComment['author_account_id'];
}
$mentionNotificationResult = (int) ($values['is_secret'] ?? 0) === 1
    ? ['mention_candidate_count' => 0, 'mention_notification_count' => 0, 'mention_account_hashes' => []]
    : sr_quiz_create_comment_mention_notifications(
        $pdo,
        $quiz,
        $commentId,
        (string) $values['body_text'],
        (int) $account['id'],
        $mentionExcludeAccountIds
    );
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'quiz.comment.created',
    'target_type' => 'quiz_comment',
    'target_id' => (string) $commentId,
    'result' => 'success',
    'message' => 'Quiz comment created.',
    'metadata' => [
        'quiz_id' => $quizId,
        'parent_comment_id' => (int) ($values['parent_comment_id'] ?? 0),
        'is_secret' => (int) ($values['is_secret'] ?? 0) === 1,
        'mention_candidate_count' => (int) ($mentionNotificationResult['mention_candidate_count'] ?? 0),
        'mention_notification_count' => (int) ($mentionNotificationResult['mention_notification_count'] ?? 0),
        'mention_account_hashes' => $mentionNotificationResult['mention_account_hashes'] ?? [],
    ],
]);

$_SESSION['sr_quiz_comment_notice'] = '댓글을 등록했습니다.';
sr_redirect($redirectUrl);
