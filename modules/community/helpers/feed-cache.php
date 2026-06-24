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
