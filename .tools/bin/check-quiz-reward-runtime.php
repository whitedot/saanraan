#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/quiz/helpers.php';

$errors = [];
$srQuizRewardRuntimeTransactions = [];
$srQuizRewardRuntimeFailNext = false;

if (!function_exists('sr_reward_reclaim_reference_id')) {
    function sr_reward_reclaim_reference_id(int $transactionId): string
    {
        return 'reward_transaction:' . (string) $transactionId;
    }
}

if (!function_exists('sr_reward_reclaim_remaining_amounts_for_transactions')) {
    function sr_reward_reclaim_remaining_amounts_for_transactions(PDO $pdo, array $transactions): array
    {
        $remainingAmounts = [];
        foreach ($transactions as $transaction) {
            if (!is_array($transaction)) {
                continue;
            }
            $transactionId = (int) ($transaction['id'] ?? 0);
            $accountId = (int) ($transaction['account_id'] ?? 0);
            $amount = (int) ($transaction['amount'] ?? 0);
            if ($transactionId < 1 || $accountId < 1 || $amount <= 0) {
                continue;
            }

            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(ABS(amount)), 0) AS reclaimed_amount
                 FROM sr_fixture_asset_transactions
                 WHERE account_id = :account_id
                   AND transaction_type = 'reclaim'
                   AND reference_type = 'reclaim'
                   AND reference_id = :reference_id"
            );
            $stmt->execute([
                'account_id' => $accountId,
                'reference_id' => sr_reward_reclaim_reference_id($transactionId),
            ]);
            $row = $stmt->fetch();
            $reclaimedAmount = is_array($row) ? (int) ($row['reclaimed_amount'] ?? 0) : 0;
            $remainingAmounts[$transactionId] = max(0, $amount - $reclaimedAmount);
        }

        return $remainingAmounts;
    }
}

if (!function_exists('sr_reward_validate_reclaim_transaction')) {
    function sr_reward_validate_reclaim_transaction(PDO $pdo, int $accountId, int $amount, string $referenceType, string $referenceId, bool $lock = false): ?string
    {
        unset($lock);
        if ($referenceType !== 'reclaim' || preg_match('/\Areward_transaction:([0-9]+)\z/', $referenceId, $matches) !== 1) {
            return 'reclaim reference is required';
        }
        if ($amount >= 0) {
            return 'reclaim amount must be negative';
        }

        $targetId = (int) $matches[1];
        $target = sr_quiz_reward_runtime_row(
            $pdo,
            'SELECT id, account_id, amount
             FROM sr_fixture_asset_transactions
             WHERE id = :id
               AND account_id = :account_id
             LIMIT 1',
            [
                'id' => $targetId,
                'account_id' => $accountId,
            ]
        );
        if ($target === [] || (int) ($target['amount'] ?? 0) <= 0) {
            return 'reclaim original transaction not found';
        }

        $remainingAmounts = sr_reward_reclaim_remaining_amounts_for_transactions($pdo, [$target]);
        if (abs($amount) > (int) ($remainingAmounts[$targetId] ?? 0)) {
            return 'reclaim amount exceeds target';
        }

        return null;
    }
}

if (!function_exists('sr_reward_create_transaction')) {
    function sr_reward_create_transaction(PDO $pdo, array $data): int
    {
        $transactionId = (int) sr_quiz_reward_runtime_scalar($pdo, 'SELECT COALESCE(MAX(id), 0) + 1 FROM sr_fixture_asset_transactions');
        $stmt = $pdo->prepare(
            'INSERT INTO sr_fixture_asset_transactions
                (id, account_id, amount, transaction_type, reference_type, reference_id, created_at)
             VALUES
                (:id, :account_id, :amount, :transaction_type, :reference_type, :reference_id, :created_at)'
        );
        $stmt->execute([
            'id' => $transactionId,
            'account_id' => (int) ($data['account_id'] ?? 0),
            'amount' => (int) ($data['amount'] ?? 0),
            'transaction_type' => (string) ($data['transaction_type'] ?? ''),
            'reference_type' => (string) ($data['reference_type'] ?? ''),
            'reference_id' => (string) ($data['reference_id'] ?? ''),
            'created_at' => '2026-06-11 00:00:00',
        ]);

        return $transactionId;
    }
}

