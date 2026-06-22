#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];
$listMode = in_array('--list', $argv, true);
$manifestMode = in_array('--manifest', $argv, true);
$includeFiles = [
    '.htaccess',
    'index.php',
    'LICENSE',
    'README.md',
];
$includeDirs = [
    '.tools',
    'assets',
    'config',
    'core',
    'database',
    'docs',
    'examples',
    'lang',
    'layouts',
    'modules',
];
$excludePrefixes = [
    '.git/',
    '.agents/',
    '.claude/',
    'config/config.php',
    'config/config.php.tmp',
    'storage/',
    'vendor/',
    'dist/',
    '.tools/browser-qa/node_modules/',
    '.tools/browser-qa/results/',
    '.tools/browser-qa/test-results/',
];
$excludeFiles = [
    'AGENTS.md',
    '.tools/browser-qa/package-lock.json',
];
$excludePatterns = [
    '/\A\.env(?:\..*)?\z/',
    '/(?:^|\/)\.env(?:\..*)?\z/',
    '/\Aconfig\/config-[^\/]+\.tmp\.php\z/',
    '/\Aconfig\/.*\.(?:bak|backup|old|orig|tmp)\z/',
    '/(?:^|\/)[^\/]+\.(?:bak|backup|old|orig|tmp|sqlite|sqlite3|db)\z/i',
    '/(?:^|\/)(?:dump|backup|production|prod|staging|local)[^\/]*\.sql\z/i',
    '/(?:^|\/)(?:id_rsa|id_dsa|id_ecdsa|id_ed25519|\.npmrc|\.pypirc|composer\.auth\.json)\z/i',
];
$requiredFiles = [
    '.htaccess',
    'index.php',
    'LICENSE',
    'README.md',
    'docs/deployment/nginx-saanraan.conf',
    'docs/deployment/nginx-saanraan-subdirectory.conf',
    'modules/htmlpurifier/DEPENDENCY.md',
    'modules/htmlpurifier/vendor/autoload.php',
    'modules/htmlpurifier/vendor/ezyang/htmlpurifier/LICENSE',
    'modules/htmlpurifier/vendor/ezyang/htmlpurifier/VERSION',
    'modules/htmlpurifier/vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php',
    'modules/ckeditor/vendor/ckeditor5/README.md',
    'modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js',
    'modules/ckeditor/vendor/ckeditor5/ckeditor5.css',
    'modules/ckeditor/vendor/ckeditor5/LICENSE.md',
    'modules/ckeditor/vendor/ckeditor5/COPYING.GPL',
];
$forbiddenFiles = [
    'AGENTS.md',
    'config/config.php',
    'config/config.php.tmp',
    'storage/installed.lock',
    'storage/installed.lock.bak.20260610112311',
    'storage/config.php.bak.20260610112311',
    'storage/logs/error.log',
    '.tools/browser-qa/package-lock.json',
];
$forbiddenPatterns = [
    '/\A\.env(?:\..*)?\z/',
    '/\Aconfig\/config-[^\/]+\.tmp\.php\z/',
    '/\Aconfig\/.*\.(?:bak|backup|old|orig|tmp)\z/',
    '/(?:^|\/)\.env(?:\..*)?\z/',
    '/(?:^|\/)[^\/]+\.(?:bak|backup|old|orig|tmp|sqlite|sqlite3|db)\z/i',
    '/(?:^|\/)(?:dump|backup|production|prod|staging|local)[^\/]*\.sql\z/i',
    '/(?:^|\/)(?:id_rsa|id_dsa|id_ecdsa|id_ed25519|\.npmrc|\.pypirc|composer\.auth\.json)\z/i',
];

function sr_release_dry_run_normalize(string $path): string
{
    $path = str_replace('\\', '/', $path);
    if (str_starts_with($path, './')) {
        $path = substr($path, 2);
    }

    return ltrim($path, '/');
}

function sr_release_dry_run_is_excluded(string $path, array $excludePrefixes, array $excludeFiles, array $excludePatterns): bool
{
    $path = sr_release_dry_run_normalize($path);
    if (in_array($path, $excludeFiles, true)) {
        return true;
    }

    foreach ($excludePatterns as $pattern) {
        if (preg_match($pattern, $path) === 1) {
            return true;
        }
    }

    foreach ($excludePrefixes as $prefix) {
        if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
            return true;
        }
    }

    return false;
}

function sr_release_dry_run_add_file(string $file, array &$files, array &$errors): void
{
    $file = sr_release_dry_run_normalize($file);
    if (!is_file($file)) {
        $errors[] = 'Release package required source file is missing: ' . $file;
        return;
    }

    $files[$file] = true;
}

