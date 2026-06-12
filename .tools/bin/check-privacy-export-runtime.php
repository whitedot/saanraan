#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

$errors = [];

function sr_privacy_export_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_privacy_export_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_privacy_export_runtime_error($message);
    }
}

function sr_privacy_export_runtime_pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $pdo;
}

function sr_privacy_export_runtime_check_quiz(): void
{
    $export = include 'modules/quiz/privacy-export.php';
    if (!is_callable($export)) {
        sr_privacy_export_runtime_error('quiz privacy export contract is not callable.');
        return;
    }

    $pdo = sr_privacy_export_runtime_pdo();
    $pdo->exec(
        'CREATE TABLE sr_quiz_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            quiz_id INTEGER NOT NULL,
            account_id INTEGER NULL,
            status TEXT NOT NULL,
            source_module TEXT NULL,
            source_type TEXT NULL,
            source_id INTEGER NULL,
            source_title_snapshot TEXT NULL,
            source_url_snapshot TEXT NULL,
            return_url TEXT NULL,
            started_at TEXT NULL,
            submitted_at TEXT NULL,
            scored_at TEXT NULL,
            rewarded_at TEXT NULL,
            total_score INTEGER NULL,
            passed INTEGER NULL,
            selected_result_id INTEGER NULL,
            answer_snapshot_json TEXT NULL,
            scoring_snapshot_json TEXT NULL,
            result_snapshot_json TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_quiz_attempt_answers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            attempt_id INTEGER NOT NULL,
            question_id INTEGER NULL,
            question_key TEXT NOT NULL,
            choice_id INTEGER NULL,
            choice_key TEXT NULL,
            answer_text TEXT NULL,
            answer_snapshot_json TEXT NOT NULL,
            score_awarded INTEGER NULL,
            category_scores_json TEXT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_quiz_attempt_result_scores (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            attempt_id INTEGER NOT NULL,
            result_id INTEGER NULL,
            category_key TEXT NULL,
            score_value INTEGER NOT NULL,
            is_selected INTEGER NOT NULL,
            snapshot_json TEXT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_quiz_reward_grants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            quiz_id INTEGER NOT NULL,
            attempt_id INTEGER NOT NULL,
            reward_policy_id INTEGER NULL,
            source_module TEXT NULL,
            source_type TEXT NULL,
            source_id INTEGER NULL,
            reward_provider TEXT NOT NULL,
            reward_module TEXT NULL,
            reward_code TEXT NULL,
            reward_amount INTEGER NULL,
            dedupe_scope TEXT NOT NULL,
            status TEXT NOT NULL,
            provider_reference_type TEXT NULL,
            provider_reference_id TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            granted_at TEXT NULL,
            failed_at TEXT NULL,
            account_id INTEGER NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_quiz_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            quiz_id INTEGER NOT NULL,
            author_account_id INTEGER NULL,
            author_public_name_snapshot TEXT NOT NULL,
            body_text TEXT NOT NULL,
            is_secret INTEGER NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            deleted_at TEXT NULL
        )'
    );

    $pdo->exec(
        "INSERT INTO sr_quiz_attempts
            (id, quiz_id, account_id, status, source_module, source_type, source_id, source_title_snapshot, source_url_snapshot, return_url, started_at, submitted_at, scored_at, rewarded_at, total_score, passed, selected_result_id, answer_snapshot_json, scoring_snapshot_json, result_snapshot_json, created_at, updated_at)
         VALUES
            (1, 10, 7, 'scored', 'content', 'content_item', 30, 'source title', '/source', '/return', '2026-06-12 00:00:00', '2026-06-12 00:01:00', '2026-06-12 00:02:00', NULL, 80, 1, 3, '{\"answers\":[{\"question_key\":\"q1\"}]}', '{\"total\":80}', '{\"result_key\":\"pass\"}', '2026-06-12 00:00:00', '2026-06-12 00:02:00'),
            (2, 10, 8, 'scored', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 20, 0, NULL, '{}', '{}', '{}', '2026-06-12 00:00:00', '2026-06-12 00:02:00')"
    );
    $pdo->exec(
        "INSERT INTO sr_quiz_attempt_answers
            (attempt_id, question_id, question_key, choice_id, choice_key, answer_text, answer_snapshot_json, score_awarded, category_scores_json, created_at)
         VALUES
            (1, 101, 'q1', 201, 'c1', 'answer text', '{\"label\":\"Question 1\"}', 10, '{\"cat\":10}', '2026-06-12 00:01:00'),
            (2, 102, 'q2', 202, 'c2', 'other answer', '{\"label\":\"Question 2\"}', 5, '{\"cat\":5}', '2026-06-12 00:01:00')"
    );
    $pdo->exec(
        "INSERT INTO sr_quiz_attempt_result_scores
            (attempt_id, result_id, category_key, score_value, is_selected, snapshot_json, created_at)
         VALUES
            (1, 3, 'cat', 80, 1, '{\"title\":\"Pass\"}', '2026-06-12 00:02:00'),
            (2, 4, 'cat', 20, 0, '{\"title\":\"Fail\"}', '2026-06-12 00:02:00')"
    );
    $pdo->exec(
        "INSERT INTO sr_quiz_reward_grants
            (quiz_id, attempt_id, reward_policy_id, source_module, source_type, source_id, reward_provider, reward_module, reward_code, reward_amount, dedupe_scope, status, provider_reference_type, provider_reference_id, created_at, updated_at, granted_at, failed_at, account_id)
         VALUES
            (10, 1, 1, 'content', 'content_item', 30, 'ledger_asset', 'point', 'pass', 100, 'attempt', 'granted', 'quiz_attempt', '1', '2026-06-12 00:02:00', '2026-06-12 00:02:00', '2026-06-12 00:02:00', NULL, 7),
            (10, 2, 1, 'content', 'content_item', 30, 'ledger_asset', 'point', 'pass', 100, 'attempt', 'granted', 'quiz_attempt', '2', '2026-06-12 00:02:00', '2026-06-12 00:02:00', '2026-06-12 00:02:00', NULL, 8)"
    );
    $pdo->exec(
        "INSERT INTO sr_quiz_comments
            (quiz_id, author_account_id, author_public_name_snapshot, body_text, is_secret, status, created_at, updated_at, deleted_at)
         VALUES
            (10, 7, 'name7', 'comment7', 0, 'published', '2026-06-12 00:03:00', '2026-06-12 00:03:00', NULL),
            (10, 8, 'name8', 'comment8', 0, 'published', '2026-06-12 00:03:00', '2026-06-12 00:03:00', NULL)"
    );

    $invalid = $export($pdo, 0);
    foreach (['quiz_attempts', 'quiz_attempt_answers', 'quiz_attempt_result_scores', 'quiz_reward_grants', 'quiz_comments'] as $key) {
        sr_privacy_export_runtime_assert(isset($invalid[$key]) && $invalid[$key] === [], 'quiz export invalid account result must include empty key: ' . $key);
    }

    $result = $export($pdo, 7);
    sr_privacy_export_runtime_assert(count($result['quiz_attempts'] ?? []) === 1, 'quiz export must include only target account attempts.');
    sr_privacy_export_runtime_assert(count($result['quiz_attempt_answers'] ?? []) === 1, 'quiz export must include target account detailed answers.');
    sr_privacy_export_runtime_assert(count($result['quiz_attempt_result_scores'] ?? []) === 1, 'quiz export must include target account result scores.');
    sr_privacy_export_runtime_assert(count($result['quiz_reward_grants'] ?? []) === 1, 'quiz export must include target account reward grants.');
    sr_privacy_export_runtime_assert(count($result['quiz_comments'] ?? []) === 1, 'quiz export must include target account comments.');

    $attempt = $result['quiz_attempts'][0] ?? [];
    $answer = $result['quiz_attempt_answers'][0] ?? [];
    $score = $result['quiz_attempt_result_scores'][0] ?? [];
    sr_privacy_export_runtime_assert(($attempt['answer_snapshot']['answers'][0]['question_key'] ?? '') === 'q1', 'quiz export must decode attempt answer snapshot JSON.');
    sr_privacy_export_runtime_assert(($attempt['scoring_snapshot']['total'] ?? null) === 80, 'quiz export must decode attempt scoring snapshot JSON.');
    sr_privacy_export_runtime_assert(($attempt['result_snapshot']['result_key'] ?? '') === 'pass', 'quiz export must decode attempt result snapshot JSON.');
    sr_privacy_export_runtime_assert(!array_key_exists('answer_snapshot_json', $attempt), 'quiz export must not expose raw attempt answer_snapshot_json after decoding.');
    sr_privacy_export_runtime_assert(($answer['answer_snapshot']['label'] ?? '') === 'Question 1', 'quiz export must decode detailed answer snapshot JSON.');
    sr_privacy_export_runtime_assert(($answer['category_scores']['cat'] ?? null) === 10, 'quiz export must decode detailed answer category scores JSON.');
    sr_privacy_export_runtime_assert(($score['snapshot']['title'] ?? '') === 'Pass', 'quiz export must decode result score snapshot JSON.');
    sr_privacy_export_runtime_assert((int) ($answer['attempt_id'] ?? 0) === 1, 'quiz export must keep detailed answer attempt id.');
}

