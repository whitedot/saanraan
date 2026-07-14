#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}
if (!function_exists('sr_module_setting')) {
    function sr_module_setting(PDO $pdo, string $moduleKey, string $settingKey, string $default = ''): string
    {
        return $default;
    }
}

require_once $root . '/modules/community/helpers/series.php';

$action = file_get_contents($root . '/modules/community/actions/series.php');
$view = file_get_contents($root . '/modules/community/views/series.php');
$helper = file_get_contents($root . '/modules/community/helpers/series.php');
foreach ([
    'action' => [$action, ["sr_get_string('page'", 'sr_community_account_series_count', '$seriesPagination']],
    'view' => [$view, ['id="community-series-list"', 'sr_public_pagination_html($seriesPagination']],
    'helper' => [$helper, ['function sr_community_account_series_count', 'LIMIT :limit OFFSET :offset']],
] as $label => [$source, $markers]) {
    if (!is_string($source)) {
        $errors[] = 'cannot read community series ' . $label . ' source';
        continue;
    }
    foreach ($markers as $marker) {
        if (!str_contains($source, $marker)) {
            $errors[] = 'community series ' . $label . ' missing pagination marker: ' . $marker;
        }
    }
}
if (is_string($helper) && str_contains($helper, 'LIMIT 200')) {
    $errors[] = 'community account series helper still hard-caps the public list at 200 rows';
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE sr_community_series_items (id INTEGER PRIMARY KEY)');
$pdo->exec('CREATE TABLE sr_community_boards (id INTEGER PRIMARY KEY, status TEXT NOT NULL)');
$pdo->exec('CREATE TABLE sr_community_board_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, board_id INTEGER NOT NULL, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL)');
$pdo->exec('CREATE TABLE sr_community_series (id INTEGER PRIMARY KEY AUTOINCREMENT, board_id INTEGER NOT NULL, owner_account_id INTEGER NOT NULL, title TEXT NOT NULL, description TEXT NOT NULL, status TEXT NOT NULL, visibility TEXT NOT NULL, admin_note TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
$pdo->exec("INSERT INTO sr_community_boards (id, status) VALUES (1, 'enabled'), (2, 'enabled'), (3, 'disabled')");
$pdo->exec("INSERT INTO sr_community_board_settings (board_id, setting_key, setting_value) VALUES (2, 'series_enabled', '0')");
$insert = $pdo->prepare("INSERT INTO sr_community_series (board_id, owner_account_id, title, description, status, visibility, admin_note, created_at, updated_at) VALUES (:board_id, :account_id, :title, '', :status, 'public', '', '2026-01-01 00:00:00', :updated_at)");
for ($rowNumber = 1; $rowNumber <= 45; $rowNumber++) {
    $insert->execute([
        'board_id' => 1,
        'account_id' => 1,
        'title' => 'series-' . (string) $rowNumber,
        'status' => 'active',
        'updated_at' => sprintf('2026-01-%02d 00:00:00', (($rowNumber - 1) % 28) + 1),
    ]);
}
$insert->execute(['board_id' => 2, 'account_id' => 1, 'title' => 'disabled-series-board', 'status' => 'active', 'updated_at' => '2026-02-01 00:00:00']);
$insert->execute(['board_id' => 3, 'account_id' => 1, 'title' => 'disabled-board', 'status' => 'active', 'updated_at' => '2026-02-02 00:00:00']);
$insert->execute(['board_id' => 1, 'account_id' => 2, 'title' => 'other-account', 'status' => 'active', 'updated_at' => '2026-02-03 00:00:00']);
$insert->execute(['board_id' => 1, 'account_id' => 1, 'title' => 'archived', 'status' => 'archived', 'updated_at' => '2026-02-04 00:00:00']);

if (sr_community_account_series_count($pdo, 1) !== 45) {
    $errors[] = 'community series count must include every eligible account series only';
}
$lastPage = sr_community_account_series($pdo, 1, 0, 20, 40);
if (count($lastPage) !== 5) {
    $errors[] = 'community series pagination must expose the final partial page';
}
$titles = array_map(static fn (array $row): string => (string) ($row['title'] ?? ''), $lastPage);
if (in_array('disabled-series-board', $titles, true) || in_array('disabled-board', $titles, true) || in_array('other-account', $titles, true) || in_array('archived', $titles, true)) {
    $errors[] = 'community series pagination must apply the same account, status, and board availability rules to rows and count';
}

if ($errors !== []) {
    fwrite(STDERR, "community series pagination checks failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "community series pagination checks completed.\n";
