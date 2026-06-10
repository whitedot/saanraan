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

function sr_notification_time_html(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return sr_e($value);
    }

    $diff = time() - $timestamp;
    if ($diff < 0) {
        $relative = date('Y-m-d H:i', $timestamp);
    } elseif ($diff < 60) {
        $relative = '방금 전';
    } elseif ($diff < 3600) {
        $relative = floor($diff / 60) . '분 전';
    } elseif ($diff < 86400) {
        $relative = floor($diff / 3600) . '시간 전';
    } elseif ($diff < 2592000) {
        $relative = floor($diff / 86400) . '일 전';
    } elseif ($diff < 31536000) {
        $relative = floor($diff / 2592000) . '개월 전';
    } else {
        $relative = floor($diff / 31536000) . '년 전';
    }

    return '<time datetime="' . sr_e($value) . '" title="' . sr_e($value) . '">' . sr_e($relative) . '</time>';
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

    $permissionPath = sr_notification_admin_clean_permission_path((string) ($data['permission_path'] ?? ''));
    $permissionAction = sr_notification_admin_clean_permission_action((string) ($data['permission_action'] ?? 'view'));
    $dedupeKey = sr_notification_admin_dedupe_key(array_merge($data, [
        'source_module_key' => $sourceModuleKey,
        'event_key' => $eventKey,
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
        'target_type' => sr_notification_clean_single_line((string) ($data['target_type'] ?? ''), 80),
        'target_id' => sr_notification_clean_single_line((string) ($data['target_id'] ?? ''), 80),
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
