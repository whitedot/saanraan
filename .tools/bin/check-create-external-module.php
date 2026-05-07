#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$workRoot = $root . '/storage/check-create-external-module-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$targetDir = $workRoot . '/toycore-module-banner';

function toy_check_create_external_module_remove_directory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $realDirectory = realpath($directory);
    $realStorage = realpath(__DIR__ . '/../../storage');
    if ($realDirectory === false || $realStorage === false || strpos($realDirectory, $realStorage . DIRECTORY_SEPARATOR) !== 0) {
        throw new RuntimeException('Refusing to remove unexpected directory: ' . $directory);
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($realDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($realDirectory);
}

function toy_check_create_external_module_run(string $command): string
{
    $output = [];
    exec($command . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException('Command failed: ' . $command . "\n" . implode("\n", $output));
    }

    return implode("\n", $output);
}

try {
    if (!mkdir($workRoot, 0755, true)) {
        throw new RuntimeException('work directory cannot be created.');
    }

    toy_check_create_external_module_run(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/create-external-module.php') . ' '
        . escapeshellarg('banner') . ' ' . escapeshellarg($targetDir) . ' ' . escapeshellarg('v0.1.1')
    );

    foreach ([
        'README.md',
        'CHANGELOG.md',
        'module/module.php',
        'module/install.sql',
        '.github/workflows/check.yml',
    ] as $path) {
        if (!is_file($targetDir . '/' . $path)) {
            throw new RuntimeException('scaffold file is missing: ' . $path);
        }
    }

    $readme = (string) file_get_contents($targetDir . '/README.md');
    $ci = (string) file_get_contents($targetDir . '/.github/workflows/check.yml');
    if (
        !str_contains($readme, 'Toycore 외부 모듈 `banner`')
        || !str_contains($readme, 'git checkout v0.1.1')
        || !str_contains($ci, 'TOYCORE_MODULE_KEY: banner')
    ) {
        throw new RuntimeException('scaffold templates were not replaced.');
    }

    toy_check_create_external_module_run(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-external-module.php') . ' '
        . escapeshellarg($targetDir . '/module') . ' ' . escapeshellarg('banner')
    );
} catch (Throwable $exception) {
    fwrite(STDERR, "external module scaffold checks failed: " . $exception->getMessage() . "\n");
    toy_check_create_external_module_remove_directory($workRoot);
    exit(1);
}

toy_check_create_external_module_remove_directory($workRoot);
echo "external module scaffold checks completed.\n";
