<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

$reference = sr_get_string('file', 180);
$storage = sr_member_profile_image_storage_reference($reference);
if (!is_array($storage)) {
    sr_render_error(404, sr_t('member::action.profile_image.not_found'));
}

$driver = (string) $storage['driver'];
$key = (string) $storage['key'];
if ($driver === 's3') {
    $url = sr_storage_public_url('s3', $key);
    if ($url === '') {
        $url = sr_storage_signed_url('s3', $key, 300);
    }

    if ($url === '') {
        sr_render_error(404, sr_t('member::action.profile_image.not_found'));
    }

    header('Cache-Control: private, max-age=300');
    sr_redirect_trusted_external($url);
}

$imagePath = sr_storage_local_path($key);
if (!is_string($imagePath)) {
    sr_render_error(404, sr_t('member::action.profile_image.not_found'));
}

$mimeType = sr_upload_detect_mime($imagePath);
$sizeBytes = filesize($imagePath);
$lastModified = filemtime($imagePath);
if (!sr_member_profile_image_mime_is_allowed($mimeType) || !is_int($sizeBytes) || !is_int($lastModified)) {
    sr_render_error(404, sr_t('member::action.profile_image.not_found'));
}

$cacheControl = 'public, max-age=31536000, immutable';
$etag = '"' . hash('sha256', $key . ':' . (string) $sizeBytes . ':' . (string) $lastModified) . '"';
if (sr_file_not_modified($etag, $lastModified)) {
    http_response_code(304);
    sr_send_file_cache_headers($cacheControl, $etag, $lastModified);
    sr_finish_response();
}

sr_send_file_headers($mimeType, $sizeBytes, $cacheControl, [
    'ETag: ' . $etag,
    'Last-Modified: ' . sr_http_date($lastModified),
]);
readfile($imagePath);
sr_finish_response();
