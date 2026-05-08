<?php

declare(strict_types=1);

function toy_community_public_board_by_key(PDO $pdo, string $boardKey): ?array
{
    $board = toy_community_board_by_key($pdo, $boardKey);
    if (!is_array($board) || (string) $board['status'] !== 'enabled' || (string) $board['read_policy'] !== 'public') {
        return null;
    }

    return $board;
}

function toy_community_public_posts(PDO $pdo, int $boardId, int $limit = 20): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(
        "SELECT id, board_id, author_account_id, title, body_text, body_format, status, view_count, last_commented_at, created_at, updated_at
         FROM toy_community_posts
         WHERE board_id = :board_id
           AND status = 'published'
         ORDER BY id DESC
         LIMIT :limit_value"
    );
    $stmt->bindValue('board_id', $boardId, PDO::PARAM_INT);
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function toy_community_public_post(PDO $pdo, int $postId): ?array
{
    if ($postId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT p.id, p.board_id, p.author_account_id, p.title, p.body_text, p.body_format, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
                b.board_key, b.title AS board_title, b.description AS board_description, b.status AS board_status, b.read_policy
         FROM toy_community_posts p
         INNER JOIN toy_community_boards b ON b.id = p.board_id
         WHERE p.id = :id
           AND p.status = 'published'
           AND b.status = 'enabled'
           AND b.read_policy = 'public'
         LIMIT 1"
    );
    $stmt->execute(['id' => $postId]);
    $post = $stmt->fetch();

    return is_array($post) ? $post : null;
}

function toy_community_public_comments(PDO $pdo, int $postId, int $limit = 50): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(
        "SELECT id, post_id, author_account_id, body_text, status, created_at, updated_at
         FROM toy_community_comments
         WHERE post_id = :post_id
           AND status = 'published'
         ORDER BY id ASC
         LIMIT :limit_value"
    );
    $stmt->bindValue('post_id', $postId, PDO::PARAM_INT);
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function toy_community_public_author_label(PDO $pdo, int $accountId): string
{
    $summary = toy_member_public_account_summary($pdo, $accountId);
    if (!is_array($summary) || (string) $summary['status'] === 'anonymized') {
        return '탈퇴 회원';
    }

    $displayName = trim((string) $summary['display_name']);
    return $displayName !== '' ? $displayName : '회원 #' . (string) $accountId;
}

function toy_community_plain_text_html(string $value): string
{
    return nl2br(toy_e($value), false);
}
