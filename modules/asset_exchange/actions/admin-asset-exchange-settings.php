<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/asset_exchange/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/asset-exchange/settings', 'view');

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_asset_exchange_settings($pdo);
$assetExchangeAssets = sr_asset_exchange_assets($pdo);
$assetExchangeAvailable = count($assetExchangeAssets) >= 2;
$notificationGroups = sr_asset_exchange_notification_groups($pdo);
$assetExchangeIdentityVerificationAvailable = sr_module_enabled($pdo, 'identity_verification')
    && is_file(SR_ROOT . '/modules/identity_verification/helpers.php');

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/asset-exchange/settings', 'edit');

    $postedSettings = $settings;
    $postedExchangeEnabled = sr_post_string('exchange_enabled', 1) === '1';
    $postedSettings['exchange_enabled'] = $assetExchangeAvailable && $postedExchangeEnabled ? '1' : '0';
    $postedSettings['identity_exchange_required'] = sr_post_string('identity_exchange_required', 1) === '1' ? '1' : '0';
    if (!$assetExchangeAvailable && $postedExchangeEnabled) {
        $errors[] = '환전을 사용하려면 환전 가능한 자산 모듈을 2개 이상 설치하고 활성화하세요.';
    }
    if (!$assetExchangeIdentityVerificationAvailable && (string) $postedSettings['identity_exchange_required'] === '1') {
        $errors[] = '환전 신청 본인확인을 사용하려면 본인확인 모듈을 먼저 설치하고 활성화하세요.';
        $postedSettings['identity_exchange_required'] = '0';
    }
    $settings = $postedSettings;

    $postedNotificationCases = $_POST['notification_cases'] ?? [];
    $postedNotificationCases = is_array($postedNotificationCases) ? $postedNotificationCases : [];
    $notificationSettingsByModule = [];
    foreach ($notificationGroups as $moduleKey => $notificationGroup) {
        $moduleKey = (string) $moduleKey;
        $postedModuleCases = isset($postedNotificationCases[$moduleKey]) && is_array($postedNotificationCases[$moduleKey])
            ? $postedNotificationCases[$moduleKey]
            : [];
        $allowedChannels = array_fill_keys((array) ($notificationGroup['channel_options'] ?? ['site']), true);
        $moduleCaseSettings = is_array($notificationGroup['all_case_settings'] ?? null) ? $notificationGroup['all_case_settings'] : [];
        $channelsFunction = (string) ($notificationGroup['channels_function'] ?? '');
        foreach ((array) ($notificationGroup['cases'] ?? []) as $caseKey => $case) {
            $caseKey = (string) $caseKey;
            $casePost = isset($postedModuleCases[$caseKey]) && is_array($postedModuleCases[$caseKey]) ? $postedModuleCases[$caseKey] : [];
            $postedChannels = $casePost['channels'] ?? [];
            $postedChannels = is_array($postedChannels) ? array_values(array_filter($postedChannels, 'is_string')) : [];
            $channels = [];
            foreach ($postedChannels as $channel) {
                if (isset($allowedChannels[$channel])) {
                    $channels[$channel] = $channel;
                }
            }

            $moduleCaseSettings[$caseKey] = [
                'event_key' => (string) ($case['event_key'] ?? ''),
                'enabled' => sr_truthy($casePost['enabled'] ?? false),
                'channels' => array_values($channels),
            ];
            if (!empty($moduleCaseSettings[$caseKey]['enabled']) && $moduleCaseSettings[$caseKey]['channels'] === []) {
                $errors[] = (string) ($notificationGroup['label'] ?? $moduleKey) . ' ' . (string) ($case['label'] ?? '알림') . ' 채널을 하나 이상 선택하세요.';
            }
            if ($channelsFunction !== '' && function_exists($channelsFunction)) {
                $moduleCaseSettings[$caseKey]['channels'] = $channelsFunction($moduleCaseSettings[$caseKey]['channels']);
            }
        }
        $notificationSettingsByModule[$moduleKey] = $moduleCaseSettings;
    }

    if ($errors === []) {
        try {
            $beforeSettings = sr_asset_exchange_settings($pdo);
            sr_asset_exchange_save_settings($pdo, $postedSettings);
            foreach ($notificationGroups as $moduleKey => $notificationGroup) {
                $settingsFunction = (string) ($notificationGroup['settings_function'] ?? '');
                $saveSettingsFunction = (string) ($notificationGroup['save_settings_function'] ?? '');
                if (!isset($notificationSettingsByModule[$moduleKey])
                    || $settingsFunction === ''
                    || $saveSettingsFunction === ''
                    || !function_exists($settingsFunction)
                    || !function_exists($saveSettingsFunction)
                ) {
                    continue;
                }
                $moduleSettings = $settingsFunction($pdo);
                $moduleSettings['notification_cases'] = $notificationSettingsByModule[$moduleKey];
                $saveSettingsFunction($pdo, $moduleSettings);
            }
            $settings = sr_asset_exchange_settings($pdo);
            $notificationGroups = sr_asset_exchange_notification_groups($pdo);

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'asset_exchange.settings.updated',
                'target_type' => 'module',
                'target_id' => 'asset_exchange',
                'result' => 'success',
                'message' => 'Asset exchange settings updated.',
                'metadata' => [
                    'before' => [
                        'exchange_enabled' => (string) ($beforeSettings['exchange_enabled'] ?? '1'),
                        'identity_exchange_required' => (string) ($beforeSettings['identity_exchange_required'] ?? '0'),
                    ],
                    'after' => [
                        'exchange_enabled' => (string) ($settings['exchange_enabled'] ?? '1'),
                        'identity_exchange_required' => (string) ($settings['identity_exchange_required'] ?? '0'),
                    ],
                    'notification_cases' => $notificationSettingsByModule,
                ],
            ]);

            sr_admin_flash_result(sr_admin_action_result([], '환전 환경설정을 저장했습니다.'));
            sr_redirect('/admin/asset-exchange/settings');
        } catch (Throwable $exception) {
            $message = $exception instanceof InvalidArgumentException ? $exception->getMessage() : '환전 환경설정 저장에 실패했습니다.';
            if (!$exception instanceof InvalidArgumentException) {
                sr_log_exception($exception, 'asset_exchange_settings_save_failed');
            }
            $errors[] = $message;
        }
    }

    sr_admin_flash_result(sr_admin_action_result($errors, ''));
    sr_redirect('/admin/asset-exchange/settings');
}

include SR_ROOT . '/modules/asset_exchange/views/admin-asset-exchange-settings.php';
