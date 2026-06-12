#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/quiz/helpers.php';

$errors = [];

function sr_quiz_delete_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_quiz_delete_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_quiz_delete_runtime_error($message);
    }
}

function sr_quiz_delete_runtime_row(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? $row : [];
}

function sr_quiz_delete_runtime_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchColumn();
}

function sr_quiz_delete_runtime_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE sr_quiz_sets (
        id INTEGER PRIMARY KEY,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        status TEXT NOT NULL,
        comments_enabled INTEGER NOT NULL DEFAULT 1,
        secret_comments_enabled INTEGER NOT NULL DEFAULT 1,
        reward_enabled INTEGER NOT NULL DEFAULT 1,
        updated_by_account_id INTEGER,
        updated_at TEXT NOT NULL,
        deleted_at TEXT
    )');
    $pdo->exec('CREATE TABLE sr_quiz_questions (
        id INTEGER PRIMARY KEY,
        quiz_id INTEGER NOT NULL,
        prompt TEXT NOT NULL,
        help_text TEXT,
        settings_json TEXT,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_quiz_choices (
        id INTEGER PRIMARY KEY,
        question_id INTEGER NOT NULL,
        label TEXT NOT NULL,
        description TEXT,
        settings_json TEXT,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_quiz_results (
        id INTEGER PRIMARY KEY,
        quiz_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        summary TEXT,
        body TEXT,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_quiz_comments (
        id INTEGER PRIMARY KEY,
        quiz_id INTEGER NOT NULL,
        author_public_name_snapshot TEXT,
        body_text TEXT NOT NULL,
        status TEXT NOT NULL,
        deleted_at TEXT,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_quiz_attempts (
        id INTEGER PRIMARY KEY,
        quiz_id INTEGER NOT NULL,
        source_title_snapshot TEXT,
        source_url_snapshot TEXT,
        return_url TEXT,
        answer_snapshot_json TEXT,
        scoring_snapshot_json TEXT,
        result_snapshot_json TEXT,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_quiz_attempt_answers (
        id INTEGER PRIMARY KEY,
        attempt_id INTEGER NOT NULL,
        answer_text TEXT,
        answer_snapshot_json TEXT,
        category_scores_json TEXT
    )');
    $pdo->exec('CREATE TABLE sr_quiz_attempt_result_scores (
        id INTEGER PRIMARY KEY,
        attempt_id INTEGER NOT NULL,
        snapshot_json TEXT
    )');
    $pdo->exec('CREATE TABLE sr_quiz_reward_grants (
        id INTEGER PRIMARY KEY,
        quiz_id INTEGER NOT NULL,
        request_snapshot_json TEXT,
        result_snapshot_json TEXT,
        error_message TEXT,
        resolution_note TEXT
    )');
}

function sr_quiz_delete_runtime_seed(PDO $pdo): void
{
    $now = '2026-06-12 05:30:00';
    $pdo->exec("INSERT INTO sr_quiz_sets (id, title, description, status, comments_enabled, secret_comments_enabled, reward_enabled, updated_at, deleted_at)
        VALUES (501, 'Original quiz', 'Original description', 'active', 1, 1, 1, '$now', NULL)");
    $pdo->exec("INSERT INTO sr_quiz_questions (id, quiz_id, prompt, help_text, settings_json, updated_at)
        VALUES (601, 501, 'Original question', 'Help', '{\"pii\":\"question\"}', '$now')");
    $pdo->exec("INSERT INTO sr_quiz_choices (id, question_id, label, description, settings_json, updated_at)
        VALUES (701, 601, 'Original choice', 'Choice description', '{\"pii\":\"choice\"}', '$now')");
    $pdo->exec("INSERT INTO sr_quiz_results (id, quiz_id, title, summary, body, updated_at)
        VALUES (801, 501, 'Original result', 'Summary', 'Body', '$now')");
    $pdo->exec("INSERT INTO sr_quiz_comments (id, quiz_id, author_public_name_snapshot, body_text, status, deleted_at, updated_at)
        VALUES (901, 501, 'Author Name', 'Comment body', 'published', NULL, '$now')");
    $pdo->exec("INSERT INTO sr_quiz_attempts (id, quiz_id, source_title_snapshot, source_url_snapshot, return_url, answer_snapshot_json, scoring_snapshot_json, result_snapshot_json, updated_at)
        VALUES (1001, 501, 'Source title', '/content/source', '/content/source?quiz=1', '{\"answer\":\"secret\"}', '{\"score\":1}', '{\"result\":\"secret\"}', '$now')");
    $pdo->exec("INSERT INTO sr_quiz_attempt_answers (id, attempt_id, answer_text, answer_snapshot_json, category_scores_json)
        VALUES (1101, 1001, 'Free text answer', '{\"answer\":\"secret\"}', '{\"cat\":1}')");
    $pdo->exec("INSERT INTO sr_quiz_attempt_result_scores (id, attempt_id, snapshot_json)
        VALUES (1201, 1001, '{\"result\":\"secret\"}')");
    $pdo->exec("INSERT INTO sr_quiz_reward_grants (id, quiz_id, request_snapshot_json, result_snapshot_json, error_message, resolution_note)
        VALUES (1301, 501, '{\"request\":\"secret\"}', '{\"result\":\"secret\"}', 'provider error', 'manual note')");
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
sr_quiz_delete_runtime_schema($pdo);
sr_quiz_delete_runtime_seed($pdo);

$deleted = sr_quiz_soft_delete($pdo, 501, 9);
sr_quiz_delete_runtime_assert($deleted, 'Quiz soft delete must return true for active quiz.');

$quiz = sr_quiz_delete_runtime_row($pdo, 'SELECT title, description, status, comments_enabled, secret_comments_enabled, reward_enabled, updated_by_account_id, deleted_at FROM sr_quiz_sets WHERE id = 501');
sr_quiz_delete_runtime_assert((string) ($quiz['title'] ?? '') === '삭제된 퀴즈', 'Quiz soft delete must redact quiz title.');
sr_quiz_delete_runtime_assert((string) ($quiz['description'] ?? '') === '삭제된 퀴즈입니다.', 'Quiz soft delete must redact quiz description.');
sr_quiz_delete_runtime_assert((string) ($quiz['status'] ?? '') === 'archived', 'Quiz soft delete must archive quiz.');
sr_quiz_delete_runtime_assert((int) ($quiz['comments_enabled'] ?? 1) === 0 && (int) ($quiz['secret_comments_enabled'] ?? 1) === 0, 'Quiz soft delete must disable comments.');
sr_quiz_delete_runtime_assert((int) ($quiz['reward_enabled'] ?? 1) === 0, 'Quiz soft delete must disable rewards.');
sr_quiz_delete_runtime_assert((int) ($quiz['updated_by_account_id'] ?? 0) === 9, 'Quiz soft delete must record admin account.');
sr_quiz_delete_runtime_assert((string) ($quiz['deleted_at'] ?? '') !== '', 'Quiz soft delete must set deleted_at.');

$question = sr_quiz_delete_runtime_row($pdo, 'SELECT prompt, help_text, settings_json FROM sr_quiz_questions WHERE id = 601');
sr_quiz_delete_runtime_assert((string) ($question['prompt'] ?? '') === '삭제된 퀴즈입니다.', 'Quiz soft delete must redact question prompt.');
sr_quiz_delete_runtime_assert(($question['help_text'] ?? null) === null && ($question['settings_json'] ?? null) === null, 'Quiz soft delete must clear question helper data.');

$choice = sr_quiz_delete_runtime_row($pdo, 'SELECT label, description, settings_json FROM sr_quiz_choices WHERE id = 701');
sr_quiz_delete_runtime_assert((string) ($choice['label'] ?? '') === '삭제된 선택지', 'Quiz soft delete must redact choices.');
sr_quiz_delete_runtime_assert(($choice['description'] ?? null) === null && ($choice['settings_json'] ?? null) === null, 'Quiz soft delete must clear choice helper data.');

$result = sr_quiz_delete_runtime_row($pdo, 'SELECT title, summary, body FROM sr_quiz_results WHERE id = 801');
sr_quiz_delete_runtime_assert((string) ($result['title'] ?? '') === '삭제된 결과', 'Quiz soft delete must redact result title.');
sr_quiz_delete_runtime_assert((string) ($result['summary'] ?? '') === '' && (string) ($result['body'] ?? '') === '', 'Quiz soft delete must clear result body.');

$comment = sr_quiz_delete_runtime_row($pdo, 'SELECT author_public_name_snapshot, body_text, status, deleted_at FROM sr_quiz_comments WHERE id = 901');
sr_quiz_delete_runtime_assert((string) ($comment['author_public_name_snapshot'] ?? '') === '', 'Quiz soft delete must clear comment author snapshot.');
sr_quiz_delete_runtime_assert((string) ($comment['body_text'] ?? '') === '삭제된 댓글입니다.', 'Quiz soft delete must redact comments.');
sr_quiz_delete_runtime_assert((string) ($comment['status'] ?? '') === 'deleted' && (string) ($comment['deleted_at'] ?? '') !== '', 'Quiz soft delete must mark comments deleted.');

$attempt = sr_quiz_delete_runtime_row($pdo, 'SELECT source_title_snapshot, source_url_snapshot, return_url, answer_snapshot_json, scoring_snapshot_json, result_snapshot_json FROM sr_quiz_attempts WHERE id = 1001');
sr_quiz_delete_runtime_assert((string) ($attempt['source_title_snapshot'] ?? '') === '' && (string) ($attempt['source_url_snapshot'] ?? '') === '', 'Quiz soft delete must clear source snapshots.');
sr_quiz_delete_runtime_assert((string) ($attempt['return_url'] ?? '') === '', 'Quiz soft delete must clear return URL.');
sr_quiz_delete_runtime_assert((string) ($attempt['answer_snapshot_json'] ?? '') === '{}' && (string) ($attempt['scoring_snapshot_json'] ?? '') === '{}' && (string) ($attempt['result_snapshot_json'] ?? '') === '{}', 'Quiz soft delete must clear attempt snapshots.');

$answer = sr_quiz_delete_runtime_row($pdo, 'SELECT answer_text, answer_snapshot_json, category_scores_json FROM sr_quiz_attempt_answers WHERE id = 1101');
sr_quiz_delete_runtime_assert(($answer['answer_text'] ?? null) === null, 'Quiz soft delete must clear free text answers.');
sr_quiz_delete_runtime_assert((string) ($answer['answer_snapshot_json'] ?? '') === '{}' && ($answer['category_scores_json'] ?? null) === null, 'Quiz soft delete must clear attempt answer snapshots.');

sr_quiz_delete_runtime_assert((string) sr_quiz_delete_runtime_scalar($pdo, 'SELECT snapshot_json FROM sr_quiz_attempt_result_scores WHERE id = 1201') === '{}', 'Quiz soft delete must clear attempt result score snapshots.');

$grant = sr_quiz_delete_runtime_row($pdo, 'SELECT request_snapshot_json, result_snapshot_json, error_message, resolution_note FROM sr_quiz_reward_grants WHERE id = 1301');
sr_quiz_delete_runtime_assert((string) ($grant['request_snapshot_json'] ?? '') === '{}' && (string) ($grant['result_snapshot_json'] ?? '') === '{}', 'Quiz soft delete must clear reward snapshots.');
sr_quiz_delete_runtime_assert((string) ($grant['error_message'] ?? '') === '' && (string) ($grant['resolution_note'] ?? '') === '', 'Quiz soft delete must clear reward operational notes.');

$deletedAgain = sr_quiz_soft_delete($pdo, 501, 9);
sr_quiz_delete_runtime_assert(!$deletedAgain, 'Quiz soft delete must be idempotent for already deleted quiz.');

if ($errors !== []) {
    fwrite(STDERR, "quiz delete runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "quiz delete runtime checks completed.\n";
