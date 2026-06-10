<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/notification/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/admin-notifications', 'view');

$allowedAdminNotificationStatuses = sr_notification_admin_operation_statuses();
$allowedAdminNotificationSeverities = sr_notification_admin_severities();
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $intent = sr_post_string('intent', 40);
    $notificationId = sr_admin_post_positive_int('notification_id');
    $postErrors = [];
    $postNotice = '';

    if ($notificationId < 1) {
        $postErrors[] = '처리할 운영 알림을 선택하세요.';
    }

    if ($postErrors === []) {
        if ($intent === 'mark_read') {
            if (sr_notification_admin_mark_read($pdo, $notificationId, (int) $account['id'], false)) {
                $postNotice = '운영 알림을 읽음 처리했습니다.';
            } else {
                $postErrors[] = '운영 알림을 찾을 수 없거나 권한이 없습니다.';
            }
        } elseif ($intent === 'acknowledge') {
            if (sr_notification_admin_mark_read($pdo, $notificationId, (int) $account['id'], true)) {
                $postNotice = '운영 알림을 확인했습니다.';
            } else {
                $postErrors[] = '운영 알림을 찾을 수 없거나 권한이 없습니다.';
            }
        } elseif (in_array($intent, ['process', 'archive', 'reopen'], true)) {
            $targetStatus = match ($intent) {
                'process' => 'processed',
                'archive' => 'archived',
                default => 'open',
            };
            if (sr_notification_admin_set_status($pdo, $notificationId, (int) $account['id'], $targetStatus)) {
                $postNotice = match ($targetStatus) {
                    'processed' => '운영 알림을 처리됨으로 변경했습니다.',
                    'archived' => '운영 알림을 보관했습니다.',
                    default => '운영 알림을 다시 열었습니다.',
                };
            } else {
                $postErrors[] = '운영 알림을 찾을 수 없거나 권한이 없습니다.';
            }
        } else {
            $postErrors[] = '요청한 운영 알림 작업이 올바르지 않습니다.';
        }
    }

    if ($postErrors === []) {
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'admin_notification.updated',
            'target_type' => 'admin_notification',
            'target_id' => (string) $notificationId,
            'result' => 'success',
            'message' => 'Admin notification updated.',
            'metadata' => [
                'intent' => $intent,
            ],
        ]);
    }

    sr_admin_redirect_with_result(
        sr_admin_action_result($postErrors, $postNotice),
        sr_admin_post_return_url('/admin/admin-notifications')
    );
}

$adminNotificationFilters = sr_notification_admin_filters($allowedAdminNotificationStatuses, $allowedAdminNotificationSeverities);
$adminNotificationStatusCounts = sr_notification_admin_operation_status_counts($pdo, (int) $account['id'], $allowedAdminNotificationStatuses);
$adminNotificationPagination = sr_admin_pagination_from_total($pdo, sr_notification_admin_count($pdo, (int) $account['id'], $adminNotificationFilters));
$adminNotifications = sr_notification_admin_rows(
    $pdo,
    (int) $account['id'],
    $adminNotificationFilters,
    (int) $adminNotificationPagination['per_page'],
    sr_admin_pagination_offset($adminNotificationPagination)
);

include SR_ROOT . '/modules/notification/views/admin-admin-notifications.php';
