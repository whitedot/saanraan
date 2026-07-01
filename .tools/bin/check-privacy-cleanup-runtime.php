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

function sr_privacy_cleanup_runtime_check_asset_ledger(): void
{
    $cleanup = include 'modules/asset_ledger/privacy-cleanup.php';
    if (!is_callable($cleanup)) {
        sr_privacy_cleanup_runtime_error('asset_ledger privacy cleanup contract is not callable.');
        return;
    }

    $pdo = sr_privacy_cleanup_runtime_pdo();
    $pdo->exec('CREATE TABLE sr_asset_recovery_failures (id INTEGER PRIMARY KEY, dedupe_key TEXT NOT NULL, account_id INTEGER NOT NULL, actor_account_id INTEGER NULL, operation_context_json TEXT NULL, version INTEGER NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec("INSERT INTO sr_asset_recovery_failures (id, dedupe_key, account_id, actor_account_id, operation_context_json, version, updated_at) VALUES (1, 'source:community:1:rev:community.post.reward_reversal', 7, 7, '{\"route_context\":\"admin.assets.recovery_failures\"}', 1, ''), (2, 'source:community:2:rev:community.post.reward_reversal', 8, 7, '{}', 1, '')");

    $invalidResult = $cleanup($pdo, 0, []);
    sr_privacy_cleanup_runtime_assert(is_array($invalidResult) && ($invalidResult['cleaned'] ?? null) === false, 'asset_ledger cleanup must return cleaned=false for invalid account id.');

    $result = $cleanup($pdo, 7, []);
    sr_privacy_cleanup_runtime_assert(($result['cleaned'] ?? null) === true, 'asset_ledger cleanup must return cleaned=true.');
    sr_privacy_cleanup_runtime_assert((int) ($result['asset_recovery_failures_anonymized'] ?? -1) === 1, 'asset_ledger cleanup must report recovery failure anonymization count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['asset_recovery_failure_actor_links_cleared'] ?? -1) === 1, 'asset_ledger cleanup must report actor link cleanup count.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT account_id FROM sr_asset_recovery_failures WHERE id = 1') === 0, 'asset_ledger cleanup must remove target account id.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT dedupe_key FROM sr_asset_recovery_failures WHERE id = 1') === 'anonymized:asset_recovery:1', 'asset_ledger cleanup must rewrite target dedupe key.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT operation_context_json FROM sr_asset_recovery_failures WHERE id = 1') === '{}', 'asset_ledger cleanup must clear operation context.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT account_id FROM sr_asset_recovery_failures WHERE id = 2') === 8, 'asset_ledger cleanup must not alter other account rows.');
    sr_privacy_cleanup_runtime_assert(sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT actor_account_id FROM sr_asset_recovery_failures WHERE id = 2') === null, 'asset_ledger cleanup must clear actor-only recovery links.');
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
    $pdo->exec('CREATE TABLE sr_content_asset_access_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE sr_content_asset_action_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE sr_content_author_reward_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, author_account_id INTEGER NOT NULL, created_by_account_id INTEGER NULL)');
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
    $pdo->exec("INSERT INTO sr_content_asset_access_logs (account_id) VALUES (7), (8)");
    $pdo->exec("INSERT INTO sr_content_asset_action_logs (account_id) VALUES (7), (8)");
    $pdo->exec("INSERT INTO sr_content_author_reward_logs (author_account_id, created_by_account_id) VALUES (7, 1), (8, 1)");
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
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT account_id FROM sr_content_asset_access_logs WHERE id = 1') === 7, 'content cleanup must retain account id on asset access logs.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT account_id FROM sr_content_asset_action_logs WHERE id = 1') === 7, 'content cleanup must retain account id on asset action logs.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT author_account_id FROM sr_content_author_reward_logs WHERE id = 1') === 7, 'content cleanup must retain author account id on reward logs.');
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
    $pdo->exec('CREATE TABLE sr_community_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, author_account_id INTEGER NULL, author_public_name_snapshot TEXT NOT NULL, extra_values_json TEXT NOT NULL DEFAULT "[]", updated_at TEXT NOT NULL DEFAULT "")');
    $pdo->exec('CREATE TABLE sr_community_post_field_values (id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER NOT NULL, cleanup_policy_snapshot TEXT NOT NULL, value_text TEXT NULL, value_json TEXT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_comments (id INTEGER PRIMARY KEY AUTOINCREMENT, author_account_id INTEGER NULL, author_public_name_snapshot TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_series (id INTEGER PRIMARY KEY AUTOINCREMENT, created_by INTEGER NULL, updated_by INTEGER NULL, moderated_by INTEGER NULL)');
    $pdo->exec('CREATE TABLE sr_community_series_items (id INTEGER PRIMARY KEY AUTOINCREMENT, created_by INTEGER NULL)');
    $pdo->exec('CREATE TABLE sr_community_series_scraps (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_asset_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE sr_community_asset_recovery_failures (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL, actor_account_id INTEGER NULL, operation_context_json TEXT NULL)');
    $pdo->exec('CREATE TABLE sr_community_publisher_reward_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, downloader_account_id INTEGER NOT NULL, publisher_account_id INTEGER NOT NULL)');
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
    $pdo->exec("INSERT INTO sr_community_posts (author_account_id, author_public_name_snapshot, extra_values_json) VALUES (7, 'post7', '{\"public\":{\"label\":\"Public\",\"value\":\"field7\",\"cleanup_policy\":\"anonymize\"},\"retain\":{\"label\":\"Retain\",\"value\":\"retain7\",\"cleanup_policy\":\"retain\"},\"legacy\":{\"label\":\"Legacy\",\"value\":\"legacy7\"}}'), (8, 'post8', '{\"public\":{\"label\":\"Public\",\"value\":\"field8\",\"cleanup_policy\":\"anonymize\"}}')");
    $pdo->exec("INSERT INTO sr_community_post_field_values (post_id, cleanup_policy_snapshot, value_text, value_json, updated_at) VALUES (1, 'anonymize', 'field7', '{\"value\":\"field7\"}', ''), (1, 'retain', 'retain7', '{\"value\":\"retain7\"}', ''), (1, '', 'legacy7', '{\"value\":\"legacy7\"}', ''), (2, 'anonymize', 'field8', '{\"value\":\"field8\"}', '')");
    $pdo->exec("INSERT INTO sr_community_comments (author_account_id, author_public_name_snapshot) VALUES (7, 'comment7'), (8, 'comment8')");
    $pdo->exec("INSERT INTO sr_community_series (created_by, updated_by, moderated_by) VALUES (7, 7, 7), (8, 8, 8)");
    $pdo->exec("INSERT INTO sr_community_series_items (created_by) VALUES (7), (8)");
    $pdo->exec("INSERT INTO sr_community_series_scraps (account_id) VALUES (7), (8)");
    $pdo->exec("INSERT INTO sr_community_asset_logs (account_id) VALUES (7), (8)");
    $pdo->exec("INSERT INTO sr_community_asset_recovery_failures (account_id, actor_account_id, operation_context_json) VALUES (7, 7, '{\"route_context\":\"admin.community.posts\"}'), (8, 7, '{\"route_context\":\"admin.community.posts\"}')");
    $pdo->exec("INSERT INTO sr_community_publisher_reward_logs (downloader_account_id, publisher_account_id) VALUES (7, 8), (8, 7)");
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
    sr_privacy_cleanup_runtime_assert((int) ($result['community_post_extra_values_anonymized_count'] ?? -1) === 1, 'community cleanup must report post extra value snapshot anonymization count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['community_post_field_values_anonymized_count'] ?? -1) === 2, 'community cleanup must report post field values anonymization count including legacy policy rows.');
    sr_privacy_cleanup_runtime_assert((int) ($result['community_submission_consent_anonymized_count'] ?? -1) === 1, 'community cleanup must report submission consent anonymization count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['community_asset_recovery_failure_anonymized_count'] ?? -1) === 1, 'community cleanup must report asset recovery failure anonymization count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['community_asset_recovery_failure_actor_links_cleared'] ?? -1) === 1, 'community cleanup must report asset recovery actor link cleanup count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['community_series_scrap_deleted_count'] ?? -1) === 1, 'community cleanup must report series scrap deletion count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['community_series_metadata_anonymized_count'] ?? -1) === 4, 'community cleanup must report series metadata anonymization count.');

    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_member_nicknames WHERE account_id = 7') === 0, 'community cleanup must delete target nickname.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_community_account_levels WHERE account_id = 7') === 0, 'community cleanup must delete target account level.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT source_reference FROM sr_community_access_entitlements WHERE id = 1') === '', 'community cleanup must clear target entitlement source reference.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT author_public_name_snapshot FROM sr_community_posts WHERE id = 1') === '', 'community cleanup must clear target post public name snapshot.');
    $cleanedExtraValues = json_decode((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT extra_values_json FROM sr_community_posts WHERE id = 1'), true);
    sr_privacy_cleanup_runtime_assert(is_array($cleanedExtraValues), 'community cleanup must keep post extra value snapshot JSON decodable.');
    sr_privacy_cleanup_runtime_assert((string) ($cleanedExtraValues['public']['value'] ?? 'missing') === '', 'community cleanup must clear anonymize post extra value snapshot.');
    sr_privacy_cleanup_runtime_assert((string) ($cleanedExtraValues['legacy']['value'] ?? 'missing') === '', 'community cleanup must treat legacy post extra value snapshots as anonymize.');
    sr_privacy_cleanup_runtime_assert((string) ($cleanedExtraValues['retain']['value'] ?? '') === 'retain7', 'community cleanup must retain retain-policy post extra value snapshot.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT value_text FROM sr_community_post_field_values WHERE id = 1') === '', 'community cleanup must clear anonymize post field value.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT value_text FROM sr_community_post_field_values WHERE id = 2') === 'retain7', 'community cleanup must retain retain-policy post field value.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT value_text FROM sr_community_post_field_values WHERE id = 3') === '', 'community cleanup must treat legacy post field value policies as anonymize.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT author_public_name_snapshot FROM sr_community_comments WHERE id = 1') === '', 'community cleanup must clear target comment public name snapshot.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_community_series WHERE id = 1 AND created_by IS NULL AND updated_by IS NULL AND moderated_by IS NULL') === 1, 'community cleanup must clear target series metadata.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_community_series_items WHERE id = 1 AND created_by IS NULL') === 1, 'community cleanup must clear target series item metadata.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT account_id FROM sr_community_asset_logs WHERE id = 1') === 7, 'community cleanup must retain account id on asset logs.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT account_id FROM sr_community_asset_recovery_failures WHERE id = 1') === 0, 'community cleanup must anonymize target asset recovery failure account id.');
    sr_privacy_cleanup_runtime_assert(sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT actor_account_id FROM sr_community_asset_recovery_failures WHERE id = 2') === null, 'community cleanup must clear actor-only asset recovery links.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT downloader_account_id FROM sr_community_publisher_reward_logs WHERE id = 1') === 7, 'community cleanup must retain downloader account id on publisher reward logs.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT publisher_account_id FROM sr_community_publisher_reward_logs WHERE id = 2') === 7, 'community cleanup must retain publisher account id on publisher reward logs.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_community_submission_consents WHERE id = 1 AND account_id IS NULL AND ip_hash IS NULL AND user_agent_hash IS NULL') === 1, 'community cleanup must anonymize target submission consent.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT nickname FROM sr_member_nicknames WHERE account_id = 8') === 'nick8', 'community cleanup must not alter other account nickname.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT source_reference FROM sr_community_access_entitlements WHERE id = 2') === 'ref8', 'community cleanup must not alter other account entitlement.');
}

