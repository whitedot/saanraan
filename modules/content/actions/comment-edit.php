<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
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
$commentPage = sr_content_comment_page_for_comment($pdo, (int) $comment['content_id'], $commentId, 20);
$redirectUrl = sr_content_path((string) $page['slug']) . ($commentPage > 1 ? '?comment_page=' . rawurlencode((string) $commentPage) : '') . '#content-comment-' . (string) $commentId;

if (!sr_content_account_can_edit_comment($comment, $account)) {
    sr_render_error(403, '댓글을 수정할 권한이 없습니다.');
}

$contentSettings = sr_content_settings($pdo);
$values = sr_content_comment_input_values($pdo, $contentSettings);
if (empty($contentSettings['secret_comments_enabled'])) {
    $values['is_secret'] = (int) ($comment['is_secret'] ?? 0) === 1 ? 1 : 0;
}
$errors = sr_content_validate_comment_input($values);
if ($errors !== []) {
    $_SESSION['sr_content_comment_errors'] = $errors;
    sr_redirect($redirectUrl);
}

sr_content_update_comment_content($pdo, $commentId, $values);
$contentCommentMentionNotificationResult = (int) ($values['is_secret'] ?? 0) === 1
    ? ['mention_candidate_count' => 0, 'mention_notification_count' => 0, 'mention_account_hashes' => []]
    : sr_content_create_comment_mention_notifications(
        $pdo,
        $page,
        $commentId,
        (string) $values['body_text'],
        (int) $account['id'],
        [(int) $comment['author_account_id']],
        (string) ($comment['body_text'] ?? '')
    );
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'content.comment.updated_by_author',
    'target_type' => 'content_comment',
    'target_id' => (string) $commentId,
    'result' => 'success',
    'message' => 'Content comment updated by author.',
    'metadata' => [
        'content_id' => (int) $comment['content_id'],
        'is_secret' => (int) ($values['is_secret'] ?? 0) === 1,
        'mention_candidate_count' => (int) ($contentCommentMentionNotificationResult['mention_candidate_count'] ?? 0),
        'mention_notification_count' => (int) ($contentCommentMentionNotificationResult['mention_notification_count'] ?? 0),
        'mention_account_hashes' => $contentCommentMentionNotificationResult['mention_account_hashes'] ?? [],
    ],
]);
$_SESSION['sr_content_comment_notice'] = '댓글을 수정했습니다.';
sr_redirect($redirectUrl);
