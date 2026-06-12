#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

function sr_reward_check_file(string $path, array $needles): string
{
    global $errors, $root;

    $fullPath = $root . '/' . $path;
    $content = file_get_contents($fullPath);
    if (!is_string($content)) {
        $errors[] = 'file cannot be read: ' . $path;
        return '';
    }

    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = $path . ' missing marker: ' . $needle;
        }
    }

    return $content;
}

function sr_reward_check_order(string $path, string $firstNeedle, string $secondNeedle): void
{
    global $errors;

    $content = sr_reward_check_file($path, []);
    $first = strpos($content, $firstNeedle);
    $second = strpos($content, $secondNeedle);
    if ($first === false || $second === false || $first >= $second) {
        $errors[] = $path . ' must contain marker order: ' . $firstNeedle . ' before ' . $secondNeedle;
    }
}

sr_reward_check_file('docs/plans/reward-abuse-common-standards.md', [
    'reward_provider',
    'reward_module',
    'dedupe_scope',
    'dedupe_key',
    'Provider 재검증 기준',
]);

sr_reward_check_file('modules/quiz/install.sql', [
    'CREATE TABLE IF NOT EXISTS sr_quiz_reward_grants',
    'reward_provider VARCHAR(30)',
    'reward_module VARCHAR(40)',
    'reward_code VARCHAR(120)',
    'dedupe_scope VARCHAR(20)',
    'dedupe_key VARCHAR(190)',
    'UNIQUE KEY uq_sr_quiz_reward_grants_dedupe',
]);
sr_reward_check_file('modules/quiz/helpers.php', [
    '$insertVerb = \'INSERT IGNORE\';',
    '$insertVerb = \'INSERT OR IGNORE\';',
    '$insertVerb . \' INTO sr_quiz_reward_grants',
    '$lockClause = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === \'sqlite\' ? \'\' : \' FOR UPDATE\';',
    'SELECT * FROM sr_quiz_reward_grants WHERE dedupe_key = :dedupe_key LIMIT 1',
    'sr_quiz_refresh_reward_grant_for_retry',
    'sr_quiz_issue_coupon_reward_grant',
    'sr_quiz_reward_coupon_definition_is_available',
    'status = \\\'granted\\\'',
    'status = \\\'failed\\\'',
]);

sr_reward_check_file('modules/survey/install.sql', [
    'CREATE TABLE IF NOT EXISTS sr_survey_reward_grants',
    'reward_provider VARCHAR(30)',
    'reward_module VARCHAR(40)',
    'reward_code VARCHAR(120)',
    'dedupe_scope VARCHAR(20)',
    'dedupe_key VARCHAR(190)',
    'UNIQUE KEY uq_sr_survey_reward_grants_dedupe',
]);
sr_reward_check_file('modules/survey/helpers.php', [
    '$insertVerb = \'INSERT IGNORE\';',
    '$insertVerb = \'INSERT OR IGNORE\';',
    '$insertVerb . \' INTO sr_survey_reward_grants',
    '$lockClause = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === \'sqlite\' ? \'\' : \' FOR UPDATE\';',
    'SELECT * FROM sr_survey_reward_grants WHERE dedupe_key = :dedupe_key LIMIT 1',
    'sr_survey_refresh_reward_grant_for_retry',
    'sr_survey_issue_coupon_reward_grant',
    'sr_survey_coupon_definition_is_available',
    'status = \\\'granted\\\'',
    'status = \\\'failed\\\'',
]);

sr_reward_check_file('modules/coupon/install.sql', [
    'CREATE TABLE IF NOT EXISTS sr_coupon_redemptions',
    'dedupe_key VARCHAR(160)',
    'UNIQUE KEY uq_sr_coupon_redemptions_dedupe',
]);
sr_reward_check_file('modules/coupon/helpers.php', [
    'FOR UPDATE',
    'sr_coupon_has_redemption',
    'dedupe_key',
    'sr_coupon_revoke_consumer_access',
]);

sr_reward_check_file('modules/point/helpers.php', [
    'reference_type',
    'reference_id',
    'sr_point_refunded_amount_for_reference_locked',
    'Point refund amount exceeds remaining reference amount.',
    'sr_ledger_for_update_clause($pdo)',
    'sr_ledger_insert_ignore_into_clause($pdo)',
]);
sr_reward_check_file('modules/reward/helpers.php', [
    'reference_type',
    'reference_id',
    'sr_reward_account_can_request_withdrawal',
    'sr_reward_complete_withdrawal_request',
    'FOR UPDATE',
]);
sr_reward_check_file('modules/deposit/helpers.php', [
    'reference_type',
    'reference_id',
    'sr_deposit_account_can_request_refund',
    'sr_deposit_complete_refund_request',
    'FOR UPDATE',
]);
sr_reward_check_file('modules/asset_exchange/helpers.php', [
    'exchange_group_id',
    'sr_asset_exchange_for_update_clause($pdo)',
    'reference_type',
    'reference_id',
]);

sr_reward_check_file('modules/content/install.sql', [
    'CREATE TABLE IF NOT EXISTS sr_content_access_entitlements',
    'CREATE TABLE IF NOT EXISTS sr_content_asset_access_logs',
    'CREATE TABLE IF NOT EXISTS sr_content_asset_action_logs',
    'dedupe_key VARCHAR(160)',
    'UNIQUE KEY uq_sr_content_asset_access_dedupe',
    'UNIQUE KEY uq_sr_content_asset_action_dedupe',
]);
sr_reward_check_file('modules/content/helpers/assets.php', [
    'sr_content_asset_policy_requires_confirmation',
    'sr_content_asset_log_status_pending',
    'sr_content_grant_access_entitlement',
    'sr_content_once_access_already_granted',
    'sr_content_has_coupon_access_history',
]);
sr_reward_check_file('modules/content/helpers/files.php', [
    'sr_content_refund_file_download',
    'sr_content_revoke_file_download_access_entitlement',
]);
sr_reward_check_order('modules/content/actions/download.php', 'sr_content_charge_file_download(', 'sr_redirect_trusted_external($downloadUrl)');
sr_reward_check_order('modules/content/actions/download.php', 'sr_content_charge_file_download(', 'readfile($filePath)');

sr_reward_check_file('modules/community/install.sql', [
    'CREATE TABLE IF NOT EXISTS sr_community_access_entitlements',
    'CREATE TABLE IF NOT EXISTS sr_community_asset_logs',
    'CREATE TABLE IF NOT EXISTS sr_community_publisher_reward_logs',
    'dedupe_key VARCHAR(160)',
    'UNIQUE KEY uq_sr_community_asset_logs_dedupe',
    'UNIQUE KEY uq_sr_community_publisher_reward_dedupe',
]);
sr_reward_check_file('modules/community/helpers/assets.php', [
    'sr_community_asset_policy_requires_confirmation',
    'sr_community_asset_log_status_pending',
    'sr_community_grant_access_entitlement',
    'sr_community_has_asset_event_history',
    'sr_community_has_coupon_access_history',
    'sr_community_grant_attachment_publisher_reward',
]);
sr_reward_check_order('modules/community/actions/attachment.php', 'sr_community_run_asset_event(', 'sr_redirect_trusted_external($downloadUrl)');
sr_reward_check_order('modules/community/actions/attachment.php', 'sr_community_run_asset_event(', 'readfile($filePath)');

if ($errors !== []) {
    fwrite(STDERR, "reward abuse standard checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "reward abuse standard checks completed.\n";
