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
    $requiresPushReauth = in_array($intent, ['connect_telegram_push', 'disable_push_endpoint'], true);
    $currentPassword = '';

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
    } elseif ($requiresPushReauth) {
        $currentPasswordValue = sr_post_string_without_truncation('current_password', 255);
        $currentPassword = is_string($currentPasswordValue) ? $currentPasswordValue : '';
        $reauthThrottle = sr_member_reauth_throttle_status($pdo, (int) $account['id']);
        if (!empty($reauthThrottle['limited'])) {
            $errors[] = sr_t('member::action.reauth.throttled');
            sr_member_log_auth($pdo, (int) $account['id'], 'reauth_blocked', 'failure');
        } elseif (!password_verify($currentPassword, (string) ($account['password_hash'] ?? ''))) {
            $errors[] = sr_t('member::action.account.current_password_invalid');
            sr_member_log_auth($pdo, (int) $account['id'], 'notification_push_reauth', 'failure');
        }

        if ($errors === [] && $intent === 'connect_telegram_push') {
            $telegramChatId = sr_post_string('telegram_chat_id', 120);
            $recipientLabel = sr_post_string('recipient_label', 120);
            try {
                $endpointId = sr_notification_save_member_push_endpoint($pdo, [
                    'account_id' => (int) $account['id'],
                    'provider_key' => 'telegram_bot',
                    'endpoint' => $telegramChatId,
                    'recipient_label' => $recipientLabel,
                ]);
                sr_member_log_auth($pdo, (int) $account['id'], 'notification_push_connect', 'success');
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'member',
                    'event_type' => 'notification.member_push_endpoint.connected',
                    'target_type' => 'notification_push_endpoint',
                    'target_id' => (string) $endpointId,
                    'result' => 'success',
                    'message' => 'Member Telegram push endpoint connected.',
                    'metadata' => [
                        'provider_key' => 'telegram_bot',
                    ],
                ]);
                sr_notification_create_account_event($pdo, [
                    'account_id' => (int) $account['id'],
                    'module_key' => 'notification',
                    'event_key' => 'member_push_endpoint.connected',
                ]);
                $notice = 'Telegram 푸시 수신처를 연결했습니다.';
            } catch (InvalidArgumentException) {
                $errors[] = 'Telegram chat ID를 확인해 주세요.';
                sr_member_log_auth($pdo, (int) $account['id'], 'notification_push_connect', 'failure');
            } catch (RuntimeException) {
                $errors[] = '현재 Telegram 푸시 연결을 사용할 수 없습니다.';
                sr_member_log_auth($pdo, (int) $account['id'], 'notification_push_connect', 'failure');
            }
        } elseif ($errors === [] && $intent === 'disable_push_endpoint') {
            $endpointId = (int) sr_post_string('endpoint_id', 20);
            if (sr_notification_disable_member_push_endpoint($pdo, (int) $account['id'], $endpointId)) {
                sr_member_log_auth($pdo, (int) $account['id'], 'notification_push_disable', 'success');
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'member',
                    'event_type' => 'notification.member_push_endpoint.disabled',
                    'target_type' => 'notification_push_endpoint',
                    'target_id' => (string) $endpointId,
                    'result' => 'success',
                    'message' => 'Member Telegram push endpoint disabled.',
                    'metadata' => [
                        'provider_key' => 'telegram_bot',
                    ],
                ]);
                sr_notification_create_account_event($pdo, [
                    'account_id' => (int) $account['id'],
                    'module_key' => 'notification',
                    'event_key' => 'member_push_endpoint.disabled',
                ]);
                $notice = 'Telegram 푸시 수신처를 해제했습니다.';
            } else {
                $errors[] = '해제할 수신처를 찾을 수 없습니다.';
                sr_member_log_auth($pdo, (int) $account['id'], 'notification_push_disable', 'failure');
            }
        }
    }

    $_SESSION['sr_notification_account_flash'] = [
        'errors' => $errors,
        'notice' => $notice,
    ];
    sr_redirect('/account/notifications');
}

$notifications = [];
$notificationEventSelect = sr_notification_event_select_sql($pdo, 'n');
$notificationSql = "SELECT n.id, n.title, n.body_text, n.body_format, n.link_url" . $notificationEventSelect . ",
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
$notifications = sr_notification_apply_rendered_titles($pdo, $notifications);

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

$notificationSettings = sr_notification_settings($pdo);
$pushProviderReady = sr_notification_member_external_provider_is_ready('telegram_bot', $notificationSettings);
$pushEndpoints = sr_notification_member_push_endpoint_rows($pdo, (int) $account['id']);
$pushActiveCount = sr_notification_member_push_active_count($pdo, (int) $account['id'], 'telegram_bot');
$pushLimitReached = $pushActiveCount >= 5;

include SR_ROOT . '/modules/notification/views/account-notifications.php';
