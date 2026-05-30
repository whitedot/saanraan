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

if ($errors !== []) {
    fwrite(STDERR, "asset exchange log checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "asset exchange log checks completed.\n";
