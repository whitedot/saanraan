#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

function sr_check_order(string $content, string $firstNeedle, string $secondNeedle): bool
{
    $first = strpos($content, $firstNeedle);
    $second = strpos($content, $secondNeedle);

    return $first !== false && $second !== false && $first < $second;
}

$contentDownload = file_get_contents($root . '/modules/content/actions/download.php');
if (!is_string($contentDownload)) {
    $errors[] = 'Content download action cannot be read.';
} else {
    if (!sr_check_order($contentDownload, "sr_storage_signed_url('s3'", 'sr_content_charge_file_download(')) {
        $errors[] = 'Content downloads must prepare the S3 signed URL before charging assets.';
    }
    if (!sr_check_order($contentDownload, 'sr_content_file_path($file)', 'sr_content_charge_file_download(')) {
        $errors[] = 'Content downloads must verify the local file path before charging assets.';
    }
    if (!sr_check_order($contentDownload, 'sr_content_charge_file_download(', 'sr_redirect_external($downloadUrl)')) {
        $errors[] = 'Content downloads must charge before handing off a prepared S3 download URL.';
    }
    if (!sr_check_order($contentDownload, 'sr_content_charge_file_download(', 'readfile($filePath)')) {
        $errors[] = 'Content downloads must charge before streaming a prepared local file.';
    }
}

$contentAssets = file_get_contents($root . '/modules/content/helpers/assets.php');
if (!is_string($contentAssets)) {
    $errors[] = 'Content asset helper cannot be read.';
} else {
    if (strpos($contentAssets, "return in_array(\$chargePolicy, ['once', 'every_view', 'every_download'], true);") === false) {
        $errors[] = 'Content paid access must require POST confirmation for once and repeated charge policies.';
    }
    if (strpos($contentAssets, "log_status = :log_status") === false || strpos($contentAssets, 'sr_content_asset_log_status_pending()') === false) {
        $errors[] = 'Content asset logs must distinguish pending placeholders from completed zero-amount logs.';
    }
}

$communityAttachment = file_get_contents($root . '/modules/community/actions/attachment.php');
if (!is_string($communityAttachment)) {
    $errors[] = 'Community attachment action cannot be read.';
} else {
    if (!sr_check_order($communityAttachment, "sr_storage_signed_url('s3'", 'sr_community_run_asset_event(')) {
        $errors[] = 'Community attachments must prepare the S3 signed URL before charging assets.';
    }
    if (!sr_check_order($communityAttachment, 'sr_community_attachment_file_path($attachment)', 'sr_community_run_asset_event(')) {
        $errors[] = 'Community attachments must verify the local file path before charging assets.';
    }
    if (!sr_check_order($communityAttachment, 'sr_community_run_asset_event(', 'sr_redirect_external($downloadUrl)')) {
        $errors[] = 'Community attachments must charge before handing off a prepared S3 download URL.';
    }
    if (!sr_check_order($communityAttachment, 'sr_community_run_asset_event(', 'readfile($filePath)')) {
        $errors[] = 'Community attachments must charge before streaming a prepared local file.';
    }
}

$communityAssets = file_get_contents($root . '/modules/community/helpers/assets.php');
if (!is_string($communityAssets)) {
    $errors[] = 'Community asset helper cannot be read.';
} else {
    if (strpos($communityAssets, "return in_array(\$chargePolicy, ['once', 'every_view', 'every_download', 'every_action'], true);") === false) {
        $errors[] = 'Community paid access must require POST confirmation for once and repeated charge policies.';
    }
    if (strpos($communityAssets, "log_status = :log_status") === false || strpos($communityAssets, 'sr_community_asset_log_status_pending()') === false) {
        $errors[] = 'Community asset logs must distinguish pending placeholders from completed zero-amount logs.';
    }
}

if ($errors !== []) {
    fwrite(STDERR, "paid download delivery checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "paid download delivery checks completed.\n";
