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
        'notification_cases[',
        'data-' . $dataPrefix . '-case-toggle',
        'data-' . $dataPrefix . '-channel',
        'data-' . $dataPrefix . '-required-label',
        '알림 채널을 하나 이상 선택하세요.',
    ]);
}

sr_asset_notification_settings_require_markers('docs/module-guide.md', [
    '포인트, 적립금, 예치금, 쿠폰 모듈은 각 환경설정 화면에서 거래/지급/사용/환불/상태 변경 같은 회원 알림 케이스별 사용 여부와 채널을 저장한다.',
]);
sr_asset_notification_settings_require_markers('docs/security-model.md', [
    '포인트/적립금/예치금/쿠폰 환경설정의 케이스별 알림 채널',
]);
sr_asset_notification_settings_require_markers('docs/core-decisions.md', [
    '포인트/적립금/예치금/쿠폰 모듈은 자기 환경설정에서 케이스별 회원 알림 사용 여부와 채널을 저장하고',
]);

if ($errors !== []) {
    fwrite(STDERR, "asset notification setting checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "asset notification setting checks completed.\n";
