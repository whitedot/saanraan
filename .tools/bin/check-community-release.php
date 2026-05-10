#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('TOY_ROOT', $root);
chdir($root);

$errors = [];

function toy_community_release_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function toy_community_release_array_file(string $path): array
{
    if (!is_file($path)) {
        toy_community_release_error('Required community release file is missing: ' . $path);
        return [];
    }

    $value = include $path;
    if (!is_array($value)) {
        toy_community_release_error('Community release file must return an array: ' . $path);
        return [];
    }

    return $value;
}

function toy_community_release_value_file(string $path): mixed
{
    if (!is_file($path)) {
        toy_community_release_error('Required community release file is missing: ' . $path);
        return null;
    }

    return include $path;
}

function toy_community_release_require_list_values(array $actualValues, array $requiredValues, string $label): void
{
    foreach ($requiredValues as $requiredValue) {
        if (!in_array($requiredValue, $actualValues, true)) {
            toy_community_release_error($label . ' is missing required value: ' . $requiredValue);
        }
    }
}

function toy_community_release_file_contains(string $path, array $needles, string $label): void
{
    if (!is_file($path)) {
        toy_community_release_error('Required community release file is missing: ' . $path);
        return;
    }

    $content = file_get_contents($path);
    if (!is_string($content)) {
        toy_community_release_error('Required community release file cannot be read: ' . $path);
        return;
    }

    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            toy_community_release_error($label . ' must contain: ' . $needle);
        }
    }
}

function toy_community_release_files(string $directory, array $extensions): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && in_array(strtolower($file->getExtension()), $extensions, true)) {
            $files[] = $file->getPathname();
        }
    }

    sort($files, SORT_STRING);
    return $files;
}

function toy_community_release_package_entries(string $directory): array
{
    $entries = [];
    foreach (new DirectoryIterator($directory) as $entry) {
        if ($entry->isDot()) {
            continue;
        }

        $entries[] = $entry->getFilename();
    }

    sort($entries, SORT_STRING);
    return $entries;
}

function toy_community_release_directory_is_empty(string $directory): bool
{
    if (!is_dir($directory)) {
        return true;
    }

    foreach (new DirectoryIterator($directory) as $entry) {
        if (!$entry->isDot()) {
            return false;
        }
    }

    return true;
}

$module = toy_community_release_array_file('modules/community/module.php');
$paths = toy_community_release_array_file('modules/community/paths.php');
$adminMenu = toy_community_release_array_file('modules/community/admin-menu.php');
$menuLinks = toy_community_release_array_file('modules/community/menu-links.php');
$extensionPoints = toy_community_release_array_file('modules/community/extension-points.php');
$memberGroupRules = toy_community_release_array_file('modules/community/member-group-rules.php');
$privacyExport = toy_community_release_value_file('modules/community/privacy-export.php');
$sitemap = toy_community_release_value_file('modules/community/sitemap.php');

if ((string) ($module['version'] ?? '') !== '2026.05.001') {
    toy_community_release_error('Community v1 release version must remain 2026.05.001 until update SQL is introduced.');
}

toy_community_release_file_contains('index.php', [
    "toy_enabled_module_contract_files(\$pdo, 'paths.php')",
], 'Front controller route loading');
toy_community_release_file_contains('core/actions/install.php', [
    "'community' => [",
    "'version' => '2026.05.001'",
    "'label' => '커뮤니티'",
    "'description' => '게시판, 댓글, 신고, 쪽지, 스크랩 기능을 설치합니다.'",
], 'Install optional community module');
toy_community_release_file_contains('.tools/bin/smoke-http.php', [
    'TOY_SMOKE_EXPECT_COMMUNITY=1',
    '$expectCommunity = getenv(\'TOY_SMOKE_EXPECT_COMMUNITY\') === \'1\'',
    '$check[\'expect_installed_route\'] = true',
    'returned 404 while TOY_SMOKE_EXPECT_COMMUNITY=1',
], 'Community installed HTTP smoke mode');

