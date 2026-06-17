<?php

declare(strict_types=1);

function sr_community_board_groups(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT g.*,
                COUNT(b.id) AS board_count
         FROM sr_community_board_groups g
         LEFT JOIN sr_community_boards b ON b.board_group_id = g.id
         GROUP BY g.id, g.group_key, g.title, g.description, g.status, g.sort_order, g.created_at, g.updated_at
         ORDER BY g.sort_order ASC, g.id ASC'
    );

    return $stmt->fetchAll();
}

function sr_community_admin_board_group_query_parts(array $filters): array
{
    $where = [];
    $params = [];
    $status = is_array($filters['status'] ?? null) ? $filters['status'] : [];
    $field = (string) ($filters['field'] ?? 'all');
    $keyword = trim((string) ($filters['q'] ?? ''));

    if ($status !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('g.status', 'status', $status);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if ($keyword !== '') {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $keyword) . '%';
        if ($field === 'key') {
            $where[] = 'g.group_key LIKE :keyword';
            $params['keyword'] = $like;
        } elseif ($field === 'title') {
            $where[] = 'g.title LIKE :keyword';
            $params['keyword'] = $like;
        } else {
            $where[] = '(g.group_key LIKE :group_key_keyword OR g.title LIKE :title_keyword OR g.description LIKE :description_keyword)';
            $params['group_key_keyword'] = $like;
            $params['title_keyword'] = $like;
            $params['description_keyword'] = $like;
        }
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_community_admin_board_group_count(PDO $pdo, array $filters): int
{
    $queryParts = sr_community_admin_board_group_query_parts($filters);
    $sql = 'SELECT COUNT(*) AS count_value FROM sr_community_board_groups g';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_community_admin_board_group_sort_options(): array
{
    return [
        'group_key' => ['columns' => ['g.group_key', 'g.id']],
        'title' => ['columns' => ['g.title', 'g.id']],
        'status' => ['columns' => ['g.status', 'g.id']],
        'board_count' => ['columns' => ['board_count', 'g.id']],
        'sort_order' => ['columns' => ['g.sort_order', 'g.id']],
    ];
}

function sr_community_admin_board_group_default_sort(): array
{
    return sr_admin_sort_default('sort_order', 'asc');
}

function sr_community_admin_board_groups(PDO $pdo, array $filters, int $limit = 0, int $offset = 0, array $sort = []): array
{
    $queryParts = sr_community_admin_board_group_query_parts($filters);
    $sql = 'SELECT g.*,
                   COUNT(b.id) AS board_count
            FROM sr_community_board_groups g
            LEFT JOIN sr_community_boards b ON b.board_group_id = g.id';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }
    $sql .= ' GROUP BY g.id, g.group_key, g.title, g.description, g.status, g.sort_order, g.created_at, g.updated_at'
        . sr_admin_sort_order_sql(sr_community_admin_board_group_sort_options(), $sort, sr_community_admin_board_group_default_sort());
    if ($limit > 0) {
        $sql .= ' LIMIT :limit_value OFFSET :offset_value';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($queryParts['params'] as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, PDO::PARAM_STR);
    }
    if ($limit > 0) {
        $stmt->bindValue('limit_value', max(1, min(1000, $limit)), PDO::PARAM_INT);
        $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_community_admin_board_group_status_counts(PDO $pdo, array $allowedStatuses): array
{
    $counts = ['total' => 0];
    foreach ($allowedStatuses as $status) {
        $counts[(string) $status] = 0;
    }

    $stmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_community_board_groups GROUP BY status');
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

function sr_community_enabled_board_groups(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT *
         FROM sr_community_board_groups
         WHERE status = 'enabled'
         ORDER BY sort_order ASC, id ASC"
    );

    return $stmt->fetchAll();
}

function sr_community_primary_menu_fallback_links(PDO $pdo): array
{
    $groupLinks = [];
    foreach (sr_community_enabled_board_groups($pdo) as $group) {
        $groupKey = (string) ($group['group_key'] ?? '');
        if (!sr_community_board_group_key_is_valid($groupKey)) {
            continue;
        }

        $groupLabel = trim((string) ($group['title'] ?? ''));
        $groupLinks[] = [
            'label' => $groupLabel !== '' ? $groupLabel : $groupKey,
            'url' => sr_community_board_group_path($groupKey),
            'group_key' => $groupKey,
        ];
    }

    if ($groupLinks !== []) {
        return $groupLinks;
    }

    $boardLinks = [];
    foreach (sr_community_enabled_boards($pdo) as $board) {
        if ((string) ($board['effective_read_policy'] ?? $board['read_policy'] ?? '') !== 'public') {
            continue;
        }

        $groupKey = (string) ($board['board_group_key'] ?? '');
        if (sr_community_board_group_key_is_valid($groupKey) && (string) ($board['board_group_status'] ?? '') === 'enabled') {
            continue;
        }

        $boardKey = (string) ($board['board_key'] ?? '');
        if (!sr_community_board_key_is_valid($boardKey)) {
            continue;
        }
        $boardLabel = trim((string) ($board['title'] ?? ''));
        $boardLinks[] = [
            'label' => $boardLabel !== '' ? $boardLabel : $boardKey,
            'url' => '/community/board?key=' . rawurlencode($boardKey),
            'board_key' => $boardKey,
        ];
    }

    return $boardLinks;
}

function sr_community_board_group_by_id(PDO $pdo, int $groupId): ?array
{
    if ($groupId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_community_board_groups WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $groupId]);
    $group = $stmt->fetch();

    return is_array($group) ? $group : null;
}

function sr_community_board_group_by_key(PDO $pdo, string $groupKey): ?array
{
    if (!sr_community_board_group_key_is_valid($groupKey)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_community_board_groups WHERE group_key = :group_key LIMIT 1');
    $stmt->execute(['group_key' => $groupKey]);
    $group = $stmt->fetch();

    return is_array($group) ? $group : null;
}

function sr_community_enabled_board_group_by_key(PDO $pdo, string $groupKey): ?array
{
    $group = sr_community_board_group_by_key($pdo, $groupKey);
    if (!is_array($group) || (string) ($group['status'] ?? '') !== 'enabled') {
        return null;
    }

    return $group;
}

function sr_community_create_board_group(PDO $pdo, array $data): int
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_board_groups
            (group_key, title, description, status, sort_order, created_at, updated_at)
         VALUES
            (:group_key, :title, :description, :status, :sort_order, :created_at, :updated_at)'
    );
    $stmt->execute([
        'group_key' => (string) $data['group_key'],
        'title' => (string) $data['title'],
        'description' => (string) $data['description'],
        'status' => (string) $data['status'],
        'sort_order' => (int) $data['sort_order'],
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_community_update_board_group(PDO $pdo, int $groupId, array $data): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_community_board_groups
         SET title = :title,
             description = :description,
             status = :status,
             sort_order = :sort_order,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'title' => (string) $data['title'],
        'description' => (string) $data['description'],
        'status' => (string) $data['status'],
        'sort_order' => (int) $data['sort_order'],
        'updated_at' => sr_now(),
        'id' => $groupId,
    ]);
}
