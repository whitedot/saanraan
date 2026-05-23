<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    if ($accountId < 1) {
        return [
            'asset_access_logs' => [],
            'asset_action_logs' => [],
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT l.id, l.content_id, p.slug, p.title, l.account_id, l.asset_module, l.transaction_id,
                l.reference_type, l.reference_id, l.access_kind, l.charge_policy, l.amount, l.created_at
         FROM sr_content_asset_access_logs l
         LEFT JOIN sr_content_items p ON p.id = l.content_id
         WHERE l.account_id = :account_id
         ORDER BY l.id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);

    $accessLogs = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT l.id, l.content_id, p.slug, p.title, l.account_id, l.asset_module, l.transaction_id,
                l.reference_type, l.reference_id, l.action_key, l.direction, l.amount, l.created_at
         FROM sr_content_asset_action_logs l
         LEFT JOIN sr_content_items p ON p.id = l.content_id
         WHERE l.account_id = :account_id
         ORDER BY l.id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);

    return [
        'asset_access_logs' => $accessLogs,
        'asset_action_logs' => $stmt->fetchAll(),
    ];
};