function sr_quiz_reward_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_quiz_reward_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_quiz_reward_runtime_error($message);
    }
}

function sr_quiz_reward_runtime_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchColumn();
}

function sr_quiz_reward_runtime_row(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? $row : [];
}

function sr_quiz_reward_runtime_transaction(PDO $pdo, array $data): int
{
    global $srQuizRewardRuntimeTransactions, $srQuizRewardRuntimeFailNext;

    if ($srQuizRewardRuntimeFailNext) {
        $srQuizRewardRuntimeFailNext = false;
        throw new RuntimeException('fixture transaction failed');
    }

    $srQuizRewardRuntimeTransactions[] = $data;
    $transactionId = count($srQuizRewardRuntimeTransactions);
    $stmt = $pdo->prepare(
        'INSERT INTO sr_fixture_asset_transactions
            (id, account_id, amount, transaction_type, reference_type, reference_id, created_at)
         VALUES
            (:id, :account_id, :amount, :transaction_type, :reference_type, :reference_id, :created_at)'
    );
    $stmt->execute([
        'id' => $transactionId,
        'account_id' => (int) ($data['account_id'] ?? 0),
        'amount' => (int) ($data['amount'] ?? 0),
        'transaction_type' => (string) ($data['transaction_type'] ?? ''),
        'reference_type' => (string) ($data['reference_type'] ?? ''),
        'reference_id' => (string) ($data['reference_id'] ?? ''),
        'created_at' => '2026-06-11 00:00:00',
    ]);

    return $transactionId;
}

