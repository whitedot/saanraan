#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$configFile = $root . '/config/config.php';
$errors = [];
$warnings = [];

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

echo "Deployment config check passed.\n";
