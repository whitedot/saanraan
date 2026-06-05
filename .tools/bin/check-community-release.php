#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/community/helpers.php';

$errors = [];

function sr_community_release_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_community_release_include(string $path): mixed
{
    if (!is_file($path)) {
        sr_community_release_error('Required community release file is missing: ' . $path);
        return null;
    }

    try {
        return include $path;
    } catch (Throwable $exception) {
        sr_community_release_error('Community release file cannot be loaded: ' . $path . ' ' . $exception->getMessage());
        return null;
    }
}

function sr_community_release_array_file(string $path): array
{
    $value = sr_community_release_include($path);
    if (!is_array($value)) {
        sr_community_release_error('Community release file must return an array: ' . $path);
        return [];
    }

    return $value;
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

function sr_community_release_package_entries(string $directory): array
{
    $entries = [];
    foreach (new DirectoryIterator($directory) as $entry) {
        if (!$entry->isDot()) {
            $entries[] = $entry->getFilename();
        }
    }

    sort($entries, SORT_STRING);
    return $entries;
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

function sr_community_release_require_list_values(array $actualValues, array $requiredValues, string $label): void
{
    foreach ($requiredValues as $requiredValue) {
        if (!in_array($requiredValue, $actualValues, true)) {
            sr_community_release_error($label . ' is missing required value: ' . $requiredValue);
        }
    }
}

function sr_community_release_wrapper_action(string $path, array $requiredNeedles, string $includeNeedle, string $label): void
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

    $normalizedContent = str_replace(["\r\n", "\r"], "\n", trim($content));
    foreach ($requiredNeedles as $needle) {
        if (!str_contains($normalizedContent, $needle)) {
            sr_community_release_error($label . ' must contain: ' . $needle);
        }
    }
    if (!str_contains($normalizedContent, $includeNeedle)) {
        sr_community_release_error($label . ' must include shared action: ' . $includeNeedle);
    }
    if (substr_count($normalizedContent, 'include ') !== 1) {
        sr_community_release_error($label . ' must include exactly one shared action.');
    }

    $forbiddenFragments = [
        'sr_post_',
        '$_FILES',
        'sr_audit_log(',
        'sr_admin_require_permission(',
        'sr_community_create_',
        'sr_community_update_',
        'sr_community_delete_',
    ];
    foreach ($forbiddenFragments as $fragment) {
        if (str_contains($normalizedContent, $fragment)) {
            sr_community_release_error($label . ' must remain a thin wrapper and not contain state-changing logic: ' . $fragment);
        }
    }
}

$module = sr_community_release_array_file('modules/community/module.php');
$paths = sr_community_release_array_file('modules/community/paths.php');
$adminMenu = sr_community_release_array_file('modules/community/admin-menu.php');
$extensionPoints = sr_community_release_array_file('modules/community/extension-points.php');
$memberGroupRules = sr_community_release_array_file('modules/community/member-group-rules.php');
$menuLinks = sr_community_release_include('modules/community/menu-links.php');
$privacyExport = sr_community_release_include('modules/community/privacy-export.php');
$privacyCleanup = sr_community_release_include('modules/community/privacy-cleanup.php');
$sitemap = sr_community_release_include('modules/community/sitemap.php');

$moduleVersion = (string) ($module['version'] ?? '');
if (preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $moduleVersion) !== 1) {
    sr_community_release_error('Community module version must use YYYY.MM.NNN format.');
}

sr_community_release_file_contains('core/actions/install.php', [
    "'community' => [",
    "'version' => '" . $moduleVersion . "'",
], 'Install optional community module');
sr_community_release_file_contains('core/actions/install.php', [
    "'label' => sr_t('install.module.community.label')",
], 'Install optional community module label');