$requiredPackageEntries = [
    'actions',
    'admin-menu.php',
    'extension-points.php',
    'helpers',
    'helpers.php',
    'install.sql',
    'member-group-rules.php',
    'menu-links.php',
    'module.php',
    'paths.php',
    'privacy-export.php',
    'sitemap.php',
    'skins',
    'themes',
    'views',
];
$allowedPackageEntries = array_merge($requiredPackageEntries, [
    'lang',
    'updates',
]);
$packageEntries = toy_community_release_package_entries('modules/community');
toy_community_release_require_list_values($packageEntries, $requiredPackageEntries, 'Community package structure');
foreach ($packageEntries as $entry) {
    if (!in_array($entry, $allowedPackageEntries, true)) {
        toy_community_release_error('Community v1 package must not include unexpected top-level entry: modules/community/' . $entry);
    }
}

if (!toy_community_release_directory_is_empty('modules/community/lang')) {
    toy_community_release_error('Community v1 package must not include lang files before translation support is introduced.');
}

if (!toy_community_release_directory_is_empty('modules/community/updates')) {
    toy_community_release_error('Community v1 package must not include update files while version remains 2026.05.001.');
}

foreach (['actions', 'helpers', 'skins', 'themes', 'views'] as $requiredDirectory) {
    if (!is_dir('modules/community/' . $requiredDirectory)) {
        toy_community_release_error('Community package directory is missing: modules/community/' . $requiredDirectory);
    }
}

$requiredContracts = [
    'paths.php',
    'admin-menu.php',
    'menu-links.php',
    'extension-points.php',
    'privacy-export.php',
    'sitemap.php',
    'member-group-rules.php',
];
$provides = isset($module['contracts']['provides']) && is_array($module['contracts']['provides'])
    ? array_values(array_map('strval', $module['contracts']['provides']))
    : [];
toy_community_release_require_list_values($provides, $requiredContracts, 'Community contracts.provides');

$requiredModules = isset($module['requires']['modules']) && is_array($module['requires']['modules'])
    ? array_values(array_map('strval', $module['requires']['modules']))
    : [];
toy_community_release_require_list_values($requiredModules, ['member', 'admin'], 'Community requires.modules');

$requiredRoutes = [
    'GET /community',
    'GET /community/board',
    'GET /community/post',
    'GET /community/attachment',
    'GET /community/write',
    'POST /community/write',
    'GET /community/edit',
    'POST /community/edit',
    'POST /community/delete',
    'POST /community/comment',
    'POST /community/comment/edit',
    'POST /community/comment/delete',
    'POST /community/report',
    'GET /community/scraps',
    'POST /community/scrap',
    'GET /community/messages',
    'GET /community/message',
    'GET /community/message/write',
    'POST /community/message/write',
    'POST /community/message/delete',
    'GET /admin/community/boards',
    'POST /admin/community/boards',
    'GET /admin/community/posts',
    'POST /admin/community/posts',
    'GET /admin/community/reports',
    'POST /admin/community/reports',
];
toy_community_release_require_list_values(array_keys($paths), $requiredRoutes, 'Community paths.php');
foreach ($requiredRoutes as $route) {
    $actionPath = (string) ($paths[$route] ?? '');
    if (preg_match('/\Aactions\/[a-z0-9_\-\/]+\.php\z/', $actionPath) !== 1 || !is_file('modules/community/' . $actionPath)) {
        toy_community_release_error('Community paths.php route must map to an existing action file: ' . $route);
    }
}

$adminMenuPaths = [];
foreach ($adminMenu as $entry) {
    if (is_array($entry) && is_string($entry['path'] ?? null)) {
        $adminMenuPaths[] = (string) $entry['path'];
    }
}
toy_community_release_require_list_values($adminMenuPaths, [
    '/admin/community/boards',
    '/admin/community/reports',
    '/admin/community/posts',
], 'Community admin-menu.php');

$menuLinkUrls = [];
foreach ($menuLinks as $entry) {
    if (!is_array($entry)) {
        toy_community_release_error('Community menu-links.php entries must be arrays.');
        continue;
    }

    $label = is_string($entry['label'] ?? null) ? trim((string) $entry['label']) : '';
    $url = is_string($entry['url'] ?? null) ? (string) $entry['url'] : '';
    if ($label === '' || $url === '') {
        toy_community_release_error('Community menu-links.php entries must include label and url.');
        continue;
    }
    if ($url[0] !== '/' && preg_match('#\Ahttps?://#', $url) !== 1) {
        toy_community_release_error('Community menu-links.php url must be an internal path or http(s) URL: ' . $url);
    }

    $menuLinkUrls[] = $url;
}
toy_community_release_require_list_values($menuLinkUrls, [
    '/community',
    '/community/scraps',
    '/community/messages',
], 'Community menu-links.php');

