#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}
ini_set('error_log', sys_get_temp_dir() . '/saanraan-privacy-export-status-check.log');

if (!function_exists('sr_now')) {
    function sr_now(): string
    {
        return '2026-07-02 12:00:00';
    }
}

function sr_enabled_module_contract_files(PDO $pdo, string $contractFile, array $excludedModuleKeys = []): array
{
    if ($contractFile !== 'privacy-export.php') {
        return [];
    }

    return [
        'member' => '/tmp/member-privacy-export.php',
        'empty_module' => '/tmp/empty-privacy-export.php',
        'broken_module' => '/tmp/broken-privacy-export.php',
    ];
}

function sr_load_module_contract_file(string $moduleKey, string $file): mixed
{
    if ($moduleKey === 'member') {
        return static function (PDO $pdo, int $accountId): array {
            return [
                'account' => [
                    'id' => $accountId,
                    'email' => 'member@example.test',
                    'password_hash' => 'must-not-export',
                ],
            ];
        };
    }

    if ($moduleKey === 'empty_module') {
        return [];
    }

    if ($moduleKey === 'broken_module') {
        return static function (): array {
            throw new RuntimeException('simulated privacy export failure');
        };
    }

    return null;
}

require_once SR_ROOT . '/modules/privacy/helpers/requests.php';

$errors = [];

function sr_privacy_export_status_check_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(
    'CREATE TABLE sr_privacy_requests (
        id INTEGER PRIMARY KEY,
        account_id INTEGER NULL,
        request_type TEXT NOT NULL,
        status TEXT NOT NULL,
        request_message TEXT NULL,
        admin_note TEXT NULL,
        handled_at TEXT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )'
);
$pdo->exec(
    "INSERT INTO sr_privacy_requests
        (id, account_id, request_type, status, request_message, admin_note, handled_at, created_at, updated_at)
     VALUES
        (1, 7, 'access', 'completed', 'request', 'admin@example.test token=abc', '2026-07-02 11:00:00', '2026-07-02 10:00:00', '2026-07-02 11:00:00')"
);

$export = sr_privacy_export_data($pdo, 7);
$encodedExport = json_encode($export, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

sr_privacy_export_status_check_assert(($export['export_schema_version'] ?? '') === 'privacy_export_v1', 'Privacy export must include schema version.');
sr_privacy_export_status_check_assert(is_array($export['sections'] ?? null), 'Privacy export must include explanatory sections metadata.');
sr_privacy_export_status_check_assert(($export['partial_export'] ?? false) === true, 'Privacy export must mark partial_export when a module export fails.');
sr_privacy_export_status_check_assert(is_array($export['module_export_status'] ?? null), 'Privacy export must include module_export_status.');

$memberStatus = $export['module_export_status']['member']['status'] ?? '';
$emptyStatus = $export['module_export_status']['empty_module']['status'] ?? '';
$brokenStatus = $export['module_export_status']['broken_module']['status'] ?? '';

sr_privacy_export_status_check_assert($memberStatus === 'success', 'Successful module export must be marked success.');
sr_privacy_export_status_check_assert($emptyStatus === 'empty', 'Empty module export must be marked empty.');
sr_privacy_export_status_check_assert($brokenStatus === 'failed', 'Throwing module export must be marked failed.');
sr_privacy_export_status_check_assert(
    ($export['module_export_status']['broken_module']['error_code'] ?? '') === 'module_export_exception',
    'Failed module export must include a stable error_code.'
);
sr_privacy_export_status_check_assert(
    is_string($export['module_export_status']['broken_module']['evidence_id'] ?? null)
        && str_contains((string) $export['module_export_status']['broken_module']['evidence_id'], 'privacy_export_module_broken_module_7_20260702120000_'),
    'Failed module export must include an evidence_id that identifies module, account, and export time.'
);
sr_privacy_export_status_check_assert(
    !array_key_exists('broken_module', $export['module_exports'] ?? []),
    'Failed module export data must not be included in module_exports.'
);
sr_privacy_export_status_check_assert(
    !array_key_exists('password_hash', $export['module_exports']['member']['account'] ?? []),
    'Successful module export must still remove internal hash fields.'
);
sr_privacy_export_status_check_assert(
    is_string($encodedExport)
        && !str_contains($encodedExport, 'simulated privacy export failure'),
    'Privacy export JSON must not expose raw exception messages from failed module exports.'
);

if ($errors !== []) {
    fwrite(STDERR, "privacy export status checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "privacy export status checks completed.\n";
