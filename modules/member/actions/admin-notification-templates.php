<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$notificationTemplateAdminHelper = SR_ROOT . '/modules/notification/helpers/event-template-admin.php';
if (!sr_module_enabled($pdo, 'notification') || !is_file($notificationTemplateAdminHelper)) {
    sr_render_error(404, '알림 모듈이 설치되어 있지 않아 알림/메일 관리 화면을 사용할 수 없습니다.');
    return;
}
require_once $notificationTemplateAdminHelper;

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/member-notification-templates', 'view');
if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/member-notification-templates', 'edit');
}

sr_member_ensure_notification_templates($pdo);
sr_notification_event_template_admin_handle($pdo, $site ?? null, [
    'module_key' => 'member',
    'permission_path' => '/admin/member-notification-templates',
    'return_path' => '/admin/member-notification-templates',
    'title' => '회원 알림/메일 관리',
    'subtitle' => '회원 계정 이벤트 알림과 이메일 인증, 비밀번호 재설정 요청, 로그인 2차 인증 메일 문구를 관리합니다.',
    'include_delivery_templates' => true,
    'delivery_template_keys' => [
        'member.email_verification',
        'member.password_reset',
        'member.login_mfa_email_code',
    ],
]);