$pointKeys = [];
foreach ($extensionPoints as $entry) {
    if (!is_array($entry)) {
        toy_community_release_error('Community extension-points.php entries must be arrays.');
        continue;
    }

    $pointKey = is_string($entry['point_key'] ?? null) ? (string) $entry['point_key'] : '';
    $label = is_string($entry['label'] ?? null) ? trim((string) $entry['label']) : '';
    if ($pointKey === '' || $label === '') {
        toy_community_release_error('Community extension-points.php entries must include point_key and label.');
        continue;
    }
    if (!isset($entry['slots']) || !is_array($entry['slots']) || $entry['slots'] === []) {
        toy_community_release_error('Community extension-points.php entries must include non-empty slots: ' . $pointKey);
        continue;
    }

    foreach ($entry['slots'] as $slot) {
        if (!is_array($slot) || !is_string($slot['slot_key'] ?? null) || trim((string) $slot['slot_key']) === '') {
            toy_community_release_error('Community extension-points.php slots must include slot_key: ' . $pointKey);
            continue;
        }
        if (!is_string($slot['kind'] ?? null) || (string) $slot['kind'] !== 'content') {
            toy_community_release_error('Community extension-points.php slots must use content kind: ' . $pointKey . ' ' . (string) $slot['slot_key']);
        }
    }

    $pointKeys[] = $pointKey;
}
toy_community_release_require_list_values($pointKeys, [
    'community.home',
    'community.board.list',
    'community.post.view',
    'community.post.form',
], 'Community extension-points.php');

if (!is_callable($privacyExport)) {
    toy_community_release_error('Community privacy-export.php must return a callable.');
}

if (!is_callable($sitemap)) {
    toy_community_release_error('Community sitemap.php must return a callable.');
}

toy_community_release_file_contains('modules/community/sitemap.php', [
    "WHERE status = 'enabled'",
    "AND read_policy = 'public'",
    "WHERE p.status = 'published'",
    "AND b.status = 'enabled'",
    "AND b.read_policy = 'public'",
    "'loc' => '/community/board?key='",
    "'loc' => '/community/post?id='",
], 'Community sitemap.php');

toy_community_release_file_contains('modules/community/privacy-export.php', [
    "'posts' => []",
    "'comments' => []",
    "'attachments' => []",
    "'reports' => []",
    "'messages' => []",
    "'scraps' => []",
    'WHERE author_account_id = :account_id',
    'WHERE uploader_account_id = :account_id',
    'WHERE reporter_account_id = :account_id',
    'WHERE sender_account_id = :account_id OR recipient_account_id = :account_id',
    'WHERE account_id = :account_id',
    'SELECT id, board_id, title, body_text, body_format, status, created_at, updated_at',
    'SELECT id, post_id, body_text, status, created_at, updated_at',
], 'Community privacy-export.php');
$privacyExportContent = is_file('modules/community/privacy-export.php') ? (string) file_get_contents('modules/community/privacy-export.php') : '';
if (str_contains($privacyExportContent, 'checksum_sha256')) {
    toy_community_release_error('Community privacy export must not include attachment checksum hashes.');
}

$memberGroupRuleKeys = [];
foreach ($memberGroupRules as $entry) {
    if (!is_array($entry)) {
        toy_community_release_error('Community member-group-rules.php entries must be arrays.');
        continue;
    }

    $ruleKey = is_string($entry['rule_key'] ?? null) ? (string) $entry['rule_key'] : '';
    $label = is_string($entry['label'] ?? null) ? trim((string) $entry['label']) : '';
    $evaluator = is_string($entry['evaluator'] ?? null) ? (string) $entry['evaluator'] : '';
    if ($ruleKey === '' || $label === '' || $evaluator === '') {
        toy_community_release_error('Community member-group-rules.php entries must include rule_key, label, and evaluator.');
        continue;
    }
    if (!str_starts_with($ruleKey, 'community.')) {
        toy_community_release_error('Community member-group-rules.php rule_key must start with community.: ' . $ruleKey);
    }
    if (!function_exists($evaluator)) {
        toy_community_release_error('Community member-group-rules.php evaluator must exist: ' . $evaluator);
    }
    if (!isset($entry['params']) || !is_array($entry['params']) || $entry['params'] === []) {
        toy_community_release_error('Community member-group-rules.php entries must include non-empty params: ' . $ruleKey);
    }

    $memberGroupRuleKeys[] = $ruleKey;
}
toy_community_release_require_list_values($memberGroupRuleKeys, [
    'community.board.post_count_at_least',
], 'Community member-group-rules.php');

