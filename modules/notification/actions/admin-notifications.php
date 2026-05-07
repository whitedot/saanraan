<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/admin/helpers.php';
require_once TOY_ROOT . '/modules/notification/helpers.php';

$account = toy_member_require_login($pdo);
toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$allowedAudiences = ['account', 'all'];
$allowedChannels = toy_notification_allowed_channels();
$allowedDeliveryStatuses = ['queued', 'ready', 'sent', 'failed', 'canceled'];
$errors = [];
$notice = '';
$filters = [
    'audience' => toy_get_string('audience', 30),
    'delivery_channel' => toy_get_string('delivery_channel', 30),
    'delivery_status' => toy_get_string('delivery_status', 30),
];
if ($filters['audience'] !== '' && !in_array($filters['audience'], $allowedAudiences, true)) {
    $filters['audience'] = '';
}
if ($filters['delivery_channel'] !== '' && !in_array($filters['delivery_channel'], $allowedChannels, true)) {
    $filters['delivery_channel'] = '';
}
if ($filters['delivery_status'] !== '' && !in_array($filters['delivery_status'], $allowedDeliveryStatuses, true)) {
    $filters['delivery_status'] = '';
}

if (toy_request_method() === 'POST') {
    toy_require_csrf();

    $intent = toy_post_string('intent', 40);

    if ($intent === 'delete_notification') {
        $notificationId = (int) toy_post_string('notification_id', 20);

        if ($notificationId <= 0) {
            $errors[] = '삭제할 알림을 찾을 수 없습니다.';
        }

        if ($errors === []) {
            $stmt = $pdo->prepare('SELECT id FROM toy_notifications WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $notificationId]);
            if (!is_array($stmt->fetch())) {
                $errors[] = '삭제할 알림을 찾을 수 없습니다.';
            }
        }

        if ($errors === []) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('DELETE FROM toy_notification_deliveries WHERE notification_id = :notification_id');
                $stmt->execute(['notification_id' => $notificationId]);
                $deletedDeliveries = $stmt->rowCount();

                $stmt = $pdo->prepare('DELETE FROM toy_notification_reads WHERE notification_id = :notification_id');
                $stmt->execute(['notification_id' => $notificationId]);
                $deletedReads = $stmt->rowCount();

                $stmt = $pdo->prepare('DELETE FROM toy_notifications WHERE id = :id');
                $stmt->execute(['id' => $notificationId]);

                $pdo->commit();

                toy_audit_log($pdo, [
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
        $deliveryId = (int) toy_post_string('delivery_id', 20);
        $status = toy_post_string('status', 30);

        if ($deliveryId <= 0) {
            $errors[] = '발송 항목을 찾을 수 없습니다.';
        }
        if (!in_array($status, $allowedDeliveryStatuses, true)) {
            $errors[] = '발송 상태 값이 올바르지 않습니다.';
        }

        if ($errors === []) {
            $stmt = $pdo->prepare('SELECT id FROM toy_notification_deliveries WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $deliveryId]);
            if (!is_array($stmt->fetch())) {
                $errors[] = '발송 항목을 찾을 수 없습니다.';
            }
        }

        if ($errors === []) {
            $stmt = $pdo->prepare(
                'UPDATE toy_notification_deliveries
                 SET status = :status,
                     attempted_at = :attempted_at,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $now = toy_now();
            $stmt->execute([
                'status' => $status,
                'attempted_at' => in_array($status, ['sent', 'failed'], true) ? $now : null,
                'updated_at' => $now,
                'id' => $deliveryId,
            ]);

            toy_audit_log($pdo, [
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
        $audience = toy_post_string('audience', 30);
        $accountId = (int) toy_post_string('account_id', 20);
        $title = toy_notification_clean_single_line(toy_post_string('title', 160), 160);
        $bodyText = toy_notification_clean_text(toy_post_string('body_text', 5000), 5000);
        $rawLinkUrl = toy_post_string('link_url', 255);
        $linkUrl = toy_notification_clean_link_url($rawLinkUrl);
        $recipient = toy_notification_clean_single_line(toy_post_string('recipient', 255), 255);
        $postedChannels = $_POST['channels'] ?? [];
        $channels = [];

        if (!in_array($audience, $allowedAudiences, true)) {
            $errors[] = '알림 대상을 선택하세요.';
        }
        if ($audience === 'account' && $accountId <= 0) {
            $errors[] = '회원 ID를 입력하세요.';
        }
        if ($audience === 'account' && $accountId > 0) {
            $stmt = $pdo->prepare('SELECT id FROM toy_member_accounts WHERE id = :id LIMIT 1');
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
                if (in_array($channel, $allowedChannels, true)) {
                    $channels[$channel] = $channel;
                }
            }
        }
        if ($channels === []) {
            $errors[] = '발송 채널을 하나 이상 선택하세요.';
        }
        if (toy_notification_external_channels(array_values($channels)) !== [] && $recipient === '') {
            $errors[] = '이메일, SMS, 알림톡 채널은 외부 수신자를 입력해야 합니다.';
        }

        if ($errors === []) {
            try {
                $notificationId = toy_notification_create($pdo, [
                    'audience' => $audience,
                    'account_id' => $audience === 'account' ? $accountId : null,
                    'title' => $title,
                    'body_text' => $bodyText,
                    'link_url' => $linkUrl,
                    'channels' => array_values($channels),
                    'recipient' => $recipient,
                    'created_by_account_id' => (int) $account['id'],
                ]);

                toy_audit_log($pdo, [
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

                $notice = '알림을 등록했습니다. 이메일/SMS/알림톡은 발송 대기열에 쌓입니다.';
            } catch (Throwable $exception) {
                $errors[] = '알림 등록 중 오류가 발생했습니다.';
            }
        }
    }
}

$notifications = [];
$notificationSql = 'SELECT id, audience, status, created_at
                    FROM toy_notifications';
$notificationParams = [];
if ($filters['audience'] !== '') {
    $notificationSql .= ' WHERE audience = :audience';
    $notificationParams['audience'] = $filters['audience'];
}
$notificationSql .= ' ORDER BY id DESC LIMIT 100';
$stmt = $pdo->prepare($notificationSql);
$stmt->execute($notificationParams);
foreach ($stmt->fetchAll() as $row) {
    $notifications[] = $row;
}

$deliveries = [];
$deliverySql = 'SELECT d.id, d.notification_id, d.channel, d.status, d.updated_at
                FROM toy_notification_deliveries d';
$deliveryParams = [];
$deliveryWhere = [];
if ($filters['delivery_channel'] !== '') {
    $deliveryWhere[] = 'd.channel = :delivery_channel';
    $deliveryParams['delivery_channel'] = $filters['delivery_channel'];
}
if ($filters['delivery_status'] !== '') {
    $deliveryWhere[] = 'd.status = :delivery_status';
    $deliveryParams['delivery_status'] = $filters['delivery_status'];
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

include TOY_ROOT . '/modules/notification/views/admin-notifications.php';
