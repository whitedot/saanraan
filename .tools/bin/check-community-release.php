#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

$errors = [];

function sr_community_release_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_community_release_array_file(string $path): array
{
    if (!is_file($path)) {
        sr_community_release_error('Required community release file is missing: ' . $path);
        return [];
    }

    $value = include $path;
    if (!is_array($value)) {
        sr_community_release_error('Community release file must return an array: ' . $path);
        return [];
    }

    return $value;
}

function sr_community_release_value_file(string $path): mixed
{
    if (!is_file($path)) {
        sr_community_release_error('Required community release file is missing: ' . $path);
        return null;
    }

    return include $path;
}

function sr_community_release_require_list_values(array $actualValues, array $requiredValues, string $label): void
{
    foreach ($requiredValues as $requiredValue) {
        if (!in_array($requiredValue, $actualValues, true)) {
            sr_community_release_error($label . ' is missing required value: ' . $requiredValue);
        }
    }
}

function sr_community_release_file_contains(string $path, array $needles, string $label): void
{
    if (!is_file($path)) {
        sr_community_release_error('Required community release file is missing: ' . $path);
        return;
    }

    $content = file_get_contents($path);
    if (!is_string($content)) {
        sr_community_release_error('Required community release file cannot be read: ' . $path);
        return;
    }

    $normalizedContent = str_replace(["\r\n", "\r"], "\n", $content);

    foreach ($needles as $needle) {
        $normalizedNeedle = str_replace(["\r\n", "\r"], "\n", (string) $needle);
        if (!str_contains($normalizedContent, $normalizedNeedle)) {
            sr_community_release_error($label . ' must contain: ' . $needle);
        }
    }
}

function sr_community_release_files(string $directory, array $extensions): array
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

function sr_community_release_package_entries(string $directory): array
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

function sr_community_release_directory_is_empty(string $directory): bool
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

$module = sr_community_release_array_file('modules/community/module.php');
$paths = sr_community_release_array_file('modules/community/paths.php');
$adminMenu = sr_community_release_array_file('modules/community/admin-menu.php');
$menuLinks = sr_community_release_array_file('modules/community/menu-links.php');
$extensionPoints = sr_community_release_array_file('modules/community/extension-points.php');
$memberGroupRules = sr_community_release_array_file('modules/community/member-group-rules.php');
$privacyExport = sr_community_release_value_file('modules/community/privacy-export.php');
$sitemap = sr_community_release_value_file('modules/community/sitemap.php');

if ((string) ($module['version'] ?? '') !== '2026.05.010') {
    sr_community_release_error('Community module version must be 2026.05.010.');
}

sr_community_release_file_contains('index.php', [
    "sr_enabled_module_contract_files(\$pdo, 'paths.php')",
], 'Front controller route loading');
sr_community_release_file_contains('core/actions/install.php', [
    "'community' => [",
    "'version' => '2026.05.010'",
    "'label' => '커뮤니티'",
    "'description' => '게시판, 댓글, 신고, 쪽지, 스크랩 기능을 설치합니다.'",
], 'Install optional community module');
sr_community_release_file_contains('.tools/bin/smoke-http.php', [
    'SR_SMOKE_EXPECT_COMMUNITY=1',
    '$expectCommunity = getenv(\'SR_SMOKE_EXPECT_COMMUNITY\') === \'1\'',
    '$check[\'expect_installed_route\'] = true',
    'returned 404 while SR_SMOKE_EXPECT_COMMUNITY=1',
], 'Community installed HTTP smoke mode');

$requiredPackageEntries = [
    'actions',
    'admin-menu.php',
    'assets',
    'dashboard.php',
    'extension-points.php',
    'helpers',
    'helpers.php',
    'install.sql',
    'layout-options.php',
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
$packageEntries = sr_community_release_package_entries('modules/community');
sr_community_release_require_list_values($packageEntries, $requiredPackageEntries, 'Community package structure');
foreach ($packageEntries as $entry) {
    if (!in_array($entry, $allowedPackageEntries, true)) {
        sr_community_release_error('Community v1 package must not include unexpected top-level entry: modules/community/' . $entry);
    }
}

if (!sr_community_release_directory_is_empty('modules/community/lang')) {
    sr_community_release_error('Community v1 package must not include lang files before translation support is introduced.');
}

if (sr_community_release_directory_is_empty('modules/community/updates')) {
    sr_community_release_error('Community module updates directory must include schema updates after 2026.05.001.');
}
sr_community_release_file_contains('modules/community/updates/2026.05.004.sql', [
    "(10, '레벨 10', '커뮤니티 활동 점수 3000점 이상입니다.', 3000, 'enabled', 100, NOW(), NOW())",
    "SET version = '2026.05.004'",
], 'Community 2026.05.004 update');

foreach (['actions', 'helpers', 'skins', 'themes', 'views'] as $requiredDirectory) {
    if (!is_dir('modules/community/' . $requiredDirectory)) {
        sr_community_release_error('Community package directory is missing: modules/community/' . $requiredDirectory);
    }
}

$requiredContracts = [
    'paths.php',
    'admin-menu.php',
    'dashboard.php',
    'menu-links.php',
    'extension-points.php',
    'privacy-export.php',
    'sitemap.php',
    'member-group-rules.php',
];
$provides = isset($module['contracts']['provides']) && is_array($module['contracts']['provides'])
    ? array_values(array_map('strval', $module['contracts']['provides']))
    : [];
sr_community_release_require_list_values($provides, $requiredContracts, 'Community contracts.provides');

$requiredModules = isset($module['requires']['modules']) && is_array($module['requires']['modules'])
    ? array_values(array_map('strval', $module['requires']['modules']))
    : [];
sr_community_release_require_list_values($requiredModules, ['member', 'admin'], 'Community requires.modules');

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
    'GET /admin/community/settings',
    'POST /admin/community/settings',
    'GET /admin/community/boards',
    'POST /admin/community/boards',
    'GET /admin/community/board-groups',
    'POST /admin/community/board-groups',
    'GET /admin/community/posts',
    'POST /admin/community/posts',
    'GET /admin/community/reports',
    'POST /admin/community/reports',
];
sr_community_release_require_list_values(array_keys($paths), $requiredRoutes, 'Community paths.php');
foreach ($requiredRoutes as $route) {
    $actionPath = (string) ($paths[$route] ?? '');
    if (preg_match('/\Aactions\/[a-z0-9_\-\/]+\.php\z/', $actionPath) !== 1 || !is_file('modules/community/' . $actionPath)) {
        sr_community_release_error('Community paths.php route must map to an existing action file: ' . $route);
    }
}

