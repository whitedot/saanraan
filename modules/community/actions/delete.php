<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$postIdValue = sr_post_string('post_id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$post = sr_community_post_for_read($pdo, $postId, $account);
if (!is_array($post)) {
    $rawPost = sr_community_admin_post_by_id($pdo, $postId);
    $rawBoard = is_array($rawPost) ? sr_community_board_by_id($pdo, (int) ($rawPost['board_id'] ?? 0)) : null;
    if (!is_array($rawPost) || !is_array($rawBoard) || (string) ($rawPost['status'] ?? '') !== 'published' || (string) ($rawBoard['status'] ?? '') !== 'enabled') {
        sr_render_error(404, sr_t('community::action.error.post_not_found'));
    }
    if (!sr_community_account_can_delete_post($rawPost, $account, $pdo)) {
        sr_render_error(403, sr_t('community::action.error.post_delete_forbidden'));
    }
    $post = $rawPost;
}

if (!sr_community_account_can_delete_post($post, $account, $pdo)) {
    sr_render_error(403, sr_t('community::action.error.post_delete_forbidden'));
}

$settings = sr_community_settings($pdo);
if (!empty($settings['post_reward_reversal_enabled'])) {
    $reversalResult = sr_community_reverse_asset_grant($pdo, (int) $post['author_account_id'], 'post_reward', 'community.post', $postId, 'post_reward_reversal', 'community.post.reward_reversal');
    if (empty($reversalResult['allowed'])) {
        sr_render_error(409, sr_community_asset_reversal_error_message($reversalResult, 'community::action.error.post_reward_reversal_balance_low', 'community::action.error.post_reward_reversal_failed'));
    }
}
sr_community_update_post_status($pdo, $postId, 'deleted');
$levelSnapshot = sr_community_maybe_recalculate_account_level($pdo, (int) $post['author_account_id'], null, 'post_deleted');
$groupEvaluationSummary = sr_member_group_evaluate_account($pdo, (int) $post['author_account_id'], [
    'source_module_key' => 'community',
]);
$updatedAttachmentCount = sr_community_update_post_attachments_status($pdo, $postId, 'deleted');
$isAuthorDelete = (int) $post['author_account_id'] === (int) $account['id'];
$isAdminDelete = !$isAuthorDelete && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'delete');
$deleteActorType = $isAuthorDelete ? 'member' : ($isAdminDelete ? 'admin' : 'community_board_manager');
$deleteEventType = $isAuthorDelete ? 'community.post.deleted_by_author' : ($isAdminDelete ? 'community.post.deleted_by_admin' : 'community.post.deleted_by_board_manager');
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => $deleteActorType,
    'event_type' => $deleteEventType,
    'target_type' => 'community_post',
    'target_id' => (string) $postId,
    'result' => 'success',
    'message' => 'Community post deleted.',
    'metadata' => array_merge([
        'board_key' => (string) $post['board_key'],
        'before_status' => (string) $post['status'],
        'after_status' => 'deleted',
        'updated_attachment_count' => $updatedAttachmentCount,
        'community_level_value' => (int) ($levelSnapshot['level_value'] ?? 0),
        'community_score_value' => (int) ($levelSnapshot['score_value'] ?? 0),
    ], sr_community_member_group_evaluation_metadata($groupEvaluationSummary)),
]);
$_SESSION['sr_community_board_notice'] = sr_t('community::action.notice.post_deleted');
sr_redirect('/community/board?key=' . rawurlencode((string) $post['board_key']));
