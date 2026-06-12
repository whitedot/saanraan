#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/survey/helpers.php';

$errors = [];

function sr_survey_statistics_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_survey_statistics_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_survey_statistics_runtime_error($message);
    }
}

function sr_survey_statistics_runtime_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE sr_survey_responses (
        id INTEGER PRIMARY KEY,
        survey_id INTEGER NOT NULL,
        account_id INTEGER,
        status TEXT NOT NULL DEFAULT "submitted",
        quality_status TEXT NOT NULL DEFAULT "accepted",
        is_test INTEGER NOT NULL DEFAULT 0,
        submitted_at TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_survey_response_answers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        response_id INTEGER NOT NULL,
        question_id INTEGER NOT NULL,
        question_key TEXT NOT NULL,
        choice_id INTEGER,
        choice_key TEXT,
        answer_text TEXT,
        answer_number REAL,
        other_text TEXT,
        answer_snapshot_json TEXT NOT NULL,
        created_at TEXT NOT NULL
    )');
}

function sr_survey_statistics_runtime_insert_response(PDO $pdo, int $id, int $surveyId, ?int $accountId, string $qualityStatus, int $isTest): void
{
    $pdo->prepare(
        'INSERT INTO sr_survey_responses (id, survey_id, account_id, quality_status, is_test, submitted_at, created_at, updated_at)
         VALUES (:id, :survey_id, :account_id, :quality_status, :is_test, :submitted_at, :created_at, :updated_at)'
    )->execute([
        'id' => $id,
        'survey_id' => $surveyId,
        'account_id' => $accountId,
        'quality_status' => $qualityStatus,
        'is_test' => $isTest,
        'submitted_at' => '2026-06-12 05:10:00',
        'created_at' => '2026-06-12 05:10:00',
        'updated_at' => '2026-06-12 05:10:00',
    ]);
}

function sr_survey_statistics_runtime_insert_answer(PDO $pdo, int $responseId, string $questionKey, ?string $choiceKey, ?float $answerNumber): void
{
    $pdo->prepare(
        'INSERT INTO sr_survey_response_answers (response_id, question_id, question_key, choice_id, choice_key, answer_number, answer_snapshot_json, created_at)
         VALUES (:response_id, 1, :question_key, NULL, :choice_key, :answer_number, :answer_snapshot_json, :created_at)'
    )->execute([
        'response_id' => $responseId,
        'question_key' => $questionKey,
        'choice_key' => $choiceKey,
        'answer_number' => $answerNumber,
        'answer_snapshot_json' => '{}',
        'created_at' => '2026-06-12 05:10:00',
    ]);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
sr_survey_statistics_runtime_schema($pdo);

sr_survey_statistics_runtime_insert_response($pdo, 1, 7, 10, 'accepted', 0);
sr_survey_statistics_runtime_insert_response($pdo, 2, 7, null, 'flagged', 0);
sr_survey_statistics_runtime_insert_response($pdo, 3, 7, 12, 'excluded', 0);
sr_survey_statistics_runtime_insert_response($pdo, 4, 7, 13, 'accepted', 1);
sr_survey_statistics_runtime_insert_response($pdo, 5, 8, 14, 'accepted', 0);

sr_survey_statistics_runtime_insert_answer($pdo, 1, 'q_choice', 'a', null);
sr_survey_statistics_runtime_insert_answer($pdo, 1, 'q_choice', 'a', null);
sr_survey_statistics_runtime_insert_answer($pdo, 1, 'q_choice', 'b', null);
sr_survey_statistics_runtime_insert_answer($pdo, 2, 'q_choice', 'a,b', null);
sr_survey_statistics_runtime_insert_answer($pdo, 3, 'q_choice', 'a', null);
sr_survey_statistics_runtime_insert_answer($pdo, 4, 'q_choice', 'b', null);
sr_survey_statistics_runtime_insert_answer($pdo, 5, 'q_choice', 'a', null);

sr_survey_statistics_runtime_insert_answer($pdo, 1, 'q_number', null, 3.0);
sr_survey_statistics_runtime_insert_answer($pdo, 2, 'q_number', null, 5.0);
sr_survey_statistics_runtime_insert_answer($pdo, 3, 'q_number', null, 99.0);
sr_survey_statistics_runtime_insert_answer($pdo, 4, 'q_number', null, 1.0);
sr_survey_statistics_runtime_insert_answer($pdo, 5, 'q_number', null, 7.0);

$summary = sr_survey_statistics_summary($pdo, 7);
sr_survey_statistics_runtime_assert((int) ($summary['total_count'] ?? 0) === 3, 'Survey statistics summary must exclude test responses from total count.');
sr_survey_statistics_runtime_assert((int) ($summary['accepted_count'] ?? 0) === 1, 'Survey statistics summary must count accepted non-test responses.');
sr_survey_statistics_runtime_assert((int) ($summary['flagged_count'] ?? 0) === 1, 'Survey statistics summary must count flagged non-test responses.');
sr_survey_statistics_runtime_assert((int) ($summary['excluded_count'] ?? 0) === 1, 'Survey statistics summary must count excluded non-test responses.');
sr_survey_statistics_runtime_assert((int) ($summary['anonymous_count'] ?? 0) === 1, 'Survey statistics summary must count anonymous non-test responses.');

$choiceStats = sr_survey_statistics_choice_counts($pdo, 7);
sr_survey_statistics_runtime_assert((int) ($choiceStats['q_choice']['a'] ?? 0) === 2, 'Survey choice stats must count each response once per choice and include flagged responses.');
sr_survey_statistics_runtime_assert((int) ($choiceStats['q_choice']['b'] ?? 0) === 2, 'Survey choice stats must split comma choice keys and include flagged responses.');
sr_survey_statistics_runtime_assert(!isset($choiceStats['q_choice']['c']), 'Survey choice stats must not invent missing choices.');

$numberStats = sr_survey_statistics_number_stats($pdo, 7);
$number = $numberStats['q_number'] ?? [];
sr_survey_statistics_runtime_assert((int) ($number['answer_count'] ?? 0) === 2, 'Survey number stats must exclude excluded and test responses.');
sr_survey_statistics_runtime_assert(abs((float) ($number['average_value'] ?? 0.0) - 4.0) < 0.0001, 'Survey number stats must average included responses.');
sr_survey_statistics_runtime_assert((float) ($number['min_value'] ?? 0.0) === 3.0, 'Survey number stats must calculate minimum included response.');
sr_survey_statistics_runtime_assert((float) ($number['max_value'] ?? 0.0) === 5.0, 'Survey number stats must calculate maximum included response.');

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo 'survey statistics runtime checks completed.' . PHP_EOL;
