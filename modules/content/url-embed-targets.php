<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return [
    'targets' => [
        [
            'target_module' => 'content',
            'target_type' => 'content',
            'label' => '콘텐츠',
            'allowed_variants' => ['summary'],
            'default_variant' => 'summary',
            'fragment_cache_public' => true,
            'resolve_url' => static function (PDO $pdo, array $context): ?array {
                $path = (string) parse_url((string) ($context['url'] ?? ''), PHP_URL_PATH);
                if (!str_starts_with($path, '/content/')) {
                    return null;
                }
                $slug = rawurldecode(substr($path, strlen('/content/')));
                if ($slug === '' || str_contains($slug, '/')) {
                    return null;
                }
                $stmt = $pdo->prepare('SELECT id, slug, title, summary, status, cover_image_url, asset_access_enabled, asset_access_amount, updated_at FROM sr_content_items WHERE slug = :slug LIMIT 1');
                $stmt->execute(['slug' => $slug]);
                $row = $stmt->fetch();
                if (!is_array($row)) {
                    return null;
                }
                $public = (string) ($row['status'] ?? '') === 'published'
                    && (!function_exists('sr_content_asset_access_required') || !sr_content_asset_access_required($row));
                return [
                    'target_id' => (string) (int) ($row['id'] ?? 0),
                    'canonical_url' => sr_content_path((string) ($row['slug'] ?? '')),
                    'label_snapshot' => (string) ($row['title'] ?? ''),
                    'summary_snapshot' => (string) ($row['summary'] ?? ''),
                    'image_snapshot' => (string) ($row['cover_image_url'] ?? ''),
                    'image_snapshot_policy' => (string) ($row['cover_image_url'] ?? '') !== '' ? 'public_url_ok' : 'none',
                    'target_state' => $public ? 'public' : 'private',
                    'cache_status' => $public ? 'fresh' : 'broken',
                    'target_cache_version' => (string) ($row['updated_at'] ?? ''),
                ];
            },
            'render_embed' => static function (PDO $pdo, array $embed, array $context): array {
                $stmt = $pdo->prepare('SELECT slug, title, summary, status, cover_image_url, asset_access_enabled, asset_access_amount, updated_at FROM sr_content_items WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => (int) ($embed['target_id'] ?? 0)]);
                $row = $stmt->fetch();
                if (!is_array($row)) {
                    return ['html' => '', 'cache_status' => 'deleted'];
                }
                if ((string) ($row['status'] ?? '') !== 'published' || (function_exists('sr_content_asset_access_required') && sr_content_asset_access_required($row))) {
                    return ['html' => '', 'cache_status' => 'broken', 'target_cache_version' => (string) ($row['updated_at'] ?? '')];
                }
                $canonicalUrl = sr_content_path((string) ($row['slug'] ?? ''));
                $label = (string) ($row['title'] ?? '');
                $summary = sr_url_embed_clean_summary((string) ($row['summary'] ?? ''));
                $image = sr_url_embed_safe_url((string) ($row['cover_image_url'] ?? ''));
                $html = '<aside class="content-embed-summary" data-content-embed="summary">';
                if ($image !== '') {
                    $html .= '<a class="content-embed-summary-image" href="' . sr_e($canonicalUrl) . '"><img src="' . sr_e($image) . '" alt="" loading="lazy" decoding="async" /></a>';
                }
                $html .= '<strong><a href="' . sr_e($canonicalUrl) . '">' . sr_e($label) . '</a></strong>';
                if ($summary !== '') {
                    $html .= '<p>' . sr_e($summary) . '</p>';
                }
                return ['html' => $html . '</aside>', 'cache_status' => 'fresh', 'target_cache_version' => (string) ($row['updated_at'] ?? '')];
            },
        ],
    ],
];
