<?php

declare(strict_types=1);

return static function (PDO $pdo, ?array $site): array {
    require_once __DIR__ . '/helpers.php';

    $stmt = $pdo->query(
        "SELECT slug, updated_at
         FROM sr_pages
         WHERE status = 'published'
         ORDER BY updated_at DESC, id DESC
         LIMIT 1000"
    );

    $entries = [];
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
