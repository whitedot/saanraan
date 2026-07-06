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

function sr_check_community_board_settings_runtime(): void
{
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        sr_check_community_board_settings_error('SQLite PDO driver is required for community board runtime fixture.');
        return;
    }

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec(
        "CREATE TABLE sr_community_board_settings (
            board_id INTEGER NOT NULL,
            setting_key TEXT NOT NULL,
            setting_value TEXT NOT NULL,
            value_type TEXT NOT NULL DEFAULT 'string',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            PRIMARY KEY (board_id, setting_key)
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_community_board_group_settings (
            group_id INTEGER NOT NULL,
            setting_key TEXT NOT NULL,
            setting_value TEXT NOT NULL,
            value_type TEXT NOT NULL DEFAULT 'string',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            PRIMARY KEY (group_id, setting_key)
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_community_boards (
            id INTEGER PRIMARY KEY,
            board_group_id INTEGER NULL,
            board_key TEXT NOT NULL,
            title TEXT NOT NULL,
            status TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_community_posts (
            id INTEGER PRIMARY KEY,
            board_id INTEGER NOT NULL,
            author_account_id INTEGER NOT NULL DEFAULT 0,
            author_public_name_snapshot TEXT NOT NULL DEFAULT '',
            guest_author_name TEXT NOT NULL DEFAULT '',
            guest_password_hash TEXT NULL,
            guest_ip_hash TEXT NULL,
            guest_user_agent_hash TEXT NULL,
            extra_values_json TEXT NULL,
            title TEXT NOT NULL,
            body_text TEXT NOT NULL,
            reaction_preset_key TEXT NOT NULL DEFAULT '',
            reaction_comment_preset_key TEXT NOT NULL DEFAULT '',
            seo_title TEXT NOT NULL DEFAULT '',
            seo_description TEXT NOT NULL DEFAULT '',
            og_title TEXT NOT NULL DEFAULT '',
            og_description TEXT NOT NULL DEFAULT '',
            og_image_attachment_id INTEGER NULL,
            is_secret INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL,
            hidden_at TEXT NULL,
            hidden_until TEXT NULL,
            hidden_reason TEXT NOT NULL DEFAULT '',
            hidden_note TEXT NULL,
            hidden_by_account_id INTEGER NULL,
            hidden_before_status TEXT NOT NULL DEFAULT '',
            summary_feed_candidate INTEGER NOT NULL DEFAULT 1,
            view_count INTEGER NOT NULL DEFAULT 0,
            last_commented_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_community_comments (
            id INTEGER PRIMARY KEY,
            post_id INTEGER NOT NULL,
            parent_comment_id INTEGER NULL,
            thread_root_id INTEGER NULL,
            depth INTEGER NOT NULL DEFAULT 1,
            author_account_id INTEGER NULL,
            author_public_name_snapshot TEXT NOT NULL DEFAULT '',
            guest_author_name TEXT NOT NULL DEFAULT '',
            guest_password_hash TEXT NULL,
            guest_ip_hash TEXT NULL,
            guest_user_agent_hash TEXT NULL,
            body_text TEXT NOT NULL DEFAULT '',
            is_secret INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL,
            hidden_at TEXT NULL,
            hidden_until TEXT NULL,
            hidden_reason TEXT NOT NULL DEFAULT '',
            hidden_note TEXT NULL,
            hidden_by_account_id INTEGER NULL,
            hidden_before_status TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_community_attachments (
            id INTEGER PRIMARY KEY,
            post_id INTEGER NOT NULL,
            status TEXT NOT NULL,
            mime_type TEXT NOT NULL,
            storage_driver TEXT NOT NULL DEFAULT '',
            storage_key TEXT NOT NULL DEFAULT '',
            size_bytes INTEGER NOT NULL DEFAULT 0,
            checksum_sha256 TEXT NOT NULL DEFAULT '',
            width INTEGER NULL,
            height INTEGER NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_member_accounts (
            id INTEGER PRIMARY KEY,
            status TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_modules (
            id INTEGER PRIMARY KEY,
            module_key TEXT NOT NULL,
            version TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_module_settings (
            module_id INTEGER NOT NULL,
            setting_key TEXT NOT NULL,
            setting_value TEXT NOT NULL,
            value_type TEXT NOT NULL DEFAULT 'string',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            PRIMARY KEY (module_id, setting_key)
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_identity_verification_attempts (
            id INTEGER PRIMARY KEY,
            purpose TEXT NOT NULL,
            status TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_identity_verification_results (
            id INTEGER PRIMARY KEY,
            attempt_id INTEGER NOT NULL,
            account_id INTEGER NULL,
            provider_key TEXT NOT NULL,
            age_over_19 INTEGER NULL,
            verified_at TEXT NOT NULL,
            expires_at TEXT NULL,
            created_at TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_identity_verification_links (
            id INTEGER PRIMARY KEY,
            account_id INTEGER NOT NULL,
            result_id INTEGER NOT NULL,
            purpose TEXT NOT NULL,
            linked_at TEXT NOT NULL,
            revoked_at TEXT NULL,
            created_at TEXT NOT NULL
        )"
    );

    $now = '2026-06-14 12:00:00';
    $pdo->exec("INSERT INTO sr_community_boards (id, board_group_id, board_key, title, status) VALUES (10, 20, 'fixture', 'Fixture Board', 'enabled')");
    $board = [
        'id' => 10,
        'board_group_id' => 20,
        'status' => 'enabled',
        'read_policy' => 'public',
        'post_body_min_length' => '0',
        'post_body_max_length' => '0',
    ];
    $groupSettingStmt = $pdo->prepare(
        'INSERT INTO sr_community_board_group_settings
            (group_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (20, :setting_key, :setting_value, :value_type, :created_at, :updated_at)'
    );
    foreach ([
        ['setting_key' => 'post_body_min_length', 'setting_value' => '3', 'value_type' => 'int'],
        ['setting_key' => 'post_body_max_length', 'setting_value' => '5', 'value_type' => 'int'],
        ['setting_key' => 'list_per_page', 'setting_value' => '2', 'value_type' => 'int'],
        ['setting_key' => 'list_default_sort', 'setting_value' => 'comments', 'value_type' => 'string'],
    ] as $setting) {
        $groupSettingStmt->execute([
            'setting_key' => $setting['setting_key'],
            'setting_value' => $setting['setting_value'],
            'value_type' => $setting['value_type'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    $boardSettingStmt = $pdo->prepare(
        'INSERT INTO sr_community_board_settings
            (board_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (10, :setting_key, :setting_value, :value_type, :created_at, :updated_at)'
    );
    foreach ([
        ['setting_key' => 'post_body_max_length', 'setting_value' => '0', 'value_type' => 'int'],
        ['setting_key' => 'post_edit_lock_comment_count', 'setting_value' => '2', 'value_type' => 'int'],
        ['setting_key' => 'post_delete_lock_comment_count', 'setting_value' => '3', 'value_type' => 'int'],
        ['setting_key' => 'comment_body_min_length', 'setting_value' => '2', 'value_type' => 'int'],
        ['setting_key' => 'comment_body_max_length', 'setting_value' => '4', 'value_type' => 'int'],
        ['setting_key' => 'summary_feed_enabled', 'setting_value' => '1', 'value_type' => 'bool'],
        ['setting_key' => 'reaction_enabled', 'setting_value' => '1', 'value_type' => 'bool'],
    ] as $setting) {
        $boardSettingStmt->execute([
            'setting_key' => $setting['setting_key'],
            'setting_value' => $setting['setting_value'],
            'value_type' => $setting['value_type'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_posts
            (id, board_id, author_account_id, title, body_text, is_secret, status, view_count, created_at, updated_at)
         VALUES
            (:id, 10, 0, :title, :body_text, :is_secret, :status, :view_count, :created_at, :updated_at)'
    );
    foreach ([
        ['id' => 1, 'title' => 'first', 'body_text' => 'alpha body', 'is_secret' => 0, 'status' => 'published', 'view_count' => 5],
        ['id' => 2, 'title' => 'second', 'body_text' => 'beta body', 'is_secret' => 0, 'status' => 'published', 'view_count' => 30],
        ['id' => 3, 'title' => 'third', 'body_text' => '<p>gamma<br>body</p>', 'is_secret' => 0, 'status' => 'published', 'view_count' => 10],
        ['id' => 4, 'title' => 'draft', 'body_text' => 'hidden', 'is_secret' => 0, 'status' => 'draft', 'view_count' => 999],
        ['id' => 5, 'title' => 'secret alpha', 'body_text' => 'private token', 'is_secret' => 1, 'status' => 'published', 'view_count' => 1],
    ] as $post) {
        $stmt->execute([
            'id' => $post['id'],
            'title' => $post['title'],
            'body_text' => $post['body_text'],
            'is_secret' => $post['is_secret'],
            'status' => $post['status'],
            'view_count' => $post['view_count'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    $commentStmt = $pdo->prepare('INSERT INTO sr_community_comments (id, post_id, status, created_at) VALUES (:id, :post_id, :status, :created_at)');
    foreach ([
        ['id' => 1, 'post_id' => 1, 'status' => 'published'],
        ['id' => 2, 'post_id' => 1, 'status' => 'published'],
        ['id' => 3, 'post_id' => 1, 'status' => 'deleted'],
        ['id' => 4, 'post_id' => 3, 'status' => 'published'],
    ] as $comment) {
        $commentStmt->execute([
            'id' => $comment['id'],
            'post_id' => $comment['post_id'],
            'status' => $comment['status'],
            'created_at' => $now,
        ]);
    }

    if (sr_community_board_post_body_min_length($pdo, $board) !== 0) {
        sr_check_community_board_settings_error('community board must not use group fallback min length.');
    }
    if (sr_community_board_post_body_max_length($pdo, $board) !== 0) {
        sr_check_community_board_settings_error('community board zero value override failed.');
    }
    if (sr_community_board_list_per_page($pdo, $board, ['posts_per_page' => 20]) !== 20) {
        sr_check_community_board_settings_error('community board list per page must not use group fallback.');
    }
    if (sr_community_board_list_default_sort($pdo, $board) !== 'latest') {
        sr_check_community_board_settings_error('community board list default sort must not use group fallback.');
    }
    $globalDefaults = sr_community_board_default_settings([
        'post_body_min_length' => 7,
        'post_body_max_length' => 9,
    ]);
    if ((string) ($globalDefaults['post_body_min_length'] ?? '') !== '7' || (string) ($globalDefaults['post_body_max_length'] ?? '') !== '9') {
        sr_check_community_board_settings_error('community global post body length defaults must seed new board settings.');
    }
    $globalLimitDefaults = sr_community_board_default_settings([
        'post_body_min_length' => sr_community_post_body_setting_max_length() + 1,
        'post_body_max_length' => sr_community_post_body_setting_max_length() + 1,
    ]);
    if ((int) ($globalLimitDefaults['post_body_min_length'] ?? 0) !== sr_community_post_body_setting_max_length()
        || (int) ($globalLimitDefaults['post_body_max_length'] ?? 0) !== sr_community_post_body_setting_max_length()) {
        sr_check_community_board_settings_error('community global post body length defaults must be bounded by storage policy.');
    }

    $commentSortedIds = array_map('intval', array_column(sr_community_public_posts($pdo, 10, 10, 0, '', 0, 'comments'), 'id'));
    if ($commentSortedIds !== [1, 3, 5, 2]) {
        sr_check_community_board_settings_error('community comment sort runtime order failed.');
    }
    $viewSortedIds = array_map('intval', array_column(sr_community_public_posts($pdo, 10, 2, 0, '', 0, 'views'), 'id'));
    if ($viewSortedIds !== [2, 3]) {
        sr_check_community_board_settings_error('community views sort and limit runtime order failed.');
    }
    $identityBoard = [
        'id' => 10,
        'board_group_id' => 20,
        'status' => 'enabled',
        'read_policy' => 'public',
    ];
    $boardSettingStmt->execute([
        'setting_key' => 'identity_verification_enabled',
        'setting_value' => '1',
        'value_type' => 'bool',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $boardSettingStmt->execute([
        'setting_key' => 'identity_verification_required_actions',
        'setting_value' => '["enter","read","write","comment","download"]',
        'value_type' => 'json',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    if (!sr_community_board_requires_verification_login($pdo, $identityBoard, ['identity_restricted_board_required' => false])) {
        sr_check_community_board_settings_error('community board identity setting must require login before policy evaluation.');
    }
    foreach (['enter', 'read', 'write', 'comment', 'download'] as $identityAction) {
        if (!sr_community_board_identity_action_required($pdo, $identityBoard, $identityAction)) {
            sr_check_community_board_settings_error('community board identity action setting failed for ' . $identityAction . '.');
        }
    }
    $pdo->prepare(
        "UPDATE sr_community_board_settings
            SET setting_value = :setting_value
          WHERE board_id = 10
            AND setting_key = 'identity_verification_required_actions'"
    )->execute(['setting_value' => '["read"]']);
    if (sr_community_board_requires_verification_login($pdo, $identityBoard, ['identity_restricted_board_required' => false], 'enter')) {
        sr_check_community_board_settings_error('community board read-only identity action must not require board entry login.');
    }
    if (!sr_community_board_requires_verification_login($pdo, $identityBoard, ['identity_restricted_board_required' => false], 'read')) {
        sr_check_community_board_settings_error('community board read identity action must require post read login.');
    }
    $missingIdentityPolicy = sr_community_identity_action_policy($pdo, $identityBoard, ['id' => 1], 'read', '/community/post?id=1', ['identity_restricted_board_required' => false]);
    if (empty($missingIdentityPolicy['required']) || !empty($missingIdentityPolicy['satisfied']) || !empty($missingIdentityPolicy['available'])) {
        sr_check_community_board_settings_error('community identity policy must fail closed without identity verification module.');
    }
    $pdo->exec("INSERT INTO sr_modules (id, module_key, version, status) VALUES (1, 'identity_verification', '1.0.0', 'enabled')");
    $pdo->exec("INSERT INTO sr_modules (id, module_key, version, status) VALUES (2, 'identity_kcp', '1.0.0', 'enabled')");
    $moduleSettingStmt = $pdo->prepare(
        'INSERT INTO sr_module_settings
            (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (1, :setting_key, :setting_value, :value_type, :created_at, :updated_at)'
    );
    foreach ([
        ['setting_key' => 'enabled', 'setting_value' => '1', 'value_type' => 'bool'],
        ['setting_key' => 'default_provider_key', 'setting_value' => 'kcp', 'value_type' => 'string'],
        ['setting_key' => 'provider_kcp_enabled', 'setting_value' => '1', 'value_type' => 'bool'],
    ] as $setting) {
        $moduleSettingStmt->execute([
            'setting_key' => $setting['setting_key'],
            'setting_value' => $setting['setting_value'],
            'value_type' => $setting['value_type'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    $pdo->exec(
        "INSERT INTO sr_identity_verification_attempts
            (id, purpose, status)
         VALUES
            (1, 'community.restricted_board', 'verified')"
    );
    $pdo->exec(
        "INSERT INTO sr_identity_verification_results
            (id, attempt_id, account_id, provider_key, age_over_19, verified_at, expires_at, created_at)
         VALUES
            (1, 1, 1, 'kcp', 1, '2026-06-14 11:30:00', NULL, '2026-06-14 11:30:00')"
    );
    $pdo->exec(
        "INSERT INTO sr_identity_verification_links
            (id, account_id, result_id, purpose, linked_at, revoked_at, created_at)
         VALUES
            (1, 1, 1, 'community.restricted_board', '2026-06-14 11:30:00', NULL, '2026-06-14 11:30:00')"
    );
    sr_clear_module_settings_cache('identity_verification');
    $linkedWithoutSessionIdentityPolicy = sr_community_identity_action_policy($pdo, $identityBoard, ['id' => 1], 'read', '/community/post?id=1', ['identity_restricted_board_required' => false]);
    if (empty($linkedWithoutSessionIdentityPolicy['required']) || empty($linkedWithoutSessionIdentityPolicy['available']) || !empty($linkedWithoutSessionIdentityPolicy['satisfied'])) {
        sr_check_community_board_settings_error('community identity policy must block valid identity links outside the current login session.');
    }
    $_SESSION['sr_identity_verification_results'] = [
        'community.restricted_board' => [
            'result_id' => 1,
            'account_id' => 1,
            'purpose' => 'community.restricted_board',
            'verified_at' => time() - 3600,
            'identity' => [],
        ],
    ];
    $passedIdentityPolicy = sr_community_identity_action_policy($pdo, $identityBoard, ['id' => 1], 'read', '/community/post?id=1', ['identity_restricted_board_required' => false]);
    if (empty($passedIdentityPolicy['required']) || empty($passedIdentityPolicy['available']) || empty($passedIdentityPolicy['satisfied'])) {
        sr_check_community_board_settings_error('community identity policy must pass accounts with a current-session identity verification result.');
    }
    $failedIdentityPolicy = sr_community_identity_action_policy($pdo, $identityBoard, ['id' => 2], 'read', '/community/post?id=1', ['identity_restricted_board_required' => false]);
    if (empty($failedIdentityPolicy['required']) || empty($failedIdentityPolicy['available']) || !empty($failedIdentityPolicy['satisfied'])) {
        sr_check_community_board_settings_error('community identity policy must block accounts without a valid identity verification link.');
    }
    if (!sr_community_post_locked_by_comments($pdo, $board, 1, 'edit')) {
        sr_check_community_board_settings_error('community edit lock threshold runtime check failed.');
    }
    if (sr_community_post_locked_by_comments($pdo, $board, 1, 'delete')) {
        sr_check_community_board_settings_error('community delete lock threshold runtime check failed.');
    }
    if (sr_community_validate_post_body_length($pdo, $board, ['body_text' => 'ab', 'body_format' => 'plain']) !== []) {
        sr_check_community_board_settings_error('community post body minimum must not use group fallback.');
    }
    $globalLengthBoard = [
        'id' => 11,
        'board_group_id' => 0,
        'status' => 'enabled',
        'read_policy' => 'public',
    ];
    if (sr_community_validate_post_body_length($pdo, $globalLengthBoard, ['body_text' => 'abcd', 'body_format' => 'plain'], ['post_body_min_length' => 5]) === []) {
        sr_check_community_board_settings_error('community post body minimum must use global fallback when board setting is missing.');
    }
    if (sr_community_validate_post_body_length($pdo, $globalLengthBoard, ['body_text' => 'abcdef', 'body_format' => 'plain'], ['post_body_max_length' => 5]) === []) {
        sr_check_community_board_settings_error('community post body maximum must use global fallback when board setting is missing.');
    }
    if (sr_community_validate_comment_body_length($pdo, $board, ['body_text' => 'abcde']) === []) {
        sr_check_community_board_settings_error('community comment body maximum runtime validation failed.');
    }
    $searchByBodyIds = array_map('intval', array_column(sr_community_search_posts($pdo, [10], 'alpha', 10, 0, [10]), 'id'));
    if ($searchByBodyIds !== [5, 1]) {
        sr_check_community_board_settings_error('community global search title/body runtime check failed.');
    }
    $searchTitleOnlyIds = array_map('intval', array_column(sr_community_search_posts($pdo, [10], 'alpha', 10, 0, []), 'id'));
    if ($searchTitleOnlyIds !== [5]) {
        sr_check_community_board_settings_error('community global search title-only board policy failed.');
    }
    if (sr_community_search_posts($pdo, [10], 'private', 10, 0, [10]) !== []) {
        sr_check_community_board_settings_error('community global search secret body exclusion failed.');
    }
    if (!sr_community_effective_board_reaction_enabled($pdo, $board, ['reaction_enabled' => false])) {
        sr_check_community_board_settings_error('community board reaction enabled override failed.');
    }
    if (!sr_community_effective_board_summary_feed_enabled($pdo, $board)) {
        sr_check_community_board_settings_error('community board summary feed enabled override failed.');
    }
    $formatPost = ['id' => 3, 'board_id' => 10, 'body_text' => '<p>gamma<br>body</p>'];
    if (sr_community_post_body_format($pdo, $formatPost, ['post_editor' => 'textarea']) !== 'plain') {
        sr_check_community_board_settings_error('community post body format should default to plain when no board editor setting is stored.');
    }
    $boardSettingStmt->execute([
        'setting_key' => 'post_editor',
        'setting_value' => 'html',
        'value_type' => 'string',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    if (sr_community_post_body_format($pdo, $formatPost, ['post_editor' => 'textarea']) !== 'html') {
        sr_check_community_board_settings_error('community post body format must follow the current board editor setting for existing posts.');
    }
    $pdo->prepare(
        "UPDATE sr_community_board_settings
            SET setting_value = 'textarea'
          WHERE board_id = 10
            AND setting_key = 'post_editor'"
    )->execute();
    if (sr_community_post_body_format($pdo, $formatPost, ['post_editor' => 'html']) !== 'plain') {
        sr_check_community_board_settings_error('community post body format must change with the current board editor setting instead of a stored row snapshot.');
    }
    $pdo->prepare(
        "UPDATE sr_community_board_settings
            SET setting_value = 'markdown'
          WHERE board_id = 10
            AND setting_key = 'post_editor'"
    )->execute();
    if (sr_community_post_body_format($pdo, $formatPost, ['post_editor' => 'textarea']) !== 'plain') {
        sr_check_community_board_settings_error('community post body format must fall back to plain when markdown editor is configured but inactive.');
    }
    $pdo->exec("INSERT INTO sr_modules (id, module_key, version, status) VALUES (3, 'markdown_editor', '2026.07.001', 'enabled')");
    if (sr_community_post_body_format($pdo, $formatPost, ['post_editor' => 'textarea']) !== 'markdown') {
        sr_check_community_board_settings_error('community post body format must use markdown when the current board editor setting is active.');
    }
    sr_community_sync_board_summary_feed_candidates($pdo, 10, false);
    $candidateCount = (int) $pdo->query('SELECT COUNT(*) FROM sr_community_posts WHERE board_id = 10 AND summary_feed_candidate = 0')->fetchColumn();
    if ($candidateCount !== 5) {
        sr_check_community_board_settings_error('community board summary feed setting must synchronize post summary candidates.');
    }
}

$settingKeys = [
    'identity_verification_enabled',
    'identity_verification_purpose',
    'identity_verification_required_actions',
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
    'summary_feed_enabled',
    'reaction_enabled',
];

sr_check_community_board_settings_contains('modules/community/helpers/boards.php', $settingKeys, 'community board/group setting key contract');
sr_check_community_board_settings_contains('modules/community/helpers/boards.php', [
    "'secret_posts_enabled' => !empty(\$settings['secret_posts_enabled']) ? '1' : '0'",
    "'secret_comments_enabled' => !empty(\$settings['secret_comments_enabled']) ? '1' : '0'",
], 'community board group default secret setting contract');
sr_check_community_board_settings_contains('modules/community/helpers/posts.php', [
    'sr_community_board_list_sort_values',
    'sr_community_board_list_per_page',
    'function sr_community_search_posts(PDO $pdo, array $boardIds, string $keyword, int $limit = 20, int $offset = 0, ?array $bodySearchBoardIds = null)',
    'function sr_community_public_posts(PDO $pdo, int $boardId, int $limit = 20, int $offset = 0, string $keyword = \'\', int $categoryId = 0, string $sort = \'latest\')',
    'published_comment_count DESC, p.id DESC',
], 'community board runtime setting helpers');
sr_check_community_board_settings_contains('modules/community/helpers/posts-writing.php', [
    'sr_community_validate_post_body_length',
    'sr_community_post_locked_by_comments',
], 'community post write runtime setting helpers');
sr_check_community_board_settings_contains('modules/community/helpers/posts-comments.php', [
    'sr_community_validate_comment_body_length',
], 'community comment runtime setting helpers');
sr_check_community_board_settings_contains('modules/community/helpers/admin-boards.php', array_merge($settingKeys, [
    'sr_community_post_body_setting_max_length()',
    'sr_community_board_list_sort_key($listDefaultSortInput)',
    '게시글 본문 최소 길이는 최대 길이보다 클 수 없습니다.',
]), 'community board admin setting save');
sr_check_community_board_settings_contains('modules/community/actions/admin-settings.php', [
    'sr_admin_post_int_in_range(\'post_body_min_length\', 0, $postBodyMaxSettingLength)',
    'sr_admin_post_int_in_range(\'post_body_max_length\', 0, $postBodyMaxSettingLength)',
    '게시글 본문 최소 길이는 최대 길이보다 클 수 없습니다.',
    '[\'post_body_min_length\', (string) $postBodyMinLength, \'int\']',
    '[\'post_body_max_length\', (string) $postBodyMaxLength, \'int\']',
], 'community global post body length setting save');
sr_check_community_board_settings_contains('modules/community/views/admin-settings.php', [
    '게시글은 저장 시점의 본문 포맷을 따로 보존하지 않으므로',
    '기존 게시판의 공개 출력 방식도 함께 바뀔 수 있습니다.',
], 'community global post editor operational warning');
sr_check_community_board_settings_contains('modules/community/views/admin-boards.php', [
    '커뮤니티 게시글은 저장 시점의 본문 포맷을 따로 보존하지 않으므로',
    '기존 게시글의 공개 출력 방식도 함께 바뀔 수 있습니다.',
], 'community board post editor operational warning');
sr_check_community_board_settings_contains('modules/community/helpers/boards.php', [
    'function sr_community_effective_board_reaction_enabled',
    "sr_community_effective_board_setting(\$pdo, \$board, 'reaction_enabled'",
], 'community board reaction enabled helper');
sr_check_community_board_settings_contains('modules/community/helpers/boards.php', [
    'function sr_community_effective_board_summary_feed_enabled',
    "sr_community_effective_board_setting(\$pdo, \$board, 'summary_feed_enabled'",
    'function sr_community_sync_board_summary_feed_candidates',
    'summary_feed_candidate',
], 'community board summary feed enabled helper');
sr_check_community_board_settings_contains('modules/community/helpers/posts-writing.php', [
    'sr_community_summary_feed_candidate_value_for_board($pdo, $boardId)',
    'summary_feed_candidate',
], 'community post summary feed candidate creation');
sr_check_community_board_settings_contains('modules/community/helpers/post-body-settings.php', [
    'function sr_community_post_body_setting_max_length',
    'function sr_community_post_body_length_setting',
    'function sr_community_post_body_storage_max_bytes',
], 'community post body length setting helpers');
sr_check_community_board_settings_contains('modules/community/helpers/levels.php', [
    '\'post_body_min_length\' => sr_community_post_body_length_setting',
    '\'post_body_max_length\' => sr_community_post_body_length_setting',
], 'community global post body length defaults');
sr_check_community_board_settings_contains('modules/community/helpers/posts.php', [
    'helpers/post-body-settings.php',
], 'community posts helper body length dependency');
sr_check_community_board_settings_contains('modules/community/helpers/posts-writing.php', [
    'sr_community_post_body_storage_max_bytes()',
    'sr_community_post_body_setting_max_length()',
    'sr_community_post_body_length_setting($settings[\'post_body_min_length\'] ?? 0)',
    'sr_community_post_body_length_setting($settings[\'post_body_max_length\'] ?? 0)',
], 'community post body input and board length contract');
sr_check_community_board_settings_contains('modules/community/actions/write.php', [
    'sr_community_validate_post_body_length($pdo, $board, $values, $settings)',
], 'community post create global body length validation');
sr_check_community_board_settings_contains('modules/community/actions/edit.php', [
    'sr_community_validate_post_body_length($pdo, $board, $values, $settings)',
], 'community post edit global body length validation');
sr_check_community_board_settings_contains('modules/community/reaction-targets.php', [
    'sr_community_effective_board_reaction_enabled($pdo, $board, $settings)',
], 'community reaction target board enabled contract');
sr_check_community_board_settings_contains('modules/community/helpers/boards.php', [
    "\$board['secret_posts_enabled'] = sr_community_board_setting_value",
    "\$board['secret_comments_enabled'] = sr_community_board_setting_value",
    "\$board['post_edit_lock_comment_count'] = sr_community_board_setting_value",
    "\$board['post_delete_lock_comment_count'] = sr_community_board_setting_value",
    "\$board['post_body_min_length'] = sr_community_board_setting_value",
    "\$board['post_body_max_length'] = sr_community_board_setting_value",
    "\$board['comment_body_min_length'] = sr_community_board_setting_value",
    "\$board['comment_body_max_length'] = sr_community_board_setting_value",
    "\$board['list_excerpt_enabled'] = sr_community_board_setting_value",
    "\$board['list_excerpt_length'] = sr_community_board_setting_value",
    "\$board['list_per_page'] = sr_community_board_setting_value",
    "\$board['list_default_sort'] =",
    "\$summaryFeedSetting = sr_community_board_setting_value",
    "\$board['summary_feed_enabled'] = \$summaryFeedSetting ?? '1'",
], 'community admin board edit form value contract');
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
sr_check_community_board_settings_contains('modules/community/skins/basic/list.php', [
    'empty($listExcerptEnabled)',
    '(int) $listExcerptLength',
    'sr_community_body_excerpt',
], 'community basic list excerpt rendering');
sr_check_community_board_settings_contains('modules/community/theme/basic/layout.php', [
    'data-community-layout-search-form',
    'data-community-layout-search-input',
    'data-community-layout-search-min-length="2"',
    'data-community-layout-search-alert',
], 'community layout search form frontend hook');
sr_check_community_board_settings_contains('modules/community/assets/layout.js', [
    'function handleSearchFormSubmit(event)',
    "form.hasAttribute('data-community-layout-search-form')",
    'keyword.length >= minLength',
    'window.alert(alertMessage);',
    'event.stopImmediatePropagation();',
    'event.preventDefault();',
], 'community layout search frontend guard');

if (sr_community_board_list_sort_key('views') !== 'views' || sr_community_board_list_sort_key('bad') !== 'latest') {
    sr_check_community_board_settings_error('community list sort key normalization failed.');
}
if (sr_community_body_plain_length('<p>안녕<br>하세요</p>', 'html') !== 6) {
    sr_check_community_board_settings_error('community HTML body plain length normalization failed.');
}
if (sr_community_body_excerpt('abcdef', 'plain', 3) !== 'abc...') {
    sr_check_community_board_settings_error('community body excerpt truncation failed.');
}

sr_check_community_board_settings_runtime();

if ($errors !== []) {
    fwrite(STDERR, "community board setting checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "community board setting checks completed.\n";
