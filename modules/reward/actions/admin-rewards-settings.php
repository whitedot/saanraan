<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/reward/helpers.php';

$account = sr_member_require_login($pdo);
$permissionPath = '/admin/rewards/settings';
sr_admin_require_permission($pdo, (int) $account['id'], $permissionPath, 'view');

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_reward_settings($pdo);
$memberGroups = sr_member_groups($pdo);
$notificationCases = sr_reward_notification_cases();
$notificationChannelOptions = sr_reward_notification_channel_options($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], $permissionPath, 'edit');

    $usageEnabled = sr_post_string('usage_enabled', 1) === '1';
    $displayName = sr_reward_clean_text(sr_post_string('display_name', 80), 40);
    $unitLabel = sr_reward_clean_text(sr_post_string('unit_label', 40), 20);
    $defaultExpirationDaysInput = sr_post_string('default_expiration_days', 20);
    $defaultExpirationDays = sr_reward_normalize_expiration_days($defaultExpirationDaysInput);
    $withdrawalRequestsEnabled = sr_post_string('withdrawal_requests_enabled', 1) === '1';
    $postedGroupKeys = $_POST['withdrawal_allowed_group_keys'] ?? [];
    $allowedGroupKeys = sr_reward_normalize_group_keys(is_array($postedGroupKeys) ? $postedGroupKeys : []);
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
    if ($displayName === '') {
        $errors[] = '적립금 표시명을 입력하세요.';
    }
    if ($unitLabel === '') {
        $unitLabel = '원';
    }
    if ($defaultExpirationDaysInput !== '' && (preg_match('/\A\d+\z/', $defaultExpirationDaysInput) !== 1 || (int) $defaultExpirationDaysInput > 3650)) {
        $errors[] = '적립금 유효기간은 0 이상의 정수로 입력하세요.';
    }
    $enabledGroupKeys = [];
    foreach ($memberGroups as $group) {
        if ((string) ($group['status'] ?? '') === 'enabled') {
            $enabledGroupKeys[(string) $group['group_key']] = true;
        }
    }
    foreach ($allowedGroupKeys as $groupKey) {
        if (!isset($enabledGroupKeys[$groupKey])) {
            $errors[] = '출금 가능 회원 그룹 선택값이 올바르지 않습니다.';
            break;
        }
    }

    if ($errors === []) {
        try {
            sr_reward_save_settings($pdo, [
                'usage_enabled' => $usageEnabled,
                'display_name' => $displayName,
                'unit_label' => $unitLabel,
                'default_expiration_days' => $defaultExpirationDays,
                'withdrawal_requests_enabled' => $withdrawalRequestsEnabled,
                'withdrawal_allowed_group_keys' => $allowedGroupKeys,
                'notification_cases' => $caseSettings,
            ]);
            $settings = sr_reward_settings($pdo);
            $notificationCases = sr_reward_notification_cases();
            $notificationChannelOptions = sr_reward_notification_channel_options($pdo);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'reward.settings.updated',
                'target_type' => 'module',
                'target_id' => 'reward',
                'result' => 'success',
                'message' => 'Reward settings updated.',
                'metadata' => [
                    'usage_enabled' => $usageEnabled,
                    'display_name' => (string) ($settings['display_name'] ?? $displayName),
                    'unit_label' => (string) ($settings['unit_label'] ?? $unitLabel),
                    'default_expiration_days' => (string) ($settings['default_expiration_days'] ?? $defaultExpirationDays),
                    'withdrawal_requests_enabled' => $withdrawalRequestsEnabled,
                    'withdrawal_allowed_group_keys' => $allowedGroupKeys,
                    'notification_cases' => (array) ($settings['notification_cases'] ?? []),
                ],
            ]);
            sr_admin_flash_result(sr_admin_action_result([], '적립금 환경설정을 저장했습니다.'));
            sr_redirect('/admin/rewards/settings');
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'reward_settings_save_failed');
            $errors[] = '적립금 환경설정 저장 중 오류가 발생했습니다.';
        }
    }

    sr_admin_flash_result(sr_admin_action_result($errors, ''));
    sr_redirect($permissionPath);
}

$adminPageTitle = sr_reward_display_name($pdo) . ' 환경설정';

include SR_ROOT . '/modules/reward/views/admin-settings.php';
