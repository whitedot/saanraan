#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

$helper = file_get_contents($root . '/modules/asset_exchange/helpers.php');
if (!is_string($helper)) {
    $errors[] = 'Asset exchange helper cannot be read.';
} elseif (
    strpos($helper, "'deposit_amount' => (int) \$quote['deposit_amount']") === false
    || strpos($helper, "'deposit_amount' => (int) \$quote['deposit_before_fee']") !== false
) {
    $errors[] = 'Asset exchange logs must store the final deposit amount after fee deduction.';
}

$update = file_get_contents($root . '/modules/asset_exchange/updates/2026.05.002.sql');
if (!is_string($update)) {
    $errors[] = 'Asset exchange deposit amount correction update is missing.';
} elseif (
    strpos($update, 'SET deposit_amount = deposit_amount - fee_amount') === false
    || strpos($update, "WHERE module_key = 'asset_exchange'") === false
) {
    $errors[] = 'Asset exchange update must correct existing fee-inclusive deposit log amounts and bump the module version.';
}

$smokeTest = file_get_contents($root . '/docs/smoke-test.md');
if (!is_string($smokeTest) || strpos($smokeTest, 'sr_asset_exchange_logs.deposit_amount`에는 수수료 차감 후 최종 증가액') === false) {
    $errors[] = 'Smoke test docs must state that asset exchange log deposit_amount is the final amount after fee deduction.';
}

$accountAction = file_get_contents($root . '/modules/asset_exchange/actions/account-asset-exchange.php');
if (!is_string($accountAction)) {
    $errors[] = 'Asset exchange account action cannot be read.';
} elseif (
    strpos($accountAction, 'sr_asset_exchange_execute_rate_limited($pdo, (int) $account[\'id\'])') === false
    || strpos($accountAction, 'sr_asset_exchange_record_execute_attempt($pdo, (int) $account[\'id\'])') === false
) {
    $errors[] = 'Asset exchange account execution must enforce account rate limiting before exchange execution.';
}

$adminLogAction = file_get_contents($root . '/modules/asset_exchange/actions/admin-asset-exchange-logs.php');
$adminLogView = file_get_contents($root . '/modules/asset_exchange/views/admin-asset-exchange-logs.php');
$paths = file_get_contents($root . '/modules/asset_exchange/paths.php');
$httpSmoke = file_get_contents($root . '/.tools/bin/smoke-http.php');
if (
    !is_string($paths)
    || strpos($paths, "'POST /admin/asset-exchange/logs' => 'actions/admin-asset-exchange-logs.php'") === false
    || !is_string($adminLogAction)
    || strpos($adminLogAction, 'sr_require_csrf();') === false
    || strpos($adminLogAction, 'sr_admin_require_permission($pdo, (int) $account[\'id\'], \'/admin/asset-exchange/logs\', \'edit\');') === false
    || strpos($adminLogAction, 'sr_asset_exchange_correct_completed_group($pdo, $exchangeGroupId, (int) $account[\'id\'], $reason)') === false
    || strpos($adminLogAction, "'event_type' => 'asset_exchange.log.corrected'") === false
    || !is_string($adminLogView)
    || strpos($adminLogView, 'name="intent" value="correct_completed_group"') === false
    || strpos($adminLogView, 'sr_csrf_field()') === false
    || strpos($adminLogView, '환전 묶음 정정') === false
    || !is_string($httpSmoke)
    || strpos($httpSmoke, 'admin asset exchange logs entry') === false
    || strpos($httpSmoke, 'admin asset exchange correction action guard') === false
) {
    $errors[] = 'Asset exchange admin logs must expose a CSRF-protected edit action for completed group correction.';
}

if (
    !is_string($helper)
    || strpos($helper, "return 'asset_exchange.execute.account';") === false
    || strpos($helper, 'function sr_asset_exchange_execute_rate_limit_window_seconds(): int') === false
    || strpos($helper, 'function sr_asset_exchange_execute_rate_limit_max_attempts(): int') === false
) {
    $errors[] = 'Asset exchange helper must define account execution rate limit policy.';
}

