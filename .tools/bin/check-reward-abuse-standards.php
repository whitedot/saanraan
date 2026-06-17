#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}
$errors = [];

if (!function_exists('sr_now')) {
    function sr_now(): string
    {
        return '2026-06-12 12:00:00';
    }
}

if (!function_exists('sr_t')) {
    function sr_t(string $key): string
    {
        return $key;
    }
}

if (!function_exists('sr_module_settings')) {
    function sr_module_settings(PDO $pdo, string $moduleKey): array
    {
        if ($moduleKey === 'reward') {
            return [
                'withdrawal_requests_enabled' => '1',
                'withdrawal_allowed_group_keys_json' => '["__all__"]',
            ];
        }
        if ($moduleKey === 'deposit') {
            return [
                'refund_requests_enabled' => '1',
                'refund_allowed_group_keys_json' => '["__all__"]',
            ];
        }

        return [];
    }
}

if (!function_exists('sr_member_account_in_any_group')) {
    function sr_member_account_in_any_group(PDO $pdo, int $accountId, array $groupKeys): bool
    {
        return false;
    }
}

if (!function_exists('sr_module_contract_function')) {
    function sr_module_contract_function(PDO $pdo, string $moduleKey, string $contractFile, string $functionKey): string
    {
        return '';
    }
}

if (!function_exists('sr_log_exception')) {
    function sr_log_exception(Throwable $exception, string $context = ''): void
    {
    }
}

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

function sr_reward_abuse_runtime_pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec(
        'CREATE TABLE sr_reward_balances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL UNIQUE,
            balance INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_reward_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            amount INTEGER NOT NULL,
            balance_after INTEGER NOT NULL,
            transaction_type TEXT NOT NULL,
            reason TEXT NOT NULL DEFAULT \'\',
            reference_type TEXT NOT NULL DEFAULT \'\',
            reference_id TEXT NOT NULL DEFAULT \'\',
            created_by_account_id INTEGER NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_reward_withdrawal_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            amount INTEGER NOT NULL,
            bank_name TEXT NOT NULL,
            bank_account_number TEXT NOT NULL,
            bank_account_holder TEXT NOT NULL,
            requester_note TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT \'pending\',
            admin_note TEXT NOT NULL DEFAULT \'\',
            transaction_id INTEGER NULL,
            processed_by_account_id INTEGER NULL,
            requested_at TEXT NOT NULL,
            processed_at TEXT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_deposit_balances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL UNIQUE,
            balance INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_deposit_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            amount INTEGER NOT NULL,
            balance_after INTEGER NOT NULL,
            transaction_type TEXT NOT NULL,
            reason TEXT NOT NULL DEFAULT \'\',
            reference_type TEXT NOT NULL DEFAULT \'\',
            reference_id TEXT NOT NULL DEFAULT \'\',
            created_by_account_id INTEGER NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_deposit_refund_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            amount INTEGER NOT NULL,
            bank_name TEXT NOT NULL,
            bank_account_number TEXT NOT NULL,
            bank_account_holder TEXT NOT NULL,
            requester_note TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT \'pending\',
            admin_note TEXT NOT NULL DEFAULT \'\',
            transaction_id INTEGER NULL,
            processed_by_account_id INTEGER NULL,
            requested_at TEXT NOT NULL,
            processed_at TEXT NULL,
            updated_at TEXT NOT NULL
        )'
    );

    return $pdo;
}

