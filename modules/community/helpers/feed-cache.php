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

    if ((string) ($board['effective_summary_feed_enabled'] ?? $board['summary_feed_enabled'] ?? '1') !== '1') {
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

function sr_community_board_settings_table_exists(PDO $pdo): bool
{
    static $existsByConnection = [];
    $cacheKey = (string) spl_object_id($pdo);
    if (array_key_exists($cacheKey, $existsByConnection)) {
        return $existsByConnection[$cacheKey];
    }

    try {
        $pdo->query('SELECT 1 FROM sr_community_board_settings LIMIT 1');
        $existsByConnection[$cacheKey] = true;
        return $existsByConnection[$cacheKey];
    } catch (Throwable) {
        $existsByConnection[$cacheKey] = false;
        return $existsByConnection[$cacheKey];
    }
}

function sr_community_summary_feed_enabled_sql_condition(PDO $pdo, string $boardIdExpression, string $indent = '               '): string
{
    if (!sr_community_board_settings_table_exists($pdo)) {
        return '';
    }

    $disabledValues = "('0', 'false', 'no', 'off')";
    $sql = $indent . "AND NOT EXISTS (
" . $indent . "    SELECT 1
" . $indent . "    FROM sr_community_board_settings home_setting
" . $indent . "    WHERE home_setting.board_id = " . $boardIdExpression . "
" . $indent . "      AND home_setting.setting_key = 'summary_feed_enabled'
" . $indent . "      AND home_setting.setting_value IN " . $disabledValues . "
" . $indent . ")
" . $indent . "AND NOT EXISTS (
" . $indent . "    SELECT 1
" . $indent . "    FROM sr_community_board_settings legacy_home_setting
" . $indent . "    WHERE legacy_home_setting.board_id = " . $boardIdExpression . "
" . $indent . "      AND legacy_home_setting.setting_key = 'home_feed_enabled'
" . $indent . "      AND legacy_home_setting.setting_value IN " . $disabledValues . "
" . $indent . "      AND NOT EXISTS (
" . $indent . "          SELECT 1
" . $indent . "          FROM sr_community_board_settings summary_setting
" . $indent . "          WHERE summary_setting.board_id = legacy_home_setting.board_id
" . $indent . "            AND summary_setting.setting_key = 'summary_feed_enabled'
" . $indent . "      )
" . $indent . ")\n";

    return $sql;
}

function sr_community_summary_post_candidate_sql_condition(PDO $pdo, string $postAlias, string $boardIdExpression, string $indent = '               '): string
{
    $postAlias = preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]*\z/', $postAlias) === 1 ? $postAlias : 'p';
    if (function_exists('sr_community_post_summary_feed_candidate_column_exists') && sr_community_post_summary_feed_candidate_column_exists($pdo)) {
        return $indent . 'AND ' . $postAlias . ".summary_feed_candidate = 1\n";
    }

    return sr_community_summary_feed_enabled_sql_condition($pdo, $boardIdExpression, $indent);
}

function sr_community_feed_cache_refresh_seconds(string $feedKey): int
{
    $feedKey = sr_community_feed_cache_key($feedKey);
    if ($feedKey === 'community.home.popular') {
        return 300;
    }

    return 0;
}

function sr_community_feed_cache_ttl_seconds(string $feedKey): int
{
    return sr_community_feed_cache_refresh_seconds($feedKey);
}

function sr_community_feed_cache_refresh_policy_label(string $feedKey): string
{
    $refreshSeconds = sr_community_feed_cache_refresh_seconds($feedKey);
    if ($refreshSeconds > 0) {
        return '조회수 기반 ' . (string) max(1, (int) ceil($refreshSeconds / 60)) . '분 재계산';
    }

    return '변경 시 갱신';
}

