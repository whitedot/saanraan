#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

function sr_community_guest_author_select(PDO $pdo, string $tableName, string $alias): string
{
    return ', ' . $alias . '.guest_author_name, ' . $alias . '.guest_password_hash';
}

function sr_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sr_url(string $path): string
{
    return $path;
}

require_once $root . '/modules/community/helpers/posts-comments.php';

$errors = [];

function sr_community_comment_pagination_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

function sr_community_comment_pagination_ids(array $page): array
{
    return array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), (array) ($page['comments'] ?? []));
}

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$pdo->exec("CREATE TABLE sr_member_accounts (id INTEGER PRIMARY KEY, display_name TEXT NOT NULL DEFAULT '', status TEXT NOT NULL)");
$pdo->exec(
    'CREATE TABLE sr_community_comments (
        id INTEGER PRIMARY KEY,
        post_id INTEGER NOT NULL,
        parent_comment_id INTEGER NULL,
        thread_root_id INTEGER NULL,
        depth INTEGER NOT NULL,
        author_account_id INTEGER NULL,
        author_public_name_snapshot TEXT NOT NULL DEFAULT \'\',
        guest_author_name TEXT NOT NULL DEFAULT \'\',
        guest_password_hash TEXT NULL,
        body_text TEXT NOT NULL,
        extra_values_json TEXT NULL,
        is_secret INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )'
);
$pdo->exec('CREATE INDEX idx_sr_community_comments_thread ON sr_community_comments (post_id, status, thread_root_id, depth, id)');
$pdo->exec("INSERT INTO sr_member_accounts (id, status) VALUES (10, 'active')");
$insert = $pdo->prepare(
    'INSERT INTO sr_community_comments
        (id, post_id, parent_comment_id, thread_root_id, depth, author_account_id, author_public_name_snapshot, body_text, status, created_at, updated_at)
     VALUES
        (:id, 1, :parent_comment_id, :thread_root_id, :depth, 10, :author_name, :body_text, :status, :created_at, :updated_at)'
);
$rows = [
    [1, null, 1, 1, 'published'],
    [2, 1, 1, 2, 'published'],
    [3, 2, 1, 3, 'published'],
    [4, null, 4, 1, 'published'],
    [5, 4, 4, 2, 'published'],
    [6, null, 6, 1, 'published'],
    [7, null, 7, 1, 'hidden'],
    [8, null, 8, 1, 'published'],
];
foreach ($rows as [$id, $parentCommentId, $threadRootId, $depth, $status]) {
    $insert->execute([
        'id' => $id,
        'parent_comment_id' => $parentCommentId,
        'thread_root_id' => $threadRootId,
        'depth' => $depth,
        'author_name' => '작성자 ' . $id,
        'body_text' => '댓글 ' . $id,
        'status' => $status,
        'created_at' => '2026-07-14 12:00:00',
        'updated_at' => '2026-07-14 12:00:00',
    ]);
}

$threadPlan = $pdo->query(
    "EXPLAIN QUERY PLAN
     SELECT id
     FROM sr_community_comments
     WHERE post_id = 1
       AND status = 'published'
     ORDER BY thread_root_id ASC, depth ASC, id ASC
     LIMIT 20"
)->fetchAll(PDO::FETCH_ASSOC);
$threadPlanText = implode("\n", array_map(static fn (array $row): string => implode('|', array_map('strval', $row)), $threadPlan));
sr_community_comment_pagination_assert(str_contains($threadPlanText, 'idx_sr_community_comments_thread'), 'Comment page query plan must use the thread ordering index.');
sr_community_comment_pagination_assert(!str_contains($threadPlanText, 'USE TEMP B-TREE'), 'Comment page query plan must not use a temporary ordering tree.');

$firstPage = sr_community_post_comment_page($pdo, 1, 1, 2);
sr_community_comment_pagination_assert(sr_community_comment_pagination_ids($firstPage) === [1, 2], 'First comment page must return the first two threaded rows.');
sr_community_comment_pagination_assert((int) ($firstPage['total_pages'] ?? 0) === 4, 'Comment pagination must expose the total page count.');
sr_community_comment_pagination_assert(!empty($firstPage['has_next']) && empty($firstPage['has_previous']), 'First comment page must expose only a next page.');
$defaultPage = sr_community_post_comment_page($pdo, 1);
sr_community_comment_pagination_assert((int) ($defaultPage['per_page'] ?? 0) === 20, 'Comment pagination must use the performance-oriented default page size of 20.');

$secondPage = sr_community_post_comment_page($pdo, 1, 2, 2);
sr_community_comment_pagination_assert(sr_community_comment_pagination_ids($secondPage) === [3, 4], 'Second comment page must continue without gaps or duplicates.');

$thirdPage = sr_community_post_comment_page($pdo, 1, 3, 2);
sr_community_comment_pagination_assert(sr_community_comment_pagination_ids($thirdPage) === [5, 6], 'Third comment page must preserve depth and id ordering.');

$fourthPage = sr_community_post_comment_page($pdo, 1, 4, 2);
sr_community_comment_pagination_assert(sr_community_comment_pagination_ids($fourthPage) === [8], 'Numeric pagination must retain the final normalized root comment.');
sr_community_comment_pagination_assert(empty($fourthPage['has_next']) && !empty($fourthPage['has_previous']), 'Final comment page must expose only a previous page.');

