#!/usr/bin/env php
<?php

declare(strict_types=1);

function toy_smoke_argument(array $argv, int $index, string $environmentKey): string
{
    $argument = (string) ($argv[$index] ?? '');
    if ($argument !== '') {
        return $argument;
    }

    $environmentValue = getenv($environmentKey);
    return is_string($environmentValue) ? $environmentValue : '';
}

$baseUrl = rtrim(toy_smoke_argument($argv, 1, 'TOY_SMOKE_BASE_URL'), '/');
if ($baseUrl === '' || !preg_match('#\Ahttps?://#', $baseUrl)) {
    fwrite(STDERR, "Usage: php .tools/bin/smoke-http.php http://127.0.0.1:8080\nEnv: TOY_SMOKE_BASE_URL\n");
    exit(2);
}

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
        'label' => 'community comment action auth guard',
        'method' => 'POST',
        'path' => '/community/comment',
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
        'label' => 'community admin boards entry',
        'path' => '/admin/community/boards',
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
        'path' => '/assets/toycore.css',
        'allowed_statuses' => [200],
        'must_contain' => ['body'],
    ],
    [
        'label' => 'database SQL protection',
        'path' => '/database/core/install.sql',
        'must_not_expose' => ['CREATE TABLE IF NOT EXISTS toy_site_settings'],
    ],
    [
        'label' => 'module SQL protection',
        'path' => '/modules/member/install.sql',
        'must_not_expose' => ['CREATE TABLE IF NOT EXISTS toy_member_accounts'],
    ],
    [
        'label' => 'community SQL protection',
        'path' => '/modules/community/install.sql',
        'must_not_expose' => ['CREATE TABLE IF NOT EXISTS toy_community_boards'],
    ],
    [
        'label' => 'community metadata protection',
        'path' => '/modules/community/module.php',
        'must_not_expose' => ["'name' => 'Community'"],
    ],
    [
        'label' => 'core PHP protection',
        'path' => '/core/helpers.php',
        'must_not_expose' => ['require_once TOY_ROOT'],
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
        'must_not_expose' => ['Minimal sample module for Toycore extension contracts.'],
    ],
    [
        'label' => 'agent instructions protection',
        'path' => '/AGENTS.md',
        'must_not_expose' => ['# AGENTS.md'],
    ],
    [
        'label' => 'readme protection',
        'path' => '/README.md',
        'must_not_expose' => ['# Toycore'],
    ],
    [
        'label' => 'tooling protection',
        'path' => '/.tools/bin/check.php',
        'must_not_expose' => ['toy_check_run'],
    ],
    [
        'label' => 'repository metadata protection',
        'path' => '/.git/HEAD',
        'must_not_expose' => ['ref: refs/'],
    ],
];

function toy_smoke_fetch(string $url, string $method): array
{
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'timeout' => 10,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'header' => "User-Agent: Toycore-Smoke-Check\r\n",
        ],
    ]);

    set_error_handler(static function (): bool {
        return true;
    });
    $body = file_get_contents($url, false, $context);
    restore_error_handler();
    $headers = $http_response_header ?? [];
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

function toy_smoke_location_path(string $location): string
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

$errors = [];
foreach ($checks as $check) {
    $url = $baseUrl . (string) $check['path'];
    $method = strtoupper((string) ($check['method'] ?? 'GET'));
    $response = toy_smoke_fetch($url, $method);
    $status = (int) $response['status'];
    $body = (string) $response['body'];
    $locationPath = toy_smoke_location_path((string) $response['location']);
    $label = (string) $check['label'];
    $checkErrors = [];

    if (isset($check['allowed_statuses']) && !in_array($status, $check['allowed_statuses'], true)) {
        $checkErrors[] = $label . ' returned unexpected status ' . $status . ' for ' . $url;
    }

    foreach ($check['must_contain'] ?? [] as $needle) {
        if (!str_contains($body, (string) $needle)) {
            $checkErrors[] = $label . ' did not contain expected text "' . (string) $needle . '" for ' . $url;
        }
    }

    $statusSpecificNeedles = isset($check['must_contain_by_status'][$status]) && is_array($check['must_contain_by_status'][$status])
        ? $check['must_contain_by_status'][$status]
        : [];
    foreach ($statusSpecificNeedles as $needle) {
        if (!str_contains($body, (string) $needle)) {
            $checkErrors[] = $label . ' did not contain expected text "' . (string) $needle . '" for HTTP ' . (string) $status . ' ' . $url;
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
    fwrite(STDERR, "toycore HTTP smoke checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "toycore HTTP smoke checks completed.\n";