$adminMenuPaths = [];
$adminMenuItems = isset($adminMenu['items']) && is_array($adminMenu['items']) ? $adminMenu['items'] : $adminMenu;
foreach ($adminMenuItems as $entry) {
    if (is_array($entry) && is_string($entry['path'] ?? null)) {
        $adminMenuPaths[] = (string) $entry['path'];
    }
}
if (($adminMenu['label'] ?? null) !== '커뮤니티') {
    sr_community_release_error('Community admin-menu.php must use the community module group label.');
}
sr_community_release_require_list_values($adminMenuPaths, [
    '/admin/community/settings',
    '/admin/community/boards',
    '/admin/community/board-groups',
    '/admin/community/reports',
    '/admin/community/posts',
], 'Community admin-menu.php');

$menuLinkUrls = [];
foreach ($menuLinks as $entry) {
    if (!is_array($entry)) {
        sr_community_release_error('Community menu-links.php entries must be arrays.');
        continue;
    }

    $label = is_string($entry['label'] ?? null) ? trim((string) $entry['label']) : '';
    $url = is_string($entry['url'] ?? null) ? (string) $entry['url'] : '';
    if ($label === '' || $url === '') {
        sr_community_release_error('Community menu-links.php entries must include label and url.');
        continue;
    }
    if ($url[0] !== '/' && preg_match('#\Ahttps?://#', $url) !== 1) {
        sr_community_release_error('Community menu-links.php url must be an internal path or http(s) URL: ' . $url);
    }

    $menuLinkUrls[] = $url;
}
sr_community_release_require_list_values($menuLinkUrls, [
    '/community',
    '/community/scraps',
    '/community/messages',
], 'Community menu-links.php');

$pointKeys = [];
foreach ($extensionPoints as $entry) {
    if (!is_array($entry)) {
        sr_community_release_error('Community extension-points.php entries must be arrays.');
        continue;
    }

    $pointKey = is_string($entry['point_key'] ?? null) ? (string) $entry['point_key'] : '';
    $label = is_string($entry['label'] ?? null) ? trim((string) $entry['label']) : '';
    if ($pointKey === '' || $label === '') {
        sr_community_release_error('Community extension-points.php entries must include point_key and label.');
        continue;
    }
    if (!isset($entry['slots']) || !is_array($entry['slots']) || $entry['slots'] === []) {
        sr_community_release_error('Community extension-points.php entries must include non-empty slots: ' . $pointKey);
        continue;
    }

    foreach ($entry['slots'] as $slot) {
        if (!is_array($slot) || !is_string($slot['slot_key'] ?? null) || trim((string) $slot['slot_key']) === '') {
            sr_community_release_error('Community extension-points.php slots must include slot_key: ' . $pointKey);
            continue;
        }
        if (!is_string($slot['kind'] ?? null) || (string) $slot['kind'] !== 'content') {
            sr_community_release_error('Community extension-points.php slots must use content kind: ' . $pointKey . ' ' . (string) $slot['slot_key']);
        }
    }

    $pointKeys[] = $pointKey;
}
sr_community_release_require_list_values($pointKeys, [
    'community.home',
    'community.board.list',
    'community.post.view',
    'community.post.form',
], 'Community extension-points.php');
sr_community_release_file_contains('modules/community/extension-points.php', [
    "'point_key' => 'community.post.view'",
    "'slot_key' => 'before_content'",
    "'slot_key' => 'after_content'",
    "'point_key' => 'community.post.form'",
    "'slot_key' => 'before_form'",
    "'slot_key' => 'after_form'",
], 'Community extension-points.php major surfaces');

if (!is_callable($privacyExport)) {
    sr_community_release_error('Community privacy-export.php must return a callable.');
}

if (!is_callable($sitemap)) {
    sr_community_release_error('Community sitemap.php must return a callable.');
}

sr_community_release_file_contains('modules/community/sitemap.php', [
    "WHERE status = 'enabled'",
    'sr_community_account_can_read_board($pdo, $board, null)',
    "WHERE p.status = 'published'",
    "AND b.status = 'enabled'",
    'sr_community_account_can_read_board($pdo, $board, null)',
    "'loc' => '/community/board?key='",
    "'loc' => '/community/post?id='",
], 'Community sitemap.php');

