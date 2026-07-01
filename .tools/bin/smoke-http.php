#!/usr/bin/env php
<?php

declare(strict_types=1);

function sr_smoke_argument(array $argv, int $index, string $environmentKey): string
{
    $argument = (string) ($argv[$index] ?? '');
    if ($argument !== '') {
        return $argument;
    }

    $environmentValue = getenv($environmentKey);
    return is_string($environmentValue) ? $environmentValue : '';
}

$baseUrl = rtrim(sr_smoke_argument($argv, 1, 'SR_SMOKE_BASE_URL'), '/');
if ($baseUrl === '' || !preg_match('#\Ahttps?://#', $baseUrl)) {
    fwrite(STDERR, "Usage: php .tools/bin/smoke-http.php http://127.0.0.1:8080\nEnv: SR_SMOKE_BASE_URL SR_SMOKE_EXPECT_COMMUNITY=1 SR_SMOKE_MEMBER_ONLY=1\n");
    exit(2);
}
$expectCommunity = getenv('SR_SMOKE_EXPECT_COMMUNITY') === '1';
$expectMemberOnly = getenv('SR_SMOKE_MEMBER_ONLY') === '1';
$basePath = (string) (parse_url($baseUrl, PHP_URL_PATH) ?? '');
$basePath = $basePath !== '/' ? rtrim($basePath, '/') : '';

