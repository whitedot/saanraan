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
        extra_values_json TEXT NULL,
        title TEXT NOT NULL,
        body_text TEXT NOT NULL DEFAULT "",
        body_format TEXT NOT NULL DEFAULT "plain",
        reaction_preset_key TEXT NOT NULL DEFAULT "",
        reaction_comment_preset_key TEXT NOT NULL DEFAULT "",
        is_secret INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL,
        hidden_at TEXT NULL,
        hidden_until TEXT NULL,
        hidden_reason TEXT NOT NULL DEFAULT "",
        hidden_note TEXT NULL,
        hidden_by_account_id INTEGER NULL,
        hidden_before_status TEXT NOT NULL DEFAULT "",
        summary_feed_candidate INTEGER NOT NULL DEFAULT 1,
        view_count INTEGER NOT NULL DEFAULT 0,
        last_commented_at TEXT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_community_comments (
        id INTEGER PRIMARY KEY,
        post_id INTEGER NOT NULL,
        author_account_id INTEGER NULL,
        author_public_name_snapshot TEXT NOT NULL DEFAULT "",
        guest_author_name TEXT NOT NULL DEFAULT "",
        guest_password_hash TEXT NULL,
        guest_ip_hash TEXT NULL,
        guest_user_agent_hash TEXT NULL,
        body_text TEXT NOT NULL DEFAULT "",
        is_secret INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL,
        hidden_at TEXT NULL,
        hidden_until TEXT NULL,
        hidden_reason TEXT NOT NULL DEFAULT "",
        hidden_note TEXT NULL,
        hidden_by_account_id INTEGER NULL,
        hidden_before_status TEXT NOT NULL DEFAULT "",
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
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
    $pdo->exec('CREATE TABLE sr_community_boards (
        id INTEGER PRIMARY KEY,
        board_group_id INTEGER NULL,
        board_key TEXT NOT NULL DEFAULT "",
        title TEXT NOT NULL DEFAULT "",
        status TEXT NOT NULL DEFAULT "enabled"
    )');
    $pdo->exec('CREATE TABLE sr_community_board_settings (
        board_id INTEGER NOT NULL,
        setting_key TEXT NOT NULL,
        setting_value TEXT NOT NULL,
        value_type TEXT NOT NULL DEFAULT "string",
        created_at TEXT NOT NULL DEFAULT "",
        updated_at TEXT NOT NULL DEFAULT ""
    )');
    $pdo->exec('CREATE TABLE sr_member_accounts (
        id INTEGER PRIMARY KEY,
        status TEXT NOT NULL
    )');
    foreach ([1, 2, 3] as $accountId) {
        $pdo->prepare('INSERT INTO sr_member_accounts (id, status) VALUES (:id, "active")')->execute(['id' => $accountId]);
    }
    $insertBoard = $pdo->prepare(
        'INSERT INTO sr_community_boards (id, board_group_id, board_key, title, status)
         VALUES (:id, :board_group_id, :board_key, :title, "enabled")'
    );
    foreach ([
        ['id' => 1, 'board_group_id' => 0],
        ['id' => 3, 'board_group_id' => 0],
        ['id' => 5, 'board_group_id' => 0],
        ['id' => 82, 'board_group_id' => 0],
    ] as $row) {
        $insertBoard->execute([
            'id' => $row['id'],
            'board_group_id' => $row['board_group_id'],
            'board_key' => 'board_' . (string) $row['id'],
            'title' => 'Board ' . (string) $row['id'],
        ]);
    }

    $insertPost = $pdo->prepare(
        'INSERT INTO sr_community_posts
            (id, board_id, author_account_id, title, body_text, status, summary_feed_candidate, view_count, created_at, updated_at)
         VALUES
            (:id, :board_id, :author_account_id, :title, "", :status, :summary_feed_candidate, :view_count, :created_at, :updated_at)'
    );
    foreach ([
        ['id' => 1, 'board_id' => 1, 'view_count' => 50, 'status' => 'published'],
        ['id' => 2, 'board_id' => 1, 'view_count' => 10, 'status' => 'published'],
        ['id' => 3, 'board_id' => 3, 'view_count' => 90, 'status' => 'published'],
        ['id' => 4, 'board_id' => 5, 'view_count' => 80, 'status' => 'published', 'summary_feed_candidate' => 0],
        ['id' => 5, 'board_id' => 1, 'view_count' => 999, 'status' => 'hidden'],
        ['id' => 40011, 'board_id' => 82, 'view_count' => 1000, 'status' => 'published', 'summary_feed_candidate' => 0],
    ] as $row) {
        $insertPost->execute([
            'id' => $row['id'],
            'board_id' => $row['board_id'],
            'author_account_id' => (($row['id'] - 1) % 3) + 1,
            'title' => 'Post ' . (string) $row['id'],
            'status' => $row['status'],
            'summary_feed_candidate' => (int) ($row['summary_feed_candidate'] ?? 1),
            'view_count' => $row['view_count'],
            'created_at' => '2026-06-24 12:00:00',
            'updated_at' => '2026-06-24 12:00:00',
        ]);
    }
    $pdo->prepare(
        'INSERT INTO sr_community_board_settings (board_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES (:board_id, "summary_feed_enabled", "0", "bool", "2026-06-24 12:00:00", "2026-06-24 12:00:00")'
    )->execute(['board_id' => 5]);
    $insertComment = $pdo->prepare(
        'INSERT INTO sr_community_comments
            (id, post_id, author_account_id, author_public_name_snapshot, guest_author_name, body_text, is_secret, status, created_at, updated_at)
         VALUES
            (:id, :post_id, :author_account_id, :author_public_name_snapshot, "", :body_text, 0, :status, :created_at, :updated_at)'
    );
    foreach ([
        ['id' => 10, 'post_id' => 1, 'body_text' => '첫 번째 댓글', 'status' => 'published'],
        ['id' => 11, 'post_id' => 2, 'body_text' => '두 번째 댓글', 'status' => 'published'],
        ['id' => 12, 'post_id' => 4, 'body_text' => '피드 제외 댓글', 'status' => 'published'],
        ['id' => 13, 'post_id' => 5, 'body_text' => '숨김 글 댓글', 'status' => 'published'],
        ['id' => 14, 'post_id' => 1, 'body_text' => '숨김 댓글', 'status' => 'hidden'],
    ] as $row) {
        $insertComment->execute([
            'id' => $row['id'],
            'post_id' => $row['post_id'],
            'author_account_id' => (($row['id'] - 1) % 3) + 1,
            'author_public_name_snapshot' => 'Commenter ' . (string) $row['id'],
            'body_text' => $row['body_text'],
            'status' => $row['status'],
            'created_at' => '2026-06-24 12:' . str_pad((string) $row['id'], 2, '0', STR_PAD_LEFT) . ':00',
            'updated_at' => '2026-06-24 12:' . str_pad((string) $row['id'], 2, '0', STR_PAD_LEFT) . ':00',
        ]);
    }

    $boards = [
        ['id' => 1, 'status' => 'enabled', 'read_policy' => 'public', 'effective_read_policy' => 'public'],
        ['id' => 3, 'status' => 'enabled', 'read_policy' => 'member', 'effective_read_policy' => 'member'],
        ['id' => 5, 'status' => 'enabled', 'read_policy' => 'public', 'effective_read_policy' => 'public'],
        ['id' => 82, 'status' => 'enabled', 'read_policy' => 'public', 'effective_read_policy' => 'public'],
    ];
    $baselineBoards = sr_community_feed_cache_public_baseline_boards($boards);
    $homeExcerptAllowed = array_fill_keys(sr_community_feed_cache_public_baseline_board_ids($baselineBoards), true);
    $settings = sr_community_default_settings();
    sr_check_community_feed_cache_contract_assert(
        sr_community_home_public_feed_cache_board_ids($boards, $homeExcerptAllowed) === [1, 5, 82],
        'home public feed cache board ids must exclude member boards and boards without excerpt permission.'
    );

    sr_community_feed_cache_mark_all_stale($pdo, 'fixture_reset');
    sr_community_home_warm_public_feed_cache($pdo, $boards, $settings, $homeExcerptAllowed);
    $mixedStoreStatus = sr_community_feed_cache_persistent_store_status($pdo);
    sr_check_community_feed_cache_contract_assert((int) ($mixedStoreStatus['active_count'] ?? 0) >= 3, 'mixed readable home chrome must still warm public baseline feed cache files.');
    $latest = sr_community_home_post_feed($pdo, $baselineBoards, $settings, $homeExcerptAllowed, 10, 'latest');
    $popular = sr_community_home_post_feed($pdo, $baselineBoards, $settings, $homeExcerptAllowed, 10, 'views');
    $latestCached = sr_community_home_post_feed($pdo, $baselineBoards, $settings, $homeExcerptAllowed, 10, 'latest');
    $latestComments = sr_community_home_latest_comments($pdo, sr_community_feed_cache_public_baseline_board_ids($baselineBoards), $homeExcerptAllowed, 10, true);
    $latestCommentsCached = sr_community_home_latest_comments($pdo, sr_community_feed_cache_public_baseline_board_ids($baselineBoards), $homeExcerptAllowed, 10, true);
    $s3BodyImageUrl = sr_community_home_post_image_url($pdo, [
        'id' => 9001,
        'body_format' => 'html',
        'body_text' => '<p><img src="/community/body-file?post_id=9001&amp;file=body.webp&amp;d=s3"></p>',
        'is_secret' => 0,
    ], ['id' => 1, 'read_policy' => 'public', 'effective_read_policy' => 'public'], $settings, true);

    sr_check_community_feed_cache_contract_assert(
        array_map(static fn (array $post): int => (int) $post['id'], $latest) === [2, 1],
        'home latest feed fixture must exclude boards with summary_feed_enabled disabled.'
    );
    sr_check_community_feed_cache_contract_assert(
        array_map(static fn (array $post): int => (int) $post['id'], $popular) === [1, 2],
        'home popular feed fixture must exclude boards with summary_feed_enabled disabled.'
    );
    sr_check_community_feed_cache_contract_assert(
        array_map(static fn (array $post): int => (int) $post['id'], $latestCached) === [2, 1],
        'home latest feed fixture must render filtered posts from persistent cache snapshots and keep post 40011 out.'
    );
    sr_check_community_feed_cache_contract_assert(
        array_map(static fn (array $comment): int => (int) $comment['id'], $latestComments) === [11, 10],
        'home latest comments fixture must exclude hidden comments, hidden posts, and boards with summary_feed_enabled disabled.'
    );
    sr_check_community_feed_cache_contract_assert(
        array_map(static fn (array $comment): int => (int) $comment['id'], $latestCommentsCached) === [11, 10],
        'home latest comments fixture must render from file cache snapshots.'
    );
    sr_check_community_feed_cache_contract_assert(
        str_contains($s3BodyImageUrl, '/community/body-file?post_id=9001') && str_contains($s3BodyImageUrl, 'd=s3'),
        'home body image fallback must preserve the community body-file storage driver query.'
    );
    $storeStatus = sr_community_feed_cache_persistent_store_status($pdo);
    sr_check_community_feed_cache_contract_assert((string) ($storeStatus['mode'] ?? '') === 'file_persistent', 'admin store status must report file persistent cache mode.');
    sr_check_community_feed_cache_contract_assert((int) ($storeStatus['row_count'] ?? 0) >= 3, 'admin store status must count home feed cache files.');
    sr_check_community_feed_cache_contract_assert((int) ($storeStatus['active_count'] ?? 0) >= 3, 'admin store status must count currently reusable home feed cache files.');
    sr_check_community_feed_cache_contract_assert((int) ($storeStatus['fresh_count'] ?? 0) >= 3, 'admin store status must count fresh home feed cache files.');
    sr_check_community_feed_cache_contract_assert((string) ($storeStatus['latest_generated_at'] ?? '') !== '', 'admin store status must expose latest generated time.');
    sr_check_community_feed_cache_contract_assert(
        in_array('community.home.latest_comments', array_map(static fn (array $row): string => (string) ($row['feed_key'] ?? ''), sr_community_feed_cache_admin_context_rows($pdo)), true),
        'admin context rows must include latest comments file cache.'
    );
    sr_check_community_feed_cache_contract_assert(sr_community_feed_cache_refresh_seconds('community.home.popular') === 300, 'popular feed cache refresh policy must be hard-coded to five minutes.');
    sr_check_community_feed_cache_contract_assert(sr_community_feed_cache_refresh_seconds('community.home.latest') === 0, 'latest feed cache refresh policy must be event-driven.');
    sr_check_community_feed_cache_contract_assert(sr_community_feed_cache_refresh_seconds('community.home.latest_comments') === 0, 'latest comments feed cache refresh policy must be event-driven.');
    sr_check_community_feed_cache_contract_assert(sr_community_feed_cache_refresh_policy_label('community.home.latest') === '변경 시 갱신', 'latest feed cache admin policy label must describe event-driven refresh.');
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
    'list_image_storage_key' => 'community/44/source.jpg',
    'list_image_mime_type' => 'image/jpeg',
    'list_image_size_bytes' => 2048,
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
sr_check_community_feed_cache_contract_assert(($snapshot['comment_count'] ?? null) === 100, 'card snapshot may store public comment count for cached home card rendering.');
sr_check_community_feed_cache_contract_assert(($snapshot['thumbnail_source']['attachment_id'] ?? null) === 44, 'card snapshot must store thumbnail source marker instead of rendered URL.');
sr_check_community_feed_cache_contract_assert(($snapshot['thumbnail_source']['storage_key'] ?? '') !== '', 'card snapshot must store thumbnail source key so cache hits can avoid attachment relookup.');
sr_check_community_feed_cache_contract_assert(!array_key_exists('author_label_snapshot', $snapshot), 'card snapshot must not store author label snapshot.');
sr_check_community_feed_cache_contract_assert(!array_key_exists('published_comment_count', $snapshot), 'card snapshot must not store source-specific comment count key.');
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

$commentSnapshotJson = json_encode([[
    'snapshot_schema_version' => 'community_home_comment_snapshot_v1',
    'id' => 9,
    'post_id' => 88,
    'board_id' => 5,
    'body_excerpt' => '댓글 요약',
]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
sr_check_community_feed_cache_contract_assert(
    count(sr_community_feed_cache_snapshots_from_json(is_string($commentSnapshotJson) ? $commentSnapshotJson : '[]', ['community_home_comment_snapshot_v1'])) === 1,
    'latest comment cache snapshots must be readable with their own schema version.'
);

$memoryContext = sr_community_feed_cache_context([
    'feed_key' => 'community.home.latest',
    'board_ids' => [1],
    'sort' => 'latest',
    'display_count' => 1,
    'fetch_count' => 1,
    'locale' => 'ko',
    'policy_version' => 'memory-fixture-v1',
]);
$memoryHash = sr_community_feed_cache_context_hash($memoryContext);
$GLOBALS['sr_community_feed_cache_memory_records'] = [];
sr_community_feed_cache_remember_record($memoryHash, sr_community_feed_cache_file_record($memoryContext, [[
    'snapshot_schema_version' => 'community_feed_card_snapshot_v1',
    'post_id' => 99,
    'board_id' => 1,
]], sr_now()));
sr_check_community_feed_cache_contract_assert(
    count(sr_community_feed_cache_read(new PDO('sqlite::memory:'), $memoryContext)) === 1,
    'feed cache read must reuse same-request memory records before falling back to file storage.'
);
sr_community_feed_cache_mark_all_stale(new PDO('sqlite::memory:'), 'memory_fixture_reset');
sr_check_community_feed_cache_contract_assert(
    sr_community_feed_cache_read(new PDO('sqlite::memory:'), $memoryContext) === null,
    'feed cache stale invalidation must clear same-request memory records as well as file records.'
);

sr_check_community_feed_cache_contract_home_feed_fixture();

sr_check_community_feed_cache_contract_contains('modules/community/helpers/feed-cache.php', [
    "'baseline' => 'everyone_discoverable_public_boards'",
    "effective_summary_feed_enabled",
    'function sr_community_summary_feed_enabled_sql_condition',
    'function sr_community_summary_post_candidate_sql_condition',
    'summary-feed-candidate-v1',
    'summary_feed_candidate = 1',
    'function sr_community_feed_cache_read',
    'function sr_community_feed_cache_file_root',
    'function sr_community_feed_cache_file_path',
    'community_feed_file_cache_v1',
    'function sr_community_feed_cache_memory_record',
    'function sr_community_feed_cache_remember_record',
    'function sr_community_feed_cache_clear_memory_records',
    'function sr_community_feed_cache_write',
    'function sr_community_feed_cache_write_snapshots',
    'function sr_community_feed_cache_mark_all_stale',
    'function sr_community_feed_cache_post_feed_query',
    'function sr_community_feed_cache_persistent_store_status',
    'function sr_community_feed_cache_admin_board_rows',
    'function sr_community_feed_cache_admin_context_rows',
    'latest_generated_at',
    'expired_count',
    'SELECT p0.id',
    'INNER JOIN sr_community_posts p ON p.id = picked.id',
    'SELECT MIN(att_img.id)',
    'author_account_id',
    'published_comment_count',
    'sr_community_feed_cache_snapshot_forbidden_keys',
]);

sr_check_community_feed_cache_contract_contains('modules/community/install.sql', [
    'idx_sr_community_posts_status_view_id (status, view_count, id)',
    'summary_feed_candidate TINYINT(1) NOT NULL DEFAULT 1',
    'idx_sr_community_posts_summary_status_id (summary_feed_candidate, status, id)',
]);

sr_check_community_feed_cache_contract_contains('modules/community/updates/2026.06.035.sql', [
    'idx_sr_community_posts_status_view_id',
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_posts ADD KEY idx_sr_community_posts_status_view_id (status, view_count, id)',
]);

sr_check_community_feed_cache_contract_contains('modules/community/updates/2026.06.038.sql', [
    'ADD COLUMN summary_feed_candidate TINYINT(1) NOT NULL DEFAULT 1',
    'idx_sr_community_posts_summary_status_id',
    'idx_sr_community_posts_summary_status_view_id',
    'UPDATE {{SR_TABLE_PREFIX}}community_posts p',
    "home_setting.setting_key = 'summary_feed_enabled'",
]);

sr_check_community_feed_cache_contract_contains('modules/community/updates/2026.06.039.sql', [
    'CHANGE COLUMN home_feed_candidate summary_feed_candidate',
    "legacy_setting.setting_key = 'home_feed_enabled'",
    "summary_setting.setting_key = 'summary_feed_enabled'",
    'idx_sr_community_posts_summary_status_id',
    "SET version = '2026.06.039'",
]);

sr_check_community_feed_cache_contract_contains('modules/community/updates/2026.06.040.sql', [
    'DROP TABLE IF EXISTS {{SR_TABLE_PREFIX}}community_feed_cache',
    "SET version = '2026.06.040'",
]);

sr_check_community_feed_cache_contract_contains('modules/community/helpers/presentation.php', [
    '$summaryFeedBoards = []',
    'sr_community_effective_board_summary_feed_enabled($pdo, $board)',
    'sr_community_home_latest_comment_rows_from_snapshots($cachedSnapshots, $readableBoardIds)',
    'function sr_community_home_warm_public_feed_cache',
    'function sr_community_home_public_feed_cache_board_ids',
    '$latestCommentsUsePublicCache = $readableBoardIds !== [] && $readableBoardIds === $publicFeedCacheBoardIds',
    'sr_community_home_warm_public_feed_cache($pdo, $summaryFeedBoards, $settings, $homeExcerptAllowedByBoardId)',
    'function sr_community_home_filter_rows_by_board_ids',
    '$latestPosts = sr_community_home_filter_rows_by_board_ids($latestPosts, $readableBoardIds)',
    '$recentSeries = sr_community_home_filter_rows_by_board_ids($recentSeries, $readableBoardIds)',
    'sr_community_feed_cache_post_feed_query($pdo',
    'sr_community_feed_cache_read($pdo',
    'sr_community_feed_cache_write($pdo',
    'function sr_community_home_latest_comments',
    "'feed_key' => 'community.home.latest_comments'",
    "'policy_version' => 'summary-feed-candidate-v1'",
    'sr_community_feed_cache_write_snapshots(',
]);

sr_check_community_feed_cache_contract_contains('modules/community/theme/basic/home-frame-start.php', [
    '$communityFrameHomeBoardIds = array_map(\'intval\', array_keys($homeExcerptAllowedByBoardId));',
    '$latestPosts = isset($latestPosts) && is_array($latestPosts) ? sr_community_home_filter_rows_by_board_ids($latestPosts, $communityFrameHomeBoardIds) : [];',
    '$popularPosts = isset($popularPosts) && is_array($popularPosts) ? sr_community_home_filter_rows_by_board_ids($popularPosts, $communityFrameHomeBoardIds) : [];',
    '$latestComments = isset($latestComments) && is_array($latestComments) ? sr_community_home_filter_rows_by_board_ids($latestComments, $communityFrameHomeBoardIds) : [];',
    '$recentSeries = isset($recentSeries) && is_array($recentSeries) ? sr_community_home_filter_rows_by_board_ids($recentSeries, $communityFrameHomeBoardIds) : [];',
]);

sr_check_community_feed_cache_contract_contains('modules/community/paths.php', [
    "'GET /admin/community/feed-cache' => 'actions/admin-feed-cache.php'",
]);

sr_check_community_feed_cache_contract_contains('modules/community/admin-menu.php', [
    "'label' => '피드 캐시'",
    "'path' => '/admin/community/feed-cache'",
]);

sr_check_community_feed_cache_contract_contains('modules/community/actions/admin-feed-cache.php', [
    'sr_admin_require_permission($pdo, (int) $account[\'id\'], \'/admin/community/feed-cache\', \'view\')',
    'sr_community_feed_cache_admin_context_rows($pdo)',
]);

sr_check_community_feed_cache_contract_contains('modules/community/views/admin-feed-cache.php', [
    '$adminPageTitle = \'피드 캐시\'',
    '현재 유효한 컨텍스트',
    '파일 영속 캐시 사용',
    '현재 유효',
    '갱신 대기',
    '갱신 정책',
    '변경 시 갱신',
    '마지막 생성',
]);

sr_check_community_feed_cache_contract_contains('modules/community/views/admin-boards.php', [
    '커뮤니티 피드 노출',
    'name="summary_feed_enabled"',
    '게시판 목록 밖에서 모아 보여 주는 피드 후보',
]);

sr_check_community_feed_cache_contract_contains('modules/community/helpers/admin-boards.php', [
    'sr_community_feed_cache_mark_all_stale($pdo, \'board_settings_changed\')',
]);

sr_check_community_feed_cache_contract_contains('docs/performance-policy.md', [
    '커뮤니티 피드 노출',
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
