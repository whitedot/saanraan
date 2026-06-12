#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/survey/helpers.php';

$errors = [];

function sr_survey_export_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_survey_export_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_survey_export_runtime_error($message);
    }
}

function sr_survey_export_runtime_ids(array $rows): array
{
    return array_map(static fn (array $row): int => (int) ($row['id'] ?? $row['survey_id'] ?? 0), $rows);
}

function sr_survey_export_runtime_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE sr_survey_forms (
        id INTEGER PRIMARY KEY,
        survey_key TEXT NOT NULL,
        title TEXT NOT NULL,
        questionnaire_version INTEGER NOT NULL DEFAULT 1,
        deleted_at TEXT
    )');
    $pdo->exec('CREATE TABLE sr_survey_questions (
        id INTEGER PRIMARY KEY,
        survey_id INTEGER NOT NULL,
        question_key TEXT NOT NULL,
        question_type TEXT NOT NULL,
        prompt TEXT NOT NULL,
        required INTEGER NOT NULL DEFAULT 1,
        analysis_note TEXT,
        number_min TEXT,
        number_max TEXT,
        scale_points INTEGER,
        nonresponse_policy TEXT NOT NULL DEFAULT "none",
        sort_order INTEGER NOT NULL DEFAULT 0
    )');
    $pdo->exec('CREATE TABLE sr_survey_choices (
        id INTEGER PRIMARY KEY,
        question_id INTEGER NOT NULL,
        choice_key TEXT NOT NULL,
        label TEXT NOT NULL,
        is_other INTEGER NOT NULL DEFAULT 0,
        is_nonresponse INTEGER NOT NULL DEFAULT 0,
        sort_order INTEGER NOT NULL DEFAULT 0
    )');
    $pdo->exec('CREATE TABLE sr_survey_responses (
        id INTEGER PRIMARY KEY,
        survey_id INTEGER NOT NULL,
        account_id INTEGER,
        status TEXT NOT NULL DEFAULT "submitted",
        quality_status TEXT NOT NULL DEFAULT "accepted",
        is_test INTEGER NOT NULL DEFAULT 0,
        submitted_at TEXT NOT NULL,
        rewarded_at TEXT,
        answer_snapshot_json TEXT,
        consent_snapshot_json TEXT,
        metadata_snapshot_json TEXT,
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

function sr_survey_export_runtime_seed(PDO $pdo): void
{
    $pdo->exec("INSERT INTO sr_survey_forms (id, survey_key, title, questionnaire_version, deleted_at) VALUES
        (7, 'survey_a', '=Formula Title', 2, NULL),
        (8, 'survey_deleted', 'Deleted Survey', 1, '2026-06-12 05:20:00')");
    $pdo->exec("INSERT INTO sr_survey_questions (id, survey_id, question_key, question_type, prompt, required, analysis_note, number_min, number_max, scale_points, nonresponse_policy, sort_order) VALUES
        (101, 7, 'q_choice', 'single_choice', 'Pick one', 1, '@note', NULL, NULL, NULL, 'none', 1),
        (102, 7, 'q_text', 'short_text', 'Text', 0, '', NULL, NULL, NULL, 'none', 2),
        (201, 8, 'q_deleted', 'short_text', 'Deleted question', 1, '', NULL, NULL, NULL, 'none', 1)");
    $pdo->exec("INSERT INTO sr_survey_choices (id, question_id, choice_key, label, is_other, is_nonresponse, sort_order) VALUES
        (1001, 101, 'yes', '+Yes', 0, 0, 1),
        (1002, 101, 'no', 'No', 0, 0, 2),
        (2001, 201, 'gone', 'Gone', 0, 0, 1)");
    $stmt = $pdo->prepare(
        'INSERT INTO sr_survey_responses
            (id, survey_id, account_id, status, quality_status, is_test, submitted_at, rewarded_at, answer_snapshot_json, consent_snapshot_json, metadata_snapshot_json, created_at, updated_at)
         VALUES
            (:id, :survey_id, :account_id, "submitted", :quality_status, :is_test, :submitted_at, NULL, :answer_snapshot_json, "{}", "{}", :submitted_at, :submitted_at)'
    );
    foreach ([
        [1, 7, 10, 'accepted', 0, '2026-06-12 05:24:00', '{"safe":true}'],
        [2, 7, null, 'flagged', 0, '2026-06-12 05:23:00', '{"flagged":true}'],
        [3, 7, 11, 'excluded', 0, '2026-06-12 05:22:00', '{"excluded":true}'],
        [4, 7, 12, 'accepted', 1, '2026-06-12 05:21:00', '{"test":true}'],
        [5, 8, 13, 'accepted', 0, '2026-06-12 05:20:00', '{"deleted":true}'],
    ] as [$id, $surveyId, $accountId, $qualityStatus, $isTest, $submittedAt, $snapshot]) {
        $stmt->execute([
            'id' => $id,
            'survey_id' => $surveyId,
            'account_id' => $accountId,
            'quality_status' => $qualityStatus,
            'is_test' => $isTest,
            'submitted_at' => $submittedAt,
            'answer_snapshot_json' => $snapshot,
        ]);
    }
    $answerStmt = $pdo->prepare(
        'INSERT INTO sr_survey_response_answers (response_id, question_id, question_key, choice_id, choice_key, answer_text, answer_number, other_text, answer_snapshot_json, created_at)
         VALUES (:response_id, 101, :question_key, NULL, :choice_key, :answer_text, NULL, :other_text, "{}", "2026-06-12 05:25:00")'
    );
    foreach ([
        [1, 'q_choice', 'yes', '=answer', ''],
        [2, 'q_choice', 'no', 'flagged answer', ''],
        [3, 'q_choice', 'yes', 'excluded answer', ''],
        [4, 'q_choice', 'yes', 'test answer', ''],
        [5, 'q_deleted', 'gone', 'deleted answer', ''],
    ] as [$responseId, $questionKey, $choiceKey, $answerText, $otherText]) {
        $answerStmt->execute([
            'response_id' => $responseId,
            'question_key' => $questionKey,
            'choice_key' => $choiceKey,
            'answer_text' => $answerText,
            'other_text' => $otherText,
        ]);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
sr_survey_export_runtime_schema($pdo);
sr_survey_export_runtime_seed($pdo);

$limits = sr_survey_admin_export_limits();
sr_survey_export_runtime_assert(($limits['raw'] ?? 0) === 5000, 'Survey raw CSV export limit must remain 5000.');
sr_survey_export_runtime_assert(($limits['analysis'] ?? 0) === 20000, 'Survey analysis CSV export limit must remain 20000.');
sr_survey_export_runtime_assert(($limits['codebook'] ?? 0) === 10000, 'Survey codebook CSV export limit must remain 10000.');
sr_survey_export_runtime_assert(sr_survey_csv_cell('=1+1') === "'=1+1", 'Survey CSV cells must escape formula-like equals prefix.');
sr_survey_export_runtime_assert(sr_survey_csv_cell('+1') === "'+1", 'Survey CSV cells must escape formula-like plus prefix.');
sr_survey_export_runtime_assert(sr_survey_csv_cell('-1') === "'-1", 'Survey CSV cells must escape formula-like minus prefix.');
sr_survey_export_runtime_assert(sr_survey_csv_cell('@cmd') === "'@cmd", 'Survey CSV cells must escape formula-like at prefix.');
sr_survey_export_runtime_assert(sr_survey_csv_cell('safe') === 'safe', 'Survey CSV cells must preserve safe text.');

$rawRows = sr_survey_admin_export_raw_rows($pdo, 7, '', false, 5000);
sr_survey_export_runtime_assert(sr_survey_export_runtime_ids($rawRows) === [1, 2, 3], 'Raw survey export must exclude test responses by default and keep newest order.');

$rawWithTestRows = sr_survey_admin_export_raw_rows($pdo, 7, '', true, 5000);
sr_survey_export_runtime_assert(sr_survey_export_runtime_ids($rawWithTestRows) === [1, 2, 3, 4], 'Raw survey export must include test responses only when requested.');

$flaggedRows = sr_survey_admin_export_raw_rows($pdo, 7, 'flagged', false, 5000);
sr_survey_export_runtime_assert(sr_survey_export_runtime_ids($flaggedRows) === [2], 'Raw survey export quality filter must limit rows.');

$analysisRows = sr_survey_admin_export_analysis_rows($pdo, 7, '', false, 20000);
sr_survey_export_runtime_assert(sr_survey_export_runtime_ids($analysisRows) === [1, 2], 'Analysis survey export must exclude test and excluded responses by default.');
sr_survey_export_runtime_assert((string) ($analysisRows[0]['answer_text'] ?? '') === '=answer', 'Analysis survey export must return raw values before CSV cell escaping.');

$analysisWithTestRows = sr_survey_admin_export_analysis_rows($pdo, 7, '', true, 20000);
sr_survey_export_runtime_assert(sr_survey_export_runtime_ids($analysisWithTestRows) === [1, 2, 4], 'Analysis survey export must include test responses only when requested while still excluding excluded responses.');

$codebookRows = sr_survey_admin_export_codebook_rows($pdo, 0, 10000);
sr_survey_export_runtime_assert(count($codebookRows) === 3, 'Codebook export must include active survey questions and choices.');
foreach ($codebookRows as $row) {
    sr_survey_export_runtime_assert((int) ($row['survey_id'] ?? 0) !== 8, 'Codebook export must exclude deleted surveys.');
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo 'survey export runtime checks completed.' . PHP_EOL;
