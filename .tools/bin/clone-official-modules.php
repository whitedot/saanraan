#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$targetRoot = (string) ($argv[1] ?? (getenv('TOYCORE_MODULE_REPO_ROOT') ?: dirname($root)));
$sharedRef = trim((string) (getenv('TOYCORE_MODULE_REF') ?: ''));
$indexPath = (string) (getenv('TOYCORE_MODULE_INDEX_PATH') ?: ($root . '/docs/module-index.json'));
$dryRun = (string) getenv('TOYCORE_CLONE_OFFICIAL_MODULES_DRY_RUN') === '1';

if ($targetRoot !== '' && !str_starts_with($targetRoot, '/') && preg_match('/\A[A-Za-z]:[\/\\\\]/', $targetRoot) !== 1) {
    $targetRoot = $root . '/' . $targetRoot;
}

if ($indexPath !== '' && !str_starts_with($indexPath, '/') && preg_match('/\A[A-Za-z]:[\/\\\\]/', $indexPath) !== 1) {
    $indexPath = $root . '/' . $indexPath;
}

function toy_clone_official_modules_fail(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function toy_clone_official_modules_safe_ref(string $ref): bool
{
    return $ref !== ''
        && strlen($ref) <= 120
        && !str_contains($ref, '..')
        && !str_starts_with($ref, '/')
        && !str_ends_with($ref, '/')
        && !str_contains($ref, '//')
        && preg_match('/\A[A-Za-z0-9._\/-]+\z/', $ref) === 1;
}

function toy_clone_official_modules_run(array $command, bool $dryRun): void
{
    $parts = [];
    foreach ($command as $part) {
        $parts[] = escapeshellarg((string) $part);
    }

    $commandText = implode(' ', $parts);
    if ($dryRun) {
        echo 'DRY RUN: ' . $commandText . "\n";
        return;
    }

    passthru($commandText, $exitCode);
    if ($exitCode !== 0) {
        toy_clone_official_modules_fail('Command failed: ' . $commandText);
    }
}

function toy_clone_official_modules_ref(array $entry, string $sharedRef): string
{
    if ($sharedRef !== '') {
        return $sharedRef;
    }

    $latestVersion = is_string($entry['latest_version'] ?? null) ? (string) $entry['latest_version'] : '';
    if ($latestVersion !== '' && preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $latestVersion) === 1) {
        return 'v' . $latestVersion;
    }

    return '';
}

if (!is_dir($targetRoot) && !mkdir($targetRoot, 0755, true)) {
    toy_clone_official_modules_fail('Module repository root cannot be created: ' . $targetRoot);
}

$content = file_get_contents($indexPath);
if (!is_string($content)) {
    toy_clone_official_modules_fail('Module index cannot be read: ' . $indexPath);
}

$index = json_decode($content, true);
if (!is_array($index) || !is_array($index['modules'] ?? null)) {
    toy_clone_official_modules_fail('Module index JSON is invalid: ' . $indexPath);
}

foreach ($index['modules'] as $entry) {
    if (!is_array($entry)) {
        continue;
    }

    $moduleKey = is_string($entry['module_key'] ?? null) ? (string) $entry['module_key'] : '';
    $repository = is_string($entry['repository'] ?? null) ? (string) $entry['repository'] : '';
    if (preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $moduleKey) !== 1 || $repository === '') {
        continue;
    }

    $expectedRepository = 'https://github.com/whitedot/toycore-module-' . str_replace('_', '-', $moduleKey);
    if ($repository !== $expectedRepository) {
        toy_clone_official_modules_fail('Module repository mismatch: ' . $moduleKey);
    }

    $target = rtrim($targetRoot, "/\\") . '/toycore-module-' . str_replace('_', '-', $moduleKey);
    if (!is_dir($target . '/.git')) {
        toy_clone_official_modules_run(['git', 'clone', $repository, $target], $dryRun);
    } else {
        toy_clone_official_modules_run(['git', '-C', $target, 'fetch', '--tags', 'origin'], $dryRun);
    }

    $ref = toy_clone_official_modules_ref($entry, $sharedRef);
    if ($ref === '') {
        echo 'Using default branch for ' . $moduleKey . "\n";
        continue;
    }

    if (!toy_clone_official_modules_safe_ref($ref)) {
        toy_clone_official_modules_fail('Unsafe module ref: ' . $moduleKey . ' ' . $ref);
    }

    toy_clone_official_modules_run(['git', '-C', $target, 'fetch', '--tags', 'origin', $ref], $dryRun);
    toy_clone_official_modules_run(['git', '-C', $target, 'checkout', '--detach', $ref], $dryRun);
}

echo "official module repositories are ready under " . $targetRoot . "\n";
