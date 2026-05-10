#!/usr/bin/env php
<?php

declare(strict_types=1);

$baseUrl = rtrim((string) ($argv[1] ?? ''), '/');
if ($baseUrl === '' || !preg_match('#\Ahttps?://#', $baseUrl)) {
    fwrite(STDERR, "Usage: php .tools/bin/smoke-http.php http://127.0.0.1:8080\n");
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
        'allowed_statuses' => [200, 302, 404],
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

function toy_smoke_fetch(string $url): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'ignore_errors' => true,
            'header' => "User-Agent: Toycore-Smoke-Check\r\n",
        ],
    ]);

    $body = file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $status = 0;
    foreach ($headers as $header) {
        if (preg_match('#\AHTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
            $status = (int) $matches[1];
        }
    }

    return [
        'status' => $status,
        'body' => is_string($body) ? $body : '',
    ];
}

$errors = [];
foreach ($checks as $check) {
    $url = $baseUrl . (string) $check['path'];
    $response = toy_smoke_fetch($url);
    $status = (int) $response['status'];
    $body = (string) $response['body'];
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

    foreach ($check['must_not_contain'] ?? [] as $needle) {
        if (str_contains($body, (string) $needle)) {
            $checkErrors[] = $label . ' contained forbidden text "' . (string) $needle . '" for ' . $url;
        }
    }

    foreach ($check['must_not_expose'] ?? [] as $pattern) {
        if (preg_match('/' . preg_quote((string) $pattern, '/') . '/', $body) === 1) {
            $checkErrors[] = $label . ' exposed internal file content for ' . $url;
        }
    }

    if ($checkErrors === []) {
        echo '[ok] ' . $label . ' ' . $status . "\n";
    } else {
        echo '[fail] ' . $label . ' ' . $status . "\n";
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
