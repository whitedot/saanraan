<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/content/helpers.php';
require_once SR_ROOT . '/modules/content/helpers/member-groups.php';
require_once SR_ROOT . '/modules/member/helpers.php';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
}

$fileIdValue = sr_request_method() === 'POST' ? sr_post_string('id', 20) : sr_get_string('id', 20);
$fileId = preg_match('/\A[1-9][0-9]*\z/', $fileIdValue) === 1 ? (int) $fileIdValue : 0;
$contentIdValue = sr_request_method() === 'POST' ? sr_post_string('content_id', 20) : sr_get_string('content_id', 20);
$contentId = preg_match('/\A[1-9][0-9]*\z/', $contentIdValue) === 1 ? (int) $contentIdValue : 0;
$file = sr_content_published_file_by_id($pdo, $fileId, $contentId);
if (!is_array($file)) {
    sr_render_error(404, sr_t('content::action.error.download_file_not_found'));
}
$contentPath = sr_content_slug_is_valid((string) ($file['slug'] ?? ''))
    ? sr_content_path((string) $file['slug'])
    : '';

$mimeType = (string) $file['mime_type'];
$driver = sr_content_file_storage_driver($file);
$storageKey = sr_content_file_storage_key($file);
if (!sr_content_file_mime_is_allowed($mimeType) || $storageKey === '') {
    sr_render_error(404, sr_t('content::action.error.download_file_not_found'));
}

$recordedSize = (int) ($file['size_bytes'] ?? 0);
$recordedChecksum = (string) ($file['checksum_sha256'] ?? '');
$head = sr_storage_head($driver, $storageKey);
if (!is_array($head) || $recordedSize < 1 || (int) ($head['content_length'] ?? 0) !== $recordedSize) {
    sr_render_error(404, sr_t('content::action.error.download_file_not_found'));
}

$actualChecksum = (string) (($head['metadata']['sha256'] ?? '') ?: '');
if (preg_match('/\A[a-f0-9]{64}\z/', $recordedChecksum) !== 1 || $actualChecksum === '' || !hash_equals($recordedChecksum, $actualChecksum)) {
    sr_render_error(404, sr_t('content::action.error.download_file_not_found'));
}

$downloadUrl = '';
$filePath = null;
if ($driver === 's3') {
    $downloadUrl = sr_storage_signed_url('s3', $storageKey, 300, [
        'response-content-type' => sr_download_content_type($mimeType),
        'response-content-disposition' => sr_download_content_disposition((string) $file['original_name']),
    ]);
    if ($downloadUrl === '') {
        sr_render_error(404, sr_t('content::action.error.download_file_not_found'));
    }
} else {
    $filePath = sr_content_file_path($file);
    if (!is_string($filePath)) {
        sr_render_error(404, sr_t('content::action.error.download_file_not_found'));
    }
}

$downloadAccountId = null;
$downloadAccess = [];
$downloadAlreadyRecorded = false;
if (sr_content_file_download_required($file)) {
    $account = sr_member_require_login($pdo);
    $downloadAccountId = (int) $account['id'];
    $assetRequestToken = sr_post_string_without_truncation('asset_request_token', 64) ?? '';
    $assetConfirmedPost = sr_request_method() === 'POST' && sr_post_string('asset_confirm', 1) === '1';
    $couponIssueIdValue = sr_request_method() === 'POST' ? (sr_post_string('coupon_issue_id', 20) ?? '') : '';
    $couponIssueId = preg_match('/\A[1-9][0-9]*\z/', $couponIssueIdValue) === 1 ? (int) $couponIssueIdValue : 0;
    $downloadAccess = sr_content_charge_file_download($pdo, $file, (int) $account['id'], sr_request_method() === 'POST', $assetRequestToken, $couponIssueId, true, $assetConfirmedPost);
    if (empty($downloadAccess['allowed'])) {
        if ((string) ($downloadAccess['error_key'] ?? '') === 'asset_confirmation_required') {
            if ($contentPath !== '') {
                $_SESSION['sr_content_action_errors'] = ['다운로드 확인이 필요합니다. 파일 버튼을 다시 눌러 확인해 주세요.'];
                sr_redirect($contentPath);
            }

            sr_render_error(403, (string) ($downloadAccess['message'] ?? sr_t('content::action.error.download_forbidden')));
        }
        if ($contentPath !== '') {
            $_SESSION['sr_content_action_errors'] = [(string) ($downloadAccess['message'] ?? sr_t('content::action.error.download_forbidden'))];
            sr_redirect($contentPath);
        }

        sr_render_error(403, (string) ($downloadAccess['message'] ?? sr_t('content::action.error.download_forbidden')));
    }
    if (!empty($downloadAccess['charged'])) {
        sr_content_member_group_evaluate_after_activity($pdo, (int) $account['id']);
    }
    if (sr_request_method() === 'POST') {
        if (sr_content_asset_policy_requires_confirmation((string) ($file['asset_charge_policy'] ?? 'once'))) {
            sr_content_mark_asset_confirmation_session('download', (int) $account['id'], (int) $file['id'], (string) ($downloadAccess['confirmation_fingerprint'] ?? ''));
        }
        sr_content_record_file_download($pdo, $file, $downloadAccountId, $downloadAccess);
        $downloadAlreadyRecorded = true;
        sr_redirect('/content/download?id=' . rawurlencode((string) $file['id']) . '&content_id=' . rawurlencode((string) (int) ($file['content_id'] ?? 0)));
    }
} else {
    $currentAccount = sr_member_current_account($pdo);
    if (is_array($currentAccount)) {
        $downloadAccountId = (int) $currentAccount['id'];
    }
}

if (!$downloadAlreadyRecorded && empty($downloadAccess['confirmed_access'])) {
    sr_content_record_file_download($pdo, $file, $downloadAccountId, $downloadAccess);
}

if ($downloadUrl !== '') {
    header('Cache-Control: private, max-age=300');
    sr_redirect_trusted_external($downloadUrl);
}

sr_send_download_headers($mimeType, (string) $file['original_name'], 'attachment', $recordedSize, 'private, no-store, no-cache, must-revalidate');
readfile($filePath);
sr_finish_response();
