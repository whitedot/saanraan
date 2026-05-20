<?php

declare(strict_types=1);

function sr_community_asset_modules(): array
{
    return [
        'point' => [
            'label' => '포인트',
            'module_key' => 'point',
            'helper' => SR_ROOT . '/modules/point/helpers.php',
            'balance_function' => 'sr_point_balance',
            'transaction_function' => 'sr_point_create_transaction',
            'use_type' => 'use',
            'credit_type' => 'grant',
            'refund_type' => 'refund',
        ],
        'deposit' => [
            'label' => '예치금',
            'module_key' => 'deposit',
            'helper' => SR_ROOT . '/modules/deposit/helpers.php',
            'balance_function' => 'sr_deposit_balance',
            'transaction_function' => 'sr_deposit_create_transaction',
            'use_type' => 'use',
            'credit_type' => 'deposit',
            'refund_type' => 'refund',
        ],
        'reward' => [
            'label' => '적립금',
            'module_key' => 'reward',
            'helper' => SR_ROOT . '/modules/reward/helpers.php',
            'balance_function' => 'sr_reward_balance',
            'transaction_function' => 'sr_reward_create_transaction',
            'use_type' => 'use',
            'credit_type' => 'grant',
            'refund_type' => 'refund',
        ],
    ];
}

function sr_community_asset_charge_policies(): array
{
    return [
        'once' => '최초 1회',
        'every_view' => '매 열람',
        'every_download' => '매 다운로드',
        'every_action' => '매 활동',
    ];
}

function sr_community_asset_module_is_available(PDO $pdo, string $assetModule): bool
{
    $modules = sr_community_asset_modules();
    if (!isset($modules[$assetModule])) {
        return false;
    }

    $module = $modules[$assetModule];
    $helper = (string) ($module['helper'] ?? '');
    if (!sr_module_enabled($pdo, (string) ($module['module_key'] ?? '')) || !is_file($helper)) {
        return false;
    }

    require_once $helper;

    return function_exists((string) ($module['balance_function'] ?? ''))
        && function_exists((string) ($module['transaction_function'] ?? ''));
}

function sr_community_asset_module_options(PDO $pdo): array
{
    $available = [];
    foreach (sr_community_asset_modules() as $assetModule => $module) {
        if (sr_community_asset_module_is_available($pdo, (string) $assetModule)) {
            $available[$assetModule] = $module;
        }
    }

    return $available;
}

function sr_community_asset_module_label(string $assetModule): string
{
    $modules = sr_community_asset_modules();
    return isset($modules[$assetModule]) ? (string) $modules[$assetModule]['label'] : '회원 자산';
}

function sr_community_asset_module_key(string $value): string
{
    return isset(sr_community_asset_modules()[$value]) ? $value : 'point';
}

function sr_community_asset_charge_policy(string $value, string $fallback = 'once'): string
{
    return isset(sr_community_asset_charge_policies()[$value]) ? $value : $fallback;
}

function sr_community_asset_balance(PDO $pdo, string $assetModule, int $accountId): int
{
    if (!sr_community_asset_module_is_available($pdo, $assetModule)) {
        return 0;
    }

    $module = sr_community_asset_modules()[$assetModule];
    $balanceFunction = (string) $module['balance_function'];

    return (int) $balanceFunction($pdo, $accountId);
}

function sr_community_create_asset_transaction(PDO $pdo, string $assetModule, array $data): int
{
    if (!sr_community_asset_module_is_available($pdo, $assetModule)) {
        throw new RuntimeException('Community asset module is not available.');
    }

    $module = sr_community_asset_modules()[$assetModule];
    $transactionFunction = (string) $module['transaction_function'];

    return (int) $transactionFunction($pdo, $data);
}

function sr_community_asset_board_setting(PDO $pdo, array $board, array $settings, string $key, mixed $default): string
{
    $value = sr_community_board_setting_value($pdo, (int) ($board['id'] ?? 0), $key);
    if (is_string($value) && $value !== '') {
        return $value;
    }

    return (string) ($settings[$key] ?? $default);
}

function sr_community_asset_bool_config(PDO $pdo, array $board, array $settings, string $key, bool $default = false): bool
{
    return sr_community_bool_setting(sr_community_asset_board_setting($pdo, $board, $settings, $key, $default ? '1' : '0'));
}

function sr_community_asset_amount_config(PDO $pdo, array $board, array $settings, string $key): int
{
    return min(999999999, max(0, (int) sr_community_asset_board_setting($pdo, $board, $settings, $key, '0')));
}

