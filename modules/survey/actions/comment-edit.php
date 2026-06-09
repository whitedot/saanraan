<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$commentIdValue = sr_post_string('comment_id', 20);
$commentId = preg_match('/\A[1-9][0-9]*\z/', $commentIdValue) === 1 ? (int) $commentIdValue : 0;
$comment = sr_survey_comment_by_id($pdo, $commentId);
if (!is_array($comment)) {
    sr_render_error(404, '댓글을 찾을 수 없습니다.');
}

$survey = sr_survey_by_id($pdo, (int) $comment['survey_id']);
if (!is_array($survey) || (string) ($survey['status'] ?? '') !== 'active' || !sr_survey_public_window_is_open($survey)) {
    sr_render_error(404, '설문을 찾을 수 없습니다.');
}
if ((int) ($survey['comments_enabled'] ?? 0) !== 1) {
    sr_render_error(403, '이 설문의 댓글을 수정할 수 없습니다.');
}
if (!sr_survey_account_can_edit_comment($comment, $account)) {
    sr_render_error(403, '댓글을 수정할 권한이 없습니다.');
}

$values = sr_survey_comment_input_values();
if ((int) ($survey['secret_comments_enabled'] ?? 0) !== 1) {
    $values['is_secret'] = (int) ($comment['is_secret'] ?? 0) === 1 ? 1 : 0;
}
$errors = sr_survey_validate_comment_input($values);
$redirectUrl = '/survey/' . rawurlencode((string) ($survey['survey_key'] ?? '')) . '#survey-comments';
if ($errors !== []) {
    $_SESSION['sr_survey_comment_errors'] = $errors;
    sr_redirect($redirectUrl);
}

sr_survey_update_comment_content($pdo, $commentId, $values);
$mentionNotificationResult = (int) ($values['is_secret'] ?? 0) === 1
    ? ['mention_candidate_count' => 0, 'mention_notification_count' => 0, 'mention_account_hashes' => []]
    : sr_survey_create_comment_mention_notifications(
        $pdo,
        $survey,
        $commentId,
        (string) $values['body_text'],
        (int) $account['id'],
        [(int) $comment['author_account_id']],
        (string) ($comment['body_text'] ?? '')
    );
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'survey.comment.updated_by_author',
    'target_type' => 'survey_comment',
    'target_id' => (string) $commentId,
    'result' => 'success',
    'message' => 'Survey comment updated by author.',
    'metadata' => [
        'survey_id' => (int) $comment['survey_id'],
        'is_secret' => (int) ($values['is_secret'] ?? 0) === 1,
        'mention_candidate_count' => (int) ($mentionNotificationResult['mention_candidate_count'] ?? 0),
        'mention_notification_count' => (int) ($mentionNotificationResult['mention_notification_count'] ?? 0),
        'mention_account_hashes' => $mentionNotificationResult['mention_account_hashes'] ?? [],
    ],
]);

$_SESSION['sr_survey_comment_notice'] = '댓글을 수정했습니다.';
sr_redirect($redirectUrl);
