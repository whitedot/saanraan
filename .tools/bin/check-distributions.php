#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$distRoot = $root . '/dist';
$expectedVersion = (string) ($argv[1] ?? '');

if ($expectedVersion !== '' && preg_match('/\A(?:dev|\d{4}\.\d{2}\.\d{3})\z/', $expectedVersion) !== 1) {
    fwrite(STDERR, "Usage: php .tools/bin/check-distributions.php [dev|YYYY.MM.NNN]\n");
    exit(1);
}

$errors = [];

function toy_distribution_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function toy_distribution_read_json(string $path): array
{
    $content = file_get_contents($path);
    if (!is_string($content)) {
        toy_distribution_error('Cannot read JSON file: ' . $path);
        return [];
    }

    $decoded = json_decode($content, true);
    if (!is_array($decoded)) {
        toy_distribution_error('Invalid JSON file: ' . $path);
        return [];
    }

    return $decoded;
}

function toy_distribution_read_policy(string $root): array
{
    $policy = toy_distribution_read_json($root . '/docs/distributions.json');
    if (!is_array($policy['packages'] ?? null)) {
        toy_distribution_error('Distribution policy packages are missing.');
        return [
            'packages' => [],
            'default_optional_modules' => [],
        ];
    }

    $packages = [];
    foreach ($policy['packages'] as $packageKey => $modules) {
        if (!is_string($packageKey) || !is_array($modules)) {
            toy_distribution_error('Distribution policy package entry is invalid.');
            continue;
        }

        $moduleKeys = [];
        foreach ($modules as $moduleKey) {
            if (!is_string($moduleKey) || preg_match('/\A[a-z0-9_]+\z/', $moduleKey) !== 1) {
                toy_distribution_error('Distribution policy module key is invalid: ' . (string) $moduleKey);
                continue;
            }

            $moduleKeys[] = $moduleKey;
        }

        $packages['toycore-' . $packageKey] = $moduleKeys;
    }

    $defaultOptionalModules = [];
    if (!is_array($policy['default_optional_modules'] ?? null)) {
        toy_distribution_error('Distribution policy default optional modules are missing.');
    } else {
        foreach ($policy['default_optional_modules'] as $moduleKey) {
            if (!is_string($moduleKey) || preg_match('/\A[a-z0-9_]+\z/', $moduleKey) !== 1) {
                toy_distribution_error('Distribution policy default optional module key is invalid: ' . (string) $moduleKey);
                continue;
            }

            $defaultOptionalModules[] = $moduleKey;
        }
    }

    foreach (['toycore-minimal', 'toycore-standard', 'toycore-ops'] as $packageName) {
        if (!array_key_exists($packageName, $packages)) {
            toy_distribution_error('Distribution policy package is missing: ' . $packageName);
        }
    }

    return [
        'packages' => $packages,
        'default_optional_modules' => $defaultOptionalModules,
    ];
}

function toy_distribution_module_version(string $moduleDir): string
{
    $moduleFile = $moduleDir . '/module.php';
    if (!is_file($moduleFile)) {
        return '';
    }

    $metadata = include $moduleFile;
    if (!is_array($metadata)) {
        return '';
    }

    return (string) ($metadata['version'] ?? '');
}

function toy_distribution_install_array_block(string $content, string $variableName): string
{
    $start = strpos($content, '$' . $variableName . ' = [');
    if ($start === false) {
        toy_distribution_error('Install array is missing: ' . $variableName);
        return '';
    }

    $end = strpos($content, "];", $start);
    if ($end === false) {
        toy_distribution_error('Install array is not closed: ' . $variableName);
        return '';
    }

    return substr($content, $start, $end - $start);
}

function toy_distribution_install_optional_modules(string $root): array
{
    $installAction = $root . '/core/actions/install.php';
    $content = file_get_contents($installAction);
    if (!is_string($content)) {
        toy_distribution_error('Install action cannot be read: ' . $installAction);
        return [];
    }

    $block = toy_distribution_install_array_block($content, 'optionalModules');
    preg_match_all("/'([a-z0-9_]+)'\\s*=>\\s*\\[/", $block, $matches);
    return $matches[1] ?? [];
}

function toy_distribution_validate_common_files(string $packageRoot): void
{
    foreach ([
        'README.md',
        'index.php',
        'assets/toycore.css',
        'config/.gitignore',
        'core',
        'database',
        'docs/module-index.json',
        'docs/shared-hosting-install.md',
        'modules/member/module.php',
        'modules/admin/module.php',
    ] as $path) {
        if (!file_exists($packageRoot . '/' . $path)) {
            toy_distribution_error('Distribution file is missing: ' . $packageRoot . '/' . $path);
        }
    }
}

