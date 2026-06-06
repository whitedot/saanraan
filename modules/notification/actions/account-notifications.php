<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/notification/helpers.php';

$account = sr_member_require_login($pdo);
$errors = [];
$notice = '';
$flash = isset($_SESSION['sr_notification_account_flash']) && is_array($_SESSION['sr_notification_account_flash'])
    ? $_SESSION['sr_notification_account_flash']
    : [];
unset($_SESSION['sr_notification_account_flash']);
$errors = isset($flash['errors']) && is_array($flash['errors']) ? array_values(array_map('strval', $flash['errors'])) : [];
$notice = (string) ($flash['notice'] ?? '');
$filters = [
    'status' => sr_get_string('status', 20),
];
if (!in_array($filters['status'], ['', 'read'], true)) {
    $filters['status'] = '';
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $intent = sr_post_string('intent', 40);
    $notificationId = (int) sr_post_string('notification_id', 20);

    if ($intent === 'mark_all_read') {
        $now = sr_now();

        $stmt = $pdo->prepare(
            "UPDATE sr_notifications
             SET read_at = :read_at, updated_at = :updated_at
             WHERE account_id = :account_id AND read_at IS NULL"
        );
        $stmt->execute([
            'read_at' => $now,
            'updated_at' => $now,
            'account_id' => (int) $account['id'],
        ]);

        $stmt = $pdo->prepare(
            "INSERT INTO sr_notification_reads (notification_id, account_id, read_at)
             SELECT n.id, :account_id, :read_at
             FROM sr_notifications n
             LEFT JOIN sr_notification_reads r ON r.notification_id = n.id AND r.account_id = :read_account_id
             WHERE n.audience = 'all' AND r.id IS NULL"
        );
        $stmt->execute([
            'account_id' => (int) $account['id'],
            'read_at' => $now,
            'read_account_id' => (int) $account['id'],
        ]);

        $notice = '모든 알림을 읽음 처리했습니다.';
    } elseif ($notificationId > 0) {
        if (sr_notification_mark_read($pdo, $notificationId, (int) $account['id'])) {
            $notice = '알림을 읽음 처리했습니다.';
        }
    }

    $_SESSION['sr_notification_account_flash'] = [
        'errors' => $errors,
        'notice' => $notice,
    ];
    sr_redirect('/account/notifications');
}

$notifications = [];
$notificationSql = "SELECT n.id, n.title, n.body_text, n.body_format, n.link_url,
                           CASE WHEN COALESCE(n.read_at, r.read_at) IS NULL THEN 'unread' ELSE 'read' END AS status,
                           COALESCE(n.read_at, r.read_at) AS read_at,
                           n.created_at
                    FROM sr_notifications n
                    LEFT JOIN sr_notification_reads r ON r.notification_id = n.id AND r.account_id = :read_account_id
                    WHERE (n.account_id = :account_id OR n.audience = 'all')";
$notificationParams = [
    'read_account_id' => (int) $account['id'],
    'account_id' => (int) $account['id'],
];

if ($filters['status'] === 'read') {
    $notificationSql .= ' AND COALESCE(n.read_at, r.read_at) IS NOT NULL';
} else {
    $notificationSql .= ' AND COALESCE(n.read_at, r.read_at) IS NULL';
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
     FROM sr_notifications n
     LEFT JOIN sr_notification_reads r ON r.notification_id = n.id AND r.account_id = :read_account_id
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

include SR_ROOT . '/modules/notification/views/account-notifications.php';
