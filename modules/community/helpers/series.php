<?php

declare(strict_types=1);

function sr_community_series_statuses(): array
{
    return ['pending', 'active', 'hidden', 'archived', 'deleted'];
}

function sr_community_series_visibility_values(): array
{
    return ['public', 'member', 'private'];
}

function sr_community_series_item_statuses(): array
{
    return ['active', 'hidden', 'removed'];
}

function sr_community_account_series(PDO $pdo, int $accountId, int $boardId = 0): array
{
    if ($accountId < 1) {
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

function sr_community_series_by_id(PDO $pdo, int $seriesId): ?array
{
    if ($seriesId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_community_series WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $seriesId]);
    $series = $stmt->fetch();

    return is_array($series) ? $series : null;
}

function sr_community_series_items(PDO $pdo, int $seriesId, bool $publicOnly = false, ?array $account = null, int $currentPostId = 0): array
{
    if ($seriesId < 1) {
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
    if ($postId < 1) {
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
    if ($postId < 1) {
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
    $stmt = $pdo->prepare(
        "UPDATE sr_community_series_items
         SET active_post_id = NULL,
             item_status = 'removed',
             updated_at = :updated_at
         WHERE series_id = :series_id"
    );
    $stmt->execute(['updated_at' => sr_now(), 'series_id' => $seriesId]);
}
