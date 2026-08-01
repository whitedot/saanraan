<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_notification_clean_single_line(string $value, int $maxLength): string
{
    return sr_clean_single_line($value, $maxLength);
}

function sr_notification_table_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];

    $cacheKey = spl_object_id($pdo) . '.' . $table . '.' . $column;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $stmt = $pdo->query('SELECT ' . $column . ' FROM ' . $table . ' LIMIT 0');
        $cache[$cacheKey] = $stmt !== false;
    } catch (Throwable) {
        $cache[$cacheKey] = false;
    }

    return $cache[$cacheKey];
}

function sr_notification_event_columns_available(PDO $pdo): bool
{
    return sr_notification_table_has_column($pdo, 'sr_notifications', 'source_module_key')
        && sr_notification_table_has_column($pdo, 'sr_notifications', 'event_key')
        && sr_notification_table_has_column($pdo, 'sr_notifications', 'metadata_json');
}

function sr_notification_event_select_sql(PDO $pdo, string $alias = 'n'): string
{
    if (!sr_notification_event_columns_available($pdo)) {
        return ", '' AS source_module_key, '' AS event_key, NULL AS metadata_json";
    }

    $aliasPrefix = $alias !== '' ? $alias . '.' : '';
    return ', ' . $aliasPrefix . 'source_module_key, ' . $aliasPrefix . 'event_key, ' . $aliasPrefix . 'metadata_json';
}

function sr_notification_title_from_row(PDO $pdo, array $notification): string
{
    $title = sr_notification_clean_single_line((string) ($notification['title'] ?? ''), 160);
    return $title !== '' ? $title : '알림';
}

function sr_notification_apply_rendered_titles(PDO $pdo, array $notifications): array
{
    foreach ($notifications as $index => $notification) {
        if (is_array($notification)) {
            $notifications[$index]['title'] = sr_notification_title_from_row($pdo, $notification);
        }
    }

    return $notifications;
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

function sr_notification_public_header_summary(PDO $pdo, int $accountId, int $limit = 5): array
{
    if ($accountId <= 0) {
        return ['unread' => 0, 'items' => []];
    }

    $limit = max(1, min(10, $limit));

    try {
        $eventSelect = sr_notification_event_select_sql($pdo, 'n');
        $stmt = $pdo->prepare(
            "SELECT n.id, n.title, n.body_text, n.body_format, n.link_url" . $eventSelect . ",
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
        $items = sr_notification_apply_rendered_titles($pdo, $items);

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
