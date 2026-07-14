#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];
$source = static function (string $file) use ($root, &$errors): string {
    $contents = file_get_contents($root . '/' . $file);
    if (!is_string($contents)) {
        $errors[] = 'cannot read admin comment source: ' . $file;
        return '';
    }

    return $contents;
};
$assertContains = static function (string $file, array $markers) use ($source, &$errors): void {
    $contents = $source($file);
    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $errors[] = $file . ' missing admin comment pagination marker: ' . $marker;
        }
    }
};

foreach (['quiz' => '/admin/quiz/comments', 'survey' => '/admin/surveys/comments'] as $moduleKey => $path) {
    $action = 'modules/' . $moduleKey . '/actions/admin-comments.php';
    $helper = 'modules/' . $moduleKey . '/helpers/comments.php';
    $label = $moduleKey === 'quiz' ? '퀴즈' : '설문';
    $assertContains($helper, [
        'function sr_' . $moduleKey . '_admin_comment_count(',
        'int $limit = 100, int $offset = 0',
        'LIMIT :limit_value OFFSET :offset_value',
    ]);
    $assertContains($action, [
        'sr_admin_pagination_from_total($pdo, sr_' . $moduleKey . '_admin_comment_count(',
        'sr_admin_pagination_offset($commentPagination)',
        'sr_admin_pagination_summary_html($commentPagination)',
        "sr_admin_pagination_html(\$commentPagination, '" . $label . " 댓글 목록 페이지')",
        $path,
    ]);
}

function sr_member_settings(PDO $pdo): array
{
    return [];
}

function sr_member_public_name(array $account, array $settings = [], string $fallback = '회원'): string
{
    return (string) (($account['display_name'] ?? '') ?: $fallback);
}

require_once $root . '/modules/quiz/helpers/comments.php';
require_once $root . '/modules/survey/helpers/comments.php';
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE sr_member_accounts (id INTEGER PRIMARY KEY, display_name TEXT, status TEXT)');
$pdo->exec("INSERT INTO sr_member_accounts VALUES (1, 'Member', 'active')");
$pdo->exec('CREATE TABLE sr_quiz_sets (id INTEGER PRIMARY KEY, quiz_key TEXT, title TEXT)');
$pdo->exec("INSERT INTO sr_quiz_sets VALUES (1, 'quiz', 'Quiz')");
$pdo->exec('CREATE TABLE sr_survey_forms (id INTEGER PRIMARY KEY, survey_key TEXT, title TEXT)');
$pdo->exec("INSERT INTO sr_survey_forms VALUES (1, 'survey', 'Survey')");
foreach (['quiz' => 'quiz_id', 'survey' => 'survey_id'] as $moduleKey => $ownerColumn) {
    $pdo->exec(
        'CREATE TABLE sr_' . $moduleKey . '_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ' . $ownerColumn . ' INTEGER NOT NULL,
            author_account_id INTEGER NOT NULL,
            author_public_name_snapshot TEXT NOT NULL,
            body_text TEXT NOT NULL,
            is_secret INTEGER NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $insert = $pdo->prepare(
        'INSERT INTO sr_' . $moduleKey . '_comments
         (' . $ownerColumn . ", author_account_id, author_public_name_snapshot, body_text, is_secret, status, created_at)
         VALUES (1, 1, 'Member', :body_text, 0, 'published', :created_at)"
    );
    for ($rowNumber = 1; $rowNumber <= 45; $rowNumber++) {
        $insert->execute([
            'body_text' => ucfirst($moduleKey) . ' comment ' . (string) $rowNumber,
            'created_at' => sprintf('2026-01-01 00:00:%02d', $rowNumber % 60),
        ]);
    }
}

if (sr_quiz_admin_comment_count($pdo) !== 45 || sr_survey_admin_comment_count($pdo) !== 45) {
    $errors[] = 'admin comment counts must include every matching row';
}
$quizFinalPage = sr_quiz_admin_comments($pdo, [], 20, 40);
if (count($quizFinalPage) !== 5 || (int) ($quizFinalPage[0]['id'] ?? 0) !== 5 || (int) ($quizFinalPage[4]['id'] ?? 0) !== 1) {
    $errors[] = 'quiz admin comments must expose the final partial page';
}
$surveySecondPage = sr_survey_admin_comments($pdo, [], 20, 20);
if (count($surveySecondPage) !== 20 || (int) ($surveySecondPage[0]['id'] ?? 0) !== 25 || (int) ($surveySecondPage[19]['id'] ?? 0) !== 6) {
    $errors[] = 'survey admin comments must return the requested ordered slice';
}
if (sr_quiz_admin_comment_count($pdo, ['q' => 'comment 45']) !== 1 || sr_survey_admin_comment_count($pdo, ['status' => 'hidden']) !== 0) {
    $errors[] = 'admin comment counts must apply the same filters as row queries';
}

if ($errors !== []) {
    fwrite(STDERR, "admin comment pagination checks failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "admin comment pagination checks completed.\n";