$installSql = is_file('modules/community/install.sql') ? (string) file_get_contents('modules/community/install.sql') : '';
if ($installSql === '') {
    toy_community_release_error('Community install.sql must not be empty.');
}

$requiredTables = [
    'toy_community_boards',
    'toy_community_board_settings',
    'toy_community_posts',
    'toy_community_comments',
    'toy_community_attachments',
    'toy_community_reports',
    'toy_community_messages',
    'toy_community_scraps',
];
foreach ($requiredTables as $tableName) {
    if (!str_contains($installSql, 'CREATE TABLE IF NOT EXISTS ' . $tableName)) {
        toy_community_release_error('Community install.sql is missing table: ' . $tableName);
    }
}

$requiredInstallFragments = [
    'toy_community_boards' => [
        'board_key VARCHAR(60) NOT NULL',
        'UNIQUE KEY uq_toy_community_boards_key (board_key)',
        "('free', '자유게시판', '기본 커뮤니티 게시판입니다.', 'enabled', 'public', 'member', 'member', 1, 10, NOW(), NOW())",
    ],
    'toy_community_posts' => [
        'body_format VARCHAR(20) NOT NULL DEFAULT \'plain\'',
        'KEY idx_toy_community_posts_board_status_id (board_id, status, id)',
        'KEY idx_toy_community_posts_author_id (author_account_id, id)',
    ],
    'toy_community_comments' => [
        'KEY idx_toy_community_comments_post_status_id (post_id, status, id)',
        'KEY idx_toy_community_comments_author_id (author_account_id, id)',
    ],
    'toy_community_attachments' => [
        'checksum_sha256 CHAR(64) NOT NULL',
        'KEY idx_toy_community_attachments_checksum (checksum_sha256)',
    ],
    'toy_community_reports' => [
        'UNIQUE KEY uq_toy_community_reports_target_reporter (reporter_account_id, target_type, target_id)',
        'KEY idx_toy_community_reports_status_created (status, created_at)',
    ],
    'toy_community_messages' => [
        'sender_deleted_at DATETIME NULL',
        'recipient_deleted_at DATETIME NULL',
        'KEY idx_toy_community_messages_recipient_deleted_id (recipient_account_id, recipient_deleted_at, id)',
        'KEY idx_toy_community_messages_sender_deleted_id (sender_account_id, sender_deleted_at, id)',
    ],
    'toy_community_scraps' => [
        'UNIQUE KEY uq_toy_community_scraps_account_post (account_id, post_id)',
        'KEY idx_toy_community_scraps_account_id (account_id, id)',
    ],
];
foreach ($requiredInstallFragments as $installArea => $fragments) {
    foreach ($fragments as $fragment) {
        if (!str_contains($installSql, $fragment)) {
            toy_community_release_error('Community install.sql is missing required ' . $installArea . ' fragment: ' . $fragment);
        }
    }
}

preg_match_all('/CREATE TABLE IF NOT EXISTS\s+([a-z0-9_]+)/i', $installSql, $matches);
foreach ($matches[1] as $tableName) {
    if (!str_starts_with(strtolower((string) $tableName), 'toy_community_')) {
        toy_community_release_error('Community install.sql must only create toy_community_* tables: ' . (string) $tableName);
    }
}

$memberOnlyActions = [
    'modules/community/actions/write.php',
    'modules/community/actions/edit.php',
    'modules/community/actions/delete.php',
    'modules/community/actions/comment.php',
    'modules/community/actions/comment-edit.php',
    'modules/community/actions/comment-delete.php',
    'modules/community/actions/report.php',
    'modules/community/actions/scraps.php',
    'modules/community/actions/scrap-toggle.php',
    'modules/community/actions/messages.php',
    'modules/community/actions/message-view.php',
    'modules/community/actions/message-write.php',
    'modules/community/actions/message-delete.php',
];
foreach ($memberOnlyActions as $actionPath) {
    toy_community_release_file_contains($actionPath, ['toy_member_require_login($pdo)'], $actionPath);
}

