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
        'template_path' => '/admin/points/notification-templates',
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
        'template_path' => '/admin/rewards/notification-templates',
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
        'template_path' => '/admin/deposits/notification-templates',
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
    $templateActionPath = 'modules/' . $moduleKey . '/actions/admin-notification-templates.php';
    $pathsPath = 'modules/' . $moduleKey . '/paths.php';
    $menuPath = 'modules/' . $moduleKey . '/admin-menu.php';
    $prefix = 'sr_' . $moduleKey;

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
        'notification_cases',
        $prefix . '_notification_case_settings_from_value($settings[\'notification_cases\'] ?? [])',
        '\'notification_cases\' => (array) ($settings[\'notification_cases\'] ?? [])',
    ]);

    $viewContents = sr_asset_notification_settings_file($viewPath);
    if (str_contains($viewContents, 'notification_cases[')) {
        sr_asset_notification_settings_error($viewPath . ' must not edit notification_cases directly after module notification template pages own those settings.');
    }

    sr_asset_notification_settings_require_markers($templateActionPath, [
        "require_once SR_ROOT . '/modules/" . $moduleKey . "/helpers.php';",
        "sr_notification_event_template_admin_handle(\$pdo, \$site ?? null, [",
        "'module_key' => '" . $moduleKey . "'",
        "'return_path' => '" . (string) ($module['template_path'] ?? '') . "'",
    ]);
    sr_asset_notification_settings_require_markers($pathsPath, [
        "'GET " . (string) ($module['template_path'] ?? '') . "'",
        "'POST " . (string) ($module['template_path'] ?? '') . "'",
        "'actions/admin-notification-templates.php'",
    ]);
    sr_asset_notification_settings_require_markers($menuPath, [
        '알림/메일',
        (string) ($module['template_path'] ?? ''),
    ]);
}

sr_asset_notification_settings_require_markers('modules/coupon/helpers.php', [
    'function sr_coupon_notification_setting_for_event(array $settings, string $eventKey): ?array',
    "'default_enabled' => true",
]);
sr_asset_notification_settings_require_markers('modules/coupon/actions/admin-coupon-settings.php', [
    '\'notification_cases\' => $caseSettings',
    'sr_coupon_notification_case_settings_from_value($settings[\'notification_cases\'] ?? [])',
    '\'notification_cases\' => (array) ($settings[\'notification_cases\'] ?? [])',
]);
$couponSettingsView = sr_asset_notification_settings_file('modules/coupon/views/admin-settings.php');
if (str_contains($couponSettingsView, 'notification_cases[')) {
    sr_asset_notification_settings_error('modules/coupon/views/admin-settings.php must not edit notification_cases directly after coupon notification template page owns those settings.');
}
sr_asset_notification_settings_require_markers('modules/coupon/actions/admin-notification-templates.php', [
    "require_once SR_ROOT . '/modules/coupon/helpers.php';",
    "sr_notification_event_template_admin_handle(\$pdo, \$site ?? null, [",
    "'module_key' => 'coupon'",
    "'return_path' => '/admin/coupons/notification-templates'",
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
    '$notificationGroups = [];',
]);
$assetExchangeView = sr_asset_notification_settings_file('modules/asset_exchange/views/admin-asset-exchange.php');
if (str_contains($assetExchangeView, 'notification_cases[') || str_contains($assetExchangeView, 'data-asset-exchange-notification-')) {
    sr_asset_notification_settings_error('modules/asset_exchange/views/admin-asset-exchange.php must not edit asset notification cases after owner module template pages own those settings.');
}

sr_asset_notification_settings_require_markers('docs/module-guide.md', [
    '포인트, 적립금, 예치금, 쿠폰 모듈은 각 알림/메일 관리 화면에서 거래/지급/사용/환불/상태 변경 같은 회원 알림 케이스별 제목, 본문, 사용 여부와 채널을 저장한다.',
    '환전 출금/입금/수수료 알림도 포인트/적립금/예치금 모듈의 알림/메일 관리 화면에서 편집한다.',
    '포인트 모듈의 회원 알림 케이스 기본값은 사용 안 함이고, 적립금/예치금/쿠폰 모듈은 기존 동작 보존을 위해 케이스 기본값을 사용으로 둔다.',
]);
sr_asset_notification_settings_require_markers('docs/security-model.md', [
    '포인트/적립금/예치금/쿠폰 알림/메일 관리 화면의 케이스별 알림 채널',
    '환전 알림도 각 자산 모듈의 케이스별 채널 설정으로 저장한다.',
]);
sr_asset_notification_settings_require_markers('docs/core-decisions.md', [
    '포인트/적립금/예치금/쿠폰 모듈은 자기 알림/메일 관리 화면에서 케이스별 회원 알림 제목, 본문, 사용 여부와 채널을 저장하고',
    '환전 출금/입금/수수료 알림 케이스도 자산 모듈의 알림/메일 관리 화면에서 편집합니다.',
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
