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
$values = sr_community_comment_input_values();
$errors = sr_community_validate_comment_input($values);

if ($errors === [] && sr_community_comment_rate_limited($pdo, (int) $account['id'], $settings)) {
    $errors[] = '짧은 시간에 댓글을 너무 많이 작성했습니다. 잠시 후 다시 시도해 주세요.';
}

if ($errors !== []) {
    $_SESSION['sr_community_comment_errors'] = $errors;
    $_SESSION['sr_community_comment_body'] = is_string($values['body_text']) ? $values['body_text'] : '';
    sr_redirect('/community/post?id=' . (string) $postId . '#comments');
}

$commentId = sr_community_create_comment($pdo, $postId, (int) $account['id'], $values);
sr_community_record_comment_rate_limit($pdo, (int) $account['id'], $settings);
$levelSnapshot = sr_community_maybe_recalculate_account_level($pdo, (int) $account['id'], $settings, 'comment_created');
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'community.comment.created',
    'target_type' => 'community_comment',
    'target_id' => (string) $commentId,
    'result' => 'success',
    'message' => 'Community comment created.',
    'metadata' => [
        'post_id' => $postId,
        'community_level_value' => (int) ($levelSnapshot['level_value'] ?? 0),
        'community_score_value' => (int) ($levelSnapshot['score_value'] ?? 0),
    ],
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
$_SESSION['sr_community_comment_notice'] = '댓글을 등록했습니다.';
sr_redirect('/community/post?id=' . (string) $postId . '#comments');
