<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_require_login($pdo);
$postIdValue = toy_get_string('id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$post = toy_community_post_for_read($pdo, $postId, $account);
if (!is_array($post)) {
    toy_render_error(404, '게시글을 찾을 수 없습니다.');
}

if (!toy_community_account_can_edit_post($post, $account)) {
    toy_render_error(403, '이 게시글을 수정할 수 없습니다.');
}

$board = [
    'id' => (int) $post['board_id'],
    'board_key' => (string) $post['board_key'],
    'title' => (string) $post['board_title'],
];
$errors = [];
$values = [
    'title' => (string) $post['title'],
    'body_text' => (string) $post['body_text'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    toy_require_csrf();

    $submittedPostIdValue = toy_post_string('post_id', 20);
    $submittedPostId = preg_match('/\A[1-9][0-9]*\z/', $submittedPostIdValue) === 1 ? (int) $submittedPostIdValue : 0;
    if ($submittedPostId !== $postId) {
        toy_render_error(400, '요청한 게시글 값이 올바르지 않습니다.');
    }

    $values = toy_community_post_input_values();
    $errors = toy_community_validate_post_input($values);

    if ($errors === []) {
        toy_community_update_post_content($pdo, $postId, $values);
        toy_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'community.post.updated_by_author',
            'target_type' => 'community_post',
            'target_id' => (string) $postId,
            'result' => 'success',
            'message' => 'Community post updated by author.',
            'metadata' => [
                'board_key' => (string) $post['board_key'],
            ],
        ]);
        toy_redirect('/community/post?id=' . (string) $postId);
    }
}

$pageTitle = '게시글 수정';
$formAction = '/community/edit?id=' . (string) $postId;
$submitLabel = '수정';
$postIdField = $postId;
$skinKey = toy_community_skin_key();
$skinView = toy_community_skin_view($skinKey, 'form');

include $skinView;
