<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/deposit/helpers.php';

$account = sr_member_require_login($pdo);
$permissionPath = '/admin/deposits/settings';
sr_admin_require_permission($pdo, (int) $account['id'], $permissionPath, 'view');

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_deposit_settings($pdo);
$memberGroups = sr_member_groups($pdo);
$notificationCases = sr_deposit_notification_cases();
$notificationChannelOptions = sr_deposit_notification_channel_options($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], $permissionPath, 'edit');

    $usageEnabled = sr_post_string('usage_enabled', 1) === '1';
    $displayName = sr_deposit_clean_text(sr_post_string('display_name', 80), 40);
    $unitLabel = sr_deposit_clean_text(sr_post_string('unit_label', 40), 20);
    $refundRequestsEnabled = sr_post_string('refund_requests_enabled', 1) === '1';
    $postedGroupKeys = $_POST['refund_allowed_group_keys'] ?? [];
    $allowedGroupKeys = sr_deposit_normalize_group_keys(is_array($postedGroupKeys) ? $postedGroupKeys : []);
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
    if ($refundRequestsEnabled && $allowedGroupKeys === []) {
        $errors[] = '환불 신청을 사용하려면 환불 신청 허용 대상을 선택하세요.';
    }
    if ($displayName === '') {
        $errors[] = '예치금 표시명을 입력하세요.';
    }
    if ($unitLabel === '') {
        $unitLabel = '원';
    }
    $enabledGroupKeys = [];
    foreach ($memberGroups as $group) {
        if ((string) ($group['status'] ?? '') === 'enabled') {
            $enabledGroupKeys[(string) $group['group_key']] = true;
        }
    }
    foreach ($allowedGroupKeys as $groupKey) {
        if ($groupKey === sr_deposit_refund_all_members_key()) {
            continue;
        }
        if (!isset($enabledGroupKeys[$groupKey])) {
            $errors[] = '환불 신청 가능 회원 그룹 선택값이 올바르지 않습니다.';
            break;
        }
    }

    if ($errors === []) {
        try {
            sr_deposit_save_settings($pdo, [
                'usage_enabled' => $usageEnabled,
                'display_name' => $displayName,
                'unit_label' => $unitLabel,
                'refund_requests_enabled' => $refundRequestsEnabled,
                'refund_allowed_group_keys' => $allowedGroupKeys,
                'notification_cases' => $caseSettings,
            ]);
            $settings = sr_deposit_settings($pdo);
            $notificationCases = sr_deposit_notification_cases();
            $notificationChannelOptions = sr_deposit_notification_channel_options($pdo);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'deposit.settings.updated',
                'target_type' => 'module',
                'target_id' => 'deposit',
                'result' => 'success',
                'message' => 'Deposit settings updated.',
                'metadata' => [
                    'usage_enabled' => $usageEnabled,
                    'display_name' => (string) ($settings['display_name'] ?? $displayName),
                    'unit_label' => (string) ($settings['unit_label'] ?? $unitLabel),
                    'refund_requests_enabled' => $refundRequestsEnabled,
                    'refund_allowed_group_keys' => $allowedGroupKeys,
                    'notification_cases' => (array) ($settings['notification_cases'] ?? []),
                ],
            ]);
            sr_admin_flash_result(sr_admin_action_result([], '예치금 환경설정을 저장했습니다.'));
            sr_redirect('/admin/deposits/settings');
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'deposit_settings_save_failed');
            $errors[] = '예치금 환경설정 저장 중 오류가 발생했습니다.';
        }
    }

    sr_admin_flash_result(sr_admin_action_result($errors, ''));
    sr_redirect($permissionPath);
}

$adminPageTitle = sr_deposit_display_name($pdo) . ' 환경설정';

include SR_ROOT . '/modules/deposit/views/admin-settings.php';
