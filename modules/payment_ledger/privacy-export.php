<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    if ($accountId < 1) {
        return [
            'payment_records' => [],
            'payment_record_items' => [],
        ];
    }

    try {
        $pdo->query('SELECT 1 FROM sr_payment_records LIMIT 1');
        $stmt = $pdo->prepare(
            'SELECT id, dedupe_key, account_id, subject_module, subject_type, subject_id, payment_kind,
                    status, payable_amount, settlement_amount, settlement_currency, description,
                    snapshot_json, created_at, updated_at, cancelled_at
             FROM sr_payment_records
             WHERE account_id = :account_id
             ORDER BY id DESC
             LIMIT 1000'
        );
        $stmt->execute(['account_id' => $accountId]);
        $records = $stmt->fetchAll();
    } catch (Throwable) {
        $records = [];
    }

    $recordIds = [];
    foreach ($records as $record) {
        $recordId = (int) ($record['id'] ?? 0);
        if ($recordId > 0) {
            $recordIds[] = $recordId;
        }
    }

    $items = [];
    if ($recordIds !== []) {
        try {
            $pdo->query('SELECT 1 FROM sr_payment_record_items LIMIT 1');
            $placeholders = implode(', ', array_fill(0, count($recordIds), '?'));
            $stmt = $pdo->prepare(
                'SELECT id, payment_record_id, item_kind, owner_module, reference_type, reference_id,
                        amount, currency_code, reversible, reversal_status, snapshot_json, created_at, updated_at
                 FROM sr_payment_record_items
                 WHERE payment_record_id IN (' . $placeholders . ')
                 ORDER BY id ASC
                 LIMIT 2000'
            );
            $stmt->execute($recordIds);
            $items = $stmt->fetchAll();
        } catch (Throwable) {
            $items = [];
        }
    }

    return [
        'payment_records' => $records,
        'payment_record_items' => $items,
    ];
};