function sr_privacy_cleanup_runtime_check_notification(): void
{
    $cleanup = include 'modules/notification/privacy-cleanup.php';
    if (!is_callable($cleanup)) {
        sr_privacy_cleanup_runtime_error('notification privacy cleanup contract is not callable.');
        return;
    }

    $pdo = sr_privacy_cleanup_runtime_pdo();
    $pdo->exec(
        'CREATE TABLE sr_notification_push_endpoints (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            provider_key TEXT NOT NULL,
            recipient_type TEXT NOT NULL DEFAULT "personal",
            endpoint_ciphertext TEXT NOT NULL,
            endpoint_fingerprint TEXT NOT NULL,
            recipient_label TEXT NOT NULL DEFAULT "",
            recipient_masked TEXT NOT NULL DEFAULT "",
            status TEXT NOT NULL DEFAULT "active",
            key_version TEXT NOT NULL DEFAULT "v1",
            verified_at TEXT NULL,
            disabled_at TEXT NULL,
            last_used_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        "INSERT INTO sr_notification_push_endpoints
            (account_id, provider_key, recipient_type, endpoint_ciphertext, endpoint_fingerprint, recipient_label, recipient_masked, status, created_at, updated_at)
         VALUES
            (7, 'telegram_bot', 'personal', 'secret7', 'fp7', 'phone7', '1234***', 'active', '', ''),
            (8, 'telegram_bot', 'personal', 'secret8', 'fp8', 'phone8', '9876***', 'active', '', '')"
    );

    $invalidResult = $cleanup($pdo, 0, ['event_type' => 'withdrawal']);
    sr_privacy_cleanup_runtime_assert(is_array($invalidResult) && ($invalidResult['cleaned'] ?? null) === false, 'notification cleanup must return cleaned=false for invalid account id.');

    $result = $cleanup($pdo, 7, ['event_type' => 'withdrawal']);
    sr_privacy_cleanup_runtime_assert(is_array($result), 'notification cleanup must return an array result.');
    sr_privacy_cleanup_runtime_assert(($result['cleaned'] ?? null) === true, 'notification cleanup result must include cleaned=true.');
    sr_privacy_cleanup_runtime_assert(($result['event_type'] ?? '') === 'withdrawal', 'notification cleanup result must preserve event_type.');
    sr_privacy_cleanup_runtime_assert((int) ($result['notification_push_endpoint_disabled_count'] ?? -1) === 1, 'notification cleanup must report push endpoint cleanup count.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT endpoint_ciphertext FROM sr_notification_push_endpoints WHERE id = 1') === '', 'notification cleanup must clear target push endpoint ciphertext.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT status FROM sr_notification_push_endpoints WHERE id = 1') === 'disabled', 'notification cleanup must disable target push endpoint.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT endpoint_ciphertext FROM sr_notification_push_endpoints WHERE id = 2') === 'secret8', 'notification cleanup must not alter other account push endpoint ciphertext.');
}

