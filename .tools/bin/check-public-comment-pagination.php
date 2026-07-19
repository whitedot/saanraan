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

require_once $root . '/core/helpers/runtime.php';
require_once $root . '/core/helpers/output.php';
require_once $root . '/core/helpers/storage.php';
require_once $root . '/modules/member/helpers/profile.php';
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
$pdo->exec("INSERT INTO sr_member_accounts (id, display_name, status) VALUES (1, '작성자', 'active'), (2, '탈퇴회원', 'withdrawn')");
$pdo->exec("CREATE TABLE sr_member_profiles (account_id INTEGER PRIMARY KEY, profile_image_path TEXT NOT NULL)");
$avatarReference = 'local:member/profile-images/2026/07/' . str_repeat('a', 32) . '.jpg';
$avatarInsert = $pdo->prepare('INSERT INTO sr_member_profiles (account_id, profile_image_path) VALUES (:account_id, :profile_image_path)');
$avatarInsert->execute(['account_id' => 1, 'profile_image_path' => $avatarReference]);
$avatarInsert->execute(['account_id' => 2, 'profile_image_path' => $avatarReference]);
$avatarSources = sr_member_public_profile_image_sources($pdo, [1, 2, 1, 0]);
$assert(isset($avatarSources[1]) && str_contains((string) $avatarSources[1], '/member/profile-image?file='), 'Public avatar lookup must return a valid uploaded avatar for an active account.');
$assert(!isset($avatarSources[2]), 'Public avatar lookup must exclude withdrawn accounts.');
$avatarHtml = sr_member_public_profile_image_html((string) ($avatarSources[1] ?? ''), 'fixture-avatar invalid"class');
$assert(str_contains($avatarHtml, 'member-profile-image') && str_contains($avatarHtml, 'fixture-avatar'), 'Public avatar markup must keep its base and valid module class.');
$assert(str_contains($avatarHtml, 'member-profile-image-size-medium') && str_contains($avatarHtml, 'width="32" height="32"'), 'Public avatar markup must use the medium size contract by default.');
$assert(!str_contains($avatarHtml, 'invalid"class'), 'Public avatar markup must discard unsafe module classes.');
$assert(sr_member_public_profile_image_html('', 'fixture-avatar') === '', 'Public avatar markup must stay absent when neither an uploaded avatar nor a fallback label exists.');
$fallbackAvatarHtml = sr_member_public_profile_image_html('', 'fixture-avatar', 'medium', ' 작성자 ');
$assert(
    str_contains($fallbackAvatarHtml, 'member-profile-image-fallback')
        && str_contains($fallbackAvatarHtml, 'member-default-avatar')
        && str_contains($fallbackAvatarHtml, '>작</span>')
        && str_contains($fallbackAvatarHtml, 'member-avatar-color-'),
    'Public avatar markup must render the escaped first display-name character in a stable colored fallback circle.'
);
$smallAvatarHtml = sr_member_public_profile_image_html((string) ($avatarSources[1] ?? ''), 'fixture-avatar', 'small');
$largeAvatarHtml = sr_member_public_profile_image_html((string) ($avatarSources[1] ?? ''), 'fixture-avatar', 'large');
$customSmallAvatarHtml = sr_member_public_profile_image_html((string) ($avatarSources[1] ?? ''), 'fixture-avatar', 'small', '', 29);
$assert(str_contains($smallAvatarHtml, 'member-profile-image-size-small') && str_contains($smallAvatarHtml, 'width="24" height="24"'), 'Small public avatar markup must use the 24px size contract.');
$assert(str_contains($largeAvatarHtml, 'member-profile-image-size-large') && str_contains($largeAvatarHtml, 'width="40" height="40"'), 'Large public avatar markup must use the 40px size contract.');
$assert(str_contains($customSmallAvatarHtml, 'width="29" height="29"') && str_contains($customSmallAvatarHtml, '--member-profile-image-size: 29px'), 'Public avatar markup must apply the configured usage-tier pixels to attributes and CSS.');
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
    'modules/community/theme/basic/list.php#list-profile-image' => 'community-list-author-profile-image',
    'modules/community/skins/basic/list.php#list-profile-image' => 'community-list-author-profile-image',
    'modules/community/theme/basic/post.php#post-avatar' => 'community-post-author-avatar',
    'modules/community/theme/basic/post.php#comment-avatar' => 'community-comment-author-avatar',
    'modules/community/skins/basic/view.php#post-avatar' => 'community-post-author-avatar',
    'modules/community/skins/basic/view.php#comment-avatar' => 'community-comment-author-avatar',
    'modules/content/actions/view.php' => 'sr_content_comment_page(',
    'modules/content/theme/basic/content.php#post-avatar' => 'content-post-author-avatar',
    'modules/content/theme/basic/content.php#comment-avatar' => 'content-comment-author-avatar',
    'modules/content/views/content.php#post-avatar' => 'content-post-author-avatar',
    'modules/content/views/content.php#comment-avatar' => 'content-comment-author-avatar',
    'modules/quiz/theme/basic/view.php' => 'sr_quiz_comment_page(',
    'modules/quiz/theme/basic/view.php#post-avatar' => 'sr-quiz-post-author-avatar',
    'modules/quiz/theme/basic/view.php#comment-avatar' => 'sr-quiz-comment-author-avatar',
    'modules/quiz/skins/basic/view.php' => 'sr_quiz_comment_page(',
    'modules/quiz/skins/basic/view.php#post-avatar' => 'sr-quiz-post-author-avatar',
    'modules/quiz/skins/basic/view.php#comment-avatar' => 'sr-quiz-comment-author-avatar',
    'modules/survey/theme/basic/view.php' => 'sr_survey_comment_page(',
    'modules/survey/theme/basic/view.php#post-avatar' => 'sr-survey-post-author-avatar',
    'modules/survey/theme/basic/view.php#comment-avatar' => 'sr-survey-comment-author-avatar',
    'modules/survey/skins/basic/view.php' => 'sr_survey_comment_page(',
    'modules/survey/skins/basic/view.php#post-avatar' => 'sr-survey-post-author-avatar',
    'modules/survey/skins/basic/view.php#comment-avatar' => 'sr-survey-comment-author-avatar',
    'modules/content/theme/basic/content.php#pagination' => 'sr_public_pagination_html($contentCommentPage',
    'modules/content/views/content.php#pagination' => 'sr_public_pagination_html($contentCommentPage',
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

