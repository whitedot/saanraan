<?php

declare(strict_types=1);

function toy_community_board_key_is_valid(string $boardKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $boardKey) === 1;
}

function toy_community_board_statuses(): array
{
    return ['enabled', 'disabled', 'archived'];
}

function toy_community_policy_values(string $policy): array
{
    if ($policy === 'read') {
        return ['public', 'member', 'group'];
    }

    if ($policy === 'write') {
        return ['member', 'group', 'admin'];
    }

    if ($policy === 'comment') {
        return ['member', 'group', 'disabled'];
    }

    return [];
}

function toy_community_boards(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, board_key, title, description, status, read_policy, write_policy, comment_policy, image_uploads_enabled, sort_order, created_at, updated_at
         FROM toy_community_boards
         ORDER BY sort_order ASC, id ASC'
    );

    return $stmt->fetchAll();
}

function toy_community_enabled_boards(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id, board_key, title, description, status, read_policy, write_policy, comment_policy, image_uploads_enabled, sort_order, created_at, updated_at
         FROM toy_community_boards
         WHERE status = 'enabled'
         ORDER BY sort_order ASC, id ASC"
    );

    return $stmt->fetchAll();
}

function toy_community_board_by_key(PDO $pdo, string $boardKey): ?array
{
    if (!toy_community_board_key_is_valid($boardKey)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, board_key, title, description, status, read_policy, write_policy, comment_policy, image_uploads_enabled, sort_order, created_at, updated_at
         FROM toy_community_boards
         WHERE board_key = :board_key
         LIMIT 1'
    );
    $stmt->execute(['board_key' => $boardKey]);
    $board = $stmt->fetch();

    return is_array($board) ? $board : null;
}

function toy_community_create_board(PDO $pdo, array $data): int
{
    $now = toy_now();
    $stmt = $pdo->prepare(
        'INSERT INTO toy_community_boards
            (board_key, title, description, status, read_policy, write_policy, comment_policy, image_uploads_enabled, sort_order, created_at, updated_at)
         VALUES
            (:board_key, :title, :description, :status, :read_policy, :write_policy, :comment_policy, :image_uploads_enabled, :sort_order, :created_at, :updated_at)'
    );
    $stmt->execute([
        'board_key' => (string) $data['board_key'],
        'title' => (string) $data['title'],
        'description' => (string) $data['description'],
        'status' => (string) $data['status'],
        'read_policy' => (string) $data['read_policy'],
        'write_policy' => (string) $data['write_policy'],
        'comment_policy' => (string) $data['comment_policy'],
        'image_uploads_enabled' => !empty($data['image_uploads_enabled']) ? 1 : 0,
        'sort_order' => (int) $data['sort_order'],
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}
