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
    fwrite(STDERR, "Usage: php .tools/bin/smoke-http.php http://127.0.0.1:8080\nEnv: SR_SMOKE_BASE_URL SR_SMOKE_EXPECT_COMMUNITY=1\n");
    exit(2);
}
$expectCommunity = getenv('SR_SMOKE_EXPECT_COMMUNITY') === '1';

$checks = [
    [
        'label' => 'home or install entry',
        'path' => '/',
        'allowed_statuses' => [200, 302],
        'must_not_contain' => ['Fatal error', 'Stack trace'],
    ],
    [
        'label' => 'login route',
        'path' => '/login',
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
        'label' => 'admin content link refs entry',
        'path' => '/admin/content/link-refs',
        'allowed_statuses' => [200, 302, 403, 404],
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
        'label' => 'community admin link refs entry',
        'path' => '/admin/community/link-refs',
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
        'label' => 'stylesheet',
        'path' => '/assets/saanraan.css',
        'allowed_statuses' => [200],
        'must_contain' => ['body'],
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
        'label' => 'config directory protection',
        'path' => '/config/.gitignore',
        'must_not_expose' => ['config-*.tmp.php'],
    ],
    [
        'label' => 'storage directory protection',
        'path' => '/storage/.gitignore',
        'must_not_expose' => ['!.gitignore'],
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
    foreach ($headers as $header) {
        if (preg_match('#\AHTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
            $status = (int) $matches[1];
        }
        if (preg_match('#\ALocation:\s*(.+)\z#i', $header, $matches) === 1) {
            $location = trim($matches[1]);
        }
    }

    return [
        'status' => $status,
        'body' => is_string($body) ? $body : '',
        'location' => $location,
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
        && str_contains($body, '<title>Saanraan 설치</title>')
        && str_contains($body, 'Saanraan 실행에 필요한 DB 연결');
}

function sr_smoke_is_install_csrf_error(int $status, string $body): bool
{
    return $status === 400
        && str_contains($body, '<title>400</title>')
        && str_contains($body, '요청 보안 토큰이 올바르지 않습니다.');
}

$errors = [];
$isInstallMode = false;
foreach ($checks as $check) {
    $url = $baseUrl . (string) $check['path'];
    $method = strtoupper((string) ($check['method'] ?? 'GET'));
    $response = sr_smoke_fetch($url, $method, (string) ($check['body'] ?? ''));
    $status = (int) $response['status'];
    $body = (string) $response['body'];
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

    foreach ($check['must_contain'] ?? [] as $needle) {
        if (!str_contains($body, (string) $needle)) {
            $checkErrors[] = $label . ' did not contain expected text "' . (string) $needle . '" for ' . $url;
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

    if ($status === 302 && isset($check['redirect_path_prefixes']) && is_array($check['redirect_path_prefixes'])) {
        $matchedRedirect = false;
        foreach ($check['redirect_path_prefixes'] as $prefix) {
            if (str_starts_with($locationPath, (string) $prefix)) {
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
