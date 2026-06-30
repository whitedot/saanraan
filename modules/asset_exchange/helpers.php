<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/helpers/common.php';

function sr_asset_exchange_execute_rate_limit_bucket(): string
{
    return 'asset_exchange.execute.account';
}

function sr_asset_exchange_execute_rate_limit_window_seconds(): int
{
    return 60;
}

function sr_asset_exchange_execute_rate_limit_max_attempts(): int
{
    return 10;
}

function sr_asset_exchange_submit_token_lifetime_seconds(): int
{
    return 600;
}

function sr_asset_exchange_quote_token_hash(int $policyId, int $amount, array $quote): string
{
    return hash('sha256', implode('|', [
        (string) $policyId,
        (string) $amount,
        (string) ((int) ($quote['request_amount'] ?? 0)),
        (string) ((int) ($quote['deposit_before_fee'] ?? 0)),
        (string) ((int) ($quote['fee_amount'] ?? 0)),
        (string) ((int) ($quote['deposit_amount'] ?? 0)),
    ]));
}

function sr_asset_exchange_submit_tokens(): array
{
    return isset($_SESSION['sr_asset_exchange_submit_tokens']) && is_array($_SESSION['sr_asset_exchange_submit_tokens'])
        ? $_SESSION['sr_asset_exchange_submit_tokens']
        : [];
}

function sr_asset_exchange_prune_submit_tokens(array $tokens): array
{
    $cutoff = time() - sr_asset_exchange_submit_token_lifetime_seconds();
    foreach ($tokens as $token => $payload) {
        $createdAt = is_array($payload) ? (int) ($payload['created_at'] ?? 0) : (int) $payload;
        if ($createdAt < $cutoff) {
            unset($tokens[$token]);
        }
    }

    if (count($tokens) > 10) {
        uasort($tokens, static function (mixed $left, mixed $right): int {
            $leftCreatedAt = is_array($left) ? (int) ($left['created_at'] ?? 0) : (int) $left;
            $rightCreatedAt = is_array($right) ? (int) ($right['created_at'] ?? 0) : (int) $right;
            return $leftCreatedAt <=> $rightCreatedAt;
        });
        $tokens = array_slice($tokens, -10, null, true);
    }

    return $tokens;
}

function sr_asset_exchange_store_submit_token(int $policyId, int $amount, array $quote): string
{
    $token = bin2hex(random_bytes(16));
    $tokens = sr_asset_exchange_prune_submit_tokens(sr_asset_exchange_submit_tokens());
    $tokens[$token] = [
        'created_at' => time(),
        'policy_id' => $policyId,
        'amount' => $amount,
        'quote_hash' => sr_asset_exchange_quote_token_hash($policyId, $amount, $quote),
    ];
    $_SESSION['sr_asset_exchange_submit_tokens'] = $tokens;

    return $token;
}

function sr_asset_exchange_consume_submit_token(string $token, int $policyId, int $amount, array $quote): bool
{
    $tokens = sr_asset_exchange_prune_submit_tokens(sr_asset_exchange_submit_tokens());
    $payload = isset($tokens[$token]) && is_array($tokens[$token]) ? $tokens[$token] : null;
    unset($tokens[$token]);
    $_SESSION['sr_asset_exchange_submit_tokens'] = $tokens;
    if (!is_array($payload)) {
        return false;
    }

    return (int) ($payload['policy_id'] ?? 0) === $policyId
        && (int) ($payload['amount'] ?? 0) === $amount
        && hash_equals((string) ($payload['quote_hash'] ?? ''), sr_asset_exchange_quote_token_hash($policyId, $amount, $quote));
}

function sr_asset_exchange_execute_rate_limited(PDO $pdo, int $accountId): bool
{
    if ($accountId < 1) {
        return true;
    }

    return sr_rate_limit_count(
        $pdo,
        sr_asset_exchange_execute_rate_limit_bucket(),
        (string) $accountId,
        sr_asset_exchange_execute_rate_limit_window_seconds()
    ) >= sr_asset_exchange_execute_rate_limit_max_attempts();
}

function sr_asset_exchange_record_execute_attempt(PDO $pdo, int $accountId): void
{
    if ($accountId < 1) {
        return;
    }

    sr_rate_limit_increment(
        $pdo,
        sr_asset_exchange_execute_rate_limit_bucket(),
        (string) $accountId,
        sr_asset_exchange_execute_rate_limit_window_seconds()
    );
}

function sr_asset_exchange_time_html(?string $value, string $emptyText = ''): string
{
    return sr_relative_time_html($value, $emptyText);
}

function sr_asset_exchange_log_status_label(string $status): string
{
    return match ($status) {
        'completed' => '완료',
        'failed' => '실패',
        default => $status,
    };
}

function sr_asset_exchange_canonical_asset_keys(): array
{
    return ['point', 'reward', 'deposit'];
}

function sr_asset_exchange_canonical_asset_key_set(): array
{
    return array_fill_keys(sr_asset_exchange_canonical_asset_keys(), true);
}

function sr_asset_exchange_is_canonical_asset_key(string $moduleKey): bool
{
    return isset(sr_asset_exchange_canonical_asset_key_set()[$moduleKey]);
}

function sr_asset_exchange_canonical_policy_pairs(): array
{
    $pairs = [];
    foreach (sr_asset_exchange_canonical_asset_keys() as $fromModuleKey) {
        foreach (sr_asset_exchange_canonical_asset_keys() as $toModuleKey) {
            if ($fromModuleKey === $toModuleKey) {
                continue;
            }

            $pairs[] = [
                'from_module_key' => $fromModuleKey,
                'to_module_key' => $toModuleKey,
            ];
        }
    }

    return $pairs;
}

function sr_asset_exchange_canonical_policy_sort_order(string $fromModuleKey, string $toModuleKey): int
{
    foreach (sr_asset_exchange_canonical_policy_pairs() as $index => $pair) {
        if ((string) $pair['from_module_key'] === $fromModuleKey && (string) $pair['to_module_key'] === $toModuleKey) {
            return $index;
        }
    }

    return 0;
}

function sr_asset_exchange_is_canonical_pair(string $fromModuleKey, string $toModuleKey): bool
{
    $fromModuleKey = sr_asset_exchange_clean_module_key($fromModuleKey);
    $toModuleKey = sr_asset_exchange_clean_module_key($toModuleKey);

    return $fromModuleKey !== ''
        && $toModuleKey !== ''
        && $fromModuleKey !== $toModuleKey
        && sr_asset_exchange_is_canonical_asset_key($fromModuleKey)
        && sr_asset_exchange_is_canonical_asset_key($toModuleKey);
}

function sr_asset_exchange_policy_slot_key(string $fromModuleKey, string $toModuleKey): string
{
    return $fromModuleKey . '>' . $toModuleKey;
}

function sr_asset_exchange_relative_value_setting_keys(): array
{
    return [
        'point' => 'relative_value_point',
        'reward' => 'relative_value_reward',
        'deposit' => 'relative_value_deposit',
    ];
}

function sr_asset_exchange_relative_values_from_settings(array $settings): array
{
    $settings = sr_asset_exchange_normalize_settings($settings);
    $values = [];
    foreach (sr_asset_exchange_relative_value_setting_keys() as $moduleKey => $settingKey) {
        $values[$moduleKey] = sr_asset_exchange_positive_int(
            $settings[$settingKey] ?? '1',
            '자산별 환산 기준은 1 이상의 정수로 입력하세요.'
        );
    }

    return $values;
}

function sr_asset_exchange_rate_parts_for_pair(array $settings, string $fromModuleKey, string $toModuleKey): array
{
    if (!sr_asset_exchange_is_canonical_pair($fromModuleKey, $toModuleKey)) {
        throw new InvalidArgumentException('환전 정책은 포인트, 적립금, 예치금 사이에서만 계산할 수 있습니다.');
    }

    $values = sr_asset_exchange_relative_values_from_settings($settings);

    return [$values[$toModuleKey], $values[$fromModuleKey]];
}

function sr_asset_exchange_assets(PDO $pdo): array
{
    $assets = [];
    foreach (sr_enabled_module_contract_files($pdo, 'asset-exchange.php') as $moduleKey => $contractFile) {
        $moduleKey = (string) $moduleKey;
        if (!sr_asset_exchange_is_canonical_asset_key($moduleKey)) {
            continue;
        }

        $contract = sr_load_module_contract_file($moduleKey, $contractFile);
        if (!is_array($contract)) {
            continue;
        }

        $helpers = (string) ($contract['helpers'] ?? '');
        if ($helpers !== '' && preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helpers) === 1) {
            $helperPath = SR_ROOT . '/modules/' . $moduleKey . '/' . $helpers;
            if (is_file($helperPath)) {
                require_once $helperPath;
            }
        }

        $balanceFunction = (string) ($contract['balance_function'] ?? '');
        $transactionFunction = (string) ($contract['transaction_function'] ?? '');
        if (!function_exists($balanceFunction) || !function_exists($transactionFunction)) {
            continue;
        }
        $availableFunction = (string) ($contract['available_function'] ?? '');
        if ($availableFunction !== '') {
            if (!function_exists($availableFunction) || !$availableFunction($pdo)) {
                continue;
            }
        }

        $labelFunction = (string) ($contract['label_function'] ?? '');
        $unitFunction = (string) ($contract['unit_function'] ?? '');
        $label = (string) ($contract['label'] ?? $moduleKey);
        if (function_exists($labelFunction)) {
            try {
                $resolvedLabel = trim((string) $labelFunction($pdo));
                if ($resolvedLabel !== '') {
                    $label = $resolvedLabel;
                }
            } catch (Throwable) {
                $label = (string) ($contract['label'] ?? $moduleKey);
            }
        }
        $unitLabel = (string) ($contract['unit_label'] ?? '');
        if (function_exists($unitFunction)) {
            try {
                $unitLabel = (string) $unitFunction($pdo);
            } catch (Throwable) {
                $unitLabel = (string) ($contract['unit_label'] ?? '');
            }
        }
        $assets[$moduleKey] = [
            'module_key' => $moduleKey,
            'label' => $label,
            'unit_label' => $unitLabel,
            'cash_like' => !empty($contract['cash_like']),
            'balance_function' => $balanceFunction,
            'transaction_function' => $transactionFunction,
            'exchange_out_type' => (string) ($contract['exchange_out_type'] ?? 'exchange_out'),
            'exchange_in_type' => (string) ($contract['exchange_in_type'] ?? 'exchange_in'),
            'exchange_fee_type' => (string) ($contract['exchange_fee_type'] ?? 'exchange_fee'),
        ];
    }

    uasort($assets, static function (array $left, array $right): int {
        return strcmp((string) $left['label'], (string) $right['label']);
    });

    return $assets;
}

