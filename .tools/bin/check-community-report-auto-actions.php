#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

$errors = [];

function sr_community_report_auto_action_check_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_community_report_auto_action_check_contains(string $file, array $needles): void
{
    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        sr_community_report_auto_action_check_error('cannot read file: ' . $file);
        return;
    }

    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            sr_community_report_auto_action_check_error($file . ' is missing marker: ' . $needle);
        }
    }
}

function sr_now(): string
{
    return '2026-07-01 12:00:00';
}

sr_community_report_auto_action_check_contains('modules/community/install.sql', [
    'CREATE TABLE IF NOT EXISTS sr_community_report_auto_actions',
    'active_target_uid VARCHAR(80) NULL',
    'target_hidden_by_account_id BIGINT UNSIGNED NULL',
    'threshold_value INT UNSIGNED NOT NULL DEFAULT 0',
    'UNIQUE KEY uq_sr_community_report_auto_actions_active_target (active_target_uid)',
    'KEY idx_sr_community_report_auto_actions_target_status (target_type, target_id, status)',
]);

sr_community_report_auto_action_check_contains('modules/community/updates/2026.06.046.sql', [
    'CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}community_report_auto_actions',
    "SET version = '2026.06.046'",
]);

sr_community_report_auto_action_check_contains('modules/community/module.php', [
    "'version' => '2026.06.046'",
    "'report_auto_action_enabled' => false",
    "'report_auto_action_threshold' => 5",
    "'report_auto_action_public_mode' => 'exclude'",
]);

sr_community_report_auto_action_check_contains('core/actions/install.php', [
    "'community' => [\n        'name' => '커뮤니티',\n        'version' => '2026.06.046'",
]);

sr_community_report_auto_action_check_contains('modules/community/helpers/levels.php', [
    "'report_auto_action_enabled' => (bool)",
    '$settings[\'report_auto_action_threshold\'] = min(100, max(2,',
    "['exclude', 'placeholder']",
]);

sr_community_report_auto_action_check_contains('modules/community/helpers/reports.php', [
    'function sr_community_report_auto_action_active_target_uid',
    'function sr_community_report_auto_action_transition',
    'active_target_uid = NULL',
    "'active', 'confirmed', 'released', 'skipped', 'failed'",
]);

sr_community_report_auto_action_check_contains('docs/implementation-snapshot.md', [
    'sr_community_report_auto_actions',
]);

sr_community_report_auto_action_check_contains('docs/privacy-processing-records.md', [
    '신고 자동 임시 조치',
]);

if ($errors === []) {
    require_once SR_ROOT . '/modules/community/helpers/reports.php';

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        'CREATE TABLE sr_community_report_auto_actions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            target_type TEXT NOT NULL,
            target_id INTEGER NOT NULL,
            active_target_uid TEXT NULL UNIQUE,
            source_report_id INTEGER NULL,
            action_key TEXT NOT NULL DEFAULT "",
            status TEXT NOT NULL DEFAULT "active",
            target_before_status TEXT NOT NULL DEFAULT "",
            target_hidden_at_snapshot TEXT NULL,
            target_hidden_reason TEXT NOT NULL DEFAULT "",
            target_hidden_by_account_id INTEGER NULL,
            threshold_value INTEGER NOT NULL DEFAULT 0,
            total_reporter_count INTEGER NOT NULL DEFAULT 0,
            eligible_reporter_count INTEGER NOT NULL DEFAULT 0,
            excluded_reporter_count INTEGER NOT NULL DEFAULT 0,
            excluded_report_count INTEGER NOT NULL DEFAULT 0,
            abuse_guard_summary_json TEXT NULL,
            settings_snapshot_json TEXT NULL,
            failure_reason TEXT NOT NULL DEFAULT "",
            metadata_json TEXT NULL,
            applied_at TEXT NULL,
            released_at TEXT NULL,
            reviewed_at TEXT NULL,
            reviewer_account_id INTEGER NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        "INSERT INTO sr_community_report_auto_actions
            (target_type, target_id, active_target_uid, status, created_at, updated_at)
         VALUES
            ('post', 7, 'post:7', 'active', '2026-07-01 11:00:00', '2026-07-01 11:00:00'),
            ('comment', 9, 'comment:9', 'active', '2026-07-01 11:00:00', '2026-07-01 11:00:00')"
    );

    if (sr_community_report_auto_action_active_target_uid('post', 7) !== 'post:7') {
        sr_community_report_auto_action_check_error('post target uid helper returned an unexpected value.');
    }
    if (sr_community_report_auto_action_active_target_uid('message', 7) !== '') {
        sr_community_report_auto_action_check_error('message target must be excluded from auto action target uid helper.');
    }

    $kept = sr_community_report_auto_action_transition($pdo, 1, 'active');
    $row = $pdo->query('SELECT status, active_target_uid FROM sr_community_report_auto_actions WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    if (!$kept || !is_array($row) || (string) $row['status'] !== 'active' || (string) $row['active_target_uid'] !== 'post:7') {
        sr_community_report_auto_action_check_error('non-terminal transition must keep active_target_uid.');
    }

    $released = sr_community_report_auto_action_transition($pdo, 2, 'released', [
        'reviewer_account_id' => 42,
        'metadata' => ['reason' => 'manual_restore'],
    ]);
    $row = $pdo->query('SELECT status, active_target_uid, reviewer_account_id, released_at, reviewed_at, metadata_json FROM sr_community_report_auto_actions WHERE id = 2')->fetch(PDO::FETCH_ASSOC);
    if (!$released || !is_array($row)) {
        sr_community_report_auto_action_check_error('terminal transition did not update fixture row.');
    } elseif (
        (string) $row['status'] !== 'released'
        || $row['active_target_uid'] !== null
        || (int) $row['reviewer_account_id'] !== 42
        || (string) $row['released_at'] === ''
        || (string) $row['reviewed_at'] === ''
        || !str_contains((string) $row['metadata_json'], 'manual_restore')
    ) {
        sr_community_report_auto_action_check_error('terminal transition must clear active_target_uid and retain review metadata.');
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo 'Community report auto action contract OK' . PHP_EOL;
