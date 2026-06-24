#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once $root . '/core/helpers.php';

function sr_community_feed_fixture_usage(): void
{
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  SR_COMMUNITY_FEED_FIXTURE_ALLOW_MUTATION=1 php .tools/bin/seed-community-feed-fixture.php seed [run_key] [posts] [boards]\n");
    fwrite(STDERR, "  SR_COMMUNITY_FEED_FIXTURE_ALLOW_MUTATION=1 php .tools/bin/seed-community-feed-fixture.php cleanup [run_key]\n");
    fwrite(STDERR, "\n");
    fwrite(STDERR, "Env: SR_COMMUNITY_FEED_FIXTURE_RUN_KEY SR_COMMUNITY_FEED_FIXTURE_POSTS SR_COMMUNITY_FEED_FIXTURE_BOARDS SR_COMMUNITY_FEED_FIXTURE_BATCH SR_COMMUNITY_FEED_FIXTURE_REPLACE\n");
}

function sr_community_feed_fixture_bool_env(string $key): bool
{
    $value = getenv($key);

    return is_string($value) && in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

function sr_community_feed_fixture_int_env(string $key, int $default, int $min, int $max): int
{
    $value = getenv($key);
    if (!is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
        return $default;
    }

    return max($min, min($max, (int) $value));
}

function sr_community_feed_fixture_run_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_]/', '_', $value) ?? '';
    $value = preg_replace('/_+/', '_', $value) ?? '';
    $value = trim($value, '_');
    if ($value === '') {
        $value = 'sr369_' . date('ymdhi');
    }
    if (preg_match('/\A[a-z]/', $value) !== 1) {
        $value = 'sr369_' . $value;
    }

    return substr($value, 0, 24);
}

function sr_community_feed_fixture_count(PDO $pdo, string $table, string $whereSql, array $params): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $whereSql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function sr_community_feed_fixture_ids(PDO $pdo, string $table, string $whereSql, array $params): array
{
    $stmt = $pdo->prepare('SELECT id FROM ' . $table . ' WHERE ' . $whereSql . ' ORDER BY id ASC');
    $stmt->execute($params);

    return array_map('intval', array_column($stmt->fetchAll(), 'id'));
}

function sr_community_feed_fixture_placeholders(array $ids, string $prefix): array
{
    $placeholders = [];
    $params = [];
    foreach (array_values($ids) as $index => $id) {
        $key = $prefix . (string) $index;
        $placeholders[] = ':' . $key;
        $params[$key] = (int) $id;
    }

    return [$placeholders, $params];
}

function sr_community_feed_fixture_delete_by_ids(PDO $pdo, string $table, string $column, array $ids): int
{
    $deleted = 0;
    foreach (array_chunk($ids, 500) as $chunkIndex => $chunk) {
        [$placeholders, $params] = sr_community_feed_fixture_placeholders($chunk, 'id_' . (string) $chunkIndex . '_');
        if ($placeholders === []) {
            continue;
        }
        $stmt = $pdo->prepare('DELETE FROM ' . $table . ' WHERE ' . $column . ' IN (' . implode(', ', $placeholders) . ')');
        $stmt->execute($params);
        $deleted += $stmt->rowCount();
    }

    return $deleted;
}

function sr_community_feed_fixture_cleanup(PDO $pdo, string $runKey): array
{
    $boardIds = sr_community_feed_fixture_ids($pdo, 'sr_community_boards', 'board_key LIKE :prefix', ['prefix' => $runKey . '_b%']);
    $postIds = [];
    if ($boardIds !== []) {
        foreach (array_chunk($boardIds, 500) as $chunkIndex => $chunk) {
            [$placeholders, $params] = sr_community_feed_fixture_placeholders($chunk, 'board_' . (string) $chunkIndex . '_');
            $stmt = $pdo->prepare('SELECT id FROM sr_community_posts WHERE board_id IN (' . implode(', ', $placeholders) . ')');
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $row) {
                $postId = (int) ($row['id'] ?? 0);
                if ($postId > 0) {
                    $postIds[$postId] = $postId;
                }
            }
        }
    }

    $pdo->beginTransaction();
    try {
        $deletedAttachments = sr_community_feed_fixture_delete_by_ids($pdo, 'sr_community_attachments', 'post_id', array_values($postIds));
        $deletedComments = sr_community_feed_fixture_delete_by_ids($pdo, 'sr_community_comments', 'post_id', array_values($postIds));
        $deletedPosts = sr_community_feed_fixture_delete_by_ids($pdo, 'sr_community_posts', 'id', array_values($postIds));
        $deletedBoardSettings = sr_community_feed_fixture_delete_by_ids($pdo, 'sr_community_board_settings', 'board_id', $boardIds);
        $deletedBoards = sr_community_feed_fixture_delete_by_ids($pdo, 'sr_community_boards', 'id', $boardIds);

        $groupIds = sr_community_feed_fixture_ids($pdo, 'sr_community_board_groups', 'group_key = :group_key', ['group_key' => $runKey . '_group']);
        $deletedGroupSettings = sr_community_feed_fixture_delete_by_ids($pdo, 'sr_community_board_group_settings', 'group_id', $groupIds);
        $deletedGroups = sr_community_feed_fixture_delete_by_ids($pdo, 'sr_community_board_groups', 'id', $groupIds);
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    return [
        'attachments' => $deletedAttachments,
        'comments' => $deletedComments,
        'posts' => $deletedPosts,
        'board_settings' => $deletedBoardSettings,
        'boards' => $deletedBoards,
        'board_group_settings' => $deletedGroupSettings,
        'board_groups' => $deletedGroups,
    ];
}

