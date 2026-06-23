#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_module_status_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_module_status_extract_bundle_rows(string $content): array
{
    $rows = [];
    foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $content)) as $line) {
        if (!preg_match('/^\|\s*[^|\n]+\s*\|\s*`([a-z0-9_]+)`\s*\|\s*`([^`]+)`\s*\|(.+)\|$/', $line, $matches)) {
            continue;
        }

        $cells = array_map('trim', explode('|', trim($line, '|')));
        if (count($cells) !== 5) {
            sr_module_status_error('Module status row must have 5 cells: ' . $matches[1]);
            continue;
        }

        $rows[$matches[1]] = [
            'category' => $cells[0],
            'module' => trim($cells[1], '` '),
            'status' => trim($cells[2], '` '),
            'evidence' => $cells[3],
            'remaining' => $cells[4],
            'line' => $line,
        ];
    }

    return $rows;
}

$statusFile = 'docs/module-status.md';
$readmeFile = 'README.md';
$operatorFeatureFile = 'docs/operator-feature-list.md';
$improvementRecordFile = 'docs/records/improvement-hardening-verification-2026-06-11.md';
$content = is_file($statusFile) ? file_get_contents($statusFile) : false;
$readme = is_file($readmeFile) ? file_get_contents($readmeFile) : false;
$operatorFeature = is_file($operatorFeatureFile) ? file_get_contents($operatorFeatureFile) : false;
$improvementRecord = is_file($improvementRecordFile) ? file_get_contents($improvementRecordFile) : false;
if (!is_string($content)) {
    fwrite(STDERR, "module status checks failed:\n- docs/module-status.md is missing or unreadable.\n");
    exit(1);
}

$moduleKeys = [];
foreach (glob('modules/*/module.php') ?: [] as $moduleFile) {
    $moduleKeys[] = basename(dirname($moduleFile));
}
sort($moduleKeys, SORT_STRING);

foreach ($moduleKeys as $moduleKey) {
    if (!str_contains($content, '| `' . $moduleKey . '` |')) {
        sr_module_status_error('Module status row is missing for module: ' . $moduleKey);
    }
}

$bundleRows = sr_module_status_extract_bundle_rows($content);
$rowModuleKeys = array_keys($bundleRows);
sort($rowModuleKeys, SORT_STRING);
if ($rowModuleKeys !== $moduleKeys) {
    sr_module_status_error('Module status table rows must match module.php directories exactly. expected=' . implode(',', $moduleKeys) . ' actual=' . implode(',', $rowModuleKeys));
}

$allowedStatusValues = ['stable-candidate', 'beta', 'experimental'];
foreach ($bundleRows as $moduleKey => $row) {
    if (!in_array($row['status'], $allowedStatusValues, true)) {
        sr_module_status_error('Unknown bundled module status value for ' . $moduleKey . ': ' . $row['status']);
    }

    foreach (['category', 'evidence', 'remaining'] as $field) {
        if ($row[$field] === '' || $row[$field] === '-') {
            sr_module_status_error('Module status row ' . $moduleKey . ' has empty field: ' . $field);
        }
    }

    if ($row['status'] === 'stable-candidate' && !preg_match('/점검|smoke|Smoke|reconciliation|fixture/i', $row['evidence'])) {
        sr_module_status_error('stable-candidate module must cite concrete verification evidence: ' . $moduleKey);
    }

    if ($row['status'] === 'beta' && !preg_match('/smoke|Smoke|수동|reconciliation|동시성|브라우저|업로드|복구|회수|재시도|정리|개인정보/', $row['remaining'])) {
        sr_module_status_error('beta module must keep a concrete 1.0 reinforcement target: ' . $moduleKey);
    }
}

