<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return [
    'targets' => [
        [
            'target_module' => 'community',
            'target_type' => 'post',
            'label' => '커뮤니티 게시글',
            'allowed_variants' => ['card', 'button', 'compact'],
            'default_variant' => 'card',
            'search' => static function (PDO $pdo, array $context): array {
                $keyword = trim(preg_replace('/\s+/', ' ', (string) ($context['keyword'] ?? '')) ?? '');
                $keyword = function_exists('mb_substr') ? mb_substr($keyword, 0, 120) : substr($keyword, 0, 120);
                $limit = max(1, min(20, (int) ($context['limit'] ?? 10)));
                $mode = (string) ($context['context'] ?? 'public');
                $where = $keyword === '' ? '1 = 1' : "(p.id = :id OR p.title LIKE :keyword_post_title ESCAPE '\\\\' OR b.title LIKE :keyword_board_title ESCAPE '\\\\' OR b.board_key LIKE :keyword_board_key ESCAPE '\\\\')";
                $params = [];
                if ($keyword !== '') {
                    $keywordLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
                    $params = [
                        'id' => preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1 ? (int) $keyword : 0,
                        'keyword_post_title' => $keywordLike,
                        'keyword_board_title' => $keywordLike,
                        'keyword_board_key' => $keywordLike,
                    ];
                }

                $visibilitySql = $mode === 'admin'
                    ? 'p.status <> \'deleted\''
                    : 'p.status = \'published\' AND b.status = \'enabled\' AND b.read_policy = \'public\'';
                $stmt = $pdo->prepare(
                    'SELECT p.id, p.title, p.body_text, p.status, p.updated_at,
                            b.board_key, b.title AS board_title, b.status AS board_status, b.read_policy
                     FROM sr_community_posts p
                     INNER JOIN sr_community_boards b ON b.id = p.board_id
                     WHERE ' . $visibilitySql . '
                       AND ' . $where . '
                     ORDER BY p.created_at DESC, p.id DESC
                     LIMIT ' . $limit
                );
                $stmt->execute($params);

                return array_map(static function (array $row): array {
                    $postId = (string) (int) ($row['id'] ?? 0);
                    $summary = trim(strip_tags((string) ($row['body_text'] ?? '')));
                    $summary = preg_replace('/\s+/', ' ', $summary) ?? '';
                    $summary = function_exists('mb_substr') ? mb_substr($summary, 0, 120) : substr($summary, 0, 120);
                    $status = (string) ($row['status'] ?? '') === 'published' && (string) ($row['board_status'] ?? '') === 'enabled' && (string) ($row['read_policy'] ?? 'public') === 'public'
                        ? 'active'
                        : ((string) ($row['status'] ?? '') === 'deleted' ? 'deleted' : 'private');

                    return [
                        'target_id' => $postId,
                        'label_snapshot' => (string) ($row['title'] ?? ''),
                        'summary' => $summary,
                        'public_url' => '/community/post?id=' . rawurlencode($postId),
                        'admin_url' => '/admin/community/posts?q=' . rawurlencode($postId),
                        'status' => $status,
                        'meta' => '게시글 #' . $postId . ' / 게시판: ' . (string) ($row['board_title'] ?? '') . ' (' . (string) ($row['board_key'] ?? '') . ')',
                    ];
                }, $stmt->fetchAll());
            },
            'resolve' => static function (PDO $pdo, array $context): ?array {
                $postId = (int) ($context['target_id'] ?? 0);
                if ($postId < 1) {
                    return null;
                }

                $stmt = $pdo->prepare(
                    'SELECT p.id, p.title, p.body_text, p.status, b.status AS board_status, b.read_policy
                     FROM sr_community_posts p
                     INNER JOIN sr_community_boards b ON b.id = p.board_id
                     WHERE p.id = :id
                     LIMIT 1'
                );
                $stmt->execute(['id' => $postId]);
                $row = $stmt->fetch();
                if (!is_array($row)) {
                    return [
                        'label_snapshot' => '게시글 #' . (string) $postId,
                        'status' => 'broken',
                    ];
                }

                $postStatus = (string) ($row['status'] ?? '');
                if ($postStatus === 'deleted') {
                    $status = 'deleted';
                } elseif ($postStatus === 'published' && (string) ($row['board_status'] ?? '') === 'enabled' && (string) ($row['read_policy'] ?? 'public') === 'public') {
                    $status = 'active';
                } else {
                    $status = 'private';
                }
                $summary = trim(strip_tags((string) ($row['body_text'] ?? '')));
                $summary = preg_replace('/\s+/', ' ', $summary) ?? '';

                return [
                    'label_snapshot' => (string) ($row['title'] ?? ''),
                    'summary' => $summary,
                    'public_url' => '/community/post?id=' . rawurlencode((string) $postId),
                    'admin_url' => '/admin/community/posts?q=' . rawurlencode((string) $postId),
                    'status' => $status,
                ];
            },
        ],
    ],
];
