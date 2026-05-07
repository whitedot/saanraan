<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/notification/helpers.php';

$account = toy_member_require_login($pdo);
$errors = [];
$notice = '';
$filters = [
    'status' => toy_get_string('status', 20),
];
if (!in_array($filters['status'], ['', 'unread', 'read'], true)) {
    $filters['status'] = '';
}

if (toy_request_method() === 'POST') {
    toy_require_csrf();
    $intent = toy_post_string('intent', 40);
    $notificationId = (int) toy_post_string('notification_id', 20);

    if ($intent === 'mark_all_read') {
        $now = toy_now();

        $stmt = $pdo->prepare(
            "UPDATE toy_notifications
             SET read_at = :read_at, status = :status, updated_at = :updated_at
             WHERE account_id = :account_id AND read_at IS NULL"
        );
        $stmt->execute([
            'read_at' => $now,
            'status' => 'read',
            'updated_at' => $now,
            'account_id' => (int) $account['id'],
        ]);

        $stmt = $pdo->prepare(
            "INSERT INTO toy_notification_reads (notification_id, account_id, read_at)
             SELECT n.id, :account_id, :read_at
             FROM toy_notifications n
             LEFT JOIN toy_notification_reads r ON r.notification_id = n.id AND r.account_id = :read_account_id
             WHERE n.audience = 'all' AND r.id IS NULL"
        );
        $stmt->execute([
            'account_id' => (int) $account['id'],
            'read_at' => $now,
            'read_account_id' => (int) $account['id'],
        ]);

        $notice = '모든 알림을 읽음 처리했습니다.';
    } elseif ($notificationId > 0) {
        $now = toy_now();
        $stmt = $pdo->prepare(
            "SELECT id, audience
             FROM toy_notifications
             WHERE id = :id
               AND (account_id = :account_id OR audience = 'all')
             LIMIT 1"
        );
        $stmt->execute([
            'id' => $notificationId,
            'account_id' => (int) $account['id'],
        ]);
        $notification = $stmt->fetch();

        if (is_array($notification)) {
            if ((string) $notification['audience'] === 'all') {
                $stmt = $pdo->prepare(
                    'INSERT INTO toy_notification_reads (notification_id, account_id, read_at)
                     VALUES (:notification_id, :account_id, :read_at)
                     ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)'
                );
                $stmt->execute([
                    'notification_id' => $notificationId,
                    'account_id' => (int) $account['id'],
                    'read_at' => $now,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE toy_notifications
                     SET read_at = :read_at, status = :status, updated_at = :updated_at
                     WHERE id = :id AND account_id = :account_id'
                );
                $stmt->execute([
                    'read_at' => $now,
                    'status' => 'read',
                    'updated_at' => $now,
                    'id' => $notificationId,
                    'account_id' => (int) $account['id'],
                ]);
            }

            $notice = '알림을 읽음 처리했습니다.';
        }
    }
}

$notifications = [];
$notificationSql = "SELECT n.id, n.title, n.body_text, n.link_url,
                           CASE WHEN COALESCE(n.read_at, r.read_at) IS NULL THEN n.status ELSE 'read' END AS status,
                           COALESCE(n.read_at, r.read_at) AS read_at,
                           n.created_at
                    FROM toy_notifications n
                    LEFT JOIN toy_notification_reads r ON r.notification_id = n.id AND r.account_id = :read_account_id
                    WHERE (n.account_id = :account_id OR n.audience = 'all')";
$notificationParams = [
    'read_account_id' => (int) $account['id'],
    'account_id' => (int) $account['id'],
];

if ($filters['status'] === 'unread') {
    $notificationSql .= ' AND COALESCE(n.read_at, r.read_at) IS NULL';
} elseif ($filters['status'] === 'read') {
    $notificationSql .= ' AND COALESCE(n.read_at, r.read_at) IS NOT NULL';
}

$notificationSql .= ' ORDER BY n.id DESC LIMIT 100';
$stmt = $pdo->prepare($notificationSql);
$stmt->execute($notificationParams);
foreach ($stmt->fetchAll() as $row) {
    $notifications[] = $row;
}

$stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_count,
        SUM(CASE WHEN COALESCE(n.read_at, r.read_at) IS NULL THEN 1 ELSE 0 END) AS unread_count
     FROM toy_notifications n
     LEFT JOIN toy_notification_reads r ON r.notification_id = n.id AND r.account_id = :read_account_id
     WHERE n.account_id = :account_id OR n.audience = 'all'"
);
$stmt->execute([
    'read_account_id' => (int) $account['id'],
    'account_id' => (int) $account['id'],
]);
$summaryRow = $stmt->fetch();
$notificationSummary = [
    'total' => is_array($summaryRow) ? (int) $summaryRow['total_count'] : 0,
    'unread' => is_array($summaryRow) ? (int) $summaryRow['unread_count'] : 0,
];

include TOY_ROOT . '/modules/notification/views/account-notifications.php';