$expectedEvidenceMarkers = [
    'admin' => ['check-admin-action-security.php', 'check-admin-navigation-runtime.php', '메뉴/route runtime fixture'],
    'asset_ledger' => ['check-asset-reconciliation.php', 'reconciliation'],
    'point' => ['check-asset-reconciliation.php', 'check-asset-settlement-contract.php', '포인트 만료 dry-run fixture'],
    'reward' => ['check-asset-reconciliation.php', 'check-asset-settlement-contract.php'],
    'deposit' => ['check-asset-reconciliation.php', 'check-asset-settlement-contract.php'],
    'asset_exchange' => ['check-asset-exchange-logs.php', 'check-asset-exchange-runtime.php', '정정', 'rollback runtime fixture'],
    'coupon' => ['check-coupon-redemption-runtime.php', 'check-coupon-admin-validation.php', '유료 열람/다운로드 쿠폰 우선 적용 runtime fixture', '쿠폰 접근권 부분 실패 rollback fixture'],
    'site_menu' => ['check-site-menu-seed-order.php', '메뉴 렌더 runtime fixture', 'URL 안전성 fixture'],
    'logo_manager' => ['check-logo-manager-favicon.php', 'head link runtime fixture', '아이콘 세트'],
    'banner' => ['check-popup-layer-targets.php', '렌더 runtime fixture'],
    'popup_layer' => ['check-popup-layer-targets.php', 'check-ckeditor-assets.php', '렌더 runtime fixture', '임시 파일 cleanup fixture'],
    'seo' => ['check-seo-runtime.php', 'sitemap/robots runtime fixture'],
    'content' => ['check-paid-download-delivery.php', 'check-content-file-cleanup-runtime.php', 'check-content-copy-runtime.php', 'check-asset-idempotency.php', '파일/시리즈/임베드 삭제 정리 runtime fixture', '복사 runtime fixture', 'sanitizer fixture'],
    'community' => ['check-community-release.php', 'check-community-board-copy-job-lock.php', 'check-community-attachment-runtime.php', 'check-asset-idempotency.php', '유료 첨부 접근권 runtime fixture'],
    'quiz' => ['check-quiz-consistency.php', 'check-quiz-reward-runtime.php', 'check-quiz-delete-runtime.php', 'privacy runtime fixture', '보상 지급/원장 lookup/회수 가능액/회수 실행 runtime fixture', '관리자 보상 회수 화면/POST 계약', '삭제/source snapshot 정리 runtime fixture'],
    'survey' => ['check-survey-consistency.php', 'check-survey-response-runtime.php', 'check-survey-reward-runtime.php', 'check-survey-statistics-runtime.php', 'check-survey-export-runtime.php', '응답 제출 runtime fixture', 'CSV export runtime fixture', 'privacy runtime fixture', '보상 지급 runtime fixture', '통계 runtime fixture'],
    'embed_manager' => ['check-embed-manager-contracts.php', 'URL cache sync runtime fixture', 'private/broken 렌더링 fixture'],
    'ckeditor' => ['check-rich-text-sanitizer.php', 'check-htmlpurifier-runtime.php', 'check-ckeditor-assets.php', 'check-browser-qa.php', 'ckeditor-browser-smoke.spec.js', 'HTML Purifier', '캐시 경로', '브라우저 asset 로딩/fallback smoke'],
    'privacy' => ['check-privacy-contract-matrix.php', 'check-privacy-export-runtime.php', 'check-privacy-cleanup-runtime.php'],
    'notification' => ['check-mention-ux.php', 'check-notification-runtime.php', '이벤트 템플릿 runtime fixture', 'delivery queue fixture'],
];

foreach ($expectedEvidenceMarkers as $moduleKey => $markers) {
    if (!isset($bundleRows[$moduleKey])) {
        continue;
    }

    $evidence = (string) $bundleRows[$moduleKey]['evidence'];
    foreach ($markers as $marker) {
        if (!str_contains($evidence, $marker)) {
            sr_module_status_error('Module status evidence marker missing for ' . $moduleKey . ': ' . $marker);
        }
    }
}

$expectedRemainingMarkers = [
    'asset_ledger' => ['release-installed-gate-status.php --run-readonly', '설치 DB reconciliation 실행 기록'],
    'point' => ['release-installed-gate-status.php --run-readonly', '설치 DB 만료 dry-run'],
    'coupon' => ['release-installed-gate-status.php', '자산/쿠폰/유료 접근권 mutation smoke'],
    'community' => ['release-installed-gate-status.php', '인증 smoke', '자산/쿠폰/유료 접근권 mutation smoke'],
    'quiz' => ['release-installed-gate-status.php', '퀴즈 E2E smoke'],
    'privacy' => ['release-installed-gate-status.php', '개인정보 export/cleanup 설치 DB smoke'],
    'ckeditor' => ['release-installed-gate-status.php', 'CKEditor upload/save browser smoke'],
];

