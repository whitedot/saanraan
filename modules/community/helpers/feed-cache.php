<?php

declare(strict_types=1);

function sr_community_feed_cache_sort_key(string $value): string
{
    return in_array($value, ['latest', 'views', 'activity', 'reactions'], true) ? $value : 'latest';
}

function sr_community_feed_cache_count(int $value, int $default = 20): int
{
    if ($value < 1) {
        return max(1, min(100, $default));
    }

    return max(1, min(100, $value));
}

function sr_community_feed_cache_key(string $value): string
{
    $value = strtolower(trim($value));

    return preg_match('/\A[a-z0-9][a-z0-9_.:-]{0,119}\z/', $value) === 1 ? $value : 'community.feed';
}

function sr_community_feed_cache_locale_key(string $value): string
{
    $value = strtolower(trim($value));

    return preg_match('/\A[a-z]{2}(?:[-_][a-z0-9]{2,8})?\z/', $value) === 1 ? str_replace('_', '-', $value) : 'ko';
}

function sr_community_feed_cache_policy_version(string $value): string
{
    $value = strtolower(trim($value));

    return preg_match('/\A[a-z0-9][a-z0-9_.:-]{0,79}\z/', $value) === 1 ? $value : 'v1';
}

function sr_community_feed_cache_public_baseline_board(array $board): bool
{
    if ((string) ($board['status'] ?? '') !== 'enabled') {
        return false;
    }

    return (string) ($board['effective_read_policy'] ?? $board['read_policy'] ?? '') === 'public';
}

function sr_community_feed_cache_public_baseline_boards(array $boards): array
{
    $baseline = [];
    foreach ($boards as $board) {
        if (!is_array($board) || !sr_community_feed_cache_public_baseline_board($board)) {
            continue;
        }

        $boardId = (int) ($board['id'] ?? 0);
        if ($boardId < 1 || isset($baseline[$boardId])) {
            continue;
        }

        $baseline[$boardId] = $board;
    }

    ksort($baseline, SORT_NUMERIC);

    return array_values($baseline);
}

function sr_community_feed_cache_public_baseline_board_ids(array $boards): array
{
    return array_map(
        static fn (array $board): int => (int) ($board['id'] ?? 0),
        sr_community_feed_cache_public_baseline_boards($boards)
    );
}

function sr_community_feed_cache_context(array $options): array
{
    $boardIds = [];
    foreach (($options['board_ids'] ?? []) as $boardId) {
        $id = (int) $boardId;
        if ($id > 0) {
            $boardIds[$id] = $id;
        }
    }
    ksort($boardIds, SORT_NUMERIC);

    $displayCount = sr_community_feed_cache_count((int) ($options['display_count'] ?? 0), 20);
    $fetchCount = sr_community_feed_cache_count((int) ($options['fetch_count'] ?? 0), max(30, $displayCount));
    if ($fetchCount < $displayCount) {
        $fetchCount = $displayCount;
    }

    return [
        'schema_version' => 'community_feed_cache_context_v1',
        'feed_key' => sr_community_feed_cache_key((string) ($options['feed_key'] ?? 'community.feed')),
        'baseline' => 'everyone_discoverable_public_boards',
        'board_ids' => array_values($boardIds),
        'sort' => sr_community_feed_cache_sort_key((string) ($options['sort'] ?? 'latest')),
        'fetch_count' => $fetchCount,
        'display_count' => $displayCount,
        'locale' => sr_community_feed_cache_locale_key((string) ($options['locale'] ?? 'ko')),
        'policy_version' => sr_community_feed_cache_policy_version((string) ($options['policy_version'] ?? 'v1')),
    ];
}

function sr_community_feed_cache_context_hash(array $context): string
{
    $normalized = sr_community_feed_cache_context($context);
    $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        $json = '{}';
    }

    return hash('sha256', $json);
}