if (!is_string($smokeTest) || strpos($smokeTest, '짧은 시간 반복 실행은 계정 단위 제한으로 막히는지 확인') === false) {
    $errors[] = 'Smoke test docs must include asset exchange account rate limit coverage.';
}

if (
    !is_string($helper)
    || strpos($helper, 'function sr_asset_exchange_submit_token_lifetime_seconds(): int') === false
    || strpos($helper, 'function sr_asset_exchange_quote_token_hash(int $policyId, int $amount, array $quote): string') === false
    || strpos($helper, 'function sr_asset_exchange_consume_submit_token(string $token, int $policyId, int $amount, array $quote): bool') === false
    || strpos($helper, "'quote_hash' => sr_asset_exchange_quote_token_hash") === false
) {
    $errors[] = 'Asset exchange submit tokens must expire and be bound to policy, amount, and quote.';
}

if (!is_string($accountAction) || strpos($accountAction, 'sr_asset_exchange_consume_submit_token($submitToken, (int) $selectedPolicy[\'id\'], $amount, $quote)') === false) {
    $errors[] = 'Asset exchange account action must verify the bound quote token before execution.';
}

$installSql = file_get_contents($root . '/modules/asset_exchange/install.sql');
$indexUpdate = file_get_contents($root . '/modules/asset_exchange/updates/2026.05.007.sql');
if (
    !is_string($installSql)
    || strpos($installSql, 'idx_sr_asset_exchange_logs_reexchange (account_id, to_module_key, status, created_at)') === false
    || !is_string($indexUpdate)
    || strpos($indexUpdate, 'idx_sr_asset_exchange_logs_reexchange (account_id, to_module_key, status, created_at)') === false
) {
    $errors[] = 'Asset exchange reexchange fee lookup must have an account/to-module/status/created index in install and update SQL.';
}
if (
    !is_string($helper)
    || strpos($helper, 'function sr_asset_exchange_validate_policy_cycle_safety(PDO $pdo, array $policy): void') === false
    || strpos($helper, 'sr_asset_exchange_policy_cycle_increases_value($policy, $reversePolicy)') === false
    || strpos($helper, '무수수료 양방향 환전에서 반복 환전 시 가치가 증가할 수 있습니다') === false
) {
    $errors[] = 'Asset exchange policy save must reject fee-free bidirectional cycles that can increase value.';
}

if (
    !is_string($helper)
    || strpos($helper, 'function sr_asset_exchange_execute_once(PDO $pdo, array $policy, int $accountId, int $amount, ?int $createdByAccountId = null): int') === false
    || strpos($helper, 'function sr_asset_exchange_is_retryable_transaction_exception(Throwable $exception): bool') === false
    || strpos($helper, 'in_array($driverCode, [1205, 1213], true)') === false
) {
    $errors[] = 'Asset exchange execution must retry standalone deadlock/lock-timeout transaction failures.';
}

if (
    !is_string($helper)
    || strpos($helper, 'function sr_asset_exchange_correct_completed_group(PDO $pdo, string $exchangeGroupId, int $createdByAccountId') === false
    || strpos($helper, "'reference_type' => 'asset_exchange_correction'") === false
    || strpos($helper, 'correction_for:') === false
    || strpos($helper, 'function sr_asset_exchange_correction_group_id(string $exchangeGroupId): string') === false
    || strpos($helper, 'function sr_asset_exchange_for_update_clause(PDO $pdo): string') === false
) {
    $errors[] = 'Asset exchange must provide a completed group correction helper that records reversal ledger entries.';
}

if (
    !is_string($helper)
    || strpos($helper, '$feeTransactionId = $toTransactionFunction($pdo, [') === false
    || strpos($helper, "'amount' => \$feeAmount") === false
    || strpos($helper, "'amount' => -\$depositBeforeFee") === false
) {
    $errors[] = 'Asset exchange correction must refund the fee before withdrawing the pre-fee deposit amount.';
}

