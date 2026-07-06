#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

$errors = [];

function sr_member_session_lifetime_check_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_member_session_lifetime_check_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_member_session_lifetime_check_error($message);
    }
}

function sr_now(): string
{
    return date('Y-m-d H:i:s');
}

function sr_client_ip(): string
{
    return '127.0.0.1';
}

function sr_client_user_agent(): string
{
    return 'member-session-lifetime-runtime';
}

function sr_t(string $key, array $params = []): string
{
    return $key;
}

function sr_filter_view_options(array $options, array $requiredViewKeys, string $label): array
{
    return $options;
}

function sr_module_metadata(string $moduleKey): array
{
    $path = SR_ROOT . '/modules/' . $moduleKey . '/module.php';
    $metadata = is_file($path) ? require $path : [];

    return is_array($metadata) ? $metadata : [];
}

function sr_module_settings(PDO $pdo, string $moduleKey): array
{
    $stmt = $pdo->prepare(
        'SELECT s.setting_key, s.setting_value, s.value_type
         FROM sr_module_settings s
         INNER JOIN sr_modules m ON m.id = s.module_id
         WHERE m.module_key = :module_key'
    );
    $stmt->execute(['module_key' => $moduleKey]);
    $settings = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = (string) ($row['setting_key'] ?? '');
        $value = (string) ($row['setting_value'] ?? '');
        $type = (string) ($row['value_type'] ?? 'string');
        if ($key === '') {
            continue;
        }
        if ($type === 'int') {
            $settings[$key] = (int) $value;
        } elseif ($type === 'bool') {
            $settings[$key] = $value === '1';
        } else {
            $settings[$key] = $value;
        }
    }

    return $settings;
}

require_once SR_ROOT . '/modules/member/helpers/sessions.php';

function sr_member_session_lifetime_fixture_pdo(bool $withSettings = true): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec(
        'CREATE TABLE sr_member_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            session_token_hash TEXT NOT NULL UNIQUE,
            remember_token_hash TEXT NULL,
            ip_address TEXT NOT NULL,
            user_agent TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            revoked_at TEXT NULL,
            created_at TEXT NOT NULL,
            last_seen_at TEXT NOT NULL
        )'
    );
    if ($withSettings) {
        $pdo->exec('CREATE TABLE sr_modules (id INTEGER PRIMARY KEY AUTOINCREMENT, module_key TEXT NOT NULL UNIQUE)');
        $pdo->exec('CREATE TABLE sr_module_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, module_id INTEGER NOT NULL, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL, value_type TEXT NOT NULL, UNIQUE(module_id, setting_key))');
        $pdo->exec("INSERT INTO sr_modules (id, module_key) VALUES (1, 'member')");
    }

    return $pdo;
}

function sr_member_session_lifetime_put_setting(PDO $pdo, int $seconds): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type)
         VALUES (1, :setting_key, :setting_value, :value_type)
         ON CONFLICT(module_id, setting_key) DO UPDATE SET
            setting_value = excluded.setting_value,
            value_type = excluded.value_type'
    );
    $stmt->execute([
        'setting_key' => 'session_lifetime_seconds',
        'setting_value' => (string) $seconds,
        'value_type' => 'int',
    ]);
}

function sr_member_session_lifetime_row(PDO $pdo, string $tokenHash): array
{
    $stmt = $pdo->prepare('SELECT * FROM sr_member_sessions WHERE session_token_hash = :session_token_hash LIMIT 1');
    $stmt->execute(['session_token_hash' => $tokenHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : [];
}

function sr_member_session_lifetime_insert(PDO $pdo, int $accountId, string $tokenHash, string $createdAt, string $expiresAt): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO sr_member_sessions
            (account_id, session_token_hash, remember_token_hash, ip_address, user_agent, expires_at, revoked_at, created_at, last_seen_at)
         VALUES
            (:account_id, :session_token_hash, NULL, :ip_address, :user_agent, :expires_at, NULL, :created_at, :last_seen_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'session_token_hash' => $tokenHash,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'fixture',
        'expires_at' => $expiresAt,
        'created_at' => $createdAt,
        'last_seen_at' => $createdAt,
    ]);
}

$fallbackPdo = sr_member_session_lifetime_fixture_pdo(false);
sr_member_session_lifetime_check_assert(
    sr_member_session_lifetime_seconds($fallbackPdo) === 86400,
    'Lifetime helper should fall back to 86400 seconds when settings tables are unavailable.'
);

$pdo = sr_member_session_lifetime_fixture_pdo();
sr_member_session_lifetime_check_assert(
    sr_member_session_lifetime_seconds($pdo) === 86400,
    'Default member session lifetime should be 86400 seconds.'
);

