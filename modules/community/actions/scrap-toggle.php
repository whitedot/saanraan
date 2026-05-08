<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_require_login($pdo);
toy_require_csrf();

$postIdValue = toy_post_string('post_id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$intent = toy_post_string('intent', 20);

if ($intent === 'remove') {
    toy_community_remove_scrap($pdo, (int) $account['id'], $postId);
    $post = toy_community_public_post($pdo, $postId);
    if (!is_array($post)) {
        toy_redirect('/community/scraps');
    }
    toy_redirect('/community/post?id=' . (string) $postId);
}

$post = toy_community_public_post($pdo, $postId);
if (!is_array($post)) {
    toy_render_error(404, '게시글을 찾을 수 없습니다.');
} else {
    toy_community_add_scrap($pdo, (int) $account['id'], $postId);
}

toy_redirect('/community/post?id=' . (string) $postId);
