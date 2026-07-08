<?php

declare(strict_types=1);

function sr_message_default_settings(): array
{
    $metadata = sr_module_metadata('message');
    return is_array($metadata['settings'] ?? null) ? $metadata['settings'] : [];
}

function sr_message_bool_setting(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function sr_message_policy(string $value, string $default = 'all'): string
{
    return in_array($value, ['all', 'group', 'opt_in', 'disabled'], true) ? $value : $default;
}

function sr_message_group_keys_from_setting(mixed $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $value);
    }
    if (!is_array($value)) {
        return [];
    }

    $keys = [];
    foreach ($value as $groupKey) {
        $groupKey = strtolower(trim((string) $groupKey));
        if (function_exists('sr_member_group_key_is_valid') && !sr_member_group_key_is_valid($groupKey)) {
            continue;
        }
        if (preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $groupKey) === 1) {
            $keys[$groupKey] = true;
        }
    }

    return array_keys($keys);
}

function sr_message_settings(PDO $pdo): array
{
    $settings = array_merge(sr_message_default_settings(), sr_module_settings($pdo, 'message'));
    $settings['message_enabled'] = sr_message_bool_setting($settings['message_enabled'] ?? true);
    $settings['send_policy'] = sr_message_policy((string) ($settings['send_policy'] ?? 'all'), 'all');
    $settings['receive_policy'] = sr_message_policy((string) ($settings['receive_policy'] ?? 'all'), 'all');
    if ($settings['send_policy'] === 'opt_in') {
        $settings['send_policy'] = 'all';
    }
    $settings['send_group_keys'] = sr_message_group_keys_from_setting($settings['send_group_keys'] ?? []);
    $settings['receive_group_keys'] = sr_message_group_keys_from_setting($settings['receive_group_keys'] ?? []);
    $settings['member_receive_opt_enabled'] = sr_message_bool_setting($settings['member_receive_opt_enabled'] ?? true);
    $settings['default_member_receive_enabled'] = sr_message_bool_setting($settings['default_member_receive_enabled'] ?? true);
    $settings['message_create_window_seconds'] = min(86400, max(60, (int) ($settings['message_create_window_seconds'] ?? 300)));
    $settings['message_create_limit'] = min(200, max(1, (int) ($settings['message_create_limit'] ?? 20)));
    $settings['message_charge_enabled'] = false;

    return $settings;
}

function sr_message_enabled(PDO $pdo, ?array $settings = null): bool
{
    $settings = is_array($settings) ? $settings : sr_message_settings($pdo);

    return !empty($settings['message_enabled']);
}

function sr_message_member_settings(PDO $pdo, int $accountId, ?array $settings = null): array
{
    $settings = is_array($settings) ? $settings : sr_message_settings($pdo);
    $defaultReceive = !empty($settings['default_member_receive_enabled']);
    if ($accountId < 1) {
        return ['receive_enabled' => $defaultReceive, 'has_row' => false];
    }

    try {
        $stmt = $pdo->prepare('SELECT receive_enabled FROM sr_message_member_settings WHERE account_id = :account_id LIMIT 1');
        $stmt->execute(['account_id' => $accountId]);
        $row = $stmt->fetch();
    } catch (Throwable) {
        $row = false;
    }

    if (!is_array($row)) {
        return ['receive_enabled' => $defaultReceive, 'has_row' => false];
    }

    return ['receive_enabled' => (int) ($row['receive_enabled'] ?? 0) === 1, 'has_row' => true];
}

