<?php

declare(strict_types=1);

return static function (PDO $pdo, ?array $site): array {
    require_once __DIR__ . '/helpers.php';

    $entries = [];
    foreach (sr_page_enabled_groups($pdo) as $group) {
        $groupKey = (string) ($group['group_key'] ?? '');
        if (!sr_page_group_key_is_valid($groupKey)) {
            continue;
        }

        $entries[] = [
            'loc' => sr_page_group_path($groupKey),
            'lastmod' => substr((string) ($group['updated_at'] ?? ''), 0, 10),
            'changefreq' => 'weekly',
            'priority' => '0.5',
        ];
    }

    $stmt = $pdo->query(
        "SELECT slug, updated_at
         FROM sr_pages
         WHERE status = 'published'
         ORDER BY updated_at DESC, id DESC
         LIMIT 1000"
    );

    foreach ($stmt->fetchAll() as $page) {
        $slug = (string) ($page['slug'] ?? '');
        if (!sr_page_slug_is_valid($slug)) {
            continue;
        }

        $entries[] = [
            'loc' => sr_page_path($slug),
            'lastmod' => substr((string) $page['updated_at'], 0, 10),
            'changefreq' => 'monthly',
            'priority' => '0.6',
        ];
    }

    return $entries;
};
