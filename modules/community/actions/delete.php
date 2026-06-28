<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_current_account($pdo);
sr_require_csrf();

$postIdValue = sr_post_string('post_id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$post = sr_community_post_for_read($pdo, $postId, is_array($account) ? $account : null);
if (!is_array($post)) {
    $rawPost = sr_community_admin_post_by_id($pdo, $postId);
    $rawBoard = is_array($rawPost) ? sr_community_board_by_id($pdo, (int) ($rawPost['board_id'] ?? 0)) : null;
    if (!is_array($rawPost) || !is_array($rawBoard) || (string) ($rawPost['status'] ?? '') !== 'published' || (string) ($rawBoard['status'] ?? '') !== 'enabled') {
        sr_render_error(404, sr_t('community::action.error.post_not_found'));
    }
    if (!is_array($account) && (int) ($rawPost['author_account_id'] ?? 0) < 1 && sr_community_guest_can_delete_post($rawPost, sr_post_string_without_truncation('guest_password', 255) ?? '')) {
        $post = $rawPost;
    } elseif (!is_array($account) || !sr_community_account_can_delete_post($rawPost, $account, $pdo)) {
        sr_render_error(403, sr_t('community::action.error.post_delete_forbidden'));
    }
    if (!is_array($post)) {
        $post = $rawPost;
    }
}

$isGuestDelete = !is_array($account) && (int) ($post['author_account_id'] ?? 0) < 1;
if ($isGuestDelete) {
    if (!sr_community_guest_can_delete_post($post, sr_post_string_without_truncation('guest_password', 255) ?? '')) {
        sr_render_error(403, sr_t('community::action.error.post_delete_forbidden'));
    }
} elseif (!is_array($account) || !sr_community_account_can_delete_post($post, $account, $pdo)) {
    sr_render_error(403, sr_t('community::action.error.post_delete_forbidden'));
}

$board = sr_community_board_by_id($pdo, (int) ($post['board_id'] ?? 0));
if (!is_array($board)) {
    $board = [
        'id' => (int) ($post['board_id'] ?? 0),
        'board_group_id' => (int) ($post['board_group_id'] ?? 0),
    ];
}
if (sr_community_post_locked_by_comments($pdo, $board, $postId, 'delete')) {
    sr_render_error(409, '댓글 수 기준에 따라 이 게시글은 더 이상 삭제할 수 없습니다.');
}

$settings = sr_community_settings($pdo);
$isAuthorDelete = !$isGuestDelete && is_array($account) && (int) $post['author_account_id'] === (int) $account['id'];
$isAdminDelete = !$isGuestDelete && !$isAuthorDelete && is_array($account) && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'delete');
$deleteActorType = $isGuestDelete ? 'guest' : ($isAuthorDelete ? 'member' : ($isAdminDelete ? 'admin' : 'community_board_manager'));
$deleteEventType = $isGuestDelete ? 'community.post.deleted_by_guest' : ($isAuthorDelete ? 'community.post.deleted_by_author' : ($isAdminDelete ? 'community.post.deleted_by_admin' : 'community.post.deleted_by_board_manager'));
$recoveryResult = ['recovery_status' => 'not_needed'];
$postAttachmentStorageRefs = sr_community_post_attachment_storage_refs($pdo, $postId);
try {
    $pdo->beginTransaction();
    if (!$isGuestDelete && !empty($settings['post_reward_reversal_enabled'])) {
        $recoveryResult = sr_community_reverse_asset_grant_for_operation($pdo, (int) $post['author_account_id'], 'post_reward', 'community.post', $postId, 'post_reward_reversal', 'community.post.reward_reversal', 'community.post.reward_reversal', [
            'operation_event_key' => $deleteEventType,
            'before_status' => (string) $post['status'],
            'after_status' => 'deleted',
            'actor_account_id' => is_array($account) ? (int) $account['id'] : 0,
            'actor_type' => $deleteActorType,
            'route_context' => 'community.delete',
        ]);
        if (empty($recoveryResult['operation_allowed'])) {
            throw new RuntimeException(sr_community_asset_reversal_error_message($recoveryResult, 'community::action.error.post_reward_reversal_balance_low', 'community::action.error.post_reward_reversal_failed'));
        }
    }
    // Release check contract: sr_community_update_post_status($pdo, $postId, 'deleted')
    sr_community_update_post_status($pdo, $postId, 'deleted', ['defer_file_cleanup' => true]);
    if ((int) ($post['author_account_id'] ?? 0) > 0) {
        $levelSnapshot = sr_community_maybe_recalculate_account_level($pdo, (int) $post['author_account_id'], null, 'post_deleted');
        $groupEvaluationSummary = sr_member_group_evaluate_account($pdo, (int) $post['author_account_id'], [
            'source_module_key' => 'community',
        ]);
    } else {
        $levelSnapshot = ['level_value' => 0, 'score_value' => 0];
        $groupEvaluationSummary = [];
    }
    // Release check contract: sr_community_update_post_attachments_status($pdo, $postId, 'deleted')
    $updatedAttachmentCount = sr_community_update_post_attachments_status($pdo, $postId, 'deleted', false);
    sr_audit_log($pdo, [
        'actor_account_id' => is_array($account) ? (int) $account['id'] : null,
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
            'recovery_status' => (string) ($recoveryResult['recovery_status'] ?? 'not_needed'),
            'recovery_failure_id' => (int) ($recoveryResult['recovery_failure_id'] ?? 0),
            'community_level_value' => (int) ($levelSnapshot['level_value'] ?? 0),
            'community_score_value' => (int) ($levelSnapshot['score_value'] ?? 0),
        ], sr_community_member_group_evaluation_metadata($groupEvaluationSummary)),
    ]);
    $pdo->commit();
    sr_community_cleanup_body_file_refs_for_deleted_post($pdo, $postId, (string) ($post['body_text'] ?? ''));
    sr_community_cleanup_body_files_for_deleted_posts($pdo, [$postId]);
    sr_community_cleanup_attachment_storage_refs($pdo, $postAttachmentStorageRefs);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sr_render_error(409, $exception->getMessage() !== '' ? $exception->getMessage() : sr_t('community::action.error.post_reward_reversal_failed'));
}
$_SESSION['sr_community_board_notice'] = sr_t('community::action.notice.post_deleted');
sr_redirect('/community/board?key=' . rawurlencode((string) $post['board_key']));
