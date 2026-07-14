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

function sr_quiz_check_file_not_contains(string $path, array $needles): void
{
    $content = file_get_contents($path);
    if (!is_string($content)) {
        sr_quiz_check_error('file cannot be read: ' . $path);
        return;
    }

    foreach ($needles as $needle) {
        if (str_contains($content, $needle)) {
            sr_quiz_check_error($path . ' must not contain legacy marker: ' . $needle);
        }
    }
}

function sr_quiz_check_command(array $command, int $expectedExitCode, array $markers, string $label): void
{
    $parts = [];
    foreach ($command as $part) {
        $parts[] = escapeshellarg($part);
    }

    $output = [];
    exec(implode(' ', $parts) . ' 2>&1', $output, $exitCode);
    $text = implode("\n", $output);
    if ($exitCode !== $expectedExitCode) {
        sr_quiz_check_error($label . ' expected exit ' . (string) $expectedExitCode . ', got ' . (string) $exitCode . ': ' . $text);
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($text, $marker)) {
            sr_quiz_check_error($label . ' output must contain: ' . $marker);
        }
    }
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
        'skin_key VARCHAR(40) NOT NULL DEFAULT \'\'',
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

    sr_quiz_check_file_contains('modules/quiz/module.php', [
        "'version' => '2026.07.002'",
    ]);
    sr_quiz_check_file_contains('modules/quiz/updates/2026.06.012.sql', [
        'ALTER TABLE sr_quiz_sets',
        'ADD COLUMN skin_key VARCHAR(40) NOT NULL DEFAULT \'\'',
    ]);
    sr_quiz_check_file_not_contains('modules/quiz/install.sql', [
        'theme_key',
    ]);
    sr_quiz_check_file_contains('modules/quiz/updates/2026.06.014.sql', [
        'ADD COLUMN reaction_preset_key VARCHAR(80) NOT NULL DEFAULT \'\'',
        'ADD COLUMN reaction_comment_preset_key VARCHAR(80) NOT NULL DEFAULT \'\'',
    ]);
    sr_quiz_check_file_contains('modules/quiz/updates/2026.06.015.sql', [
        'ALTER TABLE sr_quiz_sets',
        'ADD COLUMN cover_image_url VARCHAR(255) NOT NULL DEFAULT \'\'',
    ]);
    sr_quiz_check_file_contains('modules/quiz/updates/2026.06.016.sql', [
        'ADD COLUMN view_count BIGINT UNSIGNED NOT NULL DEFAULT 0',
        'ADD KEY idx_sr_quiz_sets_view_count (view_count, id)',
    ]);
    sr_quiz_check_file_contains('modules/quiz/updates/2026.06.017.sql', [
        "name = '퀴즈·테스트'",
        "version = '2026.06.017'",
    ]);
    sr_quiz_check_file_contains('modules/quiz/updates/2026.06.019.sql', [
        'DELETE FROM {{SR_TABLE_PREFIX}}admin_account_permissions',
        "WHERE menu_path = '/admin/quiz/manual'",
        "version = '2026.06.019'",
    ]);
    sr_quiz_check_file_not_contains('modules/quiz/updates/2026.06.019.sql', [
        'admin_permissions',
        'WHERE path =',
    ]);
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
        'GET /quiz/ui-kit',
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
    $quizPaths = include 'modules/quiz/paths.php';
    if (!is_array($quizPaths)) {
        sr_quiz_check_error('Quiz paths.php must return an array.');
    } else {
        $quizRouteKeys = array_keys($quizPaths);
        $uiKitIndex = array_search('GET /quiz/ui-kit', $quizRouteKeys, true);
        $wildcardIndex = array_search('GET /quiz/*', $quizRouteKeys, true);
        if (!is_int($uiKitIndex) || !is_int($wildcardIndex) || $uiKitIndex > $wildcardIndex) {
            sr_quiz_check_error('Quiz UI kit route must be registered before wildcard public path.');
        }
    }
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
        'sr_quiz_display_settings_for_quiz',
        'sr_quiz_latest_attempt_result',
        '$quizCommentsEnabled && $submitResult !== null',
        'sr_reaction_render_widget($pdo, \'quiz\', \'quiz_set\'',
        '$submitResult !== null',
        '?result=1#quiz-comments',
        "sr_quiz_skin_view_file(\$quizSettings, 'result')",
    ]);
    sr_quiz_check_file_contains('modules/quiz/helpers.php', [
        'deleted_at IS NULL',
        'sr_quiz_attempts',
        'sr_quiz_reward_grants',
        'SELECT 1 FROM sr_quiz_sets WHERE quiz_key = :quiz_key',
        'sr_quiz_key_is_reserved',
        'member_group_keys_json',
        'sr_quiz_display_settings_for_quiz',
        "\$normalized['default_reward_enabled'] = \$normalized['default_reward_provider'] !== 'none';",
        "\$provider === 'none'",
        "\$context['consumer_domain'] = 'quiz';",
        "'/modules/quiz/assets/module.js'",
        'sr_quiz_optional_option_key_from_post',
        "'card' => '카드형'",
        "'focus' => '집중형'",
        'attempt_limit_policy',
        'sr_quiz_admin_reward_grants_for_attempts',
        'a.result_snapshot_json',
        '$rows[$index][\'result_title\']',
    ]);
    sr_quiz_check_file_contains('modules/quiz/helpers/attempts.php', [
        'sr_quiz_valid_source_context',
        'sr_quiz_source_context_is_accessible',
        'sr_content_once_access_already_granted',
        'sr_quiz_score_answers',
        'sr_quiz_account_can_attempt',
        'sr_quiz_lock_quiz_for_attempt',
        'sr_quiz_public_window_is_open',
        'sr_quiz_attempt_answers',
        '\'choice_keys\' => $choiceKeys',
        'count(array_filter($choiceKeys)) === 1',
        'sr_quiz_attempt_display_score',
        '\'display_score\' => $displayScore',
    ]);
    sr_quiz_check_file_contains('modules/quiz/helpers/comments.php', [
        'sr_quiz_create_comment',
        'sr_quiz_create_comment_mention_notifications',
        'sr_quiz_account_has_result',
        'sr_quiz_comments',
    ]);
    sr_quiz_check_file_contains('modules/quiz/helpers/admin.php', [
        'comments_enabled',
        'skin_key = :skin_key',
        'Quiz to update was not found.',
    ]);
    sr_quiz_check_file_contains('modules/quiz/helpers/rewards.php', [
        'sr_quiz_default_reward_providers',
        "'none' => '지급안함'",
        '$lockClause = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === \'sqlite\' ? \'\' : \' FOR UPDATE\';',
        '$dedupeScope === \'per_source\'',
        '$dedupeScope === \'per_attempt\'',
        'sr_quiz_issue_coupon_reward_grant',
        'sr_quiz_refresh_reward_grant_for_retry',
        'sr_quiz_reward_grant_ledger_transaction',
        'sr_quiz_reward_grant_by_id',
        'sr_quiz_reward_grant_reclaim_status',
        'sr_quiz_reclaim_reward_grant',
        'reference_type\' => \'quiz_reward',
    ]);
    sr_quiz_check_file_contains('modules/quiz/helpers/admin.php', [
        "WHERE question_id IN (SELECT id FROM sr_quiz_questions WHERE quiz_id = :quiz_id)",
        "WHERE attempt_id IN (SELECT id FROM sr_quiz_attempts WHERE quiz_id = :quiz_id)",
        "source_title_snapshot = ''",
        "request_snapshot_json = '{}'",
    ]);
    sr_quiz_check_file_contains('modules/quiz/module.php', [
        'member-assets.php',
        'notification-events.php',
    ]);
    sr_quiz_check_file_contains('modules/quiz/reaction-targets.php', [
        'sr_quiz_account_has_result',
        '?result=1',
        'can_write',
    ]);
    sr_quiz_check_file_contains('modules/quiz/actions/admin-quiz.php', [
        'sr_require_csrf()',
        'sr_quiz_admin_quizzes',
        'sr_quiz_save_admin_quiz',
        'name="skin_key"',
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
    sr_quiz_check_file_contains('modules/quiz/views/admin-settings.php', [
        'sr_quiz_default_reward_providers',
        'data-quiz-settings-reward-policy-row',
        'var rewardSelected = ledgerSelected || couponSelected;',
        'setRowsHidden(rewardPolicyRows, !rewardSelected);',
    ]);
    sr_quiz_check_file_contains('modules/quiz/skins/basic/result.php', [
        '$submitResult[\'display_score\']',
    ]);
    sr_quiz_check_file_contains('modules/quiz/actions/comment.php', [
        'sr_quiz_create_comment',
        'sr_quiz_create_comment_mention_notifications',
        'sr_quiz_public_window_is_open',
        'sr_quiz_account_has_result',
        "'?result=1'",
        'sr_quiz_comment_page_for_comment',
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
    sr_quiz_check_file_contains('modules/quiz/url-embed-targets.php', [
        "'target_module' => 'quiz'",
        "'target_type' => 'quiz_set'",
        'sr-quiz-embed',
        'fragment_cache_schema',
    ]);
    sr_quiz_check_file_not_contains('modules/content/actions/view.php', [
        'sr_quiz_content_quizzes',
        '$contentQuizLinks',
    ]);
    sr_quiz_check_file_not_contains('modules/content/views/content.php', [
        'content-quiz-dialog',
        'data-content-quiz-dialog-open',
        'contentQuizLinks',
        'embed=1',
    ]);
    sr_quiz_check_file_not_contains('modules/content/theme/basic/content.php', [
        'content-quiz-dialog',
        'data-content-quiz-dialog-open',
        'contentQuizLinks',
        'embed=1',
    ]);
    sr_quiz_check_file_not_contains('modules/content/theme/basic/assets/module.css', [
        'content-quiz-dialog',
        'content-quiz-link',
        'content-quiz-links',
    ]);
    foreach ([
        'modules/content/theme/sample/assets/theme.css',
        'modules/community/theme/sample/assets/theme.css',
        'modules/quiz/theme/sample/assets/theme.css',
        'modules/survey/theme/sample/assets/theme.css',
    ] as $sampleThemeCss) {
        sr_quiz_check_file_not_contains($sampleThemeCss, [
            'content-quiz-link',
        ]);
    }
    sr_quiz_check_file_not_contains('modules/quiz/helpers.php', [
        'function sr_quiz_content_quizzes',
    ]);
    sr_quiz_check_file_contains('.tools/bin/smoke-quiz-e2e.php', [
        'SR_SMOKE_ALLOW_MUTATION=1',
        'SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1',
        'saanraan quiz E2E smoke refused to run because it creates quiz and attempt data.',
        'saanraan quiz E2E smoke refused to run against a public-looking base URL.',
        'sr_quiz_e2e_requires_public_mutation_override',
        'SR_SMOKE_ADMIN_IDENTIFIER',
        'SR_SMOKE_ADMIN_PASSWORD',
        'sr_quiz_e2e_choice',
        'attempt_limit_policy',
        'per_quiz_once',
        '보상이 지급되었습니다.',
        '응시 제한에 따라 다시 제출할 수 없습니다.',
    ]);
    sr_quiz_check_command(
        [
            'env',
            'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
            'SR_SMOKE_ADMIN_IDENTIFIER=admin',
            'SR_SMOKE_ADMIN_PASSWORD=12341234',
            PHP_BINARY,
            '.tools/bin/smoke-quiz-e2e.php',
        ],
        2,
        [
            'saanraan quiz E2E smoke refused to run because it creates quiz and attempt data.',
            'SR_SMOKE_ALLOW_MUTATION=1',
        ],
        'Quiz E2E smoke mutation guard'
    );
    sr_quiz_check_command(
        [
            'env',
            'SR_SMOKE_ALLOW_MUTATION=1',
            'SR_SMOKE_BASE_URL=https://example.com',
            'SR_SMOKE_ADMIN_IDENTIFIER=admin',
            'SR_SMOKE_ADMIN_PASSWORD=12341234',
            PHP_BINARY,
            '.tools/bin/smoke-quiz-e2e.php',
        ],
        2,
        [
            'saanraan quiz E2E smoke refused to run against a public-looking base URL.',
            'SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1',
        ],
        'Quiz E2E smoke public mutation URL guard'
    );
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

function sr_quiz_check_submission_prg(): void
{
    require_once 'modules/quiz/helpers/attempts.php';
    $_SESSION = [];
    sr_quiz_submission_flash_store(7, ['필수 문항입니다.'], [11 => [101, 102]]);
    $flash = sr_quiz_submission_flash_take(7);
    if (($flash['errors'] ?? []) !== ['필수 문항입니다.'] || ($flash['selected_choice_ids'][11] ?? []) !== [101, 102]) {
        sr_quiz_check_error('Quiz submission flash must preserve validation errors and selected choices across redirect.');
    }
    if (sr_quiz_submission_flash_take(7) !== ['errors' => [], 'selected_choice_ids' => []]) {
        sr_quiz_check_error('Quiz submission flash must be consumed once.');
    }

    foreach ([
        'modules/quiz/theme/basic/view.php',
        'modules/quiz/theme/sample/view.php',
        'modules/quiz/skins/basic/view.php',
    ] as $viewFile) {
        sr_quiz_check_file_contains($viewFile, [
            'sr_quiz_submission_flash_take(',
            'sr_quiz_submission_flash_store(',
            'sr_redirect($quizResultUrl)',
            'sr_redirect($quizNextUrl)',
            "sr_public_feedback_toasts('quiz', '', \$submitErrors)",
            "? ' checked' : ''",
        ]);
    }
}

sr_quiz_check_module_files();
sr_quiz_check_schema();
sr_quiz_check_asset_lookup_contracts();
sr_quiz_check_paths_and_admin();
sr_quiz_check_privacy_contracts();
sr_quiz_check_docs();
sr_quiz_check_submission_prg();

if ($errors !== []) {
    fwrite(STDERR, "quiz consistency checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "quiz consistency checks completed.\n";
