#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

$content = file_get_contents($root . '/modules/content/helpers/assets.php');
if (
    !is_string($content)
    || strpos($content, 'function sr_content_asset_retry_operation(PDO $pdo, callable $operation): array') === false
    || strpos($content, 'function sr_content_charge_view_access_once(PDO $pdo, array $page, int $accountId): array') === false
    || strpos($content, 'function sr_content_charge_file_download_once(PDO $pdo, array $file, int $accountId): array') === false
    || strpos($content, 'function sr_content_run_asset_action_once(PDO $pdo, array $page, int $accountId): array') === false
    || substr_count($content, 'sr_content_asset_is_retryable_transaction_exception($exception)') < 6
) {
    $errors[] = 'Content asset operations must retry retryable top-level ledger transaction failures.';
}

$community = file_get_contents($root . '/modules/community/helpers/assets.php');
if (
    !is_string($community)
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
