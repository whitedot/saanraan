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
    '/게시판 추가 입력 항목과 같은/' => '회원 문구를 게시판 추가 입력에 빗대지 마세요.',
    '/커뮤니티 추가 항목처럼/' => '회원 문구를 커뮤니티 추가 항목에 빗대지 마세요.',
    '/커뮤니티 대상과 동일한 방식/' => '대상 검증 실패를 다른 모듈 방식에 빗대지 마세요.',
    '/reference contract 기반/' => '운영자 문구에 구현 contract 표현을 쓰지 마세요.',
];

$scanFiles = [
    'docs/admin-ui-guide.md',
    'modules/admin/helpers/action-results.php',
    'modules/member/lang/ko.php',
    'modules/member/views/admin-settings.php',
    'modules/member/views/admin-members.php',
    'modules/coupon/views/admin-coupons.php',
    'modules/banner/views/admin-banners.php',
    'modules/popup_layer/views/admin-popup-layers.php',
    'modules/community/views/admin-settings.php',
    'modules/community/views/admin-boards.php',
];

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
        if (preg_match($pattern, $content) === 1) {
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
