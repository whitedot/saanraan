#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}
chdir($root);

require_once SR_ROOT . '/core/helpers/module-source.php';

function sr_check_module_source_policy_remove_dir(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($directory);
}

function sr_check_module_source_policy_write_file(string $root, string $relative): void
{
    $path = $root . '/' . $relative;
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create fixture directory: ' . $directory);
    }

    file_put_contents($path, 'fixture');
}

$fixtureRoot = sys_get_temp_dir() . '/sr-module-source-policy-' . bin2hex(random_bytes(6));
mkdir($fixtureRoot, 0777, true);

try {
    $allowedDir = $fixtureRoot . '/allowed';
    mkdir($allowedDir, 0777, true);
    foreach (['module.php', 'install.sql', 'assets/app.js', 'views/page.html', 'assets/style.css'] as $relative) {
        sr_check_module_source_policy_write_file($allowedDir, $relative);
    }

    $allowedErrors = sr_module_source_file_errors($allowedDir);
    if ($allowedErrors !== []) {
        fwrite(STDERR, "Allowed module source fixture was rejected:\n" . implode("\n", $allowedErrors) . "\n");
        exit(1);
    }

    $blockedDir = $fixtureRoot . '/blocked';
    mkdir($blockedDir, 0777, true);
    $blockedFiles = [
        '.env.local',
        '.gitignore',
        '.npmrc',
        'composer/auth.json',
        'keys/id_rsa',
        'certs/client.p12',
        'data/export.sqlite',
        'backup/module.bak',
        'actions/route.php7',
        'actions/hook.pht',
        'actions/template.phtml',
        'bin/task.sh',
    ];
    foreach ($blockedFiles as $relative) {
        sr_check_module_source_policy_write_file($blockedDir, $relative);
    }

    $blockedErrors = sr_module_source_file_errors($blockedDir);
    foreach ($blockedFiles as $relative) {
        $found = false;
        foreach ($blockedErrors as $error) {
            if (str_contains($error, $relative)) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            fwrite(STDERR, 'Blocked module source fixture was not rejected: ' . $relative . "\n");
            fwrite(STDERR, implode("\n", $blockedErrors) . "\n");
            exit(1);
        }
    }
} finally {
    sr_check_module_source_policy_remove_dir($fixtureRoot);
}

echo "module source file policy checks completed.\n";
