#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_risk_register_check_contains(string $file, array $markers, array &$errors): void
{
    if (!is_file($file)) {
        $errors[] = 'Required risk register document is missing: ' . $file;
        return;
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        $errors[] = 'Required risk register document cannot be read: ' . $file;
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $errors[] = 'Risk register marker missing in ' . $file . ': ' . $marker;
        }
    }
}

function sr_risk_register_read(string $file, array &$errors): string
{
    if (!is_file($file)) {
        $errors[] = 'Required risk register document is missing: ' . $file;
        return '';
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        $errors[] = 'Required risk register document cannot be read: ' . $file;
        return '';
    }

    return $contents;
}

function sr_risk_register_extract_rows(string $contents, array &$errors): array
{
    $rows = [];
    foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $contents)) as $line) {
        if (!preg_match('/^\|\s*(R-\d{2})\s*\|(.+)\|$/', $line, $matches)) {
            continue;
        }

        $cells = array_map('trim', explode('|', trim($line, '|')));
        if (count($cells) !== 6) {
            $errors[] = 'Risk register row must have 6 cells: ' . $matches[1];
            continue;
        }

        $rows[$matches[1]] = [
            'area' => $cells[1],
            'status' => trim($cells[2], '` '),
            'risk' => $cells[3],
            'evidence' => $cells[4],
            'remaining' => $cells[5],
            'line' => $line,
        ];
    }

    return $rows;
}

function sr_risk_register_check_row_markers(array $row, string $id, array $markers, array &$errors): void
{
    $haystack = $row['evidence'] . "\n" . $row['remaining'];
    foreach ($markers as $marker) {
        if (!str_contains($haystack, $marker)) {
            $errors[] = 'Risk register row ' . $id . ' is missing marker: ' . $marker;
        }
    }
}

sr_risk_register_check_contains('docs/risk-register.md', [
    'R-01',
    'R-02',
    'R-03',
    'R-04',
    'R-05',
    'R-06',
    'R-07',
    'R-08',
    'R-09',
    'R-10',
    'R-11',
    'open',
    'mitigating',
    'watch',
    'accepted-for-1.0',
    'verification-status.md',
    'module-status.md',
    'operational-status.md',
    'dependency-policy.md',
    'positioning.md',
    'php .tools/bin/check.php',
    '.tools/bin/reconcile-assets.php',
    '.tools/bin/ops-status.php',
], $errors);

$riskRegister = sr_risk_register_read('docs/risk-register.md', $errors);
$rows = sr_risk_register_extract_rows($riskRegister, $errors);
$expectedRows = [
    'R-01' => ['mitigating', [
        'verification-status.md',
        'smoke-test.md',
        'operational-status.md',
        '자산 불일치 대응 절차',
        '.tools/bin/reconcile-assets.php',
        '/admin/assets/reconciliation',
        '.tools/bin/check-asset-reconciliation.php',
        '.tools/bin/check-asset-idempotency.php',
        '.tools/bin/check-asset-deadlock-retry.php',
        '.tools/bin/check-paid-download-delivery.php',
        '.tools/bin/check-asset-exchange-runtime.php',
        '.tools/bin/check-asset-settlement-contract.php',
        '.tools/bin/check-coupon-redemption-runtime.php',
        '쿠폰 접근권 부분 실패 rollback fixture',
        'pcntl',
        '병렬 HTTP 동시 제출 fixture',
        '정정/복구 절차 smoke 기록',
    ]],
    'R-02' => ['mitigating', [
        'security-model.md',
        'rich-text-sanitizer-policy.md',
        'dependency-policy.md',
        '.tools/bin/check-rich-text-sanitizer.php',
        '.tools/bin/check-htmlpurifier-runtime.php',
        '.tools/bin/check-htmlpurifier-vendor-integrity.php',
        '.tools/bin/check-rich-text-sanitizer-policy.php',
        '.tools/bin/check-ckeditor-assets.php',
        '.tools/bin/check-browser-qa.php',
        'ckeditor-browser-smoke.spec.js',
        '브라우저 asset 로딩',
        'CKEditor',
        '저장 HTML sanitizer',
    ]],
    'R-03' => ['accepted-for-1.0', [
        'operational-status.md',
        'performance-policy.md',
        '.tools/bin/ops-status.php',
        '/admin/operations',
        '.tools/bin/check-operational-status.php',
        '설치 DB 또는 staging',
    ]],
    'R-04' => ['mitigating', [
        'module-guide.md',
        'security-model.md',
        'privacy-contract-matrix.md',
        '.tools/bin/check-privacy-contract-matrix.php',
        '.tools/bin/check-privacy-export-runtime.php',
        '.tools/bin/check-privacy-cleanup-runtime.php',
        'export_retained',
        '고위험 필드',
        '보존기간 정책',
        '릴리스 차단',
    ]],
    'R-05' => ['mitigating', [
        'module-status.md',
        'verification-status.md',
        'contribution-guide.md',
        '`beta`',
        '상태 등급 상향 근거',
    ]],
    'R-06' => ['watch', [
        'security-model.md',
        'security-baseline-evidence.md',
        '.tools/bin/check-security-baseline.php',
        '.tools/bin/check-admin-action-security.php',
        '.tools/bin/check-auth-runtime.php',
        '보안 헤더',
    ]],
    'R-07' => ['watch', [
        'dependency-policy.md',
        'modules/htmlpurifier/DEPENDENCY.md',
        '.tools/bin/check-dependency-policy.php',
        '.tools/bin/check-htmlpurifier-vendor-integrity.php',
        '.tools/bin/check-htmlpurifier-runtime.php',
        '.tools/bin/check-ckeditor-assets.php',
        '.tools/bin/check-release-package-policy.php',
        '.tools/bin/release-preflight.php',
        '.tools/bin/release-package-dry-run.php',
        'dry-run manifest',
        'Purifier 로드 상태',
        'CKEditor asset',
    ]],
    'R-08' => ['watch', [
        'deployment-protection.md',
        'smoke-test.md',
        'HTTP smoke 보호 경로 검사',
        '.tools/bin/check-deployment-protection.php',
        '.tools/bin/check-deployment-config.php',
        'Apache/nginx',
    ]],
    'R-09' => ['accepted-for-1.0', [
        'README.md',
        'implementation-snapshot.md',
        'release-process.md',
        'Wiki DB/관리자/요청 흐름',
    ]],
    'R-10' => ['mitigating', [
        'positioning.md',
        'module-status.md',
        'release-verification-template.md',
        '.tools/bin/check-positioning.php',
        '.tools/bin/check-release-verification-records.php',
        '검증 기록 누적',
        '사용 판단 기준',
    ]],
    'R-11' => ['mitigating', [
        'performance-policy.md',
        'performance-baseline-evidence.md',
        '.tools/bin/check-performance-policy.php',
        '.tools/bin/check-performance-baseline.php',
        '.tools/bin/check-admin-pagination-runtime.php',
        '.tools/bin/check-community-board-copy-limits.php',
        '.tools/bin/check-survey-export-runtime.php',
        '인덱스 안전선',
        '실행 계획',
        '인덱스 검토 기록',
    ]],
];

