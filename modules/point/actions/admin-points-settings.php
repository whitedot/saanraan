<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/point/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/points/settings', 'view');

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_point_settings($pdo);
$notificationCases = sr_point_notification_cases();
$notificationChannelOptions = sr_point_notification_channel_options($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/points/settings', 'edit');

    $intent = sr_post_string('intent', 40);
    if ($intent === '' || $intent === 'save_settings') {
        $defaultExpirationDaysInput = sr_post_string('default_expiration_days', 20);
        $postedSettings = [
            'usage_enabled' => sr_post_string('usage_enabled', 1) === '1',
            'display_name' => sr_point_clean_text(sr_post_string('display_name', 80), 40),
            'unit_label' => sr_point_clean_text(sr_post_string('unit_label', 40), 20),
            'default_expiration_days' => $defaultExpirationDaysInput,
        ];
        $postedCases = $_POST['notification_cases'] ?? [];
        $postedCases = is_array($postedCases) ? $postedCases : [];
        $allowedChannels = array_fill_keys($notificationChannelOptions, true);
        $caseSettings = [];
        foreach ($notificationCases as $caseKey => $case) {
            $caseKey = (string) $caseKey;
            $casePost = isset($postedCases[$caseKey]) && is_array($postedCases[$caseKey]) ? $postedCases[$caseKey] : [];
            $postedChannels = $casePost['channels'] ?? [];
            $postedChannels = is_array($postedChannels) ? array_values(array_filter($postedChannels, 'is_string')) : [];
            $channels = [];
            foreach ($postedChannels as $channel) {
                if (isset($allowedChannels[$channel])) {
                    $channels[$channel] = $channel;
                }
            }

            $caseSettings[$caseKey] = [
                'event_key' => (string) ($case['event_key'] ?? ''),
                'enabled' => sr_truthy($casePost['enabled'] ?? false),
                'channels' => array_values($channels),
            ];
            if (!empty($caseSettings[$caseKey]['enabled']) && $caseSettings[$caseKey]['channels'] === []) {
                $errors[] = (string) ($case['label'] ?? '알림') . ' 채널을 하나 이상 선택하세요.';
            }
        }
        $postedSettings['notification_cases'] = $caseSettings;
        $postedSettings['default_expiration_days'] = (string) sr_point_normalize_expiration_days($postedSettings['default_expiration_days']);
        $settings = array_merge($settings, $postedSettings);

        if ($postedSettings['display_name'] === '') {
            $errors[] = sr_t('point::action.admin.settings.display_name_required');
        }
        if ($defaultExpirationDaysInput !== '' && (preg_match('/\A\d+\z/', $defaultExpirationDaysInput) !== 1 || (int) $defaultExpirationDaysInput > 3650)) {
            $errors[] = sr_t('point::action.admin.settings.expiration_days_invalid');
        }

        if ($errors === []) {
            try {
                sr_point_save_settings($pdo, $postedSettings);
                $settings = sr_point_settings($pdo);
                $notificationCases = sr_point_notification_cases();
                $notificationChannelOptions = sr_point_notification_channel_options($pdo);

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'point.settings.updated',
                    'target_type' => 'module',
                    'target_id' => 'point',
                    'result' => 'success',
                    'message' => 'Point settings updated.',
                    'metadata' => [
                        'usage_enabled' => !empty($settings['usage_enabled']),
                        'display_name' => (string) $settings['display_name'],
                        'unit_label' => (string) $settings['unit_label'],
                        'default_expiration_days' => (string) $settings['default_expiration_days'],
                        'notification_cases' => (array) ($settings['notification_cases'] ?? []),
                    ],
                ]);

                sr_admin_flash_result(sr_admin_action_result([], sr_t('point::action.admin.settings.saved')));
                sr_redirect('/admin/points/settings');
            } catch (Throwable $exception) {
                if ($exception->getMessage() === 'Point display name is required.') {
                    $errors[] = sr_t('point::action.admin.settings.display_name_required');
                } else {
                    sr_log_exception($exception, 'point_settings_save_failed');
                    $errors[] = sr_t('point::action.admin.settings.save_failed');
                }
            }
        }
    }

    sr_admin_flash_result(sr_admin_action_result($errors, ''));
    sr_redirect('/admin/points/settings');
}

include SR_ROOT . '/modules/point/views/admin-settings.php';