function sr_community_asset_event_config(PDO $pdo, array $board, array $settings, string $prefix, string $defaultPolicy = 'once'): array
{
    $enabled = sr_community_asset_bool_config($pdo, $board, $settings, $prefix . '_enabled');
    $assetModule = sr_community_asset_module_key(sr_community_asset_board_setting($pdo, $board, $settings, $prefix . '_asset_module', 'point'));
    $amount = sr_community_asset_amount_config($pdo, $board, $settings, $prefix . '_amount');
    $policy = sr_community_asset_charge_policy(sr_community_asset_board_setting($pdo, $board, $settings, $prefix . '_charge_policy', $defaultPolicy), $defaultPolicy);

    return [
        'enabled' => $enabled,
        'asset_module' => $assetModule,
        'amount' => $amount,
        'charge_policy' => $policy,
    ];
}

function sr_community_asset_event_required(array $config): bool
{
    return !empty($config['enabled']) && (int) ($config['amount'] ?? 0) > 0;
}

function sr_community_save_board_asset_settings(PDO $pdo, int $boardId, array $assetSettings): void
{
    foreach ($assetSettings as $settingKey => $settingValue) {
        $valueType = is_bool($settingValue) ? 'bool' : (is_int($settingValue) ? 'int' : 'string');
        $settingValue = is_bool($settingValue) ? ($settingValue ? '1' : '0') : (string) $settingValue;
        sr_community_set_board_setting($pdo, $boardId, (string) $settingKey, $settingValue, $valueType);
    }
}

function sr_community_asset_dedupe_key(string $assetModule, int $accountId, string $eventKey, int $subjectId): string
{
    return 'community.' . $eventKey . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId;
}

function sr_community_asset_log(PDO $pdo, string $dedupeKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_community_asset_logs
         WHERE dedupe_key = :dedupe_key
         LIMIT 1'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_has_asset_event(PDO $pdo, string $assetModule, int $accountId, string $eventKey, int $subjectId): bool
{
    $log = sr_community_asset_log($pdo, sr_community_asset_dedupe_key($assetModule, $accountId, $eventKey, $subjectId));

    return is_array($log) && (int) ($log['transaction_id'] ?? 0) > 0;
}

function sr_community_insert_asset_log_placeholder(PDO $pdo, array $row): bool
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO sr_community_asset_logs
            (account_id, asset_module, transaction_id, reference_type, reference_id, subject_type, subject_id, event_key, direction, charge_policy, amount, dedupe_key, created_at)
         VALUES
            (:account_id, :asset_module, 0, :reference_type, :reference_id, :subject_type, :subject_id, :event_key, :direction, :charge_policy, :amount, :dedupe_key, :created_at)'
    );
    $stmt->execute([
        'account_id' => (int) $row['account_id'],
        'asset_module' => (string) $row['asset_module'],
        'reference_type' => (string) $row['reference_type'],
        'reference_id' => (string) $row['reference_id'],
        'subject_type' => (string) $row['subject_type'],
        'subject_id' => (int) $row['subject_id'],
        'event_key' => (string) $row['event_key'],
        'direction' => (string) $row['direction'],
        'charge_policy' => (string) $row['charge_policy'],
        'amount' => (int) $row['amount'],
        'dedupe_key' => (string) $row['dedupe_key'],
        'created_at' => sr_now(),
    ]);

    return $stmt->rowCount() > 0;
}

function sr_community_update_asset_log_transaction(PDO $pdo, string $dedupeKey, int $transactionId): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_community_asset_logs
         SET transaction_id = :transaction_id
         WHERE dedupe_key = :dedupe_key'
    );
    $stmt->execute([
        'transaction_id' => $transactionId,
        'dedupe_key' => $dedupeKey,
    ]);
}

function sr_community_delete_asset_log_placeholder(PDO $pdo, string $dedupeKey): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM sr_community_asset_logs
         WHERE dedupe_key = :dedupe_key
           AND transaction_id = 0'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
}

