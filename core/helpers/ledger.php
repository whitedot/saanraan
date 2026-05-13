<?php

declare(strict_types=1);

function sr_ledger_create_transaction(PDO $pdo, array $config, array $data): int
{
    $balanceTable = (string) ($config['balance_table'] ?? '');
    $transactionTable = (string) ($config['transaction_table'] ?? '');
    $balanceRowError = (string) ($config['balance_row_error'] ?? 'Ledger balance row was not created.');
    $negativeBalanceError = (string) ($config['negative_balance_error'] ?? 'Ledger balance cannot be negative.');

    if (!sr_ledger_is_safe_table_name($balanceTable) || !sr_ledger_is_safe_table_name($transactionTable)) {
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
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO ' . $balanceTable . ' (account_id, balance, created_at, updated_at)
             VALUES (:account_id, 0, :created_at, :updated_at)'
        );
        $stmt->execute([
            'account_id' => $accountId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $stmt = $pdo->prepare('SELECT balance FROM ' . $balanceTable . ' WHERE account_id = :account_id LIMIT 1 FOR UPDATE');
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
        $pdo->commit();

        return $transactionId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function sr_ledger_is_safe_table_name(string $tableName): bool
{
    return preg_match('/\Asr_[a-z0-9_]{1,120}\z/', $tableName) === 1;
}

function sr_ledger_nullable_positive_int(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    $intValue = (int) $value;
    return $intValue > 0 ? $intValue : null;
}
