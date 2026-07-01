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
    'function sr_community_report_active_auto_action',
    'function sr_community_report_auto_actions_by_targets',
    'function sr_community_report_auto_action_transition',
    'function sr_community_release_report_auto_action_target',
    'function sr_community_maybe_apply_report_auto_action',
    'active_target_uid = NULL',
    "'hidden_reason' => 'report_threshold'",
    "'hidden_by_account_id' => null",
    'sr_community_update_post_attachments_status($pdo, $targetId, \'active\')',
    '\'hidden_by_account_id\' => $adminAccountId',
    "'active', 'confirmed', 'released', 'skipped', 'failed'",
]);

sr_community_report_auto_action_check_contains('modules/community/actions/report.php', [
    'sr_community_maybe_apply_report_auto_action($pdo, $reportId, $settings)',
    "'event_type' => 'community.report.auto_action_evaluated'",
    "'event_type' => 'community.report.auto_action_failed'",
]);

sr_community_report_auto_action_check_contains('modules/community/actions/admin-settings.php', [
    "sr_admin_post_int_in_range('report_auto_action_threshold', 2, 100)",
    "sr_admin_post_int_in_range('report_auto_action_window_days', 0, 365)",
    '[\'report_auto_action_enabled\', $reportAutoActionEnabled ? \'1\' : \'0\', \'bool\']',
    '[\'report_auto_action_public_mode\', $reportAutoActionPublicMode, \'string\']',
]);

sr_community_report_auto_action_check_contains('modules/community/views/admin-settings.php', [
    'community-settings-section-report-auto-action',
    "'report_auto_action_enabled'",
    'name="report_auto_action_threshold"',
    'name="report_auto_action_window_days"',
    "'report_auto_action_public_mode'",
]);

sr_community_report_auto_action_check_contains('modules/community/actions/admin-reports.php', [
    "sr_post_string('auto_action_status', 30)",
    'sr_community_report_active_auto_action($pdo, (string) $report[\'target_type\'], (int) $report[\'target_id\'], true)',
    'sr_community_release_report_auto_action_target($pdo, $activeAutoAction)',
    "'event_type' => 'community.report.auto_action_reviewed'",
    'sr_community_report_auto_actions_by_targets($pdo, $reports)',
]);

sr_community_report_auto_action_check_contains('modules/community/views/admin-reports.php', [
    '자동조치',
    'sr_community_report_auto_action_status_label',
    'name="auto_action_status"',
    'sr_community_report_auto_action_review_options',
]);

sr_community_report_auto_action_check_contains('docs/implementation-snapshot.md', [
    'sr_community_report_auto_actions',
]);

sr_community_report_auto_action_check_contains('docs/privacy-processing-records.md', [
    '신고 자동 임시 조치',
]);