function sr_privacy_export_runtime_check_survey(): void
{
    $export = include 'modules/survey/privacy-export.php';
    if (!is_callable($export)) {
        sr_privacy_export_runtime_error('survey privacy export contract is not callable.');
        return;
    }

    $pdo = sr_privacy_export_runtime_pdo();
    $pdo->exec(
        'CREATE TABLE sr_survey_forms (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            survey_key TEXT NOT NULL,
            title TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_survey_responses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            survey_id INTEGER NOT NULL,
            account_id INTEGER NULL,
            status TEXT NOT NULL,
            quality_status TEXT NOT NULL,
            quality_note TEXT NULL,
            consent_snapshot_json TEXT NULL,
            metadata_snapshot_json TEXT NULL,
            answer_snapshot_json TEXT NULL,
            submitted_at TEXT NOT NULL,
            rewarded_at TEXT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_survey_response_answers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            response_id INTEGER NOT NULL,
            question_id INTEGER NULL,
            question_key TEXT NOT NULL,
            choice_id INTEGER NULL,
            choice_key TEXT NULL,
            answer_text TEXT NULL,
            answer_number TEXT NULL,
            other_text TEXT NULL,
            answer_snapshot_json TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_survey_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            survey_id INTEGER NOT NULL,
            author_account_id INTEGER NULL,
            author_public_name_snapshot TEXT NOT NULL,
            body_text TEXT NOT NULL,
            is_secret INTEGER NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            deleted_at TEXT NULL
        )'
    );

    $pdo->exec("INSERT INTO sr_survey_forms (id, survey_key, title) VALUES (10, 'survey_a', 'Survey A')");
    $pdo->exec(
        "INSERT INTO sr_survey_responses
            (id, survey_id, account_id, status, quality_status, quality_note, consent_snapshot_json, metadata_snapshot_json, answer_snapshot_json, submitted_at, rewarded_at)
         VALUES
            (1, 10, 7, 'submitted', 'accepted', '', '{\"version\":\"v1\"}', '{\"ip\":\"masked\"}', '{\"answers\":1}', '2026-06-12 00:00:00', NULL),
            (2, 10, 8, 'submitted', 'accepted', '', '{}', '{}', '{}', '2026-06-12 00:00:00', NULL)"
    );
    $pdo->exec(
        "INSERT INTO sr_survey_response_answers
            (response_id, question_id, question_key, choice_id, choice_key, answer_text, answer_number, other_text, answer_snapshot_json, created_at)
         VALUES
            (1, 101, 'q1', 201, 'c1', 'answer text', '5', 'other', '{\"label\":\"Question 1\"}', '2026-06-12 00:01:00'),
            (2, 102, 'q2', 202, 'c2', 'other answer', '3', '', '{\"label\":\"Question 2\"}', '2026-06-12 00:01:00')"
    );
    $pdo->exec(
        "INSERT INTO sr_survey_comments
            (survey_id, author_account_id, author_public_name_snapshot, body_text, is_secret, status, created_at, updated_at, deleted_at)
         VALUES
            (10, 7, 'name7', 'comment7', 0, 'published', '2026-06-12 00:02:00', '2026-06-12 00:02:00', NULL),
            (10, 8, 'name8', 'comment8', 0, 'published', '2026-06-12 00:02:00', '2026-06-12 00:02:00', NULL)"
    );

    $invalid = $export($pdo, 0);
    sr_privacy_export_runtime_assert($invalid === [], 'survey export must return an empty array for invalid account id.');

    $result = $export($pdo, 7);
    sr_privacy_export_runtime_assert(($result[0]['key'] ?? '') === 'survey.responses', 'survey export must include responses section.');
    sr_privacy_export_runtime_assert(($result[1]['key'] ?? '') === 'survey.comments', 'survey export must include comments section.');
    sr_privacy_export_runtime_assert(count($result[0]['rows'] ?? []) === 1, 'survey export must include only target account responses.');
    sr_privacy_export_runtime_assert(count($result[1]['rows'] ?? []) === 1, 'survey export must include only target account comments.');
    $response = $result[0]['rows'][0] ?? [];
    sr_privacy_export_runtime_assert(($response['consent_snapshot']['version'] ?? '') === 'v1', 'survey export must decode consent snapshot JSON.');
    sr_privacy_export_runtime_assert(($response['answer_snapshot']['answers'] ?? null) === 1, 'survey export must decode response answer snapshot JSON.');
    sr_privacy_export_runtime_assert(($response['answers'][0]['answer_snapshot']['label'] ?? '') === 'Question 1', 'survey export must include detailed answers with decoded snapshots.');
}

