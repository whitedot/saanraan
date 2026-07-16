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
    '마지막 `importantHelp` 인자를 명시적으로 켠 항목에만 `?`를 출력',
    '대표 필드나 섹션 제목 한 곳에만 `?`를 둔다',
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
    "~<input\\b(?=[^>]*\\bname=([\"'])account_id\\1)(?=[^>]*\\btype=([\"'])number\\2)(?=[^>]*\\bclass=([\"'])[^\"']*\\bfiltering-input\\b[^\"']*\\3)~" => '회원 필터 account_id는 숫자 전용 input 대신 공개 해시를 받을 수 있는 텍스트 입력을 쓰세요.',
    '/게시판 추가 입력 항목과 같은/' => '회원 문구를 게시판 추가 입력에 빗대지 마세요.',
    '/커뮤니티 추가 항목처럼/' => '회원 문구를 커뮤니티 추가 항목에 빗대지 마세요.',
    '/커뮤니티 대상과 동일한 방식/' => '대상 검증 실패를 다른 모듈 방식에 빗대지 마세요.',
    '/reference contract 기반/' => '운영자 문구에 구현 contract 표현을 쓰지 마세요.',
    '/계정 guard/' => '운영자 화면 문구에는 계정 guard 대신 작성 제한, 게시 보류처럼 동작을 설명하는 표현을 쓰세요.',
    '/publication hold|confirmed hold|confirmed 기반 hold|overlap 검토 기준|active auto_action/u' => '신고 자동조치와 작성 제한 문구에는 내부 상태명 대신 운영자용 한국어 표현을 쓰세요.',
    '/Bearer token|Slack provider|Discord provider|Telegram provider|webhook URL|dead-letter|Lock 만료|runner에서|다시 claim|HTTP API endpoint/u' => '알림 설정 문구에는 외부 서비스 설정값도 운영자용 한국어 표현을 먼저 쓰세요.',
    '/외부 provider|provider secret key|site key와 secret key|프로바이더 타임아웃|프로바이더 공통|산술 문제 fallback/u' => '자동등록방지 설정 문구에는 외부 provider 대신 외부 검사처럼 운영자용 한국어 표현을 쓰세요.',
    '/Mock provider|Mock 제공자|OAuth state|provider client id|provider client secret|provider 라벨|Scope 항목|Scope 추가|Claim path|Claim 선택/u' => '회원 OAuth 설정 문구에는 provider, scope, claim 같은 구현 용어보다 운영자용 한국어 표현을 먼저 쓰세요.',
    '/리액션 Preset|Preset 관리|Preset 추가|Preset 수정|리액션 preset|리액션 레코드|기존 레코드|사용 레코드|reaction key|source row/u' => '리액션 관리자 문구에는 preset/record 같은 내부 표현보다 묶음, 사용 기록, 리액션 키를 쓰세요.',
    '/리워드 로그|작성자 리워드|게시자 리워드|퀴즈 리워드|설문 리워드/u' => '관리자 보상 화면 문구에는 리워드 대신 보상을 쓰세요.',
    '/알림 delivery|delivery 처리 수|delivery 작업|Dead-letter|Lock 만료|실패 map/u' => '관리자 운영 문구에는 delivery/lock/map 같은 내부 표현보다 발송, 작업 잠금, 항목 같은 한국어 표현을 쓰세요.',
    '/이메일 발송 작업/u' => '알림 발송 작업 화면은 이메일 전용이 아니므로 공용 발송 작업 문구를 쓰세요.',
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

$managementColumnFiles = [
    'modules/admin/helpers/comment-extra-fields.php',
    'modules/asset_exchange/views/admin-asset-exchange-logs.php',
    'modules/asset_ledger/views/admin-recovery-failures.php',
    'modules/community/helpers/admin-post-extra-fields.php',
    'modules/community/views/admin-boards.php',
    'modules/community/views/admin-reports.php',
    'modules/content/views/admin-content-groups.php',
    'modules/member/views/admin-settings.php',
    'modules/notification/views/account-notifications.php',
    'modules/notification/views/admin-admin-notifications.php',
    'modules/quiz/actions/admin-groups.php',
    'modules/survey/actions/admin-groups.php',
];
foreach ($managementColumnFiles as $relativePath) {
    $content = file_get_contents($root . '/' . $relativePath);
    if (!is_string($content)) {
        $errors[] = '관리 열 문구 검사 파일을 읽을 수 없습니다: ' . $relativePath;
        continue;
    }
    if (str_contains($content, '>작업</th>')) {
        $errors[] = '버튼과 링크를 담는 목록 열은 작업 대신 관리로 표시해야 합니다: ' . $relativePath;
    }
    if (!str_contains($content, '>관리</th>')) {
        $errors[] = '목록 관리 열 표기가 빠졌습니다: ' . $relativePath;
    }
}

$policyDocumentLang = file_get_contents($root . '/modules/policy_documents/lang/ko.php');
if (!is_string($policyDocumentLang) || !str_contains($policyDocumentLang, "'ui.action' => '관리'")) {
    $errors[] = '정책 문서 목록의 버튼 열은 관리로 표시해야 합니다.';
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

$formHelperPath = $root . '/modules/admin/helpers/forms.php';
$formHelper = is_file($formHelperPath) ? file_get_contents($formHelperPath) : false;
if (!is_string($formHelper)) {
    $errors[] = '관리자 폼 라벨 helper를 읽을 수 없습니다.';
} else {
    foreach ([
        'bool $importantHelp = false',
        'if (!$importantHelp)',
        'return \'<label class="form-label"',
    ] as $marker) {
        if (!str_contains($formHelper, $marker)) {
            $errors[] = '일반 라벨이 기본이고 중요한 도움말만 opt-in하는 계약이 빠졌습니다: ' . $marker;
        }
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . "\n");
    }
    exit(1);
}

echo "admin UX writing checks completed.\n";
