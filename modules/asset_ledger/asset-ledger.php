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
