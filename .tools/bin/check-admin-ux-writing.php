#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

$requiredGuidePhrases = [
    '## 관리자 안내 문구',
    'form-help',
    '$adminPageSubtitle',
    'sr_admin_feedback_toasts($notice, $errors)',
    'placeholder는 안내 문구의 대체물이 아니다',
    '한 모듈의 화면에서 다른 모듈의 기능을 비유로 들어 설명하지 않는다',
    '민감하거나 운영자 판단에 필요하지 않은 값은 토스트, alert, 도움말에 노출하지 않는다',
];

$guidePath = $root . '/docs/admin-ui-guide.md';
$guide = is_file($guidePath) ? file_get_contents($guidePath) : false;
if (!is_string($guide)) {
    $errors[] = 'docs/admin-ui-guide.md를 읽을 수 없습니다.';
} else {
    foreach ($requiredGuidePhrases as $phrase) {
        if (!str_contains($guide, $phrase)) {
            $errors[] = '관리자 UI 가이드에 UX writing 기준이 빠졌습니다: ' . $phrase;
        }
    }
}

$blockedPatterns = [
    '/admin-form-help/' => '관리자 힌트 표준 class는 form-help입니다.',
    '/회원 ID|회원 번호|회원번호/' => '운영자 화면 문구에는 회원 ID/회원 번호 대신 회원, 공개 해시 등 문맥에 맞는 표현을 쓰세요.',
    '/게시판 추가 입력 항목과 같은/' => '회원 문구를 게시판 추가 입력에 빗대지 마세요.',
    '/커뮤니티 추가 항목처럼/' => '회원 문구를 커뮤니티 추가 항목에 빗대지 마세요.',
    '/커뮤니티 대상과 동일한 방식/' => '대상 검증 실패를 다른 모듈 방식에 빗대지 마세요.',
    '/reference contract 기반/' => '운영자 문구에 구현 contract 표현을 쓰지 마세요.',
];

$scanFiles = ['docs/admin-ui-guide.md', 'modules/admin/helpers/action-results.php'];
$moduleRoot = $root . '/modules';
foreach (glob($moduleRoot . '/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
    foreach (['views', 'actions', 'helpers'] as $subdir) {
        $scanRoot = $moduleDir . '/' . $subdir;
        if (!is_dir($scanRoot)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'php') {
                $scanFiles[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }
    }

    $langFile = $moduleDir . '/lang/ko.php';
    if (is_file($langFile)) {
        $scanFiles[] = substr($langFile, strlen($root) + 1);
    }
}

$scanFiles = array_values(array_unique($scanFiles));

foreach ($scanFiles as $relativePath) {
    $path = $root . '/' . $relativePath;
    if (!is_file($path)) {
        continue;
    }

    $content = file_get_contents($path);
    if (!is_string($content)) {
        $errors[] = '파일을 읽을 수 없습니다: ' . $relativePath;
        continue;
    }

    foreach ($blockedPatterns as $pattern => $message) {
        $scanContent = $content;
        if ($pattern === '/회원 ID|회원 번호|회원번호/') {
            $scanContent = preg_replace('/\splaceholder=(["\']).*?\1/s', '', $scanContent) ?? $scanContent;
        }
        if (preg_match($pattern, $scanContent) === 1) {
            $errors[] = $message . ' file=' . $relativePath;
        }
    }
}

$toastHelperPath = $root . '/modules/admin/helpers/action-results.php';
$toastHelper = is_file($toastHelperPath) ? file_get_contents($toastHelperPath) : false;
if (is_string($toastHelper)) {
    foreach (['alert-removable', 'alert-success', 'alert-danger'] as $marker) {
        if (!str_contains($toastHelper, $marker)) {
            $errors[] = '관리자 토스트가 UI kit alert class를 포함해야 합니다: ' . $marker;
        }
    }
} else {
    $errors[] = '관리자 토스트 helper를 읽을 수 없습니다.';
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . "\n");
    }
    exit(1);
}

echo "admin UX writing checks completed.\n";
