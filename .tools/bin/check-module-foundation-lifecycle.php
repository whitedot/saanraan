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

foreach (glob(SR_ROOT . '/modules/*/module.php') ?: [] as $moduleFile) {
    $moduleKey = basename(dirname($moduleFile));
    if (!preg_match('/\A[a-z0-9_]+\z/', $moduleKey)) {
        continue;
    }

    $metadata = sr_module_metadata($moduleKey);
    $metadataRequiredModuleKeys = sr_module_required_module_keys_from_metadata($metadata);
    $helperFoundationKeys = sr_module_foundation_dependencies($moduleKey);

    foreach ($helperFoundationKeys as $foundationModuleKey) {
        sr_module_foundation_lifecycle_assert(
            sr_module_is_foundation($foundationModuleKey),
            $moduleKey . ' foundation helper references a non-foundation module: ' . $foundationModuleKey
        );
        sr_module_foundation_lifecycle_assert(
            in_array($foundationModuleKey, $metadataRequiredModuleKeys, true),
            $moduleKey . ' foundation helper dependency must also be declared in module.php requires.modules: ' . $foundationModuleKey
        );
    }

    foreach (array_intersect($metadataRequiredModuleKeys, sr_foundation_module_keys()) as $foundationModuleKey) {
        sr_module_foundation_lifecycle_assert(
            in_array($foundationModuleKey, $helperFoundationKeys, true),
            $moduleKey . ' module.php foundation dependency must also be known to sr_module_foundation_dependencies: ' . $foundationModuleKey
        );
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
sr_module_foundation_lifecycle_assert($paymentDependents === ['content'], 'payment_ledger disable guard must include enabled payment-recording dependents only.');

$assetDisableErrors = sr_module_disable_errors($pdo, 'asset_ledger');
sr_module_foundation_lifecycle_assert($assetDisableErrors !== [] && str_contains($assetDisableErrors[0], 'point'), 'asset_ledger disable errors must block active dependent modules.');

$paymentDisableErrors = sr_module_disable_errors($pdo, 'payment_ledger');
sr_module_foundation_lifecycle_assert(
    $paymentDisableErrors !== []
    && str_contains($paymentDisableErrors[0], 'content')
    && !str_contains($paymentDisableErrors[0], 'coupon'),
    'payment_ledger disable errors must block active dependent modules.'
);

sr_module_foundation_lifecycle_assert(sr_module_disable_errors($pdo, 'content') === [], 'non-foundation modules must not use foundation disable guard.');

$pdo->exec("UPDATE sr_modules SET status = 'disabled' WHERE module_key = 'content'");
sr_module_foundation_lifecycle_assert(sr_module_disable_errors($pdo, 'payment_ledger') === [], 'payment_ledger disable guard must allow disabling after dependents are disabled.');

$adminModuleActions = (string) file_get_contents(SR_ROOT . '/modules/admin/helpers/module-actions.php');
$adminModulesView = (string) file_get_contents(SR_ROOT . '/modules/admin/views/modules.php');
sr_module_foundation_lifecycle_assert(
    str_contains($adminModuleActions, 'sr_enabled_modules_requiring_foundation')
    && !str_contains($adminModuleActions, 'sr_enabled_asset_modules_requiring_foundation'),
    'admin module actions must use the generic foundation dependent lookup.'
);
sr_module_foundation_lifecycle_assert(
    str_contains($adminModulesView, '활성 의존 모듈')
    && !str_contains($adminModulesView, '활성 자산 모듈('),
    'admin module status modal must describe generic foundation dependents.'
);

if ($errors !== []) {
    fwrite(STDERR, "module foundation lifecycle checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "module foundation lifecycle checks completed.\n";
