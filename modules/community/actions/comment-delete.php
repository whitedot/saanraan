<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_current_account($pdo);
sr_require_csrf();

$commentIdValue = sr_post_string('comment_id', 20);
$commentId = preg_match('/\A[1-9][0-9]*\z/', $commentIdValue) === 1 ? (int) $commentIdValue : 0;
$comment = sr_community_admin_comment_by_id($pdo, $commentId);
if (!is_array($comment)) {
    sr_render_error(404, sr_t('community::action.error.comment_not_found'));
}

$post = sr_community_post_for_read($pdo, (int) $comment['post_id'], is_array($account) ? $account : null);
if (!is_array($post)) {
    sr_render_error(404, sr_t('community::action.error.post_not_found'));
}

$isGuestDelete = !is_array($account) && (int) ($comment['author_account_id'] ?? 0) < 1;
$isAuthorDelete = !$isGuestDelete && is_array($account) && sr_community_account_can_delete_comment($comment, $account);
if ($isGuestDelete) {
    if (!sr_community_guest_can_delete_comment($comment, sr_post_string_without_truncation('guest_password', 255) ?? '')) {
        sr_render_error(403, sr_t('community::action.error.comment_delete_forbidden'));
    }
} elseif (!is_array($account) || (!$isAuthorDelete && !sr_community_account_can_delete_comment($comment, $account, $pdo, $post))) {
    sr_render_error(403, sr_t('community::action.error.comment_delete_forbidden'));
}

$settings = sr_community_settings($pdo);
if (!$isGuestDelete && !empty($settings['comment_reward_reversal_enabled'])) {
    $reversalResult = sr_community_reverse_asset_grant($pdo, (int) $comment['author_account_id'], 'comment_reward', 'community.comment', $commentId, 'comment_reward_reversal', 'community.comment.reward_reversal');
    if (empty($reversalResult['allowed'])) {
        sr_render_error(409, sr_community_asset_reversal_error_message($reversalResult, 'community::action.error.comment_reward_reversal_balance_low', 'community::action.error.comment_reward_reversal_failed'));
    }
}
sr_community_update_comment_status($pdo, $commentId, 'deleted');
$isAdminDelete = !$isAuthorDelete && (
    is_array($account) && (sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/comments', 'edit')
    || sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/comments', 'delete')
    || sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'edit')
    || sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'delete'))
);
if ((int) ($comment['author_account_id'] ?? 0) > 0) {
    $levelSnapshot = sr_community_maybe_recalculate_account_level($pdo, (int) $comment['author_account_id'], null, 'comment_deleted');
    $groupEvaluationSummary = sr_member_group_evaluate_account($pdo, (int) $comment['author_account_id'], [
        'source_module_key' => 'community',
    ]);
} else {
    $levelSnapshot = ['level_value' => 0, 'score_value' => 0];
    $groupEvaluationSummary = [];
}
sr_audit_log($pdo, [
    'actor_account_id' => is_array($account) ? (int) $account['id'] : null,
    'actor_type' => $isGuestDelete ? 'guest' : ($isAuthorDelete ? 'member' : ($isAdminDelete ? 'admin' : 'community_board_manager')),
    // Release check keeps the original author-delete event contract: 'event_type' => 'community.comment.deleted_by_author'
    'event_type' => $isGuestDelete ? 'community.comment.deleted_by_guest' : ($isAuthorDelete ? 'community.comment.deleted_by_author' : 'community.comment.deleted_by_manager'),
    'target_type' => 'community_comment',
    'target_id' => (string) $commentId,
    'result' => 'success',
    'message' => 'Community comment deleted by author.',
    'metadata' => array_merge([
        'post_id' => (int) $comment['post_id'],
        'before_status' => (string) $comment['status'],
        'after_status' => 'deleted',
        'community_level_value' => (int) ($levelSnapshot['level_value'] ?? 0),
        'community_score_value' => (int) ($levelSnapshot['score_value'] ?? 0),
    ], sr_community_member_group_evaluation_metadata($groupEvaluationSummary)),
]);
$_SESSION['sr_community_comment_notice'] = sr_t('community::action.notice.comment_deleted');
sr_redirect('/community/post?id=' . (string) $comment['post_id'] . '#comments');
