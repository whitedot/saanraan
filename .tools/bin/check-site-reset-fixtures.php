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
    'SR_SEED_SKIP_RICH_FIXTURES',
    'saanraan dummy HTTP seed refused to run because it creates and trims QA data.',
    'sr_load_config()',
    'seed_post',
    'seed_trim_rows',
    'seed_content_download_fixtures',
    'seed_community_download_fixtures',
    'seed_quiz_fixtures',
    'seed_survey_fixtures',
    'content_download_fixtures',
    'community_download_fixtures',
    'quiz_fixtures',
    'survey_fixtures',
    'rich_fixtures',
    'sr_quiz_save_admin_quiz',
    'sr_survey_replace_questions',
    'sr_survey_replace_reward_policy',
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

sr_site_reset_check_contains('.tools/bin/seed-community-feed-fixture.php', [
    'SR_COMMUNITY_FEED_FIXTURE_ALLOW_MUTATION=1',
    'SR_COMMUNITY_FEED_FIXTURE_RUN_KEY',
    'SR_COMMUNITY_FEED_FIXTURE_REPLACE',
    'sr_community_feed_fixture_cleanup',
    'sr_community_feed_fixture_seed',
]);

sr_site_reset_check_contains('.tools/bin/measure-community-home-feed.php', [
    'SR_COMMUNITY_FEED_MEASURE_CONFIG',
    'sr_load_config()',
    'target-readonly',
]);

sr_site_reset_check_command(
    [
        PHP_BINARY,
        '.tools/bin/seed-community-feed-fixture.php',
        'seed',
        'sr369_guard',
    ],
    2,
    [
        'Refused: this tool creates or deletes fixture rows.',
        'SR_COMMUNITY_FEED_FIXTURE_ALLOW_MUTATION=1',
    ],
    'Community feed fixture mutation guard'
);

sr_site_reset_check_contains('docs/site-reset-and-fixtures.md', [
    '운영 DB에서 실행하는 절차가 아니다',
    'SR_SEED_ALLOW_MUTATION=1',
    'SR_SEED_SKIP_RICH_FIXTURES=1',
    '콘텐츠 파일 다운로드',
    '커뮤니티 첨부 다운로드',
    '퀴즈의 무료/포인트 보상/적립금 보상',
    '설문의 공개 무보상/회원 포인트 보상/회원 적립금 보상',
    'seed-dummy-http.php',
    'seed-community-feed-fixture.php',
    'SR_COMMUNITY_FEED_FIXTURE_ALLOW_MUTATION=1',
    'SR_COMMUNITY_FEED_MEASURE_CONFIG=1',
    '현재 DB가 로컬 또는 스테이징인지 확인한다',
]);

sr_site_reset_check_contains('docs/smoke-test.md', [
    '사이트 초기화와 더미 데이터 기준',
    'seed-dummy-http.php',
    'SR_SEED_ALLOW_MUTATION=1',
    'SR_SEED_SKIP_RICH_FIXTURES=1',
    '다운로드는 무료/포인트 차감/적립금 차감',
    '퀴즈와 설문은 보상 없음/포인트 보상/적립금 보상',
]);

if ($errors !== []) {
    fwrite(STDERR, "site reset fixture checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "site reset fixture checks completed.\n";
