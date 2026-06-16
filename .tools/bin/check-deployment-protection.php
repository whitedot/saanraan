#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_deployment_protection_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_deployment_protection_read(string $file): string
{
    if (!is_file($file)) {
        sr_deployment_protection_error('Required deployment protection file is missing: ' . $file);
        return '';
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        sr_deployment_protection_error('Required deployment protection file cannot be read: ' . $file);
        return '';
    }

    return $contents;
}

$doc = sr_deployment_protection_read('docs/deployment-protection.md');
$apache = sr_deployment_protection_read('.htaccess');
$nginx = sr_deployment_protection_read('docs/deployment/nginx-saanraan.conf');
$smoke = sr_deployment_protection_read('.tools/bin/smoke-http.php');
$devRouter = sr_deployment_protection_read('.tools/bin/dev-router.php');
$risk = sr_deployment_protection_read('docs/risk-register.md');
$verification = sr_deployment_protection_read('docs/verification-status.md');
$smokeDoc = sr_deployment_protection_read('docs/smoke-test.md');

$protectedDirectories = [
    'config' => ['config'],
    'core' => ['core'],
    'database' => ['database'],
    'docs' => ['docs'],
    'examples' => ['examples'],
    'storage' => ['storage'],
    'modules' => ['modules'],
    '.git' => ['.git', '\.git'],
    '.tools' => ['.tools', '\.tools'],
    '.claude' => ['.claude', '\.claude'],
];

foreach ($protectedDirectories as $directoryLabel => $markers) {
    foreach ([
        'docs/deployment-protection.md' => $doc,
        '.htaccess' => $apache,
        'docs/deployment/nginx-saanraan.conf' => $nginx,
    ] as $file => $contents) {
        $found = false;
        foreach ($markers as $marker) {
            if ($contents !== '' && str_contains($contents, $marker)) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            sr_deployment_protection_error($file . ' is missing protected directory marker: ' . $directoryLabel);
        }
    }
}

foreach ([
    'AGENTS.md' => ['AGENTS.md', 'AGENTS\.md'],
    'README.md' => ['README.md', 'README\.md'],
    'LICENSE' => ['LICENSE'],
    '.gitignore' => ['.gitignore', '\.gitignore'],
    '.htaccess' => ['.htaccess', '\.htaccess'],
    '.env' => ['.env', '\.env'],
    '.env.*' => ['.env.*', '\.env(?:\..*)?'],
] as $rootFile => $markers) {
    foreach ([
        'docs/deployment-protection.md' => $doc,
        '.htaccess' => $apache,
        'docs/deployment/nginx-saanraan.conf' => $nginx,
    ] as $file => $contents) {
        $found = false;
        foreach ($markers as $marker) {
            if ($contents !== '' && str_contains($contents, $marker)) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            sr_deployment_protection_error($file . ' is missing protected root file marker: ' . $rootFile);
        }
    }
}

foreach ([
    '/database/core/install.sql',
    '/modules/member/install.sql',
    '/modules/community/install.sql',
    '/modules/community/module.php',
    '/core/helpers.php',
    '/config/.gitignore',
    '/config/config.php',
    '/storage/.gitignore',
    '/storage/installed.lock',
    '/docs/deployment-protection.md',
    '/examples/sample_module/module.php',
    '/AGENTS.md',
    '/README.md',
    '/.tools/bin/check.php',
    '/.git/HEAD',
    '/.env.local',
] as $path) {
    if ($smoke !== '' && !str_contains($smoke, "'" . $path . "'") && !str_contains($smoke, '"' . $path . '"')) {
        sr_deployment_protection_error('HTTP smoke is missing protected path: ' . $path);
    }
}

foreach ([
    'config|core|database|docs|examples|storage',
    '\.git|\.tools|\.claude',
    'modules/[a-z][a-z0-9_]{1,39}/assets/',
    'modules/[a-z][a-z0-9_]{1,39}/skins/[a-z][a-z0-9_]{0,39}/',
    'modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js',
    'AGENTS\.md|README\.md|LICENSE|\.gitignore|\.htaccess|\.env',
] as $marker) {
    if ($devRouter !== '' && !str_contains($devRouter, $marker)) {
        sr_deployment_protection_error('dev-router is missing protected path marker: ' . $marker);
    }
}

foreach ([
    '/assets/reset.css',
    '/assets/theme.css',
    '/assets/layout.css',
    '/assets/module.css',
    '/assets/ui-kit.css',
    '/assets/ui-kit-layout.css',
    '/assets/public-layout.js',
    '/assets/fonts/material-symbols-outlined.ttf',
    '/modules/admin/assets/tokens.css',
    '/modules/content/assets/reset.css',
    '/modules/content/assets/layout.css',
    '/modules/content/assets/module.css',
    '/modules/content/assets/layout.js',
    '/modules/community/assets/reset.css',
    '/modules/community/assets/layout.css',
    '/modules/community/assets/module.css',
    '/modules/community/assets/layout.js',
    '/modules/community/skins/compact/skin.css',
    '/modules/quiz/assets/layout.css',
    '/modules/quiz/assets/layout.js',
    '/modules/survey/assets/layout.css',
    '/modules/survey/assets/layout.js',
    '/modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js',
] as $path) {
    if ($doc !== '' && !str_contains($doc, $path)) {
        sr_deployment_protection_error('docs/deployment-protection.md is missing public asset marker: ' . $path);
    }
}

foreach ([
    'location /assets/',
    'location ~ ^/modules/[a-z][a-z0-9_]{1,39}/assets/',
    'location ~ ^/modules/[a-z][a-z0-9_]{1,39}/skins/[a-z][a-z0-9_]{0,39}/',
    'location = /modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js',
    'location = /assets/fonts/material-symbols-outlined.ttf',
] as $marker) {
    if ($nginx !== '' && !str_contains($nginx, $marker)) {
        sr_deployment_protection_error('nginx sample is missing public asset location marker: ' . $marker);
    }
}

foreach ([
    'deployment-protection.md',
    'HTTP smoke',
    '실제 Apache/nginx 배포',
    'check-deployment-protection.php',
] as $marker) {
    if ($risk !== '' && !str_contains($risk, $marker)) {
        sr_deployment_protection_error('Risk register is missing deployment protection marker: ' . $marker);
    }
}

foreach ([
    '배포 보호',
    'HTTP smoke',
    'check-deployment-protection.php',
    'check-deployment-config.php',
    'config-mode',
    'config-owner-group',
] as $marker) {
    if ($verification !== '' && !str_contains($verification, $marker)) {
        sr_deployment_protection_error('Verification status is missing deployment protection marker: ' . $marker);
    }
}

foreach ([
    '/config/config.php 직접 접근',
    '/storage/installed.lock 직접 접근',
    '개발용 router도 운영 배포 규칙',
    '보호 경로를 직접 403',
] as $marker) {
    if ($smokeDoc !== '' && !str_contains($smokeDoc, $marker)) {
        sr_deployment_protection_error('Smoke test document is missing deployment protection marker: ' . $marker);
    }
}

if ($errors !== []) {
    fwrite(STDERR, "deployment protection checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "deployment protection checks completed.\n";
