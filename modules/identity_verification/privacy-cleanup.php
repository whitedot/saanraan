<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId, mixed $context = []): array {
    $eventType = is_array($context) ? (string) ($context['event_type'] ?? '') : (string) $context;
    $shouldAnonymize = in_array($eventType, ['anonymize', 'member.anonymized', 'member.status_anonymized', 'member.status_withdrawn'], true);
    if ($accountId < 1 || !$shouldAnonymize) {
        return [
            'identity_verification_links_revoked' => 0,
            'identity_verification_attempts_anonymized' => 0,
            'identity_verification_results_anonymized' => 0,
        ];
    }

    $now = sr_now();
    $startedTransaction = !$pdo->inTransaction();
    try {
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        $stmt = $pdo->prepare(
            'UPDATE sr_identity_verification_links
             SET revoked_at = COALESCE(revoked_at, :revoked_at)
             WHERE account_id = :account_id'
        );
        $stmt->execute([
            'revoked_at' => $now,
            'account_id' => $accountId,
        ]);
        $links = $stmt->rowCount();

        $stmt = $pdo->prepare(
            'DELETE FROM sr_identity_verification_identity_locks
             WHERE account_id = :account_id'
        );
        $stmt->execute(['account_id' => $accountId]);

        $stmt = $pdo->prepare(
            'UPDATE sr_identity_verification_attempts
             SET account_id = NULL,
                 updated_at = :updated_at
             WHERE account_id = :account_id'
        );
        $stmt->execute([
            'updated_at' => $now,
            'account_id' => $accountId,
        ]);
        $attempts = $stmt->rowCount();

        $stmt = $pdo->prepare(
            'UPDATE sr_identity_verification_results
             SET account_id = NULL
             WHERE account_id = :account_id'
        );
        $stmt->execute(['account_id' => $accountId]);
        $results = $stmt->rowCount();

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'identity_verification_links_revoked' => $links,
            'identity_verification_attempts_anonymized' => $attempts,
            'identity_verification_results_anonymized' => $results,
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
};
