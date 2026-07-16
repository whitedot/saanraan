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
require_once 'modules/community/helpers/board-delete-jobs.php';

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

function sr_community_board_key_is_valid(string $boardKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $boardKey) === 1;
}

function sr_community_board_by_key(PDO $pdo, string $boardKey): ?array
{
    try {
        $stmt = $pdo->prepare('SELECT board_key FROM sr_community_boards WHERE board_key = :board_key LIMIT 1');
        $stmt->execute(['board_key' => $boardKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return null;
    }

    return is_array($row) ? $row : null;
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
    sr_community_board_delete_load_assessment(['posts' => 1, 'comments' => 0, 'attachments' => 0, 'series' => 0])['grade'] === 'low',
    'Board delete load should stay low for a single post.'
);

sr_board_copy_limit_assert(
    sr_community_board_delete_load_assessment(['posts' => 500, 'comments' => 0, 'attachments' => 0, 'series' => 0])['requires_batch_review'] === true,
    'Board delete load should require batch review at the delete job threshold.'
);

sr_board_copy_limit_assert(
    sr_community_board_copy_candidate_key('Bad Board Copy!') === 'bad_board_copy',
    'Board copy candidate key should normalize unsafe characters.'
);

sr_board_copy_limit_assert(
    strlen(sr_community_board_copy_key_with_suffix(str_repeat('a', 60), 12)) === 60
        && str_ends_with(sr_community_board_copy_key_with_suffix(str_repeat('a', 60), 12), '_12'),
    'Board copy suffixed key should stay within the board key length limit.'
);

if (class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $keyPdo = new PDO('sqlite::memory:');
    $keyPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $keyPdo->exec('CREATE TABLE sr_community_boards (id INTEGER PRIMARY KEY AUTOINCREMENT, board_key TEXT NOT NULL UNIQUE)');
    $keyPdo->exec('CREATE TABLE sr_community_board_copy_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, target_board_id INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL, options_json TEXT NULL)');
    $keyPdo->prepare('INSERT INTO sr_community_boards (board_key) VALUES (:board_key)')->execute(['board_key' => 'qa_board_copy']);
    $keyPdo->prepare('INSERT INTO sr_community_board_copy_jobs (target_board_id, status, options_json) VALUES (0, :status, :options_json)')->execute([
        'status' => 'pending',
        'options_json' => json_encode(['board_key' => 'qa_board_copy_2'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $reservedJobId = (int) $keyPdo->lastInsertId();
    sr_board_copy_limit_assert(
        sr_community_board_copy_unique_board_key($keyPdo, 'qa_board_copy') === 'qa_board_copy_3',
        'Board copy unique key should skip existing boards and reserved copy jobs.'
    );
    sr_board_copy_limit_assert(
        sr_community_board_copy_unique_board_key($keyPdo, 'qa_board_copy', $reservedJobId) === 'qa_board_copy_2',
        'Board copy unique key should ignore the current copy job reservation.'
    );
    sr_board_copy_limit_assert(
        sr_community_board_copy_suggestion(['board_key' => 'qa_board'], $keyPdo)['board_key'] === 'qa_board_copy_3',
        'Board copy suggestion should use the next available copy key.'
    );
} else {
    sr_board_copy_limit_error('PDO sqlite driver is required for board copy key fixture.');
}

$copyAction = is_file('modules/community/actions/admin-board-copy.php') ? file_get_contents('modules/community/actions/admin-board-copy.php') : false;
$copyView = is_file('modules/community/views/admin-board-copy.php') ? file_get_contents('modules/community/views/admin-board-copy.php') : false;
$boardListView = is_file('modules/community/views/admin-boards.php') ? file_get_contents('modules/community/views/admin-boards.php') : false;
$copyJobsView = is_file('modules/community/views/admin-board-copy-jobs.php') ? file_get_contents('modules/community/views/admin-board-copy-jobs.php') : false;
$copyJobsHelper = is_file('modules/community/helpers/board-copy-jobs.php') ? file_get_contents('modules/community/helpers/board-copy-jobs.php') : false;
$communityAdminCss = is_file('modules/community/assets/admin.css') ? file_get_contents('modules/community/assets/admin.css') : false;
sr_board_copy_limit_assert(is_string($copyAction) && str_contains($copyAction, "sr_redirect('/admin/community/boards/copy?id=' . (string) \$boardId);"), 'Board copy validation failure should return to the copy page.');
sr_board_copy_limit_assert(is_string($copyAction) && str_contains($copyAction, "sr_community_board_copy_values"), 'Board copy validation failure should preserve submitted values for the copy page.');
sr_board_copy_limit_assert(is_string($copyView) && str_contains($copyView, "/admin/community/board-copy-jobs"), 'Board copy form should link to the copy job list.');
sr_board_copy_limit_assert(is_string($boardListView) && str_contains($boardListView, "/admin/community/board-copy-jobs"), 'Community board list should link to the copy job list.');
sr_board_copy_limit_assert(is_string($copyJobsView) && str_contains($copyJobsView, "/admin/community/boards"), 'Board copy job list should link back to the board list.');
sr_board_copy_limit_assert(is_string($copyView) && str_contains($copyView, 'class="btn btn-outline-secondary"') && !str_contains($copyView, "sr_material_icon_html('history')"), 'Board copy job buttons should use secondary outline styling without the history icon.');
sr_board_copy_limit_assert(is_string($boardListView) && str_contains($boardListView, 'class="btn btn-ghost-secondary"') && !str_contains($boardListView, "sr_material_icon_html('history')"), 'Community board list copy job button should use secondary ghost styling without the history icon.');
sr_board_copy_limit_assert(is_string($copyJobsView) && str_contains($copyJobsView, 'class="btn btn-ghost-secondary"') && !str_contains($copyJobsView, "sr_material_icon_html('view_list')"), 'Board copy jobs board list button should use secondary ghost styling without an icon.');
sr_board_copy_limit_assert(is_string($copyView) && str_contains($copyView, "sr_e('작업 관리')") && !str_contains($copyView, "sr_e('복사 작업')"), 'Board copy buttons should say job management.');
sr_board_copy_limit_assert(is_string($boardListView) && str_contains($boardListView, "sr_e('작업 관리')") && !str_contains($boardListView, "sr_e('복사 작업')"), 'Community board list copy job button should say job management.');
sr_board_copy_limit_assert(is_string($copyJobsView) && str_contains($copyJobsView, '게시판 작업 관리') && !str_contains($copyJobsView, '게시판 배치 복사'), 'Board copy job page title should say board job management.');
sr_board_copy_limit_assert(is_string($copyJobsView) && str_contains($copyJobsView, 'card admin-list-card admin-list-form') && str_contains($copyJobsView, 'admin-list-summary-row'), 'Board copy job list should use the standard admin list card surface.');
sr_board_copy_limit_assert(is_string($copyJobsView) && str_contains($copyJobsView, 'badge-status <?php echo sr_e($communityBoardCopyJobStatusClass'), 'Board copy job status should use status badges.');
sr_board_copy_limit_assert(is_string($copyJobsView) && str_contains($copyJobsView, "['completed', 'cancelled']") && str_contains($copyJobsView, "'확인' : '계속하기'") && !str_contains($copyJobsView, "<?php echo sr_e('열기'); ?>"), 'Board copy job list manage button should use status-aware labels.');
sr_board_copy_limit_assert(is_string($copyJobsView) && str_contains($copyJobsView, "['pending', 'failed', 'paused']") && str_contains($copyJobsView, 'name="intent" value="cancel" class="btn btn-sm btn-outline-danger"'), 'Board copy job list should expose cancel actions for cancellable jobs.');
sr_board_copy_limit_assert(is_string($copyJobsView) && str_contains($copyJobsView, 'card admin-form ui-form-theme admin-community-board-copy-job-detail') && str_contains($copyJobsView, '<?php } else { ?>'), 'Board copy job detail should use the detail-only form surface without the recent jobs list.');
sr_board_copy_limit_assert(is_string($copyJobsView) && str_contains($copyJobsView, '작업 내용') && str_contains($copyJobsView, 'admin-community-board-copy-job-heading-badges'), 'Board copy job detail should use a concise content header with spaced badges.');
sr_board_copy_limit_assert(is_string($copyJobsView) && str_contains($copyJobsView, 'class="form-row"') && str_contains($copyJobsView, '<span class="form-label"><?php echo sr_e(\'상태\'); ?></span>') && !str_contains($copyJobsView, '<dl class="admin-community-board-copy-job-summary"'), 'Board copy job detail should use form-style rows instead of a definition list.');
sr_board_copy_limit_assert(is_string($copyJobsView) && str_contains($copyJobsView, 'form-sticky-actions form-actions form-actions-split admin-community-board-copy-job-actions'), 'Board copy job step actions should use sticky submit styling.');
sr_board_copy_limit_assert(is_string($copyJobsView) && str_contains($copyJobsView, 'admin-community-board-copy-job-action-left') && str_contains($copyJobsView, 'admin-community-board-copy-job-action-right'), 'Board copy job step actions should split destructive and primary actions.');
sr_board_copy_limit_assert(is_string($copyJobsView) && str_contains($copyJobsView, 'name="intent" value="cancel" class="btn btn-outline-danger"'), 'Board copy job cleanup action should use an outline danger button.');
sr_board_copy_limit_assert(is_string($copyJobsView) && str_contains($copyJobsView, "? '정리 다시 시도' : '현재 단계 계속'"), 'Board copy job run button should explain that it continues the current stage.');
sr_board_copy_limit_assert(is_string($copyJobsView) && str_contains($copyJobsView, 'admin-community-board-copy-job-summary-row'), 'Board copy job list summary row should have a dedicated spacing class.');
sr_board_copy_limit_assert(is_string($communityAdminCss) && str_contains($communityAdminCss, '.admin-community-board-copy-job-summary-row{padding-block:'), 'Board copy job list header badges should have vertical spacing.');
sr_board_copy_limit_assert(is_string($communityAdminCss) && str_contains($communityAdminCss, '.admin-community-board-copy-job-table .admin-table-actions-cell{') && str_contains($communityAdminCss, '.admin-community-board-copy-job-table .admin-row-actions{'), 'Board copy job list row actions should have screen-specific table spacing.');
sr_board_copy_limit_assert(is_string($communityAdminCss) && str_contains($communityAdminCss, '.admin-community-board-copy-job-detail>.form-row{'), 'Board copy job detail form rows should have screen-specific form layout styling.');
sr_board_copy_limit_assert(is_string($communityAdminCss) && str_contains($communityAdminCss, '.admin-community-board-copy-job-actions{gap:') && !str_contains($communityAdminCss, '.admin-community-board-copy-job-actions{border-top:'), 'Board copy job sticky submit should not draw a top divider.');
sr_board_copy_limit_assert(is_string($communityAdminCss) && str_contains($communityAdminCss, '.admin-community-board-copy-job-action-right{justify-content:flex-end;margin-left:auto}'), 'Board copy job primary step actions should align right.');
sr_board_copy_limit_assert(is_string($copyJobsHelper) && str_contains($copyJobsHelper, 'ORDER BY CASE WHEN j.status IN'), 'Board copy job list should prioritize unfinished or failed jobs.');
sr_board_copy_limit_assert(is_string($copyJobsHelper) && str_contains($copyJobsHelper, "SET thread_root_id = :thread_root_id") && str_contains($copyJobsHelper, "'thread_root_id' => \$newCommentId"), 'Copied comments must receive their own thread root id.');

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
