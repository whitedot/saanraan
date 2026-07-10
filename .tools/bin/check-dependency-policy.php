#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_dependency_policy_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

$policyFile = 'docs/dependency-policy.md';
$outputHelperFile = 'core/helpers/output.php';
$releaseProcessFile = 'docs/release-process.md';
$docsReadmeFile = 'docs/README.md';
$thirdPartyNoticesFile = 'THIRD_PARTY_NOTICES.md';
$ckeditorVendorReadme = 'modules/ckeditor/vendor/ckeditor5/README.md';
$htmlPurifierReadme = 'modules/htmlpurifier/README.md';
$htmlPurifierDependency = 'modules/htmlpurifier/DEPENDENCY.md';
$htmlPurifierComposer = 'modules/htmlpurifier/composer.json';
$htmlPurifierLock = 'modules/htmlpurifier/composer.lock';
$htmlPurifierAutoload = 'modules/htmlpurifier/vendor/autoload.php';
$htmlPurifierLicense = 'modules/htmlpurifier/vendor/ezyang/htmlpurifier/LICENSE';
$htmlPurifierVersion = 'modules/htmlpurifier/vendor/ezyang/htmlpurifier/VERSION';

$policy = is_file($policyFile) ? file_get_contents($policyFile) : false;
$outputHelper = is_file($outputHelperFile) ? file_get_contents($outputHelperFile) : false;
$releaseProcess = is_file($releaseProcessFile) ? file_get_contents($releaseProcessFile) : false;
$docsReadme = is_file($docsReadmeFile) ? file_get_contents($docsReadmeFile) : false;
$thirdPartyNotices = is_file($thirdPartyNoticesFile) ? file_get_contents($thirdPartyNoticesFile) : false;

if (!is_string($policy)) {
    sr_dependency_policy_error('Dependency policy document is missing or unreadable.');
}
if (!is_string($outputHelper)) {
    sr_dependency_policy_error('Output helper is missing or unreadable.');
}
if (!is_string($thirdPartyNotices)) {
    sr_dependency_policy_error('Third-party notices document is missing or unreadable.');
}

$purifierPaths = [
    'vendor/autoload.php',
    'vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php',
    'modules/htmlpurifier/vendor/autoload.php',
    'modules/htmlpurifier/vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php',
];

foreach ($purifierPaths as $path) {
    if (is_string($policy) && !str_contains($policy, $path)) {
        sr_dependency_policy_error('Dependency policy is missing HTML Purifier path: ' . $path);
    }
    if (is_string($outputHelper) && !str_contains($outputHelper, $path)) {
        sr_dependency_policy_error('Output helper is missing HTML Purifier path: ' . $path);
    }
}

foreach ([
    'storage/cache/htmlpurifier',
    'ezyang/htmlpurifier',
    'check-rich-text-sanitizer.php',
    '런타임 Composer 필수 의존',
    '라이선스',
] as $marker) {
    if (is_string($policy) && !str_contains($policy, $marker)) {
        sr_dependency_policy_error('Dependency policy is missing marker: ' . $marker);
    }
}

foreach ([
    'function sr_sanitize_rich_text_html_with_purifier',
    'function sr_rich_text_purifier_available',
    'function sr_rich_text_purifier_cache_dir',
    'storage/cache/htmlpurifier',
    'Cache.SerializerPath',
] as $marker) {
    if (is_string($outputHelper) && !str_contains($outputHelper, $marker)) {
        sr_dependency_policy_error('Output helper is missing dependency marker: ' . $marker);
    }
}

if (is_string($releaseProcess) && !str_contains($releaseProcess, 'dependency-policy.md')) {
    sr_dependency_policy_error('Release process must link dependency-policy.md.');
}

if (is_string($docsReadme) && !str_contains($docsReadme, 'dependency-policy.md')) {
    sr_dependency_policy_error('docs/README.md must link dependency-policy.md.');
}

if (!is_file($ckeditorVendorReadme)) {
    sr_dependency_policy_error('CKEditor vendor README is missing.');
}