sr_community_release_file_contains('modules/community/privacy-export.php', [
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
    sr_community_release_error('Community privacy export must not include attachment checksum hashes.');
}

$memberGroupRuleKeys = [];
foreach ($memberGroupRules as $entry) {
    if (!is_array($entry)) {
        sr_community_release_error('Community member-group-rules.php entries must be arrays.');
        continue;
    }

    $ruleKey = is_string($entry['rule_key'] ?? null) ? (string) $entry['rule_key'] : '';
    $label = is_string($entry['label'] ?? null) ? trim((string) $entry['label']) : '';
    $evaluator = is_string($entry['evaluator'] ?? null) ? (string) $entry['evaluator'] : '';
    if ($ruleKey === '' || $label === '' || $evaluator === '') {
        sr_community_release_error('Community member-group-rules.php entries must include rule_key, label, and evaluator.');
        continue;
    }
    if (!str_starts_with($ruleKey, 'community.')) {
        sr_community_release_error('Community member-group-rules.php rule_key must start with community.: ' . $ruleKey);
    }
    if (!function_exists($evaluator)) {
        sr_community_release_error('Community member-group-rules.php evaluator must exist: ' . $evaluator);
    }
    if (!isset($entry['params']) || !is_array($entry['params']) || $entry['params'] === []) {
        sr_community_release_error('Community member-group-rules.php entries must include non-empty params: ' . $ruleKey);
    }

    $memberGroupRuleKeys[] = $ruleKey;
}
sr_community_release_require_list_values($memberGroupRuleKeys, [
    'community.board.post_count_at_least',
], 'Community member-group-rules.php');

$installSql = is_file('modules/community/install.sql') ? (string) file_get_contents('modules/community/install.sql') : '';
if ($installSql === '') {
    sr_community_release_error('Community install.sql must not be empty.');
}

$requiredTables = [
    'sr_community_board_groups',
    'sr_community_boards',
    'sr_community_board_settings',
    'sr_community_board_group_settings',
    'sr_community_board_setting_sources',
    'sr_community_posts',
    'sr_community_comments',
    'sr_community_attachments',
    'sr_community_reports',
    'sr_community_messages',
    'sr_community_scraps',
    'sr_community_levels',
    'sr_community_account_levels',
    'sr_community_level_logs',
];
foreach ($requiredTables as $tableName) {
    if (!str_contains($installSql, 'CREATE TABLE IF NOT EXISTS ' . $tableName)) {
        sr_community_release_error('Community install.sql is missing table: ' . $tableName);
    }
}

$requiredInstallFragments = [
    'sr_community_boards' => [
        'board_group_id BIGINT UNSIGNED NULL',
        'board_key VARCHAR(60) NOT NULL',
        'UNIQUE KEY uq_sr_community_boards_key (board_key)',
        'KEY idx_sr_community_boards_group_sort (board_group_id, sort_order, id)',
        "('free', '자유게시판', '기본 커뮤니티 게시판입니다.', 'enabled', 'public', 'member', 'member', 1, 10, NOW(), NOW())",
    ],
    'sr_community_board_groups' => [
        'UNIQUE KEY uq_sr_community_board_groups_key (group_key)',
        'KEY idx_sr_community_board_groups_status_sort (status, sort_order, id)',
    ],
    'sr_community_board_group_settings' => [
        'UNIQUE KEY uq_sr_community_board_group_settings_key (group_id, setting_key)',
    ],
    'sr_community_board_setting_sources' => [
        'UNIQUE KEY uq_sr_community_board_setting_sources_key (board_id, setting_key)',
    ],
    'sr_community_posts' => [
        'body_format VARCHAR(20) NOT NULL DEFAULT \'plain\'',
        'KEY idx_sr_community_posts_board_status_id (board_id, status, id)',
        'KEY idx_sr_community_posts_author_id (author_account_id, id)',
    ],
    'sr_community_comments' => [
        'KEY idx_sr_community_comments_post_status_id (post_id, status, id)',
        'KEY idx_sr_community_comments_author_id (author_account_id, id)',
    ],
    'sr_community_attachments' => [
        'checksum_sha256 CHAR(64) NOT NULL',
        'KEY idx_sr_community_attachments_checksum (checksum_sha256)',
    ],
    'sr_community_reports' => [
        'UNIQUE KEY uq_sr_community_reports_target_reporter (reporter_account_id, target_type, target_id)',
        'KEY idx_sr_community_reports_status_created (status, created_at)',
    ],
    'sr_community_messages' => [
        'sender_deleted_at DATETIME NULL',
        'recipient_deleted_at DATETIME NULL',
        'KEY idx_sr_community_messages_recipient_deleted_id (recipient_account_id, recipient_deleted_at, id)',
        'KEY idx_sr_community_messages_sender_deleted_id (sender_account_id, sender_deleted_at, id)',
    ],
    'sr_community_scraps' => [
        'UNIQUE KEY uq_sr_community_scraps_account_post (account_id, post_id)',
        'KEY idx_sr_community_scraps_account_id (account_id, id)',
    ],
    'sr_community_levels' => [
        'UNIQUE KEY uq_sr_community_levels_value (level_value)',
        'KEY idx_sr_community_levels_status_score (status, min_score, level_value)',
        "(10, '레벨 10', '커뮤니티 활동 점수 3000점 이상입니다.', 3000, 'enabled', 100, NOW(), NOW())",
    ],
    'sr_community_account_levels' => [
        'UNIQUE KEY uq_sr_community_account_levels_account (account_id)',
        'KEY idx_sr_community_account_levels_level (level_value, account_id)',
    ],
    'sr_community_level_logs' => [
        'KEY idx_sr_community_level_logs_account_id (account_id, id)',
    ],
];
foreach ($requiredInstallFragments as $installArea => $fragments) {
    foreach ($fragments as $fragment) {
        if (!str_contains($installSql, $fragment)) {
            sr_community_release_error('Community install.sql is missing required ' . $installArea . ' fragment: ' . $fragment);
        }
    }
}

preg_match_all('/CREATE TABLE IF NOT EXISTS\s+([a-z0-9_]+)/i', $installSql, $matches);
foreach ($matches[1] as $tableName) {
    if (!str_starts_with(strtolower((string) $tableName), 'sr_community_')) {
        sr_community_release_error('Community install.sql must only create sr_community_* tables: ' . (string) $tableName);
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
    sr_community_release_file_contains($actionPath, ['sr_member_require_login($pdo)'], $actionPath);
}

sr_community_release_file_contains('modules/community/actions/attachment.php', [
    'sr_community_attachment_for_read($pdo, $attachmentId, is_array($account) ? $account : null)',
    'sr_community_attachment_read_board($pdo, $attachmentId)',
    "sr_community_effective_board_policy(\$pdo, \$board, 'read_policy')",
    'sr_member_require_login($pdo)',
    'sr_community_account_can_read_board($pdo, $board, is_array($account) ? $account : null)',
    'sr_community_attachment_mime_is_allowed($mimeType)',
    'hash_equals($recordedChecksum, $actualChecksum)',
    "header('X-Content-Type-Options: nosniff')",
    "header('Cache-Control: private, no-store, no-cache, must-revalidate')",
    'sr_finish_response()',
], 'Community attachment response');
sr_community_release_file_contains('modules/community/helpers/attachments.php', [
    "WHERE id = :id\n           AND status = 'active'",
    'sr_community_post_for_read($pdo, (int) $attachment[\'post_id\'], $account)',
    "p.status = 'published'",
    "b.status = 'enabled'",
    'function sr_community_upload_post_files',
    'function sr_community_attachment_is_image',
    'function sr_community_file_extension_mime_map',
    "realpath(SR_ROOT . '/storage')",
    'str_starts_with($realPath, $storagePrefix)',
], 'Community attachment helpers');

sr_community_release_file_contains('modules/community/actions/write.php', [
    'sr_admin_has_role($pdo, (int) $account[\'id\'], [\'owner\', \'admin\', \'manager\'])',
    'sr_community_account_can_write_board($pdo, $board, $account, $isAdminWriter)',
    'sr_community_post_rate_limited($pdo, (int) $account[\'id\'], $settings)',
    'sr_community_record_post_rate_limit($pdo, (int) $account[\'id\'], $settings)',
    'sr_member_group_evaluate_account($pdo, (int) $account[\'id\'], [',
    'sr_community_maybe_recalculate_account_level($pdo, (int) $account[\'id\'], $settings, \'post_created\')',
    'sr_community_upload_post_image($pdo, $postId, (int) $account[\'id\'], $_FILES[\'image_attachment\'], $settings)',
    'sr_community_upload_post_files($pdo, $postId, (int) $account[\'id\'], $_FILES[\'file_attachments\'], $settings)',
    "'event_type' => 'community.attachment.created'",
    "'event_type' => 'community.post.created'",
    'sr_community_member_group_evaluation_metadata($groupEvaluationSummary)',
], 'Community write action policy');
sr_community_release_file_contains('modules/community/actions/view.php', [
    '$postBoard = sr_community_board_by_id($pdo, (int) $post[\'board_id\'])',
    "'banner_before_view_id'",
    "'banner_after_view_id'",
    "'popup_layer_view_id'",
], 'Community view display settings');
sr_community_release_file_contains('modules/community/actions/edit.php', [
    '$board = sr_community_board_by_id($pdo, (int) $post[\'board_id\'])',
    "'board_key' => (string) \$post['board_key']",
], 'Community edit form board settings');
sr_community_release_file_contains('modules/community/skins/basic/view.php', [
    'sr_popup_layer_render_public_layer($pdo, (int) ($post[\'popup_layer_view_id\'] ?? 0))',
    'sr_banner_render_public_banner($pdo, (int) ($post[\'banner_before_view_id\'] ?? 0))',
    'sr_banner_render_public_banner($pdo, (int) ($post[\'banner_after_view_id\'] ?? 0))',
], 'Community post view public display');
sr_community_release_file_contains('modules/community/skins/basic/form.php', [
    'sr_popup_layer_render_public_layer($pdo, (int) ($board[\'popup_layer_form_id\'] ?? 0))',
    'sr_banner_render_public_banner($pdo, (int) ($board[\'banner_before_form_id\'] ?? 0))',
    'sr_banner_render_public_banner($pdo, (int) ($board[\'banner_after_form_id\'] ?? 0))',
], 'Community post form public display');
sr_community_release_file_contains('modules/community/actions/delete.php', [
    'sr_community_account_can_delete_post($post, $account)',
    'sr_community_update_post_status($pdo, $postId, \'deleted\')',
    'sr_member_group_evaluate_account($pdo, (int) $post[\'author_account_id\'], [',
    'sr_community_maybe_recalculate_account_level($pdo, (int) $post[\'author_account_id\'], null, \'post_deleted\')',
    'sr_community_update_post_attachments_status($pdo, $postId, \'deleted\')',
    "'event_type' => 'community.post.deleted_by_author'",
    'sr_community_member_group_evaluation_metadata($groupEvaluationSummary)',
], 'Community delete action policy');

sr_community_release_file_contains('modules/community/actions/comment.php', [
    'sr_community_account_can_comment_post($pdo, $post, $account)',
    'sr_community_comment_rate_limited($pdo, (int) $account[\'id\'], $settings)',
    'sr_community_record_comment_rate_limit($pdo, (int) $account[\'id\'], $settings)',
    'sr_community_maybe_recalculate_account_level($pdo, (int) $account[\'id\'], $settings, \'comment_created\')',
    "'event_type' => 'community.comment.created'",
    'sr_community_create_account_notification(',
    "(int) \$post['author_account_id'] !== (int) \$account['id']",
], 'Community comment action policy');
sr_community_release_file_contains('modules/community/actions/comment-edit.php', [
    'sr_community_post_for_read($pdo, (int) $comment[\'post_id\'], $account)',
    'sr_community_account_can_edit_comment($comment, $account)',
    'sr_community_update_comment_content($pdo, $commentId, $values)',
    "'event_type' => 'community.comment.updated_by_author'",
], 'Community comment edit action policy');
sr_community_release_file_contains('modules/community/actions/comment-delete.php', [
    'sr_community_post_for_read($pdo, (int) $comment[\'post_id\'], $account)',
    'sr_community_account_can_delete_comment($comment, $account)',
    'sr_community_update_comment_status($pdo, $commentId, \'deleted\')',
    'sr_community_maybe_recalculate_account_level($pdo, (int) $comment[\'author_account_id\'], null, \'comment_deleted\')',
    "'event_type' => 'community.comment.deleted_by_author'",
], 'Community comment delete action policy');
sr_community_release_file_contains('modules/community/actions/report.php', [
    'sr_community_report_target($pdo, $targetType, $targetId, (int) $account[\'id\'])',
    'in_array($reasonKey, sr_community_report_reason_keys(), true)',
    '(int) $target[\'reported_account_id\'] === (int) $account[\'id\']',
    'sr_community_report_rate_limited($pdo, (int) $account[\'id\'], $settings)',
    'sr_community_report_exists($pdo, (int) $account[\'id\'], (string) $target[\'target_type\'], (int) $target[\'target_id\'])',
    'sr_community_record_report_rate_limit($pdo, (int) $account[\'id\'], $settings)',
    "'event_type' => 'community.report.created'",
    'sr_community_create_admin_report_notifications(',
], 'Community report action policy');

sr_community_release_file_contains('modules/community/actions/message-write.php', [
    "sr_get_string('to_account', 40)",
    'sr_member_public_account_summary_by_hash($pdo, $config, $recipientAccountHash)',
    "'recipient_identifier' => ''",
], 'Community message write recipient preset');
$messageWriteContent = is_file('modules/community/actions/message-write.php') ? (string) file_get_contents('modules/community/actions/message-write.php') : '';
if (str_contains($messageWriteContent, "sr_get_string('to',")) {
    sr_community_release_error('Community message write must not accept recipient identifier from GET to parameter; use to_account public hash.');
}
sr_community_release_file_contains('modules/community/actions/scrap-toggle.php', [
    'sr_community_remove_scrap($pdo, (int) $account[\'id\'], $postId)',
    'sr_community_post_for_read($pdo, $postId, $account)',
    'sr_community_add_scrap($pdo, (int) $account[\'id\'], $postId)',
    "'event_type' => 'community.scrap.added'",
    "'event_type' => 'community.scrap.removed'",
], 'Community scrap action policy');
sr_community_release_file_contains('modules/community/helpers/scraps.php', [
    'INSERT IGNORE INTO sr_community_scraps',
    'WHERE s.account_id = :account_id',
    '$scrap[\'can_view\'] = (string) ($scrap[\'post_status\'] ?? \'\') === \'published\'',
    'sr_community_account_can_read_board($pdo, $board, $account)',
], 'Community scrap helper policy');
sr_community_release_file_contains('modules/community/actions/message-write.php', [
    'sr_member_public_account_summary_by_hash($pdo, $config, (string) $values[\'recipient_account_hash\'])',
    'sr_member_find_by_identifier($pdo, $config, (string) $values[\'recipient_identifier\'])',
    '(int) $recipient[\'id\'] === (int) $account[\'id\']',
    'sr_community_message_rate_limited($pdo, (int) $account[\'id\'], $settings)',
    'sr_community_account_can_write_message($pdo, $account, $settings)',
    'sr_community_record_message_rate_limit($pdo, (int) $account[\'id\'], $settings)',
    "'event_type' => 'community.message.sent'",
    'sr_community_create_account_notification(',
], 'Community message write policy');
sr_community_release_file_contains('modules/community/actions/message-delete.php', [
    'sr_community_message_participants_for_account($pdo, $messageId, (int) $account[\'id\'])',
    'sr_community_soft_delete_message($pdo, $message, (int) $account[\'id\'])',
    "'event_type' => 'community.message.deleted_by_account'",
], 'Community message delete policy');
sr_community_release_file_contains('modules/community/helpers/messages.php', [
    'recipient_account_hash',
    'sr_member_public_account_hash_is_valid($recipientAccountHash)',
    'sr_post_string_without_truncation(\'body_text\', 5000)',
    'sr_rate_limit_count($pdo, \'community.message.account\', (string) $accountId, $windowSeconds)',
    'sr_rate_limit_increment($pdo, \'community.message.account\', (string) $accountId, $windowSeconds)',
    'UPDATE sr_community_messages SET sender_deleted_at = :deleted_at',
    'UPDATE sr_community_messages SET recipient_deleted_at = :deleted_at',
], 'Community message helper policy');

sr_community_release_file_contains('modules/community/helpers/notifications.php', [
    'sr_module_enabled($pdo, \'notification\')',
    '$helperPath = SR_ROOT . \'/modules/notification/helpers.php\'',
    'is_file($helperPath)',
    'require_once $helperPath',
    'function_exists(\'sr_notification_create\')',
    'try {',
    'sr_notification_create($pdo, [',
    "'audience' => 'account'",
    "'channels' => ['site']",
    'sr_log_exception($exception, \'community_notification_create\')',
    'sr_admin_account_roles',
    "r.role_key IN ('owner', 'admin', 'manager')",
    "a.status = 'active'",
    'sr_community_notification_admin_account_ids($pdo)',
    "'/admin/community/reports'",
], 'Community notification optional integration');
sr_community_release_file_contains('.tools/bin/smoke-community-auth.php', [
    "'intent' => 'add'",
    "sr_auth_smoke_assert_status(\$errors, 'scrap add', \$scrapResponse, [302])",
    "sr_auth_smoke_assert_body_contains(\$errors, 'scrap list', \$scraps, \$title)",
    "'intent' => 'remove'",
    "sr_auth_smoke_assert_status(\$errors, 'scrap remove', \$scrapRemoveResponse, [302])",
    "sr_auth_smoke_assert_body_not_contains(\$errors, 'scrap list after remove', \$scrapsAfterRemove, \$title)",
], 'Community authenticated smoke scrap flow');
sr_community_release_file_contains('.tools/bin/smoke-community-auth.php', [
    "sr_auth_smoke_request(\$baseUrl, 'GET', '/community/edit?id=' . (string) \$createdPostId, [], \$cookies)",
    "sr_auth_smoke_assert_status(\$errors, 'post edit form', \$editForm, [200])",
    "'post_id' => (string) \$createdPostId",
    "'title' => \$editedTitle",
    "'body_text' => \$editedBody",
    "sr_auth_smoke_assert_status(\$errors, 'post edit submit', \$editResponse, [302])",
    "sr_auth_smoke_assert_body_contains(\$errors, 'edited post view', \$editedPostView, \$editedTitle)",
    '$title = $editedTitle',
], 'Community authenticated smoke post edit flow');
sr_community_release_file_contains('.tools/bin/smoke-community-auth.php', [
    'function sr_auth_smoke_message_id_from_path(string $messagePath): string',
    '$sentMessageId = sr_auth_smoke_message_id_from_path($sentMessagePath)',
    "sr_auth_smoke_request(\$baseUrl, 'POST', '/community/message/delete', [",
    "'message_id' => \$sentMessageId",
    "sr_auth_smoke_assert_status(\$errors, 'sent message delete', \$messageDeleteResponse, [302])",
    "sr_auth_smoke_assert_body_not_contains(\$errors, 'sent message box after delete', \$sentMessagesAfterDelete, \$sentMessagePath)",
    "sr_auth_smoke_assert_status(\$errors, 'deleted sent message view', \$deletedSentMessageView, [404])",
], 'Community authenticated smoke message delete flow');
sr_community_release_file_contains('.tools/bin/smoke-community-auth.php', [
    'function sr_auth_smoke_comment_id_for_body(array $response, string $commentBody): string',
    'admin comment list did not contain comment body',
    "'intent' => 'comment_status'",
    "'comment_id' => \$commentId",
    "'status' => 'hidden'",
    "sr_auth_smoke_assert_status(\$errors, 'admin comment hide', \$commentHideResponse, [200])",
    "sr_auth_smoke_assert_body_not_contains(\$errors, 'post view after comment hide', \$postAfterCommentHide, \$commentBody)",
], 'Community authenticated smoke admin comment flow');

sr_community_release_file_contains('modules/community/actions/admin-boards.php', [
    'sr_admin_require_role($pdo, (int) $account[\'id\'], [\'owner\', \'admin\', \'manager\'])',
    'sr_admin_require_role($pdo, (int) $account[\'id\'], [\'owner\', \'admin\'])',
    '$allowedReadPolicies = sr_community_policy_values(\'read\')',
    '$allowedWritePolicies = sr_community_policy_values(\'write\')',
    '$allowedCommentPolicies = sr_community_policy_values(\'comment\')',
    '$communitySkinOptions = sr_community_skin_options()',
    '$intent === \'update_skin\'',
    '$memberGroups = sr_member_groups($pdo)',
    '(string) ($memberGroup[\'status\'] ?? \'\') !== \'enabled\'',
    '$publicBannerSettingLabels = [',
    "'banner_before_view_id' => '글보기 상단 배너'",
    "'banner_after_form_id' => '글쓰기 폼 하단 배너'",
    "'popup_layer_view_id' => '글보기 팝업레이어'",
    '$publicDisplaySettingValues[$displaySettingKey] = sr_admin_post_int_in_range($displaySettingKey, 0, 999999999)',
    'sr_admin_post_int_in_range(\'attachment_max_bytes\', 1024, 10485760)',
    'sr_admin_post_int_in_range(\'attachment_max_count\', 0, 10)',
    'sr_admin_post_int_in_range(\'file_attachment_max_bytes\', 1024, 20971520)',
    'sr_admin_post_int_in_range(\'file_attachment_max_count\', 0, 5)',
    '$maxLevel = sr_community_max_level_value()',
    'sr_admin_post_int_in_range(\'read_min_level\', 0, $maxLevel)',
    'sr_community_invalid_board_group_keys_from_input($groupKeysInput)',
    'sr_community_invalid_file_extensions_from_input($fileAllowedExtensionsInput)',
    '$unknownGroupKeys = array_values(array_diff($groupKeys, $enabledMemberGroupKeys))',
    '(string) $policyGroupKeys[0] === \'group\' && $policyGroupKeys[1] === []',
    'if (!isset($communitySkinOptions[$skinKey]))',
    'sr_community_board_by_key($pdo, $boardKey) !== null',
    'sr_community_set_board_setting($pdo, $boardId, \'skin_key\', $skinKey, \'string\')',
    'sr_community_set_board_setting($pdo, $boardId, \'attachment_max_bytes\', (string) $attachmentMaxBytes, \'int\')',
    'sr_community_set_board_setting($pdo, $boardId, $displaySettingKey, (string) $displaySettingValue, \'int\')',
    'sr_community_set_board_setting($pdo, $boardId, \'file_attachment_max_bytes\', (string) $fileAttachmentMaxBytes, \'int\')',
    'sr_community_set_board_setting($pdo, $boardId, \'read_min_level\', (string) $readMinLevel, \'int\')',
    'sr_community_set_board_setting($pdo, $boardId, \'read_group_keys\', sr_community_board_group_keys_setting_value($readGroupKeys), \'json\')',
    "'event_type' => 'community.board.created'",
    "'event_type' => 'community.board.updated'",
], 'Community admin board policy');
sr_community_release_file_contains('modules/community/helpers/themes.php', [
    'function sr_community_layout_key(array $settings',
    'function sr_community_layout_home_view(string $layoutKey',
    'function sr_community_theme_options(): array',
    "'home' => SR_ROOT . '/modules/community/themes/basic/home.php'",
    'function sr_community_skin_options(): array',
], 'Community theme and skin allowlists');
sr_community_release_file_contains('modules/community/helpers/themes.php', [
    'function sr_community_skin_files(): array',
    'function sr_community_skin_options(): array',
    "'basic' => SR_ROOT . '/modules/community/skins/basic/skin.php'",
    'function sr_community_skin_definition_is_valid(string $skinKey, array $definition): bool',
    'function sr_community_required_skin_view_keys(): array',
    "return ['list', 'post', 'form'];",
    'function sr_community_skin_action(string $skinKey, string $actionKey, string $method): ?array',
], 'Community skin allowlist');
sr_community_release_file_contains('modules/community/actions/admin-settings.php', [
    '$communityLayoutOptions = sr_public_layout_options($pdo)',
    'if (!isset($communityLayoutOptions[$layoutKey]))',
    '커뮤니티 레이아웃 값이 올바르지 않습니다.',
    "['layout_key', \$layoutKey, 'string']",
], 'Community admin layout setting policy');
sr_community_release_file_contains('modules/community/views/admin-settings.php', [
    '<label class="form-label" for="community_admin_settings_layout_key">커뮤니티 레이아웃</label>',
    '<select id="community_admin_settings_layout_key" name="layout_key" class="form-select">',
    'foreach ($communityLayoutOptions as $layoutKey => $layoutOption)',
], 'Community admin layout field');
sr_community_release_file_contains('modules/community/views/admin-boards.php', [
    '<input type="hidden" name="intent" value="update_skin">',
    '<span class="form-label">게시판 스킨</span>',
    '<select name="skin_key" class="form-select">',
    'foreach ($communitySkinOptions as $skinKey => $skinOption)',
], 'Community admin board skin field');
sr_community_release_file_contains('modules/community/actions/admin-board-groups.php', [
    'sr_admin_require_role($pdo, (int) $account[\'id\'], [\'owner\', \'admin\', \'manager\'])',
    'sr_admin_require_role($pdo, (int) $account[\'id\'], [\'owner\', \'admin\'])',
    '$allowedGroupStatuses = sr_community_board_group_statuses()',
    '$memberGroups = sr_member_groups($pdo)',
    '(string) ($memberGroup[\'status\'] ?? \'\') !== \'enabled\'',
    'sr_community_board_group_by_id($pdo, $groupId)',
    'sr_community_board_group_by_key($pdo, $groupKey) !== null',
    'sr_community_create_board_group($pdo, [',
    'sr_community_update_board_group($pdo, $groupId, [',
    'sr_community_set_board_group_setting($pdo, $groupId, \'read_policy\', $readPolicy)',
    'sr_community_set_board_group_setting($pdo, $groupId, \'read_min_level\', (string) $readMinLevel, \'int\')',
    'sr_community_set_board_group_setting($pdo, $groupId, \'file_attachment_max_bytes\', (string) $fileAttachmentMaxBytes, \'int\')',
    'sr_community_set_board_group_setting($pdo, $groupId, \'read_group_keys\', sr_community_board_group_keys_setting_value($readGroupKeys), \'json\')',
    'sr_community_apply_board_group_settings_to_boards($pdo, $groupId, $applySettingKeys)',
    "'event_type' => \$eventType",
    "'target_type' => 'community_board_group'",
], 'Community admin board group policy');
sr_community_release_file_contains('modules/community/actions/admin-settings.php', [
    'sr_admin_require_role($pdo, (int) $account[\'id\'], [\'owner\', \'admin\', \'manager\'])',
    'sr_admin_require_role($pdo, (int) $account[\'id\'], [\'owner\', \'admin\'])',
    'sr_require_csrf()',
    '$levelAutoRecalculate = ($_POST[\'level_auto_recalculate\'] ?? \'\') === \'1\'',
    '[\'level_auto_recalculate\', $levelAutoRecalculate ? \'1\' : \'0\', \'bool\']',
    '$intent === \'save_level_definitions\'',
    'sr_community_update_level_min_scores($pdo, $minScoresById)',
    'sr_community_access_condition_priority(sr_post_string(\'access_condition_priority\', 40))',
    'sr_community_message_write_policy(sr_post_string(\'message_write_policy\', 40))',
    'sr_community_recalculate_recent_account_levels($pdo, 200)',
    "'event_type' => 'community.settings.updated'",
    "'event_type' => 'community.level_definitions.updated'",
    "'event_type' => 'community.levels.recalculated'",
], 'Community admin settings policy');
sr_community_release_file_contains('modules/community/actions/admin-posts.php', [
    'sr_admin_require_role($pdo, (int) $account[\'id\'], [\'owner\', \'admin\', \'manager\'])',
    '$allowedPostStatuses = sr_community_post_statuses()',
    '$allowedCommentStatuses = sr_community_comment_statuses()',
    'sr_community_update_post_status($pdo, $postId, $status)',
    'sr_member_group_evaluate_account($pdo, (int) $post[\'author_account_id\'], [',
    'sr_community_maybe_recalculate_account_level($pdo, (int) $post[\'author_account_id\'], null, \'post_status_updated\')',
    'sr_community_update_post_attachments_status($pdo, $postId, $status)',
    'sr_community_restore_hidden_post_attachments($pdo, $postId)',
    "'event_type' => 'community.post.status_updated'",
    'sr_community_member_group_evaluation_metadata($groupEvaluationSummary)',
    'sr_community_update_comment_status($pdo, $commentId, $status)',
    'sr_community_maybe_recalculate_account_level($pdo, (int) $comment[\'author_account_id\'], null, \'comment_status_updated\')',
    "'event_type' => 'community.comment.status_updated'",
], 'Community admin post policy');
sr_community_release_file_contains('modules/community/helpers/levels.php', [
    "'level_auto_recalculate' => (bool) (\$settings['level_auto_recalculate'] ?? false)",
    "function sr_community_maybe_recalculate_account_level(PDO \$pdo, int \$accountId, ?array \$settings = null, string \$reasonKey = 'activity_changed'): array",
    "empty(\$settings['level_auto_recalculate'])",
    'sr_community_account_level_snapshot($pdo, $accountId)',
    'function sr_community_update_level_min_scores(PDO $pdo, array $minScoresById): int',
    '레벨 최소 점수는 낮은 레벨부터 같거나 커야 합니다.',
], 'Community level helper policy');
sr_community_release_file_contains('modules/community/views/admin-settings.php', [
    'name="level_auto_recalculate"',
    '게시글/댓글 활동 후 레벨 자동 재계산',
    'name="intent" value="save_level_definitions"',
    'name="level_min_score[',
    '레벨 정의 저장',
], 'Community admin settings level UI');
sr_community_release_file_contains('modules/community/actions/admin-reports.php', [
    'sr_admin_require_role($pdo, (int) $account[\'id\'], [\'owner\', \'admin\', \'manager\'])',
    '$allowedStatuses = sr_community_report_statuses()',
    'sr_post_string_without_truncation(\'review_note\', 1000)',
    'sr_community_report_by_id($pdo, $reportId)',
    'in_array($status, $allowedStatuses, true)',
    '$reviewNote === null',
    'sr_community_update_report_status($pdo, $reportId, $status, (int) $account[\'id\'], (string) $reviewNote)',
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
    'modules/community/actions/skin-action.php',
    'modules/community/actions/message-write.php',
    'modules/community/actions/message-delete.php',
    'modules/community/actions/admin-settings.php',
    'modules/community/actions/admin-boards.php',
    'modules/community/actions/admin-board-groups.php',
    'modules/community/actions/admin-posts.php',
    'modules/community/actions/admin-reports.php',
];
foreach ($stateChangingActions as $actionPath) {
    sr_community_release_file_contains($actionPath, ['sr_require_csrf('], $actionPath);
}

foreach (sr_community_release_files('modules/community/assets', ['css']) as $assetFile) {
    $basename = basename($assetFile);
    if (!str_starts_with($basename, 'community-')) {
        sr_community_release_error('Community CSS asset must use community- prefix: ' . $assetFile);
    }
}

foreach (sr_community_release_files('modules/community', ['js', 'scss']) as $assetFile) {
    sr_community_release_error('Community module must not ship JS/SCSS assets without a release policy update: ' . $assetFile);
}

foreach (sr_community_release_files('modules/community', ['php']) as $phpFile) {
    $content = file_get_contents($phpFile);
    if (!is_string($content)) {
        continue;
    }

    foreach (['<style', 'style='] as $forbiddenFragment) {
        if (str_contains($content, $forbiddenFragment)) {
            sr_community_release_error('Community module must not include inline styling "' . $forbiddenFragment . '" in ' . $phpFile);
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
