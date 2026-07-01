<?php

declare(strict_types=1);

function sr_payment_ledger_tables_available(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM sr_payment_records LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_payment_record_items LIMIT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

function sr_payment_ledger_clean_key(string $value, int $maxLength = 190): string
{
    $value = trim($value);
    if ($maxLength < 1) {
        $maxLength = 190;
    }

    return substr($value, 0, $maxLength);
}

function sr_payment_ledger_clean_identifier(string $value, int $maxLength = 80, bool $allowDots = false): string
{
    $value = trim($value);
    if ($maxLength < 1) {
        $maxLength = 80;
    }
    if (strlen($value) > $maxLength) {
        return '';
    }

    $value = strtolower($value);
    $pattern = $allowDots ? '/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*\z/' : '/\A[a-z][a-z0-9_]*\z/';

    return preg_match($pattern, $value) === 1 ? $value : '';
}

function sr_payment_ledger_optional_identifier(array $data, string $key, string $default, int $maxLength, string $message): string
{
    if (!array_key_exists($key, $data)) {
        return $default;
    }

    $raw = trim((string) $data[$key]);
    if ($raw === '') {
        throw new InvalidArgumentException($message);
    }

    $value = sr_payment_ledger_clean_identifier($raw, $maxLength);
    if ($value === '') {
        throw new InvalidArgumentException($message);
    }

    return $value;
}

function sr_payment_ledger_clean_reference_key(string $value, int $maxLength = 190): string
{
    $value = trim($value);
    if ($maxLength < 1) {
        $maxLength = 190;
    }
    if ($value === '' || strlen($value) > $maxLength) {
        return '';
    }

    return $value;
}

function sr_payment_ledger_clean_module_key(string $value): string
{
    $value = sr_payment_ledger_clean_identifier($value, 40);
    if (function_exists('sr_is_safe_module_key')) {
        return sr_is_safe_module_key($value) ? $value : '';
    }

    return preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $value) === 1 ? $value : '';
}

function sr_payment_ledger_clean_currency_code(string $value): string
{
    $value = strtoupper(trim($value));
    if ($value === '') {
        return '';
    }

    return preg_match('/\A[A-Z]{3}\z/', $value) === 1 ? $value : '';
}

function sr_payment_ledger_integer_amount(mixed $value, string $message): int
{
    if (is_int($value)) {
        return $value;
    }

    if (is_string($value)) {
        $value = trim($value);
        if (preg_match('/\A-?\d+\z/', $value) === 1) {
            return (int) $value;
        }
    }

    throw new InvalidArgumentException($message);
}

function sr_payment_ledger_nonnegative_amount(mixed $value, string $message): int
{
    $amount = sr_payment_ledger_integer_amount($value, $message);
    if ($amount < 0) {
        throw new InvalidArgumentException($message);
    }

    return $amount;
}

function sr_payment_ledger_target_contracts(PDO $pdo): array
{
    if (!function_exists('sr_enabled_module_contract_files') || !function_exists('sr_load_module_contract_file')) {
        return [];
    }

    $contracts = [];
    foreach (sr_enabled_module_contract_files($pdo, 'payment-ledger-targets.php', ['payment_ledger']) as $moduleKey => $file) {
        $targets = sr_load_module_contract_file($moduleKey, $file);
        if (!is_array($targets)) {
            continue;
        }

        foreach ($targets as $target) {
            if (!is_array($target)) {
                continue;
            }

            $subjectModule = sr_payment_ledger_clean_module_key((string) ($target['subject_module'] ?? ''));
            $subjectType = sr_payment_ledger_clean_identifier((string) ($target['subject_type'] ?? ''), 80, true);
            $label = sr_payment_ledger_clean_key((string) ($target['label'] ?? ''), 80);
            if ($subjectModule === '' || $subjectType === '' || $label === '') {
                continue;
            }
            if ($subjectModule !== (string) $moduleKey) {
                continue;
            }

            $target['provider_module_key'] = (string) $moduleKey;
            $target['subject_module'] = $subjectModule;
            $target['subject_type'] = $subjectType;
            $target['label'] = $label;
            $contracts[$subjectModule][$subjectType] = $target;
        }
    }

    return $contracts;
}

function sr_payment_ledger_target_exists(PDO $pdo, string $subjectModule, string $subjectType): bool
{
    $contracts = sr_payment_ledger_target_contracts($pdo);
    return isset($contracts[$subjectModule][$subjectType]);
}

