<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_coupon_clean_key(string $value, int $maxLength = 60): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_]/', '', $value);
    $value = is_string($value) ? $value : '';

    return substr($value, 0, $maxLength);
}

function sr_coupon_clean_currency_code(string $value): string
{
    $value = strtoupper(trim($value));

    return preg_match('/\A[A-Z]{3}\z/', $value) === 1 ? $value : '';
}

function sr_coupon_nonnegative_int_or_null(mixed $value): ?int
{
    if (is_int($value)) {
        return $value >= 0 ? $value : null;
    }
    if (is_string($value)) {
        $value = trim($value);
        if (preg_match('/\A[0-9]+\z/', $value) === 1) {
            return (int) $value;
        }
    }

    return null;
}

function sr_coupon_optional_enum_value(array $data, string $key, array $allowed, string $default, string $message): string
{
    if (!array_key_exists($key, $data)) {
        return $default;
    }

    $value = trim((string) $data[$key]);
    if ($value === '' || !in_array($value, $allowed, true)) {
        throw new InvalidArgumentException($message);
    }

    return $value;
}

function sr_coupon_key_is_valid(string $couponKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $couponKey) === 1;
}

function sr_coupon_clean_text(string $value, int $maxLength): string
{
    return sr_clean_single_line($value, $maxLength);
}

function sr_coupon_like_keyword(string $keyword): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
}

function sr_coupon_statuses(): array
{
    return ['active', 'issue_stopped', 'disabled'];
}

function sr_coupon_status_label(string $status): string
{
    return match ($status) {
        'active' => '사용',
        'issue_stopped' => '지급 중지',
        'disabled' => '사용 중지',
        default => $status,
    };
}

function sr_coupon_validity_policies(): array
{
    return [
        'none' => '제한 없음',
        'fixed_range' => '고정 사용 기간',
        'fixed_expiry' => '고정 만료일',
        'relative_days' => '발급 후 일수',
    ];
}

function sr_coupon_validity_policy_label(string $policy): string
{
    $labels = sr_coupon_validity_policies();

    return (string) ($labels[$policy] ?? $policy);
}

function sr_coupon_definition_allows_issue(string $status): bool
{
    return $status === 'active';
}

function sr_coupon_definition_allows_redeem(string $status): bool
{
    return in_array($status, ['active', 'issue_stopped'], true);
}

function sr_coupon_default_settings(): array
{
    return [
        'usage_enabled' => true,
        'coupon_zone_label' => '쿠폰존',
        'notification_cases' => sr_coupon_default_notification_case_settings(),
        'disabled_reclaim_notifications_enabled' => true,
        'disabled_reclaim_notification_event_key' => 'issue.definition_disabled',
        'disabled_reclaim_notification_channels' => ['site'],
    ];
}

function sr_coupon_normalize_zone_label(string $label): string
{
    $label = sr_coupon_clean_text($label, 40);

    return $label !== '' ? $label : '쿠폰존';
}

function sr_coupon_notification_cases(): array
{
    return [
        'issue_created' => [
            'event_key' => 'issue.created',
            'label' => '지급 알림',
            'description' => '회원에게 쿠폰·이용권을 지급했을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'redemption_redeemed' => [
            'event_key' => 'redemption.redeemed',
            'label' => '사용 알림',
            'description' => '쿠폰·이용권이 사용되었을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'redemption_refunded' => [
            'event_key' => 'redemption.refunded',
            'label' => '사용 환불 알림',
            'description' => '쿠폰·이용권 사용 내역을 수동 환불했을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'issue_refunded' => [
            'event_key' => 'issue.refunded',
            'label' => '발급 환불 알림',
            'description' => '유료 발급된 쿠폰·이용권의 발급 자산을 환불했을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'issue_status_updated' => [
            'event_key' => 'issue.status_updated',
            'label' => '지급 상태 변경 알림',
            'description' => '지급 취소, 만료, 탈퇴 처리 등 회원 지급건 상태가 바뀌었을 때 보냅니다.',
            'default_enabled' => true,
        ],
        'definition_disabled' => [
            'event_key' => 'issue.definition_disabled',
            'label' => '사용 중지 회수 알림',
            'description' => '쿠폰 종류를 사용 중지로 전환하면 이미 지급받았고 아직 한 번도 사용하지 않은 활성 지급건의 회원에게 보냅니다.',
            'default_enabled' => true,
        ],
    ];
}

