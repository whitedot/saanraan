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

function sr_paid_download_fixture_prepare(bool $ok, array &$events): string
{
    $events[] = 'prepare';
    if (!$ok) {
        throw new RuntimeException('prepare_failed');
    }

    return 'prepared-url';
}

function sr_paid_download_fixture_charge(bool $ok, array &$events): void
{
    $events[] = 'charge';
    if (!$ok) {
        throw new RuntimeException('charge_failed');
    }
}

function sr_paid_download_fixture_deliver(array &$events): void
{
    $events[] = 'deliver';
}

function sr_paid_download_fixture_run(bool $prepareOk, bool $chargeOk): array
{
    $events = [];
    try {
        sr_paid_download_fixture_prepare($prepareOk, $events);
        sr_paid_download_fixture_charge($chargeOk, $events);
        sr_paid_download_fixture_deliver($events);

        return ['ok' => true, 'events' => $events];
    } catch (Throwable $exception) {
        return ['ok' => false, 'events' => $events, 'error' => $exception->getMessage()];
    }
}

function sr_paid_download_fixture_assert(array $actual, array $events, bool $ok, string $message): void
{
    global $errors;

    if (($actual['events'] ?? []) !== $events || (bool) ($actual['ok'] ?? false) !== $ok) {
        $errors[] = $message . ' expected events=' . implode(',', $events) . ' ok=' . ($ok ? 'true' : 'false');
    }
}

$contentDownload = file_get_contents($root . '/modules/content/actions/download.php');
if (!is_string($contentDownload)) {
    $errors[] = 'Content download action cannot be read.';
} else {
    if (!sr_check_order($contentDownload, "sr_storage_signed_url('s3'", 'sr_content_charge_file_download(')) {
        $errors[] = 'Content downloads must prepare the S3 signed URL before charging assets.';
    }
    if (strpos($contentDownload, "'response-content-disposition' => sr_download_content_disposition((string) \$file['original_name'])") === false) {
        $errors[] = 'Content downloads must use the shared download disposition helper for signed URLs.';
    }
    if (strpos($contentDownload, "sr_send_download_headers(\$mimeType, (string) \$file['original_name'], 'attachment', \$recordedSize, 'private, no-store, no-cache, must-revalidate')") === false) {
        $errors[] = 'Content downloads must use the shared download header helper for local streaming.';
    }
    if (strpos($contentDownload, "header('Content-Disposition:") !== false || strpos($contentDownload, "header('Content-Length: ' . (string) \$recordedSize)") !== false) {
        $errors[] = 'Content downloads must not assemble local download headers directly.';
    }
    if (!sr_check_order($contentDownload, 'sr_content_file_path($file)', 'sr_content_charge_file_download(')) {
        $errors[] = 'Content downloads must verify the local file path before charging assets.';
    }
    if (!sr_check_order($contentDownload, 'sr_content_charge_file_download(', 'sr_redirect_trusted_external($downloadUrl)')) {
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
    if (strpos($communityAttachment, "'response-content-disposition' => sr_download_content_disposition((string) \$attachment['original_name'], \$disposition)") === false) {
        $errors[] = 'Community attachments must use the shared download disposition helper for signed URLs.';
    }
    if (strpos($communityAttachment, "sr_send_download_headers(\$mimeType, (string) \$attachment['original_name'], \$disposition, \$recordedSize, 'private, no-store, no-cache, must-revalidate')") === false) {
        $errors[] = 'Community attachments must use the shared download header helper for local streaming.';
    }
    if (strpos($communityAttachment, "header('Content-Disposition:") !== false || strpos($communityAttachment, "header('Content-Length: ' . (string) \$recordedSize)") !== false) {
        $errors[] = 'Community attachments must not assemble local download headers directly.';
    }
    if (!sr_check_order($communityAttachment, 'sr_community_attachment_file_path($attachment)', 'sr_community_run_asset_event(')) {
        $errors[] = 'Community attachments must verify the local file path before charging assets.';
    }
    if (!sr_check_order($communityAttachment, 'sr_community_run_asset_event(', 'sr_redirect_trusted_external($downloadUrl)')) {
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

sr_paid_download_fixture_assert(
    sr_paid_download_fixture_run(false, true),
    ['prepare'],
    false,
    'Paid download fixture must not charge when file or signed URL preparation fails.'
);
sr_paid_download_fixture_assert(
    sr_paid_download_fixture_run(true, false),
    ['prepare', 'charge'],
    false,
    'Paid download fixture must not deliver when asset charge fails.'
);
sr_paid_download_fixture_assert(
    sr_paid_download_fixture_run(true, true),
    ['prepare', 'charge', 'deliver'],
    true,
    'Paid download fixture must prepare, charge, then deliver on success.'
);

if ($errors !== []) {
    fwrite(STDERR, "paid download delivery checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "paid download delivery checks completed.\n";