$checks = [
    [
        'label' => 'home or install entry',
        'path' => '/',
        'allowed_statuses' => [200, 302],
        'required_headers' => [
            'x-content-type-options' => 'nosniff',
            'x-frame-options' => 'SAMEORIGIN',
            'referrer-policy' => 'no-referrer',
            'content-security-policy' => ["default-src 'self'", "frame-src 'self' https://www.youtube-nocookie.com https://www.youtube.com"],
            'cache-control' => 'no-store',
        ],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'login route',
        'path' => '/login',
        'allowed_statuses' => [200, 302],
        'required_headers' => [
            'x-content-type-options' => 'nosniff',
            'x-frame-options' => 'SAMEORIGIN',
            'referrer-policy' => 'no-referrer',
            'content-security-policy' => ["default-src 'self'", "frame-src 'self' https://www.youtube-nocookie.com https://www.youtube.com"],
            'cache-control' => 'no-store',
        ],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'password reset route',
        'path' => '/password/reset',
        'allowed_statuses' => [200, 302],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'public UI kit route',
        'path' => '/ui-kit',
        'allowed_statuses' => [200],
        'must_contain_by_status' => [
            200 => ['Public UI-KIT'],
        ],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'content theme UI kit route',
        'path' => '/content/ui-kit',
        'allowed_statuses' => [200],
        'must_contain_by_status' => [
            200 => ['data-theme-ui-kit-view="content.'],
        ],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community theme UI kit route',
        'path' => '/community/ui-kit',
        'allowed_statuses' => [200],
        'must_contain_by_status' => [
            200 => ['data-theme-ui-kit-view="community.'],
        ],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'quiz theme UI kit route',
        'path' => '/quiz/ui-kit',
        'allowed_statuses' => [200],
        'must_contain_by_status' => [
            200 => ['data-theme-ui-kit-view="quiz.'],
        ],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'survey theme UI kit route',
        'path' => '/survey/ui-kit',
        'allowed_statuses' => [200],
        'must_contain_by_status' => [
            200 => ['data-theme-ui-kit-view="survey.'],
        ],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'favicon fallback endpoint',
        'path' => '/favicon.ico',
        'allowed_statuses' => [302, 404],
        'required_headers' => [
            'cache-control' => 'no-store',
        ],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'admin entry',
        'path' => '/admin',
        'allowed_statuses' => [200, 302, 403],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'admin updates entry',
        'path' => '/admin/updates',
        'allowed_statuses' => [200, 302, 403],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'admin operations entry',
        'path' => '/admin/operations',
        'allowed_statuses' => [200, 302, 403],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'admin storage cache entry',
        'path' => '/admin/storage-cache',
        'allowed_statuses' => [200, 302, 403],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'content missing slug entry',
        'path' => '/content/example',
        'allowed_statuses' => [200, 302, 404],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'admin content entry',
        'path' => '/admin/content',
        'allowed_statuses' => [200, 302, 403, 404],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'admin content author rewards entry',
        'path' => '/admin/content/author-rewards',
        'allowed_statuses' => [200, 302, 403, 404],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'admin content payments entry',
        'path' => '/admin/content/payments',
        'allowed_statuses' => [200, 302, 403, 404],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'admin asset reconciliation entry',
        'path' => '/admin/assets/reconciliation',
        'allowed_statuses' => [200, 302, 403, 404],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'admin asset recovery failures entry',
        'path' => '/admin/assets/recovery-failures',
        'allowed_statuses' => [200, 302, 403, 404],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'admin asset exchange logs entry',
        'path' => '/admin/asset-exchange/logs',
        'allowed_statuses' => [200, 302, 403, 404],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'admin asset exchange correction action guard',
        'method' => 'POST',
        'path' => '/admin/asset-exchange/logs',
        'body' => 'intent=correct_completed_group&exchange_group_id=fixture_exchange_group',
        'allowed_statuses' => [200, 302, 400, 403, 404],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community entry',
        'path' => '/community',
        'allowed_statuses' => [200, 302, 404],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community default board entry',
        'path' => '/community/board?key=free',
        'allowed_statuses' => [200, 302, 403, 404],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community default board group entry',
        'path' => '/community/group?key=general',
        'allowed_statuses' => [200, 302, 404],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community message write entry',
        'path' => '/community/message/write',
        'allowed_statuses' => [302, 404],
        'redirect_path_prefixes' => ['/login?next='],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community write auth guard',
        'path' => '/community/write?key=free',
        'allowed_statuses' => [302, 404],
        'redirect_path_prefixes' => ['/login?next='],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community edit auth guard',
        'path' => '/community/edit?id=1',
        'allowed_statuses' => [302, 404],
        'redirect_path_prefixes' => ['/login?next='],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community edit action auth guard',
        'method' => 'POST',
        'path' => '/community/edit',
        'allowed_statuses' => [302, 404],
        'redirect_path_prefixes' => ['/login?next='],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community delete action auth guard',
        'method' => 'POST',
        'path' => '/community/delete',
        'allowed_statuses' => [302, 404],
        'redirect_path_prefixes' => ['/login?next='],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community comment action auth guard',
        'method' => 'POST',
        'path' => '/community/comment',
        'allowed_statuses' => [302, 404],
        'redirect_path_prefixes' => ['/login?next='],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'content comment action auth guard',
        'method' => 'POST',
        'path' => '/content/comment',
        'allowed_statuses' => [302, 404],
        'redirect_path_prefixes' => ['/login?next='],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community report action auth guard',
        'method' => 'POST',
        'path' => '/community/report',
        'allowed_statuses' => [302, 404],
        'redirect_path_prefixes' => ['/login?next='],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community comment edit action auth guard',
        'method' => 'POST',
        'path' => '/community/comment/edit',
        'allowed_statuses' => [302, 404],
        'redirect_path_prefixes' => ['/login?next='],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community comment delete action auth guard',
        'method' => 'POST',
        'path' => '/community/comment/delete',
        'allowed_statuses' => [302, 404],
        'redirect_path_prefixes' => ['/login?next='],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community scraps auth guard',
        'path' => '/community/scraps',
        'allowed_statuses' => [302, 404],
        'redirect_path_prefixes' => ['/login?next='],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community scrap action auth guard',
        'method' => 'POST',
        'path' => '/community/scrap',
        'allowed_statuses' => [302, 404],
        'redirect_path_prefixes' => ['/login?next='],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community series scrap action auth guard',
        'method' => 'POST',
        'path' => '/community/scrap',
        'body' => 'target_type=series&series_id=1&intent=add',
        'allowed_statuses' => [302, 404],
        'redirect_path_prefixes' => ['/login?next='],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community messages auth guard',
        'path' => '/community/messages',
        'allowed_statuses' => [302, 404],
        'redirect_path_prefixes' => ['/login?next='],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community message view auth guard',
        'path' => '/community/message?id=1',
        'allowed_statuses' => [302, 404],
        'redirect_path_prefixes' => ['/login?next='],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community message write action auth guard',
        'method' => 'POST',
        'path' => '/community/message/write',
        'allowed_statuses' => [302, 404],
        'redirect_path_prefixes' => ['/login?next='],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community message delete action auth guard',
        'method' => 'POST',
        'path' => '/community/message/delete',
        'allowed_statuses' => [302, 404],
        'redirect_path_prefixes' => ['/login?next='],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community admin boards entry',
        'path' => '/admin/community/boards',
        'allowed_statuses' => [200, 302, 403, 404],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community admin board groups entry',
        'path' => '/admin/community/board-groups',
        'allowed_statuses' => [200, 302, 403, 404],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community admin reports entry',
        'path' => '/admin/community/reports',
        'allowed_statuses' => [200, 302, 403, 404],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community admin posts entry',
        'path' => '/admin/community/posts',
        'allowed_statuses' => [200, 302, 403, 404],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community admin payments entry',
        'path' => '/admin/community/payments',
        'allowed_statuses' => [200, 302, 403, 404],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community admin recovery failures entry',
        'path' => '/admin/community/recovery-failures',
        'allowed_statuses' => [200, 302, 403, 404],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'community admin feed cache entry',
        'path' => '/admin/community/feed-cache',
        'allowed_statuses' => [200, 302, 403, 404],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'sitemap endpoint',
        'path' => '/sitemap.xml',
        'allowed_statuses' => [200, 404],
        'must_contain_by_status' => [
            200 => ['<urlset', '</urlset>'],
        ],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'robots endpoint',
        'path' => '/robots.txt',
        'allowed_statuses' => [200, 404],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'public layout stylesheet',
        'path' => '/assets/layout.css',
        'allowed_statuses' => [200],
        'must_contain' => [
            '.public-layout-header',
            '.public-layout-main',
            '.public-layout-footer',
            'grid-template-columns: minmax(180px, 1fr) auto minmax(90px, 1fr)',
        ],
    ],
    [
        'label' => 'public module stylesheet',
        'path' => '/assets/module.css',
        'allowed_statuses' => [200],
        'must_contain' => [
            '.public-home',
        ],
    ],
    [
        'label' => 'site sample view theme stylesheet',
        'path' => '/assets/theme/sample.css',
        'allowed_statuses' => [200],
        'must_contain' => [
            '.example-site-theme',
        ],
    ],
    [
        'label' => 'public layout script',
        'path' => '/assets/public-layout.js',
        'allowed_statuses' => [200],
        'must_contain' => [
            '[data-public-scroll-header]',
            'is-public-layout-header-hidden',
        ],
    ],
    [
        'label' => 'public UI stylesheet',
        'path' => '/assets/ui-kit.css',
        'allowed_statuses' => [200],
        'must_contain' => [
            '.public-ui-scope',
            '.public-ui-card',
        ],
    ],
    [
        'label' => 'public UI kit layout stylesheet',
        'path' => '/assets/ui-kit-layout.css',
        'allowed_statuses' => [200],
        'must_contain' => [
            '.public-ui-kit',
        ],
    ],
    [
        'label' => 'content sample view theme stylesheet',
        'path' => '/modules/content/theme/sample/assets/theme.css',
        'allowed_statuses' => [200],
        'must_contain' => [
            '.example-content-theme',
        ],
    ],
    [
        'label' => 'community theme reset stylesheet',
        'path' => '/modules/community/theme/basic/assets/reset.css',
        'allowed_statuses' => [200],
        'must_contain' => [
            '--color-body-bg',
        ],
    ],
    [
        'label' => 'community theme UI stylesheet',
        'path' => '/modules/community/theme/basic/assets/ui-kit.css',
        'allowed_statuses' => [200],
        'must_contain' => [
            '.community-ui-scope',
        ],
    ],
    [
        'label' => 'community theme layout stylesheet',
        'path' => '/modules/community/theme/basic/assets/layout.css',
        'allowed_statuses' => [200],
        'must_contain' => [
            '.community-layout-header',
            '.community-layout-main',
            '.community-layout-footer',
        ],
    ],
    [
        'label' => 'community theme public stylesheet',
        'path' => '/modules/community/theme/basic/assets/module.css',
        'allowed_statuses' => [200],
        'must_contain' => [
            '.community-screen',
        ],
    ],
    [
        'label' => 'community sample view theme stylesheet',
        'path' => '/modules/community/theme/sample/assets/theme.css',
        'allowed_statuses' => [200],
        'must_contain' => [
            '.example-community-theme',
        ],
    ],
    [
        'label' => 'community theme UI kit layout stylesheet',
        'path' => '/modules/community/theme/basic/assets/ui-kit-layout.css',
        'allowed_statuses' => [200],
        'must_contain' => [
            '.community-ui-kit',
        ],
    ],
    [
        'label' => 'quiz theme layout stylesheet',
        'path' => '/modules/quiz/theme/basic/assets/layout.css',
        'allowed_statuses' => [200],
        'must_contain' => [
            '.quiz-layout-header',
            '.quiz-layout-main',
            '.quiz-layout-footer',
        ],
    ],
    [
        'label' => 'quiz sample view theme stylesheet',
        'path' => '/modules/quiz/theme/sample/assets/theme.css',
        'allowed_statuses' => [200],
        'must_contain' => [
            '.example-quiz-theme',
        ],
    ],
    [
        'label' => 'survey theme layout stylesheet',
        'path' => '/modules/survey/theme/basic/assets/layout.css',
        'allowed_statuses' => [200],
        'must_contain' => [
            '.survey-layout-header',
            '.survey-layout-main',
            '.survey-layout-footer',
        ],
    ],
    [
        'label' => 'survey sample view theme stylesheet',
        'path' => '/modules/survey/theme/sample/assets/theme.css',
        'allowed_statuses' => [200],
        'must_contain' => [
            '.example-survey-theme',
        ],
    ],
    [
        'label' => 'content layout script',
        'path' => '/modules/content/assets/layout.js',
        'allowed_statuses' => [200],
        'must_contain' => [
            '[data-content-scroll-header]',
            'is-content-layout-header-hidden',
        ],
    ],
    [
        'label' => 'content module script',
        'path' => '/modules/content/assets/module.js',
        'allowed_statuses' => [200],
        'must_contain' => [
            "'use strict'",
        ],
    ],
    [
        'label' => 'quiz layout script',
        'path' => '/modules/quiz/assets/layout.js',
        'allowed_statuses' => [200],
        'must_contain' => [
            '[data-quiz-scroll-header]',
            'is-quiz-layout-header-hidden',
        ],
    ],
    [
        'label' => 'quiz module script',
        'path' => '/modules/quiz/assets/module.js',
        'allowed_statuses' => [200],
        'must_contain' => [
            "'use strict'",
        ],
    ],
    [
        'label' => 'survey layout script',
        'path' => '/modules/survey/assets/layout.js',
        'allowed_statuses' => [200],
        'must_contain' => [
            '[data-survey-scroll-header]',
            'is-survey-layout-header-hidden',
        ],
    ],
    [
        'label' => 'survey module script',
        'path' => '/modules/survey/assets/module.js',
        'allowed_statuses' => [200],
        'must_contain' => [
            "'use strict'",
        ],
    ],
    [
        'label' => 'community layout script',
        'path' => '/modules/community/assets/layout.js',
        'allowed_statuses' => [200],
        'must_contain' => [
            '[data-community-scroll-nav]',
            'is-community-layout-nav-hidden',
        ],
    ],
    [
        'label' => 'community module script',
        'path' => '/modules/community/assets/module.js',
        'allowed_statuses' => [200],
        'must_contain' => [
            "'use strict'",
        ],
    ],
    [
        'label' => 'member basic skin stylesheet',
        'path' => '/modules/member/skins/basic/skin.css',
        'allowed_statuses' => [200],
        'must_contain' => [
            '.member-skin-basic-page',
        ],
    ],
    [
        'label' => 'ckeditor plugin script',
        'path' => '/modules/ckeditor/assets/saanraan-ckeditor.js',
        'allowed_statuses' => [200],
        'must_contain' => [
            'window.srCkeditorInstances',
            'sr-ckeditor-unavailable',
        ],
    ],
    [
        'label' => 'ckeditor plugin stylesheet',
        'path' => '/modules/ckeditor/assets/saanraan-ckeditor.css',
        'allowed_statuses' => [200],
        'must_contain' => [
            '.sr-ckeditor',
        ],
    ],
    [
        'label' => 'ckeditor self-hosted script',
        'path' => '/modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js',
        'allowed_statuses' => [200],
        'must_contain' => [
            'CKEditor',
            'ClassicEditor',
        ],
    ],
    [
        'label' => 'ckeditor self-hosted stylesheet',
        'path' => '/modules/ckeditor/vendor/ckeditor5/ckeditor5.css',
        'allowed_statuses' => [200],
        'must_contain' => [
            '.ck',
        ],
    ],
    [
        'label' => 'database SQL protection',
        'path' => '/database/core/install.sql',
        'must_not_expose' => ['CREATE TABLE IF NOT EXISTS sr_site_settings'],
    ],
    [
        'label' => 'module SQL protection',
        'path' => '/modules/member/install.sql',
        'must_not_expose' => ['CREATE TABLE IF NOT EXISTS sr_member_accounts'],
    ],
    [
        'label' => 'community SQL protection',
        'path' => '/modules/community/install.sql',
        'must_not_expose' => ['CREATE TABLE IF NOT EXISTS sr_community_boards'],
    ],
    [
        'label' => 'community metadata protection',
        'path' => '/modules/community/module.php',
        'must_not_expose' => ["'name' => 'Community'"],
    ],
    [
        'label' => 'core PHP protection',
        'path' => '/core/helpers.php',
        'must_not_expose' => ['require_once SR_ROOT'],
    ],
    [
        'label' => 'request bootstrap PHP protection',
        'path' => '/core/request-bootstrap.php',
        'must_not_expose' => ['function sr_request_bootstrap_'],
    ],
    [
        'label' => 'config directory protection',
        'path' => '/config/.gitignore',
        'must_not_expose' => ['config-*.tmp.php'],
    ],
    [
        'label' => 'config file protection',
        'path' => '/config/config.php',
        'must_not_expose' => ['db', 'password', 'app_key'],
    ],
    [
        'label' => 'storage directory protection',
        'path' => '/storage/.gitignore',
        'must_not_expose' => ['!.gitignore'],
    ],
    [
        'label' => 'installed lock protection',
        'path' => '/storage/installed.lock',
        'must_not_expose' => ['installed'],
    ],
    [
        'label' => 'docs protection',
        'path' => '/docs/deployment-protection.md',
        'must_not_expose' => ['# 배포 보호 기준'],
    ],
    [
        'label' => 'examples protection',
        'path' => '/examples/sample_module/module.php',
        'must_not_expose' => ['Minimal sample module for Saanraan extension contracts.'],
    ],
    [
        'label' => 'agent instructions protection',
        'path' => '/AGENTS.md',
        'must_not_expose' => ['# AGENTS.md'],
    ],
    [
        'label' => 'readme protection',
        'path' => '/README.md',
        'must_not_expose' => ['# Saanraan'],
    ],
    [
        'label' => 'tooling protection',
        'path' => '/.tools/bin/check.php',
        'must_not_expose' => ['sr_check_run'],
    ],
    [
        'label' => 'repository metadata protection',
        'path' => '/.git/HEAD',
        'must_not_expose' => ['ref: refs/'],
    ],
    [
        'label' => 'environment variant protection',
        'path' => '/.env.local',
        'must_not_expose' => ['SR_DB_PASSWORD', 'DB_PASSWORD', 'APP_KEY'],
    ],
];