function sr_privacy_cleanup_runtime_check_policy_documents(): void
{
    $cleanup = include 'modules/policy_documents/privacy-cleanup.php';
    if (!is_callable($cleanup)) {
        sr_privacy_cleanup_runtime_error('policy_documents privacy cleanup contract is not callable.');
        return;
    }

    $pdo = sr_privacy_cleanup_runtime_pdo();
    $pdo->exec(
        'CREATE TABLE sr_policy_document_mail_deliveries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_id INTEGER NOT NULL,
            account_id INTEGER NULL,
            status TEXT NOT NULL,
            failure_code TEXT NOT NULL DEFAULT "",
            claimed_at TEXT NULL,
            sent_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        "INSERT INTO sr_policy_document_mail_deliveries
            (job_id, account_id, status, created_at, updated_at)
         VALUES
            (1, 7, 'sent', '', ''),
            (1, 8, 'sent', '', '')"
    );

    $result = $cleanup($pdo, 7, ['event_type' => 'withdrawal']);
    sr_privacy_cleanup_runtime_assert(is_array($result), 'policy_documents cleanup must return an array result.');
    sr_privacy_cleanup_runtime_assert((int) ($result['policy_document_mail_deliveries_anonymized'] ?? -1) === 1, 'policy_documents cleanup must report delivery anonymization count.');
    sr_privacy_cleanup_runtime_assert(sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT account_id FROM sr_policy_document_mail_deliveries WHERE id = 1') === null, 'policy_documents cleanup must clear target delivery account id.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT updated_at FROM sr_policy_document_mail_deliveries WHERE id = 1') === sr_now(), 'policy_documents cleanup must timestamp target delivery anonymization.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT account_id FROM sr_policy_document_mail_deliveries WHERE id = 2') === 8, 'policy_documents cleanup must not alter other account delivery account id.');
}

function sr_privacy_cleanup_runtime_check_payment_ledger(): void
{
    $cleanup = include 'modules/payment_ledger/privacy-cleanup.php';
    if (!is_callable($cleanup)) {
        sr_privacy_cleanup_runtime_error('payment_ledger privacy cleanup contract is not callable.');
        return;
    }

    $pdo = sr_privacy_cleanup_runtime_pdo();
    $pdo->exec('CREATE TABLE sr_payment_records (id INTEGER PRIMARY KEY, dedupe_key TEXT NOT NULL, account_id INTEGER NOT NULL, subject_module TEXT NOT NULL, subject_type TEXT NOT NULL, subject_id TEXT NOT NULL, payment_kind TEXT NOT NULL, status TEXT NOT NULL, payable_amount INTEGER NOT NULL, settlement_amount INTEGER NOT NULL, settlement_currency TEXT NOT NULL, description TEXT NOT NULL, snapshot_json TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, cancelled_at TEXT NULL)');
    $pdo->exec('CREATE TABLE sr_payment_record_items (id INTEGER PRIMARY KEY, payment_record_id INTEGER NOT NULL, item_kind TEXT NOT NULL, owner_module TEXT NOT NULL, reference_type TEXT NOT NULL, reference_id TEXT NOT NULL, amount INTEGER NOT NULL, currency_code TEXT NOT NULL, reversible INTEGER NOT NULL, reversal_status TEXT NOT NULL, snapshot_json TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec("INSERT INTO sr_payment_records (id, dedupe_key, account_id, subject_module, subject_type, subject_id, payment_kind, status, payable_amount, settlement_amount, settlement_currency, description, snapshot_json, created_at, updated_at, cancelled_at) VALUES (1, 'payment7', 7, 'content', 'content.view', '7801', 'purchase', 'paid', 100, 60, 'KRW', 'payment 7', '{\"schema_version\":\"payment_record_v1\"}', '', '', NULL), (2, 'payment77', 77, 'content', 'content.view', '7802', 'purchase', 'paid', 100, 100, 'KRW', 'payment 77', '{\"schema_version\":\"payment_record_v1\"}', '', '', NULL)");
    $pdo->exec("INSERT INTO sr_payment_record_items (id, payment_record_id, item_kind, owner_module, reference_type, reference_id, amount, currency_code, reversible, reversal_status, snapshot_json, created_at, updated_at) VALUES (1, 1, 'access_entitlement', 'content', 'content.access', 'content.view:7801:account:7', 0, '', 1, 'none', '{\"source_reference\":\"content:view:7801:account:7:intent:abc\",\"unrelated_reference\":\"content:view:7801:account:77:intent:abc\"}', '', ''), (2, 2, 'access_entitlement', 'content', 'content.access', 'content.view:7802:account:77', 0, '', 1, 'none', '{\"source_reference\":\"content:view:7802:account:77:intent:abc\"}', '', '')");

    $invalidResult = $cleanup($pdo, 0, ['event_type' => 'member.anonymized']);
    sr_privacy_cleanup_runtime_assert(is_array($invalidResult) && (int) ($invalidResult['payment_records'] ?? -1) === 0 && (int) ($invalidResult['payment_record_items'] ?? -1) === 0, 'payment_ledger cleanup must return zero counts for invalid account id.');

    $noopResult = $cleanup($pdo, 7, ['event_type' => 'member.profile_updated']);
    sr_privacy_cleanup_runtime_assert(is_array($noopResult) && (int) ($noopResult['payment_records'] ?? -1) === 0 && (int) ($noopResult['payment_record_items'] ?? -1) === 0, 'payment_ledger cleanup must return zero counts for non-anonymize events.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT account_id FROM sr_payment_records WHERE id = 1') === 7, 'payment_ledger cleanup must not alter records on non-anonymize events.');

    $result = $cleanup($pdo, 7, ['event_type' => 'member.anonymized']);
    sr_privacy_cleanup_runtime_assert(is_array($result), 'payment_ledger cleanup must return an array result.');
    sr_privacy_cleanup_runtime_assert((int) ($result['payment_records'] ?? -1) === 1, 'payment_ledger cleanup must report payment record anonymization count.');
    sr_privacy_cleanup_runtime_assert((int) ($result['payment_record_items'] ?? -1) === 1, 'payment_ledger cleanup must report payment item reference anonymization count.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT account_id FROM sr_payment_records WHERE id = 1') === 0, 'payment_ledger cleanup must clear target payment record account id.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT reference_id FROM sr_payment_record_items WHERE id = 1') === 'content.view:7801:account:anonymous', 'payment_ledger cleanup must redact target account item references.');
    $snapshot = json_decode((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT snapshot_json FROM sr_payment_record_items WHERE id = 1'), true);
    sr_privacy_cleanup_runtime_assert(is_array($snapshot) && ($snapshot['source_reference'] ?? '') === 'content:view:7801:account:anonymous:intent:abc', 'payment_ledger cleanup must redact target account references in item snapshots.');
    sr_privacy_cleanup_runtime_assert(is_array($snapshot) && ($snapshot['unrelated_reference'] ?? '') === 'content:view:7801:account:77:intent:abc', 'payment_ledger cleanup must not redact similar account id prefixes in snapshots.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT account_id FROM sr_payment_records WHERE id = 2') === 77, 'payment_ledger cleanup must not alter other account records.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT reference_id FROM sr_payment_record_items WHERE id = 2') === 'content.view:7802:account:77', 'payment_ledger cleanup must not alter other account item references.');
}

