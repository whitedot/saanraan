<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (sr_request_method() !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => ['message' => '허용되지 않는 요청입니다.']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        sr_finish_response();
    }

    $account = sr_member_require_login($pdo);
    sr_require_csrf();
    $boardKey = sr_get_string('board_key', 60);
    $board = sr_community_board_by_key($pdo, $boardKey);
    if (!is_array($board) || (string) ($board['status'] ?? '') !== 'enabled') {
        throw new RuntimeException('게시판을 찾을 수 없습니다.');
    }
    $postIdValue = sr_get_string('post_id', 20);
    $postId = preg_match('/\A[1-9][0-9]*\z/', $postIdValue) === 1 ? (int) $postIdValue : 0;
    $post = null;
    if ($postId > 0) {
        $post = sr_community_post_for_read($pdo, $postId, $account);
        if (!is_array($post)) {
            throw new RuntimeException('게시글을 찾을 수 없습니다.');
        }
        if ((int) ($post['board_id'] ?? 0) !== (int) ($board['id'] ?? 0)) {
            throw new RuntimeException('게시판과 게시글이 일치하지 않습니다.');
        }
    }

    $upload = $_FILES['upload'] ?? $_FILES['file'] ?? null;
    if (!is_array($upload)) {
        throw new RuntimeException('업로드할 본문 이미지를 선택하세요.');
    }

    $stored = sr_community_upload_body_file($pdo, (int) $account['id'], $board, $upload, sr_post_string('upload_token', 64), $post);
    try {
        sr_community_cleanup_expired_body_files($pdo, 5);
    } catch (Throwable $cleanupException) {
        sr_log_exception($cleanupException, 'community_body_file_opportunistic_cleanup_failed');
    }
    echo json_encode([
        'url' => (string) $stored['url'],
        'width' => (int) ($stored['width'] ?? 0),
        'height' => (int) ($stored['height'] ?? 0),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    sr_log_exception($exception, 'community_body_file_upload_failed');
    http_response_code(400);
    echo json_encode([
        'error' => [
            'message' => $exception instanceof RuntimeException ? $exception->getMessage() : '본문 이미지 업로드를 처리할 수 없습니다.',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

sr_finish_response();
