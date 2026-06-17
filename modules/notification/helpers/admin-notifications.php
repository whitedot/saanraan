<?php

declare(strict_types=1);

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
