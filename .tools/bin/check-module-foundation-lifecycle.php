#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once SR_ROOT . '/core/helpers/settings.php';
require_once SR_ROOT . '/core/helpers/module-lifecycle.php';

$errors = [];

function sr_module_foundation_lifecycle_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_module_foundation_lifecycle_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_module_foundation_lifecycle_error($message);
    }
}

function sr_module_foundation_lifecycle_pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec(
        'CREATE TABLE sr_modules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_key TEXT NOT NULL UNIQUE,
            version TEXT NOT NULL,
            status TEXT NOT NULL
        )'
    );

    return $pdo;
}

function sr_module_foundation_lifecycle_insert_modules(PDO $pdo, array $rows): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO sr_modules (module_key, version, status)
         VALUES (:module_key, :version, :status)'
    );

    foreach ($rows as $row) {
        $stmt->execute([
            'module_key' => (string) ($row['module_key'] ?? ''),
            'version' => (string) ($row['version'] ?? '2026.07.001'),
            'status' => (string) ($row['status'] ?? 'enabled'),
        ]);
    }
}

$pdo = sr_module_foundation_lifecycle_pdo();
sr_module_foundation_lifecycle_insert_modules($pdo, [
    ['module_key' => 'member'],
    ['module_key' => 'admin'],
    ['module_key' => 'asset_ledger'],
    ['module_key' => 'payment_ledger'],
    ['module_key' => 'point'],
    ['module_key' => 'reward', 'status' => 'disabled'],
    ['module_key' => 'content'],
    ['module_key' => 'community', 'status' => 'disabled'],
    ['module_key' => 'coupon'],
    ['module_key' => 'seo'],
]);

$assetDependents = sr_enabled_modules_requiring_foundation($pdo, 'asset_ledger');
sr_module_foundation_lifecycle_assert($assetDependents === ['point'], 'asset_ledger disable guard must include enabled asset modules only.');

$paymentDependents = sr_enabled_modules_requiring_foundation($pdo, 'payment_ledger');
sr_module_foundation_lifecycle_assert($paymentDependents === ['content', 'coupon'], 'payment_ledger disable guard must include enabled content/coupon dependents only.');

$assetDisableErrors = sr_module_disable_errors($pdo, 'asset_ledger');
sr_module_foundation_lifecycle_assert($assetDisableErrors !== [] && str_contains($assetDisableErrors[0], 'point'), 'asset_ledger disable errors must block active dependent modules.');

$paymentDisableErrors = sr_module_disable_errors($pdo, 'payment_ledger');
sr_module_foundation_lifecycle_assert(
    $paymentDisableErrors !== []
    && str_contains($paymentDisableErrors[0], 'content')
    && str_contains($paymentDisableErrors[0], 'coupon'),
    'payment_ledger disable errors must block active dependent modules.'
);

sr_module_foundation_lifecycle_assert(sr_module_disable_errors($pdo, 'content') === [], 'non-foundation modules must not use foundation disable guard.');

$pdo->exec("UPDATE sr_modules SET status = 'disabled' WHERE module_key IN ('content', 'coupon')");
sr_module_foundation_lifecycle_assert(sr_module_disable_errors($pdo, 'payment_ledger') === [], 'payment_ledger disable guard must allow disabling after dependents are disabled.');

if ($errors !== []) {
    fwrite(STDERR, "module foundation lifecycle checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "module foundation lifecycle checks completed.\n";