$ledgerHelper = file_get_contents($root . '/modules/asset_ledger/helpers.php');
if (
    !is_string($ledgerHelper)
    || strpos($ledgerHelper, 'function sr_ledger_insert_ignore_into_clause(PDO $pdo): string') === false
    || strpos($ledgerHelper, 'function sr_ledger_for_update_clause(PDO $pdo): string') === false
    || strpos($ledgerHelper, 'INSERT OR IGNORE INTO') === false
) {
    $errors[] = 'Shared asset ledger must use SQLite-safe insert-ignore and row-lock clauses for runtime fixtures.';
}

if ($errors === []) {
    $runtimeErrors = sr_asset_exchange_runtime_correction_check($root);
    foreach ($runtimeErrors as $runtimeError) {
        $errors[] = $runtimeError;
    }
}

if ($errors !== []) {
    fwrite(STDERR, "asset exchange log checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "asset exchange log checks completed.\n";

function sr_asset_exchange_runtime_correction_check(string $root): array
{
    if (!extension_loaded('pdo_sqlite')) {
        return ['pdo_sqlite extension is required for the asset exchange correction runtime fixture.'];
    }

    if (!defined('SR_ROOT')) {
        define('SR_ROOT', $root);
    }

    require_once $root . '/core/helpers.php';
    require_once $root . '/modules/asset_exchange/helpers.php';

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    sr_asset_exchange_runtime_create_schema($pdo);
    sr_asset_exchange_runtime_seed_completed_exchange($pdo);

    $exchangeGroupId = 'fixture_exchange_group';
    $correctionGroupId = sr_asset_exchange_correction_group_id($exchangeGroupId);
    $correctionLogId = sr_asset_exchange_correct_completed_group($pdo, $exchangeGroupId, 9001, 'runtime correction');

    $errors = [];
    if ($correctionLogId <= 0) {
        $errors[] = 'Asset exchange correction runtime fixture did not return a correction log id.';
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_asset_exchange_logs WHERE exchange_group_id = :exchange_group_id LIMIT 1');
    $stmt->execute(['exchange_group_id' => $correctionGroupId]);
    $correctionLog = $stmt->fetch();
    if (!is_array($correctionLog)) {
        $errors[] = 'Asset exchange correction runtime fixture did not create a correction log.';
    } else {
        $expected = [
            'request_amount' => -100,
            'deposit_amount' => -90,
            'fee_amount' => -10,
            'status' => 'completed',
            'failure_reason' => 'correction_for:' . $exchangeGroupId,
        ];
        foreach ($expected as $key => $value) {
            if ((string) ($correctionLog[$key] ?? '') !== (string) $value) {
                $errors[] = 'Asset exchange correction runtime fixture logged unexpected ' . $key . '.';
            }
        }
    }

    $rewardBalance = sr_asset_exchange_runtime_balance($pdo, 'sr_reward_balances', 123);
    $depositBalance = sr_asset_exchange_runtime_balance($pdo, 'sr_deposit_balances', 123);
    if ($rewardBalance !== 100) {
        $errors[] = 'Asset exchange correction runtime fixture did not restore the source reward balance.';
    }
    if ($depositBalance !== 0) {
        $errors[] = 'Asset exchange correction runtime fixture did not reverse the final deposit balance.';
    }

    $rewardCorrectionAmount = sr_asset_exchange_runtime_reference_sum($pdo, 'sr_reward_transactions', $correctionGroupId);
    $depositCorrectionAmount = sr_asset_exchange_runtime_reference_sum($pdo, 'sr_deposit_transactions', $correctionGroupId);
    if ($rewardCorrectionAmount !== 100) {
        $errors[] = 'Asset exchange correction runtime fixture did not record the reward reversal ledger entry.';
    }
    if ($depositCorrectionAmount !== -90) {
        $errors[] = 'Asset exchange correction runtime fixture did not record the net deposit reversal ledger entries.';
    }

    try {
        sr_asset_exchange_correct_completed_group($pdo, $exchangeGroupId, 9001, 'runtime duplicate correction');
        $errors[] = 'Asset exchange correction runtime fixture did not reject duplicate correction.';
    } catch (RuntimeException $exception) {
        if (strpos($exception->getMessage(), '이미 정정된 환전 묶음') === false) {
            $errors[] = 'Asset exchange correction runtime fixture rejected duplicate correction with an unexpected message.';
        }
    }

    return $errors;
}

function sr_asset_exchange_runtime_create_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE sr_modules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_key TEXT NOT NULL,
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

function sr_asset_exchange_runtime_seed_completed_exchange(PDO $pdo): void
{
    $now = sr_now();
    $pdo->prepare('INSERT INTO sr_modules (module_key, version, status) VALUES (:module_key, :version, :status)')
        ->execute(['module_key' => 'reward', 'version' => 'fixture', 'status' => 'enabled']);
    $pdo->prepare('INSERT INTO sr_modules (module_key, version, status) VALUES (:module_key, :version, :status)')
        ->execute(['module_key' => 'deposit', 'version' => 'fixture', 'status' => 'enabled']);
    $pdo->prepare('INSERT INTO sr_reward_balances (account_id, balance, created_at, updated_at) VALUES (:account_id, :balance, :created_at, :updated_at)')
        ->execute(['account_id' => 123, 'balance' => 0, 'created_at' => $now, 'updated_at' => $now]);
    $pdo->prepare('INSERT INTO sr_deposit_balances (account_id, balance, created_at, updated_at) VALUES (:account_id, :balance, :created_at, :updated_at)')
        ->execute(['account_id' => 123, 'balance' => 90, 'created_at' => $now, 'updated_at' => $now]);
    $pdo->prepare(
        'INSERT INTO sr_asset_exchange_logs
            (exchange_group_id, policy_id, account_id, from_module_key, to_module_key, request_amount,
             rate_numerator, rate_denominator, rounding_mode, deposit_amount, fee_amount, fee_trigger, fee_basis,
             status, failure_reason, from_transaction_id, to_transaction_id, fee_transaction_id, created_by_account_id, created_at)
         VALUES
            (:exchange_group_id, :policy_id, :account_id, :from_module_key, :to_module_key, :request_amount,
             :rate_numerator, :rate_denominator, :rounding_mode, :deposit_amount, :fee_amount, :fee_trigger, :fee_basis,
             :status, :failure_reason, :from_transaction_id, :to_transaction_id, :fee_transaction_id, :created_by_account_id, :created_at)'
    )->execute([
        'exchange_group_id' => 'fixture_exchange_group',
        'policy_id' => 10,
        'account_id' => 123,
        'from_module_key' => 'reward',
        'to_module_key' => 'deposit',
        'request_amount' => 100,
        'rate_numerator' => 1,
        'rate_denominator' => 1,
        'rounding_mode' => 'floor',
        'deposit_amount' => 90,
        'fee_amount' => 10,
        'fee_trigger' => 'always',
        'fee_basis' => 'to_amount',
        'status' => 'completed',
        'failure_reason' => '',
        'from_transaction_id' => 1,
        'to_transaction_id' => 2,
        'fee_transaction_id' => 3,
        'created_by_account_id' => 9001,
        'created_at' => $now,
    ]);
}

function sr_asset_exchange_runtime_balance(PDO $pdo, string $table, int $accountId): int
{
    $stmt = $pdo->prepare('SELECT balance FROM ' . $table . ' WHERE account_id = :account_id LIMIT 1');
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();

    return is_array($row) ? (int) $row['balance'] : 0;
}

function sr_asset_exchange_runtime_reference_sum(PDO $pdo, string $table, string $referenceId): int
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0) AS total_amount
         FROM ' . $table . "
         WHERE reference_type = 'asset_exchange_correction'
           AND reference_id = :reference_id"
    );
    $stmt->execute(['reference_id' => $referenceId]);
    $row = $stmt->fetch();

    return is_array($row) ? (int) $row['total_amount'] : 0;
}
