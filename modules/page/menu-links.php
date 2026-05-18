<?php

declare(strict_types=1);

return static function (PDO $pdo): array {
    require_once __DIR__ . '/helpers.php';

    $stmt = $pdo->query(
        "SELECT slug, title
         FROM sr_pages
         WHERE status = 'published'
         ORDER BY title ASC, id ASC
         LIMIT 1000"
    );

    $links = [];
    foreach ($stmt->fetchAll() as $page) {
        $slug = (string) ($page['slug'] ?? '');
        if (!sr_page_slug_is_valid($slug)) {
            continue;
        }

        $links[] = [
            'label' => (string) $page['title'],
            'url' => sr_page_path($slug),
        ];
    }

    return $links;
};
