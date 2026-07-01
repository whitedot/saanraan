<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/helpers/common.php';

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
    return ['site', 'email', 'telegram_bot'];
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
        'issue.definition_disabled' => '사용 중지 회수 알림 이메일 채널이 켜져 있습니다. 사용 중지로 전환하면 미사용 지급건 회원에게 대량 이메일 발송이 발생할 수 있습니다.',
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

function sr_coupon_target_types(?PDO $pdo = null): array
{
    $targetTypes = [
        'all' => '전체',
    ];

    if ($pdo === null) {
        return $targetTypes;
    }

    foreach (sr_coupon_target_contracts($pdo) as $targetType => $target) {
        $targetTypes[(string) $targetType] = (string) ($target['label'] ?? $targetType);
    }

    return $targetTypes;
}

function sr_coupon_target_display(string $targetType, string $targetId, ?PDO $pdo = null): string
{
    $targetTypes = sr_coupon_target_types($pdo);
    $label = (string) ($targetTypes[$targetType] ?? $targetType);
    if ($targetType === 'all' || $targetId === '') {
        return $label;
    }

    return $label . ' #' . $targetId;
}

function sr_coupon_reference_display(string $moduleKey, string $referenceType, string $referenceId): string
{
    $moduleLabels = [
        'content' => '콘텐츠',
        'community' => '커뮤니티',
        'quiz' => '퀴즈',
        'survey' => '설문',
    ];
    $referenceLabels = [
        'content.view' => '콘텐츠 열람',
        'content.download' => '콘텐츠 다운로드',
        'content.action' => '콘텐츠 완료 처리',
        'community.post' => '커뮤니티 게시글',
        'community.comment' => '커뮤니티 댓글',
        'quiz.attempt' => '퀴즈 응시',
        'survey.response' => '설문 응답',
    ];

    $parts = [];
    if ($moduleKey !== '') {
        $parts[] = (string) ($moduleLabels[$moduleKey] ?? $moduleKey);
    }
    if ($referenceType !== '') {
        $parts[] = function_exists('sr_admin_code_label')
            ? sr_admin_code_label($referenceType, 'reference_type')
            : (string) ($referenceLabels[$referenceType] ?? $referenceType);
    }
    if ($referenceId !== '') {
        $parts[] = '#' . $referenceId;
    }

    return implode(' ', $parts);
}

function sr_coupon_refundable_policies(): array
{
    return [
        'none' => '환급 없음',
        'refundable' => '환급 가능',
    ];
}

function sr_coupon_claim_campaign_statuses(): array
{
    return ['draft', 'active', 'paused', 'ended'];
}

function sr_coupon_claim_campaign_status_label(string $status): string
{
    return match ($status) {
        'draft' => '초안',
        'active' => '진행',
        'paused' => '중지',
        'ended' => '종료',
        default => $status,
    };
}

function sr_coupon_claim_types(): array
{
    return ['free', 'paid'];
}

function sr_coupon_claim_type_label(string $claimType): string
{
    return match ($claimType) {
        'paid' => '유료',
        default => '무료',
    };
}

function sr_coupon_asset_options(PDO $pdo): array
{
    if (!function_exists('sr_member_ledger_asset_definitions')) {
        require_once SR_ROOT . '/modules/member/helpers/assets.php';
    }

    return sr_member_ledger_asset_definitions($pdo);
}

function sr_coupon_asset_module_keys_from_value(PDO $pdo, mixed $value): array
{
    $assetOptions = sr_coupon_asset_options($pdo);
    $rawValues = is_array($value) ? $value : json_decode((string) $value, true);
    if (!is_array($rawValues)) {
        $rawValues = preg_split('/[\s,]+/', (string) $value);
    }

    $selected = [];
    foreach (is_array($rawValues) ? $rawValues : [] as $rawValue) {
        $assetModule = sr_coupon_clean_key((string) $rawValue, 60);
        if (isset($assetOptions[$assetModule])) {
            $selected[$assetModule] = true;
        }
    }

    $ordered = [];
    foreach (array_keys($assetOptions) as $assetModule) {
        if (isset($selected[$assetModule])) {
            $ordered[] = $assetModule;
        }
    }

    return $ordered;
}