function sr_coupon_notification_case_key_for_event(string $eventKey): string
{
    foreach (sr_coupon_notification_cases() as $caseKey => $case) {
        if ((string) ($case['event_key'] ?? '') === $eventKey) {
            return (string) $caseKey;
        }
    }

    return '';
}

function sr_coupon_default_notification_case_settings(): array
{
    $settings = [];
    foreach (sr_coupon_notification_cases() as $caseKey => $case) {
        $settings[(string) $caseKey] = [
            'event_key' => (string) ($case['event_key'] ?? ''),
            'enabled' => !empty($case['default_enabled']),
            'channels' => ['site'],
        ];
    }

    return $settings;
}

function sr_coupon_account_notification_channel_keys(): array
{
    return ['site', 'email', 'slack_webhook', 'discord_webhook', 'telegram_bot'];
}

function sr_coupon_notification_channels_from_value(mixed $value): array
{
    $rawValues = is_array($value) ? $value : json_decode((string) $value, true);
    if (!is_array($rawValues)) {
        $rawValues = ['site'];
    }

    $allowed = sr_coupon_account_notification_channel_keys();
    $channels = [];
    foreach ($rawValues as $channel) {
        if (is_string($channel) && in_array($channel, $allowed, true)) {
            $channels[$channel] = $channel;
        }
    }

    return $channels === [] ? ['site'] : array_values($channels);
}

function sr_coupon_notification_channel_options(PDO $pdo): array
{
    $channels = ['site'];
    if (sr_coupon_notification_event_function($pdo) !== '') {
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

    $allowed = sr_coupon_account_notification_channel_keys();
    $options = [];
    foreach ($channels as $channel) {
        if (is_string($channel) && in_array($channel, $allowed, true)) {
            $options[$channel] = $channel;
        }
    }

    return $options === [] ? ['site'] : array_values($options);
}

function sr_coupon_notification_case_settings_from_value(mixed $value): array
{
    $rawSettings = is_array($value) ? $value : json_decode((string) $value, true);
    if (!is_array($rawSettings)) {
        $rawSettings = [];
    }

    $caseKeyByEventKey = [];
    foreach (sr_coupon_notification_cases() as $caseKey => $case) {
        $caseKeyByEventKey[(string) ($case['event_key'] ?? '')] = (string) $caseKey;
    }

    $normalized = sr_coupon_default_notification_case_settings();
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
            $normalized[$caseKey]['channels'] = sr_coupon_notification_channels_from_value($rawCaseSettings['channels']);
        }
    }

    return $normalized;
}

function sr_coupon_notification_setting_for_event(array $settings, string $eventKey): ?array
{
    $caseKey = sr_coupon_notification_case_key_for_event($eventKey);
    if ($caseKey === '') {
        return null;
    }

    $caseSettings = sr_coupon_notification_case_settings_from_value($settings['notification_cases'] ?? []);
    return isset($caseSettings[$caseKey]) && is_array($caseSettings[$caseKey]) ? $caseSettings[$caseKey] : null;
}

function sr_coupon_notification_event_uses_email(PDO $pdo, string $eventKey): bool
{
    $caseSetting = sr_coupon_notification_setting_for_event(sr_coupon_settings($pdo), $eventKey);
    if (!is_array($caseSetting) || empty($caseSetting['enabled'])) {
        return false;
    }

    return in_array('email', sr_coupon_notification_channels_from_value($caseSetting['channels'] ?? []), true);
}

