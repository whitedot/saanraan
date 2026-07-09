<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/coupon/helpers.php';

$account = sr_member_require_login($pdo);
$permissionPath = '/admin/coupons/settings';
sr_admin_require_permission($pdo, (int) $account['id'], $permissionPath, 'view');

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_coupon_settings($pdo);
$notificationCases = sr_coupon_notification_cases();
$notificationChannelOptions = sr_coupon_notification_channel_options($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], $permissionPath, 'edit');

    $usageEnabled = sr_post_string('usage_enabled', 1) === '1';
    $couponZoneLabel = sr_coupon_normalize_zone_label(sr_post_string('coupon_zone_label', 40));
    $caseSettings = sr_coupon_notification_case_settings_from_value($settings['notification_cases'] ?? []);

    $definitionDisabledCase = $caseSettings['definition_disabled'] ?? ['enabled' => true, 'channels' => ['site']];
    $postedSettings = [
        'usage_enabled' => $usageEnabled,
        'coupon_zone_label' => $couponZoneLabel,
        'notification_cases' => $caseSettings,
        'disabled_reclaim_notifications_enabled' => !empty($definitionDisabledCase['enabled']),
        'disabled_reclaim_notification_event_key' => 'issue.definition_disabled',
        'disabled_reclaim_notification_channels' => sr_coupon_notification_channels_from_value($definitionDisabledCase['channels'] ?? ['site']),
    ];

    if ($errors === []) {
        try {
            sr_coupon_save_settings($pdo, $postedSettings);
            $settings = sr_coupon_settings($pdo);
            $notificationCases = sr_coupon_notification_cases();
            $notificationChannelOptions = sr_coupon_notification_channel_options($pdo);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'coupon.settings.updated',
                'target_type' => 'module',
                'target_id' => 'coupon',
                'result' => 'success',
                'message' => 'Coupon settings updated.',
                'metadata' => [
                    'usage_enabled' => !empty($settings['usage_enabled']),
                    'coupon_zone_label' => (string) ($settings['coupon_zone_label'] ?? '쿠폰존'),
                    'notification_cases' => (array) ($settings['notification_cases'] ?? []),
                    'disabled_reclaim_notifications_enabled' => !empty($settings['disabled_reclaim_notifications_enabled']),
                    'disabled_reclaim_notification_channels' => (array) ($settings['disabled_reclaim_notification_channels'] ?? ['site']),
                ],
            ]);
            sr_admin_flash_result(sr_admin_action_result([], '쿠폰·이용권 환경설정을 저장했습니다.'));
            sr_redirect('/admin/coupons/settings');
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'coupon_settings_save_failed');
            $errors[] = '쿠폰·이용권 환경설정 저장 중 오류가 발생했습니다.';
        }
    }

    sr_admin_flash_result(sr_admin_action_result($errors, ''));
    sr_redirect('/admin/coupons/settings');
}

$adminPageTitle = '쿠폰·이용권 환경설정';
$adminPageSubtitle = [
    '이메일과 외부 푸시는 알림 모듈 설정과 회원별 수신처가 준비된 경우에만 실제 발송됩니다.',
    '이메일 채널은 쿠폰 지급이나 사용 중지 처리에서 대량 발송될 수 있으므로 대상 범위와 발송 설정을 확인한 뒤 사용하세요.',
];

include SR_ROOT . '/modules/coupon/views/admin-settings.php';
