#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/asset_exchange/helpers.php';

$errors = [];

function sr_asset_exchange_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_asset_exchange_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_asset_exchange_runtime_error($message);
    }
}

function sr_asset_exchange_runtime_schema(PDO $pdo, bool $includeDepositTransactions = true): void
{
    $pdo->exec(
        "CREATE TABLE sr_modules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_key TEXT NOT NULL UNIQUE,
            version TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_reward_balances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL UNIQUE,
            balance INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_reward_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            amount INTEGER NOT NULL,
            balance_after INTEGER NOT NULL,
            transaction_type TEXT NOT NULL,
            reason TEXT NOT NULL DEFAULT '',
            reference_type TEXT NOT NULL DEFAULT '',
            reference_id TEXT NOT NULL DEFAULT '',
            created_by_account_id INTEGER NULL,
            created_at TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_deposit_balances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL UNIQUE,
            balance INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )"
    );
    if ($includeDepositTransactions) {
        $pdo->exec(
            "CREATE TABLE sr_deposit_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id INTEGER NOT NULL,
                amount INTEGER NOT NULL,
                balance_after INTEGER NOT NULL,
                transaction_type TEXT NOT NULL,
                reason TEXT NOT NULL DEFAULT '',
                reference_type TEXT NOT NULL DEFAULT '',
                reference_id TEXT NOT NULL DEFAULT '',
                created_by_account_id INTEGER NULL,
                created_at TEXT NOT NULL
            )"
        );
    }
    $pdo->exec(
        "CREATE TABLE sr_asset_exchange_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            exchange_group_id TEXT NOT NULL UNIQUE,
            policy_id INTEGER NULL,
            account_id INTEGER NOT NULL,
            from_module_key TEXT NOT NULL,
            to_module_key TEXT NOT NULL,
            request_amount INTEGER NOT NULL,
            rate_numerator INTEGER NOT NULL,
            rate_denominator INTEGER NOT NULL,
            rounding_mode TEXT NOT NULL,
            deposit_amount INTEGER NOT NULL,
            fee_amount INTEGER NOT NULL DEFAULT 0,
            fee_trigger TEXT NOT NULL DEFAULT 'none',
            fee_basis TEXT NOT NULL DEFAULT 'to_amount',
            status TEXT NOT NULL,
            failure_reason TEXT NOT NULL DEFAULT '',
            from_transaction_id INTEGER NULL,
            to_transaction_id INTEGER NULL,
            fee_transaction_id INTEGER NULL,
            created_by_account_id INTEGER NULL,
            created_at TEXT NOT NULL
        )"
    );
}

function sr_asset_exchange_runtime_seed(PDO $pdo, int $accountId, int $rewardBalance): void
{
    $now = sr_now();
    foreach (['asset_exchange', 'reward', 'deposit'] as $moduleKey) {
        $pdo->prepare('INSERT INTO sr_modules (module_key, version, status) VALUES (:module_key, :version, :status)')
            ->execute(['module_key' => $moduleKey, 'version' => 'fixture', 'status' => 'enabled']);
    }
    $pdo->prepare('INSERT INTO sr_reward_balances (account_id, balance, created_at, updated_at) VALUES (:account_id, :balance, :created_at, :updated_at)')
        ->execute(['account_id' => $accountId, 'balance' => $rewardBalance, 'created_at' => $now, 'updated_at' => $now]);
    $pdo->prepare('INSERT INTO sr_deposit_balances (account_id, balance, created_at, updated_at) VALUES (:account_id, 0, :created_at, :updated_at)')
        ->execute(['account_id' => $accountId, 'created_at' => $now, 'updated_at' => $now]);
}

function sr_asset_exchange_runtime_policy(): array
{
    return [
        'id' => 55,
        'from_module_key' => 'reward',
        'to_module_key' => 'deposit',
        'status' => 'enabled',
        'rate_numerator' => 1,
        'rate_denominator' => 1,
        'min_amount' => 1,
        'max_amount' => null,
        'rounding_mode' => 'floor',
        'fee_trigger' => 'always',
        'fee_basis' => 'to_amount',
        'fee_type' => 'rate',
        'fee_rate_numerator' => 10,
        'fee_rate_denominator' => 100,
        'fee_fixed_amount' => 0,
        'fee_min_amount' => null,
        'fee_max_amount' => null,
    ];
}

