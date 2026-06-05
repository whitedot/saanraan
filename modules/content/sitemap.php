<?php

declare(strict_types=1);

return static function (PDO $pdo, ?array $site): array {
    require_once __DIR__ . '/helpers.php';

    $entries = [
        [
            'loc' => '/content',
            'lastmod' => substr(sr_now(), 0, 10),
            'changefreq' => 'daily',
            'priority' => '0.7',
        ],
    ];
    foreach (sr_content_enabled_groups($pdo) as $group) {
        $groupKey = (string) ($group['group_key'] ?? '');
        if (!sr_content_group_key_is_valid($groupKey)) {
            continue;
        }

        $entries[] = [
            'loc' => sr_content_group_path($groupKey),
            'lastmod' => substr((string) ($group['updated_at'] ?? ''), 0, 10),
            'changefreq' => 'weekly',
            'priority' => '0.5',
        ];
    }

    $stmt = $pdo->query(
        "SELECT slug, updated_at
         FROM sr_content_items
         WHERE status = 'published'
         ORDER BY updated_at DESC, id DESC
         LIMIT 1000"
    );

    foreach ($stmt->fetchAll() as $page) {
        $slug = (string) ($page['slug'] ?? '');
        if (!sr_content_slug_is_valid($slug)) {
            continue;
        }

        $entries[] = [
            'loc' => sr_content_path($slug),
            'lastmod' => substr((string) $page['updated_at'], 0, 10),
            'changefreq' => 'monthly',
            'priority' => '0.6',
        ];
    }

    return $entries;
};
