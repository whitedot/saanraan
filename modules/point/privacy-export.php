<?php

declare(strict_types=1);

require_once SR_ROOT . '/core/helpers/privacy-export.php';

return static function (PDO $pdo, int $accountId): array {
    $sectionLimits = [];
    if ($accountId < 1) {
        return [
            'balance' => [],
            'transactions' => [],
            'expiration_consumptions' => [],
            '_limits' => [],
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT account_id, balance, created_at, updated_at
         FROM sr_point_balances
         WHERE account_id = :account_id
         LIMIT 1'
    );
    $stmt->execute(['account_id' => $accountId]);
    $balance = $stmt->fetch();

    $stmt = $pdo->prepare(
        'SELECT id, account_id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_by_account_id, expires_at, expires_remaining, expired_at, created_at
         FROM sr_point_transactions
         WHERE account_id = :account_id
         ORDER BY id ASC
         LIMIT 1001'
    );
    $stmt->execute(['account_id' => $accountId]);
    $transactions = sr_privacy_export_limit_rows($stmt->fetchAll(), 'transactions', $sectionLimits, 1000);

    $stmt = $pdo->prepare(
        'SELECT id, account_id, consume_transaction_id, source_transaction_id, amount, source_expires_at, created_at
         FROM sr_point_expiration_consumptions
         WHERE account_id = :account_id
         ORDER BY id ASC
         LIMIT 1001'
    );
    $stmt->execute(['account_id' => $accountId]);

    return [
        'balance' => is_array($balance) ? $balance : [],
        'transactions' => $transactions,
        'expiration_consumptions' => sr_privacy_export_limit_rows($stmt->fetchAll(), 'expiration_consumptions', $sectionLimits, 1000),
        '_limits' => $sectionLimits,
    ];
};
