<?php

declare(strict_types=1);

return static function (PDO $pdo, ?array $site): array {
    $entries = [];

    $stmt = $pdo->query(
        "SELECT board_key, updated_at
         FROM toy_community_boards
         WHERE status = 'enabled'
           AND read_policy = 'public'
         ORDER BY sort_order ASC, id ASC
         LIMIT 1000"
    );
    foreach ($stmt->fetchAll() as $board) {
        $entries[] = [
            'loc' => '/community',
            'lastmod' => substr((string) $board['updated_at'], 0, 10),
        ];
    }

    $stmt = $pdo->query(
        "SELECT p.id, p.updated_at
         FROM toy_community_posts p
         INNER JOIN toy_community_boards b ON b.id = p.board_id
         WHERE p.status = 'published'
           AND b.status = 'enabled'
           AND b.read_policy = 'public'
         ORDER BY p.updated_at DESC
         LIMIT 1000"
    );
    foreach ($stmt->fetchAll() as $post) {
        $entries[] = [
            'loc' => '/community/post?id=' . (int) $post['id'],
            'lastmod' => substr((string) $post['updated_at'], 0, 10),
        ];
    }

    return $entries;
};
