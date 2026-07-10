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

function sr_community_release_file_not_contains(string $path, array $needles, string $label): void
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

    foreach ($needles as $needle) {
        if (str_contains($content, $needle)) {
            sr_community_release_error($label . ' must not contain legacy marker: ' . $needle);
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

function sr_community_release_command(array $command, int $expectedExitCode, array $markers, string $label): void
{
    $parts = [];
    foreach ($command as $part) {
        $parts[] = escapeshellarg($part);
    }

    $output = [];
    exec(implode(' ', $parts) . ' 2>&1', $output, $exitCode);
    $text = implode("\n", $output);
    if ($exitCode !== $expectedExitCode) {
        sr_community_release_error($label . ' expected exit ' . (string) $expectedExitCode . ', got ' . (string) $exitCode . ': ' . $text);
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($text, $marker)) {
            sr_community_release_error($label . ' output must contain: ' . $marker);
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
    "'GET /community/group?key=general' => true",
    "'GET /community/edit?id=1' => true",
    "'label' => 'community comment action guest csrf guard'",
    "'allowed_statuses' => [302, 400, 404]",
    'returned 404 while SR_SMOKE_EXPECT_COMMUNITY=1',
], 'Community installed HTTP smoke mode');
sr_community_release_file_contains('.tools/bin/smoke-community-auth.php', [
    'SR_SMOKE_ALLOW_MUTATION=1',
    'SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1',
    'saanraan authenticated community smoke refused to run because it creates community data.',
    'saanraan authenticated community smoke refused to run against a public-looking base URL.',
    'sr_auth_smoke_requires_public_mutation_override',
    'reporter_identifier and reporter_password must be provided together.',
    'admin_identifier and admin_password must be provided together.',
    'recipient_password requires recipient_identifier.',
    'function sr_auth_smoke_first_message_path',
    '\/message\\?id=[0-9]+',
], 'Community authenticated smoke configuration guards');
sr_community_release_command(
    [
        'env',
        'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
        'SR_SMOKE_IDENTIFIER=writer',
        'SR_SMOKE_PASSWORD=12341234',
        'SR_SMOKE_REPORTER_IDENTIFIER=reporter',
        PHP_BINARY,
        '.tools/bin/smoke-community-auth.php',
    ],
    2,
    [
        'saanraan authenticated community smoke configuration failed:',
        'reporter_identifier and reporter_password must be provided together.',
    ],
    'Community authenticated smoke reporter credential pair guard'
);
sr_community_release_command(
    [
        'env',
        'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
        'SR_SMOKE_IDENTIFIER=writer',
        'SR_SMOKE_PASSWORD=12341234',
        'SR_SMOKE_ADMIN_PASSWORD=12341234',
        PHP_BINARY,
        '.tools/bin/smoke-community-auth.php',
    ],
    2,
    [
        'saanraan authenticated community smoke configuration failed:',
        'admin_identifier and admin_password must be provided together.',
    ],
    'Community authenticated smoke admin credential pair guard'
);
sr_community_release_command(
    [
        'env',
        'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
        'SR_SMOKE_IDENTIFIER=writer',
        'SR_SMOKE_PASSWORD=12341234',
        'SR_SMOKE_RECIPIENT_PASSWORD=12341234',
        PHP_BINARY,
        '.tools/bin/smoke-community-auth.php',
    ],
    2,
    [
        'saanraan authenticated community smoke configuration failed:',
        'recipient_password requires recipient_identifier.',
    ],
    'Community authenticated smoke recipient password guard'
);
sr_community_release_command(
    [
        'env',
        'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
        'SR_SMOKE_IDENTIFIER=writer',
        'SR_SMOKE_PASSWORD=12341234',
        PHP_BINARY,
        '.tools/bin/smoke-community-auth.php',
    ],
    2,
    [
        'saanraan authenticated community smoke refused to run because it creates community data.',
        'SR_SMOKE_ALLOW_MUTATION=1',
    ],
    'Community authenticated smoke mutation guard'
);
sr_community_release_command(
    [
        'env',
        'SR_SMOKE_ALLOW_MUTATION=1',
        'SR_SMOKE_BASE_URL=https://example.com',
        'SR_SMOKE_IDENTIFIER=writer',
        'SR_SMOKE_PASSWORD=12341234',
        PHP_BINARY,
        '.tools/bin/smoke-community-auth.php',
    ],
    2,
    [
        'saanraan authenticated community smoke refused to run against a public-looking base URL.',
        'SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1',
    ],
    'Community authenticated smoke public mutation URL guard'
);

$requiredPackageEntries = [
    'actions',
    'admin-menu.php',
    'assets',
    'banner-references.php',
    'dashboard.php',
    'url-embed-targets.php',
    'extension-points.php',
    'helpers',
    'helpers.php',
    'install.sql',
    'layout-options.php',
    'member-only-routes.php',
    'member-group-rules.php',
    'menu-links.php',
    'module.php',
    'operational-status.php',
    'paths.php',
    'popup-layer-references.php',
    'privacy-cleanup.php',
    'privacy-export.php',
    'reaction-targets.php',
    'retention-targets.php',
    'antispam-targets.php',
    'sitemap.php',
    'skins',
    'theme',
    'views',
    'coupon-targets.php',
    'payment-ledger-targets.php',
    'asset-recovery-targets.php',
    'member-group-references.php',
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
    'member-only-routes.php',
    'banner-references.php',
    'popup-layer-references.php',
    'member-group-references.php',
    'url-embed-targets.php',
    'reaction-targets.php',
    'operational-status.php',
    'retention-targets.php',
    'antispam-targets.php',
    'asset-recovery-targets.php',
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
    'GET /community/group',
    'GET /community/board',
    'GET /community/post',
    'GET /community/attachment',
    'GET /community/write',
    'POST /community/write',
    'GET /community/edit',
    'POST /community/edit',
    'POST /community/hide',
    'POST /community/delete',
    'POST /community/comment',
    'POST /community/comment/edit',
    'POST /community/comment/hide',
    'POST /community/comment/delete',
    'POST /community/report',
    'GET /community/scraps',
    'POST /community/scrap',
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
    'sr_community_board_group_path($groupKey)',
    '/community/board?key=',
], 'Community menu-links.php');
if (!is_callable($privacyExport)) {
    sr_community_release_error('Community privacy-export.php must return a callable.');
}
if (!is_callable($privacyCleanup)) {
    sr_community_release_error('Community privacy-cleanup.php must return a callable.');
}
sr_community_release_file_contains('modules/community/privacy-export.php', [
    'sr_community_extra_field_values_export_json((string) ($post[\'extra_values_json\'] ?? \'\'))',
], 'Community privacy export additional field snapshot policy');
sr_community_release_file_contains('modules/community/privacy-cleanup.php', [
    'sr_community_extra_field_values_cleanup_json($originalJson)',
    "cleanup_policy_snapshot <> 'retain'",
    "v.cleanup_policy_snapshot <> 'retain'",
    "'community_post_extra_values_anonymized_count' => \$postExtraValuesAnonymizedCount",
], 'Community privacy cleanup additional field snapshot policy');
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
    'community.board.list',
    'community.post.view',
    'community.post.form',
], 'Community extension-points.php');
sr_community_release_file_contains('modules/community/extension-points.php', [
    "'point_key' => 'community.board.list'",
    "'slot_key' => 'before_list'",
    "'slot_key' => 'after_list'",
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
    'sr_community_board_group_path($groupKey)',
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
    "'scraps' => []",
    'WHERE author_account_id = :account_id',
    'WHERE uploader_account_id = :account_id',
    'WHERE reporter_account_id = :account_id',
    'WHERE account_id = :account_id',
    'SELECT id, board_id, title, body_text, status, created_at, updated_at',
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
    'sr_community_report_auto_actions',
    'sr_community_scraps',
    'sr_community_levels',
    'sr_community_account_levels',
    'sr_community_level_logs',
    'sr_community_level_recalculate_jobs',
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
        'author_public_name_snapshot VARCHAR(120) NOT NULL DEFAULT \'\'',
        'extra_values_json TEXT NULL',
        'is_notice TINYINT(1) NOT NULL DEFAULT 0',
        'KEY idx_sr_community_posts_board_status_id (board_id, status, id)',
        'KEY idx_sr_community_posts_board_notice_status_id (board_id, is_notice, status, id)',
        'KEY idx_sr_community_posts_status_view_id (status, view_count, id)',
        'KEY idx_sr_community_posts_author_id (author_account_id, id)',
    ],
    'sr_community_comments' => [
        'author_public_name_snapshot VARCHAR(120) NOT NULL DEFAULT \'\'',
        'KEY idx_sr_community_comments_post_status_id (post_id, status, id)',
        'KEY idx_sr_community_comments_author_id (author_account_id, id)',
    ],
    'sr_community_hidden_targets' => [
        'target_type VARCHAR(20) NOT NULL',
        'hidden_reason VARCHAR(40) NOT NULL DEFAULT \'\'',
        'UNIQUE KEY uq_sr_community_hidden_targets_target (target_type, target_id)',
        'KEY idx_sr_community_hidden_targets_actor (hidden_by_account_id, id)',
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
    'sr_community_report_auto_actions' => [
        'active_target_uid VARCHAR(80) NULL',
        'target_hidden_by_account_id BIGINT UNSIGNED NULL',
        'threshold_value INT UNSIGNED NOT NULL DEFAULT 0',
        'UNIQUE KEY uq_sr_community_report_auto_actions_active_target (active_target_uid)',
        'KEY idx_sr_community_report_auto_actions_target_status (target_type, target_id, status)',
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
    "'response-content-disposition' => sr_download_content_disposition((string) \$attachment['original_name'], \$disposition)",
    "sr_send_download_headers(\$mimeType, (string) \$attachment['original_name'], \$disposition, \$recordedSize, 'private, no-store, no-cache, must-revalidate')",
], 'Community attachment download policy');

sr_community_release_file_contains('modules/community/actions/write.php', [
    'sr_community_account_can_write_board($pdo, $board, is_array($account) ? $account : null, $isAdminWriter)',
    'sr_community_guest_post_rate_limited($pdo, $settings)',
    'sr_community_post_rate_limited($pdo, (int) $account[\'id\'], $settings)',
    "sr_community_asset_event_config(\$pdo, \$board, \$settings, 'write_charge', 'every_action')",
    'sr_community_record_guest_post_rate_limit($pdo, $settings)',
    'sr_community_record_post_rate_limit($pdo, $authorAccountId, $settings)',
    'sr_community_extra_field_values_json($extraFieldDefinitions, $extraFieldValues)',
    "'event_type' => 'community.post.created'",
], 'Community write action policy');
sr_community_release_file_contains('modules/community/helpers/posts-extra-fields.php', [
    'function sr_community_extra_field_scalar_string',
    'function sr_community_extra_field_definition_validation_errors',
    'function sr_community_extra_field_definitions_input_errors',
    'function sr_community_extra_field_value_max_length',
    'isset($posted[$key]) && !is_scalar($posted[$key])',
    '$values[$key] = is_scalar($value) ? trim((string) $value)',
    "\$errors[] = \$label . '을(를) 확인해 주세요.'",
    '$valueLength > $maxLength',
    "'show_in_admin' => !empty(\$definition['show_in_admin'])",
    "'export_policy' => (string) (\$definition['export_policy'] ?? 'include')",
    "'cleanup_policy' => (string) (\$definition['cleanup_policy'] ?? 'anonymize')",
    'function sr_community_extra_field_values_export_json',
    'function sr_community_extra_field_values_cleanup_json',
    'function sr_community_sync_group_board_field_definitions',
    '값 형식이 올바르지 않습니다.',
], 'Community post extra field input validation');
sr_community_release_file_contains('modules/community/helpers/admin-boards.php', [
    '$extraFieldDefinitionErrors = sr_community_extra_field_definitions_input_errors($extraFieldsInput)',
    '$errors = array_merge($errors, $extraFieldDefinitionErrors)',
], 'Community admin board extra field definition validation');
sr_community_release_file_contains('modules/community/actions/edit.php', [
    'sr_community_account_can_edit_post($post, $account)',
    '$submittedPostId !== $postId',
    'sr_community_guest_can_edit_post($post, sr_post_string_without_truncation(\'guest_password\', 255) ?? \'\')',
    'sr_community_validate_extra_field_values($extraFieldDefinitions, $extraFieldValues)',
    'sr_community_update_post_content($pdo, $postId, $values, $authorAccountId)',
    "'event_type' => 'community.post.updated_by_author'",
], 'Community edit action policy');
sr_community_release_file_contains('modules/community/actions/delete.php', [
    'sr_community_admin_post_by_id($pdo, $postId)',
    'sr_community_account_can_delete_post($post, $account, $pdo)',
    'sr_community_update_post_status($pdo, $postId, \'deleted\')',
    'sr_community_update_post_attachments_status($pdo, $postId, \'deleted\')',
    "'community.post.deleted_by_author'",
    "'community.post.deleted_by_admin'",
    "'community.post.deleted_by_board_manager'",
], 'Community delete action policy');
sr_community_release_file_contains('modules/community/actions/post-hide.php', [
    'sr_community_account_can_hide_post($pdo, $post, $account)',
    "sr_community_update_post_status(\$pdo, \$postId, 'hidden'",
    "sr_community_update_post_attachments_status(\$pdo, \$postId, 'hidden')",
    "'community.post.hidden_by_board_manager'",
], 'Community board staff post hide policy');
sr_community_release_file_contains('modules/community/paths.php', [
    "'POST /community/notice' => 'actions/post-notice.php'",
], 'Community post notice route');
sr_community_release_file_contains('modules/community/actions/post-notice.php', [
    'sr_community_account_can_write_notice($pdo, $board, $account, $isAdminWriter)',
    "sr_community_update_post_notice(\$pdo, \$postId, \$isNotice)",
    "'community.post.notice_set'",
    "'community.post.notice_removed'",
], 'Community post notice action');
sr_community_release_file_contains('modules/community/actions/admin-boards.php', [
    'sr_community_can_delete_board($pdo, $boardId)',
    'delete_confirm_text',
    "'event_type' => 'community.board.delete_confirmation_failed'",
    "'confirmation_checked' => true",
    "'load_grade' => (string) \$deleteLoadAssessment['grade']",
], 'Community board delete high-load confirmation policy');
sr_community_release_file_contains('modules/community/actions/view.php', [
    '$isBoardManagerOgRemove',
    "'actor_type' => \$isAdminOgRemove ? 'admin' : 'community_board_manager'",
    "'permission_source' => \$isAdminOgRemove ? 'admin' : 'board_manager'",
], 'Community delegated OG removal audit policy');
sr_community_release_file_contains('modules/community/helpers/posts-writing.php', [
    "function sr_community_account_can_write_notice",
    "sr_community_account_has_board_management_permission(\$pdo, (int) (\$board['id'] ?? 0), \$accountId, 'write_notice')",
    "sr_admin_has_permission(\$pdo, \$accountId, '/admin/community/posts', 'delete')",
    "sr_community_account_has_board_management_permission(\$pdo, (int) (\$post['board_id'] ?? 0), \$accountId, 'delete_post')",
    "function sr_community_account_can_hide_post",
    "sr_community_account_has_board_management_permission(\$pdo, (int) (\$post['board_id'] ?? 0), \$accountId, 'hide_post')",
    "sr_url_embed_sync_body_url_cache(\$pdo, 'community', 'post', \$postId, 'body', '', null)",
], 'Community delegated post delete policy');
sr_community_release_file_contains('modules/community/actions/comment.php', [
    'sr_community_account_can_comment_post($pdo, $post, is_array($account) ? $account : null)',
    'sr_community_guest_comment_rate_limited($pdo, $settings)',
    'sr_community_comment_rate_limited($pdo, (int) $account[\'id\'], $settings)',
    "sr_community_asset_event_config(\$pdo, \$board, \$settings, 'comment_charge', 'every_action')",
    'sr_community_record_guest_comment_rate_limit($pdo, $settings)',
    'sr_community_record_comment_rate_limit($pdo, $authorAccountId, $settings)',
    "'event_type' => 'community.comment.created'",
    'sr_community_create_account_event_notification(',
    "'comment.created'",
], 'Community comment action policy');
sr_community_release_file_contains('modules/community/actions/comment-edit.php', [
    'sr_community_account_can_edit_comment($comment, $account)',
    'sr_community_guest_can_edit_comment($comment, sr_post_string_without_truncation(\'guest_password\', 255) ?? \'\')',
    'sr_community_update_comment_content($pdo, $commentId, $values)',
    "'event_type' => 'community.comment.updated_by_author'",
], 'Community comment edit action policy');
sr_community_release_file_contains('modules/community/actions/comment-delete.php', [
    'sr_community_account_can_delete_comment($comment, $account)',
    'sr_community_guest_can_delete_comment($comment, sr_post_string_without_truncation(\'guest_password\', 255) ?? \'\')',
    'sr_community_update_comment_status($pdo, $commentId, \'deleted\')',
    "'event_type' => 'community.comment.deleted_by_author'",
    "'community.comment.deleted_by_board_manager'",
], 'Community comment delete action policy');
sr_community_release_file_contains('modules/community/actions/comment-hide.php', [
    'sr_community_account_can_hide_comment($pdo, $comment, $post, $account)',
    "sr_community_update_comment_status(\$pdo, \$commentId, 'hidden'",
    "'community.comment.hidden_by_board_manager'",
], 'Community board staff comment hide policy');
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
    "sr_module_contract_function(\$pdo, 'notification', 'notification-events.php', 'create_account_event_function')",
    'catch (Throwable $exception)',
    'function sr_community_create_admin_report_notifications',
    "p.menu_path = '/admin/community/reports'",
    "p.action_key = 'view'",
], 'Community optional notification integration');

