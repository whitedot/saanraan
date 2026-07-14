<?php

declare(strict_types=1);

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

function sr_notification_member_external_immediate_delivery_enabled(): bool
{
    return PHP_SAPI !== 'cli' && function_exists('sr_request_method') && sr_request_method() === 'POST';
}

function sr_notification_current_site_context(PDO $pdo): array
{
    if (is_array($GLOBALS['site'] ?? null)) {
        return $GLOBALS['site'];
    }
    if (function_exists('sr_load_site')) {
        try {
            $site = sr_load_site($pdo);
            return is_array($site) ? $site : [];
        } catch (Throwable) {
            return [];
        }
    }

    return [];
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

function sr_notification_absolute_link_url(array $site, string $linkUrl): string
{
    if (sr_is_http_url($linkUrl)) {
        return $linkUrl;
    }
    if (!sr_is_safe_relative_url($linkUrl)) {
        return '';
    }
    if (function_exists('sr_absolute_url')) {
        return sr_absolute_url($site, $linkUrl);
    }

    $baseUrl = rtrim((string) ($site['base_url'] ?? ''), '/');
    if ($baseUrl === '' && function_exists('sr_current_base_url')) {
        $baseUrl = rtrim(sr_current_base_url(), '/');
    }
    if ($baseUrl !== '' && preg_match('~\Ahttps?://[^\s/$.?#].[^\s]*\z~i', $baseUrl) === 1) {
        return $baseUrl . '/' . ltrim($linkUrl, '/');
    }

    return sr_url($linkUrl);
}

function sr_notification_body_contains_link_url(string $body, string $linkUrl, string $absoluteLinkUrl): bool
{
    $body = trim($body);
    return $body !== '' && (
        ($linkUrl !== '' && str_contains($body, $linkUrl))
        || ($absoluteLinkUrl !== '' && str_contains($body, $absoluteLinkUrl))
    );
}

function sr_notification_body_promote_relative_link_url(string $body, string $linkUrl, string $absoluteLinkUrl): string
{
    if ($body === '' || $linkUrl === '' || $absoluteLinkUrl === '' || sr_is_http_url($linkUrl) || $linkUrl === $absoluteLinkUrl) {
        return $body;
    }
    if (str_contains($body, $absoluteLinkUrl)) {
        return $body;
    }

    return str_replace($linkUrl, $absoluteLinkUrl, $body);
}

function sr_notification_body_with_link_url(string $body, array $site, string $linkUrl): string
{
    $linkUrl = sr_notification_clean_link_url($linkUrl);
    if ($linkUrl === '') {
        return $body;
    }
    $absoluteLinkUrl = sr_notification_absolute_link_url($site, $linkUrl);
    $body = sr_notification_body_promote_relative_link_url($body, $linkUrl, $absoluteLinkUrl);
    if ($absoluteLinkUrl === '' || sr_notification_body_contains_link_url($body, $linkUrl, $absoluteLinkUrl)) {
        return $body;
    }

    return $body . ($body !== '' ? "\n\n" : '') . $absoluteLinkUrl;
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
            'member_enabled_setting' => 'slack_member_push_enabled',
            'webhook_url_setting' => 'slack_webhook_url',
            'channel_label_setting' => 'slack_channel_label',
        ],
        'discord_webhook' => [
            'label' => 'Discord webhook',
            'enabled_setting' => 'discord_webhook_enabled',
            'member_enabled_setting' => 'discord_member_push_enabled',
            'webhook_url_setting' => 'discord_webhook_url',
            'channel_label_setting' => 'discord_channel_label',
        ],
        'telegram_bot' => [
            'label' => 'Telegram bot',
            'enabled_setting' => 'telegram_bot_enabled',
            'member_enabled_setting' => 'telegram_member_push_enabled',
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

function sr_notification_member_external_channel_labels(): array
{
    return [
        'slack_webhook' => 'Slack',
        'discord_webhook' => 'Discord',
        'telegram_bot' => 'Telegram',
    ];
}

function sr_notification_member_external_channel_label(string $channel): string
{
    $labels = sr_notification_member_external_channel_labels();
    return (string) ($labels[$channel] ?? $channel);
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
    if (!function_exists('sr_secret_at_rest_key')) {
        return '';
    }

    try {
        return sr_secret_at_rest_key('notification|' . $purpose);
    } catch (Throwable) {
        return '';
    }
}

function sr_notification_secret_crypto_available(): bool
{
    return function_exists('sr_secret_at_rest_crypto_available') && sr_secret_at_rest_crypto_available();
}

function sr_notification_secret_encrypt(string $plaintext, string $purpose): string
{
    $plaintext = (string) $plaintext;
    if ($plaintext === '' || !function_exists('sr_secret_at_rest_encrypt')) {
        return '';
    }

    try {
        return sr_secret_at_rest_encrypt($plaintext, 'notification|' . $purpose);
    } catch (Throwable) {
        return '';
    }
}

function sr_notification_secret_decrypt(string $ciphertext, string $purpose): ?string
{
    if ($ciphertext === '' || !function_exists('sr_secret_at_rest_decrypt')) {
        return null;
    }

    try {
        return sr_secret_at_rest_decrypt($ciphertext, 'notification|' . $purpose);
    } catch (Throwable) {
        return null;
    }
}

function sr_notification_secret_fingerprint(string $plaintext, string $purpose): string
{
    if ($plaintext === '' || !function_exists('sr_secret_at_rest_fingerprint')) {
        return '';
    }

    try {
        return sr_secret_at_rest_fingerprint($plaintext, 'notification|' . $purpose);
    } catch (Throwable) {
        return '';
    }
}

function sr_notification_delivery_endpoint_id(string $recipient): int
{
    return preg_match('/\Aendpoint:([1-9][0-9]*)\z/', trim($recipient), $matches) === 1 ? (int) $matches[1] : 0;
}

function sr_notification_member_external_channel_keys(): array
{
    return ['slack_webhook', 'discord_webhook', 'telegram_bot'];
}

function sr_notification_admin_external_delivery_sql_condition(string $alias = 'd'): string
{
    return $alias . '.channel IN (' . sr_notification_admin_external_channel_sql_list() . ") AND " . $alias . ".recipient NOT LIKE 'endpoint:%'";
}

function sr_notification_member_external_provider_is_ready(string $channel, array $settings): bool
{
    $options = sr_notification_external_provider_options();
    if (!isset($options[$channel]) || empty($settings['external_push_enabled'])) {
        return false;
    }

    $memberEnabledSetting = (string) ($options[$channel]['member_enabled_setting'] ?? '');
    if ($memberEnabledSetting !== '' && empty($settings[$memberEnabledSetting])) {
        return false;
    }

    if ($channel === 'telegram_bot') {
        return sr_notification_telegram_bot_token_is_allowed((string) ($settings['telegram_bot_token'] ?? ''))
            && sr_notification_secret_crypto_available();
    }

    return in_array($channel, ['slack_webhook', 'discord_webhook'], true)
        && sr_notification_secret_crypto_available();
}

function sr_notification_member_push_endpoint_is_allowed(string $providerKey, string $endpoint): bool
{
    if ($providerKey === 'telegram_bot') {
        return sr_notification_telegram_chat_id_is_allowed($endpoint);
    }

    if (in_array($providerKey, ['slack_webhook', 'discord_webhook'], true)) {
        return sr_notification_webhook_url_is_allowed($endpoint);
    }

    return false;
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
    if (!sr_notification_member_push_endpoint_is_allowed($providerKey, $endpoint)) {
        throw new InvalidArgumentException('Member push endpoint is invalid.');
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
    if ($fingerprint === '') {
        throw new RuntimeException('Member push endpoint fingerprint failed.');
    }

    $label = sr_notification_clean_single_line((string) ($data['recipient_label'] ?? ''), 120);
    $masked = sr_notification_push_endpoint_mask($providerKey, $endpoint);
    $now = sr_now();

    $stmt = $pdo->prepare('SELECT id, account_id FROM sr_notification_push_endpoints WHERE provider_key = :provider_key AND endpoint_fingerprint = :endpoint_fingerprint LIMIT 1');
    $stmt->execute([
        'provider_key' => $providerKey,
        'endpoint_fingerprint' => $fingerprint,
    ]);
    $existingEndpoint = $stmt->fetch();
    $existingId = is_array($existingEndpoint) ? (int) ($existingEndpoint['id'] ?? 0) : 0;
    if ($existingId > 0) {
        if ((int) ($existingEndpoint['account_id'] ?? 0) !== $accountId) {
            throw new InvalidArgumentException('Member push endpoint is already connected.');
        }
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
        "SELECT COUNT(*) AS endpoint_count
         FROM sr_notification_push_endpoints
         WHERE account_id = :account_id
           AND provider_key = :provider_key
           AND status = 'active'"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'provider_key' => $providerKey,
    ]);
    if ((int) $stmt->fetchColumn() >= 5) {
        throw new InvalidArgumentException('Member push endpoint limit exceeded.');
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

function sr_notification_member_push_endpoint_rows(PDO $pdo, int $accountId): array
{
    if ($accountId <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT id, account_id, provider_key, recipient_type, recipient_label, recipient_masked,
                    status, verified_at, disabled_at, last_used_at, created_at, updated_at
             FROM sr_notification_push_endpoints
             WHERE account_id = :account_id
               AND recipient_type = 'personal'
             ORDER BY provider_key ASC, status ASC, id ASC"
        );
        $stmt->execute(['account_id' => $accountId]);
    } catch (Throwable) {
        return [];
    }

    return $stmt->fetchAll();
}

function sr_notification_member_push_active_count(PDO $pdo, int $accountId, string $providerKey): int
{
    if ($accountId <= 0 || !in_array($providerKey, sr_notification_member_external_channel_keys(), true)) {
        return 0;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS endpoint_count
             FROM sr_notification_push_endpoints
             WHERE account_id = :account_id
               AND provider_key = :provider_key
               AND recipient_type = 'personal'
               AND status = 'active'"
        );
        $stmt->execute([
            'account_id' => $accountId,
            'provider_key' => $providerKey,
        ]);
    } catch (Throwable) {
        return 0;
    }

    return (int) $stmt->fetchColumn();
}

function sr_notification_disable_member_push_endpoint(PDO $pdo, int $accountId, int $endpointId, string $now = ''): bool
{
    if ($accountId <= 0 || $endpointId <= 0) {
        return false;
    }

    $now = $now !== '' ? $now : sr_now();
    try {
        $stmt = $pdo->prepare(
            "UPDATE sr_notification_push_endpoints
             SET endpoint_ciphertext = '',
                 status = 'disabled',
                 disabled_at = :disabled_at,
                 updated_at = :updated_at
             WHERE id = :id
               AND account_id = :account_id
               AND recipient_type = 'personal'
               AND status = 'active'"
        );
        $stmt->execute([
            'disabled_at' => $now,
            'updated_at' => $now,
            'id' => $endpointId,
            'account_id' => $accountId,
        ]);

        return $stmt->rowCount() > 0;
    } catch (Throwable) {
        return false;
    }
}

function sr_notification_cleanup_member_push_endpoints(PDO $pdo, int $accountId, string $now = ''): int
{
    if ($accountId <= 0) {
        return 0;
    }

    $now = $now !== '' ? $now : sr_now();
    try {
        $stmt = $pdo->prepare(
            "UPDATE sr_notification_push_endpoints
             SET endpoint_ciphertext = '',
                 status = 'disabled',
                 disabled_at = COALESCE(disabled_at, :disabled_at),
                 updated_at = :updated_at
             WHERE account_id = :account_id
               AND recipient_type = 'personal'
               AND (endpoint_ciphertext <> '' OR status <> 'disabled')"
        );
        $stmt->execute([
            'disabled_at' => $now,
            'updated_at' => $now,
            'account_id' => $accountId,
        ]);

        return $stmt->rowCount();
    } catch (Throwable) {
        return 0;
    }
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
         ORDER BY
           CASE
             WHEN d.recipient LIKE \'endpoint:%\' THEN 0
             WHEN d.channel IN (' . sr_notification_admin_external_channel_sql_list() . ') THEN 1
             ELSE 2
           END ASC,
           d.id ASC
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
    $eventSelect = sr_notification_event_select_sql($pdo, 'n');
    $stmt = $pdo->prepare(
        "SELECT d.id, d.notification_id, d.channel, d.recipient, d.status, d.attempt_count,
                CASE WHEN " . $adminExternalCondition . " THEN an.title ELSE n.title END AS title" . $eventSelect . ",
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
    if (is_array($delivery)) {
        $delivery['title'] = sr_notification_title_from_row($pdo, $delivery);
    }

    return is_array($delivery) ? $delivery : null;
}

function sr_notification_claim_delivery_by_id(PDO $pdo, int $deliveryId, string $lockId, string $now, int $lockTimeoutSeconds, array $channels = []): ?array
{
    if ($deliveryId <= 0) {
        return null;
    }

    $channels = $channels === [] ? array_merge(['email'], sr_notification_admin_external_channel_keys()) : sr_notification_normalize_channels($channels);
    $channels = array_values(array_filter($channels, static fn (string $channel): bool => $channel !== 'site'));
    if ($channels === []) {
        return null;
    }

    $lockCutoff = date('Y-m-d H:i:s', strtotime($now) - max(30, $lockTimeoutSeconds));
    $channelPlaceholders = [];
    $updateParams = [
        'id' => $deliveryId,
        'locked_at' => $now,
        'locked_by' => $lockId,
        'updated_at' => $now,
        'now_due' => $now,
        'lock_cutoff' => $lockCutoff,
    ];
    foreach ($channels as $index => $channel) {
        $key = 'channel_' . (string) $index;
        $channelPlaceholders[] = ':' . $key;
        $updateParams[$key] = $channel;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_notification_deliveries
         SET status = \'processing\',
             locked_at = :locked_at,
             locked_by = :locked_by,
             attempt_count = attempt_count + 1,
             updated_at = :updated_at
         WHERE id = :id
           AND channel IN (' . implode(', ', $channelPlaceholders) . ')
           AND recipient LIKE \'endpoint:%\'
           AND (
                (status = \'queued\' AND (next_attempt_at IS NULL OR next_attempt_at <= :now_due))
                OR (status = \'processing\' AND (locked_at IS NULL OR locked_at <= :lock_cutoff))
           )'
    );
    $stmt->execute($updateParams);
    if ($stmt->rowCount() < 1) {
        return null;
    }

    $eventSelect = sr_notification_event_select_sql($pdo, 'n');
    $stmt = $pdo->prepare(
        'SELECT d.id, d.notification_id, d.channel, d.recipient, d.status, d.attempt_count,
                n.title AS title' . $eventSelect . ',
                n.body_text AS body_text,
                n.body_format AS body_format,
                n.link_url AS link_url
         FROM sr_notification_deliveries d
         INNER JOIN sr_notifications n ON n.id = d.notification_id
         WHERE d.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $deliveryId]);
    $delivery = $stmt->fetch();
    if (is_array($delivery)) {
        $delivery['title'] = sr_notification_title_from_row($pdo, $delivery);
    }

    return is_array($delivery) ? $delivery : null;
}

function sr_notification_delete_sent_delivery(PDO $pdo, int $deliveryId): void
{
    if ($deliveryId <= 0) {
        return;
    }

    $stmt = $pdo->prepare(
        "DELETE FROM sr_notification_deliveries
         WHERE id = :id
           AND status = 'sent'
           AND recipient LIKE 'endpoint:%'"
    );
    $stmt->execute(['id' => $deliveryId]);
}

function sr_notification_process_immediate_member_external_deliveries(PDO $pdo, array $site, array $deliveryIds): array
{
    $settings = sr_notification_settings($pdo);
    $maxAttempts = max(1, min(20, (int) ($settings['delivery_max_attempts'] ?? 5)));
    $lockTimeoutSeconds = max(30, min(3600, (int) ($settings['delivery_lock_timeout_seconds'] ?? 300)));
    $lockId = 'immediate:' . sr_notification_delivery_runner_lock_id();
    $result = [
        'claimed' => 0,
        'sent' => 0,
        'failed' => 0,
        'dead' => 0,
        'skipped' => 0,
    ];

    foreach (array_values(array_unique(array_map('intval', $deliveryIds))) as $deliveryId) {
        $now = sr_now();
        $delivery = sr_notification_claim_delivery_by_id($pdo, $deliveryId, $lockId, $now, $lockTimeoutSeconds, sr_notification_member_external_channel_keys());
        if ($delivery === null) {
            continue;
        }

        $result['claimed']++;
        try {
            $deliveryResult = sr_notification_process_delivery($pdo, $site, $delivery, $settings, $now, $maxAttempts);
            $result['sent'] += (int) ($deliveryResult['sent'] ?? 0);
            $result['failed'] += (int) ($deliveryResult['failed'] ?? 0);
            $result['dead'] += (int) ($deliveryResult['dead'] ?? 0);
            $result['skipped'] += (int) ($deliveryResult['skipped'] ?? 0);
            if ((string) ($deliveryResult['status'] ?? '') === 'sent') {
                sr_notification_delete_sent_delivery($pdo, (int) ($delivery['id'] ?? 0));
            }
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'notification_immediate_delivery');
            $exceptionMessage = '발송 처리 예외: ' . get_class($exception);
            if ($exception->getMessage() !== '') {
                $exceptionMessage .= ': ' . $exception->getMessage();
            }
            $status = sr_notification_mark_delivery_failed(
                $pdo,
                (int) ($delivery['id'] ?? 0),
                sr_now(),
                $exceptionMessage,
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
    } elseif ((string) ($delivery['body_format'] ?? 'plain') === 'markdown') {
        $body = sr_markdown_plain_text_for_body($pdo, $body);
    }
    $linkUrl = sr_notification_clean_link_url((string) ($delivery['link_url'] ?? ''));
    $body = sr_notification_body_with_link_url($body, $site, $linkUrl);

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
    $body = (string) ($delivery['body_text'] ?? '');
    if ((string) ($delivery['body_format'] ?? 'plain') === 'markdown' && ($GLOBALS['pdo'] ?? null) instanceof PDO) {
        $body = sr_markdown_plain_text_for_body($GLOBALS['pdo'], $body);
    } else {
        $body = trim(strip_tags($body));
    }
    $linkUrl = sr_notification_clean_link_url((string) ($delivery['link_url'] ?? ''));
    $body = sr_notification_body_with_link_url($body, $site, $linkUrl);
    $lines = ['[' . $siteName . '] ' . $title];
    if ($body !== '') {
        $lines[] = sr_notification_clean_text($body, 1500);
    }

    if ($channel === 'telegram_bot') {
        return [
            'chat_id' => $endpoint,
            'text' => implode("\n\n", $lines),
            'disable_web_page_preview' => true,
        ];
    }

    if ($channel === 'discord_webhook') {
        return ['content' => implode("\n\n", $lines)];
    }

    return ['text' => implode("\n\n", $lines)];
}

function sr_notification_member_external_push_endpoint(string $channel, array $settings, string $endpoint): string
{
    if ($channel === 'telegram_bot') {
        return sr_notification_external_push_endpoint($channel, $settings);
    }

    return in_array($channel, ['slack_webhook', 'discord_webhook'], true) && sr_notification_webhook_url_is_allowed($endpoint)
        ? $endpoint
        : '';
}

function sr_notification_member_push_delivery_context(PDO $pdo, array $delivery): ?array
{
    $deliveryId = (int) ($delivery['id'] ?? 0);
    $endpointId = sr_notification_delivery_endpoint_id((string) ($delivery['recipient'] ?? ''));
    $channel = (string) ($delivery['channel'] ?? '');
    if ($deliveryId <= 0 || $endpointId <= 0 || !in_array($channel, sr_notification_member_external_channel_keys(), true)) {
        return null;
    }

    $eventSelect = sr_notification_event_select_sql($pdo, 'n');
    $stmt = $pdo->prepare(
        "SELECT d.id AS delivery_id, d.notification_id, d.channel, d.attempt_count,
                n.account_id, n.audience, n.status AS notification_status, n.title, n.body_text, n.body_format, n.link_url" . $eventSelect . ",
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
    if (is_array($row)) {
        $row['title'] = sr_notification_title_from_row($pdo, $row);
    }

    return is_array($row) ? $row : null;
}

function sr_notification_is_member_push_delivery(PDO $pdo, array $delivery): bool
{
    return sr_notification_member_push_delivery_context($pdo, $delivery) !== null;
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
    if (!is_string($endpoint) || $endpoint === '' || !sr_notification_member_push_endpoint_is_allowed($channel, $endpoint)) {
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
        sr_notification_member_external_push_endpoint($channel, $settings, $endpoint),
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
    $body = (string) ($delivery['body_text'] ?? '');
    if ((string) ($delivery['body_format'] ?? 'plain') === 'markdown' && ($GLOBALS['pdo'] ?? null) instanceof PDO) {
        $body = sr_markdown_plain_text_for_body($GLOBALS['pdo'], $body);
    } else {
        $body = trim(strip_tags($body));
    }
    $linkUrl = sr_notification_clean_link_url((string) ($delivery['link_url'] ?? ''));
    $body = sr_notification_body_with_link_url($body, $site, $linkUrl);
    $lines = ['[' . $siteName . '] ' . $title];
    if ($body !== '') {
        $lines[] = sr_notification_clean_text($body, 1500);
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
    if (!sr_notification_webhook_url_is_allowed($url)) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'provider_unavailable'];
    }

    $timeout = min(30, max(3, $timeoutSeconds));
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($json)) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'payload_encode_failed'];
    }
    $lastTransportError = '';

    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        if ($handle !== false) {
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
                CURLOPT_FOLLOWLOCATION => false,
            ];
            if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
                $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            }
            curl_setopt_array($handle, $options);
            $body = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $errno = (int) curl_errno($handle);
            curl_close($handle);
            if (is_string($body)) {
                return [
                    'ok' => true,
                    'status' => $status,
                    'body' => $body,
                    'error' => '',
                ];
            }
            $lastTransportError = $errno > 0 ? 'provider_unavailable:curl_' . (string) $errno : 'provider_unavailable';
        }
    }

    if (!ini_get('allow_url_fopen')) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => $lastTransportError !== '' ? $lastTransportError : 'provider_unavailable'];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $json,
            'timeout' => $timeout,
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
        'error' => is_string($body) ? '' : ($lastTransportError !== '' ? $lastTransportError : 'provider_unavailable'),
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

