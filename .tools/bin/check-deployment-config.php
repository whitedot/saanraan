#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$configFile = $root . '/config/config.php';
$errors = [];
$warnings = [];

function sr_deployment_config_file_mode(string $path): string
{
    if (!file_exists($path)) {
        return '-';
    }

    $mode = fileperms($path);
    if ($mode === false) {
        return 'unknown';
    }

    return sprintf('%04o', $mode & 0777);
}

function sr_deployment_config_user_name(int $id): string
{
    if (function_exists('posix_getpwuid')) {
        $info = posix_getpwuid($id);
        if (is_array($info) && is_string($info['name'] ?? null) && $info['name'] !== '') {
            return $info['name'];
        }
    }

    return (string) $id;
}

function sr_deployment_config_group_name(int $id): string
{
    if (function_exists('posix_getgrgid')) {
        $info = posix_getgrgid($id);
        if (is_array($info) && is_string($info['name'] ?? null) && $info['name'] !== '') {
            return $info['name'];
        }
    }

    return (string) $id;
}

function sr_deployment_config_file_owner_group(string $path): string
{
    if (!file_exists($path)) {
        return '-';
    }

    $owner = fileowner($path);
    $group = filegroup($path);
    if ($owner === false || $group === false) {
        return 'unknown';
    }

    return sr_deployment_config_user_name($owner) . ':' . sr_deployment_config_group_name($group);
}

if (!is_file($configFile)) {
    echo "config/config.php is not present; deployment config check skipped.\n";
    exit(0);
}

$perms = fileperms($configFile);
if (!is_int($perms)) {
    $errors[] = 'config/config.php permissions cannot be read.';
} else {
    $mode = $perms & 0777;
    if (($mode & 0077) !== 0) {
        $errors[] = sprintf('config/config.php must not be readable or writable by group/other; current mode is %04o.', $mode);
    }
}

if (!is_readable($configFile)) {
    if ($errors !== []) {
        foreach ($errors as $error) {
            fwrite(STDERR, $error . "\n");
        }
        exit(1);
    }

    echo 'config-mode: ' . sr_deployment_config_file_mode($configFile) . "\n";
    echo 'config-owner-group: ' . sr_deployment_config_file_owner_group($configFile) . "\n";
    echo "config/config.php is not readable by current user; permission check passed and content check skipped.\n";
    exit(0);
}

$config = include $configFile;
if (!is_array($config)) {
    $errors[] = 'config/config.php must return an array.';
} else {
    $db = isset($config['db']) && is_array($config['db']) ? $config['db'] : [];
    $password = (string) ($db['password'] ?? '');
    $passwordEnv = (string) ($db['password_env'] ?? '');
    $envAvailable = $passwordEnv !== '' && getenv($passwordEnv) !== false;
    if ($password === '' && !$envAvailable) {
        $errors[] = $passwordEnv === ''
            ? 'DB password is not configured. Set db.password_env or db.password.'
            : 'Configured DB password environment variable is not available and db.password fallback is empty: ' . $passwordEnv;
    }
    if ($password !== '' && $passwordEnv === '') {
        $warnings[] = 'config/config.php stores db.password directly. This is acceptable for shared hosting only when config/ is blocked from web access and config/config.php is mode 600 where possible.';
    }
    if ($password !== '' && $passwordEnv !== '' && !$envAvailable) {
        $warnings[] = 'Configured DB password environment variable is not available; db.password fallback will be used.';
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . "\n");
    }
    exit(1);
}

foreach ($warnings as $warning) {
    fwrite(STDERR, 'Warning: ' . $warning . "\n");
}

echo 'config-mode: ' . sr_deployment_config_file_mode($configFile) . "\n";
echo 'config-owner-group: ' . sr_deployment_config_file_owner_group($configFile) . "\n";
echo "Deployment config check passed.\n";
