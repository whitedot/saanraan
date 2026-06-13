#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

$errors = [];

function sr_privacy_cleanup_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

if (!function_exists('sr_now')) {
    function sr_now(): string
    {
        return '2026-06-12 00:00:00';
    }
}

function sr_privacy_cleanup_runtime_pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->sqliteCreateFunction('CONCAT', static function (...$parts): string {
        return implode('', array_map(static fn ($part): string => (string) $part, $parts));
    }, -1);

    return $pdo;
}

function sr_privacy_cleanup_runtime_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function sr_privacy_cleanup_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_privacy_cleanup_runtime_error($message);
    }
}

function sr_privacy_cleanup_runtime_check_quiz(): void
{
    $cleanup = include 'modules/quiz/privacy-cleanup.php';
    if (!is_callable($cleanup)) {
        sr_privacy_cleanup_runtime_error('quiz privacy cleanup contract is not callable.');
        return;
    }

    $pdo = sr_privacy_cleanup_runtime_pdo();
    $pdo->exec(
        'CREATE TABLE sr_quiz_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NULL,
            user_agent_hash TEXT NULL,
            ip_hash TEXT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_quiz_reward_grants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NULL,
            dedupe_key TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_quiz_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            author_account_id INTEGER NULL,
            author_public_name_snapshot TEXT NOT NULL
        )'
    );
    $pdo->exec("INSERT INTO sr_quiz_attempts (account_id, user_agent_hash, ip_hash) VALUES (7, 'ua7', 'ip7'), (8, 'ua8', 'ip8')");
    $pdo->exec("INSERT INTO sr_quiz_reward_grants (account_id, dedupe_key) VALUES (7, 'quiz:7'), (8, 'quiz:8')");
    $pdo->exec("INSERT INTO sr_quiz_comments (author_account_id, author_public_name_snapshot) VALUES (7, 'name7'), (8, 'name8')");

    $invalidResult = $cleanup($pdo, 0, ['event_type' => 'withdrawal']);
    sr_privacy_cleanup_runtime_assert(is_array($invalidResult) && ($invalidResult['cleaned'] ?? null) === false, 'quiz cleanup must return cleaned=false for invalid account id.');

    $result = $cleanup($pdo, 7, ['event_type' => 'withdrawal']);
    sr_privacy_cleanup_runtime_assert(is_array($result), 'quiz cleanup must return an array result.');
    sr_privacy_cleanup_runtime_assert(($result['cleaned'] ?? null) === true, 'quiz cleanup result must include cleaned=true.');
    sr_privacy_cleanup_runtime_assert(($result['event_type'] ?? '') === 'withdrawal', 'quiz cleanup result must preserve event_type.');
    sr_privacy_cleanup_runtime_assert((int) ($result['quiz_attempt_anonymized_count'] ?? -1) === 1, 'quiz cleanup must report attempt anonymization count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['quiz_reward_grant_anonymized_count'] ?? -1) === 1, 'quiz cleanup must report reward grant anonymization count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['quiz_comment_anonymized_count'] ?? -1) === 1, 'quiz cleanup must report comment anonymization count.');

    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_quiz_attempts WHERE account_id IS NULL AND user_agent_hash IS NULL AND ip_hash IS NULL') === 1, 'quiz cleanup must anonymize target attempts.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT dedupe_key FROM sr_quiz_reward_grants WHERE id = 1') === 'anonymized:quiz_reward:1', 'quiz cleanup must rewrite target reward dedupe key.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT author_public_name_snapshot FROM sr_quiz_comments WHERE id = 1') === '', 'quiz cleanup must clear target comment public name snapshot.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT user_agent_hash FROM sr_quiz_attempts WHERE account_id = 8') === 'ua8', 'quiz cleanup must not alter other account attempts.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT dedupe_key FROM sr_quiz_reward_grants WHERE account_id = 8') === 'quiz:8', 'quiz cleanup must not alter other account reward grants.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT author_public_name_snapshot FROM sr_quiz_comments WHERE author_account_id = 8') === 'name8', 'quiz cleanup must not alter other account comments.');
}

