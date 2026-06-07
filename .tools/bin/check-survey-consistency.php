#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_survey_check_read(string $path): string
{
    global $errors;

    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        $errors[] = 'Cannot read file: ' . $path;
        return '';
    }

    return $contents;
}

function sr_survey_check_contains(string $path, string $needle, string $message): void
{
    global $errors;

    $contents = sr_survey_check_read($path);
    if ($contents === '' || !str_contains($contents, $needle)) {
        $errors[] = $message . ': ' . $path;
    }
}

function sr_survey_check_not_contains(string $path, string $needle, string $message): void
{
    global $errors;

    $contents = sr_survey_check_read($path);
    if ($contents !== '' && str_contains($contents, $needle)) {
        $errors[] = $message . ': ' . $path;
    }
}

sr_survey_check_contains(
    'modules/survey/install.sql',
    'CREATE TABLE IF NOT EXISTS sr_survey_reward_grants',
    'Survey reward grants table must exist'
);
sr_survey_check_contains(
    'modules/survey/install.sql',
    'account_id BIGINT UNSIGNED NULL',
    'Survey reward grants account_id must allow privacy cleanup nulling'
);
sr_survey_check_contains(
    'modules/survey/updates/2026.06.004.sql',
    'MODIFY COLUMN account_id BIGINT UNSIGNED NULL',
    'Survey reward grants privacy cleanup schema update must be present'
);
sr_survey_check_contains(
    'modules/survey/privacy-cleanup.php',
    "dedupe_key = CONCAT(\\'anonymized:survey_reward:\\', id)",
    'Survey reward dedupe keys must be anonymized during privacy cleanup'
);
sr_survey_check_contains(
    'modules/quiz/privacy-cleanup.php',
    "dedupe_key = CONCAT(\\'anonymized:quiz_reward:\\', id)",
    'Quiz reward dedupe keys must be anonymized during privacy cleanup'
);

foreach (['answer_snapshot_json', 'consent_snapshot_json', 'metadata_snapshot_json', 'sr_survey_response_answers', "'answers' =>"] as $needle) {
    sr_survey_check_contains(
        'modules/survey/privacy-export.php',
        $needle,
        'Survey privacy export must include response snapshots and answer rows'
    );
}

foreach (['SELECT a.question_key, a.choice_key', 'GROUP BY a.question_key, a.choice_key'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/actions/admin-statistics.php',
        $needle,
        'Survey choice statistics must use stable question and choice keys'
    );
}
foreach (['GROUP BY a.question_id', 'choiceStats[(int)', 'choice_id IS NOT NULL'] as $needle) {
    sr_survey_check_not_contains(
        'modules/survey/actions/admin-statistics.php',
        $needle,
        'Survey choice statistics must not depend on regenerated numeric IDs'
    );
}

foreach ([
    'foreach ($choices as $choice)',
    'sr_survey_other_answers_from_post',
    "'other_text' => (int) (\$choice['is_other'] ?? 0) === 1",
    '기타 답변을 입력해 주세요.',
    'sr_survey_current_user_agent_hash',
    'sr_survey_current_ip_hash',
    'account_id IS NULL',
    'user_agent_hash',
    'ip_hash',
] as $needle) {
    sr_survey_check_contains(
        'modules/survey/helpers.php',
        $needle,
        'Survey response helpers must preserve multi-choice answers and anonymous duplicate checks'
    );
}

foreach (['other_answers[', 'sr-survey-other-input'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/actions/view.php',
        $needle,
        'Survey public form must collect text for selected other choices'
    );
}

foreach ([
    'sr_survey_admin_question_signature',
    '설문지 잠금 상태에서는 문항을 수정할 수 없습니다.',
    '수정할 설문을 찾을 수 없습니다.',
] as $needle) {
    sr_survey_check_contains(
        'modules/survey/actions/admin-surveys.php',
        $needle,
        'Survey admin save/delete validation must remain enforced'
    );
}

sr_survey_check_not_contains(
    'modules/survey/actions/view.php',
    "sr_get_string('reward'",
    'Survey completion page must not trust reward status from query string'
);

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo 'survey consistency checks completed.' . PHP_EOL;
