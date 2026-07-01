<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId, string $mode): array {
    if ($accountId < 1 || $mode !== 'anonymize') {
        return ['payment_records' => 0, 'payment_record_items' => 0];
    }

    $startedTransaction = !$pdo->inTransaction();
    try {
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        $itemStmt = $pdo->prepare(
            'UPDATE sr_payment_record_items
             SET reference_id = REPLACE(reference_id, :account_marker, :anonymous_marker),
                 updated_at = :updated_at
             WHERE payment_record_id IN (
                 SELECT id
                 FROM sr_payment_records
                 WHERE account_id = :account_id_for_items
             )
               AND reference_id LIKE :account_marker_like'
        );
        $updatedAt = sr_now();
        $itemStmt->execute([
            'account_marker' => ':account:' . (string) $accountId,
            'anonymous_marker' => ':account:anonymous',
            'updated_at' => $updatedAt,
            'account_id_for_items' => $accountId,
            'account_marker_like' => '%:account:' . (string) $accountId . '%',
        ]);

        $stmt = $pdo->prepare(
            'UPDATE sr_payment_records
             SET account_id = 0,
                 updated_at = :updated_at
             WHERE account_id = :account_id'
        );
        $stmt->execute([
            'updated_at' => $updatedAt,
            'account_id' => $accountId,
        ]);

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'payment_records' => $stmt->rowCount(),
            'payment_record_items' => $itemStmt->rowCount(),
        ];
    } catch (Throwable) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['payment_records' => 0, 'payment_record_items' => 0];
    }
};
