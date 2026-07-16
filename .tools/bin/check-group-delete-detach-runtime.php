#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/content/helpers.php';
require_once $root . '/modules/community/helpers.php';
require_once $root . '/modules/quiz/helpers/groups.php';
require_once $root . '/modules/survey/helpers/groups.php';

$errors = [];

$contentGroupAdminView = (string) file_get_contents($root . '/modules/content/views/admin-content-groups.php');
if (!str_contains($contentGroupAdminView, '그룹을 만든 뒤에는 연결 주소가 바뀌지 않도록 수정할 수 없습니다.')) {
    $errors[] = 'Content group identifier help must explain why the value cannot change after creation.';
}
if (!str_contains($contentGroupAdminView, '사용안함·보관 상태에서는 그룹 공개 화면과 새 회원 제출을 사용할 수 없습니다.')) {
    $errors[] = 'Content group status help must explain the public and member submission effect.';
}
if (!str_contains($contentGroupAdminView, "['enabled', 'disabled', 'archived']")) {
    $errors[] = 'Content group status descriptions must include the archived status offered by the form.';
}
if (!str_contains($contentGroupAdminView, '해당 콘텐츠의 댓글') || !str_contains($contentGroupAdminView, '사이트 메뉴나 초기 화면에서 이 그룹을 사용 중인 곳이')) {
    $errors[] = 'Content group delete help must explain retained content data and blocking references.';
}

