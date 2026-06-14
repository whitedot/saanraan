#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/content/helpers.php';
require_once $root . '/modules/community/helpers.php';

$errors = [];

function sr_group_delete_detach_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_group_delete_detach_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_group_delete_detach_error($message);
    }
}

function sr_group_delete_detach_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchColumn();
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    sr_group_delete_detach_error('SQLite PDO driver is required for group delete detach runtime fixture.');
} else {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec('CREATE TABLE sr_site_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL, value_type TEXT NOT NULL DEFAULT "string")');
    $pdo->exec('CREATE TABLE sr_site_menu_items (id INTEGER PRIMARY KEY AUTOINCREMENT, url TEXT NOT NULL DEFAULT "")');
    $pdo->exec('CREATE TABLE sr_content_groups (id INTEGER PRIMARY KEY, group_key TEXT NOT NULL, title TEXT NOT NULL, status TEXT NOT NULL DEFAULT "enabled")');
    $pdo->exec('CREATE TABLE sr_content_group_settings (group_id INTEGER NOT NULL, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL, value_type TEXT NOT NULL DEFAULT "string", created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_content_items (id INTEGER PRIMARY KEY, content_group_id INTEGER, title TEXT NOT NULL, slug TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_content_revisions (id INTEGER PRIMARY KEY, content_id INTEGER NOT NULL, content_group_id INTEGER, title TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_content_comments (id INTEGER PRIMARY KEY, content_id INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE sr_content_files (id INTEGER PRIMARY KEY, content_id INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_board_groups (id INTEGER PRIMARY KEY, group_key TEXT NOT NULL, title TEXT NOT NULL, status TEXT NOT NULL DEFAULT "enabled")');
    $pdo->exec('CREATE TABLE sr_community_board_group_settings (group_id INTEGER NOT NULL, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL, value_type TEXT NOT NULL DEFAULT "string", created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_boards (id INTEGER PRIMARY KEY, board_group_id INTEGER, board_key TEXT NOT NULL, title TEXT NOT NULL, updated_at TEXT NOT NULL)');

    $now = '2026-06-14 12:00:00';
    $pdo->exec("INSERT INTO sr_content_groups (id, group_key, title) VALUES (10, 'content_group', 'Content Group')");
    $pdo->exec("INSERT INTO sr_content_group_settings (group_id, setting_key, setting_value, created_at, updated_at) VALUES (10, 'status', 'enabled', '$now', '$now')");
    $pdo->exec("INSERT INTO sr_content_items (id, content_group_id, title, slug, updated_at) VALUES (100, 10, 'Linked Content', 'linked-content', '$now')");
    $pdo->exec("INSERT INTO sr_content_revisions (id, content_id, content_group_id, title) VALUES (1000, 100, 10, 'Revision')");
    $pdo->exec('INSERT INTO sr_content_comments (id, content_id) VALUES (2000, 100)');
    $pdo->exec('INSERT INTO sr_content_files (id, content_id) VALUES (3000, 100)');

    $contentDelete = sr_content_delete_group($pdo, 10);
    sr_group_delete_detach_assert(!empty($contentDelete['can_delete']), 'Content group delete should be allowed with linked content.');
    sr_group_delete_detach_assert((int) ($contentDelete['detached_contents'] ?? 0) === 1, 'Content group delete should report detached content count.');
    sr_group_delete_detach_assert((int) sr_group_delete_detach_scalar($pdo, 'SELECT COUNT(*) FROM sr_content_groups WHERE id = 10') === 0, 'Content group row should be deleted.');
    sr_group_delete_detach_assert((int) sr_group_delete_detach_scalar($pdo, 'SELECT COUNT(*) FROM sr_content_group_settings WHERE group_id = 10') === 0, 'Content group settings should be deleted.');
    sr_group_delete_detach_assert((int) sr_group_delete_detach_scalar($pdo, 'SELECT COUNT(*) FROM sr_content_items WHERE id = 100') === 1, 'Linked content should remain after content group delete.');
    sr_group_delete_detach_assert(sr_group_delete_detach_scalar($pdo, 'SELECT content_group_id FROM sr_content_items WHERE id = 100') === null, 'Linked content group id should be cleared.');
    sr_group_delete_detach_assert((int) sr_group_delete_detach_scalar($pdo, 'SELECT COUNT(*) FROM sr_content_comments WHERE content_id = 100') === 1, 'Linked content comments should remain.');
    sr_group_delete_detach_assert((int) sr_group_delete_detach_scalar($pdo, 'SELECT COUNT(*) FROM sr_content_files WHERE content_id = 100') === 1, 'Linked content files should remain.');

    $pdo->exec("INSERT INTO sr_community_board_groups (id, group_key, title) VALUES (20, 'board_group', 'Board Group')");
    $pdo->exec("INSERT INTO sr_community_board_group_settings (group_id, setting_key, setting_value, created_at, updated_at) VALUES (20, 'status', 'enabled', '$now', '$now')");
    $pdo->exec("INSERT INTO sr_community_boards (id, board_group_id, board_key, title, updated_at) VALUES (200, 20, 'linked_board', 'Linked Board', '$now')");

    $boardGroupDelete = sr_community_delete_board_group($pdo, 20);
    sr_group_delete_detach_assert(!empty($boardGroupDelete['can_delete']), 'Board group delete should be allowed with linked boards.');
    sr_group_delete_detach_assert((int) ($boardGroupDelete['detached_boards'] ?? 0) === 1, 'Board group delete should report detached board count.');
    sr_group_delete_detach_assert((int) sr_group_delete_detach_scalar($pdo, 'SELECT COUNT(*) FROM sr_community_board_groups WHERE id = 20') === 0, 'Board group row should be deleted.');
    sr_group_delete_detach_assert((int) sr_group_delete_detach_scalar($pdo, 'SELECT COUNT(*) FROM sr_community_board_group_settings WHERE group_id = 20') === 0, 'Board group settings should be deleted.');
    sr_group_delete_detach_assert((int) sr_group_delete_detach_scalar($pdo, 'SELECT COUNT(*) FROM sr_community_boards WHERE id = 200') === 1, 'Linked board should remain after board group delete.');
    sr_group_delete_detach_assert(sr_group_delete_detach_scalar($pdo, 'SELECT board_group_id FROM sr_community_boards WHERE id = 200') === null, 'Linked board group id should be cleared.');
}

if ($errors !== []) {
    fwrite(STDERR, "group delete detach runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "group delete detach runtime checks completed.\n";
