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
    $isBatchIntent = in_array($intent, ['batch_mark_read', 'batch_acknowledge', 'batch_process', 'batch_archive', 'batch_reopen'], true);
    $notificationId = $isBatchIntent ? 0 : sr_admin_post_positive_int('notification_id');
    $notificationIds = [];
    $postErrors = [];
    $postNotice = '';

    if ($isBatchIntent) {
        $rawSelectedIds = $_POST['selected_admin_notification_ids'] ?? [];
        $notificationIds = sr_admin_positive_int_list_from_input($rawSelectedIds, $hasInvalidSelectedId);
        if ($notificationIds === []) {
            $postErrors[] = '일괄 처리할 운영 알림을 선택하세요.';
        }
        if ($hasInvalidSelectedId) {
            $postErrors[] = '선택한 운영 알림 ID 값이 올바르지 않습니다.';
        }
        if (count($notificationIds) > 100) {
            $postErrors[] = '운영 알림 일괄 처리는 한 번에 100건 이하로 실행하세요.';
        }
    } elseif ($notificationId < 1) {
        $postErrors[] = '처리할 운영 알림을 선택하세요.';
    } else {
        $notificationIds = [$notificationId];
    }

    if ($postErrors === []) {
        $processedCount = 0;
        foreach ($notificationIds as $targetNotificationId) {
            if (in_array($intent, ['mark_read', 'batch_mark_read'], true)) {
                $processed = sr_notification_admin_mark_read($pdo, $targetNotificationId, (int) $account['id'], false);
            } elseif (in_array($intent, ['acknowledge', 'batch_acknowledge'], true)) {
                $processed = sr_notification_admin_mark_read($pdo, $targetNotificationId, (int) $account['id'], true);
            } elseif (in_array($intent, ['process', 'archive', 'reopen', 'batch_process', 'batch_archive', 'batch_reopen'], true)) {
                $targetStatus = match ($intent) {
                    'process', 'batch_process' => 'processed',
                    'archive', 'batch_archive' => 'archived',
                    default => 'open',
                };
                $processed = sr_notification_admin_set_status($pdo, $targetNotificationId, (int) $account['id'], $targetStatus);
            } else {
                $processed = false;
                $postErrors[] = '요청한 운영 알림 작업이 올바르지 않습니다.';
                break;
            }

            if ($processed) {
                $processedCount++;
            }
        }

        if ($postErrors === [] && $processedCount !== count($notificationIds)) {
            $postErrors[] = '일부 운영 알림을 찾을 수 없거나 권한이 없습니다.';
        }

        if ($postErrors === []) {
            $postNotice = match ($intent) {
                'mark_read' => '운영 알림을 읽음 처리했습니다.',
                'acknowledge' => '운영 알림을 확인했습니다.',
                'process' => '운영 알림을 처리됨으로 변경했습니다.',
                'archive' => '운영 알림을 보관했습니다.',
                'reopen' => '운영 알림을 다시 열었습니다.',
                'batch_mark_read' => '운영 알림 ' . number_format($processedCount) . '건을 읽음 처리했습니다.',
                'batch_acknowledge' => '운영 알림 ' . number_format($processedCount) . '건을 확인했습니다.',
                'batch_process' => '운영 알림 ' . number_format($processedCount) . '건을 처리됨으로 변경했습니다.',
                'batch_archive' => '운영 알림 ' . number_format($processedCount) . '건을 보관했습니다.',
                'batch_reopen' => '운영 알림 ' . number_format($processedCount) . '건을 다시 열었습니다.',
                default => '운영 알림을 변경했습니다.',
            };
        }
    }

    if ($postErrors === []) {
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'admin_notification.updated',
            'target_type' => 'admin_notification',
            'target_id' => $isBatchIntent ? 'batch' : (string) $notificationId,
            'result' => 'success',
            'message' => 'Admin notification updated.',
            'metadata' => [
                'intent' => $intent,
                'notification_ids' => $notificationIds,
                'processed_count' => count($notificationIds),
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