function sr_community_feed_cache_post_feed_query(PDO $pdo, array $boardIds, int $limit, string $sort, string $paramPrefix = 'feed_board_id_'): array
{
    $ids = [];
    foreach ($boardIds as $boardId) {
        $id = (int) $boardId;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    ksort($ids, SORT_NUMERIC);
    if ($ids === []) {
        return ['', []];
    }

    $placeholders = [];
    $params = [];
    $index = 0;
    foreach (array_values($ids) as $boardId) {
        $paramKey = $paramPrefix . (string) $index;
        $placeholders[] = ':' . $paramKey;
        $params[$paramKey] = $boardId;
        $index++;
    }

    $limit = sr_community_feed_cache_count($limit, 20);
    $orderSql = sr_community_feed_cache_sort_key($sort) === 'views'
        ? 'p.view_count DESC, p.id DESC'
        : 'p.id DESC';
    $innerOrderSql = sr_community_feed_cache_sort_key($sort) === 'views'
        ? 'p0.view_count DESC, p0.id DESC'
        : 'p0.id DESC';
    $authorSnapshotSelectSql = sr_community_author_public_name_snapshot_select($pdo, 'sr_community_posts', 'p');
    $secretPostSelectSql = sr_community_post_secret_column_exists($pdo) ? 'p.is_secret,' : '0 AS is_secret,';
    $params['limit_value'] = $limit;

    return [
        'SELECT p.id, p.board_id, NULL AS category_id, NULL AS category_key, NULL AS category_title, NULL AS category_status,
                p.author_account_id, ' . $authorSnapshotSelectSql . sr_community_guest_author_select($pdo, 'sr_community_posts', 'p') . sr_community_post_extra_values_select($pdo, 'p') . ', author.status AS author_account_status, p.title, p.body_text, p.body_format, ' . $secretPostSelectSql . ' p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
                (SELECT COUNT(*) FROM sr_community_comments c WHERE c.post_id = p.id AND c.status = \'published\') AS published_comment_count,
                (SELECT COUNT(*) FROM sr_community_attachments att WHERE att.post_id = p.id AND att.status = \'active\') AS active_attachment_count,
                list_image.id AS list_image_attachment_id,
                list_image.storage_driver AS list_image_storage_driver,
                list_image.storage_key AS list_image_storage_key,
                list_image.mime_type AS list_image_mime_type,
                list_image.size_bytes AS list_image_size_bytes,
                list_image.checksum_sha256 AS list_image_checksum_sha256,
                list_image.width AS list_image_width,
                list_image.height AS list_image_height
         FROM (
             SELECT p0.id
             FROM sr_community_posts p0
             WHERE p0.status = \'published\'
               AND p0.board_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY ' . $innerOrderSql . '
             LIMIT :limit_value
         ) picked
         INNER JOIN sr_community_posts p ON p.id = picked.id
         LEFT JOIN sr_member_accounts author ON author.id = p.author_account_id
         LEFT JOIN sr_community_attachments list_image ON list_image.id = (
             SELECT MIN(att_img.id)
             FROM sr_community_attachments att_img
             WHERE att_img.post_id = p.id
               AND att_img.status = \'active\'
               AND att_img.mime_type IN (\'image/jpeg\', \'image/png\', \'image/gif\', \'image/webp\')
         )
         ORDER BY ' . $orderSql,
        $params,
    ];
}

function sr_community_feed_cache_thumbnail_source_marker(array $post): array
{
    $attachmentId = (int) ($post['list_image_attachment_id'] ?? $post['thumbnail_attachment_id'] ?? 0);
    if ($attachmentId < 1) {
        return [];
    }

    return [
        'attachment_id' => $attachmentId,
        'checksum_sha256' => (string) ($post['list_image_checksum_sha256'] ?? $post['thumbnail_checksum_sha256'] ?? ''),
        'width' => max(0, (int) ($post['list_image_width'] ?? $post['thumbnail_width'] ?? 0)),
        'height' => max(0, (int) ($post['list_image_height'] ?? $post['thumbnail_height'] ?? 0)),
    ];
}

function sr_community_feed_cache_card_snapshot(array $post): array
{
    $postId = (int) ($post['post_id'] ?? $post['id'] ?? 0);
    $boardId = (int) ($post['board_id'] ?? 0);

    return [
        'snapshot_schema_version' => 'community_feed_card_snapshot_v1',
        'post_id' => max(0, $postId),
        'board_id' => max(0, $boardId),
        'title' => sr_clean_single_line((string) ($post['title'] ?? ''), 160),
        'author_account_id' => max(0, (int) ($post['author_account_id'] ?? 0)),
        'view_count' => max(0, (int) ($post['view_count'] ?? 0)),
        'thumbnail_source' => sr_community_feed_cache_thumbnail_source_marker($post),
        'is_secret' => !empty($post['is_secret']) ? 1 : 0,
        'created_at' => sr_clean_single_line((string) ($post['created_at'] ?? ''), 40),
        'updated_at' => sr_clean_single_line((string) ($post['updated_at'] ?? ''), 40),
        'source_updated_at' => sr_clean_single_line((string) ($post['source_updated_at'] ?? $post['updated_at'] ?? ''), 40),
    ];
}

function sr_community_feed_cache_card_snapshot_json(array $post): string
{
    $json = json_encode(sr_community_feed_cache_card_snapshot($post), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return is_string($json) ? $json : '{}';
}

function sr_community_feed_cache_snapshot_forbidden_keys(): array
{
    return [
        'author_label_snapshot',
        'author_public_name_snapshot',
        'published_comment_count',
        'body_text',
        'body_html',
        'html',
        'csrf_token',
        'permission',
        'permissions',
        'can_read',
        'paid_access',
        'paid_access_state',
        'entitlement',
        'entitlements',
        'thumbnail_url',
    ];
}

function sr_community_feed_cache_snapshot_contains_forbidden_key(array $value): bool
{
    $forbidden = array_fill_keys(sr_community_feed_cache_snapshot_forbidden_keys(), true);
    foreach ($value as $key => $item) {
        if (isset($forbidden[(string) $key])) {
            return true;
        }
        if (is_array($item) && sr_community_feed_cache_snapshot_contains_forbidden_key($item)) {
            return true;
        }
    }

    return false;
}

function sr_community_feed_cache_persistent_store_status(PDO $pdo): array
{
    $status = [
        'mode' => 'contract_only',
        'table_exists' => false,
        'file_cache_exists' => false,
        'row_count' => 0,
    ];

    try {
        $stmt = $pdo->query('SELECT COUNT(*) FROM sr_community_feed_cache');
        $status['table_exists'] = true;
        $status['row_count'] = (int) $stmt->fetchColumn();
    } catch (Throwable) {
        $status['table_exists'] = false;
    }

    $root = defined('SR_ROOT') ? (string) SR_ROOT : dirname(__DIR__, 3);
    $status['file_cache_exists'] = is_dir(rtrim($root, '/\\') . '/storage/cache/community-feed');
    if ($status['table_exists'] || $status['file_cache_exists']) {
        $status['mode'] = 'persistent_detected';
    }

    return $status;
}

function sr_community_feed_cache_admin_board_rows(PDO $pdo, array $boards, array $settings): array
{
    $rows = [];
    foreach ($boards as $board) {
        if (!is_array($board)) {
            continue;
        }

        $boardId = (int) ($board['id'] ?? 0);
        if ($boardId < 1) {
            continue;
        }

        $paidReadConfig = function_exists('sr_community_asset_event_config')
            ? sr_community_asset_event_config($pdo, $board, $settings, 'paid_read', 'once')
            : ['enabled' => false];
        $readPolicy = (string) ($board['effective_read_policy'] ?? $board['read_policy'] ?? '');
        $baseline = sr_community_feed_cache_public_baseline_board($board);
        $homeExcerptAllowed = !function_exists('sr_community_asset_event_required') || !sr_community_asset_event_required($paidReadConfig);

        $rows[] = [
            'id' => $boardId,
            'board_key' => (string) ($board['board_key'] ?? ''),
            'title' => (string) ($board['title'] ?? ''),
            'status' => (string) ($board['status'] ?? ''),
            'read_policy' => $readPolicy,
            'public_baseline' => $baseline,
            'home_excerpt_allowed' => $homeExcerptAllowed,
            'paid_read_required' => !$homeExcerptAllowed,
        ];
    }

    return $rows;
}

function sr_community_feed_cache_admin_context_rows(array $boardIds): array
{
    $contexts = [];
    foreach ([
        ['feed_key' => 'community.home.latest', 'sort' => 'latest', 'display_count' => 10, 'fetch_count' => 10],
        ['feed_key' => 'community.home.popular', 'sort' => 'views', 'display_count' => 5, 'fetch_count' => 5],
    ] as $context) {
        $context['board_ids'] = $boardIds;
        $context = sr_community_feed_cache_context($context);
        $contexts[] = [
            'feed_key' => (string) $context['feed_key'],
            'sort' => (string) $context['sort'],
            'display_count' => (int) $context['display_count'],
            'fetch_count' => (int) $context['fetch_count'],
            'locale' => (string) $context['locale'],
            'policy_version' => (string) $context['policy_version'],
            'context_hash' => sr_community_feed_cache_context_hash($context),
        ];
    }

    return $contexts;
}
