<?php

declare(strict_types=1);

function sr_community_account_has_scrap(PDO $pdo, int $accountId, int $postId): bool
{
    if ($accountId < 1 || $postId < 1) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_community_scraps
         WHERE account_id = :account_id
           AND post_id = :post_id
         LIMIT 1'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'post_id' => $postId,
    ]);

    return is_array($stmt->fetch());
}

function sr_community_account_has_series_scrap(PDO $pdo, int $accountId, int $seriesId): bool
{
    if ($accountId < 1 || $seriesId < 1 || !sr_community_series_scraps_supported($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_community_series_scraps
         WHERE account_id = :account_id
           AND series_id = :series_id
         LIMIT 1'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'series_id' => $seriesId,
    ]);

    return is_array($stmt->fetch());
}

function sr_community_add_scrap(PDO $pdo, int $accountId, int $postId): bool
{
    if ($accountId < 1 || $postId < 1) {
        return false;
    }

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO sr_community_scraps
            (account_id, post_id, created_at)
         VALUES
            (:account_id, :post_id, :created_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'post_id' => $postId,
        'created_at' => sr_now(),
    ]);

    return $stmt->rowCount() > 0;
}

function sr_community_remove_scrap(PDO $pdo, int $accountId, int $postId): bool
{
    if ($accountId < 1 || $postId < 1) {
        return false;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM sr_community_scraps
         WHERE account_id = :account_id
           AND post_id = :post_id'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'post_id' => $postId,
    ]);

    return $stmt->rowCount() > 0;
}

function sr_community_add_series_scrap(PDO $pdo, int $accountId, int $seriesId): bool
{
    if ($accountId < 1 || $seriesId < 1 || !sr_community_series_scraps_supported($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO sr_community_series_scraps
            (account_id, series_id, created_at)
         VALUES
            (:account_id, :series_id, :created_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'series_id' => $seriesId,
        'created_at' => sr_now(),
    ]);

    return $stmt->rowCount() > 0;
}

function sr_community_remove_series_scrap(PDO $pdo, int $accountId, int $seriesId): bool
{
    if ($accountId < 1 || $seriesId < 1 || !sr_community_series_scraps_supported($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM sr_community_series_scraps
         WHERE account_id = :account_id
           AND series_id = :series_id'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'series_id' => $seriesId,
    ]);

    return $stmt->rowCount() > 0;
}

function sr_community_account_scrap_count(PDO $pdo, int $accountId): int
{
    if ($accountId < 1) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sr_community_scraps WHERE account_id = :account_id');
    $stmt->execute(['account_id' => $accountId]);

    return max(0, (int) $stmt->fetchColumn());
}

function sr_community_account_scraps(PDO $pdo, int $accountId, ?array $account = null, int $limit = 50, int $offset = 0): array
{
    if ($accountId < 1) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $categorySupported = sr_community_categories_supported($pdo);
    $categorySelectSql = $categorySupported
        ? 'cat.category_key, cat.title AS category_title, cat.status AS category_status'
        : 'NULL AS category_key, NULL AS category_title, NULL AS category_status';
    $categoryJoinSql = $categorySupported ? 'LEFT JOIN sr_community_categories cat ON cat.id = p.category_id' : '';
    $stmt = $pdo->prepare(
        'SELECT s.id, s.account_id, s.post_id, s.created_at,
                p.title, p.status AS post_status, p.created_at AS post_created_at,
                (SELECT COUNT(*) FROM sr_community_comments c WHERE c.post_id = p.id AND c.status = \'published\') AS published_comment_count,
                ' . $categorySelectSql . ',
                b.id AS board_id,
                b.board_group_id,
                b.board_key, b.title AS board_title, b.status AS board_status, b.read_policy
         FROM sr_community_scraps s
         LEFT JOIN sr_community_posts p ON p.id = s.post_id
         LEFT JOIN sr_community_boards b ON b.id = p.board_id
         ' . $categoryJoinSql . '
         WHERE s.account_id = :account_id
         ORDER BY s.id DESC
         LIMIT :limit_value OFFSET :offset_value'
    );
    $stmt->bindValue('account_id', $accountId, PDO::PARAM_INT);
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset_value', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $scraps = $stmt->fetchAll();
    foreach ($scraps as &$scrap) {
        $board = [
            'id' => (int) ($scrap['board_id'] ?? 0),
            'board_group_id' => (int) ($scrap['board_group_id'] ?? 0),
            'status' => (string) ($scrap['board_status'] ?? ''),
            'read_policy' => (string) ($scrap['read_policy'] ?? ''),
        ];
        $scrap['can_view'] = (string) ($scrap['post_status'] ?? '') === 'published'
            && sr_community_account_can_read_board($pdo, $board, $account);
    }
    unset($scrap);

    return $scraps;
}

function sr_community_account_series_scrap_count(PDO $pdo, int $accountId): int
{
    if ($accountId < 1 || !sr_community_series_scraps_supported($pdo)) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sr_community_series_scraps WHERE account_id = :account_id');
    $stmt->execute(['account_id' => $accountId]);

    return max(0, (int) $stmt->fetchColumn());
}

function sr_community_account_series_scraps(PDO $pdo, int $accountId, ?array $account = null, int $limit = 50, int $offset = 0): array
{
    if ($accountId < 1 || !sr_community_series_scraps_supported($pdo)) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);
    $stmt = $pdo->prepare(
        'SELECT ss.id, ss.account_id, ss.series_id, ss.created_at,
                s.board_id, s.owner_account_id, s.title, s.description, s.status AS series_status,
                s.visibility, s.created_at AS series_created_at, s.updated_at AS series_updated_at,
                b.board_key, b.title AS board_title, b.status AS board_status, b.read_policy
         FROM sr_community_series_scraps ss
         LEFT JOIN sr_community_series s ON s.id = ss.series_id
         LEFT JOIN sr_community_boards b ON b.id = s.board_id
         WHERE ss.account_id = :account_id
         ORDER BY ss.id DESC
         LIMIT :limit_value OFFSET :offset_value'
    );
    $stmt->bindValue('account_id', $accountId, PDO::PARAM_INT);
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset_value', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $scraps = $stmt->fetchAll();
    foreach ($scraps as &$scrap) {
        $series = [
            'board_id' => (int) ($scrap['board_id'] ?? 0),
            'owner_account_id' => (int) ($scrap['owner_account_id'] ?? 0),
            'status' => (string) ($scrap['series_status'] ?? ''),
            'visibility' => (string) ($scrap['visibility'] ?? ''),
        ];
        $scrap['can_view'] = sr_community_series_can_view($pdo, $series, $account);
    }
    unset($scrap);

    return $scraps;
}

function sr_community_scrap_row_can_view(array $scrap): bool
{
    return !empty($scrap['can_view']);
}
