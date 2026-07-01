<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId, string $mode): array {
    if ($accountId < 1 || $mode !== 'anonymize') {
        return ['payment_records' => 0];
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE sr_payment_records
             SET account_id = 0,
                 updated_at = :updated_at
             WHERE account_id = :account_id'
        );
        $stmt->execute([
            'updated_at' => sr_now(),
            'account_id' => $accountId,
        ]);
        return ['payment_records' => $stmt->rowCount()];
    } catch (Throwable) {
        return ['payment_records' => 0];
    }
};
