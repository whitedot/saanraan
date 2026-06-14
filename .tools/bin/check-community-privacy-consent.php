#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once $root . '/core/helpers.php';
require_once $root . '/modules/admin/helpers.php';
require_once $root . '/modules/community/helpers.php';

$errors = [];

$mustContain = static function (string $file, array $markers) use (&$errors): void {
    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        $errors[] = 'cannot read ' . $file;
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, (string) $marker)) {
            $errors[] = $file . ' is missing marker: ' . (string) $marker;
        }
    }
};

$mustContain('modules/community/helpers/privacy-consents.php', [
    'sr_community_privacy_consent_setting_keys',
    'sr_community_privacy_consent_admin_summary_html',
    'privacy_consent_require_attachment_upload',
    'sr_community_privacy_consent_validation_errors',
    'sr_community_record_submission_consents',
    'sr_community_submission_consents_table_exists',
    'sr_community_privacy_consent_body_upload_targets_from_values',
    '($ref[\'type\'] ?? \'\') === \'tmp\'',
    '$requiredActionKeys = sr_community_privacy_consent_required_actions($pdo, $board, $actionKeys)',
    'foreach ($requiredActionKeys as $actionKey)',
    '$idSuffix',
]);
$mustContain('modules/community/helpers/boards.php', [
    'privacy_consent_enabled',
    'privacy_consent_require_post',
    'privacy_consent_require_comment',
    'privacy_consent_require_attachment_upload',
]);
$mustContain('modules/community/actions/admin-boards.php', [
    'privacy_consent_enabled',
    '개인정보 수집 및 이용동의 적용 대상을 하나 이상 선택해 주세요.',
    'privacy_consent_require_attachment_upload',
]);
$mustContain('modules/community/actions/admin-settings.php', [
    'privacy_consent_enabled',
    'privacy_consent_require_post',
    '개인정보 수집 및 이용동의 적용 대상을 하나 이상 선택해 주세요.',
]);
$mustContain('modules/community/views/admin-settings.php', [
    'community-settings-section-privacy-consent',
    'privacy_consent_title',
    'sr_community_privacy_consent_target_keys',
]);
$mustContain('modules/community/actions/admin-board-groups.php', [
    'group_privacy_consent_enabled',
    'group_privacy_consent_require_post',
    '개인정보 수집 및 이용동의 적용 대상을 하나 이상 선택해 주세요.',
]);
$mustContain('modules/community/views/admin-board-groups.php', [
    'community-board-group-section-privacy-consent',
    'group_privacy_consent_title',
    'sr_community_privacy_consent_target_keys',
]);
$mustContain('modules/community/views/admin-boards.php', [
    'community-board-section-privacy-consent',
    'privacy_consent_title',
    'sr_community_privacy_consent_target_keys',
]);
$mustContain('modules/community/actions/write.php', [
    'sr_community_privacy_consent_post_targets_from_request($values)',
    'sr_community_privacy_consent_validation_errors',
    'sr_community_record_submission_consents',
]);
$mustContain('modules/community/actions/edit.php', [
    'sr_community_privacy_consent_post_targets_from_request($values)',
    'sr_community_privacy_consent_validation_errors',
    'sr_community_record_submission_consents',
]);
$mustContain('modules/community/actions/body-file-upload.php', [
    'sr_community_privacy_consent_validation_errors($pdo, $board, [\'attachment_upload\'])',
]);
$mustContain('modules/ckeditor/assets/saanraan-ckeditor.js', [
    'community_privacy_consent_accepted',
]);
$mustContain('modules/community/actions/comment.php', [
    "['comment']",
    'sr_community_privacy_consent_validation_errors',
    'sr_community_record_submission_consents',
]);
$mustContain('modules/community/helpers/posts.php', [
    'privacy_consent_count',
    "pc.subject_type = \\'community.post\\'",
    "pc.subject_type = \\'community.comment\\'",
]);
$mustContain('modules/community/views/admin-posts.php', [
    '개인정보 동의',
    'sr_community_privacy_consent_admin_summary_html($post)',
    'sr_community_privacy_consent_admin_summary_html($comment)',
]);
$mustContain('modules/community/skins/basic/form.php', [
    'sr_community_privacy_consent_field_html',
    'attachment_upload',
    "\$communityPrivacyConsentBrowserRequired = sr_community_privacy_consent_required_for(\$pdo, \$board, 'post')",
]);
$mustContain('modules/community/skins/basic/view.php', [
    'sr_community_privacy_consent_field_html',
    'comment_reply_',
    'comment_new',
]);
$mustContain('modules/community/install.sql', [
    'CREATE TABLE IF NOT EXISTS sr_community_submission_consents',
    'consent_body_snapshot',
    'user_agent_hash',
]);
$mustContain('modules/community/updates/2026.06.019.sql', [
    'CREATE TABLE IF NOT EXISTS sr_community_submission_consents',
    "version = '2026.06.019'",
]);
$mustContain('modules/community/privacy-export.php', [
    'submission_consents',
    'sr_community_submission_consents',
    'consent_version_snapshot',
    'ip_hash',
    'user_agent_hash',
]);
$mustContain('modules/community/privacy-cleanup.php', [
    'sr_community_submission_consents',
    'community_submission_consent_anonymized_count',
]);