function sr_coupon_asset_modules_json(array $assetModules): string
{
    $encoded = json_encode(array_values($assetModules), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return is_string($encoded) ? $encoded : '[]';
}

function sr_coupon_assert_paid_claim_asset_purchase_power(PDO $pdo, array $assetModules, string $priceCurrencyCode): void
{
    $assetOptions = sr_coupon_asset_options($pdo);
    $priceCurrencyCode = function_exists('sr_normalize_currency_code')
        ? sr_normalize_currency_code($priceCurrencyCode)
        : strtoupper(trim($priceCurrencyCode));
    $priceMinUnit = function_exists('sr_currency_min_unit') ? sr_currency_min_unit($priceCurrencyCode) : 1;
    if ($priceCurrencyCode === '' || $priceMinUnit < 1) {
        throw new InvalidArgumentException('유료 발급 통화가 지원되지 않습니다.');
    }

    foreach ($assetModules as $assetModule) {
        $assetModule = (string) $assetModule;
        if (!isset($assetOptions[$assetModule])) {
            throw new InvalidArgumentException('유료 발급에 사용할 포인트/금액 항목을 다시 선택하세요.');
        }

        $purchasePower = function_exists('sr_member_asset_purchase_power_from_contract')
            ? sr_member_asset_purchase_power_from_contract($pdo, $assetOptions[$assetModule])
            : (is_array($assetOptions[$assetModule]['purchase_power'] ?? null) ? $assetOptions[$assetModule]['purchase_power'] : []);
        $assetUnits = (int) ($purchasePower['asset_units'] ?? 0);
        $settlementUnits = (int) ($purchasePower['settlement_units'] ?? 0);
        $settlementCurrency = function_exists('sr_normalize_currency_code')
            ? sr_normalize_currency_code((string) ($purchasePower['settlement_currency'] ?? ''))
            : strtoupper(trim((string) ($purchasePower['settlement_currency'] ?? '')));

        if ($assetUnits < 1 || $settlementUnits < 1 || $settlementCurrency !== $priceCurrencyCode) {
            throw new InvalidArgumentException('유료 발급 통화와 허용 포인트/금액 항목의 환산 통화가 일치해야 합니다.');
        }
    }
}

function sr_coupon_asset_module_labels(PDO $pdo, mixed $value): string
{
    $assetOptions = sr_coupon_asset_options($pdo);
    $labels = [];
    foreach (sr_coupon_asset_module_keys_from_value($pdo, $value) as $assetModule) {
        $labels[] = (string) ($assetOptions[$assetModule]['label'] ?? $assetModule);
    }

    return $labels !== [] ? implode(', ', $labels) : '없음';
}

function sr_coupon_claim_surfaces(): array
{
    return ['coupon_zone', 'direct_link', 'popup_layer', 'content_embed'];
}

function sr_coupon_claim_log_statuses(): array
{
    return ['reserved', 'pending_payment', 'issued', 'failed', 'cancelled', 'expired'];
}

function sr_coupon_claim_log_status_label(string $status): string
{
    return match ($status) {
        'reserved' => '예약',
        'pending_payment' => '결제 대기',
        'issued' => '발급 완료',
        'failed' => '실패',
        'cancelled' => '취소',
        'expired' => '만료',
        'expired_unmaterialized' => '만료(미정리)',
        default => $status,
    };
}

function sr_coupon_claim_source_label(string $source): string
{
    return match ($source) {
        'coupon_zone' => '쿠폰존',
        'direct_link' => '직접 링크',
        'popup_layer' => '팝업레이어',
        'content_embed' => '본문 임베드',
        'admin' => '관리자',
        default => $source,
    };
}

function sr_coupon_claim_tables_available(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM sr_coupon_claim_campaigns LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_coupon_claim_logs LIMIT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

function sr_coupon_claim_log_asset_reference_columns_available(PDO $pdo): bool
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }

    try {
        $stmt = $pdo->query('SELECT asset_reference_module, asset_reference_type, asset_reference_id FROM sr_coupon_claim_logs LIMIT 1');
        $available = $stmt !== false;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function sr_coupon_claim_dedupe_hash(string $dedupeKey): string
{
    return hash('sha256', $dedupeKey);
}

function sr_coupon_claim_surfaces_json(array $surfaces): string
{
    $allowed = array_fill_keys(sr_coupon_claim_surfaces(), true);
    $values = [];
    foreach ($surfaces as $surface) {
        $surface = (string) $surface;
        if (isset($allowed[$surface])) {
            $values[$surface] = true;
        }
    }

    return json_encode(array_keys($values), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function sr_coupon_claim_surfaces_from_value(mixed $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($value)) {
        return [];
    }

    $allowed = array_fill_keys(sr_coupon_claim_surfaces(), true);
    $surfaces = [];
    foreach ($value as $surface) {
        $surface = (string) $surface;
        if (isset($allowed[$surface])) {
            $surfaces[$surface] = true;
        }
    }

    return array_keys($surfaces);
}

function sr_coupon_claim_datetime_or_null(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $normalized = str_replace('T', ' ', substr($value, 0, 19));
    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', strlen($normalized) === 16 ? $normalized . ':00' : $normalized);
    if (!$date instanceof DateTimeImmutable) {
        throw new InvalidArgumentException('날짜와 시간 형식이 올바르지 않습니다.');
    }

    return $date->format('Y-m-d H:i:s');
}

function sr_coupon_claim_campaign_by_key(PDO $pdo, string $campaignKey, bool $forUpdate = false): ?array
{
    $campaignKey = sr_coupon_clean_key($campaignKey);
    if (!sr_coupon_key_is_valid($campaignKey) || !sr_coupon_claim_tables_available($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT c.*, d.coupon_key, d.title AS coupon_title, d.description AS coupon_description, d.status AS coupon_status, d.target_type, d.target_id, d.max_uses_per_issue
         FROM sr_coupon_claim_campaigns c
         INNER JOIN sr_coupon_definitions d ON d.id = c.coupon_definition_id
         WHERE c.campaign_key = :campaign_key
         LIMIT 1' . ($forUpdate ? sr_coupon_for_update_clause($pdo) : '')
    );
    $stmt->execute(['campaign_key' => $campaignKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_coupon_claim_campaign_by_id(PDO $pdo, int $campaignId): ?array
{
    if ($campaignId <= 0 || !sr_coupon_claim_tables_available($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT c.*, d.coupon_key, d.title AS coupon_title, d.description AS coupon_description, d.status AS coupon_status, d.target_type, d.target_id, d.max_uses_per_issue
         FROM sr_coupon_claim_campaigns c
         INNER JOIN sr_coupon_definitions d ON d.id = c.coupon_definition_id
         WHERE c.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $campaignId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_coupon_public_flash_result(array $result): void
{
    $_SESSION['sr_coupon_public_flash'] = [
        'errors' => array_values(array_map('strval', $result['errors'] ?? [])),
        'notice' => (string) ($result['notice'] ?? ''),
    ];
}

function sr_coupon_public_pop_flash_result(): array
{
    $result = is_array($_SESSION['sr_coupon_public_flash'] ?? null)
        ? $_SESSION['sr_coupon_public_flash']
        : ['errors' => [], 'notice' => ''];
    unset($_SESSION['sr_coupon_public_flash']);

    return [
        'errors' => array_values(array_map('strval', $result['errors'] ?? [])),
        'notice' => (string) ($result['notice'] ?? ''),
    ];
}

function sr_coupon_public_claim_intent_key(int $campaignId, int $accountId): string
{
    return (string) max(0, $accountId) . ':' . (string) $campaignId;
}

function sr_coupon_public_claim_intent_token(int $campaignId, int $accountId = 0): string
{
    if ($campaignId <= 0) {
        return '';
    }

    if (!isset($_SESSION['sr_coupon_claim_intents']) || !is_array($_SESSION['sr_coupon_claim_intents'])) {
        $_SESSION['sr_coupon_claim_intents'] = [];
    }

    $key = sr_coupon_public_claim_intent_key($campaignId, $accountId);
    $token = (string) ($_SESSION['sr_coupon_claim_intents'][$key] ?? '');
    if ($token === '') {
        $token = bin2hex(random_bytes(16));
        $_SESSION['sr_coupon_claim_intents'][$key] = $token;
    }

    return $token;
}

function sr_coupon_public_claim_intent_token_matches(int $campaignId, int $accountId, string $token): bool
{
    $token = trim($token);
    if ($campaignId <= 0 || $accountId <= 0 || $token === '') {
        return false;
    }
    if (!isset($_SESSION['sr_coupon_claim_intents']) || !is_array($_SESSION['sr_coupon_claim_intents'])) {
        return false;
    }

    $current = (string) ($_SESSION['sr_coupon_claim_intents'][sr_coupon_public_claim_intent_key($campaignId, $accountId)] ?? '');

    return $current !== '' && hash_equals($current, $token);
}

function sr_coupon_public_rotate_claim_intent_token(int $campaignId, int $accountId = 0): string
{
    if ($campaignId <= 0) {
        return '';
    }

    if (!isset($_SESSION['sr_coupon_claim_intents']) || !is_array($_SESSION['sr_coupon_claim_intents'])) {
        $_SESSION['sr_coupon_claim_intents'] = [];
    }

    $token = bin2hex(random_bytes(16));
    $_SESSION['sr_coupon_claim_intents'][sr_coupon_public_claim_intent_key($campaignId, $accountId)] = $token;

    return $token;
}

function sr_coupon_create_claim_campaign(PDO $pdo, array $data): int
{
    if (!sr_coupon_claim_tables_available($pdo)) {
        throw new InvalidArgumentException('쿠폰 발급 캠페인 업데이트를 먼저 적용하세요.');
    }

    $payload = sr_coupon_claim_campaign_payload($pdo, $data, null);
    $now = sr_now();
    $payload['created_at'] = $now;
    $payload['updated_at'] = $now;

    $stmt = $pdo->prepare(
        'INSERT INTO sr_coupon_claim_campaigns
            (campaign_key, coupon_definition_id, title, description, status, claim_type, price_amount, price_currency_code, allowed_asset_modules_json, starts_at, ends_at, issue_expires_in_days, issue_expires_at, total_claim_limit, per_account_limit, visibility, exposure_surfaces_json, login_required, created_at, updated_at)
         VALUES
            (:campaign_key, :coupon_definition_id, :title, :description, :status, :claim_type, :price_amount, :price_currency_code, :allowed_asset_modules_json, :starts_at, :ends_at, :issue_expires_in_days, :issue_expires_at, :total_claim_limit, :per_account_limit, :visibility, :exposure_surfaces_json, :login_required, :created_at, :updated_at)'
    );
    $stmt->execute($payload);

    return (int) $pdo->lastInsertId();
}

function sr_coupon_update_claim_campaign(PDO $pdo, int $campaignId, array $data): void
{
    if (!sr_coupon_claim_tables_available($pdo)) {
        throw new InvalidArgumentException('쿠폰 발급 캠페인 업데이트를 먼저 적용하세요.');
    }
    if ($campaignId <= 0) {
        throw new InvalidArgumentException('수정할 발급 캠페인을 선택하세요.');
    }

    $current = sr_coupon_claim_campaign_by_id($pdo, $campaignId);
    if (!is_array($current)) {
        throw new InvalidArgumentException('수정할 발급 캠페인을 찾을 수 없습니다.');
    }
    $payload = sr_coupon_claim_campaign_payload($pdo, $data, $current);
    $hasClaims = sr_coupon_claim_campaign_log_count($pdo, $campaignId) > 0;
    if ($hasClaims && (string) ($payload['campaign_key'] ?? '') !== (string) ($current['campaign_key'] ?? '')) {
        throw new InvalidArgumentException('발급 로그가 있는 캠페인의 key는 변경할 수 없습니다.');
    }
    if ($hasClaims && (int) ($payload['coupon_definition_id'] ?? 0) !== (int) ($current['coupon_definition_id'] ?? 0)) {
        throw new InvalidArgumentException('발급 로그가 있는 캠페인의 연결 쿠폰은 변경할 수 없습니다.');
    }
    if ($hasClaims && (string) ($payload['claim_type'] ?? '') !== (string) ($current['claim_type'] ?? '')) {
        throw new InvalidArgumentException('발급 로그가 있는 캠페인의 발급 유형은 변경할 수 없습니다.');
    }
    if ($hasClaims && (int) ($payload['price_amount'] ?? 0) !== (int) ($current['price_amount'] ?? 0)) {
        throw new InvalidArgumentException('발급 로그가 있는 캠페인의 가격은 변경할 수 없습니다.');
    }
    if ($hasClaims && (string) ($payload['price_currency_code'] ?? '') !== (string) ($current['price_currency_code'] ?? '')) {
        throw new InvalidArgumentException('발급 로그가 있는 캠페인의 통화는 변경할 수 없습니다.');
    }
    if ($hasClaims && (string) ($payload['allowed_asset_modules_json'] ?? '') !== (string) ($current['allowed_asset_modules_json'] ?? '')) {
        throw new InvalidArgumentException('발급 로그가 있는 캠페인의 허용 포인트/금액 항목은 변경할 수 없습니다.');
    }

    $occupiedTotal = sr_coupon_claim_campaign_occupied_count($pdo, $campaignId);
    if ($payload['total_claim_limit'] !== null && $occupiedTotal > (int) $payload['total_claim_limit']) {
        throw new InvalidArgumentException('총 발급 한도는 이미 점유된 발급 수보다 작게 줄일 수 없습니다.');
    }
    $maxPerAccount = sr_coupon_claim_campaign_max_account_occupancy($pdo, $campaignId);
    if ($maxPerAccount > (int) $payload['per_account_limit']) {
        throw new InvalidArgumentException('회원당 발급 한도는 이미 점유된 회원별 발급 수보다 작게 줄일 수 없습니다.');
    }

    $payload['id'] = $campaignId;
    $payload['updated_at'] = sr_now();
    $stmt = $pdo->prepare(
        'UPDATE sr_coupon_claim_campaigns
         SET campaign_key = :campaign_key,
             coupon_definition_id = :coupon_definition_id,
             title = :title,
             description = :description,
             status = :status,
             claim_type = :claim_type,
             price_amount = :price_amount,
             price_currency_code = :price_currency_code,
             allowed_asset_modules_json = :allowed_asset_modules_json,
             starts_at = :starts_at,
             ends_at = :ends_at,
             issue_expires_in_days = :issue_expires_in_days,
             issue_expires_at = :issue_expires_at,
             total_claim_limit = :total_claim_limit,
             per_account_limit = :per_account_limit,
             visibility = :visibility,
             exposure_surfaces_json = :exposure_surfaces_json,
             login_required = :login_required,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute($payload);
}

function sr_coupon_claim_campaign_payload(PDO $pdo, array $data, ?array $current): array
{
    $campaignKey = sr_coupon_clean_key((string) ($data['campaign_key'] ?? ''));
    if (!sr_coupon_key_is_valid($campaignKey)) {
        throw new InvalidArgumentException('캠페인 키는 영문 소문자로 시작하고 소문자, 숫자, 밑줄만 사용할 수 있습니다.');
    }
    $stmt = $pdo->prepare('SELECT id FROM sr_coupon_claim_campaigns WHERE campaign_key = :campaign_key LIMIT 1');
    $stmt->execute(['campaign_key' => $campaignKey]);
    $existing = $stmt->fetch();
    if (is_array($existing) && (int) ($existing['id'] ?? 0) !== (int) ($current['id'] ?? 0)) {
        throw new InvalidArgumentException('이미 사용 중인 캠페인 key입니다.');
    }

    $definitionId = (int) ($data['coupon_definition_id'] ?? 0);
    $definition = sr_coupon_definition_by_id($pdo, $definitionId);
    if (!is_array($definition)) {
        throw new InvalidArgumentException('연결할 쿠폰을 선택하세요.');
    }

    $title = sr_coupon_clean_text((string) ($data['title'] ?? ''), 120);
    if ($title === '') {
        throw new InvalidArgumentException('캠페인 제목을 입력하세요.');
    }

    $status = sr_coupon_optional_enum_value($data, 'status', sr_coupon_claim_campaign_statuses(), 'draft', '발급 캠페인 상태가 올바르지 않습니다.');
    if ($status === 'active' && !sr_coupon_definition_allows_issue((string) ($definition['status'] ?? ''))) {
        throw new InvalidArgumentException('발급 가능한 쿠폰만 활성 발급 캠페인에 연결할 수 있습니다.');
    }
    $claimType = sr_coupon_optional_enum_value($data, 'claim_type', sr_coupon_claim_types(), 'free', '발급 유형이 올바르지 않습니다.');
    $priceAmount = null;
    $priceCurrencyCode = '';
    $allowedAssetModulesJson = null;
    if ($claimType === 'paid') {
        $priceAmount = sr_coupon_claim_positive_int($data['price_amount'] ?? '', 999999999, '유료 발급 가격');
        $priceCurrencyCode = sr_coupon_clean_currency_code((string) ($data['price_currency_code'] ?? 'KRW'));
        if ($priceCurrencyCode === '') {
            throw new InvalidArgumentException('유료 발급 통화를 입력하세요.');
        }
        $allowedAssetModules = sr_coupon_asset_module_keys_from_value($pdo, $data['allowed_asset_modules'] ?? []);
        if ($allowedAssetModules === []) {
            throw new InvalidArgumentException('유료 발급에 사용할 포인트/금액 항목을 하나 이상 선택하세요.');
        }
        if ($status === 'active') {
            sr_coupon_assert_paid_claim_asset_purchase_power($pdo, $allowedAssetModules, $priceCurrencyCode);
        }
        $allowedAssetModulesJson = sr_coupon_asset_modules_json($allowedAssetModules);
    }

    $perAccountLimit = sr_coupon_claim_positive_int($data['per_account_limit'] ?? '1', 1000, '회원당 발급 한도');
    $totalClaimLimit = sr_coupon_claim_optional_positive_int($data['total_claim_limit'] ?? '', 999999999, '총 발급 한도');
    $startsAt = sr_coupon_claim_datetime_or_null((string) ($data['starts_at'] ?? ''));
    $endsAt = sr_coupon_claim_datetime_or_null((string) ($data['ends_at'] ?? ''));
    if ($startsAt !== null && $endsAt !== null && strcmp($startsAt, $endsAt) > 0) {
        throw new InvalidArgumentException('발급 종료 시각은 시작 시각 이후여야 합니다.');
    }

    $surfaces = sr_coupon_claim_surfaces_from_value($data['exposure_surfaces'] ?? ['coupon_zone']);
    if ($surfaces === []) {
        $surfaces = ['coupon_zone'];
    }

    return [
        'campaign_key' => $campaignKey,
        'coupon_definition_id' => $definitionId,
        'title' => $title,
        'description' => sr_coupon_clean_text((string) ($data['description'] ?? ''), 1000),
        'status' => $status,
        'claim_type' => $claimType,
        'price_amount' => $priceAmount,
        'price_currency_code' => $priceCurrencyCode,
        'allowed_asset_modules_json' => $allowedAssetModulesJson,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'issue_expires_in_days' => sr_coupon_claim_optional_positive_int($data['issue_expires_in_days'] ?? '', 3650, '발급본 만료일수'),
        'issue_expires_at' => sr_coupon_claim_datetime_or_null((string) ($data['issue_expires_at'] ?? '')),
        'total_claim_limit' => $totalClaimLimit,
        'per_account_limit' => $perAccountLimit,
        'visibility' => (string) ($data['visibility'] ?? 'hidden') === 'public' ? 'public' : 'hidden',
        'exposure_surfaces_json' => sr_coupon_claim_surfaces_json($surfaces),
        'login_required' => !empty($data['login_required']) ? 1 : 0,
    ];
}

function sr_coupon_claim_positive_int(mixed $value, int $max, string $label): int
{
    if (is_array($value)) {
        throw new InvalidArgumentException($label . '는 1부터 ' . number_format($max) . ' 사이의 정수로 입력하세요.');
    }
    $stringValue = trim((string) $value);
    if ($stringValue === '' || preg_match('/\A[1-9][0-9]*\z/', $stringValue) !== 1) {
        throw new InvalidArgumentException($label . '는 1부터 ' . number_format($max) . ' 사이의 정수로 입력하세요.');
    }
    $intValue = (int) $stringValue;
    if ($intValue < 1 || $intValue > $max) {
        throw new InvalidArgumentException($label . '는 1부터 ' . number_format($max) . ' 사이의 정수로 입력하세요.');
    }

    return $intValue;
}

function sr_coupon_claim_optional_positive_int(mixed $value, int $max, string $label): ?int
{
    if (is_array($value)) {
        throw new InvalidArgumentException($label . '는 비워 두거나 1부터 ' . number_format($max) . ' 사이의 정수로 입력하세요.');
    }
    $stringValue = trim((string) $value);
    if ($stringValue === '') {
        return null;
    }

    return sr_coupon_claim_positive_int($stringValue, $max, $label);
}

function sr_coupon_claim_campaign_log_count(PDO $pdo, int $campaignId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sr_coupon_claim_logs WHERE campaign_id = :campaign_id');
    $stmt->execute(['campaign_id' => $campaignId]);

    return (int) $stmt->fetchColumn();
}

function sr_coupon_claim_campaign_occupied_condition(string $nowParam = ':now_value'): string
{
    return "status = 'issued' OR (status IN ('reserved', 'pending_payment') AND (reserved_until IS NULL OR reserved_until >= " . $nowParam . '))';
}

function sr_coupon_claim_campaign_occupied_count(PDO $pdo, int $campaignId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sr_coupon_claim_logs
         WHERE campaign_id = :campaign_id
           AND (' . sr_coupon_claim_campaign_occupied_condition(':now_value') . ')'
    );
    $stmt->execute([
        'campaign_id' => $campaignId,
        'now_value' => sr_now(),
    ]);

    return (int) $stmt->fetchColumn();
}

function sr_coupon_claim_campaign_max_account_occupancy(PDO $pdo, int $campaignId): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS occupied_count
         FROM sr_coupon_claim_logs
         WHERE campaign_id = :campaign_id
           AND (' . sr_coupon_claim_campaign_occupied_condition(':now_value') . ')
         GROUP BY account_id
         ORDER BY occupied_count DESC
         LIMIT 1'
    );
    $stmt->execute([
        'campaign_id' => $campaignId,
        'now_value' => sr_now(),
    ]);

    return (int) $stmt->fetchColumn();
}

function sr_coupon_target_contract_helper_path(string $moduleKey, array $target): string
{
    $helpers = (string) ($target['helpers'] ?? '');
    if ($helpers === '' || preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helpers) !== 1) {
        return '';
    }

    $path = SR_ROOT . '/modules/' . $moduleKey . '/' . $helpers;
    return is_file($path) ? $path : '';
}

function sr_coupon_target_contracts(PDO $pdo): array
{
    $contracts = [];
    foreach (sr_enabled_module_contract_files($pdo, 'coupon-targets.php', ['coupon']) as $moduleKey => $file) {
        $contractTargets = sr_load_module_contract_file($moduleKey, $file);
        if (!is_array($contractTargets)) {
            continue;
        }

        foreach ($contractTargets as $target) {
            if (!is_array($target)) {
                continue;
            }

            $targetType = (string) ($target['target_type'] ?? '');
            $label = sr_coupon_clean_text((string) ($target['label'] ?? ''), 80);
            if ($targetType === '' || $label === '' || preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $targetType) !== 1) {
                continue;
            }
            if (isset($contracts[$targetType])) {
                continue;
            }

            $helperPath = sr_coupon_target_contract_helper_path($moduleKey, $target);
            if ($helperPath !== '') {
                require_once $helperPath;
            }

            $target['module_key'] = $moduleKey;
            $target['label'] = $label;
            $target['capabilities'] = sr_coupon_target_contract_capabilities($target);
            $contracts[$targetType] = $target;
        }
    }

    return $contracts;
}

function sr_coupon_target_contract_capabilities(array $target): array
{
    $capabilities = [];
    foreach ($target['capabilities'] ?? [] as $capability) {
        $capability = (string) $capability;
        if (preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $capability) === 1) {
            $capabilities[$capability] = true;
        }
    }

    foreach ([
        'search' => 'search_function',
        'health' => 'health_function',
        'admin_url' => 'admin_url_function',
        'pricing' => 'pricing_function',
        'redeem' => 'redeem_function',
        'revoke_access' => 'revoke_access_function',
    ] as $capability => $functionKey) {
        $functionName = (string) ($target[$functionKey] ?? '');
        if ($functionName !== '' && function_exists($functionName)) {
            $capabilities[$capability] = true;
        }
    }

    return array_keys($capabilities);
}

function sr_coupon_target_contract_has_capability(array $target, string $capability): bool
{
    return in_array($capability, array_map('strval', $target['capabilities'] ?? []), true);
}

function sr_coupon_target_capability_labels(): array
{
    return [
        'search' => '검색',
        'health' => '상태 확인',
        'admin_url' => '관리자 링크',
        'pricing' => '가격 조회',
        'redeem' => '사용 처리',
        'revoke_access' => '접근권 회수',
    ];
}

function sr_coupon_target_capability_summary(array $capabilities): string
{
    $labels = sr_coupon_target_capability_labels();
    $summary = [];
    foreach ($capabilities as $capability) {
        $capability = (string) $capability;
        $summary[] = (string) ($labels[$capability] ?? $capability);
    }

    return implode(', ', array_values(array_unique(array_filter($summary))));
}

function sr_coupon_assert_refundable_target_contract(PDO $pdo, string $targetType, string $refundablePolicy): void
{
    if ($refundablePolicy !== 'refundable' || $targetType === 'all') {
        return;
    }

    $contracts = sr_coupon_target_contracts($pdo);
    $target = $contracts[$targetType] ?? null;
    if (!is_array($target) || !sr_coupon_target_contract_has_capability($target, 'revoke_access')) {
        throw new InvalidArgumentException('환급 가능 쿠폰은 접근권 회수를 지원하는 사용처에만 연결할 수 있습니다.');
    }
}

function sr_coupon_assert_refundable_benefit_model(string $couponType, string $refundablePolicy): void
{
    if ($refundablePolicy !== 'refundable' || $couponType === 'access') {
        return;
    }

    throw new InvalidArgumentException('정액/정률 할인 쿠폰은 복합 자산 결제 취소 계약이 준비될 때까지 환급 가능으로 설정할 수 없습니다.');
}

function sr_coupon_target_pricing(PDO $pdo, string $targetType, string $targetId, int $accountId = 0, array $context = []): array
{
    $contracts = sr_coupon_target_contracts($pdo);
    $target = $contracts[$targetType] ?? null;
    $pricingFunction = is_array($target) ? (string) ($target['pricing_function'] ?? '') : '';
    if (!is_array($target) || $pricingFunction === '' || !function_exists($pricingFunction)) {
        return [
            'ok' => false,
            'failure_code' => 'pricing_not_supported',
            'failure_message' => '가격 조회를 지원하지 않는 쿠폰 사용처입니다.',
        ];
    }

    try {
        $pricing = $pricingFunction($pdo, $targetType, $targetId, $accountId, $context);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'coupon_target_pricing_' . $targetType);
        return [
            'ok' => false,
            'failure_code' => 'pricing_failed',
            'failure_message' => '쿠폰 사용처 가격을 확인할 수 없습니다.',
        ];
    }

    return sr_coupon_normalize_target_pricing($pricing, $targetType, $targetId);
}

function sr_coupon_target_pricing_admin_label(array $pricing): string
{
    if (empty($pricing['ok'])) {
        $message = sr_coupon_clean_text((string) ($pricing['failure_message'] ?? '가격 조회를 지원하지 않습니다.'), 120);
        return '가격 조회: ' . ($message !== '' ? $message : '지원하지 않음');
    }

    $amount = sr_coupon_nonnegative_int_or_null($pricing['price_amount'] ?? 0);
    if ($amount === null) {
        return '가격 조회: 가격 정보 오류';
    }
    $unit = (string) ($pricing['currency_code'] ?? '');
    if ($unit === '') {
        $unit = (string) ($pricing['asset_unit'] ?? '');
    }
    if ($unit === '') {
        $unit = '단위 없음';
    }

    if (!empty($pricing['is_free']) || $amount === 0) {
        return '현재 가격: 무료';
    }

    return '현재 가격: ' . number_format($amount) . $unit;
}

function sr_coupon_normalize_target_pricing(mixed $pricing, string $targetType, string $targetId): array
{
    if (!is_array($pricing) || empty($pricing['ok'])) {
        return [
            'ok' => false,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'failure_code' => sr_coupon_clean_key((string) ($pricing['failure_code'] ?? 'target_unavailable'), 60),
            'failure_message' => sr_coupon_clean_text((string) ($pricing['failure_message'] ?? '사용할 수 없는 대상입니다.'), 255),
        ];
    }

    $priceAmount = sr_coupon_nonnegative_int_or_null($pricing['price_amount'] ?? 0);
    if ($priceAmount === null) {
        return [
            'ok' => false,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'failure_code' => 'pricing_amount_invalid',
            'failure_message' => '쿠폰 사용처 가격 금액이 올바르지 않습니다.',
        ];
    }
    $currencyCode = sr_coupon_clean_currency_code((string) ($pricing['currency_code'] ?? ''));
    $assetUnit = sr_coupon_clean_key((string) ($pricing['asset_unit'] ?? ''), 40);
    if (($currencyCode === '' && $assetUnit === '') || ($currencyCode !== '' && $assetUnit !== '')) {
        return [
            'ok' => false,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'failure_code' => 'pricing_unit_invalid',
            'failure_message' => '쿠폰 사용처 가격 단위가 올바르지 않습니다.',
        ];
    }

    return [
        'ok' => true,
        'target_type' => (string) ($pricing['target_type'] ?? $targetType),
        'target_id' => (string) ($pricing['target_id'] ?? $targetId),
        'price_amount' => $priceAmount,
        'currency_code' => $currencyCode,
        'asset_unit' => $assetUnit,
        'is_free' => $priceAmount === 0,
        'already_entitled' => !empty($pricing['already_entitled']),
        'policy_summary' => sr_coupon_clean_text((string) ($pricing['policy_summary'] ?? ''), 255),
        'priced_at' => sr_coupon_clean_text((string) ($pricing['priced_at'] ?? sr_now()), 30),
        'failure_code' => null,
        'failure_message' => null,
    ];
}

function sr_coupon_issue_member_groups(PDO $pdo): array
{
    if (!function_exists('sr_member_groups') || !function_exists('sr_member_groups_table_exists') || !sr_member_groups_table_exists($pdo)) {
        return [];
    }

    return array_values(array_filter(sr_member_groups($pdo), static function (array $group): bool {
        return (string) ($group['status'] ?? '') === 'enabled';
    }));
}

function sr_coupon_issue_target_account_ids(PDO $pdo, array $runtimeConfig, string $targetMode, string $accountIdentifier, string $groupKey): array
{
    if (!in_array($targetMode, ['member', 'all', 'group'], true)) {
        throw new InvalidArgumentException('쿠폰 지급 대상을 선택해 주세요.');
    }

    if ($targetMode === 'member') {
        $accountId = sr_admin_member_account_id_from_identifier($pdo, $runtimeConfig, $accountIdentifier);
        if ($accountId <= 0) {
            throw new InvalidArgumentException('쿠폰을 지급할 회원을 선택해 주세요.');
        }

        return [$accountId];
    }

    if ($targetMode === 'all') {
        $stmt = $pdo->query("SELECT id FROM sr_member_accounts WHERE status = 'active' ORDER BY id ASC");
        $accountIds = array_map('intval', array_column($stmt->fetchAll(), 'id'));
        if ($accountIds === []) {
            throw new InvalidArgumentException('쿠폰을 지급할 활성 회원이 없습니다.');
        }

        return $accountIds;
    }

    if (
        !function_exists('sr_member_group_by_key')
        || !function_exists('sr_member_group_key_is_valid')
        || !sr_member_group_key_is_valid($groupKey)
    ) {
        throw new InvalidArgumentException('쿠폰을 지급할 회원 그룹을 선택해 주세요.');
    }

    $group = sr_member_group_by_key($pdo, $groupKey);
    if (!is_array($group) || (string) ($group['status'] ?? '') !== 'enabled') {
        throw new InvalidArgumentException('사용 가능한 회원 그룹을 선택해 주세요.');
    }

    $stmt = $pdo->prepare(
        "SELECT DISTINCT m.account_id
         FROM sr_member_group_memberships m
         INNER JOIN sr_member_accounts a ON a.id = m.account_id
         WHERE m.group_id = :group_id
           AND m.status = 'active'
           AND a.status = 'active'
           AND (m.expires_at IS NULL OR m.expires_at >= :now)
         ORDER BY m.account_id ASC"
    );
    $stmt->execute([
        'group_id' => (int) $group['id'],
        'now' => sr_now(),
    ]);
    $accountIds = array_map('intval', array_column($stmt->fetchAll(), 'account_id'));
    if ($accountIds === []) {
        throw new InvalidArgumentException('선택한 회원 그룹에 지급 가능한 활성 회원이 없습니다.');
    }

    return $accountIds;
}

function sr_coupon_target_search(PDO $pdo, string $targetType, string $keyword, int $limit = 20): array
{
    if (!array_key_exists($targetType, sr_coupon_target_types($pdo)) || $targetType === 'all') {
        return [];
    }

    $keyword = sr_coupon_clean_text($keyword, 120);
    $limit = max(1, min(30, $limit));
    $contracts = sr_coupon_target_contracts($pdo);
    $target = $contracts[$targetType] ?? null;
    $searchFunction = is_array($target) ? (string) ($target['search_function'] ?? '') : '';
    if ($searchFunction === '' || !function_exists($searchFunction)) {
        return [];
    }

    try {
        $results = $searchFunction($pdo, $targetType, $keyword, $limit);
        if (!is_array($results)) {
            return [];
        }

        $capabilities = is_array($target) ? array_map('strval', $target['capabilities'] ?? []) : [];
        $capabilitySummary = sr_coupon_target_capability_summary($capabilities);
        foreach ($results as $index => $result) {
            if (!is_array($result)) {
                unset($results[$index]);
                continue;
            }

            $result['capabilities'] = $capabilities;
            $result['capability_label'] = $capabilitySummary !== '' ? '기능: ' . $capabilitySummary : '';
            $referenceId = (string) ($result['reference_id'] ?? '');
            if ($referenceId !== '' && sr_coupon_target_contract_has_capability($target, 'pricing')) {
                $pricing = sr_coupon_target_pricing($pdo, $targetType, $referenceId, 0, ['source' => 'admin_lookup']);
                $result['pricing_label'] = sr_coupon_target_pricing_admin_label($pricing);
                if (!empty($pricing['ok'])) {
                    $snapshot = sr_coupon_redemption_pricing_snapshot_from_result($pricing, $targetType, $referenceId);
                    $result['policy_summary'] = (string) ($snapshot['policy_summary'] ?? '');
                    $result['priced_at'] = (string) ($snapshot['priced_at'] ?? '');
                }
            } elseif ($referenceId !== '') {
                $result['pricing_label'] = '가격 조회: 지원하지 않음';
            }
            $results[$index] = $result;
        }

        return array_values($results);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'coupon_target_search_' . $targetType);
        return [];
    }
}

function sr_coupon_definition_by_id(PDO $pdo, int $definitionId): ?array
{
    if ($definitionId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_coupon_definitions WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $definitionId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_coupon_issue_by_id(PDO $pdo, int $issueId): ?array
{
    if ($issueId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT i.*, d.coupon_key, d.title, d.description, d.coupon_type, d.target_type, d.target_id, d.refundable_policy, d.max_uses_per_issue
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         WHERE i.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $issueId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_coupon_definition_reference_count(PDO $pdo, array $target, array $context): int
{
    return count(sr_coupon_definition_reference_rows($pdo, $target, $context));
}

function sr_coupon_definition_reference_rows(PDO $pdo, array $target, array $context): array
{
    $definitionId = (int) ($target['target_id'] ?? 0);
    if ($definitionId <= 0) {
        return [];
    }

    $definition = is_array($context['definition'] ?? null) ? $context['definition'] : sr_coupon_definition_by_id($pdo, $definitionId);
    $targetKey = (string) ($target['target_key'] ?? '');
    $domainTarget = [
        'target_type' => (string) ($definition['target_type'] ?? ''),
        'target_id' => (string) ($definition['target_id'] ?? ''),
    ];

    $rows = [];
    if (sr_coupon_table_available($pdo, 'sr_coupon_issues')) {
        try {
            $stmt = $pdo->prepare(
                'SELECT status, COUNT(*) AS reference_count, MAX(updated_at) AS updated_at
                 FROM sr_coupon_issues
                 WHERE coupon_definition_id = :definition_id
                 GROUP BY status
                 ORDER BY status ASC'
            );
            $stmt->execute(['definition_id' => $definitionId]);
        } catch (Throwable) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT status, COUNT(*) AS reference_count, \'\' AS updated_at
                     FROM sr_coupon_issues
                     WHERE coupon_definition_id = :definition_id
                     GROUP BY status
                     ORDER BY status ASC'
                );
                $stmt->execute(['definition_id' => $definitionId]);
            } catch (Throwable) {
                $stmt = null;
            }
        }
        if ($stmt === null) {
            return $rows;
        }
        foreach ($stmt->fetchAll() as $row) {
            $status = (string) ($row['status'] ?? '');
            $rows[] = [
                'consumer_module_key' => 'coupon',
                'reference_type' => 'coupon_history',
                'reference_id' => 'definition:' . (string) $definitionId . ':issue_status:' . $status,
                'title' => '지급 쿠폰 ' . (string) (int) ($row['reference_count'] ?? 0) . '건',
                'target_type' => 'coupon_definition',
                'target_id' => (string) $definitionId,
                'target_key' => $targetKey,
                'policy_status' => $status,
                'updated_at' => (string) ($row['updated_at'] ?? ''),
                'metadata' => ['history_kind' => 'coupon_issue', 'domain_target' => $domainTarget],
            ];
        }
    }

    if (sr_coupon_table_available($pdo, 'sr_coupon_redemptions')) {
        try {
            $stmt = $pdo->prepare(
                'SELECT status, COUNT(*) AS reference_count, MAX(COALESCE(refunded_at, redeemed_at)) AS updated_at
                 FROM sr_coupon_redemptions
                 WHERE coupon_definition_id = :definition_id
                 GROUP BY status
                 ORDER BY status ASC'
            );
            $stmt->execute(['definition_id' => $definitionId]);
        } catch (Throwable) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT status, COUNT(*) AS reference_count, \'\' AS updated_at
                     FROM sr_coupon_redemptions
                     WHERE coupon_definition_id = :definition_id
                     GROUP BY status
                     ORDER BY status ASC'
                );
                $stmt->execute(['definition_id' => $definitionId]);
            } catch (Throwable) {
                $stmt = null;
            }
        }
        if ($stmt === null) {
            return $rows;
        }
        foreach ($stmt->fetchAll() as $row) {
            $status = (string) ($row['status'] ?? '');
            $rows[] = [
                'consumer_module_key' => 'coupon',
                'reference_type' => 'coupon_history',
                'reference_id' => 'definition:' . (string) $definitionId . ':redemption_status:' . $status,
                'title' => '쿠폰 사용 이력 ' . (string) (int) ($row['reference_count'] ?? 0) . '건',
                'target_type' => 'coupon_definition',
                'target_id' => (string) $definitionId,
                'target_key' => $targetKey,
                'policy_status' => $status,
                'updated_at' => (string) ($row['updated_at'] ?? ''),
                'metadata' => ['history_kind' => 'coupon_redemption', 'domain_target' => $domainTarget],
            ];
        }
    }

    return $rows;
}

function sr_coupon_definition_reference_health(PDO $pdo, array $target, array $row, array $context): array
{
    $definitionId = (int) ($target['target_id'] ?? 0);
    $definition = $definitionId > 0 ? sr_coupon_definition_by_id($pdo, $definitionId) : null;
    if (!is_array($definition)) {
        return ['status' => 'missing_target', 'message' => '쿠폰 정의를 찾을 수 없습니다.'];
    }

    if (!sr_coupon_definition_allows_redeem((string) ($definition['status'] ?? ''))) {
        return ['status' => 'disabled_target', 'message' => '쿠폰 정의가 사용 중지 상태입니다.'];
    }

    return ['status' => 'ok'];
}

function sr_coupon_definition_reference_admin_url(array $row, array $context): string
{
    return '/admin/coupons?coupon_q=' . rawurlencode((string) ($context['coupon_key'] ?? ''));
}

function sr_coupon_definitions(PDO $pdo, int $limit = 100): array
{
    $limit = max(1, min(300, $limit));
    $stmt = $pdo->query(
        'SELECT *
         FROM sr_coupon_definitions
         ORDER BY id DESC
         LIMIT ' . $limit
    );

    return $stmt->fetchAll();
}

function sr_coupon_admin_definition_filters(PDO $pdo): array
{
    return [
        'status' => sr_admin_get_allowed_single_array('status', sr_coupon_statuses(), 30),
        'target_type' => sr_admin_get_allowed_single_array('target_type', array_keys(sr_coupon_target_types($pdo)), 60),
        'q' => sr_coupon_clean_text(sr_get_string('q', 120), 120),
    ];
}

function sr_coupon_admin_definition_sort_options(): array
{
    return [
        'coupon_key' => ['columns' => ['coupon_key', 'id']],
        'title' => ['columns' => ['title', 'id']],
        'target_type' => ['columns' => ['target_type', 'target_id', 'id']],
        'status' => ['columns' => ['status', 'id']],
        'created_at' => ['columns' => ['created_at', 'id']],
    ];
}

function sr_coupon_admin_definition_default_sort(): array
{
    return sr_admin_sort_default('created_at', 'desc');
}

function sr_coupon_admin_definition_count(PDO $pdo, array $filters): int
{
    $where = [];
    $params = [];

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['target_type'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('target_type', 'target_type', $filters['target_type']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $keyword = sr_coupon_clean_text((string) ($filters['q'] ?? ''), 120);
    if ($keyword !== '') {
        $where[] = "(coupon_key LIKE :keyword_like ESCAPE '\\\\' OR title LIKE :keyword_like ESCAPE '\\\\' OR description LIKE :keyword_like ESCAPE '\\\\' OR target_id LIKE :keyword_like ESCAPE '\\\\')";
        $params['keyword_like'] = sr_coupon_like_keyword($keyword);
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS count_value
         FROM sr_coupon_definitions'
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
    );
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_coupon_admin_definitions(PDO $pdo, array $filters, int $limit = 100, array $sort = [], int $offset = 0): array
{
    $limit = max(1, min(300, $limit));
    $offset = max(0, $offset);
    $where = [];
    $params = [];
    $sortOptions = sr_coupon_admin_definition_sort_options();
    $defaultSort = sr_coupon_admin_definition_default_sort();
    $orderSql = sr_admin_sort_order_sql($sortOptions, $sort, $defaultSort);
    if ($orderSql === '') {
        $orderSql = ' ORDER BY id DESC';
    }

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['target_type'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('target_type', 'target_type', $filters['target_type']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $keyword = sr_coupon_clean_text((string) ($filters['q'] ?? ''), 120);
    if ($keyword !== '') {
        $where[] = "(coupon_key LIKE :keyword_like ESCAPE '\\\\' OR title LIKE :keyword_like ESCAPE '\\\\' OR description LIKE :keyword_like ESCAPE '\\\\' OR target_id LIKE :keyword_like ESCAPE '\\\\')";
        $params['keyword_like'] = sr_coupon_like_keyword($keyword);
    }

    $sql = 'SELECT *
            FROM sr_coupon_definitions'
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
        . $orderSql
        . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_coupon_admin_issue_filters(PDO $pdo, array $runtimeConfig): array
{
    return [
        'status' => sr_admin_get_allowed_array('status', sr_coupon_issue_statuses(), 30),
        'target_type' => sr_admin_get_allowed_single_array('target_type', array_keys(sr_coupon_target_types($pdo)), 60),
        'coupon_q' => sr_coupon_clean_text(sr_get_string('coupon_q', 120), 120),
        'account' => sr_admin_member_account_lookup_filter($pdo, $runtimeConfig),
    ];
}

function sr_coupon_admin_issue_sort_options(): array
{
    return [
        'member' => ['columns' => ["COALESCE(a.display_name, '')", 'a.email', 'i.account_id', 'i.id']],
        'coupon' => ['columns' => ['d.title', 'd.coupon_key', 'i.id']],
        'target_type' => ['columns' => ['d.target_type', 'd.target_id', 'i.id']],
        'status' => ['columns' => ['i.status', 'i.id']],
        'used_count' => ['columns' => ['i.used_count', 'i.id']],
        'issued_at' => ['columns' => ['i.issued_at', 'i.id']],
    ];
}

function sr_coupon_admin_issue_default_sort(): array
{
    return sr_admin_sort_default('issued_at', 'desc');
}

function sr_coupon_admin_issue_count(PDO $pdo, array $runtimeConfig, array $filters): int
{
    sr_coupon_expire_active_issues($pdo);

    $where = [];
    $params = [];

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('i.status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['target_type'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('d.target_type', 'target_type', $filters['target_type']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $couponKeyword = sr_coupon_clean_text((string) ($filters['coupon_q'] ?? ''), 120);
    if ($couponKeyword !== '') {
        $where[] = "(d.coupon_key LIKE :coupon_keyword_like ESCAPE '\\\\' OR d.title LIKE :coupon_keyword_like ESCAPE '\\\\')";
        $params['coupon_keyword_like'] = sr_coupon_like_keyword($couponKeyword);
    }

    $accountFilter = is_array($filters['account'] ?? null) ? $filters['account'] : [];
    $accountId = (int) ($accountFilter['account_id'] ?? 0);
    if ($accountId > 0) {
        $where[] = 'i.account_id = :account_id';
        $params['account_id'] = $accountId;
    } elseif (trim((string) ($accountFilter['keyword'] ?? '')) !== '') {
        $where[] = '1 = 0';
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS count_value
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         LEFT JOIN sr_member_accounts a ON a.id = i.account_id'
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
    );
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_coupon_admin_issues(PDO $pdo, array $runtimeConfig, array $filters, int $limit = 100, array $sort = [], int $offset = 0): array
{
    sr_coupon_expire_active_issues($pdo);

    $limit = max(1, min(300, $limit));
    $offset = max(0, $offset);
    $where = [];
    $params = [];
    $sortOptions = sr_coupon_admin_issue_sort_options();
    $defaultSort = sr_coupon_admin_issue_default_sort();
    $orderSql = sr_admin_sort_order_sql($sortOptions, $sort, $defaultSort);
    if ($orderSql === '') {
        $orderSql = ' ORDER BY i.id DESC';
    }

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('i.status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['target_type'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('d.target_type', 'target_type', $filters['target_type']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $couponKeyword = sr_coupon_clean_text((string) ($filters['coupon_q'] ?? ''), 120);
    if ($couponKeyword !== '') {
        $where[] = "(d.coupon_key LIKE :coupon_keyword_like ESCAPE '\\\\' OR d.title LIKE :coupon_keyword_like ESCAPE '\\\\')";
        $params['coupon_keyword_like'] = sr_coupon_like_keyword($couponKeyword);
    }

    $accountFilter = is_array($filters['account'] ?? null) ? $filters['account'] : [];
    $accountId = (int) ($accountFilter['account_id'] ?? 0);
    if ($accountId > 0) {
        $where[] = 'i.account_id = :account_id';
        $params['account_id'] = $accountId;
    } elseif (trim((string) ($accountFilter['keyword'] ?? '')) !== '') {
        $where[] = '1 = 0';
    }

    $sql = 'SELECT i.id, i.account_id, i.status, i.used_count, i.issued_at, i.expires_at,
                   i.claim_type, i.nominal_price_amount, i.nominal_price_currency_code,
                   d.title, d.coupon_key, d.target_type, d.target_id,
                   a.display_name, a.email, a.status AS account_status
            FROM sr_coupon_issues i
            INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
            LEFT JOIN sr_member_accounts a ON a.id = i.account_id'
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
        . $orderSql
        . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['account_public_hash'] = sr_admin_member_public_hash($runtimeConfig, (int) ($row['account_id'] ?? 0));
        $rows[] = $row;
    }

    return $rows;
}

function sr_coupon_admin_redemption_filters(PDO $pdo, array $runtimeConfig): array
{
    return [
        'status' => sr_admin_get_allowed_single_array('status', ['redeemed', 'refunded'], 30),
        'target_type' => sr_admin_get_allowed_single_array('target_type', array_keys(sr_coupon_target_types($pdo)), 60),
        'refundable_policy' => sr_admin_get_allowed_single_array('refundable_policy', array_keys(sr_coupon_refundable_policies()), 30),
        'coupon_q' => sr_coupon_clean_text(sr_get_string('coupon_q', 120), 120),
        'account' => sr_admin_member_account_lookup_filter($pdo, $runtimeConfig),
    ];
}

function sr_coupon_admin_redemption_sort_options(): array
{
    return [
        'member' => ['columns' => ["COALESCE(a.display_name, '')", 'a.email', 'r.account_id', 'r.id']],
        'coupon' => ['columns' => ['d.title', 'd.coupon_key', 'r.id']],
        'target_type' => ['columns' => ['r.target_type', 'r.target_id', 'r.id']],
        'status' => ['columns' => ['r.status', 'r.id']],
        'redeemed_at' => ['columns' => ['r.redeemed_at', 'r.id']],
        'refunded_at' => ['columns' => ['refunded_at', 'r.id']],
    ];
}

function sr_coupon_admin_redemption_default_sort(): array
{
    return sr_admin_sort_default('redeemed_at', 'desc');
}

function sr_coupon_admin_claim_campaign_filters(): array
{
    return [
        'status' => sr_admin_get_allowed_single_array('status', sr_coupon_claim_campaign_statuses(), 30),
        'claim_type' => sr_admin_get_allowed_single_array('claim_type', sr_coupon_claim_types(), 20),
        'visibility' => sr_admin_get_allowed_single_array('visibility', ['hidden', 'public'], 20),
        'q' => sr_coupon_clean_text(sr_get_string('q', 120), 120),
    ];
}

function sr_coupon_admin_claim_campaigns(PDO $pdo, int $limit = 100, array $filters = []): array
{
    if (!sr_coupon_claim_tables_available($pdo)) {
        return [];
    }

    $limit = max(1, min(300, $limit));
    $where = [];
    $params = [];

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('c.status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['claim_type'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('c.claim_type', 'claim_type', $filters['claim_type']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['visibility'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('c.visibility', 'visibility', $filters['visibility']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $keyword = sr_coupon_clean_text((string) ($filters['q'] ?? ''), 120);
    if ($keyword !== '') {
        $where[] = "(c.campaign_key LIKE :campaign_keyword_like ESCAPE '\\\\' OR c.title LIKE :campaign_keyword_like ESCAPE '\\\\' OR d.coupon_key LIKE :campaign_keyword_like ESCAPE '\\\\' OR d.title LIKE :campaign_keyword_like ESCAPE '\\\\')";
        $params['campaign_keyword_like'] = sr_coupon_like_keyword($keyword);
    }

    $sql = 'SELECT c.id, c.campaign_key, c.title, c.status, c.claim_type, c.price_amount, c.price_currency_code,
                   c.allowed_asset_modules_json, c.total_claim_limit, c.per_account_limit, c.visibility,
                   d.coupon_key, d.title AS coupon_title
            FROM sr_coupon_claim_campaigns c
            INNER JOIN sr_coupon_definitions d ON d.id = c.coupon_definition_id'
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
        . ' ORDER BY c.id DESC
            LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_coupon_admin_claim_campaign_definition_options(PDO $pdo, int $limit = 300): array
{
    $limit = max(1, min(1000, $limit));
    $stmt = $pdo->query(
        'SELECT id, coupon_key, title, status
         FROM sr_coupon_definitions
         ORDER BY id DESC
         LIMIT ' . $limit
    );

    return $stmt->fetchAll();
}

function sr_coupon_admin_claim_log_filters(PDO $pdo, array $runtimeConfig): array
{
    return [
        'status' => sr_admin_get_allowed_array('status', array_merge(sr_coupon_claim_log_statuses(), ['expired_unmaterialized']), 30),
        'claim_source' => sr_admin_get_allowed_single_array('claim_source', array_merge(sr_coupon_claim_surfaces(), ['admin']), 40),
        'campaign_q' => sr_coupon_clean_text(sr_get_string('campaign_q', 120), 120),
        'account' => sr_admin_member_account_lookup_filter($pdo, $runtimeConfig),
    ];
}

function sr_coupon_admin_claim_logs(PDO $pdo, int $limit = 100, array $filters = []): array
{
    if (!sr_coupon_claim_tables_available($pdo)) {
        return [];
    }

    $limit = max(1, min(300, $limit));
    $now = sr_now();
    $where = [];
    $params = [];

    $statusFilters = is_array($filters['status'] ?? null) ? array_values(array_map('strval', $filters['status'])) : [];
    if ($statusFilters !== []) {
        $statusConditions = [];
        $materializedStatuses = array_values(array_filter($statusFilters, static fn (string $status): bool => $status !== 'expired_unmaterialized'));
        if ($materializedStatuses !== []) {
            [$condition, $conditionParams] = sr_admin_sql_in_condition('l.status', 'log_status', $materializedStatuses);
            $statusConditions[] = $condition;
            $params = array_merge($params, $conditionParams);
        }
        if (in_array('expired_unmaterialized', $statusFilters, true)) {
            $statusConditions[] = "(l.status IN ('reserved', 'pending_payment') AND l.reserved_until IS NOT NULL AND l.reserved_until <> '' AND l.reserved_until < :claim_log_now)";
            $params['claim_log_now'] = $now;
        }
        if ($statusConditions !== []) {
            $where[] = '(' . implode(' OR ', $statusConditions) . ')';
        }
    }

    if (($filters['claim_source'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('l.claim_source', 'claim_source', $filters['claim_source']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $keyword = sr_coupon_clean_text((string) ($filters['campaign_q'] ?? ''), 120);
    if ($keyword !== '') {
        $where[] = "(c.campaign_key LIKE :claim_log_keyword_like ESCAPE '\\\\' OR c.title LIKE :claim_log_keyword_like ESCAPE '\\\\' OR d.coupon_key LIKE :claim_log_keyword_like ESCAPE '\\\\' OR d.title LIKE :claim_log_keyword_like ESCAPE '\\\\')";
        $params['claim_log_keyword_like'] = sr_coupon_like_keyword($keyword);
    }

    $accountFilter = is_array($filters['account'] ?? null) ? $filters['account'] : [];
    $accountId = (int) ($accountFilter['account_id'] ?? 0);
    if ($accountId > 0) {
        $where[] = 'l.account_id = :claim_log_account_id';
        $params['claim_log_account_id'] = $accountId;
    } elseif (trim((string) ($accountFilter['keyword'] ?? '')) !== '') {
        $where[] = '1 = 0';
    }

    $sql = 'SELECT l.id, l.campaign_id, l.coupon_definition_id, l.account_id, l.coupon_issue_id, l.dedupe_key,
                   l.claim_source, l.status, l.reserved_until, l.failure_code, l.failure_message, l.created_at,
                   c.campaign_key, c.title AS campaign_title, d.coupon_key, d.title AS coupon_title,
                   a.email AS account_email, a.display_name AS account_display_name
            FROM sr_coupon_claim_logs l
            INNER JOIN sr_coupon_claim_campaigns c ON c.id = l.campaign_id
            INNER JOIN sr_coupon_definitions d ON d.id = l.coupon_definition_id
            LEFT JOIN sr_member_accounts a ON a.id = l.account_id'
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
        . ' ORDER BY l.id DESC
            LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $status = (string) ($row['status'] ?? '');
        $reservedUntil = (string) ($row['reserved_until'] ?? '');
        $row['display_status'] = $status;
        $row['is_lazy_expired'] = false;
        if (
            in_array($status, ['reserved', 'pending_payment'], true)
            && $reservedUntil !== ''
            && strcmp($reservedUntil, $now) < 0
        ) {
            $row['display_status'] = 'expired_unmaterialized';
            $row['is_lazy_expired'] = true;
        }
        $rows[] = $row;
    }

    return $rows;
}

function sr_coupon_create_definition(PDO $pdo, array $data): int
{
    $couponKey = sr_coupon_clean_key((string) ($data['coupon_key'] ?? ''));
    $title = sr_coupon_clean_text((string) ($data['title'] ?? ''), 120);
    $description = sr_coupon_clean_text((string) ($data['description'] ?? ''), 1000);
    $status = sr_coupon_optional_enum_value($data, 'status', sr_coupon_statuses(), 'active', '쿠폰 상태가 올바르지 않습니다.');
    $couponType = sr_coupon_clean_key((string) ($data['coupon_type'] ?? 'access'), 40);
    if ($couponType === '') {
        $couponType = 'access';
    }
    if (!array_key_exists($couponType, sr_coupon_types())) {
        throw new InvalidArgumentException('쿠폰 혜택 유형이 올바르지 않습니다.');
    }
    $discountAmount = 0;
    $discountPercent = 0;
    $discountCurrencyCode = '';
    if ($couponType === 'fixed_discount') {
        $discountAmountValue = $data['discount_amount'] ?? '';
        if (is_array($discountAmountValue)) {
            throw new InvalidArgumentException('정액 할인 금액은 1 이상 정수로 입력하세요.');
        }
        $discountAmountString = trim((string) $discountAmountValue);
        if ($discountAmountString === '' || preg_match('/\A[1-9][0-9]*\z/', $discountAmountString) !== 1) {
            throw new InvalidArgumentException('정액 할인 금액은 1 이상 정수로 입력하세요.');
        }
        $discountAmount = (int) $discountAmountString;
        if ($discountAmount < 1 || $discountAmount > 999999999) {
            throw new InvalidArgumentException('정액 할인 금액은 1부터 999999999 사이로 입력하세요.');
        }
        $discountCurrencyCode = strtoupper(trim((string) ($data['discount_currency_code'] ?? 'KRW')));
        if ($discountCurrencyCode === '') {
            $discountCurrencyCode = 'KRW';
        }
        if (preg_match('/\A[A-Z]{3}\z/', $discountCurrencyCode) !== 1) {
            throw new InvalidArgumentException('정액 할인 통화는 영문 3자리로 입력하세요.');
        }
    } elseif ($couponType === 'percent_discount') {
        $discountPercentValue = $data['discount_percent'] ?? '';
        if (is_array($discountPercentValue)) {
            throw new InvalidArgumentException('정률 할인율은 1부터 100 사이의 정수로 입력하세요.');
        }
        $discountPercentString = trim((string) $discountPercentValue);
        if ($discountPercentString === '' || preg_match('/\A[1-9][0-9]*\z/', $discountPercentString) !== 1) {
            throw new InvalidArgumentException('정률 할인율은 1부터 100 사이의 정수로 입력하세요.');
        }
        $discountPercent = (int) $discountPercentString;
        if ($discountPercent < 1 || $discountPercent > 100) {
            throw new InvalidArgumentException('정률 할인율은 1부터 100 사이의 정수로 입력하세요.');
        }
    }
    if ($couponType !== 'access' && !sr_coupon_definition_discount_columns_available($pdo)) {
        throw new InvalidArgumentException('쿠폰 할인 설정 업데이트를 먼저 적용하세요.');
    }
    $targetType = array_key_exists((string) ($data['target_type'] ?? 'all'), sr_coupon_target_types($pdo)) ? (string) $data['target_type'] : 'all';
    $targetId = sr_coupon_clean_text((string) ($data['target_id'] ?? ''), 80);
    $refundablePolicy = array_key_exists((string) ($data['refundable_policy'] ?? 'none'), sr_coupon_refundable_policies()) ? (string) $data['refundable_policy'] : 'none';
    $maxUsesValue = $data['max_uses_per_issue'] ?? '1';
    if (is_array($maxUsesValue)) {
        throw new InvalidArgumentException('사용 가능 횟수는 1부터 1000 사이의 정수로 입력하세요.');
    }
    $maxUsesString = trim((string) $maxUsesValue);
    if ($maxUsesString === '' || preg_match('/\A[1-9][0-9]*\z/', $maxUsesString) !== 1) {
        throw new InvalidArgumentException('사용 가능 횟수는 1부터 1000 사이의 정수로 입력하세요.');
    }
    $maxUses = (int) $maxUsesString;
    if ($maxUses < 1 || $maxUses > 1000) {
        throw new InvalidArgumentException('사용 가능 횟수는 1부터 1000 사이의 정수로 입력하세요.');
    }

    if (!sr_coupon_key_is_valid($couponKey)) {
        throw new InvalidArgumentException('쿠폰 키는 영문 소문자로 시작하고 소문자, 숫자, 밑줄만 사용할 수 있습니다.');
    }

    if ($title === '') {
        throw new InvalidArgumentException('쿠폰 키와 이름을 입력하세요.');
    }
    sr_coupon_assert_refundable_benefit_model($couponType, $refundablePolicy);
    sr_coupon_assert_refundable_target_contract($pdo, $targetType, $refundablePolicy);

    $stmt = $pdo->prepare('SELECT id FROM sr_coupon_definitions WHERE coupon_key = :coupon_key LIMIT 1');
    $stmt->execute(['coupon_key' => $couponKey]);
    if (is_array($stmt->fetch())) {
        throw new InvalidArgumentException('이미 사용 중인 쿠폰 키입니다.');
    }

    $now = sr_now();
    $insertColumns = [
        'coupon_key',
        'title',
        'description',
        'status',
        'coupon_type',
        'target_type',
        'target_id',
        'refundable_policy',
        'max_uses_per_issue',
        'valid_from',
        'valid_until',
        'created_at',
        'updated_at',
    ];
    $insertValues = [
        'coupon_key' => $couponKey,
        'title' => $title,
        'description' => $description,
        'status' => $status,
        'coupon_type' => $couponType,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'refundable_policy' => $refundablePolicy,
        'max_uses_per_issue' => $maxUses,
        'valid_from' => null,
        'valid_until' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if (sr_coupon_definition_discount_columns_available($pdo)) {
        array_splice($insertColumns, 5, 0, ['discount_amount', 'discount_percent', 'discount_currency_code']);
        $insertValues['discount_amount'] = $discountAmount;
        $insertValues['discount_percent'] = $discountPercent;
        $insertValues['discount_currency_code'] = $discountCurrencyCode;
    }
    $placeholders = array_map(static fn (string $column): string => ':' . $column, $insertColumns);
    $stmt = $pdo->prepare(
        'INSERT INTO sr_coupon_definitions
            (' . implode(', ', $insertColumns) . ')
         VALUES
            (' . implode(', ', $placeholders) . ')'
    );
    $stmt->execute($insertValues);

    return (int) $pdo->lastInsertId();
}

function sr_coupon_update_definition_status(PDO $pdo, int $definitionId, string $status): void
{
    if ($definitionId <= 0 || !in_array($status, sr_coupon_statuses(), true)) {
        throw new InvalidArgumentException('쿠폰 종류의 상태가 올바르지 않습니다.');
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_coupon_definitions
         SET status = :status,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'status' => $status,
        'updated_at' => sr_now(),
        'id' => $definitionId,
    ]);
}

function sr_coupon_unused_active_issue_ids_for_definition(PDO $pdo, int $definitionId): array
{
    if ($definitionId <= 0 || !sr_coupon_tables_available($pdo)) {
        return [];
    }

    sr_coupon_expire_active_issues($pdo);
    $stmt = $pdo->prepare(
        "SELECT i.id
         FROM sr_coupon_issues i
         WHERE i.coupon_definition_id = :definition_id
           AND i.status = 'active'
           AND i.used_count = 0
           AND (i.expires_at IS NULL OR i.expires_at >= :now_value)
         ORDER BY i.id ASC"
    );
    $stmt->execute([
        'definition_id' => $definitionId,
        'now_value' => sr_now(),
    ]);

    return array_map('intval', array_column($stmt->fetchAll(), 'id'));
}

function sr_coupon_notify_definition_disabled_unused_issue_reclaims(PDO $pdo, array $definitionIds, ?int $createdByAccountId = null): array
{
    $definitionIds = array_values(array_unique(array_filter(array_map('intval', $definitionIds), static fn (int $definitionId): bool => $definitionId > 0)));
    if ($definitionIds === []) {
        return ['target_issue_count' => 0, 'notification_count' => 0, 'skipped' => true];
    }

    $settings = sr_coupon_settings($pdo);
    $caseSetting = sr_coupon_notification_setting_for_event($settings, 'issue.definition_disabled');
    if (!is_array($caseSetting) || empty($caseSetting['enabled'])) {
        return ['target_issue_count' => 0, 'notification_count' => 0, 'skipped' => true];
    }

    $eventKey = (string) ($settings['disabled_reclaim_notification_event_key'] ?? 'issue.definition_disabled');
    if (preg_match('/\A[a-z0-9_.-]{1,120}\z/', $eventKey) !== 1 || sr_coupon_notification_event_function($pdo) === '') {
        return ['target_issue_count' => 0, 'notification_count' => 0, 'skipped' => true];
    }
    $channels = sr_coupon_notification_channels_from_value($caseSetting['channels'] ?? ['site']);

    $targetIssueCount = 0;
    $notificationCount = 0;
    foreach ($definitionIds as $definitionId) {
        foreach (sr_coupon_unused_active_issue_ids_for_definition($pdo, $definitionId) as $issueId) {
            $targetIssueCount++;
            $notificationId = sr_coupon_notify_issue_event($pdo, $issueId, $eventKey, $createdByAccountId, [
                'definition_status' => 'disabled',
                'reclaim_reason' => 'coupon_definition_disabled',
            ], $channels);
            if ($notificationId !== null && $notificationId > 0) {
                $notificationCount++;
            }
        }
    }

    return [
        'target_issue_count' => $targetIssueCount,
        'notification_count' => $notificationCount,
        'skipped' => false,
    ];
}

function sr_coupon_issue_to_account(PDO $pdo, int $definitionId, int $accountId, string $reason = '', ?int $issuedByAccountId = null, ?string $expiresAt = null, array $claimContext = []): int
{
    if (!sr_coupon_usage_enabled($pdo)) {
        throw new RuntimeException('쿠폰·이용권을 사용하지 않도록 설정되어 있습니다.');
    }

    if ($definitionId <= 0 || $accountId <= 0) {
        throw new InvalidArgumentException('쿠폰 종류와 지급할 회원을 선택해 주세요.');
    }

    $definition = sr_coupon_definition_by_id($pdo, $definitionId);
    if (!is_array($definition) || !sr_coupon_definition_allows_issue((string) ($definition['status'] ?? ''))) {
        throw new InvalidArgumentException('사용 중인 쿠폰 종류만 지급할 수 있습니다.');
    }

    $now = sr_now();
    $claimType = sr_coupon_clean_key((string) ($claimContext['claim_type'] ?? 'manual'), 20);
    if (!in_array($claimType, ['manual', 'free', 'paid', 'admin'], true)) {
        $claimType = 'manual';
    }
    $snapshotJson = null;
    if (isset($claimContext['claim_snapshot_json']) && is_string($claimContext['claim_snapshot_json'])) {
        $snapshotJson = $claimContext['claim_snapshot_json'];
    } elseif (isset($claimContext['claim_snapshot']) && is_array($claimContext['claim_snapshot'])) {
        $encodedSnapshot = json_encode($claimContext['claim_snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $snapshotJson = is_string($encodedSnapshot) ? $encodedSnapshot : null;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_coupon_issues
            (coupon_definition_id, account_id, status, issued_reason, issued_by_account_id, claim_type, claim_campaign_id, claim_log_id, nominal_price_amount, nominal_price_currency_code, asset_reference_module, asset_reference_type, asset_reference_id, claim_snapshot_json, issued_at, expires_at, used_count, created_at, updated_at)
         VALUES
            (:coupon_definition_id, :account_id, :status, :issued_reason, :issued_by_account_id, :claim_type, :claim_campaign_id, :claim_log_id, :nominal_price_amount, :nominal_price_currency_code, :asset_reference_module, :asset_reference_type, :asset_reference_id, :claim_snapshot_json, :issued_at, :expires_at, 0, :created_at, :updated_at)'
    );
    $stmt->execute([
        'coupon_definition_id' => $definitionId,
        'account_id' => $accountId,
        'status' => 'active',
        'issued_reason' => sr_coupon_clean_text($reason, 255),
        'issued_by_account_id' => $issuedByAccountId !== null && $issuedByAccountId > 0 ? $issuedByAccountId : null,
        'claim_type' => $claimType,
        'claim_campaign_id' => isset($claimContext['claim_campaign_id']) && (int) $claimContext['claim_campaign_id'] > 0 ? (int) $claimContext['claim_campaign_id'] : null,
        'claim_log_id' => isset($claimContext['claim_log_id']) && (int) $claimContext['claim_log_id'] > 0 ? (int) $claimContext['claim_log_id'] : null,
        'nominal_price_amount' => max(0, (int) ($claimContext['nominal_price_amount'] ?? 0)),
        'nominal_price_currency_code' => sr_coupon_clean_currency_code((string) ($claimContext['nominal_price_currency_code'] ?? '')),
        'asset_reference_module' => sr_coupon_clean_key((string) ($claimContext['asset_reference_module'] ?? ''), 60),
        'asset_reference_type' => sr_coupon_clean_text((string) ($claimContext['asset_reference_type'] ?? ''), 80),
        'asset_reference_id' => sr_coupon_clean_text((string) ($claimContext['asset_reference_id'] ?? ''), 120),
        'claim_snapshot_json' => $snapshotJson,
        'issued_at' => $now,
        'expires_at' => $expiresAt,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $issueId = (int) $pdo->lastInsertId();
    sr_coupon_notify_issue_event($pdo, $issueId, 'issue.created', $issuedByAccountId);

    return $issueId;
}

function sr_coupon_public_claim_campaigns(PDO $pdo, int $accountId = 0, int $limit = 50): array
{
    if (!sr_coupon_usage_enabled($pdo) || !sr_coupon_claim_tables_available($pdo)) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $now = sr_now();
    $stmt = $pdo->prepare(
        "SELECT c.*, d.coupon_key, d.title AS coupon_title, d.description AS coupon_description, d.status AS coupon_status, d.target_type, d.target_id, d.max_uses_per_issue
         FROM sr_coupon_claim_campaigns c
         INNER JOIN sr_coupon_definitions d ON d.id = c.coupon_definition_id
         WHERE c.status = 'active'
           AND c.visibility = 'public'
           AND c.exposure_surfaces_json LIKE :surface_like
           AND (c.starts_at IS NULL OR c.starts_at <= :starts_now)
           AND (c.ends_at IS NULL OR c.ends_at >= :ends_now)
           AND d.status = 'active'
         ORDER BY c.id DESC
         LIMIT " . $limit
    );
    $stmt->execute([
        'surface_like' => '%"coupon_zone"%',
        'starts_now' => $now,
        'ends_now' => $now,
    ]);

    $campaigns = [];
    foreach ($stmt->fetchAll() as $campaign) {
        $campaign['claim_state'] = sr_coupon_claim_campaign_state($pdo, $campaign, $accountId);
        $campaigns[] = $campaign;
    }

    return $campaigns;
}

function sr_coupon_public_claim_campaign(PDO $pdo, string $campaignKey, int $accountId = 0, array $allowedSurfaces = ['coupon_zone', 'direct_link', 'content_embed']): ?array
{
    $campaign = sr_coupon_claim_campaign_by_key($pdo, $campaignKey);
    if (!is_array($campaign)) {
        return null;
    }
    if ((string) ($campaign['status'] ?? '') !== 'active' || (string) ($campaign['visibility'] ?? '') !== 'public') {
        return null;
    }
    if ((string) ($campaign['coupon_status'] ?? '') !== 'active') {
        return null;
    }

    $now = sr_now();
    if ((string) ($campaign['starts_at'] ?? '') !== '' && strcmp((string) $campaign['starts_at'], $now) > 0) {
        return null;
    }
    if ((string) ($campaign['ends_at'] ?? '') !== '' && strcmp((string) $campaign['ends_at'], $now) < 0) {
        return null;
    }

    $surfaces = array_fill_keys(sr_coupon_claim_surfaces_from_value($campaign['exposure_surfaces_json'] ?? ''), true);
    $claimSource = '';
    foreach ($allowedSurfaces as $surface) {
        $surface = (string) $surface;
        if (isset($surfaces[$surface])) {
            $claimSource = $surface;
            break;
        }
    }
    if ($claimSource === '') {
        return null;
    }

    $campaign['claim_source'] = $claimSource;
    $campaign['claim_state'] = sr_coupon_claim_campaign_state($pdo, $campaign, $accountId);

    return $campaign;
}

function sr_coupon_claim_campaign_state(PDO $pdo, array $campaign, int $accountId = 0): array
{
    $campaignId = (int) ($campaign['id'] ?? 0);
    if ($campaignId <= 0 || !sr_coupon_claim_tables_available($pdo)) {
        return ['claimable' => false, 'remaining' => null, 'claimed_count' => 0, 'message' => ''];
    }

    $now = sr_now();
    $occupiedCondition = sr_coupon_claim_campaign_occupied_condition(':now_value');
    $params = ['campaign_id' => $campaignId, 'now_value' => $now];
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS count_value
         FROM sr_coupon_claim_logs
         WHERE campaign_id = :campaign_id
           AND (' . $occupiedCondition . ')'
    );
    $stmt->execute($params);
    $occupiedCount = (int) $stmt->fetchColumn();
    $totalLimit = (int) ($campaign['total_claim_limit'] ?? 0);
    $remaining = $totalLimit > 0 ? max(0, $totalLimit - $occupiedCount) : null;

    $claimedCount = 0;
    if ($accountId > 0) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS count_value
             FROM sr_coupon_claim_logs
             WHERE campaign_id = :campaign_id
               AND account_id = :account_id
               AND (' . $occupiedCondition . ')'
        );
        $stmt->execute([
            'campaign_id' => $campaignId,
            'account_id' => $accountId,
            'now_value' => $now,
        ]);
        $claimedCount = (int) $stmt->fetchColumn();
    }

    $perLimit = max(1, (int) ($campaign['per_account_limit'] ?? 1));
    $claimable = ($remaining === null || $remaining > 0) && ($accountId <= 0 || $claimedCount < $perLimit);
    $message = '';
    if ($remaining !== null && $remaining <= 0) {
        $message = '준비된 쿠폰이 모두 발급되었습니다.';
    } elseif ($accountId > 0 && $claimedCount >= $perLimit) {
        $message = '이미 받을 수 있는 수량을 모두 받았습니다.';
    }

    return [
        'claimable' => $claimable,
        'remaining' => $remaining,
        'occupied_count' => $occupiedCount,
        'claimed_count' => $claimedCount,
        'message' => $message,
    ];
}

function sr_coupon_claim_issue_expires_at(array $campaign): ?string
{
    $issueExpiresAt = (string) ($campaign['issue_expires_at'] ?? '');
    if ($issueExpiresAt !== '') {
        return $issueExpiresAt;
    }

    $days = (int) ($campaign['issue_expires_in_days'] ?? 0);
    if ($days < 1) {
        return null;
    }

    return (new DateTimeImmutable(sr_now()))->modify('+' . (string) $days . ' days')->format('Y-m-d H:i:s');
}

function sr_coupon_self_expire_claims(PDO $pdo, int $campaignId, int $accountId): int
{
    if ($campaignId <= 0 || $accountId <= 0) {
        return 0;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        "UPDATE sr_coupon_claim_logs
         SET status = 'expired',
             occupying_account_id = NULL,
             updated_at = :updated_at
         WHERE campaign_id = :campaign_id
           AND account_id = :account_id
           AND status IN ('reserved', 'pending_payment')
           AND reserved_until IS NOT NULL
           AND reserved_until < :now_value"
    );
    $stmt->execute([
        'updated_at' => $now,
        'campaign_id' => $campaignId,
        'account_id' => $accountId,
        'now_value' => $now,
    ]);

    return $stmt->rowCount();
}

function sr_coupon_claim_free_campaign(PDO $pdo, string $campaignKey, int $accountId, string $intentToken, string $claimSource = 'coupon_zone', array $sourceContext = []): array
{
    if (!sr_coupon_usage_enabled($pdo)) {
        throw new InvalidArgumentException('쿠폰·이용권을 사용하지 않도록 설정되어 있습니다.');
    }

    if ($accountId <= 0) {
        throw new InvalidArgumentException('로그인이 필요한 쿠폰입니다.');
    }
    if (!sr_coupon_claim_tables_available($pdo)) {
        throw new InvalidArgumentException('쿠폰 발급 캠페인 업데이트를 먼저 적용하세요.');
    }

    $campaign = sr_coupon_claim_campaign_by_key($pdo, $campaignKey);
    if (!is_array($campaign)) {
        throw new InvalidArgumentException('쿠폰 캠페인을 찾을 수 없습니다.');
    }
    if ((string) ($campaign['claim_type'] ?? '') !== 'free') {
        throw new InvalidArgumentException('무료 발급 캠페인만 바로 받을 수 있습니다.');
    }

    $intentToken = sr_coupon_clean_text($intentToken, 120);
    if ($intentToken === '') {
        throw new InvalidArgumentException('쿠폰 발급 요청 토큰이 올바르지 않습니다.');
    }
    $dedupeKey = 'coupon_claim:' . (string) (int) $campaign['id'] . ':' . (string) $accountId . ':' . $intentToken;
    $dedupeHash = sr_coupon_claim_dedupe_hash($dedupeKey);
    $claimSource = in_array($claimSource, sr_coupon_claim_surfaces(), true) ? $claimSource : 'coupon_zone';

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $campaign = sr_coupon_claim_campaign_by_key($pdo, $campaignKey, true);
        if (!is_array($campaign)) {
            throw new InvalidArgumentException('쿠폰 캠페인을 찾을 수 없습니다.');
        }

        sr_coupon_validate_claim_campaign($campaign, $claimSource);
        sr_coupon_self_expire_claims($pdo, (int) $campaign['id'], $accountId);

        $existing = sr_coupon_claim_log_by_dedupe_hash($pdo, (int) $campaign['id'], $dedupeHash);
        if (is_array($existing) && (string) ($existing['status'] ?? '') === 'issued') {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return [
                'claimed' => true,
                'already_claimed' => true,
                'coupon_issue_id' => (int) ($existing['coupon_issue_id'] ?? 0),
                'claim_log_id' => (int) ($existing['id'] ?? 0),
            ];
        }
        if (is_array($existing)) {
            throw new InvalidArgumentException('이전 발급 요청이 아직 처리 중입니다.');
        }

        sr_coupon_assert_claim_limits($pdo, $campaign, $accountId);

        $now = sr_now();
        $stmt = $pdo->prepare(
            'INSERT INTO sr_coupon_claim_logs
                (campaign_id, coupon_definition_id, account_id, coupon_issue_id, claim_source, source_context_json, dedupe_key, dedupe_hash, occupying_account_id, status, reserved_until, failure_code, failure_message, created_at, issued_at, updated_at)
             VALUES
                (:campaign_id, :coupon_definition_id, :account_id, NULL, :claim_source, :source_context_json, :dedupe_key, :dedupe_hash, :occupying_account_id, :status, NULL, \'\', \'\', :created_at, NULL, :updated_at)'
        );
        $stmt->execute([
            'campaign_id' => (int) $campaign['id'],
            'coupon_definition_id' => (int) $campaign['coupon_definition_id'],
            'account_id' => $accountId,
            'claim_source' => $claimSource,
            'source_context_json' => json_encode($sourceContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'dedupe_key' => $dedupeKey,
            'dedupe_hash' => $dedupeHash,
            'occupying_account_id' => (int) ($campaign['per_account_limit'] ?? 1) === 1 ? $accountId : null,
            'status' => 'reserved',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $claimLogId = (int) $pdo->lastInsertId();

        $issueId = sr_coupon_issue_to_account(
            $pdo,
            (int) $campaign['coupon_definition_id'],
            $accountId,
            'claim_campaign:' . (string) ($campaign['campaign_key'] ?? ''),
            null,
            sr_coupon_claim_issue_expires_at($campaign),
            [
                'claim_type' => 'free',
                'claim_campaign_id' => (int) $campaign['id'],
                'claim_log_id' => $claimLogId,
                'claim_snapshot' => [
                    'schema_version' => 'coupon_claim_snapshot_v1',
                    'claim_type' => 'free',
                    'campaign_id' => (int) $campaign['id'],
                    'campaign_key' => (string) ($campaign['campaign_key'] ?? ''),
                    'claim_log_id' => $claimLogId,
                    'nominal_price' => [
                        'amount' => 0,
                        'currency_code' => '',
                    ],
                    'charged_allocations' => [],
                    'settlement_kind' => 'free',
                ],
            ]
        );

        $stmt = $pdo->prepare(
            "UPDATE sr_coupon_claim_logs
             SET coupon_issue_id = :coupon_issue_id,
                 status = 'issued',
                 issued_at = :issued_at,
                 updated_at = :updated_at
             WHERE id = :id"
        );
        $stmt->execute([
            'coupon_issue_id' => $issueId,
            'issued_at' => $now,
            'updated_at' => $now,
            'id' => $claimLogId,
        ]);

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'claimed' => true,
            'already_claimed' => false,
            'coupon_issue_id' => $issueId,
            'claim_log_id' => $claimLogId,
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_coupon_asset_balance(PDO $pdo, string $assetModule, int $accountId): int
{
    $assetOptions = sr_coupon_asset_options($pdo);
    if (!isset($assetOptions[$assetModule])) {
        return 0;
    }

    $balanceFunction = (string) ($assetOptions[$assetModule]['balance_function'] ?? '');
    return $balanceFunction !== '' && function_exists($balanceFunction)
        ? max(0, (int) $balanceFunction($pdo, $accountId))
        : 0;
}

function sr_coupon_asset_transaction(PDO $pdo, string $assetModule, array $data): int
{
    $assetOptions = sr_coupon_asset_options($pdo);
    if (!isset($assetOptions[$assetModule])) {
        throw new RuntimeException('쿠폰 발급에 사용할 수 없는 포인트/금액 항목입니다.');
    }

    $transactionFunction = (string) ($assetOptions[$assetModule]['transaction_function'] ?? '');
    if ($transactionFunction === '' || !function_exists($transactionFunction)) {
        throw new RuntimeException('쿠폰 발급 자산 거래 함수를 찾을 수 없습니다.');
    }

    return (int) $transactionFunction($pdo, $data);
}

function sr_coupon_claim_paid_campaign_with_asset(PDO $pdo, string $campaignKey, int $accountId, string $intentToken, array $assetModules, string $claimSource = 'coupon_zone', array $sourceContext = []): array
{
    if (!sr_coupon_usage_enabled($pdo)) {
        throw new InvalidArgumentException('쿠폰·이용권을 사용하지 않도록 설정되어 있습니다.');
    }

    if ($accountId <= 0) {
        throw new InvalidArgumentException('로그인 후 쿠폰을 발급받을 수 있습니다.');
    }
    $intentToken = trim($intentToken);
    if ($intentToken === '') {
        throw new InvalidArgumentException('발급 요청 토큰이 없습니다. 화면을 새로고침한 뒤 다시 시도해 주세요.');
    }

    $campaign = sr_coupon_claim_campaign_by_key($pdo, $campaignKey);
    if (!is_array($campaign)) {
        throw new InvalidArgumentException('발급 캠페인을 찾을 수 없습니다.');
    }
    if ((string) ($campaign['claim_type'] ?? '') !== 'paid') {
        throw new InvalidArgumentException('유료 발급 캠페인이 아닙니다.');
    }

    $allowedAssetModules = sr_coupon_asset_module_keys_from_value($pdo, $campaign['allowed_asset_modules_json'] ?? '');
    $selectedAssetModules = sr_coupon_asset_module_keys_from_value($pdo, $assetModules);
    if ($selectedAssetModules === []) {
        throw new InvalidArgumentException('유료 발급에 사용할 포인트/금액 항목을 선택하세요.');
    }
    foreach ($selectedAssetModules as $assetModule) {
        if (!in_array($assetModule, $allowedAssetModules, true)) {
            throw new InvalidArgumentException('유료 발급에 사용할 포인트/금액 항목을 다시 선택하세요.');
        }
    }

    $dedupeKey = 'coupon_paid_claim:' . (string) (int) $campaign['id'] . ':' . (string) $accountId . ':' . $intentToken;
    $dedupeHash = sr_coupon_claim_dedupe_hash($dedupeKey);
    $startedTransaction = !$pdo->inTransaction();
    $savepointName = 'sr_coupon_paid_claim';
    if ($startedTransaction) {
        $pdo->beginTransaction();
    } else {
        $pdo->exec('SAVEPOINT ' . $savepointName);
    }

    try {
        $campaign = sr_coupon_claim_campaign_by_key($pdo, $campaignKey, true);
        if (!is_array($campaign)) {
            throw new InvalidArgumentException('발급 캠페인을 찾을 수 없습니다.');
        }
        if ((string) ($campaign['claim_type'] ?? '') !== 'paid') {
            throw new InvalidArgumentException('유료 발급 캠페인이 아닙니다.');
        }
        $lockedAllowedAssetModules = sr_coupon_asset_module_keys_from_value($pdo, $campaign['allowed_asset_modules_json'] ?? '');
        foreach ($selectedAssetModules as $assetModule) {
            if (!in_array($assetModule, $lockedAllowedAssetModules, true)) {
                throw new InvalidArgumentException('유료 발급에 사용할 포인트/금액 항목을 다시 선택하세요.');
            }
        }
        sr_coupon_validate_claim_campaign($campaign, $claimSource);
        sr_coupon_self_expire_claims($pdo, (int) $campaign['id'], $accountId);

        $existing = sr_coupon_claim_log_by_dedupe_hash($pdo, (int) $campaign['id'], $dedupeHash);
        if (is_array($existing) && (string) ($existing['status'] ?? '') === 'issued' && (int) ($existing['coupon_issue_id'] ?? 0) > 0) {
            if ($startedTransaction) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT ' . $savepointName);
            }
            return [
                'claimed' => false,
                'already_claimed' => true,
                'coupon_issue_id' => (int) $existing['coupon_issue_id'],
                'claim_log_id' => (int) ($existing['id'] ?? 0),
            ];
        }
        if (is_array($existing)) {
            throw new InvalidArgumentException('이전 발급 요청이 아직 처리 중입니다.');
        }

        sr_coupon_assert_claim_limits($pdo, $campaign, $accountId);

        $now = sr_now();
        $stmt = $pdo->prepare(
            'INSERT INTO sr_coupon_claim_logs
                (campaign_id, coupon_definition_id, account_id, coupon_issue_id, claim_source, source_context_json, dedupe_key, dedupe_hash, occupying_account_id, status, reserved_until, failure_code, failure_message, created_at, issued_at, updated_at)
             VALUES
                (:campaign_id, :coupon_definition_id, :account_id, NULL, :claim_source, :source_context_json, :dedupe_key, :dedupe_hash, :occupying_account_id, :status, NULL, \'\', \'\', :created_at, NULL, :updated_at)'
        );
        $stmt->execute([
            'campaign_id' => (int) $campaign['id'],
            'coupon_definition_id' => (int) $campaign['coupon_definition_id'],
            'account_id' => $accountId,
            'claim_source' => $claimSource,
            'source_context_json' => json_encode($sourceContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'dedupe_key' => $dedupeKey,
            'dedupe_hash' => $dedupeHash,
            'occupying_account_id' => (int) ($campaign['per_account_limit'] ?? 1) === 1 ? $accountId : null,
            'status' => 'reserved',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $claimLogId = (int) $pdo->lastInsertId();
        $priceAmount = max(0, (int) ($campaign['price_amount'] ?? 0));
        $priceCurrencyCode = sr_coupon_clean_currency_code((string) ($campaign['price_currency_code'] ?? ''));
        $plan = sr_member_asset_settlement_plan(
            $pdo,
            sr_coupon_asset_options($pdo),
            static function (PDO $pdo, string $assetModule) use ($accountId): int {
                return sr_coupon_asset_balance($pdo, $assetModule, $accountId);
            },
            $selectedAssetModules,
            $priceAmount,
            $priceCurrencyCode
        );
        if (empty($plan['ok'])) {
            throw new InvalidArgumentException('선택한 포인트/금액 항목의 잔액이 부족하거나 정확히 차감할 수 없습니다.');
        }

        $chargedAllocations = [];
        foreach ((array) ($plan['allocations'] ?? []) as $allocation) {
            $assetModule = (string) ($allocation['asset_module'] ?? '');
            $amount = (int) ($allocation['amount'] ?? 0);
            if ($assetModule === '' || $amount <= 0) {
                continue;
            }
            $transactionId = sr_coupon_asset_transaction($pdo, $assetModule, [
                'account_id' => $accountId,
                'amount' => -$amount,
                'transaction_type' => 'use',
                'reason' => '유료 쿠폰 발급: ' . (string) ($campaign['title'] ?? $campaign['campaign_key'] ?? ''),
                'reference_type' => 'coupon_claim',
                'reference_id' => (string) $claimLogId,
                'created_by_account_id' => null,
            ]);
            $allocation['transaction_id'] = $transactionId;
            $chargedAllocations[] = $allocation;
        }
        if ($priceAmount > 0 && $chargedAllocations === []) {
            throw new InvalidArgumentException('차감할 포인트/금액 항목을 찾을 수 없습니다.');
        }
        $roundingPolicyVersion = '';
        foreach ($chargedAllocations as $allocation) {
            if (!is_array($allocation)) {
                continue;
            }
            $purchasePowerSnapshot = is_array($allocation['purchase_power_snapshot'] ?? null) ? $allocation['purchase_power_snapshot'] : [];
            $roundingPolicyVersion = (string) ($purchasePowerSnapshot['rounding_policy_version'] ?? '');
            if ($roundingPolicyVersion !== '') {
                break;
            }
        }
        if ($roundingPolicyVersion === '') {
            throw new RuntimeException('유료 쿠폰 발급 차감 스냅샷의 반올림 정책 버전을 확인할 수 없습니다.');
        }

        $issueId = sr_coupon_issue_to_account(
            $pdo,
            (int) $campaign['coupon_definition_id'],
            $accountId,
            'paid_claim_campaign:' . (string) ($campaign['campaign_key'] ?? ''),
            null,
            sr_coupon_claim_issue_expires_at($campaign),
            [
                'claim_type' => 'paid',
                'claim_campaign_id' => (int) $campaign['id'],
                'claim_log_id' => $claimLogId,
                'nominal_price_amount' => $priceAmount,
                'nominal_price_currency_code' => $priceCurrencyCode,
                'asset_reference_module' => 'coupon',
                'asset_reference_type' => 'paid_claim',
                'asset_reference_id' => (string) $claimLogId,
                'claim_snapshot' => [
                    'schema_version' => 'coupon_claim_snapshot_v1',
                    'claim_type' => 'paid',
                    'settlement_kind' => 'paid',
                    'snapshot_schema_version' => 'asset_settlement_snapshot_v1',
                    'rounding_policy_version' => $roundingPolicyVersion,
                    'campaign_id' => (int) $campaign['id'],
                    'campaign_key' => (string) ($campaign['campaign_key'] ?? ''),
                    'claim_log_id' => $claimLogId,
                    'nominal_price' => [
                        'amount' => $priceAmount,
                        'currency_code' => $priceCurrencyCode,
                    ],
                    'charged_allocations' => $chargedAllocations,
                ],
            ]
        );

        $claimLogUpdateSql = "UPDATE sr_coupon_claim_logs
             SET coupon_issue_id = :coupon_issue_id,
                 status = 'issued',
                 issued_at = :issued_at,
                 updated_at = :updated_at";
        $claimLogUpdateParams = [
            'coupon_issue_id' => $issueId,
            'issued_at' => sr_now(),
            'updated_at' => sr_now(),
            'id' => $claimLogId,
        ];
        if (sr_coupon_claim_log_asset_reference_columns_available($pdo)) {
            $claimLogUpdateSql .= ",
                 asset_reference_module = :asset_reference_module,
                 asset_reference_type = :asset_reference_type,
                 asset_reference_id = :asset_reference_id,
                 payment_reference_module = '',
                 payment_reference_type = '',
                 payment_reference_id = ''";
            $claimLogUpdateParams['asset_reference_module'] = 'coupon';
            $claimLogUpdateParams['asset_reference_type'] = 'paid_claim';
            $claimLogUpdateParams['asset_reference_id'] = (string) $claimLogId;
        }
        $claimLogUpdateSql .= '
             WHERE id = :id';
        $stmt = $pdo->prepare($claimLogUpdateSql);
        $stmt->execute($claimLogUpdateParams);

        if ($startedTransaction) {
            $pdo->commit();
        } else {
            $pdo->exec('RELEASE SAVEPOINT ' . $savepointName);
        }

        return [
            'claimed' => true,
            'already_claimed' => false,
            'coupon_issue_id' => $issueId,
            'claim_log_id' => $claimLogId,
            'charged_allocations' => $chargedAllocations,
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        } elseif (!$startedTransaction && $pdo->inTransaction()) {
            $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepointName);
            $pdo->exec('RELEASE SAVEPOINT ' . $savepointName);
        }

        throw $exception;
    }
}

function sr_coupon_validate_claim_campaign(array $campaign, string $surface): void
{
    $now = sr_now();
    if ((string) ($campaign['status'] ?? '') !== 'active') {
        throw new InvalidArgumentException('현재 발급 중인 쿠폰이 아닙니다.');
    }
    if ((string) ($campaign['visibility'] ?? '') !== 'public') {
        throw new InvalidArgumentException('공개 발급 쿠폰이 아닙니다.');
    }
    if ((string) ($campaign['coupon_status'] ?? '') !== 'active') {
        throw new InvalidArgumentException('연결된 쿠폰이 사용 상태가 아닙니다.');
    }
    if ((string) ($campaign['starts_at'] ?? '') !== '' && strcmp((string) $campaign['starts_at'], $now) > 0) {
        throw new InvalidArgumentException('아직 발급 시작 전입니다.');
    }
    if ((string) ($campaign['ends_at'] ?? '') !== '' && strcmp((string) $campaign['ends_at'], $now) < 0) {
        throw new InvalidArgumentException('발급 기간이 종료되었습니다.');
    }
    if (!in_array($surface, sr_coupon_claim_surfaces_from_value($campaign['exposure_surfaces_json'] ?? ''), true)) {
        throw new InvalidArgumentException('이 화면에서는 발급할 수 없는 쿠폰입니다.');
    }
}

function sr_coupon_claim_log_by_dedupe_hash(PDO $pdo, int $campaignId, string $dedupeHash): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_coupon_claim_logs
         WHERE campaign_id = :campaign_id
           AND dedupe_hash = :dedupe_hash
         LIMIT 1'
    );
    $stmt->execute([
        'campaign_id' => $campaignId,
        'dedupe_hash' => $dedupeHash,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_coupon_assert_claim_limits(PDO $pdo, array $campaign, int $accountId): void
{
    $state = sr_coupon_claim_campaign_state($pdo, $campaign, $accountId);
    if (!$state['claimable']) {
        $message = (string) ($state['message'] ?? '');
        throw new InvalidArgumentException($message !== '' ? $message : '쿠폰을 발급할 수 없습니다.');
    }
}

function sr_coupon_update_issue_status(PDO $pdo, int $issueId, string $status, ?int $updatedByAccountId = null): void
{
    if ($issueId <= 0 || !in_array($status, sr_coupon_issue_statuses(), true)) {
        throw new InvalidArgumentException('Coupon issue status is invalid.');
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_coupon_issues
         SET status = :status,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'status' => $status,
        'updated_at' => sr_now(),
        'id' => $issueId,
    ]);

    sr_coupon_notify_issue_event($pdo, $issueId, 'issue.status_updated', $updatedByAccountId, [
        'status_label' => sr_coupon_issue_status_label($status),
    ]);
}

function sr_coupon_issue_status_label(string $status): string
{
    $labels = [
        'active' => '사용 가능',
        'used' => '사용 완료',
        'expired' => '만료',
        'revoked' => '지급 취소',
        'withdrawn_expired' => '탈퇴 만료',
        'refund_requested' => '환급 요청',
        'refunded' => '환급 완료',
    ];

    return $labels[$status] ?? $status;
}

function sr_coupon_redemption_status_label(string $status): string
{
    $labels = [
        'redeemed' => '사용 완료',
        'refunded' => '환불 완료',
    ];

    return $labels[$status] ?? $status;
}

function sr_coupon_active_account_issues(PDO $pdo, int $accountId, int $limit = 100): array
{
    if ($accountId <= 0 || !sr_coupon_usage_enabled($pdo)) {
        return [];
    }

    sr_coupon_expire_active_issues($pdo, $accountId);

    $limit = max(1, min(300, $limit));
    $discountColumns = sr_coupon_definition_discount_columns_available($pdo)
        ? 'd.discount_amount, d.discount_percent, d.discount_currency_code'
        : '0 AS discount_amount, 0 AS discount_percent, \'\' AS discount_currency_code';
    $stmt = $pdo->prepare(
        "SELECT i.*, d.coupon_key, d.title, d.description, d.coupon_type, " . $discountColumns . ", d.target_type, d.target_id, d.refundable_policy, d.max_uses_per_issue
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         WHERE i.account_id = :account_id
           AND i.status = 'active'
           AND d.status IN ('active', 'issue_stopped')
           AND (i.expires_at IS NULL OR i.expires_at >= :now_value)
         ORDER BY i.id DESC
         LIMIT " . $limit
    );
    $stmt->execute([
        'account_id' => $accountId,
        'now_value' => sr_now(),
    ]);

    return $stmt->fetchAll();
}

function sr_coupon_active_account_target_issues(PDO $pdo, int $accountId, string $targetType, string $targetId, int $limit = 20): array
{
    if ($accountId <= 0 || $targetType === '' || $targetId === '' || !sr_coupon_usage_enabled($pdo) || !sr_coupon_tables_available($pdo)) {
        return [];
    }

    $matched = [];
    foreach (sr_coupon_active_account_issues($pdo, $accountId, max(100, $limit * 5)) as $issue) {
        if (!sr_coupon_issue_matches_target($issue, $targetType, $targetId)) {
            continue;
        }

        $matched[] = $issue;
        if (count($matched) >= $limit) {
            break;
        }
    }

    return $matched;
}

function sr_coupon_active_account_issue_count(PDO $pdo, int $accountId): int
{
    if ($accountId <= 0 || !sr_coupon_usage_enabled($pdo) || !sr_coupon_tables_available($pdo)) {
        return 0;
    }

    sr_coupon_expire_active_issues($pdo, $accountId);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         WHERE i.account_id = :account_id
           AND i.status = 'active'
           AND d.status IN ('active', 'issue_stopped')
           AND (i.expires_at IS NULL OR i.expires_at >= :now_value)"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'now_value' => sr_now(),
    ]);

    return (int) $stmt->fetchColumn();
}

function sr_coupon_tables_available(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM sr_coupon_issues LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_coupon_definitions LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_coupon_redemptions LIMIT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

function sr_coupon_table_available(PDO $pdo, string $tableName): bool
{
    if (preg_match('/\Asr_coupon_[a-z0-9_]+\z/', $tableName) !== 1) {
        return false;
    }

    try {
        $pdo->query('SELECT 1 FROM ' . $tableName . ' LIMIT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

function sr_coupon_issue_matches_target(array $issue, string $targetType, string $targetId): bool
{
    $definitionTargetType = (string) ($issue['target_type'] ?? '');
    $definitionTargetId = (string) ($issue['target_id'] ?? '');
    if ($definitionTargetType === 'all') {
        return true;
    }

    return $definitionTargetType === $targetType
        && ($definitionTargetId === '' || $definitionTargetId === $targetId);
}

function sr_coupon_has_redemption(PDO $pdo, int $accountId, string $dedupeKey): bool
{
    if ($accountId <= 0 || $dedupeKey === '') {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM sr_coupon_redemptions
         WHERE account_id = :account_id
           AND dedupe_key = :dedupe_key
           AND status = 'redeemed'
         LIMIT 1"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'dedupe_key' => $dedupeKey,
    ]);

    return is_array($stmt->fetch());
}

function sr_coupon_redemption_refund_columns_available(PDO $pdo): bool
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }

    try {
        $stmt = $pdo->query('SELECT refunded_at, refunded_by_account_id, refund_note FROM sr_coupon_redemptions LIMIT 1');
        $available = $stmt !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function sr_coupon_redemption_pricing_columns_available(PDO $pdo): bool
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }

    try {
        $stmt = $pdo->query('SELECT amount, currency_code, asset_unit, policy_summary, priced_at, target_snapshot_json FROM sr_coupon_redemptions LIMIT 1');
        $available = $stmt !== false;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function sr_coupon_issue_claim_columns_available(PDO $pdo): bool
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }

    try {
        $stmt = $pdo->query('SELECT claim_type, claim_campaign_id, claim_log_id, nominal_price_amount, nominal_price_currency_code, asset_reference_module, asset_reference_type, asset_reference_id, claim_snapshot_json FROM sr_coupon_issues LIMIT 1');
        $available = $stmt !== false;
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function sr_coupon_redemption_pricing_snapshot_from_result(array $pricing, string $targetType, string $targetId): array
{
    if (empty($pricing['ok'])) {
        return [
            'amount' => 0,
            'currency_code' => '',
            'asset_unit' => '',
            'policy_summary' => '',
            'priced_at' => null,
            'target_snapshot_json' => null,
        ];
    }

    $snapshot = [
        'target_type' => (string) ($pricing['target_type'] ?? $targetType),
        'target_id' => (string) ($pricing['target_id'] ?? $targetId),
        'amount' => sr_coupon_nonnegative_int_or_null($pricing['price_amount'] ?? 0) ?? 0,
        'currency_code' => sr_coupon_clean_currency_code((string) ($pricing['currency_code'] ?? '')),
        'asset_unit' => sr_coupon_clean_key((string) ($pricing['asset_unit'] ?? ''), 40),
        'is_free' => !empty($pricing['is_free']),
        'already_entitled' => !empty($pricing['already_entitled']),
        'policy_summary' => sr_coupon_clean_text((string) ($pricing['policy_summary'] ?? ''), 255),
        'priced_at' => sr_coupon_clean_text((string) ($pricing['priced_at'] ?? sr_now()), 30),
    ];
    foreach (['coupon_type', 'discount_amount', 'remaining_amount'] as $optionalKey) {
        if (array_key_exists($optionalKey, $pricing)) {
            $amount = $optionalKey === 'coupon_type' ? null : sr_coupon_nonnegative_int_or_null($pricing[$optionalKey]);
            $snapshot[$optionalKey] = $optionalKey === 'coupon_type'
                ? sr_coupon_clean_key((string) $pricing[$optionalKey], 40)
                : ($amount ?? 0);
        }
    }

    return [
        'amount' => $snapshot['amount'],
        'currency_code' => $snapshot['currency_code'],
        'asset_unit' => $snapshot['asset_unit'],
        'policy_summary' => $snapshot['policy_summary'],
        'priced_at' => $snapshot['priced_at'],
        'target_snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
    ];
}

function sr_coupon_admin_redemption_count(PDO $pdo, array $runtimeConfig, array $filters = []): int
{
    $where = [];
    $params = [];

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('r.status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['target_type'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('r.target_type', 'target_type', $filters['target_type']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['refundable_policy'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('d.refundable_policy', 'refundable_policy', $filters['refundable_policy']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $couponKeyword = sr_coupon_clean_text((string) ($filters['coupon_q'] ?? ''), 120);
    if ($couponKeyword !== '') {
        $where[] = "(d.coupon_key LIKE :coupon_keyword_like ESCAPE '\\\\' OR d.title LIKE :coupon_keyword_like ESCAPE '\\\\')";
        $params['coupon_keyword_like'] = sr_coupon_like_keyword($couponKeyword);
    }

    $accountFilter = is_array($filters['account'] ?? null) ? $filters['account'] : [];
    $accountId = (int) ($accountFilter['account_id'] ?? 0);
    if ($accountId > 0) {
        $where[] = 'r.account_id = :account_id';
        $params['account_id'] = $accountId;
    } elseif (trim((string) ($accountFilter['keyword'] ?? '')) !== '') {
        $where[] = '1 = 0';
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS count_value
         FROM sr_coupon_redemptions r
         INNER JOIN sr_coupon_definitions d ON d.id = r.coupon_definition_id
         INNER JOIN sr_coupon_issues i ON i.id = r.coupon_issue_id
         LEFT JOIN sr_member_accounts a ON a.id = r.account_id'
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
    );
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_coupon_admin_redemptions(PDO $pdo, array $runtimeConfig, int $limit = 100, array $filters = [], array $sort = [], int $offset = 0): array
{
    $limit = max(1, min(300, $limit));
    $offset = max(0, $offset);
    $refundColumns = sr_coupon_redemption_refund_columns_available($pdo)
        ? 'r.refunded_at, r.refunded_by_account_id, r.refund_note'
        : 'NULL AS refunded_at, NULL AS refunded_by_account_id, \'\' AS refund_note';
    $pricingColumns = sr_coupon_redemption_pricing_columns_available($pdo)
        ? 'r.amount, r.currency_code, r.asset_unit, r.policy_summary, r.priced_at'
        : '0 AS amount, \'\' AS currency_code, \'\' AS asset_unit, \'\' AS policy_summary, NULL AS priced_at';
    $where = [];
    $params = [];
    $sortOptions = sr_coupon_admin_redemption_sort_options();
    $defaultSort = sr_coupon_admin_redemption_default_sort();
    $orderSql = sr_admin_sort_order_sql($sortOptions, $sort, $defaultSort);
    if ($orderSql === '') {
        $orderSql = ' ORDER BY r.id DESC';
    }

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('r.status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['target_type'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('r.target_type', 'target_type', $filters['target_type']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['refundable_policy'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('d.refundable_policy', 'refundable_policy', $filters['refundable_policy']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $couponKeyword = sr_coupon_clean_text((string) ($filters['coupon_q'] ?? ''), 120);
    if ($couponKeyword !== '') {
        $where[] = "(d.coupon_key LIKE :coupon_keyword_like ESCAPE '\\\\' OR d.title LIKE :coupon_keyword_like ESCAPE '\\\\')";
        $params['coupon_keyword_like'] = sr_coupon_like_keyword($couponKeyword);
    }

    $accountFilter = is_array($filters['account'] ?? null) ? $filters['account'] : [];
    $accountId = (int) ($accountFilter['account_id'] ?? 0);
    if ($accountId > 0) {
        $where[] = 'r.account_id = :account_id';
        $params['account_id'] = $accountId;
    } elseif (trim((string) ($accountFilter['keyword'] ?? '')) !== '') {
        $where[] = '1 = 0';
    }

    $sql = 'SELECT r.id, r.coupon_issue_id, r.coupon_definition_id, r.account_id,
                   r.target_type, r.target_id, r.reference_module, r.reference_type, r.reference_id,
                   r.dedupe_key, r.status, r.redeemed_at, ' . $refundColumns . ', ' . $pricingColumns . ',
                   d.coupon_key, d.title, d.coupon_type, d.refundable_policy, i.status AS issue_status, i.used_count,
                   a.display_name, a.email, a.status AS account_status
            FROM sr_coupon_redemptions r
            INNER JOIN sr_coupon_definitions d ON d.id = r.coupon_definition_id
            INNER JOIN sr_coupon_issues i ON i.id = r.coupon_issue_id
            LEFT JOIN sr_member_accounts a ON a.id = r.account_id'
        . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
        . $orderSql
        . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['account_public_hash'] = sr_admin_member_public_hash($runtimeConfig, (int) ($row['account_id'] ?? 0));
        $rows[] = $row;
    }

    return $rows;
}

function sr_coupon_refund_redemption(PDO $pdo, int $redemptionId, int $adminAccountId, string $refundNote): array
{
    $refundNote = sr_coupon_clean_text($refundNote, 255);
    if ($redemptionId <= 0) {
        throw new InvalidArgumentException('환불할 쿠폰 사용 내역을 선택하세요.');
    }
    if ($adminAccountId <= 0) {
        throw new InvalidArgumentException('관리자 계정을 확인할 수 없습니다.');
    }
    if ($refundNote === '') {
        throw new InvalidArgumentException('환불 사유를 입력하세요.');
    }
    if (!sr_coupon_redemption_refund_columns_available($pdo)) {
        throw new InvalidArgumentException('쿠폰 환불 컬럼 업데이트를 먼저 적용하세요.');
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT r.*, d.refundable_policy, d.coupon_type, d.max_uses_per_issue, d.title, i.status AS issue_status, i.used_count
             FROM sr_coupon_redemptions r
             INNER JOIN sr_coupon_definitions d ON d.id = r.coupon_definition_id
             INNER JOIN sr_coupon_issues i ON i.id = r.coupon_issue_id
             WHERE r.id = :id
             LIMIT 1'
            . sr_coupon_for_update_clause($pdo)
        );
        $stmt->execute(['id' => $redemptionId]);
        $redemption = $stmt->fetch();
        if (!is_array($redemption)) {
            throw new InvalidArgumentException('쿠폰 사용 내역을 찾을 수 없습니다.');
        }
        if ((string) ($redemption['status'] ?? '') !== 'redeemed') {
            throw new InvalidArgumentException('이미 환불되었거나 환불할 수 없는 사용 내역입니다.');
        }
        if ((string) ($redemption['refundable_policy'] ?? '') !== 'refundable') {
            throw new InvalidArgumentException('환급 가능 정책인 쿠폰만 수동 환불할 수 있습니다.');
        }
        if ((string) ($redemption['coupon_type'] ?? 'access') !== 'access') {
            throw new InvalidArgumentException('접근권 쿠폰 사용 내역만 수동 환불할 수 있습니다. 할인 쿠폰 복합 결제는 소비 도메인 취소 계약이 필요합니다.');
        }

        $now = sr_now();
        $usedCount = max(0, (int) ($redemption['used_count'] ?? 0) - 1);
        $issueStatus = (string) ($redemption['issue_status'] ?? '');
        $nextIssueStatus = $issueStatus === 'used' ? 'active' : $issueStatus;

        $originalDedupeKey = (string) ($redemption['dedupe_key'] ?? '');
        $refundedDedupeKey = sr_coupon_refunded_dedupe_key($redemptionId, $originalDedupeKey);

        $stmt = $pdo->prepare(
            "UPDATE sr_coupon_redemptions
             SET status = 'refunded',
                 dedupe_key = :dedupe_key,
                 refunded_at = :refunded_at,
                 refunded_by_account_id = :refunded_by_account_id,
                 refund_note = :refund_note
             WHERE id = :id
               AND status = 'redeemed'"
        );
        $stmt->execute([
            'dedupe_key' => $refundedDedupeKey,
            'refunded_at' => $now,
            'refunded_by_account_id' => $adminAccountId,
            'refund_note' => $refundNote,
            'id' => $redemptionId,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new InvalidArgumentException('이미 환불되었거나 환불할 수 없는 사용 내역입니다.');
        }

        $stmt = $pdo->prepare(
            'UPDATE sr_coupon_issues
             SET used_count = :used_count,
                 status = :status,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'used_count' => $usedCount,
            'status' => $nextIssueStatus,
            'updated_at' => $now,
            'id' => (int) $redemption['coupon_issue_id'],
        ]);

        $revokedAccess = sr_coupon_revoke_target_access_or_fail($pdo, (string) ($redemption['target_type'] ?? ''), (int) $redemption['account_id'], $originalDedupeKey);

        if ($startedTransaction) {
            $pdo->commit();
        }

        sr_coupon_notify_issue_event($pdo, (int) $redemption['coupon_issue_id'], 'redemption.refunded', $adminAccountId, [
            'redemption_id' => $redemptionId,
            'refund_note' => $refundNote,
            'refunded_at' => $now,
            'used_count' => $usedCount,
            'revoked_access_count' => $revokedAccess,
            'original_dedupe_key' => $originalDedupeKey,
            'refunded_dedupe_key' => $refundedDedupeKey,
            'status_label' => sr_coupon_issue_status_label($nextIssueStatus),
        ]);

        return [
            'coupon_issue_id' => (int) $redemption['coupon_issue_id'],
            'coupon_definition_id' => (int) $redemption['coupon_definition_id'],
            'account_id' => (int) $redemption['account_id'],
            'coupon_title' => (string) ($redemption['title'] ?? ''),
            'used_count' => $usedCount,
            'issue_status' => $nextIssueStatus,
            'refunded_at' => $now,
            'revoked_access_count' => $revokedAccess,
            'original_dedupe_key' => $originalDedupeKey,
            'refunded_dedupe_key' => $refundedDedupeKey,
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_coupon_asset_refund_reference_id(string $assetModule, int $transactionId): string
{
    return sr_coupon_clean_key($assetModule, 60) . '_transaction:' . (string) $transactionId;
}

function sr_coupon_refund_paid_issue_assets(PDO $pdo, int $issueId, int $adminAccountId, string $refundNote): array
{
    $refundNote = sr_coupon_clean_text($refundNote, 255);
    if ($issueId <= 0) {
        throw new InvalidArgumentException('환불할 쿠폰 발급본을 선택하세요.');
    }
    if ($adminAccountId <= 0) {
        throw new InvalidArgumentException('관리자 계정을 확인할 수 없습니다.');
    }
    if ($refundNote === '') {
        throw new InvalidArgumentException('환불 사유를 입력하세요.');
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT i.*, d.title AS coupon_title
             FROM sr_coupon_issues i
             INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
             WHERE i.id = :id
             LIMIT 1'
            . sr_coupon_for_update_clause($pdo)
        );
        $stmt->execute(['id' => $issueId]);
        $issue = $stmt->fetch();
        if (!is_array($issue)) {
            throw new InvalidArgumentException('쿠폰 발급본을 찾을 수 없습니다.');
        }
        if ((string) ($issue['claim_type'] ?? '') !== 'paid') {
            throw new InvalidArgumentException('유료 발급 쿠폰만 자산 환불할 수 있습니다.');
        }
        if ((string) ($issue['status'] ?? '') === 'refunded') {
            throw new InvalidArgumentException('이미 환불된 쿠폰입니다.');
        }
        if ((int) ($issue['used_count'] ?? 0) > 0 || (string) ($issue['status'] ?? '') === 'used') {
            throw new InvalidArgumentException('이미 사용된 쿠폰은 발급 환불할 수 없습니다.');
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS redemption_count
             FROM sr_coupon_redemptions
             WHERE coupon_issue_id = :issue_id
               AND status = 'redeemed'"
        );
        $stmt->execute(['issue_id' => $issueId]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException('사용 이력이 있는 쿠폰은 발급 환불할 수 없습니다.');
        }

        $claimLog = null;
        $claimLogId = (int) ($issue['claim_log_id'] ?? 0);
        if ($claimLogId > 0) {
            $stmt = $pdo->prepare(
                'SELECT *
                 FROM sr_coupon_claim_logs
                 WHERE id = :id
                 LIMIT 1'
                . sr_coupon_for_update_clause($pdo)
            );
            $stmt->execute(['id' => $claimLogId]);
            $claimLogRow = $stmt->fetch();
            $claimLog = is_array($claimLogRow) ? $claimLogRow : null;
        }

        $snapshot = json_decode((string) ($issue['claim_snapshot_json'] ?? ''), true);
        $allocations = is_array($snapshot) ? (array) ($snapshot['charged_allocations'] ?? []) : [];
        if ($allocations === []) {
            throw new InvalidArgumentException('환불할 자산 차감 스냅샷이 없습니다.');
        }

        $refundTransactions = [];
        foreach ($allocations as $allocation) {
            if (!is_array($allocation)) {
                continue;
            }
            $assetModule = sr_coupon_clean_key((string) ($allocation['asset_module'] ?? ''), 60);
            $amount = (int) ($allocation['amount'] ?? $allocation['asset_amount'] ?? 0);
            $sourceTransactionId = (int) ($allocation['transaction_id'] ?? 0);
            if ($assetModule === '' || $amount <= 0 || $sourceTransactionId <= 0) {
                throw new InvalidArgumentException('환불할 자산 차감 스냅샷이 올바르지 않습니다.');
            }

            $transactionData = [
                'account_id' => (int) ($issue['account_id'] ?? 0),
                'amount' => $amount,
                'transaction_type' => 'refund',
                'reason' => '유료 쿠폰 발급 환불: ' . (string) ($issue['coupon_title'] ?? ''),
                'reference_type' => 'refund',
                'reference_id' => sr_coupon_asset_refund_reference_id($assetModule, $sourceTransactionId),
                'created_by_account_id' => $adminAccountId,
            ];
            if ($assetModule === 'point') {
                $transactionData['refund_expiration_policy'] = 'original';
            }

            $refundTransactionId = sr_coupon_asset_transaction($pdo, $assetModule, $transactionData);
            $refundTransactions[] = [
                'asset_module' => $assetModule,
                'source_transaction_id' => $sourceTransactionId,
                'refund_transaction_id' => $refundTransactionId,
                'amount' => $amount,
            ];
        }
        if ($refundTransactions === []) {
            throw new InvalidArgumentException('환불할 자산 차감 스냅샷이 없습니다.');
        }

        $now = sr_now();
        $stmt = $pdo->prepare(
            "UPDATE sr_coupon_issues
             SET status = 'refunded',
                 updated_at = :updated_at
             WHERE id = :id
               AND status <> 'refunded'"
        );
        $stmt->execute([
            'updated_at' => $now,
            'id' => $issueId,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new InvalidArgumentException('이미 환불된 쿠폰입니다.');
        }

        if (is_array($claimLog)) {
            $stmt = $pdo->prepare(
                "UPDATE sr_coupon_claim_logs
                 SET status = 'cancelled',
                     occupying_account_id = NULL,
                     failure_code = :failure_code,
                     failure_message = :failure_message,
                     updated_at = :updated_at
                 WHERE id = :id"
            );
            $stmt->execute([
                'failure_code' => 'refunded',
                'failure_message' => $refundNote,
                'updated_at' => $now,
                'id' => (int) ($claimLog['id'] ?? 0),
            ]);
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        sr_coupon_notify_issue_event($pdo, $issueId, 'issue.refunded', $adminAccountId, [
            'refund_note' => $refundNote,
            'refunded_at' => $now,
            'refund_transactions' => $refundTransactions,
            'status_label' => sr_coupon_issue_status_label('refunded'),
        ]);

        return [
            'coupon_issue_id' => $issueId,
            'coupon_definition_id' => (int) ($issue['coupon_definition_id'] ?? 0),
            'account_id' => (int) ($issue['account_id'] ?? 0),
            'claim_log_id' => $claimLogId,
            'refunded_at' => $now,
            'refund_transactions' => $refundTransactions,
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function sr_coupon_refunded_dedupe_key(int $redemptionId, string $originalDedupeKey): string
{
    return 'refunded:' . (string) $redemptionId . ':' . substr(sha1($originalDedupeKey), 0, 24);
}

function sr_coupon_revoke_consumer_access(PDO $pdo, int $accountId, string $dedupeKey): int
{
    if ($accountId <= 0 || $dedupeKey === '') {
        return 0;
    }

    $revoked = 0;
    foreach (sr_coupon_target_contracts($pdo) as $target) {
        $revokeFunction = (string) ($target['revoke_access_function'] ?? '');
        if ($revokeFunction === '' || !function_exists($revokeFunction)) {
            continue;
        }

        try {
            $revoked += max(0, (int) $revokeFunction($pdo, $accountId, $dedupeKey));
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'coupon_revoke_consumer_access');
        }
    }

    return $revoked;
}

function sr_coupon_revoke_target_access_or_fail(PDO $pdo, string $targetType, int $accountId, string $dedupeKey): int
{
    if ($targetType === '' || $accountId <= 0 || $dedupeKey === '') {
        return 0;
    }

    $contracts = sr_coupon_target_contracts($pdo);
    $target = $contracts[$targetType] ?? null;
    $revokeFunction = is_array($target) ? (string) ($target['revoke_access_function'] ?? '') : '';
    if ($revokeFunction === '' || !function_exists($revokeFunction)) {
        throw new RuntimeException('쿠폰 사용처 접근권 회수 계약이 없습니다.');
    }

    try {
        $revoked = max(0, (int) $revokeFunction($pdo, $accountId, $dedupeKey));
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'coupon_revoke_target_access');
        throw new RuntimeException('쿠폰 사용처 접근권 회수에 실패했습니다.', 0, $exception);
    }

    if ($revoked < 1) {
        throw new RuntimeException('쿠폰 사용처 접근권 회수 대상이 없습니다.');
    }

    return $revoked;
}

function sr_coupon_redeem_for_target(PDO $pdo, int $accountId, string $targetType, string $targetId, array $context = []): array
{
    $dedupeKey = sr_coupon_clean_text((string) ($context['dedupe_key'] ?? ''), 160);
    $requestedIssueId = max(0, (int) ($context['coupon_issue_id'] ?? 0));
    if ($accountId <= 0 || $targetType === '' || $dedupeKey === '' || !sr_coupon_usage_enabled($pdo) || !sr_coupon_tables_available($pdo)) {
        return ['allowed' => false, 'processed' => false, 'message' => ''];
    }

    sr_coupon_expire_active_issues($pdo, $accountId);

    if (sr_coupon_has_redemption($pdo, $accountId, $dedupeKey)) {
        return ['allowed' => true, 'processed' => false, 'already_redeemed' => true, 'message' => ''];
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $issueIdCondition = $requestedIssueId > 0 ? ' AND i.id = :issue_id' : '';
        $couponTypeCondition = $requestedIssueId > 0 ? '' : " AND d.coupon_type = 'access'";
        $discountColumns = sr_coupon_definition_discount_columns_available($pdo)
            ? 'd.discount_amount, d.discount_percent, d.discount_currency_code'
            : '0 AS discount_amount, 0 AS discount_percent, \'\' AS discount_currency_code';
        $stmt = $pdo->prepare(
            "SELECT i.*, d.coupon_key, d.title, d.coupon_type, " . $discountColumns . ", d.target_type, d.target_id, d.max_uses_per_issue, d.refundable_policy
             FROM sr_coupon_issues i
             INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
             WHERE i.account_id = :account_id
               AND i.status = 'active'
               AND d.status IN ('active', 'issue_stopped')
               AND (i.expires_at IS NULL OR i.expires_at >= :now_value)" . $issueIdCondition . $couponTypeCondition . "
             ORDER BY i.expires_at IS NULL ASC, i.expires_at ASC, i.id ASC"
            . sr_coupon_for_update_clause($pdo)
        );
        $params = [
            'account_id' => $accountId,
            'now_value' => sr_now(),
        ];
        if ($requestedIssueId > 0) {
            $params['issue_id'] = $requestedIssueId;
        }
        $stmt->execute($params);
        $selectedIssue = null;
        foreach ($stmt->fetchAll() as $issue) {
            if (sr_coupon_issue_matches_target($issue, $targetType, $targetId)) {
                $selectedIssue = $issue;
                break;
            }
        }

        if (!is_array($selectedIssue)) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }
            return ['allowed' => false, 'processed' => false, 'message' => ''];
        }

        $pricing = sr_coupon_target_pricing($pdo, $targetType, $targetId, $accountId, $context);
        if (array_key_exists('price_amount', $context)) {
            $contextPriceAmount = sr_coupon_nonnegative_int_or_null($context['price_amount']);
            if ($contextPriceAmount === null) {
                $pricing = [
                    'ok' => false,
                    'failure_code' => 'pricing_amount_invalid',
                    'failure_message' => '쿠폰 사용처 가격 금액이 올바르지 않습니다.',
                ];
            } else {
                $pricing['price_amount'] = $contextPriceAmount;
            }
        }
        if (array_key_exists('currency_code', $context)) {
            $pricing['currency_code'] = sr_coupon_clean_currency_code((string) $context['currency_code']);
        }
        if (array_key_exists('policy_summary', $context)) {
            $pricing['policy_summary'] = sr_coupon_clean_text((string) $context['policy_summary'], 255);
        }
        if (!empty($pricing['ok']) && !empty($pricing['already_entitled'])) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return [
                'allowed' => true,
                'processed' => false,
                'already_entitled' => true,
                'message' => '',
            ];
        }
        $discountApplication = sr_coupon_discount_application($selectedIssue, $pricing);
        if (empty($discountApplication['ok'])) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return [
                'allowed' => false,
                'processed' => false,
                'message' => (string) ($discountApplication['message'] ?? ''),
            ];
        }
        $pricing['coupon_type'] = (string) ($discountApplication['coupon_type'] ?? ($selectedIssue['coupon_type'] ?? 'access'));
        $pricing['discount_amount'] = (int) ($discountApplication['discount_amount'] ?? 0);
        $pricing['remaining_amount'] = (int) ($discountApplication['remaining_amount'] ?? 0);

        $now = sr_now();
        $redemptionColumns = [
            'coupon_issue_id',
            'coupon_definition_id',
            'account_id',
            'target_type',
            'target_id',
            'reference_module',
            'reference_type',
            'reference_id',
            'dedupe_key',
            'status',
            'redeemed_at',
            'created_at',
        ];
        $redemptionValues = [
            'coupon_issue_id' => (int) $selectedIssue['id'],
            'coupon_definition_id' => (int) $selectedIssue['coupon_definition_id'],
            'account_id' => $accountId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reference_module' => sr_coupon_clean_key((string) ($context['reference_module'] ?? ''), 60),
            'reference_type' => sr_coupon_clean_text((string) ($context['reference_type'] ?? ''), 80),
            'reference_id' => sr_coupon_clean_text((string) ($context['reference_id'] ?? $targetId), 120),
            'dedupe_key' => $dedupeKey,
            'status' => 'redeemed',
            'redeemed_at' => $now,
            'created_at' => $now,
        ];
        if (sr_coupon_redemption_pricing_columns_available($pdo)) {
            $pricingSnapshot = sr_coupon_redemption_pricing_snapshot_from_result($pricing, $targetType, $targetId);
            foreach (['amount', 'currency_code', 'asset_unit', 'policy_summary', 'priced_at', 'target_snapshot_json'] as $column) {
                $redemptionColumns[] = $column;
                $redemptionValues[$column] = $pricingSnapshot[$column];
            }
        }
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $redemptionColumns);
        $stmt = $pdo->prepare(
            'INSERT INTO sr_coupon_redemptions
                (' . implode(', ', $redemptionColumns) . ')
             VALUES
                (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($redemptionValues);
        $redemptionId = (int) $pdo->lastInsertId();

        $usedCount = (int) $selectedIssue['used_count'] + 1;
        $maxUses = max(1, (int) $selectedIssue['max_uses_per_issue']);
        $newStatus = $usedCount >= $maxUses ? 'used' : 'active';
        $stmt = $pdo->prepare(
            'UPDATE sr_coupon_issues
             SET used_count = :used_count,
                 status = :status,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'used_count' => $usedCount,
            'status' => $newStatus,
            'updated_at' => $now,
            'id' => (int) $selectedIssue['id'],
        ]);

        if ($startedTransaction) {
            $pdo->commit();
        }

        sr_coupon_notify_issue_event($pdo, (int) $selectedIssue['id'], 'redemption.redeemed', null, [
            'redemption_id' => $redemptionId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reference_module' => sr_coupon_clean_key((string) ($context['reference_module'] ?? ''), 60),
            'reference_type' => sr_coupon_clean_text((string) ($context['reference_type'] ?? ''), 80),
            'reference_id' => sr_coupon_clean_text((string) ($context['reference_id'] ?? $targetId), 120),
            'used_count' => $usedCount,
            'max_uses_per_issue' => $maxUses,
            'status_label' => sr_coupon_issue_status_label($newStatus),
            'created_at' => $now,
        ]);

        return [
            'allowed' => true,
            'processed' => true,
            'coupon_issue_id' => (int) $selectedIssue['id'],
            'coupon_definition_id' => (int) $selectedIssue['coupon_definition_id'],
            'coupon_redemption_id' => $redemptionId,
            'dedupe_key' => $dedupeKey,
            'coupon_title' => (string) $selectedIssue['title'],
            'coupon_type' => (string) ($selectedIssue['coupon_type'] ?? 'access'),
            'discount_amount' => (int) ($discountApplication['discount_amount'] ?? 0),
            'remaining_amount' => (int) ($discountApplication['remaining_amount'] ?? 0),
            'full_coverage' => !empty($discountApplication['full_coverage']),
            'message' => '',
        ];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'coupon_redeem_for_target');
        }

        return ['allowed' => false, 'processed' => false, 'message' => ''];
    }
}

function sr_coupon_notify_issue_event(PDO $pdo, int $issueId, string $eventKey, ?int $createdByAccountId = null, array $metadata = [], array $channels = []): ?int
{
    $createAccountEventFunction = sr_coupon_notification_event_function($pdo);
    if ($createAccountEventFunction === '') {
        return null;
    }

    if ($channels === []) {
        $caseSetting = sr_coupon_notification_setting_for_event(sr_coupon_settings($pdo), $eventKey);
        if (is_array($caseSetting)) {
            if (empty($caseSetting['enabled'])) {
                return null;
            }
            $channels = sr_coupon_notification_channels_from_value($caseSetting['channels'] ?? ['site']);
        }
    }

    $issue = sr_coupon_issue_by_id($pdo, $issueId);
    if (!is_array($issue)) {
        return null;
    }

    try {
        $payload = [
            'account_id' => (int) $issue['account_id'],
            'module_key' => 'coupon',
            'event_key' => $eventKey,
            'created_by_account_id' => $createdByAccountId !== null && $createdByAccountId > 0 ? $createdByAccountId : null,
            'metadata' => array_merge(sr_coupon_issue_notification_metadata($issue), $metadata),
        ];
        if ($channels !== []) {
            $channels = sr_coupon_notification_channels_from_value($channels);
            $payload['channels'] = $channels;
        }

        return $createAccountEventFunction($pdo, $payload);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'coupon_issue_notification');
        return null;
    }
}

function sr_coupon_notification_event_function(PDO $pdo): string
{
    return sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_account_event_function');
}

function sr_coupon_issue_notification_metadata(array $issue): array
{
    return [
        'coupon_issue_id' => (int) ($issue['id'] ?? 0),
        'coupon_definition_id' => (int) ($issue['coupon_definition_id'] ?? 0),
        'coupon_key' => (string) ($issue['coupon_key'] ?? ''),
        'coupon_title' => (string) ($issue['title'] ?? ''),
        'asset_label' => '쿠폰·이용권',
        'status' => (string) ($issue['status'] ?? ''),
        'status_label' => sr_coupon_issue_status_label((string) ($issue['status'] ?? '')),
        'issued_reason' => (string) ($issue['issued_reason'] ?? ''),
        'target_type' => (string) ($issue['target_type'] ?? ''),
        'target_id' => (string) ($issue['target_id'] ?? ''),
        'used_count' => (int) ($issue['used_count'] ?? 0),
        'max_uses_per_issue' => (int) ($issue['max_uses_per_issue'] ?? 1),
        'issued_at' => (string) ($issue['issued_at'] ?? ''),
        'expires_at' => (string) ($issue['expires_at'] ?? ''),
        'created_at' => sr_now(),
    ];
}

function sr_coupon_process_account_withdrawal(PDO $pdo, int $accountId): array
{
    if ($accountId <= 0 || !sr_coupon_tables_available($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT i.id,
                CASE WHEN d.refundable_policy = 'refundable' THEN 'refund_requested' ELSE 'withdrawn_expired' END AS next_status
         FROM sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         WHERE i.account_id = :account_id
           AND i.status = 'active'"
    );
    $stmt->execute(['account_id' => $accountId]);
    $pendingIssues = $stmt->fetchAll();

    $now = sr_now();
    $stmt = $pdo->prepare(
        "UPDATE sr_coupon_issues i
         INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
         SET i.status = CASE WHEN d.refundable_policy = 'refundable' THEN 'refund_requested' ELSE 'withdrawn_expired' END,
             i.updated_at = :updated_at
         WHERE i.account_id = :account_id
           AND i.status = 'active'"
    );
    $stmt->execute([
        'updated_at' => $now,
        'account_id' => $accountId,
    ]);
    $updatedCount = $stmt->rowCount();

    foreach ($pendingIssues as $pendingIssue) {
        $issueId = (int) ($pendingIssue['id'] ?? 0);
        $nextStatus = (string) ($pendingIssue['next_status'] ?? '');
        if ($issueId <= 0 || !in_array($nextStatus, ['withdrawn_expired', 'refund_requested'], true)) {
            continue;
        }

        sr_coupon_notify_issue_event($pdo, $issueId, 'issue.status_updated', null, [
            'status_label' => sr_coupon_issue_status_label($nextStatus),
        ]);
    }

    return [
        'label' => '쿠폰·이용권',
        'amount' => $updatedCount,
        'process' => '소멸/환급 검토',
    ];
}
