<?php

declare(strict_types=1);

function toy_deposit_balance(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT balance FROM toy_deposit_balances WHERE account_id = :account_id LIMIT 1');
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();

    return is_array($row) ? (int) $row['balance'] : 0;
}

function toy_deposit_create_transaction(PDO $pdo, array $data): int
{
    $accountId = (int) ($data['account_id'] ?? 0);
    $amount = (int) ($data['amount'] ?? 0);
    $transactionType = toy_deposit_clean_key((string) ($data['transaction_type'] ?? 'adjustment'), 40);
    $reason = toy_deposit_clean_text((string) ($data['reason'] ?? ''), 255);
    $referenceType = toy_deposit_clean_key((string) ($data['reference_type'] ?? ''), 60);
    $referenceId = toy_deposit_clean_reference_id((string) ($data['reference_id'] ?? ''), 120);
    $createdByAccountId = isset($data['created_by_account_id']) ? (int) $data['created_by_account_id'] : null;

    if ($accountId <= 0) {
        throw new InvalidArgumentException('Account id is required.');
    }

    if ($amount === 0) {
        throw new InvalidArgumentException('Amount must not be zero.');
    }

    if (!toy_deposit_transaction_type_allows_amount($transactionType, $amount)) {
        throw new InvalidArgumentException('Deposit transaction amount sign is invalid for type.');
    }

    return toy_ledger_create_transaction($pdo, [
        'balance_table' => 'toy_deposit_balances',
        'transaction_table' => 'toy_deposit_transactions',
        'balance_row_error' => 'Deposit balance row was not created.',
        'negative_balance_error' => 'Deposit balance cannot be negative.',
    ], [
        'account_id' => $accountId,
        'amount' => $amount,
        'transaction_type' => $transactionType,
        'reason' => $reason,
        'reference_type' => $referenceType,
        'reference_id' => $referenceId,
        'created_by_account_id' => $createdByAccountId,
    ]);
}

function toy_deposit_transaction_type_allows_amount(string $transactionType, int $amount): bool
{
    if ($amount === 0) {
        return false;
    }

    if (in_array($transactionType, ['deposit', 'refund'], true)) {
        return $amount > 0;
    }

    if (in_array($transactionType, ['use', 'withdraw'], true)) {
        return $amount < 0;
    }

    return $transactionType === 'adjustment';
}

function toy_deposit_clean_key(string $value, int $maxLength): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9_.-]/', '', strtolower($value));
    $value = is_string($value) ? $value : '';

    return substr($value, 0, $maxLength);
}

function toy_deposit_clean_reference_id(string $value, int $maxLength): string
{
    $value = trim($value);
    $value = preg_replace('/[^a-zA-Z0-9_.:-]/', '', $value);
    $value = is_string($value) ? $value : '';

    return substr($value, 0, $maxLength);
}

function toy_deposit_clean_text(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}
