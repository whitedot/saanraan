#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_asset_notification_settings_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_asset_notification_settings_file(string $path): string
{
    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        sr_asset_notification_settings_error('cannot read required file: ' . $path);
        return '';
    }

    return $contents;
}

function sr_asset_notification_settings_require_markers(string $path, array $markers): void
{
    $contents = sr_asset_notification_settings_file($path);
    if ($contents === '') {
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            sr_asset_notification_settings_error($path . ' must contain asset notification setting marker: ' . $marker);
        }
    }
}

foreach ([
    'point' => [
        'label' => '포인트',
        'settings_path' => '/admin/points/settings',
        'default_case_enabled' => 'false',
        'events' => [
            'transaction.grant',
            'transaction.refund',
            'transaction.exchange_in',
            'transaction.use',
            'transaction.exchange_out',
            'transaction.exchange_fee',
            'transaction.expire',
            'transaction.adjustment.increase',
            'transaction.adjustment.decrease',
        ],
    ],
    'reward' => [
        'label' => '적립금',
        'settings_path' => '/admin/rewards/settings',
        'default_case_enabled' => 'true',
        'events' => [
            'transaction.grant',
            'transaction.refund',
            'transaction.exchange_in',
            'transaction.use',
            'transaction.exchange_out',
            'transaction.exchange_fee',
            'transaction.expire',
            'transaction.withdraw',
            'transaction.reclaim',
            'transaction.adjustment.increase',
            'transaction.adjustment.decrease',
        ],
    ],
    'deposit' => [
        'label' => '예치금',
        'settings_path' => '/admin/deposits/settings',
        'default_case_enabled' => 'true',
        'events' => [
            'transaction.deposit',
            'transaction.refund',
            'transaction.exchange_in',
            'transaction.use',
            'transaction.exchange_out',
            'transaction.exchange_fee',
            'transaction.withdraw',
            'transaction.adjustment.increase',
            'transaction.adjustment.decrease',
        ],
    ],
] as $moduleKey => $module) {
    $helperPath = 'modules/' . $moduleKey . '/helpers.php';
    $actionPath = 'modules/' . $moduleKey . '/actions/admin-' . ($moduleKey === 'deposit' ? 'deposits' : $moduleKey . 's') . '-settings.php';
    if ($moduleKey === 'point') {
        $actionPath = 'modules/point/actions/admin-points-settings.php';
    }
    $viewPath = 'modules/' . $moduleKey . '/views/admin-settings.php';
    $prefix = 'sr_' . $moduleKey;
    $dataPrefix = $moduleKey . '-notification';

    sr_asset_notification_settings_require_markers($helperPath, [
        "'notification_cases' => " . $prefix . '_default_notification_case_settings()',
        'function ' . $prefix . '_notification_cases(): array',
        'function ' . $prefix . '_notification_case_key_for_event(string $eventKey): string',
        'function ' . $prefix . '_default_notification_case_settings(): array',
        "'default_enabled' => " . (string) ($module['default_case_enabled'] ?? 'true'),
        'function ' . $prefix . '_notification_channels_from_value(mixed $value): array',
        'function ' . $prefix . '_notification_channel_options(PDO $pdo): array',
        'function ' . $prefix . '_notification_case_settings_from_value(mixed $value): array',
        'function ' . $prefix . '_notification_setting_for_event(array $settings, string $eventKey): ?array',
        '$settings[\'notification_cases\'] = ' . $prefix . '_notification_case_settings_from_value($settings[\'notification_cases\'] ?? []);',
        "'notification_cases'",
        (string) ($module['label'] ?? ''),
        '$caseSetting = ' . $prefix . '_notification_setting_for_event(' . $prefix . '_settings($pdo), $eventKey);',
        'if (empty($caseSetting[\'enabled\'])) {' . "\n" . '                return null;',
        '$payload[\'channels\'] = $channels;',
    ]);

    foreach ($module['events'] as $eventKey) {
        sr_asset_notification_settings_require_markers($helperPath, ["'event_key' => '" . $eventKey . "'"]);
        sr_asset_notification_settings_require_markers('modules/notification/install.sql', ["'" . $moduleKey . "', '" . $eventKey . "'"]);
        if (str_starts_with($eventKey, 'transaction.exchange_') || ($moduleKey === 'reward' && $eventKey === 'transaction.withdraw')) {
            sr_asset_notification_settings_require_markers('modules/notification/updates/2026.06.012.sql', ["'" . $moduleKey . "', '" . $eventKey . "'"]);
        }
    }

    sr_asset_notification_settings_require_markers($actionPath, [
        '$notificationCases = ' . $prefix . '_notification_cases();',
        '$notificationChannelOptions = ' . $prefix . '_notification_channel_options($pdo);',
        '$postedCases = $_POST[\'notification_cases\'] ?? [];',
        '$allowedChannels = array_fill_keys($notificationChannelOptions, true);',
        'notification_cases',
        '$caseSettings',
        '\'notification_cases\' => (array) ($settings[\'notification_cases\'] ?? [])',
    ]);

    sr_asset_notification_settings_require_markers($viewPath, [
        '$notificationCases',
        '$notificationCaseSettings = ' . $prefix . '_notification_case_settings_from_value($settings[\'notification_cases\'] ?? []);',
        '$allNotificationCasesEnabled',
        'notification_cases[',
        'data-' . $dataPrefix . '-bulk-toggle',
        'data-' . $dataPrefix . '-case-toggle',
        'data-' . $dataPrefix . '-channel',
        'data-' . $dataPrefix . '-required-label',
        '전체활성',
        '전체비활성',
        '알림 채널을 하나 이상 선택하세요.',
    ]);
}

