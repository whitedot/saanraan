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
    "'version' => '2026.06.047'",
    "'account_guard_publication_hold_enabled' => false",
    "'account_guard_confirmed_hold_enabled' => false",
]);

sr_community_account_guard_check_contains('core/actions/install.php', [
    "'community' => [\n        'name' => '커뮤니티',\n        'version' => '2026.06.047'",
]);

sr_community_account_guard_check_contains('modules/community/helpers.php', [
    "helpers/account-guards.php",
]);

sr_community_account_guard_check_contains('modules/community/helpers/levels.php', [
    "'account_guard_publication_hold_enabled' => (bool)",
    '$settings[\'account_guard_publication_hold_threshold\'] = min(20, max(2,',
    "'account_guard_confirmed_hold_enabled' => (bool)",
    '$settings[\'account_guard_confirmed_hold_window_days\'] = min(365, max(1,',
]);

sr_community_account_guard_check_contains('modules/community/helpers/account-guards.php', [
    'function sr_community_account_guard_active_uid',
    'function sr_community_account_guard_transition',
    'function sr_community_account_active_guards',
    'active_guard_uid = NULL',
    "'publication_hold', 'confirmed_hold', 'write_cooldown', 'needs_review'",
]);

sr_community_account_guard_check_contains('modules/community/actions/admin-settings.php', [
    "sr_admin_post_int_in_range('account_guard_publication_hold_threshold', 2, 20)",
    "sr_admin_post_int_in_range('account_guard_confirmed_hold_window_days', 1, 365)",
    "['account_guard_publication_hold_enabled', \$accountGuardPublicationHoldEnabled ? '1' : '0', 'bool']",
    "['account_guard_confirmed_hold_duration_minutes', (string) \$accountGuardConfirmedHoldDurationMinutes, 'int']",
]);

sr_community_account_guard_check_contains('modules/community/views/admin-settings.php', [
    "'account_guard_publication_hold_enabled'",
    'name="account_guard_publication_hold_threshold"',
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

    $activeGuards = sr_community_account_active_guards($pdo, 7, '2026-07-01 12:00:00');
    if (count($activeGuards) !== 1 || (string) ($activeGuards[0]['guard_type'] ?? '') !== 'publication_hold') {
        sr_community_account_guard_check_error('active guard query must exclude expired guard rows.');
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
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

fwrite(STDOUT, 'Community account guard contract OK' . PHP_EOL);