function sr_community_feed_cache_expires_at(string $generatedAt, string $feedKey): string
{
    $refreshSeconds = sr_community_feed_cache_refresh_seconds($feedKey);
    if ($refreshSeconds < 1) {
        return '9999-12-31 23:59:59';
    }

    $timestamp = strtotime($generatedAt);
    if ($timestamp === false) {
        $timestamp = time();
    }

    return date('Y-m-d H:i:s', $timestamp + $refreshSeconds);
}

function sr_community_feed_cache_context_for_home(array $boards, array $homeExcerptAllowedByBoardId, int $displayCount, string $sort): array
{
    $boardIds = [];
    foreach ($boards as $board) {
        if (!is_array($board) || !sr_community_feed_cache_public_baseline_board($board)) {
            return [];
        }

        $boardId = (int) ($board['id'] ?? 0);
        if ($boardId < 1 || empty($homeExcerptAllowedByBoardId[$boardId])) {
            return [];
        }

        $boardIds[$boardId] = $boardId;
    }
    if ($boardIds === []) {
        return [];
    }

    ksort($boardIds, SORT_NUMERIC);
    $sort = sr_community_feed_cache_sort_key($sort);

    return sr_community_feed_cache_context([
        'feed_key' => $sort === 'views' ? 'community.home.popular' : 'community.home.latest',
        'board_ids' => array_values($boardIds),
        'sort' => $sort,
        'display_count' => $displayCount,
        'fetch_count' => $displayCount,
        'locale' => 'ko',
        'policy_version' => 'summary-feed-candidate-v1',
    ]);
}

function sr_community_feed_cache_snapshot_json(array $snapshots): string
{
    $safeSnapshots = [];
    foreach ($snapshots as $snapshot) {
        if (!is_array($snapshot) || sr_community_feed_cache_snapshot_contains_forbidden_key($snapshot)) {
            continue;
        }
        $safeSnapshots[] = $snapshot;
    }

    $json = json_encode($safeSnapshots, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return is_string($json) ? $json : '[]';
}

function sr_community_feed_cache_snapshots_from_json(string $json, array $allowedSchemaVersions = ['community_feed_card_snapshot_v1']): array
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }

    $allowedSchemas = [];
    foreach ($allowedSchemaVersions as $schemaVersion) {
        $schemaVersion = (string) $schemaVersion;
        if ($schemaVersion !== '') {
            $allowedSchemas[$schemaVersion] = true;
        }
    }
    if ($allowedSchemas === []) {
        $allowedSchemas['community_feed_card_snapshot_v1'] = true;
    }

    $snapshots = [];
    foreach ($decoded as $snapshot) {
        if (!is_array($snapshot) || sr_community_feed_cache_snapshot_contains_forbidden_key($snapshot)) {
            continue;
        }
        if (!isset($allowedSchemas[(string) ($snapshot['snapshot_schema_version'] ?? '')])) {
            continue;
        }
        $postId = (int) ($snapshot['post_id'] ?? $snapshot['id'] ?? 0);
        $boardId = (int) ($snapshot['board_id'] ?? 0);
        if ($postId < 1 || $boardId < 1) {
            continue;
        }
        $snapshots[] = $snapshot;
    }

    return $snapshots;
}

function sr_community_feed_cache_file_root(): string
{
    $root = defined('SR_ROOT') ? (string) SR_ROOT : dirname(__DIR__, 3);

    return rtrim($root, '/\\') . '/storage/cache/community-feed';
}

function sr_community_feed_cache_file_path(string $contextHash): string
{
    $contextHash = preg_match('/\A[a-f0-9]{64}\z/', $contextHash) === 1 ? $contextHash : str_repeat('0', 64);

    return sr_community_feed_cache_file_root() . '/' . substr($contextHash, 0, 2) . '/' . $contextHash . '.json';
}

