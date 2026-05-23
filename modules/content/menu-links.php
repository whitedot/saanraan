<?php

declare(strict_types=1);

return static function (PDO $pdo): array {
    require_once __DIR__ . '/helpers.php';

    $links = [];

    foreach (sr_content_enabled_groups($pdo) as $group) {
        $groupKey = (string) ($group['group_key'] ?? '');
        if (!sr_content_group_key_is_valid($groupKey)) {
            continue;
        }

        $links[] = [
            'asset_type' => 'content_group',
            'asset_type_label' => sr_t('content::ui.content.5875c5b3'),
            'label' => (string) ($group['title'] ?? $groupKey),
            'url' => sr_content_group_path($groupKey),
        ];
    }

    $stmt = $pdo->query(
        "SELECT slug, title
         FROM sr_content_items
         WHERE status = 'published'
         ORDER BY title ASC, id ASC
         LIMIT 1000"
    );

    foreach ($stmt->fetchAll() as $page) {
        $slug = (string) ($page['slug'] ?? '');
        if (!sr_content_slug_is_valid($slug)) {
            continue;
        }

        $links[] = [
            'asset_type' => 'content',
            'asset_type_label' => sr_t('content::ui.content.6c84a1b3'),
            'label' => (string) $page['title'],
            'url' => sr_content_path($slug),
        ];
    }

    return $links;
};
