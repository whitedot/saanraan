#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once 'modules/community/helpers/attachments.php';
require_once 'modules/community/helpers/board-copy.php';

$errors = [];

function sr_board_copy_limit_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_board_copy_limit_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_board_copy_limit_error($message);
    }
}

function sr_board_copy_limit_counts(array $overrides = []): array
{
    return array_merge([
        'posts' => 10,
        'comments' => 20,
        'attachments' => 3,
        'bytes' => 4096,
        'unsupported_storage' => false,
        'missing_files' => [],
        'legacy_link_card_tokens' => 0,
        'series' => 0,
        'series_items' => 0,
    ], $overrides);
}

$limits = sr_community_board_copy_limits();
foreach ([
    'posts' => 500,
    'comments' => 5000,
    'attachments' => 500,
    'bytes' => 314572800,
] as $key => $expected) {
    sr_board_copy_limit_assert(($limits[$key] ?? null) === $expected, 'Unexpected board copy limit for ' . $key);
}

sr_board_copy_limit_assert(
    sr_community_board_copy_batch_threshold_errors(sr_board_copy_limit_counts([
        'posts' => $limits['posts'],
        'comments' => $limits['comments'],
        'attachments' => $limits['attachments'],
        'bytes' => $limits['bytes'],
    ])) === [],
    'Board copy threshold should allow values exactly at the sync limit.'
);

foreach ([
    'posts' => '동기 복사 상한을 초과했습니다: posts',
    'comments' => '동기 복사 상한을 초과했습니다: comments',
    'attachments' => '동기 복사 상한을 초과했습니다: attachments',
] as $key => $expectedMessage) {
    $messages = sr_community_board_copy_batch_threshold_errors(sr_board_copy_limit_counts([
        $key => $limits[$key] + 1,
    ]));
    sr_board_copy_limit_assert(in_array($expectedMessage, $messages, true), 'Board copy threshold error missing for ' . $key);
}

$messages = sr_community_board_copy_batch_threshold_errors(sr_board_copy_limit_counts([
    'bytes' => $limits['bytes'] + 1,
]));
sr_board_copy_limit_assert(in_array('첨부 총량이 동기 복사 상한을 초과했습니다.', $messages, true), 'Board copy byte threshold error missing.');

$messages = sr_community_board_copy_batch_block_errors(sr_board_copy_limit_counts([
    'unsupported_storage' => true,
    'missing_files' => [12],
    'legacy_link_card_tokens' => 1,
]));
foreach ([
    '현재 저장소 driver에서는 첨부파일 포함 복사를 지원하지 않습니다.',
    '원본 첨부파일을 확인할 수 없어 복사를 시작하지 않았습니다.',
    'legacy 링크 카드 토큰이 남아 있는 게시글이 있어 게시판 복사를 시작하지 않았습니다. 해당 게시글 본문에서 토큰을 제거한 뒤 다시 시도하세요.',
] as $expectedMessage) {
    sr_board_copy_limit_assert(in_array($expectedMessage, $messages, true), 'Board copy block error missing: ' . $expectedMessage);
}

$messages = sr_community_board_copy_limit_errors(sr_board_copy_limit_counts([
    'posts' => $limits['posts'] + 1,
    'unsupported_storage' => true,
]));
sr_board_copy_limit_assert(count($messages) === 2, 'Board copy full limit errors should include threshold and block errors.');

$messages = sr_community_board_copy_batch_errors(sr_board_copy_limit_counts([
    'posts' => $limits['posts'] + 1,
]));
sr_board_copy_limit_assert($messages === [], 'Board copy batch should be available when only the sync threshold is exceeded.');

$messages = sr_community_board_copy_batch_errors(sr_board_copy_limit_counts());
sr_board_copy_limit_assert(
    $messages === [],
    'Board copy job path should be available for small full-copy jobs.'
);

$messages = sr_community_board_copy_batch_errors(sr_board_copy_limit_counts([
    'unsupported_storage' => true,
    'posts' => $limits['posts'] + 1,
]));
sr_board_copy_limit_assert(
    $messages === ['현재 저장소 driver에서는 첨부파일 포함 복사를 지원하지 않습니다.'],
    'Board copy batch should prioritize hard block errors over threshold state.'
);

$warnings = sr_community_board_copy_storage_warnings(sr_board_copy_limit_counts(['bytes' => 1048576]));
sr_board_copy_limit_assert(
    isset($warnings[0]) && str_contains($warnings[0], '1.0 MB') && str_contains($warnings[0], '여유 공간'),
    'Board copy storage warning should include formatted attachment size.'
);

$warnings = sr_community_board_copy_storage_warnings(sr_board_copy_limit_counts(['bytes' => 0]));
sr_board_copy_limit_assert(
    isset($warnings[0]) && str_contains($warnings[0], 'DB 용량'),
    'Board copy storage warning should mention DB capacity when no attachments are present.'
);

if ($errors !== []) {
    fwrite(STDERR, "community board copy limit checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "community board copy limit checks completed.\n";
