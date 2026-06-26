<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/helpers/common.php';

require_once SR_ROOT . '/modules/asset_ledger/helpers.php';

function sr_reward_balance(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT balance FROM sr_reward_balances WHERE account_id = :account_id LIMIT 1');
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();

    return is_array($row) ? (int) $row['balance'] : 0;
}

function sr_reward_admin_adjustment_once_limit(): int
{
    return 10000000;
}

function sr_reward_admin_adjustment_daily_limit(): int
{
    return 10000000;
}

function sr_reward_admin_adjustment_approval_threshold(): int
{
    return 1000000;
}

function sr_reward_withdrawal_min_amount(): int
{
    return 1000;
}

function sr_reward_withdrawal_max_amount(): int
{
    return 10000000;
}

function sr_reward_withdrawal_all_members_key(): string
{
    return '__all__';
}

function sr_reward_default_settings(): array
{
    return [
        'usage_enabled' => true,
        'withdrawal_requests_enabled' => false,
        'withdrawal_allowed_group_keys_json' => '[]',
        'notifications_enabled' => true,
        'notification_cases' => sr_reward_default_notification_case_settings(),
    ];
}

function sr_reward_notification_cases(): array
{
    return [
        'transaction_grant' => [
            'event_key' => 'transaction.grant',
            'label' => '지급 알림',
            'description' => '적립금이 지급되었을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'transaction_refund' => [
            'event_key' => 'transaction.refund',
            'label' => '복원 알림',
            'description' => '적립금이 환불 또는 복원되었을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'transaction_exchange_in' => [
            'event_key' => 'transaction.exchange_in',
            'label' => '환전 입금 알림',
            'description' => '환전으로 적립금이 입금되었을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'transaction_use' => [
            'event_key' => 'transaction.use',
            'label' => '사용 알림',
            'description' => '적립금이 사용되었을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'transaction_exchange_out' => [
            'event_key' => 'transaction.exchange_out',
            'label' => '환전 출금 알림',
            'description' => '환전으로 적립금이 출금되었을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'transaction_exchange_fee' => [
            'event_key' => 'transaction.exchange_fee',
            'label' => '환전 수수료 알림',
            'description' => '환전 수수료로 적립금이 차감되었을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'transaction_expire' => [
            'event_key' => 'transaction.expire',
            'label' => '만료 알림',
            'description' => '적립금이 만료되었을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'transaction_withdraw' => [
            'event_key' => 'transaction.withdraw',
            'label' => '출금 알림',
            'description' => '적립금 출금이 처리되었을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'transaction_reclaim' => [
            'event_key' => 'transaction.reclaim',
            'label' => '회수 알림',
            'description' => '적립금이 회수되었을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'transaction_adjustment_increase' => [
            'event_key' => 'transaction.adjustment.increase',
            'label' => '증가 조정 알림',
            'description' => '관리자 조정 등으로 적립금이 증가했을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'transaction_adjustment_decrease' => [
            'event_key' => 'transaction.adjustment.decrease',
            'label' => '감소 조정 알림',
            'description' => '관리자 조정 등으로 적립금이 감소했을 때 보냅니다.',
            'default_enabled' => true,
        ],
    ];
}

function sr_reward_notification_case_key_for_event(string $eventKey): string
{
    foreach (sr_reward_notification_cases() as $caseKey => $case) {
        if ((string) ($case['event_key'] ?? '') === $eventKey) {
            return (string) $caseKey;
        }
    }

    return '';
}

function sr_reward_default_notification_case_settings(): array
{
    $settings = [];
    foreach (sr_reward_notification_cases() as $caseKey => $case) {
        $settings[(string) $caseKey] = [
            'event_key' => (string) ($case['event_key'] ?? ''),
            'enabled' => !empty($case['default_enabled']),
            'channels' => ['site'],
        ];
    }

    return $settings;
}

function sr_reward_account_notification_channel_keys(): array
{
    return ['site', 'email', 'telegram_bot'];
}

function sr_reward_notification_channels_from_value(mixed $value): array
{
    $rawValues = is_array($value) ? $value : json_decode((string) $value, true);
    if (!is_array($rawValues)) {
        $rawValues = ['site'];
    }

    $allowed = sr_reward_account_notification_channel_keys();
    $channels = [];
    foreach ($rawValues as $channel) {
        if (is_string($channel) && in_array($channel, $allowed, true)) {
            $channels[$channel] = $channel;
        }
    }

    return $channels === [] ? ['site'] : array_values($channels);
}

function sr_reward_notification_channel_options(PDO $pdo): array
{
    $channels = ['site'];
    if (sr_reward_notification_event_function($pdo) !== '') {
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

    $allowed = sr_reward_account_notification_channel_keys();
    $options = [];
    foreach ($channels as $channel) {
        if (is_string($channel) && in_array($channel, $allowed, true)) {
            $options[$channel] = $channel;
        }
    }

    return $options === [] ? ['site'] : array_values($options);
}

function sr_reward_notification_case_settings_from_value(mixed $value): array
{
    $rawSettings = is_array($value) ? $value : json_decode((string) $value, true);
    if (!is_array($rawSettings)) {
        $rawSettings = [];
    }

    $caseKeyByEventKey = [];
    foreach (sr_reward_notification_cases() as $caseKey => $case) {
        $caseKeyByEventKey[(string) ($case['event_key'] ?? '')] = (string) $caseKey;
    }

    $normalized = sr_reward_default_notification_case_settings();
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
            $normalized[$caseKey]['channels'] = sr_reward_notification_channels_from_value($rawCaseSettings['channels']);
        }
    }

    return $normalized;
}

