#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/survey/helpers.php';

$errors = [];

function sr_survey_response_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_survey_response_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_survey_response_runtime_error($message);
    }
}

function sr_survey_response_runtime_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchColumn();
}

function sr_survey_response_runtime_row(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? $row : [];
}

function sr_survey_response_runtime_expect_exception(callable $callback, string $messagePart, string $assertion): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        sr_survey_response_runtime_assert(str_contains($exception->getMessage(), $messagePart), $assertion);
        return;
    }

    sr_survey_response_runtime_error($assertion);
}

function sr_survey_response_runtime_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE sr_survey_forms (
        id INTEGER PRIMARY KEY,
        survey_key TEXT NOT NULL,
        title TEXT NOT NULL,
        research_purpose TEXT,
        target_population TEXT,
        recruitment_method TEXT,
        project_brief TEXT,
        sponsor_name TEXT NOT NULL DEFAULT "",
        research_region TEXT NOT NULL DEFAULT "",
        research_language TEXT NOT NULL DEFAULT "",
        fieldwork_method TEXT NOT NULL DEFAULT "",
        sample_frame TEXT,
        sample_method TEXT NOT NULL DEFAULT "",
        target_sample_size INTEGER,
        quota_policy TEXT,
        response_rate_basis TEXT,
        analysis_plan TEXT,
        weighting_policy TEXT,
        margin_error_note TEXT,
        methodology_disclosure TEXT,
        ethics_note TEXT,
        sensitive_data_policy TEXT,
        recontact_policy TEXT,
        withdrawal_policy TEXT,
        vendor_name TEXT NOT NULL DEFAULT "",
        external_channel_policy TEXT,
        invite_token_policy TEXT,
        qa_status TEXT NOT NULL DEFAULT "unchecked",
        questionnaire_version INTEGER NOT NULL DEFAULT 1,
        estimated_minutes INTEGER,
        organizer_name TEXT NOT NULL DEFAULT "",
        contact_text TEXT NOT NULL DEFAULT "",
        consent_required INTEGER NOT NULL DEFAULT 0,
        consent_text TEXT,
        privacy_notice TEXT,
        anonymous_allowed INTEGER NOT NULL DEFAULT 0,
        login_required INTEGER NOT NULL DEFAULT 1,
        public_listed INTEGER NOT NULL DEFAULT 1,
        robots_policy TEXT NOT NULL DEFAULT "auto",
        status TEXT NOT NULL DEFAULT "draft",
        starts_at TEXT,
        ends_at TEXT,
        response_limit_policy TEXT NOT NULL DEFAULT "per_survey_once",
        response_limit_period_seconds INTEGER,
        member_group_keys_json TEXT,
        comments_enabled INTEGER NOT NULL DEFAULT 0,
        secret_comments_enabled INTEGER NOT NULL DEFAULT 0,
        reward_enabled INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        deleted_at TEXT
    )');
    $pdo->exec('CREATE TABLE sr_survey_questions (
        id INTEGER PRIMARY KEY,
        survey_id INTEGER NOT NULL,
        question_key TEXT NOT NULL,
        question_type TEXT NOT NULL,
        prompt TEXT NOT NULL,
        required INTEGER NOT NULL DEFAULT 1,
        min_choices INTEGER,
        max_choices INTEGER,
        scale_points INTEGER,
        number_min REAL,
        number_max REAL,
        allow_decimal INTEGER NOT NULL DEFAULT 0,
        allow_other INTEGER NOT NULL DEFAULT 0,
        nonresponse_policy TEXT NOT NULL DEFAULT "none",
        sort_order INTEGER NOT NULL DEFAULT 0,
        settings_json TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_survey_choices (
        id INTEGER PRIMARY KEY,
        question_id INTEGER NOT NULL,
        choice_key TEXT NOT NULL,
        label TEXT NOT NULL,
        is_other INTEGER NOT NULL DEFAULT 0,
        is_nonresponse INTEGER NOT NULL DEFAULT 0,
        sort_order INTEGER NOT NULL DEFAULT 0,
        settings_json TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_survey_responses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        survey_id INTEGER NOT NULL,
        account_id INTEGER,
        status TEXT NOT NULL DEFAULT "submitted",
        quality_status TEXT NOT NULL DEFAULT "accepted",
        quality_note TEXT,
        consent_snapshot_json TEXT,
        metadata_snapshot_json TEXT,
        is_test INTEGER NOT NULL DEFAULT 0,
        submitted_at TEXT NOT NULL,
        rewarded_at TEXT,
        answer_snapshot_json TEXT,
        user_agent_hash TEXT,
        ip_hash TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_survey_response_answers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        response_id INTEGER NOT NULL,
        question_id INTEGER,
        question_key TEXT NOT NULL,
        choice_id INTEGER,
        choice_key TEXT,
        answer_text TEXT,
        answer_number REAL,
        other_text TEXT,
        answer_snapshot_json TEXT NOT NULL,
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_survey_reward_policies (
        id INTEGER PRIMARY KEY,
        survey_id INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT "active",
        reward_provider TEXT NOT NULL DEFAULT "ledger_asset",
        reward_module TEXT NOT NULL,
        reward_code TEXT NOT NULL,
        reward_amount INTEGER,
        dedupe_scope TEXT NOT NULL DEFAULT "per_survey",
        sort_order INTEGER NOT NULL DEFAULT 0,
        settings_json TEXT,
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

function sr_survey_response_runtime_seed(PDO $pdo): array
{
    $now = '2026-06-12 05:45:00';
    $pdo->prepare(
        'INSERT INTO sr_survey_forms
            (id, survey_key, title, research_purpose, consent_required, consent_text, privacy_notice, anonymous_allowed,
             login_required, status, response_limit_policy, response_limit_period_seconds, member_group_keys_json,
             reward_enabled, created_at, updated_at)
         VALUES
            (7, "runtime_response_survey", "Runtime Response Survey", "Runtime purpose", 1, "Consent text", "Privacy notice", 1,
             0, "active", "per_survey_once", NULL, "[]", 0, :created_at, :updated_at)'
    )->execute([
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $questionStmt = $pdo->prepare(
        'INSERT INTO sr_survey_questions
            (id, survey_id, question_key, question_type, prompt, required, min_choices, max_choices, scale_points,
             number_min, number_max, allow_decimal, allow_other, nonresponse_policy, sort_order, created_at, updated_at)
         VALUES
            (:id, 7, :question_key, :question_type, :prompt, :required, :min_choices, :max_choices, :scale_points,
             :number_min, :number_max, :allow_decimal, :allow_other, :nonresponse_policy, :sort_order, :created_at, :updated_at)'
    );
    foreach ([
        [101, 'q_single', 'single_choice', 'Single prompt', 1, null, null, null, null, null, 0, 1, 'none', 10],
        [102, 'q_multi', 'multiple_choice', 'Multi prompt', 1, 1, 2, null, null, null, 0, 0, 'none', 20],
        [103, 'q_number', 'number', 'Number prompt', 1, null, null, null, 1.0, 5.0, 0, 0, 'none', 30],
        [104, 'q_text', 'short_text', 'Text prompt', 0, null, null, null, null, null, 0, 0, 'none', 40],
    ] as [$id, $key, $type, $prompt, $required, $minChoices, $maxChoices, $scalePoints, $numberMin, $numberMax, $allowDecimal, $allowOther, $nonresponsePolicy, $sortOrder]) {
        $questionStmt->execute([
            'id' => $id,
            'question_key' => $key,
            'question_type' => $type,
            'prompt' => $prompt,
            'required' => $required,
            'min_choices' => $minChoices,
            'max_choices' => $maxChoices,
            'scale_points' => $scalePoints,
            'number_min' => $numberMin,
            'number_max' => $numberMax,
            'allow_decimal' => $allowDecimal,
            'allow_other' => $allowOther,
            'nonresponse_policy' => $nonresponsePolicy,
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $choiceStmt = $pdo->prepare(
        'INSERT INTO sr_survey_choices
            (id, question_id, choice_key, label, is_other, is_nonresponse, sort_order, created_at, updated_at)
         VALUES
            (:id, :question_id, :choice_key, :label, :is_other, :is_nonresponse, :sort_order, :created_at, :updated_at)'
    );
    foreach ([
        [1001, 101, 'yes', 'Yes', 0, 0, 10],
        [1002, 101, 'other', 'Other', 1, 0, 20],
        [1003, 102, 'a', 'A', 0, 0, 10],
        [1004, 102, 'b', 'B', 0, 0, 20],
        [1005, 102, 'c', 'C', 0, 0, 30],
        [1006, 102, 'refusal', 'Refusal', 0, 1, 40],
    ] as [$id, $questionId, $choiceKey, $label, $isOther, $isNonresponse, $sortOrder]) {
        $choiceStmt->execute([
            'id' => $id,
            'question_id' => $questionId,
            'choice_key' => $choiceKey,
            'label' => $label,
            'is_other' => $isOther,
            'is_nonresponse' => $isNonresponse,
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    return sr_survey_response_runtime_row($pdo, 'SELECT * FROM sr_survey_forms WHERE id = 7');
}

$_SERVER['HTTP_USER_AGENT'] = 'survey-response-runtime';
$_SERVER['REMOTE_ADDR'] = '127.0.0.23';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
sr_survey_response_runtime_schema($pdo);
$survey = sr_survey_response_runtime_seed($pdo);
$questions = sr_survey_questions_with_choices($pdo, 7);

$answers = [
    101 => [1002],
    102 => [1003, 1004],
    103 => '4',
    104 => 'free text',
];
$otherAnswers = [
    101 => [1002 => 'custom other'],
];
$result = sr_survey_submit_response($pdo, $survey, $questions, 0, $answers, [], true, false, $otherAnswers);
$responseId = (int) ($result['response_id'] ?? 0);
sr_survey_response_runtime_assert($responseId === 1, 'Survey response submit must create a response row.');
sr_survey_response_runtime_assert((int) sr_survey_response_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_survey_response_answers WHERE response_id = 1') === 5, 'Survey response submit must create answer rows for each selected choice and scalar answer.');

$response = sr_survey_response_runtime_row($pdo, 'SELECT account_id, consent_snapshot_json, metadata_snapshot_json, answer_snapshot_json, user_agent_hash, ip_hash FROM sr_survey_responses WHERE id = 1');
sr_survey_response_runtime_assert($response !== [], 'Survey response row must exist.');
sr_survey_response_runtime_assert($response['account_id'] === null, 'Anonymous survey response must store NULL account id.');
sr_survey_response_runtime_assert((string) ($response['user_agent_hash'] ?? '') === hash('sha256', 'survey-response-runtime'), 'Anonymous response must store user agent hash.');
sr_survey_response_runtime_assert((string) ($response['ip_hash'] ?? '') === hash('sha256', '127.0.0.23'), 'Anonymous response must store IP hash.');
$answerSnapshot = json_decode((string) ($response['answer_snapshot_json'] ?? '{}'), true);
sr_survey_response_runtime_assert(is_array($answerSnapshot) && (string) ($answerSnapshot['other_answers'][101][1002] ?? '') === 'custom other', 'Survey response snapshot must preserve other answer text.');
$consentSnapshot = json_decode((string) ($response['consent_snapshot_json'] ?? '{}'), true);
sr_survey_response_runtime_assert(is_array($consentSnapshot) && ($consentSnapshot['consent_accepted'] ?? false) === true, 'Survey response must snapshot consent acceptance.');
$metadataSnapshot = json_decode((string) ($response['metadata_snapshot_json'] ?? '{}'), true);
sr_survey_response_runtime_assert(is_array($metadataSnapshot) && (string) ($metadataSnapshot['research_purpose'] ?? '') === 'Runtime purpose', 'Survey response must snapshot research metadata.');

$otherAnswer = sr_survey_response_runtime_row($pdo, 'SELECT choice_key, other_text FROM sr_survey_response_answers WHERE response_id = 1 AND choice_id = 1002');
sr_survey_response_runtime_assert((string) ($otherAnswer['choice_key'] ?? '') === 'other', 'Survey response answer row must store selected other choice key.');
sr_survey_response_runtime_assert((string) ($otherAnswer['other_text'] ?? '') === 'custom other', 'Survey response answer row must store selected other text.');
$numberAnswer = sr_survey_response_runtime_row($pdo, 'SELECT answer_number FROM sr_survey_response_answers WHERE response_id = 1 AND question_key = "q_number"');
sr_survey_response_runtime_assert((float) ($numberAnswer['answer_number'] ?? 0) === 4.0, 'Survey number answer row must store numeric answer.');

sr_survey_response_runtime_expect_exception(
    static fn (): array => sr_survey_submit_response($pdo, $survey, $questions, 0, $answers, [], true, false, $otherAnswers),
    '이미 참여한 설문입니다.',
    'Anonymous duplicate responses must be blocked by user agent and IP hash.'
);

$pdo->exec("UPDATE sr_survey_forms SET response_limit_policy = 'unlimited'");
$surveyUnlimited = $survey;
$surveyUnlimited['response_limit_policy'] = 'unlimited';
$missingOtherAnswers = $answers;
$missingOtherAnswers[103] = '3';
sr_survey_response_runtime_expect_exception(
    static fn (): array => sr_survey_submit_response($pdo, $surveyUnlimited, $questions, 0, $missingOtherAnswers, [], true, false, []),
    '기타 답변을 입력해 주세요.',
    'Selected other choice must require other text.'
);

$tooManyAnswers = $answers;
$tooManyAnswers[101] = [1001];
$tooManyAnswers[102] = [1003, 1004, 1005];
sr_survey_response_runtime_expect_exception(
    static fn (): array => sr_survey_submit_response($pdo, $surveyUnlimited, $questions, 0, $tooManyAnswers, [], true, false, []),
    '최대 선택 수',
    'Multiple choice answer must enforce max choices.'
);

$nonresponseMixedAnswers = $answers;
$nonresponseMixedAnswers[101] = [1001];
$nonresponseMixedAnswers[102] = [1003, 1006];
sr_survey_response_runtime_expect_exception(
    static fn (): array => sr_survey_submit_response($pdo, $surveyUnlimited, $questions, 0, $nonresponseMixedAnswers, [], true, false, []),
    '무응답 선택지',
    'Nonresponse choices must not be combined with normal choices.'
);

$outOfRangeAnswers = $answers;
$outOfRangeAnswers[101] = [1001];
$outOfRangeAnswers[103] = '6';
sr_survey_response_runtime_expect_exception(
    static fn (): array => sr_survey_submit_response($pdo, $surveyUnlimited, $questions, 0, $outOfRangeAnswers, [], true, false, []),
    '최대값',
    'Number answer must enforce maximum value.'
);

if ($errors !== []) {
    fwrite(STDERR, "survey response runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "survey response runtime checks completed.\n";