function sr_message_save_member_settings(PDO $pdo, int $accountId, bool $receiveEnabled): void
{
    if ($accountId < 1) {
        return;
    }

    $now = sr_now();
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = $driver === 'sqlite'
        ? 'INSERT INTO sr_message_member_settings (account_id, receive_enabled, created_at, updated_at)
           VALUES (:account_id, :receive_enabled, :created_at, :updated_at)
           ON CONFLICT(account_id) DO UPDATE SET receive_enabled = excluded.receive_enabled, updated_at = excluded.updated_at'
        : 'INSERT INTO sr_message_member_settings (account_id, receive_enabled, created_at, updated_at)
           VALUES (:account_id, :receive_enabled, :created_at, :updated_at)
           ON DUPLICATE KEY UPDATE receive_enabled = VALUES(receive_enabled), updated_at = VALUES(updated_at)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'account_id' => $accountId,
        'receive_enabled' => $receiveEnabled ? 1 : 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function sr_message_registration_field_key(): string
{
    return 'message_receive_enabled';
}

function sr_message_registration_fields(PDO $pdo): array
{
    $settings = sr_message_settings($pdo);
    if (empty($settings['message_enabled']) || empty($settings['member_receive_opt_enabled'])) {
        return [];
    }

    return [
        [
            'key' => sr_message_registration_field_key(),
            'type' => 'checkbox',
            'label' => '쪽지 수신 허용',
            'help' => '다른 회원이 나에게 쪽지를 보낼 수 있습니다.',
            'default' => !empty($settings['default_member_receive_enabled']),
        ],
    ];
}

function sr_message_registration_save(PDO $pdo, int $accountId, array $values, array $context = []): array
{
    if ($accountId < 1) {
        return ['receive_enabled' => false, 'saved' => false];
    }

    try {
        $settings = sr_message_settings($pdo);
        $receiveEnabled = !empty($settings['default_member_receive_enabled']);
        $fieldKey = sr_message_registration_field_key();
        $canUseRegistrationValue = !empty($settings['message_enabled'])
            && !empty($settings['member_receive_opt_enabled'])
            && array_key_exists($fieldKey, $values);
        if ($canUseRegistrationValue) {
            $receiveEnabled = (string) $values[$fieldKey] === '1';
        }

        sr_message_save_member_settings($pdo, $accountId, $receiveEnabled);

        return [
            'receive_enabled' => $receiveEnabled,
            'source' => $canUseRegistrationValue
                ? 'registration_form'
                : 'default',
            'saved' => true,
        ];
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'message_registration_save');
        throw new RuntimeException('message_registration_save_failed', 0, $exception);
    }
}

function sr_message_account_label(?string $displayName, int $accountId, bool $showIdentifier = false, ?array $config = null, ?string $accountStatus = null): string
{
    $label = trim((string) $displayName);
    if ((string) $accountStatus !== 'active') {
        $label = sr_t('member::account.withdrawn_display_name');
    }
    if ($label === '') {
        $label = $accountId > 0 ? '회원' : '알 수 없는 회원';
    }
    if (!$showIdentifier || $accountId < 1) {
        return $label;
    }

    $runtimeConfig = is_array($config) ? $config : sr_runtime_config();
    if (function_exists('sr_member_label_with_identifier')) {
        return sr_member_label_with_identifier($label, $runtimeConfig, $accountId, $showIdentifier);
    }

    return $label . ' #' . (string) $accountId;
}

function sr_message_notification_create_function(PDO $pdo): string
{
    return sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_function');
}

function sr_message_create_account_notification(
    PDO $pdo,
    int $accountId,
    string $title,
    string $bodyText,
    string $linkUrl,
    ?int $createdByAccountId = null
): bool {
    $createNotificationFunction = sr_message_notification_create_function($pdo);
    if ($accountId < 1 || $createNotificationFunction === '') {
        return false;
    }

    try {
        $createNotificationFunction($pdo, [
            'audience' => 'account',
            'account_id' => $accountId,
            'title' => $title,
            'body_text' => $bodyText,
            'link_url' => $linkUrl,
            'channels' => ['site'],
            'created_by_account_id' => $createdByAccountId,
        ]);
        return true;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'message_notification_create');
    }

    return false;
}

function sr_message_account_is_staff_bypass(PDO $pdo, int $accountId): bool
{
    if ($accountId < 1) {
        return false;
    }
    if ((!function_exists('sr_admin_is_owner') || !function_exists('sr_admin_has_permission')) && is_file(SR_ROOT . '/modules/admin/helpers.php')) {
        require_once SR_ROOT . '/modules/admin/helpers.php';
    }
    if (!function_exists('sr_admin_is_owner') || !function_exists('sr_admin_has_permission')) {
        return false;
    }

    return sr_admin_is_owner($pdo, $accountId)
        || sr_admin_has_permission($pdo, $accountId, '/admin/message/settings', 'edit')
        || sr_admin_has_permission($pdo, $accountId, '/admin/members', 'view');
}

