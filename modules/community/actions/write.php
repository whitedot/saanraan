<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/admin/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_require_login($pdo);
$boardKey = toy_get_string('key', 60);
$board = toy_community_board_by_key($pdo, $boardKey);
if (!is_array($board) || (string) $board['status'] !== 'enabled') {
    toy_render_error(404, '게시판을 찾을 수 없습니다.');
}

$isAdminWriter = toy_admin_has_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);
if (!toy_community_account_can_write_board($pdo, $board, $account, $isAdminWriter)) {
    toy_render_error(403, '이 게시판에 글을 작성할 수 없습니다.');
}

$settings = toy_module_settings($pdo, 'community');
$errors = [];
$notice = '';
$values = [
    'title' => '',
    'body_text' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    toy_require_csrf();

    $values = toy_community_post_input_values();
    $errors = toy_community_validate_post_input($values);

    if ($errors === [] && toy_community_post_rate_limited($pdo, (int) $account['id'], $settings)) {
        $errors[] = '짧은 시간에 글을 너무 많이 작성했습니다. 잠시 후 다시 시도해 주세요.';
    }

    if ($errors === []) {
        $postId = toy_community_create_post($pdo, (int) $board['id'], (int) $account['id'], $values);
        toy_community_record_post_rate_limit($pdo, (int) $account['id'], $settings);
        toy_member_group_evaluate_account($pdo, (int) $account['id'], ['source_module_key' => 'community']);
        toy_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'community.post.created',
            'target_type' => 'community_post',
            'target_id' => (string) $postId,
            'result' => 'success',
            'message' => 'Community post created.',
            'metadata' => [
                'board_key' => (string) $board['board_key'],
            ],
        ]);
        toy_redirect('/community/post?id=' . (string) $postId);
    }
}

$skinKey = toy_community_skin_key();
$skinView = toy_community_skin_view($skinKey, 'form');

include $skinView;