function sr_coupon_admin_notification_email_warnings(PDO $pdo): array
{
    $warnings = [];
    $messages = [
        'issue.created' => '지급 알림 이메일 채널이 켜져 있습니다. 전체 회원 또는 그룹 지급은 대량 이메일 발송으로 이어질 수 있으니 대상 범위를 확인하세요.',
        'issue.status_updated' => '지급 상태 변경 알림 이메일 채널이 켜져 있습니다. 지급 취소를 실행하면 해당 회원에게 이메일 알림이 발송될 수 있습니다.',
        'issue.refunded' => '발급 환불 알림 이메일 채널이 켜져 있습니다. 환불 실행 후 해당 회원에게 이메일 알림이 발송될 수 있습니다.',
        'redemption.refunded' => '사용 환불 알림 이메일 채널이 켜져 있습니다. 환불 실행 후 해당 회원에게 이메일 알림이 발송될 수 있습니다.',
        'issue.definition_disabled' => '사용 중지 회수 알림 이메일 채널이 켜져 있습니다. 사용 중지로 전환하면 사용안함 지급건 회원에게 대량 이메일 발송이 발생할 수 있습니다.',
    ];
    foreach ($messages as $eventKey => $message) {
        if (sr_coupon_notification_event_uses_email($pdo, (string) $eventKey)) {
            $warnings[(string) $eventKey] = $message;
        }
    }

    return $warnings;
}

function sr_coupon_settings(PDO $pdo): array
{
    $storedSettings = sr_module_settings($pdo, 'coupon');
    $settings = array_merge(sr_coupon_default_settings(), $storedSettings);
    $settings['usage_enabled'] = sr_truthy($settings['usage_enabled'] ?? true);
    $settings['coupon_zone_label'] = sr_coupon_normalize_zone_label((string) ($settings['coupon_zone_label'] ?? ''));
    $notificationCases = sr_coupon_notification_case_settings_from_value($settings['notification_cases'] ?? []);
    if (array_key_exists('disabled_reclaim_notifications_enabled', $storedSettings)) {
        $notificationCases['definition_disabled']['enabled'] = sr_truthy($settings['disabled_reclaim_notifications_enabled'] ?? false);
    }
    if (array_key_exists('disabled_reclaim_notification_channels', $storedSettings)) {
        $notificationCases['definition_disabled']['channels'] = sr_coupon_notification_channels_from_value($settings['disabled_reclaim_notification_channels'] ?? ['site']);
    }
    $settings['notification_cases'] = $notificationCases;
    $settings['disabled_reclaim_notifications_enabled'] = array_key_exists('disabled_reclaim_notifications_enabled', $storedSettings)
        ? sr_truthy($settings['disabled_reclaim_notifications_enabled'] ?? false)
        : !empty($notificationCases['definition_disabled']['enabled']);
    $settings['disabled_reclaim_notification_channels'] = sr_coupon_notification_channels_from_value($notificationCases['definition_disabled']['channels'] ?? ['site']);
    $eventKey = sr_coupon_clean_text((string) ($settings['disabled_reclaim_notification_event_key'] ?? ''), 120);
    $settings['disabled_reclaim_notification_event_key'] = preg_match('/\A[a-z0-9_.-]{1,120}\z/', $eventKey) === 1
        ? $eventKey
        : 'issue.definition_disabled';

    return $settings;
}

