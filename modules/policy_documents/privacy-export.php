<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    $stmt = $pdo->prepare(
        'SELECT j.job_key, j.subject_snapshot, d.status, d.failure_code, d.claimed_at, d.sent_at, d.created_at
         FROM sr_policy_document_mail_deliveries d
         INNER JOIN sr_policy_document_mail_jobs j ON j.id = d.job_id
         WHERE d.account_id = :account_id
         ORDER BY d.id DESC
         LIMIT 100'
    );
    $stmt->execute(['account_id' => $accountId]);

    return [
        'policy_document_mail_deliveries' => $stmt->fetchAll(),
    ];
};
