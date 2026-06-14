<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/notification/helpers.php';

$account = sr_member_require_login($pdo);

$allowedAudiences = ['account', 'all'];
$allowedNotificationStatuses = sr_notification_admin_statuses();
$allowedChannels = sr_notification_allowed_channels();
$allowedCreateChannels = sr_notification_create_channels($pdo);
$allowedDeliveryChannels = array_values(array_intersect($allowedChannels, array_merge(['email'], sr_notification_admin_external_channel_keys())));
$adminExternalChannelSqlList = sr_notification_admin_external_channel_sql_list();
$allowedDeliveryStatuses = sr_notification_delivery_statuses();
$errors = [];
$notice = '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$notificationAdminPage = isset($notificationAdminPage) ? (string) $notificationAdminPage : 'list';
if (!in_array($notificationAdminPage, ['list', 'deliveries'], true)) {
    $notificationAdminPage = 'list';
}
$notificationPermissionPath = $notificationAdminPage === 'deliveries' ? '/admin/notification-deliveries' : '/admin/notifications';
sr_admin_require_permission($pdo, (int) $account['id'], $notificationPermissionPath, 'view');
$notificationCreateModalOpen = !empty($notificationCreateModalOpen);
if (sr_request_method() === 'GET' && $notificationCreateModalOpen) {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/notifications', 'edit');
}
$notificationCreateValues = [
    'audience' => (string) ($allowedAudiences[0] ?? 'account'),
    'account_identifier' => '',
    'title' => '',
    'body_text' => '',
    'body_format' => 'plain',
    'link_url' => '',
    'channels' => ['site'],
];
$notificationCreateFlash = isset($_SESSION['sr_admin_notification_create_state']) && is_array($_SESSION['sr_admin_notification_create_state'])
    ? $_SESSION['sr_admin_notification_create_state']
    : [];
