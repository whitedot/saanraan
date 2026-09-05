#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}
require_once $root . '/core/helpers/runtime.php';
require_once $root . '/modules/content/helpers.php';
require_once $root . '/.tools/lib/basic-theme-delegates.php';

$publicDataCacheFixtureRoot = sys_get_temp_dir() . '/saanraan-public-sidebar-cache-' . (string) getmypid() . '-' . bin2hex(random_bytes(4));
$GLOBALS['sr_public_data_cache_root_override'] = $publicDataCacheFixtureRoot;
register_shutdown_function(static function () use ($publicDataCacheFixtureRoot): void {
    if (!is_dir($publicDataCacheFixtureRoot)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($publicDataCacheFixtureRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir() && !$item->isLink()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($publicDataCacheFixtureRoot);
});

if (!function_exists('sr_log_exception')) {
    function sr_log_exception(Throwable $exception, string $context): void
    {
        $contexts = $GLOBALS['sr_public_sidebar_logged_contexts'] ?? [];
        $contexts = is_array($contexts) ? $contexts : [];
        $contexts[] = $context;
        $GLOBALS['sr_public_sidebar_logged_contexts'] = $contexts;
    }
}

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};
$assert(
    sr_public_data_cache_root() === $publicDataCacheFixtureRoot,
    'public sidebar cache fixture must not use the repository storage directory.'
);
$source = static function (string $file) use ($root, &$errors): string {
    $contents = sr_check_source_with_basic_theme_delegate($root, $file);
    if (!is_string($contents)) {
        $errors[] = 'cannot read public sidebar source: ' . $file;
        return '';
    }
    return $contents;
};
$contains = static function (string $file, array $markers) use ($source, $assert): void {
    $contents = $source($file);
    foreach ($markers as $marker) {
        $assert(str_contains($contents, $marker), $file . ' missing public sidebar marker: ' . $marker);
    }
};

$contains('modules/content/module.php', [
    "'sidebar_enabled' => true",
    "'sidebar_menu_type' => 'groups'",
    "'sidebar_popular_limit' => 5",
    "'sidebar_comments_limit' => 5",
]);
$contains('modules/content/actions/admin-settings.php', [
    "sr_post_string('sidebar_enabled'",
    "sr_post_string('sidebar_menu_type'",
    "sr_admin_post_int_in_range('sidebar_popular_limit', 1, 10)",
    "sr_admin_post_int_in_range('sidebar_comments_limit', 1, 10)",
]);
$contains('modules/content/views/admin-settings.php', [
    '콘텐츠 메인, 그룹·검색 목록, 콘텐츠 읽기, 회원 콘텐츠 작성 화면',
    "'sidebar_menu_type'",
    'name="sidebar_popular_limit"',
    'name="sidebar_comments_limit"',
    'data-content-sidebar-site-menu-row',
    "selected.value === 'site_menu'",
    'select.required = active',
]);
$contains('modules/content/helpers/sidebar.php', [
    'function sr_content_sidebar_context(',
    'function sr_content_sidebar_group_menu_rows(',
    "'site-menu-provider.php'",
    "'tree_function'",
    "'render_function'",
    "sr_public_data_cache_generation('public-side-menu', 'content.groups'",
    "p.status = 'published'",
    'p.asset_access_enabled <> 1 OR p.asset_access_amount <= 0',
    'c.is_secret = 0',
    'sr_content_comment_body_plain_text(',
]);
$contains('modules/content/theme/basic/sidebar.php', [
    '<aside class="content-sidebar"',
    '인기 콘텐츠',
    '최신댓글',
    'content-sidebar-summary-section',
    'content-sidebar-summary-meta',
    'content-sidebar-comment-excerpt',
    'content-sidebar-comment-byline',
    'content-sidebar-comment-content',
    "['content_title']",
    "'point_key' => 'content.sidebar.summary'",
]);
foreach ([
    'modules/content/views/group.php',
    'modules/content/views/search.php',
    'modules/content/views/content.php',
    'modules/content/views/account-content.php',
    'modules/content/views/home.php',
    'modules/content/theme/basic/group.php',
    'modules/content/theme/basic/content.php',
    'modules/content/theme/basic/home.php',
] as $screenFile) {
    $contains($screenFile, ['sidebar.php']);
}
$contains('modules/content/theme/basic/assets/module.css', [
    '.content-page-view',
    '.content-reading-panel',
    '.content-screen-frame',
    '.content-sidebar',
    '.content-sidebar-summary-section',
    '.content-sidebar-summary-meta',
    '.content-sidebar-comment-excerpt',
    '.content-sidebar-comment-byline',
    'white-space: nowrap',
    'var(--sr-text',
    'var(--sr-muted',
    '@media (max-width: 1024px)',
]);
$contentModuleCss = $source('modules/content/theme/basic/assets/module.css');
$contentSidebarItemRuleMatched = preg_match('/\.content-sidebar-list\s*>\s*li\s*\{([^}]*)\}/s', $contentModuleCss, $contentSidebarItemRuleMatches) === 1;
$assert($contentSidebarItemRuleMatched, 'content sidebar summary item CSS rule must exist.');
if ($contentSidebarItemRuleMatched) {
    $contentSidebarItemRule = (string) ($contentSidebarItemRuleMatches[1] ?? '');
    $assert(str_contains($contentSidebarItemRule, 'gap: 3px'), 'content sidebar summary items must follow the community three-pixel title/meta gap.');
    $assert(!preg_match('/\b(?:border|padding)(?:-[a-z]+)?\s*:/', $contentSidebarItemRule), 'content sidebar summary items must not restore the old dividers or item padding.');
}
$contentSidebarRuleMatched = preg_match('/\.content-sidebar\s*\{([^}]*)\}/s', $contentModuleCss, $contentSidebarRuleMatches) === 1;
$assert($contentSidebarRuleMatched, 'content sidebar base CSS rule must exist.');
if ($contentSidebarRuleMatched) {
    $contentSidebarRule = (string) ($contentSidebarRuleMatches[1] ?? '');
    $assert(!str_contains($contentSidebarRule, 'position: sticky'), 'content sidebar must remain in normal document flow.');
    $assert(!preg_match('/\btop\s*:/', $contentSidebarRule), 'content sidebar must not keep a sticky top offset.');
}
$contains('modules/content/theme/basic/content.php', ['content-page-view', 'content-reading-panel', 'content-view-actions', 'content-view-action-group-trailing', 'content-comments-panel']);
$contains('modules/content/views/content.php', ['content-page-view', 'content-reading-panel', 'content-view-actions', 'content-view-action-group-trailing', 'content-comments-panel']);
foreach (['modules/content/theme/basic/content.php', 'modules/content/views/content.php'] as $contentViewFile) {
    $contentViewSource = $source($contentViewFile);
    $assert(substr_count($contentViewSource, 'class="content-view-actions') === 2, $contentViewFile . ' must render top and bottom content action rows.');
    $reactionPosition = strpos($contentViewSource, "sr_reaction_render_widget(\$pdo, 'content', 'content'");
    $bottomActionsPosition = strpos($contentViewSource, 'class="content-view-actions content-view-actions-bottom"');
    $assert(is_int($reactionPosition) && is_int($bottomActionsPosition) && $bottomActionsPosition > $reactionPosition, $contentViewFile . ' must render bottom actions after the content reaction widget position.');
}
$contains('modules/content/helpers.php', ["'content.form'", 'sidebar_enabled', 'sidebar_menu_type']);

$excerpt = sr_content_sidebar_excerpt('<p>태그 <strong>제거</strong></p>', 72);
$assert($excerpt === '태그 제거', 'content sidebar excerpt must remove HTML tags.');

$contains('modules/community/helpers/boards.php', [
    "'site-menu-provider.php'",
    "'options_function'",
    "'tree_function'",
    "'render_function'",
]);
foreach ([
    'modules/content/actions/admin-settings.php',
    'modules/content/helpers/sidebar.php',
    'modules/community/actions/admin-boards.php',
    'modules/community/actions/admin-settings.php',
    'modules/community/helpers/boards.php',
] as $siteMenuConsumerFile) {
    $assert(
        !str_contains($source($siteMenuConsumerFile), 'modules/site_menu/helpers.php'),
        $siteMenuConsumerFile . ' must use the site menu provider contract instead of including its helper directly.'
    );
}

$sidebarPdo = new PDO('sqlite::memory:');
$sidebarPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sidebarPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$sidebarPdo->exec("CREATE TABLE sr_content_groups (id INTEGER PRIMARY KEY, group_key TEXT, title TEXT, description TEXT, status TEXT, sort_order INTEGER, created_at TEXT, updated_at TEXT)");
$sidebarPdo->exec("INSERT INTO sr_content_groups VALUES (1, 'news', '뉴스', '', 'enabled', 10, '2026-07-19 00:00:00', '2026-07-19 00:00:00')");
sr_public_data_cache_clear_namespace('public-side-menu');
$contentMenuRows = sr_content_sidebar_group_menu_rows($sidebarPdo);
$assert(($contentMenuRows[0]['title'] ?? '') === '뉴스', 'content sidebar group menu cache must store enabled group rows.');
$sidebarPdo->exec("UPDATE sr_content_groups SET title = 'DB만 변경' WHERE id = 1");
unset($GLOBALS['sr_public_data_cache_memory']);
$assert((sr_content_sidebar_group_menu_rows($sidebarPdo)[0]['title'] ?? '') === '뉴스', 'content sidebar group menu must reuse its file cache across request memory resets.');
$staleContentGeneration = sr_public_data_cache_generation('public-side-menu', 'content.groups', 'content_sidebar_groups_v1');
sr_content_update_group($sidebarPdo, 1, ['title' => '변경된 뉴스', 'description' => '', 'status' => 'enabled', 'sort_order' => 10]);
$assert(
    !sr_public_data_cache_write(
        'public-side-menu',
        'content.groups',
        'content_sidebar_groups_v1',
        [['group_key' => 'news', 'title' => '무효화 전 값']],
        $staleContentGeneration
    ),
    'content sidebar cache must reject a stale writer after group invalidation.'
);
$assert((sr_content_sidebar_group_menu_rows($sidebarPdo)[0]['title'] ?? '') === '변경된 뉴스', 'content group update must invalidate the sidebar menu cache.');
sr_public_data_cache_write('public-side-menu', 'content.groups', 'content_sidebar_groups_v1', [['group_key' => '../invalid', 'title' => '손상값']]);
$assert((sr_content_sidebar_group_menu_rows($sidebarPdo)[0]['title'] ?? '') === '변경된 뉴스', 'content sidebar must reject an invalid cached group row and reload the database value.');
$staleContentGeneration = sr_public_data_cache_generation('public-side-menu', 'content.groups', 'content_sidebar_groups_v1');
sr_public_data_cache_clear_namespace('public-side-menu');
$assert(
    !sr_public_data_cache_write(
        'public-side-menu',
        'content.groups',
        'content_sidebar_groups_v1',
        [['group_key' => 'news', 'title' => '무효화 전 값']],
        $staleContentGeneration
    ),
    'namespace invalidation must reject writers holding an earlier namespace generation.'
);

$publicSideMenuGenerationPath = sr_public_data_cache_namespace_generation_path('public-side-menu');
file_put_contents($publicSideMenuGenerationPath, "broken\n");
unset($GLOBALS['sr_public_data_cache_memory']);
$assert(
    sr_public_data_cache_generation('public-side-menu', 'content.groups', 'content_sidebar_groups_v1') === '',
    'an invalid namespace generation marker must disable the affected cache.'
);
$assert(
    !sr_public_data_cache_write(
        'public-side-menu',
        'content.groups',
        'content_sidebar_groups_v1',
        [['group_key' => 'news', 'title' => '손상 marker 값']]
    ),
    'an invalid namespace generation marker must reject cache writes.'
);
$assert(
    sr_public_data_cache_read('public-side-menu', 'content.groups', 'content_sidebar_groups_v1') === null,
    'an invalid namespace generation marker must reject cache reads.'
);
$assert(
    sr_public_data_cache_forget('public-side-menu', 'content.groups', 'content_sidebar_groups_v1'),
    'entry invalidation must escalate to namespace invalidation when the namespace marker is invalid.'
);
$assert(
    sr_public_data_cache_generation('public-side-menu', 'content.groups', 'content_sidebar_groups_v1') !== '',
    'entry invalidation must restore a usable generation after repairing the namespace marker.'
);

$failureNamespace = 'public-cache-failure-fixture';
$failureCacheKey = 'fixture.groups';
$failureSchema = 'fixture_groups_v1';
sr_public_data_cache_clear_namespace($failureNamespace);
$failureGeneration = sr_public_data_cache_generation($failureNamespace, $failureCacheKey, $failureSchema);
$assert(
    sr_public_data_cache_write(
        $failureNamespace,
        $failureCacheKey,
        $failureSchema,
        [['group_key' => 'fixture', 'title' => '기존 캐시']],
        $failureGeneration
    ),
    'cache invalidation failure fixture must create an initial payload.'
);
$failurePayloadPath = sr_public_data_cache_path($failureNamespace, $failureCacheKey, $failureSchema, $failureGeneration);
$failureNamespaceDirectory = sr_public_data_cache_root() . '/' . $failureNamespace;
chmod($failureNamespaceDirectory, 0555);
$assert(
    !sr_public_data_cache_clear_namespace($failureNamespace),
    'cache namespace clear must report a namespace generation rotation failure.'
);
chmod($failureNamespaceDirectory, 0775);
$assert(
    !is_file($failurePayloadPath)
        && sr_public_data_cache_read($failureNamespace, $failureCacheKey, $failureSchema, $failureGeneration) === null,
    'failed namespace generation rotation must clear known payloads and request memory as a fallback.'
);
$loggedCacheFailureContexts = $GLOBALS['sr_public_sidebar_logged_contexts'] ?? [];
$assert(
    is_array($loggedCacheFailureContexts)
        && in_array('public_data_cache_generation_invalid', $loggedCacheFailureContexts, true)
        && in_array('public_data_cache_namespace_invalidation_failed', $loggedCacheFailureContexts, true),
    'invalid generation markers and generation rotation failures must be logged.'
);
@unlink(sr_public_data_cache_namespace_generation_path($failureNamespace));
@rmdir(dirname($failurePayloadPath));
@rmdir($failureNamespaceDirectory);

if ($errors !== []) {
    fwrite(STDERR, "public module sidebar checks failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "public module sidebar checks completed.\n";
