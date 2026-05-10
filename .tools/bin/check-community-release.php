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
    if (is_array($entry) && is_string($entry['url'] ?? null)) {
        $menuLinkUrls[] = (string) $entry['url'];
    }
}
toy_community_release_require_list_values($menuLinkUrls, [
    '/community',
    '/community/scraps',
    '/community/messages',
], 'Community menu-links.php');

$pointKeys = [];
foreach ($extensionPoints as $entry) {
    if (is_array($entry) && is_string($entry['point_key'] ?? null) && isset($entry['slots']) && is_array($entry['slots'])) {
        $pointKeys[] = (string) $entry['point_key'];
    }
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
    if (is_array($entry) && is_string($entry['rule_key'] ?? null) && is_string($entry['evaluator'] ?? null)) {
        $memberGroupRuleKeys[] = (string) $entry['rule_key'];
    }
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

toy_community_release_file_contains('modules/community/actions/message-write.php', [
    "toy_get_string('to_account', 40)",
    'toy_member_public_account_summary_by_hash($pdo, $config, $recipientAccountHash)',
    "'recipient_identifier' => ''",
], 'Community message write recipient preset');
$messageWriteContent = is_file('modules/community/actions/message-write.php') ? (string) file_get_contents('modules/community/actions/message-write.php') : '';
if (str_contains($messageWriteContent, "toy_get_string('to',")) {
    toy_community_release_error('Community message write must not accept recipient identifier from GET to parameter; use to_account public hash.');
}

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