function sr_privacy_cleanup_runtime_check_survey(): void
{
    $cleanup = include 'modules/survey/privacy-cleanup.php';
    if (!is_callable($cleanup)) {
        sr_privacy_cleanup_runtime_error('survey privacy cleanup contract is not callable.');
        return;
    }

    $pdo = sr_privacy_cleanup_runtime_pdo();
    $pdo->exec(
        'CREATE TABLE sr_survey_responses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NULL,
            user_agent_hash TEXT NULL,
            ip_hash TEXT NULL,
            updated_at TEXT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_survey_reward_grants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NULL,
            dedupe_key TEXT NOT NULL,
            updated_at TEXT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_survey_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            author_account_id INTEGER NULL,
            author_public_name_snapshot TEXT NOT NULL,
            updated_at TEXT NULL
        )'
    );
    $pdo->exec("INSERT INTO sr_survey_responses (account_id, user_agent_hash, ip_hash, updated_at) VALUES (7, 'ua7', 'ip7', ''), (8, 'ua8', 'ip8', '')");
    $pdo->exec("INSERT INTO sr_survey_reward_grants (account_id, dedupe_key, updated_at) VALUES (7, 'survey:7', ''), (8, 'survey:8', '')");
    $pdo->exec("INSERT INTO sr_survey_comments (author_account_id, author_public_name_snapshot, updated_at) VALUES (7, 'name7', ''), (8, 'name8', '')");

    $invalidResult = $cleanup($pdo, 0);
    sr_privacy_cleanup_runtime_assert(is_array($invalidResult) && ($invalidResult['cleaned'] ?? null) === false, 'survey cleanup must return cleaned=false for invalid account id.');

    $result = $cleanup($pdo, 7);
    sr_privacy_cleanup_runtime_assert(is_array($result), 'survey cleanup must return an array result.');
    sr_privacy_cleanup_runtime_assert(($result['cleaned'] ?? null) === true, 'survey cleanup result must include cleaned=true.');
    sr_privacy_cleanup_runtime_assert((int) ($result['survey_response_anonymized_count'] ?? -1) === 1, 'survey cleanup must report response anonymization count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['survey_reward_grant_anonymized_count'] ?? -1) === 1, 'survey cleanup must report reward grant anonymization count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['survey_comment_anonymized_count'] ?? -1) === 1, 'survey cleanup must report comment anonymization count.');

    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_survey_responses WHERE account_id IS NULL AND user_agent_hash IS NULL AND ip_hash IS NULL AND updated_at = :updated_at', ['updated_at' => sr_now()]) === 1, 'survey cleanup must anonymize target responses and timestamp the update.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT dedupe_key FROM sr_survey_reward_grants WHERE id = 1') === 'anonymized:survey_reward:1', 'survey cleanup must rewrite target reward dedupe key.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT author_public_name_snapshot FROM sr_survey_comments WHERE id = 1') === '', 'survey cleanup must clear target comment public name snapshot.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT user_agent_hash FROM sr_survey_responses WHERE account_id = 8') === 'ua8', 'survey cleanup must not alter other account responses.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT dedupe_key FROM sr_survey_reward_grants WHERE account_id = 8') === 'survey:8', 'survey cleanup must not alter other account reward grants.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT author_public_name_snapshot FROM sr_survey_comments WHERE author_account_id = 8') === 'name8', 'survey cleanup must not alter other account comments.');
}

