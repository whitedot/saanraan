<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$contentIdValue = sr_post_string('content_id', 20);
$contentId = preg_match('/\A[1-9][0-9]*\z/', $contentIdValue) === 1 ? (int) $contentIdValue : 0;
$page = sr_content_by_id($pdo, $contentId);
if (!is_array($page) || (string) ($page['status'] ?? '') !== 'published') {
    sr_render_error(404, sr_t('content::action.error.content_not_found'));
}
if (sr_content_asset_access_required($page)) {
    $access = sr_content_charge_view_access($pdo, $page, (int) $account['id'], false, '', 0, false);
    if (empty($access['allowed'])) {
        sr_render_error(403, '콘텐츠 열람 권한이 있어야 댓글을 작성할 수 있습니다.');
    }
}

$contentSettings = sr_content_settings($pdo);
$contentSettings['comment_editor_key'] = sr_editor_normalize_key((string) ($page['comment_editor_key'] ?? 'inherit'), true);
$commentExtraFieldDefinitions = sr_comment_extra_field_definitions($page['comment_extra_fields_json'] ?? '[]');
$commentExtraFieldInput = sr_comment_extra_field_values_from_post($commentExtraFieldDefinitions);
$commentExtraFieldValues = (array) ($commentExtraFieldInput['values'] ?? []);
$values = sr_content_comment_input_values($pdo, $contentSettings);
if (empty($contentSettings['secret_comments_enabled'])) {
    $values['is_secret'] = 0;
}
$errors = sr_content_validate_comment_input($values);
$errors = array_merge($errors, (array) ($commentExtraFieldInput['errors'] ?? []));
$parentValidation = sr_content_validate_comment_parent($pdo, $contentId, $values);
$parentComment = is_array($parentValidation['parent_comment'] ?? null) ? $parentValidation['parent_comment'] : null;
$errors = array_merge($errors, (array) ($parentValidation['errors'] ?? []));
$commentPageValue = sr_post_string('comment_page', 20);
$commentPage = preg_match('/\A[1-9][0-9]*\z/', $commentPageValue) === 1 ? (int) $commentPageValue : 1;
$commentBaseUrl = sr_content_path((string) $page['slug']);
if ($errors !== []) {
    $_SESSION['sr_content_comment_errors'] = $errors;
    $_SESSION['sr_content_comment_body'] = is_string($values['body_text'] ?? null) ? (string) $values['body_text'] : '';
    $_SESSION['sr_content_comment_is_secret'] = (int) ($values['is_secret'] ?? 0) === 1;
    $_SESSION['sr_content_comment_parent_id'] = (int) ($values['parent_comment_id'] ?? 0);
    $_SESSION['sr_content_comment_extra_field_values'] = $commentExtraFieldValues;
    sr_redirect($commentBaseUrl . ($commentPage > 1 ? '?comment_page=' . rawurlencode((string) $commentPage) : '') . '#content-comments');
}

$values['parent_comment'] = $parentComment;
$values['extra_values_json'] = sr_comment_extra_field_snapshot_json($commentExtraFieldDefinitions, $commentExtraFieldValues);
$commentId = sr_content_create_comment($pdo, $contentId, (int) $account['id'], $values);
$notificationExcludeAccountIds = [];
if (is_array($parentComment) && (int) ($parentComment['author_account_id'] ?? 0) > 0) {
    $notificationExcludeAccountIds[] = (int) $parentComment['author_account_id'];
}
$commentNotificationResult = sr_content_create_comment_notifications(
    $pdo,
    $page,
    $commentId,
    (string) $values['body_text'],
    (int) $account['id'],
    (int) ($values['is_secret'] ?? 0) !== 1,
    $notificationExcludeAccountIds,
    is_array($parentComment) ? $parentComment : null
);
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'content.comment.created',
    'target_type' => 'content_comment',
    'target_id' => (string) $commentId,
    'result' => 'success',
    'message' => 'Content comment created.',
    'metadata' => [
        'content_id' => $contentId,
        'parent_comment_id' => (int) ($values['parent_comment_id'] ?? 0),
        'content_author_notification_created' => !empty($commentNotificationResult['content_author_notification_created']),
        'parent_author_notification_created' => !empty($commentNotificationResult['parent_author_notification_created']),
        'mention_candidate_count' => (int) ($commentNotificationResult['mention_candidate_count'] ?? 0),
        'mention_notification_count' => (int) ($commentNotificationResult['mention_notification_count'] ?? 0),
        'mention_account_hashes' => $commentNotificationResult['mention_account_hashes'] ?? [],
    ],
]);

$_SESSION['sr_content_comment_notice'] = '댓글을 등록했습니다.';
$commentPage = sr_content_comment_page_for_comment($pdo, $contentId, $commentId, 20);
sr_redirect($commentBaseUrl . ($commentPage > 1 ? '?comment_page=' . rawurlencode((string) $commentPage) : '') . '#content-comment-' . (string) $commentId);
