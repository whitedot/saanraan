<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId, array $context = []): array {
    if ($accountId < 1) {
        return ['cleaned' => false];
    }

    try {
        $pdo->query('SELECT 1 FROM sr_asset_recovery_failures LIMIT 1');
    } catch (Throwable) {
        return ['cleaned' => false, 'asset_recovery_failures_anonymized' => 0];
    }

    $dedupeSql = 'CONCAT(\'anonymized:asset_recovery:\', id)';
    try {
        if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $dedupeSql = "'anonymized:asset_recovery:' || id";
        }
    } catch (Throwable) {
        $dedupeSql = 'CONCAT(\'anonymized:asset_recovery:\', id)';
    }

    $stmt = $pdo->prepare(
        "UPDATE sr_asset_recovery_failures
         SET account_id = 0,
             dedupe_key = " . $dedupeSql . ",
             actor_account_id = CASE WHEN actor_account_id = :actor_account_id THEN NULL ELSE actor_account_id END,
             operation_context_json = '{}',
             version = version + 1,
             updated_at = :updated_at
         WHERE account_id = :account_id"
    );
    $stmt->execute([
        'actor_account_id' => $accountId,
        'account_id' => $accountId,
        'updated_at' => sr_now(),
    ]);

    $targetAccountRows = $stmt->rowCount();

    $stmt = $pdo->prepare(
        "UPDATE sr_asset_recovery_failures
         SET actor_account_id = NULL,
             operation_context_json = '{}',
             version = version + 1,
             updated_at = :updated_at
         WHERE actor_account_id = :actor_account_id"
    );
    $stmt->execute([
        'actor_account_id' => $accountId,
        'updated_at' => sr_now(),
    ]);

    return [
        'cleaned' => true,
        'asset_recovery_failures_anonymized' => $targetAccountRows,
        'asset_recovery_failure_actor_links_cleared' => $stmt->rowCount(),
    ];
};