function sr_coupon_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'coupon' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('쿠폰 모듈이 등록되어 있지 않습니다.');
    }

    $notificationCases = sr_coupon_notification_case_settings_from_value($settings['notification_cases'] ?? []);
    if (!array_key_exists('notification_cases', $settings)) {
        if (array_key_exists('disabled_reclaim_notifications_enabled', $settings)) {
            $notificationCases['definition_disabled']['enabled'] = sr_truthy($settings['disabled_reclaim_notifications_enabled'] ?? false);
        }
        if (array_key_exists('disabled_reclaim_notification_channels', $settings)) {
            $notificationCases['definition_disabled']['channels'] = sr_coupon_notification_channels_from_value($settings['disabled_reclaim_notification_channels'] ?? ['site']);
        }
    }
    $definitionNotificationsEnabled = !empty($notificationCases['definition_disabled']['enabled']);
    $eventKey = sr_coupon_clean_text((string) ($settings['disabled_reclaim_notification_event_key'] ?? 'issue.definition_disabled'), 120);
    if (preg_match('/\A[a-z0-9_.-]{1,120}\z/', $eventKey) !== 1) {
        throw new InvalidArgumentException('쿠폰 회수 알림 이벤트 키가 올바르지 않습니다.');
    }
    $channels = sr_coupon_notification_channels_from_value($notificationCases['definition_disabled']['channels'] ?? ['site']);
    $channelsJson = json_encode($channels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($channelsJson)) {
        $channelsJson = '["site"]';
    }
    $notificationCasesJson = json_encode($notificationCases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($notificationCasesJson)) {
        $notificationCasesJson = json_encode(sr_coupon_default_notification_case_settings(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $notificationCasesJson = is_string($notificationCasesJson) ? $notificationCasesJson : '{}';
    }
    $usageEnabled = array_key_exists('usage_enabled', $settings)
        ? sr_truthy($settings['usage_enabled'])
        : sr_coupon_usage_enabled($pdo);
    $couponZoneLabel = array_key_exists('coupon_zone_label', $settings)
        ? sr_coupon_normalize_zone_label((string) $settings['coupon_zone_label'])
        : sr_coupon_normalize_zone_label((string) (sr_coupon_settings($pdo)['coupon_zone_label'] ?? ''));

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
        ['coupon_zone_label', $couponZoneLabel, 'string'],
        ['notification_cases', $notificationCasesJson, 'json'],
        ['disabled_reclaim_notifications_enabled', $definitionNotificationsEnabled ? '1' : '0', 'bool'],
        ['disabled_reclaim_notification_event_key', $eventKey, 'string'],
        ['disabled_reclaim_notification_channels', $channelsJson, 'json'],
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

    sr_clear_module_settings_cache('coupon');
}

function sr_coupon_zone_label(PDO $pdo): string
{
    try {
        $settings = sr_coupon_settings($pdo);
    } catch (PDOException) {
        return '쿠폰존';
    }

    return sr_coupon_normalize_zone_label((string) ($settings['coupon_zone_label'] ?? ''));
}

function sr_coupon_usage_enabled(PDO $pdo): bool
{
    try {
        $settings = sr_coupon_settings($pdo);
    } catch (PDOException) {
        return true;
    }

    return !empty($settings['usage_enabled']);
}

function sr_coupon_issue_statuses(): array
{
    return ['active', 'used', 'expired', 'revoked', 'withdrawn_expired', 'refund_requested', 'refunded'];
}

function sr_coupon_types(): array
{
    return [
        'access' => '열람/이용권',
        'fixed_discount' => '정액 할인',
        'percent_discount' => '정률 할인',
    ];
}

function sr_coupon_type_label(string $couponType): string
{
    return (string) (sr_coupon_types()[$couponType] ?? $couponType);
}

function sr_coupon_definition_discount_columns_available(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query('SELECT discount_amount, discount_percent, discount_currency_code FROM sr_coupon_definitions LIMIT 1');
        return $stmt !== false;
    } catch (Throwable) {
        return false;
    }
}

function sr_coupon_definition_benefit_label(array $definition): string
{
    $couponType = (string) ($definition['coupon_type'] ?? 'access');
    if ($couponType === 'fixed_discount') {
        $amount = max(0, (int) ($definition['discount_amount'] ?? 0));
        $currencyCode = sr_coupon_clean_currency_code((string) ($definition['discount_currency_code'] ?? ''));
        if ($currencyCode === '') {
            $currencyCode = 'KRW';
        }

        if ($amount <= 0) {
            return '정액 할인';
        }

        return $currencyCode === 'KRW'
            ? number_format($amount) . '원 할인'
            : number_format($amount) . ' ' . $currencyCode . ' 할인';
    }
    if ($couponType === 'percent_discount') {
        $percent = max(0, (int) ($definition['discount_percent'] ?? 0));

        return $percent > 0 ? (string) $percent . '% 할인' : '정률 할인';
    }

    return sr_coupon_type_label($couponType);
}

function sr_coupon_definition_validity_label(array $definition): string
{
    $policy = (string) ($definition['validity_policy'] ?? 'none');
    if ($policy === 'fixed_range') {
        return '사용 기간: '
            . ((string) ($definition['valid_from'] ?? '') !== '' ? (string) $definition['valid_from'] : '시작 미정')
            . ' ~ '
            . ((string) ($definition['valid_until'] ?? '') !== '' ? (string) $definition['valid_until'] : '만료 미정');
    }
    if ($policy === 'fixed_expiry') {
        return '만료: ' . ((string) ($definition['valid_until'] ?? '') !== '' ? (string) $definition['valid_until'] : '미정');
    }
    if ($policy === 'relative_days') {
        return '발급 후 ' . number_format(max(0, (int) ($definition['validity_days'] ?? 0))) . '일';
    }

    return '제한 없음';
}