unset($_SESSION['sr_admin_notification_create_state']);
if ($notificationCreateFlash !== []) {
    $notificationCreateModalOpen = !empty($notificationCreateFlash['open']);
    if (isset($notificationCreateFlash['values']) && is_array($notificationCreateFlash['values'])) {
        $notificationCreateValues = array_merge($notificationCreateValues, [
            'audience' => (string) ($notificationCreateFlash['values']['audience'] ?? $notificationCreateValues['audience']),
            'account_identifier' => (string) ($notificationCreateFlash['values']['account_identifier'] ?? ''),
            'title' => (string) ($notificationCreateFlash['values']['title'] ?? ''),
            'body_text' => (string) ($notificationCreateFlash['values']['body_text'] ?? ''),
            'body_format' => (string) ($notificationCreateFlash['values']['body_format'] ?? $notificationCreateValues['body_format']),
            'link_url' => (string) ($notificationCreateFlash['values']['link_url'] ?? ''),
            'channels' => isset($notificationCreateFlash['values']['channels']) && is_array($notificationCreateFlash['values']['channels'])
                ? array_values(array_filter($notificationCreateFlash['values']['channels'], 'is_string'))
                : $notificationCreateValues['channels'],
        ]);
    }
}
$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$notificationListFilters = [
    'audience' => sr_admin_get_allowed_single_array('audience', $allowedAudiences, 30),
    'status' => sr_admin_get_allowed_single_array('status', $allowedNotificationStatuses, 30),
    'field' => sr_get_string('field', 20),
    'q' => trim(sr_get_string('q', 120)),
];
$deliveryListFilters = [
    'delivery_id' => sr_admin_get_positive_int('delivery_id'),
    'delivery_channel' => sr_admin_get_allowed_single_array('delivery_channel', $allowedDeliveryChannels, 30),
    'delivery_status' => sr_admin_get_allowed_array('delivery_status', $allowedDeliveryStatuses, 30),
    'field' => sr_get_string('field', 20),
    'q' => trim(sr_get_string('q', 120)),
];
if (!in_array($notificationListFilters['field'], ['all', 'title', 'body', 'link', 'account', 'id'], true)) {
    $notificationListFilters['field'] = 'all';
}
if (!in_array($deliveryListFilters['field'], ['all', 'id', 'notification', 'title', 'recipient'], true)) {
    $deliveryListFilters['field'] = 'all';
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);
    if ($intent === 'delete_notification') {
        sr_admin_require_permission($pdo, (int) $account['id'], '/admin/notifications', 'delete');
    } elseif ($intent === 'delivery_status' || $intent === 'run_deliveries') {
        sr_admin_require_permission($pdo, (int) $account['id'], '/admin/notification-deliveries', 'edit');
    } else {
        sr_admin_require_permission($pdo, (int) $account['id'], '/admin/notifications', 'edit');
    }

    if ($intent === 'delete_notification') {
        $notificationId = (int) sr_post_string('notification_id', 20);

        if ($notificationId <= 0) {
            $errors[] = '삭제할 알림을 찾을 수 없습니다.';
        }

        if ($errors === []) {
            $stmt = $pdo->prepare('SELECT id FROM sr_notifications WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $notificationId]);
            if (!is_array($stmt->fetch())) {
                $errors[] = '삭제할 알림을 찾을 수 없습니다.';
            }
        }

        if ($errors === []) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('DELETE FROM sr_notification_deliveries WHERE notification_id = :notification_id AND channel NOT IN (' . $adminExternalChannelSqlList . ')');
                $stmt->execute(['notification_id' => $notificationId]);
                $deletedDeliveries = $stmt->rowCount();

                $stmt = $pdo->prepare('DELETE FROM sr_notification_reads WHERE notification_id = :notification_id');
                $stmt->execute(['notification_id' => $notificationId]);
                $deletedReads = $stmt->rowCount();

                $stmt = $pdo->prepare('DELETE FROM sr_notifications WHERE id = :id');
                $stmt->execute(['id' => $notificationId]);

                $pdo->commit();

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'notification.deleted',
                    'target_type' => 'notification',
                    'target_id' => (string) $notificationId,
                    'result' => 'success',
                    'message' => 'Notification deleted.',
                    'metadata' => [
                        'deleted_deliveries' => $deletedDeliveries,
                        'deleted_reads' => $deletedReads,
                    ],
                ]);

                $notice = '알림을 삭제했습니다.';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $errors[] = '알림 삭제 중 오류가 발생했습니다.';
            }
        }
    } elseif ($intent === 'run_deliveries') {
        if ($notificationAdminPage !== 'deliveries') {
            $errors[] = '허용되지 않은 발송 실행 요청입니다.';
        }

        if ($errors === []) {
            $settings = sr_notification_settings($pdo);
            $batchSize = (int) ($settings['delivery_manual_batch_size'] ?? 10);
            try {
                $deliveryRunResult = sr_notification_run_delivery_batch($pdo, is_array($site ?? null) ? $site : [], $batchSize, 'admin:' . (string) (int) $account['id']);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'notification.delivery.runner_ran',
                    'target_type' => 'notification_delivery',
                    'target_id' => '',
                    'result' => 'success',
                    'message' => 'Notification delivery runner ran manually.',
                    'metadata' => [
                        'batch_size' => $batchSize,
                        'claimed' => (int) ($deliveryRunResult['claimed'] ?? 0),
                        'sent' => (int) ($deliveryRunResult['sent'] ?? 0),
                        'failed' => (int) ($deliveryRunResult['failed'] ?? 0),
                        'dead' => (int) ($deliveryRunResult['dead'] ?? 0),
                    ],
                ]);
                $notice = '발송 실행을 완료했습니다. 처리 '
                    . number_format((int) ($deliveryRunResult['claimed'] ?? 0))
                    . '건, 성공 '
                    . number_format((int) ($deliveryRunResult['sent'] ?? 0))
                    . '건, 재시도 대기 '
                    . number_format((int) ($deliveryRunResult['failed'] ?? 0))
                    . '건, dead-letter '
                    . number_format((int) ($deliveryRunResult['dead'] ?? 0))
                    . '건입니다.';
            } catch (Throwable $exception) {
                sr_log_exception($exception, 'notification_manual_runner_failed');
                $errors[] = '발송 실행 중 오류가 발생했습니다.';
            }
        }
    } elseif ($intent === 'delivery_status') {
        $deliveryId = (int) sr_post_string('delivery_id', 20);
        $status = sr_post_string('status', 30);

        if ($deliveryId <= 0) {
            $errors[] = '발송 항목을 찾을 수 없습니다.';
        }
        if (!in_array($status, $allowedDeliveryStatuses, true)) {
            $errors[] = '발송 상태 값이 올바르지 않습니다.';
        }

        if ($errors === []) {
            $deliveryStatusResult = sr_notification_update_delivery_status($pdo, $deliveryId, $status, sr_now());
            if (empty($deliveryStatusResult['ok'])) {
                if (($deliveryStatusResult['error'] ?? '') === 'not_found') {
                    $errors[] = '발송 항목을 찾을 수 없습니다.';
                } elseif (($deliveryStatusResult['error'] ?? '') === 'changed') {
                    $errors[] = '발송 항목 상태가 바뀌었습니다. 목록을 새로고침한 뒤 다시 시도하세요.';
                } else {
                    $errors[] = '허용되지 않은 발송 상태 변경입니다.';
                }
            }
        }

        if ($errors === []) {
            $beforeStatus = (string) ($deliveryStatusResult['before_status'] ?? '');
            $operation = (string) ($deliveryStatusResult['operation'] ?? '');
            if ($beforeStatus === '' || $operation === '') {
                $errors[] = '허용되지 않은 발송 상태 변경입니다.';
            }
        }

        if ($errors === []) {
            if ($status === 'failed') {
                sr_notification_create_admin_notification($pdo, [
                    'title' => '이메일 발송 작업이 실패로 표시되었습니다.',
                    'body_text' => '발송 작업 #' . (string) $deliveryId . ' 상태를 확인해 주세요.',
                    'severity' => 'warning',
                    'source_module_key' => 'notification',
                    'event_key' => 'delivery.failed',
                    'target_type' => 'notification_delivery',
                    'target_id' => (string) $deliveryId,
                    'action_url' => '/admin/notification-deliveries?delivery_status=failed',
                    'permission_path' => '/admin/notification-deliveries',
                    'permission_action' => 'view',
                    'dedupe_key' => 'notification.delivery.failed.' . (string) $deliveryId,
                    'created_by_account_id' => (int) $account['id'],
                ]);
            }

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'notification.delivery.updated',
                'target_type' => 'notification_delivery',
                'target_id' => (string) $deliveryId,
                'result' => 'success',
                'message' => 'Notification delivery status updated.',
                'metadata' => [
                    'before_status' => $beforeStatus,
                    'status' => $status,
                    'operation' => $operation,
                ],
            ]);

            $notice = '발송 상태를 저장했습니다.';
        }
    } elseif ($intent === 'batch_status') {
        $operationKey = sr_post_string('operation_key', 80);
        $targetStatus = sr_post_string('target_status', 30);
        $rawSelectedIds = $_POST['selected_notification_ids'] ?? [];
        $selectedIds = [];
        if (is_array($rawSelectedIds)) {
            foreach ($rawSelectedIds as $rawSelectedId) {
                $selectedId = (int) $rawSelectedId;
                if ($selectedId > 0) {
                    $selectedIds[$selectedId] = $selectedId;
                }
            }
        }
        $selectedIds = array_values($selectedIds);

        if ($notificationAdminPage !== 'list') {
            $errors[] = '허용되지 않은 알림 일괄 작업입니다.';
        }
        if ($operationKey !== 'notification.set_status') {
            $errors[] = '허용되지 않은 알림 일괄 작업입니다.';
        }
        if (!in_array($targetStatus, $allowedNotificationStatuses, true)) {
            $errors[] = '변경할 알림 상태가 올바르지 않습니다.';
        }
        if ($selectedIds === []) {
            $errors[] = '상태를 변경할 알림을 선택하세요.';
        }
        if (count($selectedIds) > 100) {
            $errors[] = '알림 상태 일괄 변경은 한 번에 100건 이하로 실행하세요.';
        }

        $selectedNotifications = [];
        if ($errors === []) {
            $placeholders = [];
            $params = [];
            foreach ($selectedIds as $index => $selectedId) {
                $paramKey = 'notification_id_' . (string) $index;
                $placeholders[] = ':' . $paramKey;
                $params[$paramKey] = $selectedId;
            }
            $stmt = $pdo->prepare(
                'SELECT id, status
                 FROM sr_notifications
                 WHERE id IN (' . implode(', ', $placeholders) . ')'
            );
            foreach ($params as $paramKey => $selectedId) {
                $stmt->bindValue($paramKey, $selectedId, PDO::PARAM_INT);
            }
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $selectedNotifications[(int) $row['id']] = $row;
            }
            if (count($selectedNotifications) !== count($selectedIds)) {
                $errors[] = '선택한 알림 중 찾을 수 없는 항목이 있습니다. 목록을 새로고침한 뒤 다시 선택하세요.';
            }
        }

        if ($errors === [] && $selectedNotifications !== []) {
            $changedCount = 0;
            $skippedCount = 0;
            $batchFailureMessage = '';
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'UPDATE sr_notifications
                     SET status = :status,
                         updated_at = :updated_at
                     WHERE id = :id
                       AND status = :before_status'
                );
                foreach ($selectedIds as $selectedId) {
                    $beforeStatus = (string) ($selectedNotifications[$selectedId]['status'] ?? '');
                    if ($beforeStatus === $targetStatus) {
                        $skippedCount++;
                        continue;
                    }
                    $stmt->execute([
                        'status' => $targetStatus,
                        'updated_at' => sr_now(),
                        'id' => $selectedId,
                        'before_status' => $beforeStatus,
                    ]);
                    if ($stmt->rowCount() < 1) {
                        $batchFailureMessage = '선택한 알림 중 상태가 바뀐 항목이 있습니다. 목록을 새로고침한 뒤 다시 선택하세요.';
                        throw new RuntimeException($batchFailureMessage);
                    }
                    $changedCount++;
                }
                $pdo->commit();

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'notification.bulk_status_updated',
                    'target_type' => 'notification',
                    'target_id' => '',
                    'result' => 'success',
                    'message' => 'Notification statuses updated in bulk.',
                    'metadata' => [
                        'operation_key' => $operationKey,
                        'target_status' => $targetStatus,
                        'requested_count' => count($selectedIds),
                        'changed_count' => $changedCount,
                        'skipped_count' => $skippedCount,
                        'selected_ids' => $selectedIds,
                    ],
                ]);

                $notice = '알림 ' . number_format($changedCount) . '건의 상태를 ' . sr_admin_code_label($targetStatus, 'notification_status') . '(으)로 변경했습니다.';
                if ($skippedCount > 0) {
                    $notice .= ' 이미 같은 상태인 ' . number_format($skippedCount) . '건은 건너뛰었습니다.';
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($batchFailureMessage !== '') {
                    $errors[] = $batchFailureMessage;
                } else {
                    sr_log_exception($exception, 'notification_batch_status_failed');
                    $errors[] = '알림 상태 일괄 변경 중 오류가 발생했습니다.';
                }
            }
        }
    } else {
        $audience = sr_post_string('audience', 30);
        $accountIdentifier = sr_post_string('account_identifier', 80);
        if ($accountIdentifier === '') {
            $accountIdentifier = sr_post_string('account_id', 80);
        }
        $accountId = sr_admin_member_account_id_from_identifier($pdo, $runtimeConfig, $accountIdentifier);
        $title = sr_notification_clean_single_line(sr_post_string('title', 160), 160);
        $bodyFormat = 'plain';
        $rawBodyText = sr_post_string_without_truncation('body_text', 5000);
        $rawBodyText = is_string($rawBodyText) ? $rawBodyText : '';
        $bodyText = $bodyFormat === 'html'
            ? sr_sanitize_rich_text_html(sr_notification_clean_text($rawBodyText, 5000))
            : sr_notification_clean_text($rawBodyText, 5000);
        $rawLinkUrl = sr_post_string('link_url', 255);
        $linkUrl = sr_notification_clean_link_url($rawLinkUrl);
        $postedChannels = $_POST['channels'] ?? [];
        $channels = [];
        $notificationCreateValues = [
            'audience' => $audience,
            'account_identifier' => $accountIdentifier,
            'title' => $title,
            'body_text' => $bodyText,
            'body_format' => $bodyFormat,
            'link_url' => $rawLinkUrl,
            'channels' => is_array($postedChannels) ? array_values(array_filter($postedChannels, 'is_string')) : [],
        ];

        if (!in_array($audience, $allowedAudiences, true)) {
            $errors[] = '알림 대상을 선택하세요.';
        }
        if ($audience === 'account' && $accountId <= 0) {
            $errors[] = '회원 공개 해시를 입력하세요.';
        }
        if ($audience === 'account' && $accountId > 0) {
            $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $accountId]);
            if (!is_array($stmt->fetch())) {
                $errors[] = '대상 회원을 찾을 수 없습니다.';
            }
        }
        if ($title === '') {
            $errors[] = '제목을 입력하세요.';
        }
        if ($rawLinkUrl !== '' && $linkUrl === '') {
            $errors[] = '링크 URL은 /로 시작하는 내부 URL 또는 http/https URL이어야 합니다.';
        }
        if (!is_array($postedChannels)) {
            $errors[] = '발송 채널 값이 올바르지 않습니다.';
        } else {
            foreach ($postedChannels as $channel) {
                $channel = is_string($channel) ? $channel : '';
                if (in_array($channel, $allowedCreateChannels, true)) {
                    $channels[$channel] = $channel;
                }
            }
        }
        if ($channels === []) {
            $errors[] = '발송 채널을 하나 이상 선택하세요.';
        }
        if (in_array('email', $channels, true)) {
            if ($audience === 'account' && $accountId > 0 && sr_notification_account_email($pdo, $accountId) === '') {
                $errors[] = '대상 회원의 이메일 주소를 확인할 수 없습니다.';
            } elseif ($audience === 'all' && sr_notification_all_member_email_recipients($pdo) === []) {
                $errors[] = '이메일을 발송할 활성 회원이 없습니다.';
            }
        }

        if ($errors === []) {
            try {
                $notificationId = sr_notification_create($pdo, [
                    'audience' => $audience,
                    'account_id' => $audience === 'account' ? $accountId : null,
                    'title' => $title,
                    'body_text' => $bodyText,
                    'body_format' => $bodyFormat,
                    'link_url' => $linkUrl,
                    'channels' => array_values($channels),
                    'created_by_account_id' => (int) $account['id'],
                ]);

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'notification.created',
                    'target_type' => 'notification',
                    'target_id' => (string) $notificationId,
                    'result' => 'success',
                    'message' => 'Notification created.',
                    'metadata' => [
                        'audience' => $audience,
                        'channels' => array_values($channels),
                    ],
                ]);

                $notice = in_array('email', $channels, true)
                    ? '알림을 등록했고 이메일 발송 작업을 만들었습니다.'
                    : '알림을 등록했습니다.';
                $notificationCreateModalOpen = false;
                $notificationCreateValues = [
                    'audience' => (string) ($allowedAudiences[0] ?? 'account'),
                    'account_identifier' => '',
                    'title' => '',
                    'body_text' => '',
                    'body_format' => 'plain',
                    'link_url' => '',
                    'channels' => ['site'],
                ];
            } catch (Throwable $exception) {
                $errors[] = '알림 등록 중 오류가 발생했습니다.';
            }
        }

        if ($errors !== []) {
            $notificationCreateModalOpen = true;
        }
    }

    if ($notificationAdminPage === 'list' && $notificationCreateModalOpen && $errors !== []) {
        $_SESSION['sr_admin_notification_create_state'] = [
            'open' => true,
            'values' => $notificationCreateValues,
        ];
    }

    sr_admin_flash_result(sr_admin_action_result($errors, $notice));
    $redirectQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
    $redirectPath = $notificationAdminPage === 'deliveries' ? '/admin/notification-deliveries' : '/admin/notifications';
    sr_redirect($redirectPath . ($redirectQuery !== '' ? '?' . $redirectQuery : ''));
}