function sr_asset_exchange_notification_event_keys(): array
{
    return [
        'transaction.exchange_out',
        'transaction.exchange_in',
        'transaction.exchange_fee',
    ];
}

function sr_asset_exchange_notification_groups(PDO $pdo): array
{
    $groups = [];
    $eventKeys = array_fill_keys(sr_asset_exchange_notification_event_keys(), true);

    foreach (sr_asset_exchange_assets($pdo) as $moduleKey => $asset) {
        $moduleKey = (string) $moduleKey;
        if (preg_match('/\A[a-z][a-z0-9_]*\z/', $moduleKey) !== 1) {
            continue;
        }

        $prefix = 'sr_' . $moduleKey;
        $casesFunction = $prefix . '_notification_cases';
        $caseSettingsFunction = $prefix . '_notification_case_settings_from_value';
        $channelsFunction = $prefix . '_notification_channels_from_value';
        $channelOptionsFunction = $prefix . '_notification_channel_options';
        $settingsFunction = $prefix . '_settings';
        $saveSettingsFunction = $prefix . '_save_settings';
        if (!function_exists($casesFunction)
            || !function_exists($caseSettingsFunction)
            || !function_exists($channelsFunction)
            || !function_exists($channelOptionsFunction)
            || !function_exists($settingsFunction)
            || !function_exists($saveSettingsFunction)
        ) {
            continue;
        }

        $moduleCases = $casesFunction();
        $exchangeCases = [];
        foreach ($moduleCases as $caseKey => $case) {
            $eventKey = (string) ($case['event_key'] ?? '');
            if (isset($eventKeys[$eventKey])) {
                $exchangeCases[(string) $caseKey] = $case;
            }
        }
        if ($exchangeCases === []) {
            continue;
        }

        $moduleSettings = $settingsFunction($pdo);
        $groups[$moduleKey] = [
            'module_key' => $moduleKey,
            'label' => (string) ($asset['label'] ?? $moduleKey),
            'cases' => $exchangeCases,
            'all_case_settings' => $caseSettingsFunction($moduleSettings['notification_cases'] ?? []),
            'channel_options' => $channelOptionsFunction($pdo),
            'channels_function' => $channelsFunction,
            'settings_function' => $settingsFunction,
            'save_settings_function' => $saveSettingsFunction,
        ];
    }

    return $groups;
}

function sr_asset_exchange_default_settings(): array
{
    return [
        'exchange_enabled' => '1',
        'policy_default_status' => 'disabled',
        'relative_value_point' => '1',
        'relative_value_reward' => '1',
        'relative_value_deposit' => '1',
        'policy_default_min_amount' => '1',
        'policy_default_max_amount' => '',
        'policy_default_rounding_mode' => 'floor',
        'policy_default_fee_trigger' => 'none',
        'policy_default_fee_basis' => 'to_amount',
        'policy_default_fee_type' => 'rate',
        'policy_default_fee_rate_numerator' => '0',
        'policy_default_fee_fixed_amount' => '0',
        'policy_default_fee_min_amount' => '',
        'policy_default_fee_max_amount' => '',
    ];
}

function sr_asset_exchange_settings(PDO $pdo): array
{
    return sr_asset_exchange_normalize_settings(array_merge(
        sr_asset_exchange_default_settings(),
        sr_module_settings($pdo, 'asset_exchange')
    ));
}

function sr_asset_exchange_normalize_settings(array $settings): array
{
    $defaults = sr_asset_exchange_default_settings();
    $normalized = array_merge($defaults, $settings);

    $normalized['exchange_enabled'] = sr_truthy($normalized['exchange_enabled'] ?? false) ? '1' : '0';

    $status = (string) ($normalized['policy_default_status'] ?? 'disabled');
    $normalized['policy_default_status'] = in_array($status, ['enabled', 'disabled'], true) ? $status : 'disabled';

    $roundingMode = (string) ($normalized['policy_default_rounding_mode'] ?? 'floor');
    $normalized['policy_default_rounding_mode'] = in_array($roundingMode, ['floor', 'round', 'ceil'], true) ? $roundingMode : 'floor';

    $feeTrigger = (string) ($normalized['policy_default_fee_trigger'] ?? 'none');
    $normalized['policy_default_fee_trigger'] = in_array($feeTrigger, ['none', 'always', 'reexchange'], true) ? $feeTrigger : 'none';

    $feeType = (string) ($normalized['policy_default_fee_type'] ?? 'rate');
    $normalized['policy_default_fee_type'] = in_array($feeType, ['rate', 'fixed'], true) ? $feeType : 'rate';

    $feeBasis = (string) ($normalized['policy_default_fee_basis'] ?? 'to_amount');
    $normalized['policy_default_fee_basis'] = in_array($feeBasis, ['from_amount', 'to_amount'], true) ? $feeBasis : 'to_amount';
    if ($normalized['policy_default_fee_trigger'] === 'none') {
        $normalized['policy_default_fee_basis'] = 'to_amount';
        $normalized['policy_default_fee_type'] = 'rate';
        $normalized['policy_default_fee_rate_numerator'] = '0';
        $normalized['policy_default_fee_fixed_amount'] = '0';
        $normalized['policy_default_fee_min_amount'] = '';
        $normalized['policy_default_fee_max_amount'] = '';
    } elseif ($normalized['policy_default_fee_type'] === 'fixed') {
        $normalized['policy_default_fee_basis'] = 'to_amount';
        $normalized['policy_default_fee_rate_numerator'] = '0';
    } else {
        $normalized['policy_default_fee_fixed_amount'] = '0';
    }

    foreach (sr_asset_exchange_relative_value_setting_keys() as $key) {
        $normalized[$key] = trim((string) ($normalized[$key] ?? '1'));
        if ($normalized[$key] === '') {
            $normalized[$key] = '1';
        }
    }

    foreach ([
        'policy_default_min_amount',
        'policy_default_fee_rate_numerator',
        'policy_default_fee_fixed_amount',
    ] as $key) {
        $normalized[$key] = trim((string) ($normalized[$key] ?? $defaults[$key]));
    }
    foreach ([
        'policy_default_max_amount',
        'policy_default_fee_min_amount',
        'policy_default_fee_max_amount',
    ] as $key) {
        $normalized[$key] = trim((string) ($normalized[$key] ?? ''));
    }

    return $normalized;
}

function sr_asset_exchange_policy_defaults_from_settings(array $settings): array
{
    $settings = sr_asset_exchange_normalize_settings($settings);

    return [
        'id' => '',
        'from_module_key' => '',
        'to_module_key' => '',
        'status' => (string) $settings['policy_default_status'],
        'min_amount' => (string) $settings['policy_default_min_amount'],
        'max_amount' => (string) $settings['policy_default_max_amount'],
        'rounding_mode' => (string) $settings['policy_default_rounding_mode'],
        'fee_trigger' => (string) $settings['policy_default_fee_trigger'],
        'fee_basis' => (string) $settings['policy_default_fee_basis'],
        'fee_type' => (string) $settings['policy_default_fee_type'],
        'fee_rate_numerator' => (string) $settings['policy_default_fee_rate_numerator'],
        'fee_fixed_amount' => (string) $settings['policy_default_fee_fixed_amount'],
        'fee_min_amount' => (string) $settings['policy_default_fee_min_amount'],
        'fee_max_amount' => (string) $settings['policy_default_fee_max_amount'],
    ];
}

function sr_asset_exchange_validate_settings(array $settings): array
{
    $rawStatus = (string) ($settings['policy_default_status'] ?? '');
    if (!in_array($rawStatus, ['enabled', 'disabled'], true)) {
        throw new InvalidArgumentException('공통 상태가 올바르지 않습니다.');
    }
    $rawRoundingMode = (string) ($settings['policy_default_rounding_mode'] ?? '');
    if (!in_array($rawRoundingMode, ['floor', 'round', 'ceil'], true)) {
        throw new InvalidArgumentException('공통 반올림 방식이 올바르지 않습니다.');
    }
    $rawFeeTrigger = (string) ($settings['policy_default_fee_trigger'] ?? '');
    if (!in_array($rawFeeTrigger, ['none', 'always', 'reexchange'], true)) {
        throw new InvalidArgumentException('공통 수수료 적용 조건이 올바르지 않습니다.');
    }
    if ($rawFeeTrigger !== 'none') {
        $rawFeeType = (string) ($settings['policy_default_fee_type'] ?? '');
        if (!in_array($rawFeeType, ['rate', 'fixed'], true)) {
            throw new InvalidArgumentException('공통 수수료 방식이 올바르지 않습니다.');
        }
        $rawFeeBasis = (string) ($settings['policy_default_fee_basis'] ?? '');
        if ($rawFeeType === 'rate' && !in_array($rawFeeBasis, ['from_amount', 'to_amount'], true)) {
            throw new InvalidArgumentException('공통 수수료 기준이 올바르지 않습니다.');
        }
    }

    $settings = sr_asset_exchange_normalize_settings($settings);

    foreach (sr_asset_exchange_relative_value_setting_keys() as $settingKey) {
        sr_asset_exchange_positive_int($settings[$settingKey] ?? '1', '자산별 환산 기준은 1 이상의 정수로 입력하세요.');
    }
    $minAmount = sr_asset_exchange_positive_int($settings['policy_default_min_amount'], '공통 최소 환전량은 1 이상이어야 합니다.');
    $maxAmount = sr_asset_exchange_nullable_int($settings['policy_default_max_amount'], '공통 최대 환전량은 0 이상의 정수로 입력하세요.');
    if ($maxAmount !== null && $maxAmount < $minAmount) {
        throw new InvalidArgumentException('공통 최대 환전량은 공통 최소 환전량 이상이어야 합니다.');
    }

    if ((string) $settings['policy_default_fee_trigger'] !== 'none') {
        if ((string) $settings['policy_default_fee_type'] === 'rate') {
            $feeRateNumerator = sr_asset_exchange_optional_non_negative_int($settings['policy_default_fee_rate_numerator'], 0, '공통 정률 수수료는 0 이상의 정수로 입력하세요.');
            if ($feeRateNumerator <= 0) {
                throw new InvalidArgumentException('공통 수수료를 정률로 쓰려면 정률 수수료를 1 이상 입력하세요.');
            }
        } else {
            $feeFixedAmount = sr_asset_exchange_optional_non_negative_int($settings['policy_default_fee_fixed_amount'], 0, '공통 정액 수수료는 0 이상의 정수로 입력하세요.');
            if ($feeFixedAmount <= 0) {
                throw new InvalidArgumentException('공통 수수료를 정액으로 쓰려면 정액 수수료를 1 이상 입력하세요.');
            }
        }
        $feeMinAmount = sr_asset_exchange_nullable_int($settings['policy_default_fee_min_amount'], '공통 최소 수수료는 0 이상의 정수로 입력하세요.');
        $feeMaxAmount = sr_asset_exchange_nullable_int($settings['policy_default_fee_max_amount'], '공통 최대 수수료는 0 이상의 정수로 입력하세요.');
        if ($feeMaxAmount !== null && $feeMinAmount !== null && $feeMaxAmount < $feeMinAmount) {
            throw new InvalidArgumentException('공통 최대 수수료는 공통 최소 수수료 이상이어야 합니다.');
        }
    }

    return $settings;
}