function sr_community_feed_cache_file_record(array $context, array $snapshots, string $now): array
{
    $context = sr_community_feed_cache_context($context);
    $contextHash = sr_community_feed_cache_context_hash($context);

    return [
        'schema_version' => 'community_feed_file_cache_v1',
        'context_hash' => $contextHash,
        'feed_key' => (string) $context['feed_key'],
        'sort_key' => (string) $context['sort'],
        'locale' => (string) $context['locale'],
        'policy_version' => (string) $context['policy_version'],
        'baseline' => (string) $context['baseline'],
        'board_ids' => array_values(array_map('intval', $context['board_ids'])),
        'display_count' => (int) $context['display_count'],
        'fetch_count' => (int) $context['fetch_count'],
        'snapshot_count' => count($snapshots),
        'snapshots' => array_values($snapshots),
        'cache_status' => 'fresh',
        'generated_at' => $now,
        'expires_at' => sr_community_feed_cache_expires_at($now, (string) $context['feed_key']),
        'stale_reason' => '',
        'updated_at' => $now,
    ];
}

function sr_community_feed_cache_file_records(int $limit = 1000): array
{
    $root = sr_community_feed_cache_file_root();
    if (!is_dir($root)) {
        return [];
    }

    $records = [];
    foreach (glob($root . '/*/*.json') ?: [] as $path) {
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }
        $json = file_get_contents($path);
        if (!is_string($json)) {
            continue;
        }
        $record = json_decode($json, true);
        if (!is_array($record) || (string) ($record['schema_version'] ?? '') !== 'community_feed_file_cache_v1') {
            continue;
        }
        $records[] = $record;
        if (count($records) >= $limit) {
            break;
        }
    }

    return $records;
}

function sr_community_feed_cache_file_is_active(array $record): bool
{
    if ((string) ($record['cache_status'] ?? '') !== 'fresh') {
        return false;
    }

    $feedKey = (string) ($record['feed_key'] ?? '');
    if (sr_community_feed_cache_refresh_seconds($feedKey) < 1) {
        return true;
    }

    $expiresAt = strtotime((string) ($record['expires_at'] ?? ''));

    return $expiresAt !== false && $expiresAt > time();
}

function sr_community_feed_cache_memory_record(string $contextHash): ?array
{
    $records = $GLOBALS['sr_community_feed_cache_memory_records'] ?? [];
    if (!is_array($records) || !isset($records[$contextHash]) || !is_array($records[$contextHash])) {
        return null;
    }

    return $records[$contextHash];
}

function sr_community_feed_cache_remember_record(string $contextHash, array $record): void
{
    $records = $GLOBALS['sr_community_feed_cache_memory_records'] ?? [];
    if (!is_array($records)) {
        $records = [];
    }

    $records[$contextHash] = $record;
    $GLOBALS['sr_community_feed_cache_memory_records'] = $records;
}

function sr_community_feed_cache_clear_memory_records(): void
{
    $GLOBALS['sr_community_feed_cache_memory_records'] = [];
}

