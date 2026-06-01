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
    sr_render_error(404, sr_t('community::action.error.comment_not_found'));
}

$post = sr_community_post_for_read($pdo, (int) $comment['post_id'], $account);
if (!is_array($post)) {
    sr_render_error(404, sr_t('community::action.error.post_not_found'));
}

if (!sr_community_account_can_edit_comment($comment, $account)) {
    sr_render_error(403, sr_t('community::action.error.comment_edit_forbidden'));
}

$values = sr_community_comment_input_values();
$errors = sr_community_validate_comment_input($values);
if ($errors !== []) {
    $_SESSION['sr_community_comment_errors'] = $errors;
    sr_redirect('/community/post?id=' . (string) $comment['post_id'] . '#comments');
}

sr_community_update_comment_content($pdo, $commentId, $values);
$commentMentionNotificationResult = sr_community_create_comment_mention_notifications(
    $pdo,
    (int) $comment['post_id'],
    $commentId,
    (string) $values['body_text'],
    (int) $account['id'],
    [(int) $comment['author_account_id']]
);
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'community.comment.updated_by_author',
    'target_type' => 'community_comment',
    'target_id' => (string) $commentId,
    'result' => 'success',
    'message' => 'Community comment updated by author.',
    'metadata' => [
        'post_id' => (int) $comment['post_id'],
        'mention_candidate_count' => (int) ($commentMentionNotificationResult['mention_candidate_count'] ?? 0),
        'mention_notification_count' => (int) ($commentMentionNotificationResult['mention_notification_count'] ?? 0),
        'mention_account_hashes' => $commentMentionNotificationResult['mention_account_hashes'] ?? [],
    ],
]);
$_SESSION['sr_community_comment_notice'] = sr_t('community::action.notice.comment_updated');
sr_redirect('/community/post?id=' . (string) $comment['post_id'] . '#comments');