function sr_asset_exchange_save_settings(PDO $pdo, array $settings): void
{
    $settings = sr_asset_exchange_validate_settings($settings);
    sr_asset_exchange_validate_canonical_policy_rates_for_settings($pdo, $settings);

    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'asset_exchange' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('포인트/금액 환전 모듈이 등록되어 있지 않습니다.');
    }

    $rows = [];
    foreach (sr_asset_exchange_default_settings() as $key => $defaultValue) {
        $rows[] = [$key, (string) ($settings[$key] ?? $defaultValue), 'string'];
    }

    $now = sr_now();
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
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
        foreach ($rows as $row) {
            $stmt->execute([
                'module_id' => (int) $module['id'],
                'setting_key' => (string) $row[0],
                'setting_value' => (string) $row[1],
                'value_type' => (string) $row[2],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        sr_asset_exchange_sync_canonical_policies($pdo, $settings);

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    sr_clear_module_settings_cache('asset_exchange');
}

function sr_asset_exchange_enabled_from_settings(array $settings): bool
{
    return sr_truthy($settings['exchange_enabled'] ?? false);
}

function sr_asset_exchange_enabled(PDO $pdo): bool
{
    return sr_asset_exchange_enabled_from_settings(sr_asset_exchange_settings($pdo));
}

function sr_asset_exchange_canonical_policy_rows_from_settings(array $settings): array
{
    $settings = sr_asset_exchange_normalize_settings($settings);
    $values = sr_asset_exchange_relative_values_from_settings($settings);
    $status = (string) $settings['policy_default_status'];
    $minAmount = sr_asset_exchange_positive_int($settings['policy_default_min_amount'], '공통 최소 환전량은 1 이상이어야 합니다.');
    $maxAmount = sr_asset_exchange_nullable_int($settings['policy_default_max_amount'], '공통 최대 환전량은 0 이상의 정수로 입력하세요.');
    $roundingMode = (string) $settings['policy_default_rounding_mode'];
    $feeTrigger = (string) $settings['policy_default_fee_trigger'];
    $feeBasis = (string) $settings['policy_default_fee_basis'];
    $feeType = (string) $settings['policy_default_fee_type'];
    $feeRateNumerator = sr_asset_exchange_optional_non_negative_int($settings['policy_default_fee_rate_numerator'] ?? null, 0, '공통 정률 수수료는 0 이상의 정수로 입력하세요.');
    $feeFixedAmount = sr_asset_exchange_optional_non_negative_int($settings['policy_default_fee_fixed_amount'] ?? null, 0, '공통 정액 수수료는 0 이상의 정수로 입력하세요.');
    $feeMinAmount = sr_asset_exchange_nullable_int($settings['policy_default_fee_min_amount'], '공통 최소 수수료는 0 이상의 정수로 입력하세요.');
    $feeMaxAmount = sr_asset_exchange_nullable_int($settings['policy_default_fee_max_amount'], '공통 최대 수수료는 0 이상의 정수로 입력하세요.');

    if ($feeTrigger === 'none') {
        $feeBasis = 'to_amount';
        $feeRateNumerator = 0;
        $feeFixedAmount = 0;
        $feeMinAmount = null;
        $feeMaxAmount = null;
    } elseif ($feeType === 'fixed') {
        $feeBasis = 'to_amount';
        $feeRateNumerator = 0;
    } else {
        $feeFixedAmount = 0;
    }

    $rows = [];
    foreach (sr_asset_exchange_canonical_policy_pairs() as $index => $pair) {
        $fromModuleKey = (string) $pair['from_module_key'];
        $toModuleKey = (string) $pair['to_module_key'];
        $rows[] = [
            'from_module_key' => $fromModuleKey,
            'to_module_key' => $toModuleKey,
            'status' => $status,
            'rate_numerator' => $values[$toModuleKey],
            'rate_denominator' => $values[$fromModuleKey],
            'min_amount' => $minAmount,
            'max_amount' => $maxAmount,
            'rounding_mode' => $roundingMode,
            'fee_trigger' => $feeTrigger,
            'fee_basis' => $feeBasis,
            'fee_rate_numerator' => $feeRateNumerator,
            'fee_rate_denominator' => 100,
            'fee_fixed_amount' => $feeFixedAmount,
            'fee_min_amount' => $feeMinAmount,
            'fee_max_amount' => $feeMaxAmount,
            'sort_order' => $index,
        ];
    }

    return $rows;
}

function sr_asset_exchange_validate_relative_policy_cycles(array $settings): void
{
    $rows = sr_asset_exchange_canonical_policy_rows_from_settings($settings);
    $rowsBySlot = [];
    foreach ($rows as $row) {
        if ((string) ($row['status'] ?? '') !== 'enabled') {
            return;
        }
        $rowsBySlot[sr_asset_exchange_policy_slot_key((string) $row['from_module_key'], (string) $row['to_module_key'])] = $row;
    }

    foreach (sr_asset_exchange_canonical_asset_keys() as $firstAssetKey) {
        foreach (sr_asset_exchange_canonical_asset_keys() as $secondAssetKey) {
            if ($firstAssetKey === $secondAssetKey) {
                continue;
            }

            $forward = $rowsBySlot[sr_asset_exchange_policy_slot_key($firstAssetKey, $secondAssetKey)] ?? null;
            $back = $rowsBySlot[sr_asset_exchange_policy_slot_key($secondAssetKey, $firstAssetKey)] ?? null;
            if (is_array($forward) && is_array($back) && sr_asset_exchange_policy_cycle_increases_value_sequence([$forward, $back])) {
                throw new InvalidArgumentException('자산별 환산 기준과 반올림 설정으로 양방향 반복 환전 가치가 증가할 수 있습니다. 환산 기준, 반올림 또는 수수료 정책을 조정하세요.');
            }

            foreach (sr_asset_exchange_canonical_asset_keys() as $thirdAssetKey) {
                if ($thirdAssetKey === $firstAssetKey || $thirdAssetKey === $secondAssetKey) {
                    continue;
                }

                $first = $rowsBySlot[sr_asset_exchange_policy_slot_key($firstAssetKey, $secondAssetKey)] ?? null;
                $second = $rowsBySlot[sr_asset_exchange_policy_slot_key($secondAssetKey, $thirdAssetKey)] ?? null;
                $third = $rowsBySlot[sr_asset_exchange_policy_slot_key($thirdAssetKey, $firstAssetKey)] ?? null;
                if (
                    is_array($first)
                    && is_array($second)
                    && is_array($third)
                    && sr_asset_exchange_policy_cycle_increases_value_sequence([$first, $second, $third])
                ) {
                    throw new InvalidArgumentException('자산별 환산 기준과 반올림 설정으로 3자 순환 환전 가치가 증가할 수 있습니다. 환산 기준, 반올림 또는 수수료 정책을 조정하세요.');
                }
            }
        }
    }
}

function sr_asset_exchange_sync_canonical_policies(PDO $pdo, array $settings): void
{
    sr_asset_exchange_remove_noncanonical_policies($pdo);
    sr_asset_exchange_validate_canonical_policy_rates_for_settings($pdo, $settings);

    $rows = sr_asset_exchange_canonical_policy_rows_for_sync($pdo, $settings);
    $now = sr_now();

    $selectStmt = $pdo->prepare(
        'SELECT id FROM sr_asset_exchange_policies
         WHERE from_module_key = :from_module_key
           AND to_module_key = :to_module_key
         LIMIT 1'
    );
    $updateStmt = $pdo->prepare(
        'UPDATE sr_asset_exchange_policies
         SET status = :status, rate_numerator = :rate_numerator, rate_denominator = :rate_denominator,
             min_amount = :min_amount, max_amount = :max_amount, rounding_mode = :rounding_mode,
             fee_trigger = :fee_trigger, fee_basis = :fee_basis,
             fee_rate_numerator = :fee_rate_numerator, fee_rate_denominator = :fee_rate_denominator,
             fee_fixed_amount = :fee_fixed_amount, fee_min_amount = :fee_min_amount, fee_max_amount = :fee_max_amount,
             sort_order = :sort_order, updated_at = :updated_at
         WHERE id = :id'
    );
    $insertStmt = $pdo->prepare(
        'INSERT INTO sr_asset_exchange_policies
            (from_module_key, to_module_key, status, rate_numerator, rate_denominator, min_amount, max_amount, rounding_mode,
             fee_trigger, fee_basis, fee_rate_numerator, fee_rate_denominator, fee_fixed_amount, fee_min_amount, fee_max_amount,
             sort_order, created_at, updated_at)
         VALUES
            (:from_module_key, :to_module_key, :status, :rate_numerator, :rate_denominator, :min_amount, :max_amount, :rounding_mode,
             :fee_trigger, :fee_basis, :fee_rate_numerator, :fee_rate_denominator, :fee_fixed_amount, :fee_min_amount, :fee_max_amount,
             :sort_order, :created_at, :updated_at)'
    );

    foreach ($rows as $row) {
        $selectStmt->execute([
            'from_module_key' => (string) $row['from_module_key'],
            'to_module_key' => (string) $row['to_module_key'],
        ]);
        $existing = $selectStmt->fetch();
        $params = [
            'status' => (string) $row['status'],
            'rate_numerator' => (int) $row['rate_numerator'],
            'rate_denominator' => (int) $row['rate_denominator'],
            'min_amount' => (int) $row['min_amount'],
            'max_amount' => $row['max_amount'],
            'rounding_mode' => (string) $row['rounding_mode'],
            'fee_trigger' => (string) $row['fee_trigger'],
            'fee_basis' => (string) $row['fee_basis'],
            'fee_rate_numerator' => (int) $row['fee_rate_numerator'],
            'fee_rate_denominator' => (int) $row['fee_rate_denominator'],
            'fee_fixed_amount' => (int) $row['fee_fixed_amount'],
            'fee_min_amount' => $row['fee_min_amount'],
            'fee_max_amount' => $row['fee_max_amount'],
            'sort_order' => (int) $row['sort_order'],
            'updated_at' => $now,
        ];

        if (is_array($existing)) {
            $params['id'] = (int) $existing['id'];
            $updateStmt->execute($params);
            continue;
        }

        $params['from_module_key'] = (string) $row['from_module_key'];
        $params['to_module_key'] = (string) $row['to_module_key'];
        $params['created_at'] = $now;
        $insertStmt->execute($params);
    }
}

function sr_asset_exchange_canonical_policy_rows_for_sync(PDO $pdo, array $settings): array
{
    $defaultRows = sr_asset_exchange_canonical_policy_rows_from_settings($settings);
    $existingRows = [];
    $stmt = $pdo->query('SELECT * FROM sr_asset_exchange_policies');
    foreach ($stmt ? $stmt->fetchAll() : [] as $row) {
        if (!is_array($row)) {
            continue;
        }

        $fromModuleKey = (string) ($row['from_module_key'] ?? '');
        $toModuleKey = (string) ($row['to_module_key'] ?? '');
        if (!sr_asset_exchange_is_canonical_pair($fromModuleKey, $toModuleKey)) {
            continue;
        }

        $existingRows[sr_asset_exchange_policy_slot_key($fromModuleKey, $toModuleKey)] = $row;
    }

    $rows = [];
    foreach ($defaultRows as $defaultRow) {
        $slotKey = sr_asset_exchange_policy_slot_key(
            (string) ($defaultRow['from_module_key'] ?? ''),
            (string) ($defaultRow['to_module_key'] ?? '')
        );
        $existingRow = $existingRows[$slotKey] ?? null;
        if (is_array($existingRow)) {
            $preservedKeys = [
                'status',
                'min_amount',
                'max_amount',
                'rounding_mode',
                'fee_trigger',
                'fee_basis',
                'fee_rate_numerator',
                'fee_rate_denominator',
                'fee_fixed_amount',
                'fee_min_amount',
                'fee_max_amount',
            ];
            foreach ($preservedKeys as $preservedKey) {
                if (array_key_exists($preservedKey, $existingRow)) {
                    $defaultRow[$preservedKey] = $existingRow[$preservedKey];
                }
            }
        }

        $rows[] = $defaultRow;
    }

    return $rows;
}

function sr_asset_exchange_validate_canonical_policy_rates_for_settings(PDO $pdo, array $settings): void
{
    $rows = sr_asset_exchange_canonical_policy_rows_for_sync($pdo, $settings);
    $rowsBySlot = [];
    foreach ($rows as $row) {
        if ((string) ($row['status'] ?? '') !== 'enabled') {
            continue;
        }
        sr_asset_exchange_validate_policy_positive_result($row);
        $rowsBySlot[sr_asset_exchange_policy_slot_key((string) $row['from_module_key'], (string) $row['to_module_key'])] = $row;
    }

    foreach (sr_asset_exchange_canonical_asset_keys() as $firstAssetKey) {
        foreach (sr_asset_exchange_canonical_asset_keys() as $secondAssetKey) {
            if ($firstAssetKey === $secondAssetKey) {
                continue;
            }

            $forward = $rowsBySlot[sr_asset_exchange_policy_slot_key($firstAssetKey, $secondAssetKey)] ?? null;
            $back = $rowsBySlot[sr_asset_exchange_policy_slot_key($secondAssetKey, $firstAssetKey)] ?? null;
            if (is_array($forward) && is_array($back) && sr_asset_exchange_policy_cycle_increases_value_sequence([$forward, $back])) {
                throw new InvalidArgumentException('자산별 환산 기준 변경으로 양방향 반복 환전 가치가 증가할 수 있습니다. 환산 기준, 반올림 또는 수수료 정책을 조정하세요.');
            }

            foreach (sr_asset_exchange_canonical_asset_keys() as $thirdAssetKey) {
                if ($thirdAssetKey === $firstAssetKey || $thirdAssetKey === $secondAssetKey) {
                    continue;
                }

                $first = $rowsBySlot[sr_asset_exchange_policy_slot_key($firstAssetKey, $secondAssetKey)] ?? null;
                $second = $rowsBySlot[sr_asset_exchange_policy_slot_key($secondAssetKey, $thirdAssetKey)] ?? null;
                $third = $rowsBySlot[sr_asset_exchange_policy_slot_key($thirdAssetKey, $firstAssetKey)] ?? null;
                if (
                    is_array($first)
                    && is_array($second)
                    && is_array($third)
                    && sr_asset_exchange_policy_cycle_increases_value_sequence([$first, $second, $third])
                ) {
                    throw new InvalidArgumentException('자산별 환산 기준 변경으로 3자 순환 환전 가치가 증가할 수 있습니다. 환산 기준, 반올림 또는 수수료 정책을 조정하세요.');
                }
            }
        }
    }
}

function sr_asset_exchange_remove_noncanonical_policies(PDO $pdo): void
{
    $stmt = $pdo->query('SELECT id, from_module_key, to_module_key FROM sr_asset_exchange_policies');
    $rows = $stmt ? $stmt->fetchAll() : [];
    $policyIds = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        if (!sr_asset_exchange_is_canonical_pair((string) ($row['from_module_key'] ?? ''), (string) ($row['to_module_key'] ?? ''))) {
            $policyIds[] = (int) ($row['id'] ?? 0);
        }
    }

    $policyIds = array_values(array_filter(array_unique($policyIds), static function (int $policyId): bool {
        return $policyId > 0;
    }));
    if ($policyIds === []) {
        return;
    }

    $placeholders = implode(', ', array_fill(0, count($policyIds), '?'));
    $stmt = $pdo->prepare('UPDATE sr_asset_exchange_logs SET policy_id = NULL WHERE policy_id IN (' . $placeholders . ')');
    $stmt->execute($policyIds);

    $stmt = $pdo->prepare('DELETE FROM sr_asset_exchange_policies WHERE id IN (' . $placeholders . ')');
    $stmt->execute($policyIds);
}

function sr_asset_exchange_policy(PDO $pdo, int $policyId): ?array
{
    if ($policyId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_asset_exchange_policies WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $policyId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_asset_exchange_policies(PDO $pdo, bool $enabledOnly = false): array
{
    $sql = 'SELECT * FROM sr_asset_exchange_policies';
    if ($enabledOnly) {
        $sql .= " WHERE status = 'enabled'";
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';

    return $pdo->query($sql)->fetchAll();
}

function sr_asset_exchange_policy_by_slot(PDO $pdo, string $fromModuleKey, string $toModuleKey): ?array
{
    $fromModuleKey = sr_asset_exchange_clean_module_key($fromModuleKey);
    $toModuleKey = sr_asset_exchange_clean_module_key($toModuleKey);
    if (!sr_asset_exchange_is_canonical_pair($fromModuleKey, $toModuleKey)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM sr_asset_exchange_policies
         WHERE from_module_key = :from_module_key
           AND to_module_key = :to_module_key
         LIMIT 1'
    );
    $stmt->execute([
        'from_module_key' => $fromModuleKey,
        'to_module_key' => $toModuleKey,
    ]);

    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_asset_exchange_policy_slots(PDO $pdo, array $assets): array
{
    $slots = [];
    $exchangeEnabled = sr_asset_exchange_enabled($pdo);
    foreach (sr_asset_exchange_canonical_policy_pairs() as $pair) {
        $fromModuleKey = (string) $pair['from_module_key'];
        $toModuleKey = (string) $pair['to_module_key'];
        $policy = sr_asset_exchange_policy_by_slot($pdo, $fromModuleKey, $toModuleKey);
        $configured = is_array($policy);
        $status = $configured ? (string) ($policy['status'] ?? 'disabled') : 'unconfigured';
        $fromActive = isset($assets[$fromModuleKey]);
        $toActive = isset($assets[$toModuleKey]);

        $slots[] = [
            'from_module_key' => $fromModuleKey,
            'to_module_key' => $toModuleKey,
            'policy' => $policy,
            'configured' => $configured,
            'status' => $status,
            'from_active' => $fromActive,
            'to_active' => $toActive,
            'executable' => $exchangeEnabled && $configured && $status === 'enabled' && $fromActive && $toActive,
        ];
    }

    return $slots;
}

function sr_asset_exchange_save_policy(PDO $pdo, array $data): int
{
    $policyId = (int) ($data['id'] ?? 0);
    $fromModuleKey = sr_asset_exchange_clean_module_key((string) ($data['from_module_key'] ?? ''));
    $toModuleKey = sr_asset_exchange_clean_module_key((string) ($data['to_module_key'] ?? ''));
    $status = (string) ($data['status'] ?? 'disabled');
    $roundingMode = (string) ($data['rounding_mode'] ?? 'floor');
    $feeTrigger = (string) ($data['fee_trigger'] ?? 'none');
    $feeBasis = (string) ($data['fee_basis'] ?? 'to_amount');
    $feeType = (string) ($data['fee_type'] ?? 'rate');

    if ($fromModuleKey === '' || $toModuleKey === '' || $fromModuleKey === $toModuleKey) {
        throw new InvalidArgumentException('출금 항목과 입금 항목을 서로 다르게 선택하세요.');
    }
    if (!sr_asset_exchange_is_canonical_pair($fromModuleKey, $toModuleKey)) {
        throw new InvalidArgumentException('환전 정책은 포인트, 적립금, 예치금 사이의 고정 6개 조합만 저장할 수 있습니다.');
    }

    $existingPolicy = null;
    if ($policyId > 0) {
        $existingPolicy = sr_asset_exchange_policy($pdo, $policyId);
        if ($existingPolicy === null) {
            throw new InvalidArgumentException('수정할 환전 정책을 찾을 수 없습니다.');
        }
        if (
            (string) ($existingPolicy['from_module_key'] ?? '') !== $fromModuleKey
            || (string) ($existingPolicy['to_module_key'] ?? '') !== $toModuleKey
        ) {
            throw new InvalidArgumentException('고정 환전 조합은 변경할 수 없습니다.');
        }
    }

    if (!in_array($status, ['enabled', 'disabled'], true)) {
        throw new InvalidArgumentException('정책 상태가 올바르지 않습니다.');
    }
    if (!in_array($roundingMode, ['floor', 'round', 'ceil'], true)) {
        throw new InvalidArgumentException('반올림 방식이 올바르지 않습니다.');
    }
    if (!in_array($feeTrigger, ['none', 'always', 'reexchange'], true)) {
        throw new InvalidArgumentException('수수료 적용 조건이 올바르지 않습니다.');
    }
    if ($feeTrigger === 'none') {
        $feeBasis = 'to_amount';
        $feeType = 'rate';
    } else {
        if (!in_array($feeType, ['rate', 'fixed'], true)) {
            throw new InvalidArgumentException('수수료 방식이 올바르지 않습니다.');
        }
        if ($feeType === 'rate' && !in_array($feeBasis, ['from_amount', 'to_amount'], true)) {
            throw new InvalidArgumentException('수수료 계산 기준이 올바르지 않습니다.');
        }
        if ($feeType === 'fixed') {
            $feeBasis = 'to_amount';
        }
    }

    $assets = sr_asset_exchange_assets($pdo);
    if (!isset($assets[$fromModuleKey], $assets[$toModuleKey]) && $status === 'enabled') {
        throw new InvalidArgumentException('설치되어 있고 활성화된 포인트/금액 항목만 사용 상태로 저장할 수 있습니다.');
    }

    [$rateNumerator, $rateDenominator] = sr_asset_exchange_rate_parts_for_pair(
        sr_asset_exchange_settings($pdo),
        $fromModuleKey,
        $toModuleKey
    );
    $minAmount = sr_asset_exchange_positive_int($data['min_amount'] ?? 0, '최소 환전량은 1 이상이어야 합니다.');
    $maxAmount = sr_asset_exchange_nullable_int($data['max_amount'] ?? null, '최대 환전량은 0 이상의 정수로 입력하세요.');
    if ($maxAmount !== null && $maxAmount < $minAmount) {
        throw new InvalidArgumentException('최대 환전량은 최소 환전량 이상이어야 합니다.');
    }

    $feeRateDenominator = 100;
    $feeRateNumerator = sr_asset_exchange_optional_non_negative_int($data['fee_rate_numerator'] ?? null, 0, '정률 수수료는 0 이상의 정수로 입력하세요.');
    $feeFixedAmount = sr_asset_exchange_optional_non_negative_int($data['fee_fixed_amount'] ?? null, 0, '정액 수수료는 0 이상의 정수로 입력하세요.');
    if ($feeTrigger === 'none') {
        $feeRateNumerator = 0;
        $feeFixedAmount = 0;
        $feeBasis = 'to_amount';
    } elseif ($feeType === 'rate') {
        $feeFixedAmount = 0;
    } else {
        $feeRateNumerator = 0;
        $feeBasis = 'to_amount';
    }
    $feeMinAmount = sr_asset_exchange_nullable_int($data['fee_min_amount'] ?? null, '최소 수수료는 0 이상의 정수로 입력하세요.');
    $feeMaxAmount = sr_asset_exchange_nullable_int($data['fee_max_amount'] ?? null, '최대 수수료는 0 이상의 정수로 입력하세요.');
    if ($feeTrigger === 'none') {
        $feeMinAmount = null;
        $feeMaxAmount = null;
    }
    if ($feeMaxAmount !== null && $feeMinAmount !== null && $feeMaxAmount < $feeMinAmount) {
        throw new InvalidArgumentException('최대 수수료는 최소 수수료 이상이어야 합니다.');
    }
    if ($feeTrigger !== 'none' && $feeType === 'rate' && $feeRateNumerator === 0) {
        throw new InvalidArgumentException('정률 수수료를 1 이상 입력하세요.');
    }
    if ($feeTrigger !== 'none' && $feeType === 'fixed' && $feeFixedAmount === 0) {
        throw new InvalidArgumentException('정액 수수료를 1 이상 입력하세요.');
    }

    sr_asset_exchange_validate_policy_positive_result([
        'status' => $status,
        'rate_numerator' => $rateNumerator,
        'rate_denominator' => $rateDenominator,
        'min_amount' => $minAmount,
        'rounding_mode' => $roundingMode,
        'fee_trigger' => $feeTrigger,
        'fee_basis' => $feeBasis,
        'fee_rate_numerator' => $feeRateNumerator,
        'fee_rate_denominator' => $feeRateDenominator,
        'fee_fixed_amount' => $feeFixedAmount,
        'fee_min_amount' => $feeMinAmount,
        'fee_max_amount' => $feeMaxAmount,
    ]);

    $stmt = $pdo->prepare(
        'SELECT id FROM sr_asset_exchange_policies
         WHERE from_module_key = :from_module_key
           AND to_module_key = :to_module_key
           AND id <> :id
         LIMIT 1'
    );
    $stmt->execute([
        'from_module_key' => $fromModuleKey,
        'to_module_key' => $toModuleKey,
        'id' => $policyId,
    ]);
    if (is_array($stmt->fetch())) {
        throw new InvalidArgumentException('이미 같은 항목 조합의 환전 정책이 있습니다.');
    }

    sr_asset_exchange_validate_policy_cycle_safety($pdo, [
        'id' => $policyId,
        'from_module_key' => $fromModuleKey,
        'to_module_key' => $toModuleKey,
        'status' => $status,
        'rate_numerator' => $rateNumerator,
        'rate_denominator' => $rateDenominator,
        'rounding_mode' => $roundingMode,
        'fee_trigger' => $feeTrigger,
    ]);

    $now = sr_now();
    $params = [
        'from_module_key' => $fromModuleKey,
        'to_module_key' => $toModuleKey,
        'status' => $status,
        'rate_numerator' => $rateNumerator,
        'rate_denominator' => $rateDenominator,
        'min_amount' => $minAmount,
        'max_amount' => $maxAmount,
        'rounding_mode' => $roundingMode,
        'fee_trigger' => $feeTrigger,
        'fee_basis' => $feeBasis,
        'fee_rate_numerator' => $feeRateNumerator,
        'fee_rate_denominator' => $feeRateDenominator,
        'fee_fixed_amount' => $feeFixedAmount,
        'fee_min_amount' => $feeMinAmount,
        'fee_max_amount' => $feeMaxAmount,
        'sort_order' => sr_asset_exchange_canonical_policy_sort_order($fromModuleKey, $toModuleKey),
        'updated_at' => $now,
    ];

    if ($policyId > 0) {
        $params['id'] = $policyId;
        $stmt = $pdo->prepare(
            'UPDATE sr_asset_exchange_policies
             SET from_module_key = :from_module_key, to_module_key = :to_module_key, status = :status,
                 rate_numerator = :rate_numerator, rate_denominator = :rate_denominator,
                 min_amount = :min_amount, max_amount = :max_amount, rounding_mode = :rounding_mode,
                 fee_trigger = :fee_trigger, fee_basis = :fee_basis,
                 fee_rate_numerator = :fee_rate_numerator, fee_rate_denominator = :fee_rate_denominator,
                 fee_fixed_amount = :fee_fixed_amount, fee_min_amount = :fee_min_amount, fee_max_amount = :fee_max_amount,
                 sort_order = :sort_order, updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute($params);
        return $policyId;
    }

    $params['created_at'] = $now;
    $stmt = $pdo->prepare(
        'INSERT INTO sr_asset_exchange_policies
            (from_module_key, to_module_key, status, rate_numerator, rate_denominator, min_amount, max_amount, rounding_mode,
             fee_trigger, fee_basis, fee_rate_numerator, fee_rate_denominator, fee_fixed_amount, fee_min_amount, fee_max_amount,
             sort_order, created_at, updated_at)
         VALUES
            (:from_module_key, :to_module_key, :status, :rate_numerator, :rate_denominator, :min_amount, :max_amount, :rounding_mode,
             :fee_trigger, :fee_basis, :fee_rate_numerator, :fee_rate_denominator, :fee_fixed_amount, :fee_min_amount, :fee_max_amount,
             :sort_order, :created_at, :updated_at)'
    );
    $stmt->execute($params);

    return (int) $pdo->lastInsertId();
}

function sr_asset_exchange_validate_policy_cycle_safety(PDO $pdo, array $policy): void
{
    if ((string) ($policy['status'] ?? '') !== 'enabled') {
        return;
    }

    $fromModuleKey = (string) ($policy['from_module_key'] ?? '');
    $toModuleKey = (string) ($policy['to_module_key'] ?? '');
    if ($fromModuleKey === '' || $toModuleKey === '') {
        return;
    }

    if (!sr_asset_exchange_is_canonical_pair($fromModuleKey, $toModuleKey)) {
        return;
    }

    $policiesBySlot = [];
    foreach (sr_asset_exchange_policies($pdo, true) as $storedPolicy) {
        if (!is_array($storedPolicy) || (int) ($storedPolicy['id'] ?? 0) === (int) ($policy['id'] ?? 0)) {
            continue;
        }
        if (!sr_asset_exchange_is_canonical_pair(
            (string) ($storedPolicy['from_module_key'] ?? ''),
            (string) ($storedPolicy['to_module_key'] ?? '')
        )) {
            continue;
        }

        $storedSlotKey = sr_asset_exchange_policy_slot_key(
            (string) ($storedPolicy['from_module_key'] ?? ''),
            (string) ($storedPolicy['to_module_key'] ?? '')
        );
        $policiesBySlot[$storedSlotKey] = $storedPolicy;
    }

    $policiesBySlot[sr_asset_exchange_policy_slot_key($fromModuleKey, $toModuleKey)] = $policy;

    foreach (sr_asset_exchange_canonical_asset_keys() as $firstAssetKey) {
        foreach (sr_asset_exchange_canonical_asset_keys() as $secondAssetKey) {
            if ($firstAssetKey === $secondAssetKey) {
                continue;
            }

            $forward = $policiesBySlot[sr_asset_exchange_policy_slot_key($firstAssetKey, $secondAssetKey)] ?? null;
            $back = $policiesBySlot[sr_asset_exchange_policy_slot_key($secondAssetKey, $firstAssetKey)] ?? null;
            if (is_array($forward) && is_array($back) && sr_asset_exchange_policy_cycle_increases_value_sequence([$forward, $back])) {
                throw new InvalidArgumentException('무수수료 양방향 환전에서 반복 환전 시 가치가 증가할 수 있습니다. 비율, 반올림 또는 수수료 정책을 조정하세요.');
            }

            foreach (sr_asset_exchange_canonical_asset_keys() as $thirdAssetKey) {
                if ($thirdAssetKey === $firstAssetKey || $thirdAssetKey === $secondAssetKey) {
                    continue;
                }

                $first = $policiesBySlot[sr_asset_exchange_policy_slot_key($firstAssetKey, $secondAssetKey)] ?? null;
                $second = $policiesBySlot[sr_asset_exchange_policy_slot_key($secondAssetKey, $thirdAssetKey)] ?? null;
                $third = $policiesBySlot[sr_asset_exchange_policy_slot_key($thirdAssetKey, $firstAssetKey)] ?? null;
                if (
                    is_array($first)
                    && is_array($second)
                    && is_array($third)
                    && sr_asset_exchange_policy_cycle_increases_value_sequence([$first, $second, $third])
                ) {
                    throw new InvalidArgumentException('무수수료 3자 순환 환전에서 반복 환전 시 가치가 증가할 수 있습니다. 비율, 반올림 또는 수수료 정책을 조정하세요.');
                }
            }
        }
    }
}

function sr_asset_exchange_policy_cycle_increases_value(array $outPolicy, array $returnPolicy): bool
{
    return sr_asset_exchange_policy_cycle_increases_value_sequence([$outPolicy, $returnPolicy]);
}

function sr_asset_exchange_policy_cycle_increases_value_sequence(array $policies): bool
{
    if ($policies === []) {
        return false;
    }

    $ratioNumerator = 1;
    $ratioDenominator = 1;
    foreach ($policies as $policy) {
        if ((string) ($policy['fee_trigger'] ?? 'none') !== 'none') {
            return false;
        }

        $ratioNumerator *= max(1, (int) ($policy['rate_numerator'] ?? 1));
        $ratioDenominator *= max(1, (int) ($policy['rate_denominator'] ?? 1));
    }

    if ($ratioNumerator > $ratioDenominator) {
        return true;
    }

    for ($amount = 1; $amount <= 1000; $amount++) {
        $converted = $amount;
        foreach ($policies as $policy) {
            $converted = sr_asset_exchange_apply_ratio(
                $converted,
                max(1, (int) ($policy['rate_numerator'] ?? 1)),
                max(1, (int) ($policy['rate_denominator'] ?? 1)),
                (string) ($policy['rounding_mode'] ?? 'floor')
            );
            if ($converted <= 0) {
                break;
            }
        }
        if ($converted > $amount) {
            return true;
        }
    }

    return false;
}

function sr_asset_exchange_rate_parts(array $data): array
{
    $rateRatio = trim((string) ($data['rate_ratio'] ?? ''));
    if ($rateRatio !== '') {
        if (preg_match('/\A([0-9]+)\s*[:\/]\s*([0-9]+)\z/', $rateRatio, $matches) !== 1) {
            throw new InvalidArgumentException('환전 비율은 출금 기준량:입금 환산량 형식으로 입력하세요. 예: 100:1');
        }

        $rateDenominator = sr_asset_exchange_positive_int($matches[1], '출금 기준량은 1 이상이어야 합니다.');
        $rateNumerator = sr_asset_exchange_positive_int($matches[2], '입금 환산량은 1 이상이어야 합니다.');

        return [$rateNumerator, $rateDenominator];
    }

    return [
        sr_asset_exchange_positive_int($data['rate_numerator'] ?? 0, '입금 환산량은 1 이상이어야 합니다.'),
        sr_asset_exchange_positive_int($data['rate_denominator'] ?? 0, '출금 기준량은 1 이상이어야 합니다.'),
    ];
}

function sr_asset_exchange_quote(PDO $pdo, array $policy, int $accountId, int $amount): array
{
    if ($amount <= 0) {
        throw new InvalidArgumentException('환전 금액은 1 이상이어야 합니다.');
    }
    if (!sr_asset_exchange_enabled($pdo)) {
        throw new InvalidArgumentException('환전 기능이 현재 사용 중지되어 있습니다.');
    }
    if ((string) ($policy['status'] ?? '') !== 'enabled') {
        throw new InvalidArgumentException('비활성 정책은 실행할 수 없습니다.');
    }
    if (!sr_asset_exchange_is_canonical_pair((string) ($policy['from_module_key'] ?? ''), (string) ($policy['to_module_key'] ?? ''))) {
        throw new InvalidArgumentException('고정 환전 조합이 아닌 정책은 실행할 수 없습니다.');
    }
    $assets = sr_asset_exchange_assets($pdo);
    $fromModuleKey = (string) ($policy['from_module_key'] ?? '');
    $toModuleKey = (string) ($policy['to_module_key'] ?? '');
    if (!isset($assets[$fromModuleKey], $assets[$toModuleKey])) {
        throw new InvalidArgumentException('환전 대상 포인트/금액 항목이 활성 상태가 아닙니다.');
    }
    $balanceFunction = (string) $assets[$fromModuleKey]['balance_function'];
    if ($accountId > 0 && $balanceFunction($pdo, $accountId) < $amount) {
        throw new InvalidArgumentException('출금 항목의 잔액이 부족합니다.');
    }
    if ($amount < (int) ($policy['min_amount'] ?? 1)) {
        throw new InvalidArgumentException('최소 환전량보다 작습니다.');
    }
    $maxAmount = isset($policy['max_amount']) && $policy['max_amount'] !== null ? (int) $policy['max_amount'] : 0;
    if ($maxAmount > 0 && $amount > $maxAmount) {
        throw new InvalidArgumentException('최대 환전량보다 큽니다.');
    }

    $depositBeforeFee = sr_asset_exchange_apply_ratio(
        $amount,
        (int) ($policy['rate_numerator'] ?? 1),
        (int) ($policy['rate_denominator'] ?? 1),
        (string) ($policy['rounding_mode'] ?? 'floor')
    );
    if ($depositBeforeFee <= 0) {
        throw new InvalidArgumentException('환전 결과 금액이 0입니다.');
    }

    $feeApplies = sr_asset_exchange_fee_applies($pdo, $policy, $accountId);
    $feeAmount = $feeApplies ? sr_asset_exchange_fee_amount($policy, $amount, $depositBeforeFee) : 0;
    $depositAmount = $depositBeforeFee - $feeAmount;
    if ($depositAmount <= 0) {
        throw new InvalidArgumentException('수수료 차감 후 입금액이 0 이하입니다.');
    }

    return [
        'request_amount' => $amount,
        'deposit_before_fee' => $depositBeforeFee,
        'deposit_amount' => $depositAmount,
        'fee_amount' => $feeAmount,
        'fee_applies' => $feeApplies,
    ];
}

function sr_asset_exchange_execute(PDO $pdo, array $policy, int $accountId, int $amount, ?int $createdByAccountId = null): int
{
    if ($pdo->inTransaction()) {
        return sr_asset_exchange_execute_once($pdo, $policy, $accountId, $amount, $createdByAccountId);
    }

    $maxAttempts = sr_asset_exchange_transaction_retry_max_attempts();
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            return sr_asset_exchange_execute_once($pdo, $policy, $accountId, $amount, $createdByAccountId);
        } catch (Throwable $exception) {
            if ($attempt >= $maxAttempts || !sr_asset_exchange_is_retryable_transaction_exception($exception)) {
                throw $exception;
            }
            usleep(50000 * $attempt);
        }
    }

    throw new RuntimeException('환전 실행 재시도 횟수를 초과했습니다.');
}

function sr_asset_exchange_execute_once(PDO $pdo, array $policy, int $accountId, int $amount, ?int $createdByAccountId = null): int
{
    $assets = sr_asset_exchange_assets($pdo);
    $fromModuleKey = (string) ($policy['from_module_key'] ?? '');
    $toModuleKey = (string) ($policy['to_module_key'] ?? '');
    if (!sr_asset_exchange_is_canonical_pair($fromModuleKey, $toModuleKey)) {
        throw new RuntimeException('고정 환전 조합이 아닌 정책은 실행할 수 없습니다.');
    }
    if (!isset($assets[$fromModuleKey], $assets[$toModuleKey])) {
        throw new RuntimeException('환전 대상 포인트/금액 항목이 활성 상태가 아닙니다.');
    }

    $quote = sr_asset_exchange_quote($pdo, $policy, $accountId, $amount);
    $groupId = sr_asset_exchange_group_id();
    $now = sr_now();
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $fromTransactionFunction = (string) $assets[$fromModuleKey]['transaction_function'];
        $toTransactionFunction = (string) $assets[$toModuleKey]['transaction_function'];
        $fromTransactionId = $fromTransactionFunction($pdo, [
            'account_id' => $accountId,
            'amount' => -$amount,
            'transaction_type' => (string) $assets[$fromModuleKey]['exchange_out_type'],
            'reason' => '포인트/금액 환전 출금',
            'reference_type' => 'asset_exchange',
            'reference_id' => $groupId,
            'created_by_account_id' => $createdByAccountId,
        ]);
        $toTransactionId = $toTransactionFunction($pdo, [
            'account_id' => $accountId,
            'amount' => (int) $quote['deposit_before_fee'],
            'transaction_type' => (string) $assets[$toModuleKey]['exchange_in_type'],
            'reason' => '포인트/금액 환전 입금',
            'reference_type' => 'asset_exchange',
            'reference_id' => $groupId,
            'created_by_account_id' => $createdByAccountId,
        ]);
        $feeTransactionId = null;
        if ((int) $quote['fee_amount'] > 0) {
            $feeTransactionId = $toTransactionFunction($pdo, [
                'account_id' => $accountId,
                'amount' => -(int) $quote['fee_amount'],
                'transaction_type' => (string) $assets[$toModuleKey]['exchange_fee_type'],
                'reason' => '포인트/금액 환전 업무 수수료',
                'reference_type' => 'asset_exchange',
                'reference_id' => $groupId,
                'created_by_account_id' => $createdByAccountId,
            ]);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO sr_asset_exchange_logs
                (exchange_group_id, policy_id, account_id, from_module_key, to_module_key, request_amount,
                 rate_numerator, rate_denominator, rounding_mode, deposit_amount, fee_amount, fee_trigger, fee_basis,
                 status, failure_reason, from_transaction_id, to_transaction_id, fee_transaction_id, created_by_account_id, created_at)
             VALUES
                (:exchange_group_id, :policy_id, :account_id, :from_module_key, :to_module_key, :request_amount,
                 :rate_numerator, :rate_denominator, :rounding_mode, :deposit_amount, :fee_amount, :fee_trigger, :fee_basis,
                 :status, :failure_reason, :from_transaction_id, :to_transaction_id, :fee_transaction_id, :created_by_account_id, :created_at)'
        );
        $stmt->execute([
            'exchange_group_id' => $groupId,
            'policy_id' => (int) $policy['id'],
            'account_id' => $accountId,
            'from_module_key' => $fromModuleKey,
            'to_module_key' => $toModuleKey,
            'request_amount' => $amount,
            'rate_numerator' => (int) $policy['rate_numerator'],
            'rate_denominator' => (int) $policy['rate_denominator'],
            'rounding_mode' => (string) $policy['rounding_mode'],
            'deposit_amount' => (int) $quote['deposit_amount'],
            'fee_amount' => (int) $quote['fee_amount'],
            'fee_trigger' => (string) $policy['fee_trigger'],
            'fee_basis' => (string) $policy['fee_basis'],
            'status' => 'completed',
            'failure_reason' => '',
            'from_transaction_id' => $fromTransactionId,
            'to_transaction_id' => $toTransactionId,
            'fee_transaction_id' => $feeTransactionId,
            'created_by_account_id' => $createdByAccountId,
            'created_at' => $now,
        ]);
        $logId = (int) $pdo->lastInsertId();

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return $logId;
}

function sr_asset_exchange_transaction_retry_max_attempts(): int
{
    return 3;
}

function sr_asset_exchange_is_retryable_transaction_exception(Throwable $exception): bool
{
    if (!$exception instanceof PDOException) {
        return false;
    }

    $sqlState = (string) $exception->getCode();
    $driverCode = isset($exception->errorInfo[1]) ? (int) $exception->errorInfo[1] : 0;

    return $sqlState === '40001' || in_array($driverCode, [1205, 1213], true);
}

function sr_asset_exchange_for_update_clause(PDO $pdo): string
{
    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable) {
        $driver = '';
    }

    return $driver === 'sqlite' ? '' : ' FOR UPDATE';
}

function sr_asset_exchange_record_failure(PDO $pdo, array $policy, int $accountId, int $amount, string $failureReason, ?int $createdByAccountId = null): ?int
{
    $policyId = (int) ($policy['id'] ?? 0);
    $fromModuleKey = (string) ($policy['from_module_key'] ?? '');
    $toModuleKey = (string) ($policy['to_module_key'] ?? '');
    if ($policyId <= 0 || $accountId <= 0 || $fromModuleKey === '' || $toModuleKey === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_asset_exchange_logs
            (exchange_group_id, policy_id, account_id, from_module_key, to_module_key, request_amount,
             rate_numerator, rate_denominator, rounding_mode, deposit_amount, fee_amount, fee_trigger, fee_basis,
             status, failure_reason, from_transaction_id, to_transaction_id, fee_transaction_id, created_by_account_id, created_at)
         VALUES
            (:exchange_group_id, :policy_id, :account_id, :from_module_key, :to_module_key, :request_amount,
             :rate_numerator, :rate_denominator, :rounding_mode, :deposit_amount, :fee_amount, :fee_trigger, :fee_basis,
             :status, :failure_reason, :from_transaction_id, :to_transaction_id, :fee_transaction_id, :created_by_account_id, :created_at)'
    );
    $stmt->execute([
        'exchange_group_id' => sr_asset_exchange_group_id(),
        'policy_id' => $policyId,
        'account_id' => $accountId,
        'from_module_key' => $fromModuleKey,
        'to_module_key' => $toModuleKey,
        'request_amount' => $amount,
        'rate_numerator' => max(1, (int) ($policy['rate_numerator'] ?? 1)),
        'rate_denominator' => max(1, (int) ($policy['rate_denominator'] ?? 1)),
        'rounding_mode' => (string) ($policy['rounding_mode'] ?? 'floor'),
        'deposit_amount' => 0,
        'fee_amount' => 0,
        'fee_trigger' => (string) ($policy['fee_trigger'] ?? 'none'),
        'fee_basis' => (string) ($policy['fee_basis'] ?? 'to_amount'),
        'status' => 'failed',
        'failure_reason' => sr_asset_exchange_clean_text($failureReason, 255),
        'from_transaction_id' => null,
        'to_transaction_id' => null,
        'fee_transaction_id' => null,
        'created_by_account_id' => $createdByAccountId,
        'created_at' => sr_now(),
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_asset_exchange_correct_completed_group(PDO $pdo, string $exchangeGroupId, int $createdByAccountId, string $reason = '포인트/금액 환전 정정'): int
{
    $exchangeGroupId = sr_asset_exchange_clean_reference_id($exchangeGroupId, 80);
    if ($exchangeGroupId === '') {
        throw new InvalidArgumentException('정정할 환전 묶음 ID가 필요합니다.');
    }

    $correctionGroupId = sr_asset_exchange_correction_group_id($exchangeGroupId);
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM sr_asset_exchange_logs WHERE exchange_group_id = :exchange_group_id LIMIT 1');
        $stmt->execute(['exchange_group_id' => $correctionGroupId]);
        if (is_array($stmt->fetch())) {
            throw new RuntimeException('이미 정정된 환전 묶음입니다.');
        }

        $stmt = $pdo->prepare(
            'SELECT * FROM sr_asset_exchange_logs WHERE exchange_group_id = :exchange_group_id LIMIT 1'
            . sr_asset_exchange_for_update_clause($pdo)
        );
        $stmt->execute(['exchange_group_id' => $exchangeGroupId]);
        $log = $stmt->fetch();
        if (!is_array($log)) {
            throw new RuntimeException('정정할 환전 로그를 찾을 수 없습니다.');
        }
        if ((string) ($log['status'] ?? '') !== 'completed') {
            throw new RuntimeException('완료된 환전 로그만 정정할 수 있습니다.');
        }
        if ((int) ($log['request_amount'] ?? 0) <= 0 || (int) ($log['deposit_amount'] ?? 0) <= 0) {
            throw new RuntimeException('정정할 환전 로그의 금액이 올바르지 않습니다.');
        }

        $assets = sr_asset_exchange_assets($pdo);
        $fromModuleKey = (string) ($log['from_module_key'] ?? '');
        $toModuleKey = (string) ($log['to_module_key'] ?? '');
        if (!isset($assets[$fromModuleKey], $assets[$toModuleKey])) {
            throw new RuntimeException('정정 대상 자산 모듈이 활성 상태가 아닙니다.');
        }

        $fromTransactionFunction = (string) $assets[$fromModuleKey]['transaction_function'];
        $toTransactionFunction = (string) $assets[$toModuleKey]['transaction_function'];
        $requestAmount = (int) $log['request_amount'];
        $depositAmount = (int) $log['deposit_amount'];
        $feeAmount = max(0, (int) ($log['fee_amount'] ?? 0));
        $depositBeforeFee = $depositAmount + $feeAmount;
        $cleanReason = sr_asset_exchange_clean_text($reason, 255);

        $fromTransactionId = $fromTransactionFunction($pdo, [
            'account_id' => (int) $log['account_id'],
            'amount' => $requestAmount,
            'transaction_type' => 'adjustment',
            'reason' => $cleanReason,
            'reference_type' => 'asset_exchange_correction',
            'reference_id' => $correctionGroupId,
            'created_by_account_id' => $createdByAccountId,
        ]);
        $feeTransactionId = null;
        if ($feeAmount > 0) {
            $feeTransactionId = $toTransactionFunction($pdo, [
                'account_id' => (int) $log['account_id'],
                'amount' => $feeAmount,
                'transaction_type' => 'adjustment',
                'reason' => $cleanReason,
                'reference_type' => 'asset_exchange_correction',
                'reference_id' => $correctionGroupId,
                'created_by_account_id' => $createdByAccountId,
            ]);
        }
        $toTransactionId = $toTransactionFunction($pdo, [
            'account_id' => (int) $log['account_id'],
            'amount' => -$depositBeforeFee,
            'transaction_type' => 'adjustment',
            'reason' => $cleanReason,
            'reference_type' => 'asset_exchange_correction',
            'reference_id' => $correctionGroupId,
            'created_by_account_id' => $createdByAccountId,
        ]);

        $stmt = $pdo->prepare(
            'INSERT INTO sr_asset_exchange_logs
                (exchange_group_id, policy_id, account_id, from_module_key, to_module_key, request_amount,
                 rate_numerator, rate_denominator, rounding_mode, deposit_amount, fee_amount, fee_trigger, fee_basis,
                 status, failure_reason, from_transaction_id, to_transaction_id, fee_transaction_id, created_by_account_id, created_at)
             VALUES
                (:exchange_group_id, :policy_id, :account_id, :from_module_key, :to_module_key, :request_amount,
                 :rate_numerator, :rate_denominator, :rounding_mode, :deposit_amount, :fee_amount, :fee_trigger, :fee_basis,
                 :status, :failure_reason, :from_transaction_id, :to_transaction_id, :fee_transaction_id, :created_by_account_id, :created_at)'
        );
        $stmt->execute([
            'exchange_group_id' => $correctionGroupId,
            'policy_id' => (int) ($log['policy_id'] ?? 0) > 0 ? (int) $log['policy_id'] : null,
            'account_id' => (int) $log['account_id'],
            'from_module_key' => $fromModuleKey,
            'to_module_key' => $toModuleKey,
            'request_amount' => -$requestAmount,
            'rate_numerator' => max(1, (int) ($log['rate_numerator'] ?? 1)),
            'rate_denominator' => max(1, (int) ($log['rate_denominator'] ?? 1)),
            'rounding_mode' => (string) ($log['rounding_mode'] ?? 'floor'),
            'deposit_amount' => -$depositAmount,
            'fee_amount' => -$feeAmount,
            'fee_trigger' => (string) ($log['fee_trigger'] ?? 'none'),
            'fee_basis' => (string) ($log['fee_basis'] ?? 'to_amount'),
            'status' => 'completed',
            'failure_reason' => 'correction_for:' . $exchangeGroupId,
            'from_transaction_id' => $fromTransactionId,
            'to_transaction_id' => $toTransactionId,
            'fee_transaction_id' => $feeTransactionId,
            'created_by_account_id' => $createdByAccountId,
            'created_at' => sr_now(),
        ]);
        $correctionLogId = (int) $pdo->lastInsertId();

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return $correctionLogId;
}

function sr_asset_exchange_correction_group_id(string $exchangeGroupId): string
{
    return 'aec_' . substr(hash('sha256', $exchangeGroupId), 0, 48);
}

function sr_asset_exchange_apply_ratio(int $amount, int $numerator, int $denominator, string $roundingMode): int
{
    if ($amount <= 0 || $numerator <= 0 || $denominator <= 0) {
        return 0;
    }

    $product = $amount * $numerator;
    if ($roundingMode === 'ceil') {
        return intdiv($product + $denominator - 1, $denominator);
    }
    if ($roundingMode === 'round') {
        return intdiv(($product * 2) + $denominator, $denominator * 2);
    }

    return intdiv($product, $denominator);
}

function sr_asset_exchange_fee_applies(PDO $pdo, array $policy, int $accountId): bool
{
    $trigger = (string) ($policy['fee_trigger'] ?? 'none');
    if ($trigger === 'none') {
        return false;
    }
    if ($trigger === 'always') {
        return true;
    }

    $fromModuleKey = (string) ($policy['from_module_key'] ?? '');
    $assets = sr_asset_exchange_assets($pdo);
    if (empty($assets[$fromModuleKey]['cash_like'])) {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM sr_asset_exchange_logs
         WHERE account_id = :account_id
           AND to_module_key = :from_module_key
           AND status = 'completed'
         LIMIT 1"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'from_module_key' => $fromModuleKey,
    ]);

    return is_array($stmt->fetch());
}

function sr_asset_exchange_fee_amount(array $policy, int $fromAmount, int $toAmount): int
{
    $basis = (string) ($policy['fee_basis'] ?? 'to_amount') === 'from_amount' ? $fromAmount : $toAmount;
    $fee = (int) ($policy['fee_fixed_amount'] ?? 0);
    $rateNumerator = (int) ($policy['fee_rate_numerator'] ?? 0);
    $rateDenominator = 100;
    if ($rateNumerator > 0) {
        $fee += intdiv($basis * $rateNumerator, $rateDenominator);
    }
    if (isset($policy['fee_min_amount']) && $policy['fee_min_amount'] !== null) {
        $fee = max($fee, (int) $policy['fee_min_amount']);
    }
    if (isset($policy['fee_max_amount']) && $policy['fee_max_amount'] !== null) {
        $fee = min($fee, (int) $policy['fee_max_amount']);
    }

    return max(0, $fee);
}

function sr_asset_exchange_validate_policy_positive_result(array $policy): void
{
    if ((string) ($policy['status'] ?? '') !== 'enabled') {
        return;
    }

    $minAmount = max(1, (int) ($policy['min_amount'] ?? 1));
    $depositAmount = sr_asset_exchange_apply_ratio(
        $minAmount,
        max(1, (int) ($policy['rate_numerator'] ?? 1)),
        max(1, (int) ($policy['rate_denominator'] ?? 1)),
        (string) ($policy['rounding_mode'] ?? 'floor')
    );
    if ($depositAmount <= 0) {
        throw new InvalidArgumentException('최소 환전량 기준 입금 결과가 0입니다. 최소 환전량, 환산 기준 또는 반올림 방식을 조정하세요.');
    }

    if ((string) ($policy['fee_trigger'] ?? 'none') === 'none') {
        return;
    }

    $feeAmount = sr_asset_exchange_fee_amount($policy, $minAmount, $depositAmount);
    if ($depositAmount - $feeAmount <= 0) {
        throw new InvalidArgumentException('최소 환전량 기준 수수료 차감 후 입금 결과가 0 이하입니다. 최소 환전량 또는 수수료를 조정하세요.');
    }
}

function sr_asset_exchange_clean_module_key(string $value): string
{
    $value = strtolower(trim($value));
    return sr_is_safe_module_key($value) ? $value : '';
}

function sr_asset_exchange_positive_int(mixed $value, string $message): int
{
    $intValue = sr_asset_exchange_int_string($value);
    if ($intValue <= 0) {
        throw new InvalidArgumentException($message);
    }

    return $intValue;
}

function sr_asset_exchange_nullable_int(mixed $value, string $message): ?int
{
    if ($value === null || trim((string) $value) === '') {
        return null;
    }

    $intValue = sr_asset_exchange_required_int($value, $message);
    if ($intValue < 0) {
        throw new InvalidArgumentException($message);
    }

    return $intValue;
}

function sr_asset_exchange_optional_int(mixed $value, int $default, string $message): int
{
    if ($value === null || trim((string) $value) === '') {
        return $default;
    }

    return sr_asset_exchange_required_int($value, $message);
}

function sr_asset_exchange_optional_non_negative_int(mixed $value, int $default, string $message): int
{
    $intValue = sr_asset_exchange_optional_int($value, $default, $message);
    if ($intValue < 0) {
        throw new InvalidArgumentException($message);
    }

    return $intValue;
}

function sr_asset_exchange_required_int(mixed $value, string $message): int
{
    $string = trim((string) $value);
    if (preg_match('/\A-?\d+\z/', $string) !== 1) {
        throw new InvalidArgumentException($message);
    }

    return (int) $string;
}

function sr_asset_exchange_int_string(mixed $value): int
{
    $string = trim((string) $value);
    if (preg_match('/\A-?\d+\z/', $string) !== 1) {
        return 0;
    }

    return (int) $string;
}

function sr_asset_exchange_clean_text(string $value, int $maxLength): string
{
    return sr_clean_single_line($value, $maxLength);
}

function sr_asset_exchange_clean_reference_id(string $value, int $maxLength): string
{
    $value = trim($value);
    $value = preg_replace('/[^a-zA-Z0-9_.:-]/', '', $value);
    $value = is_string($value) ? $value : '';

    return substr($value, 0, $maxLength);
}

function sr_asset_exchange_group_id(): string
{
    return 'ae_' . date('YmdHis') . '_' . bin2hex(random_bytes(8));
}

function sr_asset_exchange_asset_label(array $assets, string $moduleKey): string
{
    return (string) ($assets[$moduleKey]['label'] ?? $moduleKey);
}

function sr_asset_exchange_available_policies(array $policies, array $assets): array
{
    $available = [];
    foreach ($policies as $policy) {
        if (!is_array($policy)) {
            continue;
        }

        if (
            sr_asset_exchange_is_canonical_pair((string) ($policy['from_module_key'] ?? ''), (string) ($policy['to_module_key'] ?? ''))
            && isset($assets[(string) ($policy['from_module_key'] ?? '')], $assets[(string) ($policy['to_module_key'] ?? '')])
        ) {
            $available[] = $policy;
        }
    }

    return $available;
}

function sr_asset_exchange_member_has_available_policy(PDO $pdo, int $accountId): bool
{
    if ($accountId <= 0) {
        return false;
    }
    if (!sr_asset_exchange_enabled($pdo)) {
        return false;
    }

    $assets = sr_asset_exchange_assets($pdo);
    if ($assets === []) {
        return false;
    }

    foreach (sr_asset_exchange_available_policies(sr_asset_exchange_policies($pdo, true), $assets) as $policy) {
        $fromModuleKey = (string) ($policy['from_module_key'] ?? '');
        if (!isset($assets[$fromModuleKey])) {
            continue;
        }

        $balanceFunction = (string) ($assets[$fromModuleKey]['balance_function'] ?? '');
        if (!function_exists($balanceFunction)) {
            continue;
        }

        $balance = (int) $balanceFunction($pdo, $accountId);
        $minAmount = max(1, (int) ($policy['min_amount'] ?? 1));
        if ($balance < $minAmount) {
            continue;
        }

        try {
            sr_asset_exchange_quote($pdo, $policy, $accountId, $minAmount);
            return true;
        } catch (Throwable) {
            continue;
        }
    }

    return false;
}