$communityBoardGroupAdminView = (string) file_get_contents($root . '/modules/community/views/admin-board-groups.php');
if (!str_contains($communityBoardGroupAdminView, '연결된 게시판을 자동으로 중지하거나 삭제하지 않습니다.')) {
    $errors[] = 'Community board group status help must distinguish group visibility from board status.';
}
if (!str_contains($communityBoardGroupAdminView, '이 값은 그룹 안의 게시판 순서를 바꾸지 않습니다.')) {
    $errors[] = 'Community board group sort help must distinguish group order from board order.';
}
if (!str_contains($communityBoardGroupAdminView, "['enabled', 'disabled', 'archived']")) {
    $errors[] = 'Community board group status descriptions must include archived.';
}
if (!str_contains($communityBoardGroupAdminView, '이후 각 게시판의 자체 설정을 사용합니다.') || !str_contains($communityBoardGroupAdminView, '사이트 메뉴에서 이 그룹을 사용 중인 곳이')) {
    $errors[] = 'Community board group delete help must explain board fallback and menu references.';
}

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
    $pdo->exec('CREATE TABLE sr_content_setting_sources (content_id INTEGER NOT NULL, setting_key TEXT NOT NULL, source TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_content_revisions (id INTEGER PRIMARY KEY, content_id INTEGER NOT NULL, content_group_id INTEGER, title TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_content_comments (id INTEGER PRIMARY KEY, content_id INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE sr_content_files (id INTEGER PRIMARY KEY, content_id INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_board_groups (id INTEGER PRIMARY KEY, group_key TEXT NOT NULL, title TEXT NOT NULL, status TEXT NOT NULL DEFAULT "enabled")');
    $pdo->exec('CREATE TABLE sr_community_board_group_settings (group_id INTEGER NOT NULL, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL, value_type TEXT NOT NULL DEFAULT "string", created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_boards (id INTEGER PRIMARY KEY, board_group_id INTEGER, board_key TEXT NOT NULL, title TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_board_setting_sources (board_id INTEGER NOT NULL, setting_key TEXT NOT NULL, source TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_quiz_groups (id INTEGER PRIMARY KEY, group_key TEXT NOT NULL, title TEXT NOT NULL, description TEXT NOT NULL DEFAULT "", status TEXT NOT NULL DEFAULT "enabled", sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_quiz_sets (id INTEGER PRIMARY KEY, quiz_group_id INTEGER, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_quiz_setting_sources (quiz_id INTEGER NOT NULL, setting_key TEXT NOT NULL, source TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_survey_groups (id INTEGER PRIMARY KEY, group_key TEXT NOT NULL, title TEXT NOT NULL, description TEXT NOT NULL DEFAULT "", status TEXT NOT NULL DEFAULT "enabled", sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_survey_forms (id INTEGER PRIMARY KEY, survey_group_id INTEGER, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_survey_setting_sources (survey_id INTEGER NOT NULL, setting_key TEXT NOT NULL, source TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');

    $now = '2026-06-14 12:00:00';
    $pdo->exec("INSERT INTO sr_content_groups (id, group_key, title) VALUES (10, 'content_group', 'Content Group')");
    $pdo->exec("INSERT INTO sr_content_group_settings (group_id, setting_key, setting_value, created_at, updated_at) VALUES (10, 'status', 'enabled', '$now', '$now')");
    $pdo->exec("INSERT INTO sr_content_items (id, content_group_id, title, slug, updated_at) VALUES (100, 10, 'Linked Content', 'linked-content', '$now')");
    $pdo->exec("INSERT INTO sr_content_setting_sources (content_id, setting_key, source, created_at, updated_at) VALUES (100, 'status', 'group', '$now', '$now'), (100, 'layout_key', 'all', '$now', '$now')");
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
    sr_group_delete_detach_assert((string) sr_group_delete_detach_scalar($pdo, "SELECT source FROM sr_content_setting_sources WHERE content_id = 100 AND setting_key = 'status'") === 'content', 'Deleted content group source should fall back to content.');
    sr_group_delete_detach_assert((string) sr_group_delete_detach_scalar($pdo, "SELECT source FROM sr_content_setting_sources WHERE content_id = 100 AND setting_key = 'layout_key'") === 'all', 'Content all source should remain unchanged after group delete.');
    sr_group_delete_detach_assert((int) sr_group_delete_detach_scalar($pdo, 'SELECT COUNT(*) FROM sr_content_comments WHERE content_id = 100') === 1, 'Linked content comments should remain.');
    sr_group_delete_detach_assert((int) sr_group_delete_detach_scalar($pdo, 'SELECT COUNT(*) FROM sr_content_files WHERE content_id = 100') === 1, 'Linked content files should remain.');

    $pdo->exec("INSERT INTO sr_community_board_groups (id, group_key, title) VALUES (20, 'board_group', 'Board Group')");
    $pdo->exec("INSERT INTO sr_community_board_group_settings (group_id, setting_key, setting_value, created_at, updated_at) VALUES (20, 'status', 'enabled', '$now', '$now')");
    $pdo->exec("INSERT INTO sr_community_boards (id, board_group_id, board_key, title, updated_at) VALUES (200, 20, 'linked_board', 'Linked Board', '$now')");
    $pdo->exec("INSERT INTO sr_community_board_setting_sources (board_id, setting_key, source, created_at, updated_at) VALUES (200, 'status', 'group', '$now', '$now'), (200, 'read_policy', 'all', '$now', '$now')");

    $boardGroupDelete = sr_community_delete_board_group($pdo, 20);
    sr_group_delete_detach_assert(!empty($boardGroupDelete['can_delete']), 'Board group delete should be allowed with linked boards.');
    sr_group_delete_detach_assert((int) ($boardGroupDelete['detached_boards'] ?? 0) === 1, 'Board group delete should report detached board count.');
    sr_group_delete_detach_assert((int) sr_group_delete_detach_scalar($pdo, 'SELECT COUNT(*) FROM sr_community_board_groups WHERE id = 20') === 0, 'Board group row should be deleted.');
    sr_group_delete_detach_assert((int) sr_group_delete_detach_scalar($pdo, 'SELECT COUNT(*) FROM sr_community_board_group_settings WHERE group_id = 20') === 0, 'Board group settings should be deleted.');
    sr_group_delete_detach_assert((int) sr_group_delete_detach_scalar($pdo, 'SELECT COUNT(*) FROM sr_community_boards WHERE id = 200') === 1, 'Linked board should remain after board group delete.');
    sr_group_delete_detach_assert(sr_group_delete_detach_scalar($pdo, 'SELECT board_group_id FROM sr_community_boards WHERE id = 200') === null, 'Linked board group id should be cleared.');
    sr_group_delete_detach_assert((string) sr_group_delete_detach_scalar($pdo, "SELECT source FROM sr_community_board_setting_sources WHERE board_id = 200 AND setting_key = 'status'") === 'board', 'Deleted board group source should fall back to board.');
    sr_group_delete_detach_assert((string) sr_group_delete_detach_scalar($pdo, "SELECT source FROM sr_community_board_setting_sources WHERE board_id = 200 AND setting_key = 'read_policy'") === 'all', 'Board all source should remain unchanged after group delete.');

    $pdo->exec("INSERT INTO sr_quiz_groups (id, group_key, title, created_at, updated_at) VALUES (30, 'quiz_group', 'Quiz Group', '$now', '$now')");
    $pdo->exec("INSERT INTO sr_quiz_sets (id, quiz_group_id, updated_at) VALUES (300, 30, '$now')");
    $pdo->exec("INSERT INTO sr_quiz_setting_sources (quiz_id, setting_key, source, created_at, updated_at) VALUES (300, 'status', 'group', '$now', '$now'), (300, 'skin_key', 'all', '$now', '$now')");
    sr_group_delete_detach_assert(sr_quiz_delete_group($pdo, 30), 'Quiz group delete should succeed.');
    sr_group_delete_detach_assert(sr_group_delete_detach_scalar($pdo, 'SELECT quiz_group_id FROM sr_quiz_sets WHERE id = 300') === null, 'Linked quiz group id should be cleared.');
    sr_group_delete_detach_assert((string) sr_group_delete_detach_scalar($pdo, "SELECT source FROM sr_quiz_setting_sources WHERE quiz_id = 300 AND setting_key = 'status'") === 'item', 'Deleted quiz group source should fall back to item.');
    sr_group_delete_detach_assert((string) sr_group_delete_detach_scalar($pdo, "SELECT source FROM sr_quiz_setting_sources WHERE quiz_id = 300 AND setting_key = 'skin_key'") === 'all', 'Quiz all source should remain unchanged after group delete.');

    $pdo->exec("INSERT INTO sr_survey_groups (id, group_key, title, created_at, updated_at) VALUES (40, 'survey_group', 'Survey Group', '$now', '$now')");
    $pdo->exec("INSERT INTO sr_survey_forms (id, survey_group_id, updated_at) VALUES (400, 40, '$now')");
    $pdo->exec("INSERT INTO sr_survey_setting_sources (survey_id, setting_key, source, created_at, updated_at) VALUES (400, 'status', 'group', '$now', '$now'), (400, 'skin_key', 'all', '$now', '$now')");
    sr_group_delete_detach_assert(sr_survey_delete_group($pdo, 40), 'Survey group delete should succeed.');
    sr_group_delete_detach_assert(sr_group_delete_detach_scalar($pdo, 'SELECT survey_group_id FROM sr_survey_forms WHERE id = 400') === null, 'Linked survey group id should be cleared.');
    sr_group_delete_detach_assert((string) sr_group_delete_detach_scalar($pdo, "SELECT source FROM sr_survey_setting_sources WHERE survey_id = 400 AND setting_key = 'status'") === 'item', 'Deleted survey group source should fall back to item.');
    sr_group_delete_detach_assert((string) sr_group_delete_detach_scalar($pdo, "SELECT source FROM sr_survey_setting_sources WHERE survey_id = 400 AND setting_key = 'skin_key'") === 'all', 'Survey all source should remain unchanged after group delete.');
}

if ($errors !== []) {
    fwrite(STDERR, "group delete detach runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "group delete detach runtime checks completed.\n";
