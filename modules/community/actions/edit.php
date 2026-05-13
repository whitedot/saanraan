<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
$postIdValue = sr_get_string('id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$post = sr_community_post_for_read($pdo, $postId, $account);
if (!is_array($post)) {
    sr_render_error(404, '게시글을 찾을 수 없습니다.');
}

if (!sr_community_account_can_edit_post($post, $account)) {
    sr_render_error(403, '이 게시글을 수정할 수 없습니다.');
}

$board = sr_community_board_by_id($pdo, (int) $post['board_id']);
if (!is_array($board)) {
    $board = [
        'id' => (int) $post['board_id'],
        'board_key' => (string) $post['board_key'],
        'title' => (string) $post['board_title'],
    ];
}
$settings = sr_community_settings($pdo);
$board['image_uploads_enabled'] = 0;
$board['file_uploads_enabled'] = 0;
$errors = [];
$values = [
    'title' => (string) $post['title'],
    'body_text' => (string) $post['body_text'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sr_require_csrf();

    $submittedPostIdValue = sr_post_string('post_id', 20);
    $submittedPostId = preg_match('/\A[1-9][0-9]*\z/', $submittedPostIdValue) === 1 ? (int) $submittedPostIdValue : 0;
    if ($submittedPostId !== $postId) {
        sr_render_error(400, '요청한 게시글 값이 올바르지 않습니다.');
    }

    $values = sr_community_post_input_values();
    $errors = sr_community_validate_post_input($values);

    if ($errors === []) {
        sr_community_update_post_content($pdo, $postId, $values);
        sr_audit_log($pdo, [
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
        $_SESSION['sr_community_post_notice'] = '게시글을 수정했습니다.';
        sr_redirect('/community/post?id=' . (string) $postId);
    }
}

$pageTitle = '게시글 수정';
$formAction = '/community/edit?id=' . (string) $postId;
$submitLabel = '수정';
$postIdField = $postId;
$skinKey = sr_community_board_skin_key($pdo, $post);
$skinView = sr_community_skin_view($skinKey, 'form');

include $skinView;
