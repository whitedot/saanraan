<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/notification/helpers/event-template-admin.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/quiz/notification-templates', 'view');
if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/quiz/notification-templates', 'edit');
}

sr_notification_event_template_admin_handle($pdo, $site ?? null, [
    'module_key' => 'quiz',
    'permission_path' => '/admin/quiz/notification-templates',
    'return_path' => '/admin/quiz/notification-templates',
    'title' => '퀴즈 알림 템플릿 관리',
]);
