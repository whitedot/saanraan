<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/admin/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_require_login($pdo);
toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);

$errors = [];
$notice = '';
$allowedStatuses = toy_community_post_statuses();

if (toy_request_method() === 'POST') {
    toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);
    toy_require_csrf();

    $postIdValue = toy_post_string('post_id', 20);
    $postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
    $status = toy_post_string('status', 30);
    $post = toy_community_admin_post_by_id($pdo, $postId);

    if (!is_array($post)) {
        $errors[] = '게시글을 찾을 수 없습니다.';
    }

    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = '게시글 상태 값이 올바르지 않습니다.';
    }

    if ($errors === [] && is_array($post)) {
        toy_community_update_post_status($pdo, $postId, $status);
        toy_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'community.post.status_updated',
            'target_type' => 'community_post',
            'target_id' => (string) $postId,
            'result' => 'success',
            'message' => 'Community post status updated.',
            'metadata' => [
                'before_status' => (string) $post['status'],
                'after_status' => $status,
            ],
        ]);
        $notice = '게시글 상태를 변경했습니다.';
    }
}

$posts = toy_community_admin_posts($pdo, 100);

include TOY_ROOT . '/modules/community/views/admin-posts.php';
