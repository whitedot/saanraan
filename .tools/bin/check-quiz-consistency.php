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
        '.tools/bin/smoke-quiz-e2e.php',
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
        'CREATE TABLE IF NOT EXISTS sr_quiz_comments',
        'UNIQUE KEY uq_sr_quiz_reward_grants_dedupe',
        'starts_at DATETIME',
        'ends_at DATETIME',
        'attempt_limit_policy VARCHAR(30)',
        'member_group_keys_json LONGTEXT',
        'comments_enabled TINYINT(1)',
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
        'POST /quiz/comment',
        'POST /quiz/comment/edit',
        'POST /quiz/comment/delete',
        'POST /quiz/*',
        'GET /admin/quiz',
        'POST /admin/quiz',
        'GET /admin/quiz/attempts',
        'POST /admin/quiz/attempts',
        'GET /admin/quiz/comments',
        'POST /admin/quiz/comments',
    ]);
    sr_quiz_check_file_contains('modules/quiz/admin-menu.php', [
        '/admin/quiz',
        '/admin/quiz/attempts',
        '/admin/quiz/comments',
    ]);
    sr_quiz_check_file_contains('modules/quiz/skins/basic/view.php', [
        'rawurldecode($quizKey)',
        'sr_quiz_key_is_valid($quizKey)',
        'sr_require_csrf()',
        'sr_quiz_submit_attempt',
        'sr_quiz_asset_options',
        'source_module',
        'source_type',
        'source_id',
        'target="_top"',
        'quiz-comments',
        'sr_quiz_comments',
        'sr_member_mention_plain_text_html',
        'data-sr-mention-input',
    ]);
    sr_quiz_check_file_contains('modules/quiz/helpers.php', [
        'deleted_at IS NULL',
        'sr_quiz_attempts',
        'sr_quiz_attempt_answers',
        'sr_quiz_reward_grants',
        'sr_quiz_comments',
        'SELECT 1 FROM sr_quiz_sets WHERE quiz_key = :quiz_key',
        'sr_quiz_key_is_reserved',
        'sr_quiz_valid_source_context',
        'sr_quiz_source_context_is_accessible',
        'sr_content_once_access_already_granted',
        'sr_quiz_score_answers',
        'sr_quiz_account_can_attempt',
        'sr_quiz_lock_quiz_for_attempt',
        'sr_quiz_public_window_is_open',
        'member_group_keys_json',
        'comments_enabled',
        'sr_quiz_create_comment',
        'sr_quiz_create_comment_mention_notifications',
        'attempt_limit_policy',
        '$lockClause = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === \'sqlite\' ? \'\' : \' FOR UPDATE\';',
        'Quiz to update was not found.',
        '$dedupeScope === \'per_source\'',
        '$dedupeScope === \'per_attempt\'',
        'sr_quiz_issue_coupon_reward_grant',
        'sr_quiz_refresh_reward_grant_for_retry',
        'sr_quiz_reward_grant_ledger_transaction',
        'sr_quiz_reward_grant_by_id',
        'sr_quiz_reward_grant_reclaim_status',
        'sr_quiz_reclaim_reward_grant',
        'sr_quiz_admin_reward_grants_for_attempts',
        'reference_type\' => \'quiz_reward',
        '\'choice_keys\' => $choiceKeys',
        'count(array_filter($choiceKeys)) === 1',
        'sr_quiz_attempt_display_score',
        '\'display_score\' => $displayScore',
        'a.result_snapshot_json',
        '$rows[$index][\'result_title\']',
    ]);
    sr_quiz_check_file_contains('modules/quiz/helpers.php', [
        "WHERE question_id IN (SELECT id FROM sr_quiz_questions WHERE quiz_id = :quiz_id)",
        "WHERE attempt_id IN (SELECT id FROM sr_quiz_attempts WHERE quiz_id = :quiz_id)",
        "source_title_snapshot = ''",
        "request_snapshot_json = '{}'",
    ]);
    sr_quiz_check_file_contains('modules/quiz/module.php', [
        'member-assets.php',
        'notification-events.php',
    ]);
    sr_quiz_check_file_contains('modules/quiz/actions/admin-quiz.php', [
        'sr_require_csrf()',
        'sr_quiz_admin_quizzes',
        'sr_quiz_save_admin_quiz',
        'sr_quiz_soft_delete',
        '저장할 퀴즈를 찾을 수 없습니다.',
        'question_uid[]',
        'starts_at',
        'ends_at',
        'member_group_keys',
        'comments_enabled',
        'attempt_limit_policy',
        'attempt_count',
        'passed_count',
        'reward_module',
        'content_source_ids',
    ]);
    sr_quiz_check_file_contains('modules/quiz/skins/basic/result.php', [
        '$submitResult[\'display_score\']',
    ]);
    sr_quiz_check_file_contains('modules/quiz/actions/comment.php', [
        'sr_quiz_create_comment',
        'sr_quiz_create_comment_mention_notifications',
        'sr_quiz_public_window_is_open',
    ]);
    sr_quiz_check_file_contains('modules/quiz/actions/comment-edit.php', [
        'sr_quiz_update_comment_content',
        'sr_quiz_create_comment_mention_notifications',
        'sr_quiz_account_can_edit_comment',
    ]);
    sr_quiz_check_file_contains('modules/quiz/actions/comment-delete.php', [
        'sr_quiz_update_comment_status',
        'sr_quiz_account_can_delete_comment',
    ]);
    sr_quiz_check_file_contains('modules/quiz/actions/admin-comments.php', [
        '/admin/quiz/comments',
        'sr_quiz_admin_comments',
        'sr_quiz_update_comment_status',
        'sr_member_mention_plain_text_html',
    ]);
    sr_quiz_check_file_contains('modules/quiz/actions/admin-attempts.php', [
        'sr_admin_require_permission($pdo, (int) ($account[\'id\'] ?? 0), \'/admin/quiz/attempts\', \'edit\')',
        'intent !== \'reclaim_reward\'',
        'sr_quiz_reclaim_reward_grant',
        'sr_quiz_admin_reward_grants_for_attempts',
        'admin-quiz-reward-grants',
        'quiz-reward-reclaim-modal-',
        '회수 가능',
        'sr_admin_post_return_url(\'/admin/quiz/attempts\')',
        'sr_admin_feedback_toasts',
        'sr_quiz_reward_grants',
        'grant_status',
        'reward_module',
        'reward_amount',
        'result_title',
        'result_summary',
    ]);
    sr_quiz_check_file_contains('modules/quiz/assets/admin.css', [
        '.admin-quiz-reward-grants',
        '.admin-quiz-reward-grant',
    ]);
    sr_quiz_check_file_contains('modules/quiz/helpers.php', [
        "quiz_q_' . (string) \$index",
        "attempt_q_' . (string) \$index",
        '$keywordWhere[] = $column . \' LIKE :\' . $paramKey',
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
    sr_quiz_check_file_contains('.tools/bin/smoke-quiz-e2e.php', [
        'SR_SMOKE_ADMIN_IDENTIFIER',
        'SR_SMOKE_ADMIN_PASSWORD',
        'sr_quiz_e2e_choice',
        'attempt_limit_policy',
        'per_quiz_once',
        '보상이 지급되었습니다.',
        '응시 제한에 따라 다시 제출할 수 없습니다.',
    ]);
}

function sr_quiz_check_privacy_contracts(): void
{
    sr_quiz_check_file_contains('modules/quiz/privacy-export.php', [
        'sr_quiz_attempts',
        'scoring_snapshot_json',
        'sr_quiz_attempt_result_scores',
        'sr_quiz_reward_grants',
        'sr_quiz_comments',
        'account_id = :account_id',
    ]);
    sr_quiz_check_file_contains('modules/quiz/privacy-cleanup.php', [
        'UPDATE sr_quiz_attempts',
        'UPDATE sr_quiz_reward_grants',
        'UPDATE sr_quiz_comments',
        'account_id = NULL',
        'user_agent_hash = NULL',
        'ip_hash = NULL',
    ]);
}

function sr_quiz_check_docs(): void
{
    sr_quiz_check_file_contains('docs/plans/quiz-reward-module-plan.md', [
        '`content/content_item`과 `community/community_post`',
        '`per_quiz`, `per_source`, `per_attempt`',
        '`ledger_asset` 또는 `coupon`',
        'transaction_lookup_function',
        '포커스 trap',
        '`return_to`',
        '`return_url`',
        'smoke-quiz-e2e.php',
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
