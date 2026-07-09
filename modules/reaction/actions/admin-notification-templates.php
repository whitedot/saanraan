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
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/reactions/notification-templates', 'view');
if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/reactions/notification-templates', 'edit');
}

sr_notification_event_template_admin_handle($pdo, $site ?? null, [
    'module_key' => 'reaction',
    'permission_path' => '/admin/reactions/notification-templates',
    'return_path' => '/admin/reactions/notification-templates',
    'title' => '리액션 알림/메일 관리',
]);
