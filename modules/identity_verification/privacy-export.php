<?php

declare(strict_types=1);

require_once SR_ROOT . '/core/helpers/privacy-export.php';

return static function (PDO $pdo, int $accountId): array {
    $sectionLimits = [];
    if ($accountId < 1) {
        return [
            'identity_verification_attempts' => [],
            'identity_verification_results' => [],
            'identity_verification_links' => [],
            '_limits' => [],
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT id, verification_key, provider_key, method, purpose, subject_module, subject_type, subject_id,
                status, provider_transaction_id, requested_at, completed_at, failed_at, expires_at,
                failure_code, failure_message, created_at, updated_at
         FROM sr_identity_verification_attempts
         WHERE account_id = :account_id
         ORDER BY id DESC
         LIMIT 1001'
    );
    $stmt->execute(['account_id' => $accountId]);
    $attempts = sr_privacy_export_limit_rows($stmt->fetchAll(), 'identity_verification_attempts', $sectionLimits, 1000);

    $stmt = $pdo->prepare(
        'SELECT id, attempt_id, provider_key, provider_transaction_id,
                CASE WHEN ci_hash <> \'\' THEN 1 ELSE 0 END AS ci_recorded,
                CASE WHEN di_hash <> \'\' THEN 1 ELSE 0 END AS di_recorded,
                CASE WHEN name_hash <> \'\' THEN 1 ELSE 0 END AS name_recorded,
                CASE WHEN phone_hash <> \'\' THEN 1 ELSE 0 END AS phone_recorded,
                birth_date, gender, nationality, age_over_14, age_over_19, result_summary_json,
                verified_at, expires_at, created_at
         FROM sr_identity_verification_results
         WHERE account_id = :account_id
         ORDER BY id DESC
         LIMIT 1001'
    );
    $stmt->execute(['account_id' => $accountId]);
    $results = sr_privacy_export_limit_rows($stmt->fetchAll(), 'identity_verification_results', $sectionLimits, 1000);

    $stmt = $pdo->prepare(
        'SELECT id, result_id, purpose, linked_at, revoked_at, created_at
         FROM sr_identity_verification_links
         WHERE account_id = :account_id
         ORDER BY id DESC
         LIMIT 1001'
    );
    $stmt->execute(['account_id' => $accountId]);
    $links = sr_privacy_export_limit_rows($stmt->fetchAll(), 'identity_verification_links', $sectionLimits, 1000);

    return [
        'identity_verification_attempts' => $attempts,
        'identity_verification_results' => $results,
        'identity_verification_links' => $links,
        '_limits' => $sectionLimits,
    ];
};
