#!/usr/bin/env php
<?php

declare(strict_types=1);

define('SR_ROOT', dirname(__DIR__, 2));

require_once SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/member/helpers.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

$config = ['app_key' => str_repeat('public-hash-fixture-key-', 4)];
$firstHash = sr_member_public_account_hash($config, 7);
$secondHash = sr_member_public_account_hash($config, 8);

$assert(preg_match('/\A[a-f0-9]{32}\z/', $firstHash) === 1, 'Public account hash must remain a 32-character lowercase hexadecimal identifier.');
$assert($firstHash === sr_member_public_account_hash($config, 7), 'Public account hash must be stable for the same account and app key.');
$assert($firstHash !== $secondHash, 'Different account ids must produce different public account hashes.');
$assert(sr_member_public_account_id_from_hash($config, $firstHash) === 7, 'Public account hash must resolve directly to its account id.');

$tamperedHash = substr($firstHash, 0, 31) . ($firstHash[31] === '0' ? '1' : '0');
$assert(sr_member_public_account_id_from_hash($config, $tamperedHash) === 0, 'Tampered public account hash must be rejected.');
$assert(sr_member_public_account_id_from_hash(['app_key' => 'different-fixture-key'], $firstHash) === 0, 'Public account hash must be bound to the app key.');

if (extension_loaded('pdo_sqlite')) {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('CREATE TABLE sr_modules (id INTEGER PRIMARY KEY, module_key TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE sr_module_settings (module_id INTEGER, setting_key TEXT, setting_value TEXT, value_type TEXT)');
    $pdo->exec('CREATE TABLE sr_member_accounts (id INTEGER PRIMARY KEY, display_name TEXT, locale TEXT, status TEXT)');
    $pdo->exec("INSERT INTO sr_modules (id, module_key, status) VALUES (1, 'member', 'enabled')");
    $pdo->exec("INSERT INTO sr_member_accounts (id, display_name, locale, status) VALUES (7, 'Seven', 'ko', 'active'), (8, 'Eight', 'ko', 'suspended')");

    $summary = sr_member_public_account_summary_by_hash($pdo, $config, $firstHash);
    $assert(is_array($summary) && (int) ($summary['id'] ?? 0) === 7, 'Public account hash lookup must load the decoded active account only.');
    $assert(sr_member_public_account_summary_by_hash($pdo, $config, $secondHash) === null, 'Public account hash lookup must reject inactive accounts.');
}

$source = file_get_contents(SR_ROOT . '/modules/member/helpers/accounts.php');
$assert(is_string($source) && !str_contains($source, 'function sr_member_public_account_summaries_by_hash'), 'Public account hash lookup must not rebuild a map of every active account.');
$assert(is_string($source) && !str_contains($source, 'static $cachedMaps = []'), 'Public account hash lookup must not cache every active account in memory.');

if ($errors !== []) {
    fwrite(STDERR, "member public hash checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "member public hash checks completed.\n";
