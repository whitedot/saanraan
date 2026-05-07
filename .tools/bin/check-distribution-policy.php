#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

function toy_distribution_policy_error(array &$errors, string $message): void
{
    $errors[] = $message;
}

function toy_distribution_policy_read_json(array &$errors, string $path): array
{
    $content = file_get_contents($path);
    if (!is_string($content)) {
        toy_distribution_policy_error($errors, 'Cannot read distribution policy: ' . $path);
        return [];
    }

    $decoded = json_decode($content, true);
    if (!is_array($decoded)) {
        toy_distribution_policy_error($errors, 'Invalid distribution policy JSON: ' . $path);
        return [];
    }

    return $decoded;
}

function toy_distribution_policy_module_keys(array &$errors, array $values, string $label): array
{
    $moduleKeys = [];
    foreach ($values as $value) {
        if (!is_string($value) || preg_match('/\A[a-z0-9_]+\z/', $value) !== 1) {
            toy_distribution_policy_error($errors, 'Invalid module key in ' . $label . ': ' . (string) $value);
            continue;
        }

        if (in_array($value, $moduleKeys, true)) {
            toy_distribution_policy_error($errors, 'Duplicate module key in ' . $label . ': ' . $value);
            continue;
        }

        $moduleKeys[] = $value;
    }

    return $moduleKeys;
}

function toy_distribution_policy_install_optional_modules(array &$errors, string $root): array
{
    $installAction = $root . '/core/actions/install.php';
    $content = file_get_contents($installAction);
    if (!is_string($content)) {
        toy_distribution_policy_error($errors, 'Install action cannot be read: ' . $installAction);
        return [];
    }

    $start = strpos($content, '$optionalModules = [');
    if ($start === false) {
        toy_distribution_policy_error($errors, 'Install optional module array is missing.');
        return [];
    }

    $end = strpos($content, '];', $start);
    if ($end === false) {
        toy_distribution_policy_error($errors, 'Install optional module array is not closed.');
        return [];
    }

    $block = substr($content, $start, $end - $start);
    preg_match_all("/'([a-z0-9_]+)'\\s*=>\\s*\\[/", $block, $matches);
    return $matches[1] ?? [];
}

$policy = toy_distribution_policy_read_json($errors, $root . '/docs/distributions.json');
$packages = [];
if (!is_array($policy['packages'] ?? null)) {
    toy_distribution_policy_error($errors, 'Distribution policy packages are missing.');
} else {
    foreach ($policy['packages'] as $packageKey => $moduleKeys) {
        if (!is_string($packageKey) || preg_match('/\A[a-z0-9_]+\z/', $packageKey) !== 1 || !is_array($moduleKeys)) {
            toy_distribution_policy_error($errors, 'Invalid distribution package entry.');
            continue;
        }

        $packages[$packageKey] = toy_distribution_policy_module_keys($errors, $moduleKeys, 'packages.' . $packageKey);
    }
}

$defaultOptionalModules = [];
if (!is_array($policy['default_optional_modules'] ?? null)) {
    toy_distribution_policy_error($errors, 'Distribution policy default optional modules are missing.');
} else {
    $defaultOptionalModules = toy_distribution_policy_module_keys($errors, $policy['default_optional_modules'], 'default_optional_modules');
}

foreach (['minimal', 'standard', 'ops'] as $packageKey) {
    if (!isset($packages[$packageKey])) {
        toy_distribution_policy_error($errors, 'Distribution package is missing: ' . $packageKey);
    }
}

foreach ($packages as $packageKey => $moduleKeys) {
    foreach (['member', 'admin'] as $requiredModuleKey) {
        if (!in_array($requiredModuleKey, $moduleKeys, true)) {
            toy_distribution_policy_error($errors, 'Distribution package must include ' . $requiredModuleKey . ': ' . $packageKey);
        }
    }
}

if (isset($packages['standard'])) {
    $standardOptionalModules = array_values(array_diff($packages['standard'], ['member', 'admin']));
    if ($standardOptionalModules !== $defaultOptionalModules) {
        toy_distribution_policy_error($errors, 'Default optional modules must match standard optional modules.');
    }
}

$installOptionalModules = toy_distribution_policy_install_optional_modules($errors, $root);
if (isset($packages['ops'])) {
    $opsOptionalModules = array_values(array_diff($packages['ops'], ['member', 'admin']));
    if ($opsOptionalModules !== $installOptionalModules) {
        toy_distribution_policy_error($errors, 'Ops optional modules must match install optional module list.');
    }
}

if ($errors !== []) {
    fwrite(STDERR, "toycore distribution policy checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "toycore distribution policy checks completed.\n";