toy_community_release_file_contains('modules/community/actions/attachment.php', [
    'toy_community_attachment_for_read($pdo, $attachmentId, is_array($account) ? $account : null)',
    'toy_community_attachment_read_board($pdo, $attachmentId)',
    'toy_community_board_requires_login($board)',
    'toy_member_require_login($pdo)',
    'toy_community_account_can_read_board($pdo, $board, is_array($account) ? $account : null)',
    'toy_community_attachment_mime_is_allowed($mimeType)',
    'hash_equals($recordedChecksum, $actualChecksum)',
    "header('X-Content-Type-Options: nosniff')",
    "header('Cache-Control: private, no-store, no-cache, must-revalidate')",
    'toy_finish_response()',
], 'Community attachment response');
toy_community_release_file_contains('modules/community/helpers/attachments.php', [
    "WHERE id = :id\n           AND status = 'active'",
    'toy_community_post_for_read($pdo, (int) $attachment[\'post_id\'], $account)',
    "p.status = 'published'",
    "b.status = 'enabled'",
    "mime_type IN ('image/jpeg', 'image/png', 'image/webp')",
    "realpath(TOY_ROOT . '/storage')",
    'str_starts_with($realPath, $storagePrefix)',
], 'Community attachment helpers');

toy_community_release_file_contains('modules/community/actions/write.php', [
    'toy_admin_has_role($pdo, (int) $account[\'id\'], [\'owner\', \'admin\', \'manager\'])',
    'toy_community_account_can_write_board($pdo, $board, $account, $isAdminWriter)',
    'toy_community_post_rate_limited($pdo, (int) $account[\'id\'], $settings)',
    'toy_community_record_post_rate_limit($pdo, (int) $account[\'id\'], $settings)',
    'toy_member_group_evaluate_account($pdo, (int) $account[\'id\'], [',
    'toy_community_upload_post_image($pdo, $postId, (int) $account[\'id\'], $_FILES[\'image_attachment\'], $settings)',
    "'event_type' => 'community.attachment.created'",
    "'event_type' => 'community.post.created'",
    'toy_community_member_group_evaluation_metadata($groupEvaluationSummary)',
], 'Community write action policy');
toy_community_release_file_contains('modules/community/actions/delete.php', [
    'toy_community_account_can_delete_post($post, $account)',
    'toy_community_update_post_status($pdo, $postId, \'deleted\')',
    'toy_member_group_evaluate_account($pdo, (int) $post[\'author_account_id\'], [',
    'toy_community_update_post_attachments_status($pdo, $postId, \'deleted\')',
    "'event_type' => 'community.post.deleted_by_author'",
    'toy_community_member_group_evaluation_metadata($groupEvaluationSummary)',
], 'Community delete action policy');

toy_community_release_file_contains('modules/community/actions/comment.php', [
    'toy_community_account_can_comment_post($pdo, $post, $account)',
    'toy_community_comment_rate_limited($pdo, (int) $account[\'id\'], $settings)',
    'toy_community_record_comment_rate_limit($pdo, (int) $account[\'id\'], $settings)',
    "'event_type' => 'community.comment.created'",
    'toy_community_create_account_notification(',
    "(int) \$post['author_account_id'] !== (int) \$account['id']",
], 'Community comment action policy');
toy_community_release_file_contains('modules/community/actions/comment-edit.php', [
    'toy_community_post_for_read($pdo, (int) $comment[\'post_id\'], $account)',
    'toy_community_account_can_edit_comment($comment, $account)',
    'toy_community_update_comment_content($pdo, $commentId, $values)',
    "'event_type' => 'community.comment.updated_by_author'",
], 'Community comment edit action policy');
toy_community_release_file_contains('modules/community/actions/comment-delete.php', [
    'toy_community_post_for_read($pdo, (int) $comment[\'post_id\'], $account)',
    'toy_community_account_can_delete_comment($comment, $account)',
    'toy_community_update_comment_status($pdo, $commentId, \'deleted\')',
    "'event_type' => 'community.comment.deleted_by_author'",
], 'Community comment delete action policy');
toy_community_release_file_contains('modules/community/actions/report.php', [
    'toy_community_report_target($pdo, $targetType, $targetId, (int) $account[\'id\'])',
    'in_array($reasonKey, toy_community_report_reason_keys(), true)',
    '(int) $target[\'reported_account_id\'] === (int) $account[\'id\']',
    'toy_community_report_rate_limited($pdo, (int) $account[\'id\'], $settings)',
    'toy_community_report_exists($pdo, (int) $account[\'id\'], (string) $target[\'target_type\'], (int) $target[\'target_id\'])',
    'toy_community_record_report_rate_limit($pdo, (int) $account[\'id\'], $settings)',
    "'event_type' => 'community.report.created'",
    'toy_community_create_admin_report_notifications(',
], 'Community report action policy');