function sr_community_feed_cache_read(PDO $pdo, array $context, array $allowedSchemaVersions = ['community_feed_card_snapshot_v1']): ?array
{
    unset($pdo);

    $context = sr_community_feed_cache_context($context);
    $contextHash = sr_community_feed_cache_context_hash($context);
    $memoryRecord = sr_community_feed_cache_memory_record($contextHash);
    if (is_array($memoryRecord) && sr_community_feed_cache_file_is_active($memoryRecord)) {
        return sr_community_feed_cache_snapshots_from_json(
            json_encode($memoryRecord['snapshots'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]',
            $allowedSchemaVersions
        );
    }

    $path = sr_community_feed_cache_file_path($contextHash);
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $json = file_get_contents($path);
    if (!is_string($json)) {
        return null;
    }
    $record = json_decode($json, true);
    if (!is_array($record)
        || (string) ($record['schema_version'] ?? '') !== 'community_feed_file_cache_v1'
        || (string) ($record['context_hash'] ?? '') !== $contextHash
        || !sr_community_feed_cache_file_is_active($record)
    ) {
        return null;
    }

    return sr_community_feed_cache_snapshots_from_json(
        json_encode($record['snapshots'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]',
        $allowedSchemaVersions
    );
}

function sr_community_feed_cache_write(PDO $pdo, array $context, array $posts): void
{
    $snapshots = [];
    foreach ($posts as $post) {
        if (is_array($post)) {
            $snapshots[] = sr_community_feed_cache_card_snapshot($post);
        }
    }

    sr_community_feed_cache_write_snapshots($pdo, $context, $snapshots);
}

function sr_community_feed_cache_write_snapshots(PDO $pdo, array $context, array $snapshots): void
{
    unset($pdo);

    $context = sr_community_feed_cache_context($context);
    $safeSnapshots = [];
    foreach ($snapshots as $snapshot) {
        if (is_array($snapshot) && !sr_community_feed_cache_snapshot_contains_forbidden_key($snapshot)) {
            $safeSnapshots[] = $snapshot;
        }
    }

    $now = sr_now();
    $record = sr_community_feed_cache_file_record($context, $safeSnapshots, $now);
    $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        return;
    }
    $contextHash = sr_community_feed_cache_context_hash($context);
    sr_community_feed_cache_remember_record($contextHash, $record);
    $path = sr_community_feed_cache_file_path($contextHash);
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return;
    }

    $temporaryPath = $path . '.tmp.' . bin2hex(random_bytes(6));
    $handle = @fopen($temporaryPath, 'wb');
    if ($handle === false) {
        return;
    }
    $written = false;
    if (flock($handle, LOCK_EX)) {
        $written = fwrite($handle, $json . "\n") !== false;
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
    if (!$written || !@rename($temporaryPath, $path)) {
        @unlink($temporaryPath);
        return;
    }
    @chmod($path, 0664);
}

function sr_community_feed_cache_mark_all_stale(PDO $pdo, string $reason = 'content_changed'): void
{
    unset($pdo, $reason);

    sr_community_feed_cache_clear_memory_records();
    foreach (glob(sr_community_feed_cache_file_root() . '/*/*.json') ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
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
    $summaryFeedCandidateSql = sr_community_summary_post_candidate_sql_condition($pdo, 'p0', 'p0.board_id');

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
 ' . $summaryFeedCandidateSql . '            ORDER BY ' . $innerOrderSql . '
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
        'storage_driver' => (string) ($post['list_image_storage_driver'] ?? $post['thumbnail_storage_driver'] ?? 'local'),
        'storage_key' => (string) ($post['list_image_storage_key'] ?? $post['thumbnail_storage_key'] ?? ''),
        'mime_type' => (string) ($post['list_image_mime_type'] ?? $post['thumbnail_mime_type'] ?? ''),
        'size_bytes' => max(0, (int) ($post['list_image_size_bytes'] ?? $post['thumbnail_size_bytes'] ?? 0)),
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
        'comment_count' => max(0, (int) ($post['published_comment_count'] ?? $post['comment_count'] ?? 0)),
        'thumbnail_source' => sr_community_feed_cache_thumbnail_source_marker($post),
        'is_secret' => !empty($post['is_secret']) ? 1 : 0,
        'excerpt' => !empty($post['is_secret']) ? '' : sr_community_body_excerpt((string) ($post['body_text'] ?? ''), (string) ($post['body_format'] ?? 'plain'), 160),
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
    unset($pdo);

    $status = [
        'mode' => 'not_installed',
        'file_cache_exists' => false,
        'row_count' => 0,
        'active_count' => 0,
        'fresh_count' => 0,
        'stale_count' => 0,
        'expired_count' => 0,
        'latest_generated_at' => '',
        'latest_updated_at' => '',
        'next_expires_at' => '',
    ];

    $status['file_cache_exists'] = is_dir(sr_community_feed_cache_file_root());
    $records = sr_community_feed_cache_file_records();
    foreach ($records as $record) {
        $status['row_count']++;
        if ((string) ($record['cache_status'] ?? '') === 'fresh') {
            $status['fresh_count']++;
        } else {
            $status['stale_count']++;
        }
        if (sr_community_feed_cache_file_is_active($record)) {
            $status['active_count']++;
            $expiresAt = (string) ($record['expires_at'] ?? '');
            if ($expiresAt !== ''
                && $expiresAt !== '9999-12-31 23:59:59'
                && ($status['next_expires_at'] === '' || strcmp($expiresAt, $status['next_expires_at']) < 0)
            ) {
                $status['next_expires_at'] = $expiresAt;
            }
        } elseif ((string) ($record['cache_status'] ?? '') === 'fresh') {
            $status['expired_count']++;
        }
        $generatedAt = (string) ($record['generated_at'] ?? '');
        $updatedAt = (string) ($record['updated_at'] ?? '');
        if ($generatedAt !== '' && strcmp($generatedAt, $status['latest_generated_at']) > 0) {
            $status['latest_generated_at'] = $generatedAt;
        }
        if ($updatedAt !== '' && strcmp($updatedAt, $status['latest_updated_at']) > 0) {
            $status['latest_updated_at'] = $updatedAt;
        }
    }
    if ($status['file_cache_exists']) {
        $status['mode'] = 'file_persistent';
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
        $summaryFeedEnabled = (string) ($board['effective_summary_feed_enabled'] ?? $board['summary_feed_enabled'] ?? '1') === '1';
        $baseline = sr_community_feed_cache_public_baseline_board($board);
        $homeExcerptAllowed = !function_exists('sr_community_asset_event_required') || !sr_community_asset_event_required($paidReadConfig);

        $rows[] = [
            'id' => $boardId,
            'board_key' => (string) ($board['board_key'] ?? ''),
            'title' => (string) ($board['title'] ?? ''),
            'status' => (string) ($board['status'] ?? ''),
            'read_policy' => $readPolicy,
            'summary_feed_enabled' => $summaryFeedEnabled,
            'public_baseline' => $baseline,
            'home_excerpt_allowed' => $homeExcerptAllowed,
            'paid_read_required' => !$homeExcerptAllowed,
        ];
    }

    return $rows;
}

function sr_community_feed_cache_admin_context_rows(PDO $pdo): array
{
    unset($pdo);

    $rows = [];
    foreach (sr_community_feed_cache_file_records(100) as $row) {
        if (!sr_community_feed_cache_file_is_active($row)) {
            continue;
        }

        $boardIds = $row['board_ids'] ?? [];
        $boardCount = is_array($boardIds) ? count(array_filter($boardIds, static fn (mixed $boardId): bool => (int) $boardId > 0)) : 0;
        $rows[] = [
            'feed_key' => (string) ($row['feed_key'] ?? ''),
            'sort' => (string) ($row['sort_key'] ?? ''),
            'display_count' => (int) ($row['display_count'] ?? 0),
            'fetch_count' => (int) ($row['fetch_count'] ?? 0),
            'locale' => (string) ($row['locale'] ?? ''),
            'policy_version' => (string) ($row['policy_version'] ?? ''),
            'baseline' => (string) ($row['baseline'] ?? ''),
            'board_count' => $boardCount,
            'snapshot_count' => (int) ($row['snapshot_count'] ?? 0),
            'cache_status' => (string) ($row['cache_status'] ?? ''),
            'refresh_policy' => sr_community_feed_cache_refresh_policy_label((string) ($row['feed_key'] ?? '')),
            'generated_at' => (string) ($row['generated_at'] ?? ''),
            'expires_at' => (string) ($row['expires_at'] ?? ''),
            'stale_reason' => (string) ($row['stale_reason'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'context_hash' => (string) ($row['context_hash'] ?? ''),
        ];
    }

    return $rows;
}
