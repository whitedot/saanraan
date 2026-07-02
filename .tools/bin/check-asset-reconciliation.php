#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
define('SR_ROOT', $root);
require_once $root . '/core/helpers.php';
require_once $root . '/modules/asset_ledger/helpers.php';

$errors = [];

function sr_asset_reconciliation_check_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_asset_reconciliation_check_file_contains(string $file, array $markers): void
{
    $content = is_file($file) ? file_get_contents($file) : false;
    if (!is_string($content)) {
        sr_asset_reconciliation_check_error('required file is missing or unreadable: ' . $file);
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($content, $marker)) {
            sr_asset_reconciliation_check_error($file . ' is missing marker: ' . $marker);
        }
    }
}

sr_asset_reconciliation_check_file_contains('modules/asset_ledger/helpers.php', [
    'function sr_asset_reconciliation_targets',
    'function sr_asset_reconcile_all',
    'function sr_asset_reconcile_module',
    'function sr_asset_reconciliation_summary',
    'sr_point_balances',
    'sr_reward_balances',
    'sr_deposit_balances',
    'missing_balance_row',
    'balance_sum_mismatch',
    'last_balance_after_mismatch',
    'nonzero_balance_without_transactions',
    'balance_after_sequence_mismatch',
    'function sr_asset_reconcile_sequence_mismatches',
    "'sr_deposit_balances' => 'sr_deposit_transactions'",
]);

sr_asset_reconciliation_check_file_contains('modules/reward/helpers.php', [
    'function sr_reward_insert_ledger_transaction',
    'expires_remaining',
    'sr_reward_transactions',
]);

sr_asset_reconciliation_check_file_contains('modules/deposit/helpers.php', [
    'sr_ledger_create_transaction($pdo',
    "'balance_table' => 'sr_deposit_balances'",
    "'transaction_table' => 'sr_deposit_transactions'",
]);

sr_asset_reconciliation_check_file_contains('modules/point/helpers.php', [
    'function sr_point_insert_ledger_transaction',
    'expires_remaining',
    'sr_point_transactions',
]);

sr_asset_reconciliation_check_file_contains('.tools/bin/reconcile-assets.php', [
    "require_once SR_ROOT . '/modules/asset_ledger/helpers.php'",
    'sr_asset_reconcile_all($pdo',
    'sr_asset_reconciliation_summary($reconciliationResults)',
    'sequence_mismatch_transaction_id=',
    'asset reconciliation mismatches=',
    'asset reconciliation errors=',
]);

sr_asset_reconciliation_check_file_contains('modules/asset_ledger/paths.php', [
    'GET /admin/assets/reconciliation',
    'actions/admin-assets-reconciliation.php',
]);

sr_asset_reconciliation_check_file_contains('modules/asset_ledger/admin-menu.php', [
    '포인트/금액 점검',
    '정합성 점검',
    '/admin/assets/reconciliation',
]);

sr_asset_reconciliation_check_file_contains('core/actions/install.php', [
    "'asset_ledger' => [",
    "'label' => '잔액 처리 기반'",
    '포인트, 적립금, 예치금의 공통 잔액 처리와 원장 정합성 점검 기반을 제공합니다.',
]);

sr_asset_reconciliation_check_file_contains('core/views/install.php', [
    'member → admin → policy_documents → privacy',
    '선택 모듈, 플러그인, 자동 포함 모듈 설치',
    '자동 포함:',
    'data-install-auto-dependency-labels',
]);

sr_asset_reconciliation_check_file_contains('modules/asset_ledger/actions/admin-assets-reconciliation.php', [
    'sr_member_require_login($pdo)',
    'sr_admin_require_permission($pdo',
    "'/admin/assets/reconciliation', 'view'",
    'sr_asset_reconcile_all($pdo',
]);

sr_asset_reconciliation_check_file_contains('modules/asset_ledger/views/admin-reconciliation.php', [
    '포인트/금액 정합성 점검',
    'sr_asset_reconciliation_summary($reconciliationResults)',
    '점검 요약',
    '잔액 행 없음',
    '거래별 잔액 연쇄 불일치',
    '연쇄 오류 거래',
    '포인트/금액 항목',
]);

sr_asset_reconciliation_check_file_contains('docs/verification-status.md', [
    '/admin/assets/reconciliation',
    'reconcile-assets.php',
]);

sr_asset_reconciliation_check_file_contains('docs/operational-status.md', [
    '/admin/assets/reconciliation',
    '포인트/금액 정합성 점검',
]);

