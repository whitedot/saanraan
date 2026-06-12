#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_verification_template_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

$templateFile = 'docs/release-verification-template.md';
$content = is_file($templateFile) ? file_get_contents($templateFile) : false;
if (!is_string($content)) {
    fwrite(STDERR, "verification template checks failed:\n- docs/release-verification-template.md is missing or unreadable.\n");
    exit(1);
}

foreach ([
    '## 대상',
    '## 범위',
    '## 정적 점검',
    '## 릴리스 후보 필수 설치 DB 게이트',
    '## HTTP Smoke',
    '## 인증 Smoke',
    '## 브라우저/수동 점검',
    '## 실패와 제한',
    '## 리스크별 릴리스 판정 연결',
    '## 모듈 상태 영향',
    '## 판정',
] as $section) {
    if (!str_contains($content, $section)) {
        sr_verification_template_error('Verification template section is missing: ' . $section);
    }
}

foreach ([
    'git diff --check',
    'php .tools/bin/check.php',
    'php .tools/bin/check-rich-text-sanitizer.php',
    'php .tools/bin/reconcile-assets.php',
    'php .tools/bin/ops-status.php',
    'php .tools/bin/check-tool-gate-coverage.php',
    'php .tools/bin/check-release-verification-records.php',
    'php .tools/bin/check-doc-links.php',
    'php .tools/bin/check-module-status.php',
    'php .tools/bin/check-risk-register.php',
    'php .tools/bin/check-privacy-export-runtime.php',
    'php .tools/bin/check-privacy-cleanup-runtime.php',
    'php .tools/bin/check-admin-pagination-runtime.php',
    'php .tools/bin/check-htmlpurifier-vendor-integrity.php',
    'php .tools/bin/check-browser-qa.php',
    'php .tools/bin/check-release-package-policy.php',
    'php .tools/bin/release-preflight.php',
    'php .tools/bin/release-package-dry-run.php',
    'php .tools/bin/release-package-dry-run.php --manifest',
    'php .tools/bin/smoke-asset-idempotency-http.php',
    'php .tools/bin/smoke-http.php',
    'php .tools/bin/smoke-community-auth.php',
    'php .tools/bin/smoke-quiz-e2e.php',
    'npm --prefix .tools/browser-qa run test:ckeditor',
] as $command) {
    if (!str_contains($content, $command)) {
        sr_verification_template_error('Verification template command is missing: ' . $command);
    }
}

foreach ([
    '미설치 install-mode smoke만으로 통과 처리하지 않는다',
    '조건부 통과',
    '판정 보류',
    '새 설치 또는 업데이트 적용',
    '/admin/assets/reconciliation',
    '/admin/operations',
    '자산/쿠폰/유료 접근권 mutation smoke',
    '퀴즈 E2E smoke',
    'SR_SMOKE_ALLOW_MUTATION=1',
    '개인정보 export/cleanup smoke',
    'CKEditor asset/fallback browser smoke',
    'CKEditor upload/save browser smoke',
    '성능 수동 점검',
    '새 `check-*.php` 도구의 통합 게이트 연결 확인',
    '검증 기록 section, 최종 판정, 설치 DB 게이트 판정 규칙 확인',
    '문서 링크와 `.tools/bin/*.php` 명령 참조 존재 확인',
    '번들 모듈 상태표와 module.php 일치',
    '리스크 등록부 상태, 증거, 후속 기준 확인',
    'quiz/survey/content/community 개인정보 export 계약의 fixture 기반 상세 답변, snapshot, 활동 데이터 포함과 타 계정 제외 확인',
    'quiz/survey/content/community 개인정보 cleanup 계약의 fixture 기반 익명화와 결과 count 확인',
    '관리자 페이지네이션 helper의 clamp, offset, URL, HTML 상태 확인',
    'HTML Purifier vendor 버전, source reference, 라이선스, 런타임 클래스 버전 대조',
    'Playwright 하니스, CKEditor 브라우저 asset/fallback smoke spec, JS 문법 확인',
    '릴리스 후보 필수 설치 DB 게이트 미실행 여부',
    '프로젝트 리스크 레지스터',
    'open` 또는 `mitigating` 항목이 실행 증거 없이 남아 있으면',
] as $marker) {
    if (!str_contains($content, $marker)) {
        sr_verification_template_error('Verification template release candidate gate marker is missing: ' . $marker);
    }
}

