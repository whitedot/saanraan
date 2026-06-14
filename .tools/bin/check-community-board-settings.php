#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/community/helpers.php';

$errors = [];

function sr_check_community_board_settings_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_check_community_board_settings_content(string $path): string
{
    $content = file_get_contents($path);
    if (!is_string($content)) {
        sr_check_community_board_settings_error('file cannot be read: ' . $path);
        return '';
    }

    return $content;
}

function sr_check_community_board_settings_contains(string $path, array $needles, string $label): void
{
    $content = sr_check_community_board_settings_content($path);
    foreach ($needles as $needle) {
        if (!str_contains($content, (string) $needle)) {
            sr_check_community_board_settings_error($label . ' must contain: ' . (string) $needle);
        }
    }
}

$settingKeys = [
    'post_edit_lock_comment_count',
    'post_delete_lock_comment_count',
    'post_body_min_length',
    'post_body_max_length',
    'comment_body_min_length',
    'comment_body_max_length',
    'list_excerpt_enabled',
    'list_excerpt_length',
    'list_per_page',
    'list_default_sort',
];

sr_check_community_board_settings_contains('modules/community/helpers/boards.php', $settingKeys, 'community board/group setting key contract');
sr_check_community_board_settings_contains('modules/community/helpers/posts.php', [
    'sr_community_board_list_sort_values',
    'sr_community_board_list_per_page',
    'sr_community_validate_post_body_length',
    'sr_community_validate_comment_body_length',
    'sr_community_post_locked_by_comments',
    'published_comment_count DESC, p.id DESC',
], 'community board runtime setting helpers');
sr_check_community_board_settings_contains('modules/community/actions/admin-boards.php', array_merge($settingKeys, [
    'sr_community_board_list_sort_key($listDefaultSortInput)',
    '게시글 본문 최소 길이는 최대 길이보다 클 수 없습니다.',
]), 'community board admin setting save');
sr_check_community_board_settings_contains('modules/community/actions/admin-board-groups.php', [
    'group_post_edit_lock_comment_count',
    'group_post_delete_lock_comment_count',
    'group_post_body_min_length',
    'group_post_body_max_length',
    'group_comment_body_min_length',
    'group_comment_body_max_length',
    'group_list_excerpt_enabled',
    'group_list_excerpt_length',
    'group_list_per_page',
    'group_list_default_sort',
    '게시판 그룹의 댓글 본문 최소 길이는 최대 길이보다 클 수 없습니다.',
], 'community board group admin setting save');
sr_check_community_board_settings_contains('modules/community/actions/list.php', [
    'sr_community_board_list_per_page',
    'sr_community_board_list_default_sort',
    'sr_community_board_list_excerpt_enabled',
], 'community public list setting application');
sr_check_community_board_settings_contains('modules/community/actions/write.php', ['sr_community_validate_post_body_length'], 'community post create length validation');
sr_check_community_board_settings_contains('modules/community/actions/edit.php', [
    'sr_community_post_locked_by_comments($pdo, $board, $postId, \'edit\')',
    'sr_community_validate_post_body_length',
], 'community post edit lock and length validation');
sr_check_community_board_settings_contains('modules/community/actions/delete.php', ['sr_community_post_locked_by_comments($pdo, $board, $postId, \'delete\')'], 'community post delete lock validation');
sr_check_community_board_settings_contains('modules/community/actions/comment.php', ['sr_community_validate_comment_body_length'], 'community comment create length validation');
sr_check_community_board_settings_contains('modules/community/actions/comment-edit.php', ['sr_community_validate_comment_body_length'], 'community comment edit length validation');
sr_check_community_board_settings_contains('modules/community/skins/basic/list.php', ['sr_community_body_excerpt'], 'community basic list excerpt rendering');

if (sr_community_board_list_sort_key('views') !== 'views' || sr_community_board_list_sort_key('bad') !== 'latest') {
    sr_check_community_board_settings_error('community list sort key normalization failed.');
}
if (sr_community_body_plain_length('<p>안녕<br>하세요</p>', 'html') !== 6) {
    sr_check_community_board_settings_error('community HTML body plain length normalization failed.');
}
if (sr_community_body_excerpt('abcdef', 'plain', 3) !== 'abc...') {
    sr_check_community_board_settings_error('community body excerpt truncation failed.');
}

if ($errors !== []) {
    fwrite(STDERR, "community board setting checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "community board setting checks completed.\n";