function sr_message_account_matches_group_policy(PDO $pdo, int $accountId, array $groupKeys): bool
{
    if ($groupKeys === []) {
        return false;
    }
    if (!function_exists('sr_member_account_in_any_group') && is_file(SR_ROOT . '/modules/member/helpers.php')) {
        require_once SR_ROOT . '/modules/member/helpers.php';
    }

    return function_exists('sr_member_account_in_any_group') && sr_member_account_in_any_group($pdo, $accountId, $groupKeys);
}

function sr_message_account_can_receive_for_send(PDO $pdo, int $accountId, array $settings): bool
{
    if ($accountId < 1) {
        return false;
    }

    $memberSettings = sr_message_member_settings($pdo, $accountId, $settings);
    if (empty($memberSettings['receive_enabled'])) {
        return false;
    }

    $policy = (string) ($settings['receive_policy'] ?? 'all');
    if ($policy === 'disabled') {
        return false;
    }
    if ($policy === 'group') {
        return sr_message_account_matches_group_policy($pdo, $accountId, (array) ($settings['receive_group_keys'] ?? []));
    }
    if ($policy === 'opt_in') {
        return !empty($memberSettings['has_row']);
    }

    return $policy === 'all';
}

function sr_message_account_can_send(PDO $pdo, array $account, ?array $settings = null): bool
{
    $accountId = (int) ($account['id'] ?? 0);
    $settings = is_array($settings) ? $settings : sr_message_settings($pdo);
    if ($accountId < 1 || empty($settings['message_enabled'])) {
        return false;
    }
    if (sr_message_account_is_staff_bypass($pdo, $accountId)) {
        return true;
    }
    if (!sr_message_account_can_receive_for_send($pdo, $accountId, $settings)) {
        return false;
    }

    $policy = (string) $settings['send_policy'];
    if ($policy === 'disabled') {
        return false;
    }
    if ($policy === 'group') {
        return sr_message_account_matches_group_policy($pdo, $accountId, (array) $settings['send_group_keys']);
    }

    return $policy === 'all';
}

function sr_message_account_can_receive(PDO $pdo, array $recipient, array $senderAccount, ?array $settings = null): bool
{
    $recipientId = (int) ($recipient['id'] ?? 0);
    $senderId = (int) ($senderAccount['id'] ?? 0);
    $settings = is_array($settings) ? $settings : sr_message_settings($pdo);
    if ($recipientId < 1 || (string) ($recipient['status'] ?? '') !== 'active' || empty($settings['message_enabled'])) {
        return false;
    }
    if (sr_message_account_is_staff_bypass($pdo, $senderId)) {
        return true;
    }

    $memberSettings = sr_message_member_settings($pdo, $recipientId, $settings);
    if (empty($memberSettings['receive_enabled'])) {
        return false;
    }

    $policy = (string) $settings['receive_policy'];
    if ($policy === 'disabled') {
        return false;
    }
    if ($policy === 'group') {
        return sr_message_account_matches_group_policy($pdo, $recipientId, (array) $settings['receive_group_keys']);
    }
    if ($policy === 'opt_in') {
        return !empty($memberSettings['has_row']) && !empty($memberSettings['receive_enabled']);
    }

    return $policy === 'all';
}

