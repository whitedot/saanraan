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

$errors = [];

function sr_install_reset_check_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_install_reset_check_contains(string $path, array $markers): void
{
    if (!is_file($path)) {
        sr_install_reset_check_error('Required file is missing: ' . $path);
        return;
    }

    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        sr_install_reset_check_error('Required file cannot be read: ' . $path);
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            sr_install_reset_check_error($path . ' missing marker: ' . $marker);
        }
    }
}

$sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS sr_member_accounts (id INTEGER PRIMARY KEY);
CREATE TABLE {{SR_TABLE_PREFIX}}community_posts (id INTEGER PRIMARY KEY);
'CREATE TABLE {{SR_TABLE_PREFIX}}content_file_links (id BIGINT UNSIGNED NOT NULL)';
CREATE TABLE IF NOT EXISTS external_table (id INTEGER PRIMARY KEY);
SQL;

$names = sr_install_reset_table_names_from_sql($sql, 'sr_');
foreach (['sr_member_accounts', 'sr_community_posts', 'sr_content_file_links'] as $expectedTable) {
    if (!in_array($expectedTable, $names, true)) {
        sr_install_reset_check_error('SQL table allowlist parser missed: ' . $expectedTable);
    }
}
if (in_array('external_table', $names, true)) {
    sr_install_reset_check_error('SQL table allowlist parser must ignore non-prefixed tables.');
}

$prefixedNames = sr_install_reset_table_names_from_sql($sql, 'demo_');
foreach (['demo_member_accounts', 'demo_community_posts', 'demo_content_file_links'] as $expectedTable) {
    if (!in_array($expectedTable, $prefixedNames, true)) {
        sr_install_reset_check_error('SQL table allowlist parser missed custom prefix table: ' . $expectedTable);
    }
}

$allowlist = sr_install_reset_table_allowlist(SR_ROOT, 'sr_');
foreach (['sr_site_settings', 'sr_modules', 'sr_member_accounts', 'sr_community_posts'] as $expectedTable) {
    if (!in_array($expectedTable, $allowlist, true)) {
        sr_install_reset_check_error('Repository install reset allowlist missed: ' . $expectedTable);
    }
}

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('CREATE TABLE sr_member_accounts (id INTEGER PRIMARY KEY)');
$pdo->exec('CREATE TABLE sr_community_posts (id INTEGER PRIMARY KEY)');
$pdo->exec('CREATE TABLE sr_content_files (id INTEGER PRIMARY KEY, storage_driver TEXT, storage_key TEXT, created_storage_driver TEXT, created_storage_key TEXT, bad_storage_key TEXT)');
$pdo->exec('CREATE TABLE demo_content_files (id INTEGER PRIMARY KEY, storage_driver TEXT, storage_key TEXT)');
$pdo->exec('CREATE TABLE sr_not_allowlisted (id INTEGER PRIMARY KEY)');
$pdo->exec('CREATE TABLE member_legacy (id INTEGER PRIMARY KEY)');
$pdo->exec('INSERT INTO sr_member_accounts (id) VALUES (1), (2)');
$pdo->exec('INSERT INTO sr_community_posts (id) VALUES (10)');
$fixtureKey = 'cache/check-install-reset-policy/sample.txt';
$fixtureDir = SR_ROOT . '/storage/' . dirname($fixtureKey);
if (!is_dir($fixtureDir) && !mkdir($fixtureDir, 0755, true) && !is_dir($fixtureDir)) {
    sr_install_reset_check_error('Unable to create install reset storage fixture directory.');
}
$fixtureFile = $fixtureDir . '/sample.txt';
if (file_put_contents($fixtureFile, 'sample') === false) {
    sr_install_reset_check_error('Unable to write install reset storage fixture file.');
}
$pdo->exec(
    "INSERT INTO sr_content_files (id, storage_driver, storage_key, created_storage_driver, created_storage_key, bad_storage_key)
     VALUES
     (1, 'local', 'cache/check-install-reset-policy/sample.txt', 's3', 'content/remote-object.txt', '../unsafe'),
     (2, 'local', '', 'local', '', '')"
);
$pdo->exec("INSERT INTO demo_content_files (id, storage_driver, storage_key) VALUES (1, 'local', 'cache/check-install-reset-policy/sample.txt')");
$preview = sr_install_reset_table_preview($pdo, ['sr_member_accounts', 'sr_community_posts'], 'sr_');
$targetNames = array_map(
    static fn (array $table): string => (string) ($table['name'] ?? ''),
    is_array($preview['tables'] ?? null) ? $preview['tables'] : []
);

