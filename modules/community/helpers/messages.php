<?php

declare(strict_types=1);

function sr_community_message_box(PDO $pdo, int $accountId, string $box, int $limit = 50): array
{
    if ($accountId < 1) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    if ($box === 'sent') {
        $sql = 'SELECT m.id, m.sender_account_id, m.recipient_account_id, m.status, m.read_at, m.sender_deleted_at, m.recipient_deleted_at, m.created_at, m.updated_at,
                       recipient.display_name AS other_display_name
                FROM sr_community_messages m
                LEFT JOIN sr_member_accounts recipient ON recipient.id = m.recipient_account_id
                WHERE m.sender_account_id = :account_id
                  AND m.sender_deleted_at IS NULL
                ORDER BY m.id DESC
                LIMIT :limit_value';
    } else {
        $sql = 'SELECT m.id, m.sender_account_id, m.recipient_account_id, m.status, m.read_at, m.sender_deleted_at, m.recipient_deleted_at, m.created_at, m.updated_at,
                       sender.display_name AS other_display_name
                FROM sr_community_messages m
                LEFT JOIN sr_member_accounts sender ON sender.id = m.sender_account_id
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

function sr_community_message_account_label(?string $displayName, int $accountId, bool $showIdentifier = false, ?array $config = null): string
{
    $label = trim((string) $displayName);
    if ($label === 'withdrawn') {
        $label = sr_t('member::account.withdrawn_display_name');
    }

    if ($label === '') {
        $label = $accountId > 0 ? sr_t('community::report.account.member') : sr_t('community::report.account.unknown');
    }

    if (!$showIdentifier || $accountId < 1) {
        return $label;
    }

    $runtimeConfig = is_array($config) ? $config : sr_runtime_config();

    return sr_community_member_label_with_identifier($label, $runtimeConfig, $accountId, $showIdentifier);
}

function sr_community_message_by_id_for_account(PDO $pdo, int $messageId, int $accountId): ?array
{
    if ($messageId < 1 || $accountId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT m.id, m.sender_account_id, m.recipient_account_id, m.body_text, m.status, m.read_at, m.sender_deleted_at, m.recipient_deleted_at, m.created_at, m.updated_at,
                sender.display_name AS sender_display_name,
                recipient.display_name AS recipient_display_name
         FROM sr_community_messages m
         LEFT JOIN sr_member_accounts sender ON sender.id = m.sender_account_id
         LEFT JOIN sr_member_accounts recipient ON recipient.id = m.recipient_account_id
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

function sr_community_message_participants_for_account(PDO $pdo, int $messageId, int $accountId): ?array
{
    if ($messageId < 1 || $accountId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, sender_account_id, recipient_account_id
         FROM sr_community_messages
         WHERE id = :id
           AND (
                (sender_account_id = :sender_account_id AND sender_deleted_at IS NULL)
                OR (recipient_account_id = :recipient_account_id AND recipient_deleted_at IS NULL)
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

function sr_community_mark_message_read(PDO $pdo, array $message, int $accountId): void
{
    if ((int) $message['recipient_account_id'] !== $accountId || (string) ($message['read_at'] ?? '') !== '') {
        return;
    }

    $now = sr_now();
    $stmt = $pdo->prepare('UPDATE sr_community_messages SET read_at = :read_at, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        'read_at' => $now,
        'updated_at' => $now,
        'id' => (int) $message['id'],
    ]);
}

function sr_community_message_input_values(): array
{
    $recipientAccountHash = strtolower(trim(sr_post_string('recipient_account_hash', 40)));

    return [
        'recipient_account_hash' => sr_member_public_account_hash_is_valid($recipientAccountHash) ? $recipientAccountHash : '',
        'recipient_identifier' => sr_post_string_without_truncation('recipient_identifier', 255),
        'body_text' => sr_post_string_without_truncation('body_text', 5000),
    ];
}

function sr_community_validate_message_input(array $values): array
{
    $errors = [];
    $recipientAccountHash = is_string($values['recipient_account_hash'] ?? null) ? (string) $values['recipient_account_hash'] : '';
    if ($recipientAccountHash === '' && (!is_string($values['recipient_identifier']) || trim($values['recipient_identifier']) === '')) {
        $errors[] = sr_t('community::action.error.recipient_required');
    }

    if (!is_string($values['body_text'])) {
        $errors[] = sr_t('community::action.error.message_body_too_long');
    } elseif (trim($values['body_text']) === '') {
        $errors[] = sr_t('community::action.error.message_body_required');
    }

    return $errors;
}

function sr_community_create_message(PDO $pdo, int $senderAccountId, int $recipientAccountId, string $bodyText): int
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_messages
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

function sr_community_account_can_write_message(PDO $pdo, array $account, ?array $settings = null): bool
{
    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId < 1) {
        return false;
    }

    $settings = is_array($settings) ? sr_community_normalize_settings($settings) : sr_community_settings($pdo);
    $policy = (string) $settings['message_write_policy'];
    if ($policy === 'disabled') {
        return false;
    }

    $groupKeys = $policy === 'group' ? (array) $settings['message_write_group_keys'] : [];
    $result = sr_community_account_satisfies_access($pdo, $accountId, [
        'settings' => $settings,
        'group_keys' => $groupKeys,
        'group_required' => $policy === 'group',
        'min_level' => (int) $settings['message_write_min_level'],
    ]);

    return !empty($result['allowed']);
}

function sr_community_message_rate_limited(PDO $pdo, int $accountId, array $settings): bool
{
    $windowSeconds = min(86400, max(60, (int) ($settings['message_create_window_seconds'] ?? 300)));
    $limit = min(200, max(1, (int) ($settings['message_create_limit'] ?? 20)));

    return sr_community_rate_limits_table_exists($pdo)
        && sr_rate_limit_count($pdo, 'community.message.account', (string) $accountId, $windowSeconds) >= $limit;
}

function sr_community_record_message_rate_limit(PDO $pdo, int $accountId, array $settings): void
{
    if (!sr_community_rate_limits_table_exists($pdo)) {
        return;
    }

    $windowSeconds = min(86400, max(60, (int) ($settings['message_create_window_seconds'] ?? 300)));
    sr_rate_limit_increment($pdo, 'community.message.account', (string) $accountId, $windowSeconds);
}

function sr_community_soft_delete_message(PDO $pdo, array $message, int $accountId): void
{
    $now = sr_now();
    if ((int) $message['sender_account_id'] === $accountId) {
        $stmt = $pdo->prepare('UPDATE sr_community_messages SET sender_deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id');
    } elseif ((int) $message['recipient_account_id'] === $accountId) {
        $stmt = $pdo->prepare('UPDATE sr_community_messages SET recipient_deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id');
    } else {
        return;
    }

    $stmt->execute([
        'deleted_at' => $now,
        'updated_at' => $now,
        'id' => (int) $message['id'],
    ]);
}
