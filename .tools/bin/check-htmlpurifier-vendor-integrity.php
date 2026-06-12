#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_htmlpurifier_integrity_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_htmlpurifier_integrity_read(string $file): string
{
    if (!is_file($file)) {
        sr_htmlpurifier_integrity_error('Required HTML Purifier vendor file is missing: ' . $file);
        return '';
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        sr_htmlpurifier_integrity_error('Required HTML Purifier vendor file is unreadable: ' . $file);
        return '';
    }

    return $contents;
}

function sr_htmlpurifier_integrity_json(string $file): array
{
    $contents = sr_htmlpurifier_integrity_read($file);
    if ($contents === '') {
        return [];
    }

    $decoded = json_decode($contents, true);
    if (!is_array($decoded)) {
        sr_htmlpurifier_integrity_error('HTML Purifier vendor JSON is invalid: ' . $file);
        return [];
    }

    return $decoded;
}

$expectedPackage = 'ezyang/htmlpurifier';
$expectedPrettyVersion = 'v4.19.0';
$expectedVersion = '4.19.0';
$expectedNormalizedVersion = '4.19.0.0';
$expectedReference = 'b287d2a16aceffbf6e0295559b39662612b77fcf';
$expectedSource = 'https://github.com/ezyang/htmlpurifier.git';
$expectedLicense = 'LGPL-2.1-or-later';

$version = trim(sr_htmlpurifier_integrity_read('modules/htmlpurifier/vendor/ezyang/htmlpurifier/VERSION'));
if ($version !== $expectedVersion) {
    sr_htmlpurifier_integrity_error('HTML Purifier VERSION mismatch: expected ' . $expectedVersion . ', got ' . ($version !== '' ? $version : '(empty)'));
}

foreach ([
    'modules/htmlpurifier/vendor/autoload.php',
    'modules/htmlpurifier/vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php',
    'modules/htmlpurifier/vendor/ezyang/htmlpurifier/LICENSE',
] as $file) {
    sr_htmlpurifier_integrity_read($file);
}

$license = sr_htmlpurifier_integrity_read('modules/htmlpurifier/vendor/ezyang/htmlpurifier/LICENSE');
if ($license !== '' && (!str_contains($license, 'GNU LESSER GENERAL PUBLIC LICENSE') || !str_contains($license, 'Version 2.1'))) {
    sr_htmlpurifier_integrity_error('HTML Purifier license file does not look like LGPL 2.1.');
}

$dependency = sr_htmlpurifier_integrity_read('modules/htmlpurifier/DEPENDENCY.md');
foreach ([
    $expectedPackage,
    '`' . $expectedPrettyVersion . '`',
    $expectedSource,
    $expectedReference,
    $expectedLicense,
    'vendor/ezyang/htmlpurifier/LICENSE',
    'vendor/ezyang/htmlpurifier/VERSION',
] as $marker) {
    if ($dependency !== '' && !str_contains($dependency, $marker)) {
        sr_htmlpurifier_integrity_error('HTML Purifier dependency record is missing marker: ' . $marker);
    }
}

$composerJson = sr_htmlpurifier_integrity_json('modules/htmlpurifier/composer.json');
$requirements = is_array($composerJson['require'] ?? null) ? $composerJson['require'] : [];
if (($requirements[$expectedPackage] ?? '') !== '^4.19') {
    sr_htmlpurifier_integrity_error('HTML Purifier module composer.json must require ezyang/htmlpurifier ^4.19.');
}

$vendorComposerJson = sr_htmlpurifier_integrity_json('modules/htmlpurifier/vendor/ezyang/htmlpurifier/composer.json');
if (($vendorComposerJson['name'] ?? '') !== $expectedPackage) {
    sr_htmlpurifier_integrity_error('Vendored HTML Purifier composer.json package name mismatch.');
}
if (($vendorComposerJson['license'] ?? '') !== $expectedLicense) {
    sr_htmlpurifier_integrity_error('Vendored HTML Purifier composer.json license mismatch.');
}

