<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    if ($accountId < 1) {
        return [
            'identity_verification_attempts' => [],
            'identity_verification_results' => [],
            'identity_verification_links' => [],
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT id, verification_key, provider_key, method, purpose, subject_module, subject_type, subject_id,
                status, provider_transaction_id, requested_at, completed_at, failed_at, expires_at,
                failure_code, failure_message, created_at, updated_at
         FROM sr_identity_verification_attempts
         WHERE account_id = :account_id
         ORDER BY id DESC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $attempts = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT id, attempt_id, provider_key, provider_transaction_id, ci_hash, di_hash, name_hash, phone_hash,
                birth_date, gender, nationality, age_over_14, age_over_19, result_summary_json,
                verified_at, expires_at, created_at
         FROM sr_identity_verification_results
         WHERE account_id = :account_id
         ORDER BY id DESC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $results = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT id, result_id, purpose, linked_at, revoked_at, created_at
         FROM sr_identity_verification_links
         WHERE account_id = :account_id
         ORDER BY id DESC
         LIMIT 1000'
    );
    $stmt->execute(['account_id' => $accountId]);
    $links = $stmt->fetchAll();

    return [
        'identity_verification_attempts' => $attempts,
        'identity_verification_results' => $results,
        'identity_verification_links' => $links,
    ];
};
