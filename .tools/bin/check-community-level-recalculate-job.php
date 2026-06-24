#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
define('SR_ROOT', $root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/community/helpers/levels.php';

$errors = [];

function sr_community_level_recalculate_job_check_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec(
        'CREATE TABLE sr_community_level_recalculate_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            requested_by INTEGER NULL,
            status TEXT NOT NULL DEFAULT "running",
            stage TEXT NOT NULL DEFAULT "accounts",
            cursor_value INTEGER NOT NULL DEFAULT 0,
            processed_total INTEGER NOT NULL DEFAULT 0,
            total_count INTEGER NOT NULL DEFAULT 0,
            batch_size INTEGER NOT NULL DEFAULT 50,
            lock_token TEXT NOT NULL DEFAULT "",
            failure_message TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            started_at TEXT NULL,
            completed_at TEXT NULL,
            failed_at TEXT NULL
        )'
    );

    $job = sr_community_level_recalculate_job_create($pdo, 7, 100, 25);
    $jobId = (int) ($job['id'] ?? 0);
    $lockToken = (string) ($job['lock_token'] ?? '');
    if ($jobId < 1 || $lockToken === '') {
        sr_community_level_recalculate_job_check_error('Level recalculate job create should return id and lock token.');
    }

    $loaded = sr_community_level_recalculate_job_by_id($pdo, $jobId);
    if (!is_array($loaded) || (string) ($loaded['status'] ?? '') !== 'running') {
        sr_community_level_recalculate_job_check_error('Created level recalculate job should load as running.');
    }
    try {
        sr_community_level_recalculate_job_require_running($loaded ?? [], 'stale-token');
        sr_community_level_recalculate_job_check_error('Level recalculate job should reject stale lock token.');
    } catch (RuntimeException $exception) {
        // Expected.
    }

    sr_community_level_recalculate_job_progress($pdo, $jobId, $lockToken, 10, 5, 100);
    $loaded = sr_community_level_recalculate_job_by_id($pdo, $jobId);
    if ((int) ($loaded['cursor_value'] ?? 0) !== 10 || (int) ($loaded['processed_total'] ?? 0) !== 5) {
        sr_community_level_recalculate_job_check_error('Level recalculate job progress should update cursor and processed total.');
    }

    sr_community_level_recalculate_job_complete($pdo, $jobId, $lockToken, 100, 100, 100);
    $loaded = sr_community_level_recalculate_job_by_id($pdo, $jobId);
    if ((string) ($loaded['status'] ?? '') !== 'completed' || (string) ($loaded['stage'] ?? '') !== 'complete') {
        sr_community_level_recalculate_job_check_error('Level recalculate job complete should close the job.');
    }

    try {
        sr_community_level_recalculate_job_progress($pdo, $jobId, $lockToken, 101, 101, 100);
        sr_community_level_recalculate_job_check_error('Completed level recalculate job should reject further progress.');
    } catch (RuntimeException $exception) {
        // Expected.
    }

    $failedJob = sr_community_level_recalculate_job_create($pdo, 8, 120, 50);
    sr_community_level_recalculate_job_fail($pdo, (int) $failedJob['id'], (string) $failedJob['lock_token'], new RuntimeException('fixture failure'));
    $loaded = sr_community_level_recalculate_job_by_id($pdo, (int) $failedJob['id']);
    if ((string) ($loaded['status'] ?? '') !== 'failed' || !str_contains((string) ($loaded['failure_message'] ?? ''), 'fixture failure')) {
        sr_community_level_recalculate_job_check_error('Level recalculate job fail should store failed status and message.');
    }
} catch (Throwable $exception) {
    sr_community_level_recalculate_job_check_error('Level recalculate job fixture failed: ' . $exception->getMessage());
}

if ($errors !== []) {
    fwrite(STDERR, "community level recalculate job checks failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "community level recalculate job checks completed.\n";
