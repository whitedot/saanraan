<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    if ($accountId < 1) {
        return ['records' => []];
    }

    $stmt = $pdo->prepare(
        'SELECT r.id,
                r.target_module,
                r.target_type,
                r.target_id,
                r.reaction_key,
                COALESCE(d.label, \'\') AS current_label,
                r.created_at,
                r.updated_at
         FROM sr_reaction_records r
         LEFT JOIN sr_reaction_definitions d ON d.reaction_key = r.reaction_key
         WHERE r.account_id = :account_id
         ORDER BY r.id ASC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);

    return [
        'records' => $stmt->fetchAll(),
    ];
};
