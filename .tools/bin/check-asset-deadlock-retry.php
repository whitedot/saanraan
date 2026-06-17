#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

$contentBase = file_get_contents($root . '/modules/content/helpers/assets.php');
$contentAccess = file_get_contents($root . '/modules/content/helpers/asset-access.php');
$contentActions = file_get_contents($root . '/modules/content/helpers/asset-actions.php');
$content = (is_string($contentBase) ? $contentBase : '')
    . "\n"
    . (is_string($contentAccess) ? $contentAccess : '')
    . "\n"
    . (is_string($contentActions) ? $contentActions : '');
if (
    !is_string($contentBase)
    || !is_string($contentAccess)
    || !is_string($contentActions)
    || strpos($content, 'function sr_content_asset_retry_operation(PDO $pdo, callable $operation): array') === false
    || preg_match('/function\s+sr_content_charge_view_access_once\s*\(\s*PDO\s+\$pdo\s*,\s*array\s+\$page\s*,\s*int\s+\$accountId\b/', $content) !== 1
    || preg_match('/function\s+sr_content_charge_file_download_once\s*\(\s*PDO\s+\$pdo\s*,\s*array\s+\$file\s*,\s*int\s+\$accountId\b/', $content) !== 1
    || strpos($content, 'function sr_content_run_asset_action_once(PDO $pdo, array $page, int $accountId): array') === false
    || substr_count($content, 'sr_content_asset_is_retryable_transaction_exception($exception)') < 6
) {
    $errors[] = 'Content asset operations must retry retryable top-level ledger transaction failures.';
}

$communityBase = file_get_contents($root . '/modules/community/helpers/assets.php');
$communityEvents = file_get_contents($root . '/modules/community/helpers/asset-events.php');
$community = (is_string($communityBase) ? $communityBase : '')
    . "\n"
    . (is_string($communityEvents) ? $communityEvents : '');
if (
    !is_string($communityBase)
    || !is_string($communityEvents)
    || strpos($community, 'function sr_community_asset_retry_operation(PDO $pdo, callable $operation): array') === false
    || strpos($community, 'function sr_community_run_asset_event_once(PDO $pdo, array $config, int $accountId') === false
    || substr_count($community, 'sr_community_asset_is_retryable_transaction_exception($exception)') < 2
) {
    $errors[] = 'Community asset operations must retry retryable top-level ledger transaction failures.';
}

if ($errors !== []) {
    fwrite(STDERR, "asset deadlock retry checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "asset deadlock retry checks completed.\n";
