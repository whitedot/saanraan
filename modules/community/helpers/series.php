<?php

declare(strict_types=1);

function sr_community_series_statuses(): array
{
    return ['pending', 'active', 'hidden', 'archived', 'deleted'];
}

function sr_community_series_status_label(string $status): string
{
    return [
        'pending' => '대기',
        'active' => '사용',
        'hidden' => '숨김',
        'archived' => '보관',
        'deleted' => '삭제',
    ][$status] ?? $status;
}

function sr_community_series_visibility_values(): array
{
    return ['public', 'member', 'private'];
}

function sr_community_series_visibility_label(string $visibility): string
{
    return [
        'public' => '전체 공개',
        'member' => '회원 공개',
        'private' => '비공개',
    ][$visibility] ?? $visibility;
}

function sr_community_series_item_statuses(): array
{
    return ['active', 'hidden', 'removed'];
}

function sr_community_series_post_sort_order(string $key = 'series_sort_order'): ?int
{
    $value = $_POST[$key] ?? '';
    if (is_array($value)) {
        return null;
    }

    $value = trim((string) $value);
    if ($value === '' || strlen($value) > 10 || preg_match('/\A\d+\z/', $value) !== 1) {
        return null;
    }

    $sortOrder = (int) $value;
    if ($sortOrder < 0 || $sortOrder > 1000000) {
        return null;
    }

    return $sortOrder;
}

