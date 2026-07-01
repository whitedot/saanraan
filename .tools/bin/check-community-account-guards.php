#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

$errors = [];

function sr_community_account_guard_check_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_community_account_guard_check_contains(string $file, array $needles): void
{
    $content = is_file($file) ? file_get_contents($file) : false;
    if (!is_string($content)) {
        sr_community_account_guard_check_error('cannot read file: ' . $file);
        return;
    }

    foreach ($needles as $needle) {
        if (!str_contains($content, (string) $needle)) {
            sr_community_account_guard_check_error($file . ' is missing marker: ' . $needle);
        }
    }
}

function sr_now(): string
{
    return '2026-07-01 12:00:00';
}

sr_community_account_guard_check_contains('modules/community/install.sql', [
    'CREATE TABLE IF NOT EXISTS sr_community_account_guard_events',
    'CREATE TABLE IF NOT EXISTS sr_community_account_guards',
    'CREATE TABLE IF NOT EXISTS sr_community_account_guard_locks',
    'UNIQUE KEY uq_sr_community_account_guards_active_uid (active_guard_uid)',
    'PRIMARY KEY (account_id)',
]);

sr_community_account_guard_check_contains('modules/community/updates/2026.06.047.sql', [
    'CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}community_account_guard_events',
    'CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}community_account_guards',
    'CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}community_account_guard_locks',
    "SET version = '2026.06.047'",
]);

sr_community_account_guard_check_contains('modules/community/module.php', [
    "'version' => '2026.06.048'",
    "'account_guard_publication_hold_enabled' => false",
    "'account_guard_publication_hold_overlap_review_percent' => 80",
    "'account_guard_confirmed_hold_enabled' => false",
]);

sr_community_account_guard_check_contains('core/actions/install.php', [
    "'community' => [\n        'name' => '커뮤니티',\n        'version' => '2026.06.048'",
]);

sr_community_account_guard_check_contains('modules/community/helpers.php', [
    "helpers/account-guards.php",
]);

sr_community_account_guard_check_contains('modules/community/helpers/levels.php', [
    "'account_guard_publication_hold_enabled' => (bool)",
    '$settings[\'account_guard_publication_hold_threshold\'] = min(20, max(2,',
    '$settings[\'account_guard_publication_hold_overlap_review_percent\'] = min(100, max(0,',
    "'account_guard_confirmed_hold_enabled' => (bool)",
    '$settings[\'account_guard_confirmed_hold_window_days\'] = min(365, max(1,',
]);

sr_community_account_guard_check_contains('modules/community/helpers/account-guards.php', [
    'function sr_community_account_guard_active_uid',
    'function sr_community_account_guard_by_id',
    'function sr_community_admin_account_guard_rows',
    'function sr_community_account_guard_type_label',
    'function sr_community_account_guard_status_label',
    'function sr_community_account_guard_transition',
    'function sr_community_account_active_guards',
    'function sr_community_account_guard_write_decision',
    'function sr_community_evaluate_account_guard_after_auto_action',
    'function sr_community_evaluate_account_publication_hold',
    'function sr_community_evaluate_account_confirmed_hold',
    'function sr_community_account_guard_trigger_fingerprint',
    'active_guard_uid = NULL',
    "'publication_hold', 'confirmed_hold', 'write_cooldown', 'needs_review'",
]);

sr_community_account_guard_check_contains('modules/community/actions/report.php', [
    'sr_community_evaluate_account_guard_after_auto_action($pdo, (int) $autoActionResult[\'auto_action_id\'], $settings)',
    "'event_type' => 'community.account_guard.evaluated'",
    "'event_type' => 'community.account_guard.evaluation_failed'",
]);

sr_community_account_guard_check_contains('modules/community/actions/write.php', [
    'sr_community_account_guard_write_decision($pdo, (int) $account[\'id\'], \'post\')',
    '$values[\'initial_status\'] = \'pending\'',
    "sr_redirect('/community/my?type=posts')",
    "'account_guard_write_decision' => \$accountGuardWriteDecision",
]);

sr_community_account_guard_check_contains('modules/community/helpers/posts-writing.php', [
    "\$initialStatusInput = (string) (\$values['initial_status'] ?? 'published')",
    "'status' => \$initialStatus",
]);

