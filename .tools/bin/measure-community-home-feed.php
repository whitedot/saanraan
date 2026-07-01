#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once $root . '/core/helpers.php';
require_once $root . '/modules/community/helpers.php';

function sr_measure_community_home_feed_int_env(string $key, int $default, int $min, int $max): int
{
    $value = getenv($key);
    if (!is_string($value) || !preg_match('/\A[0-9]+\z/', $value)) {
        return $default;
    }

    return max($min, min($max, (int) $value));
}

function sr_measure_community_home_feed_string_env(string $key, string $default = ''): string
{
    $value = getenv($key);

    return is_string($value) ? trim($value) : $default;
}

function sr_measure_community_home_feed_bool_env(string $key): bool
{
    return in_array(strtolower(sr_measure_community_home_feed_string_env($key)), ['1', 'true', 'yes', 'on'], true);
}

function sr_measure_community_home_feed_ms(float $start): float
{
    return round((microtime(true) - $start) * 1000, 3);
}

function sr_measure_community_home_feed_sql(PDO $pdo, string $sort, array $boardIds, int $limit): array
{
    return sr_community_feed_cache_post_feed_query($pdo, $boardIds, $limit, $sort, 'board_id_');
}

function sr_measure_community_home_feed_variant_sql(PDO $pdo, string $sort, array $boardIds, int $limit, string $variant): array
{
    if ($variant !== 'limited_posts_first') {
        return sr_measure_community_home_feed_sql($pdo, $sort, $boardIds, $limit);
    }

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
    foreach (array_values($ids) as $index => $boardId) {
        $paramKey = 'variant_board_id_' . (string) $index;
        $placeholders[] = ':' . $paramKey;
        $params[$paramKey] = $boardId;
    }

    $limit = sr_community_feed_cache_count($limit, 20);
    $orderSql = sr_community_feed_cache_sort_key($sort) === 'views'
        ? 'p.view_count DESC, p.id DESC'
        : 'p.id DESC';
    $innerOrderSql = sr_community_feed_cache_sort_key($sort) === 'views'
        ? 'p0.view_count DESC, p0.id DESC'
        : 'p0.id DESC';
    $params['limit_value'] = $limit;

    return [
        'SELECT p.id, p.board_id, NULL AS category_id, NULL AS category_key, NULL AS category_title, NULL AS category_status,
                p.author_account_id, p.author_public_name_snapshot' . sr_community_guest_author_select($pdo, 'sr_community_posts', 'p') . sr_community_post_extra_values_select($pdo, 'p') . ', author.status AS author_account_status, p.title, p.body_text, p.body_format, p.is_secret, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
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

function sr_measure_community_home_feed_query(PDO $pdo, string $sort, array $boardIds, int $limit): array
{
    [$sql, $params] = sr_measure_community_home_feed_sql($pdo, $sort, $boardIds, $limit);
    if ($sql === '') {
        return [];
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, (int) $value, PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_measure_community_home_feed_variant_query(PDO $pdo, string $sort, array $boardIds, int $limit, string $variant): array
{
    [$sql, $params] = sr_measure_community_home_feed_variant_sql($pdo, $sort, $boardIds, $limit, $variant);
    if ($sql === '') {
        return [];
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, (int) $value, PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_measure_community_home_feed_query_plan(PDO $pdo, string $sort, array $boardIds, int $limit): array
{
    [$sql, $params] = sr_measure_community_home_feed_sql($pdo, $sort, $boardIds, $limit);
    if ($sql === '') {
        return [];
    }

    $prefix = str_starts_with((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite') ? 'EXPLAIN QUERY PLAN ' : 'EXPLAIN ';
    $stmt = $pdo->prepare($prefix . $sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, (int) $value, PDO::PARAM_INT);
    }
    $stmt->execute();

    return array_map(static fn (array $row): string => implode('|', array_map(static fn ($value): string => (string) $value, $row)), $stmt->fetchAll());
}

function sr_measure_community_home_feed_variant_query_plan(PDO $pdo, string $sort, array $boardIds, int $limit, string $variant): array
{
    [$sql, $params] = sr_measure_community_home_feed_variant_sql($pdo, $sort, $boardIds, $limit, $variant);
    if ($sql === '') {
        return [];
    }

    $prefix = str_starts_with((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite') ? 'EXPLAIN QUERY PLAN ' : 'EXPLAIN ';
    $stmt = $pdo->prepare($prefix . $sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, (int) $value, PDO::PARAM_INT);
    }
    $stmt->execute();

    return array_map(static fn (array $row): string => implode('|', array_map(static fn ($value): string => (string) $value, $row)), $stmt->fetchAll());
}

function sr_measure_community_home_feed_connect_target(): ?PDO
{
    if (sr_measure_community_home_feed_bool_env('SR_COMMUNITY_FEED_MEASURE_CONFIG')) {
        $config = sr_load_config();
        sr_set_runtime_config($config);

        return sr_db($config);
    }

    $dsn = sr_measure_community_home_feed_string_env('SR_COMMUNITY_FEED_MEASURE_DSN');
    if ($dsn === '') {
        return null;
    }

    $user = sr_measure_community_home_feed_string_env('SR_COMMUNITY_FEED_MEASURE_USER');
    $password = sr_measure_community_home_feed_string_env('SR_COMMUNITY_FEED_MEASURE_PASSWORD');
    $prefix = sr_table_prefix(['db' => ['table_prefix' => sr_measure_community_home_feed_string_env('SR_COMMUNITY_FEED_MEASURE_TABLE_PREFIX', 'sr_')]]);

    return new SrPrefixedPDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ], $prefix);
}

function sr_measure_community_home_feed_target_board_ids(PDO $pdo): array
{
    $manual = sr_measure_community_home_feed_string_env('SR_COMMUNITY_FEED_MEASURE_BOARD_IDS');
    if ($manual !== '') {
        $ids = [];
        foreach (preg_split('/\s*,\s*/', $manual) ?: [] as $value) {
            if (preg_match('/\A[1-9][0-9]*\z/', $value) === 1) {
                $ids[(int) $value] = (int) $value;
            }
        }

        ksort($ids, SORT_NUMERIC);

        return array_values($ids);
    }

    $limit = sr_measure_community_home_feed_int_env('SR_COMMUNITY_FEED_MEASURE_BOARD_LIMIT', 200, 1, 1000);
    $stmt = $pdo->prepare(
        "SELECT id
         FROM sr_community_boards
         WHERE status = 'enabled'
           AND read_policy = 'public'
         ORDER BY sort_order ASC, id ASC
         LIMIT :limit_value"
    );
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $ids = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}

function sr_measure_community_home_feed_run(PDO $pdo, array $boardIds, string $fixtureStep, string $fixtureSize, float $fixtureMs, string $dbVersion, string $hostingProbe, string $decision): void
{
    $latestColdStart = microtime(true);
    $latestCold = sr_measure_community_home_feed_query($pdo, 'latest', $boardIds, 10);
    $latestColdMs = sr_measure_community_home_feed_ms($latestColdStart);
    $latestWarmStart = microtime(true);
    $latestWarm = sr_measure_community_home_feed_query($pdo, 'latest', $boardIds, 10);
    $latestWarmMs = sr_measure_community_home_feed_ms($latestWarmStart);

    $popularColdStart = microtime(true);
    $popularCold = sr_measure_community_home_feed_query($pdo, 'views', $boardIds, 5);
    $popularColdMs = sr_measure_community_home_feed_ms($popularColdStart);
    $popularWarmStart = microtime(true);
    $popularWarm = sr_measure_community_home_feed_query($pdo, 'views', $boardIds, 5);
    $popularWarmMs = sr_measure_community_home_feed_ms($popularWarmStart);

    echo "community-home-feed-measurement\n";
    echo "fixture-step: " . $fixtureStep . "\n";
    echo "fixture-size: " . $fixtureSize . "\n";
    echo "fixture-build-ms: " . (string) $fixtureMs . "\n";
    echo "db-version: " . $dbVersion . "\n";
    echo "hosting-probe: " . $hostingProbe . "\n";
    echo "query-id: community.home.latest\n";
    echo "sql-summary: home feed select sort=latest public baseline boards\n";
    echo "bind-summary: board_ids=" . (string) count($boardIds) . " limit=10\n";
    echo "explain:\n";
    foreach (sr_measure_community_home_feed_query_plan($pdo, 'latest', $boardIds, 10) as $line) {
        echo "  " . $line . "\n";
    }
    echo "response-ms-cold: " . (string) $latestColdMs . "\n";
    echo "response-ms-warm: " . (string) $latestWarmMs . "\n";
    echo "returned-rows: cold=" . (string) count($latestCold) . " warm=" . (string) count($latestWarm) . "\n";
    if ($latestCold === [] && $latestWarm === []) {
        echo "representative-warning: no returned rows; this does not prove a populated feed bottleneck\n";
    }
    echo "decision: " . $decision . "\n";
    echo "follow-up: compare against #369 cache-table threshold before persistent cache work\n";
    echo "query-id: community.home.popular\n";
    echo "sql-summary: home feed select sort=views public baseline boards\n";
    echo "bind-summary: board_ids=" . (string) count($boardIds) . " limit=5\n";
    echo "explain:\n";
    foreach (sr_measure_community_home_feed_query_plan($pdo, 'views', $boardIds, 5) as $line) {
        echo "  " . $line . "\n";
    }
    echo "response-ms-cold: " . (string) $popularColdMs . "\n";
    echo "response-ms-warm: " . (string) $popularWarmMs . "\n";
    echo "returned-rows: cold=" . (string) count($popularCold) . " warm=" . (string) count($popularWarm) . "\n";
    if ($popularCold === [] && $popularWarm === []) {
        echo "representative-warning: no returned rows; this does not prove a populated feed bottleneck\n";
    }
    echo "decision: " . $decision . "\n";
    echo "follow-up: compare against #369 cache-table threshold before persistent cache work\n";
}

function sr_measure_community_home_feed_variant_result(PDO $pdo, array $boardIds, string $label, string $variant): void
{
    $latestColdStart = microtime(true);
    $latestCold = sr_measure_community_home_feed_variant_query($pdo, 'latest', $boardIds, 10, $variant);
    $latestColdMs = sr_measure_community_home_feed_ms($latestColdStart);
    $latestWarmStart = microtime(true);
    $latestWarm = sr_measure_community_home_feed_variant_query($pdo, 'latest', $boardIds, 10, $variant);
    $latestWarmMs = sr_measure_community_home_feed_ms($latestWarmStart);

    $popularColdStart = microtime(true);
    $popularCold = sr_measure_community_home_feed_variant_query($pdo, 'views', $boardIds, 5, $variant);
    $popularColdMs = sr_measure_community_home_feed_ms($popularColdStart);
    $popularWarmStart = microtime(true);
    $popularWarm = sr_measure_community_home_feed_variant_query($pdo, 'views', $boardIds, 5, $variant);
    $popularWarmMs = sr_measure_community_home_feed_ms($popularWarmStart);

    echo "candidate-label: " . $label . "\n";
    echo "candidate-variant: " . $variant . "\n";
    echo "query-id: community.home.latest\n";
    echo "explain:\n";
    foreach (sr_measure_community_home_feed_variant_query_plan($pdo, 'latest', $boardIds, 10, $variant) as $line) {
        echo "  " . $line . "\n";
    }
    echo "response-ms-cold: " . (string) $latestColdMs . "\n";
    echo "response-ms-warm: " . (string) $latestWarmMs . "\n";
    echo "returned-rows: cold=" . (string) count($latestCold) . " warm=" . (string) count($latestWarm) . "\n";
    echo "query-id: community.home.popular\n";
    echo "explain:\n";
    foreach (sr_measure_community_home_feed_variant_query_plan($pdo, 'views', $boardIds, 5, $variant) as $line) {
        echo "  " . $line . "\n";
    }
    echo "response-ms-cold: " . (string) $popularColdMs . "\n";
    echo "response-ms-warm: " . (string) $popularWarmMs . "\n";
    echo "returned-rows: cold=" . (string) count($popularCold) . " warm=" . (string) count($popularWarm) . "\n";
}

function sr_measure_community_home_feed_compare(PDO $pdo, array $boardIds, string $fixtureSize, string $dbVersion): void
{
    $indexName = 'idx_sr_community_posts_status_view_id_candidate';
    $allowMutation = sr_measure_community_home_feed_bool_env('SR_COMMUNITY_FEED_MEASURE_COMPARE_MUTATION');

    echo "community-home-feed-candidate-comparison\n";
    echo "fixture-size: " . $fixtureSize . "\n";
    echo "db-version: " . $dbVersion . "\n";
    echo "board_ids: " . (string) count($boardIds) . "\n";
    echo "mutation-enabled: " . ($allowMutation ? 'yes' : 'no') . "\n";

    if ($allowMutation) {
        $pdo->exec('DROP INDEX IF EXISTS ' . $indexName . ' ON sr_community_posts');
    }

    sr_measure_community_home_feed_variant_result($pdo, $boardIds, 'baseline-no-candidate-index', 'baseline');
    sr_measure_community_home_feed_variant_result($pdo, $boardIds, 'limited-posts-first-no-candidate-index', 'limited_posts_first');

    if (!$allowMutation) {
        echo "candidate-index-skipped: set SR_COMMUNITY_FEED_MEASURE_COMPARE_MUTATION=1 on local/staging disposable data to create/drop " . $indexName . "\n";
        return;
    }

    $indexStart = microtime(true);
    $pdo->exec('CREATE INDEX ' . $indexName . ' ON sr_community_posts (status, view_count, id)');
    echo "candidate-index-created: " . $indexName . "\n";
    echo "candidate-index-create-ms: " . (string) sr_measure_community_home_feed_ms($indexStart) . "\n";

    try {
        sr_measure_community_home_feed_variant_result($pdo, $boardIds, 'baseline-with-status-view-index', 'baseline');
        sr_measure_community_home_feed_variant_result($pdo, $boardIds, 'limited-posts-first-with-status-view-index', 'limited_posts_first');
    } finally {
        $dropStart = microtime(true);
        $pdo->exec('DROP INDEX IF EXISTS ' . $indexName . ' ON sr_community_posts');
        echo "candidate-index-dropped: " . $indexName . "\n";
        echo "candidate-index-drop-ms: " . (string) sr_measure_community_home_feed_ms($dropStart) . "\n";
    }
}

function sr_measure_community_home_feed_create_fixture(PDO $pdo, int $postCount, int $boardCount): array
{
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $pdo->exec('PRAGMA journal_mode = MEMORY');
        $pdo->exec('PRAGMA synchronous = OFF');
    }
    foreach ([
        'sr_community_board_group_settings',
        'sr_community_board_settings',
        'sr_community_attachments',
        'sr_community_comments',
        'sr_community_posts',
        'sr_member_accounts',
    ] as $tableName) {
        $pdo->exec('DROP TABLE IF EXISTS ' . $tableName);
    }
    $pdo->exec('CREATE TABLE sr_community_posts (
        id BIGINT PRIMARY KEY,
        board_id BIGINT NOT NULL,
        author_account_id BIGINT NOT NULL DEFAULT 0,
        author_public_name_snapshot VARCHAR(120) NOT NULL DEFAULT "",
        guest_author_name VARCHAR(120) NOT NULL DEFAULT "",
        guest_password_hash VARCHAR(255) NULL,
        guest_ip_hash CHAR(64) NULL,
        guest_user_agent_hash CHAR(64) NULL,
        extra_values_json TEXT NULL,
        title VARCHAR(255) NOT NULL,
        body_text TEXT NOT NULL,
        body_format VARCHAR(20) NOT NULL DEFAULT "plain",
        reaction_preset_key VARCHAR(80) NOT NULL DEFAULT "",
        reaction_comment_preset_key VARCHAR(80) NOT NULL DEFAULT "",
        is_secret TINYINT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL,
        hidden_at DATETIME NULL,
        hidden_until DATETIME NULL,
        hidden_reason VARCHAR(40) NOT NULL DEFAULT "",
        hidden_note TEXT NULL,
        hidden_by_account_id BIGINT NULL,
        hidden_before_status VARCHAR(30) NOT NULL DEFAULT "",
        summary_feed_candidate TINYINT NOT NULL DEFAULT 1,
        view_count BIGINT NOT NULL DEFAULT 0,
        last_commented_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_community_comments (
        id BIGINT PRIMARY KEY,
        post_id BIGINT NOT NULL,
        parent_comment_id BIGINT NULL,
        thread_root_id BIGINT NULL,
        depth TINYINT NOT NULL DEFAULT 1,
        author_account_id BIGINT NULL,
        author_public_name_snapshot VARCHAR(120) NOT NULL DEFAULT "",
        guest_author_name VARCHAR(120) NOT NULL DEFAULT "",
        guest_password_hash VARCHAR(255) NULL,
        guest_ip_hash CHAR(64) NULL,
        guest_user_agent_hash CHAR(64) NULL,
        body_text TEXT NOT NULL DEFAULT "",
        is_secret TINYINT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_community_attachments (
        id BIGINT PRIMARY KEY,
        post_id BIGINT NOT NULL,
        uploader_account_id BIGINT NOT NULL DEFAULT 0,
        original_name VARCHAR(255) NOT NULL DEFAULT "",
        stored_name VARCHAR(255) NOT NULL DEFAULT "",
        storage_path VARCHAR(255) NOT NULL DEFAULT "",
        status VARCHAR(20) NOT NULL,
        mime_type VARCHAR(80) NOT NULL,
        storage_driver VARCHAR(20) NOT NULL DEFAULT "",
        storage_key VARCHAR(255) NOT NULL DEFAULT "",
        size_bytes BIGINT NOT NULL DEFAULT 0,
        checksum_sha256 VARCHAR(64) NOT NULL DEFAULT "",
        width INT NULL,
        height INT NULL,
        created_at DATETIME NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_member_accounts (
        id BIGINT PRIMARY KEY,
        status VARCHAR(20) NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_community_board_settings (
        board_id BIGINT NOT NULL,
        setting_key VARCHAR(120) NOT NULL,
        setting_value TEXT NOT NULL,
        value_type VARCHAR(20) NOT NULL DEFAULT "string",
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (board_id, setting_key)
    )');
    $pdo->exec('CREATE TABLE sr_community_board_group_settings (
        group_id BIGINT NOT NULL,
        setting_key VARCHAR(120) NOT NULL,
        setting_value TEXT NOT NULL,
        value_type VARCHAR(20) NOT NULL DEFAULT "string",
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (group_id, setting_key)
    )');
    $pdo->exec('CREATE INDEX idx_sr_community_posts_board_status_id ON sr_community_posts (board_id, status, id)');
    $pdo->exec('CREATE INDEX idx_sr_community_posts_status_id ON sr_community_posts (status, id)');
    $pdo->exec('CREATE INDEX idx_sr_community_posts_view ON sr_community_posts (status, view_count, id)');
    $pdo->exec('CREATE INDEX idx_sr_community_comments_post_status_id ON sr_community_comments (post_id, status, id)');
    $pdo->exec('CREATE INDEX idx_sr_community_attachments_post_status_id ON sr_community_attachments (post_id, status, id)');

    $pdo->beginTransaction();
    $postStmt = $pdo->prepare(
        'INSERT INTO sr_community_posts
            (id, board_id, author_account_id, author_public_name_snapshot, title, body_text, body_format, is_secret, status, view_count, created_at, updated_at)
         VALUES
            (:id, :board_id, :author_account_id, :author_public_name_snapshot, :title, :body_text, "plain", :is_secret, :status, :view_count, :created_at, :updated_at)'
    );
    $commentStmt = $pdo->prepare('INSERT INTO sr_community_comments (id, post_id, status) VALUES (:id, :post_id, :status)');
    $attachmentStmt = $pdo->prepare(
        'INSERT INTO sr_community_attachments
            (id, post_id, uploader_account_id, original_name, stored_name, storage_path, status, mime_type, storage_driver, storage_key, size_bytes, checksum_sha256, width, height, created_at)
         VALUES
            (:id, :post_id, 0, :original_name, :stored_name, :storage_path, :status, :mime_type, "local", :storage_key, 2048, :checksum_sha256, 640, 360, :created_at)'
    );
    $memberStmt = $pdo->prepare('INSERT INTO sr_member_accounts (id, status) VALUES (:id, "active")');
    for ($accountId = 1; $accountId <= 100; $accountId++) {
        $memberStmt->execute(['id' => $accountId]);
    }

    $commentId = 1;
    $attachmentId = 1;
    $now = '2026-06-24 12:00:00';
    for ($postId = 1; $postId <= $postCount; $postId++) {
        $status = $postId % 17 === 0 ? 'hidden' : 'published';
        $boardId = (($postId - 1) % $boardCount) + 1;
        $postStmt->execute([
            'id' => $postId,
            'board_id' => $boardId,
            'author_account_id' => ($postId % 100) + 1,
            'author_public_name_snapshot' => 'author ' . (string) (($postId % 100) + 1),
            'title' => 'Fixture post ' . (string) $postId,
            'body_text' => 'Fixture body ' . (string) $postId,
            'is_secret' => $postId % 29 === 0 ? 1 : 0,
            'status' => $status,
            'view_count' => ($postId * 37) % 100000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if ($status === 'published' && $postId % 3 === 0) {
            $commentStmt->execute(['id' => $commentId++, 'post_id' => $postId, 'status' => 'published']);
        }
        if ($status === 'published' && $postId % 8 === 0) {
            $attachmentStmt->execute([
                'id' => $attachmentId++,
                'post_id' => $postId,
                'status' => 'active',
                'mime_type' => 'image/jpeg',
                'original_name' => 'fixture-' . (string) $postId . '.jpg',
                'stored_name' => 'fixture-' . (string) $postId . '.jpg',
                'storage_path' => 'storage/community/fixture/' . (string) $postId . '.jpg',
                'storage_key' => 'community/fixture/' . (string) $postId . '.jpg',
                'checksum_sha256' => hash('sha256', 'fixture-' . (string) $postId),
                'created_at' => $now,
            ]);
        }
    }
    $pdo->commit();

    $boards = [];
    for ($boardId = 1; $boardId <= $boardCount; $boardId++) {
        $boards[] = [
            'id' => $boardId,
            'status' => 'enabled',
            'read_policy' => 'public',
            'effective_read_policy' => 'public',
        ];
    }

    return $boards;
}

$postCount = sr_measure_community_home_feed_int_env('SR_COMMUNITY_FEED_MEASURE_POSTS', 10000, 1000, 100000);
$boardCount = sr_measure_community_home_feed_int_env('SR_COMMUNITY_FEED_MEASURE_BOARDS', 20, 1, 200);
$targetPdo = sr_measure_community_home_feed_connect_target();
if ($targetPdo instanceof PDO) {
    $targetFixture = sr_measure_community_home_feed_bool_env('SR_COMMUNITY_FEED_MEASURE_FIXTURE');
    $fixtureMs = 0.0;
    if ($targetFixture) {
        $fixtureStart = microtime(true);
        $boards = sr_measure_community_home_feed_create_fixture($targetPdo, $postCount, $boardCount);
        $fixtureMs = sr_measure_community_home_feed_ms($fixtureStart);
        $boardIds = sr_community_feed_cache_public_baseline_board_ids($boards);
    } else {
        $boardIds = sr_measure_community_home_feed_target_board_ids($targetPdo);
    }
    if ($boardIds === []) {
        fwrite(STDERR, "No public baseline board ids found. Set SR_COMMUNITY_FEED_MEASURE_BOARD_IDS for explicit read-only measurement.\n");
        exit(2);
    }

    $driver = (string) $targetPdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $version = (string) $targetPdo->query('SELECT VERSION()')->fetchColumn();
    if (sr_measure_community_home_feed_bool_env('SR_COMMUNITY_FEED_MEASURE_COMPARE')) {
        sr_measure_community_home_feed_compare(
            $targetPdo,
            $boardIds,
            $targetFixture ? 'posts=' . (string) $postCount . ' boards=' . (string) $boardCount : 'board_ids=' . (string) count($boardIds),
            $driver . ' ' . $version
        );
        exit(0);
    }

    $targetMode = sr_measure_community_home_feed_bool_env('SR_COMMUNITY_FEED_MEASURE_CONFIG') ? 'config DB' : 'explicit DSN';
    sr_measure_community_home_feed_run(
        $targetPdo,
        $boardIds,
        $targetFixture ? 'mariadb-fixture' : 'target-readonly',
        $targetFixture ? 'posts=' . (string) $postCount . ' boards=' . (string) $boardCount : 'board_ids=' . (string) count($boardIds),
        $fixtureMs,
        $driver . ' ' . $version,
        $targetFixture ? 'explicit DSN fixture measurement on MySQL/MariaDB syntax' : $targetMode . ' read-only measurement; production data is not required or recommended',
        $targetFixture ? 'MySQL/MariaDB fixture evidence only; target data is still required before persistent cache work' : 'target DB evidence; persistent cache work still needs repository decision using this record'
    );
    exit(0);
}

$pdo = new PDO('sqlite::memory:');
$fixtureStart = microtime(true);
$boards = sr_measure_community_home_feed_create_fixture($pdo, $postCount, $boardCount);
$fixtureMs = sr_measure_community_home_feed_ms($fixtureStart);
$boardIds = sr_community_feed_cache_public_baseline_board_ids($boards);
$dbVersion = (string) $pdo->query('SELECT sqlite_version()')->fetchColumn();

sr_measure_community_home_feed_run(
    $pdo,
    $boardIds,
    'sqlite-local',
    'posts=' . (string) $postCount . ' boards=' . (string) $boardCount,
    $fixtureMs,
    'sqlite ' . $dbVersion,
    'sqlite fixture only; run explicit MySQL/MariaDB DSN on local/staging before persistent cache table work',
    'fixture evidence only; does not authorize persistent cache table without target DB measurement'
);