$notificationStatusCounts = sr_notification_admin_status_counts($pdo, $allowedNotificationStatuses);
$notificationSort = sr_admin_sort_from_request(sr_notification_admin_notification_sort_options(), sr_notification_admin_notification_default_sort());
$notificationPagination = sr_admin_pagination_from_total($pdo, $notificationAdminPage === 'list' ? sr_notification_admin_notification_count($pdo, $notificationListFilters) : 0);
$notifications = $notificationAdminPage === 'list'
    ? sr_notification_admin_notifications($pdo, (int) $notificationPagination['per_page'], $notificationListFilters, sr_admin_pagination_offset($notificationPagination), $notificationSort)
    : [];

$deliveryStatusCounts = ['total' => 0];
foreach ($allowedDeliveryStatuses as $status) {
    $deliveryStatusCounts[$status] = 0;
}
$stmt = $pdo->query("SELECT status, COUNT(*) AS count_value FROM sr_notification_deliveries WHERE channel <> 'site' GROUP BY status");
foreach ($stmt->fetchAll() as $row) {
    $status = (string) ($row['status'] ?? '');
    $count = (int) ($row['count_value'] ?? 0);
    if (array_key_exists($status, $deliveryStatusCounts)) {
        $deliveryStatusCounts[$status] = $count;
    }
    $deliveryStatusCounts['total'] += $count;
}