if (array_keys($rows) !== array_keys($expectedRows)) {
    $errors[] = 'Risk register rows must be exactly: ' . implode(', ', array_keys($expectedRows));
}

$allowedStatuses = ['open', 'mitigating', 'watch', 'accepted-for-1.0'];
foreach ($expectedRows as $id => [$expectedStatus, $markers]) {
    if (!isset($rows[$id])) {
        $errors[] = 'Risk register row is missing: ' . $id;
        continue;
    }

    if (!in_array($rows[$id]['status'], $allowedStatuses, true)) {
        $errors[] = 'Risk register row ' . $id . ' has invalid status: ' . $rows[$id]['status'];
    }

    if ($rows[$id]['status'] !== $expectedStatus) {
        $errors[] = 'Risk register row ' . $id . ' expected status ' . $expectedStatus . ', got ' . $rows[$id]['status'];
    }

    foreach (['area', 'risk', 'evidence', 'remaining'] as $field) {
        if ($rows[$id][$field] === '' || $rows[$id][$field] === '-') {
            $errors[] = 'Risk register row ' . $id . ' has empty field: ' . $field;
        }
    }

    sr_risk_register_check_row_markers($rows[$id], $id, $markers, $errors);
}

sr_risk_register_check_contains('README.md', [
    'risk-register.md',
    '리스크',
], $errors);

sr_risk_register_check_contains('docs/README.md', [
    'risk-register.md',
    '프로젝트 리스크 레지스터',
], $errors);

sr_risk_register_check_contains('docs/1.0-scope.md', [
    'risk-register.md',
    '프로젝트 리스크 레지스터',
], $errors);

$verificationRecord = sr_risk_register_read('docs/records/improvement-hardening-verification-2026-06-11.md', $errors);
if ($verificationRecord !== '') {
    sr_risk_register_check_contains('docs/records/improvement-hardening-verification-2026-06-11.md', [
        '## 리스크별 릴리스 판정 연결',
        '이번 기록은 1.0 릴리스 후보 판정이 아니라',
        '조건부 통과',
        'check-risk-register.php',
        'check-release-package-policy.php',
        'release-preflight.php',
        'release-package-dry-run.php',
        'fallback sanitizer fixture',
        'dry-run',
        'manifest',
    ], $errors);

    foreach (array_keys($expectedRows) as $id) {
        if (!preg_match('/^\|\s*' . preg_quote($id, '/') . '\b.*\|\s*조건부\s*\|/m', $verificationRecord)) {
            $errors[] = 'Improvement verification record must keep ' . $id . ' as conditional, not final pass.';
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "risk register checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "risk register checks completed.\n";
