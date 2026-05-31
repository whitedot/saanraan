<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    if ($accountId < 1) {
        return [
            'balance' => [],
            'transactions' => [],
            'withdrawal_requests' => [],
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT account_id, balance, created_at, updated_at
         FROM sr_reward_balances
         WHERE account_id = :account_id
         LIMIT 1'
    );
    $stmt->execute(['account_id' => $accountId]);
    $balance = $stmt->fetch();

    $stmt = $pdo->prepare(
        'SELECT id, account_id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_by_account_id, created_at
         FROM sr_reward_transactions
         WHERE account_id = :account_id
         ORDER BY id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $transactions = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT id, account_id, amount, bank_name, bank_account_number, bank_account_holder, requester_note, status, admin_note, transaction_id, processed_by_account_id, requested_at, processed_at, updated_at
         FROM sr_reward_withdrawal_requests
         WHERE account_id = :account_id
         ORDER BY id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);

    return [
        'balance' => is_array($balance) ? $balance : [],
        'transactions' => $transactions,
        'withdrawal_requests' => $stmt->fetchAll(),
    ];
};
