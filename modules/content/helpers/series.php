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

function sr_content_series_status_label(string $status): string
{
    return [
        'pending' => '대기',
        'active' => '사용',
        'hidden' => '숨김',
        'archived' => '보관',
        'deleted' => '삭제',
    ][$status] ?? $status;
}

function sr_content_series_visibility_values(): array
{
    return ['public', 'member', 'private'];
}

function sr_content_series_visibility_label(string $visibility): string
{
    return [
        'public' => '전체 공개',
        'member' => '회원 공개',
        'private' => '비공개',
    ][$visibility] ?? $visibility;
}

function sr_content_series_table_exists(PDO $pdo): bool
{
    static $existsByConnection = [];
    $cacheKey = (string) spl_object_id($pdo);
    if (array_key_exists($cacheKey, $existsByConnection)) {
        return $existsByConnection[$cacheKey];
    }

    try {
        $pdo->query(
            'SELECT id, series_key, title, description, status, visibility, sort_order, created_by, updated_by, created_at, updated_at
             FROM sr_content_series
             LIMIT 0'
        );
        $existsByConnection[$cacheKey] = true;
    } catch (Throwable $exception) {
        $existsByConnection[$cacheKey] = false;
    }

    return $existsByConnection[$cacheKey];
}

function sr_content_series_items_table_exists(PDO $pdo): bool
{
    static $existsByConnection = [];
    $cacheKey = (string) spl_object_id($pdo);
    if (array_key_exists($cacheKey, $existsByConnection)) {
        return $existsByConnection[$cacheKey];
    }

    try {
        $pdo->query(
            'SELECT id, series_id, content_id, active_content_id, episode_label, item_status, sort_order, created_by, created_at, updated_at
             FROM sr_content_series_items
             LIMIT 0'
        );
        $existsByConnection[$cacheKey] = true;
    } catch (Throwable $exception) {
        $existsByConnection[$cacheKey] = false;
    }

    return $existsByConnection[$cacheKey];
}