foreach ($expectedRemainingMarkers as $moduleKey => $markers) {
    if (!isset($bundleRows[$moduleKey])) {
        continue;
    }

    $remaining = (string) $bundleRows[$moduleKey]['remaining'];
    foreach ($markers as $marker) {
        if (!str_contains($remaining, $marker)) {
            sr_module_status_error('Module status remaining marker missing for ' . $moduleKey . ': ' . $marker);
        }
    }
}

if (is_string($improvementRecord)
    && preg_match_all('/^\| `([a-z0-9_]+)` \| `([^`]+)` \| `([^`]+)` \|/m', $improvementRecord, $matches, PREG_SET_ORDER) > 0
) {
    foreach ($matches as $match) {
        $moduleKey = (string) $match[1];
        $recordPreviousStatus = (string) $match[2];
        if (!isset($bundleRows[$moduleKey])) {
            continue;
        }

        if ($recordPreviousStatus !== $bundleRows[$moduleKey]['status']) {
            sr_module_status_error('Improvement record previous status does not match module-status.md for ' . $moduleKey . ': record=' . $recordPreviousStatus . ' module-status=' . $bundleRows[$moduleKey]['status']);
        }
    }
}

foreach (['identity-verification-plugin-plan.md', 'member-migration-plan.md', 'payment-plugin-plan.md'] as $planFile) {
    if (!str_contains($content, $planFile)) {
        sr_module_status_error('Planned status row is missing for plan: ' . $planFile);
    }
}

if (!str_contains($content, '.tools/bin/check-module-status.php')) {
    sr_module_status_error('Module status document must mention check-module-status.php.');
}

foreach ([
    '현재 증거',
    '1.0 전 보강 기준',
    '비어 있으면 안 된다',
    '구체적인 smoke',
] as $marker) {
    if (!str_contains($content, $marker)) {
        sr_module_status_error('Module status document must explain row evidence marker: ' . $marker);
    }
}

foreach ([
    'docs/module-status.md',
    'docs/verification-status.md',
    '상태 등급',
    '검증 증거 수준',
] as $marker) {
    if (is_string($readme) && !str_contains($readme, $marker)) {
        sr_module_status_error('README must expose module status/verification marker: ' . $marker);
    }
}

if (is_string($readme) && str_contains($readme, '| 분류 | 모듈 | 상태 |')) {
    sr_module_status_error('README bundle module table must not use 상태 for feature summary column.');
}

foreach ([
    'module-status.md',
    'verification-status.md',
    'risk-register.md',
    '운영 신뢰성을 보증하는 검증 기록은 아니다',
    'stable-candidate',
    'beta',
] as $marker) {
    if (is_string($operatorFeature) && !str_contains($operatorFeature, $marker)) {
        sr_module_status_error('Operator feature list must expose verification/status marker: ' . $marker);
    }
}

if (preg_match_all('/`([a-z-]+)`/', $content, $matches) > 0) {
    $allowedStatusValues = ['stable-candidate', 'beta', 'experimental', 'planned'];
    foreach ($matches[1] as $value) {
        if (in_array($value, $allowedStatusValues, true)) {
            continue;
        }
    }
}

$statusRowPattern = '/^\| [^|\n]+ \| `?[^|\n`]+`? \| `([^`]+)` \|/m';
if (preg_match_all($statusRowPattern, $content, $matches) > 0) {
    $allowedStatusValues = ['stable-candidate', 'beta', 'experimental', 'planned'];
    foreach ($matches[1] as $statusValue) {
        if (!in_array($statusValue, $allowedStatusValues, true)) {
            sr_module_status_error('Unknown module status value: ' . $statusValue);
        }
    }
}

if (!preg_match('/\| `stable-candidate` \|/', $content)) {
    sr_module_status_error('Module status document must define stable-candidate.');
}
if (!preg_match('/\| `beta` \|/', $content)) {
    sr_module_status_error('Module status document must define beta.');
}
if (!preg_match('/\| `planned` \|/', $content)) {
    sr_module_status_error('Module status document must define planned.');
}

if ($errors !== []) {
    fwrite(STDERR, "module status checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "module status checks completed.\n";
