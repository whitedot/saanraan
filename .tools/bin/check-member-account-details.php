#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/member/helpers/settings.php';
require_once $root . '/modules/member/helpers/nicknames.php';
require_once $root . '/modules/member/helpers/accounts.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDOUT, "Member account detail checks skipped: PDO SQLite is unavailable.\n");
    exit(0);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(
    'CREATE TABLE sr_member_accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_identifier_hash TEXT NOT NULL UNIQUE,
        login_id_hash TEXT NULL UNIQUE,
        email TEXT NOT NULL,
        email_hash TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        display_name TEXT NOT NULL,
        locale TEXT NOT NULL,
        status TEXT NOT NULL,
        email_verified_at TEXT NULL,
        last_login_at TEXT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )'
);

$config = ['app_key' => 'member-account-details-fixture-key'];
$insertAccount = static function (PDO $pdo, array $config, int $id, string $email, string $loginId): void {
    $emailHash = sr_hmac_hash($email, $config);
    $loginIdHash = sr_hmac_hash($loginId, $config);
    $stmt = $pdo->prepare(
        'INSERT INTO sr_member_accounts
            (id, account_identifier_hash, login_id_hash, email, email_hash, password_hash, display_name, locale, status, email_verified_at, created_at, updated_at)
         VALUES
            (:id, :account_identifier_hash, :login_id_hash, :email, :email_hash, :password_hash, :display_name, :locale, :status, :email_verified_at, :created_at, :updated_at)'
    );
    $stmt->execute([
        'id' => $id,
        'account_identifier_hash' => $loginIdHash,
        'login_id_hash' => $loginIdHash,
        'email' => $email,
        'email_hash' => $emailHash,
        'password_hash' => 'fixture-password-hash',
        'display_name' => 'fixture',
        'locale' => 'ko',
        'status' => 'active',
        'email_verified_at' => '2026-07-20 00:00:00',
        'created_at' => '2026-07-20 00:00:00',
        'updated_at' => '2026-07-20 00:00:00',
    ]);
};

$insertAccount($pdo, $config, 1, 'first@example.com', 'first_login');
$insertAccount($pdo, $config, 2, 'second@example.com', 'second_login');
$pdo->exec('CREATE TABLE sr_fixture_posts (id INTEGER PRIMARY KEY, author_account_id INTEGER NOT NULL)');
$pdo->exec('INSERT INTO sr_fixture_posts (id, author_account_id) VALUES (10, 1)');

$account = sr_member_find_by_id($pdo, 1);
$assert(is_array($account), 'Fixture member account must be available before update.');
if (is_array($account)) {
    $result = sr_member_update_account_details(
        $pdo,
        $config,
        $account,
        'changed@example.com',
        'changed_login',
        '변경회원',
        'en',
        true
    );
    $updated = sr_member_find_by_id($pdo, 1);
    $assert(is_array($updated), 'Updated member account must remain available by stable account ID.');
    $assert((int) $pdo->query('SELECT author_account_id FROM sr_fixture_posts WHERE id = 10')->fetchColumn() === 1, 'Login ID changes must not alter post ownership linked by account ID.');
    $assert(sr_member_find_by_identifier($pdo, $config, 'first_login') === null, 'The previous login ID must stop resolving after change.');
    $assert((int) (sr_member_find_by_identifier($pdo, $config, 'changed_login')['id'] ?? 0) === 1, 'The new login ID must resolve to the same stable account ID.');
    $assert((string) ($updated['account_identifier_hash'] ?? '') === sr_hmac_hash('changed_login', $config), 'Primary and login identifier hashes must move together.');
    $assert((string) ($updated['login_id_hash'] ?? '') === sr_hmac_hash('changed_login', $config), 'Login ID hash must be replaced atomically.');
    $assert((string) ($updated['email'] ?? '') === 'changed@example.com' && ($updated['email_verified_at'] ?? null) === null, 'Changed email must be stored and require verification again.');
    $assert(!empty($result['login_id_changed']) && !empty($result['email_changed']), 'Account detail update result must report identifier changes.');
}

$duplicateLoginRejected = false;
try {
    $account = sr_member_find_by_id($pdo, 1);
    if (is_array($account)) {
        sr_member_update_account_details($pdo, $config, $account, 'changed@example.com', 'second_login', '변경회원', 'en', true);
    }
} catch (RuntimeException $exception) {
    $duplicateLoginRejected = $exception->getMessage() === 'member_account_login_id_duplicate';
}
$assert($duplicateLoginRejected, 'Account detail update must reject another account login ID.');

$duplicateEmailRejected = false;
try {
    $account = sr_member_find_by_id($pdo, 1);
    if (is_array($account)) {
        sr_member_update_account_details($pdo, $config, $account, 'second@example.com', null, '변경회원', 'en', true);
    }
} catch (RuntimeException $exception) {
    $duplicateEmailRejected = $exception->getMessage() === 'member_account_email_duplicate';
}
$assert($duplicateEmailRejected, 'Account detail update must reject another account email.');

$domainHashReferences = [];
$allowedHashModules = ['member', 'member_oauth', 'privacy'];
foreach (new DirectoryIterator($root . '/modules') as $moduleDirectory) {
    if (!$moduleDirectory->isDir() || $moduleDirectory->isDot() || in_array($moduleDirectory->getFilename(), $allowedHashModules, true)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleDirectory->getPathname(), FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'sql'], true)) {
            continue;
        }
        $source = file_get_contents($file->getPathname());
        if (is_string($source) && (str_contains($source, 'login_id_hash') || str_contains($source, 'account_identifier_hash'))) {
            $domainHashReferences[] = str_replace($root . '/', '', $file->getPathname());
        }
    }
}
$assert($domainHashReferences === [], 'Domain modules must link member data by account ID, not login identifier hashes: ' . implode(', ', $domainHashReferences));

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

fwrite(STDOUT, "Member account detail checks passed.\n");
