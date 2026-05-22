<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$postIdValue = sr_post_string('post_id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$post = sr_community_post_for_read($pdo, $postId, $account);
if (!is_array($post)) {
    sr_render_error(404, '게시글을 찾을 수 없습니다.');
}

if (!sr_community_account_can_comment_post($pdo, $post, $account)) {
    sr_render_error(403, '이 게시글에 댓글을 작성할 수 없습니다.');
}

$settings = sr_community_settings($pdo);
$board = sr_community_board_by_id($pdo, (int) $post['board_id']);
$commentChargeConfig = is_array($board) ? sr_community_asset_event_config($pdo, $board, $settings, 'comment_charge', 'every_action') : ['enabled' => false];
$commentRewardConfig = is_array($board) ? sr_community_asset_event_config($pdo, $board, $settings, 'comment_reward', 'once') : ['enabled' => false];
$values = sr_community_comment_input_values();
$errors = sr_community_validate_comment_input($values);

if ($errors === [] && sr_community_comment_rate_limited($pdo, (int) $account['id'], $settings)) {
    $errors[] = '짧은 시간에 댓글을 너무 많이 작성했습니다. 잠시 후 다시 시도해 주세요.';
}

if ($errors === [] && sr_community_asset_event_required($commentChargeConfig)) {
    $assetModules = sr_community_asset_module_keys_from_value($commentChargeConfig['asset_module'] ?? 'point');
    $amount = (int) $commentChargeConfig['amount'];
    if (!sr_community_asset_modules_available($pdo, $assetModules)) {
        $errors[] = '선택한 자산 모듈을 모두 사용할 수 없어 댓글을 작성할 수 없습니다.';
    } elseif (sr_community_asset_combined_balance($pdo, $assetModules, (int) $account['id']) < $amount) {
        $errors[] = '선택한 자산의 합산 잔액이 부족해 댓글을 작성할 수 없습니다.';
    }
}

if ($errors !== []) {
    $_SESSION['sr_community_comment_errors'] = $errors;
    $_SESSION['sr_community_comment_body'] = is_string($values['body_text']) ? $values['body_text'] : '';
    sr_redirect('/community/post?id=' . (string) $postId . '#comments');
}

$commentId = sr_community_create_comment($pdo, $postId, (int) $account['id'], $values);
$commentChargeResult = sr_community_asset_event_required($commentChargeConfig)
    ? sr_community_run_asset_event($pdo, $commentChargeConfig, (int) $account['id'], 'comment_write_charge', 'community.comment', $commentId, 'use', '커뮤니티 댓글 작성')
    : ['allowed' => true, 'processed' => false];
if (empty($commentChargeResult['allowed'])) {
    sr_community_update_comment_status($pdo, $commentId, 'deleted');
    $_SESSION['sr_community_comment_errors'] = [(string) ($commentChargeResult['message'] ?? '회원 자산 차감에 실패해 댓글을 작성할 수 없습니다.')];
    sr_redirect('/community/post?id=' . (string) $postId . '#comments');
}
$commentRewardResult = sr_community_asset_event_required($commentRewardConfig)
    ? sr_community_run_asset_event($pdo, $commentRewardConfig, (int) $account['id'], 'comment_reward', 'community.comment', $commentId, 'grant', '커뮤니티 댓글 적립')
    : ['allowed' => true, 'processed' => false];
sr_community_record_comment_rate_limit($pdo, (int) $account['id'], $settings);
$levelSnapshot = sr_community_maybe_recalculate_account_level($pdo, (int) $account['id'], $settings, 'comment_created');
$groupEvaluationSummary = sr_member_group_evaluate_account($pdo, (int) $account['id'], [
    'source_module_key' => 'community',
]);
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'community.comment.created',
    'target_type' => 'community_comment',
    'target_id' => (string) $commentId,
    'result' => 'success',
    'message' => 'Community comment created.',
    'metadata' => array_merge([
        'post_id' => $postId,
        'community_level_value' => (int) ($levelSnapshot['level_value'] ?? 0),
        'community_score_value' => (int) ($levelSnapshot['score_value'] ?? 0),
        'asset_comment_charge_processed' => !empty($commentChargeResult['processed']),
        'asset_comment_reward_processed' => !empty($commentRewardResult['processed']),
    ], sr_community_member_group_evaluation_metadata($groupEvaluationSummary)),
]);
if ((int) $post['author_account_id'] !== (int) $account['id']) {
    sr_community_create_account_notification(
        $pdo,
        (int) $post['author_account_id'],
        '게시글에 새 댓글이 달렸습니다.',
        sr_community_message_account_label((string) ($account['display_name'] ?? ''), (int) $account['id']) . '님이 댓글을 남겼습니다.',
        '/community/post?id=' . (string) $postId . '#comments',
        (int) $account['id']
    );
}
if (!empty($commentRewardResult['processed'])) {
    $_SESSION['sr_community_comment_notice'] = sr_community_asset_module_label((string) $commentRewardConfig['asset_module']) . ' ' . number_format((int) $commentRewardConfig['amount']) . '을(를) 적립했습니다.';
} elseif (empty($commentChargeResult['allowed'])) {
    $_SESSION['sr_community_comment_notice'] = (string) ($commentChargeResult['message'] ?? '회원 자산 차감에 실패했습니다.');
} else {
    $_SESSION['sr_community_comment_notice'] = '댓글을 등록했습니다.';
}
sr_redirect('/community/post?id=' . (string) $postId . '#comments');