if ($expectCommunity) {
    foreach ($checks as &$check) {
        $path = (string) ($check['path'] ?? '');
        if (!str_starts_with($path, '/community') && !str_starts_with($path, '/admin/community')) {
            continue;
        }

        if (isset($check['must_not_expose'])) {
            continue;
        }

        $allowedStatuses = isset($check['allowed_statuses']) && is_array($check['allowed_statuses'])
            ? $check['allowed_statuses']
            : [];
        $check['allowed_statuses'] = array_values(array_filter($allowedStatuses, static function ($status): bool {
            return (int) $status !== 404;
        }));
        $check['expect_installed_route'] = true;
    }
    unset($check);
}

if ($expectMemberOnly) {
    $memberOnlyRedirectPaths = [
        '/' => true,
        '/ui-kit' => true,
        '/content/example' => true,
        '/community' => true,
        '/community/board?key=free' => true,
        '/community/group?key=general' => true,
        '/community/message/write' => true,
        '/community/write?key=free' => true,
        '/community/edit?id=1' => true,
        '/community/scraps' => true,
        '/community/messages' => true,
        '/community/message?id=1' => true,
    ];
    $memberOnlyForbiddenPosts = [
        'POST /community/edit' => true,
        'POST /community/delete' => true,
        'POST /community/comment' => true,
        'POST /content/comment' => true,
        'POST /community/report' => true,
        'POST /community/comment/edit' => true,
        'POST /community/comment/delete' => true,
        'POST /community/scrap' => true,
        'POST /community/message/write' => true,
        'POST /community/message/delete' => true,
    ];

    foreach ($checks as &$check) {
        if (isset($check['must_not_expose'])) {
            continue;
        }

        $path = (string) ($check['path'] ?? '');
        $method = strtoupper((string) ($check['method'] ?? 'GET'));
        $key = $method . ' ' . $path;

        if (isset($memberOnlyRedirectPaths[$path]) && $method === 'GET') {
            $check['allowed_statuses'] = [302, 404];
            $check['redirect_path_prefixes'] = ['/login?next='];
            unset($check['must_contain'], $check['must_contain_by_status']);
        } elseif (isset($memberOnlyForbiddenPosts[$key])) {
            $check['allowed_statuses'] = [403, 404];
            unset($check['redirect_path_prefixes']);
        } elseif ($path === '/sitemap.xml') {
            $check['allowed_statuses'] = [200, 404];
            $check['must_contain_by_status'] = [
                200 => ['<urlset', '</urlset>'],
            ];
        } elseif ($path === '/robots.txt') {
            $check['allowed_statuses'] = [200, 404];
            $check['must_contain_by_status'] = [
                200 => ['User-agent: *', 'Disallow: /'],
            ];
        }
    }
    unset($check);
}

