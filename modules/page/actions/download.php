<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/page/helpers.php';
require_once SR_ROOT . '/modules/page/helpers/member-groups.php';
require_once SR_ROOT . '/modules/member/helpers.php';

$fileId = (int) sr_get_string('id', 20);
$file = sr_page_published_file_by_id($pdo, $fileId);
if (!is_array($file)) {
    sr_render_error(404, sr_t('page::action.error.download_file_not_found'));
}

if (sr_page_file_download_required($file)) {
    $account = sr_member_require_login($pdo);
    $downloadAccess = sr_page_charge_file_download($pdo, $file, (int) $account['id']);
    if (empty($downloadAccess['allowed'])) {
        sr_render_error(403, (string) ($downloadAccess['message'] ?? sr_t('page::action.error.download_forbidden')));
    }
    if (!empty($downloadAccess['charged'])) {
        sr_page_member_group_evaluate_after_activity($pdo, (int) $account['id']);
    }
}

$mimeType = (string) $file['mime_type'];
$driver = sr_page_file_storage_driver($file);
$storageKey = sr_page_file_storage_key($file);
if (!sr_page_file_mime_is_allowed($mimeType) || $storageKey === '') {
    sr_render_error(404, sr_t('page::action.error.download_file_not_found'));
}

$recordedSize = (int) ($file['size_bytes'] ?? 0);
$recordedChecksum = (string) ($file['checksum_sha256'] ?? '');
$head = sr_storage_head($driver, $storageKey);
if (!is_array($head) || $recordedSize < 1 || (int) ($head['content_length'] ?? 0) !== $recordedSize) {
    sr_render_error(404, sr_t('page::action.error.download_file_not_found'));
}

$actualChecksum = (string) (($head['metadata']['sha256'] ?? '') ?: '');
if (preg_match('/\A[a-f0-9]{64}\z/', $recordedChecksum) !== 1 || $actualChecksum === '' || !hash_equals($recordedChecksum, $actualChecksum)) {
    sr_render_error(404, sr_t('page::action.error.download_file_not_found'));
}

if ($driver === 's3') {
    $downloadUrl = sr_storage_signed_url('s3', $storageKey, 300, [
        'response-content-type' => sr_download_content_type($mimeType),
        'response-content-disposition' => 'attachment; filename="' . sr_download_filename((string) $file['original_name']) . '"',
    ]);
    if ($downloadUrl === '') {
        sr_render_error(404, sr_t('page::action.error.download_file_not_found'));
    }

    header('Cache-Control: private, max-age=300');
    sr_redirect_external($downloadUrl);
}

$filePath = sr_page_file_path($file);
if (!is_string($filePath)) {
    sr_render_error(404, sr_t('page::action.error.download_file_not_found'));
}

header('Content-Type: ' . sr_download_content_type($mimeType));
header('Content-Disposition: attachment; filename="' . sr_download_filename((string) $file['original_name']) . '"');
header('Content-Length: ' . (string) $recordedSize);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
readfile($filePath);
sr_finish_response();
