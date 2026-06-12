#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_installed_gate_status_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_installed_gate_status_read(string $file): string
{
    if (!is_file($file)) {
        sr_installed_gate_status_error('Installed gate status required file is missing: ' . $file);
        return '';
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        sr_installed_gate_status_error('Installed gate status required file cannot be read: ' . $file);
        return '';
    }

    return $contents;
}

function sr_installed_gate_status_require_markers(string $file, array $markers): void
{
    $contents = sr_installed_gate_status_read($file);
    if ($contents === '') {
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            sr_installed_gate_status_error('Installed gate status marker missing in ' . $file . ': ' . $marker);
        }
    }
}

function sr_installed_gate_status_exec(array $command): string
{
    $parts = [];
    foreach ($command as $part) {
        $parts[] = escapeshellarg($part);
    }

    $output = [];
    exec(implode(' ', $parts) . ' 2>&1', $output, $exitCode);
    $text = implode("\n", $output);
    if ($exitCode !== 0) {
        sr_installed_gate_status_error('Installed gate status command failed: ' . implode(' ', $command) . "\n" . $text);
        return '';
    }

    return $text;
}

$output = sr_installed_gate_status_exec([PHP_BINARY, '.tools/bin/release-installed-gate-status.php']);
foreach ([
    'release-installed-gate-status-version: 1',
    'installed-lock:',
    'config-readable:',
    'config-mode:',
    'config-owner-group:',
    'sr-is-installed:',
    'browser-qa-base-url:',
    'run-readonly: no',
    'run-browser-qa: no',
    'run-auth-smoke: no',
    'run-asset-smoke: no',
    'run-privacy-fixtures: no',
    'run-performance-fixtures: no',
    'mutation-smoke-allowed: no',
    "gate\t새 설치 또는 업데이트 적용\t",
    "gate\t`php .tools/bin/reconcile-assets.php`\t",
    "gate\t`php .tools/bin/ops-status.php`\t",
    "gate\t/admin/assets/reconciliation\t",
    "gate\t/admin/operations\t",
    "gate\t인증 smoke\t",
    "gate\t자산/쿠폰/유료 접근권 mutation smoke\t",
    "gate\t개인정보 export/cleanup smoke\t",
    "gate\tCKEditor asset/fallback browser smoke\t",
    "gate\tCKEditor upload/save browser smoke\t",
    "gate\t성능 수동 점검\t",
    'unresolved-gates:',
    'release installed gate status completed.',
] as $marker) {
    if ($output !== '' && !str_contains($output, $marker)) {
        sr_installed_gate_status_error('Installed gate status output marker missing: ' . $marker);
    }
}

sr_installed_gate_status_require_markers('.tools/bin/release-installed-gate-status.php', [
    'release-installed-gate-status-version: 1',
    '--run-readonly',
    '--run-browser-qa',
    '--run-auth-smoke',
    '--run-asset-smoke',
    '--run-privacy-fixtures',
    '--run-performance-fixtures',
    'SR_SMOKE_ALLOW_MUTATION',
    'config/config.php is not readable by current user',
    'sr_release_gate_status_file_mode',
    'sr_release_gate_status_file_owner_group',
    'set SR_SMOKE_BASE_URL and use an administrator session',
    'SR_BROWSER_QA_BASE_URL',
    'npm --prefix .tools/browser-qa run test:ckeditor',
    'smoke-community-auth.php',
    'smoke-asset-idempotency-http.php',
    'check-privacy-export-runtime.php',
    'check-privacy-cleanup-runtime.php',
    'check-performance-policy.php',
    'check-performance-baseline.php',
    'check-admin-pagination-runtime.php',
    'check-community-board-copy-limits.php',
    'check-survey-export-runtime.php',
    'installed DB performance review still required',
    'installed DB smoke still required',
    'upload adapter, saved HTML sanitizer',
    'do not run against production',
]);

sr_installed_gate_status_require_markers('docs/release-verification-template.md', [
    'php .tools/bin/release-installed-gate-status.php',
    'php .tools/bin/release-installed-gate-status.php --run-readonly',
    '설치 DB 게이트 상태표',
]);

sr_installed_gate_status_require_markers('docs/verification-status.md', [
    'php .tools/bin/release-installed-gate-status.php',
    '설치 DB 게이트 상태표',
]);

sr_installed_gate_status_require_markers('docs/records/improvement-hardening-verification-2026-06-11.md', [
    'php .tools/bin/release-installed-gate-status.php',
    'release-installed-gate-status-version: 1',
]);

if ($errors !== []) {
    fwrite(STDERR, "installed gate status checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "installed gate status checks completed.\n";
