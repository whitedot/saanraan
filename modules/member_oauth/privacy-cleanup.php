<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId, array $context = []): array {
    $stmt = $pdo->prepare(
        'UPDATE sr_member_oauth_accounts
         SET revoked_at = COALESCE(revoked_at, :revoked_at),
             email_snapshot = "",
             display_name_snapshot = "",
             updated_at = :updated_at
         WHERE account_id = :account_id'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'revoked_at' => sr_now(),
        'updated_at' => sr_now(),
    ]);

    return [
        'oauth_accounts_revoked' => $stmt->rowCount(),
    ];
};
