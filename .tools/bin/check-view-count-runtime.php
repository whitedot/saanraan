#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/community/helpers.php';
require_once $root . '/modules/content/helpers.php';
require_once $root . '/modules/quiz/helpers.php';
require_once $root . '/modules/survey/helpers.php';

$errors = [];

function sr_view_count_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_view_count_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_view_count_runtime_error($message);
    }
}

function sr_view_count_runtime_scalar(PDO $pdo, string $sql): int
{
    $value = $pdo->query($sql)->fetchColumn();

    return (int) $value;
}

function sr_view_count_runtime_file_contains(string $path, array $needles, string $label): void
{
    $content = file_get_contents($path);
    if (!is_string($content)) {
        sr_view_count_runtime_error($label . ' file cannot be read: ' . $path);
        return;
    }

    foreach ($needles as $needle) {
        if (!str_contains($content, (string) $needle)) {
            sr_view_count_runtime_error($label . ' must contain: ' . (string) $needle);
        }
    }
}

function sr_view_count_runtime_check_session_dedupe(string $sessionKey, callable $shouldCount): void
{
    unset($_SESSION[$sessionKey]);

    sr_view_count_runtime_assert($shouldCount(0) === false, $sessionKey . ' should ignore invalid ids.');
    sr_view_count_runtime_assert($shouldCount(101) === true, $sessionKey . ' should count the first view.');
    sr_view_count_runtime_assert($shouldCount(101) === false, $sessionKey . ' should not count the same item twice in one session.');
    sr_view_count_runtime_assert($shouldCount(102) === true, $sessionKey . ' should count a different item in the same session.');

    unset($_SESSION[$sessionKey]);
}

function sr_view_count_runtime_check_increment(PDO $pdo, string $table, callable $increment): void
{
    $pdo->exec('CREATE TABLE ' . $table . ' (id INTEGER PRIMARY KEY, view_count INTEGER NOT NULL DEFAULT 0)');
    $pdo->exec('INSERT INTO ' . $table . ' (id, view_count) VALUES (1, 7)');

    $increment($pdo, 0);
    sr_view_count_runtime_assert(
        sr_view_count_runtime_scalar($pdo, 'SELECT view_count FROM ' . $table . ' WHERE id = 1') === 7,
        $table . ' increment should ignore invalid ids.'
    );

    $increment($pdo, 1);
    sr_view_count_runtime_assert(
        sr_view_count_runtime_scalar($pdo, 'SELECT view_count FROM ' . $table . ' WHERE id = 1') === 8,
        $table . ' increment should add exactly one view.'
    );
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

sr_view_count_runtime_check_session_dedupe('sr_community_viewed_posts', 'sr_community_should_count_post_view');
sr_view_count_runtime_check_session_dedupe('sr_content_viewed_items', 'sr_content_should_count_view');
sr_view_count_runtime_check_session_dedupe('sr_quiz_viewed_sets', 'sr_quiz_should_count_view');
sr_view_count_runtime_check_session_dedupe('sr_survey_viewed_forms', 'sr_survey_should_count_view');

sr_view_count_runtime_file_contains('modules/community/helpers/posts.php', [
    "'view_count' => ['columns' => ['p.view_count', 'p.id']]",
    'p.title, p.status, p.view_count',
], 'Community admin post list');
sr_view_count_runtime_file_contains('modules/community/views/admin-posts.php', [
    "sr_admin_sort_header_html('조회수', 'view_count'",
    "number_format((int) (\$post['view_count'] ?? 0))",
], 'Community admin post view count column');
sr_view_count_runtime_file_contains('modules/content/helpers.php', [
    "'view_count' => [",
    "'columns' => ['p.view_count', 'p.id']",
    'SELECT p.*,',
], 'Content admin list');
sr_view_count_runtime_file_contains('modules/content/views/admin-contents.php', [
    "sr_content_admin_sort_header_html('조회수', 'view_count'",
    "number_format((int) (\$page['view_count'] ?? 0))",
], 'Content admin view count column');
sr_view_count_runtime_file_contains('modules/quiz/helpers.php', [
    "'view_count' => ['columns' => ['q.view_count', 'q.id']]",
    'q.member_group_keys_json, q.view_count, q.reward_enabled, q.updated_at',
], 'Quiz admin list');
sr_view_count_runtime_file_contains('modules/quiz/actions/admin-quiz.php', [
    "sr_admin_sort_header_html('조회수', 'view_count'",
    "number_format((int) (\$quiz['view_count'] ?? 0))",
], 'Quiz admin view count column');
sr_view_count_runtime_file_contains('modules/survey/helpers.php', [
    "'view_count' => ['columns' => ['s.view_count', 's.id']]",
], 'Survey admin list sort');
sr_view_count_runtime_file_contains('modules/survey/helpers/admin-surveys.php', [
    's.member_group_keys_json, s.view_count, s.reward_enabled, s.updated_at',
    'GROUP BY s.id, s.survey_key, s.title, s.status, s.starts_at, s.ends_at, s.qa_status, s.member_group_keys_json, s.view_count, s.reward_enabled, s.updated_at',
], 'Survey admin view count query');
sr_view_count_runtime_file_contains('modules/survey/actions/admin-surveys.php', [
    "sr_admin_sort_header_html('조회수', 'view_count'",
    "number_format((int) (\$survey['view_count'] ?? 0))",
], 'Survey admin view count column');

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    sr_view_count_runtime_error('SQLite PDO driver is required for view count runtime fixture.');
} else {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    sr_view_count_runtime_check_increment($pdo, 'sr_community_posts', 'sr_community_increment_post_view_count');
    sr_view_count_runtime_check_increment($pdo, 'sr_content_items', 'sr_content_increment_view_count');
    sr_view_count_runtime_check_increment($pdo, 'sr_quiz_sets', 'sr_quiz_increment_view_count');
    sr_view_count_runtime_check_increment($pdo, 'sr_survey_forms', 'sr_survey_increment_view_count');
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, '[check-view-count-runtime] ' . $error . PHP_EOL);
    }
    exit(1);
}

echo "View count runtime checks passed.\n";
