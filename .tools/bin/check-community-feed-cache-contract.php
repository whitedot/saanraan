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

sr_check_community_feed_cache_contract_contains('modules/community/helpers/feed-cache.php', [
    "'baseline' => 'everyone_discoverable_public_boards'",
    'function sr_community_feed_cache_post_feed_query',
    'author_account_id',
    'published_comment_count',
    'sr_community_feed_cache_snapshot_forbidden_keys',
]);

sr_check_community_feed_cache_contract_contains('modules/community/helpers/presentation.php', [
    'sr_community_feed_cache_post_feed_query($pdo',
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