function sr_privacy_export_runtime_check_content(): void
{
    $export = include 'modules/content/privacy-export.php';
    if (!is_callable($export)) {
        sr_privacy_export_runtime_error('content privacy export contract is not callable.');
        return;
    }

    $pdo = sr_privacy_export_runtime_pdo();
    $pdo->exec('CREATE TABLE sr_content_items (id INTEGER PRIMARY KEY, slug TEXT NOT NULL, title TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_content_groups (id INTEGER PRIMARY KEY, group_key TEXT NOT NULL, title TEXT NOT NULL)');
    $pdo->exec(
        'CREATE TABLE sr_content_access_entitlements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            content_id INTEGER NOT NULL,
            subject_type TEXT NOT NULL,
            subject_id INTEGER NOT NULL,
            access_kind TEXT NOT NULL,
            source_kind TEXT NOT NULL,
            source_asset_module TEXT NOT NULL,
            source_charge_policy TEXT NOT NULL,
            source_reference TEXT NOT NULL,
            granted_at TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_content_asset_access_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NOT NULL,
            account_id INTEGER NOT NULL,
            asset_module TEXT NOT NULL,
            transaction_id INTEGER NOT NULL,
            reference_type TEXT NOT NULL,
            reference_id TEXT NOT NULL,
            access_kind TEXT NOT NULL,
            charge_policy TEXT NOT NULL,
            amount INTEGER NOT NULL,
            settlement_amount INTEGER NOT NULL,
            settlement_currency TEXT NOT NULL,
            purchase_power_snapshot_json TEXT NOT NULL,
            group_policy_snapshot_json TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_content_asset_action_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NOT NULL,
            account_id INTEGER NOT NULL,
            asset_module TEXT NOT NULL,
            transaction_id INTEGER NOT NULL,
            reference_type TEXT NOT NULL,
            reference_id TEXT NOT NULL,
            action_key TEXT NOT NULL,
            direction TEXT NOT NULL,
            amount INTEGER NOT NULL,
            settlement_amount INTEGER NOT NULL,
            settlement_currency TEXT NOT NULL,
            purchase_power_snapshot_json TEXT NOT NULL,
            group_policy_snapshot_json TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec('CREATE TABLE sr_content_files (id INTEGER PRIMARY KEY, title TEXT NOT NULL, original_name TEXT NOT NULL)');
    $pdo->exec(
        'CREATE TABLE sr_content_file_download_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NOT NULL,
            file_id INTEGER NOT NULL,
            account_id INTEGER NOT NULL,
            download_type TEXT NOT NULL,
            charge_policy TEXT NOT NULL,
            asset_module TEXT NOT NULL,
            amount INTEGER NOT NULL,
            asset_access_log_ids_json TEXT NOT NULL,
            refund_status TEXT NOT NULL,
            refund_transaction_ids_json TEXT NOT NULL,
            refund_note TEXT NOT NULL,
            refunded_by_account_id INTEGER NULL,
            refunded_at TEXT NULL,
            access_revoked_at TEXT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_content_submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NULL,
            content_group_id INTEGER NOT NULL,
            author_account_id INTEGER NOT NULL,
            slug TEXT NOT NULL,
            title TEXT NOT NULL,
            summary TEXT NOT NULL,
            body_text TEXT NOT NULL,
            body_format TEXT NOT NULL,
            review_status TEXT NOT NULL,
            publish_target_status TEXT NOT NULL,
            review_note TEXT NOT NULL,
            reviewed_by INTEGER NULL,
            reviewed_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_content_author_applications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            application_note TEXT NOT NULL,
            status TEXT NOT NULL,
            review_note TEXT NOT NULL,
            reviewed_by INTEGER NULL,
            reviewed_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_content_author_reward_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            submission_id INTEGER NOT NULL,
            content_id INTEGER NOT NULL,
            author_account_id INTEGER NOT NULL,
            asset_module TEXT NOT NULL,
            amount INTEGER NOT NULL,
            transaction_id INTEGER NOT NULL,
            status TEXT NOT NULL,
            failure_reason TEXT NOT NULL,
            created_by_account_id INTEGER NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_content_series (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            series_key TEXT NOT NULL,
            title TEXT NOT NULL,
            description TEXT NOT NULL,
            status TEXT NOT NULL,
            visibility TEXT NOT NULL,
            sort_order INTEGER NOT NULL,
            created_by INTEGER NOT NULL,
            updated_by INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_content_series_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            series_id INTEGER NOT NULL,
            content_id INTEGER NOT NULL,
            active_content_id INTEGER NOT NULL,
            episode_label TEXT NOT NULL,
            item_status TEXT NOT NULL,
            sort_order INTEGER NOT NULL,
            created_by INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_content_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NOT NULL,
            parent_comment_id INTEGER NULL,
            thread_root_id INTEGER NOT NULL,
            depth INTEGER NOT NULL,
            author_account_id INTEGER NOT NULL,
            author_public_name_snapshot TEXT NOT NULL,
            body_text TEXT NOT NULL,
            is_secret INTEGER NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );

    $pdo->exec("INSERT INTO sr_content_items (id, slug, title) VALUES (10, 'post-a', 'Post A'), (20, 'post-b', 'Post B')");
    $pdo->exec("INSERT INTO sr_content_groups (id, group_key, title) VALUES (1, 'group_a', 'Group A')");
    $pdo->exec("INSERT INTO sr_content_access_entitlements (account_id, content_id, subject_type, subject_id, access_kind, source_kind, source_asset_module, source_charge_policy, source_reference, granted_at, created_at) VALUES (7, 10, 'content', 10, 'view', 'asset', 'point', 'once', 'ref7', '', ''), (8, 20, 'content', 20, 'view', 'asset', 'point', 'once', 'ref8', '', '')");
    $pdo->exec("INSERT INTO sr_content_asset_access_logs (content_id, account_id, asset_module, transaction_id, reference_type, reference_id, access_kind, charge_policy, amount, settlement_amount, settlement_currency, purchase_power_snapshot_json, group_policy_snapshot_json, created_at) VALUES (10, 7, 'point', 101, 'content', '10', 'view', 'once', 100, 100, 'KRW', '{\"asset_units\":100,\"settlement_units\":100,\"settlement_currency\":\"KRW\",\"currency_min_unit\":1}', '{}', ''), (20, 8, 'point', 102, 'content', '20', 'view', 'once', 200, 200, 'KRW', '{}', '{}', '')");
    $pdo->exec("INSERT INTO sr_content_asset_action_logs (content_id, account_id, asset_module, transaction_id, reference_type, reference_id, action_key, direction, amount, settlement_amount, settlement_currency, purchase_power_snapshot_json, group_policy_snapshot_json, created_at) VALUES (10, 7, 'point', 201, 'content', '10', 'download', 'debit', 50, 50, 'KRW', '{\"asset_units\":50,\"settlement_units\":50,\"settlement_currency\":\"KRW\",\"currency_min_unit\":1}', '{}', ''), (20, 8, 'point', 202, 'content', '20', 'download', 'debit', 60, 60, 'KRW', '{}', '{}', '')");
    $pdo->exec("INSERT INTO sr_content_files (id, title, original_name) VALUES (5, 'File A', 'a.pdf'), (6, 'File B', 'b.pdf')");
    $pdo->exec("INSERT INTO sr_content_file_download_logs (content_id, file_id, account_id, download_type, charge_policy, asset_module, amount, asset_access_log_ids_json, refund_status, refund_transaction_ids_json, refund_note, refunded_by_account_id, refunded_at, access_revoked_at, created_at) VALUES (10, 5, 7, 'paid', 'once', 'point', 50, '[1]', 'none', '[]', '', NULL, NULL, NULL, ''), (20, 6, 8, 'paid', 'once', 'point', 60, '[2]', 'none', '[]', '', NULL, NULL, NULL, '')");
    $pdo->exec("INSERT INTO sr_content_submissions (content_id, content_group_id, author_account_id, slug, title, summary, body_text, body_format, review_status, publish_target_status, review_note, reviewed_by, reviewed_at, created_at, updated_at) VALUES (10, 1, 7, 'sub-a', 'Sub A', 'sum', 'body', 'html', 'approved', 'published', '', NULL, NULL, '', ''), (20, 1, 8, 'sub-b', 'Sub B', 'sum', 'body', 'html', 'approved', 'published', '', NULL, NULL, '', '')");
    $pdo->exec("INSERT INTO sr_content_author_applications (account_id, application_note, status, review_note, reviewed_by, reviewed_at, created_at, updated_at) VALUES (7, 'apply7', 'approved', '', NULL, NULL, '', ''), (8, 'apply8', 'approved', '', NULL, NULL, '', '')");
    $pdo->exec("INSERT INTO sr_content_author_reward_logs (submission_id, content_id, author_account_id, asset_module, amount, transaction_id, status, failure_reason, created_by_account_id, created_at, updated_at) VALUES (1, 10, 7, 'point', 30, 301, 'granted', '', NULL, '', ''), (2, 20, 8, 'point', 40, 302, 'granted', '', NULL, '', '')");
    $pdo->exec("INSERT INTO sr_content_series (series_key, title, description, status, visibility, sort_order, created_by, updated_by, created_at, updated_at) VALUES ('s7', 'Series 7', '', 'active', 'public', 0, 7, 7, '', ''), ('s8', 'Series 8', '', 'active', 'public', 0, 8, 8, '', '')");
    $pdo->exec("INSERT INTO sr_content_series_items (series_id, content_id, active_content_id, episode_label, item_status, sort_order, created_by, created_at, updated_at) VALUES (1, 10, 10, 'E1', 'active', 0, 7, '', ''), (2, 20, 20, 'E2', 'active', 0, 8, '', '')");
    $pdo->exec("INSERT INTO sr_content_comments (content_id, parent_comment_id, thread_root_id, depth, author_account_id, author_public_name_snapshot, body_text, is_secret, status, created_at, updated_at) VALUES (10, NULL, 1, 1, 7, 'name7', 'comment7', 1, 'published', '', ''), (20, NULL, 2, 1, 8, 'name8', 'comment8', 0, 'published', '', '')");

    $invalid = $export($pdo, 0);
    foreach (['access_entitlements', 'asset_access_logs', 'file_download_logs', 'asset_action_logs', 'submissions', 'author_applications', 'author_reward_logs', 'comments', 'series', 'series_items'] as $key) {
        sr_privacy_export_runtime_assert(isset($invalid[$key]) && $invalid[$key] === [], 'content export invalid account result must include empty key: ' . $key);
    }

    $result = $export($pdo, 7);
    foreach (['access_entitlements', 'asset_access_logs', 'file_download_logs', 'asset_action_logs', 'submissions', 'author_applications', 'author_reward_logs', 'comments', 'series', 'series_items'] as $key) {
        sr_privacy_export_runtime_assert(count($result[$key] ?? []) === 1, 'content export must include one target row for: ' . $key);
    }
    sr_privacy_export_runtime_assert(($result['access_entitlements'][0]['source_reference'] ?? '') === 'ref7', 'content export must include target entitlement source reference.');
    sr_privacy_export_runtime_assert(($result['asset_access_logs'][0]['settlement_summary']['purchase_power']['asset_units'] ?? null) === 100, 'content export must decode access log purchase power snapshot.');
    sr_privacy_export_runtime_assert(($result['asset_action_logs'][0]['settlement_summary']['purchase_power']['asset_units'] ?? null) === 50, 'content export must decode action log purchase power snapshot.');
    sr_privacy_export_runtime_assert(($result['file_download_logs'][0]['original_name'] ?? '') === 'a.pdf', 'content export must include joined file original name.');
    sr_privacy_export_runtime_assert(($result['comments'][0]['author_public_name_snapshot'] ?? '') === 'name7', 'content export must include comment author public name snapshot.');
    sr_privacy_export_runtime_assert((int) ($result['comments'][0]['is_secret'] ?? -1) === 1, 'content export must include comment secret flag.');
}

function sr_privacy_export_runtime_check_community(): void
{
    $export = include 'modules/community/privacy-export.php';
    if (!is_callable($export)) {
        sr_privacy_export_runtime_error('community privacy export contract is not callable.');
        return;
    }

    $pdo = sr_privacy_export_runtime_pdo();
    $pdo->exec('CREATE TABLE sr_community_categories (id INTEGER PRIMARY KEY, board_id INTEGER NOT NULL, category_key TEXT NOT NULL, title TEXT NOT NULL)');
    $pdo->exec(
        'CREATE TABLE sr_community_posts (
            id INTEGER PRIMARY KEY,
            board_id INTEGER NOT NULL,
            category_id INTEGER NULL,
            author_account_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            author_public_name_snapshot TEXT NOT NULL,
            body_text TEXT NOT NULL,
            body_format TEXT NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_community_comments (
            id INTEGER PRIMARY KEY,
            post_id INTEGER NOT NULL,
            parent_comment_id INTEGER NULL,
            thread_root_id INTEGER NOT NULL,
            depth INTEGER NOT NULL,
            author_account_id INTEGER NOT NULL,
            author_public_name_snapshot TEXT NOT NULL,
            body_text TEXT NOT NULL,
            is_secret INTEGER NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec('CREATE TABLE sr_community_attachments (id INTEGER PRIMARY KEY, post_id INTEGER NOT NULL, uploader_account_id INTEGER NOT NULL, original_name TEXT NOT NULL, mime_type TEXT NOT NULL, size_bytes INTEGER NOT NULL, width INTEGER NULL, height INTEGER NULL, status TEXT NOT NULL, created_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_reports (id INTEGER PRIMARY KEY, reporter_account_id INTEGER NOT NULL, reported_account_id INTEGER NULL, target_type TEXT NOT NULL, target_id INTEGER NOT NULL, reason_key TEXT NOT NULL, memo_text TEXT NOT NULL, status TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_messages (id INTEGER PRIMARY KEY, sender_account_id INTEGER NOT NULL, recipient_account_id INTEGER NOT NULL, body_text TEXT NOT NULL, status TEXT NOT NULL, read_at TEXT NULL, sender_deleted_at TEXT NULL, recipient_deleted_at TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_scraps (id INTEGER PRIMARY KEY, account_id INTEGER NOT NULL, post_id INTEGER NOT NULL, created_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_series_scraps (id INTEGER PRIMARY KEY, account_id INTEGER NOT NULL, series_id INTEGER NOT NULL, created_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_series (id INTEGER PRIMARY KEY, board_id INTEGER NOT NULL, owner_account_id INTEGER NOT NULL, title TEXT NOT NULL, description TEXT NOT NULL, status TEXT NOT NULL, visibility TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_series_items (id INTEGER PRIMARY KEY, series_id INTEGER NOT NULL, post_id INTEGER NOT NULL, active_post_id INTEGER NOT NULL, episode_label TEXT NOT NULL, item_status TEXT NOT NULL, sort_order INTEGER NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_account_levels (account_id INTEGER PRIMARY KEY, level_value INTEGER NOT NULL, score_value INTEGER NOT NULL, post_count INTEGER NOT NULL, comment_count INTEGER NOT NULL, evaluated_at TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_level_logs (id INTEGER PRIMARY KEY, account_id INTEGER NOT NULL, old_level_value INTEGER NOT NULL, new_level_value INTEGER NOT NULL, old_score_value INTEGER NOT NULL, new_score_value INTEGER NOT NULL, reason_key TEXT NOT NULL, created_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_access_entitlements (id INTEGER PRIMARY KEY, account_id INTEGER NOT NULL, subject_type TEXT NOT NULL, subject_id INTEGER NOT NULL, event_key TEXT NOT NULL, source_kind TEXT NOT NULL, source_asset_module TEXT NOT NULL, source_charge_policy TEXT NOT NULL, source_reference TEXT NOT NULL, granted_at TEXT NOT NULL, created_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_asset_logs (id INTEGER PRIMARY KEY, account_id INTEGER NOT NULL, asset_module TEXT NOT NULL, transaction_id INTEGER NOT NULL, reference_type TEXT NOT NULL, reference_id TEXT NOT NULL, subject_type TEXT NOT NULL, subject_id INTEGER NOT NULL, event_key TEXT NOT NULL, direction TEXT NOT NULL, charge_policy TEXT NOT NULL, amount INTEGER NOT NULL, settlement_amount INTEGER NOT NULL, settlement_currency TEXT NOT NULL, purchase_power_snapshot_json TEXT NOT NULL, group_policy_snapshot_json TEXT NOT NULL, created_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_publisher_reward_logs (id INTEGER PRIMARY KEY, charge_asset_log_id INTEGER NOT NULL, charge_transaction_id INTEGER NOT NULL, reward_transaction_id INTEGER NOT NULL, reversal_transaction_id INTEGER NULL, post_id INTEGER NOT NULL, attachment_id INTEGER NOT NULL, downloader_account_id INTEGER NOT NULL, publisher_account_id INTEGER NOT NULL, asset_module TEXT NOT NULL, charge_amount INTEGER NOT NULL, reward_rate INTEGER NOT NULL, reward_amount INTEGER NOT NULL, status TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_submission_consents (id INTEGER PRIMARY KEY, board_id INTEGER NOT NULL, subject_type TEXT NOT NULL, subject_id INTEGER NOT NULL, action_key TEXT NOT NULL, account_id INTEGER NOT NULL, consent_title_snapshot TEXT NOT NULL, consent_body_snapshot TEXT NOT NULL, consent_version_snapshot TEXT NOT NULL, consent_required INTEGER NOT NULL, consent_accepted INTEGER NOT NULL, ip_hash TEXT NOT NULL, user_agent_hash TEXT NOT NULL, created_at TEXT NOT NULL)');

    $pdo->exec("INSERT INTO sr_community_categories (id, board_id, category_key, title) VALUES (1, 1, 'cat', 'Category')");
    $pdo->exec("INSERT INTO sr_community_posts (id, board_id, category_id, author_account_id, title, author_public_name_snapshot, body_text, body_format, status, created_at, updated_at) VALUES (10, 1, 1, 7, 'Post 7', 'post-name7', 'body7', 'html', 'published', '', ''), (20, 1, 1, 8, 'Post 8', 'post-name8', 'body8', 'html', 'published', '', '')");
    $pdo->exec("INSERT INTO sr_community_comments (id, post_id, parent_comment_id, thread_root_id, depth, author_account_id, author_public_name_snapshot, body_text, is_secret, status, created_at, updated_at) VALUES (100, 10, NULL, 100, 1, 7, 'comment-name7', 'comment7', 1, 'published', '', ''), (200, 20, NULL, 200, 1, 8, 'comment-name8', 'comment8', 0, 'published', '', '')");
    $pdo->exec("INSERT INTO sr_community_attachments (id, post_id, uploader_account_id, original_name, mime_type, size_bytes, width, height, status, created_at) VALUES (1, 10, 7, 'a.png', 'image/png', 123, 10, 10, 'active', ''), (2, 20, 8, 'b.png', 'image/png', 234, 20, 20, 'active', '')");
    $pdo->exec("INSERT INTO sr_community_reports (id, reporter_account_id, reported_account_id, target_type, target_id, reason_key, memo_text, status, created_at, updated_at) VALUES (1, 7, 8, 'post', 20, 'spam', 'memo', 'open', '', ''), (2, 8, 7, 'post', 10, 'spam', 'memo', 'open', '', '')");
    $pdo->exec("INSERT INTO sr_community_messages (id, sender_account_id, recipient_account_id, body_text, status, read_at, sender_deleted_at, recipient_deleted_at, created_at, updated_at) VALUES (1, 7, 8, 'sent', 'sent', NULL, NULL, NULL, '', ''), (2, 8, 7, 'received', 'sent', NULL, NULL, NULL, '', '')");
    $pdo->exec("INSERT INTO sr_community_scraps (id, account_id, post_id, created_at) VALUES (1, 7, 10, ''), (2, 8, 20, '')");
    $pdo->exec("INSERT INTO sr_community_series_scraps (id, account_id, series_id, created_at) VALUES (1, 7, 1, ''), (2, 8, 2, '')");
    $pdo->exec("INSERT INTO sr_community_series (id, board_id, owner_account_id, title, description, status, visibility, created_at, updated_at) VALUES (1, 1, 7, 'Series 7', '', 'active', 'public', '', ''), (2, 1, 8, 'Series 8', '', 'active', 'public', '', '')");
    $pdo->exec("INSERT INTO sr_community_series_items (id, series_id, post_id, active_post_id, episode_label, item_status, sort_order, created_at, updated_at) VALUES (1, 1, 10, 10, 'E1', 'active', 0, '', ''), (2, 2, 20, 20, 'E2', 'active', 0, '', '')");
    $pdo->exec("INSERT INTO sr_community_account_levels (account_id, level_value, score_value, post_count, comment_count, evaluated_at, created_at, updated_at) VALUES (7, 3, 100, 1, 1, '', '', ''), (8, 4, 200, 1, 1, '', '', '')");
    $pdo->exec("INSERT INTO sr_community_level_logs (id, account_id, old_level_value, new_level_value, old_score_value, new_score_value, reason_key, created_at) VALUES (1, 7, 2, 3, 80, 100, 'post', ''), (2, 8, 3, 4, 150, 200, 'post', '')");
    $pdo->exec("INSERT INTO sr_community_access_entitlements (id, account_id, subject_type, subject_id, event_key, source_kind, source_asset_module, source_charge_policy, source_reference, granted_at, created_at) VALUES (1, 7, 'post', 10, 'view', 'asset', 'point', 'once', 'ref7', '', ''), (2, 8, 'post', 20, 'view', 'asset', 'point', 'once', 'ref8', '', '')");
    $pdo->exec("INSERT INTO sr_community_asset_logs (id, account_id, asset_module, transaction_id, reference_type, reference_id, subject_type, subject_id, event_key, direction, charge_policy, amount, settlement_amount, settlement_currency, purchase_power_snapshot_json, group_policy_snapshot_json, created_at) VALUES (1, 7, 'point', 101, 'post', '10', 'post', 10, 'view', 'debit', 'once', 100, 100, 'KRW', '{\"asset_units\":100,\"settlement_units\":100,\"settlement_currency\":\"KRW\",\"currency_min_unit\":1}', '{}', ''), (2, 8, 'point', 102, 'post', '20', 'post', 20, 'view', 'debit', 'once', 200, 200, 'KRW', '{}', '{}', '')");
    $pdo->exec("INSERT INTO sr_community_publisher_reward_logs (id, charge_asset_log_id, charge_transaction_id, reward_transaction_id, reversal_transaction_id, post_id, attachment_id, downloader_account_id, publisher_account_id, asset_module, charge_amount, reward_rate, reward_amount, status, created_at, updated_at) VALUES (1, 1, 101, 201, NULL, 10, 1, 7, 8, 'point', 100, 50, 50, 'granted', '', ''), (2, 2, 102, 202, NULL, 20, 2, 8, 7, 'point', 100, 50, 50, 'granted', '', '')");
    $pdo->exec("INSERT INTO sr_community_submission_consents (id, board_id, subject_type, subject_id, action_key, account_id, consent_title_snapshot, consent_body_snapshot, consent_version_snapshot, consent_required, consent_accepted, ip_hash, user_agent_hash, created_at) VALUES (1, 1, 'post', 10, 'write', 7, 'Privacy', 'Body', 'v1', 1, 1, 'ip7', 'ua7', ''), (2, 1, 'post', 20, 'write', 8, 'Privacy', 'Body', 'v1', 1, 1, 'ip8', 'ua8', '')");

    $invalid = $export($pdo, 0);
    foreach (['posts', 'comments', 'attachments', 'reports', 'messages', 'scraps', 'series_scraps', 'series', 'series_items', 'level', 'level_logs', 'access_entitlements', 'asset_logs', 'publisher_reward_logs', 'submission_consents'] as $key) {
        sr_privacy_export_runtime_assert(isset($invalid[$key]) && $invalid[$key] === [], 'community export invalid account result must include empty key: ' . $key);
    }

    $result = $export($pdo, 7);
    foreach (['posts', 'comments', 'attachments', 'reports', 'scraps', 'series_scraps', 'series', 'series_items', 'level_logs', 'access_entitlements', 'asset_logs', 'submission_consents'] as $key) {
        sr_privacy_export_runtime_assert(count($result[$key] ?? []) === 1, 'community export must include one target row for: ' . $key);
    }
    sr_privacy_export_runtime_assert(count($result['messages'] ?? []) === 2, 'community export must include sent and received messages for target account.');
    sr_privacy_export_runtime_assert(count($result['publisher_reward_logs'] ?? []) === 2, 'community export must include downloader and publisher reward rows for target account.');
    sr_privacy_export_runtime_assert(($result['posts'][0]['author_public_name_snapshot'] ?? '') === 'post-name7', 'community export must include post author public name snapshot.');
    sr_privacy_export_runtime_assert(($result['posts'][0]['category_key'] ?? '') === 'cat', 'community export must include post category metadata.');
    sr_privacy_export_runtime_assert(($result['comments'][0]['author_public_name_snapshot'] ?? '') === 'comment-name7', 'community export must include comment author public name snapshot.');
    sr_privacy_export_runtime_assert((int) ($result['comments'][0]['is_secret'] ?? -1) === 1, 'community export must include comment secret flag.');
    sr_privacy_export_runtime_assert(($result['reports'][0]['reported_account_role'] ?? '') === 'masked_counterparty', 'community export must mask reported counterparty when it is not the target account.');
    sr_privacy_export_runtime_assert(($result['asset_logs'][0]['settlement_summary']['purchase_power']['asset_units'] ?? null) === 100, 'community export must decode asset log purchase power snapshot.');
    sr_privacy_export_runtime_assert(($result['submission_consents'][0]['ip_hash'] ?? '') === 'ip7', 'community export must include target submission consent hashes.');
}

sr_privacy_export_runtime_check_quiz();
sr_privacy_export_runtime_check_survey();
sr_privacy_export_runtime_check_content();
sr_privacy_export_runtime_check_community();

if ($errors !== []) {
    fwrite(STDERR, "privacy export runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "privacy export runtime checks completed.\n";
