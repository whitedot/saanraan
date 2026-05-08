<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/admin/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_require_login($pdo);
toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);

$errors = [];
$notice = '';
$allowedStatuses = toy_community_board_statuses();
$allowedReadPolicies = toy_community_policy_values('read');
$allowedWritePolicies = toy_community_policy_values('write');
$allowedCommentPolicies = toy_community_policy_values('comment');

if (toy_request_method() === 'POST') {
    toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);
    toy_require_csrf();

    $intent = toy_post_string('intent', 40);
    $boardKey = toy_post_string('board_key', 60);
    $title = toy_post_string('title', 120);
    $description = toy_post_string_without_truncation('description', 2000);
    $status = toy_post_string('status', 30);
    $readPolicy = toy_post_string('read_policy', 30);
    $writePolicy = toy_post_string('write_policy', 30);
    $commentPolicy = toy_post_string('comment_policy', 30);
    $sortOrder = toy_admin_post_int_in_range('sort_order', 0, 1000000);

    if ($intent === 'create' && !toy_community_board_key_is_valid($boardKey)) {
        $errors[] = '게시판 key는 영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 사용할 수 있습니다.';
    }

    if ($title === '') {
        $errors[] = '게시판 이름을 입력하세요.';
    }

    if ($description === null) {
        $errors[] = '설명은 2000자 이하로 입력하세요.';
        $description = '';
    }

    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = '게시판 상태 값이 올바르지 않습니다.';
    }

    if (!in_array($readPolicy, $allowedReadPolicies, true)) {
        $errors[] = '읽기 정책 값이 올바르지 않습니다.';
    }

    if (!in_array($writePolicy, $allowedWritePolicies, true)) {
        $errors[] = '쓰기 정책 값이 올바르지 않습니다.';
    }

    if (!in_array($commentPolicy, $allowedCommentPolicies, true)) {
        $errors[] = '댓글 정책 값이 올바르지 않습니다.';
    }

    if ($sortOrder === null) {
        $errors[] = '정렬 순서는 0 이상의 정수여야 합니다.';
        $sortOrder = 0;
    }

    if ($errors === [] && $intent === 'create' && toy_community_board_by_key($pdo, $boardKey) !== null) {
        $errors[] = '이미 사용 중인 게시판 key입니다.';
    }

    if ($intent === 'create' && $errors === []) {
        $boardId = toy_community_create_board($pdo, [
            'board_key' => $boardKey,
            'title' => $title,
            'description' => (string) $description,
            'status' => $status,
            'read_policy' => $readPolicy,
            'write_policy' => $writePolicy,
            'comment_policy' => $commentPolicy,
            'image_uploads_enabled' => ($_POST['image_uploads_enabled'] ?? '') === '1',
            'sort_order' => (int) $sortOrder,
        ]);

        toy_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'community.board.created',
            'target_type' => 'community_board',
            'target_id' => (string) $boardId,
            'result' => 'success',
            'message' => 'Community board created.',
            'metadata' => [
                'board_key' => $boardKey,
                'status' => $status,
            ],
        ]);

        $notice = '게시판을 만들었습니다.';
    } elseif ($intent === 'update' && $errors === []) {
        $boardIdValue = toy_post_string('board_id', 20);
        $boardId = preg_match('/\A[1-9][0-9]*\z/', $boardIdValue) === 1 ? (int) $boardIdValue : 0;
        $board = toy_community_board_by_id($pdo, $boardId);
        if (!is_array($board)) {
            $errors[] = '게시판을 찾을 수 없습니다.';
        }

        if ($errors === [] && is_array($board)) {
            toy_community_update_board($pdo, $boardId, [
                'title' => $title,
                'description' => (string) $description,
                'status' => $status,
                'read_policy' => $readPolicy,
                'write_policy' => $writePolicy,
                'comment_policy' => $commentPolicy,
                'image_uploads_enabled' => ($_POST['image_uploads_enabled'] ?? '') === '1',
                'sort_order' => (int) $sortOrder,
            ]);

            toy_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'community.board.updated',
                'target_type' => 'community_board',
                'target_id' => (string) $boardId,
                'result' => 'success',
                'message' => 'Community board updated.',
                'metadata' => [
                    'board_key' => (string) $board['board_key'],
                    'before_status' => (string) $board['status'],
                    'after_status' => $status,
                ],
            ]);

            $notice = '게시판 설정을 변경했습니다.';
        }
    } elseif (!in_array($intent, ['create', 'update'], true)) {
        $errors[] = '작업 값이 올바르지 않습니다.';
    }
}

$boards = toy_community_boards($pdo);

include TOY_ROOT . '/modules/community/views/admin-boards.php';
