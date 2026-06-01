<?php

declare(strict_types=1);

function sr_content_series_key_is_valid(string $seriesKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $seriesKey) === 1;
}

function sr_content_series_statuses(): array
{
    return ['pending', 'active', 'hidden', 'archived', 'deleted'];
}

function sr_content_series_visibility_values(): array
{
    return ['public', 'member', 'private'];
}

function sr_content_series_by_id(PDO $pdo, int $seriesId): ?array
{
    if ($seriesId < 1) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM sr_content_series WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $seriesId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function sr_content_series_by_key(PDO $pdo, string $seriesKey): ?array
{
    if (!sr_content_series_key_is_valid($seriesKey)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_content_series WHERE series_key = :series_key LIMIT 1');
    $stmt->execute(['series_key' => $seriesKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_series_key_exists(PDO $pdo, string $seriesKey, int $exceptSeriesId = 0): bool
{
    if (!sr_content_series_key_is_valid($seriesKey)) {
        return false;
    }

    $params = ['series_key' => $seriesKey];
    $where = 'series_key = :series_key';
    if ($exceptSeriesId > 0) {
        $where .= ' AND id <> :except_series_id';
        $params['except_series_id'] = $exceptSeriesId;
    }

    $stmt = $pdo->prepare('SELECT id FROM sr_content_series WHERE ' . $where . ' LIMIT 1');
    $stmt->execute($params);

    return is_array($stmt->fetch());
}

function sr_content_series_list(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM sr_content_series ORDER BY sort_order ASC, id DESC LIMIT 200');
    return $stmt->fetchAll();
}

function sr_content_series_item_visible_to_account(PDO $pdo, array $item, ?array $account, int $currentContentId): bool
{
    $itemContentId = (int) ($item['content_id'] ?? 0);
    if ($itemContentId < 1) {
        return false;
    }
    if ($itemContentId === $currentContentId) {
        return true;
    }

    $page = sr_content_by_id($pdo, $itemContentId);
    if (!is_array($page) || (string) ($page['status'] ?? '') !== 'published') {
        return false;
    }

    $page = sr_content_with_effective_settings($pdo, $page);
    if (!sr_content_asset_access_required($page)) {
        return true;
    }

    $accountId = is_array($account) ? (int) ($account['id'] ?? 0) : 0;
    if ($accountId < 1) {
        return false;
    }

    $assetModules = sr_content_asset_module_keys_from_value($page['asset_module'] ?? '');
    return sr_content_once_access_already_granted($pdo, $assetModules, $accountId, $itemContentId);
}

function sr_content_series_items(PDO $pdo, int $seriesId, bool $publicOnly = false, ?array $account = null, int $currentContentId = 0): array
{
    if ($seriesId < 1) {
        return [];
    }
    $where = 'si.series_id = :series_id';
    if ($publicOnly) {
        $where .= " AND si.item_status = 'active' AND c.status = 'published'";
    }
    $stmt = $pdo->prepare(
        'SELECT si.id, si.series_id, si.content_id, si.active_content_id, si.episode_label, si.item_status, si.sort_order,
                c.slug, c.title AS content_title, c.status AS content_status
         FROM sr_content_series_items si
         INNER JOIN sr_content_items c ON c.id = si.content_id
         WHERE ' . $where . '
         ORDER BY si.sort_order ASC, si.id ASC'
    );
    $stmt->execute(['series_id' => $seriesId]);
    $items = $stmt->fetchAll();
    if (!$publicOnly) {
        return $items;
    }

    $filtered = [];
    foreach ($items as $item) {
        if (sr_content_series_item_visible_to_account($pdo, $item, $account, $currentContentId)) {
            $filtered[] = $item;
        }
    }

    return sr_content_series_items_with_navigation($filtered, $currentContentId);
}

function sr_content_series_for_content(PDO $pdo, int $contentId, ?array $account = null, bool $adminPreview = false): ?array
{
    $stmt = $pdo->prepare(
        "SELECT s.*, si.id AS item_id, si.episode_label, si.item_status, si.sort_order
         FROM sr_content_series_items si
         INNER JOIN sr_content_series s ON s.id = si.series_id
         WHERE si.active_content_id = :content_id
         LIMIT 1"
    );
    $stmt->execute(['content_id' => $contentId]);
    $series = $stmt->fetch();
    if (!is_array($series) || (string) $series['status'] !== 'active' || (string) $series['item_status'] !== 'active') {
        return null;
    }
    if ((string) $series['visibility'] === 'member' && !is_array($account)) {
        return null;
    }
    if ((string) $series['visibility'] === 'private' && !$adminPreview) {
        return null;
    }
    $series['items'] = sr_content_series_items($pdo, (int) $series['id'], true, $account, $contentId);
    return $series;
}

function sr_content_series_items_with_navigation(array $items, int $currentContentId): array
{
    $previous = null;
    $next = null;
    foreach ($items as $index => $item) {
        if ((int) ($item['content_id'] ?? 0) === $currentContentId) {
            $previous = $items[$index - 1] ?? null;
            $next = $items[$index + 1] ?? null;
            break;
        }
    }

    foreach ($items as $index => $item) {
        $items[$index]['series_is_current'] = (int) ($item['content_id'] ?? 0) === $currentContentId ? 1 : 0;
        $items[$index]['series_is_previous'] = is_array($previous) && (int) ($previous['content_id'] ?? 0) === (int) ($item['content_id'] ?? 0) ? 1 : 0;
        $items[$index]['series_is_next'] = is_array($next) && (int) ($next['content_id'] ?? 0) === (int) ($item['content_id'] ?? 0) ? 1 : 0;
    }

    return $items;
}

function sr_content_active_series_item_for_content(PDO $pdo, int $contentId): ?array
{
    if ($contentId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT si.id, si.series_id, si.content_id, si.active_content_id, si.episode_label, si.item_status, si.sort_order,
                s.series_key, s.title AS series_title, s.status AS series_status, s.visibility
         FROM sr_content_series_items si
         INNER JOIN sr_content_series s ON s.id = si.series_id
         WHERE si.active_content_id = :content_id
         LIMIT 1"
    );
    $stmt->execute(['content_id' => $contentId]);
    $item = $stmt->fetch();

    return is_array($item) ? $item : null;
}

function sr_content_create_series(PDO $pdo, array $values, int $accountId): int
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_content_series
            (series_key, title, description, status, visibility, sort_order, created_by, updated_by, created_at, updated_at)
         VALUES
            (:series_key, :title, :description, :status, :visibility, :sort_order, :created_by, :updated_by, :created_at, :updated_at)'
    );
    $stmt->execute([
        'series_key' => (string) $values['series_key'],
        'title' => trim((string) $values['title']),
        'description' => trim((string) ($values['description'] ?? '')),
        'status' => (string) ($values['status'] ?? 'active'),
        'visibility' => (string) ($values['visibility'] ?? 'public'),
        'sort_order' => (int) ($values['sort_order'] ?? 0),
        'created_by' => $accountId,
        'updated_by' => $accountId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    return (int) $pdo->lastInsertId();
}

function sr_content_update_series(PDO $pdo, int $seriesId, array $values, int $accountId): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_content_series
         SET title = :title,
             description = :description,
             status = :status,
             visibility = :visibility,
             sort_order = :sort_order,
             updated_by = :updated_by,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'title' => trim((string) $values['title']),
        'description' => trim((string) ($values['description'] ?? '')),
        'status' => (string) ($values['status'] ?? 'active'),
        'visibility' => (string) ($values['visibility'] ?? 'public'),
        'sort_order' => (int) ($values['sort_order'] ?? 0),
        'updated_by' => $accountId,
        'updated_at' => sr_now(),
        'id' => $seriesId,
    ]);
    if (in_array((string) ($values['status'] ?? ''), ['archived', 'deleted'], true)) {
        sr_content_remove_series_items($pdo, $seriesId);
    }
}

function sr_content_set_content_series(PDO $pdo, int $contentId, int $seriesId, string $episodeLabel, int $sortOrder, int $accountId): void
{
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE sr_content_series_items SET active_content_id = NULL, item_status = \'removed\', updated_at = :updated_at WHERE active_content_id = :content_id')
            ->execute(['updated_at' => sr_now(), 'content_id' => $contentId]);
        if ($seriesId > 0) {
            $now = sr_now();
            $stmt = $pdo->prepare(
                'INSERT INTO sr_content_series_items
                    (series_id, content_id, active_content_id, episode_label, item_status, sort_order, created_by, created_at, updated_at)
                 VALUES
                    (:series_id, :content_id, :active_content_id, :episode_label, \'active\', :sort_order, :created_by, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    active_content_id = VALUES(active_content_id),
                    episode_label = VALUES(episode_label),
                    item_status = \'active\',
                    sort_order = VALUES(sort_order),
                    updated_at = VALUES(updated_at)'
            );
            $stmt->execute([
                'series_id' => $seriesId,
                'content_id' => $contentId,
                'active_content_id' => $contentId,
                'episode_label' => trim($episodeLabel),
                'sort_order' => $sortOrder,
                'created_by' => $accountId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_content_remove_series_items(PDO $pdo, int $seriesId): void
{
    $stmt = $pdo->prepare(
        "UPDATE sr_content_series_items
         SET active_content_id = NULL,
             item_status = 'removed',
             updated_at = :updated_at
         WHERE series_id = :series_id"
    );
    $stmt->execute(['updated_at' => sr_now(), 'series_id' => $seriesId]);
}