sr_community_account_guard_check_contains('modules/community/actions/my.php', [
    "AND p.status IN (\\'published\\', \\'pending\\')",
    "unset(\$_SESSION['sr_community_post_notice'])",
]);

sr_community_account_guard_check_contains('modules/community/views/my.php', [
    '검토 대기 중',
    "sr_public_feedback_toasts('community', (string) (\$myNotice ?? ''), [])",
]);

sr_community_account_guard_check_contains('modules/community/actions/admin-reports.php', [
    "\$intent === 'release_account_guard'",
    "sr_community_account_guard_transition(\$pdo, \$guardId, 'released'",
    "'event_type' => 'community.account_guard.released'",
    'sr_community_admin_account_guard_rows($pdo, 20)',
    'sr_community_evaluate_account_guard_after_auto_action($pdo, (int) $autoActionReviewResult[\'auto_action_id\'], $settings)',
    "'event_type' => 'community.account_guard.evaluated'",
    "'event_type' => 'community.account_guard.evaluation_failed'",
]);

sr_community_account_guard_check_contains('modules/community/views/admin-reports.php', [
    '계정 guard',
    'name="intent" value="release_account_guard"',
    'sr_community_account_guard_type_label',
    'sr_community_account_guard_status_label',
]);

sr_community_account_guard_check_contains('modules/community/actions/admin-settings.php', [
    "sr_admin_post_int_in_range('account_guard_publication_hold_threshold', 2, 20)",
    "sr_admin_post_int_in_range('account_guard_publication_hold_overlap_review_percent', 0, 100)",
    "sr_admin_post_int_in_range('account_guard_confirmed_hold_window_days', 1, 365)",
    "['account_guard_publication_hold_enabled', \$accountGuardPublicationHoldEnabled ? '1' : '0', 'bool']",
    "['account_guard_confirmed_hold_duration_minutes', (string) \$accountGuardConfirmedHoldDurationMinutes, 'int']",
]);

sr_community_account_guard_check_contains('modules/community/views/admin-settings.php', [
    "'account_guard_publication_hold_enabled'",
    'name="account_guard_publication_hold_threshold"',
    'name="account_guard_publication_hold_overlap_review_percent"',
    "'account_guard_confirmed_hold_enabled'",
    'name="account_guard_confirmed_hold_window_days"',
]);

sr_community_account_guard_check_contains('modules/community/privacy-export.php', [
    "'account_guard_events' => []",
    "'account_guards' => []",
    'sr_community_account_guard_events',
    'sr_community_account_guards',
]);

sr_community_account_guard_check_contains('modules/community/privacy-cleanup.php', [
    'sr_community_account_guard_events',
    'sr_community_account_guards',
    'active_guard_uid = CASE WHEN account_id = :guard_active_account_id THEN NULL ELSE active_guard_uid END',
]);

