<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return static function (PDO $pdo, ?array $site): array {
    $entries = [];

    $stmt = $pdo->query(
        "SELECT id, board_group_id, board_key, status, read_policy, updated_at
         FROM toy_community_boards
         WHERE status = 'enabled'
         ORDER BY sort_order ASC, id ASC
         LIMIT 1000"
    );
    foreach ($stmt->fetchAll() as $board) {
        if (!toy_community_account_can_read_board($pdo, $board, null)) {
            continue;
        }

        $entries[] = [
            'loc' => '/community/board?key=' . rawurlencode((string) $board['board_key']),
            'lastmod' => substr((string) $board['updated_at'], 0, 10),
        ];
    }

    $stmt = $pdo->query(
        "SELECT p.id, p.board_id, p.updated_at,
                b.board_group_id, b.status AS board_status, b.read_policy
         FROM toy_community_posts p
         INNER JOIN toy_community_boards b ON b.id = p.board_id
         WHERE p.status = 'published'
           AND b.status = 'enabled'
         ORDER BY p.updated_at DESC
         LIMIT 1000"
    );
    foreach ($stmt->fetchAll() as $post) {
        $board = [
            'id' => (int) $post['board_id'],
            'board_group_id' => (int) ($post['board_group_id'] ?? 0),
            'status' => (string) $post['board_status'],
            'read_policy' => (string) $post['read_policy'],
        ];
        if (!toy_community_account_can_read_board($pdo, $board, null)) {
            continue;
        }

        $entries[] = [
            'loc' => '/community/post?id=' . (int) $post['id'],
            'lastmod' => substr((string) $post['updated_at'], 0, 10),
        ];
    }

    return $entries;
};
