#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_browser_qa_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_browser_qa_read(string $file): string
{
    if (!is_file($file)) {
        sr_browser_qa_error('Required browser QA file is missing: ' . $file);
        return '';
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        sr_browser_qa_error('Required browser QA file cannot be read: ' . $file);
        return '';
    }

    return $contents;
}

function sr_browser_qa_require_markers(string $file, array $markers): void
{
    $contents = sr_browser_qa_read($file);
    if ($contents === '') {
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            sr_browser_qa_error($file . ' is missing marker: ' . $marker);
        }
    }
}

function sr_browser_qa_node_check(string $file): void
{
    if (!function_exists('exec')) {
        return;
    }

    $nodePathOutput = [];
    exec('command -v node 2>/dev/null', $nodePathOutput, $nodeStatus);
    if ($nodeStatus !== 0 || $nodePathOutput === []) {
        return;
    }

    $nodePath = trim((string) $nodePathOutput[0]);
    if ($nodePath === '') {
        return;
    }

    $output = [];
    exec(escapeshellarg($nodePath) . ' --check ' . escapeshellarg($file) . ' 2>&1', $output, $status);
    if ($status !== 0) {
        sr_browser_qa_error('Node syntax check failed for ' . $file . ': ' . implode("\n", $output));
    }
}

sr_browser_qa_require_markers('.tools/browser-qa/package.json', [
    '"test"',
    '"test:ckeditor"',
    '"test:core"',
    'playwright test -c playwright.config.js',
    'tests/ckeditor-browser-smoke.spec.js',
    '--project=chromium-full',
    '@playwright/test',
    '@axe-core/playwright',
]);

sr_browser_qa_require_markers('.tools/browser-qa/playwright.config.js', [
    'SR_BROWSER_QA_BASE_URL',
    'SR_SMOKE_BASE_URL',
    'chromium-full',
    'firefox-core',
    'webkit-core',
]);

sr_browser_qa_require_markers('.tools/browser-qa/tests/ckeditor-browser-smoke.spec.js', [
    "test.describe('CKEditor browser smoke'",
    '/modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js',
    '/modules/ckeditor/vendor/ckeditor5/ckeditor5.css',
    '/modules/ckeditor/assets/saanraan-ckeditor.js',
    '/modules/ckeditor/assets/saanraan-ckeditor.css',
    'textarea.dataset.srEditorReady === \'1\'',
    'sr-ckeditor-unavailable',
    'data-sr-editor-format="ckeditor"',
    'body_format',
    'ckeditor-upload-fixture',
    'createUploadAdapter',
    'csrf_token',
    'upload_token',
    'community_privacy_consent_accepted',
    'fixture upload rejected',
]);

sr_browser_qa_require_markers('docs/smoke-test.md', [
    'npm --prefix .tools/browser-qa run test:ckeditor',
    'ckeditor-browser-smoke.spec.js',
    'SR_BROWSER_QA_BASE_URL',
    'self-hosted CKEditor',
    'upload adapter request contract',
]);

sr_browser_qa_require_markers('docs/verification-status.md', [
    'npm --prefix .tools/browser-qa run test:ckeditor',
    'ckeditor-browser-smoke.spec.js',
    'CKEditor asset/fallback browser smoke',
    'upload adapter request contract',
]);

foreach ([
    '.tools/browser-qa/playwright.config.js',
    '.tools/browser-qa/tests/milestone-15-browser-smoke.spec.js',
    '.tools/browser-qa/tests/milestone-15-deep-browser.spec.js',
    '.tools/browser-qa/tests/ckeditor-browser-smoke.spec.js',
] as $jsFile) {
    sr_browser_qa_node_check($jsFile);
}

if ($errors !== []) {
    fwrite(STDERR, "browser QA checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "browser QA checks completed.\n";
