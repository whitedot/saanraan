#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$indexPath = (string) ($argv[1] ?? ($root . '/docs/module-index.json'));
if ($indexPath !== '' && !str_starts_with($indexPath, '/') && preg_match('/\A[A-Za-z]:[\/\\\\]/', $indexPath) !== 1) {
    $indexPath = $root . '/' . $indexPath;
}
$errors = [];

function toy_module_index_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function toy_module_index_string(array $entry, string $key, string $moduleKey): string
{
    if (!array_key_exists($key, $entry) || !is_string($entry[$key])) {
        toy_module_index_error('module-index field must be a string: ' . ($moduleKey !== '' ? $moduleKey : '(unknown)') . ' ' . $key);
        return '';
    }

    return $entry[$key];
}

function toy_module_index_https_url(string $url): bool
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false
        && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
}

function toy_module_index_safe_repository_ref(string $ref): bool
{
    return $ref !== ''
        && strlen($ref) <= 120
        && !str_contains($ref, '..')
        && !str_starts_with($ref, '/')
        && !str_ends_with($ref, '/')
        && !str_contains($ref, '//')
        && preg_match('/\A[A-Za-z0-9._\/-]+\z/', $ref) === 1;
}

function toy_module_index_safe_module_key(string $moduleKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $moduleKey) === 1;
}

function toy_module_index_validate_entry(array $entry, array &$seenModuleKeys): void
{
    $moduleKey = toy_module_index_string($entry, 'module_key', '');
    if (!toy_module_index_safe_module_key($moduleKey)) {
        toy_module_index_error('module-index module_key is invalid: ' . $moduleKey);
        return;
    }

    if (isset($seenModuleKeys[$moduleKey])) {
        toy_module_index_error('module-index module_key is duplicated: ' . $moduleKey);
    }
    $seenModuleKeys[$moduleKey] = true;

    $name = toy_module_index_string($entry, 'name', $moduleKey);
    if ($name === '') {
        toy_module_index_error('module-index name is empty: ' . $moduleKey);
    }

    $repository = toy_module_index_string($entry, 'repository', $moduleKey);
    $expectedRepository = 'https://github.com/whitedot/toycore-module-' . str_replace('_', '-', $moduleKey);
    if ($repository !== $expectedRepository) {
        toy_module_index_error('module-index repository mismatch: ' . $moduleKey);
    }

    $category = toy_module_index_string($entry, 'category', $moduleKey);
    if (preg_match('/\A[a-z0-9_-]+\z/', $category) !== 1) {
        toy_module_index_error('module-index category is invalid: ' . $moduleKey);
    }

    $latestVersion = toy_module_index_string($entry, 'latest_version', $moduleKey);
    if ($latestVersion !== '' && preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $latestVersion) !== 1) {
        toy_module_index_error('module-index latest_version is invalid: ' . $moduleKey);
    }

    $minToycoreVersion = toy_module_index_string($entry, 'min_toycore_version', $moduleKey);
    if ($minToycoreVersion !== '' && preg_match('/\A(?:v?\d+\.\d+\.\d+|\d{4}\.\d{2}\.\d{3})\z/', $minToycoreVersion) !== 1) {
        toy_module_index_error('module-index min_toycore_version is invalid: ' . $moduleKey);
    }

    $moduleContract = toy_module_index_string($entry, 'module_contract', $moduleKey);
    if ($moduleContract !== '' && preg_match('/\A\d+\.\d+\z/', $moduleContract) !== 1) {
        toy_module_index_error('module-index module_contract is invalid: ' . $moduleKey);
    }

    $zipUrl = toy_module_index_string($entry, 'zip_url', $moduleKey);
    $checksum = toy_module_index_string($entry, 'checksum', $moduleKey);
    if (($zipUrl === '') !== ($checksum === '')) {
        toy_module_index_error('module-index zip_url and checksum must be filled together: ' . $moduleKey);
    }

    if ($zipUrl !== '') {
        if (!toy_module_index_https_url($zipUrl)) {
            toy_module_index_error('module-index zip_url must be an https URL: ' . $moduleKey);
        }

        if ($latestVersion === '') {
            toy_module_index_error('module-index latest_version is required when zip_url is filled: ' . $moduleKey);
        }

        $expectedZipName = $moduleKey . '-' . $latestVersion . '.zip';
        $zipPath = (string) parse_url($zipUrl, PHP_URL_PATH);
        if (basename($zipPath) !== $expectedZipName) {
            toy_module_index_error('module-index zip_url filename mismatch: ' . $moduleKey);
        }
    }

    if ($checksum !== '' && preg_match('/\A[a-f0-9]{64}\z/', $checksum) !== 1) {
        toy_module_index_error('module-index checksum must be lowercase sha256 hex: ' . $moduleKey);
    }

    if (array_key_exists('repository_refs', $entry)) {
        if (!is_array($entry['repository_refs'])) {
            toy_module_index_error('module-index repository_refs must be an object: ' . $moduleKey);
        } else {
            foreach ($entry['repository_refs'] as $ref => $refChecksum) {
                $ref = is_string($ref) ? $ref : '';
                if (!toy_module_index_safe_repository_ref($ref) || preg_match('/\A[a-f0-9]{40}\z/', $ref) !== 1) {
                    toy_module_index_error('module-index repository_refs ref must be a 40-char lowercase commit SHA: ' . $moduleKey);
                }

                if (!is_string($refChecksum) || preg_match('/\A[a-f0-9]{64}\z/', $refChecksum) !== 1) {
                    toy_module_index_error('module-index repository_refs checksum must be lowercase sha256 hex: ' . $moduleKey);
                }
            }
        }
    }
}

if (!is_file($indexPath)) {
    fwrite(STDERR, "module index does not exist: " . $indexPath . "\n");
    exit(1);
}

$content = file_get_contents($indexPath);
if (!is_string($content)) {
    fwrite(STDERR, "module index cannot be read.\n");
    exit(1);
}

$index = json_decode($content, true);
if (!is_array($index) || !is_array($index['modules'] ?? null)) {
    fwrite(STDERR, "module index JSON is invalid.\n");
    exit(1);
}

$seenModuleKeys = [];
$moduleKeys = [];
foreach ($index['modules'] as $entry) {
    if (!is_array($entry)) {
        toy_module_index_error('module-index entry must be an object.');
        continue;
    }

    $moduleKey = is_string($entry['module_key'] ?? null) ? $entry['module_key'] : '';
    $moduleKeys[] = $moduleKey;
    toy_module_index_validate_entry($entry, $seenModuleKeys);
}

$sortedModuleKeys = $moduleKeys;
sort($sortedModuleKeys, SORT_STRING);
if ($moduleKeys !== $sortedModuleKeys) {
    toy_module_index_error('module-index modules must be sorted by module_key.');
}

if ($errors !== []) {
    fwrite(STDERR, "module index checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "module index checks completed.\n";
