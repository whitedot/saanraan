#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_quiz_check_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_quiz_check_file_contains(string $path, array $needles): string
{
    $content = file_get_contents($path);
    if (!is_string($content)) {
        sr_quiz_check_error('file cannot be read: ' . $path);
        return '';
    }

    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            sr_quiz_check_error($path . ' missing marker: ' . $needle);
        }
    }

    return $content;
}

function sr_quiz_check_module_files(): void
{
    foreach ([
        'modules/quiz/module.php',
        'modules/quiz/install.sql',
        'modules/quiz/paths.php',
        'modules/quiz/admin-menu.php',
        'modules/quiz/privacy-export.php',
        'modules/quiz/privacy-cleanup.php',
    ] as $path) {
        if (!is_file($path)) {
            sr_quiz_check_error('quiz module file is missing: ' . $path);
        }
    }
}

function sr_quiz_check_schema(): void
{
    $sql = sr_quiz_check_file_contains('modules/quiz/install.sql', [
        'CREATE TABLE IF NOT EXISTS sr_quiz_sets',
        'CREATE TABLE IF NOT EXISTS sr_quiz_sources',
        'CREATE TABLE IF NOT EXISTS sr_quiz_questions',
        'CREATE TABLE IF NOT EXISTS sr_quiz_choices',
        'CREATE TABLE IF NOT EXISTS sr_quiz_results',
        'CREATE TABLE IF NOT EXISTS sr_quiz_result_rules',
        'CREATE TABLE IF NOT EXISTS sr_quiz_reward_policies',
        'CREATE TABLE IF NOT EXISTS sr_quiz_attempts',
        'CREATE TABLE IF NOT EXISTS sr_quiz_attempt_answers',
        'CREATE TABLE IF NOT EXISTS sr_quiz_attempt_result_scores',
        'CREATE TABLE IF NOT EXISTS sr_quiz_reward_grants',
        'UNIQUE KEY uq_sr_quiz_reward_grants_dedupe',
        'return_url VARCHAR(255)',
        'source_module VARCHAR(40)',
        'source_type VARCHAR(60)',
        'source_id BIGINT UNSIGNED',
    ]);

    foreach (['scored', 'correct_answer', 'single_choice', 'ledger_asset', 'per_quiz'] as $marker) {
        if (!str_contains($sql, $marker)) {
            sr_quiz_check_error('quiz install.sql missing MVP marker: ' . $marker);
        }
    }
}

function sr_quiz_check_asset_lookup_contracts(): void
{
    sr_quiz_check_file_contains('modules/member/helpers/assets.php', [
        "'transaction_lookup_function' =>",
        '$transactionLookupFunction',
    ]);

    $assets = [
        'point' => 'sr_point_transaction_by_reference',
        'reward' => 'sr_reward_transaction_by_reference',
        'deposit' => 'sr_deposit_transaction_by_reference',
    ];

    foreach ($assets as $moduleKey => $functionName) {
        $contractPath = 'modules/' . $moduleKey . '/member-assets.php';
        $helperPath = 'modules/' . $moduleKey . '/helpers.php';
        sr_quiz_check_file_contains($contractPath, [
            "'transaction_lookup_function' => '" . $functionName . "'",
        ]);
        sr_quiz_check_file_contains($helperPath, [
            'function ' . $functionName,
            'reference_type = :reference_type',
            'reference_id = :reference_id',
        ]);
    }
}

function sr_quiz_check_paths_and_admin(): void
{
    sr_quiz_check_file_contains('modules/quiz/paths.php', [
        'GET /quiz',
        'GET /quiz/*',
        'POST /quiz/*',
        'GET /admin/quiz',
        'POST /admin/quiz',
        'GET /admin/quiz/attempts',
    ]);
    sr_quiz_check_file_contains('modules/quiz/admin-menu.php', [
        '/admin/quiz',
        '/admin/quiz/attempts',
    ]);
    sr_quiz_check_file_contains('modules/quiz/actions/view.php', [
        'rawurldecode($quizKey)',
        'sr_quiz_key_is_valid($quizKey)',
        'sr_require_csrf()',
        'sr_quiz_submit_attempt',
        'sr_quiz_asset_options',
        'source_module',
        'source_type',
        'source_id',
        'target="_top"',
    ]);
    sr_quiz_check_file_contains('modules/quiz/helpers.php', [
        'deleted_at IS NULL',
        'sr_quiz_attempts',
        'sr_quiz_attempt_answers',
        'sr_quiz_reward_grants',
        'sr_quiz_valid_source_context',
        'LIMIT 1 FOR UPDATE',
        '$dedupeScope !== \'per_quiz\'',
        'reference_type\' => \'quiz_reward',
    ]);
    sr_quiz_check_file_contains('modules/quiz/actions/admin-quiz.php', [
        'sr_require_csrf()',
        'sr_quiz_admin_quizzes',
        'sr_quiz_save_admin_quiz',
        'sr_quiz_soft_delete',
        'question_uid[]',
        'reward_module',
        'content_source_ids',
    ]);
    sr_quiz_check_file_contains('modules/content/actions/view.php', [
        'sr_quiz_content_quizzes',
    ]);
    sr_quiz_check_file_contains('modules/content/views/content.php', [
        'content-quiz-dialog',
        'data-content-quiz-dialog-open',
        'return_to',
        'source_module',
        'source_type',
        'source_id',
    ]);
    sr_quiz_check_file_contains('modules/content/assets/public.css', [
        '.content-quiz-dialog',
    ]);
}

function sr_quiz_check_privacy_contracts(): void
{
    sr_quiz_check_file_contains('modules/quiz/privacy-export.php', [
        'sr_quiz_attempts',
        'sr_quiz_reward_grants',
        'account_id = :account_id',
    ]);
    sr_quiz_check_file_contains('modules/quiz/privacy-cleanup.php', [
        'UPDATE sr_quiz_attempts',
        'UPDATE sr_quiz_reward_grants',
        'account_id = NULL',
        'user_agent_hash = NULL',
        'ip_hash = NULL',
    ]);
}

function sr_quiz_check_docs(): void
{
    sr_quiz_check_file_contains('docs/plans/quiz-reward-module-plan.md', [
        'MVP source는 `content/content_item`으로 고정',
        'transaction_lookup_function',
        '포커스 trap',
        '`return_to`',
        '`return_url`',
    ]);
}

sr_quiz_check_module_files();
sr_quiz_check_schema();
sr_quiz_check_asset_lookup_contracts();
sr_quiz_check_paths_and_admin();
sr_quiz_check_privacy_contracts();
sr_quiz_check_docs();

if ($errors !== []) {
    fwrite(STDERR, "quiz consistency checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "quiz consistency checks completed.\n";
