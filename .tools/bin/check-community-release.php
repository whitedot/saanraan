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

function sr_community_release_require_list_values(array $actualValues, array $requiredValues, string $label): void
{
    foreach ($requiredValues as $requiredValue) {
        if (!in_array($requiredValue, $actualValues, true)) {
            sr_community_release_error($label . ' is missing required value: ' . $requiredValue);
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
    'GET /admin/community/board-groups',
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
foreach ((array) ($adminMenu['items'] ?? []) as $entry) {
    if (is_array($entry) && is_string($entry['path'] ?? null)) {
        $adminMenuPaths[] = (string) $entry['path'];
    }
}
sr_community_release_require_list_values($adminMenuPaths, [
    '/admin/community/settings',
    '/admin/community/boards',
    '/admin/community/board-groups',
    '/admin/community/nicknames',
    '/admin/community/posts',
    '/admin/community/comments',
    '/admin/community/reports',
    '/admin/community/levels',
], 'Community admin-menu.php');

if (!is_array($menuLinks) && !is_callable($menuLinks)) {
    sr_community_release_error('Community menu-links.php must return an array or callable.');
}
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
    if (is_array($entry) && is_string($entry['point_key'] ?? null)) {
        $pointKeys[] = (string) $entry['point_key'];
    }
}
sr_community_release_require_list_values($pointKeys, [
    'community.home',
    'community.board.list',
    'community.post.view',
    'community.post.form',
], 'Community extension-points.php');

$memberGroupRuleKeys = [];
foreach ($memberGroupRules as $entry) {
    if (!is_array($entry)) {
        sr_community_release_error('Community member-group-rules.php entries must be arrays.');
        continue;
    }

    $ruleKey = is_string($entry['rule_key'] ?? null) ? (string) $entry['rule_key'] : '';
    $evaluator = is_string($entry['evaluator'] ?? null) ? (string) $entry['evaluator'] : '';
    if ($ruleKey === '' || $evaluator === '') {
        sr_community_release_error('Community member-group-rules.php entries must include rule_key and evaluator.');
        continue;
    }
    if (!str_starts_with($ruleKey, 'community.')) {
        sr_community_release_error('Community member-group-rules.php rule_key must start with community.: ' . $ruleKey);
    }
    if (!function_exists($evaluator)) {
        sr_community_release_error('Community member-group-rules.php evaluator must exist: ' . $evaluator);
    }
    $memberGroupRuleKeys[] = $ruleKey;
}
sr_community_release_require_list_values($memberGroupRuleKeys, [
    'community.board.post_count_at_least',
], 'Community member-group-rules.php');

$installSql = is_file('modules/community/install.sql') ? (string) file_get_contents('modules/community/install.sql') : '';
foreach ([
    'sr_community_board_groups',
    'sr_community_boards',
    'sr_community_posts',
    'sr_community_comments',
    'sr_community_attachments',
    'sr_community_reports',
    'sr_community_messages',
    'sr_community_scraps',
    'sr_community_member_nicknames',
    'sr_community_asset_logs',
] as $tableName) {
    if (!str_contains($installSql, 'CREATE TABLE IF NOT EXISTS ' . $tableName)) {
        sr_community_release_error('Community install.sql is missing table: ' . $tableName);
    }
}

sr_community_release_file_contains('modules/community/helpers/notifications.php', [
    "sr_module_enabled(\$pdo, 'notification')",
    "require_once \$helperPath;",
    "function_exists('sr_notification_create')",
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

if ($errors !== []) {
    fwrite(STDERR, "community release checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "community release checks completed.\n";
