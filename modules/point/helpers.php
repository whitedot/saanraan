<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/admin/helpers/asset-group-policies.php';

function sr_point_default_settings(): array
{
    return [
        'display_name' => '포인트',
        'unit_label' => 'P',
        'manual_adjust_group_policies_json' => '',
    ];
}

function sr_point_settings(PDO $pdo): array
{
    $settings = array_merge(sr_point_default_settings(), sr_module_settings($pdo, 'point'));
    $settings['display_name'] = sr_point_clean_text((string) ($settings['display_name'] ?? '포인트'), 40);
    if ($settings['display_name'] === '') {
        $settings['display_name'] = '포인트';
    }
    $settings['unit_label'] = sr_point_clean_text((string) ($settings['unit_label'] ?? 'P'), 20);
    if ($settings['unit_label'] === '') {
        $settings['unit_label'] = 'P';
    }
    $settings['manual_adjust_group_policies_json'] = sr_admin_asset_group_policy_json_from_value($settings['manual_adjust_group_policies_json'] ?? '');

    return $settings;
}

function sr_point_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'point' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('포인트 모듈이 등록되어 있지 않습니다.');
    }

    $displayName = sr_point_clean_text((string) ($settings['display_name'] ?? ''), 40);
    $unitLabel = sr_point_clean_text((string) ($settings['unit_label'] ?? 'P'), 20);
    $manualAdjustGroupPoliciesJson = sr_admin_asset_group_policy_json_from_value($settings['manual_adjust_group_policies_json'] ?? '');
    if ($displayName === '') {
        throw new InvalidArgumentException('Point display name is required.');
    }
    if ($unitLabel === '') {
        $unitLabel = 'P';
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_module_settings
            (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    foreach ([
        ['display_name', $displayName, 'string'],
        ['unit_label', $unitLabel, 'string'],
        ['manual_adjust_group_policies_json', $manualAdjustGroupPoliciesJson, 'string'],
    ] as $row) {
        $stmt->execute([
            'module_id' => (int) $module['id'],
            'setting_key' => (string) $row[0],
            'setting_value' => (string) $row[1],
            'value_type' => (string) $row[2],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    sr_clear_module_settings_cache('point');
}

function sr_point_display_name(PDO $pdo): string
{
    $settings = sr_point_settings($pdo);
    return (string) $settings['display_name'];
}

function sr_point_unit_label(PDO $pdo): string
{
    $settings = sr_point_settings($pdo);
    return (string) $settings['unit_label'];
}

function sr_point_asset_option(PDO $pdo): array
{
    return [
        'label' => sr_point_display_name($pdo),
        'unit_label' => sr_point_unit_label($pdo),
    ];
}

function sr_point_balance(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT balance FROM sr_point_balances WHERE account_id = :account_id LIMIT 1');
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();

    return is_array($row) ? (int) $row['balance'] : 0;
}

function sr_point_create_transaction(PDO $pdo, array $data): int
{
    $accountId = (int) ($data['account_id'] ?? 0);
    $amount = (int) ($data['amount'] ?? 0);
    $transactionType = sr_point_clean_key((string) ($data['transaction_type'] ?? 'adjustment'), 40);
    $reason = sr_point_clean_text((string) ($data['reason'] ?? ''), 255);
    $referenceType = sr_point_clean_key((string) ($data['reference_type'] ?? ''), 60);
    $referenceId = sr_point_clean_reference_id((string) ($data['reference_id'] ?? ''), 120);
    $createdByAccountId = isset($data['created_by_account_id']) ? (int) $data['created_by_account_id'] : null;

    if ($accountId <= 0) {
        throw new InvalidArgumentException('Account id is required.');
    }

    if ($amount === 0) {
        throw new InvalidArgumentException('Amount must not be zero.');
    }

    if (!sr_point_transaction_type_allows_amount($transactionType, $amount)) {
        throw new InvalidArgumentException('Point transaction amount sign is invalid for type.');
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $transactionId = sr_ledger_create_transaction($pdo, [
            'balance_table' => 'sr_point_balances',
            'transaction_table' => 'sr_point_transactions',
            'balance_row_error' => 'Point balance row was not created.',
            'negative_balance_error' => 'Point balance cannot be negative.',
        ], [
            'account_id' => $accountId,
            'amount' => $amount,
            'transaction_type' => $transactionType,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by_account_id' => $createdByAccountId,
        ]);

        sr_point_notify_transaction_created($pdo, $transactionId);

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return $transactionId;
}

function sr_point_transaction_type_allows_amount(string $transactionType, int $amount): bool
{
    if ($amount === 0) {
        return false;
    }

    if (in_array($transactionType, ['grant', 'refund', 'exchange_in'], true)) {
        return $amount > 0;
    }

    if (in_array($transactionType, ['use', 'expire', 'exchange_out', 'exchange_fee'], true)) {
        return $amount < 0;
    }

    return $transactionType === 'adjustment';
}

function sr_point_clean_key(string $value, int $maxLength): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9_.-]/', '', strtolower($value));
    $value = is_string($value) ? $value : '';

    return substr($value, 0, $maxLength);
}

function sr_point_clean_reference_id(string $value, int $maxLength): string
{
    $value = trim($value);
    $value = preg_replace('/[^a-zA-Z0-9_.:-]/', '', $value);
    $value = is_string($value) ? $value : '';

    return substr($value, 0, $maxLength);
}

function sr_point_clean_text(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function sr_point_transaction_by_id(PDO $pdo, int $transactionId): ?array
{
    if ($transactionId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, account_id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_by_account_id, created_at
         FROM sr_point_transactions
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $transactionId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_point_notify_transaction_created(PDO $pdo, int $transactionId): ?int
{
    if (!sr_module_enabled($pdo, 'notification') || !is_file(SR_ROOT . '/modules/notification/helpers.php')) {
        return null;
    }

    $transaction = sr_point_transaction_by_id($pdo, $transactionId);
    if (!is_array($transaction)) {
        return null;
    }

    try {
        require_once SR_ROOT . '/modules/notification/helpers.php';
        if (!function_exists('sr_notification_create_account_event')) {
            return null;
        }

        $amount = (int) $transaction['amount'];
        $transactionType = (string) $transaction['transaction_type'];
        $eventKey = $transactionType === 'adjustment'
            ? 'transaction.adjustment.' . ($amount > 0 ? 'increase' : 'decrease')
            : 'transaction.' . $transactionType;

        return sr_notification_create_account_event($pdo, [
            'account_id' => (int) $transaction['account_id'],
            'module_key' => 'point',
            'event_key' => $eventKey,
            'created_by_account_id' => (int) ($transaction['created_by_account_id'] ?? 0),
            'metadata' => sr_point_transaction_notification_metadata($transaction, $pdo),
        ]);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'point_transaction_notification');
        return null;
    }
}

function sr_point_transaction_notification_metadata(array $transaction, ?PDO $pdo = null): array
{
    $amount = (int) ($transaction['amount'] ?? 0);
    $assetLabel = $pdo instanceof PDO ? sr_point_display_name($pdo) : '포인트';

    return [
        'transaction_id' => (int) ($transaction['id'] ?? 0),
        'asset_label' => $assetLabel,
        'amount' => number_format($amount),
        'amount_abs' => number_format(abs($amount)),
        'amount_signed' => ($amount > 0 ? '+' : '') . number_format($amount),
        'balance_after' => number_format((int) ($transaction['balance_after'] ?? 0)),
        'transaction_type' => (string) ($transaction['transaction_type'] ?? ''),
        'reason' => (string) ($transaction['reason'] ?? ''),
        'reference_type' => (string) ($transaction['reference_type'] ?? ''),
        'reference_id' => (string) ($transaction['reference_id'] ?? ''),
        'created_at' => (string) ($transaction['created_at'] ?? ''),
    ];
}