toy_community_release_file_contains('modules/community/actions/message-write.php', [
    "toy_get_string('to_account', 40)",
    'toy_member_public_account_summary_by_hash($pdo, $config, $recipientAccountHash)',
    "'recipient_identifier' => ''",
], 'Community message write recipient preset');
$messageWriteContent = is_file('modules/community/actions/message-write.php') ? (string) file_get_contents('modules/community/actions/message-write.php') : '';
if (str_contains($messageWriteContent, "toy_get_string('to',")) {
    toy_community_release_error('Community message write must not accept recipient identifier from GET to parameter; use to_account public hash.');
}
toy_community_release_file_contains('modules/community/actions/scrap-toggle.php', [
    'toy_community_remove_scrap($pdo, (int) $account[\'id\'], $postId)',
    'toy_community_post_for_read($pdo, $postId, $account)',
    'toy_community_add_scrap($pdo, (int) $account[\'id\'], $postId)',
    "'event_type' => 'community.scrap.added'",
    "'event_type' => 'community.scrap.removed'",
], 'Community scrap action policy');
toy_community_release_file_contains('modules/community/helpers/scraps.php', [
    'INSERT IGNORE INTO toy_community_scraps',
    'WHERE s.account_id = :account_id',
    '$scrap[\'can_view\'] = (string) ($scrap[\'post_status\'] ?? \'\') === \'published\'',
    'toy_community_account_can_read_board($pdo, $board, $account)',
], 'Community scrap helper policy');
toy_community_release_file_contains('modules/community/actions/message-write.php', [
    'toy_member_public_account_summary_by_hash($pdo, $config, (string) $values[\'recipient_account_hash\'])',
    'toy_member_find_by_identifier($pdo, $config, (string) $values[\'recipient_identifier\'])',
    '(int) $recipient[\'id\'] === (int) $account[\'id\']',
    'toy_community_message_rate_limited($pdo, (int) $account[\'id\'], $settings)',
    'toy_community_record_message_rate_limit($pdo, (int) $account[\'id\'], $settings)',
    "'event_type' => 'community.message.sent'",
    'toy_community_create_account_notification(',
], 'Community message write policy');
toy_community_release_file_contains('modules/community/actions/message-delete.php', [
    'toy_community_message_participants_for_account($pdo, $messageId, (int) $account[\'id\'])',
    'toy_community_soft_delete_message($pdo, $message, (int) $account[\'id\'])',
    "'event_type' => 'community.message.deleted_by_account'",
], 'Community message delete policy');
toy_community_release_file_contains('modules/community/helpers/messages.php', [
    'recipient_account_hash',
    'toy_member_public_account_hash_is_valid($recipientAccountHash)',
    'toy_post_string_without_truncation(\'body_text\', 5000)',
    'toy_rate_limit_count($pdo, \'community.message.account\', (string) $accountId, $windowSeconds)',
    'toy_rate_limit_increment($pdo, \'community.message.account\', (string) $accountId, $windowSeconds)',
    'UPDATE toy_community_messages SET sender_deleted_at = :deleted_at',
    'UPDATE toy_community_messages SET recipient_deleted_at = :deleted_at',
], 'Community message helper policy');

