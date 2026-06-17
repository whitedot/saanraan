#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}
chdir($root);

require_once SR_ROOT . '/core/version.php';
require_once SR_ROOT . '/core/helpers/settings.php';
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

function sr_check_module_source_policy_create_test_zip(string $root, string $zipPath): void
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        throw new RuntimeException('Cannot create fixture zip.');
    }

    foreach ([
        'pkg/.env.local',
        'pkg/module/module.php',
        'pkg/module/install.sql',
    ] as $relative) {
        $zip->addFile($root . '/' . $relative, $relative);
    }

    $zip->close();
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

    $packageDir = $fixtureRoot . '/package';
    mkdir($packageDir . '/module/assets', 0777, true);
    foreach ([
        '.env.local',
        'module/module.php',
        'module/install.sql',
        'module/assets/.gitkeep',
    ] as $relative) {
        sr_check_module_source_policy_write_file($packageDir, $relative);
    }

    $packageErrors = sr_module_source_file_errors($packageDir);
    $foundPackageRootEnv = false;
    foreach ($packageErrors as $error) {
        if (str_contains($error, '.env.local')) {
            $foundPackageRootEnv = true;
            break;
        }
    }

    if (!$foundPackageRootEnv) {
        fwrite(STDERR, "Package root forbidden file was not rejected:\n" . implode("\n", $packageErrors) . "\n");
        exit(1);
    }

    foreach ($packageErrors as $error) {
        if (str_contains($error, '.gitkeep')) {
            fwrite(STDERR, "Allowed placeholder file was rejected:\n" . implode("\n", $packageErrors) . "\n");
            exit(1);
        }
    }

    if (!class_exists('ZipArchive')) {
        fwrite(STDERR, "ZipArchive is required for module source policy checks.\n");
        exit(1);
    }

    $uploadDir = $fixtureRoot . '/upload';
    mkdir($uploadDir . '/pkg/module', 0777, true);
    sr_check_module_source_policy_write_file($uploadDir, 'pkg/.env.local');
    sr_check_module_source_policy_write_file(
        $uploadDir,
        'pkg/module/module.php'
    );
    file_put_contents(
        $uploadDir . '/pkg/module/module.php',
        "<?php\nreturn [\n"
        . "    'name' => 'Test Module',\n"
        . "    'version' => '2026.06.001',\n"
        . "    'type' => 'module',\n"
        . "    'saanraan' => [\n"
        . "        'min_version' => '0.2.0',\n"
        . "        'tested_with' => ['0.2.0'],\n"
        . "        'module_contract' => '2.0',\n"
        . "    ],\n"
        . "];\n"
    );
    sr_check_module_source_policy_write_file($uploadDir, 'pkg/module/install.sql');

    $zipPath = $fixtureRoot . '/testmod-2026.06.001.zip';
    sr_check_module_source_policy_create_test_zip($uploadDir, $zipPath);

    $acceptedUpload = false;
    $uploadResult = [];
    try {
        $uploadResult = sr_extract_module_upload([
            'error' => UPLOAD_ERR_OK,
            'name' => basename($zipPath),
            'size' => filesize($zipPath),
            'tmp_name' => $zipPath,
        ], 'testmod');
        $acceptedUpload = true;
    } catch (Throwable $exception) {
        if (!str_contains($exception->getMessage(), '.env.local')) {
            fwrite(STDERR, 'Upload fixture failed for the wrong reason: ' . $exception->getMessage() . "\n");
            exit(1);
        }
    } finally {
        if (is_array($uploadResult) && is_string($uploadResult['extract_dir'] ?? null) && is_dir($uploadResult['extract_dir'])) {
            sr_remove_directory($uploadResult['extract_dir']);
        }
    }

    if ($acceptedUpload) {
        fwrite(STDERR, "Upload fixture with a package-root forbidden file was accepted.\n");
        exit(1);
    }
} finally {
    sr_check_module_source_policy_remove_dir($fixtureRoot);
}

echo "module source file policy checks completed.\n";