function sr_release_dry_run_add_dir(string $dir, array $excludePrefixes, array $excludeFiles, array $excludePatterns, array &$files, array &$errors): void
{
    $dir = sr_release_dry_run_normalize($dir);
    if (!is_dir($dir)) {
        $errors[] = 'Release package required source directory is missing: ' . $dir;
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $current) use ($excludePrefixes, $excludeFiles, $excludePatterns): bool {
                $path = sr_release_dry_run_normalize($current->getPathname());
                if ($current->isDir()) {
                    return !sr_release_dry_run_is_excluded($path . '/', $excludePrefixes, $excludeFiles, $excludePatterns);
                }

                return !sr_release_dry_run_is_excluded($path, $excludePrefixes, $excludeFiles, $excludePatterns);
            }
        )
    );

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile()) {
            $files[sr_release_dry_run_normalize($file->getPathname())] = true;
        }
    }
}

foreach ([
    '.env',
    '.env.local',
    '.env.production',
    'config/config.php',
    'config/config.php.tmp',
    'config/config-local.tmp.php',
    'config/config.php.bak',
    'config/config.php.old',
    'database/production.sql',
    'storage/private.sqlite',
    'modules/example/.env',
    'modules/example/backup.sql',
    'modules/example/id_rsa',
    'storage/logs/error.log',
    'storage/installed.lock',
    'vendor/autoload.php',
    'dist/saanraan.zip',
    '.tools/browser-qa/node_modules/playwright/index.js',
    '.tools/browser-qa/results/report.json',
] as $excludedSamplePath) {
    if (!sr_release_dry_run_is_excluded($excludedSamplePath, $excludePrefixes, $excludeFiles, $excludePatterns)) {
        $errors[] = 'Release package exclusion policy does not exclude sample path: ' . $excludedSamplePath;
    }
}

$files = [];
foreach ($includeFiles as $file) {
    if (sr_release_dry_run_is_excluded($file, $excludePrefixes, $excludeFiles, $excludePatterns)) {
        $errors[] = 'Release package include file is excluded by policy: ' . $file;
        continue;
    }

    sr_release_dry_run_add_file($file, $files, $errors);
}

foreach ($includeDirs as $dir) {
    if (sr_release_dry_run_is_excluded($dir . '/', $excludePrefixes, $excludeFiles, $excludePatterns)) {
        $errors[] = 'Release package include directory is excluded by policy: ' . $dir;
        continue;
    }

    sr_release_dry_run_add_dir($dir, $excludePrefixes, $excludeFiles, $excludePatterns, $files, $errors);
}

ksort($files, SORT_STRING);
$fileList = array_keys($files);

foreach ($requiredFiles as $file) {
    if (!isset($files[$file])) {
        $errors[] = 'Release package dry-run is missing required file: ' . $file;
    }
}

foreach ($forbiddenFiles as $file) {
    if (isset($files[$file])) {
        $errors[] = 'Release package dry-run includes forbidden file: ' . $file;
    }
}

foreach ($fileList as $file) {
    foreach (['.git/', '.agents/', '.claude/', 'storage/', 'vendor/', 'dist/', '.tools/browser-qa/node_modules/', '.tools/browser-qa/results/', '.tools/browser-qa/test-results/'] as $prefix) {
        if (str_starts_with($file, $prefix)) {
            $errors[] = 'Release package dry-run includes forbidden path prefix: ' . $file;
        }
    }

    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $file) === 1) {
            $errors[] = 'Release package dry-run includes forbidden path pattern: ' . $file;
        }
    }

    if (str_contains($file, '/../') || str_starts_with($file, '../')) {
        $errors[] = 'Release package dry-run includes path traversal segment: ' . $file;
    }
}

$manifestBody = '';
$manifestHash = '';
$manifestLines = [];
if ($manifestMode) {
    foreach ($fileList as $file) {
        $hash = hash_file('sha256', $file);
        if (!is_string($hash)) {
            $errors[] = 'Release package dry-run cannot hash file: ' . $file;
            continue;
        }

        $manifestLines[] = $hash . '  ' . $file;
    }

    $manifestBody = implode("\n", $manifestLines) . "\n";
    $manifestHash = hash('sha256', $manifestBody);
}

if ($errors !== []) {
    fwrite(STDERR, "release package dry-run checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

if ($manifestMode) {
    echo 'release-package-dry-run-version: 1' . "\n";
    echo 'files: ' . count($fileList) . "\n";
    echo 'manifest-sha256: ' . $manifestHash . "\n";
    echo "\n";
    echo $manifestBody;
    exit(0);
}

if ($listMode) {
    foreach ($fileList as $file) {
        echo $file . "\n";
    }
    exit(0);
}

echo 'release package dry-run checks completed. files=' . count($fileList) . "\n";