$deliveries = [];
$deliverySortOptions = [
    'notification' => ['columns' => ['n.title', 'd.id']],
    'channel' => ['columns' => ['d.channel', 'd.id']],
    'recipient' => ['columns' => ['d.recipient', 'd.id']],
    'status' => ['columns' => ['d.status', 'd.id']],
    'updated_at' => ['columns' => ['d.updated_at', 'd.id']],
];
$deliveryDefaultSort = sr_admin_sort_default('updated_at', 'desc');
$deliverySort = sr_admin_sort_from_request($deliverySortOptions, $deliveryDefaultSort);
$deliverySql = 'SELECT d.id, d.notification_id, d.channel, d.recipient, d.status, d.provider_message_id, d.error_message, d.attempted_at, d.updated_at,
                       d.attempt_count, d.next_attempt_at, d.locked_at,
                       CASE WHEN d.channel IN (' . $adminExternalChannelSqlList . ') THEN an.title ELSE n.title END AS notification_title
                FROM sr_notification_deliveries d
                LEFT JOIN sr_notifications n ON n.id = d.notification_id AND d.channel NOT IN (' . $adminExternalChannelSqlList . ')
                LEFT JOIN sr_admin_notifications an ON an.id = d.notification_id AND d.channel IN (' . $adminExternalChannelSqlList . ')';
