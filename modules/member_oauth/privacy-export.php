<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    $stmt = $pdo->prepare(
        'SELECT provider_key, provider_subject_display, email_snapshot, email_verified_snapshot,
                display_name_snapshot, linked_at, last_login_at, revoked_at
         FROM sr_member_oauth_accounts
         WHERE account_id = :account_id
         ORDER BY id ASC'
    );
    $stmt->execute(['account_id' => $accountId]);

    return [
        'oauth_accounts' => $stmt->fetchAll(),
    ];
};