foreach (['CKEditor 5', '48.3.0', 'GPL-2.0-or-later', 'HTML Purifier', 'LGPL-2.1-or-later', 'github-markdown-css', 'MIT'] as $marker) {
    if (is_string($thirdPartyNotices) && !str_contains($thirdPartyNotices, $marker)) {
        sr_dependency_policy_error('Third-party notices document is missing marker: ' . $marker);
    }
}

foreach ([
    $htmlPurifierReadme,
    $htmlPurifierDependency,
    $htmlPurifierComposer,
    $htmlPurifierLock,
    $htmlPurifierAutoload,
    $htmlPurifierLicense,
    $htmlPurifierVersion,
] as $file) {
    if (!is_file($file)) {
        sr_dependency_policy_error('HTML Purifier included vendor metadata is missing: ' . $file);
    }
}

$htmlPurifierReadmeContent = is_file($htmlPurifierReadme) ? file_get_contents($htmlPurifierReadme) : false;
$htmlPurifierDependencyContent = is_file($htmlPurifierDependency) ? file_get_contents($htmlPurifierDependency) : false;
$htmlPurifierComposerContent = is_file($htmlPurifierComposer) ? file_get_contents($htmlPurifierComposer) : false;
$htmlPurifierLockContent = is_file($htmlPurifierLock) ? file_get_contents($htmlPurifierLock) : false;

foreach ([
    'modules/htmlpurifier/vendor/autoload.php',
    'modules/htmlpurifier/vendor/ezyang/htmlpurifier/library/HTMLPurifier.auto.php',
    'modules/htmlpurifier/DEPENDENCY.md',
    'storage/cache/htmlpurifier',
    'check-rich-text-sanitizer.php',
    '포함 배포',
] as $marker) {
    if (is_string($htmlPurifierReadmeContent) && !str_contains($htmlPurifierReadmeContent, $marker)) {
        sr_dependency_policy_error('HTML Purifier README is missing marker: ' . $marker);
    }
}

foreach ([
    'ezyang/htmlpurifier',
    'v4.19.0',
    'https://github.com/ezyang/htmlpurifier.git',
    'b287d2a16aceffbf6e0295559b39662612b77fcf',
    'LGPL-2.1-or-later',
    'vendor/ezyang/htmlpurifier/LICENSE',
    'vendor/ezyang/htmlpurifier/VERSION',
    'composer install --no-dev --prefer-dist',
    'composer update ezyang/htmlpurifier --no-dev --prefer-dist',
] as $marker) {
    if (is_string($htmlPurifierDependencyContent) && !str_contains($htmlPurifierDependencyContent, $marker)) {
        sr_dependency_policy_error('HTML Purifier dependency record is missing marker: ' . $marker);
    }
    if (is_string($policy) && !str_contains($policy, $marker)) {
        sr_dependency_policy_error('Dependency policy is missing HTML Purifier dependency marker: ' . $marker);
    }
}

if (is_string($htmlPurifierComposerContent) && !str_contains($htmlPurifierComposerContent, 'ezyang/htmlpurifier')) {
    sr_dependency_policy_error('HTML Purifier composer metadata is missing ezyang/htmlpurifier.');
}

foreach ([
    '"version": "v4.19.0"',
    '"url": "https://github.com/ezyang/htmlpurifier.git"',
    '"reference": "b287d2a16aceffbf6e0295559b39662612b77fcf"',
    '"LGPL-2.1-or-later"',
] as $marker) {
    if (is_string($htmlPurifierLockContent) && !str_contains($htmlPurifierLockContent, $marker)) {
        sr_dependency_policy_error('HTML Purifier lock metadata is missing marker: ' . $marker);
    }
}

$htmlPurifierVersionContent = is_file($htmlPurifierVersion) ? trim((string) file_get_contents($htmlPurifierVersion)) : '';
if ($htmlPurifierVersionContent !== '4.19.0') {
    sr_dependency_policy_error('HTML Purifier VERSION file must contain 4.19.0.');
}

if ($errors !== []) {
    fwrite(STDERR, "dependency policy checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "dependency policy checks completed.\n";
