<?php

declare(strict_types=1);

function sr_notification_clean_single_line(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function sr_notification_clean_text(string $value, int $maxLength): string
{
    $value = trim($value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function sr_notification_body_format(string $value): string
{
    return in_array($value, ['plain', 'html'], true) ? $value : 'plain';
}

function sr_notification_body_html(array $notification): string
{
    return sr_body_text_html($notification);
}

function sr_notification_clean_link_url(string $value): string
{
    $value = trim($value);
    if ($value === '' || sr_is_safe_relative_url($value) || sr_is_http_url($value)) {
        return $value;
    }

    return '';
}

function sr_notification_read_redirect_url(int $notificationId, string $url = ''): string
{
    if ($notificationId <= 0) {
        return sr_url('/account/notifications');
    }

    $url = sr_notification_clean_link_url($url);
    $query = 'id=' . rawurlencode((string) $notificationId);
    if ($url !== '') {
        $query .= '&next=' . rawurlencode($url);
    }

    return sr_url('/account/notifications/read?' . $query);
}

function sr_notification_link_attributes(string $url, int $notificationId = 0, bool $markRead = false): string
{
    $url = sr_notification_clean_link_url($url);
    if ($url === '') {
        return '';
    }

    $href = $markRead && $notificationId > 0
        ? sr_notification_read_redirect_url($notificationId, $url)
        : (sr_is_http_url($url) ? $url : sr_url($url));
    $attributes = ' href="' . sr_e($href) . '"';
    if (!$markRead && sr_is_http_url($url)) {
        $attributes .= ' target="_blank" rel="noopener noreferrer"';
    }

    return $attributes;
}

function sr_notification_mark_read(PDO $pdo, int $notificationId, int $accountId): bool
{
    if ($notificationId <= 0 || $accountId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT id, audience
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
    if (!is_array($notification)) {
        return false;
    }

    $now = sr_now();
    if ((string) $notification['audience'] === 'all') {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_notification_reads (notification_id, account_id, read_at)
             VALUES (:notification_id, :account_id, :read_at)
             ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)'
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
             WHERE n.account_id = :account_id OR n.audience = 'all'
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

function sr_notification_allowed_channels(): array
{
    return ['site', 'email', 'sms', 'alimtalk'];
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
        ? sr_notification_normalize_channels($data['channels'])
        : sr_notification_template_channels(is_string($template['channels_json'] ?? null) ? (string) $template['channels_json'] : null);
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
        $recipients = $channel === 'email' ? $emailRecipients : [$recipient];
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
