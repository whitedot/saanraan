<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return [
    'targets' => [
        [
            'target_module' => 'content',
            'target_type' => 'content',
            'label' => '콘텐츠',
            'allowed_variants' => ['card', 'button', 'compact'],
            'default_variant' => 'card',
            'search' => static function (PDO $pdo, array $context): array {
                $keyword = sr_content_clean_text((string) ($context['keyword'] ?? ''), 120);
                $limit = max(1, min(20, (int) ($context['limit'] ?? 10)));
                $mode = (string) ($context['context'] ?? 'public');
                $where = $keyword === '' ? '1 = 1' : "(id = :id OR title LIKE :keyword_title ESCAPE '\\\\' OR slug LIKE :keyword_slug ESCAPE '\\\\')";
                $params = [];
                if ($keyword !== '') {
                    $keywordLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
                    $params = [
                        'id' => preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1 ? (int) $keyword : 0,
                        'keyword_title' => $keywordLike,
                        'keyword_slug' => $keywordLike,
                    ];
                }

                sr_content_publish_due_scheduled($pdo);
                $statusSql = $mode === 'admin' ? 'status <> \'deleted\'' : 'status = \'published\'';
                $stmt = $pdo->prepare(
                    'SELECT id, title, slug, summary, status, updated_at
                     FROM sr_content_items
                     WHERE ' . $statusSql . '
                       AND ' . $where . '
                     ORDER BY published_at DESC, updated_at DESC, id DESC
                     LIMIT ' . $limit
                );
                $stmt->execute($params);

                return array_map(static function (array $row): array {
                    $contentId = (string) (int) ($row['id'] ?? 0);
                    return [
                        'target_id' => $contentId,
                        'label_snapshot' => (string) ($row['title'] ?? ''),
                        'summary' => (string) ($row['summary'] ?? ''),
                        'public_url' => sr_content_path((string) ($row['slug'] ?? '')),
                        'admin_url' => '/admin/content?mode=edit&id=' . rawurlencode($contentId),
                        'status' => (string) ($row['status'] ?? '') === 'published' ? 'active' : 'private',
                        'meta' => '콘텐츠 #' . $contentId . ' / slug: ' . (string) ($row['slug'] ?? ''),
                    ];
                }, $stmt->fetchAll());
            },
            'resolve' => static function (PDO $pdo, array $context): ?array {
                $contentId = (int) ($context['target_id'] ?? 0);
                if ($contentId < 1) {
                    return null;
                }

                $stmt = $pdo->prepare('SELECT id, slug, title, summary, status, cover_image_url FROM sr_content_items WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $contentId]);
                $row = $stmt->fetch();
                if (!is_array($row)) {
                    return [
                        'label_snapshot' => '콘텐츠 #' . (string) $contentId,
                        'status' => 'broken',
                    ];
                }

                return [
                    'label_snapshot' => (string) ($row['title'] ?? ''),
                    'summary' => (string) ($row['summary'] ?? ''),
                    'image_snapshot' => (string) ($row['cover_image_url'] ?? ''),
                    'public_url' => sr_content_path((string) ($row['slug'] ?? '')),
                    'admin_url' => '/admin/content?mode=edit&id=' . rawurlencode((string) $contentId),
                    'status' => (string) ($row['status'] ?? '') === 'published' ? 'active' : 'private',
                ];
            },
        ],
    ],
];
