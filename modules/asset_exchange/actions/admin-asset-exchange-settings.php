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
$notificationGroups = sr_asset_exchange_notification_groups($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/asset-exchange/settings', 'edit');

    $postedSettings = [
        'policy_default_status' => sr_post_string('policy_default_status', 20),
        'policy_default_min_amount' => sr_post_string('policy_default_min_amount', 30),
        'policy_default_max_amount' => sr_post_string('policy_default_max_amount', 30),
        'policy_default_rounding_mode' => sr_post_string('policy_default_rounding_mode', 20),
        'policy_default_fee_trigger' => sr_post_string('policy_default_fee_trigger', 20),
        'policy_default_fee_basis' => sr_post_string('policy_default_fee_basis', 20),
        'policy_default_fee_type' => sr_post_string('policy_default_fee_type', 20),
        'policy_default_fee_rate_numerator' => sr_post_string('policy_default_fee_rate_numerator', 30),
        'policy_default_fee_fixed_amount' => sr_post_string('policy_default_fee_fixed_amount', 30),
        'policy_default_fee_min_amount' => sr_post_string('policy_default_fee_min_amount', 30),
        'policy_default_fee_max_amount' => sr_post_string('policy_default_fee_max_amount', 30),
        'policy_default_sort_order' => sr_post_string('policy_default_sort_order', 30),
    ];
    $settings = array_merge($settings, $postedSettings);

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
                    'before' => $beforeSettings,
                    'after' => $settings,
                    'policy_update_applied' => true,
                    'notification_cases' => $notificationSettingsByModule,
                ],
            ]);

            sr_admin_flash_result(sr_admin_action_result([], '환전 공통 조건을 저장하고 파생 정책에 반영했습니다.'));
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
