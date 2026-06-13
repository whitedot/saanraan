#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once $root . '/core/helpers.php';
require_once $root . '/modules/community/helpers.php';

$errors = [];

function sr_community_guest_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_community_guest_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_community_guest_runtime_error($message);
    }
}

function sr_community_guest_runtime_pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $pdo;
}

function sr_community_guest_runtime_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchColumn();
}

function sr_community_guest_runtime_row(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? $row : [];
}

function sr_community_guest_runtime_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE sr_member_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            status TEXT NOT NULL DEFAULT "active"
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_community_board_groups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            group_key TEXT NOT NULL,
            title TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "enabled",
            sort_order INTEGER NOT NULL DEFAULT 0
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_community_boards (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            board_group_id INTEGER NOT NULL DEFAULT 0,
            board_key TEXT NOT NULL,
            title TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT "",
            status TEXT NOT NULL DEFAULT "enabled",
            read_policy TEXT NOT NULL DEFAULT "public",
            write_policy TEXT NOT NULL DEFAULT "member",
            comment_policy TEXT NOT NULL DEFAULT "member",
            image_uploads_enabled INTEGER NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_community_board_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            board_id INTEGER NOT NULL,
            setting_key TEXT NOT NULL,
            setting_value TEXT NOT NULL,
            value_type TEXT NOT NULL DEFAULT "string",
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_community_board_group_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            group_id INTEGER NOT NULL,
            setting_key TEXT NOT NULL,
            setting_value TEXT NOT NULL,
            value_type TEXT NOT NULL DEFAULT "string",
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_community_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            board_id INTEGER NOT NULL,
            author_account_id INTEGER NULL,
            author_public_name_snapshot TEXT NOT NULL DEFAULT "",
            guest_author_name TEXT NOT NULL DEFAULT "",
            guest_password_hash TEXT NULL,
            guest_ip_hash TEXT NULL,
            guest_user_agent_hash TEXT NULL,
            extra_values_json TEXT NOT NULL DEFAULT "[]",
            title TEXT NOT NULL,
            body_text TEXT NOT NULL,
            body_format TEXT NOT NULL DEFAULT "plain",
            seo_title TEXT NOT NULL DEFAULT "",
            seo_description TEXT NOT NULL DEFAULT "",
            og_title TEXT NOT NULL DEFAULT "",
            og_description TEXT NOT NULL DEFAULT "",
            og_image_attachment_id INTEGER NULL,
            is_secret INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT "published",
            view_count INTEGER NOT NULL DEFAULT 0,
            last_commented_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_community_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER NOT NULL,
            parent_comment_id INTEGER NULL,
            thread_root_id INTEGER NULL,
            depth INTEGER NOT NULL DEFAULT 1,
            author_account_id INTEGER NULL,
            author_public_name_snapshot TEXT NOT NULL DEFAULT "",
            guest_author_name TEXT NOT NULL DEFAULT "",
            guest_password_hash TEXT NULL,
            guest_ip_hash TEXT NULL,
            guest_user_agent_hash TEXT NULL,
            body_text TEXT NOT NULL,
            is_secret INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT "published",
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_community_post_field_values (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER NOT NULL,
            field_key TEXT NOT NULL,
            label_snapshot TEXT NOT NULL,
            field_type_snapshot TEXT NOT NULL,
            visibility_snapshot TEXT NOT NULL,
            show_on_view_snapshot INTEGER NOT NULL DEFAULT 1,
            show_in_admin_snapshot INTEGER NOT NULL DEFAULT 1,
            privacy_purpose_snapshot TEXT NOT NULL DEFAULT "",
            export_policy_snapshot TEXT NOT NULL DEFAULT "include",
            cleanup_policy_snapshot TEXT NOT NULL DEFAULT "anonymize",
            value_text TEXT NULL,
            value_json TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );

    $now = sr_now();
    $pdo->prepare(
        'INSERT INTO sr_community_board_groups (id, group_key, title, status, sort_order)
         VALUES (1, "general", "일반", "enabled", 1)'
    )->execute();
    $pdo->prepare(
        'INSERT INTO sr_community_boards
            (id, board_group_id, board_key, title, description, status, read_policy, write_policy, comment_policy, image_uploads_enabled, sort_order, created_at, updated_at)
         VALUES
            (1, 1, "guest_runtime", "비회원 런타임", "", "enabled", "public", "guest", "guest", 1, 1, :created_at, :updated_at)'
    )->execute([
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function sr_community_guest_runtime_check(): void
{
    $pdo = sr_community_guest_runtime_pdo();
    sr_community_guest_runtime_schema($pdo);

    $_SERVER['REMOTE_ADDR'] = '198.51.100.44';
    $_SERVER['HTTP_USER_AGENT'] = 'Saanraan-Guest-Runtime';

    $board = sr_community_board_by_id($pdo, 1);
    sr_community_guest_runtime_assert(is_array($board), 'guest runtime board must be readable.');
    if (!is_array($board)) {
        return;
    }

    sr_community_guest_runtime_assert(sr_community_account_can_write_board($pdo, $board, null) === true, 'guest write policy must allow anonymous post creation.');

    $extraFieldDefinitions = sr_community_normalize_extra_field_definitions([
        [
            'key' => 'company',
            'label' => '회사명',
            'type' => 'text',
            'required' => true,
            'visibility' => 'public',
            'show_on_view' => true,
            'show_in_admin' => true,
            'privacy_purpose' => '문의 응대',
            'export_policy' => 'include',
            'cleanup_policy' => 'anonymize',
        ],
    ]);
    $extraFieldValues = ['company' => '런타임 회사'];
    sr_community_guest_runtime_assert(
        sr_community_validate_extra_field_values($extraFieldDefinitions, ['company' => str_repeat('가', 1001)]) !== [],
        'additional text field validation must reject overlong values instead of truncating them.'
    );
    sr_community_guest_runtime_assert(
        sr_community_validate_extra_field_values($extraFieldDefinitions, ['company' => ['array']]) !== [],
        'additional text field validation must reject array payloads.'
    );
    $textareaDefinitions = sr_community_normalize_extra_field_definitions([
        [
            'key' => 'memo',
            'label' => '메모',
            'type' => 'textarea',
        ],
    ]);
    sr_community_guest_runtime_assert(
        sr_community_validate_extra_field_values($textareaDefinitions, ['memo' => str_repeat('나', 5001)]) !== [],
        'additional textarea field validation must reject overlong values instead of truncating them.'
    );
    $postValues = [
        'title' => '비회원 런타임 게시글',
        'category_id' => 0,
        'body_text' => '비회원 작성 mutation 런타임 본문',
        'body_format' => 'plain',
        'seo_title' => '',
        'seo_description' => '',
        'og_title' => '',
        'og_description' => '',
        'is_secret' => 0,
        'guest_author_name' => '비회원 작성자',
        'guest_password' => 'guest-runtime-password',
        'extra_values_json' => sr_community_extra_field_values_json($extraFieldDefinitions, $extraFieldValues),
        'extra_field_definitions' => $extraFieldDefinitions,
        'extra_field_values' => $extraFieldValues,
    ];
    $postErrors = array_merge(
        sr_community_validate_post_input($postValues),
        sr_community_validate_guest_author_input($postValues),
        sr_community_validate_extra_field_values($extraFieldDefinitions, $extraFieldValues)
    );
    sr_community_guest_runtime_assert($postErrors === [], 'guest post input must pass server-side validation: ' . implode(', ', $postErrors));

    $postId = sr_community_create_post($pdo, 1, 0, $postValues);
    sr_community_guest_runtime_assert($postId > 0, 'guest post creation must return an id.');

    $post = sr_community_guest_runtime_row($pdo, 'SELECT * FROM sr_community_posts WHERE id = :id', ['id' => $postId]);
    sr_community_guest_runtime_assert(array_key_exists('author_account_id', $post) && $post['author_account_id'] === null, 'guest post account id must be NULL.');
    sr_community_guest_runtime_assert((string) ($post['author_public_name_snapshot'] ?? '') === '비회원 작성자', 'guest post author snapshot must use guest name.');
    sr_community_guest_runtime_assert((string) ($post['guest_author_name'] ?? '') === '비회원 작성자', 'guest post must store guest author name snapshot.');
    sr_community_guest_runtime_assert((string) ($post['guest_password_hash'] ?? '') !== 'guest-runtime-password', 'guest post must not store raw password.');
    sr_community_guest_runtime_assert(sr_community_guest_can_edit_post($post, 'guest-runtime-password'), 'guest post password hash must verify for edit/delete.');
    sr_community_guest_runtime_assert((string) ($post['guest_ip_hash'] ?? '') === hash('sha256', '198.51.100.44'), 'guest post must store IP hash.');
    sr_community_guest_runtime_assert((string) ($post['guest_user_agent_hash'] ?? '') === hash('sha256', 'Saanraan-Guest-Runtime'), 'guest post must store user-agent hash.');
    sr_community_guest_runtime_assert((int) sr_community_guest_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_community_post_field_values WHERE post_id = :post_id', ['post_id' => $postId]) === 1, 'guest post must persist additional field values.');
    sr_community_guest_runtime_assert((string) sr_community_guest_runtime_scalar($pdo, 'SELECT value_text FROM sr_community_post_field_values WHERE post_id = :post_id AND field_key = "company"', ['post_id' => $postId]) === '런타임 회사', 'guest post additional field value must be stored.');

    $readablePost = sr_community_post_for_read($pdo, $postId, null);
    sr_community_guest_runtime_assert(is_array($readablePost), 'guest-created post must be readable publicly.');
    sr_community_guest_runtime_assert(is_array($readablePost) && sr_community_account_can_comment_post($pdo, $readablePost, null) === true, 'guest comment policy must allow anonymous comments.');

    $commentValues = [
        'body_text' => '비회원 댓글 mutation 런타임 본문',
        'is_secret' => 0,
        'parent_comment_id' => 0,
        'parent_comment' => null,
        'guest_author_name' => '댓글 비회원',
        'guest_password' => 'guest-comment-password',
    ];
    $commentErrors = array_merge(
        sr_community_validate_comment_input($commentValues),
        sr_community_validate_guest_author_input($commentValues)
    );
    sr_community_guest_runtime_assert($commentErrors === [], 'guest comment input must pass server-side validation: ' . implode(', ', $commentErrors));

    $commentId = sr_community_create_comment($pdo, $postId, 0, $commentValues);
    sr_community_guest_runtime_assert($commentId > 0, 'guest comment creation must return an id.');

    $comment = sr_community_guest_runtime_row($pdo, 'SELECT * FROM sr_community_comments WHERE id = :id', ['id' => $commentId]);
    sr_community_guest_runtime_assert(array_key_exists('author_account_id', $comment) && $comment['author_account_id'] === null, 'guest comment account id must be NULL.');
    sr_community_guest_runtime_assert((string) ($comment['author_public_name_snapshot'] ?? '') === '댓글 비회원', 'guest comment author snapshot must use guest name.');
    sr_community_guest_runtime_assert((string) ($comment['guest_author_name'] ?? '') === '댓글 비회원', 'guest comment must store guest author name snapshot.');
    sr_community_guest_runtime_assert((string) ($comment['guest_password_hash'] ?? '') !== 'guest-comment-password', 'guest comment must not store raw password.');
    sr_community_guest_runtime_assert(sr_community_guest_can_edit_comment($comment, 'guest-comment-password'), 'guest comment password hash must verify for edit/delete.');
    sr_community_guest_runtime_assert((string) ($comment['guest_ip_hash'] ?? '') === hash('sha256', '198.51.100.44'), 'guest comment must store IP hash.');
    sr_community_guest_runtime_assert((string) ($comment['guest_user_agent_hash'] ?? '') === hash('sha256', 'Saanraan-Guest-Runtime'), 'guest comment must store user-agent hash.');
}

sr_community_guest_runtime_check();

if ($errors !== []) {
    fwrite(STDERR, "community guest runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "community guest runtime checks completed.\n";
