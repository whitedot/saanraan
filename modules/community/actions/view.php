<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$postIdValue = toy_get_string('id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$post = toy_community_public_post($pdo, $postId);
if (!is_array($post)) {
    toy_render_error(404, '게시글을 찾을 수 없습니다.');
}

$settings = toy_module_settings($pdo, 'community');
$commentsPerPage = max(1, min(100, (int) ($settings['comments_per_page'] ?? 50)));
$comments = toy_community_public_comments($pdo, (int) $post['id'], $commentsPerPage);
$skinKey = toy_community_skin_key();
$skinView = toy_community_skin_view($skinKey, 'post');

include $skinView;
