#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once SR_ROOT . '/core/helpers/runtime.php';
require_once SR_ROOT . '/core/helpers/storage.php';
require_once SR_ROOT . '/core/helpers/install-reset.php';

$args = array_slice($argv, 1);
$json = false;
$execute = false;
$confirmation = '';
$batchSize = 50;
$allowProductionLooking = false;
$confirmRemoteStorage = false;
foreach ($args as $arg) {
    if ($arg === '--json') {
        $json = true;
        continue;
    }
    if ($arg === '--preview') {
        continue;
    }
    if ($arg === '--execute') {
        $execute = true;
        continue;
    }
    if (str_starts_with($arg, '--confirm=')) {
        $confirmation = substr($arg, strlen('--confirm='));
        continue;
    }
    if (str_starts_with($arg, '--batch-size=')) {
        $batchSize = (int) substr($arg, strlen('--batch-size='));
        continue;
    }
    if ($arg === '--allow-production-looking') {
        $allowProductionLooking = true;
        continue;
    }
    if ($arg === '--confirm-remote-storage') {
        $confirmRemoteStorage = true;
        continue;
    }
    if ($arg === '--help' || $arg === '-h') {
        echo "install-reset-preview-version: 1\n";
        echo "Usage: php .tools/bin/install-reset.php [--preview] [--json]\n";
        echo "       php .tools/bin/install-reset.php --execute --confirm=초기화 [--batch-size=50] [--confirm-remote-storage] [--allow-production-looking]\n";
        exit(0);
    }

    fwrite(STDERR, "Unknown install reset option: " . $arg . "\n");
    fwrite(STDERR, "Run php .tools/bin/install-reset.php --help for supported options.\n");
    exit(2);
}

$configPath = SR_ROOT . '/config/config.php';
$lockPath = SR_ROOT . '/storage/installed.lock';
$configExists = is_file($configPath);
$configReadable = is_readable($configPath);
$lockExists = is_file($lockPath);

if (!$configExists && !$lockExists) {
    sr_install_reset_print_preview([
        'version' => 1,
        'surface' => 'cli-preview',
        'state' => 'already-initial',
        'message' => 'config/config.php and storage/installed.lock are both missing.',
        'install_state_files' => sr_install_reset_state_file_preview($configPath, $lockPath),
    ], $json);
    exit(0);
}

if ($configExists && !$configReadable) {
    sr_install_reset_print_preview([
        'version' => 1,
        'surface' => 'cli-preview',
        'state' => 'unavailable',
        'message' => 'config/config.php is not readable by the current CLI user.',
        'install_state_files' => sr_install_reset_state_file_preview($configPath, $lockPath),
    ], $json);
    exit(1);
}

try {
    $config = sr_load_config();
    $pdo = sr_db($config);
    $tablePrefix = sr_table_prefix($config);
    $allowlist = sr_install_reset_table_allowlist(SR_ROOT, $tablePrefix);
    $tablePreview = sr_install_reset_table_preview($pdo, $allowlist, $tablePrefix);
    $targetTables = array_map(
        static fn (array $table): string => (string) ($table['name'] ?? ''),
        is_array($tablePreview['tables'] ?? null) ? $tablePreview['tables'] : []
    );
    $storagePreview = sr_install_reset_storage_preview($pdo, $targetTables, $config, ['table_prefix' => $tablePrefix]);
    $environmentWarnings = sr_install_reset_environment_warnings($config);
    $execution = null;
    if ($execute) {
        $execution = sr_install_reset_execute($pdo, $targetTables, $config, SR_ROOT, [
            'confirmation' => $confirmation,
            'table_prefix' => $tablePrefix,
            'batch_size' => $batchSize,
            'allow_production_looking' => $allowProductionLooking,
            'confirm_remote_storage' => $confirmRemoteStorage,
        ]);
    }
} catch (Throwable $exception) {
    sr_install_reset_print_preview([
        'version' => 1,
        'surface' => 'cli-preview',
        'state' => 'unavailable',
        'message' => $exception->getMessage(),
        'install_state_files' => sr_install_reset_state_file_preview($configPath, $lockPath),
    ], $json);
    exit(1);
}