foreach (['sr_member_accounts', 'sr_community_posts'] as $expectedTable) {
    if (!in_array($expectedTable, $targetNames, true)) {
        sr_install_reset_check_error('Preview missed allowlisted existing table: ' . $expectedTable);
    }
}
foreach (['sr_not_allowlisted', 'member_legacy'] as $blockedTable) {
    if (in_array($blockedTable, $targetNames, true)) {
        sr_install_reset_check_error('Preview must not target non-allowlisted or non-prefixed table: ' . $blockedTable);
    }
}
if ((int) ($preview['target_table_count'] ?? 0) !== 2) {
    sr_install_reset_check_error('Preview target_table_count should be 2.');
}
if ((int) ($preview['target_row_count'] ?? 0) !== 3) {
    sr_install_reset_check_error('Preview target_row_count should be 3.');
}
if (!in_array('sr_not_allowlisted', (array) ($preview['ignored_prefixed_tables'] ?? []), true)) {
    sr_install_reset_check_error('Preview should report ignored prefixed non-allowlisted tables.');
}

$storagePreview = sr_install_reset_storage_preview($pdo, ['sr_content_files'], ['storage' => ['default' => 'local']], ['max_references_per_column' => 10]);
if ((int) ($storagePreview['reference_column_count'] ?? 0) !== 3) {
    sr_install_reset_check_error('Storage preview should discover three storage key columns.');
}
if ((int) ($storagePreview['reference_count'] ?? 0) !== 3) {
    sr_install_reset_check_error('Storage preview reference_count should be 3.');
}
if ((int) ($storagePreview['safe_reference_count'] ?? 0) !== 2) {
    sr_install_reset_check_error('Storage preview safe_reference_count should be 2.');
}
if ((int) ($storagePreview['unsafe_reference_count'] ?? 0) !== 1) {
    sr_install_reset_check_error('Storage preview unsafe_reference_count should be 1.');
}
if ((int) ($storagePreview['remote_reference_count'] ?? 0) !== 1) {
    sr_install_reset_check_error('Storage preview remote_reference_count should be 1.');
}
if ((int) ($storagePreview['local_existing_file_count'] ?? 0) !== 1) {
    sr_install_reset_check_error('Storage preview local_existing_file_count should be 1.');
}
if ((int) ($storagePreview['local_existing_bytes'] ?? 0) !== 6) {
    sr_install_reset_check_error('Storage preview local_existing_bytes should be 6.');
}

$customPrefixPreview = sr_install_reset_storage_preview($pdo, ['demo_content_files'], ['storage' => ['default' => 'local']], ['table_prefix' => 'demo_', 'max_references_per_column' => 10]);
if ((int) ($customPrefixPreview['reference_column_count'] ?? 0) !== 1 || (int) ($customPrefixPreview['local_existing_file_count'] ?? 0) !== 1) {
    sr_install_reset_check_error('Storage preview should inspect existing custom-prefix target tables.');
}

$warnings = sr_install_reset_environment_warnings([
    'env' => 'production',
    'site' => ['base_url' => 'https://www.example.com'],
    'db' => ['name' => 'saanraan_prod'],
    'storage' => ['s3' => ['bucket' => 'saanraan-live']],
]);
foreach (['config env is production-looking.', 'site base_url is not local/staging-looking.', 'db name is production-looking.', 'storage bucket is production-looking.'] as $expectedWarning) {
    if (!in_array($expectedWarning, $warnings, true)) {
        sr_install_reset_check_error('Environment warning missed: ' . $expectedWarning);
    }
}

sr_install_reset_check_contains('.tools/bin/install-reset.php', [
    'install-reset-preview-version: 1',
    'This command is read-only. Destructive execution is not implemented yet.',
    'sr_install_reset_table_allowlist',
    'sr_install_reset_table_preview',
    'sr_install_reset_storage_preview',
    'sr_install_reset_environment_warnings',
]);

sr_install_reset_check_contains('docs/install-reset.md', [
    '설치 초기화',
    'CLI preview',
    'allowlist',
    'DB introspection',
    '실행 모드는 아직 구현하지 않는다',
    'config/config.php',
    'storage/installed.lock',
    'storage reference',
    'production-looking',
]);

sr_install_reset_check_contains('docs/site-reset-and-fixtures.md', [
    '설치 초기화가 아니다',
    'docs/install-reset.md',
]);

if ($errors !== []) {
    fwrite(STDERR, "install reset policy checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "install reset policy checks completed.\n";
