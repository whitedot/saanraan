<?php

declare(strict_types=1);

return static function (PDO $pdo): array {
    require_once __DIR__ . '/helpers.php';

    $links = [];

    foreach (sr_page_enabled_groups($pdo) as $group) {
        $groupKey = (string) ($group['group_key'] ?? '');
        if (!sr_page_group_key_is_valid($groupKey)) {
            continue;
        }

        $links[] = [
            'asset_type' => 'page_group',
            'asset_type_label' => '페이지 그룹',
            'label' => (string) ($group['title'] ?? $groupKey),
            'url' => sr_page_group_path($groupKey),
        ];
    }

    $stmt = $pdo->query(
        "SELECT slug, title
         FROM sr_pages
         WHERE status = 'published'
         ORDER BY title ASC, id ASC
         LIMIT 1000"
    );

    foreach ($stmt->fetchAll() as $page) {
        $slug = (string) ($page['slug'] ?? '');
        if (!sr_page_slug_is_valid($slug)) {
            continue;
        }

        $links[] = [
            'asset_type' => 'page',
            'asset_type_label' => '페이지',
            'label' => (string) $page['title'],
            'url' => sr_page_path($slug),
        ];
    }

    return $links;
};
