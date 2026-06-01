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

function sr_community_account_scraps(PDO $pdo, int $accountId, ?array $account = null, int $limit = 50): array
{
    if ($accountId < 1) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(
        'SELECT s.id, s.account_id, s.post_id, s.created_at,
                p.title, p.status AS post_status, p.created_at AS post_created_at,
                cat.category_key, cat.title AS category_title, cat.status AS category_status,
                b.id AS board_id,
                b.board_group_id,
                b.board_key, b.title AS board_title, b.status AS board_status, b.read_policy
         FROM sr_community_scraps s
         LEFT JOIN sr_community_posts p ON p.id = s.post_id
         LEFT JOIN sr_community_boards b ON b.id = p.board_id
         LEFT JOIN sr_community_categories cat ON cat.id = p.category_id
         WHERE s.account_id = :account_id
         ORDER BY s.id DESC
         LIMIT :limit_value'
    );
    $stmt->bindValue('account_id', $accountId, PDO::PARAM_INT);
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
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

function sr_community_scrap_row_can_view(array $scrap): bool
{
    return !empty($scrap['can_view']);
}

function sr_community_scrap_row_is_public(array $scrap): bool
{
    return sr_community_scrap_row_can_view($scrap);
}
