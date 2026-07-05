<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId, array $context = []): array {
    if ($accountId < 1) {
        return ['cleaned' => false];
    }

    $stmt = $pdo->prepare('DELETE FROM sr_message_member_settings WHERE account_id = :account_id');
    $stmt->execute(['account_id' => $accountId]);
    $memberSettingsDeleted = $stmt->rowCount();

    $stmt = $pdo->prepare(
        'DELETE FROM sr_messages
         WHERE sender_account_id = :account_id
           AND recipient_account_id = :account_id
           AND sender_deleted_at IS NOT NULL
           AND recipient_deleted_at IS NOT NULL'
    );
    $stmt->execute(['account_id' => $accountId]);
    $selfMessagesDeleted = $stmt->rowCount();

    return [
        'cleaned' => true,
        'message_member_settings_deleted' => $memberSettingsDeleted,
        'self_messages_deleted' => $selfMessagesDeleted,
    ];
};