function sr_privacy_cleanup_runtime_check_content(): void
{
    require_once SR_ROOT . '/modules/content/helpers.php';
    $cleanup = include 'modules/content/privacy-cleanup.php';
    if (!is_callable($cleanup)) {
        sr_privacy_cleanup_runtime_error('content privacy cleanup contract is not callable.');
        return;
    }

    $pdo = sr_privacy_cleanup_runtime_pdo();
    $pdo->exec(
        'CREATE TABLE sr_content_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            author_account_id INTEGER NULL,
            author_public_name_snapshot TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_content_file_download_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_content_author_applications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NULL,
            application_note TEXT NOT NULL,
            review_note TEXT NOT NULL,
            updated_at TEXT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_content_access_entitlements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NULL,
            source_reference TEXT NOT NULL,
            anonymized_at TEXT NULL
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
            created_by INTEGER NULL,
            updated_by INTEGER NULL,
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
            created_by INTEGER NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );

    $pdo->exec("INSERT INTO sr_content_comments (author_account_id, author_public_name_snapshot) VALUES (7, 'name7'), (8, 'name8')");
    $pdo->exec("INSERT INTO sr_content_file_download_logs (account_id) VALUES (7), (8)");
    $pdo->exec("INSERT INTO sr_content_author_applications (account_id, application_note, review_note, updated_at) VALUES (7, 'apply7', 'review7', ''), (8, 'apply8', 'review8', '')");
    $pdo->exec("INSERT INTO sr_content_access_entitlements (account_id, source_reference, anonymized_at) VALUES (7, 'ref7', NULL), (8, 'ref8', NULL)");
    $pdo->exec("INSERT INTO sr_content_series (series_key, title, description, status, visibility, sort_order, created_by, updated_by, created_at, updated_at) VALUES ('s7', 'S7', '', 'active', 'public', 0, 7, 7, '', ''), ('s8', 'S8', '', 'active', 'public', 0, 8, 8, '', '')");
    $pdo->exec("INSERT INTO sr_content_series_items (series_id, content_id, active_content_id, episode_label, item_status, sort_order, created_by, created_at, updated_at) VALUES (1, 1, 1, '', 'active', 0, 7, '', ''), (2, 2, 2, '', 'active', 0, 8, '', '')");

    $invalidResult = $cleanup($pdo, 0);
    sr_privacy_cleanup_runtime_assert(is_array($invalidResult) && ($invalidResult['cleaned'] ?? null) === false, 'content cleanup must return cleaned=false for invalid account id.');

    $result = $cleanup($pdo, 7, ['event_type' => 'withdrawal']);
    sr_privacy_cleanup_runtime_assert(is_array($result), 'content cleanup must return an array result.');
    sr_privacy_cleanup_runtime_assert(($result['cleaned'] ?? null) === true, 'content cleanup result must include cleaned=true.');
    sr_privacy_cleanup_runtime_assert(($result['event_type'] ?? '') === 'withdrawal', 'content cleanup result must preserve event_type.');
    sr_privacy_cleanup_runtime_assert((int) ($result['content_access_entitlement_anonymized_count'] ?? -1) === 1, 'content cleanup must report access entitlement anonymization count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['content_author_snapshot_anonymized_count'] ?? -1) === 1, 'content cleanup must report comment snapshot anonymization count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['content_file_download_log_anonymized_count'] ?? -1) === 1, 'content cleanup must report file download anonymization count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['content_author_application_anonymized_count'] ?? -1) === 1, 'content cleanup must report author application anonymization count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['content_series_metadata_anonymized_count'] ?? -1) === 3, 'content cleanup must report series metadata anonymization count.');

    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT author_public_name_snapshot FROM sr_content_comments WHERE id = 1') === '', 'content cleanup must clear target comment public name snapshot.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_content_file_download_logs WHERE account_id IS NULL') === 1, 'content cleanup must anonymize target file download logs.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT application_note FROM sr_content_author_applications WHERE id = 1') === '', 'content cleanup must clear target author application note.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT source_reference FROM sr_content_access_entitlements WHERE id = 1') === '', 'content cleanup must clear target entitlement source reference.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_content_series WHERE id = 1 AND created_by IS NULL AND updated_by IS NULL') === 1, 'content cleanup must clear target series metadata.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_content_series_items WHERE id = 1 AND created_by IS NULL') === 1, 'content cleanup must clear target series item metadata.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT author_public_name_snapshot FROM sr_content_comments WHERE id = 2') === 'name8', 'content cleanup must not alter other account comments.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT source_reference FROM sr_content_access_entitlements WHERE id = 2') === 'ref8', 'content cleanup must not alter other account entitlements.');
}