function sr_quiz_reward_runtime_transaction_lookup(PDO $pdo, string $referenceType, string $referenceId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, account_id, amount, transaction_type, reference_type, reference_id, created_at
         FROM sr_fixture_asset_transactions
         WHERE reference_type = :reference_type
           AND reference_id = :reference_id
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([
        'reference_type' => $referenceType,
        'reference_id' => $referenceId,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_quiz_reward_runtime_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE sr_quiz_attempts (
        id INTEGER PRIMARY KEY,
        quiz_id INTEGER NOT NULL,
        account_id INTEGER,
        status TEXT NOT NULL DEFAULT "submitted",
        rewarded_at TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_quiz_reward_grants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        quiz_id INTEGER NOT NULL,
        attempt_id INTEGER NOT NULL,
        reward_policy_id INTEGER,
        account_id INTEGER,
        source_module TEXT,
        source_type TEXT,
        source_id INTEGER,
        reward_provider TEXT NOT NULL,
        reward_module TEXT NOT NULL,
        reward_code TEXT NOT NULL,
        reward_amount INTEGER,
        dedupe_scope TEXT NOT NULL,
        dedupe_key TEXT NOT NULL UNIQUE,
        status TEXT NOT NULL DEFAULT "pending",
        provider_reference_type TEXT,
        provider_reference_id TEXT,
        request_snapshot_json TEXT,
        result_snapshot_json TEXT,
        error_message TEXT,
        admin_resolution_type TEXT,
        resolved_by_account_id INTEGER,
        resolved_at TEXT,
        resolution_note TEXT,
        manual_completion_reference TEXT,
        replacement_grant_id INTEGER,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        granted_at TEXT,
        failed_at TEXT
    )');
    $pdo->exec('CREATE TABLE sr_fixture_asset_transactions (
        id INTEGER PRIMARY KEY,
        account_id INTEGER NOT NULL,
        amount INTEGER NOT NULL,
        transaction_type TEXT NOT NULL,
        reference_type TEXT NOT NULL,
        reference_id TEXT NOT NULL,
        created_at TEXT NOT NULL
    )');
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
sr_quiz_reward_runtime_schema($pdo);

$now = '2026-06-11 00:00:00';
$quiz = [
    'id' => 501,
    'quiz_key' => 'runtime_reward_quiz',
    'title' => 'Runtime Reward Quiz',
];
$policy = [
    'id' => 701,
    'reward_provider' => 'ledger_asset',
    'reward_module' => 'reward',
    'reward_code' => 'quiz_reward',
    'reward_amount' => 120,
    'dedupe_scope' => 'per_quiz',
];
$assetOptions = [
    'reward' => [
        'transaction_function' => 'sr_quiz_reward_runtime_transaction',
        'transaction_lookup_function' => 'sr_quiz_reward_runtime_transaction_lookup',
        'credit_type' => 'grant',
    ],
    'point' => [
        'transaction_function' => 'sr_quiz_reward_runtime_transaction',
        'transaction_lookup_function' => 'sr_quiz_reward_runtime_transaction_lookup',
        'credit_type' => 'earn',
    ],
];

$pdo->prepare('INSERT INTO sr_quiz_attempts (id, quiz_id, account_id, status, created_at, updated_at) VALUES (1, :quiz_id, 10, "submitted", :created_at, :updated_at)')->execute([
    'quiz_id' => (int) $quiz['id'],
    'created_at' => $now,
    'updated_at' => $now,
]);
$first = sr_quiz_issue_reward_grant($pdo, $quiz, 1, 10, $policy, $assetOptions, $now);
sr_quiz_reward_runtime_assert((string) ($first['status'] ?? '') === 'granted', 'quiz reward fixture should grant the first reward.');
sr_quiz_reward_runtime_assert((int) ($first['provider_reference_id'] ?? 0) === (int) ($first['id'] ?? 0), 'quiz reward fixture should reference the grant id.');
sr_quiz_reward_runtime_assert((int) sr_quiz_reward_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_quiz_reward_grants') === 1, 'quiz reward fixture should create one grant row.');
sr_quiz_reward_runtime_assert(count($srQuizRewardRuntimeTransactions) === 1, 'quiz reward fixture should create one asset transaction for the first grant.');
sr_quiz_reward_runtime_assert((string) sr_quiz_reward_runtime_scalar($pdo, 'SELECT rewarded_at FROM sr_quiz_attempts WHERE id = 1') === $now, 'quiz reward fixture should mark the rewarded attempt.');
$firstTransaction = sr_quiz_reward_grant_ledger_transaction($pdo, $first, $assetOptions);
sr_quiz_reward_runtime_assert(is_array($firstTransaction), 'quiz reward fixture should find grant ledger transaction through lookup function.');
sr_quiz_reward_runtime_assert((int) ($firstTransaction['amount'] ?? 0) === 120, 'quiz reward ledger lookup should return the granted amount.');
sr_quiz_reward_runtime_assert((string) ($firstTransaction['reference_type'] ?? '') === 'quiz_reward', 'quiz reward ledger lookup should use quiz_reward reference type.');
sr_quiz_reward_runtime_assert((string) ($firstTransaction['reference_id'] ?? '') === (string) (int) ($first['id'] ?? 0), 'quiz reward ledger lookup should use grant id reference.');
$firstReclaimStatus = sr_quiz_reward_grant_reclaim_status($pdo, $first, $assetOptions);
sr_quiz_reward_runtime_assert((bool) ($firstReclaimStatus['available'] ?? false) === true, 'quiz reward reclaim status should be available for reward asset grants.');
sr_quiz_reward_runtime_assert((int) ($firstReclaimStatus['transaction_id'] ?? 0) === 1, 'quiz reward reclaim status should expose the ledger transaction id.');
sr_quiz_reward_runtime_assert((int) ($firstReclaimStatus['remaining_amount'] ?? 0) === 120, 'quiz reward reclaim status should expose the full remaining amount.');
sr_quiz_reward_runtime_assert((string) ($firstReclaimStatus['reference_type'] ?? '') === 'reclaim', 'quiz reward reclaim status should expose reclaim reference type.');
sr_quiz_reward_runtime_assert((string) ($firstReclaimStatus['reference_id'] ?? '') === 'reward_transaction:1', 'quiz reward reclaim status should expose reward reclaim reference id.');

$pdo->prepare(
    'INSERT INTO sr_fixture_asset_transactions
        (id, account_id, amount, transaction_type, reference_type, reference_id, created_at)
     VALUES
        (101, 10, -50, "reclaim", "reclaim", "reward_transaction:1", :created_at)'
)->execute(['created_at' => $now]);
$partialReclaimStatus = sr_quiz_reward_grant_reclaim_status($pdo, $first, $assetOptions);
sr_quiz_reward_runtime_assert((bool) ($partialReclaimStatus['available'] ?? false) === true, 'quiz reward reclaim status should remain available after partial reclaim.');
sr_quiz_reward_runtime_assert((int) ($partialReclaimStatus['remaining_amount'] ?? 0) === 70, 'quiz reward reclaim status should subtract prior reclaims.');
$pdo->prepare(
    'INSERT INTO sr_fixture_asset_transactions
        (id, account_id, amount, transaction_type, reference_type, reference_id, created_at)
     VALUES
        (102, 10, -70, "reclaim", "reclaim", "reward_transaction:1", :created_at)'
)->execute(['created_at' => $now]);
$spentReclaimStatus = sr_quiz_reward_grant_reclaim_status($pdo, $first, $assetOptions);
sr_quiz_reward_runtime_assert((bool) ($spentReclaimStatus['available'] ?? true) === false, 'quiz reward reclaim status should close after full reclaim.');
sr_quiz_reward_runtime_assert((int) ($spentReclaimStatus['remaining_amount'] ?? -1) === 0, 'quiz reward reclaim status should expose zero remaining amount after full reclaim.');
sr_quiz_reward_runtime_assert((string) ($spentReclaimStatus['reason'] ?? '') === 'nothing_remaining', 'quiz reward reclaim status should explain full reclaim closure.');
$unsupportedGrant = $first;
$unsupportedGrant['reward_module'] = 'point';
$unsupportedReclaimStatus = sr_quiz_reward_grant_reclaim_status($pdo, $unsupportedGrant, $assetOptions);
sr_quiz_reward_runtime_assert((bool) ($unsupportedReclaimStatus['available'] ?? true) === false, 'quiz reward reclaim status should reject unsupported asset modules.');
sr_quiz_reward_runtime_assert((string) ($unsupportedReclaimStatus['reason'] ?? '') === 'unsupported_asset_reclaim', 'quiz reward reclaim status should explain unsupported asset modules.');

$sameAttempt = sr_quiz_issue_reward_grant($pdo, $quiz, 1, 10, $policy, $assetOptions, $now);
sr_quiz_reward_runtime_assert((string) ($sameAttempt['status'] ?? '') === 'granted', 'quiz reward fixture should return existing granted row for same attempt.');
sr_quiz_reward_runtime_assert(count($srQuizRewardRuntimeTransactions) === 1, 'quiz reward fixture should not create a second transaction for the same dedupe key.');

$pdo->prepare('INSERT INTO sr_quiz_attempts (id, quiz_id, account_id, status, created_at, updated_at) VALUES (2, :quiz_id, 10, "submitted", :created_at, :updated_at)')->execute([
    'quiz_id' => (int) $quiz['id'],
    'created_at' => $now,
    'updated_at' => $now,
]);
$duplicate = sr_quiz_issue_reward_grant($pdo, $quiz, 2, 10, $policy, $assetOptions, $now);
sr_quiz_reward_runtime_assert((string) ($duplicate['status'] ?? '') === 'duplicate', 'quiz reward fixture should mark another attempt with same per_quiz dedupe as duplicate.');
sr_quiz_reward_runtime_assert(count($srQuizRewardRuntimeTransactions) === 1, 'quiz reward fixture should not create a transaction for duplicate per_quiz attempts.');

$retryPolicy = $policy;
$retryPolicy['id'] = 702;
$retryPolicy['dedupe_scope'] = 'per_attempt';
$pdo->prepare('INSERT INTO sr_quiz_attempts (id, quiz_id, account_id, status, created_at, updated_at) VALUES (3, :quiz_id, 11, "submitted", :created_at, :updated_at)')->execute([
    'quiz_id' => (int) $quiz['id'],
    'created_at' => $now,
    'updated_at' => $now,
]);
$srQuizRewardRuntimeFailNext = true;
$failed = sr_quiz_issue_reward_grant($pdo, $quiz, 3, 11, $retryPolicy, $assetOptions, $now);
sr_quiz_reward_runtime_assert((string) ($failed['status'] ?? '') === 'failed', 'quiz reward fixture should persist failed reward grants.');
sr_quiz_reward_runtime_assert(str_contains((string) ($failed['error_message'] ?? ''), 'fixture transaction failed'), 'quiz reward fixture should store sanitized failure message.');
$retried = sr_quiz_issue_reward_grant($pdo, $quiz, 3, 11, $retryPolicy, $assetOptions, $now);
sr_quiz_reward_runtime_assert((string) ($retried['status'] ?? '') === 'granted', 'quiz reward fixture should retry failed grants using the same dedupe row.');
sr_quiz_reward_runtime_assert((int) ($retried['id'] ?? 0) === (int) ($failed['id'] ?? 0), 'quiz reward fixture should reuse the failed grant row during retry.');
sr_quiz_reward_runtime_assert((string) ($retried['error_message'] ?? '') === '', 'quiz reward fixture should clear failure message after retry.');
sr_quiz_reward_runtime_assert(count($srQuizRewardRuntimeTransactions) === 2, 'quiz reward fixture should create one new transaction after retry.');

$retryGrant = sr_quiz_reward_runtime_row($pdo, 'SELECT status, failed_at, granted_at, result_snapshot_json FROM sr_quiz_reward_grants WHERE id = :id', ['id' => (int) ($retried['id'] ?? 0)]);
sr_quiz_reward_runtime_assert((string) ($retryGrant['status'] ?? '') === 'granted', 'quiz reward fixture should store retried grant as granted.');
sr_quiz_reward_runtime_assert((string) ($retryGrant['failed_at'] ?? '') === '', 'quiz reward fixture should clear failed_at after retry.');
sr_quiz_reward_runtime_assert((string) ($retryGrant['granted_at'] ?? '') === $now, 'quiz reward fixture should store granted_at after retry.');
sr_quiz_reward_runtime_assert(str_contains((string) ($retryGrant['result_snapshot_json'] ?? ''), 'transaction_id'), 'quiz reward fixture should store transaction result snapshot.');
$retriedTransaction = sr_quiz_reward_grant_ledger_transaction($pdo, $retried, $assetOptions);
sr_quiz_reward_runtime_assert(is_array($retriedTransaction), 'quiz reward fixture should find retried grant ledger transaction through lookup function.');
sr_quiz_reward_runtime_assert((int) ($retriedTransaction['id'] ?? 0) === 2, 'quiz reward retried lookup should return the retry transaction.');
$reclaimResult = sr_quiz_reclaim_reward_grant($pdo, $retried, $assetOptions, 50, 99, 'runtime reclaim');
sr_quiz_reward_runtime_assert((bool) ($reclaimResult['ok'] ?? false) === true, 'quiz reward fixture should reclaim a reward grant through reward contract.');
sr_quiz_reward_runtime_assert((int) ($reclaimResult['transaction_id'] ?? 0) > 0, 'quiz reward reclaim should return a transaction id.');
$reclaimTransaction = sr_quiz_reward_runtime_row($pdo, 'SELECT account_id, amount, transaction_type, reference_type, reference_id FROM sr_fixture_asset_transactions WHERE id = :id', ['id' => (int) ($reclaimResult['transaction_id'] ?? 0)]);
sr_quiz_reward_runtime_assert((int) ($reclaimTransaction['account_id'] ?? 0) === 11, 'quiz reward reclaim should target the grant account.');
sr_quiz_reward_runtime_assert((int) ($reclaimTransaction['amount'] ?? 0) === -50, 'quiz reward reclaim should create a negative reclaim transaction.');
sr_quiz_reward_runtime_assert((string) ($reclaimTransaction['transaction_type'] ?? '') === 'reclaim', 'quiz reward reclaim should use reclaim transaction type.');
sr_quiz_reward_runtime_assert((string) ($reclaimTransaction['reference_type'] ?? '') === 'reclaim', 'quiz reward reclaim should use reclaim reference type.');
sr_quiz_reward_runtime_assert((string) ($reclaimTransaction['reference_id'] ?? '') === 'reward_transaction:2', 'quiz reward reclaim should reference the grant ledger transaction.');
$excessReclaimResult = sr_quiz_reclaim_reward_grant($pdo, $retried, $assetOptions, 100, 99, 'runtime reclaim excess');
sr_quiz_reward_runtime_assert((bool) ($excessReclaimResult['ok'] ?? true) === false, 'quiz reward fixture should reject reclaim amount over remaining amount.');

if ($errors !== []) {
    fwrite(STDERR, "quiz reward runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "quiz reward runtime checks completed.\n";
