#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$workRoot = $root . '/storage/check-create-external-module-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$targetDir = $workRoot . '/banner-module';
$noCiTargetDir = $workRoot . '/popup-module';
$badMenuTargetDir = $workRoot . '/bad-menu-module';

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
        . escapeshellarg('banner') . ' ' . escapeshellarg($targetDir) . ' ' . escapeshellarg('v0.1.1') . ' '
        . escapeshellarg('--with-ci')
    );

    foreach ([
        'README.md',
        'AGENTS.md',
        'CHANGELOG.md',
        'module/module.php',
        'module/install.sql',
        '.tools/bin/package-module',
        '.github/workflows/check.yml',
    ] as $path) {
        if (!is_file($targetDir . '/' . $path)) {
            throw new RuntimeException('scaffold file is missing: ' . $path);
        }
    }

    $readme = (string) file_get_contents($targetDir . '/README.md');
    $agents = (string) file_get_contents($targetDir . '/AGENTS.md');
    $ci = (string) file_get_contents($targetDir . '/.github/workflows/check.yml');
    if (
        !str_contains($readme, 'Toycore 외부 모듈 `banner`')
        || !str_contains($readme, '구현 규칙은 이 프로젝트의 `AGENTS.md`를 기준으로 한다')
        || !str_contains($readme, 'AGENTS.md 기준으로 module/paths.php와 관리자 action을 추가해줘')
        || !str_contains($readme, 'git checkout v0.1.1')
        || !str_contains($readme, 'TOYCORE=/path/to/toycore')
        || !str_contains($readme, 'php "$TOYCORE/.tools/bin/check-external-module.php" module banner')
        || !str_contains($readme, 'Set-Location C:\path\to\banner-module')
        || !str_contains($agents, 'Toycore 외부 모듈 `banner`')
        || !str_contains($agents, 'AI 코딩 도구에 작업을 맡길 때')
        || !str_contains($agents, 'php "$TOYCORE/.tools/bin/check-external-module.php" module banner')
        || !str_contains($agents, 'toy_banner_items')
        || !str_contains($agents, 'toy_banner_...')
        || !str_contains($ci, 'TOYCORE_MODULE_KEY: banner')
    ) {
        throw new RuntimeException('scaffold templates were not replaced.');
    }
    foreach (['MODULE_NAME', 'MODULE_KEY', 'MODULE_PROJECT', 'TOYCORE_VERSION', 'TOYCORE_REF', 'MODULE_CONTRACT_VERSION'] as $placeholder) {
        if (str_contains($readme, $placeholder)) {
            throw new RuntimeException('scaffold README still has placeholder: ' . $placeholder);
        }
        if (str_contains($agents, $placeholder)) {
            throw new RuntimeException('scaffold AGENTS still has placeholder: ' . $placeholder);
        }
    }

    toy_check_create_external_module_run(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-external-module.php') . ' '
        . escapeshellarg($targetDir . '/module') . ' ' . escapeshellarg('banner')
    );
    toy_check_create_external_module_run(
        escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($targetDir . '/.tools/bin/package-module')
    );
    $packageOutput = [];
    exec(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($targetDir . '/.tools/bin/package-module') . ' 2>&1',
        $packageOutput,
        $packageExitCode
    );
    if (class_exists('ZipArchive')) {
        if ($packageExitCode !== 0) {
            throw new RuntimeException('package-module should create a zip when ZipArchive is available: ' . implode("\n", $packageOutput));
        }
        if (!is_file($targetDir . '/dist/banner-2026.05.001.zip')) {
            throw new RuntimeException('package-module did not create the expected zip.');
        }
    } elseif ($packageExitCode === 0 || !str_contains(implode("\n", $packageOutput), 'PHP ZipArchive extension is required')) {
        throw new RuntimeException('package-module should explain missing ZipArchive.');
    }

    toy_check_create_external_module_run(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/create-external-module.php') . ' '
        . escapeshellarg('popup_layer') . ' ' . escapeshellarg($noCiTargetDir)
    );
    if (is_file($noCiTargetDir . '/.github/workflows/check.yml')) {
        throw new RuntimeException('CI workflow should not be created unless --with-ci is used.');
    }
    if (
        !is_file($noCiTargetDir . '/AGENTS.md')
        || !is_file($noCiTargetDir . '/module/module.php')
        || !is_file($noCiTargetDir . '/.tools/bin/package-module')
    ) {
        throw new RuntimeException('no-ci scaffold is incomplete.');
    }

    if (!mkdir($badMenuTargetDir . '/module/actions', 0755, true)) {
        throw new RuntimeException('bad menu test directory cannot be created.');
    }
    file_put_contents(
        $badMenuTargetDir . '/module/module.php',
        "<?php\n\nreturn [\n"
        . "    'name' => 'Bad Menu',\n"
        . "    'version' => '2026.05.001',\n"
        . "    'type' => 'module',\n"
        . "    'toycore' => [\n"
        . "        'min_version' => '0.1.1',\n"
        . "        'tested_with' => ['0.1.1'],\n"
        . "        'module_contract' => '1.0',\n"
        . "    ],\n"
        . "];\n"
    );
    file_put_contents($badMenuTargetDir . '/module/install.sql', "-- Bad menu test module.\n");
    file_put_contents($badMenuTargetDir . '/module/actions/admin-bad-menu.php', "<?php\n\n\$notice = '';\n");
    file_put_contents(
        $badMenuTargetDir . '/module/paths.php',
        "<?php\n\nreturn [\n    'GET /admin/bad-menu' => 'actions/admin-bad-menu.php',\n];\n"
    );
    file_put_contents(
        $badMenuTargetDir . '/module/admin-menu.php',
        "<?php\n\nreturn [\n    ['label' => 'Bad Menu', 'path' => '/admin/missing-menu', 'order' => 50],\n];\n"
    );

    $badMenuOutput = [];
    exec(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-external-module.php') . ' '
        . escapeshellarg($badMenuTargetDir . '/module') . ' ' . escapeshellarg('bad_menu') . ' 2>&1',
        $badMenuOutput,
        $badMenuExitCode
    );
    if ($badMenuExitCode === 0 || !str_contains(implode("\n", $badMenuOutput), 'admin-menu.php path is missing from paths.php')) {
        throw new RuntimeException('admin menu path mismatch should be rejected.');
    }

    $output = [];
    exec(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/create-external-module.php') . ' '
        . escapeshellarg('bad_ref') . ' ' . escapeshellarg($workRoot . '/bad-ref') . ' ' . escapeshellarg('../main') . ' 2>&1',
        $output,
        $exitCode
    );
    if ($exitCode === 0 || !str_contains(implode("\n", $output), 'Toycore ref is invalid.')) {
        throw new RuntimeException('invalid Toycore ref should be rejected.');
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "external module scaffold checks failed: " . $exception->getMessage() . "\n");
    toy_check_create_external_module_remove_directory($workRoot);
    exit(1);
}

toy_check_create_external_module_remove_directory($workRoot);
echo "external module scaffold checks completed.\n";