function sr_privacy_cleanup_runtime_check_reward(): void
{
    $cleanup = include 'modules/reward/privacy-cleanup.php';
    if (!is_callable($cleanup)) {
        sr_privacy_cleanup_runtime_error('reward privacy cleanup contract is not callable.');
        return;
    }

    $pdo = sr_privacy_cleanup_runtime_pdo();
    $pdo->exec(
        'CREATE TABLE sr_reward_withdrawal_requests (
            id INTEGER PRIMARY KEY,
            account_id INTEGER NOT NULL,
            amount INTEGER NOT NULL,
            bank_name TEXT NOT NULL,
            bank_account_number TEXT NOT NULL,
            bank_account_holder TEXT NOT NULL,
            requester_note TEXT NOT NULL,
            status TEXT NOT NULL,
            admin_note TEXT NOT NULL,
            transaction_id INTEGER NULL,
            processed_by_account_id INTEGER NULL,
            requested_at TEXT NOT NULL,
            processed_at TEXT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        "INSERT INTO sr_reward_withdrawal_requests
            (id, account_id, amount, bank_name, bank_account_number, bank_account_holder, requester_note, status, admin_note, transaction_id, processed_by_account_id, requested_at, processed_at, updated_at)
         VALUES
            (1, 7, 600, 'RewardBank7', '222-7', 'RewardHolder7', 'note7', 'completed', 'admin7', 1, 99, '', '', ''),
            (2, 8, 500, 'RewardBank8', '222-8', 'RewardHolder8', 'note8', 'pending', 'admin8', NULL, NULL, '', NULL, '')"
    );

    $invalidResult = $cleanup($pdo, 0, ['event_type' => 'member.anonymized']);
    sr_privacy_cleanup_runtime_assert(is_array($invalidResult) && ($invalidResult['cleaned'] ?? null) === false, 'reward cleanup must return cleaned=false for invalid account id.');

    $noopResult = $cleanup($pdo, 7, ['event_type' => 'member.profile_updated']);
    sr_privacy_cleanup_runtime_assert(is_array($noopResult) && ($noopResult['cleaned'] ?? null) === false, 'reward cleanup must return cleaned=false for non-anonymize events.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT bank_account_number FROM sr_reward_withdrawal_requests WHERE id = 1') === '222-7', 'reward cleanup must not alter withdrawal PII on non-anonymize events.');

    $result = $cleanup($pdo, 7, ['event_type' => 'member.anonymized']);
    sr_privacy_cleanup_runtime_assert(is_array($result), 'reward cleanup must return an array result.');
    sr_privacy_cleanup_runtime_assert(($result['cleaned'] ?? null) === true, 'reward cleanup result must include cleaned=true.');
    sr_privacy_cleanup_runtime_assert(($result['event_type'] ?? '') === 'member.anonymized', 'reward cleanup result must preserve event_type.');
    sr_privacy_cleanup_runtime_assert((int) ($result['reward_withdrawal_request_pii_cleared_count'] ?? -1) === 1, 'reward cleanup must report withdrawal PII cleanup count.');

    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT account_id FROM sr_reward_withdrawal_requests WHERE id = 1') === 7, 'reward cleanup must retain target withdrawal account id for financial evidence.');
    sr_privacy_cleanup_runtime_assert((int) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT amount FROM sr_reward_withdrawal_requests WHERE id = 1') === 600, 'reward cleanup must retain target withdrawal amount.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT bank_name FROM sr_reward_withdrawal_requests WHERE id = 1') === '', 'reward cleanup must clear target withdrawal bank name.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT bank_account_number FROM sr_reward_withdrawal_requests WHERE id = 1') === '', 'reward cleanup must clear target withdrawal bank account number.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT bank_account_holder FROM sr_reward_withdrawal_requests WHERE id = 1') === '', 'reward cleanup must clear target withdrawal bank account holder.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT requester_note FROM sr_reward_withdrawal_requests WHERE id = 1') === '', 'reward cleanup must clear target withdrawal requester note.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT admin_note FROM sr_reward_withdrawal_requests WHERE id = 1') === '', 'reward cleanup must clear target withdrawal admin note.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT updated_at FROM sr_reward_withdrawal_requests WHERE id = 1') === sr_now(), 'reward cleanup must timestamp target withdrawal PII cleanup.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT bank_account_number FROM sr_reward_withdrawal_requests WHERE id = 2') === '222-8', 'reward cleanup must not alter other account withdrawal PII.');
    sr_privacy_cleanup_runtime_assert((string) sr_privacy_cleanup_runtime_scalar($pdo, 'SELECT admin_note FROM sr_reward_withdrawal_requests WHERE id = 2') === 'admin8', 'reward cleanup must not alter other account withdrawal notes.');
}

sr_privacy_cleanup_runtime_check_asset_ledger();
sr_privacy_cleanup_runtime_check_quiz();
sr_privacy_cleanup_runtime_check_survey();
sr_privacy_cleanup_runtime_check_content();
sr_privacy_cleanup_runtime_check_community();
sr_privacy_cleanup_runtime_check_notification();
sr_privacy_cleanup_runtime_check_policy_documents();
sr_privacy_cleanup_runtime_check_payment_ledger();
sr_privacy_cleanup_runtime_check_reward();

if ($errors !== []) {
    fwrite(STDERR, "privacy cleanup runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "privacy cleanup runtime checks completed.\n";