$deliveryParams = [];
$deliveryWhere = ["d.channel <> 'site'"];
if ((int) ($deliveryListFilters['delivery_id'] ?? 0) > 0) {
    $deliveryWhere[] = 'd.id = :delivery_id';
    $deliveryParams['delivery_id'] = (int) $deliveryListFilters['delivery_id'];
}
if (($deliveryListFilters['delivery_channel'] ?? []) !== []) {
    [$condition, $conditionParams] = sr_admin_sql_in_condition('d.channel', 'delivery_channel', $deliveryListFilters['delivery_channel']);
    $deliveryWhere[] = $condition;
    $deliveryParams = array_merge($deliveryParams, $conditionParams);
}
if (($deliveryListFilters['delivery_status'] ?? []) !== []) {
    [$condition, $conditionParams] = sr_admin_sql_in_condition('d.status', 'delivery_status', $deliveryListFilters['delivery_status']);
    $deliveryWhere[] = $condition;
    $deliveryParams = array_merge($deliveryParams, $conditionParams);
}
if ($deliveryListFilters['q'] !== '') {
    $deliveryLike = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $deliveryListFilters['q']) . '%';
    if ($deliveryListFilters['field'] === 'id') {
        $deliveryWhere[] = 'CAST(d.id AS CHAR) LIKE :delivery_q';
        $deliveryParams['delivery_q'] = $deliveryLike;
    } elseif ($deliveryListFilters['field'] === 'notification') {
        $deliveryWhere[] = 'CAST(d.notification_id AS CHAR) LIKE :delivery_q';
        $deliveryParams['delivery_q'] = $deliveryLike;
    } elseif ($deliveryListFilters['field'] === 'title') {
        $deliveryWhere[] = '(CASE WHEN d.channel IN (' . $adminExternalChannelSqlList . ') THEN an.title ELSE n.title END) LIKE :delivery_q';
        $deliveryParams['delivery_q'] = $deliveryLike;
    } elseif ($deliveryListFilters['field'] === 'recipient') {
        $deliveryWhere[] = 'd.recipient LIKE :delivery_q';
        $deliveryParams['delivery_q'] = $deliveryLike;
    } else {
        $deliveryWhere[] = '(CAST(d.id AS CHAR) LIKE :delivery_q_id OR CAST(d.notification_id AS CHAR) LIKE :delivery_q_notification OR (CASE WHEN d.channel IN (' . $adminExternalChannelSqlList . ') THEN an.title ELSE n.title END) LIKE :delivery_q_title OR d.recipient LIKE :delivery_q_recipient OR d.provider_message_id LIKE :delivery_q_provider OR d.error_message LIKE :delivery_q_error)';
        $deliveryParams['delivery_q_id'] = $deliveryLike;
        $deliveryParams['delivery_q_notification'] = $deliveryLike;
        $deliveryParams['delivery_q_title'] = $deliveryLike;
        $deliveryParams['delivery_q_recipient'] = $deliveryLike;
        $deliveryParams['delivery_q_provider'] = $deliveryLike;
        $deliveryParams['delivery_q_error'] = $deliveryLike;
    }
}
if ($deliveryWhere !== []) {
    $deliverySql .= ' WHERE ' . implode(' AND ', $deliveryWhere);
}
$deliveryPagination = sr_admin_pagination_from_total($pdo, 0);
if ($notificationAdminPage === 'deliveries') {
    $deliveryCountSql = 'SELECT COUNT(*) AS count_value
                         FROM sr_notification_deliveries d
                         LEFT JOIN sr_notifications n ON n.id = d.notification_id AND d.channel NOT IN (' . $adminExternalChannelSqlList . ')
                         LEFT JOIN sr_admin_notifications an ON an.id = d.notification_id AND d.channel IN (' . $adminExternalChannelSqlList . ')'
        . ($deliveryWhere !== [] ? ' WHERE ' . implode(' AND ', $deliveryWhere) : '');
    $stmt = $pdo->prepare($deliveryCountSql);
    $stmt->execute($deliveryParams);
    $deliveryCountRow = $stmt->fetch();
    $deliveryPagination = sr_admin_pagination_from_total($pdo, is_array($deliveryCountRow) ? (int) ($deliveryCountRow['count_value'] ?? 0) : 0);
    $deliverySql .= sr_admin_sort_order_sql($deliverySortOptions, $deliverySort, $deliveryDefaultSort) . ' LIMIT :limit_value OFFSET :offset_value';
    $stmt = $pdo->prepare($deliverySql);
    $stmt->bindValue('limit_value', (int) $deliveryPagination['per_page'], PDO::PARAM_INT);
    $stmt->bindValue('offset_value', sr_admin_pagination_offset($deliveryPagination), PDO::PARAM_INT);
    foreach ($deliveryParams as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, PDO::PARAM_STR);
    }
    $stmt->execute();
    foreach ($stmt->fetchAll() as $row) {
        $deliveries[] = $row;
    }
}

include SR_ROOT . '/modules/notification/views/admin-notifications.php';