function sr_payment_ledger_json_or_null(array $value): ?string
{
    if ($value === []) {
        return null;
    }

    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($encoded) ? $encoded : null;
}

function sr_payment_ledger_item_match_key(array $item): string
{
    return implode("\x1F", [
        (string) ($item['item_kind'] ?? ''),
        (string) ($item['owner_module'] ?? ''),
        (string) ($item['reference_type'] ?? ''),
        (string) ($item['reference_id'] ?? ''),
    ]);
}

function sr_payment_ledger_normalized_item_row(array $item): array
{
    $itemKind = sr_payment_ledger_clean_identifier((string) ($item['item_kind'] ?? ''), 40);
    $ownerModule = sr_payment_ledger_clean_module_key((string) ($item['owner_module'] ?? ''));
    $referenceType = sr_payment_ledger_clean_identifier((string) ($item['reference_type'] ?? ''), 80, true);
    $referenceId = sr_payment_ledger_clean_reference_key((string) ($item['reference_id'] ?? ''), 120);
    if ($itemKind === '' || $ownerModule === '' || $referenceType === '' || $referenceId === '') {
        throw new InvalidArgumentException('결제 기록 항목 참조를 확인할 수 없습니다.');
    }
    $currencyCode = sr_payment_ledger_clean_currency_code((string) ($item['currency_code'] ?? ''));
    if ((string) ($item['currency_code'] ?? '') !== '' && $currencyCode === '') {
        throw new InvalidArgumentException('결제 기록 항목 통화 코드가 올바르지 않습니다.');
    }
    sr_payment_ledger_clean_reversal_status(
        array_key_exists('reversal_status', $item) ? (string) $item['reversal_status'] : 'none'
    );

    return [
        'item_kind' => $itemKind,
        'owner_module' => $ownerModule,
        'reference_type' => $referenceType,
        'reference_id' => $referenceId,
        'amount' => (string) sr_payment_ledger_integer_amount($item['amount'] ?? 0, '결제 기록 항목 금액은 정수여야 합니다.'),
        'currency_code' => $currencyCode,
        'reversible' => !empty($item['reversible']) ? '1' : '0',
        'snapshot_json' => sr_payment_ledger_json_or_null(is_array($item['snapshot'] ?? null) ? $item['snapshot'] : []),
    ];
}

function sr_payment_ledger_normalized_item_rows(array $items): array
{
    $rows = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $row = sr_payment_ledger_normalized_item_row($item);
        $key = sr_payment_ledger_item_match_key($row);
        if (isset($rows[$key]) && $rows[$key] !== $row) {
            throw new InvalidArgumentException('같은 결제 기록 항목 참조의 값이 서로 다릅니다.');
        }

        $rows[$key] = $row;
    }
    ksort($rows);

    return $rows;
}

