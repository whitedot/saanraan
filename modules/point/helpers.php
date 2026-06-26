<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/helpers/common.php';

require_once SR_ROOT . '/modules/asset_ledger/helpers.php';

function sr_point_default_settings(): array
{
    return [
        'usage_enabled' => true,
        'display_name' => '포인트',
        'unit_label' => 'P',
        'default_expiration_days' => '0',
        'notifications_enabled' => false,
        'notification_cases' => sr_point_default_notification_case_settings(),
    ];
}

function sr_point_notification_cases(): array
{
    return [
        'transaction_grant' => [
            'event_key' => 'transaction.grant',
            'label' => '지급 알림',
            'description' => '포인트가 지급되었을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'transaction_refund' => [
            'event_key' => 'transaction.refund',
            'label' => '복원 알림',
            'description' => '포인트가 환불 또는 복원되었을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'transaction_exchange_in' => [
            'event_key' => 'transaction.exchange_in',
            'label' => '환전 입금 알림',
            'description' => '환전으로 포인트가 입금되었을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'transaction_use' => [
            'event_key' => 'transaction.use',
            'label' => '사용 알림',
            'description' => '포인트가 사용되었을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'transaction_exchange_out' => [
            'event_key' => 'transaction.exchange_out',
            'label' => '환전 출금 알림',
            'description' => '환전으로 포인트가 출금되었을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'transaction_exchange_fee' => [
            'event_key' => 'transaction.exchange_fee',
            'label' => '환전 수수료 알림',
            'description' => '환전 수수료로 포인트가 차감되었을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'transaction_expire' => [
            'event_key' => 'transaction.expire',
            'label' => '만료 알림',
            'description' => '포인트가 만료되었을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'transaction_adjustment_increase' => [
            'event_key' => 'transaction.adjustment.increase',
            'label' => '증가 조정 알림',
            'description' => '관리자 조정 등으로 포인트가 증가했을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'transaction_adjustment_decrease' => [
            'event_key' => 'transaction.adjustment.decrease',
            'label' => '감소 조정 알림',
            'description' => '관리자 조정 등으로 포인트가 감소했을 때 보냅니다.',
            'default_enabled' => true,
        ],
    ];
}

function sr_point_notification_case_key_for_event(string $eventKey): string
{
    foreach (sr_point_notification_cases() as $caseKey => $case) {
        if ((string) ($case['event_key'] ?? '') === $eventKey) {
            return (string) $caseKey;
        }
    }

    return '';
}

function sr_point_default_notification_case_settings(): array
{
    $settings = [];
    foreach (sr_point_notification_cases() as $caseKey => $case) {
        $settings[(string) $caseKey] = [
            'event_key' => (string) ($case['event_key'] ?? ''),
            'enabled' => !empty($case['default_enabled']),
            'channels' => ['site'],
        ];
    }

    return $settings;
}

function sr_point_account_notification_channel_keys(): array
{
    return ['site', 'email', 'telegram_bot'];
}

function sr_point_notification_channels_from_value(mixed $value): array
{
    $rawValues = is_array($value) ? $value : json_decode((string) $value, true);
    if (!is_array($rawValues)) {
        $rawValues = ['site'];
    }

    $allowed = sr_point_account_notification_channel_keys();
    $channels = [];
    foreach ($rawValues as $channel) {
        if (is_string($channel) && in_array($channel, $allowed, true)) {
            $channels[$channel] = $channel;
        }
    }

    return $channels === [] ? ['site'] : array_values($channels);
}

function sr_point_notification_channel_options(PDO $pdo): array
{
    $channels = ['site'];
    if (sr_point_notification_event_function($pdo) !== '') {
        if (function_exists('sr_notification_create_channels')) {
            $channels = array_merge($channels, sr_notification_create_channels($pdo));
        }
        if (function_exists('sr_notification_member_external_channel_keys')
            && function_exists('sr_notification_member_external_provider_is_ready')
            && function_exists('sr_notification_settings')
        ) {
            $notificationSettings = sr_notification_settings($pdo);
            foreach (sr_notification_member_external_channel_keys() as $channel) {
                if (sr_notification_member_external_provider_is_ready($channel, $notificationSettings)) {
                    $channels[] = $channel;
                }
            }
        }
    }

    $allowed = sr_point_account_notification_channel_keys();
    $options = [];
    foreach ($channels as $channel) {
        if (is_string($channel) && in_array($channel, $allowed, true)) {
            $options[$channel] = $channel;
        }
    }

    return $options === [] ? ['site'] : array_values($options);
}

function sr_point_notification_case_settings_from_value(mixed $value): array
{
    $rawSettings = is_array($value) ? $value : json_decode((string) $value, true);
    if (!is_array($rawSettings)) {
        $rawSettings = [];
    }

    $caseKeyByEventKey = [];
    foreach (sr_point_notification_cases() as $caseKey => $case) {
        $caseKeyByEventKey[(string) ($case['event_key'] ?? '')] = (string) $caseKey;
    }

    $normalized = sr_point_default_notification_case_settings();
    foreach ($rawSettings as $rawCaseKey => $rawCaseSettings) {
        $caseKey = (string) $rawCaseKey;
        if (!isset($normalized[$caseKey])) {
            $caseKey = $caseKeyByEventKey[$caseKey] ?? '';
        }
        if ($caseKey === '' || !isset($normalized[$caseKey]) || !is_array($rawCaseSettings)) {
            continue;
        }

        if (array_key_exists('enabled', $rawCaseSettings)) {
            $normalized[$caseKey]['enabled'] = sr_truthy($rawCaseSettings['enabled']);
        }
        if (array_key_exists('channels', $rawCaseSettings)) {
            $normalized[$caseKey]['channels'] = sr_point_notification_channels_from_value($rawCaseSettings['channels']);
        }
    }

    return $normalized;
}

