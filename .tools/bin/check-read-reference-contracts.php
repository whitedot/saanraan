#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

define('SR_ROOT', $root);
require_once 'core/version.php';
require_once 'core/helpers.php';

$errors = [];

function sr_read_reference_check_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_read_reference_check_module_dirs(): array
{
    $dirs = [];
    foreach (new DirectoryIterator('modules') as $entry) {
        if ($entry->isDot() || !$entry->isDir()) {
            continue;
        }
        $dirs[] = $entry->getPathname();
    }
    sort($dirs);

    return $dirs;
}

function sr_read_reference_check_metadata(string $moduleDir): array
{
    $moduleFile = $moduleDir . '/module.php';
    if (!is_file($moduleFile)) {
        return [];
    }

    $metadata = include $moduleFile;
    return is_array($metadata) ? $metadata : [];
}

function sr_read_reference_check_declared(array $metadata, string $key): array
{
    $contracts = is_array($metadata['contracts'] ?? null) ? $metadata['contracts'] : [];
    $files = is_array($contracts[$key] ?? null) ? $contracts[$key] : [];

    return array_values(array_filter(array_map('strval', $files)));
}

function sr_read_reference_check_entries(array $contract): array
{
    if (isset($contract['count_function']) || isset($contract['rows_function'])) {
        return [$contract];
    }

    return $contract;
}

$readReferenceFiles = array_keys(sr_read_reference_contract_files());
$expectedConsumers = [
    'coupon' => ['coupon-references.php'],
    'banner' => ['banner-references.php'],
    'popup_layer' => ['popup-layer-references.php'],
    'member' => ['member-group-references.php'],
    'admin' => ['site-setting-references.php'],
];

foreach (sr_read_reference_check_module_dirs() as $moduleDir) {
    $moduleKey = basename($moduleDir);
    $metadata = sr_read_reference_check_metadata($moduleDir);
    $provides = sr_read_reference_check_declared($metadata, 'provides');
    $consumes = sr_read_reference_check_declared($metadata, 'consumes');

    foreach ($readReferenceFiles as $contractFile) {
        $path = $moduleDir . '/' . $contractFile;
        if (!is_file($path)) {
            continue;
        }

        if (!in_array($contractFile, $provides, true)) {
            sr_read_reference_check_error('read reference provider must declare contracts.provides: ' . $path);
        }

        $contract = include $path;
        if (!is_array($contract)) {
            sr_read_reference_check_error('read reference contract must return array: ' . $path);
            continue;
        }

        foreach (sr_read_reference_check_entries($contract) as $entry) {
            if (!is_array($entry)) {
                sr_read_reference_check_error('read reference entry must be array: ' . $path);
                continue;
            }

            if ((string) ($entry['consumer_module_key'] ?? '') !== $moduleKey) {
                sr_read_reference_check_error('read reference consumer_module_key must match module: ' . $path);
            }

            foreach (['label', 'reference_type', 'count_function', 'rows_function', 'health_function', 'admin_url_function'] as $requiredKey) {
                if (!is_string($entry[$requiredKey] ?? null) || trim((string) $entry[$requiredKey]) === '') {
                    sr_read_reference_check_error('read reference entry requires ' . $requiredKey . ': ' . $path);
                }
            }

            $helpers = $entry['helpers'] ?? [];
            if (is_string($helpers) && $helpers !== '') {
                $helpers = [$helpers];
            }
            if (!is_array($helpers)) {
                sr_read_reference_check_error('read reference helpers must be string or array: ' . $path);
                continue;
            }
            foreach ($helpers as $helper) {
                $helper = (string) $helper;
                if (preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helper) !== 1 || !is_file($moduleDir . '/' . $helper)) {
                    sr_read_reference_check_error('read reference helper is invalid or missing: ' . $path . ' ' . $helper);
                    continue;
                }
                require_once $moduleDir . '/' . $helper;
            }

            foreach (['count_function', 'rows_function', 'health_function', 'admin_url_function'] as $functionKey) {
                $functionName = (string) ($entry[$functionKey] ?? '');
                if ($functionName !== '' && !function_exists($functionName)) {
                    sr_read_reference_check_error('read reference callable does not exist: ' . $path . ' ' . $functionName);
                }
            }
        }
    }

    if (is_file($moduleDir . '/coupon-targets.php')) {
        $contract = include $moduleDir . '/coupon-targets.php';
        if (!is_array($contract)) {
            sr_read_reference_check_error('coupon-targets.php must return array: ' . $moduleDir);
            continue;
        }
        foreach ($contract as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            foreach (['health_function', 'admin_url_function'] as $optionalKey) {
                if (!isset($entry[$optionalKey]) || (string) $entry[$optionalKey] === '') {
                    continue;
                }
                $functionName = (string) $entry[$optionalKey];
                $helpers = (string) ($entry['helpers'] ?? '');
                if ($helpers !== '' && preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helpers) === 1 && is_file($moduleDir . '/' . $helpers)) {
                    require_once $moduleDir . '/' . $helpers;
                }
                if (!function_exists($functionName)) {
                    sr_read_reference_check_error('coupon-targets optional callable does not exist: ' . $moduleDir . ' ' . $functionName);
                }
            }
        }
    }
}

foreach ($expectedConsumers as $moduleKey => $contractFiles) {
    $metadata = sr_read_reference_check_metadata('modules/' . $moduleKey);
    $consumes = sr_read_reference_check_declared($metadata, 'consumes');
    foreach ($contractFiles as $contractFile) {
        if (!in_array($contractFile, $consumes, true)) {
            sr_read_reference_check_error('read reference owner must declare contracts.consumes: modules/' . $moduleKey . '/module.php ' . $contractFile);
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "read reference contract checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "read reference contract checks completed.\n";
