#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}
chdir($root);

require_once SR_ROOT . '/core/helpers/runtime.php';
require_once SR_ROOT . '/core/helpers/ops.php';
require_once SR_ROOT . '/core/helpers/output.php';
require_once SR_ROOT . '/modules/admin/helpers/action-results.php';

function sr_save_site_setting(PDO $pdo, string $key, string $value, string $valueType = 'string'): void
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_site_settings
            (setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON CONFLICT(setting_key) DO UPDATE SET
            setting_value = excluded.setting_value,
            value_type = excluded.value_type,
            updated_at = excluded.updated_at'
    );
    $stmt->execute([
        'setting_key' => $key,
        'setting_value' => $value,
        'value_type' => $valueType,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

require_once SR_ROOT . '/modules/admin/helpers/module-actions.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(
    'CREATE TABLE sr_site_settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT NOT NULL,
        value_type TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )'
);
$pdo->exec(
    'CREATE TABLE sr_audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        actor_account_id INTEGER NULL,
        actor_type TEXT NOT NULL,
        event_type TEXT NOT NULL,
        target_type TEXT NOT NULL,
        target_id TEXT NOT NULL,
        result TEXT NOT NULL,
        ip_address TEXT NOT NULL,
        user_agent TEXT NOT NULL,
        message TEXT NOT NULL,
        metadata_json TEXT NULL,
        created_at TEXT NOT NULL
    )'
);
$pdo->exec(
    "INSERT INTO sr_site_settings
        (setting_key, setting_value, value_type, created_at, updated_at)
     VALUES
        ('admin.module_sources_enabled', 'yes', 'string', '2026-06-17 00:00:00', '2026-06-17 00:00:00')"
);

$_POST = [
    'intent' => 'upload_module_zip',
    'upload_module_key' => 'sample',
    'owner_password' => 'irrelevant',
];
$_FILES = [];

$result = sr_admin_handle_modules_post(
    $pdo,
    ['id' => 1],
    true,
    [],
    ['enabled', 'disabled'],
    ['enabled', 'disabled'],
    false,
    false
);

$setting = $pdo
    ->query("SELECT setting_value, value_type FROM sr_site_settings WHERE setting_key = 'admin.module_sources_enabled'")
    ->fetch(PDO::FETCH_ASSOC);
$log = $pdo
    ->query('SELECT event_type, result, message, metadata_json FROM sr_audit_logs ORDER BY id DESC LIMIT 1')
    ->fetch(PDO::FETCH_ASSOC);

if (!is_array($result) || ($result['errors'] ?? []) === []) {
    fwrite(STDERR, "Module upload preflight should return validation errors.\n");
    exit(1);
}

if (!is_array($setting) || $setting['setting_value'] !== '0' || $setting['value_type'] !== 'bool') {
    fwrite(STDERR, "Module source allowance should close after upload preflight failure.\n");
    exit(1);
}

if (
    !is_array($log)
    || $log['event_type'] !== 'module.source.uploaded'
    || $log['result'] !== 'failure'
    || $log['message'] !== 'Module source zip upload failed.'
) {
    fwrite(STDERR, "Module upload preflight failure should write a failure audit log.\n");
    exit(1);
}

$metadata = json_decode((string) $log['metadata_json'], true);
if (
    !is_array($metadata)
    || ($metadata['stage'] ?? '') !== 'preflight'
    || ($metadata['module_sources_enabled'] ?? true) !== false
    || ($metadata['zip_upload_available'] ?? true) !== false
    || !is_array($metadata['validation_errors'] ?? null)
    || $metadata['validation_errors'] === []
) {
    fwrite(STDERR, "Module upload preflight failure audit metadata is incomplete.\n");
    exit(1);
}

echo "module upload action runtime checks completed.\n";