function sr_point_notification_setting_for_event(array $settings, string $eventKey): ?array
{
    $caseKey = sr_point_notification_case_key_for_event($eventKey);
    if ($caseKey === '') {
        return null;
    }

    if (array_key_exists('notifications_enabled', $settings) && !sr_truthy($settings['notifications_enabled'])) {
        return [
            'event_key' => $eventKey,
            'enabled' => false,
            'channels' => ['site'],
        ];
    }

    $caseSettings = sr_point_notification_case_settings_from_value($settings['notification_cases'] ?? []);
    return isset($caseSettings[$caseKey]) && is_array($caseSettings[$caseKey]) ? $caseSettings[$caseKey] : null;
}

function sr_point_relative_time_label(string $dateTime): string
{
    return sr_relative_time_label($dateTime);
}

function sr_point_time_html(?string $value, string $emptyText = ''): string
{
    return sr_relative_time_html($value, $emptyText);
}

function sr_point_settings(PDO $pdo): array
{
    $settings = array_merge(sr_point_default_settings(), sr_module_settings($pdo, 'point'));
    $settings['usage_enabled'] = sr_point_truthy($settings['usage_enabled'] ?? true);
    $settings['display_name'] = sr_point_clean_text((string) ($settings['display_name'] ?? '포인트'), 40);
    if ($settings['display_name'] === '') {
        $settings['display_name'] = '포인트';
    }
    $settings['unit_label'] = sr_point_clean_text((string) ($settings['unit_label'] ?? 'P'), 20);
    if ($settings['unit_label'] === '') {
        $settings['unit_label'] = 'P';
    }
    $settings['default_expiration_days'] = (string) sr_point_normalize_expiration_days($settings['default_expiration_days'] ?? 0);
    $settings['notifications_enabled'] = sr_point_truthy($settings['notifications_enabled'] ?? false);
    $settings['notification_cases'] = sr_point_notification_case_settings_from_value($settings['notification_cases'] ?? []);
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
    $defaultExpirationDays = sr_point_normalize_expiration_days($settings['default_expiration_days'] ?? 0);
    $usageEnabled = array_key_exists('usage_enabled', $settings)
        ? sr_point_truthy($settings['usage_enabled'])
        : sr_point_usage_enabled($pdo);
    $notificationsEnabled = array_key_exists('notifications_enabled', $settings)
        ? sr_point_truthy($settings['notifications_enabled'])
        : sr_point_truthy(sr_point_settings($pdo)['notifications_enabled'] ?? false);
    if ($displayName === '') {
        throw new InvalidArgumentException('Point display name is required.');
    }
    if ($unitLabel === '') {
        $unitLabel = 'P';
    }
    $notificationCases = array_key_exists('notification_cases', $settings)
        ? sr_point_notification_case_settings_from_value($settings['notification_cases'])
        : sr_point_notification_case_settings_from_value(sr_point_settings($pdo)['notification_cases'] ?? []);
    $notificationCasesJson = json_encode($notificationCases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($notificationCasesJson)) {
        $notificationCasesJson = json_encode(sr_point_default_notification_case_settings(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
        ['usage_enabled', $usageEnabled ? '1' : '0', 'bool'],
        ['display_name', $displayName, 'string'],
        ['unit_label', $unitLabel, 'string'],
        ['default_expiration_days', (string) $defaultExpirationDays, 'integer'],
        ['notifications_enabled', $notificationsEnabled ? '1' : '0', 'bool'],
        ['notification_cases', (string) $notificationCasesJson, 'json'],
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

function sr_point_truthy(mixed $value): bool
{
    return sr_truthy($value);
}

function sr_point_usage_enabled(PDO $pdo): bool
{
    try {
        $settings = sr_point_settings($pdo);
    } catch (PDOException) {
        return true;
    }

    return !empty($settings['usage_enabled']);
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

function sr_point_default_expiration_days(PDO $pdo): int
{
    $settings = sr_point_settings($pdo);
    return sr_point_normalize_expiration_days($settings['default_expiration_days'] ?? 0);
}

function sr_point_refund_expiration_policy_options(): array
{
    return ['original', 'reset'];
}

function sr_point_normalize_refund_expiration_policy(mixed $value): string
{
    $value = is_string($value) ? trim($value) : '';
    return in_array($value, sr_point_refund_expiration_policy_options(), true) ? $value : 'original';
}

function sr_point_normalize_expiration_days(mixed $value): int
{
    if (is_string($value)) {
        $value = trim($value);
    }
    if ($value === '' || $value === null) {
        return 0;
    }
    if (is_string($value) && preg_match('/\A\d+\z/', $value) !== 1) {
        return 0;
    }

    $days = (int) $value;
    if ($days < 0) {
        return 0;
    }
    if ($days > 3650) {
        return 3650;
    }

    return $days;
}

function sr_point_transaction_expires_at(PDO $pdo, array $data): ?string
{
    $amount = (int) ($data['amount'] ?? 0);
    $transactionType = (string) ($data['transaction_type'] ?? '');
    if ($amount <= 0 || !in_array($transactionType, ['grant', 'refund'], true)) {
        return null;
    }

    $postedExpiresAt = sr_point_normalize_expires_at($data['expires_at'] ?? null);
    if ($postedExpiresAt !== null) {
        return $postedExpiresAt;
    }

    if ($transactionType === 'refund') {
        return sr_point_refund_transaction_expires_at($pdo, $data);
    }

    $days = sr_point_default_expiration_days($pdo);
    if ($days <= 0) {
        return null;
    }

    $createdAt = (string) ($data['created_at'] ?? sr_now());
    $timestamp = strtotime($createdAt . ' +' . $days . ' days');
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function sr_point_refund_transaction_expires_at(PDO $pdo, array $data): ?string
{
    $createdAt = (string) ($data['created_at'] ?? sr_now());
    $amount = (int) ($data['amount'] ?? 0);
    $referenceType = (string) ($data['reference_type'] ?? '');
    $referenceId = (string) ($data['reference_id'] ?? '');

    $refundExpirationPolicy = sr_point_normalize_refund_expiration_policy($data['refund_expiration_policy'] ?? 'original');
    if ($refundExpirationPolicy === 'original') {
        $originalExpiresAt = sr_point_refund_original_expires_at($pdo, $referenceType, $referenceId, $amount);
        if ($originalExpiresAt !== null) {
            return $originalExpiresAt;
        }
    }

    $days = sr_point_default_expiration_days($pdo);
    if ($days <= 0) {
        return null;
    }

    $timestamp = strtotime($createdAt . ' +' . $days . ' days');
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function sr_point_refund_original_expires_at(PDO $pdo, string $referenceType, string $referenceId, int $amount = 0): ?string
{
    if ($referenceType !== 'refund' || preg_match('/\Apoint_transaction:([0-9]+)\z/', $referenceId, $matches) !== 1) {
        return null;
    }

    $transactionId = (int) $matches[1];
    $stmt = $pdo->prepare('SELECT id, amount, expires_at FROM sr_point_transactions WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $transactionId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    if ((string) ($row['expires_at'] ?? '') !== '') {
        return (string) $row['expires_at'];
    }

    if ((int) ($row['amount'] ?? 0) >= 0) {
        return null;
    }

    return sr_point_refund_consumed_expires_at($pdo, $transactionId, max(0, $amount));
}

function sr_point_refund_consumed_expires_at(PDO $pdo, int $consumeTransactionId, int $amount = 0): ?string
{
    if ($consumeTransactionId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT source_expires_at, amount
         FROM sr_point_expiration_consumptions
         WHERE consume_transaction_id = :consume_transaction_id
         ORDER BY source_expires_at ASC, id ASC'
    );
    try {
        $stmt->execute(['consume_transaction_id' => $consumeTransactionId]);
    } catch (Throwable $exception) {
        return null;
    }

    foreach ($stmt->fetchAll() as $row) {
        $sourceExpiresAt = (string) ($row['source_expires_at'] ?? '');
        if ($sourceExpiresAt === '') {
            continue;
        }
        $rowAmount = max(0, (int) ($row['amount'] ?? 0));
        if ($rowAmount <= 0) {
            continue;
        }

        return $sourceExpiresAt;
    }

    return null;
}

function sr_point_refund_expiration_allocations(PDO $pdo, array $data, ?array $referenced = null, int $alreadyRefundedAmount = 0): array
{
    $amount = max(0, (int) ($data['amount'] ?? 0));
    $accountId = (int) ($data['account_id'] ?? 0);
    if ($amount <= 0 || sr_point_normalize_refund_expiration_policy($data['refund_expiration_policy'] ?? 'original') !== 'original') {
        return [];
    }

    $referenceType = (string) ($data['reference_type'] ?? '');
    $referenceId = (string) ($data['reference_id'] ?? '');
    if ($referenceType !== 'refund' || preg_match('/\Apoint_transaction:([0-9]+)\z/', $referenceId, $matches) !== 1) {
        return [];
    }

    $referencedTransactionId = (int) $matches[1];
    if ($referenced === null) {
        $lockClause = $pdo->inTransaction() ? sr_ledger_for_update_clause($pdo) : '';
        $stmt = $pdo->prepare('SELECT id, amount, expires_at FROM sr_point_transactions WHERE id = :id LIMIT 1' . $lockClause);
        $stmt->execute(['id' => $referencedTransactionId]);
        $referenced = $stmt->fetch();
    }
    if (!is_array($referenced)) {
        return [];
    }

    $referencedExpiresAt = (string) ($referenced['expires_at'] ?? '');
    if ($referencedExpiresAt !== '') {
        return [['amount' => $amount, 'expires_at' => $referencedExpiresAt]];
    }

    if ((int) ($referenced['amount'] ?? 0) >= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT source_expires_at, amount
         FROM sr_point_expiration_consumptions
         WHERE consume_transaction_id = :consume_transaction_id
         ORDER BY source_expires_at ASC, id ASC' . ($pdo->inTransaction() ? sr_ledger_for_update_clause($pdo) : '')
    );
    try {
        $stmt->execute(['consume_transaction_id' => $referencedTransactionId]);
    } catch (Throwable $exception) {
        return [];
    }

    $remaining = $amount;
    $skipAmount = max(0, $alreadyRefundedAmount);
    if ($skipAmount === 0 && $accountId > 0) {
        $skipAmount = sr_point_refunded_amount_for_reference($pdo, $accountId, $referenceId);
    }
    $allocations = [];
    foreach ($stmt->fetchAll() as $row) {
        if ($remaining <= 0) {
            break;
        }

        $sourceExpiresAt = (string) ($row['source_expires_at'] ?? '');
        $sourceAmount = max(0, (int) ($row['amount'] ?? 0));
        if ($sourceExpiresAt === '' || $sourceAmount <= 0) {
            continue;
        }
        if ($skipAmount >= $sourceAmount) {
            $skipAmount -= $sourceAmount;
            continue;
        }
        if ($skipAmount > 0) {
            $sourceAmount -= $skipAmount;
            $skipAmount = 0;
        }

        $allocatedAmount = min($sourceAmount, $remaining);
        $lastIndex = count($allocations) - 1;
        if ($lastIndex >= 0 && (string) $allocations[$lastIndex]['expires_at'] === $sourceExpiresAt) {
            $allocations[$lastIndex]['amount'] = (int) $allocations[$lastIndex]['amount'] + $allocatedAmount;
        } else {
            $allocations[] = ['amount' => $allocatedAmount, 'expires_at' => $sourceExpiresAt];
        }
        $remaining -= $allocatedAmount;
    }

    return $allocations;
}

function sr_point_refunded_amount_for_reference(PDO $pdo, int $accountId, string $referenceId): int
{
    if ($accountId <= 0 || $referenceId === '') {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0) AS refunded_amount
         FROM sr_point_transactions
         WHERE account_id = :account_id
           AND transaction_type = \'refund\'
           AND reference_type = \'refund\'
           AND reference_id = :reference_id
           AND amount > 0'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'reference_id' => $referenceId,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? max(0, (int) ($row['refunded_amount'] ?? 0)) : 0;
}

function sr_point_refunded_amount_for_reference_locked(PDO $pdo, int $accountId, string $referenceId): int
{
    if ($accountId <= 0 || $referenceId === '') {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT amount
         FROM sr_point_transactions
         WHERE account_id = :account_id
           AND transaction_type = \'refund\'
           AND reference_type = \'refund\'
           AND reference_id = :reference_id
           AND amount > 0'
         . sr_ledger_for_update_clause($pdo)
    );
    $stmt->execute([
        'account_id' => $accountId,
        'reference_id' => $referenceId,
    ]);

    $refundedAmount = 0;
    foreach ($stmt->fetchAll() as $row) {
        $refundedAmount += max(0, (int) ($row['amount'] ?? 0));
    }

    return $refundedAmount;
}

function sr_point_normalize_expires_at(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) === 1) {
        $value .= ' 23:59:59';
    }
    if (preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $value) !== 1) {
        return null;
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function sr_point_admin_adjustment_once_limit(): int
{
    return 10000000;
}

function sr_point_admin_adjustment_daily_limit(): int
{
    return 10000000;
}

function sr_point_admin_adjustment_approval_threshold(): int
{
    return 1000000;
}

function sr_point_validate_admin_adjustment_limit(PDO $pdo, array $runtimeConfig, int $adminAccountId, string $permissionPath, int $amount, string $approvalIdentifier = '', string $approvalNote = ''): array
{
    $absoluteAmount = abs($amount);
    if ($absoluteAmount > sr_point_admin_adjustment_once_limit()) {
        return ['error' => '포인트 관리자 조정 금액이 1회 상한을 초과했습니다.', 'approval_account_id' => 0];
    }

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(ABS(amount)), 0) AS total_amount
         FROM sr_point_transactions
         WHERE created_by_account_id = :admin_account_id
           AND created_at >= :started_at
           AND transaction_type IN ('adjustment', 'grant', 'use', 'refund', 'expire')"
    );
    $stmt->execute([
        'admin_account_id' => $adminAccountId,
        'started_at' => date('Y-m-d 00:00:00'),
    ]);
    $row = $stmt->fetch();
    $usedAmount = is_array($row) ? (int) ($row['total_amount'] ?? 0) : 0;

    if ($usedAmount + $absoluteAmount > sr_point_admin_adjustment_daily_limit()) {
        return ['error' => '포인트 관리자 조정 금액이 일일 상한을 초과했습니다.', 'approval_account_id' => 0];
    }

    if ($absoluteAmount <= sr_point_admin_adjustment_approval_threshold()) {
        return ['error' => null, 'approval_account_id' => 0];
    }

    $approvalAccountId = sr_admin_member_account_id_from_identifier($pdo, $runtimeConfig, $approvalIdentifier);
    if ($approvalAccountId <= 0 || sr_point_clean_text($approvalNote, 255) === '') {
        return ['error' => '대액 포인트 조정 기록을 만들 대상 회원과 사유가 필요합니다.', 'approval_account_id' => 0];
    }

    return ['error' => null, 'approval_account_id' => $approvalAccountId];
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
    if (!sr_point_usage_enabled($pdo)) {
        throw new RuntimeException('포인트를 사용하지 않도록 설정되어 있습니다.');
    }

    $accountId = (int) ($data['account_id'] ?? 0);
    $amount = (int) ($data['amount'] ?? 0);
    $transactionType = sr_point_clean_key((string) ($data['transaction_type'] ?? 'adjustment'), 40);
    $reason = sr_point_clean_text((string) ($data['reason'] ?? ''), 255);
    $referenceType = sr_point_clean_key((string) ($data['reference_type'] ?? ''), 60);
    $referenceId = sr_point_clean_reference_id((string) ($data['reference_id'] ?? ''), 120);
    $createdByAccountId = isset($data['created_by_account_id']) ? (int) $data['created_by_account_id'] : null;
    $skipExpirationConsumption = !empty($data['skip_expiration_consumption']);

    if ($accountId <= 0) {
        throw new InvalidArgumentException('Account id is required.');
    }

    if ($amount === 0) {
        throw new InvalidArgumentException('Amount must not be zero.');
    }

    if (!sr_point_transaction_type_allows_amount($transactionType, $amount)) {
        throw new InvalidArgumentException('Point transaction amount sign is invalid for type.');
    }
    if ($transactionType === 'refund' && ($referenceType !== 'refund' || preg_match('/\Apoint_transaction:([0-9]+)\z/', $referenceId) !== 1)) {
        throw new InvalidArgumentException('Point refund reference is required.');
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $now = sr_now();
        if ($transactionType !== 'expire') {
            sr_point_expire_due_account_transactions($pdo, $accountId, 100, $now);
        }

        $expiresAt = sr_point_transaction_expires_at($pdo, [
            'transaction_type' => $transactionType,
            'amount' => $amount,
            'expires_at' => $data['expires_at'] ?? null,
            'created_at' => $now,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'refund_expiration_policy' => $data['refund_expiration_policy'] ?? 'original',
        ]);
        $expiresRemaining = $expiresAt !== null ? $amount : 0;

        $transactionId = sr_point_insert_ledger_transaction($pdo, [
            'account_id' => $accountId,
            'amount' => $amount,
            'transaction_type' => $transactionType,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by_account_id' => $createdByAccountId,
            'created_at' => $now,
            'expires_at' => $expiresAt,
            'expires_remaining' => $expiresRemaining,
        ]);

        if ($amount < 0 && !$skipExpirationConsumption) {
            sr_point_consume_expiring_grants($pdo, $accountId, abs($amount), $transactionId, $now);
        }
        if ($amount > 0 && $expiresAt !== null && strtotime($expiresAt) !== false && strtotime($expiresAt) <= strtotime($now)) {
            sr_point_expire_grant_transaction($pdo, $transactionId, $now);
        }

        if ($startedTransaction) {
            $pdo->commit();
            sr_point_notify_transaction_created($pdo, $transactionId);
        } else {
            sr_point_defer_transaction_notification($pdo, $transactionId);
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return $transactionId;
}

function sr_point_create_refund_transactions(PDO $pdo, array $data): array
{
    $amount = (int) ($data['amount'] ?? 0);
    $transactionType = sr_point_clean_key((string) ($data['transaction_type'] ?? 'refund'), 40);
    if ($transactionType !== 'refund' || $amount <= 0) {
        return [sr_point_create_transaction($pdo, $data)];
    }

    $accountId = (int) ($data['account_id'] ?? 0);
    $referenceType = (string) ($data['reference_type'] ?? '');
    $referenceId = (string) ($data['reference_id'] ?? '');
    $referencedTransactionId = 0;
    if ($referenceType === 'refund' && preg_match('/\Apoint_transaction:([0-9]+)\z/', $referenceId, $matches) === 1) {
        $referencedTransactionId = (int) $matches[1];
    }

    if ($referencedTransactionId <= 0) {
        throw new InvalidArgumentException('Point refund reference is required.');
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id, amount, transaction_type, expires_at
             FROM sr_point_transactions
             WHERE id = :id
               AND account_id = :account_id
             LIMIT 1'
             . sr_ledger_for_update_clause($pdo)
        );
        $stmt->execute([
            'id' => $referencedTransactionId,
            'account_id' => $accountId,
        ]);
        $referenced = $stmt->fetch();
        if (!is_array($referenced)) {
            throw new RuntimeException('Point refund original transaction not found.');
        }
        if ((string) ($referenced['transaction_type'] ?? '') === 'refund') {
            throw new RuntimeException('Point refund transaction cannot be refunded.');
        }
        if ((int) ($referenced['amount'] ?? 0) >= 0) {
            throw new RuntimeException('Point refund original transaction must be negative.');
        }

        $alreadyRefundedAmount = sr_point_refunded_amount_for_reference_locked($pdo, $accountId, $referenceId);
        $refundableAmount = abs((int) ($referenced['amount'] ?? 0)) - $alreadyRefundedAmount;
        if ($amount > max(0, $refundableAmount)) {
            throw new RuntimeException('Point refund amount exceeds remaining reference amount.');
        }

        $allocations = sr_point_refund_expiration_allocations($pdo, $data, $referenced, $alreadyRefundedAmount);
        if ($allocations === []) {
            $transactionId = sr_point_create_transaction($pdo, $data);
            if ($startedTransaction) {
                $pdo->commit();
            }

            return [$transactionId];
        }

        $transactionIds = [];
        $remaining = $amount;
        foreach ($allocations as $allocation) {
            if ($remaining <= 0) {
                break;
            }

            $allocationAmount = min(max(0, (int) ($allocation['amount'] ?? 0)), $remaining);
            $allocationExpiresAt = (string) ($allocation['expires_at'] ?? '');
            if ($allocationAmount <= 0 || $allocationExpiresAt === '') {
                continue;
            }

            $transactionData = $data;
            $transactionData['amount'] = $allocationAmount;
            $transactionData['expires_at'] = $allocationExpiresAt;
            $transactionIds[] = sr_point_create_transaction($pdo, $transactionData);
            $remaining -= $allocationAmount;
        }

        if ($remaining > 0) {
            $transactionData = $data;
            $transactionData['amount'] = $remaining;
            $transactionData['refund_expiration_policy'] = 'reset';
            unset($transactionData['expires_at']);
            $transactionIds[] = sr_point_create_transaction($pdo, $transactionData);
        }

        if ($transactionIds === []) {
            $transactionIds[] = sr_point_create_transaction($pdo, $data);
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        return $transactionIds;
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function sr_point_insert_ledger_transaction(PDO $pdo, array $data): int
{
    $accountId = (int) ($data['account_id'] ?? 0);
    $amount = (int) ($data['amount'] ?? 0);
    $transactionType = (string) ($data['transaction_type'] ?? 'adjustment');
    $reason = (string) ($data['reason'] ?? '');
    $referenceType = (string) ($data['reference_type'] ?? '');
    $referenceId = (string) ($data['reference_id'] ?? '');
    $createdByAccountId = sr_ledger_nullable_positive_int($data['created_by_account_id'] ?? null);
    $createdAt = (string) ($data['created_at'] ?? sr_now());
    $expiresAt = $data['expires_at'] ?? null;
    $expiresRemaining = max(0, (int) ($data['expires_remaining'] ?? 0));

    $stmt = $pdo->prepare(
        sr_ledger_insert_ignore_into_clause($pdo) . ' sr_point_balances (account_id, balance, created_at, updated_at)
         VALUES (:account_id, 0, :created_at, :updated_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    $stmt = $pdo->prepare('SELECT balance FROM sr_point_balances WHERE account_id = :account_id LIMIT 1' . sr_ledger_for_update_clause($pdo));
    $stmt->execute(['account_id' => $accountId]);
    $balanceRow = $stmt->fetch();
    if (!is_array($balanceRow)) {
        throw new RuntimeException('Point balance row was not created.');
    }

    $balanceAfter = (int) $balanceRow['balance'] + $amount;
    if ($balanceAfter < 0) {
        throw new RuntimeException('Point balance cannot be negative.');
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_point_balances
         SET balance = :balance, updated_at = :updated_at
         WHERE account_id = :account_id'
    );
    $stmt->execute([
        'balance' => $balanceAfter,
        'updated_at' => $createdAt,
        'account_id' => $accountId,
    ]);

    $stmt = $pdo->prepare(
        'INSERT INTO sr_point_transactions
            (account_id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_by_account_id, expires_at, expires_remaining, expired_at, created_at)
         VALUES
            (:account_id, :amount, :balance_after, :transaction_type, :reason, :reference_type, :reference_id, :created_by_account_id, :expires_at, :expires_remaining, NULL, :created_at)'
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
        'expires_at' => $expiresAt,
        'expires_remaining' => $expiresRemaining,
        'created_at' => $createdAt,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_point_consume_expiring_grants(PDO $pdo, int $accountId, int $amount, int $consumeTransactionId = 0, ?string $now = null): int
{
    if ($accountId <= 0 || $amount <= 0) {
        return 0;
    }

    $now = $now ?? sr_now();
    $remainingToConsume = $amount;
    $consumed = 0;

    $stmt = $pdo->prepare(
        'SELECT id, expires_at, expires_remaining
         FROM sr_point_transactions
         WHERE account_id = :account_id
           AND amount > 0
           AND expires_at IS NOT NULL
           AND expires_at > :now_value
           AND expires_remaining > 0
         ORDER BY expires_at ASC, id ASC'
         . sr_ledger_for_update_clause($pdo)
    );
    $stmt->execute([
        'account_id' => $accountId,
        'now_value' => $now,
    ]);

    $updateStmt = $pdo->prepare(
        'UPDATE sr_point_transactions
         SET expires_remaining = :expires_remaining
         WHERE id = :id'
    );
    $insertConsumptionStmt = $pdo->prepare(
        'INSERT INTO sr_point_expiration_consumptions
            (account_id, consume_transaction_id, source_transaction_id, amount, source_expires_at, created_at)
         VALUES
            (:account_id, :consume_transaction_id, :source_transaction_id, :amount, :source_expires_at, :created_at)'
    );
    foreach ($stmt->fetchAll() as $row) {
        if ($remainingToConsume <= 0) {
            break;
        }

        $rowRemaining = (int) ($row['expires_remaining'] ?? 0);
        if ($rowRemaining <= 0) {
            continue;
        }

        $consumeAmount = min($rowRemaining, $remainingToConsume);
        $sourceTransactionId = (int) $row['id'];
        $updateStmt->execute([
            'expires_remaining' => $rowRemaining - $consumeAmount,
            'id' => $sourceTransactionId,
        ]);
        if ($consumeTransactionId > 0) {
            $insertConsumptionStmt->execute([
                'account_id' => $accountId,
                'consume_transaction_id' => $consumeTransactionId,
                'source_transaction_id' => $sourceTransactionId,
                'amount' => $consumeAmount,
                'source_expires_at' => (string) $row['expires_at'],
                'created_at' => $now,
            ]);
        }
        $remainingToConsume -= $consumeAmount;
        $consumed += $consumeAmount;
    }

    return $consumed;
}

function sr_point_expire_due_preview_transactions(PDO $pdo, int $limit = 200, ?string $now = null): array
{
    $limit = max(1, min(1000, $limit));
    $now = $now ?? sr_now();
    $stmt = $pdo->prepare(
        'SELECT id, expires_remaining
         FROM sr_point_transactions
         WHERE amount > 0
           AND expires_at IS NOT NULL
           AND expires_at <= :now_value
           AND expires_remaining > 0
         ORDER BY expires_at ASC, id ASC
         LIMIT ' . $limit
    );
    $stmt->execute(['now_value' => $now]);

    $dueCount = 0;
    $dueAmount = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dueCount++;
        $dueAmount += max(0, (int) ($row['expires_remaining'] ?? 0));
    }

    return [
        'due_count' => $dueCount,
        'due_amount' => $dueAmount,
    ];
}

function sr_point_expire_due_transactions(PDO $pdo, int $limit = 200, ?string $now = null): array
{
    $limit = max(1, min(1000, $limit));
    $now = $now ?? sr_now();
    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_point_transactions
         WHERE amount > 0
           AND expires_at IS NOT NULL
           AND expires_at <= :now_value
           AND expires_remaining > 0
         ORDER BY expires_at ASC, id ASC
         LIMIT ' . $limit
    );
    $stmt->execute(['now_value' => $now]);

    $expiredCount = 0;
    $expiredAmount = 0;
    foreach ($stmt->fetchAll() as $row) {
        $result = sr_point_expire_grant_transaction($pdo, (int) ($row['id'] ?? 0), $now);
        if ($result['transaction_id'] > 0) {
            $expiredCount++;
            $expiredAmount += (int) $result['amount'];
        }
    }

    return [
        'expired_count' => $expiredCount,
        'expired_amount' => $expiredAmount,
    ];
}

function sr_point_expire_due_account_transactions(PDO $pdo, int $accountId, int $limit = 100, ?string $now = null): array
{
    if ($accountId <= 0) {
        return ['expired_count' => 0, 'expired_amount' => 0];
    }

    $limit = max(1, min(1000, $limit));
    $now = $now ?? sr_now();
    $expiredCount = 0;
    $expiredAmount = 0;
    do {
        $stmt = $pdo->prepare(
            'SELECT id
             FROM sr_point_transactions
             WHERE account_id = :account_id
               AND amount > 0
               AND expires_at IS NOT NULL
               AND expires_at <= :now_value
               AND expires_remaining > 0
             ORDER BY expires_at ASC, id ASC
             LIMIT ' . $limit
        );
        $stmt->execute([
            'account_id' => $accountId,
            'now_value' => $now,
        ]);
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $result = sr_point_expire_grant_transaction($pdo, (int) ($row['id'] ?? 0), $now);
            if ($result['transaction_id'] > 0) {
                $expiredCount++;
                $expiredAmount += (int) $result['amount'];
            }
        }
    } while (count($rows) === $limit && $expiredCount < 10000);

    if ($expiredCount >= 10000) {
        throw new RuntimeException('Too many due point expiration transactions for one account.');
    }

    return [
        'expired_count' => $expiredCount,
        'expired_amount' => $expiredAmount,
    ];
}

function sr_point_expire_grant_transaction(PDO $pdo, int $sourceTransactionId, string $now): array
{
    if ($sourceTransactionId <= 0) {
        return ['transaction_id' => 0, 'amount' => 0];
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $sourceAccountStmt = $pdo->prepare(
            'SELECT account_id
             FROM sr_point_transactions
             WHERE id = :id
               AND amount > 0
               AND expires_at IS NOT NULL
               AND expires_at <= :now_value
               AND expires_remaining > 0
             LIMIT 1'
        );
        $sourceAccountStmt->execute([
            'id' => $sourceTransactionId,
            'now_value' => $now,
        ]);
        $sourceAccount = $sourceAccountStmt->fetch();
        if (!is_array($sourceAccount)) {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return ['transaction_id' => 0, 'amount' => 0];
        }

        $accountId = (int) ($sourceAccount['account_id'] ?? 0);
        if ($accountId <= 0) {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return ['transaction_id' => 0, 'amount' => 0];
        }

        $balanceLockStmt = $pdo->prepare('SELECT balance FROM sr_point_balances WHERE account_id = :account_id LIMIT 1' . sr_ledger_for_update_clause($pdo));
        $balanceLockStmt->execute(['account_id' => $accountId]);

        $sourceStmt = $pdo->prepare(
            'SELECT id, account_id, expires_remaining
             FROM sr_point_transactions
             WHERE id = :id
               AND amount > 0
               AND expires_at IS NOT NULL
               AND expires_at <= :now_value
               AND expires_remaining > 0
             LIMIT 1'
             . sr_ledger_for_update_clause($pdo)
        );
        $sourceStmt->execute([
            'id' => $sourceTransactionId,
            'now_value' => $now,
        ]);
        $source = $sourceStmt->fetch();
        if (!is_array($source)) {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return ['transaction_id' => 0, 'amount' => 0];
        }

        $expireAmount = (int) ($source['expires_remaining'] ?? 0);
        if ($expireAmount <= 0 || $accountId <= 0) {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return ['transaction_id' => 0, 'amount' => 0];
        }

        $transactionId = sr_point_create_transaction($pdo, [
            'account_id' => $accountId,
            'amount' => -$expireAmount,
            'transaction_type' => 'expire',
            'reason' => '포인트 유효기간 만료',
            'reference_type' => 'point_expiration',
            'reference_id' => 'point_transaction:' . $sourceTransactionId,
            'created_by_account_id' => null,
            'skip_expiration_consumption' => true,
        ]);

        $updateStmt = $pdo->prepare(
            'UPDATE sr_point_transactions
             SET expires_remaining = 0,
                 expired_at = :expired_at
             WHERE id = :id'
        );
        $updateStmt->execute([
            'expired_at' => $now,
            'id' => $sourceTransactionId,
        ]);

        if ($startedTransaction) {
            $pdo->commit();
        }

        return ['transaction_id' => $transactionId, 'amount' => $expireAmount];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function sr_point_defer_transaction_notification(PDO $pdo, int $transactionId): void
{
    if ($transactionId <= 0) {
        return;
    }

    register_shutdown_function(static function () use ($pdo, $transactionId): void {
        if ($pdo->inTransaction()) {
            return;
        }
        sr_point_notify_transaction_created($pdo, $transactionId);
    });
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

function sr_point_transaction_type_label(string $transactionType): string
{
    $labels = [
        'adjustment' => '조정',
        'grant' => '지급',
        'use' => '사용',
        'refund' => '환불',
        'expire' => '만료',
    ];

    return $labels[$transactionType] ?? $transactionType;
}

function sr_point_reference_type_label(string $referenceType): string
{
    if (function_exists('sr_admin_code_label')) {
        return sr_admin_code_label($referenceType, 'reference_type');
    }

    $labels = [
        '' => '없음',
        'order' => '주문',
        'payment' => '결제',
        'refund' => '환불',
        'point_expiration' => '포인트 만료',
        'support_ticket' => '고객문의',
        'event' => '이벤트',
        'migration' => '데이터 이관',
        'member.withdrawal' => '회원 탈퇴',
        'content.view' => '콘텐츠 열람',
        'content.download' => '콘텐츠 다운로드',
        'content.action' => '콘텐츠 완료 처리',
        'community.post' => '커뮤니티 게시글',
        'community.comment' => '커뮤니티 댓글',
        'community.attachment.publisher_reward' => '첨부 다운로드 리워드',
    ];

    return $labels[$referenceType] ?? $referenceType;
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
    return sr_clean_single_line($value, $maxLength);
}

function sr_point_transaction_by_id(PDO $pdo, int $transactionId): ?array
{
    if ($transactionId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, account_id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_by_account_id, expires_at, expires_remaining, expired_at, created_at
         FROM sr_point_transactions
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $transactionId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_point_transaction_by_reference(PDO $pdo, string $referenceType, string $referenceId): ?array
{
    $referenceType = sr_point_clean_key($referenceType, 60);
    $referenceId = sr_point_clean_reference_id($referenceId, 120);
    if ($referenceType === '' || $referenceId === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, account_id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_by_account_id, expires_at, expires_remaining, expired_at, created_at
         FROM sr_point_transactions
         WHERE reference_type = :reference_type
           AND reference_id = :reference_id
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([
        'reference_type' => $referenceType,
        'reference_id' => $referenceId,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_point_admin_transaction_rows(PDO $pdo, array $config, array $sort, array $pagination, int $accountId = 0): array
{
    $sql = 'SELECT t.id, t.account_id, t.amount, t.balance_after, t.transaction_type, t.reason, t.reference_type, t.reference_id, t.created_by_account_id, t.expires_at, t.expires_remaining, t.expired_at, t.created_at,
                   a.email, a.display_name
            FROM sr_point_transactions t
            INNER JOIN sr_member_accounts a ON a.id = t.account_id';
    if ($accountId > 0) {
        $stmt = $pdo->prepare(
            $sql . '
             WHERE t.account_id = :account_id
             ' . sr_admin_sort_order_sql(sr_admin_asset_transaction_sort_options(), $sort, sr_admin_asset_transaction_default_sort()) . '
             LIMIT :limit_value OFFSET :offset_value'
        );
        $stmt->bindValue('account_id', $accountId, PDO::PARAM_INT);
        $stmt->bindValue('limit_value', (int) $pagination['per_page'], PDO::PARAM_INT);
        $stmt->bindValue('offset_value', sr_admin_pagination_offset($pagination), PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->query(
            $sql . '
             ' . sr_admin_sort_order_sql(sr_admin_asset_transaction_sort_options(), $sort, sr_admin_asset_transaction_default_sort()) . '
             LIMIT ' . (int) $pagination['per_page'] . ' OFFSET ' . sr_admin_pagination_offset($pagination)
        );
    }

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = sr_admin_member_row_with_public_hash($config, $row);
    }

    return $rows;
}

function sr_point_notify_transaction_created(PDO $pdo, int $transactionId): ?int
{
    $createAccountEventFunction = sr_point_notification_event_function($pdo);
    if ($createAccountEventFunction === '') {
        return null;
    }

    $transaction = sr_point_transaction_by_id($pdo, $transactionId);
    if (!is_array($transaction)) {
        return null;
    }

    try {
        $amount = (int) $transaction['amount'];
        $transactionType = (string) $transaction['transaction_type'];
        $eventKey = $transactionType === 'adjustment'
            ? 'transaction.adjustment.' . ($amount > 0 ? 'increase' : 'decrease')
            : 'transaction.' . $transactionType;
        $channels = [];
        $caseSetting = sr_point_notification_setting_for_event(sr_point_settings($pdo), $eventKey);
        if (is_array($caseSetting)) {
            if (empty($caseSetting['enabled'])) {
                return null;
            }
            $channels = sr_point_notification_channels_from_value($caseSetting['channels'] ?? ['site']);
        }

        $payload = [
            'account_id' => (int) $transaction['account_id'],
            'module_key' => 'point',
            'event_key' => $eventKey,
            'created_by_account_id' => (int) ($transaction['created_by_account_id'] ?? 0),
            'metadata' => sr_point_transaction_notification_metadata($transaction, $pdo),
        ];
        if ($channels !== []) {
            $payload['channels'] = $channels;
        }

        return $createAccountEventFunction($pdo, $payload);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'point_transaction_notification');
        return null;
    }
}

function sr_point_notification_event_function(PDO $pdo): string
{
    return sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_account_event_function');
}

function sr_point_transaction_notification_metadata(array $transaction, ?PDO $pdo = null): array
{
    $amount = (int) ($transaction['amount'] ?? 0);
    $assetLabel = $pdo instanceof PDO ? sr_point_display_name($pdo) : '포인트';
    $transactionType = (string) ($transaction['transaction_type'] ?? '');
    $referenceType = (string) ($transaction['reference_type'] ?? '');

    return [
        'transaction_id' => (int) ($transaction['id'] ?? 0),
        'asset_label' => $assetLabel,
        'amount' => number_format($amount),
        'amount_abs' => number_format(abs($amount)),
        'amount_signed' => ($amount > 0 ? '+' : '') . number_format($amount),
        'balance_after' => number_format((int) ($transaction['balance_after'] ?? 0)),
        'transaction_type' => $transactionType,
        'transaction_type_label' => sr_point_transaction_type_label($transactionType),
        'reason' => (string) ($transaction['reason'] ?? ''),
        'reference_type' => $referenceType,
        'reference_type_label' => sr_point_reference_type_label($referenceType),
        'reference_id' => (string) ($transaction['reference_id'] ?? ''),
        'expires_at' => (string) ($transaction['expires_at'] ?? ''),
        'expires_remaining' => number_format((int) ($transaction['expires_remaining'] ?? 0)),
        'expired_at' => (string) ($transaction['expired_at'] ?? ''),
        'created_at' => (string) ($transaction['created_at'] ?? ''),
    ];
}
