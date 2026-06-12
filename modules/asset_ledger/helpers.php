<?php

declare(strict_types=1);

function sr_ledger_create_transaction(PDO $pdo, array $config, array $data): int
{
    $balanceTable = (string) ($config['balance_table'] ?? '');
    $transactionTable = (string) ($config['transaction_table'] ?? '');
    $balanceRowError = (string) ($config['balance_row_error'] ?? 'Ledger balance row was not created.');
    $negativeBalanceError = (string) ($config['negative_balance_error'] ?? 'Ledger balance cannot be negative.');

    if (!sr_ledger_table_pair_is_allowed($balanceTable, $transactionTable)) {
        throw new InvalidArgumentException('Ledger table name is invalid.');
    }

    $accountId = (int) ($data['account_id'] ?? 0);
    $amount = (int) ($data['amount'] ?? 0);
    $transactionType = (string) ($data['transaction_type'] ?? 'adjustment');
    $reason = (string) ($data['reason'] ?? '');
    $referenceType = (string) ($data['reference_type'] ?? '');
    $referenceId = (string) ($data['reference_id'] ?? '');
    $createdByAccountId = sr_ledger_nullable_positive_int($data['created_by_account_id'] ?? null);

    if ($accountId <= 0) {
        throw new InvalidArgumentException('Account id is required.');
    }

    if ($amount === 0) {
        throw new InvalidArgumentException('Amount must not be zero.');
    }

    $now = sr_now();
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare(
            sr_ledger_insert_ignore_into_clause($pdo) . ' ' . $balanceTable . ' (account_id, balance, created_at, updated_at)
             VALUES (:account_id, 0, :created_at, :updated_at)'
        );
        $stmt->execute([
            'account_id' => $accountId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $stmt = $pdo->prepare(
            'SELECT balance FROM ' . $balanceTable . ' WHERE account_id = :account_id LIMIT 1'
            . sr_ledger_for_update_clause($pdo)
        );
        $stmt->execute(['account_id' => $accountId]);
        $balanceRow = $stmt->fetch();
        if (!is_array($balanceRow)) {
            throw new RuntimeException($balanceRowError);
        }

        $balanceAfter = (int) $balanceRow['balance'] + $amount;
        if ($balanceAfter < 0) {
            throw new RuntimeException($negativeBalanceError);
        }

        $stmt = $pdo->prepare(
            'UPDATE ' . $balanceTable . '
             SET balance = :balance, updated_at = :updated_at
             WHERE account_id = :account_id'
        );
        $stmt->execute([
            'balance' => $balanceAfter,
            'updated_at' => $now,
            'account_id' => $accountId,
        ]);

        $stmt = $pdo->prepare(
            'INSERT INTO ' . $transactionTable . '
                (account_id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_by_account_id, created_at)
             VALUES
                (:account_id, :amount, :balance_after, :transaction_type, :reason, :reference_type, :reference_id, :created_by_account_id, :created_at)'
        );
        $stmt->execute([
            'account_id' => $accountId,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'transaction_type' => $transactionType,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by_account_id' => $createdByAccountId,
            'created_at' => $now,
        ]);

        $transactionId = (int) $pdo->lastInsertId();
        if ($startedTransaction) {
            $pdo->commit();
        }

        return $transactionId;
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function sr_ledger_pdo_driver(PDO $pdo): string
{
    try {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable) {
        return '';
    }
}

function sr_ledger_insert_ignore_into_clause(PDO $pdo): string
{
    return sr_ledger_pdo_driver($pdo) === 'sqlite' ? 'INSERT OR IGNORE INTO' : 'INSERT IGNORE INTO';
}

function sr_ledger_for_update_clause(PDO $pdo): string
{
    return sr_ledger_pdo_driver($pdo) === 'sqlite' ? '' : ' FOR UPDATE';
}

function sr_asset_reconciliation_targets(): array
{
    return [
        'point' => [
            'label' => '포인트',
            'balance_table' => 'sr_point_balances',
            'transaction_table' => 'sr_point_transactions',
        ],
        'reward' => [
            'label' => '적립금',
            'balance_table' => 'sr_reward_balances',
            'transaction_table' => 'sr_reward_transactions',
        ],
        'deposit' => [
            'label' => '예치금',
            'balance_table' => 'sr_deposit_balances',
            'transaction_table' => 'sr_deposit_transactions',
        ],
    ];
}

function sr_asset_reconcile_all(PDO $pdo, int $maxRows = 50, bool $enabledOnly = true): array
{
    $maxRows = max(1, min(500, $maxRows));
    $results = [];

    foreach (sr_asset_reconciliation_targets() as $moduleKey => $target) {
        if ($enabledOnly && function_exists('sr_module_enabled') && !sr_module_enabled($pdo, (string) $moduleKey)) {
            $results[(string) $moduleKey] = [
                'module_key' => (string) $moduleKey,
                'label' => (string) $target['label'],
                'status' => 'skipped',
                'message' => 'module disabled',
                'total_accounts' => 0,
                'mismatch_count' => 0,
                'mismatches' => [],
                'truncated' => false,
            ];
            continue;
        }

        try {
            $results[(string) $moduleKey] = sr_asset_reconcile_module(
                $pdo,
                (string) $moduleKey,
                (string) $target['label'],
                (string) $target['balance_table'],
                (string) $target['transaction_table'],
                $maxRows
            );
        } catch (Throwable $exception) {
            $results[(string) $moduleKey] = [
                'module_key' => (string) $moduleKey,
                'label' => (string) $target['label'],
                'status' => 'error',
                'message' => $exception->getMessage(),
                'total_accounts' => 0,
                'mismatch_count' => 0,
                'mismatches' => [],
                'truncated' => false,
            ];
        }
    }

    return $results;
}

function sr_asset_reconcile_module(PDO $pdo, string $moduleKey, string $label, string $balanceTable, string $transactionTable, int $maxRows): array
{
    sr_asset_reconcile_assert_table($pdo, $balanceTable);
    sr_asset_reconcile_assert_table($pdo, $transactionTable);

    $balances = [];
    $stmt = $pdo->query('SELECT account_id, balance FROM ' . $balanceTable);
    foreach ($stmt ?: [] as $row) {
        $accountId = (int) $row['account_id'];
        $balances[$accountId] = (int) $row['balance'];
    }

    $ledgerTotals = [];
    $transactionCounts = [];
    $stmt = $pdo->query('SELECT account_id, COALESCE(SUM(amount), 0) AS ledger_balance, COUNT(*) AS transaction_count FROM ' . $transactionTable . ' GROUP BY account_id');
    foreach ($stmt ?: [] as $row) {
        $accountId = (int) $row['account_id'];
        $ledgerTotals[$accountId] = (int) $row['ledger_balance'];
        $transactionCounts[$accountId] = (int) $row['transaction_count'];
    }

    $lastBalances = [];
    $stmt = $pdo->query(
        'SELECT t.account_id, t.balance_after
         FROM ' . $transactionTable . ' t
         INNER JOIN (
             SELECT account_id, MAX(id) AS last_id
             FROM ' . $transactionTable . '
             GROUP BY account_id
         ) latest ON latest.account_id = t.account_id AND latest.last_id = t.id'
    );
    foreach ($stmt ?: [] as $row) {
        $lastBalances[(int) $row['account_id']] = (int) $row['balance_after'];
    }

    $sequenceMismatches = sr_asset_reconcile_sequence_mismatches($pdo, $transactionTable);

    $accountIds = array_values(array_unique(array_merge(
        array_keys($balances),
        array_keys($ledgerTotals),
        array_keys($lastBalances),
        array_keys($sequenceMismatches)
    )));
    sort($accountIds, SORT_NUMERIC);

    $mismatches = [];
    foreach ($accountIds as $accountId) {
        $storedBalance = $balances[$accountId] ?? null;
        $ledgerBalance = $ledgerTotals[$accountId] ?? 0;
        $lastBalanceAfter = $lastBalances[$accountId] ?? null;
        $transactionCount = $transactionCounts[$accountId] ?? 0;
        $sequenceMismatch = $sequenceMismatches[$accountId] ?? null;
        $issues = [];

        if ($storedBalance === null) {
            $issues[] = 'missing_balance_row';
        } elseif ($storedBalance !== $ledgerBalance) {
            $issues[] = 'balance_sum_mismatch';
        }

        if ($transactionCount > 0 && $lastBalanceAfter !== $ledgerBalance) {
            $issues[] = 'last_balance_after_mismatch';
        }

        if ($transactionCount === 0 && $storedBalance !== null && $storedBalance !== 0) {
            $issues[] = 'nonzero_balance_without_transactions';
        }

        if (is_array($sequenceMismatch)) {
            $issues[] = 'balance_after_sequence_mismatch';
        }

        if ($issues !== []) {
            $mismatches[] = [
                'account_id' => $accountId,
                'stored_balance' => $storedBalance,
                'ledger_balance' => $ledgerBalance,
                'last_balance_after' => $lastBalanceAfter,
                'transaction_count' => $transactionCount,
                'sequence_mismatch_transaction_id' => is_array($sequenceMismatch) ? (int) ($sequenceMismatch['transaction_id'] ?? 0) : null,
                'sequence_expected_balance_after' => is_array($sequenceMismatch) ? (int) ($sequenceMismatch['expected_balance_after'] ?? 0) : null,
                'sequence_actual_balance_after' => is_array($sequenceMismatch) ? (int) ($sequenceMismatch['actual_balance_after'] ?? 0) : null,
                'issues' => $issues,
            ];
        }
    }

    return [
        'module_key' => $moduleKey,
        'label' => $label,
        'status' => 'checked',
        'message' => '',
        'total_accounts' => count($accountIds),
        'mismatch_count' => count($mismatches),
        'mismatches' => array_slice($mismatches, 0, $maxRows),
        'truncated' => count($mismatches) > $maxRows,
    ];
}

function sr_asset_reconcile_sequence_mismatches(PDO $pdo, string $transactionTable): array
{
    sr_asset_reconcile_assert_table($pdo, $transactionTable);

    $runningBalances = [];
    $mismatches = [];
    $stmt = $pdo->query('SELECT id, account_id, amount, balance_after FROM ' . $transactionTable . ' ORDER BY account_id ASC, id ASC');
    foreach ($stmt ?: [] as $row) {
        $accountId = (int) $row['account_id'];
        if (isset($mismatches[$accountId])) {
            continue;
        }

        $expectedBalanceAfter = (int) ($runningBalances[$accountId] ?? 0) + (int) $row['amount'];
        $actualBalanceAfter = (int) $row['balance_after'];
        if ($actualBalanceAfter !== $expectedBalanceAfter) {
            $mismatches[$accountId] = [
                'transaction_id' => (int) $row['id'],
                'expected_balance_after' => $expectedBalanceAfter,
                'actual_balance_after' => $actualBalanceAfter,
            ];
        }

        $runningBalances[$accountId] = $expectedBalanceAfter;
    }

    return $mismatches;
}

function sr_asset_reconciliation_summary(array $results): array
{
    $summary = [
        'checked' => 0,
        'skipped' => 0,
        'error' => 0,
        'mismatch_count' => 0,
        'total_accounts' => 0,
        'has_error' => false,
        'has_mismatch' => false,
    ];

    foreach ($results as $result) {
        if (!is_array($result)) {
            continue;
        }

        $status = (string) ($result['status'] ?? '');
        if (isset($summary[$status]) && in_array($status, ['checked', 'skipped', 'error'], true)) {
            $summary[$status]++;
        }

        $summary['total_accounts'] += (int) ($result['total_accounts'] ?? 0);
        $summary['mismatch_count'] += (int) ($result['mismatch_count'] ?? 0);
    }

    $summary['has_error'] = $summary['error'] > 0;
    $summary['has_mismatch'] = $summary['mismatch_count'] > 0;

    return $summary;
}

function sr_asset_reconcile_assert_table(PDO $pdo, string $tableName): void
{
    if (!sr_ledger_is_safe_table_name($tableName)) {
        throw new InvalidArgumentException('Unsafe table name: ' . $tableName);
    }

    $pdo->query('SELECT 1 FROM ' . $tableName . ' LIMIT 1');
}

function sr_asset_reconcile_nullable_int(mixed $value): string
{
    return $value === null ? 'NULL' : (string) (int) $value;
}

function sr_ledger_is_safe_table_name(string $tableName): bool
{
    return preg_match('/\Asr_[a-z0-9_]{1,120}\z/', $tableName) === 1;
}

function sr_ledger_table_pair_is_allowed(string $balanceTable, string $transactionTable): bool
{
    if (!sr_ledger_is_safe_table_name($balanceTable) || !sr_ledger_is_safe_table_name($transactionTable)) {
        return false;
    }

    $allowedPairs = [
        'sr_reward_balances' => 'sr_reward_transactions',
        'sr_deposit_balances' => 'sr_deposit_transactions',
    ];

    return isset($allowedPairs[$balanceTable]) && $allowedPairs[$balanceTable] === $transactionTable;
}

function sr_ledger_nullable_positive_int(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    $intValue = (int) $value;
    return $intValue > 0 ? $intValue : null;
}