foreach (['modules/community/theme/basic/list.php', 'modules/community/skins/basic/list.php'] as $communityListViewPath) {
    $contents = file_get_contents($root . '/' . $communityListViewPath);
    $assert(
        is_string($contents)
            && str_contains($contents, 'sr_member_public_profile_image_sources($pdo, $communityListProfileImageAccountIds)')
            && str_contains($contents, "sr_member_profile_image_size_pixels('small', \$memberSettings)")
            && str_contains($contents, '$communityListProfileImagesEnabled ? $postAuthorLabel : \'\'')
            && str_contains($contents, 'community-board-table-author-content')
            && str_contains($contents, 'community-board-table-mobile-author'),
        $communityListViewPath . ' must batch profile image lookups and render the uploaded image or initial fallback in desktop and mobile author metadata.'
    );
}

foreach ([
    'modules/community/theme/basic/post.php' => ['$communityPostAvatarSizePixels', '$communityCommentAvatarSizePixels'],
    'modules/community/skins/basic/view.php' => ['$communityPostAvatarSizePixels', '$communityCommentAvatarSizePixels'],
    'modules/content/theme/basic/content.php' => ['$contentPostAvatarSizePixels', '$contentCommentAvatarSizePixels'],
    'modules/content/views/content.php' => ['$contentPostAvatarSizePixels', '$contentCommentAvatarSizePixels'],
    'modules/quiz/theme/basic/view.php' => ['$quizPostAvatarSizePixels', '$quizCommentAvatarSizePixels'],
    'modules/quiz/skins/basic/view.php' => ['$quizPostAvatarSizePixels', '$quizCommentAvatarSizePixels'],
    'modules/survey/theme/basic/view.php' => ['$surveyPostAvatarSizePixels', '$surveyCommentAvatarSizePixels'],
    'modules/survey/skins/basic/view.php' => ['$surveyPostAvatarSizePixels', '$surveyCommentAvatarSizePixels'],
] as $avatarViewPath => $avatarSizeMarkers) {
    $contents = file_get_contents($root . '/' . $avatarViewPath);
    $assert(
        is_string($contents)
            && str_contains($contents, $avatarSizeMarkers[0])
            && str_contains($contents, $avatarSizeMarkers[1])
            && str_contains($contents, "sr_member_profile_image_size_pixels('medium'")
            && str_contains($contents, "sr_member_profile_image_size_pixels('small'")
            && str_contains($contents, 'PublicAvatarsEnabled')
            && (
                str_contains($contents, 'AuthorLabel')
                || str_contains($contents, 'OwnerPublicName')
                || str_contains($contents, 'PublisherName')
            ),
        $avatarViewPath . ' must honor public avatar visibility and apply medium pixels to post authors and small pixels to comment authors.'
    );
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

foreach ([
    'modules/community/theme/basic/assets/module.css' => [
        '.community-comments-pagination',
        '.community-list-author-profile-image',
        '.community-board-table-author-content',
        '.community-board-table-mobile-author',
        '.community-post-author-avatar',
        '.community-comment-author-avatar',
        '.member-profile-image.member-profile-image-size-small',
        '.member-profile-image.member-profile-image-size-medium',
        '.member-profile-image.member-profile-image-size-large',
        '.member-profile-image-fallback',
        'border-bottom: 1px solid var(--community-divider',
    ],
    'modules/content/theme/basic/assets/module.css' => [
        '.content-comments-pagination',
        '.content-post-author-avatar',
        '.content-comment-author-avatar',
        '.member-profile-image.member-profile-image-size-small',
        '.member-profile-image.member-profile-image-size-medium',
        '.member-profile-image.member-profile-image-size-large',
        '.member-profile-image-fallback',
        'border-bottom: 1px solid var(--content-divider',
    ],
    'modules/quiz/theme/basic/assets/module.css' => [
        '.quiz-comments-pagination',
        '.sr-quiz-author-meta',
        '.sr-quiz-comment-author-avatar',
        '.member-profile-image.member-profile-image-size-small',
        '.member-profile-image.member-profile-image-size-medium',
        '.member-profile-image.member-profile-image-size-large',
        '.member-profile-image-fallback',
        '.sr-quiz-page .quiz-page-main .sr-quiz-comments-panel-header h2',
        '.sr-quiz-page .quiz-page-main .sr-quiz-comment-form > p',
        'border-bottom: 1px solid var(--sr-quiz-comment-divider',
    ],
    'modules/survey/theme/basic/assets/module.css' => [
        '.survey-comments-pagination',
        '.sr-survey-author-meta',
        '.sr-survey-comment-author-avatar',
        '.member-profile-image.member-profile-image-size-small',
        '.member-profile-image.member-profile-image-size-medium',
        '.member-profile-image.member-profile-image-size-large',
        '.member-profile-image-fallback',
        '.sr-survey-page .survey-page-main .sr-survey-comments-panel-header h2',
        '.sr-survey-page .survey-page-main .sr-survey-comment-form > p',
        'border-bottom: 1px solid var(--sr-survey-comment-divider',
    ],
] as $commentStylesheetPath => $markers) {
    $contents = file_get_contents($root . '/' . $commentStylesheetPath);
    foreach ($markers as $marker) {
        $assert(
            is_string($contents) && str_contains($contents, $marker),
            $commentStylesheetPath . ' must keep the community comment surface marker: ' . $marker
        );
    }
    $assert(
        is_string($contents) && preg_match('/\.(?:community|content|quiz|survey)-comments-pagination\s*\{[^}]*gap:\s*8px;[^}]*justify-content:\s*center;/s', $contents) === 1,
        $commentStylesheetPath . ' must keep community-style centered comment pagination spacing.'
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
