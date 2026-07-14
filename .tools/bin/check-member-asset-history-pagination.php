#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];
$source = static function (string $file) use ($root, &$errors): string {
    $contents = file_get_contents($root . '/' . $file);
    if (!is_string($contents)) {
        $errors[] = 'cannot read member asset history source: ' . $file;
        return '';
    }

    return $contents;
};
$assertContains = static function (string $file, array $markers) use ($source, &$errors): void {
    $contents = $source($file);
    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $errors[] = $file . ' missing pagination marker: ' . $marker;
        }
    }
};

foreach (['reward', 'deposit'] as $moduleKey) {
    $requestName = $moduleKey === 'reward' ? 'withdrawal' : 'refund';
    $action = 'modules/' . $moduleKey . '/actions/account-' . ($moduleKey === 'reward' ? 'rewards' : 'deposits') . '.php';
    $view = 'modules/' . $moduleKey . '/views/account-' . ($moduleKey === 'reward' ? 'rewards' : 'deposits') . '.php';
    $helper = 'modules/' . $moduleKey . '/helpers.php';
    $assertContains($action, [
        "sr_get_string('request_page'",
        "sr_get_string('transaction_page'",
        'SELECT COUNT(*) FROM sr_' . $moduleKey . '_transactions',
        'LIMIT :limit OFFSET :offset',
        'PDO::PARAM_INT',
    ]);
    $assertContains($view, [
        'sr_public_pagination_html($' . $moduleKey . 'RequestPagination',
        'sr_public_pagination_html($' . $moduleKey . 'TransactionPagination',
        $moduleKey . '-' . $requestName . '-history',
        $moduleKey . '-transaction-history',
    ]);
    $assertContains($helper, [
        'function sr_' . $moduleKey . '_' . $requestName . '_request_count_for_account',
        "OFFSET ' . \$offset",
    ]);
    if (str_contains($source($action), 'LIMIT 100')) {
        $errors[] = $action . ' still caps member transaction history at 100 rows';
    }
}

$assertContains('modules/point/actions/account-points.php', [
    "sr_get_string('page'",
    'SELECT COUNT(*) FROM sr_point_transactions',
    'LIMIT :limit OFFSET :offset',
    '$pointTransactionPagination',
]);
$assertContains('modules/point/views/account-points.php', [
    'id="point-transaction-history"',
    'sr_public_pagination_html($pointTransactionPagination',
]);
if (str_contains($source('modules/point/actions/account-points.php'), 'LIMIT 100')) {
    $errors[] = 'modules/point/actions/account-points.php still caps member transaction history at 100 rows';
}

if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}
require_once $root . '/modules/reward/helpers.php';
require_once $root . '/modules/deposit/helpers.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
foreach (['reward_withdrawal', 'deposit_refund'] as $tablePrefix) {
    $pdo->exec(
        'CREATE TABLE sr_' . $tablePrefix . '_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            amount INTEGER NOT NULL,
            bank_name TEXT NOT NULL,
            bank_account_number TEXT NOT NULL,
            bank_account_holder TEXT NOT NULL,
            requester_note TEXT NOT NULL,
            status TEXT NOT NULL,
            admin_note TEXT NOT NULL,
            transaction_id INTEGER NULL,
            requested_at TEXT NOT NULL,
            processed_at TEXT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $insert = $pdo->prepare(
        'INSERT INTO sr_' . $tablePrefix . '_requests
         (account_id, amount, bank_name, bank_account_number, bank_account_holder, requester_note, status, admin_note, requested_at, updated_at)
         VALUES (1, :amount, \'bank\', \'account\', \'holder\', \'\', \'pending\', \'\', \'2026-01-01 00:00:00\', \'2026-01-01 00:00:00\')'
    );
    for ($rowNumber = 1; $rowNumber <= 45; $rowNumber++) {
        $insert->execute(['amount' => $rowNumber]);
    }
}

if (sr_reward_withdrawal_request_count_for_account($pdo, 1) !== 45) {
    $errors[] = 'reward withdrawal count must include every account request';
}
$rewardPage = sr_reward_withdrawal_requests_for_account($pdo, 1, 20, 20);
if (count($rewardPage) !== 20 || (int) ($rewardPage[0]['id'] ?? 0) !== 25 || (int) ($rewardPage[19]['id'] ?? 0) !== 6) {
    $errors[] = 'reward withdrawal pagination must return the requested ordered slice';
}
if (sr_deposit_refund_request_count_for_account($pdo, 1) !== 45) {
    $errors[] = 'deposit refund count must include every account request';
}
$depositPage = sr_deposit_refund_requests_for_account($pdo, 1, 20, 40);
if (count($depositPage) !== 5 || (int) ($depositPage[0]['id'] ?? 0) !== 5 || (int) ($depositPage[4]['id'] ?? 0) !== 1) {
    $errors[] = 'deposit refund pagination must expose the final partial page';
}

if ($errors !== []) {
    fwrite(STDERR, "member asset history pagination checks failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "member asset history pagination checks completed.\n";
