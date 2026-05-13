#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);

require_once $root . '/modules/admin/helpers/retention.php';

$errors = [];

function sr_retention_check_error(array &$errors, string $message): void
{
    $errors[] = $message;
}

$expectedKeys = [
    'auth_logs',
    'audit_logs',
    'password_resets',
    'email_verifications',
    'sessions',
    'runtime_sessions',
    'rate_limits',
    'notifications',
    'notification_deliveries',
    'notification_reads',
    'module_backups',
];

$targets = sr_admin_retention_target_definitions(true, true, true, true);
if (array_keys($targets) !== $expectedKeys) {
    sr_retention_check_error($errors, 'Retention target keys changed unexpectedly.');
}

foreach ($targets as $key => $target) {
    if (!array_key_exists('enabled', $target) || !is_bool($target['enabled'])) {
        sr_retention_check_error($errors, 'Retention target enabled flag is invalid: ' . $key);
    }

    if (empty($target['cutoff_key']) || !is_string($target['cutoff_key'])) {
        sr_retention_check_error($errors, 'Retention target cutoff key is missing: ' . $key);
    }

    if (!isset($target['count_callback']) && (empty($target['count_sql']) || !is_array($target['count_params'] ?? null))) {
        sr_retention_check_error($errors, 'Retention target count metadata is missing: ' . $key);
    }

    if (!isset($target['delete_callback']) && (empty($target['delete_sql']) || !is_array($target['delete_params'] ?? null))) {
        sr_retention_check_error($errors, 'Retention target delete metadata is missing: ' . $key);
    }
}

$disabledTargets = sr_admin_retention_target_definitions(false, false, false, false);
foreach (['sessions', 'runtime_sessions', 'rate_limits', 'notifications', 'notification_deliveries', 'notification_reads'] as $key) {
    if ($disabledTargets[$key]['enabled'] !== false) {
        sr_retention_check_error($errors, 'Retention optional target should be disabled: ' . $key);
    }
}

$cleanupKeys = sr_admin_retention_cleanup_target_keys();
sort($cleanupKeys);
$sortedExpectedKeys = $expectedKeys;
sort($sortedExpectedKeys);
if ($cleanupKeys !== $sortedExpectedKeys) {
    sr_retention_check_error($errors, 'Retention cleanup keys do not match target keys.');
}

$cleanupOrder = sr_admin_retention_cleanup_target_keys();
$notificationsPosition = array_search('notifications', $cleanupOrder, true);
foreach (['notification_deliveries', 'notification_reads'] as $key) {
    $position = array_search($key, $cleanupOrder, true);
    if (!is_int($position) || !is_int($notificationsPosition) || $position > $notificationsPosition) {
        sr_retention_check_error($errors, 'Retention notification cleanup order is unsafe: ' . $key);
    }
}

$params = sr_admin_retention_query_params(
    [
        'revoked_cutoff' => 'sessions',
        'expired_cutoff' => 'sessions',
    ],
    [
        'sessions' => '2026-01-01 00:00:00',
    ]
);
if ($params !== ['revoked_cutoff' => '2026-01-01 00:00:00', 'expired_cutoff' => '2026-01-01 00:00:00']) {
    sr_retention_check_error($errors, 'Retention query params mapping failed.');
}

$retentionHelper = file_get_contents($root . '/modules/admin/helpers/retention.php');
if (!is_string($retentionHelper)) {
    sr_retention_check_error($errors, 'Retention helper cannot be read.');
} elseif (
    strpos($retentionHelper, 'sr_admin_post_int_in_range($key, 1, 3650, 5) ?? 0') === false
    || strpos($retentionHelper, "(int) sr_post_string('auth_logs_days', 5)") !== false
) {
    sr_retention_check_error($errors, 'Retention post values must reject malformed or out-of-range raw day inputs.');
}

if ($errors !== []) {
    fwrite(STDERR, "retention target checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "retention target checks completed.\n";
