#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}
require_once $root . '/modules/content/helpers.php';
require_once $root . '/modules/quiz/helpers.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};
$source = static function (string $file) use ($root, &$errors): string {
    $contents = file_get_contents($root . '/' . $file);
    if (!is_string($contents)) {
        $errors[] = 'cannot read public sidebar source: ' . $file;
        return '';
    }
    return $contents;
};
$contains = static function (string $file, array $markers) use ($source, $assert): void {
    $contents = $source($file);
    foreach ($markers as $marker) {
        $assert(str_contains($contents, $marker), $file . ' missing public sidebar marker: ' . $marker);
    }
};

$contains('modules/content/module.php', [
    "'sidebar_enabled' => true",
    "'sidebar_menu_type' => 'groups'",
    "'sidebar_popular_limit' => 5",
    "'sidebar_comments_limit' => 5",
]);
$contains('modules/content/actions/admin-settings.php', [
    "sr_post_string('sidebar_enabled'",
    "sr_post_string('sidebar_menu_type'",
    "sr_admin_post_int_in_range('sidebar_popular_limit', 1, 10)",
    "sr_admin_post_int_in_range('sidebar_comments_limit', 1, 10)",
]);
$contains('modules/content/views/admin-settings.php', [
    '콘텐츠 메인을 제외한 그룹·검색 목록, 콘텐츠 읽기, 회원 콘텐츠 작성 화면',
    "'sidebar_menu_type'",
    'name="sidebar_popular_limit"',
    'name="sidebar_comments_limit"',
    'data-content-sidebar-site-menu-row',
    "selected.value === 'site_menu'",
    'select.required = active',
]);
$contains('modules/content/helpers/sidebar.php', [
    'function sr_content_sidebar_context(',
    "p.status = 'published'",
    'p.asset_access_enabled <> 1 OR p.asset_access_amount <= 0',
    'c.is_secret = 0',
    'sr_content_comment_body_plain_text(',
]);
$contains('modules/content/theme/basic/sidebar.php', [
    '<aside class="content-sidebar"',
    '인기 콘텐츠',
    '최신댓글',
    "'point_key' => 'content.sidebar.summary'",
]);
foreach ([
    'modules/content/views/group.php',
    'modules/content/views/search.php',
    'modules/content/views/content.php',
    'modules/content/views/account-content.php',
    'modules/content/theme/basic/group.php',
    'modules/content/theme/basic/content.php',
] as $screenFile) {
    $contains($screenFile, ['sidebar.php']);
}
foreach (['modules/content/views/home.php', 'modules/content/theme/basic/home.php'] as $homeFile) {
    $assert(!str_contains($source($homeFile), 'sidebar.php'), $homeFile . ' must not render the content sidebar.');
}
$contains('modules/content/theme/basic/assets/module.css', [
    '.content-screen-frame',
    '.content-sidebar',
    'var(--sr-text',
    'var(--sr-muted',
    '@media (max-width: 1024px)',
]);
$contains('modules/content/helpers.php', ["'content.form'", 'sidebar_enabled', 'sidebar_menu_type']);

$excerpt = sr_content_sidebar_excerpt('<p>태그 <strong>제거</strong></p>', 72);
$assert($excerpt === '태그 제거', 'content sidebar excerpt must remove HTML tags.');

$contains('modules/quiz/module.php', [
    "'sidebar_enabled' => true",
    "'sidebar_menu_type' => 'groups'",
    "'sidebar_popular_limit' => 5",
    "'sidebar_comments_limit' => 5",
]);
$contains('modules/quiz/actions/admin-settings.php', [
    "sr_post_string('sidebar_menu_type'",
    "sr_admin_post_int_in_range('sidebar_popular_limit', 1, 10)",
    "sr_admin_post_int_in_range('sidebar_comments_limit', 1, 10)",
]);
$contains('modules/quiz/views/admin-settings.php', [
    '퀴즈 메인을 제외한 전체 목록과 퀴즈 풀이·결과 화면',
    'data-quiz-settings-sidebar-menu-type',
    'sidebarSiteMenu.required = active',
]);
$contains('modules/quiz/helpers/sidebar.php', [
    'function sr_quiz_sidebar_context(',
    "q.status = 'active'",
    'q.comments_enabled = 1',
    'c.is_secret = 0',
    'viewer_attempt.submitted_at IS NOT NULL',
    'sr_quiz_comment_body_plain_text(',
]);
$contains('modules/quiz/views/sidebar.php', [
    '<aside class="quiz-sidebar"',
    '인기 퀴즈',
    '최신댓글',
    "'point_key' => 'quiz.sidebar.summary'",
]);
foreach ([
    'modules/quiz/theme/basic/home.php',
    'modules/quiz/skins/basic/home.php',
    'modules/quiz/theme/basic/view.php',
    'modules/quiz/skins/basic/view.php',
] as $screenFile) {
    $contains($screenFile, ['quiz-screen-frame', 'views/sidebar.php']);
}
$contains('modules/quiz/theme/basic/home.php', ['if ($quizScreenIsList)', "'quiz.sidebar.summary'"]);
$contains('modules/quiz/skins/basic/home.php', ['if ($quizScreenIsList)', "'quiz.sidebar.summary'"]);
$contains('modules/quiz/theme/basic/view.php', ['if (!$quizEmbedded)', "'quiz.sidebar.summary'"]);
$contains('modules/quiz/theme/basic/assets/module.css', [
    '.quiz-screen-frame',
    '.quiz-sidebar',
    'var(--sr-text',
    'var(--sr-muted',
    '@media (max-width: 1024px)',
]);
$contains('modules/quiz/actions/list.php', [
    "sr_get_string('group', 64)",
    'sr_quiz_group_by_key(',
    'sr_quiz_public_quiz_count($pdo, $quizListGroupId)',
]);
$quizExcerpt = sr_quiz_sidebar_excerpt('<p>태그 <strong>제거</strong></p>', 72);
$assert($quizExcerpt === '태그 제거', 'quiz sidebar excerpt must remove HTML tags.');

if ($errors !== []) {
    fwrite(STDERR, "public module sidebar checks failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "public module sidebar checks completed.\n";
