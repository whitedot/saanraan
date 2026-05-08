<?php

declare(strict_types=1);

function toy_community_account_has_scrap(PDO $pdo, int $accountId, int $postId): bool
{
    if ($accountId < 1 || $postId < 1) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM toy_community_scraps
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

function toy_community_add_scrap(PDO $pdo, int $accountId, int $postId): void
{
    if ($accountId < 1 || $postId < 1) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO toy_community_scraps
            (account_id, post_id, created_at)
         VALUES
            (:account_id, :post_id, :created_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'post_id' => $postId,
        'created_at' => toy_now(),
    ]);
}

function toy_community_remove_scrap(PDO $pdo, int $accountId, int $postId): void
{
    if ($accountId < 1 || $postId < 1) {
        return;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM toy_community_scraps
         WHERE account_id = :account_id
           AND post_id = :post_id'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'post_id' => $postId,
    ]);
}

function toy_community_account_scraps(PDO $pdo, int $accountId, int $limit = 50): array
{
    if ($accountId < 1) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(
        'SELECT s.id, s.account_id, s.post_id, s.created_at,
                p.title, p.status AS post_status, p.created_at AS post_created_at,
                b.board_key, b.title AS board_title, b.status AS board_status, b.read_policy
         FROM toy_community_scraps s
         LEFT JOIN toy_community_posts p ON p.id = s.post_id
         LEFT JOIN toy_community_boards b ON b.id = p.board_id
         WHERE s.account_id = :account_id
         ORDER BY s.id DESC
         LIMIT :limit_value'
    );
    $stmt->bindValue('account_id', $accountId, PDO::PARAM_INT);
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function toy_community_scrap_row_is_public(array $scrap): bool
{
    return (string) ($scrap['post_status'] ?? '') === 'published'
        && (string) ($scrap['board_status'] ?? '') === 'enabled'
        && (string) ($scrap['read_policy'] ?? '') === 'public';
}