function sr_notification_process_delivery(PDO $pdo, array $site, array $delivery, array $settings, string $now, int $maxAttempts): array
{
    $channel = (string) ($delivery['channel'] ?? '');
    if ($channel === 'email') {
        return sr_notification_process_email_delivery($pdo, $site, $delivery, $settings, $now, $maxAttempts);
    }
    if (sr_notification_delivery_endpoint_id((string) ($delivery['recipient'] ?? '')) > 0
        && in_array($channel, sr_notification_member_external_channel_keys(), true)
        && sr_notification_is_member_push_delivery($pdo, $delivery)
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
            $exceptionMessage = '발송 처리 예외: ' . get_class($exception);
            if ($exception->getMessage() !== '') {
                $exceptionMessage .= ': ' . $exception->getMessage();
            }
            $status = sr_notification_mark_delivery_failed(
                $pdo,
                (int) ($delivery['id'] ?? 0),
                sr_now(),
                $exceptionMessage,
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
    if (!in_array($method, ['GET', 'POST'], true)) {
        return;
    }
    if ($path === '/manifest.webmanifest' || $path === '/service-worker.js') {
        return;
    }

    $settings = sr_notification_settings($pdo);
    if (empty($settings['delivery_web_runner_enabled'])) {
        return;
    }

    if (!sr_notification_web_delivery_runner_due($pdo, $settings)) {
        return;
    }

    $batchSize = max(1, min(5, (int) ($settings['delivery_web_runner_batch_size'] ?? 1)));
    register_shutdown_function(static function () use ($pdo, $site, $batchSize): void {
        if (!sr_notification_web_delivery_runner_request_completed() || !sr_notification_web_delivery_runner_lock_acquire($pdo)) {
            return;
        }

        try {
            sr_clear_module_settings_cache('notification');
            $settings = sr_notification_settings($pdo);
            if (empty($settings['delivery_web_runner_enabled']) || !sr_notification_web_delivery_runner_due($pdo, $settings)) {
                return;
            }

            sr_notification_save_module_runtime_setting($pdo, 'delivery_last_web_runner_at', sr_now(), 'string');
            sr_notification_run_delivery_batch($pdo, $site, $batchSize, sr_notification_delivery_runner_lock_id());
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'notification_web_runner_failed');
        } finally {
            sr_notification_web_delivery_runner_lock_release($pdo);
        }
    });
}

function sr_notification_web_delivery_runner_due(PDO $pdo, array $settings): bool
{
    $lastRunAt = (string) sr_module_setting($pdo, 'notification', 'delivery_last_web_runner_at', '');
    $lastRunTime = $lastRunAt === '' ? false : strtotime($lastRunAt);
    $interval = max(10, (int) ($settings['delivery_web_runner_interval_seconds'] ?? 60));

    return $lastRunTime === false || time() - $lastRunTime >= $interval;
}

function sr_notification_web_delivery_runner_request_completed(): bool
{
    $statusCode = http_response_code();
    if (!is_int($statusCode) || $statusCode < 200 || $statusCode >= 400) {
        return false;
    }

    $contract = $GLOBALS['sr_request_contract'] ?? null;
    return !is_array($contract) || (string) ($contract['exit_reason'] ?? '') === 'completed';
}

function sr_notification_web_delivery_runner_lock_acquire(PDO $pdo): bool
{
    try {
        if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return true;
        }

        $stmt = $pdo->prepare('SELECT GET_LOCK(:lock_name, 0) AS lock_acquired');
        $stmt->execute(['lock_name' => 'saanraan_notification_web_runner']);
        $row = $stmt->fetch();

        return is_array($row) && (string) ($row['lock_acquired'] ?? '') === '1';
    } catch (Throwable) {
        return false;
    }
}

function sr_notification_web_delivery_runner_lock_release(PDO $pdo): void
{
    try {
        if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return;
        }

        $stmt = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $stmt->execute(['lock_name' => 'saanraan_notification_web_runner']);
    } catch (Throwable) {
        return;
    }
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