function sr_community_privacy_consent_check_pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $pdo;
}

function sr_community_privacy_consent_check_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

function sr_community_privacy_consent_check_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchColumn();
}

function sr_community_privacy_consent_check_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE sr_community_boards (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            board_group_id INTEGER NOT NULL DEFAULT 0,
            board_key TEXT NOT NULL,
            title TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_member_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            display_name TEXT NULL,
            status TEXT NOT NULL DEFAULT "active"
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_member_nicknames (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            nickname TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_community_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            board_id INTEGER NOT NULL,
            category_id INTEGER NULL,
            author_account_id INTEGER NULL,
            title TEXT NOT NULL,
            body_text TEXT NOT NULL DEFAULT "",
            body_format TEXT NOT NULL DEFAULT "plain",
            status TEXT NOT NULL DEFAULT "published",
            view_count INTEGER NOT NULL DEFAULT 0,
            last_commented_at TEXT NULL,
            extra_values_json TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_community_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER NOT NULL,
            author_account_id INTEGER NULL,
            body_text TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "published",
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_community_attachments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT "active"
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
        'CREATE TABLE sr_community_submission_consents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            board_id INTEGER NOT NULL,
            subject_type TEXT NOT NULL,
            subject_id INTEGER NOT NULL,
            action_key TEXT NOT NULL,
            account_id INTEGER NULL,
            consent_title_snapshot TEXT NOT NULL DEFAULT "",
            consent_body_snapshot TEXT NULL,
            consent_version_snapshot TEXT NOT NULL DEFAULT "",
            consent_required INTEGER NOT NULL DEFAULT 1,
            consent_accepted INTEGER NOT NULL DEFAULT 1,
            ip_hash TEXT NULL,
            user_agent_hash TEXT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec("INSERT INTO sr_community_boards (id, board_group_id, board_key, title) VALUES (1, 1, 'free', 'Free')");
    $pdo->exec("INSERT INTO sr_member_accounts (id, display_name, status) VALUES (7, 'Tester', 'active')");
    $pdo->exec("INSERT INTO sr_member_nicknames (account_id, nickname) VALUES (7, 'tester')");

    $now = sr_now();
    $pdo->prepare(
        'INSERT INTO sr_community_posts (id, board_id, author_account_id, title, body_text, status, extra_values_json, created_at, updated_at)
         VALUES (11, 1, 7, "동의 게시글", "본문", "published", "[]", :created_at, :updated_at)'
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $pdo->prepare(
        'INSERT INTO sr_community_comments (id, post_id, author_account_id, body_text, status, created_at, updated_at)
         VALUES (21, 11, 7, "동의 댓글", "published", :created_at, :updated_at)'
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_board_group_settings (group_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES (1, :setting_key, :setting_value, :value_type, :created_at, :updated_at)'
    );
    foreach ([
        ['privacy_consent_enabled', '1', 'bool'],
        ['privacy_consent_title', '그룹 동의', 'string'],
        ['privacy_consent_body', '그룹 동의 본문', 'string'],
        ['privacy_consent_version', 'g1', 'string'],
        ['privacy_consent_require_post', '1', 'bool'],
        ['privacy_consent_require_comment', '1', 'bool'],
        ['privacy_consent_require_attachment_upload', '0', 'bool'],
    ] as $row) {
        $stmt->execute([
            'setting_key' => $row[0],
            'setting_value' => $row[1],
            'value_type' => $row[2],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

function sr_community_privacy_consent_check_runtime(): void
{
    $normalized = sr_community_normalize_settings([
        'privacy_consent_enabled' => '1',
        'privacy_consent_title' => ' 전역 동의 ',
        'privacy_consent_body' => ' 전역 본문 ',
        'privacy_consent_version' => '',
        'privacy_consent_require_post' => '1',
    ]);
    sr_community_privacy_consent_check_assert(($normalized['privacy_consent_enabled'] ?? null) === true, 'community settings must normalize privacy consent enabled.');
    sr_community_privacy_consent_check_assert(($normalized['privacy_consent_title'] ?? '') === '전역 동의', 'community settings must trim privacy consent title.');
    sr_community_privacy_consent_check_assert(($normalized['privacy_consent_version'] ?? '') === '1', 'community settings must default empty privacy consent version.');
    sr_community_privacy_consent_check_assert(($normalized['privacy_consent_require_post'] ?? null) === true, 'community settings must normalize privacy consent post target.');

    $pdo = sr_community_privacy_consent_check_pdo();
    sr_community_privacy_consent_check_schema($pdo);
    $board = ['id' => 1, 'board_group_id' => 1, 'board_key' => 'free'];

    $config = sr_community_effective_privacy_consent_config($pdo, $board);
    sr_community_privacy_consent_check_assert(($config['enabled'] ?? null) === true, 'group privacy consent setting must enable board consent through fallback.');
    sr_community_privacy_consent_check_assert(($config['title'] ?? '') === '그룹 동의', 'group privacy consent title must be effective.');
    sr_community_privacy_consent_check_assert(in_array('post', (array) ($config['targets'] ?? []), true), 'group privacy consent post target must be effective.');
    sr_community_privacy_consent_check_assert(in_array('comment', (array) ($config['targets'] ?? []), true), 'group privacy consent comment target must be effective.');
    sr_community_privacy_consent_check_assert(!in_array('attachment_upload', (array) ($config['targets'] ?? []), true), 'disabled attachment consent target must remain disabled.');

    $_POST = [];
    $errorsWithoutConsent = sr_community_privacy_consent_validation_errors($pdo, $board, ['post']);
    sr_community_privacy_consent_check_assert($errorsWithoutConsent !== [], 'required privacy consent must reject missing acceptance.');
    $_POST = ['community_privacy_consent_accepted' => '1'];
    $errorsWithConsent = sr_community_privacy_consent_validation_errors($pdo, $board, ['post']);
    sr_community_privacy_consent_check_assert($errorsWithConsent === [], 'required privacy consent must accept checked POST value.');

    $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
    $_SERVER['HTTP_USER_AGENT'] = 'ConsentFixture/1.0';
    $inserted = sr_community_record_submission_consents($pdo, 1, 0, 'community.post', 11, ['post', 'attachment_upload'], $board);
    sr_community_privacy_consent_check_assert($inserted === 1, 'consent record must insert only required action snapshots.');
    sr_community_privacy_consent_check_assert((int) sr_community_privacy_consent_check_scalar($pdo, 'SELECT COUNT(*) FROM sr_community_submission_consents WHERE account_id IS NULL AND action_key = "post"') === 1, 'guest consent record must allow NULL account_id.');
    sr_community_privacy_consent_check_assert((string) sr_community_privacy_consent_check_scalar($pdo, 'SELECT consent_title_snapshot FROM sr_community_submission_consents WHERE id = 1') === '그룹 동의', 'consent record must snapshot effective title.');
    sr_community_privacy_consent_check_assert((string) sr_community_privacy_consent_check_scalar($pdo, 'SELECT consent_version_snapshot FROM sr_community_submission_consents WHERE id = 1') === 'g1', 'consent record must snapshot effective version.');
    sr_community_privacy_consent_check_assert((string) sr_community_privacy_consent_check_scalar($pdo, 'SELECT ip_hash FROM sr_community_submission_consents WHERE id = 1') === hash('sha256', '203.0.113.10'), 'consent record must hash IP.');
    $insertedComment = sr_community_record_submission_consents($pdo, 1, 7, 'community.comment', 21, ['comment'], $board);
    sr_community_privacy_consent_check_assert($insertedComment === 1, 'comment consent record must insert a snapshot.');

    $adminPosts = sr_community_admin_posts($pdo, 10);
    sr_community_privacy_consent_check_assert((int) ($adminPosts[0]['privacy_consent_count'] ?? 0) === 1, 'admin post list must expose consent count.');
    sr_community_privacy_consent_check_assert((string) ($adminPosts[0]['privacy_consent_latest_at'] ?? '') !== '', 'admin post list must expose latest consent timestamp.');
    $adminComments = sr_community_admin_comments($pdo, 10);
    sr_community_privacy_consent_check_assert((int) ($adminComments[0]['privacy_consent_count'] ?? 0) === 1, 'admin comment list must expose consent count.');
    sr_community_privacy_consent_check_assert((string) ($adminComments[0]['privacy_consent_latest_at'] ?? '') !== '', 'admin comment list must expose latest consent timestamp.');
    sr_community_privacy_consent_check_assert(str_contains(sr_community_privacy_consent_admin_summary_html($adminPosts[0]), '동의 1'), 'admin consent summary html must show consent count.');

    $now = sr_now();
    $pdo->prepare(
        'INSERT INTO sr_community_board_settings (board_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES (1, "privacy_consent_title", "게시판 동의", "string", :created_at, :updated_at)'
    )->execute(['created_at' => $now, 'updated_at' => $now]);
    $overrideConfig = sr_community_effective_privacy_consent_config($pdo, $board);
    sr_community_privacy_consent_check_assert(($overrideConfig['title'] ?? '') === '게시판 동의', 'board privacy consent setting must override group title.');
}

sr_community_privacy_consent_check_runtime();

if ($errors !== []) {
    fwrite(STDERR, "community privacy consent checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "community privacy consent checks completed.\n";
