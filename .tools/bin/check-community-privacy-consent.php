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

$mustNotContain = static function (string $file, array $markers) use (&$errors): void {
    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        $errors[] = 'cannot read ' . $file;
        return;
    }

    foreach ($markers as $marker) {
        if (str_contains($contents, (string) $marker)) {
            $errors[] = $file . ' must not contain marker: ' . (string) $marker;
        }
    }
};

$mustContain('modules/community/helpers/privacy-consents.php', [
    'sr_community_privacy_consent_setting_keys',
    'privacy_consent_document_key',
    'sr_policy_document_snapshot($pdo, $documentKey)',
    'sr_community_privacy_consent_admin_summary_html',
    'sr_community_privacy_consent_admin_document_key_from_settings',
    'privacy_consent_require_attachment_upload',
    'sr_community_privacy_consent_validation_errors',
    'sr_community_record_submission_consents',
    'sr_community_submission_consents_table_exists',
    "sr_module_enabled(\$pdo, 'policy_documents') && is_file(SR_ROOT . '/modules/policy_documents/helpers.php')",
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
$mustContain('modules/community/helpers/admin-boards.php', [
    'privacy_consent_enabled',
    'sr_community_privacy_consent_document_setting_key($privacyConsentTargetKey)',
    'array_key_exists($privacyConsentDocumentSettingKey, $_POST)',
    'sr_community_privacy_consent_policy_snapshot($pdo, $targetDocumentKey)',
    '개인정보 수집 및 이용동의 적용 대상을 하나 이상 선택해 주세요.',
    'privacy_consent_require_attachment_upload',
]);
$mustNotContain('modules/community/actions/admin-boards.php', [
    '개인정보 수집 및 이용동의 본문을 입력해 주세요.',
    '개인정보 수집 및 이용동의 버전을 입력해 주세요.',
    'reset(array_filter',
]);
$mustContain('modules/community/actions/admin-settings.php', [
    'privacy_consent_enabled',
    'sr_community_privacy_consent_policy_document_options($pdo',
    'sr_community_privacy_consent_document_setting_key($privacyConsentTargetKey)',
    'array_key_exists($privacyConsentDocumentSettingKey, $_POST)',
    'sr_community_privacy_consent_policy_snapshot($pdo, $targetDocumentKey)',
    'privacy_consent_require_post',
    '개인정보 수집 및 이용동의 적용 대상을 하나 이상 선택해 주세요.',
]);
$mustNotContain('modules/community/actions/admin-settings.php', [
    '개인정보 수집 및 이용동의 본문을 입력해 주세요.',
    '개인정보 수집 및 이용동의 버전을 입력해 주세요.',
    'reset(array_filter',
]);
$mustContain('modules/community/views/admin-settings.php', [
    'community-settings-section-privacy-consent',
    'sr_community_privacy_consent_document_setting_key($privacyConsentTargetKey)',
    'community-privacy-consent-document-row',
    '$privacyConsentDocumentOptions',
    'sr_community_privacy_consent_target_keys',
]);
$mustNotContain('modules/community/views/admin-settings.php', [
    'data-community-privacy-consent-target',
]);
$mustNotContain('modules/community/actions/admin-board-groups.php', [
    'group_privacy_consent_enabled',
    '개인정보 수집 및 이용동의 본문을 입력해 주세요.',
    '개인정보 수집 및 이용동의 버전을 입력해 주세요.',
    'reset(array_filter',
]);
$mustNotContain('modules/community/views/admin-board-groups.php', [
    'community-board-group-section-privacy-consent',
    'community-privacy-consent-document-row',
    'data-community-privacy-consent-target',
]);
$mustContain('modules/community/views/admin-boards.php', [
    'community-board-section-privacy-consent',
    'sr_community_privacy_consent_document_setting_key($privacyConsentTargetKey)',
    'community-privacy-consent-document-row',
    'sr_community_privacy_consent_target_keys',
]);
$mustNotContain('modules/community/views/admin-boards.php', [
    'data-community-privacy-consent-target',
    'source_\' . $privacyConsentSettingKey',
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
]);
$mustContain('modules/community/helpers/posts-comments.php', [
    'privacy_consent_count',
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
    'policy_document_key_snapshot',
    'consent_body_hash',
    'user_agent_hash',
]);
$mustContain('modules/community/updates/2026.06.025.sql', [
    'policy_document_key_snapshot',
    "version = '2026.06.025'",
]);
$mustContain('modules/community/updates/2026.06.019.sql', [
    'CREATE TABLE IF NOT EXISTS sr_community_submission_consents',
    "version = '2026.06.019'",
]);
$mustContain('modules/community/privacy-export.php', [
    'submission_consents',
    'sr_community_submission_consents',
    'SELECT *',
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
            author_public_name_snapshot TEXT NOT NULL DEFAULT "",
            guest_author_name TEXT NOT NULL DEFAULT "",
            guest_password_hash TEXT NULL,
            guest_ip_hash TEXT NULL,
            guest_user_agent_hash TEXT NULL,
            title TEXT NOT NULL,
            body_text TEXT NOT NULL DEFAULT "",
            body_format TEXT NOT NULL DEFAULT "plain",
            reaction_preset_key TEXT NOT NULL DEFAULT "",
            reaction_comment_preset_key TEXT NOT NULL DEFAULT "",
            seo_title TEXT NOT NULL DEFAULT "",
            seo_description TEXT NOT NULL DEFAULT "",
            og_title TEXT NOT NULL DEFAULT "",
            og_description TEXT NOT NULL DEFAULT "",
            og_image_attachment_id INTEGER NULL,
            is_secret INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT "published",
            hidden_at TEXT NULL,
            hidden_until TEXT NULL,
            hidden_reason TEXT NOT NULL DEFAULT "",
            hidden_note TEXT NULL,
            hidden_by_account_id INTEGER NULL,
            hidden_before_status TEXT NOT NULL DEFAULT "",
            summary_feed_candidate INTEGER NOT NULL DEFAULT 1,
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
            hidden_at TEXT NULL,
            hidden_until TEXT NULL,
            hidden_reason TEXT NOT NULL DEFAULT "",
            hidden_note TEXT NULL,
            hidden_by_account_id INTEGER NULL,
            hidden_before_status TEXT NOT NULL DEFAULT "",
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
            policy_document_key_snapshot TEXT NOT NULL DEFAULT "",
            policy_version_key_snapshot TEXT NOT NULL DEFAULT "",
            policy_document_version_id INTEGER NULL,
            consent_title_snapshot TEXT NOT NULL DEFAULT "",
            consent_body_snapshot TEXT NULL,
            consent_body_hash TEXT NOT NULL DEFAULT "",
            consent_version_snapshot TEXT NOT NULL DEFAULT "",
            consent_required INTEGER NOT NULL DEFAULT 1,
            consent_accepted INTEGER NOT NULL DEFAULT 1,
            ip_hash TEXT NULL,
            user_agent_hash TEXT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_modules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_key TEXT NOT NULL,
            status TEXT NOT NULL,
            version TEXT NOT NULL DEFAULT "2026.06.001"
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_policy_documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_key TEXT NOT NULL,
            title TEXT NOT NULL,
            description TEXT NULL,
            status TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 100,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE sr_policy_document_versions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_id INTEGER NOT NULL,
            title_snapshot TEXT NOT NULL,
            body_html TEXT NOT NULL,
            summary_text TEXT NULL,
            body_hash TEXT NOT NULL,
            append_previous_versions INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL,
            effective_from TEXT NULL,
            published_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $pdo->exec("INSERT INTO sr_modules (id, module_key, status, version) VALUES (1, 'policy_documents', 'enabled', '2026.06.002')");
    $pdo->exec("INSERT INTO sr_policy_documents (id, document_key, title, description, status, sort_order, created_at, updated_at) VALUES (1, 'community_privacy_default', '기본 커뮤니티 동의', '', 'enabled', 1, '', ''), (2, 'community_privacy_board', '게시판 커뮤니티 동의', '', 'enabled', 2, '', '')");
    $pdo->exec("INSERT INTO sr_policy_document_versions (id, document_id, title_snapshot, body_html, summary_text, body_hash, append_previous_versions, status, effective_from, published_at, created_at, updated_at) VALUES (1, 1, '기본 커뮤니티 동의', '<p>기본 본문</p>', '', '" . hash('sha256', '<p>기본 본문</p>') . "', 0, 'published', NULL, '', '', ''), (2, 2, '게시판 커뮤니티 동의', '<p>게시판 본문</p>', '', '" . hash('sha256', '<p>게시판 본문</p>') . "', 0, 'published', NULL, '', '', '')");
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
        'INSERT INTO sr_community_board_settings (board_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES (1, :setting_key, :setting_value, :value_type, :created_at, :updated_at)'
    );
    foreach ([
        ['privacy_consent_enabled', '1', 'bool'],
        ['privacy_consent_document_key', 'community_privacy_default', 'string'],
        ['privacy_consent_comment_document_key', 'community_privacy_board', 'string'],
        ['privacy_consent_attachment_upload_document_key', 'community_privacy_board', 'string'],
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
        'privacy_consent_document_key' => 'community_privacy_default',
        'privacy_consent_require_post' => '1',
    ]);
    sr_community_privacy_consent_check_assert(($normalized['privacy_consent_enabled'] ?? null) === true, 'community settings must normalize privacy consent enabled.');
    sr_community_privacy_consent_check_assert(($normalized['privacy_consent_document_key'] ?? '') === 'community_privacy_default', 'community settings must normalize privacy consent document key.');
    sr_community_privacy_consent_check_assert(($normalized['privacy_consent_require_post'] ?? null) === true, 'community settings must normalize privacy consent post target.');
    sr_community_privacy_consent_check_assert(sr_community_privacy_consent_admin_document_key_from_settings([
        'privacy_consent_document_key' => 'community_privacy_default',
        'privacy_consent_require_post' => '1',
    ], 'post') === 'community_privacy_default', 'admin document select must reflect legacy required post document.');
    sr_community_privacy_consent_check_assert(sr_community_privacy_consent_admin_document_key_from_settings([
        'privacy_consent_document_key' => 'community_privacy_default',
        'privacy_consent_comment_document_key' => 'community_privacy_board',
        'privacy_consent_require_comment' => '1',
    ], 'comment') === 'community_privacy_board', 'admin document select must prefer target document over legacy document.');
    sr_community_privacy_consent_check_assert(sr_community_privacy_consent_admin_document_key_from_settings([
        'privacy_consent_document_key' => 'community_privacy_default',
        'privacy_consent_require_attachment_upload' => '0',
    ], 'attachment_upload') === '', 'admin document select must keep disabled legacy target unselected.');

    $pdo = sr_community_privacy_consent_check_pdo();
    sr_community_privacy_consent_check_schema($pdo);
    $board = ['id' => 1, 'board_group_id' => 1, 'board_key' => 'free'];

    $config = sr_community_effective_privacy_consent_config($pdo, $board);
    sr_community_privacy_consent_check_assert(($config['enabled'] ?? null) === true, 'board privacy consent setting must enable board consent.');
    sr_community_privacy_consent_check_assert(($config['title'] ?? '') === '기본 커뮤니티 동의', 'board privacy consent title must come from policy document.');
    sr_community_privacy_consent_check_assert(in_array('post', (array) ($config['targets'] ?? []), true), 'board privacy consent post target must be effective.');
    sr_community_privacy_consent_check_assert(in_array('comment', (array) ($config['targets'] ?? []), true), 'board privacy consent comment target must be effective.');
    sr_community_privacy_consent_check_assert(!in_array('attachment_upload', (array) ($config['targets'] ?? []), true), 'disabled attachment consent target must remain disabled.');
    sr_community_privacy_consent_check_assert(sr_community_privacy_consent_required_actions($pdo, $board, ['attachment_upload']) === [], 'disabled attachment consent target must stay disabled even with a document key.');

    $fieldHtml = sr_community_privacy_consent_field_html($pdo, $board, ['post', 'comment'], true, 'fixture');
    sr_community_privacy_consent_check_assert(str_contains($fieldHtml, '기본 커뮤니티 동의'), 'public consent field must render the post policy document title.');
    sr_community_privacy_consent_check_assert(str_contains($fieldHtml, '게시판 커뮤니티 동의'), 'public consent field must render the comment policy document title.');
    sr_community_privacy_consent_check_assert(str_contains($fieldHtml, '게시글 작성/수정'), 'public consent field must show the post target label.');
    sr_community_privacy_consent_check_assert(str_contains($fieldHtml, '댓글 작성'), 'public consent field must show the comment target label.');
    sr_community_privacy_consent_check_assert(str_contains($fieldHtml, 'community_privacy_consent_accepted'), 'public consent field must render the acceptance input.');
    sr_community_privacy_consent_check_assert(sr_community_privacy_consent_field_html($pdo, $board, ['attachment_upload'], true, 'fixture') === '', 'public consent field must not render disabled attachment consent.');

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
    sr_community_privacy_consent_check_assert((string) sr_community_privacy_consent_check_scalar($pdo, 'SELECT policy_document_key_snapshot FROM sr_community_submission_consents WHERE id = 1') === 'community_privacy_default', 'consent record must snapshot policy document key.');
    sr_community_privacy_consent_check_assert((string) sr_community_privacy_consent_check_scalar($pdo, 'SELECT consent_title_snapshot FROM sr_community_submission_consents WHERE id = 1') === '기본 커뮤니티 동의', 'consent record must snapshot policy title.');
    sr_community_privacy_consent_check_assert((string) sr_community_privacy_consent_check_scalar($pdo, 'SELECT consent_version_snapshot FROM sr_community_submission_consents WHERE id = 1') === '1', 'consent record must snapshot policy version id.');
    sr_community_privacy_consent_check_assert((string) sr_community_privacy_consent_check_scalar($pdo, 'SELECT consent_body_hash FROM sr_community_submission_consents WHERE id = 1') === hash('sha256', '<p>기본 본문</p>'), 'consent record must snapshot policy body hash.');
    sr_community_privacy_consent_check_assert((string) sr_community_privacy_consent_check_scalar($pdo, 'SELECT ip_hash FROM sr_community_submission_consents WHERE id = 1') === hash('sha256', '203.0.113.10'), 'consent record must hash IP.');
    $insertedComment = sr_community_record_submission_consents($pdo, 1, 7, 'community.comment', 21, ['comment'], $board);
    sr_community_privacy_consent_check_assert($insertedComment === 1, 'comment consent record must insert a snapshot.');
    sr_community_privacy_consent_check_assert((string) sr_community_privacy_consent_check_scalar($pdo, 'SELECT policy_document_key_snapshot FROM sr_community_submission_consents WHERE id = 2') === 'community_privacy_board', 'comment consent record must snapshot target policy document key.');

    $adminPosts = sr_community_admin_posts($pdo, 10);
    sr_community_privacy_consent_check_assert((int) ($adminPosts[0]['privacy_consent_count'] ?? 0) === 1, 'admin post list must expose consent count.');
    sr_community_privacy_consent_check_assert((string) ($adminPosts[0]['privacy_consent_latest_at'] ?? '') !== '', 'admin post list must expose latest consent timestamp.');
    $adminComments = sr_community_admin_comments($pdo, 10);
    sr_community_privacy_consent_check_assert((int) ($adminComments[0]['privacy_consent_count'] ?? 0) === 1, 'admin comment list must expose consent count.');
    sr_community_privacy_consent_check_assert((string) ($adminComments[0]['privacy_consent_latest_at'] ?? '') !== '', 'admin comment list must expose latest consent timestamp.');
    sr_community_privacy_consent_check_assert(str_contains(sr_community_privacy_consent_admin_summary_html($adminPosts[0]), '동의 1'), 'admin consent summary html must show consent count.');

    $now = sr_now();
    $pdo->prepare(
        'UPDATE sr_community_board_settings
         SET setting_value = "community_privacy_board",
             updated_at = :updated_at
         WHERE board_id = 1
           AND setting_key = "privacy_consent_document_key"'
    )->execute(['updated_at' => $now]);
    $overrideConfig = sr_community_effective_privacy_consent_config($pdo, $board);
    sr_community_privacy_consent_check_assert(($overrideConfig['title'] ?? '') === '게시판 커뮤니티 동의', 'board privacy consent setting must update document key.');
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
