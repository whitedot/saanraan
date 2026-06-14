<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId, array $context = []): array {
    $stmt = $pdo->prepare(
        'UPDATE sr_policy_document_mail_deliveries
         SET account_id = NULL,
             updated_at = :updated_at
         WHERE account_id = :account_id'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'updated_at' => sr_now(),
    ]);

    return [
        'policy_document_mail_deliveries_anonymized' => $stmt->rowCount(),
    ];
};
