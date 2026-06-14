<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    $accountEmail = '';
    $stmt = $pdo->prepare('SELECT email FROM sr_member_accounts WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $accountId]);
    $accountRow = $stmt->fetch();
    if (is_array($accountRow)) {
        $candidateEmail = sr_normalize_identifier((string) ($accountRow['email'] ?? ''));
        $accountEmail = filter_var($candidateEmail, FILTER_VALIDATE_EMAIL) ? $candidateEmail : '';
    }

    $stmt = $pdo->prepare(
        'SELECT id, account_id, audience, title, body_text, body_format, link_url, status, read_at, created_by_account_id, created_at, updated_at
         FROM sr_notifications
         WHERE account_id = :account_id OR audience = :audience
         ORDER BY id DESC
         LIMIT 200'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'audience' => 'all',
    ]);

    $notifications = [];
    $notificationIds = [];
    $accountNotificationIds = [];
    foreach ($stmt->fetchAll() as $row) {
        $notificationId = (int) ($row['id'] ?? 0);
        if ($notificationId <= 0) {
            continue;
        }

        $notifications[] = $row;
        $notificationIds[] = $notificationId;
        if ((int) ($row['account_id'] ?? 0) === $accountId) {
            $accountNotificationIds[] = $notificationId;
        }
    }

    $reads = [];
    if ($notificationIds !== []) {
        $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
        $stmt = $pdo->prepare(
            'SELECT notification_id, account_id, read_at
             FROM sr_notification_reads
             WHERE account_id = ? AND notification_id IN (' . $placeholders . ')
             ORDER BY notification_id DESC'
        );
        $stmt->execute(array_merge([$accountId], $notificationIds));
        $reads = $stmt->fetchAll();
    }

    $pushEndpoints = [];
    try {
        $stmt = $pdo->prepare(
            'SELECT provider_key, recipient_type, recipient_label, recipient_masked, status, verified_at, disabled_at, last_used_at, created_at, updated_at
             FROM sr_notification_push_endpoints
             WHERE account_id = :account_id
             ORDER BY id DESC
             LIMIT 100'
        );
        $stmt->execute(['account_id' => $accountId]);
        $pushEndpoints = $stmt->fetchAll();
    } catch (Throwable) {
        $pushEndpoints = [];
    }

    $deliveries = [];
    if ($notificationIds !== []) {
        $allPlaceholders = implode(',', array_fill(0, count($notificationIds), '?'));
        $accountPlaceholders = $accountNotificationIds !== [] ? implode(',', array_fill(0, count($accountNotificationIds), '?')) : '';
        $deliveryWhere = 'notification_id IN (' . $allPlaceholders . ') AND channel = ?';
        $deliveryParams = array_merge($notificationIds, ['site']);
        if ($accountEmail !== '') {
            $deliveryWhere = '(' . $deliveryWhere . ') OR (notification_id IN (' . $allPlaceholders . ') AND channel = ? AND recipient = ?)';
            $deliveryParams = array_merge($deliveryParams, $notificationIds, ['email', $accountEmail]);
        }
        if ($accountNotificationIds !== []) {
            $adminExternalChannels = ['slack_webhook', 'discord_webhook', 'telegram_bot'];
            $adminExternalPlaceholders = implode(',', array_fill(0, count($adminExternalChannels), '?'));
            $deliveryWhere = '(' . $deliveryWhere . ') OR (notification_id IN (' . $accountPlaceholders . ') AND channel IN (' . $adminExternalPlaceholders . ') AND recipient LIKE ?)';
            $deliveryParams = array_merge($deliveryParams, $accountNotificationIds);
            $deliveryParams = array_merge($deliveryParams, $adminExternalChannels, ['endpoint:%']);
        }

        $stmt = $pdo->prepare(
            'SELECT notification_id, channel, recipient, status, provider_message_id, error_message, attempted_at, created_at, updated_at
             FROM sr_notification_deliveries
             WHERE ' . $deliveryWhere . '
             ORDER BY id DESC'
        );
        $stmt->execute($deliveryParams);
        $deliveries = $stmt->fetchAll();
        $endpointMasks = [];
        try {
            $stmt = $pdo->prepare(
                'SELECT id, recipient_masked
                 FROM sr_notification_push_endpoints
                 WHERE account_id = :account_id'
            );
            $stmt->execute(['account_id' => $accountId]);
            foreach ($stmt->fetchAll() as $endpointRow) {
                $endpointId = (int) ($endpointRow['id'] ?? 0);
                if ($endpointId > 0) {
                    $endpointMasks[$endpointId] = (string) ($endpointRow['recipient_masked'] ?? '');
                }
            }
        } catch (Throwable) {
            $endpointMasks = [];
        }
        foreach ($deliveries as $index => $delivery) {
            $recipient = (string) ($delivery['recipient'] ?? '');
            if (preg_match('/\Aendpoint:([1-9][0-9]*)\z/', $recipient, $matches) === 1) {
                $endpointId = (int) $matches[1];
                $deliveries[$index]['recipient'] = (string) ($endpointMasks[$endpointId] ?? 'endpoint:[masked]');
            }
        }
    }

    return [
        'notifications' => $notifications,
        'reads' => $reads,
        'push_endpoints' => $pushEndpoints,
        'deliveries' => $deliveries,
    ];
};
