<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/helpers/common.php';

function sr_notification_clean_single_line(string $value, int $maxLength): string
{
    return sr_clean_single_line($value, $maxLength);
}

function sr_notification_clean_text(string $value, int $maxLength): string
{
    return sr_clean_text($value, $maxLength);
}

function sr_notification_body_format(string $value): string
{
    return in_array($value, ['plain', 'html'], true) ? $value : 'plain';
}

function sr_notification_delivery_status_transition(string $currentStatus, string $targetStatus): array
{
    $currentStatus = trim($currentStatus);
    $targetStatus = trim($targetStatus);
    $knownStatuses = sr_notification_delivery_statuses();

    if (!in_array($currentStatus, $knownStatuses, true)
        || !in_array($targetStatus, $knownStatuses, true)
        || $currentStatus === $targetStatus
        || $currentStatus === 'sent'
    ) {
        return ['allowed' => false, 'operation' => ''];
    }

    if ($targetStatus === 'queued' && in_array($currentStatus, ['failed', 'canceled', 'dead'], true)) {
        return ['allowed' => true, 'operation' => 'retry'];
    }

    if ($targetStatus === 'canceled' && in_array($currentStatus, ['queued', 'processing', 'failed', 'dead'], true)) {
        return ['allowed' => true, 'operation' => 'cancel'];
    }

    if ($targetStatus === 'failed' && in_array($currentStatus, ['queued', 'processing'], true)) {
        return ['allowed' => true, 'operation' => 'mark_failed'];
    }

    if ($targetStatus === 'dead' && in_array($currentStatus, ['queued', 'processing', 'failed'], true)) {
        return ['allowed' => true, 'operation' => 'mark_dead'];
    }

    if ($targetStatus === 'sent' && in_array($currentStatus, ['queued', 'processing', 'failed', 'canceled'], true)) {
        return ['allowed' => true, 'operation' => 'mark_sent'];
    }

    return ['allowed' => false, 'operation' => ''];
}

function sr_notification_delivery_statuses(): array
{
    return ['queued', 'processing', 'sent', 'failed', 'canceled', 'dead'];
}

function sr_notification_delivery_runner_lock_id(): string
{
    $host = gethostname();
    $host = is_string($host) && $host !== '' ? $host : 'web';
    return sr_notification_clean_single_line($host . ':' . getmypid(), 80);
}

function sr_notification_delivery_error_message(string $message): string
{
    $message = sr_notification_clean_single_line($message, 2000);
    $replacements = [
        ['/Bearer\s+[A-Za-z0-9._~+\-\/]+=*/i', 'Bearer [masked]'],
        ['/(token|secret|password|passwd|pwd|key)=([^&\s]+)/i', '$1=[masked]'],
        ['#https://hooks\.slack\.com/services/[^\s]+#i', 'https://hooks.slack.com/services/[masked]'],
        ['#https://discord(?:app)?\.com/api/webhooks/[^\s]+#i', 'https://discord.com/api/webhooks/[masked]'],
        ['#https://api\.telegram\.org/bot[0-9A-Za-z:_-]+/sendMessage#i', 'https://api.telegram.org/bot[masked]/sendMessage'],
        ['#https?://[^\s@/]+:[^\s@/]+@[^\s]+#i', '[masked-url]'],
    ];

    foreach ($replacements as $replacement) {
        $message = preg_replace($replacement[0], $replacement[1], $message) ?? 'masked error';
    }

    return sr_notification_clean_single_line($message, 255);
}

function sr_notification_mask_recipient(string $recipient): string
{
    $recipient = trim($recipient);
    if ($recipient === '') {
        return '';
    }

    if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        [$local, $domain] = explode('@', $recipient, 2);
        $localPrefix = function_exists('mb_substr') ? mb_substr($local, 0, 2) : substr($local, 0, 2);
        return $localPrefix . '***@' . $domain;
    }

    if (sr_is_http_url($recipient)) {
        $host = parse_url($recipient, PHP_URL_HOST);
        return is_string($host) && $host !== '' ? 'https://' . $host . '/[masked]' : '[masked]';
    }

    $prefix = function_exists('mb_substr') ? mb_substr($recipient, 0, 4) : substr($recipient, 0, 4);
    return $prefix . '***';
}

function sr_notification_update_delivery_status_row(PDO $pdo, int $deliveryId, string $beforeStatus, string $targetStatus, string $now): array
{
    $transition = sr_notification_delivery_status_transition($beforeStatus, $targetStatus);
    if (empty($transition['allowed'])) {
        return [
            'ok' => false,
            'error' => 'invalid_transition',
            'before_status' => $beforeStatus,
            'status' => $targetStatus,
            'operation' => '',
        ];
    }

    $attemptedAt = in_array($targetStatus, ['sent', 'failed'], true) ? $now : null;
    $clearAttemptedAt = 0;
    $providerMessageId = null;
    $errorMessage = null;
    if ((string) ($transition['operation'] ?? '') === 'retry') {
        $clearAttemptedAt = 1;
        $providerMessageId = '';
        $errorMessage = '';
    } elseif ($targetStatus === 'sent') {
        $errorMessage = '';
    }

    $stmt = $pdo->prepare(
        "UPDATE sr_notification_deliveries
         SET status = :status,
             attempted_at = CASE
                 WHEN CAST(:clear_attempted_at AS INTEGER) = 1 THEN NULL
                 WHEN :attempted_at IS NOT NULL THEN :attempted_at
                 ELSE attempted_at
             END,
             provider_message_id = COALESCE(:provider_message_id, provider_message_id),
             error_message = COALESCE(:error_message, error_message),
             locked_at = NULL,
             locked_by = '',
             next_attempt_at = NULL,
             updated_at = :updated_at
         WHERE id = :id
           AND status = :before_status"
    );
    $stmt->execute([
        'status' => $targetStatus,
        'attempted_at' => $attemptedAt,
        'clear_attempted_at' => $clearAttemptedAt,
        'provider_message_id' => $providerMessageId,
        'error_message' => $errorMessage,
        'updated_at' => $now,
        'id' => $deliveryId,
        'before_status' => $beforeStatus,
    ]);

    if ($stmt->rowCount() < 1) {
        return [
            'ok' => false,
            'error' => 'changed',
            'before_status' => $beforeStatus,
            'status' => $targetStatus,
            'operation' => (string) ($transition['operation'] ?? ''),
        ];
    }

    return [
        'ok' => true,
        'error' => '',
        'before_status' => $beforeStatus,
        'status' => $targetStatus,
        'operation' => (string) ($transition['operation'] ?? ''),
    ];
}

function sr_notification_mail_config_from_settings(array $settings): array
{
    return [
        'transport' => (string) ($settings['email_transport'] ?? 'php_mail'),
        'from_email' => (string) ($settings['email_from_email'] ?? ''),
        'from_name' => (string) ($settings['email_from_name'] ?? ''),
        'host' => (string) ($settings['email_smtp_host'] ?? ''),
        'port' => (int) ($settings['email_smtp_port'] ?? 587),
        'encryption' => (string) ($settings['email_smtp_encryption'] ?? 'tls'),
        'username' => (string) ($settings['email_smtp_username'] ?? ''),
        'password' => (string) ($settings['email_smtp_password'] ?? ''),
        'timeout_seconds' => (int) ($settings['email_timeout_seconds'] ?? 10),
        'endpoint' => (string) ($settings['email_http_api_endpoint'] ?? ''),
        'bearer_token' => (string) ($settings['email_http_api_bearer_token'] ?? ''),
    ];
}

function sr_notification_external_provider_options(): array
{
    return [
        'slack_webhook' => [
            'label' => 'Slack webhook',
            'enabled_setting' => 'slack_webhook_enabled',
            'webhook_url_setting' => 'slack_webhook_url',
            'channel_label_setting' => 'slack_channel_label',
        ],
        'discord_webhook' => [
            'label' => 'Discord webhook',
            'enabled_setting' => 'discord_webhook_enabled',
            'webhook_url_setting' => 'discord_webhook_url',
            'channel_label_setting' => 'discord_channel_label',
        ],
        'telegram_bot' => [
            'label' => 'Telegram bot',
            'enabled_setting' => 'telegram_bot_enabled',
            'bot_token_setting' => 'telegram_bot_token',
            'chat_id_setting' => 'telegram_chat_id',
            'channel_label_setting' => 'telegram_channel_label',
        ],
    ];
}

function sr_notification_admin_external_channel_keys(): array
{
    return array_keys(sr_notification_external_provider_options());
}

function sr_notification_admin_external_channel_sql_list(): string
{
    return "'" . implode("', '", sr_notification_admin_external_channel_keys()) . "'";
}

function sr_notification_external_failure_policy(string $value): string
{
    return in_array($value, ['retry', 'dead'], true) ? $value : 'retry';
}

function sr_notification_secret_display(string $value): string
{
    return trim($value) === '' ? '' : '********';
}

function sr_notification_webhook_url_is_allowed(string $url): bool
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 255 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $scheme = parse_url($url, PHP_URL_SCHEME);
    $host = parse_url($url, PHP_URL_HOST);

    return is_string($scheme)
        && strtolower($scheme) === 'https'
        && is_string($host)
        && $host !== '';
}

function sr_notification_telegram_bot_token_is_allowed(string $token): bool
{
    $token = trim($token);
    return $token !== '' && strlen($token) <= 215 && preg_match('/\A[0-9]{5,20}:[A-Za-z0-9_-]{20,194}\z/', $token) === 1;
}

function sr_notification_telegram_chat_id_is_allowed(string $chatId): bool
{
    $chatId = trim($chatId);
    return $chatId !== ''
        && strlen($chatId) <= 120
        && (preg_match('/\A-?[0-9]{1,20}\z/', $chatId) === 1 || preg_match('/\A@[A-Za-z][A-Za-z0-9_]{4,31}\z/', $chatId) === 1);
}

function sr_notification_secret_key(string $purpose): string
{
    $config = function_exists('sr_runtime_config') ? sr_runtime_config() : [];
    $appKey = function_exists('sr_app_key') ? sr_app_key($config) : (string) ($config['app_key'] ?? '');
    if ($appKey === '') {
        return '';
    }

    return hash('sha256', 'notification-secret|' . $purpose, true);
}

function sr_notification_secret_crypto_available(): bool
{
    return sr_notification_secret_key('probe') !== ''
        && (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt') || function_exists('openssl_encrypt'));
}

function sr_notification_secret_encrypt(string $plaintext, string $purpose): string
{
    $plaintext = (string) $plaintext;
    $key = sr_notification_secret_key($purpose);
    if ($plaintext === '' || $key === '') {
        return '';
    }

    if (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $purpose, $nonce, $key);
        return 'sr1:sodium:' . base64_encode($nonce . $ciphertext);
    }

    if (function_exists('openssl_encrypt')) {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $purpose, 16);
        return is_string($ciphertext) && $tag !== '' ? 'sr1:openssl:' . base64_encode($iv . $tag . $ciphertext) : '';
    }

    return '';
}

