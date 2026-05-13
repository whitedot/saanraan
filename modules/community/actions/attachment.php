<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_current_account($pdo);
$attachmentIdValue = sr_get_string('id', 20);
$attachmentId = preg_match('/\A[1-9][0-9]*\z/', $attachmentIdValue) === 1 ? (int) $attachmentIdValue : 0;
$attachment = sr_community_attachment_for_read($pdo, $attachmentId, is_array($account) ? $account : null);
if (!is_array($attachment)) {
    $board = sr_community_attachment_read_board($pdo, $attachmentId);
    if (is_array($board) && in_array(sr_community_effective_board_policy($pdo, $board, 'read_policy'), ['member', 'group'], true) && !is_array($account)) {
        $account = sr_member_require_login($pdo);
        $attachment = sr_community_attachment_for_read($pdo, $attachmentId, $account);
    }
    if (!is_array($attachment)
        && is_array($board)
        && !sr_community_account_can_read_board($pdo, $board, is_array($account) ? $account : null)
    ) {
        sr_render_error(403, '첨부 파일을 볼 수 없습니다.');
    }
}
if (!is_array($attachment)) {
    sr_render_error(404, '첨부 파일을 찾을 수 없습니다.');
}

$mimeType = (string) $attachment['mime_type'];
$driver = sr_community_attachment_storage_driver($attachment);
$storageKey = sr_community_attachment_storage_key($attachment);
if (!sr_community_attachment_mime_is_allowed($mimeType) || $storageKey === '') {
    sr_render_error(404, '첨부 파일을 찾을 수 없습니다.');
}

$recordedSize = (int) ($attachment['size_bytes'] ?? 0);
$recordedChecksum = (string) ($attachment['checksum_sha256'] ?? '');
$head = sr_storage_head($driver, $storageKey);
if (!is_array($head) || $recordedSize < 1 || (int) ($head['content_length'] ?? 0) !== $recordedSize) {
    sr_render_error(404, '첨부 파일을 찾을 수 없습니다.');
}

$actualChecksum = (string) (($head['metadata']['sha256'] ?? '') ?: '');
if (preg_match('/\A[a-f0-9]{64}\z/', $recordedChecksum) !== 1 || $actualChecksum === '' || !hash_equals($recordedChecksum, $actualChecksum)) {
    sr_render_error(404, '첨부 파일을 찾을 수 없습니다.');
}

$disposition = sr_community_attachment_is_image($attachment) ? 'inline' : 'attachment';
if ($driver === 's3') {
    $downloadUrl = sr_storage_signed_url('s3', $storageKey, 300, [
        'response-content-type' => sr_download_content_type($mimeType),
        'response-content-disposition' => $disposition . '; filename="' . sr_download_filename((string) $attachment['original_name']) . '"',
    ]);
    if ($downloadUrl === '') {
        sr_render_error(404, '첨부 파일을 찾을 수 없습니다.');
    }

    header('Cache-Control: private, max-age=300');
    sr_redirect_external($downloadUrl);
}

$filePath = sr_community_attachment_file_path($attachment);
if (!is_string($filePath)) {
    sr_render_error(404, '첨부 파일을 찾을 수 없습니다.');
}

header('Content-Type: ' . sr_download_content_type($mimeType));
header('Content-Disposition: ' . $disposition . '; filename="' . sr_download_filename((string) $attachment['original_name']) . '"');
header('Content-Length: ' . (string) $recordedSize);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
readfile($filePath);
sr_finish_response();
