<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_current_account($pdo);
$attachmentIdValue = toy_get_string('id', 20);
$attachmentId = preg_match('/\A[1-9][0-9]*\z/', $attachmentIdValue) === 1 ? (int) $attachmentIdValue : 0;
$attachment = toy_community_attachment_for_read($pdo, $attachmentId, is_array($account) ? $account : null);
if (!is_array($attachment)) {
    toy_render_error(404, '첨부 파일을 찾을 수 없습니다.');
}

$mimeType = (string) $attachment['mime_type'];
$filePath = toy_community_attachment_file_path($attachment);
if (!toy_community_attachment_mime_is_allowed($mimeType) || !is_string($filePath)) {
    toy_render_error(404, '첨부 파일을 찾을 수 없습니다.');
}

$fileSize = filesize($filePath);
$recordedSize = (int) ($attachment['size_bytes'] ?? 0);
if (!is_int($fileSize) || $fileSize < 0 || $recordedSize < 1 || $recordedSize !== $fileSize) {
    toy_render_error(404, '첨부 파일을 찾을 수 없습니다.');
}

header('Content-Type: ' . toy_download_content_type($mimeType));
header('Content-Disposition: inline; filename="' . toy_download_filename((string) $attachment['original_name']) . '"');
header('Content-Length: ' . (string) $fileSize);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
readfile($filePath);
toy_finish_response();
