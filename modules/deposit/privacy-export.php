<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    if ($accountId < 1) {
        return [
            'balance' => [],
            'transactions' => [],
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT account_id, balance, created_at, updated_at
         FROM sr_deposit_balances
         WHERE account_id = :account_id
         LIMIT 1'
    );
    $stmt->execute(['account_id' => $accountId]);
    $balance = $stmt->fetch();

    $stmt = $pdo->prepare(
        'SELECT id, account_id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_by_account_id, created_at
         FROM sr_deposit_transactions
         WHERE account_id = :account_id
         ORDER BY id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);

    return [
        'balance' => is_array($balance) ? $balance : [],
        'transactions' => $stmt->fetchAll(),
    ];
};