toy_community_release_file_contains('modules/community/helpers/notifications.php', [
    'toy_module_enabled($pdo, \'notification\')',
    '$helperPath = TOY_ROOT . \'/modules/notification/helpers.php\'',
    'is_file($helperPath)',
    'require_once $helperPath',
    'function_exists(\'toy_notification_create\')',
    'try {',
    'toy_notification_create($pdo, [',
    "'audience' => 'account'",
    "'channels' => ['site']",
    'toy_log_exception($exception, \'community_notification_create\')',
    'toy_admin_account_roles',
    "r.role_key IN ('owner', 'admin', 'manager')",
    "a.status = 'active'",
    'toy_community_notification_admin_account_ids($pdo)',
    "'/admin/community/reports'",
], 'Community notification optional integration');
toy_community_release_file_contains('.tools/bin/smoke-community-auth.php', [
    "'intent' => 'add'",
    "toy_auth_smoke_assert_status(\$errors, 'scrap add', \$scrapResponse, [302])",
    "toy_auth_smoke_assert_body_contains(\$errors, 'scrap list', \$scraps, \$title)",
    "'intent' => 'remove'",
    "toy_auth_smoke_assert_status(\$errors, 'scrap remove', \$scrapRemoveResponse, [302])",
    "toy_auth_smoke_assert_body_not_contains(\$errors, 'scrap list after remove', \$scrapsAfterRemove, \$title)",
], 'Community authenticated smoke scrap flow');
toy_community_release_file_contains('.tools/bin/smoke-community-auth.php', [
    "toy_auth_smoke_request(\$baseUrl, 'GET', '/community/edit?id=' . (string) \$createdPostId, [], \$cookies)",
    "toy_auth_smoke_assert_status(\$errors, 'post edit form', \$editForm, [200])",
    "'post_id' => (string) \$createdPostId",
    "'title' => \$editedTitle",
    "'body_text' => \$editedBody",
    "toy_auth_smoke_assert_status(\$errors, 'post edit submit', \$editResponse, [302])",
    "toy_auth_smoke_assert_body_contains(\$errors, 'edited post view', \$editedPostView, \$editedTitle)",
    '$title = $editedTitle',
], 'Community authenticated smoke post edit flow');
toy_community_release_file_contains('.tools/bin/smoke-community-auth.php', [
    'function toy_auth_smoke_message_id_from_path(string $messagePath): string',
    '$sentMessageId = toy_auth_smoke_message_id_from_path($sentMessagePath)',
    "toy_auth_smoke_request(\$baseUrl, 'POST', '/community/message/delete', [",
    "'message_id' => \$sentMessageId",
    "toy_auth_smoke_assert_status(\$errors, 'sent message delete', \$messageDeleteResponse, [302])",
    "toy_auth_smoke_assert_body_not_contains(\$errors, 'sent message box after delete', \$sentMessagesAfterDelete, \$sentMessagePath)",
    "toy_auth_smoke_assert_status(\$errors, 'deleted sent message view', \$deletedSentMessageView, [404])",
], 'Community authenticated smoke message delete flow');
toy_community_release_file_contains('.tools/bin/smoke-community-auth.php', [
    'function toy_auth_smoke_comment_id_for_body(array $response, string $commentBody): string',
    'admin comment list did not contain comment body',
    "'intent' => 'comment_status'",
    "'comment_id' => \$commentId",
    "'status' => 'hidden'",
    "toy_auth_smoke_assert_status(\$errors, 'admin comment hide', \$commentHideResponse, [200])",
    "toy_auth_smoke_assert_body_not_contains(\$errors, 'post view after comment hide', \$postAfterCommentHide, \$commentBody)",
], 'Community authenticated smoke admin comment flow');

