#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/survey/helpers.php';

$errors = [];
$srSurveyRewardRuntimeTransactions = [];
$srSurveyRewardRuntimeFailNext = false;

function sr_survey_reward_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_survey_reward_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_survey_reward_runtime_error($message);
    }
}

function sr_survey_reward_runtime_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchColumn();
}

function sr_survey_reward_runtime_transaction(PDO $pdo, array $data): int
{
    global $srSurveyRewardRuntimeTransactions, $srSurveyRewardRuntimeFailNext;

    if ($srSurveyRewardRuntimeFailNext) {
        $srSurveyRewardRuntimeFailNext = false;
        throw new RuntimeException('fixture transaction failed');
    }

    $srSurveyRewardRuntimeTransactions[] = $data;
    return count($srSurveyRewardRuntimeTransactions);
}

function sr_survey_reward_runtime_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE sr_survey_responses (
        id INTEGER PRIMARY KEY,
        survey_id INTEGER NOT NULL,
        account_id INTEGER,
        status TEXT NOT NULL DEFAULT "submitted",
        quality_status TEXT NOT NULL DEFAULT "accepted",
        rewarded_at TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_survey_reward_grants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        survey_id INTEGER NOT NULL,
        response_id INTEGER NOT NULL,
        reward_policy_id INTEGER,
        account_id INTEGER,
        reward_provider TEXT NOT NULL,
        reward_module TEXT NOT NULL,
        reward_code TEXT NOT NULL,
        reward_amount INTEGER,
        dedupe_scope TEXT NOT NULL,
        dedupe_key TEXT NOT NULL UNIQUE,
        status TEXT NOT NULL DEFAULT "pending",
        provider_reference_type TEXT,
        provider_reference_id TEXT,
        request_snapshot_json TEXT,
        result_snapshot_json TEXT,
        error_message TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        granted_at TEXT,
        failed_at TEXT
    )');
}