function sr_community_run_asset_event(PDO $pdo, array $config, int $accountId, string $eventKey, string $subjectType, int $subjectId, string $direction, string $reason): array
{
    $assetModule = (string) ($config['asset_module'] ?? '');
    $amount = (int) ($config['amount'] ?? 0);
    $chargePolicy = (string) ($config['charge_policy'] ?? 'once');

    if ($accountId <= 0 || $subjectId <= 0 || $amount <= 0 || !isset(sr_community_asset_modules()[$assetModule])) {
        return ['allowed' => true, 'processed' => false, 'message' => ''];
    }

    if (!sr_community_asset_module_is_available($pdo, $assetModule)) {
        return [
            'allowed' => false,
            'processed' => false,
            'asset_module' => $assetModule,
            'asset_label' => sr_community_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => sr_community_asset_module_label($assetModule) . ' 모듈을 사용할 수 없습니다.',
        ];
    }

    $once = in_array($chargePolicy, ['once'], true) || in_array($direction, ['grant', 'refund'], true);
    if ($once && sr_community_has_asset_event($pdo, $assetModule, $accountId, $eventKey, $subjectId)) {
        return [
            'allowed' => true,
            'processed' => false,
            'already_processed' => true,
            'asset_module' => $assetModule,
            'asset_label' => sr_community_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => '',
        ];
    }

    if ($direction === 'use' && sr_community_asset_balance($pdo, $assetModule, $accountId) < $amount) {
        return [
            'allowed' => false,
            'processed' => false,
            'asset_module' => $assetModule,
            'asset_label' => sr_community_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => sr_community_asset_module_label($assetModule) . ' 잔액이 부족합니다.',
        ];
    }

    $module = sr_community_asset_modules()[$assetModule];
    $dedupeKey = $once
        ? sr_community_asset_dedupe_key($assetModule, $accountId, $eventKey, $subjectId)
        : 'community.' . $eventKey . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId . ':' . bin2hex(random_bytes(8));
    $inserted = sr_community_insert_asset_log_placeholder($pdo, [
        'account_id' => $accountId,
        'asset_module' => $assetModule,
        'reference_type' => $subjectType,
        'reference_id' => (string) $subjectId,
        'subject_type' => $subjectType,
        'subject_id' => $subjectId,
        'event_key' => $eventKey,
        'direction' => $direction,
        'charge_policy' => $chargePolicy,
        'amount' => $amount,
        'dedupe_key' => $dedupeKey,
    ]);
    if (!$inserted) {
        return [
            'allowed' => true,
            'processed' => false,
            'already_processed' => true,
            'asset_module' => $assetModule,
            'asset_label' => sr_community_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => '',
        ];
    }

    $signedAmount = $direction === 'use' ? -$amount : $amount;
    $transactionType = $direction === 'use'
        ? (string) ($module['use_type'] ?? 'use')
        : ($direction === 'refund' ? (string) ($module['refund_type'] ?? 'refund') : (string) ($module['credit_type'] ?? 'grant'));

    try {
        $transactionId = sr_community_create_asset_transaction($pdo, $assetModule, [
            'account_id' => $accountId,
            'amount' => $signedAmount,
            'transaction_type' => $transactionType,
            'reason' => $reason,
            'reference_type' => $subjectType,
            'reference_id' => (string) $subjectId,
            'created_by_account_id' => null,
        ]);
        sr_community_update_asset_log_transaction($pdo, $dedupeKey, $transactionId);
    } catch (Throwable $exception) {
        sr_community_delete_asset_log_placeholder($pdo, $dedupeKey);
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'community_asset_event_failed');
        }

        return [
            'allowed' => false,
            'processed' => false,
            'asset_module' => $assetModule,
            'asset_label' => sr_community_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => sr_community_asset_module_label($assetModule) . ' 처리에 실패했습니다.',
        ];
    }

    return [
        'allowed' => true,
        'processed' => true,
        'asset_module' => $assetModule,
        'asset_label' => sr_community_asset_module_label($assetModule),
        'amount' => $amount,
        'direction' => $direction,
        'message' => '',
    ];
}

function sr_community_asset_reversal_config(array $originalLog): array
{
    return [
        'enabled' => true,
        'asset_module' => (string) ($originalLog['asset_module'] ?? 'point'),
        'amount' => (int) ($originalLog['amount'] ?? 0),
        'charge_policy' => 'once',
    ];
}

function sr_community_reverse_asset_grant(PDO $pdo, int $accountId, string $grantEventKey, string $subjectType, int $subjectId, string $reversalEventKey, string $reason): array
{
    foreach (array_keys(sr_community_asset_modules()) as $assetModule) {
        $original = sr_community_asset_log($pdo, sr_community_asset_dedupe_key((string) $assetModule, $accountId, $grantEventKey, $subjectId));
        if (!is_array($original) || (int) ($original['transaction_id'] ?? 0) < 1 || (string) ($original['direction'] ?? '') !== 'grant') {
            continue;
        }

        return sr_community_run_asset_event($pdo, sr_community_asset_reversal_config($original), $accountId, $reversalEventKey, $subjectType, $subjectId, 'use', $reason);
    }

    return ['allowed' => true, 'processed' => false, 'message' => ''];
}