toy_community_release_file_contains('modules/community/actions/admin-boards.php', [
    'toy_admin_require_role($pdo, (int) $account[\'id\'], [\'owner\', \'admin\', \'manager\'])',
    'toy_admin_require_role($pdo, (int) $account[\'id\'], [\'owner\', \'admin\'])',
    '$allowedReadPolicies = toy_community_policy_values(\'read\')',
    '$allowedWritePolicies = toy_community_policy_values(\'write\')',
    '$allowedCommentPolicies = toy_community_policy_values(\'comment\')',
    '$memberGroups = toy_member_groups($pdo)',
    '(string) ($memberGroup[\'status\'] ?? \'\') !== \'enabled\'',
    'toy_admin_post_int_in_range(\'attachment_max_bytes\', 1024, 10485760)',
    'toy_admin_post_int_in_range(\'attachment_max_count\', 0, 10)',
    'toy_community_invalid_board_group_keys_from_input($groupKeysInput)',
    '$unknownGroupKeys = array_values(array_diff($groupKeys, $enabledMemberGroupKeys))',
    '(string) $policyGroupKeys[0] === \'group\' && $policyGroupKeys[1] === []',
    'toy_community_board_by_key($pdo, $boardKey) !== null',
    'toy_community_set_board_setting($pdo, $boardId, \'attachment_max_bytes\', (string) $attachmentMaxBytes, \'int\')',
    'toy_community_set_board_setting($pdo, $boardId, \'read_group_keys\', toy_community_board_group_keys_setting_value($readGroupKeys), \'json\')',
    "'event_type' => 'community.board.created'",
    "'event_type' => 'community.board.updated'",
], 'Community admin board policy');
toy_community_release_file_contains('modules/community/actions/admin-posts.php', [
    'toy_admin_require_role($pdo, (int) $account[\'id\'], [\'owner\', \'admin\', \'manager\'])',
    '$allowedPostStatuses = toy_community_post_statuses()',
    '$allowedCommentStatuses = toy_community_comment_statuses()',
    'toy_community_update_post_status($pdo, $postId, $status)',
    'toy_member_group_evaluate_account($pdo, (int) $post[\'author_account_id\'], [',
    'toy_community_update_post_attachments_status($pdo, $postId, $status)',
    'toy_community_restore_hidden_post_attachments($pdo, $postId)',
    "'event_type' => 'community.post.status_updated'",
    'toy_community_member_group_evaluation_metadata($groupEvaluationSummary)',
    'toy_community_update_comment_status($pdo, $commentId, $status)',
    "'event_type' => 'community.comment.status_updated'",
], 'Community admin post policy');
toy_community_release_file_contains('modules/community/actions/admin-reports.php', [
    'toy_admin_require_role($pdo, (int) $account[\'id\'], [\'owner\', \'admin\', \'manager\'])',
    '$allowedStatuses = toy_community_report_statuses()',
    'toy_post_string_without_truncation(\'review_note\', 1000)',
    'toy_community_report_by_id($pdo, $reportId)',
    'in_array($status, $allowedStatuses, true)',
    '$reviewNote === null',
    'toy_community_update_report_status($pdo, $reportId, $status, (int) $account[\'id\'], (string) $reviewNote)',
    "'event_type' => 'community.report.status_updated'",
    "'review_note_present' => trim((string) \$reviewNote) !== ''",
    "'reported_account_id' => (int) \$report['reported_account_id']",
], 'Community admin report policy');

$stateChangingActions = [
    'modules/community/actions/write.php',
    'modules/community/actions/edit.php',
    'modules/community/actions/delete.php',
    'modules/community/actions/comment.php',
    'modules/community/actions/comment-edit.php',
    'modules/community/actions/comment-delete.php',
    'modules/community/actions/report.php',
    'modules/community/actions/scrap-toggle.php',
    'modules/community/actions/message-write.php',
    'modules/community/actions/message-delete.php',
    'modules/community/actions/admin-boards.php',
    'modules/community/actions/admin-posts.php',
    'modules/community/actions/admin-reports.php',
];
foreach ($stateChangingActions as $actionPath) {
    toy_community_release_file_contains($actionPath, ['toy_require_csrf('], $actionPath);
}

foreach (toy_community_release_files('modules/community', ['css', 'scss', 'js']) as $assetFile) {
    toy_community_release_error('Community v1 must not ship dedicated CSS/JS assets: ' . $assetFile);
}

foreach (toy_community_release_files('modules/community', ['php']) as $phpFile) {
    $content = file_get_contents($phpFile);
    if (!is_string($content)) {
        continue;
    }

    foreach (['<style', 'style=', 'class=', 'data-'] as $forbiddenFragment) {
        if (str_contains($content, $forbiddenFragment)) {
            toy_community_release_error('Community v1 must not include styling hooks "' . $forbiddenFragment . '" in ' . $phpFile);
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "community release checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "community release checks completed.\n";
