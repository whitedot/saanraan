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

foreach ([
    'docs/contribution-guide.md' => [
        'check-tool-gate-coverage.php',
        '새 `check-*.php`',
        '통합 게이트',
    ],
    'docs/verification-status.md' => [
        'check-tool-gate-coverage.php',
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
