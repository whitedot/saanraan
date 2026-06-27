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

$errors = [];

function sr_check_community_feed_cache_contract_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

function sr_check_community_feed_cache_contract_contains(string $path, array $needles): void
{
    global $errors;
    $content = file_get_contents($path);
    if (!is_string($content)) {
        $errors[] = 'cannot read contract source: ' . $path;
        return;
    }

    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = $path . ' must contain feed cache contract marker: ' . $needle;
        }
    }
}

function sr_check_community_feed_cache_contract_home_feed_fixture(): void
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('CREATE TABLE sr_community_posts (
        id INTEGER PRIMARY KEY,
        board_id INTEGER NOT NULL,
        author_account_id INTEGER NULL,
        author_public_name_snapshot TEXT NOT NULL DEFAULT "",
        guest_author_name TEXT NOT NULL DEFAULT "",
        guest_password_hash TEXT NULL,
        guest_ip_hash TEXT NULL,
        guest_user_agent_hash TEXT NULL,
        title TEXT NOT NULL,
        body_text TEXT NOT NULL DEFAULT "",
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
        status TEXT NOT NULL,
        mime_type TEXT NOT NULL,
        storage_driver TEXT NOT NULL DEFAULT "local",
        storage_key TEXT NOT NULL DEFAULT "",
        size_bytes INTEGER NOT NULL DEFAULT 0,
        checksum_sha256 TEXT NOT NULL DEFAULT "",
        width INTEGER NULL,
        height INTEGER NULL
    )');
    $pdo->exec('CREATE TABLE sr_member_accounts (
        id INTEGER PRIMARY KEY,
        status TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_community_feed_cache (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        context_hash TEXT NOT NULL UNIQUE,
        feed_key TEXT NOT NULL,
        sort_key TEXT NOT NULL DEFAULT "latest",
        locale TEXT NOT NULL DEFAULT "ko",
        policy_version TEXT NOT NULL DEFAULT "v1",
        baseline TEXT NOT NULL DEFAULT "",
        board_ids_json TEXT NOT NULL,
        display_count INTEGER NOT NULL DEFAULT 0,
        fetch_count INTEGER NOT NULL DEFAULT 0,
        snapshot_json TEXT NOT NULL,
        snapshot_count INTEGER NOT NULL DEFAULT 0,
        cache_status TEXT NOT NULL DEFAULT "fresh",
        generated_at TEXT NOT NULL,
        expires_at TEXT NOT NULL,
        stale_reason TEXT NOT NULL DEFAULT "",
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');

    foreach ([1, 2, 3] as $accountId) {
        $pdo->prepare('INSERT INTO sr_member_accounts (id, status) VALUES (:id, "active")')->execute(['id' => $accountId]);
    }

    $insertPost = $pdo->prepare(
        'INSERT INTO sr_community_posts
            (id, board_id, author_account_id, title, body_text, status, view_count, created_at, updated_at)
         VALUES
            (:id, :board_id, :author_account_id, :title, "", :status, :view_count, :created_at, :updated_at)'
    );
    foreach ([
        ['id' => 1, 'board_id' => 1, 'view_count' => 50, 'status' => 'published'],
        ['id' => 2, 'board_id' => 1, 'view_count' => 10, 'status' => 'published'],
        ['id' => 3, 'board_id' => 3, 'view_count' => 90, 'status' => 'published'],
        ['id' => 4, 'board_id' => 5, 'view_count' => 80, 'status' => 'published'],
        ['id' => 5, 'board_id' => 1, 'view_count' => 999, 'status' => 'hidden'],
    ] as $row) {
        $insertPost->execute([
            'id' => $row['id'],
            'board_id' => $row['board_id'],
            'author_account_id' => (($row['id'] - 1) % 3) + 1,
            'title' => 'Post ' . (string) $row['id'],
            'status' => $row['status'],
            'view_count' => $row['view_count'],
            'created_at' => '2026-06-24 12:00:00',
            'updated_at' => '2026-06-24 12:00:00',
        ]);
    }

    $boards = [
        ['id' => 1, 'status' => 'enabled', 'read_policy' => 'public', 'effective_read_policy' => 'public'],
        ['id' => 3, 'status' => 'enabled', 'read_policy' => 'member', 'effective_read_policy' => 'member'],
        ['id' => 5, 'status' => 'enabled', 'read_policy' => 'public', 'effective_read_policy' => 'public'],
    ];
    $baselineBoards = sr_community_feed_cache_public_baseline_boards($boards);
    $homeExcerptAllowed = array_fill_keys(sr_community_feed_cache_public_baseline_board_ids($baselineBoards), true);
    $settings = sr_community_default_settings();

    $latest = sr_community_home_post_feed($pdo, $baselineBoards, $settings, $homeExcerptAllowed, 10, 'latest');
    $popular = sr_community_home_post_feed($pdo, $baselineBoards, $settings, $homeExcerptAllowed, 10, 'views');
    $latestCached = sr_community_home_post_feed($pdo, $baselineBoards, $settings, $homeExcerptAllowed, 10, 'latest');

    sr_check_community_feed_cache_contract_assert(
        array_map(static fn (array $post): int => (int) $post['id'], $latest) === [4, 2, 1],
        'home latest feed fixture must use public baseline boards and published id desc order.'
    );
    sr_check_community_feed_cache_contract_assert(
        array_map(static fn (array $post): int => (int) $post['id'], $popular) === [4, 1, 2],
        'home popular feed fixture must use public baseline boards and snapshot view_count order.'
    );
    sr_check_community_feed_cache_contract_assert(
        array_map(static fn (array $post): int => (int) $post['id'], $latestCached) === [4, 2, 1],
        'home latest feed fixture must hydrate posts from persistent cache snapshots.'
    );
    sr_check_community_feed_cache_contract_assert(
        (int) $pdo->query('SELECT COUNT(*) FROM sr_community_feed_cache WHERE cache_status = "fresh"')->fetchColumn() === 2,
        'home feed fixture must create persistent cache rows for latest and popular feeds.'
    );
    sr_check_community_feed_cache_contract_assert(
        (string) $pdo->query('SELECT snapshot_json FROM sr_community_feed_cache WHERE feed_key = "community.home.latest" LIMIT 1')->fetchColumn() !== '',
        'home feed cache row must store snapshot JSON.'
    );
}

$boards = [
    ['id' => 3, 'status' => 'enabled', 'read_policy' => 'member', 'effective_read_policy' => 'member'],
    ['id' => 1, 'status' => 'enabled', 'read_policy' => 'public', 'effective_read_policy' => 'public'],
    ['id' => 2, 'status' => 'disabled', 'read_policy' => 'public', 'effective_read_policy' => 'public'],
    ['id' => 4, 'status' => 'enabled', 'read_policy' => 'group', 'effective_read_policy' => 'group'],
    ['id' => 5, 'status' => 'enabled', 'read_policy' => 'public', 'effective_read_policy' => 'public'],
];

sr_check_community_feed_cache_contract_assert(
    sr_community_feed_cache_public_baseline_board_ids($boards) === [1, 5],
    'v1 baseline must include only enabled public discoverable board ids in stable order.'
);

$contextA = sr_community_feed_cache_context([
    'feed_key' => 'community.home.latest',
    'board_ids' => [5, 1, 5, 0, -2],
    'sort' => 'latest',
    'fetch_count' => 40,
    'display_count' => 10,
    'locale' => 'ko_KR',
    'policy_version' => 'home-v1',
]);
$contextB = sr_community_feed_cache_context([
    'feed_key' => 'community.home.latest',
    'board_ids' => [1, 5],
    'sort' => 'latest',
    'fetch_count' => 40,
    'display_count' => 10,
    'locale' => 'ko-kr',
    'policy_version' => 'home-v1',
]);

sr_check_community_feed_cache_contract_assert(!array_key_exists('viewer_class', $contextA), 'v1 context must not include viewer class/tier.');
sr_check_community_feed_cache_contract_assert(($contextA['baseline'] ?? '') === 'everyone_discoverable_public_boards', 'v1 context must mark everyone-discoverable public baseline.');
sr_check_community_feed_cache_contract_assert(($contextA['board_ids'] ?? []) === [1, 5], 'v1 context must normalize board id scope as a set.');
sr_check_community_feed_cache_contract_assert($contextA === $contextB, 'equivalent v1 contexts must normalize identically.');
sr_check_community_feed_cache_contract_assert(
    sr_community_feed_cache_context_hash($contextA) === sr_community_feed_cache_context_hash($contextB),
    'equivalent v1 contexts must hash identically.'
);

$snapshot = sr_community_feed_cache_card_snapshot([
    'id' => 77,
    'board_id' => 5,
    'title' => "  Hello\nWorld  ",
    'author_account_id' => 9,
    'author_label_snapshot' => 'Do Not Store',
    'author_public_name_snapshot' => 'Do Not Store',
    'published_comment_count' => 100,
    'view_count' => 123,
    'thumbnail_url' => '/bad/cache.jpg',
    'list_image_attachment_id' => 44,
    'list_image_checksum_sha256' => str_repeat('a', 64),
    'list_image_width' => 640,
    'list_image_height' => 360,
    'is_secret' => 1,
    'body_text' => 'full body must not be serialized',
    'html' => '<p>bad</p>',
    'csrf_token' => 'csrf',
    'can_read' => true,
    'paid_access_state' => 'owned',
    'created_at' => '2026-06-24 12:00:00',
    'updated_at' => '2026-06-24 12:01:00',
]);

sr_check_community_feed_cache_contract_assert(($snapshot['post_id'] ?? null) === 77, 'card snapshot must store post id.');
sr_check_community_feed_cache_contract_assert(($snapshot['author_account_id'] ?? null) === 9, 'card snapshot must store author account id for render-time label resolve.');
sr_check_community_feed_cache_contract_assert(($snapshot['view_count'] ?? null) === 123, 'card snapshot must store view count for both ordering and display.');
sr_check_community_feed_cache_contract_assert(($snapshot['thumbnail_source']['attachment_id'] ?? null) === 44, 'card snapshot must store thumbnail source marker instead of rendered URL.');
sr_check_community_feed_cache_contract_assert(!array_key_exists('author_label_snapshot', $snapshot), 'card snapshot must not store author label snapshot.');
sr_check_community_feed_cache_contract_assert(!array_key_exists('published_comment_count', $snapshot), 'card snapshot must not store comment count before #360 source is adopted.');
sr_check_community_feed_cache_contract_assert(!array_key_exists('thumbnail_url', $snapshot), 'card snapshot must not store rendered thumbnail URL.');
sr_check_community_feed_cache_contract_assert(!sr_community_feed_cache_snapshot_contains_forbidden_key($snapshot), 'card snapshot must not contain forbidden fields.');

$json = sr_community_feed_cache_card_snapshot_json([
    'id' => 88,
    'board_id' => 5,
    'title' => 'Json Snapshot',
    'author_account_id' => 2,
    'body_text' => 'full body must not be serialized',
    'csrf_token' => 'bad',
]);
sr_check_community_feed_cache_contract_assert(!str_contains($json, 'body_text'), 'snapshot JSON must not contain body_text key.');
sr_check_community_feed_cache_contract_assert(!str_contains($json, 'csrf_token'), 'snapshot JSON must not contain csrf token key.');

sr_check_community_feed_cache_contract_home_feed_fixture();

sr_check_community_feed_cache_contract_contains('modules/community/helpers/feed-cache.php', [
    "'baseline' => 'everyone_discoverable_public_boards'",
    'function sr_community_feed_cache_table_exists',
    'function sr_community_feed_cache_read',
    'function sr_community_feed_cache_write',
    'function sr_community_feed_cache_mark_all_stale',
    'function sr_community_feed_cache_post_feed_query',
    'function sr_community_feed_cache_persistent_store_status',
    'function sr_community_feed_cache_admin_board_rows',
    'function sr_community_feed_cache_admin_context_rows',
    'SELECT p0.id',
    'INNER JOIN sr_community_posts p ON p.id = picked.id',
    'SELECT MIN(att_img.id)',
    'author_account_id',
    'published_comment_count',
    'sr_community_feed_cache_snapshot_forbidden_keys',
]);

sr_check_community_feed_cache_contract_contains('modules/community/install.sql', [
    'CREATE TABLE IF NOT EXISTS sr_community_feed_cache',
    'uq_sr_community_feed_cache_context (context_hash)',
    'idx_sr_community_posts_status_view_id (status, view_count, id)',
]);

sr_check_community_feed_cache_contract_contains('modules/community/updates/2026.06.035.sql', [
    'idx_sr_community_posts_status_view_id',
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_posts ADD KEY idx_sr_community_posts_status_view_id (status, view_count, id)',
]);

sr_check_community_feed_cache_contract_contains('modules/community/updates/2026.06.037.sql', [
    'CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}community_feed_cache',
    'uq_sr_community_feed_cache_context (context_hash)',
]);

sr_check_community_feed_cache_contract_contains('modules/community/helpers/presentation.php', [
    'sr_community_feed_cache_post_feed_query($pdo',
    'sr_community_feed_cache_read($pdo',
    'sr_community_feed_cache_write($pdo',
]);

sr_check_community_feed_cache_contract_contains('modules/community/paths.php', [
    "'GET /admin/community/feed-cache' => 'actions/admin-feed-cache.php'",
]);

sr_check_community_feed_cache_contract_contains('modules/community/admin-menu.php', [
    "'label' => '최신글 캐시 관리'",
    "'path' => '/admin/community/feed-cache'",
]);

sr_check_community_feed_cache_contract_contains('modules/community/actions/admin-feed-cache.php', [
    'sr_admin_require_permission($pdo, (int) $account[\'id\'], \'/admin/community/feed-cache\', \'view\')',
    'sr_community_feed_cache_public_baseline_board_ids($boards)',
    'sr_community_feed_cache_admin_context_rows($baselineBoardIds)',
]);

sr_check_community_feed_cache_contract_contains('modules/community/views/admin-feed-cache.php', [
    '$adminPageTitle = \'최신글 캐시 관리\'',
    '공개 baseline',
    '컨텍스트 해시',
    'DB 영속 캐시 사용',
]);

sr_check_community_feed_cache_contract_contains('.tools/bin/measure-community-home-feed.php', [
    'sr_community_feed_cache_post_feed_query($pdo',
]);

sr_check_community_feed_cache_contract_contains('docs/records/milestone-32-community-query-measurement-plan-2026-06-24.md', [
    'EXPLAIN',
    'response-ms-cold',
    'response-ms-warm',
]);

if ($errors !== []) {
    fwrite(STDERR, "community feed cache contract checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "community feed cache contract checks completed.\n";
