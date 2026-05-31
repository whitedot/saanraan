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
) {
    $errors[] = 'Asset exchange must provide a completed group correction helper that records reversal ledger entries.';
}

if ($errors !== []) {
    fwrite(STDERR, "asset exchange log checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "asset exchange log checks completed.\n";
