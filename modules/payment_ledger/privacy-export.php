<?php

declare(strict_types=1);

require_once SR_ROOT . '/core/helpers/privacy-export.php';

return static function (PDO $pdo, int $accountId): array {
    $sectionLimits = [];
    if ($accountId < 1) {
        return [
            'payment_records' => [],
            'payment_record_items' => [],
            '_limits' => [],
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
             LIMIT 1001'
        );
        $stmt->execute(['account_id' => $accountId]);
        $records = sr_privacy_export_limit_rows($stmt->fetchAll(), 'payment_records', $sectionLimits, 1000);
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
                 LIMIT 2001'
            );
            $stmt->execute($recordIds);
            $items = sr_privacy_export_limit_rows($stmt->fetchAll(), 'payment_record_items', $sectionLimits, 2000);
        } catch (Throwable) {
            $items = [];
        }
    }

    return [
        'payment_records' => $records,
        'payment_record_items' => $items,
        '_limits' => $sectionLimits,
    ];
};
