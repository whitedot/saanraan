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
