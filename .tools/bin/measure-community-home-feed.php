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

function sr_measure_community_home_feed_ms(float $start): float
{
    return round((microtime(true) - $start) * 1000, 3);
}

function sr_measure_community_home_feed_query_plan(PDO $pdo, string $sort, array $boardIds, int $limit): array
{
    $placeholders = [];
    $params = [];
    foreach (array_values($boardIds) as $index => $boardId) {
        $key = 'board_id_' . (string) $index;
        $placeholders[] = ':' . $key;
        $params[$key] = (int) $boardId;
    }
    if ($placeholders === []) {
        return [];
    }

    $orderSql = $sort === 'views' ? 'p.view_count DESC, p.id DESC' : 'p.id DESC';
    $stmt = $pdo->prepare(
        'EXPLAIN QUERY PLAN
         SELECT p.id, p.board_id, p.author_account_id, p.title, p.body_text, p.body_format,
                p.is_secret, p.status, p.view_count, p.last_commented_at, p.created_at, p.updated_at,
                (SELECT COUNT(*) FROM sr_community_comments c WHERE c.post_id = p.id AND c.status = \'published\') AS published_comment_count,
                (SELECT COUNT(*) FROM sr_community_attachments att WHERE att.post_id = p.id AND att.status = \'active\') AS active_attachment_count,
                list_image.id AS list_image_attachment_id
         FROM sr_community_posts p
         LEFT JOIN sr_member_accounts author ON author.id = p.author_account_id
         LEFT JOIN (
             SELECT post_id, MIN(id) AS attachment_id
             FROM sr_community_attachments
             WHERE status = \'active\'
               AND mime_type IN (\'image/jpeg\', \'image/png\', \'image/gif\', \'image/webp\')
             GROUP BY post_id
         ) list_image_pick ON list_image_pick.post_id = p.id
         LEFT JOIN sr_community_attachments list_image ON list_image.id = list_image_pick.attachment_id
         WHERE p.status = \'published\'
           AND p.board_id IN (' . implode(', ', $placeholders) . ')
         ORDER BY ' . $orderSql . '
         LIMIT :limit_value'
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return array_map(static fn (array $row): string => implode('|', array_map(static fn ($value): string => (string) $value, $row)), $stmt->fetchAll());
}

function sr_measure_community_home_feed_create_fixture(PDO $pdo, int $postCount, int $boardCount): array
{
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode = MEMORY');
    $pdo->exec('PRAGMA synchronous = OFF');
    $pdo->exec('CREATE TABLE sr_community_posts (
        id INTEGER PRIMARY KEY,
        board_id INTEGER NOT NULL,
        author_account_id INTEGER NOT NULL DEFAULT 0,
        author_public_name_snapshot TEXT NOT NULL DEFAULT "",
        guest_author_name TEXT NOT NULL DEFAULT "",
        extra_values_json TEXT NULL,
        title TEXT NOT NULL,
        body_text TEXT NOT NULL,
        body_format TEXT NOT NULL DEFAULT "plain",
        is_secret INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL,
        view_count INTEGER NOT NULL DEFAULT 0,
        last_commented_at TEXT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_community_comments (
        id INTEGER PRIMARY KEY,
        post_id INTEGER NOT NULL,
        status TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_community_attachments (
        id INTEGER PRIMARY KEY,
        post_id INTEGER NOT NULL,
        uploader_account_id INTEGER NOT NULL DEFAULT 0,
        original_name TEXT NOT NULL DEFAULT "",
        stored_name TEXT NOT NULL DEFAULT "",
        storage_path TEXT NOT NULL DEFAULT "",
        status TEXT NOT NULL,
        mime_type TEXT NOT NULL,
        storage_driver TEXT NOT NULL DEFAULT "",
        storage_key TEXT NOT NULL DEFAULT "",
        size_bytes INTEGER NOT NULL DEFAULT 0,
        checksum_sha256 TEXT NOT NULL DEFAULT "",
        width INTEGER NULL,
        height INTEGER NULL,
        created_at TEXT NOT NULL DEFAULT ""
    )');
    $pdo->exec('CREATE TABLE sr_member_accounts (
        id INTEGER PRIMARY KEY,
        status TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_community_board_settings (
        board_id INTEGER NOT NULL,
        setting_key TEXT NOT NULL,
        setting_value TEXT NOT NULL,
        value_type TEXT NOT NULL DEFAULT "string",
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        PRIMARY KEY (board_id, setting_key)
    )');
    $pdo->exec('CREATE TABLE sr_community_board_group_settings (
        group_id INTEGER NOT NULL,
        setting_key TEXT NOT NULL,
        setting_value TEXT NOT NULL,
        value_type TEXT NOT NULL DEFAULT "string",
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
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
$pdo = new PDO('sqlite::memory:');
$fixtureStart = microtime(true);
$boards = sr_measure_community_home_feed_create_fixture($pdo, $postCount, $boardCount);
$fixtureMs = sr_measure_community_home_feed_ms($fixtureStart);
$settings = sr_community_default_settings();
$homeExcerptAllowedByBoardId = array_fill_keys(sr_community_feed_cache_public_baseline_board_ids($boards), true);
$boardIds = array_values(array_keys($homeExcerptAllowedByBoardId));

$latestColdStart = microtime(true);
$latestCold = sr_community_home_post_feed($pdo, $boards, $settings, $homeExcerptAllowedByBoardId, 10, 'latest');
$latestColdMs = sr_measure_community_home_feed_ms($latestColdStart);
$latestWarmStart = microtime(true);
$latestWarm = sr_community_home_post_feed($pdo, $boards, $settings, $homeExcerptAllowedByBoardId, 10, 'latest');
$latestWarmMs = sr_measure_community_home_feed_ms($latestWarmStart);

$popularColdStart = microtime(true);
$popularCold = sr_community_home_post_feed($pdo, $boards, $settings, $homeExcerptAllowedByBoardId, 5, 'views');
$popularColdMs = sr_measure_community_home_feed_ms($popularColdStart);
$popularWarmStart = microtime(true);
$popularWarm = sr_community_home_post_feed($pdo, $boards, $settings, $homeExcerptAllowedByBoardId, 5, 'views');
$popularWarmMs = sr_measure_community_home_feed_ms($popularWarmStart);

$dbVersion = (string) $pdo->query('SELECT sqlite_version()')->fetchColumn();

echo "community-home-feed-measurement\n";
echo "fixture-step: sqlite-local\n";
echo "fixture-size: posts=" . (string) $postCount . " boards=" . (string) $boardCount . "\n";
echo "fixture-build-ms: " . (string) $fixtureMs . "\n";
echo "db-version: sqlite " . $dbVersion . "\n";
echo "hosting-probe: sqlite fixture only; run equivalent MySQL/MariaDB EXPLAIN on local/staging before persistent cache table work\n";
echo "query-id: community.home.latest\n";
echo "sql-summary: sr_community_home_post_feed sort=latest public baseline boards\n";
echo "bind-summary: board_ids=" . (string) count($boardIds) . " limit=10\n";
echo "explain:\n";
foreach (sr_measure_community_home_feed_query_plan($pdo, 'latest', $boardIds, 10) as $line) {
    echo "  " . $line . "\n";
}
echo "response-ms-cold: " . (string) $latestColdMs . "\n";
echo "response-ms-warm: " . (string) $latestWarmMs . "\n";
echo "returned-rows: cold=" . (string) count($latestCold) . " warm=" . (string) count($latestWarm) . "\n";
echo "decision: fixture evidence only; does not authorize persistent cache table without target DB measurement\n";
echo "follow-up: run on representative MySQL/MariaDB local or staging data\n";
echo "query-id: community.home.popular\n";
echo "sql-summary: sr_community_home_post_feed sort=views public baseline boards\n";
echo "bind-summary: board_ids=" . (string) count($boardIds) . " limit=5\n";
echo "explain:\n";
foreach (sr_measure_community_home_feed_query_plan($pdo, 'views', $boardIds, 5) as $line) {
    echo "  " . $line . "\n";
}
echo "response-ms-cold: " . (string) $popularColdMs . "\n";
echo "response-ms-warm: " . (string) $popularWarmMs . "\n";
echo "returned-rows: cold=" . (string) count($popularCold) . " warm=" . (string) count($popularWarm) . "\n";
echo "decision: fixture evidence only; does not authorize persistent cache table without target DB measurement\n";
echo "follow-up: run on representative MySQL/MariaDB local or staging data\n";