function sr_community_feed_fixture_seed(PDO $pdo, string $runKey, int $postCount, int $boardCount, int $batchSize): array
{
    $existingBoards = sr_community_feed_fixture_count($pdo, 'sr_community_boards', 'board_key LIKE :prefix', ['prefix' => $runKey . '_b%']);
    $existingGroups = sr_community_feed_fixture_count($pdo, 'sr_community_board_groups', 'group_key = :group_key', ['group_key' => $runKey . '_group']);
    if ($existingBoards > 0 || $existingGroups > 0) {
        if (!sr_community_feed_fixture_bool_env('SR_COMMUNITY_FEED_FIXTURE_REPLACE')) {
            throw new RuntimeException('Fixture run_key already exists. Run cleanup first or set SR_COMMUNITY_FEED_FIXTURE_REPLACE=1.');
        }
        sr_community_feed_fixture_cleanup($pdo, $runKey);
    }

    $now = date('Y-m-d H:i:s');
    $groupKey = $runKey . '_group';
    $pdo->beginTransaction();
    try {
        $groupStmt = $pdo->prepare(
            'INSERT INTO sr_community_board_groups
                (group_key, title, description, status, sort_order, created_at, updated_at)
             VALUES
                (:group_key, :title, :description, "enabled", 3690, :created_at, :updated_at)'
        );
        $groupStmt->execute([
            'group_key' => $groupKey,
            'title' => '[#369 fixture] 게시판 그룹 ' . $runKey,
            'description' => '커뮤니티 홈 피드 성능 측정용 더미 그룹입니다.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $groupId = (int) $pdo->lastInsertId();

        $boardStmt = $pdo->prepare(
            'INSERT INTO sr_community_boards
                (board_group_id, board_key, title, description, status, read_policy, write_policy, comment_policy, image_uploads_enabled, sort_order, created_at, updated_at)
             VALUES
                (:board_group_id, :board_key, :title, :description, "enabled", "public", "member", "member", 1, :sort_order, :created_at, :updated_at)'
        );
        $boardIds = [];
        for ($boardIndex = 1; $boardIndex <= $boardCount; $boardIndex++) {
            $boardKey = $runKey . '_b' . str_pad((string) $boardIndex, 3, '0', STR_PAD_LEFT);
            $boardStmt->execute([
                'board_group_id' => $groupId,
                'board_key' => $boardKey,
                'title' => '[#369 fixture] 공개 게시판 ' . (string) $boardIndex,
                'description' => '커뮤니티 홈 피드 성능 측정용 공개 baseline 게시판입니다.',
                'sort_order' => 3690 + $boardIndex,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $boardIds[] = (int) $pdo->lastInsertId();
        }

        $postStmt = $pdo->prepare(
            'INSERT INTO sr_community_posts
                (board_id, category_id, author_account_id, author_public_name_snapshot, guest_author_name, title, body_text, body_format, is_secret, status, view_count, last_commented_at, created_at, updated_at)
             VALUES
                (:board_id, NULL, NULL, :author_public_name_snapshot, "", :title, :body_text, "plain", :is_secret, :status, :view_count, :last_commented_at, :created_at, :updated_at)'
        );
        $commentStmt = $pdo->prepare(
            'INSERT INTO sr_community_comments
                (post_id, parent_comment_id, thread_root_id, depth, author_account_id, author_public_name_snapshot, guest_author_name, body_text, is_secret, status, created_at, updated_at)
             VALUES
                (:post_id, NULL, NULL, 1, NULL, :author_public_name_snapshot, "", :body_text, 0, "published", :created_at, :updated_at)'
        );
        $attachmentStmt = $pdo->prepare(
            'INSERT INTO sr_community_attachments
                (post_id, uploader_account_id, original_name, stored_name, storage_path, storage_driver, storage_key, mime_type, size_bytes, checksum_sha256, width, height, status, created_at)
             VALUES
                (:post_id, 0, :original_name, :stored_name, :storage_path, "local", :storage_key, "image/jpeg", 2048, :checksum_sha256, 640, 360, "active", :created_at)'
        );

        $createdPosts = 0;
        $createdComments = 0;
        $createdAttachments = 0;
        for ($postIndex = 1; $postIndex <= $postCount; $postIndex++) {
            $boardId = $boardIds[($postIndex - 1) % count($boardIds)];
            $createdTime = date('Y-m-d H:i:s', strtotime('-' . (string) ($postCount - $postIndex) . ' minutes'));
            $status = $postIndex % 23 === 0 ? 'hidden' : 'published';
            $hasComment = $status === 'published' && $postIndex % 3 === 0;
            $postStmt->execute([
                'board_id' => $boardId,
                'author_public_name_snapshot' => '성능측정작성자' . (string) (($postIndex % 100) + 1),
                'title' => '[#369 fixture ' . $runKey . '] 공개 baseline 게시글 ' . (string) $postIndex,
                'body_text' => "커뮤니티 홈 피드 성능 측정용 본문입니다.\nrun_key=" . $runKey . "\npost=" . (string) $postIndex,
                'is_secret' => $postIndex % 29 === 0 ? 1 : 0,
                'status' => $status,
                'view_count' => ($postIndex * 37) % 100000,
                'last_commented_at' => $hasComment ? $createdTime : null,
                'created_at' => $createdTime,
                'updated_at' => $createdTime,
            ]);
            $postId = (int) $pdo->lastInsertId();
            $createdPosts++;

            if ($hasComment) {
                $commentStmt->execute([
                    'post_id' => $postId,
                    'author_public_name_snapshot' => '성능측정댓글작성자' . (string) (($postIndex % 100) + 1),
                    'body_text' => '커뮤니티 홈 피드 측정용 댓글 ' . (string) $postIndex,
                    'created_at' => $createdTime,
                    'updated_at' => $createdTime,
                ]);
                $createdComments++;
            }

            if ($status === 'published' && $postIndex % 8 === 0) {
                $fileName = 'sr369-fixture-' . $runKey . '-' . (string) $postIndex . '.jpg';
                $storageKey = 'community/fixture/' . $runKey . '/' . $fileName;
                $attachmentStmt->execute([
                    'post_id' => $postId,
                    'original_name' => $fileName,
                    'stored_name' => $fileName,
                    'storage_path' => 'storage/' . $storageKey,
                    'storage_key' => $storageKey,
                    'checksum_sha256' => hash('sha256', $storageKey),
                    'created_at' => $createdTime,
                ]);
                $createdAttachments++;
            }

            if ($postIndex % $batchSize === 0) {
                $pdo->commit();
                echo "progress\tposts=" . (string) $createdPosts . "\n";
                $pdo->beginTransaction();
            }
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return [
        'board_groups' => 1,
        'boards' => $boardCount,
        'posts' => $createdPosts,
        'comments' => $createdComments,
        'attachments' => $createdAttachments,
    ];
}

$action = strtolower((string) ($argv[1] ?? ''));
if (!in_array($action, ['seed', 'cleanup'], true)) {
    sr_community_feed_fixture_usage();
    exit(1);
}

if (!sr_community_feed_fixture_bool_env('SR_COMMUNITY_FEED_FIXTURE_ALLOW_MUTATION')) {
    fwrite(STDERR, "Refused: this tool creates or deletes fixture rows. Set SR_COMMUNITY_FEED_FIXTURE_ALLOW_MUTATION=1 only on local/staging disposable data.\n");
    sr_community_feed_fixture_usage();
    exit(2);
}

$runKey = sr_community_feed_fixture_run_key((string) ($argv[2] ?? getenv('SR_COMMUNITY_FEED_FIXTURE_RUN_KEY') ?: 'sr369_' . date('ymdhi')));
$postCount = max(1000, min(100000, (int) ($argv[3] ?? sr_community_feed_fixture_int_env('SR_COMMUNITY_FEED_FIXTURE_POSTS', 10000, 1000, 100000))));
$boardCount = max(1, min(200, (int) ($argv[4] ?? sr_community_feed_fixture_int_env('SR_COMMUNITY_FEED_FIXTURE_BOARDS', 20, 1, 200))));
$batchSize = sr_community_feed_fixture_int_env('SR_COMMUNITY_FEED_FIXTURE_BATCH', 1000, 100, 5000);

$config = sr_load_config();
sr_set_runtime_config($config);
$pdo = sr_db($config);

echo "community-feed-fixture\n";
echo "action\t" . $action . "\n";
echo "run_key\t" . $runKey . "\n";

if ($action === 'cleanup') {
    $result = sr_community_feed_fixture_cleanup($pdo, $runKey);
} else {
    echo "posts_requested\t" . (string) $postCount . "\n";
    echo "boards_requested\t" . (string) $boardCount . "\n";
    $start = microtime(true);
    $result = sr_community_feed_fixture_seed($pdo, $runKey, $postCount, $boardCount, $batchSize);
    echo "elapsed_ms\t" . (string) round((microtime(true) - $start) * 1000, 3) . "\n";
}

foreach ($result as $key => $value) {
    echo $key . "\t" . (string) $value . "\n";
}
