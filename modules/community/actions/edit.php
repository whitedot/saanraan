<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
$postIdValue = sr_get_string('id', 20);
$postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
$post = sr_community_post_for_read($pdo, $postId, $account);
if (!is_array($post)) {
    sr_render_error(404, sr_t('community::action.error.post_not_found'));
}

if (!sr_community_account_can_edit_post($post, $account)) {
    sr_render_error(403, sr_t('community::action.error.post_edit_forbidden'));
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
if (sr_request_method() !== 'POST') {
    sr_community_require_member_nickname($pdo, $account, $settings, (string) ($_SERVER['REQUEST_URI'] ?? '/community'));
}
$board['image_uploads_enabled'] = 0;
$board['file_uploads_enabled'] = 0;
$errors = [];
$values = [
    'title' => (string) $post['title'],
    'body_text' => (string) $post['body_text'],
    'body_format' => (string) ($post['body_format'] ?? 'plain'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    sr_require_csrf();
    if (sr_community_member_needs_nickname($pdo, $account, $settings)) {
        sr_redirect('/community/nickname?next=' . rawurlencode(sr_community_safe_next_path((string) ($_SERVER['REQUEST_URI'] ?? '/community'))));
    }

    $submittedPostIdValue = sr_post_string('post_id', 20);
    $submittedPostId = preg_match('/\A[1-9][0-9]*\z/', $submittedPostIdValue) === 1 ? (int) $submittedPostIdValue : 0;
    if ($submittedPostId !== $postId) {
        sr_render_error(400, sr_t('community::action.error.post_value_invalid'));
    }

    $values = sr_community_post_input_values($pdo, $board, $settings);
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
        $_SESSION['sr_community_post_notice'] = sr_t('community::action.notice.post_updated');
        sr_redirect('/community/post?id=' . (string) $postId);
    }
}

$pageTitle = sr_t('community::action.title.post_edit');
$formAction = '/community/edit?id=' . (string) $postId;
$submitLabel = sr_t('community::action.submit.edit');
$postIdField = $postId;
$skinKey = sr_community_board_skin_key($pdo, $post);
$skinView = sr_community_skin_view($skinKey, 'form');

include $skinView;