function sr_privacy_cleanup_runtime_check_community(): void
{
    require_once SR_ROOT . '/modules/member/helpers/nicknames.php';
    require_once SR_ROOT . '/modules/community/helpers.php';
    $cleanup = include 'modules/community/privacy-cleanup.php';
    if (!is_callable($cleanup)) {
        sr_privacy_cleanup_runtime_error('community privacy cleanup contract is not callable.');
        return;
    }

    $pdo = sr_privacy_cleanup_runtime_pdo();
    $pdo->exec('CREATE TABLE sr_member_nicknames (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL, nickname TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_levels (id INTEGER PRIMARY KEY AUTOINCREMENT)');
    $pdo->exec('CREATE TABLE sr_community_account_levels (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_level_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL)');
    $pdo->exec(
        'CREATE TABLE sr_community_access_entitlements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NULL,
            source_reference TEXT NOT NULL,
            anonymized_at TEXT NULL
        )'
    );
    $pdo->exec('CREATE TABLE sr_community_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, author_account_id INTEGER NULL, author_public_name_snapshot TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_post_field_values (id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER NOT NULL, cleanup_policy_snapshot TEXT NOT NULL, value_text TEXT NULL, value_json TEXT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_comments (id INTEGER PRIMARY KEY AUTOINCREMENT, author_account_id INTEGER NULL, author_public_name_snapshot TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_series (id INTEGER PRIMARY KEY AUTOINCREMENT, created_by INTEGER NULL, updated_by INTEGER NULL, moderated_by INTEGER NULL)');
    $pdo->exec('CREATE TABLE sr_community_series_items (id INTEGER PRIMARY KEY AUTOINCREMENT, created_by INTEGER NULL)');
    $pdo->exec('CREATE TABLE sr_community_series_scraps (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL)');
    $pdo->exec(
        'CREATE TABLE sr_community_submission_consents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NULL,
            ip_hash TEXT NULL,
            user_agent_hash TEXT NULL
        )'
    );

    $pdo->exec("INSERT INTO sr_member_nicknames (account_id, nickname) VALUES (7, 'nick7'), (8, 'nick8')");
    $pdo->exec("INSERT INTO sr_community_account_levels (account_id) VALUES (7), (8)");
    $pdo->exec("INSERT INTO sr_community_level_logs (account_id) VALUES (7), (8)");
    $pdo->exec("INSERT INTO sr_community_access_entitlements (account_id, source_reference, anonymized_at) VALUES (7, 'ref7', NULL), (8, 'ref8', NULL)");
    $pdo->exec("INSERT INTO sr_community_posts (author_account_id, author_public_name_snapshot) VALUES (7, 'post7'), (8, 'post8')");
    $pdo->exec("INSERT INTO sr_community_post_field_values (post_id, cleanup_policy_snapshot, value_text, value_json, updated_at) VALUES (1, 'anonymize', 'field7', '{\"value\":\"field7\"}', ''), (1, 'retain', 'retain7', '{\"value\":\"retain7\"}', ''), (2, 'anonymize', 'field8', '{\"value\":\"field8\"}', '')");
    $pdo->exec("INSERT INTO sr_community_comments (author_account_id, author_public_name_snapshot) VALUES (7, 'comment7'), (8, 'comment8')");
    $pdo->exec("INSERT INTO sr_community_series (created_by, updated_by, moderated_by) VALUES (7, 7, 7), (8, 8, 8)");
    $pdo->exec("INSERT INTO sr_community_series_items (created_by) VALUES (7), (8)");
    $pdo->exec("INSERT INTO sr_community_series_scraps (account_id) VALUES (7), (8)");
    $pdo->exec("INSERT INTO sr_community_submission_consents (account_id, ip_hash, user_agent_hash) VALUES (7, 'ip7', 'ua7'), (8, 'ip8', 'ua8')");

    $invalidResult = $cleanup($pdo, 0);
    sr_privacy_cleanup_runtime_assert(is_array($invalidResult) && ($invalidResult['cleaned'] ?? null) === false, 'community cleanup must return cleaned=false for invalid account id.');

    $result = $cleanup($pdo, 7, ['event_type' => 'withdrawal']);
    sr_privacy_cleanup_runtime_assert(is_array($result), 'community cleanup must return an array result.');
    sr_privacy_cleanup_runtime_assert(($result['cleaned'] ?? null) === true, 'community cleanup result must include cleaned=true.');
    sr_privacy_cleanup_runtime_assert(($result['event_type'] ?? '') === 'withdrawal', 'community cleanup result must preserve event_type.');
    sr_privacy_cleanup_runtime_assert(($result['community_member_nickname_deleted'] ?? null) === true, 'community cleanup must report nickname deletion.');
    sr_privacy_cleanup_runtime_assert(($result['community_account_level_deleted'] ?? null) === true, 'community cleanup must report account level deletion.');
    sr_privacy_cleanup_runtime_assert((int) ($result['community_level_log_deleted_count'] ?? -1) === 1, 'community cleanup must report level log deletion count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['community_access_entitlement_anonymized_count'] ?? -1) === 1, 'community cleanup must report access entitlement anonymization count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['community_author_snapshot_anonymized_count'] ?? -1) === 2, 'community cleanup must report author snapshot anonymization count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['community_post_field_values_anonymized_count'] ?? -1) === 1, 'community cleanup must report post field values anonymization count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['community_submission_consent_anonymized_count'] ?? -1) === 1, 'community cleanup must report submission consent anonymization count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['community_series_scrap_deleted_count'] ?? -1) === 1, 'community cleanup must report series scrap deletion count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['community_series_metadata_anonymized_count'] ?? -1) === 4, 'community cleanup must report series metadata anonymization count.');

    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_member_nicknames WHERE account_id = 7') === 0, 'community cleanup must delete target nickname.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_community_account_levels WHERE account_id = 7') === 0, 'community cleanup must delete target account level.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT source_reference FROM sr_community_access_entitlements WHERE id = 1') === '', 'community cleanup must clear target entitlement source reference.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT author_public_name_snapshot FROM sr_community_posts WHERE id = 1') === '', 'community cleanup must clear target post public name snapshot.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT value_text FROM sr_community_post_field_values WHERE id = 1') === '', 'community cleanup must clear anonymize post field value.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT value_text FROM sr_community_post_field_values WHERE id = 2') === 'retain7', 'community cleanup must retain retain-policy post field value.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT author_public_name_snapshot FROM sr_community_comments WHERE id = 1') === '', 'community cleanup must clear target comment public name snapshot.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_community_series WHERE id = 1 AND created_by IS NULL AND updated_by IS NULL AND moderated_by IS NULL') === 1, 'community cleanup must clear target series metadata.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_community_series_items WHERE id = 1 AND created_by IS NULL') === 1, 'community cleanup must clear target series item metadata.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_community_submission_consents WHERE id = 1 AND account_id IS NULL AND ip_hash IS NULL AND user_agent_hash IS NULL') === 1, 'community cleanup must anonymize target submission consent.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT nickname FROM sr_member_nicknames WHERE account_id = 8') === 'nick8', 'community cleanup must not alter other account nickname.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT source_reference FROM sr_community_access_entitlements WHERE id = 2') === 'ref8', 'community cleanup must not alter other account entitlement.');
}

sr_privacy_cleanup_runtime_check_quiz();
sr_privacy_cleanup_runtime_check_survey();
sr_privacy_cleanup_runtime_check_content();
sr_privacy_cleanup_runtime_check_community();

if ($errors !== []) {
    fwrite(STDERR, "privacy cleanup runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "privacy cleanup runtime checks completed.\n";
