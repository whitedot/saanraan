<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/content/helpers.php';
require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

try {
    if (sr_request_method() !== 'POST') {
        sr_json_response(['error' => ['message' => '허용되지 않는 요청입니다.']], 405);
    }

    $account = sr_member_require_login($pdo);
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content', 'edit');
    sr_require_csrf();

    $upload = $_FILES['upload'] ?? $_FILES['file'] ?? null;
    if (!is_array($upload)) {
        throw new RuntimeException('업로드할 본문 이미지를 선택하세요.');
    }

    $uploadToken = sr_post_string_without_truncation('upload_token', 32) ?? '';
    $stored = sr_content_upload_body_file($pdo, (int) $account['id'], $upload, $uploadToken);
    try {
        sr_content_cleanup_expired_body_files($pdo, 5);
    } catch (Throwable $cleanupException) {
        sr_log_exception($cleanupException, 'content_body_file_opportunistic_cleanup_failed');
    }
    sr_json_response([
        'url' => (string) $stored['url'],
        'width' => (int) ($stored['width'] ?? 0),
        'height' => (int) ($stored['height'] ?? 0),
    ]);
} catch (Throwable $exception) {
    sr_log_exception($exception, 'content_body_file_upload_failed');
    sr_json_response([
        'error' => [
            'message' => $exception instanceof RuntimeException ? $exception->getMessage() : '본문 이미지 업로드를 처리할 수 없습니다.',
        ],
    ], 400);
}