function sr_smoke_fetch(string $url, string $method, string $requestBody = ''): array
{
    $headers = "User-Agent: Saanraan-Smoke-Check\r\n";
    if ($requestBody !== '') {
        $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'timeout' => 10,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'header' => $headers,
            'content' => $requestBody,
        ],
    ]);

    set_error_handler(static function (): bool {
        return true;
    });
    $body = file_get_contents($url, false, $context);
    restore_error_handler();
    $headers = function_exists('http_get_last_response_headers')
        ? http_get_last_response_headers()
        : ($http_response_header ?? []);
    $status = 0;
    $location = '';
    $headerMap = [];
    foreach ($headers as $header) {
        if (preg_match('#\AHTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
            $status = (int) $matches[1];
        }
        if (preg_match('#\ALocation:\s*(.+)\z#i', $header, $matches) === 1) {
            $location = trim($matches[1]);
        }
        if (strpos($header, ':') !== false) {
            [$name, $value] = explode(':', $header, 2);
            $headerMap[strtolower(trim($name))] = trim($value);
        }
    }

    return [
        'status' => $status,
        'body' => is_string($body) ? $body : '',
        'location' => $location,
        'headers' => $headerMap,
    ];
}

function sr_smoke_location_path(string $location): string
{
    if ($location === '') {
        return '';
    }

    $path = parse_url($location, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return $location;
    }

    $query = parse_url($location, PHP_URL_QUERY);
    if (is_string($query) && $query !== '') {
        return $path . '?' . $query;
    }

    return $path;
}

