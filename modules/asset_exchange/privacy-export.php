<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    $stmt = $pdo->prepare(
        'SELECT exchange_group_id, from_module_key, to_module_key, request_amount, deposit_amount, fee_amount, status, failure_reason, created_at
         FROM sr_asset_exchange_logs
         WHERE account_id = :account_id
         ORDER BY id DESC'
    );
    $stmt->execute(['account_id' => $accountId]);

    return [
        'asset_exchange_logs' => $stmt->fetchAll(),
    ];
};
