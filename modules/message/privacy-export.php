<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId, array $context = []): array {
    $sectionLimits = is_array($context['section_limits'] ?? null) ? $context['section_limits'] : [];
    $limit = max(1, (int) ($sectionLimits['messages'] ?? 1000)) + 1;
    $result = [
        'messages' => [],
        'member_settings' => [],
        '_limits' => [
            'messages' => ['has_more' => false],
            'member_settings' => ['has_more' => false],
        ],
    ];

    try {
        $stmt = $pdo->prepare(
            'SELECT id,
                    CASE WHEN sender_account_id = :sender_direction_account_id THEN \'sent\' ELSE \'received\' END AS message_direction,
                    CASE WHEN sender_account_id = :sender_counterparty_account_id THEN \'masked_recipient\' ELSE \'masked_sender\' END AS counterparty_role,
                    body_text, status, read_at, sender_deleted_at, recipient_deleted_at, created_at, updated_at
             FROM sr_messages
             WHERE sender_account_id = :sender_account_id OR recipient_account_id = :recipient_account_id
             ORDER BY id ASC
             LIMIT ' . $limit
        );
        $stmt->execute([
            'sender_direction_account_id' => $accountId,
            'sender_counterparty_account_id' => $accountId,
            'sender_account_id' => $accountId,
            'recipient_account_id' => $accountId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) >= $limit) {
            array_pop($rows);
            $result['_limits']['messages']['has_more'] = true;
        }
        $result['messages'] = $rows;
    } catch (Throwable) {
        $result['messages'] = [];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT account_id, receive_enabled, created_at, updated_at
             FROM sr_message_member_settings
             WHERE account_id = :account_id
             LIMIT 2'
        );
        $stmt->execute(['account_id' => $accountId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 1) {
            array_pop($rows);
            $result['_limits']['member_settings']['has_more'] = true;
        }
        $result['member_settings'] = $rows;
    } catch (Throwable) {
        $result['member_settings'] = [];
    }

    return $result;
};