sr_asset_notification_settings_require_markers('modules/coupon/helpers.php', [
    'function sr_coupon_notification_setting_for_event(array $settings, string $eventKey): ?array',
    "'default_enabled' => true",
]);
sr_asset_notification_settings_require_markers('modules/coupon/actions/admin-coupon-settings.php', [
    '\'notification_cases\' => $caseSettings',
    '\'notification_cases\' => (array) ($settings[\'notification_cases\'] ?? [])',
]);
sr_asset_notification_settings_require_markers('modules/coupon/views/admin-settings.php', [
    '$allNotificationCasesEnabled',
    'data-coupon-notification-bulk-toggle',
    '전체활성',
    '전체비활성',
]);

sr_asset_notification_settings_require_markers('modules/asset_exchange/helpers.php', [
    'function sr_asset_exchange_notification_event_keys(): array',
    'function sr_asset_exchange_notification_groups(PDO $pdo): array',
    "'transaction.exchange_out'",
    "'transaction.exchange_in'",
    "'transaction.exchange_fee'",
    "'save_settings_function'",
]);
sr_asset_notification_settings_require_markers('modules/asset_exchange/actions/admin-asset-exchange.php', [
    '$notificationGroups = sr_asset_exchange_notification_groups($pdo);',
    '$postedNotificationCases = $_POST[\'notification_cases\'] ?? [];',
    '$notificationSettingsByModule',
    '$moduleSettings[\'notification_cases\'] = $notificationSettingsByModule[$moduleKey];',
    '\'notification_cases\' => $notificationSettingsByModule',
]);
sr_asset_notification_settings_require_markers('modules/asset_exchange/views/admin-asset-exchange.php', [
    '$notificationGroups',
    '$allNotificationCasesEnabled',
    'data-asset-exchange-notification-bulk-toggle',
    'data-asset-exchange-notification-case-toggle',
    'data-asset-exchange-notification-channel',
    'data-asset-exchange-notification-required-label',
    '전체활성',
    '전체비활성',
    '알림 채널을 하나 이상 선택하세요.',
]);

sr_asset_notification_settings_require_markers('docs/module-guide.md', [
    '포인트, 적립금, 예치금, 쿠폰 모듈은 각 환경설정 화면에서 거래/지급/사용/환불/상태 변경 같은 회원 알림 케이스별 사용 여부와 채널을 저장한다.',
    '환전 환경설정 화면은 포인트/적립금/예치금이 소유한 `transaction.exchange_out`, `transaction.exchange_in`, `transaction.exchange_fee` 케이스를 함께 편집하며 저장값은 각 자산 모듈의 알림 케이스 설정에 반영한다.',
    '포인트 모듈의 회원 알림 케이스 기본값은 사용 안 함이고, 적립금/예치금/쿠폰 모듈은 기존 동작 보존을 위해 케이스 기본값을 사용으로 둔다.',
]);
sr_asset_notification_settings_require_markers('docs/security-model.md', [
    '포인트/적립금/예치금/쿠폰 환경설정의 케이스별 알림 채널',
    '환전 환경설정 화면에서 바꾸는 환전 알림도 각 자산 모듈의 케이스별 채널 설정으로 저장한다.',
]);
sr_asset_notification_settings_require_markers('docs/core-decisions.md', [
    '포인트/적립금/예치금/쿠폰 모듈은 자기 환경설정에서 케이스별 회원 알림 사용 여부와 채널을 저장하고',
    '환전 환경설정 화면은 자산 모듈이 소유한 환전 출금/입금/수수료 알림 케이스를 함께 편집하고 각 자산 모듈 설정에 저장합니다.',
    '포인트 회원 알림 케이스의 기본값은 사용 안 함',
]);

if ($errors !== []) {
    fwrite(STDERR, "asset notification setting checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "asset notification setting checks completed.\n";