$tokenHash = sr_member_create_session($pdo, 10);
$row = sr_member_session_lifetime_row($pdo, $tokenHash);
$createdAt = strtotime((string) ($row['created_at'] ?? ''));
$expiresAt = strtotime((string) ($row['expires_at'] ?? ''));
sr_member_session_lifetime_check_assert($tokenHash !== '' && $createdAt !== false && $expiresAt !== false, 'Default session row should be created.');
if ($createdAt !== false && $expiresAt !== false) {
    $delta = $expiresAt - $createdAt;
    sr_member_session_lifetime_check_assert($delta >= 86395 && $delta <= 86405, 'Default session expires_at should be about 86400 seconds after created_at.');
}

sr_member_session_lifetime_put_setting($pdo, 3600);
sr_member_session_lifetime_check_assert(
    sr_member_session_lifetime_seconds($pdo) === 3600,
    'Configured member session lifetime should be read from module settings.'
);
$shortTokenHash = sr_member_create_session($pdo, 11);
$shortRow = sr_member_session_lifetime_row($pdo, $shortTokenHash);
$shortCreatedAt = strtotime((string) ($shortRow['created_at'] ?? ''));
$shortExpiresAt = strtotime((string) ($shortRow['expires_at'] ?? ''));
if ($shortCreatedAt !== false && $shortExpiresAt !== false) {
    $delta = $shortExpiresAt - $shortCreatedAt;
    sr_member_session_lifetime_check_assert($delta >= 3595 && $delta <= 3605, 'Configured session expires_at should follow the module setting.');
}

sr_member_session_lifetime_put_setting($pdo, 60);
sr_member_session_lifetime_check_assert(
    sr_member_session_lifetime_seconds($pdo) === 1800,
    'Member settings should clamp session lifetime below the minimum to 1800 seconds.'
);
sr_member_session_lifetime_put_setting($pdo, 9999999);
sr_member_session_lifetime_check_assert(
    sr_member_session_lifetime_seconds($pdo) === 2592000,
    'Member settings should clamp session lifetime above the maximum to 2592000 seconds.'
);

sr_member_session_lifetime_put_setting($pdo, 1800);
$retroToken = str_repeat('a', 64);
sr_member_session_lifetime_insert(
    $pdo,
    12,
    $retroToken,
    date('Y-m-d H:i:s', time() - 1900),
    date('Y-m-d H:i:s', time() + 86400)
);
$_SESSION['sr_session_token_hash'] = $retroToken;
sr_member_session_lifetime_check_assert(
    sr_member_session_is_current($pdo, 12) === false,
    'Shortened lifetime should invalidate existing rows whose created_at plus current lifetime has passed.'
);

sr_member_session_lifetime_put_setting($pdo, 2592000);
$expiredStoredToken = str_repeat('b', 64);
sr_member_session_lifetime_insert(
    $pdo,
    13,
    $expiredStoredToken,
    date('Y-m-d H:i:s', time() - 3600),
    date('Y-m-d H:i:s', time() - 10)
);
$_SESSION['sr_session_token_hash'] = $expiredStoredToken;
sr_member_session_lifetime_check_assert(
    sr_member_session_is_current($pdo, 13) === false,
    'Lengthened lifetime should not revive rows whose stored expires_at has already passed.'
);

sr_member_session_lifetime_put_setting($pdo, 1800);
$cleanupToken = str_repeat('c', 64);
sr_member_session_lifetime_insert(
    $pdo,
    14,
    $cleanupToken,
    date('Y-m-d H:i:s', time() - 1900),
    date('Y-m-d H:i:s', time() + 86400)
);
$cleanupCount = sr_member_cleanup_sessions($pdo);
sr_member_session_lifetime_check_assert($cleanupCount > 0, 'Cleanup should delete rows invalidated by the current lifetime cap.');
sr_member_session_lifetime_check_assert(sr_member_session_lifetime_row($pdo, $cleanupToken) === [], 'Cleanup should remove the lifetime-capped row.');

$missingHelperCode = <<<'PHP'
if (!defined('SR_ROOT')) {
    define('SR_ROOT', getcwd());
}
function sr_now(): string { return date('Y-m-d H:i:s'); }
function sr_client_ip(): string { return '127.0.0.1'; }
function sr_client_user_agent(): string { return 'subprocess'; }
require 'modules/member/helpers/sessions.php';
$pdo = new PDO('sqlite::memory:');
exit(sr_member_session_lifetime_seconds($pdo) === 86400 ? 0 : 1);
PHP;
$command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($missingHelperCode);
exec($command, $output, $exitCode);
sr_member_session_lifetime_check_assert(
    $exitCode === 0,
    'Lifetime helper should fall back to 86400 seconds when module settings helpers are not loaded.'
);

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "member-session-lifetime-runtime: ok\n";