function sr_message_box(PDO $pdo, int $accountId, string $box, int $limit = 50): array
{
    if ($accountId < 1) {
        return [];
    }
    $limit = max(1, min(100, $limit));
    if ($box === 'sent') {
        $sql = 'SELECT m.id, m.sender_account_id, m.recipient_account_id, m.status, m.read_at, m.sender_deleted_at, m.recipient_deleted_at, m.created_at, m.updated_at,
                       recipient.display_name AS other_display_name,
                       recipient.status AS other_account_status
                FROM sr_messages m
                LEFT JOIN sr_member_accounts recipient ON recipient.id = m.recipient_account_id
                WHERE m.sender_account_id = :account_id
                  AND m.sender_deleted_at IS NULL
                ORDER BY m.id DESC
                LIMIT :limit_value';
    } else {
        $sql = 'SELECT m.id, m.sender_account_id, m.recipient_account_id, m.status, m.read_at, m.sender_deleted_at, m.recipient_deleted_at, m.created_at, m.updated_at,
                       sender.display_name AS other_display_name,
                       sender.status AS other_account_status
                FROM sr_messages m
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

function sr_message_unread_count(PDO $pdo, int $accountId): int
{
    if ($accountId < 1) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sr_messages
         WHERE recipient_account_id = :account_id
           AND recipient_deleted_at IS NULL
           AND read_at IS NULL'
    );
    $stmt->execute(['account_id' => $accountId]);

    return max(0, (int) $stmt->fetchColumn());
}

function sr_message_by_id_for_account(PDO $pdo, int $messageId, int $accountId): ?array
{
    if ($messageId < 1 || $accountId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT m.id, m.sender_account_id, m.recipient_account_id, m.body_text, m.status, m.read_at, m.sender_deleted_at, m.recipient_deleted_at, m.created_at, m.updated_at,
                sender.display_name AS sender_display_name,
                sender.status AS sender_account_status,
                recipient.display_name AS recipient_display_name,
                recipient.status AS recipient_account_status
         FROM sr_messages m
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

function sr_message_participants_for_account(PDO $pdo, int $messageId, int $accountId): ?array
{
    if ($messageId < 1 || $accountId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, sender_account_id, recipient_account_id
         FROM sr_messages
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

function sr_message_mark_read(PDO $pdo, array $message, int $accountId): void
{
    if ((int) $message['recipient_account_id'] !== $accountId || (string) ($message['read_at'] ?? '') !== '') {
        return;
    }

    $now = sr_now();
    $stmt = $pdo->prepare('UPDATE sr_messages SET read_at = :read_at, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        'read_at' => $now,
        'updated_at' => $now,
        'id' => (int) $message['id'],
    ]);
}

function sr_message_input_values(): array
{
    $recipientAccountHash = strtolower(trim(sr_post_string('recipient_account_hash', 40)));
    $recipientAccountHashesInput = $_POST['recipient_account_hashes'] ?? [];
    $recipientAccountHashes = [];
    if (is_array($recipientAccountHashesInput)) {
        foreach ($recipientAccountHashesInput as $hash) {
            $hash = strtolower(trim((string) $hash));
            if (sr_member_public_account_hash_is_valid($hash)) {
                $recipientAccountHashes[$hash] = true;
            }
        }
    }
    if (sr_member_public_account_hash_is_valid($recipientAccountHash)) {
        $recipientAccountHashes[$recipientAccountHash] = true;
    }

    return [
        'recipient_account_hash' => sr_member_public_account_hash_is_valid($recipientAccountHash) ? $recipientAccountHash : '',
        'recipient_account_hashes' => array_slice(array_keys($recipientAccountHashes), 0, 20),
        'recipient_identifier' => sr_post_string_without_truncation('recipient_identifier', 255),
        'body_text' => sr_post_string_without_truncation('body_text', 5000),
    ];
}

function sr_message_validate_input(array $values): array
{
    $errors = [];
    $recipientAccountHash = is_string($values['recipient_account_hash'] ?? null) ? (string) $values['recipient_account_hash'] : '';
    $recipientAccountHashes = is_array($values['recipient_account_hashes'] ?? null) ? (array) $values['recipient_account_hashes'] : [];
    if ($recipientAccountHash === '' && $recipientAccountHashes === [] && (!is_string($values['recipient_identifier']) || trim($values['recipient_identifier']) === '')) {
        $errors[] = '수신자를 입력해 주세요.';
    }
    if (!is_string($values['body_text'])) {
        $errors[] = '쪽지 내용이 너무 깁니다.';
    } elseif (trim($values['body_text']) === '') {
        $errors[] = '쪽지 내용을 입력해 주세요.';
    }

    return $errors;
}

function sr_message_recipients_from_values(PDO $pdo, array $config, array $values): array
{
    $recipientsById = [];
    $hashes = is_array($values['recipient_account_hashes'] ?? null) ? (array) $values['recipient_account_hashes'] : [];
    $legacyHash = is_string($values['recipient_account_hash'] ?? null) ? (string) $values['recipient_account_hash'] : '';
    if ($legacyHash !== '') {
        $hashes[] = $legacyHash;
    }

    foreach ($hashes as $hash) {
        $hash = strtolower(trim((string) $hash));
        if (!sr_member_public_account_hash_is_valid($hash)) {
            continue;
        }
        $recipient = sr_member_public_account_summary_by_hash($pdo, $config, $hash);
        if (is_array($recipient)) {
            $recipientsById[(int) $recipient['id']] = $recipient;
        }
    }
    if ($recipientsById === [] && is_string($values['recipient_identifier'] ?? null) && trim((string) $values['recipient_identifier']) !== '') {
        $recipient = sr_member_find_by_identifier($pdo, $config, (string) $values['recipient_identifier']);
        if (is_array($recipient)) {
            $recipientsById[(int) $recipient['id']] = $recipient;
        }
    }
    foreach ($recipientsById as $accountId => $recipient) {
        if ($accountId < 1 || (string) ($recipient['status'] ?? '') !== 'active') {
            unset($recipientsById[$accountId]);
        }
    }

    return array_values($recipientsById);
}

function sr_message_create(PDO $pdo, int $senderAccountId, int $recipientAccountId, string $bodyText): int
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_messages
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

function sr_message_rate_limited(PDO $pdo, int $accountId, array $settings): bool
{
    if ($accountId < 1 || !function_exists('sr_rate_limit_count')) {
        return false;
    }

    return sr_rate_limit_count($pdo, 'message.account', (string) $accountId, (int) $settings['message_create_window_seconds']) >= (int) $settings['message_create_limit'];
}

function sr_message_record_rate_limit(PDO $pdo, int $accountId, array $settings): void
{
    if ($accountId < 1 || !function_exists('sr_rate_limit_increment')) {
        return;
    }

    sr_rate_limit_increment($pdo, 'message.account', (string) $accountId, (int) $settings['message_create_window_seconds']);
}

function sr_message_soft_delete(PDO $pdo, array $message, int $accountId): void
{
    $now = sr_now();
    if ((int) $message['sender_account_id'] === $accountId) {
        $stmt = $pdo->prepare('UPDATE sr_messages SET sender_deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id');
    } elseif ((int) $message['recipient_account_id'] === $accountId) {
        $stmt = $pdo->prepare('UPDATE sr_messages SET recipient_deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id');
    } else {
        return;
    }

    $stmt->execute([
        'deleted_at' => $now,
        'updated_at' => $now,
        'id' => (int) $message['id'],
    ]);
}

function sr_message_time_html(string $datetime, string $empty = ''): string
{
    if ($datetime === '') {
        return sr_e($empty);
    }
    if (function_exists('sr_relative_time_html')) {
        return sr_relative_time_html($datetime);
    }

    return '<time datetime="' . sr_e($datetime) . '" title="' . sr_e($datetime) . '">' . sr_e($datetime) . '</time>';
}

function sr_message_plain_text_html(string $text): string
{
    return nl2br(sr_e($text));
}

function sr_message_report_target(PDO $pdo, int $messageId, int $actorAccountId): ?array
{
    $message = sr_message_participants_for_account($pdo, $messageId, $actorAccountId);
    if (!is_array($message)) {
        return null;
    }

    $reportedAccountId = (int) $message['sender_account_id'] === $actorAccountId
        ? (int) $message['recipient_account_id']
        : (int) $message['sender_account_id'];

    return [
        'target_type' => 'message',
        'target_id' => (int) $message['id'],
        'reported_account_id' => $reportedAccountId,
        'message_id' => (int) $message['id'],
        'redirect_path' => '/message?id=' . (string) $message['id'],
    ];
}