function sr_survey_reward_runtime_seed_responses(PDO $pdo, string $now): void
{
    $stmt = $pdo->prepare('INSERT INTO sr_survey_responses (id, survey_id, account_id, created_at, updated_at) VALUES (:id, :survey_id, :account_id, :created_at, :updated_at)');
    foreach ([1 => 10, 2 => 10, 3 => 11] as $responseId => $accountId) {
        $stmt->execute([
            'id' => $responseId,
            'survey_id' => 7,
            'account_id' => $accountId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
sr_survey_reward_runtime_schema($pdo);

$now = '2026-06-12 05:00:00';
sr_survey_reward_runtime_seed_responses($pdo, $now);

$survey = [
    'id' => 7,
    'survey_key' => 'runtime_survey',
    'title' => 'Runtime Survey',
];
$assetOptions = [
    'reward' => [
        'transaction_function' => 'sr_survey_reward_runtime_transaction',
        'credit_type' => 'grant',
    ],
];
$policy = [
    'id' => 3,
    'reward_provider' => 'ledger_asset',
    'reward_module' => 'reward',
    'reward_code' => 'survey_reward',
    'reward_amount' => 700,
    'dedupe_scope' => 'per_survey',
];

$first = sr_survey_issue_reward_grant($pdo, $survey, 1, 10, $policy, $assetOptions, $now);
sr_survey_reward_runtime_assert((string) ($first['status'] ?? '') === 'granted', 'First survey reward grant must be granted.');
sr_survey_reward_runtime_assert(count($srSurveyRewardRuntimeTransactions) === 1, 'First survey reward grant must create one transaction.');
sr_survey_reward_runtime_assert((int) sr_survey_reward_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_survey_reward_grants') === 1, 'First survey reward grant must create one grant row.');
sr_survey_reward_runtime_assert((string) sr_survey_reward_runtime_scalar($pdo, 'SELECT rewarded_at FROM sr_survey_responses WHERE id = 1') === $now, 'Granted survey response must be marked rewarded.');
sr_survey_reward_runtime_assert((string) ($srSurveyRewardRuntimeTransactions[0]['reference_type'] ?? '') === 'survey_reward', 'Survey reward transaction reference type must be survey_reward.');
sr_survey_reward_runtime_assert((string) ($srSurveyRewardRuntimeTransactions[0]['reference_id'] ?? '') === (string) (int) ($first['id'] ?? 0), 'Survey reward transaction reference id must point to grant id.');

$sameResponse = sr_survey_issue_reward_grant($pdo, $survey, 1, 10, $policy, $assetOptions, $now);
sr_survey_reward_runtime_assert((string) ($sameResponse['status'] ?? '') === 'granted', 'Repeated same response must return existing granted row.');
sr_survey_reward_runtime_assert(count($srSurveyRewardRuntimeTransactions) === 1, 'Repeated same response must not create another transaction.');

$duplicate = sr_survey_issue_reward_grant($pdo, $survey, 2, 10, $policy, $assetOptions, $now);
sr_survey_reward_runtime_assert((string) ($duplicate['status'] ?? '') === 'duplicate', 'Second response in per_survey scope must be treated as duplicate.');
sr_survey_reward_runtime_assert(count($srSurveyRewardRuntimeTransactions) === 1, 'Duplicate survey response must not create a transaction.');

$retryPolicy = $policy;
$retryPolicy['id'] = 4;
$retryPolicy['dedupe_scope'] = 'per_response';
$srSurveyRewardRuntimeFailNext = true;
$failed = sr_survey_issue_reward_grant($pdo, $survey, 3, 11, $retryPolicy, $assetOptions, $now);
sr_survey_reward_runtime_assert((string) ($failed['status'] ?? '') === 'failed', 'Failed survey reward transaction must leave failed grant.');
sr_survey_reward_runtime_assert((string) ($failed['error_message'] ?? '') !== '', 'Failed survey reward grant must record sanitized error message.');
sr_survey_reward_runtime_assert((string) ($failed['failed_at'] ?? '') === $now, 'Failed survey reward grant must record failed_at.');
sr_survey_reward_runtime_assert(count($srSurveyRewardRuntimeTransactions) === 1, 'Failed survey reward transaction must not be counted as successful transaction.');

$retried = sr_survey_issue_reward_grant($pdo, $survey, 3, 11, $retryPolicy, $assetOptions, $now);
sr_survey_reward_runtime_assert((int) ($retried['id'] ?? 0) === (int) ($failed['id'] ?? 0), 'Survey reward retry must reuse failed grant row.');
sr_survey_reward_runtime_assert((string) ($retried['status'] ?? '') === 'granted', 'Survey reward retry must grant the failed row.');
sr_survey_reward_runtime_assert(($retried['error_message'] ?? null) === null || (string) ($retried['error_message'] ?? '') === '', 'Survey reward retry must clear error_message.');
sr_survey_reward_runtime_assert(($retried['failed_at'] ?? null) === null || (string) ($retried['failed_at'] ?? '') === '', 'Survey reward retry must clear failed_at.');
sr_survey_reward_runtime_assert((string) sr_survey_reward_runtime_scalar($pdo, 'SELECT rewarded_at FROM sr_survey_responses WHERE id = 3') === $now, 'Retried survey response must be marked rewarded.');
sr_survey_reward_runtime_assert(count($srSurveyRewardRuntimeTransactions) === 2, 'Successful survey retry must create exactly one additional transaction.');

$resultSnapshot = json_decode((string) ($retried['result_snapshot_json'] ?? '{}'), true);
sr_survey_reward_runtime_assert(is_array($resultSnapshot) && (int) ($resultSnapshot['transaction_id'] ?? 0) === 2, 'Survey reward retry must store transaction id result snapshot.');

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo 'survey reward runtime checks completed.' . PHP_EOL;