sr_community_release_file_contains('modules/community/actions/comment.php', [
    'sr_community_create_account_event_notification(',
    "'comment.created'",
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
    "'scraps' => []",
], 'Community privacy export coverage');

sr_community_release_file_contains('modules/community/actions/admin-boards.php', [
    "sr_admin_require_permission(\$pdo, (int) \$account['id'], '/admin/community/boards', 'view')",
    "sr_admin_require_permission(\$pdo, (int) \$account['id'], '/admin/community/boards', 'edit')",
    '$allowedReadPolicies = sr_community_policy_values(\'read\')',
    '$allowedWritePolicies = sr_community_policy_values(\'write\')',
    '$allowedCommentPolicies = sr_community_policy_values(\'comment\')',
], 'Community admin board action policy setup');
sr_community_release_file_contains('modules/community/helpers/admin-boards.php', [
    'sr_admin_post_int_in_range(\'read_min_level\', 0, $maxLevel)',
    'sr_admin_post_int_in_range(\'write_min_level\', 0, $maxLevel)',
    'sr_admin_post_int_in_range(\'comment_min_level\', 0, $maxLevel)',
    'sr_community_set_board_setting($pdo, $boardId, \'read_group_keys\', sr_community_board_group_keys_setting_value($readGroupKeys), \'json\')',
    'sr_community_set_board_setting($pdo, $boardId, \'write_group_keys\', sr_community_board_group_keys_setting_value($writeGroupKeys), \'json\')',
    'sr_community_set_board_setting($pdo, $boardId, \'comment_group_keys\', sr_community_board_group_keys_setting_value($commentGroupKeys), \'json\')',
    'sr_community_set_board_setting($pdo, $boardId, \'file_allowed_extensions\', implode(\',\', $fileAllowedExtensions), \'string\')',
    "'event_type' => 'community.board.created'",
    "'event_type' => 'community.board.updated'",
], 'Community admin board save policy');
sr_community_release_file_contains('modules/community/views/admin-boards.php', [
    'data-community-extra-fields-builder',
    'data-community-extra-field-modal',
    'data-community-extra-field-add',
    'data-community-extra-field-save',
    'data-community-extra-field-action',
    'data-community-extra-field-input="key"',
    'data-community-extra-field-input="export_policy"',
    'data-community-extra-field-input="cleanup_policy"',
], 'Community admin board extra field UI');
sr_community_release_file_contains('modules/community/actions/admin-board-groups.php', [
    "sr_admin_require_permission(\$pdo, (int) \$account['id'], '/admin/community/board-groups', 'view')",
    "sr_admin_require_permission(\$pdo, (int) \$account['id'], '/admin/community/board-groups', 'edit')",
    "'target_type' => 'community_board_group'",
], 'Community admin board group basic save');
sr_community_release_file_contains('modules/community/actions/admin-settings.php', [
    "sr_admin_require_permission(\$pdo, (int) \$account['id'], \$communitySettingsPermissionPath, 'view')",
    "sr_admin_require_permission(\$pdo, (int) \$account['id'], \$communitySettingsPermissionPath, 'edit')",
    'sr_require_csrf()',
    'sr_community_update_level_min_scores($pdo, $minScoresById',
    "'event_type' => 'community.settings.updated'",
], 'Community admin settings policy');
sr_community_release_file_not_contains('modules/community/actions/admin-settings.php', [
    '$messageEnabled',
    '$messageWriteGroupKeysInput',
    '$messageWriteGroupKeys',
], 'Community admin settings message module separation');
foreach (array_merge([
    'modules/community/module.php',
    'modules/community/actions/admin-settings.php',
    'modules/community/install.sql',
], glob('modules/community/updates/*.sql') ?: []) as $communitySettingsSource) {
    sr_community_release_file_not_contains($communitySettingsSource, [
        'access_condition_priority',
    ], 'Community legacy access condition priority cleanup');
}
sr_community_release_file_not_contains('modules/community/helpers/members.php', [
    'sr_community_safe_next_path',
    'sr_community_member_needs_nickname',
    'sr_community_require_member_nickname',
    'sr_community_handle_member_nickname_setup_post',
    '/community/nickname',
], 'Community legacy nickname setup cleanup');
sr_community_release_file_not_contains('modules/community/lang/ko.php', [
    'nickname_setup_blocked',
    'ui.nickname.setup.body',
    'ui.nickname.setup.submit',
    'ui.nickname.setup.title',
], 'Community legacy nickname setup language cleanup');
sr_community_release_file_not_contains('modules/community/helpers/members.php', [
    'sr_community_member_registration_fields',
    'sr_community_member_registration_validate',
    'sr_community_member_registration_save',
    'community_nickname_set',
], 'Community legacy nickname registration extension cleanup');
sr_community_release_file_not_contains('modules/community/lang/ko.php', [
    'action.nickname_duplicate',
    'action.nickname_required',
    'action.nickname_saved',
    'action.nickname_too_long',
    'ui.nickname.register.help',
], 'Community legacy nickname registration language cleanup');
foreach ([
    'modules/community/module.php',
    'modules/community/helpers/levels.php',
    'modules/community/actions/admin-settings.php',
    'modules/community/views/admin-settings.php',
    'modules/community/install.sql',
] as $communityNicknameSettingsSource) {
    sr_community_release_file_not_contains($communityNicknameSettingsSource, [
        'nickname_enabled',
        'nickname_required',
        'community_settings_help_nickname',
    ], 'Community legacy nickname setting cleanup');
}
sr_community_release_file_not_contains('modules/community/lang/ko.php', [
    'help.nickname.body.1',
    'help.nickname.body.2',
    'help.nickname.title',
    'ui.nickname.enabled',
    'ui.nickname.enabled.choice',
    'ui.nickname.enabled.help',
], 'Community legacy nickname setting language cleanup');
sr_community_release_file_contains('modules/community/actions/admin-posts.php', [
    "sr_admin_require_permission(\$pdo, (int) \$account['id'], \$communityPostsPermissionPath, 'view')",
    "'extra_values_supported' => true",
    "'extra_field_values_supported' => sr_community_post_field_values_table_exists(\$pdo)",
    "'extra'",
    '$allowedPostStatuses = sr_community_post_statuses()',
    'sr_community_update_post_status($pdo, $postId, $status)',
    'sr_community_update_post_attachments_status($pdo, $postId, $status)',
    "'event_type' => 'community.post.status_updated'",
], 'Community admin post policy');
sr_community_release_file_contains('modules/community/views/admin-posts.php', [
    "postSearchFieldOptions['extra'] = '추가 입력'",
    'sr_community_extra_fields_admin_summary_html(sr_community_extra_field_values_from_json',
], 'Community admin post extra field list display');
sr_community_release_file_contains('modules/community/helpers/posts.php', [
    'ev.show_in_admin_snapshot = 1',
    'ev.value_text LIKE :extra_values_keyword',
], 'Community admin post extra field search visibility');
sr_community_release_file_contains('modules/community/actions/admin-posts.php', [
    "\$communityPostsPermissionPath = \$communityPostsPage === 'comments' ? '/admin/community/comments' : '/admin/community/posts'",
    '$allowedCommentStatuses = sr_community_comment_statuses()',
    'sr_community_update_comment_status($pdo, $commentId, $status)',
    "'event_type' => 'community.comment.status_updated'",
], 'Community admin comment policy');
sr_community_release_file_contains('modules/community/actions/admin-reports.php', [
    "sr_admin_require_permission(\$pdo, (int) \$account['id'], '/admin/community/reports', 'view')",
    '$allowedStatuses = sr_community_report_statuses()',
    'sr_community_report_auto_action_lock_suffix($pdo)',
    "throw new RuntimeException('report_status_conflict')",
    'sr_community_apply_report_target_action($pdo, $report, $normalizedTargetAction, (int) $account[\'id\'], true)',
    'sr_community_apply_report_reporter_action($pdo, $report, $normalizedReporterAction, (int) $account[\'id\'], true)',
    'sr_audit_log_required($pdo, [',
    "'event_type' => 'community.report.status_updated'",
], 'Community admin report policy');
sr_community_release_file_not_contains('modules/community/actions/admin-reports.php', [
    'WHERE id = :id FOR UPDATE',
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

$allowedCommunityScriptFiles = [
    'modules/community/assets/layout.js',
    'modules/community/assets/module.js',
];
foreach (sr_community_release_files('modules/community', ['js', 'scss']) as $assetFile) {
    if (!in_array($assetFile, $allowedCommunityScriptFiles, true)) {
        sr_community_release_error('Community module must not ship JS/SCSS assets without a release policy update: ' . $assetFile);
    }
}
foreach (sr_community_release_files('modules/community', ['php']) as $phpFile) {
    if (str_starts_with($phpFile, 'modules/community/views/ui-kit-samples/')) {
        continue;
    }

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
