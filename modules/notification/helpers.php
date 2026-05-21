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

function sr_notification_clean_link_url(string $value): string
{
    $value = trim($value);
    if ($value === '' || sr_is_safe_relative_url($value) || sr_is_http_url($value)) {
        return $value;
    }

    return '';
}

function sr_notification_link_attributes(string $url): string
{
    $url = sr_notification_clean_link_url($url);
    if ($url === '') {
        return '';
    }

    $attributes = ' href="' . sr_e($url) . '"';
    if (sr_is_http_url($url)) {
        $attributes .= ' target="_blank" rel="noopener noreferrer"';
    }

    return $attributes;
}

function sr_notification_allowed_channels(): array
{
    return ['site', 'email', 'sms', 'alimtalk'];
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

function sr_notification_admin_statuses(): array
{
    return ['queued', 'active', 'deleted'];
}

function sr_notification_admin_notifications(PDO $pdo, int $limit = 100, array $filters = []): array
{
    $limit = max(1, min(200, $limit));
    $where = [];
    $params = [];

    if ((string) ($filters['audience'] ?? '') !== '') {
        $where[] = 'n.audience = :audience';
        $params['audience'] = (string) $filters['audience'];
    }

    if ((string) ($filters['status'] ?? '') !== '') {
        $where[] = 'n.status = :status';
        $params['status'] = (string) $filters['status'];
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

    $sql = 'SELECT n.id, n.audience, n.account_id, n.title, n.status, n.created_at
            FROM sr_notifications n';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY n.id DESC LIMIT :limit_value';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, PDO::PARAM_STR);
    }
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
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

    $bodyText = sr_notification_clean_text((string) ($data['body_text'] ?? ''), 5000);
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
    if (sr_notification_external_channels($channels) !== [] && $recipient === '') {
        throw new InvalidArgumentException('External notification delivery requires recipient.');
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
                (account_id, audience, title, body_text, link_url, status, read_at, created_by_account_id, created_at, updated_at)
             VALUES
                (:account_id, :audience, :title, :body_text, :link_url, :status, NULL, :created_by_account_id, :created_at, :updated_at)'
        );
        $stmt->execute([
            'account_id' => $accountId,
            'audience' => $audience,
            'title' => $title,
            'body_text' => $bodyText,
            'link_url' => $linkUrl,
            'status' => 'queued',
            'created_by_account_id' => $createdByAccountId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $notificationId = (int) $pdo->lastInsertId();
        sr_notification_queue_deliveries($pdo, $notificationId, $channels, $recipient);

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

function sr_notification_queue_deliveries(PDO $pdo, int $notificationId, array $channels, string $recipient): void
{
    $channels = sr_notification_normalize_channels($channels);
    $recipient = sr_notification_clean_single_line($recipient, 255);
    if ($channels === []) {
        throw new InvalidArgumentException('Notification requires at least one delivery channel.');
    }
    if (sr_notification_external_channels($channels) !== [] && $recipient === '') {
        throw new InvalidArgumentException('External notification delivery requires recipient.');
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_notification_deliveries
            (notification_id, channel, recipient, status, provider_message_id, error_message, attempted_at, created_at, updated_at)
         VALUES
            (:notification_id, :channel, :recipient, :status, :provider_message_id, :error_message, NULL, :created_at, :updated_at)'
    );

    foreach ($channels as $channel) {
        $stmt->execute([
            'notification_id' => $notificationId,
            'channel' => $channel,
            'recipient' => $channel === 'site' ? '' : $recipient,
            'status' => $channel === 'site' ? 'ready' : 'queued',
            'provider_message_id' => '',
            'error_message' => '',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
