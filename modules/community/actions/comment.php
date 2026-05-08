<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_require_login($pdo);
toy_require_csrf();

$postIdValue = toy_post_string('post_id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$post = toy_community_public_post($pdo, $postId);
if (!is_array($post)) {
    toy_render_error(404, '게시글을 찾을 수 없습니다.');
}

if (!toy_community_account_can_comment_post($pdo, $post, $account)) {
    toy_render_error(403, '이 게시글에 댓글을 작성할 수 없습니다.');
}

$settings = toy_module_settings($pdo, 'community');
$values = toy_community_comment_input_values();
$errors = toy_community_validate_comment_input($values);

if ($errors === [] && toy_community_comment_rate_limited($pdo, (int) $account['id'], $settings)) {
    $errors[] = '짧은 시간에 댓글을 너무 많이 작성했습니다. 잠시 후 다시 시도해 주세요.';
}

if ($errors !== []) {
    $_SESSION['toy_community_comment_errors'] = $errors;
    $_SESSION['toy_community_comment_body'] = is_string($values['body_text']) ? $values['body_text'] : '';
    toy_redirect('/community/post?id=' . (string) $postId . '#comments');
}

toy_community_create_comment($pdo, $postId, (int) $account['id'], $values);
toy_community_record_comment_rate_limit($pdo, (int) $account['id'], $settings);
toy_redirect('/community/post?id=' . (string) $postId . '#comments');