function sr_content_series_feature_enabled(PDO $pdo): bool
{
    try {
        $value = function_exists('sr_module_setting') ? sr_module_setting($pdo, 'content', 'series_enabled', '1') : '1';
    } catch (Throwable $exception) {
        $value = '1';
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function sr_content_series_schema_supported(PDO $pdo): bool
{
    return sr_content_series_table_exists($pdo) && sr_content_series_items_table_exists($pdo);
}

function sr_content_series_supported(PDO $pdo): bool
{
    return sr_content_series_feature_enabled($pdo) && sr_content_series_schema_supported($pdo);
}

function sr_content_series_unavailable_message(PDO $pdo): string
{
    return sr_content_series_feature_enabled($pdo)
        ? '콘텐츠 시리즈 스키마 업데이트가 아직 적용되지 않았습니다.'
        : '콘텐츠 시리즈 기능이 꺼져 있습니다.';
}

function sr_content_series_by_id(PDO $pdo, int $seriesId): ?array
{
    if ($seriesId < 1 || !sr_content_series_supported($pdo)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM sr_content_series WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $seriesId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function sr_content_series_by_key(PDO $pdo, string $seriesKey): ?array
{
    if (!sr_content_series_key_is_valid($seriesKey) || !sr_content_series_supported($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_content_series WHERE series_key = :series_key LIMIT 1');
    $stmt->execute(['series_key' => $seriesKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_series_key_exists(PDO $pdo, string $seriesKey, int $exceptSeriesId = 0): bool
{
    if (!sr_content_series_key_is_valid($seriesKey) || !sr_content_series_supported($pdo)) {
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
    if (!sr_content_series_supported($pdo)) {
        return [];
    }

    $stmt = $pdo->query('SELECT * FROM sr_content_series ORDER BY sort_order ASC, id DESC LIMIT 200');
    return $stmt->fetchAll();
}

function sr_content_admin_series_filters(): array
{
    $statuses = sr_content_admin_multi_filter_values('status', sr_content_series_statuses());
    $visibilities = sr_content_admin_single_filter_values('visibility', sr_content_series_visibility_values());

    $field = sr_get_string('field', 20);
    if (!in_array($field, ['all', 'key', 'title'], true)) {
        $field = 'all';
    }

    return [
        'status' => $statuses,
        'visibility' => $visibilities,
        'field' => $field,
        'q' => trim(sr_get_string('q', 120)),
    ];
}

function sr_content_admin_series_query_parts(array $filters): array
{
    $where = [];
    $params = [];

    $statuses = is_array($filters['status'] ?? null) ? $filters['status'] : [];
    if ($statuses !== []) {
        $placeholders = [];
        foreach (array_values($statuses) as $index => $status) {
            $paramKey = 'status_' . (string) $index;
            $placeholders[] = ':' . $paramKey;
            $params[$paramKey] = (string) $status;
        }
        $where[] = 's.status IN (' . implode(', ', $placeholders) . ')';
    }

    $visibilities = is_array($filters['visibility'] ?? null) ? $filters['visibility'] : [];
    if ($visibilities !== []) {
        $placeholders = [];
        foreach (array_values($visibilities) as $index => $visibility) {
            $paramKey = 'visibility_' . (string) $index;
            $placeholders[] = ':' . $paramKey;
            $params[$paramKey] = (string) $visibility;
        }
        $where[] = 's.visibility IN (' . implode(', ', $placeholders) . ')';
    }

    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $field = (string) ($filters['field'] ?? 'all');
        if ($field === 'key') {
            $where[] = 's.series_key LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } elseif ($field === 'title') {
            $where[] = 's.title LIKE :keyword';
            $params['keyword'] = '%' . $keyword . '%';
        } else {
            $where[] = '(s.series_key LIKE :key_keyword OR s.title LIKE :title_keyword)';
            $params['key_keyword'] = '%' . $keyword . '%';
            $params['title_keyword'] = '%' . $keyword . '%';
        }
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_content_admin_series_count(PDO $pdo, array $filters): int
{
    if (!sr_content_series_supported($pdo)) {
        return 0;
    }

    $queryParts = sr_content_admin_series_query_parts($filters);
    $sql = 'SELECT COUNT(*) AS count_value FROM sr_content_series s';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_content_admin_series_status_counts(PDO $pdo): array
{
    $counts = ['total' => 0];
    foreach (sr_content_series_statuses() as $status) {
        $counts[$status] = 0;
    }

    if (!sr_content_series_supported($pdo)) {
        return $counts;
    }

    $stmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_content_series GROUP BY status');
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

function sr_content_admin_series_sort_options(): array
{
    return [
        'series_key' => ['columns' => ['s.series_key', 's.id']],
        'title' => ['columns' => ['s.title', 's.id']],
        'status' => ['columns' => ['s.status', 's.id']],
        'visibility' => ['columns' => ['s.visibility', 's.id']],
        'active_item_count' => ['columns' => ['active_item_count', 's.id']],
        'sort_order' => ['columns' => ['s.sort_order', 's.id']],
        'updated_at' => ['columns' => ['s.updated_at', 's.id']],
    ];
}

function sr_content_admin_series_default_sort(): array
{
    return sr_admin_sort_default('sort_order', 'asc');
}

function sr_content_admin_series_list(PDO $pdo, array $filters, int $limit = 0, int $offset = 0, array $sort = []): array
{
    if (!sr_content_series_supported($pdo)) {
        return [];
    }

    $queryParts = sr_content_admin_series_query_parts($filters);
    $where = $queryParts['where'];
    $params = $queryParts['params'];
    $sql = 'SELECT s.*,
                   (SELECT COUNT(*) FROM sr_content_series_items si WHERE si.series_id = s.id AND si.item_status = \'active\') AS active_item_count
            FROM sr_content_series s';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= sr_admin_sort_order_sql(sr_content_admin_series_sort_options(), $sort, sr_content_admin_series_default_sort());
    if ($limit > 0) {
        $sql .= ' LIMIT :limit_value OFFSET :offset_value';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, is_int($paramValue) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    if ($limit > 0) {
        $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();

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

    if ((string) ($page['asset_charge_policy'] ?? 'once') !== 'once') {
        return false;
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
    if ($seriesId < 1 || !sr_content_series_supported($pdo)) {
        return [];
    }
    $where = 'si.series_id = :series_id';
    if ($publicOnly) {
        $where .= " AND si.item_status = 'active' AND c.status = 'published'";
    }
    $stmt = $pdo->prepare(
        'SELECT c.*, si.id AS item_id, si.series_id, si.content_id, si.active_content_id, si.episode_label, si.item_status, si.sort_order,
                c.slug, c.title AS content_title, c.status AS content_status
         FROM sr_content_series_items si
         INNER JOIN sr_content_items c ON c.id = si.content_id
         WHERE ' . $where . '
         ORDER BY si.sort_order ASC, si.id ASC'
    );
    $stmt->execute(['series_id' => $seriesId]);
    $items = array_map(static function (array $row) use ($pdo): array {
        return sr_content_with_effective_settings($pdo, $row);
    }, $stmt->fetchAll());
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
    if ($contentId < 1 || !sr_content_series_supported($pdo)) {
        return null;
    }

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
    $series['price_summary'] = sr_content_series_price_summary($pdo, (int) $series['id'], $account);
    return $series;
}

function sr_content_series_price_summary(PDO $pdo, int $seriesId, ?array $account = null): array
{
    if ($seriesId < 1 || !sr_content_series_supported($pdo)) {
        return ['has_paid_items' => false, 'base_amounts' => [], 'member_amounts' => [], 'remaining_amounts' => [], 'paid_item_count' => 0, 'account_checked' => false];
    }

    sr_content_publish_due_scheduled($pdo);
    $stmt = $pdo->prepare(
        "SELECT c.*
         FROM sr_content_series_items si
         INNER JOIN sr_content_items c ON c.id = si.content_id
         WHERE si.series_id = :series_id
           AND si.item_status = 'active'
           AND c.status = 'published'
         ORDER BY si.sort_order ASC, si.id ASC"
    );
    $stmt->execute(['series_id' => $seriesId]);

    $accountId = is_array($account) ? (int) ($account['id'] ?? 0) : 0;
    $baseAmounts = [];
    $memberAmounts = [];
    $remainingAmounts = [];
    $paidItemCount = 0;

    foreach ($stmt->fetchAll() as $page) {
        $page = sr_content_with_effective_settings($pdo, $page);
        if (!sr_content_asset_access_required($page)) {
            continue;
        }

        $assetModules = sr_content_asset_module_keys_from_value($page['asset_module'] ?? '');
        if ($assetModules === []) {
            continue;
        }

        $amounts = sr_content_asset_amounts_from_value($page['asset_access_amounts_json'] ?? '', $assetModules, (int) ($page['asset_access_amount'] ?? 0));
        if ($amounts === []) {
            $amounts[(string) $assetModules[0]] = (int) ($page['asset_access_amount'] ?? 0);
        }

        $paidItemCount += 1;
        foreach ($amounts as $assetModule => $amount) {
            $baseAmounts[(string) $assetModule] = (int) ($baseAmounts[(string) $assetModule] ?? 0) + max(0, (int) $amount);
        }

        $effectiveAmounts = $amounts;
        if ($accountId > 0) {
            $policyAmounts = sr_content_asset_amounts_with_group_policy($pdo, $accountId, $assetModules, $amounts, (int) ($page['asset_access_amount'] ?? 0), $page['asset_access_group_policies_json'] ?? '', (int) ($page['asset_access_policy_set_id'] ?? 0));
            $effectiveAmounts = $policyAmounts['amounts'] !== [] ? $policyAmounts['amounts'] : [];
        }
        foreach ($effectiveAmounts as $assetModule => $amount) {
            $memberAmounts[(string) $assetModule] = (int) ($memberAmounts[(string) $assetModule] ?? 0) + max(0, (int) $amount);
        }

        $alreadyGranted = $accountId > 0
            && (string) ($page['asset_charge_policy'] ?? 'once') === 'once'
            && sr_content_once_access_already_granted($pdo, $assetModules, $accountId, (int) $page['id']);
        if (!$alreadyGranted) {
            foreach ($effectiveAmounts as $assetModule => $amount) {
                $remainingAmounts[(string) $assetModule] = (int) ($remainingAmounts[(string) $assetModule] ?? 0) + max(0, (int) $amount);
            }
        }
    }

    return [
        'has_paid_items' => $paidItemCount > 0,
        'paid_item_count' => $paidItemCount,
        'base_amounts' => $baseAmounts,
        'member_amounts' => $memberAmounts,
        'remaining_amounts' => $remainingAmounts,
        'account_checked' => $accountId > 0,
    ];
}

function sr_content_series_price_summary_text(PDO $pdo, array $summary): string
{
    if (empty($summary['has_paid_items'])) {
        return '';
    }

    $formatAmounts = static function (array $amounts) use ($pdo): string {
        $parts = [];
        foreach ($amounts as $assetModule => $amount) {
            if ((int) $amount > 0) {
                $parts[] = sr_content_asset_module_label((string) $assetModule, $pdo) . ' ' . number_format((int) $amount);
            }
        }
        return implode(', ', $parts);
    };

    $base = $formatAmounts(is_array($summary['base_amounts'] ?? null) ? $summary['base_amounts'] : []);
    $member = $formatAmounts(is_array($summary['member_amounts'] ?? null) ? $summary['member_amounts'] : []);
    $remaining = $formatAmounts(is_array($summary['remaining_amounts'] ?? null) ? $summary['remaining_amounts'] : []);

    if ($base === '') {
        return '';
    }
    $accountChecked = !empty($summary['account_checked']);
    $remainingSuffix = '';
    if ($accountChecked) {
        $remainingSuffix = ' / 남은 금액 ' . ($remaining !== '' ? $remaining : '0');
    } elseif ($remaining !== '' && $remaining !== $base && $remaining !== $member) {
        $remainingSuffix = ' / 남은 금액 ' . $remaining;
    }

    if ($member !== '' && $member !== $base) {
        return '완독 예상 금액: 원가 ' . $base . ' / 회원가 ' . $member . $remainingSuffix;
    }

    return '완독 예상 금액: ' . $base . $remainingSuffix;
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
    if ($contentId < 1 || !sr_content_series_supported($pdo)) {
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
    if (!sr_content_series_supported($pdo)) {
        throw new RuntimeException('Content series schema is not available.');
    }

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
    if (!sr_content_series_supported($pdo)) {
        throw new RuntimeException('Content series schema is not available.');
    }

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
    if (!sr_content_series_supported($pdo)) {
        if ($seriesId > 0) {
            throw new RuntimeException('Content series schema is not available.');
        }

        return;
    }

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
    if (!sr_content_series_supported($pdo)) {
        return;
    }

    $stmt = $pdo->prepare(
        "UPDATE sr_content_series_items
         SET active_content_id = NULL,
             item_status = 'removed',
             updated_at = :updated_at
         WHERE series_id = :series_id"
    );
    $stmt->execute(['updated_at' => sr_now(), 'series_id' => $seriesId]);
}

function sr_content_series_reference_counts(PDO $pdo, int $seriesId): array
{
    return [
        'items' => $seriesId > 0 && sr_content_series_supported($pdo)
            ? sr_content_optional_count($pdo, 'sr_content_series_items', 'series_id = :series_id', ['series_id' => $seriesId])
            : 0,
    ];
}

function sr_content_series_external_reference_counts(PDO $pdo, int $seriesId): array
{
    if ($seriesId < 1 || !sr_content_series_supported($pdo)) {
        return [];
    }

    return [];
}

function sr_content_can_delete_series(PDO $pdo, int $seriesId): array
{
    $series = sr_content_series_by_id($pdo, $seriesId);
    if (!is_array($series)) {
        return ['can_delete' => false, 'errors' => ['콘텐츠 시리즈를 찾을 수 없습니다.'], 'references' => [], 'external_references' => []];
    }

    $references = sr_content_series_reference_counts($pdo, $seriesId);
    $externalReferences = sr_content_series_external_reference_counts($pdo, $seriesId);
    $errors = [];
    if (array_sum(array_map('intval', $externalReferences)) > 0) {
        $errors[] = '외부 운영 참조가 있어 콘텐츠 시리즈를 삭제할 수 없습니다.';
    }

    return ['can_delete' => $errors === [], 'errors' => $errors, 'references' => $references, 'external_references' => $externalReferences, 'series' => $series];
}

function sr_content_delete_series(PDO $pdo, int $seriesId): array
{
    $check = sr_content_can_delete_series($pdo, $seriesId);
    if (empty($check['can_delete']) || !is_array($check['series'] ?? null)) {
        return $check;
    }

    $pdo->beginTransaction();
    try {
        $deletedItems = sr_content_optional_count($pdo, 'sr_content_series_items', 'series_id = :series_id', ['series_id' => $seriesId]);
        $pdo->prepare('DELETE FROM sr_content_series_items WHERE series_id = :series_id')->execute(['series_id' => $seriesId]);
        $pdo->prepare('DELETE FROM sr_content_series WHERE id = :id')->execute(['id' => $seriesId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    $check['deleted_items'] = $deletedItems;
    return $check;
}
