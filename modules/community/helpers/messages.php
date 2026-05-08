<?php

declare(strict_types=1);

function toy_community_message_box(PDO $pdo, int $accountId, string $box, int $limit = 50): array
{
    if ($accountId < 1) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    if ($box === 'sent') {
        $sql = 'SELECT m.id, m.sender_account_id, m.recipient_account_id, m.body_text, m.status, m.read_at, m.sender_deleted_at, m.recipient_deleted_at, m.created_at, m.updated_at,
                       recipient.display_name AS other_display_name
                FROM toy_community_messages m
                LEFT JOIN toy_member_accounts recipient ON recipient.id = m.recipient_account_id
                WHERE m.sender_account_id = :account_id
                  AND m.sender_deleted_at IS NULL
                ORDER BY m.id DESC
                LIMIT :limit_value';
    } else {
        $sql = 'SELECT m.id, m.sender_account_id, m.recipient_account_id, m.body_text, m.status, m.read_at, m.sender_deleted_at, m.recipient_deleted_at, m.created_at, m.updated_at,
                       sender.display_name AS other_display_name
                FROM toy_community_messages m
                LEFT JOIN toy_member_accounts sender ON sender.id = m.sender_account_id
                WHERE m.recipient_account_id = :account_id
                  AND m.recipient_deleted_at IS NULL
                ORDER BY m.id DESC
                LIMIT :limit_value';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue('account_id', $accountId, PDO::PARAM_INT);
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function toy_community_message_account_label(?string $displayName, int $accountId): string
{
    $label = trim((string) $displayName);
    if ($label !== '') {
        return $label . ' #' . (string) $accountId;
    }

    return $accountId > 0 ? '회원 #' . (string) $accountId : '알 수 없는 회원';
}

function toy_community_message_by_id_for_account(PDO $pdo, int $messageId, int $accountId): ?array
{
    if ($messageId < 1 || $accountId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT m.id, m.sender_account_id, m.recipient_account_id, m.body_text, m.status, m.read_at, m.sender_deleted_at, m.recipient_deleted_at, m.created_at, m.updated_at,
                sender.display_name AS sender_display_name,
                recipient.display_name AS recipient_display_name
         FROM toy_community_messages m
         LEFT JOIN toy_member_accounts sender ON sender.id = m.sender_account_id
         LEFT JOIN toy_member_accounts recipient ON recipient.id = m.recipient_account_id
         WHERE m.id = :id
           AND (
                (m.sender_account_id = :sender_account_id AND m.sender_deleted_at IS NULL)
                OR (m.recipient_account_id = :recipient_account_id AND m.recipient_deleted_at IS NULL)
           )
         LIMIT 1'
    );
    $stmt->execute([
        'id' => $messageId,
        'sender_account_id' => $accountId,
        'recipient_account_id' => $accountId,
    ]);
    $message = $stmt->fetch();

    return is_array($message) ? $message : null;
}

function toy_community_mark_message_read(PDO $pdo, array $message, int $accountId): void
{
    if ((int) $message['recipient_account_id'] !== $accountId || (string) ($message['read_at'] ?? '') !== '') {
        return;
    }

    $now = toy_now();
    $stmt = $pdo->prepare('UPDATE toy_community_messages SET read_at = :read_at, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        'read_at' => $now,
        'updated_at' => $now,
        'id' => (int) $message['id'],
    ]);
}

function toy_community_message_input_values(): array
{
    $recipientAccountIdValue = toy_post_string('recipient_account_id', 20);

    return [
        'recipient_account_id' => preg_match('/\A[1-9][0-9]*\z/', $recipientAccountIdValue) === 1 ? (int) $recipientAccountIdValue : 0,
        'recipient_identifier' => toy_post_string_without_truncation('recipient_identifier', 255),
        'body_text' => toy_post_string_without_truncation('body_text', 5000),
    ];
}

function toy_community_validate_message_input(array $values): array
{
    $errors = [];
    $recipientAccountId = (int) ($values['recipient_account_id'] ?? 0);
    if ($recipientAccountId < 1 && (!is_string($values['recipient_identifier']) || trim($values['recipient_identifier']) === '')) {
        $errors[] = '받는 회원을 입력해 주세요.';
    }

    if (!is_string($values['body_text'])) {
        $errors[] = '쪽지 내용은 5000자 이내로 입력해 주세요.';
    } elseif (trim($values['body_text']) === '') {
        $errors[] = '쪽지 내용을 입력해 주세요.';
    }

    return $errors;
}

function toy_community_create_message(PDO $pdo, int $senderAccountId, int $recipientAccountId, string $bodyText): int
{
    $now = toy_now();
    $stmt = $pdo->prepare(
        'INSERT INTO toy_community_messages
            (sender_account_id, recipient_account_id, body_text, status, read_at, sender_deleted_at, recipient_deleted_at, created_at, updated_at)
         VALUES
            (:sender_account_id, :recipient_account_id, :body_text, :status, NULL, NULL, NULL, :created_at, :updated_at)'
    );
    $stmt->execute([
        'sender_account_id' => $senderAccountId,
        'recipient_account_id' => $recipientAccountId,
        'body_text' => trim($bodyText),
        'status' => 'sent',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function toy_community_message_rate_limited(PDO $pdo, int $accountId, array $settings): bool
{
    $windowSeconds = min(86400, max(60, (int) ($settings['message_create_window_seconds'] ?? 300)));
    $limit = min(200, max(1, (int) ($settings['message_create_limit'] ?? 20)));

    return toy_community_rate_limits_table_exists($pdo)
        && toy_rate_limit_count($pdo, 'community.message.account', (string) $accountId, $windowSeconds) >= $limit;
}

function toy_community_record_message_rate_limit(PDO $pdo, int $accountId, array $settings): void
{
    if (!toy_community_rate_limits_table_exists($pdo)) {
        return;
    }

    $windowSeconds = min(86400, max(60, (int) ($settings['message_create_window_seconds'] ?? 300)));
    toy_rate_limit_increment($pdo, 'community.message.account', (string) $accountId, $windowSeconds);
}

function toy_community_soft_delete_message(PDO $pdo, array $message, int $accountId): void
{
    $now = toy_now();
    if ((int) $message['sender_account_id'] === $accountId) {
        $stmt = $pdo->prepare('UPDATE toy_community_messages SET sender_deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id');
    } elseif ((int) $message['recipient_account_id'] === $accountId) {
        $stmt = $pdo->prepare('UPDATE toy_community_messages SET recipient_deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id');
    } else {
        return;
    }

    $stmt->execute([
        'deleted_at' => $now,
        'updated_at' => $now,
        'id' => (int) $message['id'],
    ]);
}
