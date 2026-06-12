#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once 'modules/community/helpers/board-copy-jobs.php';

$errors = [];

function sr_board_copy_job_lock_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_board_copy_job_lock_contains(string $contents, string $marker): void
{
    if (!str_contains($contents, $marker)) {
        sr_board_copy_job_lock_error('Board copy job lock marker missing: ' . $marker);
    }
}

function sr_board_copy_job_lock_expect_throw(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        return;
    }

    sr_board_copy_job_lock_error($message);
}

$helperFile = 'modules/community/helpers/board-copy-jobs.php';
$helper = is_file($helperFile) ? file_get_contents($helperFile) : false;
if (!is_string($helper)) {
    sr_board_copy_job_lock_error('Board copy jobs helper is missing or unreadable.');
} else {
    foreach ([
        'function sr_community_board_copy_job_assert_lock(PDO $pdo, int $jobId, string $lockToken): void',
        "WHERE id = :id AND status = 'running' AND lock_token = :lock_token",
        '$result = sr_community_board_copy_job_run_stage($pdo, $job, $accountId, $limits, $token);',
        'function sr_community_board_copy_job_run_stage(PDO $pdo, array $job, int $accountId, array $limits, string $lockToken): array',
        'sr_community_board_copy_job_assert_lock($pdo, (int) $job[\'id\'], $lockToken);',
        'function sr_community_board_copy_job_prepare(PDO $pdo, array $job, string $lockToken): void',
        'function sr_community_board_copy_job_create_board(PDO $pdo, array $job, string $lockToken): void',
        'function sr_community_board_copy_job_copy_posts(PDO $pdo, array $job, int $limit, string $lockToken): array',
        'function sr_community_board_copy_job_copy_comments(PDO $pdo, array $job, int $limit, string $lockToken): array',
        'function sr_community_board_copy_job_copy_attachments(PDO $pdo, array $job, int $limit, string $lockToken): array',
        'function sr_community_board_copy_job_copy_series(PDO $pdo, array $job, string $lockToken): array',
        'function sr_community_board_copy_job_cleanup(PDO $pdo, array $job, string $lockToken): int',
        'function sr_community_board_copy_job_mark_map(PDO $pdo, int $mapId, int $targetId, string $status, string $errorText = \'\', string $driver = \'\', string $key = \'\', int $jobId = 0, string $lockToken = \'\'): void',
        'sr_community_board_copy_job_assert_lock($pdo, $jobId, $lockToken);',
        'WHERE id = :id AND lock_token = :lock_token',
    ] as $marker) {
        sr_board_copy_job_lock_contains($helper, $marker);
    }
}

if (class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE sr_community_board_copy_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, status TEXT NOT NULL, lock_token TEXT NOT NULL)');
    $stmt = $pdo->prepare('INSERT INTO sr_community_board_copy_jobs (status, lock_token) VALUES (:status, :lock_token)');
    $stmt->execute(['status' => 'running', 'lock_token' => 'token-a']);
    $runningJobId = (int) $pdo->lastInsertId();
    $stmt->execute(['status' => 'completed', 'lock_token' => 'token-b']);
    $completedJobId = (int) $pdo->lastInsertId();

    sr_community_board_copy_job_assert_lock($pdo, $runningJobId, 'token-a');
    sr_board_copy_job_lock_expect_throw(
        static fn (): null => sr_community_board_copy_job_assert_lock($pdo, $runningJobId, 'stale-token'),
        'Stale board copy lock token should be rejected.'
    );
    sr_board_copy_job_lock_expect_throw(
        static fn (): null => sr_community_board_copy_job_assert_lock($pdo, $completedJobId, 'token-b'),
        'Completed board copy job should not pass running lock assertion.'
    );
    sr_board_copy_job_lock_expect_throw(
        static fn (): null => sr_community_board_copy_job_assert_lock($pdo, $runningJobId, ''),
        'Empty board copy lock token should be rejected.'
    );
} else {
    sr_board_copy_job_lock_error('PDO sqlite driver is required for board copy job lock fixture.');
}

if ($errors !== []) {
    fwrite(STDERR, "community board copy job lock checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "community board copy job lock checks completed.\n";