if ($errors === []) {
    require_once SR_ROOT . '/modules/community/helpers/account-guards.php';

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        'CREATE TABLE sr_community_account_guards (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            guard_type TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "active",
            active_guard_uid TEXT NULL UNIQUE,
            source_event_id INTEGER NULL,
            starts_at TEXT NULL,
            expires_at TEXT NULL,
            released_at TEXT NULL,
            reviewer_account_id INTEGER NULL,
            snapshot_json TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_community_account_guard_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            source_type TEXT NOT NULL DEFAULT "",
            source_id INTEGER NULL,
            guard_type TEXT NOT NULL,
            trigger_reason TEXT NOT NULL DEFAULT "",
            status TEXT NOT NULL DEFAULT "active",
            starts_at TEXT NULL,
            expires_at TEXT NULL,
            released_at TEXT NULL,
            reviewer_account_id INTEGER NULL,
            trigger_fingerprint TEXT NOT NULL DEFAULT "",
            snapshot_json TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_community_account_guard_locks (
            account_id INTEGER PRIMARY KEY,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_community_report_auto_actions (
            id INTEGER PRIMARY KEY,
            target_type TEXT NOT NULL,
            target_id INTEGER NOT NULL,
            active_target_uid TEXT NULL,
            status TEXT NOT NULL,
            reviewed_at TEXT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_community_posts (
            id INTEGER PRIMARY KEY,
            author_account_id INTEGER NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_community_comments (
            id INTEGER PRIMARY KEY,
            author_account_id INTEGER NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_community_reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            target_type TEXT NOT NULL,
            target_id INTEGER NOT NULL,
            reporter_account_id INTEGER NOT NULL,
            status TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_member_accounts (
            id INTEGER PRIMARY KEY,
            display_name TEXT NOT NULL,
            status TEXT NOT NULL
        )'
    );
    $pdo->exec("INSERT INTO sr_member_accounts (id, display_name, status) VALUES (7, 'Guard User', 'active')");
    $pdo->exec(
        "INSERT INTO sr_community_account_guards
            (account_id, guard_type, status, active_guard_uid, expires_at, created_at, updated_at)
         VALUES
            (7, 'publication_hold', 'active', '7:publication_hold', '2026-07-01 13:00:00', '2026-07-01 11:00:00', '2026-07-01 11:00:00'),
            (7, 'write_cooldown', 'active', '7:write_cooldown', '2026-07-01 11:59:00', '2026-07-01 11:00:00', '2026-07-01 11:00:00')"
    );

    if (sr_community_account_guard_active_uid(7, 'publication_hold') !== '7:publication_hold') {
        sr_community_account_guard_check_error('active guard uid helper returned an unexpected value.');
    }
    if (sr_community_account_guard_active_uid(7, 'unknown') !== '') {
        sr_community_account_guard_check_error('unknown guard type must not produce an active uid.');
    }
    if (sr_community_account_guard_type_label('publication_hold') === 'publication_hold') {
        sr_community_account_guard_check_error('guard type label helper must return an operator-facing label.');
    }

    $activeGuards = sr_community_account_active_guards($pdo, 7, '2026-07-01 12:00:00');
    if (count($activeGuards) !== 1 || (string) ($activeGuards[0]['guard_type'] ?? '') !== 'publication_hold') {
        sr_community_account_guard_check_error('active guard query must exclude expired guard rows.');
    }
    $adminGuardRows = sr_community_admin_account_guard_rows($pdo, 10, '2026-07-01 12:00:00');
    if (count($adminGuardRows) !== 1 || (string) ($adminGuardRows[0]['account_display_name'] ?? '') !== 'Guard User') {
        sr_community_account_guard_check_error('admin account guard rows must list active current guards with member display metadata.');
    }
    $guardById = sr_community_account_guard_by_id($pdo, 1);
    if (!is_array($guardById) || (int) ($guardById['account_id'] ?? 0) !== 7) {
        sr_community_account_guard_check_error('guard id lookup helper must return the requested current guard.');
    }

    $released = sr_community_account_guard_transition($pdo, 1, 'released', [
        'reviewer_account_id' => 42,
        'snapshot' => ['reason' => 'manual_release'],
    ]);
    $row = $pdo->query('SELECT status, active_guard_uid, reviewer_account_id, released_at, snapshot_json FROM sr_community_account_guards WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    if (!$released || !is_array($row)) {
        sr_community_account_guard_check_error('guard transition did not update fixture row.');
    } elseif (
        (string) $row['status'] !== 'released'
        || $row['active_guard_uid'] !== null
        || (int) $row['reviewer_account_id'] !== 42
        || (string) $row['released_at'] === ''
        || !str_contains((string) $row['snapshot_json'], 'manual_release')
    ) {
        sr_community_account_guard_check_error('terminal guard transition must clear active_guard_uid and retain review metadata.');
    }

    $pdo->exec("INSERT INTO sr_community_posts (id, author_account_id) VALUES (21, 9), (22, 9), (31, 10), (32, 10), (41, 11), (42, 11)");
    $pdo->exec("INSERT INTO sr_community_report_auto_actions (id, target_type, target_id, active_target_uid, status, reviewed_at, updated_at) VALUES
        (201, 'post', 21, 'post:21', 'active', NULL, '2026-07-01 11:00:00'),
        (202, 'post', 22, 'post:22', 'active', NULL, '2026-07-01 11:00:00'),
        (301, 'post', 31, 'post:31', 'active', NULL, '2026-07-01 11:00:00'),
        (302, 'post', 32, 'post:32', 'active', NULL, '2026-07-01 11:00:00'),
        (401, 'post', 41, NULL, 'confirmed', '2026-07-01 11:00:00', '2026-07-01 11:00:00'),
        (402, 'post', 42, NULL, 'confirmed', '2026-07-01 11:10:00', '2026-07-01 11:10:00')");
    $pdo->exec("INSERT INTO sr_community_reports (target_type, target_id, reporter_account_id, status) VALUES
        ('post', 21, 1, 'open'), ('post', 21, 2, 'reviewing'),
        ('post', 22, 3, 'open'), ('post', 22, 4, 'reviewing'),
        ('post', 31, 5, 'open'), ('post', 31, 6, 'reviewing'),
        ('post', 32, 5, 'open'), ('post', 32, 6, 'reviewing')");

    $publicationResult = sr_community_evaluate_account_guard_after_auto_action($pdo, 202, [
        'account_guard_publication_hold_enabled' => true,
        'account_guard_publication_hold_threshold' => 2,
        'account_guard_publication_hold_overlap_review_percent' => 80,
        'account_guard_publication_hold_duration_minutes' => 60,
        'account_guard_confirmed_hold_enabled' => false,
    ]);
    if (
        (string) ($publicationResult['status'] ?? '') !== 'evaluated'
        || (string) ($publicationResult['results']['publication_hold']['status'] ?? '') !== 'created'
        || (int) $pdo->query("SELECT COUNT(*) FROM sr_community_account_guards WHERE account_id = 9 AND guard_type = 'publication_hold' AND status = 'active'")->fetchColumn() !== 1
    ) {
        sr_community_account_guard_check_error('multiple active post auto actions must create one publication hold when overlap is below review threshold.');
    }

    $overlapResult = sr_community_evaluate_account_guard_after_auto_action($pdo, 302, [
        'account_guard_publication_hold_enabled' => true,
        'account_guard_publication_hold_threshold' => 2,
        'account_guard_publication_hold_overlap_review_percent' => 80,
        'account_guard_publication_hold_duration_minutes' => 60,
        'account_guard_confirmed_hold_enabled' => false,
    ]);
    if (
        (string) ($overlapResult['results']['publication_hold']['status'] ?? '') !== 'needs_review'
        || (int) $pdo->query("SELECT COUNT(*) FROM sr_community_account_guards WHERE account_id = 10 AND guard_type = 'publication_hold'")->fetchColumn() !== 0
        || (int) $pdo->query("SELECT COUNT(*) FROM sr_community_account_guard_events WHERE account_id = 10 AND guard_type = 'needs_review' AND status = 'needs_review'")->fetchColumn() !== 1
    ) {
        sr_community_account_guard_check_error('high reporter overlap must create needs_review event without an active publication hold.');
    }

    $confirmedResult = sr_community_evaluate_account_guard_after_auto_action($pdo, 402, [
        'account_guard_publication_hold_enabled' => false,
        'account_guard_confirmed_hold_enabled' => true,
        'account_guard_confirmed_hold_threshold' => 2,
        'account_guard_confirmed_hold_window_days' => 30,
        'account_guard_confirmed_hold_duration_minutes' => 120,
    ]);
    if (
        (string) ($confirmedResult['results']['confirmed_hold']['status'] ?? '') !== 'created'
        || (int) $pdo->query("SELECT COUNT(*) FROM sr_community_account_guards WHERE account_id = 11 AND guard_type = 'confirmed_hold' AND status = 'active'")->fetchColumn() !== 1
    ) {
        sr_community_account_guard_check_error('repeated confirmed auto actions must create confirmed hold when setting is enabled.');
    }

    $postDecision = sr_community_account_guard_write_decision($pdo, 9, 'post');
    $commentDecision = sr_community_account_guard_write_decision($pdo, 9, 'comment');
    if (
        (string) ($postDecision['action'] ?? '') !== 'hold'
        || (string) ($postDecision['initial_status'] ?? '') !== 'pending'
        || (string) ($commentDecision['initial_status'] ?? '') !== 'published'
    ) {
        sr_community_account_guard_check_error('active publication hold must only change interactive post initial status to pending.');
    }

    $pdo->exec("INSERT INTO sr_community_account_guards (account_id, guard_type, status, active_guard_uid, created_at, updated_at) VALUES (12, 'write_cooldown', 'active', '12:write_cooldown', '2026-07-01 11:00:00', '2026-07-01 11:00:00')");
    $cooldownDecision = sr_community_account_guard_write_decision($pdo, 12, 'post');
    if ((string) ($cooldownDecision['action'] ?? '') !== 'block') {
        sr_community_account_guard_check_error('active write cooldown must block post writes.');
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

fwrite(STDOUT, 'Community account guard contract OK' . PHP_EOL);
