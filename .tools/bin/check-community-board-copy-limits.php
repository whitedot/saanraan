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
    sr_community_board_copy_scope_values(['copy_scope' => ['all']]) === ['settings', 'posts_comments', 'attachments', 'series'],
    'Board copy all scope should expand to every item scope.'
);

sr_board_copy_limit_assert(
    sr_community_board_copy_normalized_values(['copy_scope' => ['settings']])['mode'] === 'settings',
    'Settings-only board copy scope should stay in settings mode.'
);

sr_board_copy_limit_assert(
    sr_community_board_copy_normalized_values(['copy_scope' => ['settings', 'posts_comments']])['mode'] === 'full',
    'Post/comment board copy scope should use the job path.'
);

sr_board_copy_limit_assert(
    in_array('첨부파일을 복사하려면 게시글+댓글도 함께 선택하세요.', sr_community_board_copy_scope_errors(['copy_scope' => ['attachments']]), true),
    'Attachment scope should require post/comment scope.'
);

sr_board_copy_limit_assert(
    in_array('시리즈를 복사하려면 게시글+댓글도 함께 선택하세요.', sr_community_board_copy_scope_errors(['copy_scope' => ['series']]), true),
    'Series scope should require post/comment scope.'
);

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
]));
foreach ([
    '현재 저장소 driver에서는 첨부파일 포함 복사를 지원하지 않습니다.',
    '원본 첨부파일을 확인할 수 없어 복사를 시작하지 않았습니다.',
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

$messages = sr_community_board_copy_batch_errors_for_values(sr_board_copy_limit_counts([
    'unsupported_storage' => true,
    'missing_files' => [12],
]), ['copy_scope' => ['settings', 'posts_comments']]);
sr_board_copy_limit_assert(
    $messages === [],
    'Board copy should ignore attachment storage blockers when attachments are not selected.'
);

$selectedCounts = sr_community_board_copy_counts_for_values(sr_board_copy_limit_counts([
    'unsupported_storage' => true,
    'missing_files' => [12],
]), ['copy_scope' => ['settings', 'posts_comments']]);
sr_board_copy_limit_assert(
    (int) ($selectedCounts['attachments'] ?? -1) === 0
        && (int) ($selectedCounts['bytes'] ?? -1) === 0
        && empty($selectedCounts['unsupported_storage'])
        && ($selectedCounts['missing_files'] ?? []) === [],
    'Board copy selected counts should clear attachment counts when attachments are not selected.'
);

$load = sr_community_board_copy_load_assessment(sr_board_copy_limit_counts([
    'posts' => 1,
    'comments' => 0,
    'attachments' => 0,
    'bytes' => 0,
]), ['copy_scope' => ['settings', 'posts_comments']], true);
sr_board_copy_limit_assert(
    ($load['grade'] ?? '') === 'low' && ($load['label'] ?? '') === '낮음',
    'Small board copy load grade should be low.'
);

$load = sr_community_board_copy_load_assessment(sr_board_copy_limit_counts([
    'posts' => 50,
    'comments' => 0,
    'attachments' => 0,
    'bytes' => 0,
]), ['copy_scope' => ['settings', 'posts_comments']], true);
sr_board_copy_limit_assert(
    ($load['grade'] ?? '') === 'caution',
    'Board copy load grade should become caution at the post caution threshold.'
);

$load = sr_community_board_copy_load_assessment(sr_board_copy_limit_counts([
    'posts' => 200,
    'comments' => 0,
    'attachments' => 0,
    'bytes' => 0,
]), ['copy_scope' => ['settings', 'posts_comments']], true);
sr_board_copy_limit_assert(
    ($load['grade'] ?? '') === 'high',
    'Board copy load grade should become high at the post high threshold.'
);

$load = sr_community_board_copy_load_assessment(sr_board_copy_limit_counts([
    'posts' => $limits['posts'] + 1,
    'comments' => 0,
    'attachments' => 0,
    'bytes' => 0,
]), ['copy_scope' => ['settings', 'posts_comments']], true);
sr_board_copy_limit_assert(
    ($load['grade'] ?? '') === 'very_high',
    'Board copy load grade should become very high when the batch review threshold is exceeded.'
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
