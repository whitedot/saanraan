<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

$reference = sr_get_string('file', 180);
$storage = sr_member_avatar_storage_reference($reference);
if (!is_array($storage)) {
    sr_render_error(404, sr_t('member::action.avatar.not_found'));
}

$driver = (string) $storage['driver'];
$key = (string) $storage['key'];
if ($driver === 's3') {
    $url = sr_storage_public_url('s3', $key);
    if ($url === '') {
        $url = sr_storage_signed_url('s3', $key, 300);
    }

    if ($url === '') {
        sr_render_error(404, sr_t('member::action.avatar.not_found'));
    }

    header('Cache-Control: private, max-age=300');
    sr_redirect_external($url);
}

$imagePath = sr_storage_local_path($key);
if (!is_string($imagePath)) {
    sr_render_error(404, sr_t('member::action.avatar.not_found'));
}

$mimeType = sr_upload_detect_mime($imagePath);
$sizeBytes = filesize($imagePath);
if (!sr_member_avatar_mime_is_allowed($mimeType) || !is_int($sizeBytes)) {
    sr_render_error(404, sr_t('member::action.avatar.not_found'));
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) $sizeBytes);
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
readfile($imagePath);
sr_finish_response();
