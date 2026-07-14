#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];
$source = static function (string $file) use ($root, &$errors): string {
    $contents = file_get_contents($root . '/' . $file);
    if (!is_string($contents)) {
        $errors[] = 'cannot read community scrap source: ' . $file;
        return '';
    }

    return $contents;
};
$assertContains = static function (string $file, array $markers) use ($source, &$errors): void {
    $contents = $source($file);
    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $errors[] = $file . ' missing scrap pagination marker: ' . $marker;
        }
    }
};

$assertContains('modules/community/actions/scraps.php', [
    "sr_get_string('post_page'",
    "sr_get_string('series_page'",
    'sr_community_account_scrap_count(',
    'sr_community_account_series_scrap_count(',
    '$scrapPaginationBasePath',
    '$seriesScrapPaginationBasePath',
]);
$assertContains('modules/community/helpers/scraps.php', [
    'function sr_community_account_scrap_count(',
    'function sr_community_account_series_scrap_count(',
    'int $limit = 50, int $offset = 0',
    'LIMIT :limit_value OFFSET :offset_value',
]);
$assertContains('modules/community/views/scraps.php', [
    'id="community-post-scraps"',
    'id="community-series-scraps"',
    'name="return_to" value="scraps"',
    'sr_public_pagination_html($scrapPagination',
    'sr_public_pagination_html($seriesScrapPagination',
]);
$assertContains('modules/community/actions/scrap-toggle.php', [
    "sr_post_string('return_post_page'",
    "sr_post_string('return_series_page'",
    'sr_redirect($scrapReturnPath)',
]);

function sr_community_series_scraps_supported(PDO $pdo): bool
{
    return true;
}

function sr_community_categories_supported(PDO $pdo): bool
{
    return false;
}

function sr_community_account_can_read_board(PDO $pdo, array $board, ?array $account): bool
{
    return true;
}

function sr_community_series_can_view(PDO $pdo, array $series, ?array $account): bool
{
    return true;
}

require_once $root . '/modules/community/helpers/scraps.php';
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE sr_community_boards (id INTEGER PRIMARY KEY, board_group_id INTEGER, board_key TEXT, title TEXT, status TEXT, read_policy TEXT)');
$pdo->exec("INSERT INTO sr_community_boards VALUES (1, NULL, 'free', 'Free', 'enabled', 'public')");
$pdo->exec('CREATE TABLE sr_community_posts (id INTEGER PRIMARY KEY, board_id INTEGER, category_id INTEGER, title TEXT, status TEXT, created_at TEXT)');
$pdo->exec("INSERT INTO sr_community_posts VALUES (1, 1, NULL, 'Post', 'published', '2026-01-01 00:00:00')");
$pdo->exec('CREATE TABLE sr_community_comments (id INTEGER PRIMARY KEY, post_id INTEGER, status TEXT)');
$pdo->exec('CREATE TABLE sr_community_series (id INTEGER PRIMARY KEY, board_id INTEGER, owner_account_id INTEGER, title TEXT, description TEXT, status TEXT, visibility TEXT, created_at TEXT, updated_at TEXT)');
$pdo->exec("INSERT INTO sr_community_series VALUES (1, 1, 1, 'Series', '', 'active', 'public', '2026-01-01 00:00:00', '2026-01-01 00:00:00')");
$pdo->exec('CREATE TABLE sr_community_scraps (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER, post_id INTEGER, created_at TEXT)');
$pdo->exec('CREATE TABLE sr_community_series_scraps (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER, series_id INTEGER, created_at TEXT)');
$postInsert = $pdo->prepare("INSERT INTO sr_community_scraps (account_id, post_id, created_at) VALUES (1, 1, '2026-01-01 00:00:00')");
$seriesInsert = $pdo->prepare("INSERT INTO sr_community_series_scraps (account_id, series_id, created_at) VALUES (1, 1, '2026-01-01 00:00:00')");
for ($rowNumber = 1; $rowNumber <= 45; $rowNumber++) {
    $postInsert->execute();
    $seriesInsert->execute();
}

if (sr_community_account_scrap_count($pdo, 1) !== 45 || sr_community_account_series_scrap_count($pdo, 1) !== 45) {
    $errors[] = 'scrap counts must include every account row';
}
$postFinalPage = sr_community_account_scraps($pdo, 1, null, 20, 40);
if (count($postFinalPage) !== 5 || (int) ($postFinalPage[0]['id'] ?? 0) !== 5 || (int) ($postFinalPage[4]['id'] ?? 0) !== 1) {
    $errors[] = 'post scraps must expose the final partial page';
}
$seriesSecondPage = sr_community_account_series_scraps($pdo, 1, null, 20, 20);
if (count($seriesSecondPage) !== 20 || (int) ($seriesSecondPage[0]['id'] ?? 0) !== 25 || (int) ($seriesSecondPage[19]['id'] ?? 0) !== 6) {
    $errors[] = 'series scraps must return the requested ordered slice';
}

if ($errors !== []) {
    fwrite(STDERR, "community scrap pagination checks failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "community scrap pagination checks completed.\n";