function toy_distribution_validate_manifest(string $packageName, string $packageRoot, array $expectedModules, string $expectedVersion): void
{
    $manifestPath = $packageRoot . '/distribution-manifest.json';
    if (!is_file($manifestPath)) {
        toy_distribution_error('Distribution manifest is missing: ' . $manifestPath);
        return;
    }

    $manifest = toy_distribution_read_json($manifestPath);
    if ((string) ($manifest['package'] ?? '') !== $packageName) {
        toy_distribution_error('Distribution manifest package mismatch: ' . $manifestPath);
    }

    $manifestVersion = (string) ($manifest['version'] ?? '');
    if ($expectedVersion !== '' && $manifestVersion !== $expectedVersion) {
        toy_distribution_error('Distribution manifest version mismatch: ' . $manifestPath);
    }

    if (!is_array($manifest['modules'] ?? null)) {
        toy_distribution_error('Distribution manifest modules are missing: ' . $manifestPath);
        return;
    }

    $manifestModules = [];
    foreach ($manifest['modules'] as $module) {
        if (!is_array($module)) {
            continue;
        }

        $manifestModules[(string) ($module['module_key'] ?? '')] = (string) ($module['version'] ?? '');
    }

    if (array_keys($manifestModules) !== $expectedModules) {
        toy_distribution_error('Distribution manifest module list mismatch: ' . $manifestPath);
    }

    foreach ($expectedModules as $moduleKey) {
        $moduleDir = $packageRoot . '/modules/' . $moduleKey;
        if (!is_dir($moduleDir)) {
            toy_distribution_error('Distribution module is missing: ' . $moduleDir);
            continue;
        }

        $codeVersion = toy_distribution_module_version($moduleDir);
        if ($codeVersion === '') {
            toy_distribution_error('Distribution module version is missing: ' . $moduleDir);
            continue;
        }

        if (($manifestModules[$moduleKey] ?? '') !== $codeVersion) {
            toy_distribution_error('Distribution manifest module version mismatch: ' . $moduleDir);
        }
    }

    $moduleRoot = $packageRoot . '/modules';
    $actualModuleDirs = [];
    foreach (new DirectoryIterator($moduleRoot) as $entry) {
        if ($entry->isDot() || !$entry->isDir()) {
            continue;
        }

        $actualModuleDirs[] = $entry->getFilename();
    }
    sort($actualModuleDirs, SORT_STRING);

    $sortedExpectedModules = $expectedModules;
    sort($sortedExpectedModules, SORT_STRING);
    if ($actualModuleDirs !== $sortedExpectedModules) {
        toy_distribution_error('Distribution modules directory list mismatch: ' . $packageRoot);
    }
}

function toy_distribution_validate_install_sets(array $packages, array $installOptionalModules, array $policyDefaultOptionalModules): void
{
    if (!isset($packages['toycore-standard'], $packages['toycore-ops'])) {
        toy_distribution_error('Distribution policy must define standard and ops packages.');
        return;
    }

    $standardOptionalModules = array_values(array_diff($packages['toycore-standard'], ['member', 'admin']));
    $opsOptionalModules = array_values(array_diff($packages['toycore-ops'], ['member', 'admin']));

    if ($standardOptionalModules !== $policyDefaultOptionalModules) {
        toy_distribution_error('Standard package modules must match distribution policy default optional modules.');
    }

    if ($opsOptionalModules !== $installOptionalModules) {
        toy_distribution_error('Ops package modules must match all optional install modules.');
    }
}

if (!is_dir($distRoot)) {
    fwrite(STDERR, "dist directory does not exist. Run ./.tools/bin/package-distributions first.\n");
    exit(1);
}

$installOptionalModules = toy_distribution_install_optional_modules($root);
$policy = toy_distribution_read_policy($root);
$packages = $policy['packages'];
toy_distribution_validate_install_sets($packages, $installOptionalModules, $policy['default_optional_modules']);

foreach ($packages as $packageName => $expectedModules) {
    $packageRoot = $distRoot . '/' . $packageName;
    if (!is_dir($packageRoot)) {
        toy_distribution_error('Distribution directory is missing: ' . $packageRoot);
        continue;
    }

    toy_distribution_validate_common_files($packageRoot);
    toy_distribution_validate_manifest($packageName, $packageRoot, $expectedModules, $expectedVersion);
}

if ($errors !== []) {
    fwrite(STDERR, "toycore distribution checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "toycore distribution checks completed.\n";
