<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/notification/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$allowedAudiences = ['account', 'all'];
$allowedNotificationStatuses = sr_notification_admin_statuses();
$allowedChannels = sr_notification_allowed_channels();
$allowedCreateChannels = array_values(array_intersect($allowedChannels, ['site', 'email']));
$allowedDeliveryStatuses = ['queued', 'ready', 'sent', 'failed', 'canceled'];
$errors = [];
$notice = '';
$notificationAdminPage = isset($notificationAdminPage) ? (string) $notificationAdminPage : 'list';
if (!in_array($notificationAdminPage, ['list', 'deliveries'], true)) {
    $notificationAdminPage = 'list';
}
$notificationCreateModalOpen = !empty($notificationCreateModalOpen);
$notificationCreateValues = [
    'audience' => (string) ($allowedAudiences[0] ?? 'account'),
    'account_identifier' => '',
    'title' => '',
    'body_text' => '',
    'link_url' => '',
    'recipient' => '',
    'channels' => ['site'],
];
$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$notificationListFilters = [
    'audience' => sr_get_string('audience', 30),
    'status' => sr_get_string('status', 30),
    'field' => sr_get_string('field', 20),
    'q' => trim(sr_get_string('q', 120)),
];
$deliveryListFilters = [
    'delivery_channel' => sr_get_string('delivery_channel', 30),
    'delivery_status' => sr_get_string('delivery_status', 30),
    'field' => sr_get_string('field', 20),
    'q' => trim(sr_get_string('q', 120)),
];
if ($notificationListFilters['audience'] !== '' && !in_array($notificationListFilters['audience'], $allowedAudiences, true)) {
    $notificationListFilters['audience'] = '';
}
if ($notificationListFilters['status'] !== '' && !in_array($notificationListFilters['status'], $allowedNotificationStatuses, true)) {
    $notificationListFilters['status'] = '';
}
if (!in_array($notificationListFilters['field'], ['all', 'title', 'body', 'link', 'account', 'id'], true)) {
    $notificationListFilters['field'] = 'all';
}
if ($deliveryListFilters['delivery_channel'] !== '' && !in_array($deliveryListFilters['delivery_channel'], $allowedChannels, true)) {
    $deliveryListFilters['delivery_channel'] = '';
}
if ($deliveryListFilters['delivery_status'] !== '' && !in_array($deliveryListFilters['delivery_status'], $allowedDeliveryStatuses, true)) {
    $deliveryListFilters['delivery_status'] = '';
}
if (!in_array($deliveryListFilters['field'], ['all', 'id', 'notification', 'title', 'recipient'], true)) {
    $deliveryListFilters['field'] = 'all';
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);

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

                $stmt = $pdo->prepare('DELETE FROM sr_notification_deliveries WHERE notification_id = :notification_id');
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
            $stmt = $pdo->prepare('SELECT id FROM sr_notification_deliveries WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $deliveryId]);
            if (!is_array($stmt->fetch())) {
                $errors[] = '발송 항목을 찾을 수 없습니다.';
            }
        }

        if ($errors === []) {
            $stmt = $pdo->prepare(
                'UPDATE sr_notification_deliveries
                 SET status = :status,
                     attempted_at = :attempted_at,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $now = sr_now();
            $stmt->execute([
                'status' => $status,
                'attempted_at' => in_array($status, ['sent', 'failed'], true) ? $now : null,
                'updated_at' => $now,
                'id' => $deliveryId,
            ]);

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'notification.delivery.updated',
                'target_type' => 'notification_delivery',
                'target_id' => (string) $deliveryId,
                'result' => 'success',
                'message' => 'Notification delivery status updated.',
                'metadata' => ['status' => $status],
            ]);

            $notice = '발송 상태를 저장했습니다.';
        }
    } else {
        $audience = sr_post_string('audience', 30);
        $accountIdentifier = sr_post_string('account_identifier', 80);
        if ($accountIdentifier === '') {
            $accountIdentifier = sr_post_string('account_id', 80);
        }
        $accountId = sr_admin_member_account_id_from_identifier($pdo, $runtimeConfig, $accountIdentifier);
        $title = sr_notification_clean_single_line(sr_post_string('title', 160), 160);
        $bodyText = sr_notification_clean_text(sr_post_string('body_text', 5000), 5000);
        $rawLinkUrl = sr_post_string('link_url', 255);
        $linkUrl = sr_notification_clean_link_url($rawLinkUrl);
        $recipient = sr_notification_clean_single_line(sr_post_string('recipient', 255), 255);
        $postedChannels = $_POST['channels'] ?? [];
        $channels = [];
        $notificationCreateValues = [
            'audience' => $audience,
            'account_identifier' => $accountIdentifier,
            'title' => $title,
            'body_text' => $bodyText,
            'link_url' => $rawLinkUrl,
            'recipient' => $recipient,
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
        if (sr_notification_external_channels(array_values($channels)) !== [] && $recipient === '') {
            $errors[] = '이메일 채널은 외부 수신자를 입력해야 합니다.';
        }

        if ($errors === []) {
            try {
                $notificationId = sr_notification_create($pdo, [
                    'audience' => $audience,
                    'account_id' => $audience === 'account' ? $accountId : null,
                    'title' => $title,
                    'body_text' => $bodyText,
                    'link_url' => $linkUrl,
                    'channels' => array_values($channels),
                    'recipient' => $recipient,
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

                $notice = '알림을 등록했습니다. 이메일은 발송 대기열에 쌓입니다.';
                $notificationCreateModalOpen = false;
                $notificationCreateValues = [
                    'audience' => (string) ($allowedAudiences[0] ?? 'account'),
                    'account_identifier' => '',
                    'title' => '',
                    'body_text' => '',
                    'link_url' => '',
                    'recipient' => '',
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
}

$notificationStatusCounts = sr_notification_admin_status_counts($pdo, $allowedNotificationStatuses);
$notifications = sr_notification_admin_notifications($pdo, 100, $notificationListFilters);

$deliveryStatusCounts = ['total' => 0];
foreach ($allowedDeliveryStatuses as $status) {
    $deliveryStatusCounts[$status] = 0;
}
$stmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_notification_deliveries GROUP BY status');
foreach ($stmt->fetchAll() as $row) {
    $status = (string) ($row['status'] ?? '');
    $count = (int) ($row['count_value'] ?? 0);
    if (array_key_exists($status, $deliveryStatusCounts)) {
        $deliveryStatusCounts[$status] = $count;
    }
    $deliveryStatusCounts['total'] += $count;
}

$deliveries = [];
$deliverySql = 'SELECT d.id, d.notification_id, d.channel, d.recipient, d.status, d.provider_message_id, d.error_message, d.attempted_at, d.updated_at,
                       n.title AS notification_title
                FROM sr_notification_deliveries d
                LEFT JOIN sr_notifications n ON n.id = d.notification_id';
$deliveryParams = [];
$deliveryWhere = [];
if ($deliveryListFilters['delivery_channel'] !== '') {
    $deliveryWhere[] = 'd.channel = :delivery_channel';
    $deliveryParams['delivery_channel'] = $deliveryListFilters['delivery_channel'];
}
if ($deliveryListFilters['delivery_status'] !== '') {
    $deliveryWhere[] = 'd.status = :delivery_status';
    $deliveryParams['delivery_status'] = $deliveryListFilters['delivery_status'];
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
        $deliveryWhere[] = 'n.title LIKE :delivery_q';
        $deliveryParams['delivery_q'] = $deliveryLike;
    } elseif ($deliveryListFilters['field'] === 'recipient') {
        $deliveryWhere[] = 'd.recipient LIKE :delivery_q';
        $deliveryParams['delivery_q'] = $deliveryLike;
    } else {
        $deliveryWhere[] = '(CAST(d.id AS CHAR) LIKE :delivery_q_id OR CAST(d.notification_id AS CHAR) LIKE :delivery_q_notification OR n.title LIKE :delivery_q_title OR d.recipient LIKE :delivery_q_recipient OR d.provider_message_id LIKE :delivery_q_provider OR d.error_message LIKE :delivery_q_error)';
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
$deliverySql .= ' ORDER BY d.id DESC LIMIT 100';
$stmt = $pdo->prepare($deliverySql);
$stmt->execute($deliveryParams);
foreach ($stmt->fetchAll() as $row) {
    $deliveries[] = $row;
}

include SR_ROOT . '/modules/notification/views/admin-notifications.php';