function sr_reward_abuse_runtime_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function sr_reward_abuse_runtime_check(): void
{
    global $errors;

    require_once SR_ROOT . '/modules/reward/helpers.php';
    require_once SR_ROOT . '/modules/deposit/helpers.php';

    $pdo = sr_reward_abuse_runtime_pdo();

    $grantId = sr_reward_create_transaction($pdo, [
        'account_id' => 7,
        'amount' => 5000,
        'transaction_type' => 'grant',
        'reason' => 'runtime grant',
        'reference_type' => 'fixture',
        'reference_id' => 'grant-1',
        'created_by_account_id' => 99,
    ]);
    sr_reward_create_transaction($pdo, [
        'account_id' => 7,
        'amount' => -1000,
        'transaction_type' => 'reclaim',
        'reason' => 'runtime reclaim',
        'reference_type' => 'reclaim',
        'reference_id' => sr_reward_reclaim_reference_id($grantId),
        'created_by_account_id' => 99,
    ]);
    if (sr_reward_reclaim_remaining_amount($pdo, 7, $grantId) !== 4000) {
        $errors[] = 'reward reclaim runtime fixture must calculate remaining reclaim amount.';
    }
    if (sr_reward_validate_reclaim_transaction($pdo, 7, -4001, 'reclaim', sr_reward_reclaim_reference_id($grantId), true) !== 'reward::action.admin.reclaim_amount_exceeds_target') {
        $errors[] = 'reward reclaim runtime fixture must reject locked reclaim amounts above the remaining target.';
    }
    if (sr_reward_validate_reclaim_transaction($pdo, 7, -4000, 'reclaim', sr_reward_reclaim_reference_id($grantId), true) !== null) {
        $errors[] = 'reward reclaim runtime fixture must allow locked reclaim amounts up to the remaining target.';
    }

    $withdrawalRequestId = sr_reward_create_withdrawal_request($pdo, 7, [
        'amount' => 3000,
        'bank_name' => 'Reward Bank',
        'bank_account_number' => '555-666',
        'bank_account_holder' => 'Reward Holder',
        'requester_note' => 'runtime withdrawal',
    ]);
    if (sr_reward_withdrawal_available_amount($pdo, 7) !== 1000) {
        $errors[] = 'reward withdrawal runtime fixture must subtract pending withdrawal requests from available amount.';
    }
    try {
        sr_reward_create_withdrawal_request($pdo, 7, [
            'amount' => 2000,
            'bank_name' => 'Reward Bank',
            'bank_account_number' => '777-888',
            'bank_account_holder' => 'Reward Holder',
            'requester_note' => 'runtime withdrawal over available',
        ]);
        $errors[] = 'reward withdrawal runtime fixture must reject requests above balance minus pending withdrawals.';
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Reward withdrawal amount exceeds available balance.') {
            $errors[] = 'reward withdrawal runtime fixture rejected over-available request with unexpected error.';
        }
    }
    $withdrawalTransactionId = sr_reward_complete_withdrawal_request($pdo, $withdrawalRequestId, 99, 'paid');
    if ($withdrawalTransactionId <= 0) {
        $errors[] = 'reward withdrawal runtime fixture must create a withdrawal transaction when completed.';
    }
    if ((string) sr_reward_abuse_runtime_scalar($pdo, 'SELECT status FROM sr_reward_withdrawal_requests WHERE id = :id', ['id' => $withdrawalRequestId]) !== 'completed') {
        $errors[] = 'reward withdrawal runtime fixture must mark completed requests.';
    }
    if ((int) sr_reward_abuse_runtime_scalar($pdo, 'SELECT balance FROM sr_reward_balances WHERE account_id = 7') !== 1000) {
        $errors[] = 'reward withdrawal runtime fixture must reduce balance by completed request amount.';
    }
    try {
        sr_reward_complete_withdrawal_request($pdo, $withdrawalRequestId, 99, 'paid again');
        $errors[] = 'reward withdrawal runtime fixture must reject completing the same request twice.';
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Reward withdrawal request is not pending.') {
            $errors[] = 'reward withdrawal runtime fixture rejected duplicate completion with unexpected error.';
        }
    }

    sr_deposit_create_transaction($pdo, [
        'account_id' => 7,
        'amount' => 10000,
        'transaction_type' => 'deposit',
        'reason' => 'runtime deposit',
        'reference_type' => 'fixture',
        'reference_id' => 'deposit-1',
        'created_by_account_id' => 99,
    ]);
    $requestId = sr_deposit_create_refund_request($pdo, 7, [
        'amount' => 6000,
        'bank_name' => 'Bank',
        'bank_account_number' => '111-222',
        'bank_account_holder' => 'Holder',
        'requester_note' => 'runtime request',
    ]);
    if (sr_deposit_refund_available_amount($pdo, 7) !== 4000) {
        $errors[] = 'deposit refund runtime fixture must subtract pending refund requests from available amount.';
    }
    try {
        sr_deposit_create_refund_request($pdo, 7, [
            'amount' => 5000,
            'bank_name' => 'Bank',
            'bank_account_number' => '333-444',
            'bank_account_holder' => 'Holder',
            'requester_note' => 'runtime request over available',
        ]);
        $errors[] = 'deposit refund runtime fixture must reject requests above balance minus pending refunds.';
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Deposit refund amount exceeds available balance.') {
            $errors[] = 'deposit refund runtime fixture rejected over-available request with unexpected error.';
        }
    }

    $transactionId = sr_deposit_complete_refund_request($pdo, $requestId, 99, 'paid');
    if ($transactionId <= 0) {
        $errors[] = 'deposit refund runtime fixture must create a withdrawal transaction when completed.';
    }
    if ((string) sr_reward_abuse_runtime_scalar($pdo, 'SELECT status FROM sr_deposit_refund_requests WHERE id = :id', ['id' => $requestId]) !== 'completed') {
        $errors[] = 'deposit refund runtime fixture must mark completed requests.';
    }
    if ((int) sr_reward_abuse_runtime_scalar($pdo, 'SELECT balance FROM sr_deposit_balances WHERE account_id = 7') !== 4000) {
        $errors[] = 'deposit refund runtime fixture must reduce balance by completed request amount.';
    }
    try {
        sr_deposit_complete_refund_request($pdo, $requestId, 99, 'paid again');
        $errors[] = 'deposit refund runtime fixture must reject completing the same request twice.';
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Deposit refund request is not pending.') {
            $errors[] = 'deposit refund runtime fixture rejected duplicate completion with unexpected error.';
        }
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
sr_reward_check_file('modules/quiz/helpers/rewards.php', [
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
    'sr_ledger_for_update_clause($pdo)',
]);
sr_reward_check_file('modules/deposit/helpers.php', [
    'reference_type',
    'reference_id',
    'sr_deposit_account_can_request_refund',
    'sr_deposit_complete_refund_request',
    'sr_ledger_for_update_clause($pdo)',
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
]);
sr_reward_check_file('modules/content/helpers/asset-access.php', [
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

if (extension_loaded('pdo_sqlite')) {
    sr_reward_abuse_runtime_check();
} else {
    $errors[] = 'pdo_sqlite extension is required for reward abuse runtime checks.';
}

if ($errors !== []) {
    fwrite(STDERR, "reward abuse standard checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "reward abuse standard checks completed.\n";
