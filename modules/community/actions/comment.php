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
    sr_render_error(404, sr_t('community::action.error.post_not_found'));
}

if (!sr_community_account_can_comment_post($pdo, $post, $account)) {
    sr_render_error(403, sr_t('community::action.error.comment_write_forbidden'));
}

$settings = sr_community_settings($pdo);
$board = sr_community_board_by_id($pdo, (int) $post['board_id']);
$commentChargeConfig = is_array($board) ? sr_community_asset_event_config($pdo, $board, $settings, 'comment_charge', 'every_action') : ['enabled' => false];
$commentRewardConfig = is_array($board) ? sr_community_asset_event_config($pdo, $board, $settings, 'comment_reward', 'once') : ['enabled' => false];
$values = sr_community_comment_input_values();
$errors = sr_community_validate_comment_input($values);

if ($errors === [] && sr_community_comment_rate_limited($pdo, (int) $account['id'], $settings)) {
    $errors[] = sr_t('community::action.rate_limit.comment');
}

if ($errors === [] && sr_community_asset_event_required($commentChargeConfig)) {
    $assetModules = sr_community_asset_module_keys_from_value($commentChargeConfig['asset_module'] ?? '', true);
    if (!sr_community_asset_modules_available($pdo, $assetModules)) {
        $errors[] = sr_t('community::action.error.comment_asset_modules_unavailable');
    } elseif (!sr_community_asset_use_balance_available($pdo, $commentChargeConfig, (int) $account['id'])) {
        $errors[] = sr_t('community::action.error.comment_asset_balance_low');
    }
}

if ($errors !== []) {
    $_SESSION['sr_community_comment_errors'] = $errors;
    $_SESSION['sr_community_comment_body'] = is_string($values['body_text']) ? $values['body_text'] : '';
    sr_redirect('/community/post?id=' . (string) $postId . '#comments');
}

$commentId = sr_community_create_comment($pdo, $postId, (int) $account['id'], $values);
$commentChargeResult = sr_community_asset_event_required($commentChargeConfig)
    ? sr_community_run_asset_event($pdo, $commentChargeConfig, (int) $account['id'], 'comment_write_charge', 'community.comment', $commentId, 'use', 'community.comment.write')
    : ['allowed' => true, 'processed' => false];
if (empty($commentChargeResult['allowed'])) {
    sr_community_update_comment_status($pdo, $commentId, 'deleted');
    $_SESSION['sr_community_comment_errors'] = [(string) ($commentChargeResult['message'] ?? sr_t('community::action.error.comment_charge_failed'))];
    sr_redirect('/community/post?id=' . (string) $postId . '#comments');
}
$commentRewardResult = sr_community_asset_event_required($commentRewardConfig)
    ? sr_community_run_asset_event($pdo, $commentRewardConfig, (int) $account['id'], 'comment_reward', 'community.comment', $commentId, 'grant', 'community.comment.reward')
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
$postAuthorNotificationCreated = false;
if ((int) $post['author_account_id'] !== (int) $account['id']) {
    $commentAuthorLabel = sr_community_message_account_label(
        (string) ($account['display_name'] ?? ''),
        (int) $account['id'],
        false,
        null,
        (string) ($account['status'] ?? ''),
        sr_community_member_nickname($pdo, (int) $account['id']),
        sr_member_settings($pdo)
    );
    $postAuthorNotificationCreated = sr_community_create_account_event_notification(
        $pdo,
        (int) $post['author_account_id'],
        'comment.created',
        [
            'post_id' => $postId,
            'comment_id' => $commentId,
            'member_name' => $commentAuthorLabel,
            'link_url' => '/community/post?id=' . (string) $postId . '#comments',
            'created_at' => sr_now(),
        ],
        (int) $account['id']
    );
}
$commentMentionNotificationResult = (int) ($values['is_secret'] ?? 0) === 1
    ? ['mention_candidate_count' => 0, 'mention_notification_count' => 0, 'mention_account_hashes' => []]
    : sr_community_create_comment_mention_notifications(
        $pdo,
        $postId,
        $commentId,
        (string) $values['body_text'],
        (int) $account['id'],
        [(int) $post['author_account_id']]
    );
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'community.comment.notifications_created',
    'target_type' => 'community_comment',
    'target_id' => (string) $commentId,
    'result' => 'success',
    'message' => 'Community comment notifications created.',
    'metadata' => [
        'post_id' => $postId,
        'post_author_notification_created' => $postAuthorNotificationCreated,
        'mention_candidate_count' => (int) ($commentMentionNotificationResult['mention_candidate_count'] ?? 0),
        'mention_notification_count' => (int) ($commentMentionNotificationResult['mention_notification_count'] ?? 0),
        'mention_account_hashes' => $commentMentionNotificationResult['mention_account_hashes'] ?? [],
    ],
]);
if (!empty($commentRewardResult['processed'])) {
    $_SESSION['sr_community_comment_notice'] = sr_t('community::action.notice.asset_granted', [
        'asset' => sr_community_asset_module_label((string) $commentRewardConfig['asset_module'], $pdo),
        'amount' => number_format((int) $commentRewardConfig['amount']),
    ]);
} elseif (empty($commentChargeResult['allowed'])) {
    $_SESSION['sr_community_comment_notice'] = (string) ($commentChargeResult['message'] ?? sr_t('community::action.error.asset_charge_failed'));
} else {
    $_SESSION['sr_community_comment_notice'] = sr_t('community::action.notice.comment_created');
}
sr_redirect('/community/post?id=' . (string) $postId . '#comments');
