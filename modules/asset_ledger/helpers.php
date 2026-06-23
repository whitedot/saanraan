<?php

declare(strict_types=1);

function sr_ledger_create_transaction(PDO $pdo, array $config, array $data): int
{
    $balanceTable = (string) ($config['balance_table'] ?? '');
    $transactionTable = (string) ($config['transaction_table'] ?? '');
    $balanceRowError = (string) ($config['balance_row_error'] ?? 'Ledger balance row was not created.');
    $negativeBalanceError = (string) ($config['negative_balance_error'] ?? 'Ledger balance cannot be negative.');

    if (!sr_ledger_table_pair_is_allowed($balanceTable, $transactionTable)) {
        throw new InvalidArgumentException('Ledger table name is invalid.');
    }

    $accountId = (int) ($data['account_id'] ?? 0);
    $amount = (int) ($data['amount'] ?? 0);
    $transactionType = (string) ($data['transaction_type'] ?? 'adjustment');
    $reason = (string) ($data['reason'] ?? '');
    $referenceType = (string) ($data['reference_type'] ?? '');
    $referenceId = (string) ($data['reference_id'] ?? '');
    $createdByAccountId = sr_ledger_nullable_positive_int($data['created_by_account_id'] ?? null);

    if ($accountId <= 0) {
        throw new InvalidArgumentException('Account id is required.');
    }

    if ($amount === 0) {
        throw new InvalidArgumentException('Amount must not be zero.');
    }

    $now = sr_now();
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare(
            sr_ledger_insert_ignore_into_clause($pdo) . ' ' . $balanceTable . ' (account_id, balance, created_at, updated_at)
             VALUES (:account_id, 0, :created_at, :updated_at)'
        );
        $stmt->execute([
            'account_id' => $accountId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $stmt = $pdo->prepare(
            'SELECT balance FROM ' . $balanceTable . ' WHERE account_id = :account_id LIMIT 1'
            . sr_ledger_for_update_clause($pdo)
        );
        $stmt->execute(['account_id' => $accountId]);
        $balanceRow = $stmt->fetch();
        if (!is_array($balanceRow)) {
            throw new RuntimeException($balanceRowError);
        }

        $balanceAfter = (int) $balanceRow['balance'] + $amount;
        if ($balanceAfter < 0) {
            throw new RuntimeException($negativeBalanceError);
        }

        $stmt = $pdo->prepare(
            'UPDATE ' . $balanceTable . '
             SET balance = :balance, updated_at = :updated_at
             WHERE account_id = :account_id'
        );
        $stmt->execute([
            'balance' => $balanceAfter,
            'updated_at' => $now,
            'account_id' => $accountId,
        ]);

        $stmt = $pdo->prepare(
            'INSERT INTO ' . $transactionTable . '
                (account_id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_by_account_id, created_at)
             VALUES
                (:account_id, :amount, :balance_after, :transaction_type, :reason, :reference_type, :reference_id, :created_by_account_id, :created_at)'
        );
        $stmt->execute([
            'account_id' => $accountId,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'transaction_type' => $transactionType,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by_account_id' => $createdByAccountId,
            'created_at' => $now,
        ]);

        $transactionId = (int) $pdo->lastInsertId();
        if ($startedTransaction) {
            $pdo->commit();
        }

        return $transactionId;
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function sr_ledger_pdo_driver(PDO $pdo): string
{
    try {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable) {
        return '';
    }
}

function sr_ledger_insert_ignore_into_clause(PDO $pdo): string
{
    return sr_ledger_pdo_driver($pdo) === 'sqlite' ? 'INSERT OR IGNORE INTO' : 'INSERT IGNORE INTO';
}

function sr_ledger_for_update_clause(PDO $pdo): string
{
    return sr_ledger_pdo_driver($pdo) === 'sqlite' ? '' : ' FOR UPDATE';
}

function sr_asset_recovery_failures_table_exists(PDO $pdo): bool
{
    static $existsByConnection = [];

    $connectionKey = (string) spl_object_id($pdo);
    if (array_key_exists($connectionKey, $existsByConnection)) {
        return $existsByConnection[$connectionKey];
    }

    try {
        $stmt = $pdo->query('SELECT 1 FROM sr_asset_recovery_failures LIMIT 1');
        $existsByConnection[$connectionKey] = $stmt !== false;
    } catch (Throwable) {
        $existsByConnection[$connectionKey] = false;
    }

    return $existsByConnection[$connectionKey];
}

function sr_asset_recovery_reversal_links_table_exists(PDO $pdo): bool
{
    static $existsByConnection = [];

    $connectionKey = (string) spl_object_id($pdo);
    if (array_key_exists($connectionKey, $existsByConnection)) {
        return $existsByConnection[$connectionKey];
    }

    try {
        $stmt = $pdo->query('SELECT 1 FROM sr_asset_recovery_reversal_links LIMIT 1');
        $existsByConnection[$connectionKey] = $stmt !== false;
    } catch (Throwable) {
        $existsByConnection[$connectionKey] = false;
    }

    return $existsByConnection[$connectionKey];
}

function sr_asset_recovery_statuses(): array
{
    return ['open', 'recovered', 'manually_resolved', 'cancelled'];
}

function sr_asset_recovery_status_label(string $status): string
{
    return match ($status) {
        'open' => '미회수',
        'recovered' => '전액 회수',
        'manually_resolved' => '수동 해소',
        'cancelled' => '취소',
        'resolved' => '해소',
        default => $status,
    };
}

function sr_asset_recovery_status_normalize(string $status): string
{
    return $status === 'resolved' ? 'recovered' : $status;
}

function sr_asset_recovery_failure_reason_codes(): array
{
    return ['balance_low', 'recovered', 'manual_resolved', 'manual_cancelled', 'source_closed', 'legacy_backfill'];
}

function sr_asset_recovery_failure_reason_normalize(string $reason): string
{
    return in_array($reason, sr_asset_recovery_failure_reason_codes(), true) ? $reason : 'balance_low';
}

function sr_asset_recovery_event_key_valid(string $eventKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]*(?:\.[a-z0-9_]+){1,5}\z/', $eventKey) === 1 && strlen($eventKey) <= 100;
}

function sr_asset_recovery_dedupe_key(string $sourceModule, int $sourceLogId, string $reversalEventKey): string
{
    $sourceModule = strtolower(trim($sourceModule));
    if (!preg_match('/\A[a-z][a-z0-9_]{0,39}\z/', $sourceModule)) {
        throw new InvalidArgumentException('Invalid recovery source module.');
    }
    if ($sourceLogId < 1) {
        throw new InvalidArgumentException('Recovery source log id is required.');
    }
    if ($reversalEventKey === '') {
        throw new InvalidArgumentException('Recovery reversal event key is required.');
    }

    return 'source:' . $sourceModule . ':' . (string) $sourceLogId . ':rev:' . $reversalEventKey;
}

function sr_asset_recovery_context_json(array $operationContext): string
{
    $allowedKeys = [
        'operation_event_key',
        'before_status',
        'after_status',
        'actor_type',
        'route_context',
        'batch_operation_key',
        'source_action',
    ];
    $context = [];
    foreach ($allowedKeys as $key) {
        if (!array_key_exists($key, $operationContext)) {
            continue;
        }
        $value = $operationContext[$key];
        if (is_scalar($value) || $value === null) {
            $context[$key] = mb_substr((string) $value, 0, 120);
        }
    }
    $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return is_string($encoded) ? $encoded : '{}';
}

function sr_asset_recovery_failure_by_dedupe_key(PDO $pdo, string $dedupeKey): ?array
{
    if (!sr_asset_recovery_failures_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_asset_recovery_failures WHERE dedupe_key = :dedupe_key LIMIT 1');
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_asset_recovery_failure_by_dedupe_key_for_update(PDO $pdo, string $dedupeKey): ?array
{
    if (!sr_asset_recovery_failures_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_asset_recovery_failures
         WHERE dedupe_key = :dedupe_key
         LIMIT 1' . sr_ledger_for_update_clause($pdo)
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_asset_recovery_failure_by_id(PDO $pdo, int $failureId): ?array
{
    if (!sr_asset_recovery_failures_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT f.*, ma.email AS account_email, ma.display_name AS account_display_name
         FROM sr_asset_recovery_failures f
         LEFT JOIN sr_member_accounts ma ON ma.id = f.account_id
         WHERE f.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $failureId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_asset_recovery_failure_by_id_for_update(PDO $pdo, int $failureId): ?array
{
    if (!sr_asset_recovery_failures_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_asset_recovery_failures
         WHERE id = :id
         LIMIT 1' . sr_ledger_for_update_clause($pdo)
    );
    $stmt->execute(['id' => $failureId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_asset_recovery_record_failure(PDO $pdo, array $data): int
{
    if (!sr_asset_recovery_failures_table_exists($pdo)) {
        throw new RuntimeException('Asset recovery failure table is not available.');
    }

    $sourceModule = strtolower(trim((string) ($data['source_module'] ?? '')));
    $sourceLogId = (int) ($data['source_log_id'] ?? 0);
    $reversalEventKey = (string) ($data['reversal_event_key'] ?? '');
    $grantEventKey = (string) ($data['grant_event_key'] ?? '');
    if (!sr_asset_recovery_event_key_valid($grantEventKey) || !sr_asset_recovery_event_key_valid($reversalEventKey)) {
        throw new InvalidArgumentException('Invalid recovery event key.');
    }

    $attemptedAmount = max(0, (int) ($data['attempted_amount'] ?? 0));
    if ($attemptedAmount <= 0) {
        return 0;
    }
    $recoveredAmount = max(0, min($attemptedAmount, (int) ($data['recovered_amount'] ?? 0)));
    $unrecoveredAmount = max(0, $attemptedAmount - $recoveredAmount);
    $status = $unrecoveredAmount > 0 ? 'open' : 'recovered';
    $dedupeKey = sr_asset_recovery_dedupe_key($sourceModule, $sourceLogId, $reversalEventKey);
    $now = sr_now();
    $operationContext = is_array($data['operation_context'] ?? null) ? $data['operation_context'] : [];
    $operationEventKey = mb_substr((string) ($operationContext['operation_event_key'] ?? $data['operation_event_key'] ?? ''), 0, 100);
    $actorAccountIdValue = (int) ($operationContext['actor_account_id'] ?? $data['actor_account_id'] ?? 0);
    $actorAccountId = $actorAccountIdValue > 0 ? $actorAccountIdValue : null;
    $actorType = mb_substr((string) ($operationContext['actor_type'] ?? $data['actor_type'] ?? ''), 0, 30);
    $contextJson = sr_asset_recovery_context_json($operationContext);

    $params = [
        'dedupe_key' => $dedupeKey,
        'source_module' => $sourceModule,
        'source_log_id' => $sourceLogId,
        'asset_module' => mb_substr((string) ($data['asset_module'] ?? ''), 0, 20),
        'account_id' => (int) ($data['account_id'] ?? 0),
        'original_transaction_id' => max(0, (int) ($data['original_transaction_id'] ?? 0)),
        'subject_type' => mb_substr((string) ($data['subject_type'] ?? ''), 0, 80),
        'subject_id' => max(0, (int) ($data['subject_id'] ?? 0)),
        'grant_event_key' => $grantEventKey,
        'reversal_event_key' => $reversalEventKey,
        'operation_event_key' => $operationEventKey,
        'attempted_amount' => $attemptedAmount,
        'recovered_amount' => $recoveredAmount,
        'unrecovered_amount' => $unrecoveredAmount,
        'failure_reason' => sr_asset_recovery_failure_reason_normalize((string) ($data['failure_reason'] ?? 'balance_low')),
        'status' => $status,
        'actor_account_id' => $actorAccountId,
        'actor_type' => $actorType,
        'operation_context_json' => $contextJson,
        'created_at' => $now,
        'updated_at' => $now,
        'last_attempted_at' => $now,
        'resolved_at' => $status === 'recovered' ? $now : null,
    ];

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        if ($unrecoveredAmount <= 0) {
            $row = sr_asset_recovery_failure_by_dedupe_key_for_update($pdo, $dedupeKey);
            if (!is_array($row)) {
                if ($startedTransaction) {
                    $pdo->commit();
                }

                return 0;
            }

            if ((string) ($row['status'] ?? '') !== 'open') {
                if ($startedTransaction) {
                    $pdo->commit();
                }

                return (int) ($row['id'] ?? 0);
            }

            $stmt = $pdo->prepare(
                'UPDATE sr_asset_recovery_failures
                 SET original_transaction_id = CASE WHEN :original_transaction_id > 0 THEN :original_transaction_id ELSE original_transaction_id END,
                     recovered_amount = :recovered_amount,
                     unrecovered_amount = 0,
                     failure_reason = :failure_reason,
                     status = \'recovered\',
                     actor_account_id = :actor_account_id,
                     actor_type = :actor_type,
                     operation_context_json = :operation_context_json,
                     attempt_count = attempt_count + 1,
                     version = version + 1,
                     updated_at = :updated_at,
                     last_attempted_at = :last_attempted_at,
                     resolved_at = :resolved_at
                 WHERE id = :id
                   AND status = \'open\'
                   AND version = :version'
            );
            $stmt->execute([
                'original_transaction_id' => (int) $params['original_transaction_id'],
                'recovered_amount' => max((int) ($row['recovered_amount'] ?? 0), $recoveredAmount),
                'failure_reason' => (string) $params['failure_reason'],
                'actor_account_id' => $params['actor_account_id'],
                'actor_type' => (string) $params['actor_type'],
                'operation_context_json' => (string) $params['operation_context_json'],
                'updated_at' => $now,
                'last_attempted_at' => $now,
                'resolved_at' => $now,
                'id' => (int) ($row['id'] ?? 0),
                'version' => (int) ($row['version'] ?? 0),
            ]);
            if ($stmt->rowCount() < 1) {
                throw new RuntimeException('Asset recovery failure row was changed concurrently.');
            }

            if ($startedTransaction) {
                $pdo->commit();
            }

            return (int) ($row['id'] ?? 0);
        }

        $stmt = $pdo->prepare(
            sr_ledger_insert_ignore_into_clause($pdo) . ' sr_asset_recovery_failures
                (dedupe_key, source_module, source_log_id, asset_module, account_id, original_transaction_id,
                 subject_type, subject_id, grant_event_key, reversal_event_key, operation_event_key,
                 attempted_amount, recovered_amount, unrecovered_amount, failure_reason, status,
                 actor_account_id, actor_type, operation_context_json, attempt_count, version,
                 created_at, updated_at, last_attempted_at, resolved_at)
             VALUES
                (:dedupe_key, :source_module, :source_log_id, :asset_module, :account_id, :original_transaction_id,
                 :subject_type, :subject_id, :grant_event_key, :reversal_event_key, :operation_event_key,
                 :attempted_amount, :recovered_amount, :unrecovered_amount, :failure_reason, :status,
                 :actor_account_id, :actor_type, :operation_context_json, 1, 1,
                 :created_at, :updated_at, :last_attempted_at, :resolved_at)'
        );
        $stmt->execute($params);
        $inserted = $stmt->rowCount() > 0;

        $row = sr_asset_recovery_failure_by_dedupe_key_for_update($pdo, $dedupeKey);
        if (!is_array($row)) {
            throw new RuntimeException('Asset recovery failure row was not created.');
        }

        if (!$inserted) {
            if ((string) ($row['status'] ?? '') !== 'open') {
                if ($startedTransaction) {
                    $pdo->commit();
                }

                return (int) ($row['id'] ?? 0);
            }

            $updatedRecoveredAmount = max((int) ($row['recovered_amount'] ?? 0), $recoveredAmount);
            $updatedUnrecoveredAmount = max(0, (int) ($row['attempted_amount'] ?? $attemptedAmount) - $updatedRecoveredAmount);
            $updatedStatus = $updatedUnrecoveredAmount <= 0 ? 'recovered' : 'open';
            $stmt = $pdo->prepare(
                'UPDATE sr_asset_recovery_failures
                 SET original_transaction_id = CASE WHEN :original_transaction_id > 0 THEN :original_transaction_id ELSE original_transaction_id END,
                     recovered_amount = :recovered_amount,
                     unrecovered_amount = :unrecovered_amount,
                     failure_reason = :failure_reason,
                     status = :status,
                     actor_account_id = :actor_account_id,
                     actor_type = :actor_type,
                     operation_context_json = :operation_context_json,
                     attempt_count = attempt_count + 1,
                     version = version + 1,
                     updated_at = :updated_at,
                     last_attempted_at = :last_attempted_at,
                     resolved_at = CASE WHEN :resolved_status = \'recovered\' THEN :resolved_at ELSE resolved_at END
                 WHERE id = :id
                   AND status = \'open\'
                   AND version = :version'
            );
            $stmt->execute([
                'original_transaction_id' => (int) $params['original_transaction_id'],
                'recovered_amount' => $updatedRecoveredAmount,
                'unrecovered_amount' => $updatedUnrecoveredAmount,
                'failure_reason' => (string) $params['failure_reason'],
                'status' => $updatedStatus,
                'resolved_status' => $updatedStatus,
                'actor_account_id' => $params['actor_account_id'],
                'actor_type' => (string) $params['actor_type'],
                'operation_context_json' => (string) $params['operation_context_json'],
                'updated_at' => $now,
                'last_attempted_at' => $now,
                'resolved_at' => $now,
                'id' => (int) ($row['id'] ?? 0),
                'version' => (int) ($row['version'] ?? 0),
            ]);
            if ($stmt->rowCount() < 1) {
                throw new RuntimeException('Asset recovery failure row was changed concurrently.');
            }
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        return (int) ($row['id'] ?? 0);
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function sr_asset_recovery_record_reversal_link(PDO $pdo, int $failureId, string $assetModule, int $reversalTransactionId, int $recoveredAmount): void
{
    if ($failureId < 1 || $reversalTransactionId < 1 || $recoveredAmount < 1 || !sr_asset_recovery_reversal_links_table_exists($pdo)) {
        return;
    }

    $stmt = $pdo->prepare(
        sr_ledger_insert_ignore_into_clause($pdo) . ' sr_asset_recovery_reversal_links
            (failure_id, asset_module, reversal_transaction_id, recovered_amount, created_at)
         VALUES
            (:failure_id, :asset_module, :reversal_transaction_id, :recovered_amount, :created_at)'
    );
    $stmt->execute([
        'failure_id' => $failureId,
        'asset_module' => mb_substr($assetModule, 0, 20),
        'reversal_transaction_id' => $reversalTransactionId,
        'recovered_amount' => $recoveredAmount,
        'created_at' => sr_now(),
    ]);
}

function sr_asset_recovery_filters_from_request(): array
{
    $status = sr_get_string('status', 24);
    if ($status !== '' && !in_array($status, sr_asset_recovery_statuses(), true)) {
        $status = '';
    }
    $sourceModule = strtolower(trim(sr_get_string('source_module', 40)));
    if ($sourceModule !== '' && preg_match('/\A[a-z][a-z0-9_]{0,39}\z/', $sourceModule) !== 1) {
        $sourceModule = '';
    }
    $assetModule = strtolower(trim(sr_get_string('asset_module', 20)));
    if ($assetModule !== '' && preg_match('/\A[a-z][a-z0-9_]{0,19}\z/', $assetModule) !== 1) {
        $assetModule = '';
    }
    $subjectType = trim(sr_get_string('subject_type', 80));
    if ($subjectType !== '' && preg_match('/\A[a-z][a-z0-9_]*(?:\.[a-z0-9_]+){0,4}\z/', $subjectType) !== 1) {
        $subjectType = '';
    }
    $subjectIdValue = sr_get_string('subject_id', 20);

    return [
        'status' => $status,
        'source_module' => $sourceModule,
        'asset_module' => $assetModule,
        'subject_type' => $subjectType,
        'subject_id' => preg_match('/\A[1-9][0-9]*\z/', $subjectIdValue) === 1 ? (int) $subjectIdValue : 0,
        'q' => trim(sr_get_string('q', 120)),
        'created_from' => sr_get_string('created_from', 10),
        'created_to' => sr_get_string('created_to', 10),
    ];
}

function sr_asset_recovery_failure_where(array $filters, array &$params): string
{
    $conditions = ['1 = 1'];
    foreach (['status', 'source_module', 'asset_module', 'subject_type'] as $key) {
        if ((string) ($filters[$key] ?? '') !== '') {
            $conditions[] = 'f.' . $key . ' = :' . $key;
            $params[$key] = (string) $filters[$key];
        }
    }
    if ((int) ($filters['subject_id'] ?? 0) > 0) {
        $conditions[] = 'f.subject_id = :subject_id';
        $params['subject_id'] = (int) $filters['subject_id'];
    }
    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $conditions[] = "(f.account_id = :keyword_id OR f.account_id = :keyword_account_id OR f.subject_id = :keyword_id OR f.subject_type LIKE :keyword_like ESCAPE '\\\\' OR ma.email LIKE :keyword_like ESCAPE '\\\\' OR ma.display_name LIKE :keyword_like ESCAPE '\\\\')";
        $params['keyword_id'] = preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1 ? (int) $keyword : 0;
        $params['keyword_account_id'] = max(0, (int) ($filters['member_account_id'] ?? 0));
        $params['keyword_like'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
    }
    foreach (['created_from' => '>=', 'created_to' => '<='] as $key => $operator) {
        $value = (string) ($filters[$key] ?? '');
        if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value) === 1) {
            $conditions[] = 'f.created_at ' . $operator . ' :' . $key;
            $params[$key] = $key === 'created_to' ? $value . ' 23:59:59' : $value . ' 00:00:00';
        }
    }

    return implode(' AND ', $conditions);
}

function sr_asset_recovery_failure_count(PDO $pdo, array $filters): int
{
    if (!sr_asset_recovery_failures_table_exists($pdo)) {
        return 0;
    }

    $params = [];
    $where = sr_asset_recovery_failure_where($filters, $params);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS count_value
         FROM sr_asset_recovery_failures f
         LEFT JOIN sr_member_accounts ma ON ma.id = f.account_id
         WHERE ' . $where
    );
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_asset_recovery_failures(PDO $pdo, array $filters, int $limit, int $offset): array
{
    if (!sr_asset_recovery_failures_table_exists($pdo)) {
        return [];
    }

    $params = [];
    $where = sr_asset_recovery_failure_where($filters, $params);
    $stmt = $pdo->prepare(
        'SELECT f.*, ma.email AS account_email, ma.display_name AS account_display_name
         FROM sr_asset_recovery_failures f
         LEFT JOIN sr_member_accounts ma ON ma.id = f.account_id
         WHERE ' . $where . '
         ORDER BY f.updated_at DESC, f.id DESC
         LIMIT ' . max(1, min(200, $limit)) . ' OFFSET ' . max(0, $offset)
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_asset_recovery_update_manual_status(PDO $pdo, int $failureId, string $status, int $actorAccountId, string $reason): void
{
    if (!sr_asset_recovery_failures_table_exists($pdo)) {
        throw new RuntimeException('Asset recovery failure table is not available.');
    }
    if (!in_array($status, ['manually_resolved', 'cancelled'], true)) {
        throw new InvalidArgumentException('Invalid recovery failure status.');
    }
    $reason = trim($reason);
    if ($reason === '') {
        throw new InvalidArgumentException('Manual recovery status reason is required.');
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $failure = sr_asset_recovery_failure_by_id_for_update($pdo, $failureId);
        if (!is_array($failure) || (string) ($failure['status'] ?? '') !== 'open') {
            throw new RuntimeException('미회수 row가 이미 처리되었거나 찾을 수 없습니다.');
        }

        $now = sr_now();
        $contextJson = sr_asset_recovery_context_json([
            'operation_event_key' => $status === 'manually_resolved' ? 'manual_resolve' : 'manual_cancel',
            'actor_type' => 'admin',
            'route_context' => 'admin.assets.recovery_failures',
        ]);
        $stmt = $pdo->prepare(
            'UPDATE sr_asset_recovery_failures
             SET status = :status,
                 failure_reason = :failure_reason,
                 actor_account_id = :actor_account_id,
                 actor_type = \'admin\',
                 operation_context_json = :operation_context_json,
                 version = version + 1,
                 updated_at = :updated_at,
                 resolved_at = :resolved_at
             WHERE id = :id
               AND status = \'open\'
               AND version = :version'
        );
        $stmt->execute([
            'status' => $status,
            'failure_reason' => $status === 'manually_resolved' ? 'manual_resolved' : 'manual_cancelled',
            'actor_account_id' => $actorAccountId,
            'operation_context_json' => $contextJson,
            'updated_at' => $now,
            'resolved_at' => $now,
            'id' => $failureId,
            'version' => (int) ($failure['version'] ?? 0),
        ]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('미회수 row가 이미 처리되었거나 찾을 수 없습니다.');
        }

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function sr_asset_recovery_source_label(string $sourceModule): string
{
    return match ($sourceModule) {
        'community' => '커뮤니티',
        'content' => '콘텐츠',
        'quiz' => '퀴즈',
        'survey' => '설문',
        default => $sourceModule,
    };
}

function sr_asset_recovery_subject_link(array $failure): array
{
    $sourceModule = (string) ($failure['source_module'] ?? '');
    $subjectType = (string) ($failure['subject_type'] ?? '');
    $subjectId = (int) ($failure['subject_id'] ?? 0);
    if ($sourceModule === 'community' && $subjectType === 'community.post') {
        return ['url' => '/admin/community/posts?q=' . rawurlencode((string) $subjectId), 'label' => '게시글'];
    }
    if ($sourceModule === 'community' && $subjectType === 'community.comment') {
        return ['url' => '/admin/community/comments?q=' . rawurlencode((string) $subjectId), 'label' => '댓글'];
    }

    return ['url' => '', 'label' => $subjectType !== '' ? $subjectType : '대상'];
}

function sr_asset_recovery_retry(PDO $pdo, int $failureId, int $actorAccountId): array
{
    $failure = sr_asset_recovery_failure_by_id_for_update($pdo, $failureId);
    if (!is_array($failure) || (string) ($failure['status'] ?? '') !== 'open') {
        throw new RuntimeException('이미 처리된 미회수 기록입니다.');
    }

    if ((string) ($failure['source_module'] ?? '') === 'community') {
        require_once SR_ROOT . '/modules/community/helpers.php';
        $result = sr_community_reverse_asset_grant_for_operation(
            $pdo,
            (int) $failure['account_id'],
            (string) $failure['grant_event_key'],
            (string) $failure['subject_type'],
            (int) $failure['subject_id'],
            (string) $failure['reversal_event_key'],
            (string) $failure['reversal_event_key'],
            'asset.recovery.retry',
            [
                'operation_event_key' => 'manual_retry',
                'actor_account_id' => $actorAccountId,
                'actor_type' => 'admin',
                'route_context' => 'admin.assets.recovery_failures',
            ]
        );

        return is_array($result) ? $result : ['operation_allowed' => false, 'recovery_status' => 'failed'];
    }

    throw new RuntimeException('이 source 모듈은 아직 재회수 callback을 제공하지 않습니다.');
}

function sr_asset_reconciliation_targets(): array
{
    return [
        'point' => [
            'label' => '포인트',
            'balance_table' => 'sr_point_balances',
            'transaction_table' => 'sr_point_transactions',
        ],
        'reward' => [
            'label' => '적립금',
            'balance_table' => 'sr_reward_balances',
            'transaction_table' => 'sr_reward_transactions',
        ],
        'deposit' => [
            'label' => '예치금',
            'balance_table' => 'sr_deposit_balances',
            'transaction_table' => 'sr_deposit_transactions',
        ],
    ];
}

function sr_asset_reconcile_all(PDO $pdo, int $maxRows = 50, bool $enabledOnly = true): array
{
    $maxRows = max(1, min(500, $maxRows));
    $results = [];

    foreach (sr_asset_reconciliation_targets() as $moduleKey => $target) {
        if ($enabledOnly && function_exists('sr_module_enabled') && !sr_module_enabled($pdo, (string) $moduleKey)) {
            $results[(string) $moduleKey] = [
                'module_key' => (string) $moduleKey,
                'label' => (string) $target['label'],
                'status' => 'skipped',
                'message' => 'module disabled',
                'total_accounts' => 0,
                'mismatch_count' => 0,
                'mismatches' => [],
                'truncated' => false,
            ];
            continue;
        }

        try {
            $results[(string) $moduleKey] = sr_asset_reconcile_module(
                $pdo,
                (string) $moduleKey,
                (string) $target['label'],
                (string) $target['balance_table'],
                (string) $target['transaction_table'],
                $maxRows
            );
        } catch (Throwable $exception) {
            $results[(string) $moduleKey] = [
                'module_key' => (string) $moduleKey,
                'label' => (string) $target['label'],
                'status' => 'error',
                'message' => $exception->getMessage(),
                'total_accounts' => 0,
                'mismatch_count' => 0,
                'mismatches' => [],
                'truncated' => false,
            ];
        }
    }

    return $results;
}

function sr_asset_reconcile_module(PDO $pdo, string $moduleKey, string $label, string $balanceTable, string $transactionTable, int $maxRows): array
{
    sr_asset_reconcile_assert_table($pdo, $balanceTable);
    sr_asset_reconcile_assert_table($pdo, $transactionTable);

    $balances = [];
    $stmt = $pdo->query('SELECT account_id, balance FROM ' . $balanceTable);
    foreach ($stmt ?: [] as $row) {
        $accountId = (int) $row['account_id'];
        $balances[$accountId] = (int) $row['balance'];
    }

    $ledgerTotals = [];
    $transactionCounts = [];
    $stmt = $pdo->query('SELECT account_id, COALESCE(SUM(amount), 0) AS ledger_balance, COUNT(*) AS transaction_count FROM ' . $transactionTable . ' GROUP BY account_id');
    foreach ($stmt ?: [] as $row) {
        $accountId = (int) $row['account_id'];
        $ledgerTotals[$accountId] = (int) $row['ledger_balance'];
        $transactionCounts[$accountId] = (int) $row['transaction_count'];
    }

    $lastBalances = [];
    $stmt = $pdo->query(
        'SELECT t.account_id, t.balance_after
         FROM ' . $transactionTable . ' t
         INNER JOIN (
             SELECT account_id, MAX(id) AS last_id
             FROM ' . $transactionTable . '
             GROUP BY account_id
         ) latest ON latest.account_id = t.account_id AND latest.last_id = t.id'
    );
    foreach ($stmt ?: [] as $row) {
        $lastBalances[(int) $row['account_id']] = (int) $row['balance_after'];
    }

    $sequenceMismatches = sr_asset_reconcile_sequence_mismatches($pdo, $transactionTable);

    $accountIds = array_values(array_unique(array_merge(
        array_keys($balances),
        array_keys($ledgerTotals),
        array_keys($lastBalances),
        array_keys($sequenceMismatches)
    )));
    sort($accountIds, SORT_NUMERIC);

    $mismatches = [];
    foreach ($accountIds as $accountId) {
        $storedBalance = $balances[$accountId] ?? null;
        $ledgerBalance = $ledgerTotals[$accountId] ?? 0;
        $lastBalanceAfter = $lastBalances[$accountId] ?? null;
        $transactionCount = $transactionCounts[$accountId] ?? 0;
        $sequenceMismatch = $sequenceMismatches[$accountId] ?? null;
        $issues = [];

        if ($storedBalance === null) {
            $issues[] = 'missing_balance_row';
        } elseif ($storedBalance !== $ledgerBalance) {
            $issues[] = 'balance_sum_mismatch';
        }

        if ($transactionCount > 0 && $lastBalanceAfter !== $ledgerBalance) {
            $issues[] = 'last_balance_after_mismatch';
        }

        if ($transactionCount === 0 && $storedBalance !== null && $storedBalance !== 0) {
            $issues[] = 'nonzero_balance_without_transactions';
        }

        if (is_array($sequenceMismatch)) {
            $issues[] = 'balance_after_sequence_mismatch';
        }

        if ($issues !== []) {
            $mismatches[] = [
                'account_id' => $accountId,
                'stored_balance' => $storedBalance,
                'ledger_balance' => $ledgerBalance,
                'last_balance_after' => $lastBalanceAfter,
                'transaction_count' => $transactionCount,
                'sequence_mismatch_transaction_id' => is_array($sequenceMismatch) ? (int) ($sequenceMismatch['transaction_id'] ?? 0) : null,
                'sequence_expected_balance_after' => is_array($sequenceMismatch) ? (int) ($sequenceMismatch['expected_balance_after'] ?? 0) : null,
                'sequence_actual_balance_after' => is_array($sequenceMismatch) ? (int) ($sequenceMismatch['actual_balance_after'] ?? 0) : null,
                'issues' => $issues,
            ];
        }
    }

    return [
        'module_key' => $moduleKey,
        'label' => $label,
        'status' => 'checked',
        'message' => '',
        'total_accounts' => count($accountIds),
        'mismatch_count' => count($mismatches),
        'mismatches' => array_slice($mismatches, 0, $maxRows),
        'truncated' => count($mismatches) > $maxRows,
    ];
}

function sr_asset_reconcile_sequence_mismatches(PDO $pdo, string $transactionTable): array
{
    sr_asset_reconcile_assert_table($pdo, $transactionTable);

    $runningBalances = [];
    $mismatches = [];
    $stmt = $pdo->query('SELECT id, account_id, amount, balance_after FROM ' . $transactionTable . ' ORDER BY account_id ASC, id ASC');
    foreach ($stmt ?: [] as $row) {
        $accountId = (int) $row['account_id'];
        if (isset($mismatches[$accountId])) {
            continue;
        }

        $expectedBalanceAfter = (int) ($runningBalances[$accountId] ?? 0) + (int) $row['amount'];
        $actualBalanceAfter = (int) $row['balance_after'];
        if ($actualBalanceAfter !== $expectedBalanceAfter) {
            $mismatches[$accountId] = [
                'transaction_id' => (int) $row['id'],
                'expected_balance_after' => $expectedBalanceAfter,
                'actual_balance_after' => $actualBalanceAfter,
            ];
        }

        $runningBalances[$accountId] = $expectedBalanceAfter;
    }

    return $mismatches;
}

function sr_asset_reconciliation_summary(array $results): array
{
    $summary = [
        'checked' => 0,
        'skipped' => 0,
        'error' => 0,
        'mismatch_count' => 0,
        'total_accounts' => 0,
        'has_error' => false,
        'has_mismatch' => false,
    ];

    foreach ($results as $result) {
        if (!is_array($result)) {
            continue;
        }

        $status = (string) ($result['status'] ?? '');
        if (isset($summary[$status]) && in_array($status, ['checked', 'skipped', 'error'], true)) {
            $summary[$status]++;
        }

        $summary['total_accounts'] += (int) ($result['total_accounts'] ?? 0);
        $summary['mismatch_count'] += (int) ($result['mismatch_count'] ?? 0);
    }

    $summary['has_error'] = $summary['error'] > 0;
    $summary['has_mismatch'] = $summary['mismatch_count'] > 0;

    return $summary;
}

function sr_asset_reconcile_assert_table(PDO $pdo, string $tableName): void
{
    if (!sr_ledger_is_safe_table_name($tableName)) {
        throw new InvalidArgumentException('Unsafe table name: ' . $tableName);
    }

    $pdo->query('SELECT 1 FROM ' . $tableName . ' LIMIT 1');
}

function sr_asset_reconcile_nullable_int(mixed $value): string
{
    return $value === null ? 'NULL' : (string) (int) $value;
}

function sr_ledger_is_safe_table_name(string $tableName): bool
{
    return preg_match('/\Asr_[a-z0-9_]{1,120}\z/', $tableName) === 1;
}

function sr_ledger_table_pair_is_allowed(string $balanceTable, string $transactionTable): bool
{
    if (!sr_ledger_is_safe_table_name($balanceTable) || !sr_ledger_is_safe_table_name($transactionTable)) {
        return false;
    }

    $allowedPairs = [
        'sr_reward_balances' => 'sr_reward_transactions',
        'sr_deposit_balances' => 'sr_deposit_transactions',
    ];

    return isset($allowedPairs[$balanceTable]) && $allowedPairs[$balanceTable] === $transactionTable;
}

function sr_ledger_nullable_positive_int(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    $intValue = (int) $value;
    return $intValue > 0 ? $intValue : null;
}
