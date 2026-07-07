<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId, mixed $context = []): array {
    $eventType = is_array($context) ? (string) ($context['event_type'] ?? '') : (string) $context;
    $shouldAnonymize = in_array($eventType, ['anonymize', 'member.anonymized', 'member.status_anonymized', 'member.status_withdrawn'], true);
    if ($accountId < 1 || !$shouldAnonymize) {
        return ['payment_records' => 0, 'payment_record_items' => 0];
    }

    $accountReferencePattern = '/:account:' . preg_quote((string) $accountId, '/') . '(?=$|[:;,|])/';
    $redactAccountReferenceString = static function (string $value) use ($accountReferencePattern): string {
        return (string) preg_replace($accountReferencePattern, ':account:anonymous', $value);
    };
    $redactAccountReferences = static function (mixed $value) use (&$redactAccountReferences, $redactAccountReferenceString): mixed {
        if (is_string($value)) {
            return $redactAccountReferenceString($value);
        }
        if (!is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            $redacted[$key] = $redactAccountReferences($item);
        }

        return $redacted;
    };

    $startedTransaction = !$pdo->inTransaction();
    $savepointName = '';
    try {
        if ($startedTransaction) {
            $pdo->beginTransaction();
        } else {
            $savepointName = 'sr_payment_ledger_privacy_cleanup';
            $pdo->exec('SAVEPOINT ' . $savepointName);
        }

        $updatedAt = sr_now();
        $itemSelectStmt = $pdo->prepare(
            'SELECT i.id, i.reference_id, i.snapshot_json
             FROM sr_payment_record_items i
             INNER JOIN sr_payment_records r ON r.id = i.payment_record_id
             WHERE r.account_id = :account_id
               AND (i.reference_id LIKE :reference_account_marker_like OR i.snapshot_json LIKE :snapshot_account_marker_like)'
        );
        $accountMarkerLike = '%:account:' . (string) $accountId . '%';
        $itemSelectStmt->execute([
            'account_id' => $accountId,
            'reference_account_marker_like' => $accountMarkerLike,
            'snapshot_account_marker_like' => $accountMarkerLike,
        ]);
        $itemRows = $itemSelectStmt->fetchAll();

        $itemCount = 0;
        $itemUpdateStmt = $pdo->prepare(
            'UPDATE sr_payment_record_items
             SET reference_id = :reference_id,
                 snapshot_json = :snapshot_json,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        foreach ($itemRows as $itemRow) {
            if (!is_array($itemRow)) {
                continue;
            }

            $referenceId = (string) ($itemRow['reference_id'] ?? '');
            $nextReferenceId = $redactAccountReferenceString($referenceId);
            $snapshotJson = $itemRow['snapshot_json'] ?? null;
            $nextSnapshotJson = $snapshotJson;

            if (is_string($snapshotJson) && $snapshotJson !== '') {
                $decodedSnapshot = json_decode($snapshotJson, true);
                if (is_array($decodedSnapshot)) {
                    $redactedSnapshot = $redactAccountReferences($decodedSnapshot);
                    if ($redactedSnapshot !== $decodedSnapshot) {
                        $encodedSnapshot = json_encode($redactedSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        if (is_string($encodedSnapshot)) {
                            $nextSnapshotJson = $encodedSnapshot;
                        }
                    }
                } else {
                    $nextSnapshotJson = $redactAccountReferenceString($snapshotJson);
                }
            }

            if ($nextReferenceId === $referenceId && $nextSnapshotJson === $snapshotJson) {
                continue;
            }

            $itemUpdateStmt->execute([
                'reference_id' => $nextReferenceId,
                'snapshot_json' => $nextSnapshotJson,
                'updated_at' => $updatedAt,
                'id' => (int) ($itemRow['id'] ?? 0),
            ]);
            $itemCount += $itemUpdateStmt->rowCount();
        }

        $stmt = $pdo->prepare(
            'UPDATE sr_payment_records
             SET account_id = 0,
                 updated_at = :updated_at
             WHERE account_id = :account_id'
        );
        $stmt->execute([
            'updated_at' => $updatedAt,
            'account_id' => $accountId,
        ]);

        if ($startedTransaction) {
            $pdo->commit();
        } elseif ($savepointName !== '') {
            $pdo->exec('RELEASE SAVEPOINT ' . $savepointName);
        }

        return [
            'payment_records' => $stmt->rowCount(),
            'payment_record_items' => $itemCount,
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        } elseif ($savepointName !== '' && $pdo->inTransaction()) {
            try {
                $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepointName);
                $pdo->exec('RELEASE SAVEPOINT ' . $savepointName);
            } catch (Throwable) {
            }
        }

        throw $exception;
    }
};