function sr_notification_secret_decrypt(string $ciphertext, string $purpose): ?string
{
    $key = sr_notification_secret_key($purpose);
    if ($ciphertext === '' || $key === '') {
        return null;
    }

    if (str_starts_with($ciphertext, 'sr1:sodium:') && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')) {
        $raw = base64_decode(substr($ciphertext, strlen('sr1:sodium:')), true);
        if (!is_string($raw) || strlen($raw) <= SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES) {
            return null;
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $encrypted = substr($raw, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($encrypted, $purpose, $nonce, $key);
        return is_string($plain) ? $plain : null;
    }

    if (str_starts_with($ciphertext, 'sr1:openssl:') && function_exists('openssl_decrypt')) {
        $raw = base64_decode(substr($ciphertext, strlen('sr1:openssl:')), true);
        if (!is_string($raw) || strlen($raw) <= 28) {
            return null;
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $encrypted = substr($raw, 28);
        $plain = openssl_decrypt($encrypted, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $purpose);
        return is_string($plain) ? $plain : null;
    }

    return null;
}

function sr_notification_secret_fingerprint(string $plaintext, string $purpose): string
{
    $key = sr_notification_secret_key('fingerprint|' . $purpose);
    return hash_hmac('sha256', $plaintext, $key !== '' ? $key : 'notification-fingerprint-fallback');
}

function sr_notification_delivery_endpoint_id(string $recipient): int
{
    return preg_match('/\Aendpoint:([1-9][0-9]*)\z/', trim($recipient), $matches) === 1 ? (int) $matches[1] : 0;
}

function sr_notification_member_external_channel_keys(): array
{
    return ['telegram_bot'];
}

function sr_notification_admin_external_delivery_sql_condition(string $alias = 'd'): string
{
    return $alias . '.channel IN (' . sr_notification_admin_external_channel_sql_list() . ") AND " . $alias . ".recipient NOT LIKE 'endpoint:%'";
}

function sr_notification_member_external_provider_is_ready(string $channel, array $settings): bool
{
    if ($channel !== 'telegram_bot' || empty($settings['external_push_enabled']) || empty($settings['telegram_bot_enabled'])) {
        return false;
    }

    return sr_notification_telegram_bot_token_is_allowed((string) ($settings['telegram_bot_token'] ?? ''))
        && sr_notification_secret_crypto_available();
}

function sr_notification_push_endpoint_mask(string $providerKey, string $endpoint): string
{
    if ($providerKey === 'telegram_bot') {
        return sr_notification_mask_recipient($endpoint);
    }

    return sr_notification_mask_recipient($endpoint);
}

function sr_notification_save_member_push_endpoint(PDO $pdo, array $data): int
{
    $accountId = (int) ($data['account_id'] ?? 0);
    $providerKey = (string) ($data['provider_key'] ?? 'telegram_bot');
    $endpoint = sr_notification_clean_single_line((string) ($data['endpoint'] ?? ''), 255);
    if ($accountId <= 0 || !in_array($providerKey, sr_notification_member_external_channel_keys(), true)) {
        throw new InvalidArgumentException('Member push endpoint is invalid.');
    }
    if ($providerKey === 'telegram_bot' && !sr_notification_telegram_chat_id_is_allowed($endpoint)) {
        throw new InvalidArgumentException('Telegram chat ID is invalid.');
    }
    if (!sr_notification_member_external_provider_is_ready($providerKey, sr_notification_settings($pdo))) {
        throw new RuntimeException('Member push provider is not ready.');
    }

    $stmt = $pdo->prepare("SELECT id FROM sr_member_accounts WHERE id = :id AND status = 'active' LIMIT 1");
    $stmt->execute(['id' => $accountId]);
    if ((int) $stmt->fetchColumn() <= 0) {
        throw new InvalidArgumentException('Member account is not active.');
    }

    $purpose = 'notification-push-endpoint|' . $providerKey;
    $ciphertext = sr_notification_secret_encrypt($endpoint, $purpose);
    if ($ciphertext === '') {
        throw new RuntimeException('Member push endpoint encryption failed.');
    }

    $fingerprint = sr_notification_secret_fingerprint($endpoint, $purpose);
    $label = sr_notification_clean_single_line((string) ($data['recipient_label'] ?? ''), 120);
    $masked = sr_notification_push_endpoint_mask($providerKey, $endpoint);
    $now = sr_now();

    $stmt = $pdo->prepare('SELECT id FROM sr_notification_push_endpoints WHERE provider_key = :provider_key AND endpoint_fingerprint = :endpoint_fingerprint LIMIT 1');
    $stmt->execute([
        'provider_key' => $providerKey,
        'endpoint_fingerprint' => $fingerprint,
    ]);
    $existingId = (int) $stmt->fetchColumn();
    if ($existingId > 0) {
        $stmt = $pdo->prepare(
            "UPDATE sr_notification_push_endpoints
             SET account_id = :account_id,
                 recipient_type = 'personal',
                 endpoint_ciphertext = :endpoint_ciphertext,
                 recipient_label = :recipient_label,
                 recipient_masked = :recipient_masked,
                 status = 'active',
                 disabled_at = NULL,
                 verified_at = COALESCE(verified_at, :verified_at),
                 updated_at = :updated_at
             WHERE id = :id"
        );
        $stmt->execute([
            'account_id' => $accountId,
            'endpoint_ciphertext' => $ciphertext,
            'recipient_label' => $label,
            'recipient_masked' => $masked,
            'verified_at' => $now,
            'updated_at' => $now,
            'id' => $existingId,
        ]);
        return $existingId;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO sr_notification_push_endpoints
            (account_id, provider_key, recipient_type, endpoint_ciphertext, endpoint_fingerprint,
             recipient_label, recipient_masked, status, key_version, verified_at, disabled_at, last_used_at, created_at, updated_at)
         VALUES
            (:account_id, :provider_key, 'personal', :endpoint_ciphertext, :endpoint_fingerprint,
             :recipient_label, :recipient_masked, 'active', 'v1', :verified_at, NULL, NULL, :created_at, :updated_at)"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'provider_key' => $providerKey,
        'endpoint_ciphertext' => $ciphertext,
        'endpoint_fingerprint' => $fingerprint,
        'recipient_label' => $label,
        'recipient_masked' => $masked,
        'verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_notification_member_push_endpoints(PDO $pdo, int $accountId, string $providerKey): array
{
    if ($accountId <= 0 || !in_array($providerKey, sr_notification_member_external_channel_keys(), true)) {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT id, account_id, provider_key, recipient_type, endpoint_ciphertext, recipient_masked, status
             FROM sr_notification_push_endpoints
             WHERE account_id = :account_id
               AND provider_key = :provider_key
               AND recipient_type = 'personal'
               AND status = 'active'
             ORDER BY id ASC
             LIMIT 5"
        );
        $stmt->execute([
            'account_id' => $accountId,
            'provider_key' => $providerKey,
        ]);
    } catch (Throwable) {
        return [];
    }

    return $stmt->fetchAll();
}

function sr_notification_claim_delivery(PDO $pdo, string $lockId, string $now, int $lockTimeoutSeconds, array $channels = []): ?array
{
    $allowedChannels = array_values(array_filter(
        sr_notification_normalize_channels($channels === [] ? array_merge(['email'], sr_notification_admin_external_channel_keys()) : $channels),
        static fn (string $channel): bool => $channel !== 'site'
    ));
    if ($allowedChannels === []) {
        return null;
    }

    $lockCutoff = date('Y-m-d H:i:s', strtotime($now) - max(30, $lockTimeoutSeconds));
    $channelPlaceholders = [];
    $params = [
        'now_due' => $now,
        'lock_cutoff' => $lockCutoff,
    ];
    foreach ($allowedChannels as $index => $channel) {
        $paramKey = 'channel_' . (string) $index;
        $channelPlaceholders[] = ':' . $paramKey;
        $params[$paramKey] = $channel;
    }

    $stmt = $pdo->prepare(
        'SELECT d.id
         FROM sr_notification_deliveries d
         WHERE d.channel IN (' . implode(', ', $channelPlaceholders) . ')
           AND (
                (d.status = \'queued\' AND (d.next_attempt_at IS NULL OR d.next_attempt_at <= :now_due))
                OR (d.status = \'processing\' AND (d.locked_at IS NULL OR d.locked_at <= :lock_cutoff))
           )
         ORDER BY d.id ASC
         LIMIT 1'
    );
    $stmt->execute($params);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    $deliveryId = (int) ($row['id'] ?? 0);
    if ($deliveryId <= 0) {
        return null;
    }

    $updateParams = array_merge($params, [
        'locked_at' => $now,
        'locked_by' => $lockId,
        'updated_at' => $now,
        'id' => $deliveryId,
    ]);
    $stmt = $pdo->prepare(
        'UPDATE sr_notification_deliveries
         SET status = \'processing\',
             locked_at = :locked_at,
             locked_by = :locked_by,
             attempt_count = attempt_count + 1,
             updated_at = :updated_at
         WHERE id = :id
           AND channel IN (' . implode(', ', $channelPlaceholders) . ')
           AND (
                (status = \'queued\' AND (next_attempt_at IS NULL OR next_attempt_at <= :now_due))
                OR (status = \'processing\' AND (locked_at IS NULL OR locked_at <= :lock_cutoff))
           )'
    );
    $stmt->execute($updateParams);
    if ($stmt->rowCount() < 1) {
        return null;
    }

    $adminExternalCondition = sr_notification_admin_external_delivery_sql_condition('d');
    $stmt = $pdo->prepare(
        "SELECT d.id, d.notification_id, d.channel, d.recipient, d.status, d.attempt_count,
                CASE WHEN " . $adminExternalCondition . " THEN an.title ELSE n.title END AS title,
                CASE WHEN " . $adminExternalCondition . " THEN an.body_text ELSE n.body_text END AS body_text,
                CASE WHEN " . $adminExternalCondition . " THEN 'plain' ELSE n.body_format END AS body_format,
                CASE WHEN " . $adminExternalCondition . " THEN an.action_url ELSE n.link_url END AS link_url
         FROM sr_notification_deliveries d
         LEFT JOIN sr_notifications n ON n.id = d.notification_id AND NOT (" . $adminExternalCondition . ")
         LEFT JOIN sr_admin_notifications an ON an.id = d.notification_id AND " . $adminExternalCondition . "
         WHERE d.id = :id
         LIMIT 1"
    );
    $stmt->execute(['id' => $deliveryId]);
    $delivery = $stmt->fetch();

    return is_array($delivery) ? $delivery : null;
}

function sr_notification_claim_email_delivery(PDO $pdo, string $lockId, string $now, int $lockTimeoutSeconds): ?array
{
    return sr_notification_claim_delivery($pdo, $lockId, $now, $lockTimeoutSeconds, ['email']);
}

function sr_notification_mark_delivery_sent(PDO $pdo, int $deliveryId, string $now, string $providerMessageId = ''): void
{
    $providerMessageId = sr_notification_clean_single_line($providerMessageId, 120);
    $stmt = $pdo->prepare(
        "UPDATE sr_notification_deliveries
         SET status = 'sent',
             provider_message_id = :provider_message_id,
             error_message = '',
             attempted_at = :attempted_at,
             locked_at = NULL,
             locked_by = '',
             next_attempt_at = NULL,
             updated_at = :updated_at
         WHERE id = :id
           AND status = 'processing'"
    );
    $stmt->execute([
        'provider_message_id' => $providerMessageId,
        'attempted_at' => $now,
        'updated_at' => $now,
        'id' => $deliveryId,
    ]);
}

function sr_notification_mark_delivery_failed(PDO $pdo, int $deliveryId, string $now, string $errorMessage, int $attemptCount, int $maxAttempts): string
{
    $attemptCount = max(1, $attemptCount);
    $maxAttempts = max(1, $maxAttempts);
    $errorMessage = sr_notification_delivery_error_message($errorMessage);
    $status = $attemptCount >= $maxAttempts ? 'dead' : 'queued';
    $nextAttemptAt = $status === 'queued'
        ? date('Y-m-d H:i:s', strtotime($now) + min(3600, 60 * (2 ** min(5, $attemptCount - 1))))
        : null;

    $stmt = $pdo->prepare(
        'UPDATE sr_notification_deliveries
         SET status = :status,
             error_message = :error_message,
             attempted_at = :attempted_at,
             locked_at = NULL,
             locked_by = \'\',
             next_attempt_at = :next_attempt_at,
             updated_at = :updated_at
         WHERE id = :id
           AND status = \'processing\''
    );
    $stmt->execute([
        'status' => $status,
        'error_message' => $errorMessage,
        'attempted_at' => $now,
        'next_attempt_at' => $nextAttemptAt,
        'updated_at' => $now,
        'id' => $deliveryId,
    ]);

    return $status;
}

function sr_notification_mark_delivery_canceled(PDO $pdo, int $deliveryId, string $now, string $errorMessage): void
{
    $stmt = $pdo->prepare(
        "UPDATE sr_notification_deliveries
         SET status = 'canceled',
             error_message = :error_message,
             attempted_at = :attempted_at,
             locked_at = NULL,
             locked_by = '',
             next_attempt_at = NULL,
             updated_at = :updated_at
         WHERE id = :id
           AND status = 'processing'"
    );
    $stmt->execute([
        'error_message' => sr_notification_delivery_error_message($errorMessage),
        'attempted_at' => $now,
        'updated_at' => $now,
        'id' => $deliveryId,
    ]);
}

function sr_notification_process_email_delivery(PDO $pdo, array $site, array $delivery, array $settings, string $now, int $maxAttempts): array
{
    $deliveryId = (int) ($delivery['id'] ?? 0);
    $recipient = (string) ($delivery['recipient'] ?? '');
    $attemptCount = (int) ($delivery['attempt_count'] ?? 1);
    if ($deliveryId <= 0 || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        $status = sr_notification_mark_delivery_failed($pdo, $deliveryId, $now, 'invalid email recipient', $attemptCount, $maxAttempts);
        return ['status' => $status, 'sent' => 0, 'failed' => $status === 'dead' ? 0 : 1, 'dead' => $status === 'dead' ? 1 : 0];
    }

    if (empty($settings['email_channel_enabled'])) {
        $status = sr_notification_mark_delivery_failed($pdo, $deliveryId, $now, 'email channel disabled', $attemptCount, $maxAttempts);
        return ['status' => $status, 'sent' => 0, 'failed' => $status === 'dead' ? 0 : 1, 'dead' => $status === 'dead' ? 1 : 0];
    }

    $subject = sr_notification_clean_single_line((string) ($delivery['title'] ?? '알림'), 160);
    $body = (string) ($delivery['body_text'] ?? '');
    if ((string) ($delivery['body_format'] ?? 'plain') === 'html') {
        $body = trim(strip_tags($body));
    }
    $linkUrl = sr_notification_clean_link_url((string) ($delivery['link_url'] ?? ''));
    if ($linkUrl !== '') {
        $body .= ($body !== '' ? "\n\n" : '') . (sr_is_http_url($linkUrl) ? $linkUrl : sr_url($linkUrl));
    }

    $previousConfig = sr_runtime_config();
    $runnerConfig = $previousConfig;
    $runnerConfig['mail'] = sr_notification_mail_config_from_settings($settings);
    sr_set_runtime_config($runnerConfig);
    try {
        $sent = sr_send_mail($site, $recipient, $subject, $body);
    } finally {
        sr_set_runtime_config($previousConfig);
    }

    if ($sent) {
        sr_notification_mark_delivery_sent($pdo, $deliveryId, $now, 'email:' . (string) $deliveryId);
        return ['status' => 'sent', 'sent' => 1, 'failed' => 0, 'dead' => 0];
    }

    $status = sr_notification_mark_delivery_failed($pdo, $deliveryId, $now, 'email provider send failed', $attemptCount, $maxAttempts);
    return ['status' => $status, 'sent' => 0, 'failed' => $status === 'dead' ? 0 : 1, 'dead' => $status === 'dead' ? 1 : 0];
}

function sr_notification_member_external_push_payload(string $channel, array $delivery, array $site, string $endpoint): array
{
    $siteName = sr_notification_clean_single_line((string) ($site['site_name'] ?? $site['name'] ?? 'saanraan'), 80);
    $title = sr_notification_clean_single_line((string) ($delivery['title'] ?? '알림'), 160);
    $linkUrl = sr_notification_clean_link_url((string) ($delivery['link_url'] ?? ''));
    $lines = ['[' . $siteName . '] ' . $title, '사이트에서 내용을 확인해 주세요.'];
    if ($linkUrl !== '') {
        $lines[] = sr_is_http_url($linkUrl) ? $linkUrl : sr_url($linkUrl);
    }

    if ($channel === 'telegram_bot') {
        return [
            'chat_id' => $endpoint,
            'text' => implode("\n\n", $lines),
            'disable_web_page_preview' => true,
        ];
    }

    return ['text' => implode("\n\n", $lines)];
}

function sr_notification_member_push_delivery_context(PDO $pdo, array $delivery): ?array
{
    $deliveryId = (int) ($delivery['id'] ?? 0);
    $endpointId = sr_notification_delivery_endpoint_id((string) ($delivery['recipient'] ?? ''));
    $channel = (string) ($delivery['channel'] ?? '');
    if ($deliveryId <= 0 || $endpointId <= 0 || !in_array($channel, sr_notification_member_external_channel_keys(), true)) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT d.id AS delivery_id, d.notification_id, d.channel, d.attempt_count,
                n.account_id, n.audience, n.status AS notification_status, n.title, n.body_text, n.body_format, n.link_url,
                e.id AS endpoint_id, e.account_id AS endpoint_account_id, e.provider_key, e.recipient_type,
                e.endpoint_ciphertext, e.recipient_masked, e.status AS endpoint_status
         FROM sr_notification_deliveries d
         INNER JOIN sr_notifications n ON n.id = d.notification_id
         INNER JOIN sr_notification_push_endpoints e ON e.id = :endpoint_id
         WHERE d.id = :delivery_id
         LIMIT 1"
    );
    $stmt->execute([
        'endpoint_id' => $endpointId,
        'delivery_id' => $deliveryId,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_notification_process_member_external_push_delivery(PDO $pdo, array $site, array $delivery, array $settings, string $now, int $maxAttempts): array
{
    $deliveryId = (int) ($delivery['id'] ?? 0);
    $channel = (string) ($delivery['channel'] ?? '');
    $attemptCount = (int) ($delivery['attempt_count'] ?? 1);
    $context = sr_notification_member_push_delivery_context($pdo, $delivery);
    if ($deliveryId <= 0 || $context === null) {
        return ['status' => 'skipped', 'sent' => 0, 'failed' => 0, 'dead' => 0];
    }

    $cancelReason = '';
    if ((string) ($context['audience'] ?? '') !== 'account' || (int) ($context['account_id'] ?? 0) <= 0) {
        $cancelReason = 'member push supports account audience only';
    } elseif ((int) ($context['account_id'] ?? 0) !== (int) ($context['endpoint_account_id'] ?? 0)) {
        $cancelReason = 'member push endpoint ownership changed';
    } elseif ((string) ($context['notification_status'] ?? '') !== 'active') {
        $cancelReason = 'member push notification is not active';
    } elseif ((string) ($context['endpoint_status'] ?? '') !== 'active' || (string) ($context['recipient_type'] ?? '') !== 'personal') {
        $cancelReason = 'member push endpoint is not active';
    } elseif (!sr_notification_member_external_provider_is_ready($channel, $settings)) {
        $cancelReason = 'member push provider is not ready';
    }

    if ($cancelReason !== '') {
        sr_notification_mark_delivery_canceled($pdo, $deliveryId, $now, $cancelReason);
        return ['status' => 'canceled', 'sent' => 0, 'failed' => 0, 'dead' => 0, 'skipped' => 1];
    }

    $endpoint = sr_notification_secret_decrypt(
        (string) ($context['endpoint_ciphertext'] ?? ''),
        'notification-push-endpoint|' . $channel
    );
    if (!is_string($endpoint) || $endpoint === '' || ($channel === 'telegram_bot' && !sr_notification_telegram_chat_id_is_allowed($endpoint))) {
        sr_notification_mark_delivery_canceled($pdo, $deliveryId, $now, 'member push endpoint decrypt failed');
        return ['status' => 'canceled', 'sent' => 0, 'failed' => 0, 'dead' => 0, 'skipped' => 1];
    }

    $deliveryForPayload = array_merge($delivery, [
        'title' => (string) ($context['title'] ?? ''),
        'body_text' => (string) ($context['body_text'] ?? ''),
        'body_format' => (string) ($context['body_format'] ?? 'plain'),
        'link_url' => (string) ($context['link_url'] ?? ''),
    ]);
    $response = sr_notification_http_json_post(
        sr_notification_external_push_endpoint($channel, $settings),
        sr_notification_member_external_push_payload($channel, $deliveryForPayload, $site, $endpoint),
        (int) ($settings['email_timeout_seconds'] ?? 10)
    );
    $providerResult = sr_notification_external_push_response_result($channel, $response);
    if (!empty($providerResult['ok'])) {
        sr_notification_mark_delivery_sent($pdo, $deliveryId, $now, (string) ($providerResult['provider_message_id'] ?? $channel));
        $stmt = $pdo->prepare('UPDATE sr_notification_push_endpoints SET last_used_at = :last_used_at, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'last_used_at' => $now,
            'updated_at' => $now,
            'id' => (int) ($context['endpoint_id'] ?? 0),
        ]);
        return ['status' => 'sent', 'sent' => 1, 'failed' => 0, 'dead' => 0];
    }

    $status = sr_notification_mark_delivery_failed($pdo, $deliveryId, $now, (string) ($providerResult['error'] ?? $channel . ' failed'), $attemptCount, $maxAttempts);
    return ['status' => $status, 'sent' => 0, 'failed' => $status === 'dead' ? 0 : 1, 'dead' => $status === 'dead' ? 1 : 0];
}

function sr_notification_external_push_text(array $delivery, array $site): string
{
    $siteName = sr_notification_clean_single_line((string) ($site['site_name'] ?? $site['name'] ?? 'saanraan'), 80);
    $title = sr_notification_clean_single_line((string) ($delivery['title'] ?? '운영 알림'), 160);
    $body = trim(strip_tags((string) ($delivery['body_text'] ?? '')));
    $linkUrl = sr_notification_clean_link_url((string) ($delivery['link_url'] ?? ''));
    $lines = ['[' . $siteName . '] ' . $title];
    if ($body !== '') {
        $lines[] = sr_notification_clean_text($body, 1500);
    }
    if ($linkUrl !== '') {
        $lines[] = sr_is_http_url($linkUrl) ? $linkUrl : sr_url($linkUrl);
    }

    return implode("\n\n", $lines);
}

function sr_notification_external_push_payload(string $channel, array $delivery, array $site, array $settings): array
{
    $text = sr_notification_external_push_text($delivery, $site);
    if ($channel === 'discord_webhook') {
        return ['content' => $text];
    }
    if ($channel === 'telegram_bot') {
        return [
            'chat_id' => (string) ($settings['telegram_chat_id'] ?? ''),
            'text' => $text,
            'disable_web_page_preview' => true,
        ];
    }

    return ['text' => $text];
}

function sr_notification_http_json_post(string $url, array $payload, int $timeoutSeconds): array
{
    if (!sr_notification_webhook_url_is_allowed($url) || !ini_get('allow_url_fopen')) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'provider_unavailable'];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => sr_json_encode($payload),
            'timeout' => min(30, max(3, $timeoutSeconds)),
            'ignore_errors' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    foreach (($http_response_header ?? []) as $header) {
        if (preg_match('/\AHTTP\/\S+\s+(\d{3})\b/', (string) $header, $matches) === 1) {
            $status = (int) $matches[1];
            break;
        }
    }

    return [
        'ok' => is_string($body),
        'status' => $status,
        'body' => is_string($body) ? $body : '',
        'error' => is_string($body) ? '' : 'provider_unavailable',
    ];
}

function sr_notification_external_push_response_result(string $channel, array $response): array
{
    $status = (int) ($response['status'] ?? 0);
    $body = trim((string) ($response['body'] ?? ''));
    if ($channel === 'slack_webhook' && !empty($response['ok']) && $status >= 200 && $status < 300 && ($body === '' || strtolower($body) === 'ok')) {
        return ['ok' => true, 'provider_message_id' => 'slack_webhook', 'error' => ''];
    }

    if ($channel === 'discord_webhook' && !empty($response['ok']) && $status >= 200 && $status < 300) {
        return ['ok' => true, 'provider_message_id' => 'discord_webhook', 'error' => ''];
    }

    if ($channel === 'telegram_bot' && !empty($response['ok']) && $status >= 200 && $status < 300) {
        $decoded = json_decode($body, true);
        if (is_array($decoded) && !empty($decoded['ok'])) {
            $messageId = '';
            if (isset($decoded['result']) && is_array($decoded['result']) && isset($decoded['result']['message_id'])) {
                $messageId = 'telegram:' . (string) $decoded['result']['message_id'];
            }
            return ['ok' => true, 'provider_message_id' => $messageId !== '' ? $messageId : 'telegram_bot', 'error' => ''];
        }
    }

    $error = sr_notification_delivery_error_message($body !== '' ? $body : (string) ($response['error'] ?? $channel . ' failed'));
    return ['ok' => false, 'provider_message_id' => '', 'error' => $error];
}

function sr_notification_slack_webhook_response_result(array $response): array
{
    return sr_notification_external_push_response_result('slack_webhook', $response);
}

function sr_notification_external_push_endpoint(string $channel, array $settings): string
{
    if ($channel === 'telegram_bot') {
        $token = trim((string) ($settings['telegram_bot_token'] ?? ''));
        return sr_notification_telegram_bot_token_is_allowed($token) ? 'https://api.telegram.org/bot' . $token . '/sendMessage' : '';
    }

    $options = sr_notification_external_provider_options();
    $urlSetting = (string) ($options[$channel]['webhook_url_setting'] ?? '');
    return $urlSetting !== '' ? (string) ($settings[$urlSetting] ?? '') : '';
}

function sr_notification_external_provider_is_ready(string $channel, array $settings): bool
{
    $options = sr_notification_external_provider_options();
    if (!isset($options[$channel]) || empty($settings['external_push_enabled'])) {
        return false;
    }

    $enabledSetting = (string) ($options[$channel]['enabled_setting'] ?? '');
    if ($enabledSetting !== '' && empty($settings[$enabledSetting])) {
        return false;
    }

    if ($channel === 'telegram_bot') {
        return sr_notification_telegram_bot_token_is_allowed((string) ($settings['telegram_bot_token'] ?? ''))
            && sr_notification_telegram_chat_id_is_allowed((string) ($settings['telegram_chat_id'] ?? ''));
    }

    return sr_notification_webhook_url_is_allowed(sr_notification_external_push_endpoint($channel, $settings));
}

function sr_notification_process_external_push_delivery(PDO $pdo, array $site, array $delivery, array $settings, string $now, int $maxAttempts): array
{
    $deliveryId = (int) ($delivery['id'] ?? 0);
    $channel = (string) ($delivery['channel'] ?? '');
    $attemptCount = (int) ($delivery['attempt_count'] ?? 1);
    $failureMaxAttempts = (string) ($settings['external_push_failure_policy'] ?? 'retry') === 'dead' ? 1 : $maxAttempts;
    if ($deliveryId <= 0) {
        return ['status' => 'skipped', 'sent' => 0, 'failed' => 0, 'dead' => 0];
    }
    if (empty($settings['external_push_enabled'])) {
        $status = sr_notification_mark_delivery_failed($pdo, $deliveryId, $now, 'external push channel disabled', $attemptCount, $failureMaxAttempts);
        return ['status' => $status, 'sent' => 0, 'failed' => $status === 'dead' ? 0 : 1, 'dead' => $status === 'dead' ? 1 : 0];
    }

    if (!in_array($channel, sr_notification_admin_external_channel_keys(), true) || !sr_notification_external_provider_is_ready($channel, $settings)) {
        $status = sr_notification_mark_delivery_failed($pdo, $deliveryId, $now, $channel . ' provider missing or invalid', $attemptCount, $failureMaxAttempts);
        return ['status' => $status, 'sent' => 0, 'failed' => $status === 'dead' ? 0 : 1, 'dead' => $status === 'dead' ? 1 : 0];
    }

    $response = sr_notification_http_json_post(
        sr_notification_external_push_endpoint($channel, $settings),
        sr_notification_external_push_payload($channel, $delivery, $site, $settings),
        (int) ($settings['email_timeout_seconds'] ?? 10)
    );
    $providerResult = sr_notification_external_push_response_result($channel, $response);
    if (!empty($providerResult['ok'])) {
        sr_notification_mark_delivery_sent($pdo, $deliveryId, $now, (string) ($providerResult['provider_message_id'] ?? $channel));
        return ['status' => 'sent', 'sent' => 1, 'failed' => 0, 'dead' => 0];
    }

    $status = sr_notification_mark_delivery_failed($pdo, $deliveryId, $now, (string) ($providerResult['error'] ?? $channel . ' failed'), $attemptCount, $failureMaxAttempts);
    return ['status' => $status, 'sent' => 0, 'failed' => $status === 'dead' ? 0 : 1, 'dead' => $status === 'dead' ? 1 : 0];
}

function sr_notification_process_slack_webhook_delivery(PDO $pdo, array $site, array $delivery, array $settings, string $now, int $maxAttempts): array
{
    $delivery['channel'] = 'slack_webhook';
    return sr_notification_process_external_push_delivery($pdo, $site, $delivery, $settings, $now, $maxAttempts);
}

function sr_notification_process_delivery(PDO $pdo, array $site, array $delivery, array $settings, string $now, int $maxAttempts): array
{
    $channel = (string) ($delivery['channel'] ?? '');
    if ($channel === 'email') {
        return sr_notification_process_email_delivery($pdo, $site, $delivery, $settings, $now, $maxAttempts);
    }
    if (sr_notification_delivery_endpoint_id((string) ($delivery['recipient'] ?? '')) > 0
        && in_array($channel, sr_notification_member_external_channel_keys(), true)
    ) {
        return sr_notification_process_member_external_push_delivery($pdo, $site, $delivery, $settings, $now, $maxAttempts);
    }
    if (in_array($channel, sr_notification_admin_external_channel_keys(), true)) {
        return sr_notification_process_external_push_delivery($pdo, $site, $delivery, $settings, $now, $maxAttempts);
    }

    $status = sr_notification_mark_delivery_failed(
        $pdo,
        (int) ($delivery['id'] ?? 0),
        $now,
        'unsupported delivery channel',
        (int) ($delivery['attempt_count'] ?? 1),
        $maxAttempts
    );

    return ['status' => $status, 'sent' => 0, 'failed' => $status === 'dead' ? 0 : 1, 'dead' => $status === 'dead' ? 1 : 0];
}

function sr_notification_run_delivery_batch(PDO $pdo, array $site, int $batchSize = 5, ?string $lockId = null): array
{
    $batchSize = max(1, min(50, $batchSize));
    $settings = sr_notification_settings($pdo);
    $maxAttempts = max(1, min(20, (int) ($settings['delivery_max_attempts'] ?? 5)));
    $lockTimeoutSeconds = max(30, min(3600, (int) ($settings['delivery_lock_timeout_seconds'] ?? 300)));
    $lockId = $lockId !== null && $lockId !== '' ? sr_notification_clean_single_line($lockId, 80) : sr_notification_delivery_runner_lock_id();
    $result = [
        'claimed' => 0,
        'sent' => 0,
        'failed' => 0,
        'dead' => 0,
        'skipped' => 0,
    ];

    for ($i = 0; $i < $batchSize; $i++) {
        $now = sr_now();
        $delivery = sr_notification_claim_delivery($pdo, $lockId, $now, $lockTimeoutSeconds, array_merge(['email'], sr_notification_admin_external_channel_keys()));
        if ($delivery === null) {
            break;
        }

        $result['claimed']++;
        try {
            $deliveryResult = sr_notification_process_delivery($pdo, $site, $delivery, $settings, $now, $maxAttempts);
            $result['sent'] += (int) ($deliveryResult['sent'] ?? 0);
            $result['failed'] += (int) ($deliveryResult['failed'] ?? 0);
            $result['dead'] += (int) ($deliveryResult['dead'] ?? 0);
            $result['skipped'] += (int) ($deliveryResult['skipped'] ?? 0);
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'notification_delivery_runner');
            $status = sr_notification_mark_delivery_failed(
                $pdo,
                (int) ($delivery['id'] ?? 0),
                sr_now(),
                'runner exception',
                (int) ($delivery['attempt_count'] ?? 1),
                $maxAttempts
            );
            if ($status === 'dead') {
                $result['dead']++;
            } else {
                $result['failed']++;
            }
        }
    }

    return $result;
}

function sr_notification_save_module_runtime_setting(PDO $pdo, string $settingKey, string $settingValue, string $valueType = 'string'): void
{
    if (preg_match('/\A[a-z0-9_.-]{1,120}\z/', $settingKey) !== 1) {
        return;
    }

    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'notification' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        return;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_module_settings
            (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'module_id' => (int) $module['id'],
        'setting_key' => $settingKey,
        'setting_value' => $settingValue,
        'value_type' => $valueType,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    sr_clear_module_settings_cache('notification');
}

function sr_notification_register_web_delivery_runner(PDO $pdo, array $site, string $method, string $path): void
{
    if ($method !== 'GET') {
        return;
    }
    if ($path === '/manifest.webmanifest' || $path === '/service-worker.js') {
        return;
    }

    $settings = sr_notification_settings($pdo);
    if (empty($settings['delivery_web_runner_enabled'])) {
        return;
    }

    $lastRunAt = (string) sr_module_setting($pdo, 'notification', 'delivery_last_web_runner_at', '');
    $lastRunTime = $lastRunAt === '' ? false : strtotime($lastRunAt);
    $interval = max(10, (int) ($settings['delivery_web_runner_interval_seconds'] ?? 60));
    if ($lastRunTime !== false && time() - $lastRunTime < $interval) {
        return;
    }

    $batchSize = max(1, min(5, (int) ($settings['delivery_web_runner_batch_size'] ?? 1)));
    register_shutdown_function(static function () use ($pdo, $site, $batchSize): void {
        try {
            sr_notification_save_module_runtime_setting($pdo, 'delivery_last_web_runner_at', sr_now(), 'string');
            sr_notification_run_delivery_batch($pdo, $site, $batchSize, sr_notification_delivery_runner_lock_id());
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'notification_web_runner_failed');
        }
    });
}

function sr_notification_update_delivery_status(PDO $pdo, int $deliveryId, string $targetStatus, string $now): array
{
    $stmt = $pdo->prepare("SELECT id, status FROM sr_notification_deliveries WHERE id = :id AND channel <> 'site' LIMIT 1");
    $stmt->execute(['id' => $deliveryId]);
    $deliveryRow = $stmt->fetch();
    if (!is_array($deliveryRow)) {
        return [
            'ok' => false,
            'error' => 'not_found',
            'before_status' => '',
            'status' => $targetStatus,
            'operation' => '',
        ];
    }

    return sr_notification_update_delivery_status_row($pdo, $deliveryId, (string) ($deliveryRow['status'] ?? ''), $targetStatus, $now);
}

function sr_notification_body_html(array $notification): string
{
    return sr_body_text_html($notification);
}

function sr_notification_time_html(string $value): string
{
    return sr_relative_time_html($value);
}

function sr_notification_clean_link_url(string $value): string
{
    $value = trim($value);
    if ($value === '' || sr_is_safe_relative_url($value) || sr_is_http_url($value)) {
        return $value;
    }

    return '';
}

function sr_notification_read_token(int $notificationId, int $accountId): string
{
    if ($notificationId <= 0 || $accountId <= 0) {
        return '';
    }

    try {
        return substr(sr_hmac_hash('notification-read|' . $accountId . '|' . $notificationId, sr_runtime_config()), 0, 32);
    } catch (Throwable) {
        return '';
    }
}

function sr_notification_read_token_is_valid(int $notificationId, int $accountId, string $token): bool
{
    if ($token === '' || preg_match('/\A[a-f0-9]{32}\z/', $token) !== 1) {
        return false;
    }

    $expected = sr_notification_read_token($notificationId, $accountId);
    return $expected !== '' && hash_equals($expected, $token);
}

function sr_notification_read_redirect_url(int $notificationId, int $accountId): string
{
    if ($notificationId <= 0) {
        return sr_url('/account/notifications');
    }

    $query = 'id=' . rawurlencode((string) $notificationId);
    $token = sr_notification_read_token($notificationId, $accountId);
    if ($token === '') {
        return sr_url('/account/notifications');
    }

    $query .= '&token=' . rawurlencode($token);
    return sr_url('/account/notifications/read?' . $query);
}

function sr_notification_link_attributes(string $url, int $notificationId = 0, bool $markRead = false, int $accountId = 0): string
{
    $url = sr_notification_clean_link_url($url);
    $canMarkRead = $markRead && $notificationId > 0 && $accountId > 0 && sr_notification_read_token($notificationId, $accountId) !== '';
    if ($url === '' && !$canMarkRead) {
        return '';
    }

    $href = $url === '' ? sr_url('/account/notifications') : (sr_is_http_url($url) ? $url : sr_url($url));
    if ($canMarkRead) {
        $href = sr_notification_read_redirect_url($notificationId, $accountId);
    }

    $attributes = ' href="' . sr_e($href) . '"';
    if (!$markRead && sr_is_http_url($url)) {
        $attributes .= ' target="_blank" rel="noopener noreferrer"';
    }

    return $attributes;
}

function sr_notification_item_link_attributes(array $notification, int $accountId, bool $markRead = false): string
{
    return sr_notification_link_attributes(
        (string) ($notification['link_url'] ?? ''),
        (int) ($notification['id'] ?? 0),
        $markRead,
        $accountId
    );
}

function sr_notification_readable_notification(PDO $pdo, int $notificationId, int $accountId): ?array
{
    if ($notificationId <= 0 || $accountId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, audience, link_url
         FROM sr_notifications
         WHERE id = :id
           AND (account_id = :account_id OR audience = 'all')
         LIMIT 1"
    );
    $stmt->execute([
        'id' => $notificationId,
        'account_id' => $accountId,
    ]);
    $notification = $stmt->fetch();

    return is_array($notification) ? $notification : null;
}

function sr_notification_mark_read(PDO $pdo, int $notificationId, int $accountId): bool
{
    $notification = sr_notification_readable_notification($pdo, $notificationId, $accountId);
    if ($notification === null) {
        return false;
    }

    $now = sr_now();
    if ((string) $notification['audience'] === 'all') {
        $driver = '';
        try {
            $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable) {
            $driver = '';
        }

        $upsertClause = 'ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)';
        if ($driver === 'sqlite') {
            $upsertClause = 'ON CONFLICT(notification_id, account_id) DO UPDATE SET read_at = excluded.read_at';
        }

        $stmt = $pdo->prepare(
            'INSERT INTO sr_notification_reads (notification_id, account_id, read_at)
             VALUES (:notification_id, :account_id, :read_at)
             ' . $upsertClause
        );
        $stmt->execute([
            'notification_id' => $notificationId,
            'account_id' => $accountId,
            'read_at' => $now,
        ]);

        return true;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_notifications
         SET read_at = :read_at, updated_at = :updated_at
         WHERE id = :id AND account_id = :account_id'
    );
    $stmt->execute([
        'read_at' => $now,
        'updated_at' => $now,
        'id' => $notificationId,
        'account_id' => $accountId,
    ]);

    return true;
}

function sr_notification_mark_read_redirect_link(PDO $pdo, int $notificationId, int $accountId, string $token): string
{
    if (!sr_notification_read_token_is_valid($notificationId, $accountId, $token)) {
        return '';
    }

    $notification = sr_notification_readable_notification($pdo, $notificationId, $accountId);
    if ($notification === null) {
        return '';
    }

    if (!sr_notification_mark_read($pdo, $notificationId, $accountId)) {
        return '';
    }

    return sr_notification_clean_link_url((string) ($notification['link_url'] ?? ''));
}

function sr_notification_public_header_summary(PDO $pdo, int $accountId, int $limit = 5): array
{
    if ($accountId <= 0) {
        return ['unread' => 0, 'items' => []];
    }

    $limit = max(1, min(10, $limit));

    try {
        $stmt = $pdo->prepare(
            "SELECT n.id, n.title, n.body_text, n.body_format, n.link_url,
                    CASE WHEN COALESCE(n.read_at, r.read_at) IS NULL THEN 'unread' ELSE 'read' END AS status,
                    COALESCE(n.read_at, r.read_at) AS read_at,
                    n.created_at
             FROM sr_notifications n
             LEFT JOIN sr_notification_reads r ON r.notification_id = n.id AND r.account_id = :read_account_id
             WHERE (n.account_id = :account_id OR n.audience = 'all')
               AND COALESCE(n.read_at, r.read_at) IS NULL
             ORDER BY n.id DESC
             LIMIT " . $limit
        );
        $stmt->execute([
            'read_account_id' => $accountId,
            'account_id' => $accountId,
        ]);
        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $items[] = $row;
            }
        }

        $stmt = $pdo->prepare(
            "SELECT SUM(CASE WHEN COALESCE(n.read_at, r.read_at) IS NULL THEN 1 ELSE 0 END) AS unread_count
             FROM sr_notifications n
             LEFT JOIN sr_notification_reads r ON r.notification_id = n.id AND r.account_id = :read_account_id
             WHERE n.account_id = :account_id OR n.audience = 'all'"
        );
        $stmt->execute([
            'read_account_id' => $accountId,
            'account_id' => $accountId,
        ]);
        $summary = $stmt->fetch();
    } catch (Throwable) {
        return ['unread' => 0, 'items' => []];
    }

    return [
        'unread' => is_array($summary) ? (int) ($summary['unread_count'] ?? 0) : 0,
        'items' => $items,
    ];
}

function sr_notification_admin_notification_tables_exist(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM sr_admin_notifications LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_admin_notification_reads LIMIT 1');
    } catch (Throwable) {
        return false;
    }

    return true;
}

function sr_notification_admin_severities(): array
{
    return ['info', 'warning', 'danger'];
}

function sr_notification_admin_operation_statuses(): array
{
    return ['open', 'processed', 'archived'];
}

function sr_notification_admin_source_label(string $moduleKey, string $eventKey): string
{
    $moduleKey = trim($moduleKey);
    $eventKey = trim($eventKey);
    $moduleLabel = '';

    if ($moduleKey !== '' && sr_is_safe_module_key($moduleKey)) {
        $metadata = sr_module_metadata($moduleKey);
        $moduleName = trim((string) ($metadata['name'] ?? ''));
        $moduleLabel = $moduleName !== '' && function_exists('sr_admin_module_name_label')
            ? sr_admin_module_name_label($moduleName)
            : $moduleName;
    }
    if ($moduleLabel === '') {
        $moduleLabel = $moduleKey;
    }

    $eventLabels = [
        'content.author_application.created' => sr_t('notification::admin.event.content.author_application.created'),
        'content.storage_cleanup.retry_failed' => sr_t('notification::admin.event.content.storage_cleanup.retry_failed'),
        'community.report.created' => sr_t('notification::admin.event.community.report.created'),
        'community.storage_cleanup.retry_failed' => sr_t('notification::admin.event.community.storage_cleanup.retry_failed'),
        'notification.delivery.failed' => sr_t('notification::admin.event.notification.delivery.failed'),
        'notification.ui_dummy.created' => sr_t('notification::admin.event.notification.ui_dummy.created'),
        'privacy.request.created' => sr_t('notification::admin.event.privacy.request.created'),
    ];
    $eventLookupKey = $moduleKey !== '' ? $moduleKey . '.' . $eventKey : $eventKey;
    $eventLabel = (string) ($eventLabels[$eventLookupKey] ?? $eventKey);
    if ($eventLabel === $eventKey && $eventKey !== '' && function_exists('sr_admin_event_type_label')) {
        $eventLabel = sr_admin_event_type_label($moduleKey !== '' ? $moduleKey . '.' . $eventKey : $eventKey);
        if ($moduleLabel !== '' && str_starts_with($eventLabel, $moduleLabel . ' ')) {
            $eventLabel = trim(substr($eventLabel, strlen($moduleLabel) + 1));
        }
    }

    $parts = array_values(array_filter([$moduleLabel, $eventLabel], static fn (string $value): bool => $value !== ''));

    return $parts === [] ? '-' : implode(' / ', $parts);
}

function sr_notification_admin_clean_severity(string $value): string
{
    return in_array($value, sr_notification_admin_severities(), true) ? $value : 'info';
}

function sr_notification_admin_clean_status(string $value): string
{
    return in_array($value, sr_notification_admin_operation_statuses(), true) ? $value : 'open';
}

function sr_notification_admin_clean_permission_action(string $value): string
{
    if (!function_exists('sr_admin_normalize_permission_action')) {
        require_once SR_ROOT . '/modules/admin/helpers.php';
    }

    $action = sr_admin_normalize_permission_action($value);
    return $action !== '' ? $action : 'view';
}

function sr_notification_admin_clean_permission_path(string $value): string
{
    if (!function_exists('sr_admin_normalize_permission_path')) {
        require_once SR_ROOT . '/modules/admin/helpers.php';
    }

    return sr_admin_normalize_permission_path($value);
}

function sr_notification_admin_clean_action_url(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (!sr_is_safe_relative_url($value)) {
        return '';
    }

    $path = parse_url($value, PHP_URL_PATH);
    if (!is_string($path) || ($path !== '/admin' && !str_starts_with($path, '/admin/'))) {
        return '';
    }

    return $value;
}

function sr_notification_admin_url_with_query(string $url, array $queryValues): string
{
    $url = sr_notification_admin_clean_action_url($url);
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    $path = is_array($parts) && isset($parts['path']) && is_string($parts['path']) ? $parts['path'] : '';
    if ($path === '') {
        return $url;
    }

    $query = [];
    if (is_array($parts) && isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '') {
        parse_str($parts['query'], $query);
    }

    foreach ($queryValues as $key => $value) {
        $key = (string) $key;
        if ($key === '' || is_array($value)) {
            continue;
        }
        $query[$key] = (string) $value;
    }

    $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $fragment = is_array($parts) && isset($parts['fragment']) && is_string($parts['fragment']) && $parts['fragment'] !== ''
        ? '#' . rawurlencode($parts['fragment'])
        : '';

    return $path . ($queryString !== '' ? '?' . $queryString : '') . $fragment;
}

function sr_notification_admin_target_action_url(string $actionUrl, string $targetType, string $targetId): string
{
    $actionUrl = sr_notification_admin_clean_action_url($actionUrl);
    if ($actionUrl === '') {
        return '';
    }

    $targetType = sr_notification_clean_single_line($targetType, 80);
    $targetId = sr_notification_clean_single_line($targetId, 80);
    if ($targetType === '' || preg_match('/\A[1-9][0-9]*\z/', $targetId) !== 1) {
        return $actionUrl;
    }

    $path = parse_url($actionUrl, PHP_URL_PATH);
    $filterParam = '';
    if ($targetType === 'community_report' && $path === '/admin/community/reports') {
        $filterParam = 'report_id';
    } elseif ($targetType === 'privacy_request' && $path === '/admin/privacy-requests') {
        $filterParam = 'request_id';
    } elseif ($targetType === 'content_author_application' && $path === '/admin/content/author-applications') {
        $filterParam = 'application_id';
    } elseif ($targetType === 'notification_delivery' && $path === '/admin/notification-deliveries') {
        $filterParam = 'delivery_id';
    } elseif ($targetType === 'admin_notification' && $path === '/admin/admin-notifications') {
        $filterParam = 'notification_id';
    }

    return $filterParam !== '' ? sr_notification_admin_url_with_query($actionUrl, [$filterParam => $targetId]) : $actionUrl;
}

function sr_notification_admin_can_view(PDO $pdo, int $accountId, array $notification): bool
{
    if ($accountId <= 0) {
        return false;
    }

    if (!function_exists('sr_admin_has_permission')) {
        require_once SR_ROOT . '/modules/admin/helpers.php';
    }

    $permissionPath = sr_notification_admin_clean_permission_path((string) ($notification['permission_path'] ?? ''));
    $permissionAction = sr_notification_admin_clean_permission_action((string) ($notification['permission_action'] ?? 'view'));
    if ($permissionPath === '') {
        return sr_admin_has_admin_access($pdo, $accountId);
    }

    return sr_admin_has_permission($pdo, $accountId, $permissionPath, $permissionAction);
}

function sr_notification_admin_visible_sql(PDO $pdo, int $accountId): array
{
    if (!function_exists('sr_admin_current_permission_keys')) {
        require_once SR_ROOT . '/modules/admin/helpers.php';
    }

    if (sr_admin_is_owner($pdo, $accountId)) {
        return ['1 = 1', []];
    }

    $permissionKeys = sr_admin_current_permission_keys($pdo, $accountId);
    $conditions = ["n.permission_path = ''"];
    $params = [];
    $index = 0;
    foreach ($permissionKeys as $permissionKey) {
        [$permissionPath, $permissionAction] = sr_admin_parse_permission_token($permissionKey);
        if ($permissionPath === '' || $permissionAction === '') {
            continue;
        }
        $pathKey = 'visible_path_' . (string) $index;
        $actionKey = 'visible_action_' . (string) $index;
        $conditions[] = '(n.permission_path = :' . $pathKey . ' AND n.permission_action = :' . $actionKey . ')';
        $params[$pathKey] = $permissionPath;
        $params[$actionKey] = $permissionAction;
        if ($permissionAction !== 'view') {
            $viewPathKey = 'visible_view_path_' . (string) $index;
            $conditions[] = '(n.permission_path = :' . $viewPathKey . ' AND n.permission_action = \'view\')';
            $params[$viewPathKey] = $permissionPath;
        }
        $index++;
    }

    return ['(' . implode(' OR ', $conditions) . ')', $params];
}

function sr_notification_admin_dedupe_key(array $data): string
{
    $dedupeKey = sr_notification_clean_single_line((string) ($data['dedupe_key'] ?? ''), 190);
    if ($dedupeKey !== '') {
        return $dedupeKey;
    }

    $parts = [
        (string) ($data['source_module_key'] ?? ''),
        (string) ($data['event_key'] ?? ''),
        (string) ($data['target_type'] ?? ''),
        (string) ($data['target_id'] ?? ''),
    ];
    $base = implode('|', array_map('trim', $parts));
    if (trim($base, '| ') === '') {
        $base = (string) ($data['title'] ?? '') . '|' . (string) ($data['action_url'] ?? '');
    }

    return substr(hash('sha256', $base), 0, 48);
}

function sr_notification_create_admin_notification(PDO $pdo, array $data): ?int
{
    if (!sr_notification_admin_notification_tables_exist($pdo)) {
        return null;
    }

    $title = sr_notification_clean_single_line((string) ($data['title'] ?? ''), 160);
    if ($title === '') {
        return null;
    }

    $targetType = sr_notification_clean_single_line((string) ($data['target_type'] ?? ''), 80);
    $targetId = sr_notification_clean_single_line((string) ($data['target_id'] ?? ''), 80);
    $actionUrl = sr_notification_admin_clean_action_url((string) ($data['action_url'] ?? ''));
    if (trim((string) ($data['action_url'] ?? '')) !== '' && $actionUrl === '') {
        return null;
    }

    $sourceModuleKey = sr_notification_clean_single_line((string) ($data['source_module_key'] ?? ''), 60);
    if ($sourceModuleKey !== '' && !sr_is_safe_module_key($sourceModuleKey)) {
        $sourceModuleKey = '';
    }
    $eventKey = sr_notification_clean_single_line((string) ($data['event_key'] ?? ''), 120);
    if ($eventKey !== '' && preg_match('/\A[a-z0-9_.-]{1,120}\z/', $eventKey) !== 1) {
        $eventKey = '';
    }
    $actionUrl = sr_notification_admin_target_action_url($actionUrl, $targetType, $targetId);

    $permissionPath = sr_notification_admin_clean_permission_path((string) ($data['permission_path'] ?? ''));
    $permissionAction = sr_notification_admin_clean_permission_action((string) ($data['permission_action'] ?? 'view'));
    $dedupeKey = sr_notification_admin_dedupe_key(array_merge($data, [
        'source_module_key' => $sourceModuleKey,
        'event_key' => $eventKey,
        'target_type' => $targetType,
        'target_id' => $targetId,
    ]));
    $now = sr_now();

    $stmt = $pdo->prepare(
        'INSERT INTO sr_admin_notifications
            (title, body_text, severity, source_module_key, event_key, target_type, target_id, action_url,
             permission_path, permission_action, status, dedupe_key, occurrence_count, created_by_account_id,
             processed_by_account_id, processed_at, archived_at, last_occurred_at, created_at, updated_at)
         VALUES
            (:title, :body_text, :severity, :source_module_key, :event_key, :target_type, :target_id, :action_url,
             :permission_path, :permission_action, \'open\', :dedupe_key, 1, :created_by_account_id,
             NULL, NULL, NULL, :last_occurred_at, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            body_text = VALUES(body_text),
            severity = VALUES(severity),
            action_url = VALUES(action_url),
            permission_path = VALUES(permission_path),
            permission_action = VALUES(permission_action),
            status = \'open\',
            processed_by_account_id = NULL,
            processed_at = NULL,
            archived_at = NULL,
            occurrence_count = occurrence_count + 1,
            last_occurred_at = VALUES(last_occurred_at),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'title' => $title,
        'body_text' => sr_notification_clean_text((string) ($data['body_text'] ?? ''), 2000),
        'severity' => sr_notification_admin_clean_severity((string) ($data['severity'] ?? 'info')),
        'source_module_key' => $sourceModuleKey,
        'event_key' => $eventKey,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'action_url' => $actionUrl,
        'permission_path' => $permissionPath,
        'permission_action' => $permissionAction,
        'dedupe_key' => $dedupeKey,
        'created_by_account_id' => isset($data['created_by_account_id']) && (int) $data['created_by_account_id'] > 0 ? (int) $data['created_by_account_id'] : null,
        'last_occurred_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $stmt = $pdo->prepare('SELECT id FROM sr_admin_notifications WHERE dedupe_key = :dedupe_key LIMIT 1');
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $id = (int) $stmt->fetchColumn();
    if ($id > 0) {
        $stmt = $pdo->prepare('DELETE FROM sr_admin_notification_reads WHERE notification_id = :notification_id');
        $stmt->execute(['notification_id' => $id]);
        sr_notification_queue_admin_external_deliveries($pdo, $id);
    }

    return $id > 0 ? $id : null;
}

function sr_notification_admin_filters(array $allowedStatuses, array $allowedSeverities): array
{
    $field = sr_get_string('field', 20);
    if (!in_array($field, ['all', 'title', 'body', 'source', 'target'], true)) {
        $field = 'all';
    }

    return [
        'status' => sr_admin_get_allowed_single_array('status', $allowedStatuses, 30),
        'severity' => sr_admin_get_allowed_single_array('severity', $allowedSeverities, 30),
        'notification_id' => sr_admin_get_positive_int('notification_id'),
        'field' => $field,
        'q' => trim(sr_get_string('q', 120)),
    ];
}

function sr_notification_admin_query_parts(PDO $pdo, int $accountId, array $filters = []): array
{
    [$visibleCondition, $visibleParams] = sr_notification_admin_visible_sql($pdo, $accountId);
    $where = [$visibleCondition];
    $params = $visibleParams;

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('n.status', 'admin_notification_status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }
    if (($filters['severity'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('n.severity', 'admin_notification_severity', $filters['severity']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }
    if ((int) ($filters['notification_id'] ?? 0) > 0) {
        $where[] = 'n.id = :admin_notification_id';
        $params['admin_notification_id'] = (int) $filters['notification_id'];
    }
    if (($filters['read'] ?? '') === 'unread') {
        $where[] = 'r.read_at IS NULL';
    } elseif (($filters['read'] ?? '') === 'read') {
        $where[] = 'r.read_at IS NOT NULL';
    }

    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $field = (string) ($filters['field'] ?? 'all');
        if ($field === 'title') {
            $where[] = 'n.title LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'body') {
            $where[] = 'n.body_text LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'source') {
            $where[] = '(n.source_module_key LIKE :keyword OR n.event_key LIKE :keyword)';
            $params['keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'target') {
            $where[] = '(n.target_type LIKE :keyword OR n.target_id LIKE :keyword)';
            $params['keyword'] = '%' . $keyword . '%';
        } else {
            $where[] = '(n.title LIKE :keyword_title OR n.body_text LIKE :keyword_body OR n.source_module_key LIKE :keyword_source OR n.event_key LIKE :keyword_event OR n.target_type LIKE :keyword_target OR n.target_id LIKE :keyword_target_id)';
            $params['keyword_title'] = '%' . $keyword . '%';
            $params['keyword_body'] = '%' . $keyword . '%';
            $params['keyword_source'] = '%' . $keyword . '%';
            $params['keyword_event'] = '%' . $keyword . '%';
            $params['keyword_target'] = '%' . $keyword . '%';
            $params['keyword_target_id'] = '%' . $keyword . '%';
        }
    }

    return ['where_sql' => 'WHERE ' . implode(' AND ', $where), 'params' => $params];
}

function sr_notification_admin_count(PDO $pdo, int $accountId, array $filters = []): int
{
    if (!sr_notification_admin_notification_tables_exist($pdo)) {
        return 0;
    }

    $queryParts = sr_notification_admin_query_parts($pdo, $accountId, $filters);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS count_value
         FROM sr_admin_notifications n
         LEFT JOIN sr_admin_notification_reads r ON r.notification_id = n.id AND r.account_id = :read_account_id
         ' . $queryParts['where_sql']
    );
    foreach (array_merge($queryParts['params'], ['read_account_id' => $accountId]) as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_notification_admin_rows(PDO $pdo, int $accountId, array $filters = [], int $limit = 50, int $offset = 0): array
{
    if (!sr_notification_admin_notification_tables_exist($pdo)) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $queryParts = sr_notification_admin_query_parts($pdo, $accountId, $filters);
    $stmt = $pdo->prepare(
        'SELECT n.*, r.read_at, r.acknowledged_at
         FROM sr_admin_notifications n
         LEFT JOIN sr_admin_notification_reads r ON r.notification_id = n.id AND r.account_id = :read_account_id
         ' . $queryParts['where_sql'] . '
         ORDER BY
            CASE n.status WHEN \'open\' THEN 0 WHEN \'processed\' THEN 1 ELSE 2 END ASC,
            n.last_occurred_at DESC,
            n.id DESC
         LIMIT :limit_value OFFSET :offset_value'
    );
    foreach (array_merge($queryParts['params'], ['read_account_id' => $accountId]) as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset_value', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_notification_admin_operation_status_counts(PDO $pdo, int $accountId, array $allowedStatuses): array
{
    $counts = ['total' => 0];
    foreach ($allowedStatuses as $status) {
        $counts[$status] = 0;
    }
    if (!sr_notification_admin_notification_tables_exist($pdo)) {
        return $counts;
    }

    [$visibleCondition, $visibleParams] = sr_notification_admin_visible_sql($pdo, $accountId);
    $stmt = $pdo->prepare('SELECT n.status, COUNT(*) AS count_value FROM sr_admin_notifications n WHERE ' . $visibleCondition . ' GROUP BY n.status');
    $stmt->execute($visibleParams);
    foreach ($stmt->fetchAll() as $row) {
        $status = (string) ($row['status'] ?? '');
        $count = (int) ($row['count_value'] ?? 0);
        if (array_key_exists($status, $counts)) {
            $counts[$status] = $count;
        }
        $counts['total'] += $count;
    }

    return $counts;
}

function sr_notification_admin_header_summary(PDO $pdo, int $accountId, int $limit = 5): array
{
    $filters = ['status' => ['open'], 'severity' => [], 'read' => 'unread', 'field' => 'all', 'q' => ''];
    $limit = max(1, min(10, $limit));
    $unreadCount = sr_notification_admin_count($pdo, $accountId, $filters);

    return [
        'open_count' => $unreadCount,
        'unread_count' => $unreadCount,
        'items' => sr_notification_admin_rows($pdo, $accountId, $filters, $limit, 0),
        'url' => sr_url('/admin/admin-notifications'),
    ];
}

function sr_notification_admin_row(PDO $pdo, int $notificationId, int $accountId): ?array
{
    if ($notificationId <= 0 || !sr_notification_admin_notification_tables_exist($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_admin_notifications WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $notificationId]);
    $row = $stmt->fetch();
    if (!is_array($row) || !sr_notification_admin_can_view($pdo, $accountId, $row)) {
        return null;
    }

    return $row;
}

function sr_notification_admin_mark_read(PDO $pdo, int $notificationId, int $accountId, bool $acknowledge = false): bool
{
    if (sr_notification_admin_row($pdo, $notificationId, $accountId) === null) {
        return false;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_admin_notification_reads
            (notification_id, account_id, read_at, acknowledged_at, created_at, updated_at)
         VALUES
            (:notification_id, :account_id, :read_at, :acknowledged_at, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            read_at = COALESCE(read_at, VALUES(read_at)),
            acknowledged_at = IF(VALUES(acknowledged_at) IS NULL, acknowledged_at, COALESCE(acknowledged_at, VALUES(acknowledged_at))),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'notification_id' => $notificationId,
        'account_id' => $accountId,
        'read_at' => $now,
        'acknowledged_at' => $acknowledge ? $now : null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return true;
}

function sr_notification_admin_mark_unread(PDO $pdo, int $notificationId, int $accountId): bool
{
    if (sr_notification_admin_row($pdo, $notificationId, $accountId) === null) {
        return false;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM sr_admin_notification_reads
         WHERE notification_id = :notification_id
           AND account_id = :account_id'
    );
    $stmt->execute([
        'notification_id' => $notificationId,
        'account_id' => $accountId,
    ]);

    return true;
}

function sr_notification_admin_set_status(PDO $pdo, int $notificationId, int $accountId, string $status): bool
{
    $status = sr_notification_admin_clean_status($status);
    $row = sr_notification_admin_row($pdo, $notificationId, $accountId);
    if ($row === null) {
        return false;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'UPDATE sr_admin_notifications
         SET status = :status,
             processed_by_account_id = :processed_by_account_id,
             processed_at = :processed_at,
             archived_at = :archived_at,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'status' => $status,
        'processed_by_account_id' => $status === 'processed' ? $accountId : ($status === 'open' ? null : ($row['processed_by_account_id'] ?? null)),
        'processed_at' => $status === 'processed' ? $now : ($status === 'open' ? null : ($row['processed_at'] ?? null)),
        'archived_at' => $status === 'archived' ? $now : ($status === 'open' ? null : ($row['archived_at'] ?? null)),
        'updated_at' => $now,
        'id' => $notificationId,
    ]);

    return true;
}

function sr_notification_allowed_channels(): array
{
    return ['site', 'email', 'slack_webhook', 'discord_webhook', 'telegram_bot', 'sms', 'alimtalk'];
}

function sr_notification_default_settings(): array
{
    return [
        'email_channel_enabled' => true,
        'email_transport' => 'php_mail',
        'email_from_email' => '',
        'email_from_name' => '',
        'email_smtp_host' => '',
        'email_smtp_port' => 587,
        'email_smtp_encryption' => 'tls',
        'email_smtp_username' => '',
        'email_smtp_password' => '',
        'email_timeout_seconds' => 10,
        'email_http_api_endpoint' => '',
        'email_http_api_bearer_token' => '',
        'external_push_enabled' => false,
        'slack_webhook_enabled' => true,
        'slack_webhook_url' => '',
        'slack_channel_label' => '운영 알림',
        'discord_webhook_enabled' => false,
        'discord_webhook_url' => '',
        'discord_channel_label' => '운영 알림',
        'telegram_bot_enabled' => false,
        'telegram_bot_token' => '',
        'telegram_chat_id' => '',
        'telegram_channel_label' => '운영 알림',
        'external_push_failure_policy' => 'retry',
        'delivery_web_runner_enabled' => true,
        'delivery_web_runner_interval_seconds' => 60,
        'delivery_web_runner_batch_size' => 1,
        'delivery_manual_batch_size' => 10,
        'delivery_cli_batch_size' => 20,
        'delivery_max_attempts' => 5,
        'delivery_lock_timeout_seconds' => 300,
    ];
}

function sr_notification_settings(PDO $pdo): array
{
    $settings = array_merge(sr_notification_default_settings(), sr_module_settings($pdo, 'notification'));
    $settings['email_channel_enabled'] = (bool) $settings['email_channel_enabled'];
    $settings['email_transport'] = in_array((string) $settings['email_transport'], ['php_mail', 'smtp', 'http_api'], true) ? (string) $settings['email_transport'] : 'php_mail';
    $settings['email_smtp_port'] = max(1, min(65535, (int) $settings['email_smtp_port']));
    $settings['email_smtp_encryption'] = in_array((string) $settings['email_smtp_encryption'], ['none', 'tls', 'ssl'], true) ? (string) $settings['email_smtp_encryption'] : 'tls';
    $settings['email_timeout_seconds'] = max(3, min(30, (int) $settings['email_timeout_seconds']));
    $settings['external_push_enabled'] = (bool) $settings['external_push_enabled'];
    $settings['slack_webhook_enabled'] = (bool) $settings['slack_webhook_enabled'];
    $settings['slack_webhook_url'] = sr_notification_clean_setting_value((string) $settings['slack_webhook_url'], 255);
    $settings['slack_channel_label'] = sr_notification_clean_setting_value((string) $settings['slack_channel_label'], 80);
    $settings['discord_webhook_enabled'] = (bool) $settings['discord_webhook_enabled'];
    $settings['discord_webhook_url'] = sr_notification_clean_setting_value((string) $settings['discord_webhook_url'], 255);
    $settings['discord_channel_label'] = sr_notification_clean_setting_value((string) $settings['discord_channel_label'], 80);
    $settings['telegram_bot_enabled'] = (bool) $settings['telegram_bot_enabled'];
    $settings['telegram_bot_token'] = sr_notification_clean_setting_value((string) $settings['telegram_bot_token'], 255);
    $settings['telegram_chat_id'] = sr_notification_clean_setting_value((string) $settings['telegram_chat_id'], 120);
    $settings['telegram_channel_label'] = sr_notification_clean_setting_value((string) $settings['telegram_channel_label'], 80);
    $settings['external_push_failure_policy'] = sr_notification_external_failure_policy((string) $settings['external_push_failure_policy']);
    $settings['delivery_web_runner_enabled'] = (bool) $settings['delivery_web_runner_enabled'];
    $settings['delivery_web_runner_interval_seconds'] = max(10, min(3600, (int) $settings['delivery_web_runner_interval_seconds']));
    $settings['delivery_web_runner_batch_size'] = max(1, min(5, (int) $settings['delivery_web_runner_batch_size']));
    $settings['delivery_manual_batch_size'] = max(1, min(50, (int) $settings['delivery_manual_batch_size']));
    $settings['delivery_cli_batch_size'] = max(1, min(100, (int) $settings['delivery_cli_batch_size']));
    $settings['delivery_max_attempts'] = max(1, min(20, (int) $settings['delivery_max_attempts']));
    $settings['delivery_lock_timeout_seconds'] = max(30, min(3600, (int) $settings['delivery_lock_timeout_seconds']));

    return $settings;
}

function sr_notification_email_transport_options(): array
{
    return [
        'php_mail' => 'PHP mail()',
        'smtp' => 'SMTP',
        'http_api' => 'HTTP API',
    ];
}

function sr_notification_email_encryption_options(): array
{
    return [
        'none' => '사용 안 함',
        'tls' => 'STARTTLS',
        'ssl' => 'SSL/TLS',
    ];
}

function sr_notification_clean_setting_value(string $value, int $maxLength): string
{
    return sr_notification_clean_single_line($value, $maxLength);
}

function sr_notification_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'notification' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('알림 모듈이 등록되어 있지 않습니다.');
    }

    $rows = [
        ['email_channel_enabled', !empty($settings['email_channel_enabled']) ? '1' : '0', 'bool'],
        ['email_transport', (string) $settings['email_transport'], 'string'],
        ['email_from_email', (string) $settings['email_from_email'], 'string'],
        ['email_from_name', (string) $settings['email_from_name'], 'string'],
        ['email_smtp_host', (string) $settings['email_smtp_host'], 'string'],
        ['email_smtp_port', (string) $settings['email_smtp_port'], 'int'],
        ['email_smtp_encryption', (string) $settings['email_smtp_encryption'], 'string'],
        ['email_smtp_username', (string) $settings['email_smtp_username'], 'string'],
        ['email_smtp_password', (string) $settings['email_smtp_password'], 'string'],
        ['email_timeout_seconds', (string) $settings['email_timeout_seconds'], 'int'],
        ['email_http_api_endpoint', (string) $settings['email_http_api_endpoint'], 'string'],
        ['email_http_api_bearer_token', (string) $settings['email_http_api_bearer_token'], 'string'],
        ['external_push_enabled', !empty($settings['external_push_enabled']) ? '1' : '0', 'bool'],
        ['slack_webhook_enabled', !empty($settings['slack_webhook_enabled']) ? '1' : '0', 'bool'],
        ['slack_webhook_url', (string) $settings['slack_webhook_url'], 'string'],
        ['slack_channel_label', (string) $settings['slack_channel_label'], 'string'],
        ['discord_webhook_enabled', !empty($settings['discord_webhook_enabled']) ? '1' : '0', 'bool'],
        ['discord_webhook_url', (string) $settings['discord_webhook_url'], 'string'],
        ['discord_channel_label', (string) $settings['discord_channel_label'], 'string'],
        ['telegram_bot_enabled', !empty($settings['telegram_bot_enabled']) ? '1' : '0', 'bool'],
        ['telegram_bot_token', (string) $settings['telegram_bot_token'], 'string'],
        ['telegram_chat_id', (string) $settings['telegram_chat_id'], 'string'],
        ['telegram_channel_label', (string) $settings['telegram_channel_label'], 'string'],
        ['external_push_failure_policy', (string) $settings['external_push_failure_policy'], 'string'],
        ['delivery_web_runner_enabled', !empty($settings['delivery_web_runner_enabled']) ? '1' : '0', 'bool'],
        ['delivery_web_runner_interval_seconds', (string) $settings['delivery_web_runner_interval_seconds'], 'int'],
        ['delivery_web_runner_batch_size', (string) $settings['delivery_web_runner_batch_size'], 'int'],
        ['delivery_manual_batch_size', (string) $settings['delivery_manual_batch_size'], 'int'],
        ['delivery_cli_batch_size', (string) $settings['delivery_cli_batch_size'], 'int'],
        ['delivery_max_attempts', (string) $settings['delivery_max_attempts'], 'int'],
        ['delivery_lock_timeout_seconds', (string) $settings['delivery_lock_timeout_seconds'], 'int'],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO sr_module_settings
            (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    $now = sr_now();
    foreach ($rows as $row) {
        $stmt->execute([
            'module_id' => (int) $module['id'],
            'setting_key' => $row[0],
            'setting_value' => $row[1],
            'value_type' => $row[2],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    sr_clear_module_settings_cache('notification');
}

function sr_notification_create_channels(PDO $pdo): array
{
    $settings = sr_notification_settings($pdo);
    $channels = ['site'];
    if (!empty($settings['email_channel_enabled'])) {
        $channels[] = 'email';
    }

    return $channels;
}

function sr_notification_member_external_channels(PDO $pdo, int $accountId): array
{
    if ($accountId <= 0) {
        return [];
    }

    $settings = sr_notification_settings($pdo);
    $channels = [];
    foreach (sr_notification_member_external_channel_keys() as $channel) {
        if (sr_notification_member_external_provider_is_ready($channel, $settings)
            && sr_notification_member_push_endpoints($pdo, $accountId, $channel) !== []
        ) {
            $channels[] = $channel;
        }
    }

    return $channels;
}

function sr_notification_account_event_channels(PDO $pdo, int $accountId, array $channels): array
{
    $channels = sr_notification_normalize_channels($channels);
    if ($channels === []) {
        return ['site'];
    }

    $needsMemberExternalLookup = false;
    foreach ($channels as $channel) {
        if (in_array($channel, sr_notification_member_external_channel_keys(), true)) {
            $needsMemberExternalLookup = true;
            break;
        }
    }
    $memberExternalChannels = $needsMemberExternalLookup ? sr_notification_member_external_channels($pdo, $accountId) : [];
    $filtered = [];
    foreach ($channels as $channel) {
        if (in_array($channel, sr_notification_member_external_channel_keys(), true)) {
            if (in_array($channel, $memberExternalChannels, true)) {
                $filtered[] = $channel;
            }
            continue;
        }
        if (in_array($channel, sr_notification_admin_external_channel_keys(), true)) {
            continue;
        }
        $filtered[] = $channel;
    }

    return $filtered === [] ? ['site'] : array_values(array_unique($filtered));
}

function sr_notification_admin_external_channels(PDO $pdo): array
{
    $settings = sr_notification_settings($pdo);
    if (empty($settings['external_push_enabled'])) {
        return [];
    }

    $channels = [];
    foreach (sr_notification_admin_external_channel_keys() as $channel) {
        if (sr_notification_external_provider_is_ready($channel, $settings)) {
            $channels[] = $channel;
        }
    }

    return $channels;
}

function sr_notification_queue_admin_external_deliveries(PDO $pdo, int $adminNotificationId, array $channels = []): int
{
    if ($adminNotificationId <= 0) {
        return 0;
    }

    $settings = sr_notification_settings($pdo);
    $channels = $channels === [] ? sr_notification_admin_external_channels($pdo) : sr_notification_normalize_channels($channels);
    $channels = array_values(array_filter(
        $channels,
        static fn (string $channel): bool => in_array($channel, sr_notification_admin_external_channel_keys(), true)
            && sr_notification_external_provider_is_ready($channel, $settings)
    ));
    if ($channels === []) {
        return 0;
    }
    $providerOptions = sr_notification_external_provider_options();
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_notification_deliveries
            (notification_id, channel, recipient, status, provider_message_id, error_message, attempted_at, created_at, updated_at)
         VALUES
            (:notification_id, :channel, :recipient, \'queued\', \'\', \'\', NULL, :created_at, :updated_at)'
    );
    $queued = 0;
    foreach ($channels as $channel) {
        $labelSetting = (string) ($providerOptions[$channel]['channel_label_setting'] ?? '');
        $recipient = sr_notification_clean_single_line($labelSetting !== '' ? (string) ($settings[$labelSetting] ?? '') : '', 80);
        if ($recipient === '') {
            $recipient = $channel;
        }
        $stmt->execute([
            'notification_id' => $adminNotificationId,
            'channel' => $channel,
            'recipient' => $recipient,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $queued += $stmt->rowCount() > 0 ? 1 : 0;
    }

    return $queued;
}

function sr_notification_normalize_channels(array $channels): array
{
    $allowedChannels = sr_notification_allowed_channels();
    $normalized = [];

    foreach ($channels as $channel) {
        $channel = is_string($channel) ? $channel : '';
        if (in_array($channel, $allowedChannels, true)) {
            $normalized[$channel] = $channel;
        }
    }

    return array_values($normalized);
}

function sr_notification_external_channels(array $channels): array
{
    $externalChannels = [];

    foreach (sr_notification_normalize_channels($channels) as $channel) {
        if ($channel !== 'site') {
            $externalChannels[] = $channel;
        }
    }

    return $externalChannels;
}

function sr_notification_event_template(PDO $pdo, string $moduleKey, string $eventKey): ?array
{
    if (!sr_is_safe_module_key($moduleKey) || preg_match('/\A[a-z0-9_.-]{1,120}\z/', $eventKey) !== 1) {
        return null;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT module_key, event_key, title_template, body_template, link_template, channels_json, status
             FROM sr_notification_event_templates
             WHERE module_key = :module_key AND event_key = :event_key
             LIMIT 1"
        );
        $stmt->execute([
            'module_key' => $moduleKey,
            'event_key' => $eventKey,
        ]);
        $row = $stmt->fetch();
    } catch (Throwable) {
        return null;
    }

    return is_array($row) ? $row : null;
}

function sr_notification_render_template(string $template, array $metadata): string
{
    $values = [];
    foreach ($metadata as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $values['{' . (string) $key . '}'] = (string) $value;
        }
    }

    return strtr($template, $values);
}

function sr_notification_template_channels(?string $channelsJson): array
{
    $decoded = is_string($channelsJson) && trim($channelsJson) !== '' ? json_decode($channelsJson, true) : null;
    if (!is_array($decoded)) {
        return ['site'];
    }

    $channels = [];
    foreach ($decoded as $channel) {
        if (is_string($channel)) {
            $channels[] = $channel;
        }
    }

    $channels = sr_notification_normalize_channels($channels);
    return $channels === [] ? ['site'] : $channels;
}

function sr_notification_create_account_event(PDO $pdo, array $data): ?int
{
    $accountId = (int) ($data['account_id'] ?? 0);
    $moduleKey = (string) ($data['module_key'] ?? '');
    $eventKey = (string) ($data['event_key'] ?? '');
    if ($accountId <= 0 || !sr_is_safe_module_key($moduleKey) || preg_match('/\A[a-z0-9_.-]{1,120}\z/', $eventKey) !== 1) {
        return null;
    }

    $template = sr_notification_event_template($pdo, $moduleKey, $eventKey);
    if (!is_array($template) || (string) ($template['status'] ?? '') !== 'active') {
        return null;
    }

    $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [];
    $title = sr_notification_render_template((string) ($template['title_template'] ?? ''), $metadata);
    $bodyText = sr_notification_render_template((string) ($template['body_template'] ?? ''), $metadata);
    $linkUrl = sr_notification_render_template((string) ($template['link_template'] ?? ''), $metadata);
    $channels = isset($data['channels']) && is_array($data['channels'])
        ? sr_notification_account_event_channels($pdo, $accountId, $data['channels'])
        : sr_notification_template_channels(is_string($template['channels_json'] ?? null) ? (string) $template['channels_json'] : null);
    $channels = sr_notification_account_event_channels($pdo, $accountId, $channels);
    if ($channels === []) {
        $channels = ['site'];
    }

    return sr_notification_create($pdo, [
        'account_id' => $accountId,
        'audience' => 'account',
        'title' => $title,
        'body_text' => $bodyText,
        'link_url' => $linkUrl,
        'channels' => $channels,
        'created_by_account_id' => isset($data['created_by_account_id']) ? (int) $data['created_by_account_id'] : null,
    ]);
}

function sr_notification_admin_statuses(): array
{
    return ['active', 'deleted'];
}

function sr_notification_account_email(PDO $pdo, int $accountId): string
{
    if ($accountId <= 0) {
        return '';
    }

    $stmt = $pdo->prepare('SELECT email FROM sr_member_accounts WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $accountId]);
    $row = $stmt->fetch();
    $email = is_array($row) ? sr_normalize_identifier((string) ($row['email'] ?? '')) : '';

    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function sr_notification_all_member_email_recipients(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT email FROM sr_member_accounts WHERE status = 'active' ORDER BY id ASC");
    $recipients = [];
    foreach ($stmt->fetchAll() as $row) {
        $email = sr_normalize_identifier((string) ($row['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $recipients[$email] = $email;
        }
    }

    return array_values($recipients);
}

function sr_notification_email_recipients(PDO $pdo, string $audience, ?int $accountId): array
{
    if ($audience === 'account') {
        $email = sr_notification_account_email($pdo, (int) $accountId);
        return $email === '' ? [] : [$email];
    }

    if ($audience === 'all') {
        return sr_notification_all_member_email_recipients($pdo);
    }

    return [];
}

function sr_notification_admin_notification_query_parts(array $filters): array
{
    $where = [];
    $params = [];

    if (($filters['audience'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('n.audience', 'audience', $filters['audience']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('n.status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $field = (string) ($filters['field'] ?? 'all');
        if ($field === 'title') {
            $where[] = 'n.title LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'body') {
            $where[] = 'n.body_text LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'link') {
            $where[] = 'n.link_url LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'account') {
            $where[] = 'CAST(n.account_id AS CHAR) LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'id') {
            $where[] = 'CAST(n.id AS CHAR) LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } else {
            $where[] = '(n.title LIKE :title_keyword OR n.body_text LIKE :body_keyword OR n.link_url LIKE :link_keyword OR CAST(n.id AS CHAR) LIKE :id_keyword OR CAST(n.account_id AS CHAR) LIKE :account_keyword)';
            $params['title_keyword'] = '%' . $keyword . '%';
            $params['body_keyword'] = '%' . $keyword . '%';
            $params['link_keyword'] = '%' . $keyword . '%';
            $params['id_keyword'] = '%' . $keyword . '%';
            $params['account_keyword'] = '%' . $keyword . '%';
        }
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_notification_admin_notification_count(PDO $pdo, array $filters = []): int
{
    $queryParts = sr_notification_admin_notification_query_parts($filters);
    $sql = 'SELECT COUNT(*) AS count_value FROM sr_notifications n';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_notification_admin_notification_sort_options(): array
{
    return [
        'title' => ['columns' => ['n.title', 'n.id']],
        'audience' => ['columns' => ['n.audience', 'n.id']],
        'status' => ['columns' => ['n.status', 'n.id']],
        'created_at' => ['columns' => ['n.created_at', 'n.id']],
    ];
}

function sr_notification_admin_notification_default_sort(): array
{
    return sr_admin_sort_default('created_at', 'desc');
}

function sr_notification_admin_notifications(PDO $pdo, int $limit = 100, array $filters = [], int $offset = 0, array $sort = []): array
{
    $useLimit = $limit > 0;
    if ($useLimit) {
        $limit = max(1, min(1000, $limit));
    }
    $queryParts = sr_notification_admin_notification_query_parts($filters);
    $where = $queryParts['where'];
    $params = $queryParts['params'];
    $sql = 'SELECT n.id, n.audience, n.account_id, n.title, n.status, n.created_at
            FROM sr_notifications n';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= sr_admin_sort_order_sql(sr_notification_admin_notification_sort_options(), $sort, sr_notification_admin_notification_default_sort());
    if ($useLimit) {
        $sql .= ' LIMIT :limit_value OFFSET :offset_value';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, PDO::PARAM_STR);
    }
    if ($useLimit) {
        $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_notification_admin_status_counts(PDO $pdo, array $allowedStatuses): array
{
    $counts = ['total' => 0];
    foreach ($allowedStatuses as $status) {
        $counts[$status] = 0;
    }

    $stmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_notifications GROUP BY status');
    foreach ($stmt->fetchAll() as $row) {
        $status = (string) ($row['status'] ?? '');
        $count = (int) ($row['count_value'] ?? 0);
        if (array_key_exists($status, $counts)) {
            $counts[$status] = $count;
        }
        $counts['total'] += $count;
    }

    return $counts;
}

function sr_notification_create(PDO $pdo, array $data): int
{
    $audience = (string) ($data['audience'] ?? 'account');
    if (!in_array($audience, ['account', 'all'], true)) {
        throw new InvalidArgumentException('Notification audience is invalid.');
    }

    $accountId = isset($data['account_id']) && (int) $data['account_id'] > 0 ? (int) $data['account_id'] : null;
    if ($audience === 'account' && $accountId === null) {
        throw new InvalidArgumentException('Account notification requires account_id.');
    }

    $title = sr_notification_clean_single_line((string) ($data['title'] ?? ''), 160);
    if ($title === '') {
        throw new InvalidArgumentException('Notification title is required.');
    }

    $bodyFormat = sr_notification_body_format((string) ($data['body_format'] ?? 'plain'));
    $bodyText = $bodyFormat === 'html'
        ? sr_sanitize_rich_text_html(sr_notification_clean_text((string) ($data['body_text'] ?? ''), 5000))
        : sr_notification_clean_text((string) ($data['body_text'] ?? ''), 5000);
    $rawLinkUrl = (string) ($data['link_url'] ?? '');
    $linkUrl = sr_notification_clean_link_url($rawLinkUrl);
    if (trim($rawLinkUrl) !== '' && $linkUrl === '') {
        throw new InvalidArgumentException('Notification link URL is invalid.');
    }

    $channels = isset($data['channels']) && is_array($data['channels'])
        ? sr_notification_normalize_channels($data['channels'])
        : ['site'];
    $recipient = sr_notification_clean_single_line((string) ($data['recipient'] ?? ''), 255);
    if ($channels === []) {
        throw new InvalidArgumentException('Notification requires at least one delivery channel.');
    }
    $externalChannels = sr_notification_external_channels($channels);
    $emailRecipients = in_array('email', $channels, true)
        ? sr_notification_email_recipients($pdo, $audience, $accountId)
        : [];
    if (in_array('email', $channels, true) && $emailRecipients === []) {
        throw new InvalidArgumentException('Email notification delivery requires member email recipients.');
    }
    foreach ($externalChannels as $externalChannel) {
        if (in_array($externalChannel, sr_notification_admin_external_channel_keys(), true)) {
            if (!in_array($externalChannel, sr_notification_member_external_channel_keys(), true)
                || $audience !== 'account'
                || $accountId === null
                || sr_notification_member_push_endpoints($pdo, $accountId, $externalChannel) === []
            ) {
                throw new InvalidArgumentException('Member external push delivery requires an active member endpoint.');
            }
            continue;
        }
        if ($externalChannel !== 'email' && $recipient === '') {
            throw new InvalidArgumentException('External notification delivery requires recipient.');
        }
    }

    $createdByAccountId = isset($data['created_by_account_id']) && (int) $data['created_by_account_id'] > 0
        ? (int) $data['created_by_account_id']
        : null;

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $now = sr_now();
        $stmt = $pdo->prepare(
            'INSERT INTO sr_notifications
                (account_id, audience, title, body_text, body_format, link_url, status, read_at, created_by_account_id, created_at, updated_at)
             VALUES
                (:account_id, :audience, :title, :body_text, :body_format, :link_url, :status, NULL, :created_by_account_id, :created_at, :updated_at)'
        );
        $stmt->execute([
            'account_id' => $accountId,
            'audience' => $audience,
            'title' => $title,
            'body_text' => $bodyText,
            'body_format' => $bodyFormat,
            'link_url' => $linkUrl,
            'status' => 'active',
            'created_by_account_id' => $createdByAccountId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $notificationId = (int) $pdo->lastInsertId();
        sr_notification_queue_deliveries($pdo, $notificationId, $channels, $audience, $accountId, $recipient);

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return $notificationId;
}

function sr_notification_queue_deliveries(PDO $pdo, int $notificationId, array $channels, string $audience, ?int $accountId = null, string $recipient = ''): void
{
    $channels = sr_notification_normalize_channels($channels);
    $recipient = sr_notification_clean_single_line($recipient, 255);
    if ($channels === []) {
        throw new InvalidArgumentException('Notification requires at least one delivery channel.');
    }
    $emailRecipients = in_array('email', $channels, true)
        ? sr_notification_email_recipients($pdo, $audience, $accountId)
        : [];
    if (in_array('email', $channels, true) && $emailRecipients === []) {
        throw new InvalidArgumentException('Email notification delivery requires member email recipients.');
    }
    foreach (sr_notification_external_channels($channels) as $externalChannel) {
        if (in_array($externalChannel, sr_notification_admin_external_channel_keys(), true)) {
            if (!in_array($externalChannel, sr_notification_member_external_channel_keys(), true)
                || $audience !== 'account'
                || $accountId === null
                || sr_notification_member_push_endpoints($pdo, $accountId, $externalChannel) === []
            ) {
                throw new InvalidArgumentException('Member external push delivery requires an active member endpoint.');
            }
            continue;
        }
        if ($externalChannel !== 'email' && $recipient === '') {
            throw new InvalidArgumentException('External notification delivery requires recipient.');
        }
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_notification_deliveries
            (notification_id, channel, recipient, status, provider_message_id, error_message, attempted_at, created_at, updated_at)
         VALUES
            (:notification_id, :channel, :recipient, :status, :provider_message_id, :error_message, NULL, :created_at, :updated_at)'
    );

    foreach ($channels as $channel) {
        if ($channel === 'site') {
            continue;
        }
        if ($channel === 'email') {
            $recipients = $emailRecipients;
        } elseif (in_array($channel, sr_notification_member_external_channel_keys(), true)) {
            $recipients = [];
            foreach (sr_notification_member_push_endpoints($pdo, (int) $accountId, $channel) as $endpoint) {
                $endpointId = (int) ($endpoint['id'] ?? 0);
                if ($endpointId > 0) {
                    $recipients[] = 'endpoint:' . (string) $endpointId;
                }
            }
        } else {
            $recipients = [$recipient];
        }
        foreach ($recipients as $deliveryRecipient) {
            $stmt->execute([
                'notification_id' => $notificationId,
                'channel' => $channel,
                'recipient' => $deliveryRecipient,
                'status' => 'queued',
                'provider_message_id' => '',
                'error_message' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