if (class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('CREATE TABLE sr_test_balances (account_id INTEGER PRIMARY KEY, balance INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE sr_test_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL, amount INTEGER NOT NULL, balance_after INTEGER NOT NULL)');
    $pdo->exec('INSERT INTO sr_test_balances (account_id, balance) VALUES (1, 30), (3, 5), (4, 20), (5, 99), (6, 15)');
    $pdo->exec('INSERT INTO sr_test_transactions (account_id, amount, balance_after) VALUES (1, 10, 10), (1, 20, 30), (2, 7, 7), (4, 10, 10), (4, 10, 999), (5, 1, 1), (5, 2, 3), (6, 10, 999), (6, 5, 15)');

    $result = sr_asset_reconcile_module($pdo, 'test', '테스트', 'sr_test_balances', 'sr_test_transactions', 2);
    if ((int) ($result['total_accounts'] ?? 0) !== 6) {
        sr_asset_reconciliation_check_error('fixture total account count mismatch.');
    }
    if ((int) ($result['mismatch_count'] ?? 0) !== 5) {
        sr_asset_reconciliation_check_error('fixture mismatch count mismatch.');
    }
    if (empty($result['truncated'])) {
        sr_asset_reconciliation_check_error('fixture should mark mismatches as truncated.');
    }

    $issueMap = [];
    $fullResult = sr_asset_reconcile_module($pdo, 'test', '테스트', 'sr_test_balances', 'sr_test_transactions', 50);
    foreach ((array) ($result['mismatches'] ?? []) as $mismatch) {
        $issueMap[(int) ($mismatch['account_id'] ?? 0)] = (array) ($mismatch['issues'] ?? []);
    }
    foreach ((array) ($fullResult['mismatches'] ?? []) as $mismatch) {
        $issueMap[(int) ($mismatch['account_id'] ?? 0)] = (array) ($mismatch['issues'] ?? []);
    }
    if (!in_array('missing_balance_row', $issueMap[2] ?? [], true)) {
        sr_asset_reconciliation_check_error('fixture should detect missing balance row.');
    }
    if (!in_array('nonzero_balance_without_transactions', $issueMap[3] ?? [], true)) {
        sr_asset_reconciliation_check_error('fixture should detect nonzero balance without transactions.');
    }
    if (!in_array('last_balance_after_mismatch', $issueMap[4] ?? [], true)) {
        sr_asset_reconciliation_check_error('fixture should detect last balance_after mismatch.');
    }
    if (!in_array('balance_sum_mismatch', $issueMap[5] ?? [], true)) {
        sr_asset_reconciliation_check_error('fixture should detect balance sum mismatch.');
    }
    if (!in_array('balance_after_sequence_mismatch', $issueMap[6] ?? [], true)) {
        sr_asset_reconciliation_check_error('fixture should detect a middle balance_after sequence mismatch even when totals match.');
    }

    $sequenceMismatches = sr_asset_reconcile_sequence_mismatches($pdo, 'sr_test_transactions');
    if ((int) ($sequenceMismatches[6]['transaction_id'] ?? 0) !== 8) {
        sr_asset_reconciliation_check_error('fixture should report the first sequence mismatch transaction id.');
    }
    if ((int) ($sequenceMismatches[6]['expected_balance_after'] ?? 0) !== 10 || (int) ($sequenceMismatches[6]['actual_balance_after'] ?? 0) !== 999) {
        sr_asset_reconciliation_check_error('fixture should report expected and actual sequence balances.');
    }

    $summary = sr_asset_reconciliation_summary([
        'test' => $result,
        'disabled' => [
            'status' => 'skipped',
            'total_accounts' => 0,
            'mismatch_count' => 0,
        ],
        'broken' => [
            'status' => 'error',
            'total_accounts' => 0,
            'mismatch_count' => 0,
        ],
    ]);
    if ((int) $summary['checked'] !== 1 || (int) $summary['skipped'] !== 1 || (int) $summary['error'] !== 1 || (int) $summary['mismatch_count'] !== 5) {
        sr_asset_reconciliation_check_error('fixture summary totals mismatch.');
    }
    if (empty($summary['has_error']) || empty($summary['has_mismatch'])) {
        sr_asset_reconciliation_check_error('fixture summary flags mismatch.');
    }

    if (!sr_ledger_table_pair_is_allowed('sr_deposit_balances', 'sr_deposit_transactions')) {
        sr_asset_reconciliation_check_error('deposit ledger table pair should be allowed.');
    }
    if (sr_ledger_table_pair_is_allowed('sr_point_balances', 'sr_point_transactions')) {
        sr_asset_reconciliation_check_error('point ledger table pair should stay outside the generic ledger helper because point has expiration fields.');
    }
    if (sr_ledger_table_pair_is_allowed('sr_reward_balances', 'sr_reward_transactions')) {
        sr_asset_reconciliation_check_error('reward ledger table pair should stay outside the generic ledger helper because reward has expiration fields.');
    }
    if (sr_ledger_table_pair_is_allowed('sr_reward_transactions', 'sr_reward_balances')) {
        sr_asset_reconciliation_check_error('reversed ledger table pair should not be allowed.');
    }
} else {
    sr_asset_reconciliation_check_error('PDO sqlite driver is required for asset reconciliation fixture.');
}

if ($errors !== []) {
    fwrite(STDERR, "asset reconciliation checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "asset reconciliation checks completed.\n";
