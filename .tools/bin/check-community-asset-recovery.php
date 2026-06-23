#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

$read = static function (string $path) use (&$errors): string {
    $content = file_get_contents($path);
    if (!is_string($content)) {
        $errors[] = 'cannot read: ' . $path;
        return '';
    }

    return $content;
};

$assetEvents = $read('modules/community/helpers/asset-events.php');
$adminPosts = $read('modules/community/actions/admin-posts.php');
$postDelete = $read('modules/community/actions/delete.php');
$commentDelete = $read('modules/community/actions/comment-delete.php');
$paths = $read('modules/community/paths.php');
$installSql = $read('modules/community/install.sql');
$updateSql = $read('modules/community/updates/2026.06.033.sql');

foreach ([
    'sr_community_asset_grant_log_for_reversal',
    'sr_community_reverse_asset_grant_for_operation',
    'sr_community_asset_recovery_upsert',
    'sr_community_asset_recovery_failures_table_exists',
    'sr_community_asset_recovery_failure_by_id_for_update',
    'SAVEPOINT',
    'ROLLBACK TO SAVEPOINT',
    "['resolved', 'cancelled']",
] as $needle) {
    if (!str_contains($assetEvents, $needle)) {
        $errors[] = 'community asset recovery helper is missing contract: ' . $needle;
    }
}

if (!str_contains($assetEvents, "error_key' => 'asset_balance_low'") && !str_contains($assetEvents, '"error_key" => "asset_balance_low"')) {
    $errors[] = 'community operation recovery must preserve typed asset_balance_low classification.';
}

if (!str_contains($assetEvents, 'sr_community_asset_is_retryable_transaction_exception($exception)')) {
    $errors[] = 'community operation recovery must propagate retryable transaction exceptions.';
}

foreach ([$installSql, $updateSql] as $sql) {
    foreach ([
        'CREATE TABLE IF NOT EXISTS sr_community_asset_recovery_failures',
        'original_asset_log_id',
        'reversal_event_key',
        'UNIQUE KEY uq_sr_community_asset_recovery_original',
    ] as $needle) {
        if (!str_contains($sql, $needle)) {
            $errors[] = 'community recovery SQL is missing: ' . $needle;
        }
    }
}

foreach ([
    'admin posts action' => $adminPosts,
    'public post delete action' => $postDelete,
    'public comment delete action' => $commentDelete,
] as $label => $content) {
    if (!str_contains($content, 'beginTransaction()')) {
        $errors[] = $label . ' must open an enclosing transaction.';
    }
    if (!str_contains($content, 'sr_community_reverse_asset_grant_for_operation')) {
        $errors[] = $label . ' must use operation recovery wrapper.';
    }
}

if (preg_match('/sr_community_reverse_asset_grant\\s*\\(/', $adminPosts . $postDelete . $commentDelete) === 1) {
    $errors[] = 'operation status/delete paths must not call direct reversal primitive.';
}

foreach ([
    'GET /admin/community/recovery-failures',
    'POST /admin/community/recovery-failures',
] as $route) {
    if (!str_contains($paths, $route)) {
        $errors[] = 'community recovery admin route is missing: ' . $route;
    }
}

$adminRecovery = $read('modules/community/actions/admin-recovery-failures.php');
if (!str_contains($adminRecovery, 'sr_community_asset_recovery_failure_by_id_for_update')) {
    $errors[] = 'community recovery retry action must recheck the latest row inside the transaction.';
}
if (!str_contains($adminRecovery, 'sr_community_asset_recovery_failures_table_exists')) {
    $errors[] = 'community recovery admin action must tolerate unapplied DB updates.';
}

if ($errors !== []) {
    fwrite(STDERR, "community asset recovery check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "community asset recovery check passed.\n";