function sr_smoke_is_install_entry(int $status, string $body): bool
{
    return $status === 200
        && str_contains($body, 'sr-install-page')
        && str_contains($body, 'data-install-current-step')
        && str_contains($body, 'Saanraan 설치');
}

function sr_smoke_is_install_csrf_error(int $status, string $body): bool
{
    return $status === 400
        && str_contains($body, '<title>400</title>')
        && str_contains($body, '요청 보안 토큰이 올바르지 않습니다.');
}

function sr_smoke_css_rule_body(string $css, string $selector): string
{
    $pattern = '/(^|})\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/s';
    if (preg_match($pattern, $css, $matches) !== 1) {
        return '';
    }

    return (string) $matches[2];
}

function sr_smoke_css_declarations(string $ruleBody): array
{
    $declarations = [];
    foreach (explode(';', $ruleBody) as $declaration) {
        $parts = explode(':', $declaration, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $property = strtolower(trim($parts[0]));
        if ($property === '') {
            continue;
        }

        $declarations[$property] = trim($parts[1]);
    }

    return $declarations;
}

$errors = [];
$isInstallMode = false;
foreach ($checks as $check) {
    $url = $baseUrl . (string) $check['path'];
    $method = strtoupper((string) ($check['method'] ?? 'GET'));
    $response = sr_smoke_fetch($url, $method, (string) ($check['body'] ?? ''));
    $status = (int) $response['status'];
    $body = (string) $response['body'];
    $headers = is_array($response['headers'] ?? null) ? $response['headers'] : [];
    $locationPath = sr_smoke_location_path((string) $response['location']);
    $label = (string) $check['label'];
    $isInstallEntry = sr_smoke_is_install_entry($status, $body);
    if ($isInstallEntry) {
        $isInstallMode = true;
    }
    $isInstallPostCsrfError = $isInstallMode
        && $method === 'POST'
        && sr_smoke_is_install_csrf_error($status, $body);
    $checkErrors = [];

    if (
        !$isInstallEntry
        && !$isInstallPostCsrfError
        && isset($check['allowed_statuses'])
        && !in_array($status, $check['allowed_statuses'], true)
    ) {
        $checkErrors[] = $label . ' returned unexpected status ' . $status . ' for ' . $url;
    }

    if (!empty($check['expect_installed_route']) && $status === 404) {
        $checkErrors[] = $label . ' returned 404 while SR_SMOKE_EXPECT_COMMUNITY=1 for ' . $url;
    }

    if (!$isInstallEntry && !$isInstallPostCsrfError) {
        foreach ($check['must_contain'] ?? [] as $needle) {
            if (!str_contains($body, (string) $needle)) {
                $checkErrors[] = $label . ' did not contain expected text "' . (string) $needle . '" for ' . $url;
            }
        }
    }

    foreach ($check['must_contain_css_rules'] ?? [] as $rule) {
        if (!is_array($rule)) {
            continue;
        }

        $selector = is_string($rule['selector'] ?? null) ? (string) $rule['selector'] : '';
        if ($selector === '') {
            continue;
        }

        $ruleBody = sr_smoke_css_rule_body($body, $selector);
        if ($ruleBody === '') {
            $checkErrors[] = $label . ' did not contain expected CSS rule "' . $selector . '" for ' . $url;
            continue;
        }

        $actualDeclarations = sr_smoke_css_declarations($ruleBody);
        foreach ($rule['declarations'] ?? [] as $property => $expectedValue) {
            $property = strtolower(trim((string) $property));
            $expectedValue = trim((string) $expectedValue);
            $actualValue = $actualDeclarations[$property] ?? null;
            if ($actualValue !== $expectedValue) {
                $checkErrors[] = $label . ' CSS rule "' . $selector . '" expected "' . $property . ': ' . $expectedValue . '" but found "' . ($actualValue ?? 'missing') . '" for ' . $url;
            }
        }
    }

    $statusSpecificNeedles = isset($check['must_contain_by_status'][$status]) && is_array($check['must_contain_by_status'][$status])
        ? $check['must_contain_by_status'][$status]
        : [];
    if (!$isInstallEntry) {
        foreach ($statusSpecificNeedles as $needle) {
            if (!str_contains($body, (string) $needle)) {
                $checkErrors[] = $label . ' did not contain expected text "' . (string) $needle . '" for HTTP ' . (string) $status . ' ' . $url;
            }
        }
    }

    foreach ($check['must_not_contain'] ?? [] as $needle) {
        if (str_contains($body, (string) $needle)) {
            $checkErrors[] = $label . ' contained forbidden text "' . (string) $needle . '" for ' . $url;
        }
    }

    foreach ($check['required_headers'] ?? [] as $headerName => $expectedValueParts) {
        $headerName = strtolower(trim((string) $headerName));
        $expectedValueParts = is_array($expectedValueParts) ? $expectedValueParts : [$expectedValueParts];
        $actualHeader = (string) ($headers[$headerName] ?? '');
        foreach ($expectedValueParts as $expectedValuePart) {
            $expectedValuePart = (string) $expectedValuePart;
            if ($actualHeader === '' || !str_contains($actualHeader, $expectedValuePart)) {
                $checkErrors[] = $label . ' missing required response header "' . $headerName . ': ' . $expectedValuePart . '" for ' . $url;
            }
        }
    }

    if (!$isInstallEntry && $status === 302 && isset($check['redirect_path_prefixes']) && is_array($check['redirect_path_prefixes'])) {
        $matchedRedirect = false;
        foreach ($check['redirect_path_prefixes'] as $prefix) {
            $prefix = (string) $prefix;
            if (str_starts_with($locationPath, $prefix) || ($basePath !== '' && str_starts_with($locationPath, $basePath . $prefix))) {
                $matchedRedirect = true;
                break;
            }
        }

        if (!$matchedRedirect) {
            $checkErrors[] = $label . ' redirected to unexpected location "' . $locationPath . '" for ' . $url;
        }
    }

    foreach ($check['must_not_expose'] ?? [] as $pattern) {
        if (preg_match('/' . preg_quote((string) $pattern, '/') . '/', $body) === 1) {
            $checkErrors[] = $label . ' exposed internal file content for ' . $url;
        }
    }

    if ($checkErrors === []) {
        echo '[ok] ' . $label . ' ' . $method . ' ' . $status . "\n";
    } else {
        echo '[fail] ' . $label . ' ' . $method . ' ' . $status . "\n";
        foreach ($checkErrors as $error) {
            $errors[] = $error;
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "saanraan HTTP smoke checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "saanraan HTTP smoke checks completed.\n";
