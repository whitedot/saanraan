#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once $root . '/core/helpers/output.php';

$errors = [];

function sr_release_preflight_read_file(string $file, array &$errors): string
{
    if (!is_file($file)) {
        $errors[] = 'Required release preflight file is missing: ' . $file;
        return '';
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        $errors[] = 'Required release preflight file cannot be read: ' . $file;
        return '';
    }

    return $contents;
}

function sr_release_preflight_exec(array $command, array &$errors): string
{
    $parts = [];
    foreach ($command as $part) {
        $parts[] = escapeshellarg($part);
    }

    $output = [];
    exec(implode(' ', $parts) . ' 2>&1', $output, $exitCode);
    $text = implode("\n", $output);
    if ($exitCode !== 0) {
        $errors[] = 'Release preflight command failed: ' . implode(' ', $command) . "\n" . $text;
        return '';
    }

    return $text;
}

$versionFile = 'modules/htmlpurifier/vendor/ezyang/htmlpurifier/VERSION';
$dependencyFile = 'modules/htmlpurifier/DEPENDENCY.md';
$autoloadFile = 'modules/htmlpurifier/vendor/autoload.php';
$ckeditorReadmeFile = 'modules/ckeditor/vendor/ckeditor5/README.md';
$ckeditorFiles = [
    'modules/ckeditor/vendor/ckeditor5/README.md',
    'modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js',
    'modules/ckeditor/vendor/ckeditor5/ckeditor5.css',
    'modules/ckeditor/vendor/ckeditor5/LICENSE.md',
    'modules/ckeditor/vendor/ckeditor5/COPYING.GPL',
];

$purifierVersion = trim(sr_release_preflight_read_file($versionFile, $errors));
$dependencyRecord = sr_release_preflight_read_file($dependencyFile, $errors);
$ckeditorReadme = sr_release_preflight_read_file($ckeditorReadmeFile, $errors);
$ckeditorVersion = '';
if ($ckeditorReadme !== '' && preg_match('/^Version:\s*`([^`]+)`$/m', $ckeditorReadme, $matches) === 1) {
    $ckeditorVersion = $matches[1];
} else {
    $errors[] = 'CKEditor vendor README version marker is missing.';
}
$purifierStatus = function_exists('sr_rich_text_purifier_status') ? sr_rich_text_purifier_status() : [];
$purifierAvailable = ($purifierStatus['available'] ?? false) === true;

if ($purifierVersion === '') {
    $errors[] = 'HTML Purifier VERSION file is empty.';
}

if ($purifierVersion !== '' && $dependencyRecord !== '' && !str_contains($dependencyRecord, '`v' . $purifierVersion . '`')) {
    $errors[] = 'HTML Purifier dependency record version does not match VERSION file: ' . $purifierVersion;
}

if (!is_file($autoloadFile)) {
    $errors[] = 'HTML Purifier module autoload file is missing: ' . $autoloadFile;
}

foreach ($ckeditorFiles as $ckeditorFile) {
    if (!is_file($ckeditorFile) || filesize($ckeditorFile) <= 0) {
        $errors[] = 'CKEditor release asset is missing or empty: ' . $ckeditorFile;
    }
}

$manifest = sr_release_preflight_exec([PHP_BINARY, '.tools/bin/release-package-dry-run.php', '--manifest'], $errors);
$packageFiles = '';
$manifestHash = '';
if ($manifest !== '') {
    if (preg_match('/^files: (\d+)$/m', $manifest, $matches) === 1) {
        $packageFiles = $matches[1];
    } else {
        $errors[] = 'Release dry-run manifest file count is missing.';
    }

    if (preg_match('/^manifest-sha256: ([a-f0-9]{64})$/m', $manifest, $matches) === 1) {
        $manifestHash = $matches[1];
    } else {
        $errors[] = 'Release dry-run manifest sha256 is missing.';
    }

    foreach ([
        'modules/htmlpurifier/DEPENDENCY.md',
        'modules/htmlpurifier/vendor/autoload.php',
        'modules/htmlpurifier/vendor/ezyang/htmlpurifier/LICENSE',
        'modules/htmlpurifier/vendor/ezyang/htmlpurifier/VERSION',
        'modules/htmlpurifier/vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php',
    ] as $requiredFile) {
        if (preg_match('/^[a-f0-9]{64}  ' . preg_quote($requiredFile, '/') . '$/m', $manifest) !== 1) {
            $errors[] = 'Release dry-run manifest is missing HTML Purifier file: ' . $requiredFile;
        }
    }

    foreach ($ckeditorFiles as $requiredFile) {
        if (preg_match('/^[a-f0-9]{64}  ' . preg_quote($requiredFile, '/') . '$/m', $manifest) !== 1) {
            $errors[] = 'Release dry-run manifest is missing CKEditor file: ' . $requiredFile;
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "release preflight checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo 'release-preflight-version: 1' . "\n";
echo 'php-version: ' . PHP_VERSION . "\n";
echo 'purifier-available: ' . ($purifierAvailable ? 'yes' : 'no') . "\n";
echo 'purifier-version: ' . $purifierVersion . "\n";
echo 'purifier-module-autoload: present' . "\n";
echo 'purifier-autoload-path: ' . (string) ($purifierStatus['autoload_path'] ?? '') . "\n";
echo 'purifier-cache-dir: ' . (string) ($purifierStatus['cache_dir'] ?? '') . "\n";
echo 'purifier-cache-writable: ' . (($purifierStatus['cache_writable'] ?? false) === true ? 'yes' : 'no') . "\n";
echo 'dependency-record: present' . "\n";
echo 'ckeditor-assets: present' . "\n";
echo 'ckeditor-version: ' . $ckeditorVersion . "\n";
echo 'ckeditor-license-files: present' . "\n";
echo 'release-package-files: ' . $packageFiles . "\n";
echo 'release-package-manifest-sha256: ' . $manifestHash . "\n";
echo 'release preflight checks completed.' . "\n";