function sr_coupon_discount_application(array $issue, array $pricing): array
{
    $couponType = (string) ($issue['coupon_type'] ?? 'access');
    if ($couponType === 'access') {
        $priceAmount = !empty($pricing['ok']) ? sr_coupon_nonnegative_int_or_null($pricing['price_amount'] ?? 0) : 0;
        if ($priceAmount === null) {
            return ['ok' => false, 'discount_amount' => 0, 'remaining_amount' => 0, 'message' => '쿠폰 사용처 가격이 올바르지 않습니다.'];
        }
        return [
            'ok' => true,
            'discount_amount' => $priceAmount,
            'remaining_amount' => 0,
            'full_coverage' => true,
            'coupon_type' => $couponType,
        ];
    }

    if (empty($pricing['ok'])) {
        return ['ok' => false, 'discount_amount' => 0, 'remaining_amount' => 0, 'message' => '쿠폰 사용처 가격을 확인할 수 없습니다.'];
    }

    $priceAmount = sr_coupon_nonnegative_int_or_null($pricing['price_amount'] ?? 0);
    if ($priceAmount === null) {
        return ['ok' => false, 'discount_amount' => 0, 'remaining_amount' => 0, 'message' => '쿠폰 사용처 가격이 올바르지 않습니다.'];
    }
    if ($priceAmount <= 0) {
        return ['ok' => false, 'discount_amount' => 0, 'remaining_amount' => 0, 'message' => '할인할 결제 금액이 없습니다.'];
    }

    $discountAmount = 0;
    if ($couponType === 'fixed_discount') {
        $priceCurrency = sr_coupon_clean_currency_code((string) ($pricing['currency_code'] ?? ''));
        $discountCurrency = sr_coupon_clean_currency_code((string) ($issue['discount_currency_code'] ?? ''));
        if ($discountCurrency === '') {
            $discountCurrency = 'KRW';
        }
        if ($priceCurrency === '' || $priceCurrency !== $discountCurrency) {
            return ['ok' => false, 'discount_amount' => 0, 'remaining_amount' => $priceAmount, 'message' => '쿠폰 할인 통화가 결제 통화와 일치하지 않습니다.'];
        }
        $discountAmount = max(0, (int) ($issue['discount_amount'] ?? 0));
    } elseif ($couponType === 'percent_discount') {
        $percent = max(0, min(100, (int) ($issue['discount_percent'] ?? 0)));
        $discountAmount = intdiv($priceAmount * $percent, 100);
    } else {
        return ['ok' => false, 'discount_amount' => 0, 'remaining_amount' => $priceAmount, 'message' => '지원하지 않는 쿠폰 혜택 유형입니다.'];
    }

    $discountAmount = min($priceAmount, $discountAmount);
    if ($discountAmount <= 0) {
        return ['ok' => false, 'discount_amount' => 0, 'remaining_amount' => $priceAmount, 'message' => '적용 가능한 쿠폰 할인액이 없습니다.'];
    }

    return [
        'ok' => true,
        'discount_amount' => $discountAmount,
        'remaining_amount' => max(0, $priceAmount - $discountAmount),
        'full_coverage' => $discountAmount >= $priceAmount,
        'coupon_type' => $couponType,
    ];
}

function sr_coupon_time_html(?string $value, string $emptyText = ''): string
{
    return sr_relative_time_html($value, $emptyText);
}

function sr_coupon_expire_active_issues(PDO $pdo, ?int $accountId = null): int
{
    if (!sr_coupon_tables_available($pdo)) {
        return 0;
    }

    $now = sr_now();
    $where = "status = 'active' AND expires_at IS NOT NULL AND expires_at < :expires_before";
    $params = [
        'expires_before' => $now,
        'updated_at' => $now,
    ];
    if ($accountId !== null && $accountId > 0) {
        $where .= ' AND account_id = :account_id';
        $params['account_id'] = $accountId;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_coupon_issues
         SET status = \'expired\',
             updated_at = :updated_at
         WHERE ' . $where
    );
    $stmt->execute($params);

    return $stmt->rowCount();
}

function sr_coupon_for_update_clause(PDO $pdo): string
{
    $driver = '';
    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable) {
        $driver = '';
    }

    return $driver === 'sqlite' ? '' : ' FOR UPDATE';
}