function sr_community_series_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_community_series LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_community_series_items_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_community_series_items LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_community_series_feature_enabled(PDO $pdo): bool
{
    try {
        $value = function_exists('sr_module_setting') ? sr_module_setting($pdo, 'community', 'series_enabled', '1') : '1';
    } catch (Throwable $exception) {
        $value = '1';
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function sr_community_series_schema_supported(PDO $pdo): bool
{
    return sr_community_series_table_exists($pdo) && sr_community_series_items_table_exists($pdo);
}

function sr_community_series_supported(PDO $pdo): bool
{
    return sr_community_series_feature_enabled($pdo) && sr_community_series_schema_supported($pdo);
}

function sr_community_series_unavailable_message(PDO $pdo): string
{
    return sr_community_series_feature_enabled($pdo)
        ? '커뮤니티 시리즈 스키마 업데이트가 아직 적용되지 않았습니다.'
        : '커뮤니티 시리즈 기능이 꺼져 있습니다.';
}

function sr_community_series_scraps_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_community_series_scraps LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_community_series_scraps_supported(PDO $pdo): bool
{
    return sr_community_series_supported($pdo) && sr_community_series_scraps_table_exists($pdo);
}

function sr_community_series_can_view(PDO $pdo, array $series, ?array $account = null): bool
{
    if ((string) ($series['status'] ?? '') !== 'active') {
        return false;
    }

    $visibility = (string) ($series['visibility'] ?? 'public');
    if ($visibility === 'member' && !is_array($account)) {
        return false;
    }
    if ($visibility === 'private' && (!is_array($account) || (int) ($account['id'] ?? 0) !== (int) ($series['owner_account_id'] ?? 0))) {
        return false;
    }

    $board = sr_community_board_by_id($pdo, (int) ($series['board_id'] ?? 0));
    return is_array($board)
        && (string) ($board['status'] ?? '') === 'enabled'
        && sr_community_account_can_read_board($pdo, $board, $account);
}

function sr_community_series_for_read(PDO $pdo, int $seriesId, ?array $account = null): ?array
{
    $series = sr_community_series_by_id($pdo, $seriesId);
    if (!is_array($series) || !sr_community_series_can_view($pdo, $series, $account)) {
        return null;
    }

    return $series;
}

function sr_community_account_series(PDO $pdo, int $accountId, int $boardId = 0): array
{
    if ($accountId < 1 || !sr_community_series_supported($pdo)) {
        return [];
    }

    $where = 'owner_account_id = :account_id AND status IN (\'pending\', \'active\', \'hidden\')';
    $params = ['account_id' => $accountId];
    if ($boardId > 0) {
        $where .= ' AND board_id = :board_id';
        $params['board_id'] = $boardId;
    }

    $stmt = $pdo->prepare(
        'SELECT id, board_id, owner_account_id, title, description, status, visibility, admin_note, created_at, updated_at
         FROM sr_community_series
         WHERE ' . $where . '
         ORDER BY updated_at DESC, id DESC
         LIMIT 200'
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_community_admin_series_filters(): array
{
    $status = sr_admin_get_allowed_array('status', sr_community_series_statuses(), 30);
    $visibility = sr_admin_get_allowed_single_array('visibility', sr_community_series_visibility_values(), 30);

    $field = sr_get_string('field', 20);
    if (!in_array($field, ['all', 'title', 'board', 'owner', 'note'], true)) {
        $field = 'all';
    }

    return [
        'status' => $status,
        'visibility' => $visibility,
        'field' => $field,
        'q' => trim(sr_get_string('q', 120)),
    ];
}

function sr_community_admin_series_query_parts(array $filters): array
{
    $where = [];
    $params = [];

    if (($filters['status'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('s.status', 'status', $filters['status']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if (($filters['visibility'] ?? []) !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('s.visibility', 'visibility', $filters['visibility']);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $field = (string) ($filters['field'] ?? 'all');
        if ($field === 'title') {
            $where[] = 's.title LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'board') {
            $where[] = 'b.title LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'owner') {
            $where[] = '(a.display_name LIKE :owner_keyword OR a.email LIKE :owner_keyword)';
            $params['owner_keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'note') {
            $where[] = 's.admin_note LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } else {
            $where[] = '(s.title LIKE :title_keyword OR b.title LIKE :board_keyword OR a.display_name LIKE :owner_keyword OR a.email LIKE :owner_keyword OR s.admin_note LIKE :note_keyword)';
            $params['title_keyword'] = '%' . $keyword . '%';
            $params['board_keyword'] = '%' . $keyword . '%';
            $params['owner_keyword'] = '%' . $keyword . '%';
            $params['note_keyword'] = '%' . $keyword . '%';
        }
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_community_admin_series_count(PDO $pdo, array $filters): int
{
    if (!sr_community_series_supported($pdo)) {
        return 0;
    }

    $queryParts = sr_community_admin_series_query_parts($filters);
    $sql = 'SELECT COUNT(*) AS count_value
            FROM sr_community_series s
            INNER JOIN sr_community_boards b ON b.id = s.board_id
            LEFT JOIN sr_member_accounts a ON a.id = s.owner_account_id';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_community_admin_series_status_counts(PDO $pdo): array
{
    $counts = ['total' => 0];
    foreach (sr_community_series_statuses() as $status) {
        $counts[$status] = 0;
    }

    if (!sr_community_series_supported($pdo)) {
        return $counts;
    }

    $stmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_community_series GROUP BY status');
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

function sr_community_admin_series_sort_options(): array
{
    return [
        'title' => ['columns' => ['s.title', 's.id']],
        'board_title' => ['columns' => ['b.title', 's.id']],
        'owner_display_name' => ['columns' => ['a.display_name', 's.id']],
        'status' => ['columns' => ['s.status', 's.id']],
        'visibility' => ['columns' => ['s.visibility', 's.id']],
        'active_item_count' => ['columns' => ['active_item_count', 's.id']],
        'updated_at' => ['columns' => ['s.updated_at', 's.id']],
    ];
}

function sr_community_admin_series_default_sort(): array
{
    return sr_admin_sort_default('updated_at', 'desc');
}

function sr_community_admin_series_list(PDO $pdo, array $filters, int $limit = 0, int $offset = 0, array $sort = []): array
{
    if (!sr_community_series_supported($pdo)) {
        return [];
    }

    $queryParts = sr_community_admin_series_query_parts($filters);
    $sql = 'SELECT s.*, b.title AS board_title, a.display_name AS owner_display_name,
                   (SELECT COUNT(*) FROM sr_community_series_items si WHERE si.series_id = s.id AND si.item_status = \'active\') AS active_item_count
            FROM sr_community_series s
            INNER JOIN sr_community_boards b ON b.id = s.board_id
            LEFT JOIN sr_member_accounts a ON a.id = s.owner_account_id';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }
    $sql .= sr_admin_sort_order_sql(sr_community_admin_series_sort_options(), $sort, sr_community_admin_series_default_sort());
    if ($limit > 0) {
        $sql .= ' LIMIT :limit_value OFFSET :offset_value';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($queryParts['params'] as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, is_int($paramValue) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    if ($limit > 0) {
        $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_community_series_by_id(PDO $pdo, int $seriesId): ?array
{
    if ($seriesId < 1 || !sr_community_series_supported($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_community_series WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $seriesId]);
    $series = $stmt->fetch();

    return is_array($series) ? $series : null;
}

function sr_community_series_items(PDO $pdo, int $seriesId, bool $publicOnly = false, ?array $account = null, int $currentPostId = 0): array
{
    if ($seriesId < 1 || !sr_community_series_supported($pdo)) {
        return [];
    }

    $where = 'si.series_id = :series_id';
    if ($publicOnly) {
        $where .= " AND si.item_status = 'active' AND p.status = 'published'";
    }

    $stmt = $pdo->prepare(
        'SELECT si.id, si.series_id, si.post_id, si.active_post_id, si.episode_label, si.item_status, si.sort_order,
                p.title AS post_title, p.status AS post_status, p.board_id, b.board_key, b.title AS board_title
         FROM sr_community_series_items si
         INNER JOIN sr_community_posts p ON p.id = si.post_id
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         WHERE ' . $where . '
         ORDER BY si.sort_order ASC, si.id ASC'
    );
    $stmt->execute(['series_id' => $seriesId]);
    $items = $stmt->fetchAll();
    if (!$publicOnly) {
        return $items;
    }

    $settings = sr_community_settings($pdo);
    $filtered = [];
    foreach ($items as $item) {
        $itemPostId = (int) ($item['post_id'] ?? 0);
        if ($itemPostId === $currentPostId) {
            $filtered[] = $item;
            continue;
        }

        $post = sr_community_post_for_read($pdo, $itemPostId, $account);
        if (!is_array($post)) {
            continue;
        }

        $board = sr_community_board_by_id($pdo, (int) ($post['board_id'] ?? 0));
        if (is_array($board)) {
            $paidReadConfig = sr_community_asset_event_config($pdo, $board, $settings, 'paid_read', 'once');
            if (sr_community_asset_event_required($paidReadConfig)) {
                $accountId = is_array($account) ? (int) ($account['id'] ?? 0) : 0;
                if ($accountId < 1) {
                    continue;
                }

                $hasPaidReadAccess = sr_community_has_paid_read_session($accountId, $itemPostId);
                if (!$hasPaidReadAccess && (string) ($paidReadConfig['charge_policy'] ?? 'once') === 'once') {
                    $couponDedupeKey = 'community.post.read:coupon:' . (string) $accountId . ':' . (string) $itemPostId;
                    $hasPaidReadAccess = sr_community_once_access_already_granted($pdo, $paidReadConfig, $accountId, 'post_read', $itemPostId, $couponDedupeKey);
                }

                if (!$hasPaidReadAccess) {
                    continue;
                }
            }
        }

        $filtered[] = $item;
    }

    return sr_community_series_items_with_navigation($filtered, $currentPostId, 'post_id');
}

function sr_community_series_for_post(PDO $pdo, int $postId, ?array $account = null): ?array
{
    if ($postId < 1 || !sr_community_series_supported($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT s.*, si.id AS item_id, si.episode_label, si.item_status, si.sort_order
         FROM sr_community_series_items si
         INNER JOIN sr_community_series s ON s.id = si.series_id
         WHERE si.active_post_id = :post_id
         LIMIT 1"
    );
    $stmt->execute(['post_id' => $postId]);
    $series = $stmt->fetch();
    if (!is_array($series) || (string) ($series['status'] ?? '') !== 'active' || (string) ($series['item_status'] ?? '') !== 'active') {
        return null;
    }

    $visibility = (string) ($series['visibility'] ?? 'public');
    if ($visibility === 'member' && !is_array($account)) {
        return null;
    }
    if ($visibility === 'private' && (!is_array($account) || (int) ($account['id'] ?? 0) !== (int) ($series['owner_account_id'] ?? 0))) {
        return null;
    }

    $series['items'] = sr_community_series_items($pdo, (int) $series['id'], true, $account, $postId);
    return $series;
}

function sr_community_series_items_with_navigation(array $items, int $currentId, string $idKey): array
{
    $previous = null;
    $next = null;
    foreach ($items as $index => $item) {
        if ((int) ($item[$idKey] ?? 0) === $currentId) {
            $previous = $items[$index - 1] ?? null;
            $next = $items[$index + 1] ?? null;
            break;
        }
    }

    foreach ($items as $index => $item) {
        $items[$index]['series_is_current'] = (int) ($item[$idKey] ?? 0) === $currentId ? 1 : 0;
        $items[$index]['series_is_previous'] = is_array($previous) && (int) ($previous[$idKey] ?? 0) === (int) ($item[$idKey] ?? 0) ? 1 : 0;
        $items[$index]['series_is_next'] = is_array($next) && (int) ($next[$idKey] ?? 0) === (int) ($item[$idKey] ?? 0) ? 1 : 0;
    }

    return $items;
}

function sr_community_active_series_item_for_post(PDO $pdo, int $postId): ?array
{
    if ($postId < 1 || !sr_community_series_supported($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT si.id, si.series_id, si.post_id, si.active_post_id, si.episode_label, si.item_status, si.sort_order,
                s.board_id, s.owner_account_id, s.title AS series_title, s.status AS series_status, s.visibility
         FROM sr_community_series_items si
         INNER JOIN sr_community_series s ON s.id = si.series_id
         WHERE si.active_post_id = :post_id
         LIMIT 1"
    );
    $stmt->execute(['post_id' => $postId]);
    $item = $stmt->fetch();

    return is_array($item) ? $item : null;
}

function sr_community_create_series(PDO $pdo, int $boardId, int $ownerAccountId, array $values, int $actorAccountId): int
{
    if (!sr_community_series_supported($pdo)) {
        throw new RuntimeException('Community series schema is not available.');
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_series
            (board_id, owner_account_id, title, description, status, visibility, created_by, updated_by, created_at, updated_at)
         VALUES
            (:board_id, :owner_account_id, :title, :description, :status, :visibility, :created_by, :updated_by, :created_at, :updated_at)'
    );
    $stmt->execute([
        'board_id' => $boardId,
        'owner_account_id' => $ownerAccountId,
        'title' => trim((string) $values['title']),
        'description' => trim((string) ($values['description'] ?? '')),
        'status' => (string) ($values['status'] ?? 'active'),
        'visibility' => (string) ($values['visibility'] ?? 'public'),
        'created_by' => $actorAccountId,
        'updated_by' => $actorAccountId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_community_update_series(PDO $pdo, int $seriesId, array $values, int $actorAccountId): void
{
    if (!sr_community_series_supported($pdo)) {
        throw new RuntimeException('Community series schema is not available.');
    }

    $setSql = 'title = :title,
             description = :description,
             status = :status,
             visibility = :visibility,
             updated_by = :updated_by,
             updated_at = :updated_at';
    $params = [
        'title' => trim((string) $values['title']),
        'description' => trim((string) ($values['description'] ?? '')),
        'status' => (string) ($values['status'] ?? 'active'),
        'visibility' => (string) ($values['visibility'] ?? 'public'),
        'updated_by' => $actorAccountId,
        'updated_at' => sr_now(),
        'id' => $seriesId,
    ];
    if (array_key_exists('admin_note', $values)) {
        $setSql .= ',
             admin_note = :admin_note,
             moderated_by = :moderated_by,
             moderated_at = :moderated_at';
        $params['admin_note'] = trim((string) ($values['admin_note'] ?? ''));
        $params['moderated_by'] = $actorAccountId;
        $params['moderated_at'] = sr_now();
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_community_series
         SET ' . $setSql . '
         WHERE id = :id'
    );
    $stmt->execute($params);

    if (in_array((string) ($values['status'] ?? ''), ['archived', 'deleted'], true)) {
        sr_community_remove_series_items($pdo, $seriesId);
    }
}

function sr_community_set_post_series(PDO $pdo, int $postId, int $seriesId, string $episodeLabel, int $sortOrder, int $actorAccountId): void
{
    if (!sr_community_series_supported($pdo)) {
        if ($seriesId > 0) {
            throw new RuntimeException('Community series schema is not available.');
        }

        return;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE sr_community_series_items SET active_post_id = NULL, item_status = \'removed\', updated_at = :updated_at WHERE active_post_id = :post_id')
            ->execute(['updated_at' => sr_now(), 'post_id' => $postId]);

        if ($seriesId > 0) {
            $series = sr_community_series_by_id($pdo, $seriesId);
            $post = sr_community_admin_post_by_id($pdo, $postId);
            if (!is_array($series) || !is_array($post) || (int) $series['board_id'] !== (int) $post['board_id']) {
                throw new RuntimeException('시리즈와 게시글 게시판이 일치하지 않습니다.');
            }
            $now = sr_now();
            $stmt = $pdo->prepare(
                'INSERT INTO sr_community_series_items
                    (series_id, post_id, active_post_id, episode_label, item_status, sort_order, created_by, created_at, updated_at)
                 VALUES
                    (:series_id, :post_id, :active_post_id, :episode_label, \'active\', :sort_order, :created_by, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    active_post_id = VALUES(active_post_id),
                    episode_label = VALUES(episode_label),
                    item_status = \'active\',
                    sort_order = VALUES(sort_order),
                    updated_at = VALUES(updated_at)'
            );
            $stmt->execute([
                'series_id' => $seriesId,
                'post_id' => $postId,
                'active_post_id' => $postId,
                'episode_label' => trim($episodeLabel),
                'sort_order' => $sortOrder,
                'created_by' => $actorAccountId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $pdo->prepare('UPDATE sr_community_series SET updated_by = :updated_by, updated_at = :updated_at WHERE id = :id')
                ->execute(['updated_by' => $actorAccountId, 'updated_at' => $now, 'id' => $seriesId]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_community_remove_series_items(PDO $pdo, int $seriesId): void
{
    if (!sr_community_series_supported($pdo)) {
        return;
    }

    $stmt = $pdo->prepare(
        "UPDATE sr_community_series_items
         SET active_post_id = NULL,
             item_status = 'removed',
             updated_at = :updated_at
         WHERE series_id = :series_id"
    );
    $stmt->execute(['updated_at' => sr_now(), 'series_id' => $seriesId]);
}