function sr_payment_ledger_record_by_dedupe(PDO $pdo, string $dedupeKey): ?array
{
    if ($dedupeKey === '' || !sr_payment_ledger_tables_available($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_payment_records
         WHERE dedupe_key = :dedupe_key
         LIMIT 1'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_payment_ledger_create_record(PDO $pdo, array $data): int
{
    $result = sr_payment_ledger_create_record_result($pdo, $data);
    return (int) ($result['id'] ?? 0);
}

function sr_payment_ledger_create_record_result(PDO $pdo, array $data): array
{
    if (!sr_payment_ledger_tables_available($pdo)) {
        throw new RuntimeException('결제 기록 테이블이 준비되지 않았습니다.');
    }

    $dedupeKey = sr_payment_ledger_clean_reference_key((string) ($data['dedupe_key'] ?? ''), 190);
    $accountId = (int) ($data['account_id'] ?? 0);
    $subjectModule = sr_payment_ledger_clean_module_key((string) ($data['subject_module'] ?? ''));
    $subjectType = sr_payment_ledger_clean_identifier((string) ($data['subject_type'] ?? ''), 80, true);
    $subjectId = sr_payment_ledger_clean_reference_key((string) ($data['subject_id'] ?? ''), 120);
    if ($dedupeKey === '' || $accountId <= 0 || $subjectModule === '' || $subjectType === '' || $subjectId === '') {
        throw new InvalidArgumentException('결제 기록을 만들 대상과 중복 방지 키를 확인할 수 없습니다.');
    }
    if (!sr_payment_ledger_target_exists($pdo, $subjectModule, $subjectType)) {
        throw new InvalidArgumentException('결제 기록 대상 계약을 확인할 수 없습니다.');
    }
    $settlementCurrency = sr_payment_ledger_clean_currency_code((string) ($data['settlement_currency'] ?? ''));
    if ($settlementCurrency === '') {
        throw new InvalidArgumentException('결제 기록 통화 코드가 올바르지 않습니다.');
    }
    $payableAmount = sr_payment_ledger_nonnegative_amount($data['payable_amount'] ?? 0, '결제 전 금액은 0 이상이어야 합니다.');
    $settlementAmount = sr_payment_ledger_nonnegative_amount($data['settlement_amount'] ?? 0, '실제 결제 금액은 0 이상이어야 합니다.');
    $paymentKind = sr_payment_ledger_optional_identifier($data, 'payment_kind', 'purchase', 40, '결제 기록 종류가 올바르지 않습니다.');
    $status = sr_payment_ledger_optional_identifier($data, 'status', 'paid', 30, '결제 기록 상태가 올바르지 않습니다.');

    $existing = sr_payment_ledger_record_by_dedupe($pdo, $dedupeKey);
    if (is_array($existing)) {
        sr_payment_ledger_assert_existing_record_matches($existing, $data);
        return ['id' => (int) ($existing['id'] ?? 0), 'created' => false];
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_payment_records
            (dedupe_key, account_id, subject_module, subject_type, subject_id, payment_kind, status,
             payable_amount, settlement_amount, settlement_currency, description, snapshot_json, created_at, updated_at, cancelled_at)
         VALUES
            (:dedupe_key, :account_id, :subject_module, :subject_type, :subject_id, :payment_kind, :status,
             :payable_amount, :settlement_amount, :settlement_currency, :description, :snapshot_json, :created_at, :updated_at, NULL)'
    );
    try {
        $stmt->execute([
            'dedupe_key' => $dedupeKey,
            'account_id' => $accountId,
            'subject_module' => $subjectModule,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'payment_kind' => $paymentKind,
            'status' => $status,
            'payable_amount' => $payableAmount,
            'settlement_amount' => $settlementAmount,
            'settlement_currency' => $settlementCurrency,
            'description' => sr_payment_ledger_clean_key((string) ($data['description'] ?? ''), 255),
            'snapshot_json' => sr_payment_ledger_json_or_null(is_array($data['snapshot'] ?? null) ? $data['snapshot'] : []),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    } catch (Throwable $exception) {
        if (!sr_payment_ledger_is_duplicate_record_exception($exception)) {
            throw $exception;
        }

        $existing = sr_payment_ledger_record_by_dedupe($pdo, $dedupeKey);
        if (!is_array($existing)) {
            throw $exception;
        }

        sr_payment_ledger_assert_existing_record_matches($existing, $data);
        return ['id' => (int) ($existing['id'] ?? 0), 'created' => false];
    }

    return ['id' => (int) $pdo->lastInsertId(), 'created' => true];
}

function sr_payment_ledger_is_duplicate_record_exception(Throwable $exception): bool
{
    $code = (string) $exception->getCode();
    $message = $exception->getMessage();
    return $code === '23000'
        || str_contains($message, 'Duplicate entry')
        || str_contains($message, 'UNIQUE constraint failed')
        || str_contains($message, 'SQLSTATE[23000]');
}

function sr_payment_ledger_assert_existing_record_matches(array $existing, array $data): void
{
    $settlementCurrency = sr_payment_ledger_clean_currency_code((string) ($data['settlement_currency'] ?? ''));
    if ($settlementCurrency === '') {
        throw new InvalidArgumentException('결제 기록 통화 코드가 올바르지 않습니다.');
    }

    $checks = [
        'account_id' => (int) ($data['account_id'] ?? 0),
        'subject_module' => sr_payment_ledger_clean_module_key((string) ($data['subject_module'] ?? '')),
        'subject_type' => sr_payment_ledger_clean_identifier((string) ($data['subject_type'] ?? ''), 80, true),
        'subject_id' => sr_payment_ledger_clean_reference_key((string) ($data['subject_id'] ?? ''), 120),
    ];

    foreach ($checks as $field => $expected) {
        if ($field === 'account_id' && (int) ($existing[$field] ?? 0) === 0 && (int) $expected > 0) {
            continue;
        }
        if ((string) ($existing[$field] ?? '') !== (string) $expected) {
            throw new RuntimeException('같은 중복 방지 키의 결제 기록 대상이 기존 기록과 다릅니다.');
        }
    }

    $optionalChecks = [
        'payment_kind' => sr_payment_ledger_optional_identifier($data, 'payment_kind', 'purchase', 40, '결제 기록 종류가 올바르지 않습니다.'),
        'status' => sr_payment_ledger_optional_identifier($data, 'status', 'paid', 30, '결제 기록 상태가 올바르지 않습니다.'),
        'payable_amount' => sr_payment_ledger_nonnegative_amount($data['payable_amount'] ?? 0, '결제 전 금액은 0 이상이어야 합니다.'),
        'settlement_amount' => sr_payment_ledger_nonnegative_amount($data['settlement_amount'] ?? 0, '실제 결제 금액은 0 이상이어야 합니다.'),
        'settlement_currency' => $settlementCurrency,
    ];

    foreach ($optionalChecks as $field => $expected) {
        if (!array_key_exists($field, $data)) {
            continue;
        }
        if ($field === 'status' && (int) ($existing['account_id'] ?? 0) === 0) {
            continue;
        }
        if ((string) ($existing[$field] ?? '') !== (string) $expected) {
            throw new RuntimeException('같은 중복 방지 키의 결제 기록 값이 기존 기록과 다릅니다.');
        }
    }
}

function sr_payment_ledger_existing_item_rows(PDO $pdo, int $paymentRecordId): array
{
    $stmt = $pdo->prepare(
        'SELECT item_kind, owner_module, reference_type, reference_id, amount, currency_code, reversible, snapshot_json
         FROM sr_payment_record_items
         WHERE payment_record_id = :payment_record_id'
    );
    $stmt->execute(['payment_record_id' => $paymentRecordId]);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }

        $item = [
            'item_kind' => (string) ($row['item_kind'] ?? ''),
            'owner_module' => (string) ($row['owner_module'] ?? ''),
            'reference_type' => (string) ($row['reference_type'] ?? ''),
            'reference_id' => (string) ($row['reference_id'] ?? ''),
            'amount' => (string) ((int) ($row['amount'] ?? 0)),
            'currency_code' => (string) ($row['currency_code'] ?? ''),
            'reversible' => !empty($row['reversible']) ? '1' : '0',
            'snapshot_json' => ($row['snapshot_json'] ?? null) === null ? null : (string) $row['snapshot_json'],
        ];
        $rows[sr_payment_ledger_item_match_key($item)] = $item;
    }
    ksort($rows);

    return $rows;
}

function sr_payment_ledger_assert_existing_record_items_match(PDO $pdo, array $existing, array $expectedItems): void
{
    if ((int) ($existing['account_id'] ?? 0) === 0) {
        return;
    }

    $paymentRecordId = (int) ($existing['id'] ?? 0);
    if ($paymentRecordId <= 0) {
        throw new RuntimeException('같은 중복 방지 키의 결제 기록 항목을 확인할 수 없습니다.');
    }

    if (sr_payment_ledger_existing_item_rows($pdo, $paymentRecordId) !== $expectedItems) {
        throw new RuntimeException('같은 중복 방지 키의 결제 기록 항목이 기존 기록과 다릅니다.');
    }
}

function sr_payment_ledger_add_item(PDO $pdo, int $paymentRecordId, array $item): int
{
    if ($paymentRecordId <= 0 || !sr_payment_ledger_tables_available($pdo)) {
        throw new InvalidArgumentException('결제 기록 항목을 연결할 결제 기록을 확인할 수 없습니다.');
    }

    $normalized = sr_payment_ledger_normalized_item_row($item);

    $now = sr_now();
    $insertVerb = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
    $stmt = $pdo->prepare(
        $insertVerb . ' INTO sr_payment_record_items
            (payment_record_id, item_kind, owner_module, reference_type, reference_id, amount, currency_code,
             reversible, reversal_status, snapshot_json, created_at, updated_at)
         VALUES
            (:payment_record_id, :item_kind, :owner_module, :reference_type, :reference_id, :amount, :currency_code,
             :reversible, :reversal_status, :snapshot_json, :created_at, :updated_at)'
    );
    $stmt->execute([
        'payment_record_id' => $paymentRecordId,
        'item_kind' => $normalized['item_kind'],
        'owner_module' => $normalized['owner_module'],
        'reference_type' => $normalized['reference_type'],
        'reference_id' => $normalized['reference_id'],
        'amount' => (int) $normalized['amount'],
        'currency_code' => $normalized['currency_code'],
        'reversible' => (int) $normalized['reversible'],
        'reversal_status' => sr_payment_ledger_clean_reversal_status(
            array_key_exists('reversal_status', $item) ? (string) $item['reversal_status'] : 'none'
        ),
        'snapshot_json' => $normalized['snapshot_json'],
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    if ($stmt->rowCount() > 0) {
        return (int) $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_payment_record_items
         WHERE payment_record_id = :payment_record_id
           AND item_kind = :item_kind
           AND owner_module = :owner_module
           AND reference_type = :reference_type
           AND reference_id = :reference_id
         LIMIT 1'
    );
    $stmt->execute([
        'payment_record_id' => $paymentRecordId,
        'item_kind' => $normalized['item_kind'],
        'owner_module' => $normalized['owner_module'],
        'reference_type' => $normalized['reference_type'],
        'reference_id' => $normalized['reference_id'],
    ]);

    return (int) ($stmt->fetchColumn() ?: 0);
}

function sr_payment_ledger_record_payment(PDO $pdo, array $record, array $items): int
{
    $expectedItems = sr_payment_ledger_normalized_item_rows($items);
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $dedupeKey = sr_payment_ledger_clean_reference_key((string) ($record['dedupe_key'] ?? ''), 190);
        $existing = sr_payment_ledger_record_by_dedupe($pdo, $dedupeKey);
        if (is_array($existing)) {
            sr_payment_ledger_assert_existing_record_matches($existing, $record);
            sr_payment_ledger_assert_existing_record_items_match($pdo, $existing, $expectedItems);
            if ($startedTransaction) {
                $pdo->commit();
            }

            return (int) ($existing['id'] ?? 0);
        }

        $createResult = sr_payment_ledger_create_record_result($pdo, $record);
        $paymentRecordId = (int) ($createResult['id'] ?? 0);
        if (empty($createResult['created'])) {
            $existing = sr_payment_ledger_record_by_dedupe($pdo, $dedupeKey);
            if (is_array($existing)) {
                sr_payment_ledger_assert_existing_record_items_match($pdo, $existing, $expectedItems);
            }
            if ($startedTransaction) {
                $pdo->commit();
            }

            return $paymentRecordId;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            sr_payment_ledger_add_item($pdo, $paymentRecordId, $item);
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        return $paymentRecordId;
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function sr_payment_ledger_clean_reversal_status(string $status): string
{
    $raw = trim($status);
    if ($raw === '') {
        throw new InvalidArgumentException('결제 기록 항목 되돌림 상태가 올바르지 않습니다.');
    }

    $status = sr_payment_ledger_clean_identifier($raw, 30);
    if (in_array($status, ['none', 'pending', 'reversed', 'failed'], true)) {
        return $status;
    }

    throw new InvalidArgumentException('결제 기록 항목 되돌림 상태가 올바르지 않습니다.');
}

function sr_payment_ledger_mark_record_items_reversal_status(PDO $pdo, int $paymentRecordId, string $status, array $fromStatuses = []): int
{
    if ($paymentRecordId <= 0 || !sr_payment_ledger_tables_available($pdo)) {
        throw new InvalidArgumentException('상태를 변경할 결제 기록 항목을 확인할 수 없습니다.');
    }

    $status = sr_payment_ledger_clean_reversal_status($status);
    $where = '';
    $params = [
        'reversal_status' => $status,
        'updated_at' => sr_now(),
        'payment_record_id' => $paymentRecordId,
    ];
    $fromStatusKeys = [];
    foreach ($fromStatuses as $index => $fromStatus) {
        $fromStatus = sr_payment_ledger_clean_reversal_status((string) $fromStatus);
        $key = 'from_status_' . (int) $index;
        $params[$key] = $fromStatus;
        $fromStatusKeys[] = ':' . $key;
    }
    if ($fromStatusKeys !== []) {
        $where = ' AND reversal_status IN (' . implode(', ', $fromStatusKeys) . ')';
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_payment_record_items
         SET reversal_status = :reversal_status,
             updated_at = :updated_at
         WHERE payment_record_id = :payment_record_id
           AND reversible = 1' . $where
    );
    $stmt->execute($params);

    return $stmt->rowCount();
}

function sr_payment_ledger_normalized_item_reference(array $reference): array
{
    $itemKind = array_key_exists('item_kind', $reference)
        ? sr_payment_ledger_clean_identifier((string) $reference['item_kind'], 40)
        : '';
    $ownerModule = sr_payment_ledger_clean_module_key((string) ($reference['owner_module'] ?? ''));
    $referenceType = sr_payment_ledger_clean_identifier((string) ($reference['reference_type'] ?? ''), 80, true);
    $referenceId = sr_payment_ledger_clean_reference_key((string) ($reference['reference_id'] ?? ''), 120);
    if ($ownerModule === '' || $referenceType === '' || $referenceId === '' || (array_key_exists('item_kind', $reference) && $itemKind === '')) {
        throw new InvalidArgumentException('결제 기록 항목 참조를 확인할 수 없습니다.');
    }

    return [
        'item_kind' => $itemKind,
        'owner_module' => $ownerModule,
        'reference_type' => $referenceType,
        'reference_id' => $referenceId,
    ];
}

function sr_payment_ledger_item_reference_conditions(array $references, array &$params, string $alias = 'i'): string
{
    $normalized = [];
    foreach ($references as $reference) {
        if (!is_array($reference)) {
            continue;
        }

        $row = sr_payment_ledger_normalized_item_reference($reference);
        $key = implode("\x1F", $row);
        $normalized[$key] = $row;
    }
    if ($normalized === []) {
        return '1 = 0';
    }

    $conditions = [];
    $index = 0;
    $columnPrefix = $alias === '' ? '' : $alias . '.';
    foreach ($normalized as $reference) {
        $prefix = 'ref_' . (string) $index . '_';
        $condition = $columnPrefix . 'owner_module = :' . $prefix . 'owner_module'
            . ' AND ' . $columnPrefix . 'reference_type = :' . $prefix . 'reference_type'
            . ' AND ' . $columnPrefix . 'reference_id = :' . $prefix . 'reference_id';
        $params[$prefix . 'owner_module'] = $reference['owner_module'];
        $params[$prefix . 'reference_type'] = $reference['reference_type'];
        $params[$prefix . 'reference_id'] = $reference['reference_id'];
        if ($reference['item_kind'] !== '') {
            $condition .= ' AND ' . $columnPrefix . 'item_kind = :' . $prefix . 'item_kind';
            $params[$prefix . 'item_kind'] = $reference['item_kind'];
        }
        $conditions[] = '(' . $condition . ')';
        $index++;
    }

    return '(' . implode(' OR ', $conditions) . ')';
}

function sr_payment_ledger_refund_completed_record_ids(PDO $pdo, array $paymentRecordIds): array
{
    $ids = [];
    foreach ($paymentRecordIds as $paymentRecordId) {
        $paymentRecordId = (int) $paymentRecordId;
        if ($paymentRecordId > 0) {
            $ids[$paymentRecordId] = $paymentRecordId;
        }
    }
    if ($ids === []) {
        return [];
    }

    $refunded = [];
    foreach ($ids as $paymentRecordId) {
        $stmt = $pdo->prepare(
            "SELECT
                SUM(CASE WHEN reversible = 1 THEN 1 ELSE 0 END) AS reversible_count,
                SUM(CASE WHEN reversible = 1 AND reversal_status <> 'reversed' THEN 1 ELSE 0 END) AS open_count
             FROM sr_payment_record_items
             WHERE payment_record_id = :payment_record_id"
        );
        $stmt->execute(['payment_record_id' => $paymentRecordId]);
        $row = $stmt->fetch();
        if (!is_array($row) || (int) ($row['reversible_count'] ?? 0) < 1 || (int) ($row['open_count'] ?? 0) > 0) {
            continue;
        }

        $refunded[] = $paymentRecordId;
    }

    return $refunded;
}

function sr_payment_ledger_mark_item_references_reversed(PDO $pdo, int $accountId, array $references, string $reason = '', bool $markAllRecordItems = false): array
{
    if ($accountId <= 0 || !sr_payment_ledger_tables_available($pdo)) {
        throw new InvalidArgumentException('되돌림 처리할 결제 기록을 확인할 수 없습니다.');
    }

    $params = ['account_id' => $accountId];
    $referenceWhere = sr_payment_ledger_item_reference_conditions($references, $params);
    if ($referenceWhere === '1 = 0') {
        return ['payment_record_ids' => [], 'reversed_item_count' => 0, 'refunded_record_ids' => []];
    }

    $stmt = $pdo->prepare(
        'SELECT DISTINCT i.payment_record_id
         FROM sr_payment_record_items i
         INNER JOIN sr_payment_records r ON r.id = i.payment_record_id
         WHERE r.account_id = :account_id
           AND ' . $referenceWhere
    );
    $stmt->execute($params);

    $paymentRecordIds = [];
    foreach ($stmt->fetchAll() as $row) {
        $paymentRecordId = (int) ($row['payment_record_id'] ?? 0);
        if ($paymentRecordId > 0) {
            $paymentRecordIds[$paymentRecordId] = $paymentRecordId;
        }
    }
    if ($paymentRecordIds === []) {
        return ['payment_record_ids' => [], 'reversed_item_count' => 0, 'refunded_record_ids' => []];
    }

    $now = sr_now();
    $reversedItemCount = 0;
    foreach ($paymentRecordIds as $paymentRecordId) {
        if ($markAllRecordItems) {
            $reversedItemCount += sr_payment_ledger_mark_record_items_reversal_status($pdo, $paymentRecordId, 'reversed', ['none', 'pending', 'failed']);
            continue;
        }

        $itemParams = ['payment_record_id' => $paymentRecordId, 'updated_at' => $now];
        $itemWhere = sr_payment_ledger_item_reference_conditions($references, $itemParams, '');
        $stmt = $pdo->prepare(
            "UPDATE sr_payment_record_items
             SET reversal_status = 'reversed',
                 updated_at = :updated_at
             WHERE payment_record_id = :payment_record_id
               AND reversible = 1
               AND reversal_status IN ('none', 'pending', 'failed')
               AND " . $itemWhere
        );
        $stmt->execute($itemParams);
        $reversedItemCount += $stmt->rowCount();
    }

    $refundedRecordIds = sr_payment_ledger_refund_completed_record_ids($pdo, array_values($paymentRecordIds));
    foreach ($refundedRecordIds as $paymentRecordId) {
        $stmt = $pdo->prepare(
            "UPDATE sr_payment_records
             SET status = 'refunded',
                 description = CASE WHEN :reason = '' THEN description ELSE :reason END,
                 updated_at = :updated_at
             WHERE id = :id
               AND status NOT IN ('cancelled', 'refunded')"
        );
        $stmt->execute([
            'reason' => sr_payment_ledger_clean_key($reason, 255),
            'updated_at' => $now,
            'id' => $paymentRecordId,
        ]);
    }

    return [
        'payment_record_ids' => array_values($paymentRecordIds),
        'reversed_item_count' => $reversedItemCount,
        'refunded_record_ids' => $refundedRecordIds,
    ];
}

function sr_payment_ledger_mark_record_cancelled(PDO $pdo, int $paymentRecordId, string $reason = '', string $itemReversalStatus = 'pending'): void
{
    if ($paymentRecordId <= 0 || !sr_payment_ledger_tables_available($pdo)) {
        throw new InvalidArgumentException('취소할 결제 기록을 선택하세요.');
    }

    $itemReversalStatus = sr_payment_ledger_clean_reversal_status($itemReversalStatus);
    $now = sr_now();
    $stmt = $pdo->prepare(
        "UPDATE sr_payment_records
         SET status = 'cancelled',
             description = CASE WHEN :reason = '' THEN description ELSE :reason END,
             updated_at = :updated_at,
             cancelled_at = :cancelled_at
         WHERE id = :id
           AND status NOT IN ('cancelled', 'refunded')"
    );
    $stmt->execute([
        'reason' => sr_payment_ledger_clean_key($reason, 255),
        'updated_at' => $now,
        'cancelled_at' => $now,
        'id' => $paymentRecordId,
    ]);

    if ($stmt->rowCount() > 0) {
        sr_payment_ledger_mark_record_items_reversal_status($pdo, $paymentRecordId, $itemReversalStatus, ['none', 'failed']);
    }
}

function sr_payment_ledger_mark_cancelled(PDO $pdo, int $paymentRecordId, string $reason = ''): void
{
    sr_payment_ledger_mark_record_cancelled($pdo, $paymentRecordId, $reason);
}
