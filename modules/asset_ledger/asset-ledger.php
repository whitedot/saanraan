<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function sr_asset_ledger_transaction_retry_max_attempts(): int
{
    return 3;
}

function sr_asset_ledger_is_retryable_transaction_exception(Throwable $exception): bool
{
    if (!$exception instanceof PDOException) {
        return false;
    }

    $sqlState = (string) $exception->getCode();
    $driverCode = isset($exception->errorInfo[1]) ? (int) $exception->errorInfo[1] : 0;

    return $sqlState === '40001' || in_array($driverCode, [1205, 1213], true);
}

function sr_asset_ledger_retry_operation(PDO $pdo, callable $operation, string $failureMessage): array
{
    if ($pdo->inTransaction()) {
        return $operation();
    }

    $maxAttempts = sr_asset_ledger_transaction_retry_max_attempts();
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            return $operation();
        } catch (Throwable $exception) {
            if ($attempt >= $maxAttempts || !sr_asset_ledger_is_retryable_transaction_exception($exception)) {
                throw $exception;
            }
            usleep(50000 * $attempt);
        }
    }

    throw new RuntimeException($failureMessage);
}

function sr_asset_ledger_log_status_completed(): string
{
    return 'completed';
}

function sr_asset_ledger_log_status_pending(): string
{
    return 'pending';
}

function sr_asset_ledger_snapshot_schema_version(): string
{
    return 'asset_settlement_snapshot_v1';
}

function sr_asset_ledger_rounding_policy_version(): string
{
    return 'asset_settlement_rounding_v1';
}

function sr_asset_ledger_policy_set_ids_from_value(mixed $value): array
{
    $rawValues = [];
    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return [];
        }
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            if (is_array($decoded['policy_set_ids'] ?? null)) {
                $rawValues = $decoded['policy_set_ids'];
            } elseif ($decoded === array_values($decoded)) {
                $rawValues = $decoded;
            }
        } else {
            $rawValues = preg_split('/[\s,]+/', $trimmed) ?: [];
        }
    } elseif (is_array($value)) {
        $rawValues = is_array($value['policy_set_ids'] ?? null) ? $value['policy_set_ids'] : $value;
    }

    $selected = [];
    foreach ($rawValues as $rawValue) {
        if (!is_scalar($rawValue)) {
            continue;
        }
        $setId = (int) $rawValue;
        if ($setId > 0) {
            $selected[$setId] = true;
        }
    }

    return array_keys($selected);
}

function sr_asset_ledger_policy_set_selection_json(array $setIds): string
{
    $setIds = sr_asset_ledger_policy_set_ids_from_value($setIds);
    if ($setIds === []) {
        return '';
    }

    $json = json_encode(['policy_set_ids' => $setIds], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : '';
}

function sr_asset_ledger_policy_set_first_id(array $setIds): int
{
    $setIds = sr_asset_ledger_policy_set_ids_from_value($setIds);
    return (int) ($setIds[0] ?? 0);
}

function sr_asset_ledger_policy_set_options(array $policySets, callable $statusLabel): array
{
    $options = [];
    foreach ($policySets as $policySet) {
        $setId = (int) ($policySet['id'] ?? 0);
        if ($setId < 1) {
            continue;
        }
        $label = (string) ($policySet['title'] ?? $policySet['set_key'] ?? $setId);
        $status = (string) ($policySet['status'] ?? '');
        if ($status !== 'enabled') {
            $label .= ' (' . (string) $statusLabel($status) . ')';
        }
        $options[(string) $setId] = $label;
    }

    return $options;
}

function sr_asset_ledger_privacy_settlement_summary(array $row): array
{
    $snapshot = [];
    $snapshotJson = (string) ($row['purchase_power_snapshot_json'] ?? '');
    if ($snapshotJson !== '') {
        $decoded = json_decode($snapshotJson, true);
        $snapshot = is_array($decoded) ? $decoded : [];
    }

    return [
        'asset_module' => (string) ($row['asset_module'] ?? ''),
        'asset_amount' => (int) ($row['amount'] ?? 0),
        'settlement_amount' => (int) ($row['settlement_amount'] ?? 0),
        'settlement_currency' => (string) ($row['settlement_currency'] ?? ''),
        'settlement_kind' => (string) ($row['settlement_kind'] ?? ''),
        'snapshot_schema_version' => (string) ($row['snapshot_schema_version'] ?? ''),
        'rounding_policy_version' => (string) ($row['rounding_policy_version'] ?? ''),
        'purchase_power' => [
            'asset_units' => (int) ($snapshot['asset_units'] ?? 0),
            'settlement_units' => (int) ($snapshot['settlement_units'] ?? 0),
            'settlement_currency' => (string) ($snapshot['settlement_currency'] ?? ''),
            'currency_min_unit' => (int) ($snapshot['currency_min_unit'] ?? 0),
            'rounding_policy_version' => (string) ($snapshot['rounding_policy_version'] ?? ($snapshot['policy_version'] ?? '')),
        ],
    ];
}

function sr_asset_ledger_settlement_kind(string $direction, int $amount, int $settlementAmount, string $purchasePowerSnapshotJson): string
{
    if ($direction !== 'use') {
        return 'free';
    }

    if ($settlementAmount > 0) {
        return 'paid';
    }

    if ($amount === 0) {
        return 'paid_settled_zero';
    }

    return $purchasePowerSnapshotJson !== '' ? 'paid' : 'legacy_unknown';
}
