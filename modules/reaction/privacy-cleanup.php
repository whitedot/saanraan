<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    if ($accountId < 1) {
        return ['cleaned' => false];
    }

    $stmt = $pdo->prepare(
        'DELETE FROM sr_reaction_records
         WHERE account_id = :account_id'
    );
    $stmt->execute(['account_id' => $accountId]);

    return [
        'cleaned' => true,
        'reaction_record_deleted_count' => $stmt->rowCount(),
    ];
};