if ($errors === []) {
    require_once SR_ROOT . '/modules/community/helpers/reports.php';
    require_once SR_ROOT . '/modules/community/helpers.php';

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

    $pdo->exec('CREATE TABLE sr_community_boards (id INTEGER PRIMARY KEY, status TEXT NOT NULL)');
    $pdo->exec(
        'CREATE TABLE sr_community_posts (
            id INTEGER PRIMARY KEY,
            board_id INTEGER NOT NULL,
            status TEXT NOT NULL,
            hidden_at TEXT NULL,
            hidden_until TEXT NULL,
            hidden_reason TEXT NOT NULL DEFAULT "",
            hidden_note TEXT NULL,
            hidden_by_account_id INTEGER NULL,
            hidden_before_status TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec('CREATE TABLE sr_community_attachments (id INTEGER PRIMARY KEY, post_id INTEGER NOT NULL, status TEXT NOT NULL)');
    $pdo->exec(
        'CREATE TABLE sr_community_reports (
            id INTEGER PRIMARY KEY,
            target_type TEXT NOT NULL,
            target_id INTEGER NOT NULL,
            reporter_account_id INTEGER NOT NULL,
            reported_account_id INTEGER NULL,
            reason_key TEXT NOT NULL,
            memo_text TEXT NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec("INSERT INTO sr_community_boards (id, status) VALUES (1, 'enabled')");
    $pdo->exec("INSERT INTO sr_community_posts (id, board_id, status, updated_at) VALUES (11, 1, 'published', '2026-07-01 11:00:00')");
    $pdo->exec("INSERT INTO sr_community_attachments (id, post_id, status) VALUES (1, 11, 'active')");
    $pdo->exec(
        "INSERT INTO sr_community_reports
            (id, target_type, target_id, reporter_account_id, reported_account_id, reason_key, memo_text, status, created_at, updated_at)
         VALUES
            (101, 'post', 11, 51, 71, 'spam', '', 'open', '2026-07-01 11:00:00', '2026-07-01 11:00:00'),
            (102, 'post', 11, 52, 71, 'spam', '', 'open', '2026-07-01 11:01:00', '2026-07-01 11:01:00')"
    );

    $autoResult = sr_community_maybe_apply_report_auto_action($pdo, 102, [
        'report_auto_action_enabled' => true,
        'report_auto_action_threshold' => 2,
        'report_auto_action_window_days' => 0,
        'report_auto_action_public_mode' => 'exclude',
    ]);
    $postRow = $pdo->query('SELECT status, hidden_reason, hidden_by_account_id FROM sr_community_posts WHERE id = 11')->fetch(PDO::FETCH_ASSOC);
    $attachmentStatus = (string) $pdo->query('SELECT status FROM sr_community_attachments WHERE id = 1')->fetchColumn();
    $autoRow = $pdo->query("SELECT * FROM sr_community_report_auto_actions WHERE target_type = 'post' AND target_id = 11")->fetch(PDO::FETCH_ASSOC);
    if (
        (string) ($autoResult['status'] ?? '') !== 'applied'
        || !is_array($postRow)
        || (string) ($postRow['status'] ?? '') !== 'hidden'
        || (string) ($postRow['hidden_reason'] ?? '') !== 'report_threshold'
        || $postRow['hidden_by_account_id'] !== null
        || $attachmentStatus !== 'hidden'
        || !is_array($autoRow)
        || (string) ($autoRow['status'] ?? '') !== 'active'
        || (string) ($autoRow['active_target_uid'] ?? '') !== 'post:11'
        || (int) ($autoRow['threshold_value'] ?? 0) !== 2
        || (int) ($autoRow['eligible_reporter_count'] ?? 0) !== 2
    ) {
        sr_community_report_auto_action_check_error('threshold auto action must hide the post, sync attachments, and keep an active target uid.');
    }

    $duplicateResult = sr_community_maybe_apply_report_auto_action($pdo, 101, [
        'report_auto_action_enabled' => true,
        'report_auto_action_threshold' => 2,
        'report_auto_action_window_days' => 0,
        'report_auto_action_public_mode' => 'exclude',
    ]);
    if ((string) ($duplicateResult['status'] ?? '') !== 'active_exists') {
        sr_community_report_auto_action_check_error('auto action helper must not create a duplicate active action for the same target.');
    }

    $releaseResult = sr_community_release_report_auto_action_target($pdo, $autoRow);
    $releasedAutoAction = sr_community_report_auto_action_transition($pdo, (int) ($autoRow['id'] ?? 0), 'released', [
        'reviewer_account_id' => 77,
        'metadata' => ['source' => 'admin_report_review', 'release_result' => $releaseResult],
    ]);
    $releasedPostRow = $pdo->query('SELECT status, hidden_reason, hidden_before_status FROM sr_community_posts WHERE id = 11')->fetch(PDO::FETCH_ASSOC);
    $releasedAttachmentStatus = (string) $pdo->query('SELECT status FROM sr_community_attachments WHERE id = 1')->fetchColumn();
    $releasedAutoRow = $pdo->query("SELECT status, active_target_uid, reviewer_account_id, released_at FROM sr_community_report_auto_actions WHERE target_type = 'post' AND target_id = 11")->fetch(PDO::FETCH_ASSOC);
    if (
        empty($releaseResult['restored'])
        || !$releasedAutoAction
        || !is_array($releasedPostRow)
        || (string) ($releasedPostRow['status'] ?? '') !== 'published'
        || (string) ($releasedPostRow['hidden_reason'] ?? '') !== ''
        || (string) ($releasedPostRow['hidden_before_status'] ?? '') !== ''
        || $releasedAttachmentStatus !== 'active'
        || !is_array($releasedAutoRow)
        || (string) ($releasedAutoRow['status'] ?? '') !== 'released'
        || $releasedAutoRow['active_target_uid'] !== null
        || (int) ($releasedAutoRow['reviewer_account_id'] ?? 0) !== 77
        || (string) ($releasedAutoRow['released_at'] ?? '') === ''
    ) {
        sr_community_report_auto_action_check_error('released auto action must restore auto-hidden target and clear active uid.');
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo 'Community report auto action contract OK' . PHP_EOL;
