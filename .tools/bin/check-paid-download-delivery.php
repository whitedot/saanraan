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

if ($errors !== []) {
    fwrite(STDERR, "paid download delivery checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "paid download delivery checks completed.\n";