function sr_reward_notification_setting_for_event(array $settings, string $eventKey): ?array
{
    $caseKey = sr_reward_notification_case_key_for_event($eventKey);
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

    $caseSettings = sr_reward_notification_case_settings_from_value($settings['notification_cases'] ?? []);
    return isset($caseSettings[$caseKey]) && is_array($caseSettings[$caseKey]) ? $caseSettings[$caseKey] : null;
}

function sr_reward_settings(PDO $pdo): array
{
    $storedSettings = sr_module_settings($pdo, 'reward');
    $settings = array_merge(sr_reward_default_settings(), $storedSettings);
    $settings['usage_enabled'] = sr_reward_truthy($settings['usage_enabled'] ?? true);
    $settings['withdrawal_allowed_group_keys'] = sr_reward_normalize_group_keys(
        sr_reward_json_array((string) ($settings['withdrawal_allowed_group_keys_json'] ?? '[]'))
    );
    $settings['withdrawal_requests_enabled'] = array_key_exists('withdrawal_requests_enabled', $storedSettings)
        ? sr_reward_truthy($settings['withdrawal_requests_enabled'] ?? false)
        : $settings['withdrawal_allowed_group_keys'] !== [];
    $settings['notifications_enabled'] = sr_reward_truthy($settings['notifications_enabled'] ?? true);
    $settings['notification_cases'] = sr_reward_notification_case_settings_from_value($settings['notification_cases'] ?? []);

    return $settings;
}