$lock = sr_htmlpurifier_integrity_json('modules/htmlpurifier/composer.lock');
$lockPackage = null;
foreach (($lock['packages'] ?? []) as $package) {
    if (is_array($package) && ($package['name'] ?? '') === $expectedPackage) {
        $lockPackage = $package;
        break;
    }
}
if (!is_array($lockPackage)) {
    sr_htmlpurifier_integrity_error('HTML Purifier composer.lock package entry is missing.');
} else {
    if (($lockPackage['version'] ?? '') !== $expectedPrettyVersion) {
        sr_htmlpurifier_integrity_error('HTML Purifier composer.lock version mismatch.');
    }
    if (($lockPackage['source']['url'] ?? '') !== $expectedSource) {
        sr_htmlpurifier_integrity_error('HTML Purifier composer.lock source URL mismatch.');
    }
    if (($lockPackage['source']['reference'] ?? '') !== $expectedReference) {
        sr_htmlpurifier_integrity_error('HTML Purifier composer.lock source reference mismatch.');
    }
    if (!in_array($expectedLicense, is_array($lockPackage['license'] ?? null) ? $lockPackage['license'] : [], true)) {
        sr_htmlpurifier_integrity_error('HTML Purifier composer.lock license mismatch.');
    }
}

$installedJson = sr_htmlpurifier_integrity_json('modules/htmlpurifier/vendor/composer/installed.json');
$installedPackage = null;
foreach (($installedJson['packages'] ?? []) as $package) {
    if (is_array($package) && ($package['name'] ?? '') === $expectedPackage) {
        $installedPackage = $package;
        break;
    }
}
if (!is_array($installedPackage)) {
    sr_htmlpurifier_integrity_error('HTML Purifier vendor/composer/installed.json package entry is missing.');
} else {
    if (($installedPackage['version'] ?? '') !== $expectedPrettyVersion) {
        sr_htmlpurifier_integrity_error('HTML Purifier installed.json version mismatch.');
    }
    if (($installedPackage['version_normalized'] ?? '') !== $expectedNormalizedVersion) {
        sr_htmlpurifier_integrity_error('HTML Purifier installed.json normalized version mismatch.');
    }
    if (($installedPackage['source']['reference'] ?? '') !== $expectedReference) {
        sr_htmlpurifier_integrity_error('HTML Purifier installed.json source reference mismatch.');
    }
}

$installedPhpFile = 'modules/htmlpurifier/vendor/composer/installed.php';
$installedPhp = is_file($installedPhpFile) ? include $installedPhpFile : null;
if (!is_array($installedPhp)) {
    sr_htmlpurifier_integrity_error('HTML Purifier vendor/composer/installed.php must return an array.');
} else {
    $installedVersion = $installedPhp['versions'][$expectedPackage] ?? null;
    if (!is_array($installedVersion)) {
        sr_htmlpurifier_integrity_error('HTML Purifier installed.php version entry is missing.');
    } else {
        if (($installedVersion['pretty_version'] ?? '') !== $expectedPrettyVersion) {
            sr_htmlpurifier_integrity_error('HTML Purifier installed.php pretty_version mismatch.');
        }
        if (($installedVersion['version'] ?? '') !== $expectedNormalizedVersion) {
            sr_htmlpurifier_integrity_error('HTML Purifier installed.php version mismatch.');
        }
        if (($installedVersion['reference'] ?? '') !== $expectedReference) {
            sr_htmlpurifier_integrity_error('HTML Purifier installed.php source reference mismatch.');
        }
    }
}

require_once 'modules/htmlpurifier/vendor/autoload.php';
if (!class_exists('HTMLPurifier')) {
    sr_htmlpurifier_integrity_error('HTML Purifier class is not loadable from module autoload.');
} elseif ((string) HTMLPurifier::VERSION !== $expectedVersion) {
    sr_htmlpurifier_integrity_error('HTMLPurifier::VERSION mismatch: ' . (string) HTMLPurifier::VERSION);
}

if ($errors !== []) {
    fwrite(STDERR, "HTML Purifier vendor integrity checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "HTML Purifier vendor integrity checks completed.\n";
