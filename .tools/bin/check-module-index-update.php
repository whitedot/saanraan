#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$sourceIndexPath = $root . '/docs/module-index.json';
$workRoot = $root . '/storage/check-module-index-update-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$indexPath = $workRoot . '/module-index.json';
$zipDirectory = $workRoot . '/modules';
$moduleRepositoryRoot = $workRoot . '/module-repos';
$version = '2099.01.001';
$releaseBaseUrl = 'https://example.com/releases';
$updateModuleIndexTool = $root . '/.tools/bin/update-module-index';

function toy_check_module_index_update_remove_directory(string $directory): void
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

function toy_check_module_index_update_run(string $command, array $env = []): void
{
    $previousValues = [];
    foreach ($env as $name => $value) {
        if (preg_match('/\A[A-Z][A-Z0-9_]{1,80}\z/', $name) !== 1) {
            throw new RuntimeException('Invalid environment name: ' . $name);
        }

        $previous = getenv($name);
        $previousValues[$name] = is_string($previous) ? $previous : null;
        putenv($name . '=' . (string) $value);
    }

    try {
        $output = [];
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            $message = 'Command failed: ' . $command;
            if ($output !== []) {
                $message .= "\n" . implode("\n", $output);
            }

            throw new RuntimeException($message);
        }
    } finally {
        foreach ($previousValues as $name => $previousValue) {
            if ($previousValue === null) {
                putenv($name);
            } else {
                putenv($name . '=' . $previousValue);
            }
        }
    }
}

try {
    if (!is_file($sourceIndexPath)) {
        throw new RuntimeException('module index does not exist.');
    }

    $updateToolContent = file_get_contents($updateModuleIndexTool);
    if (
        !is_string($updateToolContent)
        || strpos($updateToolContent, "'follow_location' => 0") === false
        || strpos($updateToolContent, "'max_redirects' => 0") === false
    ) {
        throw new RuntimeException('module index update tool must not follow repository archive redirects.');
    }

    if (!mkdir($zipDirectory, 0755, true) || !mkdir($moduleRepositoryRoot, 0755, true)) {
        throw new RuntimeException('work directory cannot be created.');
    }

    if (!copy($sourceIndexPath, $indexPath)) {
        throw new RuntimeException('module index cannot be copied.');
    }

    $index = json_decode((string) file_get_contents($indexPath), true);
    if (!is_array($index) || !is_array($index['modules'] ?? null)) {
        throw new RuntimeException('module index JSON is invalid.');
    }

    $expectedChecksums = [];
    foreach ($index['modules'] as $module) {
        $moduleKey = is_array($module) ? (string) ($module['module_key'] ?? '') : '';
        if (preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $moduleKey) !== 1) {
            continue;
        }

        $zipName = $moduleKey . '-' . $version . '.zip';
        $zipPath = $zipDirectory . '/' . $zipName;
        $content = 'toycore module index update check: ' . $moduleKey;
        if (file_put_contents($zipPath, $content, LOCK_EX) === false) {
            throw new RuntimeException('test zip cannot be written: ' . $zipName);
        }

        $moduleRepoDir = $moduleRepositoryRoot . '/toycore-module-' . str_replace('_', '-', $moduleKey) . '/module';
        if (!mkdir($moduleRepoDir, 0755, true)) {
            throw new RuntimeException('test module repository cannot be created: ' . $moduleKey);
        }

        $moduleMetadata = "<?php\n\nreturn [\n"
            . "    'name' => '" . addslashes($moduleKey) . "',\n"
            . "    'version' => '" . $version . "',\n"
            . "    'type' => 'module',\n"
            . "    'toycore' => [\n"
            . "        'min_version' => '0.1.1',\n"
            . "        'module_contract' => '1.0',\n"
            . "    ],\n"
            . "];\n";
        if (file_put_contents($moduleRepoDir . '/module.php', $moduleMetadata, LOCK_EX) === false) {
            throw new RuntimeException('test module metadata cannot be written: ' . $moduleKey);
        }

        $checksum = hash_file('sha256', $zipPath);
        if (!is_string($checksum)) {
            throw new RuntimeException('test zip checksum cannot be calculated: ' . $zipName);
        }

        $expectedChecksums[$moduleKey] = $checksum;
    }

    toy_check_module_index_update_run(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/update-module-index') . ' '
            . escapeshellarg($version) . ' ' . escapeshellarg($releaseBaseUrl) . ' ' . escapeshellarg($zipDirectory),
        [
            'TOYCORE_MODULE_INDEX_PATH' => $indexPath,
            'TOYCORE_MODULE_REPO_ROOT' => $moduleRepositoryRoot,
        ]
    );
    toy_check_module_index_update_run(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/check-module-index.php') . ' ' . escapeshellarg($indexPath)
    );

    $updatedIndex = json_decode((string) file_get_contents($indexPath), true);
    if (!is_array($updatedIndex) || !is_array($updatedIndex['modules'] ?? null)) {
        throw new RuntimeException('updated module index JSON is invalid.');
    }

    foreach ($updatedIndex['modules'] as $module) {
        if (!is_array($module)) {
            continue;
        }

        $moduleKey = (string) ($module['module_key'] ?? '');
        if (!isset($expectedChecksums[$moduleKey])) {
            continue;
        }

        $expectedZipUrl = $releaseBaseUrl . '/' . rawurlencode($moduleKey . '-' . $version . '.zip');
        if (
            (string) ($module['latest_version'] ?? '') !== $version
            || (string) ($module['zip_url'] ?? '') !== $expectedZipUrl
            || (string) ($module['checksum'] ?? '') !== $expectedChecksums[$moduleKey]
            || (string) ($module['min_toycore_version'] ?? '') !== '0.1.1'
            || (string) ($module['module_contract'] ?? '') !== '1.0'
        ) {
            throw new RuntimeException('module index update result is invalid: ' . $moduleKey);
        }
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "module index update checks failed: " . $exception->getMessage() . "\n");
    toy_check_module_index_update_remove_directory($workRoot);
    exit(1);
}

toy_check_module_index_update_remove_directory($workRoot);
echo "module index update checks completed.\n";
