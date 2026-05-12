<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_require_login($pdo);
toy_require_csrf();

$postIdValue = toy_post_string('post_id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$post = toy_community_post_for_read($pdo, $postId, $account);
if (!is_array($post)) {
    toy_render_error(404, '게시글을 찾을 수 없습니다.');
}

if (!toy_community_account_can_delete_post($post, $account)) {
    toy_render_error(403, '이 게시글을 삭제할 수 없습니다.');
}

toy_community_update_post_status($pdo, $postId, 'deleted');
$groupEvaluationSummary = toy_member_group_evaluate_account($pdo, (int) $post['author_account_id'], [
    'source_module_key' => 'community',
]);
$levelSnapshot = toy_community_recalculate_account_level($pdo, (int) $post['author_account_id'], null, 'post_deleted');
$updatedAttachmentCount = toy_community_update_post_attachments_status($pdo, $postId, 'deleted');
toy_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'community.post.deleted_by_author',
    'target_type' => 'community_post',
    'target_id' => (string) $postId,
    'result' => 'success',
    'message' => 'Community post deleted by author.',
    'metadata' => array_merge([
        'board_key' => (string) $post['board_key'],
        'before_status' => (string) $post['status'],
        'after_status' => 'deleted',
        'updated_attachment_count' => $updatedAttachmentCount,
        'community_level_value' => (int) ($levelSnapshot['level_value'] ?? 0),
        'community_score_value' => (int) ($levelSnapshot['score_value'] ?? 0),
    ], toy_community_member_group_evaluation_metadata($groupEvaluationSummary)),
]);
$_SESSION['toy_community_board_notice'] = '게시글을 삭제했습니다.';
toy_redirect('/community/board?key=' . rawurlencode((string) $post['board_key']));
