<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_current_account($pdo);
$attachmentIdValue = toy_get_string('id', 20);
$attachmentId = preg_match('/\A[1-9][0-9]*\z/', $attachmentIdValue) === 1 ? (int) $attachmentIdValue : 0;
$attachment = toy_community_attachment_for_read($pdo, $attachmentId, is_array($account) ? $account : null);
if (!is_array($attachment)) {
    $board = toy_community_attachment_read_board($pdo, $attachmentId);
    if (is_array($board) && in_array(toy_community_effective_board_policy($pdo, $board, 'read_policy'), ['member', 'group'], true) && !is_array($account)) {
        $account = toy_member_require_login($pdo);
        $attachment = toy_community_attachment_for_read($pdo, $attachmentId, $account);
    }
    if (!is_array($attachment)
        && is_array($board)
        && !toy_community_account_can_read_board($pdo, $board, is_array($account) ? $account : null)
    ) {
        toy_render_error(403, '첨부 파일을 볼 수 없습니다.');
    }
}
if (!is_array($attachment)) {
    toy_render_error(404, '첨부 파일을 찾을 수 없습니다.');
}

$mimeType = (string) $attachment['mime_type'];
$driver = toy_community_attachment_storage_driver($attachment);
$storageKey = toy_community_attachment_storage_key($attachment);
if (!toy_community_attachment_mime_is_allowed($mimeType) || $storageKey === '') {
    toy_render_error(404, '첨부 파일을 찾을 수 없습니다.');
}

$recordedSize = (int) ($attachment['size_bytes'] ?? 0);
$recordedChecksum = (string) ($attachment['checksum_sha256'] ?? '');
$head = toy_storage_head($driver, $storageKey);
if (!is_array($head) || $recordedSize < 1 || (int) ($head['content_length'] ?? 0) !== $recordedSize) {
    toy_render_error(404, '첨부 파일을 찾을 수 없습니다.');
}

$actualChecksum = (string) (($head['metadata']['sha256'] ?? '') ?: '');
if (preg_match('/\A[a-f0-9]{64}\z/', $recordedChecksum) !== 1 || $actualChecksum === '' || !hash_equals($recordedChecksum, $actualChecksum)) {
    toy_render_error(404, '첨부 파일을 찾을 수 없습니다.');
}

$disposition = toy_community_attachment_is_image($attachment) ? 'inline' : 'attachment';
if ($driver === 's3') {
    $downloadUrl = toy_storage_signed_url('s3', $storageKey, 300, [
        'response-content-type' => toy_download_content_type($mimeType),
        'response-content-disposition' => $disposition . '; filename="' . toy_download_filename((string) $attachment['original_name']) . '"',
    ]);
    if ($downloadUrl === '') {
        toy_render_error(404, '첨부 파일을 찾을 수 없습니다.');
    }

    header('Cache-Control: private, max-age=300');
    toy_redirect_external($downloadUrl);
}

$filePath = toy_community_attachment_file_path($attachment);
if (!is_string($filePath)) {
    toy_render_error(404, '첨부 파일을 찾을 수 없습니다.');
}

header('Content-Type: ' . toy_download_content_type($mimeType));
header('Content-Disposition: ' . $disposition . '; filename="' . toy_download_filename((string) $attachment['original_name']) . '"');
header('Content-Length: ' . (string) $recordedSize);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
readfile($filePath);
toy_finish_response();
