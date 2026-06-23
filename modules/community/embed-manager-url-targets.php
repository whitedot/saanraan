<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return [
    'targets' => [
        [
            'target_module' => 'community',
            'target_type' => 'post',
            'label' => '커뮤니티 게시글',
            'allowed_variants' => ['summary'],
            'default_variant' => 'summary',
            'resolve_url' => static function (PDO $pdo, array $context): ?array {
                $url = (string) ($context['url'] ?? '');
                $path = (string) parse_url($url, PHP_URL_PATH);
                if ($path !== '/community/post') {
                    return null;
                }
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $postId = (int) ($query['id'] ?? 0);
                if ($postId < 1) {
                    return null;
                }
                $stmt = $pdo->prepare(
                    'SELECT p.id, p.title, p.body_text, p.status, p.updated_at, p.og_image_attachment_id,
                            b.status AS board_status, b.read_policy
                     FROM sr_community_posts p
                     INNER JOIN sr_community_boards b ON b.id = p.board_id
                     WHERE p.id = :id
                     LIMIT 1'
                );
                $stmt->execute(['id' => $postId]);
                $row = $stmt->fetch();
                if (!is_array($row)) {
                    return [
                        'target_id' => (string) $postId,
                        'canonical_url' => '/community/post?id=' . rawurlencode((string) $postId),
                        'label_snapshot' => '게시글 #' . (string) $postId,
                        'target_state' => 'deleted',
                        'cache_status' => 'deleted',
                    ];
                }
                $public = (string) ($row['status'] ?? '') === 'published'
                    && (string) ($row['board_status'] ?? '') === 'enabled'
                    && (string) ($row['read_policy'] ?? 'public') === 'public';
                $summary = sr_embed_manager_clean_summary((string) ($row['body_text'] ?? ''));
                $image = function_exists('sr_community_post_og_image_url') ? sr_community_post_og_image_url($pdo, $row) : '';
                return [
                    'target_id' => (string) $postId,
                    'canonical_url' => '/community/post?id=' . rawurlencode((string) $postId),
                    'label_snapshot' => (string) ($row['title'] ?? ''),
                    'summary_snapshot' => $summary,
                    'image_snapshot' => $image,
                    'image_snapshot_policy' => $image !== '' ? 'public_url_ok' : 'none',
                    'target_state' => $public ? 'public' : 'private',
                    'cache_status' => $public ? 'fresh' : 'broken',
                    'target_cache_version' => (string) ($row['updated_at'] ?? ''),
                ];
            },
            'render_embed' => static function (PDO $pdo, array $embed, array $context): array {
                $stmt = $pdo->prepare(
                    'SELECT p.id, p.title, p.body_text, p.status, p.updated_at, p.og_image_attachment_id,
                            b.status AS board_status, b.read_policy
                     FROM sr_community_posts p
                     INNER JOIN sr_community_boards b ON b.id = p.board_id
                     WHERE p.id = :id
                     LIMIT 1'
                );
                $stmt->execute(['id' => (int) ($embed['target_id'] ?? 0)]);
                $row = $stmt->fetch();
                if (!is_array($row)) {
                    return ['html' => '', 'cache_status' => 'deleted'];
                }
                $public = is_array($row)
                    && (string) ($row['status'] ?? '') === 'published'
                    && (string) ($row['board_status'] ?? '') === 'enabled'
                    && (string) ($row['read_policy'] ?? 'public') === 'public';
                if (!$public) {
                    return ['html' => '', 'cache_status' => 'broken', 'target_cache_version' => (string) ($row['updated_at'] ?? '')];
                }
                $canonicalUrl = '/community/post?id=' . rawurlencode((string) (int) ($row['id'] ?? 0));
                $label = (string) ($row['title'] ?? '');
                $summary = sr_embed_manager_clean_summary((string) ($row['body_text'] ?? ''));
                $image = function_exists('sr_community_post_og_image_url') ? sr_community_post_og_image_url($pdo, $row) : '';
                $html = '<aside class="community-embed-summary" data-community-embed="summary">';
                if ($image !== '') {
                    $html .= '<a class="community-embed-summary-image" href="' . sr_e($canonicalUrl) . '"><img src="' . sr_e($image) . '" alt="" loading="lazy" decoding="async" /></a>';
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
