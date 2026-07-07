<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$postIdValue = sr_post_string('post_id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$post = sr_community_admin_post_by_id($pdo, $postId);
if (!is_array($post) || (string) ($post['status'] ?? '') !== 'published') {
    sr_render_error(404, sr_t('community::action.error.post_not_found'));
}

$board = sr_community_board_by_id($pdo, (int) ($post['board_id'] ?? 0));
if (!is_array($board) || (string) ($board['status'] ?? '') !== 'enabled') {
    sr_render_error(404, sr_t('community::action.error.post_not_found'));
}

if (!sr_community_account_can_hide_post($pdo, $post, $account)) {
    sr_render_error(403, '게시글을 숨길 권한이 없습니다.');
}

$settings = sr_community_settings($pdo);
$isAdminHide = sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'edit')
    || sr_admin_has_permission($pdo, (int) $account['id'], '/admin/community/posts', 'delete');
$recoveryResult = ['recovery_status' => 'not_needed'];
try {
    $pdo->beginTransaction();
    if (!empty($settings['post_reward_reversal_enabled']) && (string) ($post['status'] ?? '') === 'published') {
        $recoveryResult = sr_community_reverse_asset_grant_for_operation($pdo, (int) ($post['author_account_id'] ?? 0), 'post_reward', 'community.post', $postId, 'post_reward_reversal', 'community.post.reward_reversal', 'community.post.reward_reversal', [
            'operation_event_key' => 'community.post.hidden_by_board_manager',
            'before_status' => (string) $post['status'],
            'after_status' => 'hidden',
            'actor_account_id' => (int) $account['id'],
            'actor_type' => $isAdminHide ? 'admin' : 'community_board_manager',
            'route_context' => 'community.post_hide',
        ]);
        if (empty($recoveryResult['operation_allowed'])) {
            throw new RuntimeException(sr_community_asset_reversal_error_message($recoveryResult, 'community::action.admin.post_reward_reversal_balance_low', 'community::action.admin.post_reward_reversal_status_failed'));
        }
    }

    sr_community_update_post_status($pdo, $postId, 'hidden', [
        'hidden_reason' => 'moderation',
        'hidden_note' => 'Hidden from public board view by board staff.',
        'hidden_by_account_id' => (int) $account['id'],
    ]);
    $updatedAttachmentCount = sr_community_update_post_attachments_status($pdo, $postId, 'hidden');
    if ((int) ($post['author_account_id'] ?? 0) > 0) {
        $levelSnapshot = sr_community_maybe_recalculate_account_level($pdo, (int) $post['author_account_id'], null, 'post_status_updated');
        $groupEvaluationSummary = sr_member_group_evaluate_account($pdo, (int) $post['author_account_id'], [
            'source_module_key' => 'community',
        ]);
    } else {
        $levelSnapshot = ['level_value' => 0, 'score_value' => 0];
        $groupEvaluationSummary = [];
    }

    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => $isAdminHide ? 'admin' : 'community_board_manager',
        'event_type' => $isAdminHide ? 'community.post.hidden_by_admin' : 'community.post.hidden_by_board_manager',
        'target_type' => 'community_post',
        'target_id' => (string) $postId,
        'result' => 'success',
        'message' => 'Community post hidden from public board view.',
        'metadata' => array_merge([
            'board_key' => (string) ($post['board_key'] ?? ''),
            'before_status' => (string) ($post['status'] ?? ''),
            'after_status' => 'hidden',
            'updated_attachment_count' => $updatedAttachmentCount,
            'recovery_status' => (string) ($recoveryResult['recovery_status'] ?? 'not_needed'),
            'recovery_failure_id' => (int) ($recoveryResult['recovery_failure_id'] ?? 0),
            'community_level_value' => (int) ($levelSnapshot['level_value'] ?? 0),
            'community_score_value' => (int) ($levelSnapshot['score_value'] ?? 0),
        ], sr_community_member_group_evaluation_metadata($groupEvaluationSummary)),
    ]);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sr_render_error(409, $exception->getMessage() !== '' ? $exception->getMessage() : sr_t('community::action.admin.post_reward_reversal_status_failed'));
}

$_SESSION['sr_community_board_notice'] = '게시글을 숨겼습니다.';
sr_redirect('/community/board?key=' . rawurlencode((string) ($post['board_key'] ?? '')));
