#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);

function sr_member_nicknames_table_exists(PDO $pdo): bool
{
    return false;
}

function sr_member_settings(PDO $pdo): array
{
    return [];
}

function sr_member_public_name(array $account, array $settings = [], string $fallback = '회원'): string
{
    $name = trim((string) ($account['display_name'] ?? ''));
    return $name !== '' ? $name : $fallback;
}

require_once $root . '/modules/content/helpers/comments.php';
require_once $root . '/modules/quiz/helpers/comments.php';
require_once $root . '/modules/survey/helpers/comments.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec("CREATE TABLE sr_member_accounts (id INTEGER PRIMARY KEY, display_name TEXT NOT NULL, status TEXT NOT NULL)");
$pdo->exec("INSERT INTO sr_member_accounts (id, display_name, status) VALUES (1, '작성자', 'active')");
foreach ([
    'content' => ['table' => 'sr_content_comments', 'foreign_key' => 'content_id'],
    'quiz' => ['table' => 'sr_quiz_comments', 'foreign_key' => 'quiz_id'],
    'survey' => ['table' => 'sr_survey_comments', 'foreign_key' => 'survey_id'],
] as $moduleKey => $definition) {
    $pdo->exec(
        'CREATE TABLE ' . $definition['table'] . ' (
            id INTEGER PRIMARY KEY,
            ' . $definition['foreign_key'] . ' INTEGER NOT NULL,
            parent_comment_id INTEGER NULL,
            thread_root_id INTEGER NULL,
            depth INTEGER NOT NULL,
            author_account_id INTEGER NULL,
            author_public_name_snapshot TEXT NOT NULL DEFAULT "",
            body_text TEXT NOT NULL,
            is_secret INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $insert = $pdo->prepare(
        'INSERT INTO ' . $definition['table'] . '
            (id, ' . $definition['foreign_key'] . ', parent_comment_id, thread_root_id, depth, author_account_id, author_public_name_snapshot, body_text, status, created_at, updated_at)
         VALUES (:id, 1, NULL, :thread_root_id, 1, 1, "작성자", :body_text, :status, "2026-07-14 00:00:00", "2026-07-14 00:00:00")'
    );
    for ($id = 1; $id <= 46; $id++) {
        $insert->execute([
            'id' => $id,
            'thread_root_id' => $id,
            'body_text' => $moduleKey . ' comment ' . (string) $id,
            'status' => $id === 46 ? 'hidden' : 'published',
        ]);
    }
}

foreach (['content', 'quiz', 'survey'] as $moduleKey) {
    $pageFunction = 'sr_' . $moduleKey . '_comment_page';
    $positionFunction = 'sr_' . $moduleKey . '_comment_page_for_comment';
    $page = $pageFunction($pdo, 1, 3, 20);
    $ids = array_map(static fn (array $comment): int => (int) ($comment['id'] ?? 0), (array) ($page['comments'] ?? []));
    $assert($ids === [41, 42, 43, 44, 45], $moduleKey . ' comment page must return rows after the first forty.');
    $assert((int) ($page['total'] ?? 0) === 45, $moduleKey . ' comment page must expose the full published count.');
    $assert((int) ($page['total_pages'] ?? 0) === 3, $moduleKey . ' comment page must expose all numeric pages.');
    $assert($positionFunction($pdo, 1, 41, 20) === 3, $moduleKey . ' new comment redirect must resolve its containing page.');
    $overflow = $pageFunction($pdo, 1, 999, 20);
    $assert((int) ($overflow['page'] ?? 0) === 3, $moduleKey . ' comment page must clamp an overflow request to the final page.');
}

$sourceChecks = [
    'modules/content/actions/view.php' => 'sr_content_comment_page(',
    'modules/quiz/theme/basic/view.php' => 'sr_quiz_comment_page(',
    'modules/quiz/skins/basic/view.php' => 'sr_quiz_comment_page(',
    'modules/survey/theme/basic/view.php' => 'sr_survey_comment_page(',
    'modules/survey/skins/basic/view.php' => 'sr_survey_comment_page(',
    'modules/content/theme/basic/content.php' => 'sr_public_pagination_html($contentCommentPage',
    'modules/content/views/content.php' => 'sr_public_pagination_html($contentCommentPage',
    'modules/quiz/theme/basic/view.php#pagination' => 'sr_public_pagination_html($quizCommentPage',
    'modules/quiz/skins/basic/view.php#pagination' => 'sr_public_pagination_html($quizCommentPage',
    'modules/survey/theme/basic/view.php#pagination' => 'sr_public_pagination_html($surveyCommentPage',
    'modules/survey/skins/basic/view.php#pagination' => 'sr_public_pagination_html($surveyCommentPage',
];
foreach ($sourceChecks as $sourceKey => $marker) {
    $file = explode('#', $sourceKey, 2)[0];
    $contents = file_get_contents($root . '/' . $file);
    $assert(is_string($contents) && str_contains($contents, $marker), $file . ' must use public comment pagination marker: ' . $marker);
}

foreach ([
    'modules/content/theme/basic/content.php',
    'modules/content/views/content.php',
    'modules/quiz/theme/basic/view.php',
    'modules/quiz/skins/basic/view.php',
    'modules/survey/theme/basic/view.php',
    'modules/survey/skins/basic/view.php',
] as $commentViewPath) {
    $contents = file_get_contents($root . '/' . $commentViewPath);
    $assert(
        is_string($contents)
            && str_contains($contents, "'compact_edges' => true")
            && str_contains($contents, "'link_class' => 'btn btn-ghost-default'")
            && str_contains($contents, "'current_class' => 'btn btn-solid-primary'"),
        $commentViewPath . ' must render the community-style compact numeric pagination surface.'
    );
}

foreach (['content', 'quiz', 'survey'] as $moduleKey) {
    $action = file_get_contents($root . '/modules/' . $moduleKey . '/actions/comment.php');
    $assert(
        is_string($action)
            && str_contains($action, "sr_{$moduleKey}_comment_page_for_comment("),
        $moduleKey . ' comment action must resolve the saved comment page before redirecting.'
    );
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, '[FAIL] ' . $error . PHP_EOL);
    }
    exit(1);
}

echo "Public comment pagination checks completed.\n";