sr_install_reset_print_preview([
    'version' => 1,
    'surface' => 'cli-preview',
    'state' => 'preview',
    'message' => 'Read-only install reset preview completed. Destructive execution is not implemented yet.',
    'install_state_files' => sr_install_reset_state_file_preview($configPath, $lockPath),
    'environment_warnings' => $environmentWarnings,
    'database' => $tablePreview,
    'storage' => $storagePreview,
    'execution' => $execution,
], $json);

function sr_install_reset_state_file_preview(string $configPath, string $lockPath): array
{
    return [
        [
            'path' => 'config/config.php',
            'present' => is_file($configPath),
            'readable' => is_readable($configPath),
        ],
        [
            'path' => 'storage/installed.lock',
            'present' => is_file($lockPath),
            'readable' => is_readable($lockPath),
        ],
    ];
}

function sr_install_reset_print_preview(array $preview, bool $json): void
{
    if ($json) {
        echo json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        return;
    }

    echo "install-reset-preview-version: " . (string) ($preview['version'] ?? 1) . "\n";
    echo "surface: " . (string) ($preview['surface'] ?? 'cli-preview') . "\n";
    echo "state: " . (string) ($preview['state'] ?? '') . "\n";
    echo "message: " . (string) ($preview['message'] ?? '') . "\n";
    echo "install-state-files:\n";
    foreach (($preview['install_state_files'] ?? []) as $file) {
        if (!is_array($file)) {
            continue;
        }
        echo "- " . (string) ($file['path'] ?? '') . ': '
            . (!empty($file['present']) ? 'present' : 'missing')
            . ', ' . (!empty($file['readable']) ? 'readable' : 'not-readable') . "\n";
    }

    $database = $preview['database'] ?? null;
    if (is_array($database)) {
        echo "database:\n";
        echo "- table-prefix: " . (string) ($database['table_prefix'] ?? '') . "\n";
        echo "- allowlist-tables: " . (string) ($database['allowlist_count'] ?? 0) . "\n";
        echo "- existing-prefixed-tables: " . (string) ($database['existing_prefixed_count'] ?? 0) . "\n";
        echo "- target-tables: " . (string) ($database['target_table_count'] ?? 0) . "\n";
        echo "- target-rows: " . (string) ($database['target_row_count'] ?? 0) . "\n";
        $ignored = is_array($database['ignored_prefixed_tables'] ?? null) ? $database['ignored_prefixed_tables'] : [];
        echo "- ignored-prefixed-tables: " . (string) count($ignored) . "\n";
    }

    $warnings = is_array($preview['environment_warnings'] ?? null) ? $preview['environment_warnings'] : [];
    echo "environment-warnings: " . (string) count($warnings) . "\n";
    foreach ($warnings as $warning) {
        echo "- " . (string) $warning . "\n";
    }

    $storage = $preview['storage'] ?? null;
    if (is_array($storage)) {
        echo "storage:\n";
        echo "- reference-columns: " . (string) ($storage['reference_column_count'] ?? 0) . "\n";
        echo "- references: " . (string) ($storage['reference_count'] ?? 0) . "\n";
        echo "- safe-references: " . (string) ($storage['safe_reference_count'] ?? 0) . "\n";
        echo "- unsafe-references: " . (string) ($storage['unsafe_reference_count'] ?? 0) . "\n";
        echo "- local-references: " . (string) ($storage['local_reference_count'] ?? 0) . "\n";
        echo "- remote-references: " . (string) ($storage['remote_reference_count'] ?? 0) . "\n";
        echo "- local-existing-files: " . (string) ($storage['local_existing_file_count'] ?? 0) . "\n";
        echo "- local-existing-bytes: " . (string) ($storage['local_existing_bytes'] ?? 0) . "\n";
        echo "- truncated: " . (!empty($storage['truncated']) ? 'yes' : 'no') . "\n";
    }

    $execution = $preview['execution'] ?? null;
    if (is_array($execution)) {
        echo "execution:\n";
        echo "- state: " . (string) ($execution['state'] ?? '') . "\n";
        echo "- message: " . (string) ($execution['message'] ?? '') . "\n";
        if (isset($execution['stage'])) {
            echo "- stage: " . (string) $execution['stage'] . "\n";
        }
    }
}
