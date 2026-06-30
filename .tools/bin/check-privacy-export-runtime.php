#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

$errors = [];

if (!function_exists('sr_normalize_identifier')) {
    function sr_normalize_identifier(string $value): string
    {
        return strtolower(trim($value));
    }
}

if (!function_exists('sr_now')) {
    function sr_now(): string
    {
        return '2026-06-14 00:00:00';
    }
}

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

function sr_privacy_export_runtime_check_member(): void
{
    $export = include 'modules/member/privacy-export.php';
    if (!is_callable($export)) {
        sr_privacy_export_runtime_error('member privacy export contract is not callable.');
        return;
    }

    $pdo = sr_privacy_export_runtime_pdo();
    $pdo->exec(
        'CREATE TABLE sr_member_accounts (
            id INTEGER PRIMARY KEY,
            email TEXT NOT NULL,
            display_name TEXT NOT NULL,
            locale TEXT NOT NULL,
            status TEXT NOT NULL,
            email_verified_at TEXT NULL,
            last_login_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec('CREATE TABLE sr_member_profiles (id INTEGER PRIMARY KEY, account_id INTEGER NOT NULL, birth_date TEXT NULL, is_adult INTEGER NULL, avatar_path TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_member_consents (id INTEGER PRIMARY KEY, account_id INTEGER NOT NULL, consent_key TEXT NOT NULL, consent_version TEXT NOT NULL, policy_document_key_snapshot TEXT NOT NULL, policy_version_key_snapshot TEXT NOT NULL, policy_document_version_id INTEGER NULL, consent_title_snapshot TEXT NOT NULL, consent_body_hash TEXT NOT NULL, consent_required INTEGER NOT NULL, consented INTEGER NOT NULL, created_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_member_auth_logs (id INTEGER PRIMARY KEY, account_id INTEGER NOT NULL, event_type TEXT NOT NULL, result TEXT NOT NULL, ip_address TEXT NOT NULL, user_agent TEXT NULL, created_at TEXT NOT NULL)');

    $pdo->exec("INSERT INTO sr_member_accounts (id, email, display_name, locale, status, email_verified_at, last_login_at, created_at, updated_at) VALUES (7, 'member7@example.test', 'Member 7', 'ko', 'active', '', '', '', ''), (8, 'member8@example.test', 'Member 8', 'ko', 'active', '', '', '', '')");
    $pdo->exec("INSERT INTO sr_member_profiles (id, account_id, birth_date, is_adult, avatar_path) VALUES (1, 7, '1990-01-02', 1, ''), (2, 8, '1988-03-04', 0, '')");
    $pdo->exec("INSERT INTO sr_member_consents (id, account_id, consent_key, consent_version, policy_document_key_snapshot, policy_version_key_snapshot, policy_document_version_id, consent_title_snapshot, consent_body_hash, consent_required, consented, created_at) VALUES (1, 7, 'privacy', 'v1', 'privacy', 'v1', 1, 'Privacy', 'hash7', 1, 1, ''), (2, 8, 'privacy', 'v1', 'privacy', 'v1', 1, 'Privacy', 'hash8', 1, 1, '')");
    $pdo->exec("INSERT INTO sr_member_auth_logs (id, account_id, event_type, result, ip_address, user_agent, created_at) VALUES (1, 7, 'login', 'success', '127.0.0.1', 'ua7', ''), (2, 8, 'login', 'success', '127.0.0.2', 'ua8', '')");

    $result = $export($pdo, 7);
    sr_privacy_export_runtime_assert(($result['account']['email'] ?? '') === 'member7@example.test', 'member export must include target account.');
    sr_privacy_export_runtime_assert(($result['profile']['birth_date'] ?? '') === '1990-01-02', 'member export must include optional birth date profile data as age-related personal data.');
    sr_privacy_export_runtime_assert(($result['profile']['is_adult'] ?? '') === '1', 'member export must include optional adult flag profile data as age-related personal data.');
    sr_privacy_export_runtime_assert(count($result['consents'] ?? []) === 1 && ($result['consents'][0]['consent_body_hash'] ?? '') === 'hash7', 'member export must include target consent evidence only.');
    sr_privacy_export_runtime_assert(count($result['auth_logs'] ?? []) === 1 && ($result['auth_logs'][0]['user_agent'] ?? '') === 'ua7', 'member export must include target auth logs only.');
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
            title TEXT NOT NULL,
            cover_image_url TEXT NOT NULL DEFAULT ""
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
            extra_values_json TEXT NOT NULL DEFAULT "[]",
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
    $pdo->exec('CREATE TABLE sr_community_post_field_values (id INTEGER PRIMARY KEY, post_id INTEGER NOT NULL, field_key TEXT NOT NULL, label_snapshot TEXT NOT NULL, field_type_snapshot TEXT NOT NULL, visibility_snapshot TEXT NOT NULL, show_on_view_snapshot INTEGER NOT NULL, show_in_admin_snapshot INTEGER NOT NULL, privacy_purpose_snapshot TEXT NOT NULL, export_policy_snapshot TEXT NOT NULL, cleanup_policy_snapshot TEXT NOT NULL, value_text TEXT NULL, value_json TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
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
    $pdo->exec('CREATE TABLE sr_community_asset_recovery_failures (id INTEGER PRIMARY KEY, account_id INTEGER NOT NULL, asset_module TEXT NOT NULL, original_asset_log_id INTEGER NOT NULL, original_transaction_id INTEGER NOT NULL, subject_type TEXT NOT NULL, subject_id INTEGER NOT NULL, grant_event_key TEXT NOT NULL, reversal_event_key TEXT NOT NULL, operation_event_key TEXT NOT NULL, attempted_amount INTEGER NOT NULL, recovered_amount INTEGER NOT NULL, unrecovered_amount INTEGER NOT NULL, failure_reason TEXT NOT NULL, status TEXT NOT NULL, actor_account_id INTEGER NULL, actor_type TEXT NOT NULL, operation_context_json TEXT NULL, attempt_count INTEGER NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, last_attempted_at TEXT NOT NULL, resolved_at TEXT NULL)');
    $pdo->exec('CREATE TABLE sr_community_publisher_reward_logs (id INTEGER PRIMARY KEY, charge_asset_log_id INTEGER NOT NULL, charge_transaction_id INTEGER NOT NULL, reward_transaction_id INTEGER NOT NULL, reversal_transaction_id INTEGER NULL, post_id INTEGER NOT NULL, attachment_id INTEGER NOT NULL, downloader_account_id INTEGER NOT NULL, publisher_account_id INTEGER NOT NULL, asset_module TEXT NOT NULL, charge_amount INTEGER NOT NULL, reward_rate INTEGER NOT NULL, reward_amount INTEGER NOT NULL, status TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_submission_consents (id INTEGER PRIMARY KEY, board_id INTEGER NOT NULL, subject_type TEXT NOT NULL, subject_id INTEGER NOT NULL, action_key TEXT NOT NULL, account_id INTEGER NOT NULL, consent_title_snapshot TEXT NOT NULL, consent_body_snapshot TEXT NOT NULL, consent_version_snapshot TEXT NOT NULL, consent_required INTEGER NOT NULL, consent_accepted INTEGER NOT NULL, ip_hash TEXT NOT NULL, user_agent_hash TEXT NOT NULL, created_at TEXT NOT NULL)');

    $pdo->exec("INSERT INTO sr_community_categories (id, board_id, category_key, title) VALUES (1, 1, 'cat', 'Category')");
    $pdo->exec("INSERT INTO sr_community_posts (id, board_id, category_id, author_account_id, title, author_public_name_snapshot, body_text, body_format, extra_values_json, status, created_at, updated_at) VALUES (10, 1, 1, 7, 'Post 7', 'post-name7', 'body7', 'html', '{\"company\":{\"label\":\"Company\",\"value\":\"Acme\",\"export_policy\":\"include\"},\"internal_note\":{\"label\":\"Internal\",\"value\":\"hidden\",\"export_policy\":\"exclude\"},\"legacy\":{\"label\":\"Legacy\",\"value\":\"legacy\"}}', 'published', '', ''), (20, 1, 1, 8, 'Post 8', 'post-name8', 'body8', 'html', '{\"company\":{\"label\":\"Company\",\"value\":\"Other\",\"export_policy\":\"include\"}}', 'published', '', '')");
    $pdo->exec("INSERT INTO sr_community_comments (id, post_id, parent_comment_id, thread_root_id, depth, author_account_id, author_public_name_snapshot, body_text, is_secret, status, created_at, updated_at) VALUES (100, 10, NULL, 100, 1, 7, 'comment-name7', 'comment7', 1, 'published', '', ''), (200, 20, NULL, 200, 1, 8, 'comment-name8', 'comment8', 0, 'published', '', '')");
    $pdo->exec("INSERT INTO sr_community_attachments (id, post_id, uploader_account_id, original_name, mime_type, size_bytes, width, height, status, created_at) VALUES (1, 10, 7, 'a.png', 'image/png', 123, 10, 10, 'active', ''), (2, 20, 8, 'b.png', 'image/png', 234, 20, 20, 'active', '')");
    $pdo->exec("INSERT INTO sr_community_post_field_values (id, post_id, field_key, label_snapshot, field_type_snapshot, visibility_snapshot, show_on_view_snapshot, show_in_admin_snapshot, privacy_purpose_snapshot, export_policy_snapshot, cleanup_policy_snapshot, value_text, value_json, created_at, updated_at) VALUES (1, 10, 'company', 'Company', 'text', 'public', 1, 0, 'reply', 'include', 'anonymize', 'Acme', '{\"value\":\"Acme\"}', '', ''), (2, 10, 'internal_note', 'Internal', 'text', 'admin', 0, 1, '', 'exclude', 'retain', 'hidden', '{\"value\":\"hidden\"}', '', ''), (3, 20, 'company', 'Company', 'text', 'public', 1, 0, 'reply', 'include', 'anonymize', 'Other', '{\"value\":\"Other\"}', '', '')");
    $pdo->exec("INSERT INTO sr_community_reports (id, reporter_account_id, reported_account_id, target_type, target_id, reason_key, memo_text, status, created_at, updated_at) VALUES (1, 7, 8, 'post', 20, 'spam', 'memo', 'open', '', ''), (2, 8, 7, 'post', 10, 'spam', 'memo', 'open', '', '')");
    $pdo->exec("INSERT INTO sr_community_messages (id, sender_account_id, recipient_account_id, body_text, status, read_at, sender_deleted_at, recipient_deleted_at, created_at, updated_at) VALUES (1, 7, 8, 'sent', 'sent', NULL, NULL, NULL, '', ''), (2, 8, 7, 'received', 'sent', NULL, NULL, NULL, '', '')");
    $pdo->exec("INSERT INTO sr_community_scraps (id, account_id, post_id, created_at) VALUES (1, 7, 10, ''), (2, 8, 20, '')");
    $pdo->exec("INSERT INTO sr_community_series_scraps (id, account_id, series_id, created_at) VALUES (1, 7, 1, ''), (2, 8, 2, '')");
    $pdo->exec("INSERT INTO sr_community_series (id, board_id, owner_account_id, title, description, status, visibility, created_at, updated_at) VALUES (1, 1, 7, 'Series 7', '', 'active', 'public', '', ''), (2, 1, 8, 'Series 8', '', 'active', 'public', '', '')");
    $pdo->exec("INSERT INTO sr_community_series_items (id, series_id, post_id, active_post_id, episode_label, item_status, sort_order, created_at, updated_at) VALUES (1, 1, 10, 10, 'E1', 'active', 0, '', ''), (2, 2, 20, 20, 'E2', 'active', 0, '', '')");
    $pdo->exec("INSERT INTO sr_community_account_levels (account_id, level_value, score_value, post_count, comment_count, evaluated_at, created_at, updated_at) VALUES (7, 3, 100, 1, 1, '', '', ''), (8, 4, 200, 1, 1, '', '', '')");
    $pdo->exec("INSERT INTO sr_community_level_logs (id, account_id, old_level_value, new_level_value, old_score_value, new_score_value, reason_key, created_at) VALUES (1, 7, 2, 3, 80, 100, 'post', ''), (2, 8, 3, 4, 150, 200, 'post', '')");
    for ($levelLogId = 3; $levelLogId <= 1002; $levelLogId++) {
        $pdo->exec("INSERT INTO sr_community_level_logs (id, account_id, old_level_value, new_level_value, old_score_value, new_score_value, reason_key, created_at) VALUES (" . (string) $levelLogId . ", 7, 3, 3, 100, 100, 'fixture', '')");
    }
    $pdo->exec("INSERT INTO sr_community_access_entitlements (id, account_id, subject_type, subject_id, event_key, source_kind, source_asset_module, source_charge_policy, source_reference, granted_at, created_at) VALUES (1, 7, 'post', 10, 'view', 'asset', 'point', 'once', 'ref7', '', ''), (2, 8, 'post', 20, 'view', 'asset', 'point', 'once', 'ref8', '', '')");
    $pdo->exec("INSERT INTO sr_community_asset_logs (id, account_id, asset_module, transaction_id, reference_type, reference_id, subject_type, subject_id, event_key, direction, charge_policy, amount, settlement_amount, settlement_currency, purchase_power_snapshot_json, group_policy_snapshot_json, created_at) VALUES (1, 7, 'point', 101, 'post', '10', 'post', 10, 'view', 'debit', 'once', 100, 100, 'KRW', '{\"asset_units\":100,\"settlement_units\":100,\"settlement_currency\":\"KRW\",\"currency_min_unit\":1}', '{}', ''), (2, 8, 'point', 102, 'post', '20', 'post', 20, 'view', 'debit', 'once', 200, 200, 'KRW', '{}', '{}', '')");
    $pdo->exec("INSERT INTO sr_community_asset_recovery_failures (id, account_id, asset_module, original_asset_log_id, original_transaction_id, subject_type, subject_id, grant_event_key, reversal_event_key, operation_event_key, attempted_amount, recovered_amount, unrecovered_amount, failure_reason, status, actor_account_id, actor_type, operation_context_json, attempt_count, created_at, updated_at, last_attempted_at, resolved_at) VALUES (1, 7, 'point', 1, 101, 'community.post', 10, 'post_reward', 'post_reward_reversal', 'community.post.deleted_by_admin', 100, 40, 60, 'balance_low', 'open', NULL, 'admin', '{\"route_context\":\"admin.community.posts\"}', 2, '', '', '', NULL), (2, 8, 'point', 2, 102, 'community.post', 20, 'post_reward', 'post_reward_reversal', 'community.post.deleted_by_admin', 200, 0, 200, 'balance_low', 'open', NULL, 'admin', '{}', 1, '', '', '', NULL)");
    $pdo->exec("INSERT INTO sr_community_publisher_reward_logs (id, charge_asset_log_id, charge_transaction_id, reward_transaction_id, reversal_transaction_id, post_id, attachment_id, downloader_account_id, publisher_account_id, asset_module, charge_amount, reward_rate, reward_amount, status, created_at, updated_at) VALUES (1, 1, 101, 201, NULL, 10, 1, 7, 8, 'point', 100, 50, 50, 'granted', '', ''), (2, 2, 102, 202, NULL, 20, 2, 8, 7, 'point', 100, 50, 50, 'granted', '', '')");
    $pdo->exec("INSERT INTO sr_community_submission_consents (id, board_id, subject_type, subject_id, action_key, account_id, consent_title_snapshot, consent_body_snapshot, consent_version_snapshot, consent_required, consent_accepted, ip_hash, user_agent_hash, created_at) VALUES (1, 1, 'post', 10, 'write', 7, 'Privacy', 'Body', 'v1', 1, 1, 'ip7', 'ua7', ''), (2, 1, 'post', 20, 'write', 8, 'Privacy', 'Body', 'v1', 1, 1, 'ip8', 'ua8', '')");

    $invalid = $export($pdo, 0);
    foreach (['posts', 'post_field_values', 'comments', 'attachments', 'reports', 'messages', 'scraps', 'series_scraps', 'series', 'series_items', 'level', 'level_logs', 'access_entitlements', 'asset_logs', 'asset_recovery_failures', 'publisher_reward_logs', 'submission_consents'] as $key) {
        sr_privacy_export_runtime_assert(isset($invalid[$key]) && $invalid[$key] === [], 'community export invalid account result must include empty key: ' . $key);
    }

    $result = $export($pdo, 7);
    foreach (['posts', 'post_field_values', 'comments', 'attachments', 'reports', 'scraps', 'series_scraps', 'series', 'series_items', 'access_entitlements', 'asset_logs', 'asset_recovery_failures', 'submission_consents'] as $key) {
        sr_privacy_export_runtime_assert(count($result[$key] ?? []) === 1, 'community export must include one target row for: ' . $key);
    }
    sr_privacy_export_runtime_assert(count($result['level_logs'] ?? []) === 1000, 'community export must cap a section at the documented row limit.');
    sr_privacy_export_runtime_assert(($result['_limits']['level_logs']['has_more'] ?? null) === true, 'community export must report when a section has more rows than the returned limit.');
    sr_privacy_export_runtime_assert(($result['_limits']['level_logs']['limit'] ?? null) === 1000, 'community export must report the per-section row limit.');
    sr_privacy_export_runtime_assert(($result['_limits']['messages']['has_more'] ?? null) === false, 'community export must report complete sections when they fit under the row limit.');
    sr_privacy_export_runtime_assert(count($result['messages'] ?? []) === 2, 'community export must include sent and received messages for target account.');
    $messageDirections = [];
    $messageCounterpartyRoles = [];
    $messageRolesByBody = [];
    foreach ($result['messages'] as $message) {
        sr_privacy_export_runtime_assert(!array_key_exists('sender_account_id', $message), 'community message export must not expose raw sender account id.');
        sr_privacy_export_runtime_assert(!array_key_exists('recipient_account_id', $message), 'community message export must not expose raw recipient account id.');
        foreach (array_keys($message) as $messageKey) {
            sr_privacy_export_runtime_assert(!str_contains((string) $messageKey, 'account_id'), 'community message export must not expose account id fields: ' . (string) $messageKey);
        }
        $messageDirections[(string) ($message['message_direction'] ?? '')] = true;
        $messageCounterpartyRoles[(string) ($message['counterparty_role'] ?? '')] = true;
        $messageRolesByBody[(string) ($message['body_text'] ?? '')] = [
            'message_direction' => (string) ($message['message_direction'] ?? ''),
            'counterparty_role' => (string) ($message['counterparty_role'] ?? ''),
        ];
    }
    sr_privacy_export_runtime_assert(isset($messageDirections['sent'], $messageDirections['received']), 'community message export must expose sent and received directions.');
    sr_privacy_export_runtime_assert(isset($messageCounterpartyRoles['masked_recipient'], $messageCounterpartyRoles['masked_sender']), 'community message export must expose masked counterparty roles.');
    sr_privacy_export_runtime_assert(($messageRolesByBody['sent']['message_direction'] ?? '') === 'sent' && ($messageRolesByBody['sent']['counterparty_role'] ?? '') === 'masked_recipient', 'community sent message export must map the counterparty to masked_recipient.');
    sr_privacy_export_runtime_assert(($messageRolesByBody['received']['message_direction'] ?? '') === 'received' && ($messageRolesByBody['received']['counterparty_role'] ?? '') === 'masked_sender', 'community received message export must map the counterparty to masked_sender.');
    sr_privacy_export_runtime_assert(count($result['publisher_reward_logs'] ?? []) === 2, 'community export must include downloader and publisher reward rows for target account.');
    sr_privacy_export_runtime_assert(($result['posts'][0]['author_public_name_snapshot'] ?? '') === 'post-name7', 'community export must include post author public name snapshot.');
    sr_privacy_export_runtime_assert(($result['posts'][0]['category_key'] ?? '') === 'cat', 'community export must include post category metadata.');
    $exportedPostExtraValues = json_decode((string) ($result['posts'][0]['extra_values_json'] ?? ''), true);
    sr_privacy_export_runtime_assert(is_array($exportedPostExtraValues), 'community export must keep post extra value snapshot JSON decodable.');
    sr_privacy_export_runtime_assert(($exportedPostExtraValues['company']['value'] ?? '') === 'Acme', 'community export must include included post extra value snapshot.');
    sr_privacy_export_runtime_assert(($exportedPostExtraValues['legacy']['value'] ?? '') === 'legacy', 'community export must treat legacy post extra value snapshot export policy as include.');
    sr_privacy_export_runtime_assert(!isset($exportedPostExtraValues['internal_note']), 'community export must exclude excluded post extra value snapshot.');
    sr_privacy_export_runtime_assert(($result['post_field_values'][0]['field_key'] ?? '') === 'company', 'community export must include included post field values.');
    sr_privacy_export_runtime_assert(($result['post_field_values'][0]['value_text'] ?? '') === 'Acme', 'community export must include target post field value text.');
    sr_privacy_export_runtime_assert(($result['comments'][0]['author_public_name_snapshot'] ?? '') === 'comment-name7', 'community export must include comment author public name snapshot.');
    sr_privacy_export_runtime_assert((int) ($result['comments'][0]['is_secret'] ?? -1) === 1, 'community export must include comment secret flag.');
    sr_privacy_export_runtime_assert(($result['reports'][0]['reported_account_role'] ?? '') === 'masked_counterparty', 'community export must mask reported counterparty when it is not the target account.');
    sr_privacy_export_runtime_assert(($result['asset_logs'][0]['settlement_summary']['purchase_power']['asset_units'] ?? null) === 100, 'community export must decode asset log purchase power snapshot.');
    sr_privacy_export_runtime_assert((int) ($result['asset_recovery_failures'][0]['unrecovered_amount'] ?? 0) === 60, 'community export must include target asset recovery failure amount.');
    sr_privacy_export_runtime_assert(($result['submission_consents'][0]['ip_hash'] ?? '') === 'ip7', 'community export must include target submission consent hashes.');
}

function sr_privacy_export_runtime_check_retained_modules(): void
{
    $pdo = sr_privacy_export_runtime_pdo();
    $pdo->exec('CREATE TABLE sr_member_accounts (id INTEGER PRIMARY KEY, email TEXT NOT NULL)');
    $pdo->exec("INSERT INTO sr_member_accounts (id, email) VALUES (7, 'member7@example.test'), (8, 'member8@example.test')");

    $pdo->exec('CREATE TABLE sr_asset_recovery_failures (id INTEGER PRIMARY KEY, dedupe_key TEXT NOT NULL, source_module TEXT NOT NULL, source_log_id INTEGER NOT NULL, asset_module TEXT NOT NULL, account_id INTEGER NOT NULL, original_transaction_id INTEGER NOT NULL, subject_type TEXT NOT NULL, subject_id INTEGER NOT NULL, grant_event_key TEXT NOT NULL, reversal_event_key TEXT NOT NULL, operation_event_key TEXT NOT NULL, attempted_amount INTEGER NOT NULL, recovered_amount INTEGER NOT NULL, unrecovered_amount INTEGER NOT NULL, failure_reason TEXT NOT NULL, status TEXT NOT NULL, actor_account_id INTEGER NULL, actor_type TEXT NOT NULL, operation_context_json TEXT NULL, attempt_count INTEGER NOT NULL, version INTEGER NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, last_attempted_at TEXT NOT NULL, resolved_at TEXT NULL)');
    $pdo->exec('CREATE TABLE sr_asset_recovery_reversal_links (id INTEGER PRIMARY KEY, failure_id INTEGER NOT NULL, asset_module TEXT NOT NULL, reversal_transaction_id INTEGER NOT NULL, recovered_amount INTEGER NOT NULL, created_at TEXT NOT NULL)');
    $pdo->exec("INSERT INTO sr_asset_recovery_failures (id, dedupe_key, source_module, source_log_id, asset_module, account_id, original_transaction_id, subject_type, subject_id, grant_event_key, reversal_event_key, operation_event_key, attempted_amount, recovered_amount, unrecovered_amount, failure_reason, status, actor_account_id, actor_type, operation_context_json, attempt_count, version, created_at, updated_at, last_attempted_at, resolved_at) VALUES (1, 'source:community:1:rev:community.post.reward_reversal', 'community', 1, 'point', 7, 101, 'community.post', 10, 'community.post.reward_grant', 'community.post.reward_reversal', 'manual_retry', 100, 40, 60, 'balance_low', 'open', NULL, 'admin', '{}', 2, 1, '', '', '', NULL), (2, 'source:community:2:rev:community.post.reward_reversal', 'community', 2, 'point', 8, 102, 'community.post', 20, 'community.post.reward_grant', 'community.post.reward_reversal', 'manual_retry', 200, 0, 200, 'balance_low', 'open', NULL, 'admin', '{}', 1, 1, '', '', '', NULL), (3, 'source:community:3:rev:community.post.reward_reversal', 'community', 3, 'point', 8, 103, 'community.post', 30, 'community.post.reward_grant', 'community.post.reward_reversal', 'manual_cancel', 300, 0, 300, 'manual_cancelled', 'cancelled', 7, 'admin', '{\"route_context\":\"admin.assets.recovery_failures\"}', 1, 1, '', '', '', '')");
    $pdo->exec("INSERT INTO sr_asset_recovery_reversal_links (id, failure_id, asset_module, reversal_transaction_id, recovered_amount, created_at) VALUES (1, 1, 'point', 201, 40, ''), (2, 2, 'point', 202, 20, ''), (3, 3, 'point', 203, 30, '')");

    $pdo->exec('CREATE TABLE sr_asset_exchange_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL, exchange_group_id TEXT NOT NULL, from_module_key TEXT NOT NULL, to_module_key TEXT NOT NULL, request_amount INTEGER NOT NULL, deposit_amount INTEGER NOT NULL, fee_amount INTEGER NOT NULL, status TEXT NOT NULL, failure_reason TEXT NOT NULL, created_at TEXT NOT NULL)');
    $pdo->exec("INSERT INTO sr_asset_exchange_logs (account_id, exchange_group_id, from_module_key, to_module_key, request_amount, deposit_amount, fee_amount, status, failure_reason, created_at) VALUES (7, 'ex7', 'reward', 'deposit', 100, 90, 10, 'completed', '', ''), (8, 'ex8', 'reward', 'deposit', 200, 180, 20, 'completed', '', '')");

    $pdo->exec('CREATE TABLE sr_coupon_definitions (id INTEGER PRIMARY KEY, coupon_key TEXT NOT NULL, title TEXT NOT NULL, target_type TEXT NOT NULL, target_id INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE sr_coupon_issues (id INTEGER PRIMARY KEY, coupon_definition_id INTEGER NOT NULL, account_id INTEGER NOT NULL, status TEXT NOT NULL, issued_reason TEXT NOT NULL, claim_type TEXT NOT NULL DEFAULT \'manual\', claim_campaign_id INTEGER NULL, claim_log_id INTEGER NULL, nominal_price_amount INTEGER NOT NULL DEFAULT 0, nominal_price_currency_code TEXT NOT NULL DEFAULT \'\', asset_reference_module TEXT NOT NULL DEFAULT \'\', asset_reference_type TEXT NOT NULL DEFAULT \'\', asset_reference_id TEXT NOT NULL DEFAULT \'\', claim_snapshot_json TEXT NULL, issued_at TEXT NOT NULL, expires_at TEXT NULL, used_count INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE sr_coupon_redemptions (id INTEGER PRIMARY KEY, coupon_definition_id INTEGER NOT NULL, account_id INTEGER NOT NULL, target_type TEXT NOT NULL, target_id INTEGER NOT NULL, reference_module TEXT NOT NULL, reference_type TEXT NOT NULL, reference_id TEXT NOT NULL, status TEXT NOT NULL, redeemed_at TEXT NOT NULL, refunded_at TEXT NULL, refunded_by_account_id INTEGER NULL, refund_note TEXT NOT NULL, amount INTEGER NOT NULL DEFAULT 0, currency_code TEXT NOT NULL DEFAULT \'\', asset_unit TEXT NOT NULL DEFAULT \'\', policy_summary TEXT NOT NULL DEFAULT \'\', priced_at TEXT NULL, target_snapshot_json TEXT NULL)');
    $pdo->exec('CREATE TABLE sr_coupon_claim_campaigns (id INTEGER PRIMARY KEY, campaign_key TEXT NOT NULL, title TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_coupon_claim_logs (id INTEGER PRIMARY KEY, campaign_id INTEGER NOT NULL, coupon_definition_id INTEGER NOT NULL, account_id INTEGER NOT NULL, claim_source TEXT NOT NULL, payment_reference_module TEXT NOT NULL, payment_reference_type TEXT NOT NULL, payment_reference_id TEXT NOT NULL, asset_reference_module TEXT NOT NULL DEFAULT \'\', asset_reference_type TEXT NOT NULL DEFAULT \'\', asset_reference_id TEXT NOT NULL DEFAULT \'\', status TEXT NOT NULL, reserved_until TEXT NULL, failure_code TEXT NOT NULL, failure_message TEXT NOT NULL, created_at TEXT NOT NULL, issued_at TEXT NULL)');
    $pdo->exec("INSERT INTO sr_coupon_definitions (id, coupon_key, title, target_type, target_id) VALUES (1, 'coupon7', 'Coupon 7', 'content', 10), (2, 'coupon8', 'Coupon 8', 'content', 20)");
    $pdo->exec("INSERT INTO sr_coupon_issues (id, coupon_definition_id, account_id, status, issued_reason, claim_type, claim_campaign_id, claim_log_id, nominal_price_amount, nominal_price_currency_code, asset_reference_module, asset_reference_type, asset_reference_id, claim_snapshot_json, issued_at, expires_at, used_count) VALUES (1, 1, 7, 'active', 'manual', 'paid', 1, 1, 120, 'KRW', 'coupon', 'paid_claim', '1', '{\"schema_version\":\"coupon_claim_snapshot_v1\",\"claim_type\":\"paid\",\"settlement_kind\":\"paid\",\"snapshot_schema_version\":\"asset_settlement_snapshot_v1\",\"rounding_policy_version\":\"asset_settlement_rounding_v1\",\"nominal_price\":{\"amount\":120,\"currency_code\":\"KRW\"},\"charged_allocations\":[{\"asset_module\":\"point\",\"amount\":120,\"transaction_id\":501,\"purchase_power_snapshot\":{\"asset_units\":120,\"settlement_units\":120,\"settlement_currency\":\"KRW\",\"currency_min_unit\":1,\"rounding_policy_version\":\"asset_settlement_rounding_v1\"}}]}', '', NULL, 1), (2, 2, 8, 'active', 'manual', 'manual', NULL, NULL, 0, '', '', '', '', NULL, '', NULL, 1)");
    $pdo->exec("INSERT INTO sr_coupon_redemptions (id, coupon_definition_id, account_id, target_type, target_id, reference_module, reference_type, reference_id, status, redeemed_at, refunded_at, refunded_by_account_id, refund_note, amount, currency_code, asset_unit, policy_summary, priced_at, target_snapshot_json) VALUES (1, 1, 7, 'content', 10, 'content', 'download', '5', 'refunded', '', '', 99, 'refund7', 120, 'KRW', '', '콘텐츠 다운로드 120KRW', '2026-06-14 00:00:00', '{\"target_type\":\"content_file\",\"target_id\":\"5\",\"amount\":120,\"currency_code\":\"KRW\",\"asset_unit\":\"\",\"policy_summary\":\"콘텐츠 다운로드 120KRW\",\"priced_at\":\"2026-06-14 00:00:00\"}'), (2, 2, 8, 'content', 20, 'content', 'download', '6', 'used', '', NULL, NULL, '', 200, 'KRW', '', '다른 회원 다운로드 200KRW', '2026-06-14 00:00:00', '{\"target_type\":\"content_file\",\"target_id\":\"6\",\"amount\":200,\"currency_code\":\"KRW\"}')");
    $pdo->exec("INSERT INTO sr_coupon_claim_campaigns (id, campaign_key, title) VALUES (1, 'claim7', 'Claim 7'), (2, 'claim8', 'Claim 8')");
    $pdo->exec("INSERT INTO sr_coupon_claim_logs (id, campaign_id, coupon_definition_id, account_id, claim_source, payment_reference_module, payment_reference_type, payment_reference_id, asset_reference_module, asset_reference_type, asset_reference_id, status, reserved_until, failure_code, failure_message, created_at, issued_at) VALUES (1, 1, 1, 7, 'coupon_zone', '', '', '', 'coupon', 'paid_claim', '1', 'issued', NULL, '', '', '2026-06-14 00:00:00', '2026-06-14 00:00:01'), (2, 2, 2, 8, 'popup_layer', '', '', '', '', '', '', 'issued', NULL, '', '', '2026-06-14 00:00:00', '2026-06-14 00:00:01')");

    $pdo->exec('CREATE TABLE sr_deposit_balances (account_id INTEGER PRIMARY KEY, balance INTEGER NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_deposit_transactions (id INTEGER PRIMARY KEY, account_id INTEGER NOT NULL, amount INTEGER NOT NULL, balance_after INTEGER NOT NULL, transaction_type TEXT NOT NULL, reason TEXT NOT NULL, reference_type TEXT NOT NULL, reference_id TEXT NOT NULL, created_by_account_id INTEGER NULL, created_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_deposit_refund_requests (id INTEGER PRIMARY KEY, account_id INTEGER NOT NULL, amount INTEGER NOT NULL, bank_name TEXT NOT NULL, bank_account_number TEXT NOT NULL, bank_account_holder TEXT NOT NULL, requester_note TEXT NOT NULL, status TEXT NOT NULL, admin_note TEXT NOT NULL, transaction_id INTEGER NULL, processed_by_account_id INTEGER NULL, requested_at TEXT NOT NULL, processed_at TEXT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec("INSERT INTO sr_deposit_balances (account_id, balance, created_at, updated_at) VALUES (7, 500, '', ''), (8, 800, '', '')");
    $pdo->exec("INSERT INTO sr_deposit_transactions (id, account_id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_by_account_id, created_at) VALUES (1, 7, 500, 500, 'admin_adjust', 'reason7', 'manual', '7', 99, ''), (2, 8, 800, 800, 'admin_adjust', 'reason8', 'manual', '8', 99, '')");
    $pdo->exec("INSERT INTO sr_deposit_refund_requests (id, account_id, amount, bank_name, bank_account_number, bank_account_holder, requester_note, status, admin_note, transaction_id, processed_by_account_id, requested_at, processed_at, updated_at) VALUES (1, 7, 300, 'Bank7', '111-7', 'Holder7', 'note7', 'completed', 'admin7', 1, 99, '', '', ''), (2, 8, 400, 'Bank8', '111-8', 'Holder8', 'note8', 'pending', '', NULL, NULL, '', NULL, '')");

    $pdo->exec('CREATE TABLE sr_notification_deliveries (id INTEGER PRIMARY KEY, notification_id INTEGER NOT NULL, channel TEXT NOT NULL, recipient TEXT NOT NULL, status TEXT NOT NULL, provider_message_id TEXT NOT NULL, error_message TEXT NOT NULL, attempted_at TEXT NULL, locked_at TEXT NULL, locked_by TEXT NOT NULL DEFAULT \'\', attempt_count INTEGER NOT NULL DEFAULT 0, next_attempt_at TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_notification_push_endpoints (id INTEGER PRIMARY KEY, account_id INTEGER NOT NULL, provider_key TEXT NOT NULL, recipient_type TEXT NOT NULL, recipient_label TEXT NOT NULL, recipient_masked TEXT NOT NULL, status TEXT NOT NULL, verified_at TEXT NULL, disabled_at TEXT NULL, last_used_at TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_notification_reads (id INTEGER PRIMARY KEY, notification_id INTEGER NOT NULL, account_id INTEGER NOT NULL, read_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_notifications (id INTEGER PRIMARY KEY, account_id INTEGER NULL, audience TEXT NOT NULL, title TEXT NOT NULL, body_text TEXT NOT NULL, body_format TEXT NOT NULL, link_url TEXT NOT NULL, status TEXT NOT NULL, read_at TEXT NULL, created_by_account_id INTEGER NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec("INSERT INTO sr_notifications (id, account_id, audience, title, body_text, body_format, link_url, status, read_at, created_by_account_id, created_at, updated_at) VALUES (1, 7, 'account', 'N7', 'body7', 'plain', '/n7', 'active', NULL, 99, '', ''), (2, 8, 'account', 'N8', 'body8', 'plain', '/n8', 'active', NULL, 99, '', ''), (3, NULL, 'all', 'All', 'body all', 'plain', '/all', 'active', NULL, 99, '', '')");
    $pdo->exec("INSERT INTO sr_notification_reads (notification_id, account_id, read_at) VALUES (3, 7, '')");
    $pdo->exec("INSERT INTO sr_notification_push_endpoints (id, account_id, provider_key, recipient_type, recipient_label, recipient_masked, status, verified_at, disabled_at, last_used_at, created_at, updated_at) VALUES (1, 7, 'telegram_bot', 'personal', 'target endpoint', 'tg7***', 'active', '', NULL, '', '', ''), (2, 8, 'telegram_bot', 'personal', 'other endpoint', 'tg8***', 'active', '', NULL, '', '', '')");
    $pdo->exec("INSERT INTO sr_notification_deliveries (id, notification_id, channel, recipient, status, provider_message_id, error_message, attempted_at, created_at, updated_at) VALUES (1, 1, 'email', 'member7@example.test', 'failed', 'provider7', 'error7', '', '', ''), (2, 2, 'email', 'member8@example.test', 'failed', 'provider8', 'error8', '', '', ''), (3, 3, 'site', '', 'queued', '', '', NULL, '', ''), (4, 3, 'email', 'member7@example.test', 'queued', '', '', NULL, '', ''), (5, 3, 'email', 'member8@example.test', 'queued', '', '', NULL, '', ''), (6, 1, 'email', 'member8@example.test', 'failed', 'provider-leak', 'error leak', '', '', ''), (7, 1, 'telegram_bot', 'endpoint:1', 'sent', 'push7', '', '', '', ''), (8, 1, 'telegram_bot', 'endpoint:2', 'sent', 'push-leak', '', '', '', '')");

    $pdo->exec('CREATE TABLE sr_point_balances (account_id INTEGER PRIMARY KEY, balance INTEGER NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_point_transactions (id INTEGER PRIMARY KEY, account_id INTEGER NOT NULL, amount INTEGER NOT NULL, balance_after INTEGER NOT NULL, transaction_type TEXT NOT NULL, reason TEXT NOT NULL, reference_type TEXT NOT NULL, reference_id TEXT NOT NULL, created_by_account_id INTEGER NULL, expires_at TEXT NULL, expires_remaining INTEGER NOT NULL, expired_at TEXT NULL, created_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_point_expiration_consumptions (id INTEGER PRIMARY KEY, account_id INTEGER NOT NULL, consume_transaction_id INTEGER NOT NULL, source_transaction_id INTEGER NOT NULL, amount INTEGER NOT NULL, source_expires_at TEXT NOT NULL, created_at TEXT NOT NULL)');
    $pdo->exec("INSERT INTO sr_point_balances (account_id, balance, created_at, updated_at) VALUES (7, 70, '', ''), (8, 80, '', '')");
    $pdo->exec("INSERT INTO sr_point_transactions (id, account_id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_by_account_id, expires_at, expires_remaining, expired_at, created_at) VALUES (1, 7, 100, 100, 'earn', 'earn7', 'quiz', '1', 99, '2026-12-31', 30, NULL, ''), (2, 8, 100, 100, 'earn', 'earn8', 'quiz', '2', 99, '2026-12-31', 30, NULL, '')");
    $pdo->exec("INSERT INTO sr_point_expiration_consumptions (id, account_id, consume_transaction_id, source_transaction_id, amount, source_expires_at, created_at) VALUES (1, 7, 3, 1, 30, '2026-12-31', ''), (2, 8, 4, 2, 30, '2026-12-31', '')");

    $pdo->exec('CREATE TABLE sr_reward_balances (account_id INTEGER PRIMARY KEY, balance INTEGER NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_reward_transactions (id INTEGER PRIMARY KEY, account_id INTEGER NOT NULL, amount INTEGER NOT NULL, balance_after INTEGER NOT NULL, transaction_type TEXT NOT NULL, reason TEXT NOT NULL, reference_type TEXT NOT NULL, reference_id TEXT NOT NULL, created_by_account_id INTEGER NULL, expires_at TEXT NULL, expires_remaining INTEGER NOT NULL DEFAULT 0, expired_at TEXT NULL, created_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_reward_expiration_consumptions (id INTEGER PRIMARY KEY, account_id INTEGER NOT NULL, consume_transaction_id INTEGER NOT NULL, source_transaction_id INTEGER NOT NULL, amount INTEGER NOT NULL, source_expires_at TEXT NOT NULL, created_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_reward_withdrawal_requests (id INTEGER PRIMARY KEY, account_id INTEGER NOT NULL, amount INTEGER NOT NULL, bank_name TEXT NOT NULL, bank_account_number TEXT NOT NULL, bank_account_holder TEXT NOT NULL, requester_note TEXT NOT NULL, status TEXT NOT NULL, admin_note TEXT NOT NULL, transaction_id INTEGER NULL, processed_by_account_id INTEGER NULL, requested_at TEXT NOT NULL, processed_at TEXT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec("INSERT INTO sr_reward_balances (account_id, balance, created_at, updated_at) VALUES (7, 700, '', ''), (8, 800, '', '')");
    $pdo->exec("INSERT INTO sr_reward_transactions (id, account_id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_by_account_id, expires_at, expires_remaining, expired_at, created_at) VALUES (1, 7, 700, 700, 'earn', 'reward7', 'community', '7', 99, '2026-12-31', 300, NULL, ''), (2, 8, 800, 800, 'earn', 'reward8', 'community', '8', 99, '2026-12-31', 300, NULL, '')");
    $pdo->exec("INSERT INTO sr_reward_expiration_consumptions (id, account_id, consume_transaction_id, source_transaction_id, amount, source_expires_at, created_at) VALUES (1, 7, 3, 1, 300, '2026-12-31', ''), (2, 8, 4, 2, 300, '2026-12-31', '')");
    $pdo->exec("INSERT INTO sr_reward_withdrawal_requests (id, account_id, amount, bank_name, bank_account_number, bank_account_holder, requester_note, status, admin_note, transaction_id, processed_by_account_id, requested_at, processed_at, updated_at) VALUES (1, 7, 600, 'RewardBank7', '222-7', 'RewardHolder7', 'note7', 'completed', 'admin7', 1, 99, '', '', ''), (2, 8, 500, 'RewardBank8', '222-8', 'RewardHolder8', 'note8', 'pending', '', NULL, NULL, '', NULL, '')");

    $assetLedgerExport = include 'modules/asset_ledger/privacy-export.php';
    $assetExchangeExport = include 'modules/asset_exchange/privacy-export.php';
    $couponExport = include 'modules/coupon/privacy-export.php';
    $depositExport = include 'modules/deposit/privacy-export.php';
    $notificationExport = include 'modules/notification/privacy-export.php';
    $pointExport = include 'modules/point/privacy-export.php';
    $rewardExport = include 'modules/reward/privacy-export.php';

    foreach ([
        'asset_ledger' => $assetLedgerExport,
        'asset_exchange' => $assetExchangeExport,
        'coupon' => $couponExport,
        'deposit' => $depositExport,
        'notification' => $notificationExport,
        'point' => $pointExport,
        'reward' => $rewardExport,
    ] as $moduleKey => $export) {
        sr_privacy_export_runtime_assert(is_callable($export), $moduleKey . ' retained privacy export contract must be callable.');
    }

    $assetLedger = $assetLedgerExport($pdo, 7);
    sr_privacy_export_runtime_assert(count($assetLedger['asset_recovery_failures'] ?? []) === 2 && (int) ($assetLedger['asset_recovery_failures'][0]['unrecovered_amount'] ?? 0) === 60, 'asset_ledger export must include target and actor-linked recovery failures.');
    sr_privacy_export_runtime_assert((int) ($assetLedger['asset_recovery_failures'][1]['actor_account_id'] ?? 0) === 7, 'asset_ledger export must include actor-linked recovery failure evidence.');
    sr_privacy_export_runtime_assert(count($assetLedger['asset_recovery_reversal_links'] ?? []) === 2 && (int) ($assetLedger['asset_recovery_reversal_links'][0]['reversal_transaction_id'] ?? 0) === 201 && (int) ($assetLedger['asset_recovery_reversal_links'][1]['reversal_transaction_id'] ?? 0) === 203, 'asset_ledger export must include target recovery reversal links.');

    $assetExchange = $assetExchangeExport($pdo, 7);
    sr_privacy_export_runtime_assert(count($assetExchange['asset_exchange_logs'] ?? []) === 1 && ($assetExchange['asset_exchange_logs'][0]['exchange_group_id'] ?? '') === 'ex7', 'asset_exchange retained export must include only target exchange logs.');
    sr_privacy_export_runtime_assert(array_key_exists('fee_amount', $assetExchange['asset_exchange_logs'][0] ?? []), 'asset_exchange retained export must include fee evidence.');

    $coupon = $couponExport($pdo, 7);
    sr_privacy_export_runtime_assert(count($coupon['coupon_issues'] ?? []) === 1 && ($coupon['coupon_issues'][0]['coupon_key'] ?? '') === 'coupon7', 'coupon retained export must include only target issues.');
    sr_privacy_export_runtime_assert(($coupon['coupon_issues'][0]['claim_type'] ?? '') === 'paid' && (int) ($coupon['coupon_issues'][0]['nominal_price_amount'] ?? 0) === 120, 'coupon retained export must include issue claim price snapshot.');
    sr_privacy_export_runtime_assert(($coupon['coupon_issues'][0]['asset_reference_module'] ?? '') === 'coupon' && ($coupon['coupon_issues'][0]['asset_reference_type'] ?? '') === 'paid_claim' && ($coupon['coupon_issues'][0]['asset_reference_id'] ?? '') === '1', 'coupon retained export must include issue asset reference evidence.');
    $couponIssueSnapshot = json_decode((string) ($coupon['coupon_issues'][0]['claim_snapshot_json'] ?? ''), true);
    sr_privacy_export_runtime_assert(is_array($couponIssueSnapshot) && ($couponIssueSnapshot['settlement_kind'] ?? '') === 'paid' && ($couponIssueSnapshot['snapshot_schema_version'] ?? '') === 'asset_settlement_snapshot_v1', 'coupon retained export must include issue claim settlement snapshot.');
    sr_privacy_export_runtime_assert(($couponIssueSnapshot['charged_allocations'][0]['purchase_power_snapshot']['rounding_policy_version'] ?? '') === 'asset_settlement_rounding_v1', 'coupon retained export must include paid claim allocation purchase power evidence.');
    sr_privacy_export_runtime_assert(count($coupon['coupon_redemptions'] ?? []) === 1 && (int) ($coupon['coupon_redemptions'][0]['refunded_by_account_id'] ?? 0) === 99, 'coupon retained export must include refund operator evidence.');
    sr_privacy_export_runtime_assert((int) ($coupon['coupon_redemptions'][0]['amount'] ?? 0) === 120 && ($coupon['coupon_redemptions'][0]['currency_code'] ?? '') === 'KRW', 'coupon retained export must include redemption pricing snapshot amount and currency.');
    sr_privacy_export_runtime_assert(($coupon['coupon_redemptions'][0]['policy_summary'] ?? '') === '콘텐츠 다운로드 120KRW', 'coupon retained export must include redemption pricing policy summary.');
    $couponTargetSnapshot = json_decode((string) ($coupon['coupon_redemptions'][0]['target_snapshot_json'] ?? ''), true);
    sr_privacy_export_runtime_assert(is_array($couponTargetSnapshot) && ($couponTargetSnapshot['target_type'] ?? '') === 'content_file' && (int) ($couponTargetSnapshot['amount'] ?? 0) === 120, 'coupon retained export must include whitelisted redemption target snapshot.');
    sr_privacy_export_runtime_assert(count($coupon['coupon_claim_logs'] ?? []) === 1 && ($coupon['coupon_claim_logs'][0]['campaign_key'] ?? '') === 'claim7', 'coupon retained export must include only target claim logs.');
    sr_privacy_export_runtime_assert(($coupon['coupon_claim_logs'][0]['claim_source'] ?? '') === 'coupon_zone', 'coupon retained export must include claim source evidence.');
    sr_privacy_export_runtime_assert(($coupon['coupon_claim_logs'][0]['payment_reference_module'] ?? '') === '' && ($coupon['coupon_claim_logs'][0]['payment_reference_type'] ?? '') === '', 'coupon retained export must keep paid claim payment references empty.');
    sr_privacy_export_runtime_assert(($coupon['coupon_claim_logs'][0]['asset_reference_module'] ?? '') === 'coupon' && ($coupon['coupon_claim_logs'][0]['asset_reference_type'] ?? '') === 'paid_claim' && ($coupon['coupon_claim_logs'][0]['asset_reference_id'] ?? '') === '1', 'coupon retained export must include claim log asset reference evidence.');

    $deposit = $depositExport($pdo, 7);
    sr_privacy_export_runtime_assert((int) ($deposit['balance']['balance'] ?? 0) === 500, 'deposit retained export must include target balance.');
    sr_privacy_export_runtime_assert(count($deposit['transactions'] ?? []) === 1 && (int) ($deposit['transactions'][0]['created_by_account_id'] ?? 0) === 99, 'deposit retained export must include target transaction operator.');
    sr_privacy_export_runtime_assert(count($deposit['refund_requests'] ?? []) === 1 && ($deposit['refund_requests'][0]['bank_account_number'] ?? '') === '111-7', 'deposit retained export must include target refund account evidence.');

    $notification = $notificationExport($pdo, 7);
    sr_privacy_export_runtime_assert(count($notification['notifications'] ?? []) === 2, 'notification retained export must include account and all-audience notifications.');
    sr_privacy_export_runtime_assert(count($notification['reads'] ?? []) === 1, 'notification retained export must include target read rows.');
    sr_privacy_export_runtime_assert(count($notification['deliveries'] ?? []) === 4, 'notification retained export must include site deliveries, account delivery, target email delivery, and target push endpoint delivery only.');
    $deliveryRecipients = array_map(static fn (array $row): string => (string) ($row['recipient'] ?? ''), $notification['deliveries'] ?? []);
    sr_privacy_export_runtime_assert(in_array('member7@example.test', $deliveryRecipients, true) && !in_array('member8@example.test', $deliveryRecipients, true), 'notification retained export must not include other account email deliveries.');
    sr_privacy_export_runtime_assert(in_array('tg7***', $deliveryRecipients, true) && !in_array('endpoint:1', $deliveryRecipients, true), 'notification retained export must mask target push endpoint deliveries.');
    sr_privacy_export_runtime_assert(!in_array('tg8***', $deliveryRecipients, true) && !in_array('endpoint:2', $deliveryRecipients, true), 'notification retained export must exclude other account push endpoint deliveries.');
    $deliveryProviderMessages = array_map(static fn (array $row): string => (string) ($row['provider_message_id'] ?? ''), $notification['deliveries'] ?? []);
    sr_privacy_export_runtime_assert(in_array('provider7', $deliveryProviderMessages, true), 'notification retained export must include provider message evidence.');
    sr_privacy_export_runtime_assert(!in_array('provider-leak', $deliveryProviderMessages, true), 'notification retained export must exclude non-target email provider evidence even on target account notifications.');
    sr_privacy_export_runtime_assert(in_array('push7', $deliveryProviderMessages, true) && !in_array('push-leak', $deliveryProviderMessages, true), 'notification retained export must exclude non-target push endpoint provider evidence.');

    $point = $pointExport($pdo, 7);
    sr_privacy_export_runtime_assert((int) ($point['balance']['balance'] ?? 0) === 70, 'point retained export must include target balance.');
    sr_privacy_export_runtime_assert(count($point['transactions'] ?? []) === 1 && ($point['transactions'][0]['expires_at'] ?? '') === '2026-12-31', 'point retained export must include target expiration evidence.');
    sr_privacy_export_runtime_assert(count($point['expiration_consumptions'] ?? []) === 1 && (int) ($point['expiration_consumptions'][0]['source_transaction_id'] ?? 0) === 1, 'point retained export must include target expiration consumption mapping.');

    $reward = $rewardExport($pdo, 7);
    sr_privacy_export_runtime_assert((int) ($reward['balance']['balance'] ?? 0) === 700, 'reward retained export must include target balance.');
    sr_privacy_export_runtime_assert(count($reward['transactions'] ?? []) === 1 && (int) ($reward['transactions'][0]['created_by_account_id'] ?? 0) === 99 && ($reward['transactions'][0]['expires_at'] ?? '') === '2026-12-31', 'reward retained export must include target transaction operator and expiration evidence.');
    sr_privacy_export_runtime_assert(count($reward['expiration_consumptions'] ?? []) === 1 && (int) ($reward['expiration_consumptions'][0]['source_transaction_id'] ?? 0) === 1, 'reward retained export must include target expiration consumption mapping.');
    sr_privacy_export_runtime_assert(count($reward['withdrawal_requests'] ?? []) === 1 && ($reward['withdrawal_requests'][0]['bank_account_number'] ?? '') === '222-7', 'reward retained export must include target withdrawal account evidence.');
}

sr_privacy_export_runtime_check_member();
sr_privacy_export_runtime_check_quiz();
sr_privacy_export_runtime_check_survey();
sr_privacy_export_runtime_check_content();
sr_privacy_export_runtime_check_community();
sr_privacy_export_runtime_check_retained_modules();

if ($errors !== []) {
    fwrite(STDERR, "privacy export runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "privacy export runtime checks completed.\n";