sr_community_release_file_contains('.tools/bin/smoke-http.php', [
    'SR_SMOKE_EXPECT_COMMUNITY=1',
    '$expectCommunity = getenv(\'SR_SMOKE_EXPECT_COMMUNITY\') === \'1\'',
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
    'privacy-cleanup.php',
    'privacy-export.php',
    'sitemap.php',
    'skins',
    'themes',
    'views',
    'coupon-targets.php',
];
$allowedPackageEntries = array_merge($requiredPackageEntries, [
    'lang',
    'updates',
]);
$packageEntries = sr_community_release_package_entries('modules/community');
sr_community_release_require_list_values($packageEntries, $requiredPackageEntries, 'Community package structure');
foreach ($packageEntries as $entry) {
    if (!in_array($entry, $allowedPackageEntries, true)) {
        sr_community_release_error('Community package must not include unexpected top-level entry: modules/community/' . $entry);
    }
}

if (!is_dir('modules/community/updates') || sr_community_release_package_entries('modules/community/updates') === []) {
    sr_community_release_error('Community module updates directory must include schema updates.');
}

$requiredContracts = [
    'paths.php',
    'admin-menu.php',
    'menu-links.php',
    'extension-points.php',
    'privacy-export.php',
    'privacy-cleanup.php',
    'sitemap.php',
    'member-group-rules.php',
    'dashboard.php',
    'layout-options.php',
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
    'GET /admin/community/boards/new',
    'POST /admin/community/boards/create',
    'GET /admin/community/boards/edit',
    'POST /admin/community/boards/update',
    'GET /admin/community/board-groups',
    'POST /admin/community/board-groups',
    'GET /admin/community/board-groups/new',
    'POST /admin/community/board-groups/create',
    'GET /admin/community/board-groups/edit',
    'POST /admin/community/board-groups/update',
    'GET /admin/community/levels',
    'POST /admin/community/levels',
    'POST /admin/community/levels/recalculate',
    'GET /admin/community/posts',
    'POST /admin/community/posts',
    'GET /admin/community/comments',
    'POST /admin/community/comments',
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
foreach ((array) ($adminMenu['items'] ?? []) as $entry) {
    if (is_array($entry) && is_string($entry['path'] ?? null)) {
        $adminMenuPaths[] = (string) $entry['path'];
    }
}
sr_community_release_require_list_values($adminMenuPaths, [
    '/admin/community/settings',
    '/admin/community/boards',
    '/admin/community/board-groups',
    '/admin/community/posts',
    '/admin/community/comments',
    '/admin/community/reports',
    '/admin/community/levels',
], 'Community admin-menu.php');

if (!is_array($menuLinks) && !is_callable($menuLinks)) {
    sr_community_release_error('Community menu-links.php must return an array or callable.');
}
sr_community_release_file_contains('modules/community/menu-links.php', [
    "'asset_type' => 'board_group'",
    "'asset_type' => 'board'",
    '/community/board?key=',
], 'Community menu-links.php');
if (!is_callable($privacyExport)) {
    sr_community_release_error('Community privacy-export.php must return a callable.');
}
if (!is_callable($privacyCleanup)) {
    sr_community_release_error('Community privacy-cleanup.php must return a callable.');
}
if (!is_callable($sitemap)) {
    sr_community_release_error('Community sitemap.php must return a callable.');
}

$pointKeys = [];
foreach ($extensionPoints as $entry) {
    if (!is_array($entry)) {
        sr_community_release_error('Community extension-points.php entries must be arrays.');
        continue;
    }

    $pointKey = is_string($entry['point_key'] ?? null) ? (string) $entry['point_key'] : '';
    if ($pointKey === '' || !is_string($entry['label'] ?? null)) {
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
    "'slot_key' => 'before_comments'",
    "'slot_key' => 'after_comments'",
    "'point_key' => 'community.post.form'",
    "'slot_key' => 'before_form'",
    "'slot_key' => 'after_form'",
], 'Community extension-points.php major surfaces');

sr_community_release_file_contains('modules/community/sitemap.php', [
    "WHERE status = 'enabled'",
    'sr_community_account_can_read_board($pdo, $board, null)',
    "WHERE p.status = 'published'",
    "AND b.status = 'enabled'",
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
    'WHERE sender_account_id = :sender_account_id OR recipient_account_id = :recipient_account_id',
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
    'community.comment_count_at_least',
    'community.level_at_least',
    'community.board_group.post_count_at_least',
    'community.board_group.comment_count_at_least',
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
    'sr_community_asset_logs',
    'sr_community_access_entitlements',
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
        'read_policy VARCHAR(30) NOT NULL DEFAULT \'public\'',
        'write_policy VARCHAR(30) NOT NULL DEFAULT \'member\'',
        'comment_policy VARCHAR(30) NOT NULL DEFAULT \'member\'',
        'UNIQUE KEY uq_sr_community_boards_key (board_key)',
        'KEY idx_sr_community_boards_group_sort (board_group_id, sort_order, id)',
    ],
    'sr_community_board_groups' => [
        'UNIQUE KEY uq_sr_community_board_groups_key (group_key)',
        'KEY idx_sr_community_board_groups_status_sort (status, sort_order, id)',
    ],
    'sr_community_board_settings' => [
        'UNIQUE KEY uq_sr_community_board_settings_key (board_id, setting_key)',
    ],
    'sr_community_board_group_settings' => [
        'UNIQUE KEY uq_sr_community_board_group_settings_key (group_id, setting_key)',
    ],
    'sr_community_board_setting_sources' => [
        'UNIQUE KEY uq_sr_community_board_setting_sources_key (board_id, setting_key)',
    ],
    'sr_community_posts' => [
        'body_format VARCHAR(20) NOT NULL DEFAULT \'plain\'',
        'author_public_name_snapshot VARCHAR(120) NOT NULL DEFAULT \'\'',
        'KEY idx_sr_community_posts_board_status_id (board_id, status, id)',
        'KEY idx_sr_community_posts_author_id (author_account_id, id)',
    ],
    'sr_community_comments' => [
        'author_public_name_snapshot VARCHAR(120) NOT NULL DEFAULT \'\'',
        'KEY idx_sr_community_comments_post_status_id (post_id, status, id)',
        'KEY idx_sr_community_comments_author_id (author_account_id, id)',
    ],
    'sr_community_attachments' => [
        'storage_driver VARCHAR(20) NOT NULL DEFAULT \'local\'',
        'storage_key VARCHAR(255) NOT NULL DEFAULT \'\'',
        'checksum_sha256 CHAR(64) NOT NULL',
        'KEY idx_sr_community_attachments_checksum (checksum_sha256)',
    ],
    'sr_community_reports' => [
        'UNIQUE KEY uq_sr_community_reports_target_reporter (reporter_account_id, target_type, target_id)',
    ],
    'sr_community_messages' => [
        'sender_deleted_at DATETIME NULL',
        'recipient_deleted_at DATETIME NULL',
    ],
    'sr_community_scraps' => [
        'UNIQUE KEY uq_sr_community_scraps_account_post (account_id, post_id)',
    ],
    'sr_community_asset_logs' => [
        'dedupe_key VARCHAR(160) NOT NULL',
        'UNIQUE KEY uq_sr_community_asset_logs_dedupe (dedupe_key)',
    ],
    'sr_community_access_entitlements' => [
        'account_id BIGINT UNSIGNED NULL',
        'UNIQUE KEY uq_sr_community_access_entitlements_account_subject (account_id, subject_type, subject_id, event_key)',
        'KEY idx_sr_community_access_entitlements_anonymized (anonymized_at)',
    ],
];
foreach ($requiredInstallFragments as $tableName => $fragments) {
    foreach ($fragments as $fragment) {
        if (!str_contains($installSql, $fragment)) {
            sr_community_release_error('Community install.sql table ' . $tableName . ' is missing fragment: ' . $fragment);
        }
    }
}

sr_community_release_file_contains('modules/community/actions/attachment.php', [
    'sr_community_attachment_for_read($pdo, $attachmentId, is_array($account) ? $account : null)',
    'sr_community_account_can_read_board($pdo, $board, is_array($account) ? $account : null)',
    '$recordedChecksum = (string) ($attachment[\'checksum_sha256\'] ?? \'\')',
    'sr_storage_head($driver, $storageKey)',
    'hash_equals($recordedChecksum, $actualChecksum)',
    "sr_community_asset_event_config(\$pdo, \$board, \$settings, 'paid_attachment_download', 'once')",
    "sr_community_run_asset_event(",
    "sr_storage_signed_url('s3', \$storageKey, 300, [",
    "header('X-Content-Type-Options: nosniff')",
], 'Community attachment download policy');

sr_community_release_file_contains('modules/community/actions/write.php', [
    'sr_community_account_can_write_board($pdo, $board, $account, $isAdminWriter)',
    'sr_community_post_rate_limited($pdo, (int) $account[\'id\'], $settings)',
    "sr_community_asset_event_config(\$pdo, \$board, \$settings, 'write_charge', 'every_action')",
    'sr_community_record_post_rate_limit($pdo, (int) $account[\'id\'], $settings)',
    "'event_type' => 'community.post.created'",
], 'Community write action policy');
sr_community_release_file_contains('modules/community/actions/edit.php', [
    'sr_community_account_can_edit_post($post, $account)',
    '$submittedPostId !== $postId',
    'sr_community_update_post_content($pdo, $postId, $values, (int) $account[\'id\'])',
    "'event_type' => 'community.post.updated_by_author'",
], 'Community edit action policy');
sr_community_release_file_contains('modules/community/actions/delete.php', [
    'sr_community_account_can_delete_post($post, $account)',
    'sr_community_update_post_status($pdo, $postId, \'deleted\')',
    'sr_community_update_post_attachments_status($pdo, $postId, \'deleted\')',
    "'event_type' => 'community.post.deleted_by_author'",
], 'Community delete action policy');
sr_community_release_file_contains('modules/community/actions/comment.php', [
    'sr_community_account_can_comment_post($pdo, $post, $account)',
    'sr_community_comment_rate_limited($pdo, (int) $account[\'id\'], $settings)',
    "sr_community_asset_event_config(\$pdo, \$board, \$settings, 'comment_charge', 'every_action')",
    'sr_community_record_comment_rate_limit($pdo, (int) $account[\'id\'], $settings)',
    "'event_type' => 'community.comment.created'",
    'sr_community_create_account_notification(',
], 'Community comment action policy');
sr_community_release_file_contains('modules/community/actions/comment-edit.php', [
    'sr_community_account_can_edit_comment($comment, $account)',
    'sr_community_update_comment_content($pdo, $commentId, $values)',
    "'event_type' => 'community.comment.updated_by_author'",
], 'Community comment edit action policy');
sr_community_release_file_contains('modules/community/actions/comment-delete.php', [
    'sr_community_account_can_delete_comment($comment, $account)',
    'sr_community_update_comment_status($pdo, $commentId, \'deleted\')',
    "'event_type' => 'community.comment.deleted_by_author'",
], 'Community comment delete action policy');
sr_community_release_file_contains('modules/community/actions/report.php', [
    'sr_community_report_target($pdo, $targetType, $targetId, (int) $account[\'id\'])',
    'sr_community_report_rate_limited($pdo, (int) $account[\'id\'], $settings)',
    'sr_community_report_exists($pdo, (int) $account[\'id\'], (string) $target[\'target_type\'], (int) $target[\'target_id\'])',
    'sr_community_record_report_rate_limit($pdo, (int) $account[\'id\'], $settings)',
    "'event_type' => 'community.report.created'",
    'sr_community_create_admin_report_notifications(',
], 'Community report action policy');

sr_community_release_file_contains('modules/community/helpers/notifications.php', [
    "sr_module_contract_function(\$pdo, 'notification', 'notification-events.php', 'create_function')",
    'catch (Throwable $exception)',
    'function sr_community_create_admin_report_notifications',
    "p.menu_path = '/admin/community/reports'",
    "p.action_key = 'view'",
], 'Community optional notification integration');

sr_community_release_file_contains('modules/community/actions/message-write.php', [
    "sr_get_string('to_account', 40)",
    'sr_community_public_account_summary_by_hash($pdo, $config,',
    "'recipient_identifier' => ''",
    'sr_community_create_account_notification(',
], 'Community hashed message recipient flow');
sr_community_release_file_contains('modules/community/views/message-write.php', [
    'name="recipient_account_hash"',
], 'Community message write view');
sr_community_release_file_contains('modules/community/views/message-view.php', [
    'rawurlencode($replyAccountHash)',
], 'Community message reply link');
sr_community_release_file_contains('modules/community/actions/comment.php', [
    'sr_community_create_account_notification(',
    "(int) \$post['author_account_id'] !== (int) \$account['id']",
], 'Community comment notification policy');
sr_community_release_file_contains('modules/community/actions/report.php', [
    'sr_community_create_admin_report_notifications(',
], 'Community report notification policy');
sr_community_release_file_contains('modules/community/privacy-export.php', [
    "'posts' => []",
    "'comments' => []",
    "'attachments' => []",
    "'reports' => []",
    "'messages' => []",
    "'scraps' => []",
], 'Community privacy export coverage');

sr_community_release_file_contains('modules/community/actions/admin-boards.php', [
    "sr_admin_require_permission(\$pdo, (int) \$account['id'], '/admin/community/boards', 'view')",
    "sr_admin_require_permission(\$pdo, (int) \$account['id'], '/admin/community/boards', 'edit')",
    '$allowedReadPolicies = sr_community_policy_values(\'read\')',
    '$allowedWritePolicies = sr_community_policy_values(\'write\')',
    '$allowedCommentPolicies = sr_community_policy_values(\'comment\')',
    'sr_admin_post_int_in_range(\'read_min_level\', 0, $maxLevel)',
    'sr_admin_post_int_in_range(\'write_min_level\', 0, $maxLevel)',
    'sr_admin_post_int_in_range(\'comment_min_level\', 0, $maxLevel)',
    'sr_community_set_board_setting($pdo, $boardId, \'read_group_keys\', sr_community_board_group_keys_setting_value($readGroupKeys), \'json\')',
    'sr_community_set_board_setting($pdo, $boardId, \'write_group_keys\', sr_community_board_group_keys_setting_value($writeGroupKeys), \'json\')',
    'sr_community_set_board_setting($pdo, $boardId, \'comment_group_keys\', sr_community_board_group_keys_setting_value($commentGroupKeys), \'json\')',
    'sr_community_set_board_setting($pdo, $boardId, \'file_allowed_extensions\', implode(\',\', $fileAllowedExtensions), \'string\')',
    "'event_type' => 'community.board.created'",
    "'event_type' => 'community.board.updated'",
], 'Community admin board policy');
sr_community_release_file_contains('modules/community/actions/admin-board-groups.php', [
    "sr_admin_require_permission(\$pdo, (int) \$account['id'], '/admin/community/board-groups', 'view')",
    "sr_admin_require_permission(\$pdo, (int) \$account['id'], '/admin/community/board-groups', 'edit')",
    '$allowedReadPolicies = sr_community_policy_values(\'read\')',
    '$allowedWritePolicies = sr_community_policy_values(\'write\')',
    '$allowedCommentPolicies = sr_community_policy_values(\'comment\')',
    'sr_community_set_board_group_setting($pdo, $groupId, \'read_group_keys\', sr_community_board_group_keys_setting_value($readGroupKeys), \'json\')',
    'sr_community_set_board_group_setting($pdo, $groupId, \'write_group_keys\', sr_community_board_group_keys_setting_value($writeGroupKeys), \'json\')',
    'sr_community_set_board_group_setting($pdo, $groupId, \'comment_group_keys\', sr_community_board_group_keys_setting_value($commentGroupKeys), \'json\')',
    "'target_type' => 'community_board_group'",
], 'Community admin board group policy');
sr_community_release_file_contains('modules/community/actions/admin-settings.php', [
    "sr_admin_require_permission(\$pdo, (int) \$account['id'], \$communitySettingsPermissionPath, 'view')",
    "sr_admin_require_permission(\$pdo, (int) \$account['id'], \$communitySettingsPermissionPath, 'edit')",
    'sr_require_csrf()',
    'sr_community_message_write_policy(sr_post_string(\'message_write_policy\', 40))',
    'sr_community_update_level_min_scores($pdo, $minScoresById',
    "'event_type' => 'community.settings.updated'",
], 'Community admin settings policy');
sr_community_release_file_contains('modules/community/actions/admin-posts.php', [
    "sr_admin_require_permission(\$pdo, (int) \$account['id'], \$communityPostsPermissionPath, 'view')",
    '$allowedPostStatuses = sr_community_post_statuses()',
    'sr_community_update_post_status($pdo, $postId, $status)',
    'sr_community_update_post_attachments_status($pdo, $postId, $status)',
    "'event_type' => 'community.post.status_updated'",
], 'Community admin post policy');
sr_community_release_file_contains('modules/community/actions/admin-posts.php', [
    "\$communityPostsPermissionPath = \$communityPostsPage === 'comments' ? '/admin/community/comments' : '/admin/community/posts'",
    '$allowedCommentStatuses = sr_community_comment_statuses()',
    'sr_community_update_comment_status($pdo, $commentId, $status)',
    "'event_type' => 'community.comment.status_updated'",
], 'Community admin comment policy');
sr_community_release_file_contains('modules/community/actions/admin-reports.php', [
    "sr_admin_require_permission(\$pdo, (int) \$account['id'], '/admin/community/reports', 'view')",
    '$allowedStatuses = sr_community_report_statuses()',
    'sr_community_update_report_status($pdo, $reportId, $status, (int) $account[\'id\'], (string) $reviewNote)',
    "'event_type' => 'community.report.status_updated'",
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
    'modules/community/actions/admin-level-recalculate.php',
];
foreach ($stateChangingActions as $actionPath) {
    sr_community_release_file_contains($actionPath, ['sr_require_csrf('], $actionPath);
}

sr_community_release_wrapper_action('modules/community/actions/admin-board-create.php', [
    '$communityBoardsPage = \'new\'',
    '$_POST[\'intent\'] = \'create\'',
], "include SR_ROOT . '/modules/community/actions/admin-boards.php';", 'Community admin board create wrapper');
sr_community_release_wrapper_action('modules/community/actions/admin-board-update.php', [
    '$communityBoardsPage = \'edit\'',
    '$_POST[\'intent\'] = \'update\'',
    '$_GET[\'edit_id\'] = $_POST[\'board_id\']',
], "include SR_ROOT . '/modules/community/actions/admin-boards.php';", 'Community admin board update wrapper');
sr_community_release_wrapper_action('modules/community/actions/admin-board-group-create.php', [
    '$communityBoardGroupsPage = \'new\'',
    '$_POST[\'intent\'] = \'create_group\'',
], "include SR_ROOT . '/modules/community/actions/admin-board-groups.php';", 'Community admin board group create wrapper');
sr_community_release_wrapper_action('modules/community/actions/admin-board-group-update.php', [
    '$communityBoardGroupsPage = \'edit\'',
    '$_POST[\'intent\'] = \'update_group\'',
    '$_GET[\'edit_id\'] = $_POST[\'group_id\']',
], "include SR_ROOT . '/modules/community/actions/admin-board-groups.php';", 'Community admin board group update wrapper');
sr_community_release_wrapper_action('modules/community/actions/admin-comments.php', [
    '$communityPostsPage = \'comments\'',
], "include SR_ROOT . '/modules/community/actions/admin-posts.php';", 'Community admin comments wrapper');
sr_community_release_wrapper_action('modules/community/actions/admin-levels.php', [
    '$communitySettingsPage = \'levels\'',
], "include SR_ROOT . '/modules/community/actions/admin-settings.php';", 'Community admin levels wrapper');

foreach (sr_community_release_files('modules/community/assets', ['css']) as $assetFile) {
    if (!str_starts_with(basename($assetFile), 'community-')) {
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
