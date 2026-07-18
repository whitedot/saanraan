<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$surveyIdValue = sr_post_string('survey_id', 20);
$surveyId = preg_match('/\A[1-9][0-9]*\z/', $surveyIdValue) === 1 ? (int) $surveyIdValue : 0;
$survey = sr_survey_by_id($pdo, $surveyId);
if (!is_array($survey) || (string) ($survey['status'] ?? '') !== 'active' || !sr_survey_public_window_is_open($survey)) {
    sr_render_error(404, '설문을 찾을 수 없습니다.');
}
if ((int) ($survey['comments_enabled'] ?? 0) !== 1 || !sr_survey_comments_table_exists($pdo)) {
    sr_render_error(403, '이 설문에는 댓글을 작성할 수 없습니다.');
}
if (!sr_survey_account_has_submitted_response($pdo, $surveyId, (int) ($account['id'] ?? 0))) {
    sr_render_error(403, '설문 참여 완료 후 댓글을 작성할 수 있습니다.');
}

$surveySettings = sr_survey_settings($pdo);
$values = sr_survey_comment_input_values($pdo, $surveySettings);
$commentExtraFieldDefinitions = sr_comment_extra_field_definitions($survey['comment_extra_fields_json'] ?? '[]');
$commentExtraFieldInput = sr_comment_extra_field_values_from_post($commentExtraFieldDefinitions);
$commentExtraFieldValues = (array) ($commentExtraFieldInput['values'] ?? []);
if ((int) ($survey['secret_comments_enabled'] ?? 0) !== 1) {
    $values['is_secret'] = 0;
}
$errors = sr_survey_validate_comment_input($values);
$errors = array_merge($errors, (array) ($commentExtraFieldInput['errors'] ?? []));
$parentValidation = sr_survey_validate_comment_parent($pdo, $surveyId, $values);
$parentComment = is_array($parentValidation['parent_comment'] ?? null) ? $parentValidation['parent_comment'] : null;
$errors = array_merge($errors, (array) ($parentValidation['errors'] ?? []));
$commentPageValue = sr_post_string('comment_page', 20);
$commentPage = preg_match('/\A[1-9][0-9]*\z/', $commentPageValue) === 1 ? (int) $commentPageValue : 1;
$redirectBaseUrl = '/survey/' . rawurlencode((string) ($survey['survey_key'] ?? '')) . '?submitted=1';
$redirectUrl = $redirectBaseUrl . ($commentPage > 1 ? '&comment_page=' . rawurlencode((string) $commentPage) : '') . '#survey-comments';
if ($errors !== []) {
    $_SESSION['sr_survey_comment_errors'] = $errors;
    $_SESSION['sr_survey_comment_body'] = is_string($values['body_text'] ?? null) ? (string) $values['body_text'] : '';
    $_SESSION['sr_survey_comment_is_secret'] = (int) ($values['is_secret'] ?? 0) === 1;
    $_SESSION['sr_survey_comment_parent_id'] = (int) ($values['parent_comment_id'] ?? 0);
    $_SESSION['sr_survey_comment_extra_field_values'] = $commentExtraFieldValues;
    sr_redirect($redirectUrl);
}

$values['parent_comment'] = $parentComment;
$values['extra_values_json'] = sr_comment_extra_field_snapshot_json($commentExtraFieldDefinitions, $commentExtraFieldValues);
$commentId = sr_survey_create_comment($pdo, $surveyId, (int) $account['id'], $values);
$mentionExcludeAccountIds = [(int) $account['id']];
$mentionNotificationResult = (int) ($values['is_secret'] ?? 0) === 1
    ? ['mention_candidate_count' => 0, 'mention_notification_count' => 0, 'mention_account_hashes' => []]
    : sr_survey_create_comment_mention_notifications(
        $pdo,
        $survey,
        $commentId,
        (string) $values['body_text'],
        (int) $account['id'],
        $mentionExcludeAccountIds
    );
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'survey.comment.created',
    'target_type' => 'survey_comment',
    'target_id' => (string) $commentId,
    'result' => 'success',
    'message' => 'Survey comment created.',
    'metadata' => [
        'survey_id' => $surveyId,
        'parent_comment_id' => (int) ($values['parent_comment_id'] ?? 0),
        'is_secret' => (int) ($values['is_secret'] ?? 0) === 1,
        'mention_candidate_count' => (int) ($mentionNotificationResult['mention_candidate_count'] ?? 0),
        'mention_notification_count' => (int) ($mentionNotificationResult['mention_notification_count'] ?? 0),
        'mention_account_hashes' => $mentionNotificationResult['mention_account_hashes'] ?? [],
    ],
]);

$_SESSION['sr_survey_comment_notice'] = '댓글을 등록했습니다.';
$commentPage = sr_survey_comment_page_for_comment($pdo, $surveyId, $commentId, 20);
sr_redirect($redirectBaseUrl . ($commentPage > 1 ? '&comment_page=' . rawurlencode((string) $commentPage) : '') . '#survey-comment-' . (string) $commentId);
