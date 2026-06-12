#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

$checkPhp = '.tools/bin/check.php';
$contents = is_file($checkPhp) ? file_get_contents($checkPhp) : false;
if (!is_string($contents)) {
    fwrite(STDERR, "tool gate coverage checks failed:\n");
    fwrite(STDERR, '- Required gate file is missing or unreadable: ' . $checkPhp . "\n");
    exit(1);
}

$integrated = [];
if (preg_match_all('/\.tools\/bin\/(check-[a-z0-9-]+\.php)/', $contents, $matches) === false) {
    $errors[] = 'Unable to scan integrated check commands in ' . $checkPhp;
} else {
    $integrated = array_values(array_unique($matches[1]));
}

$standalone = [
    'check-milestone-10-deep.php',
    'check-milestone-11-consistency.php',
    'check-milestone-15-deep-qa.php',
    'check-milestone-15-route-qa.php',
    'check-skin-theme-ui.php',
    'check-storage-helpers.php',
];

$allChecks = glob('.tools/bin/check-*.php');
if (!is_array($allChecks)) {
    $errors[] = 'Unable to list check tools in .tools/bin.';
    $allChecks = [];
}

$known = array_fill_keys(array_merge($integrated, $standalone), true);
foreach ($allChecks as $path) {
    $file = basename($path);
    if (!isset($known[$file])) {
        $errors[] = 'Check tool is not integrated into check.php or explicitly marked standalone: ' . $file;
    }
}

foreach ($standalone as $file) {
    if (!is_file('.tools/bin/' . $file)) {
        $errors[] = 'Standalone check allowlist references a missing file: ' . $file;
    }
    if (in_array($file, $integrated, true)) {
        $errors[] = 'Check tool is both integrated and standalone allowlisted: ' . $file;
    }
}

foreach ($integrated as $file) {
    if (!is_file('.tools/bin/' . $file)) {
        $errors[] = 'Integrated check command references a missing file: ' . $file;
    }
}

$releaseGateStatus = is_file('.tools/bin/release-installed-gate-status.php')
    ? file_get_contents('.tools/bin/release-installed-gate-status.php')
    : false;
$smokeTestDoc = is_file('docs/smoke-test.md') ? file_get_contents('docs/smoke-test.md') : false;
if (!is_string($releaseGateStatus)) {
    $errors[] = 'Required smoke gate file is missing or unreadable: .tools/bin/release-installed-gate-status.php';
}
if (!is_string($smokeTestDoc)) {
    $errors[] = 'Required smoke documentation is missing or unreadable: docs/smoke-test.md';
}

$allSmokeTools = glob('.tools/bin/smoke-*.php');
if (!is_array($allSmokeTools)) {
    $errors[] = 'Unable to list smoke tools in .tools/bin.';
    $allSmokeTools = [];
}

foreach ($allSmokeTools as $path) {
    $file = basename($path);
    if (is_string($releaseGateStatus) && !str_contains($releaseGateStatus, $file)) {
        $errors[] = 'Smoke tool is not connected to release-installed-gate-status.php: ' . $file;
    }
    if (is_string($smokeTestDoc) && !str_contains($smokeTestDoc, $file)) {
        $errors[] = 'Smoke tool is not documented in docs/smoke-test.md: ' . $file;
    }
}

foreach ([
    'docs/contribution-guide.md' => [
        'check-tool-gate-coverage.php',
        '새 `check-*.php`',
        '새 `smoke-*.php`',
        '통합 게이트',
    ],
    'docs/verification-status.md' => [
        'check-tool-gate-coverage.php',
        '`smoke-*.php`',
        '통합 게이트',
    ],
] as $file => $markers) {
    $doc = is_file($file) ? file_get_contents($file) : false;
    if (!is_string($doc)) {
        $errors[] = 'Required tool gate document is missing or unreadable: ' . $file;
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($doc, $marker)) {
            $errors[] = 'Tool gate documentation marker missing in ' . $file . ': ' . $marker;
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "tool gate coverage checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "tool gate coverage checks completed.\n";
