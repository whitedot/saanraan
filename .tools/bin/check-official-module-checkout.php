#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$workRoot = $root . '/storage/check-official-module-checkout-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
$indexPath = $workRoot . '/module-index.json';
$targetRoot = $workRoot . '/module-repos';

function toy_check_official_module_checkout_remove_directory(string $directory): void
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

function toy_check_official_module_checkout_run(string $command, array $env): string
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
            throw new RuntimeException('Command failed: ' . $command . "\n" . implode("\n", $output));
        }

        return implode("\n", $output);
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
    if (!mkdir($targetRoot, 0755, true)) {
        throw new RuntimeException('work directory cannot be created.');
    }

    $index = [
        'modules' => [
            [
                'module_key' => 'banner',
                'name' => 'Banner',
                'repository' => 'https://github.com/whitedot/toycore-module-banner',
                'latest_version' => '',
                'min_toycore_version' => '',
                'module_contract' => '',
                'category' => 'operations',
                'zip_url' => '',
                'checksum' => '',
            ],
            [
                'module_key' => 'seo',
                'name' => 'SEO',
                'repository' => 'https://github.com/whitedot/toycore-module-seo',
                'latest_version' => '2099.01.001',
                'min_toycore_version' => '',
                'module_contract' => '',
                'category' => 'site',
                'zip_url' => '',
                'checksum' => '',
            ],
        ],
    ];

    if (file_put_contents($indexPath, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX) === false) {
        throw new RuntimeException('test module index cannot be written.');
    }

    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg('.tools/bin/clone-official-modules.php') . ' ' . escapeshellarg($targetRoot);
    $output = toy_check_official_module_checkout_run($command, [
        'TOYCORE_MODULE_INDEX_PATH' => $indexPath,
        'TOYCORE_CLONE_OFFICIAL_MODULES_DRY_RUN' => '1',
    ]);

    foreach ([
        'toycore-module-banner',
        'Using default branch for banner',
        'toycore-module-seo',
        'v2099.01.001',
        'official module repositories are ready under',
    ] as $expectedText) {
        if (!str_contains($output, $expectedText)) {
            throw new RuntimeException('checkout dry-run output is missing: ' . $expectedText);
        }
    }

    $sharedRefOutput = toy_check_official_module_checkout_run($command, [
        'TOYCORE_MODULE_INDEX_PATH' => $indexPath,
        'TOYCORE_CLONE_OFFICIAL_MODULES_DRY_RUN' => '1',
        'TOYCORE_MODULE_REF' => 'v2099.02.001',
    ]);
    if (!str_contains($sharedRefOutput, 'v2099.02.001') || str_contains($sharedRefOutput, 'Using default branch')) {
        throw new RuntimeException('shared module ref was not applied to dry-run checkout.');
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "official module checkout checks failed: " . $exception->getMessage() . "\n");
    toy_check_official_module_checkout_remove_directory($workRoot);
    exit(1);
}

toy_check_official_module_checkout_remove_directory($workRoot);
echo "official module checkout checks completed.\n";