$overflowPage = sr_community_post_comment_page($pdo, 1, 999, 2);
sr_community_comment_pagination_assert(sr_community_comment_pagination_ids($overflowPage) === [8], 'A page above the final page must clamp to the final page.');
sr_community_comment_pagination_assert((int) ($overflowPage['page'] ?? 0) === 4, 'Clamped comment pagination must report the effective page.');
sr_community_comment_pagination_assert(sr_community_post_published_comment_count($pdo, 1) === 7, 'Published comment count must include rows beyond the first page and exclude hidden rows.');
sr_community_comment_pagination_assert(sr_community_comment_page_for_comment($pdo, 1, 5, 2) === 3, 'Comment creation must resolve the numeric page containing the new row.');
sr_community_comment_pagination_assert(sr_community_comment_page_for_comment($pdo, 1, 1, 2) === 1, 'The first comment must resolve to page one.');
$paginationHtml = sr_community_comment_pagination_html(1, $thirdPage);
sr_community_comment_pagination_assert(str_contains($paginationHtml, 'comment_page=2'), 'Pagination HTML must link numeric pages.');
sr_community_comment_pagination_assert(str_contains($paginationHtml, 'data-community-comment-page="2"'), 'Pagination links must expose the asynchronous paging hook.');
sr_community_comment_pagination_assert(str_contains($paginationHtml, 'aria-current="page">3</span>'), 'Pagination HTML must mark the active page.');

$viewAction = file_get_contents($root . '/modules/community/actions/view.php');
$commentAction = file_get_contents($root . '/modules/community/actions/comment.php');
$moduleDefinition = file_get_contents($root . '/modules/community/module.php');
$commentHelpers = file_get_contents($root . '/modules/community/helpers/posts-comments.php');
$threadIndexUpdate = file_get_contents($root . '/modules/community/updates/2026.07.009.sql');
foreach ([$viewAction, $commentAction, $moduleDefinition, $commentHelpers, $threadIndexUpdate] as $contents) {
    sr_community_comment_pagination_assert(is_string($contents), 'Community comment action source must be readable.');
}
sr_community_comment_pagination_assert(str_contains((string) $moduleDefinition, "'comments_per_page' => 20"), 'Community module settings must default to 20 comments per page.');
sr_community_comment_pagination_assert(str_contains((string) $viewAction, 'sr_community_post_comment_page('), 'Post view action must use the numeric page helper.');
sr_community_comment_pagination_assert(str_contains((string) $viewAction, "\$post['published_comment_count']"), 'Post view action must expose the full published comment count.');
sr_community_comment_pagination_assert(str_contains((string) $viewAction, 'sr_community_board_comments_per_page('), 'Post view action must resolve the board-priority page size.');
sr_community_comment_pagination_assert(str_contains((string) $viewAction, "sr_get_string('comment_fragment'"), 'Post view action must recognize asynchronous comment page requests.');
sr_community_comment_pagination_assert(str_contains((string) $viewAction, 'sr_finish_response();'), 'Comment page requests must finish through the standard response helper.');
$fragmentBranchPosition = strpos((string) $viewAction, 'if ($communityCommentFragmentRequest)');
$attachmentLoadPosition = strpos((string) $viewAction, 'sr_community_post_attachments(');
sr_community_comment_pagination_assert(
    $fragmentBranchPosition !== false && $attachmentLoadPosition !== false && $fragmentBranchPosition < $attachmentLoadPosition,
    'Comment fragment responses must finish before post attachment and series rendering data is loaded.'
);
sr_community_comment_pagination_assert(str_contains((string) $commentAction, 'sr_community_comment_page_for_comment('), 'Comment creation must redirect to the numeric page containing the new comment.');
sr_community_comment_pagination_assert(str_contains((string) $commentHelpers, 'ORDER BY c.thread_root_id ASC, c.depth ASC, c.id ASC'), 'Comment page ordering must match the thread index columns.');
sr_community_comment_pagination_assert(!str_contains((string) $commentHelpers, 'ORDER BY COALESCE(c.thread_root_id, c.id)'), 'Comment page ordering must not hide the indexed thread root behind an expression.');
sr_community_comment_pagination_assert(str_contains((string) $threadIndexUpdate, 'WHERE c.thread_root_id IS NULL;'), 'Comment thread update must repair replies from their parent thread root.');
sr_community_comment_pagination_assert(str_contains((string) $threadIndexUpdate, 'ADD KEY idx_sr_community_comments_thread (post_id, status, thread_root_id, depth, id)'), 'Comment thread update must install the ordering index.');
foreach ([
    'modules/community/skins/basic/view.php',
    'modules/community/theme/basic/post.php',
] as $viewFile) {
    $view = file_get_contents($root . '/' . $viewFile);
    sr_community_comment_pagination_assert(is_string($view) && str_contains($view, 'sr_community_comment_pagination_html('), $viewFile . ' must render numeric comment pagination.');
    sr_community_comment_pagination_assert(is_string($view) && str_contains($view, 'name="comment_page"'), $viewFile . ' must preserve the active numeric page on validation failure.');
    sr_community_comment_pagination_assert(is_string($view) && str_contains($view, '$communityCommentFragmentResponse'), $viewFile . ' must suppress the post layout for a comments-only response.');
}
$moduleScript = file_get_contents($root . '/modules/community/assets/module.js');
sr_community_comment_pagination_assert(is_string($moduleScript) && str_contains($moduleScript, "searchParams.set('comment_fragment', '1')"), 'Community module JavaScript must request comment pages without full navigation.');
sr_community_comment_pagination_assert(is_string($moduleScript) && str_contains($moduleScript, 'commentsSection.replaceWith'), 'Community module JavaScript must replace only the comments section.');

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, '[FAIL] ' . $error . PHP_EOL);
    }
    exit(1);
}

echo "Community comment pagination check passed.\n";
