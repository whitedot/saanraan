#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_site_reset_check_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_site_reset_check_read(string $path): string
{
    if (!is_file($path)) {
        sr_site_reset_check_error('Required site reset fixture file is missing: ' . $path);
        return '';
    }

    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        sr_site_reset_check_error('Required site reset fixture file cannot be read: ' . $path);
        return '';
    }

    return $contents;
}

function sr_site_reset_check_contains(string $path, array $markers): void
{
    $contents = sr_site_reset_check_read($path);
    if ($contents === '') {
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            sr_site_reset_check_error($path . ' missing marker: ' . $marker);
        }
    }
}

function sr_site_reset_check_command(array $command, int $expectedExitCode, array $markers, string $label): void
{
    $parts = [];
    foreach ($command as $part) {
        $parts[] = escapeshellarg($part);
    }

    $output = [];
    exec(implode(' ', $parts) . ' 2>&1', $output, $exitCode);
    $text = implode("\n", $output);
    if ($exitCode !== $expectedExitCode) {
        sr_site_reset_check_error($label . ' expected exit ' . (string) $expectedExitCode . ', got ' . (string) $exitCode . ': ' . $text);
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($text, $marker)) {
            sr_site_reset_check_error($label . ' output must contain: ' . $marker);
        }
    }
}

sr_site_reset_check_contains('.tools/bin/seed-dummy-http.php', [
    'SR_SEED_ALLOW_MUTATION=1',
    'saanraan dummy HTTP seed refused to run because it creates and trims QA data.',
    'sr_load_config()',
    'seed_post',
    'seed_trim_rows',
]);

sr_site_reset_check_command(
    [
        'env',
        'SR_SEED_BASE_URL=http://127.0.0.1:1',
        'SR_SEED_ADMIN_PASSWORD=12341234',
        PHP_BINARY,
        '.tools/bin/seed-dummy-http.php',
    ],
    2,
    [
        'saanraan dummy HTTP seed refused to run because it creates and trims QA data.',
        'SR_SEED_ALLOW_MUTATION=1',
    ],
    'Dummy HTTP seed mutation guard'
);

sr_site_reset_check_contains('docs/site-reset-and-fixtures.md', [
    '운영 DB에서 실행하는 절차가 아니다',
    'SR_SEED_ALLOW_MUTATION=1',
    'seed-dummy-http.php',
    '현재 DB가 로컬 또는 스테이징인지 확인한다',
]);

sr_site_reset_check_contains('docs/smoke-test.md', [
    '사이트 초기화와 더미 데이터 기준',
    'seed-dummy-http.php',
    'SR_SEED_ALLOW_MUTATION=1',
]);

if ($errors !== []) {
    fwrite(STDERR, "site reset fixture checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "site reset fixture checks completed.\n";
