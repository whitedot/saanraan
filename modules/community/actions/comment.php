<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';
if (sr_module_enabled($pdo, 'antispam') && is_file(SR_ROOT . '/modules/antispam/helpers.php')) {
    require_once SR_ROOT . '/modules/antispam/helpers.php';
}

$account = sr_member_current_account($pdo);
sr_require_csrf();

$postIdValue = sr_post_string('post_id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$post = sr_community_post_for_read($pdo, $postId, is_array($account) ? $account : null);
if (!is_array($post)) {
    sr_render_error(404, sr_t('community::action.error.post_not_found'));
}

if (!sr_community_account_can_comment_post($pdo, $post, is_array($account) ? $account : null)) {
    if (!is_array($account)) {
        $account = sr_member_require_login($pdo);
    }
    sr_render_error(403, sr_t('community::action.error.comment_write_forbidden'));
}
$isGuestAuthor = !is_array($account);

$settings = sr_community_settings($pdo);
$board = sr_community_board_by_id($pdo, (int) $post['board_id']);
if (is_array($board)) {
    if (!is_array($account) && sr_community_board_identity_action_required($pdo, $board, 'comment')) {
        $account = sr_member_require_login($pdo);
    }
    $commentIdentityPolicy = sr_community_identity_action_policy(
        $pdo,
        $board,
        is_array($account) ? $account : null,
        'comment',
        '/community/post?id=' . (string) $postId . '#comments'
    );
    if (!empty($commentIdentityPolicy['required']) && empty($commentIdentityPolicy['satisfied'])) {
        sr_render_error(403, sr_community_identity_action_error_message('comment', (string) ($commentIdentityPolicy['purpose'] ?? 'real_name')));
    }
}
$commentChargeConfig = is_array($board) ? sr_community_asset_event_config($pdo, $board, $settings, 'comment_charge', 'every_action') : ['enabled' => false];
$commentRewardConfig = is_array($board) ? sr_community_asset_event_config($pdo, $board, $settings, 'comment_reward', 'once') : ['enabled' => false];
$values = sr_community_comment_input_values();
$errors = [];
if ($isGuestAuthor) {
    $values = array_merge($values, sr_community_guest_author_input_values());
}
$antispamCommentContext = ['account' => is_array($account) ? $account : null];
if (function_exists('sr_antispam_verify')) {
    $antispamResult = sr_antispam_verify($pdo, 'community.comment.guest', 'community_comment_' . (string) $postId . '_' . (string) (int) ($values['parent_comment_id'] ?? 0), $_POST, $antispamCommentContext);
    $errors = array_merge($errors, (array) ($antispamResult['errors'] ?? []));
}
if (!is_array($board) || !sr_community_effective_board_secret_comments_enabled($pdo, $board, $settings)) {
    $values['is_secret'] = 0;
}
$errors = array_merge($errors, sr_community_validate_comment_input($values));
if (is_array($board)) {
    $errors = array_merge($errors, sr_community_validate_comment_body_length($pdo, $board, $values));
}
if ($isGuestAuthor) {
    $errors = array_merge($errors, sr_community_validate_guest_author_input($values));
}
$parentValidation = sr_community_validate_comment_parent($pdo, $postId, $values);
$parentComment = is_array($parentValidation['parent_comment'] ?? null) ? $parentValidation['parent_comment'] : null;
$errors = array_merge($errors, (array) ($parentValidation['errors'] ?? []));
if (is_array($board)) {
    $errors = array_merge($errors, sr_community_privacy_consent_validation_errors($pdo, $board, ['comment']));
}

if ($errors === [] && ($isGuestAuthor ? sr_community_guest_comment_rate_limited($pdo, $settings) : sr_community_comment_rate_limited($pdo, (int) $account['id'], $settings))) {
    $errors[] = sr_t('community::action.rate_limit.comment');
}

if ($errors === [] && sr_community_asset_event_required($commentChargeConfig)) {
    if ($isGuestAuthor) {
        $errors[] = '포인트/금액 차감이 필요한 게시글에는 비회원으로 댓글을 작성할 수 없습니다.';
    }
    $assetModules = sr_community_asset_module_keys_from_value($commentChargeConfig['asset_module'] ?? '', true);
    if ($errors === [] && !sr_community_asset_modules_available($pdo, $assetModules)) {
        $errors[] = sr_t('community::action.error.comment_asset_modules_unavailable');
    } elseif ($errors === [] && !sr_community_asset_use_balance_available($pdo, $commentChargeConfig, (int) $account['id'])) {
        $errors[] = sr_community_asset_config_balance_shortage_message($pdo, $commentChargeConfig, (int) $account['id'], '댓글을 작성할 수 없습니다.', sr_t('community::action.error.comment_asset_balance_low'));
    }
}

if ($errors !== []) {
    $_SESSION['sr_community_comment_errors'] = $errors;
    $_SESSION['sr_community_comment_body'] = is_string($values['body_text']) ? $values['body_text'] : '';
    $_SESSION['sr_community_comment_is_secret'] = (int) ($values['is_secret'] ?? 0) === 1;
    $_SESSION['sr_community_comment_parent_id'] = (int) ($values['parent_comment_id'] ?? 0);
    if ($isGuestAuthor) {
        $_SESSION['sr_community_comment_guest_author_name'] = (string) ($values['guest_author_name'] ?? '');
    }
    sr_redirect('/community/post?id=' . (string) $postId . '#comments');
}

$values['parent_comment'] = $parentComment;
$authorAccountId = is_array($account) ? (int) $account['id'] : 0;
$commentId = sr_community_create_comment($pdo, $postId, $authorAccountId, $values);
$privacyConsentRecordCount = 0;
$commentChargeResult = !$isGuestAuthor && sr_community_asset_event_required($commentChargeConfig)
    ? sr_community_run_asset_event($pdo, $commentChargeConfig, $authorAccountId, 'comment_write_charge', 'community.comment', $commentId, 'use', 'community.comment.write')
    : ['allowed' => true, 'processed' => false];
if (empty($commentChargeResult['allowed'])) {
    sr_community_update_comment_status($pdo, $commentId, 'deleted');
    $_SESSION['sr_community_comment_errors'] = [(string) ($commentChargeResult['message'] ?? sr_t('community::action.error.comment_charge_failed'))];
    sr_redirect('/community/post?id=' . (string) $postId . '#comments');
}
$privacyConsentRecordCount = is_array($board)
    ? sr_community_record_submission_consents($pdo, (int) $board['id'], $authorAccountId, 'community.comment', $commentId, ['comment'], $board)
    : 0;
$commentRewardResult = !$isGuestAuthor && sr_community_asset_event_required($commentRewardConfig)
    ? sr_community_run_asset_event($pdo, $commentRewardConfig, $authorAccountId, 'comment_reward', 'community.comment', $commentId, 'grant', 'community.comment.reward')
    : ['allowed' => true, 'processed' => false];
if ($isGuestAuthor) {
    sr_community_record_guest_comment_rate_limit($pdo, $settings);
    $levelSnapshot = ['level_value' => 0, 'score_value' => 0];
    $groupEvaluationSummary = [];
} else {
    sr_community_record_comment_rate_limit($pdo, $authorAccountId, $settings);
    $levelSnapshot = sr_community_maybe_recalculate_account_level($pdo, $authorAccountId, $settings, 'comment_created');
    $groupEvaluationSummary = sr_member_group_evaluate_account($pdo, $authorAccountId, [
        'source_module_key' => 'community',
    ]);
}
sr_audit_log($pdo, [
    'actor_account_id' => $authorAccountId > 0 ? $authorAccountId : null,
    'actor_type' => $isGuestAuthor ? 'guest' : 'member',
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
        'privacy_consent_record_count' => $privacyConsentRecordCount,
    ], sr_community_member_group_evaluation_metadata($groupEvaluationSummary)),
]);
$postAuthorNotificationCreated = false;
$parentAuthorNotificationCreated = false;
if ($isGuestAuthor) {
    $postAuthorNotificationCreated = false;
    $parentAuthorNotificationCreated = false;
    $commentMentionNotificationResult = ['mention_candidate_count' => 0, 'mention_notification_count' => 0, 'mention_account_hashes' => []];
} else {
    $commentAuthorLabel = sr_community_message_account_label(
        (string) ($account['display_name'] ?? ''),
        (int) $account['id'],
        false,
        null,
        (string) ($account['status'] ?? ''),
        sr_community_member_nickname($pdo, (int) $account['id']),
        sr_member_settings($pdo)
    );
    if ((int) $post['author_account_id'] > 0 && (int) $post['author_account_id'] !== (int) $account['id']) {
        $postAuthorNotificationCreated = sr_community_create_account_event_notification(
            $pdo,
            (int) $post['author_account_id'],
            'comment.created',
            [
                'post_id' => $postId,
                'comment_id' => $commentId,
                'parent_comment_id' => (int) ($values['parent_comment_id'] ?? 0),
                'member_name' => $commentAuthorLabel,
                'link_url' => '/community/post?id=' . (string) $postId . '#comments',
                'created_at' => sr_now(),
            ],
            (int) $account['id']
        );
    }
    if (is_array($parentComment) && (int) ($parentComment['author_account_id'] ?? 0) > 0
        && (int) ($parentComment['author_account_id'] ?? 0) !== (int) $account['id']
        && (int) ($parentComment['author_account_id'] ?? 0) !== (int) ($post['author_account_id'] ?? 0)) {
        $parentAuthorNotificationCreated = sr_community_create_account_event_notification(
            $pdo,
            (int) $parentComment['author_account_id'],
            'comment.created',
            [
                'post_id' => $postId,
                'comment_id' => $commentId,
                'parent_comment_id' => (int) ($values['parent_comment_id'] ?? 0),
                'member_name' => $commentAuthorLabel,
                'link_url' => '/community/post?id=' . (string) $postId . '#comments',
                'created_at' => sr_now(),
            ],
            (int) $account['id']
        );
    }
    $mentionExcludeAccountIds = [(int) $post['author_account_id']];
    if (is_array($parentComment) && (int) ($parentComment['author_account_id'] ?? 0) > 0) {
        $mentionExcludeAccountIds[] = (int) $parentComment['author_account_id'];
    }
    $commentMentionNotificationResult = (int) ($values['is_secret'] ?? 0) === 1
        ? ['mention_candidate_count' => 0, 'mention_notification_count' => 0, 'mention_account_hashes' => []]
        : sr_community_create_comment_mention_notifications(
            $pdo,
            $postId,
            $commentId,
            (string) $values['body_text'],
            (int) $account['id'],
            $mentionExcludeAccountIds
        );
}
sr_audit_log($pdo, [
    'actor_account_id' => $authorAccountId > 0 ? $authorAccountId : null,
    'actor_type' => $isGuestAuthor ? 'guest' : 'member',
    'event_type' => 'community.comment.notifications_created',
    'target_type' => 'community_comment',
    'target_id' => (string) $commentId,
    'result' => 'success',
    'message' => 'Community comment notifications created.',
    'metadata' => [
        'post_id' => $postId,
        'parent_comment_id' => (int) ($values['parent_comment_id'] ?? 0),
        'post_author_notification_created' => $postAuthorNotificationCreated,
        'parent_author_notification_created' => $parentAuthorNotificationCreated,
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