$riskGateMarkers = [
    'R-01 자산/쿠폰/유료 접근권' => ['reconciliation', 'mutation smoke', '동시성 fixture', '관리자 정정/복구 확인'],
    'R-02 HTML sanitizer/CKEditor' => ['sanitizer fixture', 'Purifier 로드 상태', 'fallback sanitizer fixture', 'check-browser-qa.php', 'ckeditor-browser-smoke.spec.js', 'CKEditor asset/fallback browser smoke', 'CKEditor upload/save browser smoke'],
    'R-03 공유호스팅 queue/cron/배치' => ['ops-status.php', '/admin/operations', '지연/실패 row 확인'],
    'R-04 개인정보 export/cleanup 계약' => ['privacy matrix', 'export/cleanup smoke', '운영 보존 데이터 검토'],
    'R-05 넓은 번들 모듈 표면' => ['module status', 'beta smoke 기록', '등급 상향 근거'],
    'R-06 커스텀 요청/보안 contract' => ['security baseline', 'admin action security', '인증/권한 smoke', '보안 헤더'],
    'R-07 외부 의존성/vendored asset' => ['dependency policy', 'modules/htmlpurifier/DEPENDENCY.md', 'vendor integrity', 'release package dry-run', 'dry-run manifest', 'Purifier 로드 상태', 'fallback sanitizer fixture'],
    'R-08 배포 보호' => ['deployment protection', 'HTTP 보호 경로 smoke', 'Apache/nginx 확인'],
    'R-09 문서/Wiki 지연' => ['README', '구현 스냅샷', '릴리스 절차', 'Wiki 갱신 여부'],
    'R-10 국내 CMS 대비 신뢰 증거' => ['positioning', '릴리스 검증 기록 누적', '사용 판단 기준'],
    'R-11 성능/캐시 기준' => ['performance baseline', '느린 화면 수동 점검', '실행 계획/인덱스 검토'],
];

foreach ($riskGateMarkers as $riskMarker => $markers) {
    if (!str_contains($content, $riskMarker)) {
        sr_verification_template_error('Verification template risk gate row is missing: ' . $riskMarker);
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($content, $marker)) {
            sr_verification_template_error('Verification template risk gate marker is missing for ' . $riskMarker . ': ' . $marker);
        }
    }
}

foreach (['회귀', '환경 미준비', '기존 보완 항목', '미실행'] as $classification) {
    if (!str_contains($content, $classification)) {
        sr_verification_template_error('Verification template failure classification is missing: ' . $classification);
    }
}

foreach (['통과', '조건부 통과', '실패', '판정 보류'] as $verdict) {
    if (!str_contains($content, $verdict)) {
        sr_verification_template_error('Verification template verdict option is missing: ' . $verdict);
    }
}

foreach ([
    '[검증 상태와 증거 기준](verification-status.md)',
    '[릴리스 검증 기록 템플릿](release-verification-template.md)',
] as $link) {
    $docsReadme = file_get_contents('docs/README.md');
    $verificationStatus = file_get_contents('docs/verification-status.md');
    $haystack = (is_string($docsReadme) ? $docsReadme : '') . "\n" . (is_string($verificationStatus) ? $verificationStatus : '');
    if (!str_contains($haystack, $link)) {
        sr_verification_template_error('Verification template docs link is missing: ' . $link);
    }
}

$verificationStatus = file_get_contents('docs/verification-status.md');
if (is_string($verificationStatus)) {
    foreach ([
        '릴리스 후보 필수 설치 DB 게이트',
        '리스크별 릴리스 판정 연결 결과',
        '미설치 install-mode smoke만으로 통과 처리하지 않는다',
        'check-release-verification-records.php',
        'release-installed-gate-status.php --run-readonly',
        'smoke-asset-idempotency-http.php',
        'dedupe row count',
        'CKEditor 서버 업로드 action',
        '실행 계획/인덱스 상태',
        '조건부 통과',
        '판정 보류',
    ] as $marker) {
        if (!str_contains($verificationStatus, $marker)) {
            sr_verification_template_error('Verification status document is missing release candidate gate marker: ' . $marker);
        }
    }
}

$improvementRecord = file_get_contents('docs/records/improvement-hardening-verification-2026-06-11.md');
if (is_string($improvementRecord)) {
    foreach ([
        '## 리스크별 릴리스 판정 연결',
        '이번 기록은 1.0 릴리스 후보 판정이 아니라',
        '대상 범위',
        '단일 hash 대신 각 작업 단위 commit',
        '포트는 실행 시점별 가용 포트',
        'SR_SMOKE_BASE_URL=http://127.0.0.1:<port>',
        'R-01 자산/쿠폰/유료 접근권',
        'R-02 HTML sanitizer/CKEditor',
        'R-03 공유호스팅 queue/cron/배치',
        'R-04 개인정보 export/cleanup 계약',
        'R-05 넓은 번들 모듈 표면',
        'R-06 커스텀 요청/보안 contract',
        'R-07 외부 의존성/vendored asset',
        'R-08 배포 보호',
        'R-09 문서/Wiki 지연',
        'R-10 국내 CMS 대비 신뢰 증거',
        'R-11 성능/캐시 기준',
        '조건부',
        '설치 DB',
        'release-preflight.php',
    ] as $marker) {
        if (!str_contains($improvementRecord, $marker)) {
            sr_verification_template_error('Improvement hardening verification record is missing marker: ' . $marker);
        }
    }
} else {
    sr_verification_template_error('Improvement hardening verification record is missing or unreadable.');
}

if ($errors !== []) {
    fwrite(STDERR, "verification template checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "verification template checks completed.\n";
