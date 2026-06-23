<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    if ($accountId < 1) {
        return [
            'asset_recovery_failures' => [],
            'asset_recovery_reversal_links' => [],
        ];
    }

    try {
        $pdo->query('SELECT 1 FROM sr_asset_recovery_failures LIMIT 1');
        $stmt = $pdo->prepare(
            'SELECT id, dedupe_key, source_module, source_log_id, asset_module, account_id,
                    original_transaction_id, subject_type, subject_id, grant_event_key, reversal_event_key,
                    operation_event_key, attempted_amount, recovered_amount, unrecovered_amount,
                    failure_reason, status, actor_account_id, actor_type, operation_context_json,
                    attempt_count, version, created_at, updated_at, last_attempted_at, resolved_at
             FROM sr_asset_recovery_failures
             WHERE account_id = :account_id OR actor_account_id = :actor_account_id
             ORDER BY id ASC
             LIMIT 1000'
        );
        $stmt->execute([
            'account_id' => $accountId,
            'actor_account_id' => $accountId,
        ]);
        $failures = $stmt->fetchAll();
    } catch (Throwable) {
        $failures = [];
    }

    $failureIds = [];
    foreach ($failures as $failure) {
        $failureId = (int) ($failure['id'] ?? 0);
        if ($failureId > 0) {
            $failureIds[] = $failureId;
        }
    }

    $links = [];
    if ($failureIds !== []) {
        try {
            $pdo->query('SELECT 1 FROM sr_asset_recovery_reversal_links LIMIT 1');
            $placeholders = implode(', ', array_fill(0, count($failureIds), '?'));
            $stmt = $pdo->prepare(
                'SELECT id, failure_id, asset_module, reversal_transaction_id, recovered_amount, created_at
                 FROM sr_asset_recovery_reversal_links
                 WHERE failure_id IN (' . $placeholders . ')
                 ORDER BY id ASC
                 LIMIT 1000'
            );
            $stmt->execute($failureIds);
            $links = $stmt->fetchAll();
        } catch (Throwable) {
            $links = [];
        }
    }

    return [
        'asset_recovery_failures' => $failures,
        'asset_recovery_reversal_links' => $links,
    ];
};