function sr_asset_exchange_runtime_balance(PDO $pdo, string $table, int $accountId): int
{
    $stmt = $pdo->prepare('SELECT balance FROM ' . $table . ' WHERE account_id = :account_id LIMIT 1');
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();

    return is_array($row) ? (int) $row['balance'] : 0;
}

function sr_asset_exchange_runtime_count(PDO $pdo, string $table): int
{
    $row = $pdo->query('SELECT COUNT(*) AS row_count FROM ' . $table)->fetch();

    return is_array($row) ? (int) ($row['row_count'] ?? 0) : 0;
}

function sr_asset_exchange_runtime_success_case(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_asset_exchange_runtime_schema($pdo);
    sr_asset_exchange_runtime_seed($pdo, 123, 500);

    $policy = sr_asset_exchange_runtime_policy();
    $quote = sr_asset_exchange_quote($pdo, $policy, 123, 100);
    sr_asset_exchange_runtime_assert((int) ($quote['deposit_before_fee'] ?? 0) === 100, 'Asset exchange quote must calculate pre-fee deposit amount.');
    sr_asset_exchange_runtime_assert((int) ($quote['fee_amount'] ?? 0) === 10, 'Asset exchange quote must calculate fee amount.');
    sr_asset_exchange_runtime_assert((int) ($quote['deposit_amount'] ?? 0) === 90, 'Asset exchange quote must calculate final deposit amount.');

    $logId = sr_asset_exchange_execute($pdo, $policy, 123, 100, 9001);
    sr_asset_exchange_runtime_assert($logId > 0, 'Asset exchange execution must return a log id.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_balance($pdo, 'sr_reward_balances', 123) === 400, 'Asset exchange execution must deduct the source balance.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_balance($pdo, 'sr_deposit_balances', 123) === 90, 'Asset exchange execution must add only the final post-fee destination balance.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_count($pdo, 'sr_reward_transactions') === 1, 'Asset exchange execution must create one source transaction.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_count($pdo, 'sr_deposit_transactions') === 2, 'Asset exchange execution must create destination deposit and fee transactions.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_count($pdo, 'sr_asset_exchange_logs') === 1, 'Asset exchange execution must create one completed log.');

    $log = $pdo->query('SELECT * FROM sr_asset_exchange_logs LIMIT 1')->fetch();
    sr_asset_exchange_runtime_assert(is_array($log), 'Asset exchange execution log must be readable.');
    if (is_array($log)) {
        sr_asset_exchange_runtime_assert((string) ($log['status'] ?? '') === 'completed', 'Asset exchange execution log must be completed.');
        sr_asset_exchange_runtime_assert((int) ($log['request_amount'] ?? 0) === 100, 'Asset exchange execution log must store request amount.');
        sr_asset_exchange_runtime_assert((int) ($log['deposit_amount'] ?? 0) === 90, 'Asset exchange execution log must store final post-fee deposit amount.');
        sr_asset_exchange_runtime_assert((int) ($log['fee_amount'] ?? 0) === 10, 'Asset exchange execution log must store fee amount.');
        sr_asset_exchange_runtime_assert((int) ($log['from_transaction_id'] ?? 0) > 0, 'Asset exchange execution log must link the source transaction.');
        sr_asset_exchange_runtime_assert((int) ($log['to_transaction_id'] ?? 0) > 0, 'Asset exchange execution log must link the destination transaction.');
        sr_asset_exchange_runtime_assert((int) ($log['fee_transaction_id'] ?? 0) > 0, 'Asset exchange execution log must link the fee transaction.');
    }
}

function sr_asset_exchange_runtime_rollback_case(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_asset_exchange_runtime_schema($pdo, false);
    sr_asset_exchange_runtime_seed($pdo, 123, 500);

    try {
        sr_asset_exchange_execute($pdo, sr_asset_exchange_runtime_policy(), 123, 100, 9001);
        sr_asset_exchange_runtime_error('Asset exchange execution must fail when the destination ledger write fails.');
    } catch (Throwable $exception) {
        sr_asset_exchange_runtime_assert(str_contains($exception->getMessage(), 'sr_deposit_transactions'), 'Asset exchange rollback fixture must fail at the destination ledger write.');
    }

    sr_asset_exchange_runtime_assert(!$pdo->inTransaction(), 'Asset exchange execution must leave no open transaction after rollback.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_balance($pdo, 'sr_reward_balances', 123) === 500, 'Asset exchange rollback must restore the source balance.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_balance($pdo, 'sr_deposit_balances', 123) === 0, 'Asset exchange rollback must leave the destination balance unchanged.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_count($pdo, 'sr_reward_transactions') === 0, 'Asset exchange rollback must leave no source transaction.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_count($pdo, 'sr_asset_exchange_logs') === 0, 'Asset exchange rollback must leave no completed log.');
}

function sr_asset_exchange_runtime_failure_log_case(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    sr_asset_exchange_runtime_schema($pdo);
    sr_asset_exchange_runtime_seed($pdo, 123, 500);

    $reason = str_repeat('실패 사유 ', 80);
    $logId = sr_asset_exchange_record_failure($pdo, sr_asset_exchange_runtime_policy(), 123, 100, $reason, 9001);
    sr_asset_exchange_runtime_assert(is_int($logId) && $logId > 0, 'Asset exchange failure log must return a log id.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_balance($pdo, 'sr_reward_balances', 123) === 500, 'Asset exchange failure log must not change the source balance.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_balance($pdo, 'sr_deposit_balances', 123) === 0, 'Asset exchange failure log must not change the destination balance.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_count($pdo, 'sr_reward_transactions') === 0, 'Asset exchange failure log must not create source transactions.');
    sr_asset_exchange_runtime_assert(sr_asset_exchange_runtime_count($pdo, 'sr_deposit_transactions') === 0, 'Asset exchange failure log must not create destination transactions.');

    $stmt = $pdo->prepare('SELECT * FROM sr_asset_exchange_logs WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $logId]);
    $log = $stmt->fetch();
    sr_asset_exchange_runtime_assert(is_array($log), 'Asset exchange failure log must be readable.');
    if (is_array($log)) {
        sr_asset_exchange_runtime_assert((string) ($log['status'] ?? '') === 'failed', 'Asset exchange failure log must store failed status.');
        sr_asset_exchange_runtime_assert((int) ($log['request_amount'] ?? 0) === 100, 'Asset exchange failure log must store request amount.');
        sr_asset_exchange_runtime_assert((int) ($log['deposit_amount'] ?? 0) === 0, 'Asset exchange failure log must not store a destination amount.');
        sr_asset_exchange_runtime_assert((int) ($log['fee_amount'] ?? 0) === 0, 'Asset exchange failure log must not store a fee amount.');
        sr_asset_exchange_runtime_assert((string) ($log['failure_reason'] ?? '') !== '', 'Asset exchange failure log must store a failure reason.');
        $failureReasonLength = function_exists('mb_strlen') ? mb_strlen((string) ($log['failure_reason'] ?? '')) : strlen((string) ($log['failure_reason'] ?? ''));
        sr_asset_exchange_runtime_assert($failureReasonLength <= 255, 'Asset exchange failure log must clamp failure reason length.');
        sr_asset_exchange_runtime_assert((int) ($log['created_by_account_id'] ?? 0) === 9001, 'Asset exchange failure log must store operator evidence.');
        sr_asset_exchange_runtime_assert(($log['from_transaction_id'] ?? null) === null && ($log['to_transaction_id'] ?? null) === null && ($log['fee_transaction_id'] ?? null) === null, 'Asset exchange failure log must not link nonexistent transactions.');
    }
}

if (!extension_loaded('pdo_sqlite')) {
    sr_asset_exchange_runtime_error('pdo_sqlite extension is required for asset exchange runtime checks.');
} else {
    sr_asset_exchange_runtime_success_case();
    sr_asset_exchange_runtime_rollback_case();
    sr_asset_exchange_runtime_failure_log_case();
}

if ($errors !== []) {
    fwrite(STDERR, "asset exchange runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "asset exchange runtime checks completed.\n";
