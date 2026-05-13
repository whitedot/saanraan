<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/banner/helpers.php';

$storageKey = sr_get_string('file', 180);
$storage = sr_banner_image_storage_reference($storageKey);
if (!is_array($storage)) {
    sr_render_error(404, '배너 이미지를 찾을 수 없습니다.');
}

$driver = (string) $storage['driver'];
$key = (string) $storage['key'];
if ($driver === 's3') {
    $url = sr_storage_public_url('s3', $key);
    if ($url === '') {
        $url = sr_storage_signed_url('s3', $key, 300);
    }

    if ($url === '') {
        sr_render_error(404, '배너 이미지를 찾을 수 없습니다.');
    }

    header('Cache-Control: private, max-age=300');
    sr_redirect_external($url);
}

$imagePath = sr_storage_local_path($key);
if (!is_string($imagePath)) {
    $legacyKey = preg_replace('#\Abanner/images/#', '', $key);
    $imagePath = is_string($legacyKey) ? sr_banner_image_storage_path($legacyKey) : null;
}
if (!is_string($imagePath)) {
    sr_render_error(404, '배너 이미지를 찾을 수 없습니다.');
}

$mimeType = sr_upload_detect_mime($imagePath);
$sizeBytes = filesize($imagePath);
if (!sr_banner_image_mime_is_allowed($mimeType) || !is_int($sizeBytes)) {
    sr_render_error(404, '배너 이미지를 찾을 수 없습니다.');
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) $sizeBytes);
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
readfile($imagePath);
sr_finish_response();
