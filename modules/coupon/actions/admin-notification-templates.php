<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/coupon/helpers.php';
require_once SR_ROOT . '/modules/notification/helpers/event-template-admin.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/coupons/notification-templates', 'view');
if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/coupons/notification-templates', 'edit');
}

sr_notification_event_template_admin_handle($pdo, $site ?? null, [
    'module_key' => 'coupon',
    'permission_path' => '/admin/coupons/notification-templates',
    'return_path' => '/admin/coupons/notification-templates',
    'title' => '쿠폰·이용권 알림 템플릿 관리',
    'subtitle' => '이메일 채널은 쿠폰 지급이나 사용 중지 처리에서 대량 발송될 수 있으므로 대상 범위와 발송 설정을 확인한 뒤 사용하세요.',
]);