function sr_reward_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'reward' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('적립금 모듈이 등록되어 있지 않습니다.');
    }

    $allowedGroupKeys = sr_reward_normalize_group_keys($settings['withdrawal_allowed_group_keys'] ?? []);
    $usageEnabled = array_key_exists('usage_enabled', $settings)
        ? sr_reward_truthy($settings['usage_enabled'])
        : sr_reward_usage_enabled($pdo);
    $notificationsEnabled = array_key_exists('notifications_enabled', $settings)
        ? sr_reward_truthy($settings['notifications_enabled'])
        : sr_reward_truthy(sr_reward_settings($pdo)['notifications_enabled'] ?? true);
    if (array_key_exists('withdrawal_requests_enabled', $settings)) {
        $withdrawalRequestsEnabled = sr_reward_truthy($settings['withdrawal_requests_enabled']);
    } else {
        $storedSettings = sr_module_settings($pdo, 'reward');
        $withdrawalRequestsEnabled = array_key_exists('withdrawal_requests_enabled', $storedSettings)
            ? sr_reward_truthy($storedSettings['withdrawal_requests_enabled'])
            : $allowedGroupKeys !== [];
    }
    foreach ($allowedGroupKeys as $groupKey) {
        if ($groupKey === sr_reward_withdrawal_all_members_key()) {
            continue;
        }
        if (!sr_member_group_exists($pdo, $groupKey)) {
            throw new InvalidArgumentException('Reward withdrawal group does not exist.');
        }
    }
    $notificationCases = array_key_exists('notification_cases', $settings)
        ? sr_reward_notification_case_settings_from_value($settings['notification_cases'])
        : sr_reward_notification_case_settings_from_value(sr_reward_settings($pdo)['notification_cases'] ?? []);
    $notificationCasesJson = json_encode($notificationCases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($notificationCasesJson)) {
        $notificationCasesJson = json_encode(sr_reward_default_notification_case_settings(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
    $stmt->execute([
        'module_id' => (int) $module['id'],
        'setting_key' => 'usage_enabled',
        'setting_value' => $usageEnabled ? '1' : '0',
        'value_type' => 'bool',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $stmt->execute([
        'module_id' => (int) $module['id'],
        'setting_key' => 'withdrawal_allowed_group_keys_json',
        'setting_value' => json_encode(array_values($allowedGroupKeys), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'value_type' => 'string',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $stmt->execute([
        'module_id' => (int) $module['id'],
        'setting_key' => 'withdrawal_requests_enabled',
        'setting_value' => $withdrawalRequestsEnabled ? '1' : '0',
        'value_type' => 'bool',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $stmt->execute([
        'module_id' => (int) $module['id'],
        'setting_key' => 'notifications_enabled',
        'setting_value' => $notificationsEnabled ? '1' : '0',
        'value_type' => 'bool',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $stmt->execute([
        'module_id' => (int) $module['id'],
        'setting_key' => 'notification_cases',
        'setting_value' => (string) $notificationCasesJson,
        'value_type' => 'json',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    sr_clear_module_settings_cache('reward');
}

function sr_reward_truthy(mixed $value): bool
{
    return sr_truthy($value);
}

function sr_reward_usage_enabled(PDO $pdo): bool
{
    try {
        $settings = sr_reward_settings($pdo);
    } catch (PDOException) {
        return true;
    }

    return !empty($settings['usage_enabled']);
}

function sr_reward_json_array(string $json): array
{
    return sr_json_array($json);
}

function sr_reward_normalize_group_keys(mixed $groupKeys): array
{
    if (!is_array($groupKeys)) {
        return [];
    }

    $normalized = [];
    foreach ($groupKeys as $groupKey) {
        $groupKey = (string) $groupKey;
        if ($groupKey === sr_reward_withdrawal_all_members_key()) {
            return [sr_reward_withdrawal_all_members_key()];
        }
        if (sr_member_group_key_is_valid($groupKey)) {
            $normalized[$groupKey] = true;
        }
    }

    return array_keys($normalized);
}

function sr_reward_withdrawal_allowed_group_keys(PDO $pdo): array
{
    $settings = sr_reward_settings($pdo);
    return isset($settings['withdrawal_allowed_group_keys']) && is_array($settings['withdrawal_allowed_group_keys'])
        ? $settings['withdrawal_allowed_group_keys']
        : [];
}

function sr_reward_member_group_reference_count(PDO $pdo, array $target, array $context): int
{
    return count(sr_reward_member_group_reference_rows($pdo, $target, $context));
}

function sr_reward_member_group_reference_rows(PDO $pdo, array $target, array $context): array
{
    $groupKey = (string) ($target['target_key'] ?? '');
    if ($groupKey === '') {
        return [];
    }

    $settings = sr_reward_settings($pdo);
    $allowedGroupKeys = is_array($settings['withdrawal_allowed_group_keys'] ?? null) ? array_map('strval', $settings['withdrawal_allowed_group_keys']) : [];
    if (!in_array($groupKey, $allowedGroupKeys, true)) {
        return [];
    }

    return [[
        'consumer_module_key' => 'reward',
        'reference_type' => 'reward_withdrawal_group_policy',
        'reference_id' => 'reward_settings:withdrawal_allowed_group_keys',
        'title' => '적립금 출금 신청 허용 대상',
        'target_type' => 'member_group',
        'target_id' => (string) (int) ($target['target_id'] ?? 0),
        'target_key' => $groupKey,
        'policy_status' => !empty($settings['withdrawal_requests_enabled']) ? 'enabled' : 'disabled',
        'updated_at' => '',
    ]];
}

function sr_reward_member_group_reference_health(PDO $pdo, array $target, array $row, array $context): array
{
    return (string) ($row['policy_status'] ?? '') === 'enabled'
        ? ['status' => 'ok', 'policy_status' => 'enabled']
        : ['status' => 'disabled_target', 'policy_status' => 'disabled'];
}

function sr_reward_member_group_reference_admin_url(array $row, array $context): string
{
    return '/admin/rewards/settings';
}

function sr_reward_withdrawal_requests_enabled(PDO $pdo): bool
{
    $settings = sr_reward_settings($pdo);
    return !empty($settings['withdrawal_requests_enabled']);
}

function sr_reward_account_can_request_withdrawal(PDO $pdo, int $accountId): bool
{
    if ($accountId <= 0) {
        return false;
    }
    if (!sr_reward_withdrawal_requests_enabled($pdo)) {
        return false;
    }

    $allowedGroupKeys = sr_reward_withdrawal_allowed_group_keys($pdo);
    if ($allowedGroupKeys === []) {
        return false;
    }

    if (in_array(sr_reward_withdrawal_all_members_key(), $allowedGroupKeys, true)) {
        return true;
    }

    return sr_member_account_in_any_group($pdo, $accountId, $allowedGroupKeys);
}

function sr_reward_validate_admin_adjustment_limit(PDO $pdo, array $runtimeConfig, int $adminAccountId, string $permissionPath, int $amount, string $approvalIdentifier = '', string $approvalNote = ''): array
{
    $absoluteAmount = abs($amount);
    if ($absoluteAmount > sr_reward_admin_adjustment_once_limit()) {
        return ['error' => '적립금 관리자 조정 금액이 1회 상한을 초과했습니다.', 'approval_account_id' => 0];
    }

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(ABS(amount)), 0) AS total_amount
         FROM sr_reward_transactions
         WHERE created_by_account_id = :admin_account_id
           AND created_at >= :started_at
           AND transaction_type IN ('adjustment', 'grant', 'use', 'refund', 'expire', 'reclaim')"
    );
    $stmt->execute([
        'admin_account_id' => $adminAccountId,
        'started_at' => date('Y-m-d 00:00:00'),
    ]);
    $row = $stmt->fetch();
    $usedAmount = is_array($row) ? (int) ($row['total_amount'] ?? 0) : 0;

    if ($usedAmount + $absoluteAmount > sr_reward_admin_adjustment_daily_limit()) {
        return ['error' => '적립금 관리자 조정 금액이 일일 상한을 초과했습니다.', 'approval_account_id' => 0];
    }

    if ($absoluteAmount <= sr_reward_admin_adjustment_approval_threshold()) {
        return ['error' => null, 'approval_account_id' => 0];
    }

    $approvalAccountId = sr_admin_member_account_id_from_identifier($pdo, $runtimeConfig, $approvalIdentifier);
    if ($approvalAccountId <= 0 || sr_reward_clean_text($approvalNote, 255) === '') {
        return ['error' => '대액 적립금 조정 기록을 만들 대상 회원과 사유가 필요합니다.', 'approval_account_id' => 0];
    }

    return ['error' => null, 'approval_account_id' => $approvalAccountId];
}

function sr_reward_create_transaction(PDO $pdo, array $data): int
{
    if (!sr_reward_usage_enabled($pdo)) {
        throw new RuntimeException('적립금을 사용하지 않도록 설정되어 있습니다.');
    }

    $accountId = (int) ($data['account_id'] ?? 0);
    $amount = (int) ($data['amount'] ?? 0);
    $transactionType = sr_reward_clean_key((string) ($data['transaction_type'] ?? 'adjustment'), 40);
    $reason = sr_reward_clean_text((string) ($data['reason'] ?? ''), 255);
    $referenceType = sr_reward_clean_key((string) ($data['reference_type'] ?? ''), 60);
    $referenceId = sr_reward_clean_reference_id((string) ($data['reference_id'] ?? ''), 120);
    $createdByAccountId = isset($data['created_by_account_id']) ? (int) $data['created_by_account_id'] : null;

    if ($accountId <= 0) {
        throw new InvalidArgumentException('Account id is required.');
    }

    if ($amount === 0) {
        throw new InvalidArgumentException('Amount must not be zero.');
    }

    if (!sr_reward_transaction_type_allows_amount($transactionType, $amount)) {
        throw new InvalidArgumentException('Reward transaction amount sign is invalid for type.');
    }
    if ($transactionType === 'refund' && ($referenceType !== 'refund' || preg_match('/\Areward_transaction:([0-9]+)\z/', $referenceId) !== 1)) {
        throw new InvalidArgumentException('Reward refund reference is required.');
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $transactionId = sr_ledger_create_transaction($pdo, [
            'balance_table' => 'sr_reward_balances',
            'transaction_table' => 'sr_reward_transactions',
            'balance_row_error' => 'Reward balance row was not created.',
            'negative_balance_error' => 'Reward balance cannot be negative.',
        ], [
            'account_id' => $accountId,
            'amount' => $amount,
            'transaction_type' => $transactionType,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by_account_id' => $createdByAccountId,
        ]);

        if ($startedTransaction) {
            $pdo->commit();
            sr_reward_notify_transaction_created($pdo, $transactionId);
        } else {
            sr_reward_defer_transaction_notification($pdo, $transactionId);
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return $transactionId;
}

function sr_reward_defer_transaction_notification(PDO $pdo, int $transactionId): void
{
    if ($transactionId <= 0) {
        return;
    }

    register_shutdown_function(static function () use ($pdo, $transactionId): void {
        if ($pdo->inTransaction()) {
            return;
        }
        sr_reward_notify_transaction_created($pdo, $transactionId);
    });
}

function sr_reward_transaction_type_allows_amount(string $transactionType, int $amount): bool
{
    if ($amount === 0) {
        return false;
    }

    if (in_array($transactionType, ['grant', 'refund', 'exchange_in'], true)) {
        return $amount > 0;
    }

    if (in_array($transactionType, ['use', 'expire', 'reclaim', 'withdraw', 'exchange_out', 'exchange_fee'], true)) {
        return $amount < 0;
    }

    return $transactionType === 'adjustment';
}

function sr_reward_transaction_type_label(string $transactionType): string
{
    $labels = [
        'adjustment' => '조정',
        'grant' => '지급',
        'use' => '사용',
        'refund' => '환불',
        'expire' => '만료',
        'reclaim' => '회수',
        'withdraw' => '출금',
        'exchange_in' => '환전 입금',
        'exchange_out' => '환전 출금',
        'exchange_fee' => '환전 수수료',
    ];

    return $labels[$transactionType] ?? $transactionType;
}

function sr_reward_reference_type_label(string $referenceType): string
{
    if (function_exists('sr_admin_code_label')) {
        return sr_admin_code_label($referenceType, 'reference_type');
    }

    $labels = [
        '' => '없음',
        'order' => '주문',
        'payment' => '결제',
        'refund' => '환불',
        'reclaim' => '회수',
        'support_ticket' => '고객문의',
        'event' => '이벤트',
        'migration' => '데이터 이관',
        'reward_withdrawal' => '적립금 출금',
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

function sr_reward_reclaim_reference_id(int $transactionId): string
{
    return 'reward_transaction:' . (string) $transactionId;
}

function sr_reward_reclaim_reference_transaction_id(string $referenceId): int
{
    if (preg_match('/\Areward_transaction:([0-9]+)\z/', $referenceId, $matches) !== 1) {
        return 0;
    }

    return (int) $matches[1];
}

function sr_reward_reclaim_target(PDO $pdo, int $accountId, int $transactionId, bool $lock = false): ?array
{
    if ($accountId <= 0 || $transactionId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, account_id, amount, transaction_type, reason, created_at
         FROM sr_reward_transactions
         WHERE id = :id AND account_id = :account_id
         LIMIT 1' . ($lock ? sr_ledger_for_update_clause($pdo) : '')
    );
    $stmt->execute([
        'id' => $transactionId,
        'account_id' => $accountId,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_reward_reclaimed_amount_for_target(PDO $pdo, int $accountId, int $transactionId, bool $lock = false): int
{
    if ($accountId <= 0 || $transactionId <= 0) {
        return 0;
    }

    if ($lock) {
        $stmt = $pdo->prepare(
            "SELECT amount
             FROM sr_reward_transactions
             WHERE account_id = :account_id
               AND transaction_type = 'reclaim'
               AND reference_type = 'reclaim'
               AND reference_id = :reference_id
             " . sr_ledger_for_update_clause($pdo)
        );
        $stmt->execute([
            'account_id' => $accountId,
            'reference_id' => sr_reward_reclaim_reference_id($transactionId),
        ]);

        $reclaimedAmount = 0;
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $reclaimedAmount += abs((int) ($row['amount'] ?? 0));
            }
        }

        return $reclaimedAmount;
    }

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(ABS(amount)), 0) AS reclaimed_amount
         FROM sr_reward_transactions
         WHERE account_id = :account_id
           AND transaction_type = 'reclaim'
           AND reference_type = 'reclaim'
           AND reference_id = :reference_id"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'reference_id' => sr_reward_reclaim_reference_id($transactionId),
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['reclaimed_amount'] ?? 0) : 0;
}

function sr_reward_reclaim_remaining_amount(PDO $pdo, int $accountId, int $transactionId): int
{
    $target = sr_reward_reclaim_target($pdo, $accountId, $transactionId);
    if (!is_array($target)) {
        return 0;
    }

    $targetAmount = (int) ($target['amount'] ?? 0);
    if ($targetAmount <= 0) {
        return 0;
    }

    return max(0, $targetAmount - sr_reward_reclaimed_amount_for_target($pdo, $accountId, $transactionId));
}

function sr_reward_reclaim_remaining_amounts_for_transactions(PDO $pdo, array $transactions): array
{
    $targets = [];
    $referenceIds = [];
    foreach ($transactions as $transaction) {
        if (!is_array($transaction)) {
            continue;
        }

        $transactionId = (int) ($transaction['id'] ?? 0);
        $accountId = (int) ($transaction['account_id'] ?? 0);
        $amount = (int) ($transaction['amount'] ?? 0);
        if ($transactionId <= 0 || $accountId <= 0 || $amount <= 0) {
            continue;
        }

        $referenceId = sr_reward_reclaim_reference_id($transactionId);
        $targets[$accountId . ':' . $transactionId] = [
            'account_id' => $accountId,
            'transaction_id' => $transactionId,
            'reference_id' => $referenceId,
            'amount' => $amount,
        ];
        $referenceIds[$referenceId] = true;
    }

    if ($targets === []) {
        return [];
    }

    $placeholders = [];
    $params = [];
    $index = 0;
    foreach (array_keys($referenceIds) as $referenceId) {
        $placeholder = ':reference_id_' . $index;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $referenceId;
        $index++;
    }

    $stmt = $pdo->prepare(
        "SELECT account_id, reference_id, COALESCE(SUM(ABS(amount)), 0) AS reclaimed_amount
         FROM sr_reward_transactions
         WHERE transaction_type = 'reclaim'
           AND reference_type = 'reclaim'
           AND reference_id IN (" . implode(', ', $placeholders) . ')
         GROUP BY account_id, reference_id'
    );
    foreach ($params as $placeholder => $value) {
        $stmt->bindValue($placeholder, $value);
    }
    $stmt->execute();

    $reclaimedAmounts = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }

        $reclaimedAmounts[(int) ($row['account_id'] ?? 0) . ':' . (string) ($row['reference_id'] ?? '')] = (int) ($row['reclaimed_amount'] ?? 0);
    }

    $remainingAmounts = [];
    foreach ($targets as $target) {
        $key = (int) $target['account_id'] . ':' . (string) $target['reference_id'];
        $remainingAmounts[(int) $target['transaction_id']] = max(0, (int) $target['amount'] - (int) ($reclaimedAmounts[$key] ?? 0));
    }

    return $remainingAmounts;
}

function sr_reward_validate_reclaim_transaction(PDO $pdo, int $accountId, int $amount, string $referenceType, string $referenceId, bool $lock = false): ?string
{
    if ($referenceType !== 'reclaim') {
        return sr_t('reward::action.admin.reclaim_reference_required');
    }

    $targetTransactionId = sr_reward_reclaim_reference_transaction_id($referenceId);
    if ($targetTransactionId <= 0) {
        return sr_t('reward::action.admin.reclaim_reference_required');
    }

    $target = sr_reward_reclaim_target($pdo, $accountId, $targetTransactionId, $lock);
    if (!is_array($target)) {
        return sr_t('reward::action.admin.reclaim_original_not_found');
    }

    if ((int) ($target['amount'] ?? 0) <= 0) {
        return sr_t('reward::action.admin.reclaim_original_not_positive');
    }

    $remainingAmount = max(
        0,
        (int) $target['amount'] - sr_reward_reclaimed_amount_for_target($pdo, $accountId, $targetTransactionId, $lock)
    );
    if (abs($amount) > $remainingAmount) {
        return sr_t('reward::action.admin.reclaim_amount_exceeds_target');
    }

    return null;
}

function sr_reward_clean_key(string $value, int $maxLength): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9_.-]/', '', strtolower($value));
    $value = is_string($value) ? $value : '';

    return substr($value, 0, $maxLength);
}

function sr_reward_clean_reference_id(string $value, int $maxLength): string
{
    $value = trim($value);
    $value = preg_replace('/[^a-zA-Z0-9_.:-]/', '', $value);
    $value = is_string($value) ? $value : '';

    return substr($value, 0, $maxLength);
}

function sr_reward_clean_text(string $value, int $maxLength): string
{
    return sr_clean_single_line($value, $maxLength);
}

function sr_reward_pending_withdrawal_amount(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) AS pending_amount
         FROM sr_reward_withdrawal_requests
         WHERE account_id = :account_id
           AND status = 'pending'"
    );
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['pending_amount'] ?? 0) : 0;
}

function sr_reward_withdrawal_available_amount(PDO $pdo, int $accountId): int
{
    return max(0, sr_reward_balance($pdo, $accountId) - sr_reward_pending_withdrawal_amount($pdo, $accountId));
}

function sr_reward_request_status_label(string $status): string
{
    $labels = [
        'pending' => '대기',
        'completed' => '완료',
        'rejected' => '거부',
        'canceled' => '취소',
    ];

    return $labels[$status] ?? $status;
}

function sr_reward_time_html(string $value): string
{
    return sr_relative_time_html($value);
}

function sr_reward_create_withdrawal_request(PDO $pdo, int $accountId, array $data): int
{
    $amount = (int) ($data['amount'] ?? 0);
    $bankName = sr_reward_clean_text((string) ($data['bank_name'] ?? ''), 80);
    $bankAccountNumber = sr_reward_clean_text((string) ($data['bank_account_number'] ?? ''), 80);
    $bankAccountHolder = sr_reward_clean_text((string) ($data['bank_account_holder'] ?? ''), 80);
    $requesterNote = sr_reward_clean_text((string) ($data['requester_note'] ?? ''), 255);

    if ($accountId <= 0) {
        throw new InvalidArgumentException('Account id is required.');
    }
    if ($amount < sr_reward_withdrawal_min_amount()) {
        throw new InvalidArgumentException('Reward withdrawal amount is below minimum.');
    }
    if ($amount > sr_reward_withdrawal_max_amount()) {
        throw new InvalidArgumentException('Reward withdrawal amount is above maximum.');
    }
    if ($bankName === '' || $bankAccountNumber === '' || $bankAccountHolder === '') {
        throw new InvalidArgumentException('Reward withdrawal bank fields are required.');
    }
    if (!sr_reward_usage_enabled($pdo)) {
        throw new RuntimeException('Reward usage is disabled.');
    }
    if (!sr_reward_withdrawal_requests_enabled($pdo)) {
        throw new RuntimeException('Reward withdrawal requests are disabled.');
    }
    if (!sr_reward_account_can_request_withdrawal($pdo, $accountId)) {
        throw new RuntimeException('Reward withdrawal account is not in an allowed group.');
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare('SELECT balance FROM sr_reward_balances WHERE account_id = :account_id LIMIT 1' . sr_ledger_for_update_clause($pdo));
        $stmt->execute(['account_id' => $accountId]);
        $row = $stmt->fetch();
        $balance = is_array($row) ? (int) ($row['balance'] ?? 0) : 0;
        $availableAmount = max(0, $balance - sr_reward_pending_withdrawal_amount($pdo, $accountId));
        if ($amount > $availableAmount) {
            throw new RuntimeException('Reward withdrawal amount exceeds available balance.');
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            "INSERT INTO sr_reward_withdrawal_requests
             (account_id, amount, bank_name, bank_account_number, bank_account_holder, requester_note, status, requested_at, updated_at)
             VALUES
             (:account_id, :amount, :bank_name, :bank_account_number, :bank_account_holder, :requester_note, 'pending', :requested_at, :updated_at)"
        );
        $stmt->execute([
            'account_id' => $accountId,
            'amount' => $amount,
            'bank_name' => $bankName,
            'bank_account_number' => $bankAccountNumber,
            'bank_account_holder' => $bankAccountHolder,
            'requester_note' => $requesterNote,
            'requested_at' => $now,
            'updated_at' => $now,
        ]);
        $requestId = (int) $pdo->lastInsertId();

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return $requestId;
}

function sr_reward_withdrawal_requests_for_account(PDO $pdo, int $accountId, int $limit = 20): array
{
    if ($accountId <= 0) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(
        'SELECT id, amount, bank_name, bank_account_number, bank_account_holder, requester_note, status, admin_note, transaction_id, requested_at, processed_at, updated_at
         FROM sr_reward_withdrawal_requests
         WHERE account_id = :account_id
         ORDER BY id DESC
         LIMIT ' . $limit
    );
    $stmt->execute(['account_id' => $accountId]);

    return $stmt->fetchAll();
}

function sr_reward_admin_withdrawal_request_count(PDO $pdo, $status, string $field = 'all', string $keyword = ''): int
{
    $filter = sr_reward_admin_withdrawal_request_filter_sql($status, $field, $keyword);
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM sr_reward_withdrawal_requests r
         LEFT JOIN sr_member_accounts a ON a.id = r.account_id
         {$filter['where']}"
    );
    $stmt->execute($filter['params']);

    return (int) $stmt->fetchColumn();
}

function sr_reward_admin_withdrawal_request_pending_ids(PDO $pdo, string $field = 'all', string $keyword = '', int $limit = 101): array
{
    $limit = max(1, min(500, $limit));
    $filter = sr_reward_admin_withdrawal_request_filter_sql('pending', $field, $keyword);
    $params = $filter['params'];

    $stmt = $pdo->prepare(
        "SELECT r.id
         FROM sr_reward_withdrawal_requests r
         LEFT JOIN sr_member_accounts a ON a.id = r.account_id
         {$filter['where']}
         ORDER BY r.id ASC
         LIMIT :limit"
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function sr_reward_admin_withdrawal_request_rows(PDO $pdo, array $runtimeConfig, $status, array $pagination, string $field = 'all', string $keyword = ''): array
{
    $filter = sr_reward_admin_withdrawal_request_filter_sql($status, $field, $keyword);
    $params = $filter['params'];

    $stmt = $pdo->prepare(
        "SELECT r.id, r.account_id, r.amount, r.bank_name, r.bank_account_number, r.bank_account_holder,
                r.requester_note, r.status, r.admin_note, r.transaction_id, r.processed_by_account_id,
                r.requested_at, r.processed_at, r.updated_at,
                a.email, a.display_name, a.status AS account_status
         FROM sr_reward_withdrawal_requests r
         LEFT JOIN sr_member_accounts a ON a.id = r.account_id
         {$filter['where']}
         ORDER BY CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END, r.id DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', max(1, (int) ($pagination['per_page'] ?? 20)), PDO::PARAM_INT);
    $stmt->bindValue(':offset', sr_admin_pagination_offset($pagination), PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as $index => $row) {
        if (!is_array($row)) {
            continue;
        }
        $rows[$index]['account_public_hash'] = sr_admin_member_public_hash($runtimeConfig, (int) ($row['account_id'] ?? 0));
    }

    return $rows;
}

function sr_reward_admin_withdrawal_request_filter_sql($status, string $field, string $keyword): array
{
    $where = [];
    $params = [];
    $statuses = is_array($status) ? $status : ($status === '' ? [] : [(string) $status]);
    $statuses = array_values(array_intersect($statuses, ['pending', 'completed', 'rejected', 'canceled']));
    if ($statuses !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('r.status', 'status', $statuses);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $field = in_array($field, ['all', 'member', 'bank', 'note', 'request'], true) ? $field : 'all';
    $keyword = sr_reward_clean_text($keyword, 120);
    if ($keyword !== '') {
        $keywordLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
        if ($field === 'member') {
            $where[] = "(CAST(r.account_id AS CHAR) LIKE :keyword_like ESCAPE '\\\\' OR a.email LIKE :keyword_like ESCAPE '\\\\' OR a.display_name LIKE :keyword_like ESCAPE '\\\\')";
            $params['keyword_like'] = $keywordLike;
        } elseif ($field === 'bank') {
            $where[] = "(r.bank_name LIKE :keyword_like ESCAPE '\\\\' OR r.bank_account_number LIKE :keyword_like ESCAPE '\\\\' OR r.bank_account_holder LIKE :keyword_like ESCAPE '\\\\')";
            $params['keyword_like'] = $keywordLike;
        } elseif ($field === 'note') {
            $where[] = "(r.requester_note LIKE :keyword_like ESCAPE '\\\\' OR r.admin_note LIKE :keyword_like ESCAPE '\\\\')";
            $params['keyword_like'] = $keywordLike;
        } elseif ($field === 'request') {
            $where[] = "(CAST(r.id AS CHAR) LIKE :keyword_like ESCAPE '\\\\' OR CAST(r.transaction_id AS CHAR) LIKE :keyword_like ESCAPE '\\\\')";
            $params['keyword_like'] = $keywordLike;
        } else {
            $where[] = "(CAST(r.id AS CHAR) LIKE :request_keyword_like ESCAPE '\\\\'
                OR CAST(r.transaction_id AS CHAR) LIKE :transaction_keyword_like ESCAPE '\\\\'
                OR CAST(r.account_id AS CHAR) LIKE :account_keyword_like ESCAPE '\\\\'
                OR a.email LIKE :email_keyword_like ESCAPE '\\\\'
                OR a.display_name LIKE :name_keyword_like ESCAPE '\\\\'
                OR r.bank_name LIKE :bank_keyword_like ESCAPE '\\\\'
                OR r.bank_account_number LIKE :bank_number_keyword_like ESCAPE '\\\\'
                OR r.bank_account_holder LIKE :bank_holder_keyword_like ESCAPE '\\\\'
                OR r.requester_note LIKE :requester_note_keyword_like ESCAPE '\\\\'
                OR r.admin_note LIKE :admin_note_keyword_like ESCAPE '\\\\')";
            $params['request_keyword_like'] = $keywordLike;
            $params['transaction_keyword_like'] = $keywordLike;
            $params['account_keyword_like'] = $keywordLike;
            $params['email_keyword_like'] = $keywordLike;
            $params['name_keyword_like'] = $keywordLike;
            $params['bank_keyword_like'] = $keywordLike;
            $params['bank_number_keyword_like'] = $keywordLike;
            $params['bank_holder_keyword_like'] = $keywordLike;
            $params['requester_note_keyword_like'] = $keywordLike;
            $params['admin_note_keyword_like'] = $keywordLike;
        }
    }

    return [
        'where' => $where === [] ? '' : 'WHERE ' . implode(' AND ', $where),
        'params' => $params,
    ];
}

function sr_reward_complete_withdrawal_request(PDO $pdo, int $requestId, int $adminAccountId, string $adminNote): int
{
    $adminNote = sr_reward_clean_text($adminNote, 255);
    if ($requestId <= 0 || $adminAccountId <= 0) {
        throw new InvalidArgumentException('Reward withdrawal request and admin account are required.');
    }
    if ($adminNote === '') {
        throw new InvalidArgumentException('Reward withdrawal admin note is required.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM sr_reward_withdrawal_requests WHERE id = :id LIMIT 1' . sr_ledger_for_update_clause($pdo));
        $stmt->execute(['id' => $requestId]);
        $request = $stmt->fetch();
        if (!is_array($request) || (string) ($request['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Reward withdrawal request is not pending.');
        }

        $transactionId = sr_reward_create_transaction($pdo, [
            'account_id' => (int) $request['account_id'],
            'amount' => -abs((int) $request['amount']),
            'transaction_type' => 'withdraw',
            'reason' => '적립금 출금 신청 #' . (string) $requestId . ' 완료',
            'reference_type' => 'reward_withdrawal',
            'reference_id' => 'reward_withdrawal:' . (string) $requestId,
            'created_by_account_id' => $adminAccountId,
        ]);

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            "UPDATE sr_reward_withdrawal_requests
             SET status = 'completed',
                 admin_note = :admin_note,
                 transaction_id = :transaction_id,
                 processed_by_account_id = :processed_by_account_id,
                 processed_at = :processed_at,
                 updated_at = :updated_at
             WHERE id = :id"
        );
        $stmt->execute([
            'admin_note' => $adminNote,
            'transaction_id' => $transactionId,
            'processed_by_account_id' => $adminAccountId,
            'processed_at' => $now,
            'updated_at' => $now,
            'id' => $requestId,
        ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return $transactionId;
}

function sr_reward_reject_withdrawal_request(PDO $pdo, int $requestId, int $adminAccountId, string $adminNote): void
{
    $adminNote = sr_reward_clean_text($adminNote, 255);
    if ($requestId <= 0 || $adminAccountId <= 0) {
        throw new InvalidArgumentException('Reward withdrawal request and admin account are required.');
    }
    if ($adminNote === '') {
        throw new InvalidArgumentException('Reward withdrawal rejection note is required.');
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        "UPDATE sr_reward_withdrawal_requests
         SET status = 'rejected',
             admin_note = :admin_note,
             processed_by_account_id = :processed_by_account_id,
             processed_at = :processed_at,
             updated_at = :updated_at
         WHERE id = :id
           AND status = 'pending'"
    );
    $stmt->execute([
        'admin_note' => $adminNote,
        'processed_by_account_id' => $adminAccountId,
        'processed_at' => $now,
        'updated_at' => $now,
        'id' => $requestId,
    ]);
    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('Reward withdrawal request is not pending.');
    }
}

function sr_reward_cancel_withdrawal_request(PDO $pdo, int $requestId, int $accountId): void
{
    if ($requestId <= 0 || $accountId <= 0) {
        throw new InvalidArgumentException('Reward withdrawal request and account are required.');
    }

    $stmt = $pdo->prepare(
        "UPDATE sr_reward_withdrawal_requests
         SET status = 'canceled',
             updated_at = :updated_at
         WHERE id = :id
           AND account_id = :account_id
           AND status = 'pending'"
    );
    $stmt->execute([
        'updated_at' => date('Y-m-d H:i:s'),
        'id' => $requestId,
        'account_id' => $accountId,
    ]);
    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('Reward withdrawal request could not be canceled.');
    }
}

function sr_reward_transaction_by_id(PDO $pdo, int $transactionId): ?array
{
    if ($transactionId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, account_id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_by_account_id, created_at
         FROM sr_reward_transactions
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $transactionId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_reward_transaction_by_reference(PDO $pdo, string $referenceType, string $referenceId): ?array
{
    $referenceType = sr_reward_clean_key($referenceType, 60);
    $referenceId = sr_reward_clean_reference_id($referenceId, 120);
    if ($referenceType === '' || $referenceId === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, account_id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_by_account_id, created_at
         FROM sr_reward_transactions
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

function sr_reward_notify_transaction_created(PDO $pdo, int $transactionId): ?int
{
    $createAccountEventFunction = sr_reward_notification_event_function($pdo);
    if ($createAccountEventFunction === '') {
        return null;
    }

    $transaction = sr_reward_transaction_by_id($pdo, $transactionId);
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
        $caseSetting = sr_reward_notification_setting_for_event(sr_reward_settings($pdo), $eventKey);
        if (is_array($caseSetting)) {
            if (empty($caseSetting['enabled'])) {
                return null;
            }
            $channels = sr_reward_notification_channels_from_value($caseSetting['channels'] ?? ['site']);
        }

        $payload = [
            'account_id' => (int) $transaction['account_id'],
            'module_key' => 'reward',
            'event_key' => $eventKey,
            'created_by_account_id' => (int) ($transaction['created_by_account_id'] ?? 0),
            'metadata' => sr_reward_transaction_notification_metadata($transaction),
        ];
        if ($channels !== []) {
            $payload['channels'] = $channels;
        }

        return $createAccountEventFunction($pdo, $payload);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'reward_transaction_notification');
        return null;
    }
}

function sr_reward_notification_event_function(PDO $pdo): string
{
    return sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_account_event_function');
}

function sr_reward_transaction_notification_metadata(array $transaction): array
{
    $amount = (int) ($transaction['amount'] ?? 0);
    $transactionType = (string) ($transaction['transaction_type'] ?? '');
    $referenceType = (string) ($transaction['reference_type'] ?? '');

    return [
        'transaction_id' => (int) ($transaction['id'] ?? 0),
        'asset_label' => '적립금',
        'amount' => number_format($amount),
        'amount_abs' => number_format(abs($amount)),
        'amount_signed' => ($amount > 0 ? '+' : '') . number_format($amount),
        'balance_after' => number_format((int) ($transaction['balance_after'] ?? 0)),
        'transaction_type' => $transactionType,
        'transaction_type_label' => sr_reward_transaction_type_label($transactionType),
        'reason' => (string) ($transaction['reason'] ?? ''),
        'reference_type' => $referenceType,
        'reference_type_label' => sr_reward_reference_type_label($referenceType),
        'reference_id' => (string) ($transaction['reference_id'] ?? ''),
        'created_at' => (string) ($transaction['created_at'] ?? ''),
    ];
}
