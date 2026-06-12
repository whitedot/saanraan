#!/usr/bin/env php
<?php

declare(strict_types=1);

define('SR_ROOT', dirname(__DIR__, 2));
chdir(SR_ROOT);

require_once SR_ROOT . '/core/helpers.php';

if (!sr_is_installed()) {
    fwrite(STDERR, "saanraan is not installed.\n");
    exit(2);
}

$maxRows = 50;
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/\A--max-rows=([0-9]+)\z/', (string) $argument, $matches) === 1) {
        $maxRows = max(1, min(500, (int) $matches[1]));
    }
}

try {
    $config = sr_load_config();
    sr_set_runtime_config($config);
    sr_apply_runtime_config($config);
    $pdo = sr_db($config);

    require_once SR_ROOT . '/modules/asset_ledger/helpers.php';

    $reconciliationResults = sr_asset_reconcile_all($pdo, $maxRows, true);
    $summary = sr_asset_reconciliation_summary($reconciliationResults);
    foreach ($reconciliationResults as $moduleKey => $result) {

        echo (string) $moduleKey
            . "\t" . (string) $result['status']
            . "\taccounts=" . (int) $result['total_accounts']
            . "\tmismatches=" . (int) $result['mismatch_count'];
        if ((string) $result['message'] !== '') {
            echo "\tmessage=" . (string) $result['message'];
        }
        echo "\n";

        foreach ((array) $result['mismatches'] as $mismatch) {
            echo (string) $moduleKey
                . "\tmismatch"
                . "\taccount_id=" . (int) $mismatch['account_id']
                . "\tstored_balance=" . sr_asset_reconcile_nullable_int($mismatch['stored_balance'])
                . "\tledger_balance=" . (int) $mismatch['ledger_balance']
                . "\tlast_balance_after=" . sr_asset_reconcile_nullable_int($mismatch['last_balance_after'])
                . "\ttransaction_count=" . (int) $mismatch['transaction_count']
                . "\tsequence_mismatch_transaction_id=" . sr_asset_reconcile_nullable_int($mismatch['sequence_mismatch_transaction_id'] ?? null)
                . "\tsequence_expected_balance_after=" . sr_asset_reconcile_nullable_int($mismatch['sequence_expected_balance_after'] ?? null)
                . "\tsequence_actual_balance_after=" . sr_asset_reconcile_nullable_int($mismatch['sequence_actual_balance_after'] ?? null)
                . "\tissues=" . implode(',', (array) $mismatch['issues'])
                . "\n";
        }

        if (!empty($result['truncated'])) {
            echo (string) $moduleKey . "\tmismatch_rows_truncated\tshown=" . $maxRows . "\ttotal=" . (int) $result['mismatch_count'] . "\n";
        }
    }

    if (!empty($summary['has_error'])) {
        fwrite(STDERR, 'asset reconciliation errors=' . (int) $summary['error'] . "\n");
        exit(1);
    }

    if (!empty($summary['has_mismatch'])) {
        fwrite(STDERR, 'asset reconciliation mismatches=' . (int) $summary['mismatch_count'] . "\n");
        exit(1);
    }

    echo "asset reconciliation completed without mismatches.\n";
} catch (Throwable $exception) {
    sr_log_exception($exception, 'asset_reconciliation_cli');
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
