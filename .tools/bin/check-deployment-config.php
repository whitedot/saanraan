#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$configFile = $root . '/config/config.php';
$errors = [];

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

$config = include $configFile;
if (!is_array($config)) {
    $errors[] = 'config/config.php must return an array.';
} else {
    $db = isset($config['db']) && is_array($config['db']) ? $config['db'] : [];
    $password = (string) ($db['password'] ?? '');
    $passwordEnv = (string) ($db['password_env'] ?? '');
    if ($password !== '' && $passwordEnv === '') {
        $errors[] = 'config/config.php stores db.password without db.password_env fallback.';
    }
    if ($password === '' && $passwordEnv !== '' && getenv($passwordEnv) === false) {
        $errors[] = 'Configured DB password environment variable is not available and db.password fallback is empty: ' . $passwordEnv;
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . "\n");
    }
    exit(1);
}

echo "Deployment config check passed.\n";
