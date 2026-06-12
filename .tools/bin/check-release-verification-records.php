#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_release_verification_record_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_release_verification_record_read(string $file): string
{
    if (!is_file($file)) {
        sr_release_verification_record_error('Release verification record file is missing: ' . $file);
        return '';
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        sr_release_verification_record_error('Release verification record file cannot be read: ' . $file);
        return '';
    }

    return str_replace(["\r\n", "\r"], "\n", $contents);
}

function sr_release_verification_record_section(string $content, string $section): string
{
    $quoted = preg_quote($section, '/');
    if (preg_match('/^## ' . $quoted . '\n(?<body>.*?)(?=^## |\z)/ms', $content, $matches) !== 1) {
        return '';
    }

    return trim((string) $matches['body']);
}

function sr_release_verification_record_final_verdict(string $content): string
{
    $section = sr_release_verification_record_section($content, '판정');
    if ($section === '') {
        return '';
    }

    if (preg_match('/최종 판정:\s*\n\s*-\s*(통과|조건부 통과|실패|판정 보류)/u', $section, $matches) === 1) {
        return $matches[1];
    }

    return '';
}

function sr_release_verification_record_required_gate_labels(): array
{
    return [
        '새 설치 또는 업데이트 적용',
        '`php .tools/bin/reconcile-assets.php`',
        '`php .tools/bin/ops-status.php`',
        '/admin/assets/reconciliation',
        '/admin/operations',
        '인증 smoke',
        '퀴즈 E2E smoke',
        '자산/쿠폰/유료 접근권 mutation smoke',
        '개인정보 export/cleanup smoke',
        'CKEditor asset/fallback browser smoke',
        'CKEditor upload/save browser smoke',
        '성능 수동 점검',
    ];
}

function sr_release_verification_record_required_gate_result(string $requiredGate, string $label): ?string
{
    $quoted = preg_quote($label, '/');
    if (preg_match('/^\|\s*' . $quoted . '\s*\|\s*([^|\n]+?)\s*\|/mu', $requiredGate, $matches) !== 1) {
        return null;
    }

    return trim((string) $matches[1]);
}

function sr_release_verification_record_required_gate_row(string $requiredGate, string $label): ?array
{
    $quoted = preg_quote($label, '/');
    if (preg_match('/^\|\s*' . $quoted . '\s*\|\s*([^|\n]+?)\s*\|\s*([^|\n]*?)\s*\|\s*([^|\n]*?)\s*\|/mu', $requiredGate, $matches) !== 1) {
        return null;
    }

    return [
        'result' => trim((string) $matches[1]),
        'environment' => trim((string) $matches[2]),
        'memo' => trim((string) $matches[3]),
    ];
}

function sr_release_verification_record_gate_missing_flag(string $content): string
{
    $section = sr_release_verification_record_section($content, '판정');
    if ($section === '') {
        return '';
    }

    if (preg_match('/릴리스 후보 필수 설치 DB 게이트 미실행 여부:\s*\n\s*-\s*(없음|있음)/u', $section, $matches) === 1) {
        return $matches[1];
    }

    return '';
}

function sr_release_verification_record_required_gate_rows_have_unresolved(string $content): bool
{
    $requiredGate = sr_release_verification_record_section($content, '릴리스 후보 필수 설치 DB 게이트');
    $failureLimits = sr_release_verification_record_section($content, '실패와 제한');
    $gateAndLimitText = $requiredGate . "\n" . $failureLimits;

    if ($requiredGate === '') {
        return true;
    }

    foreach (sr_release_verification_record_required_gate_labels() as $label) {
        $result = sr_release_verification_record_required_gate_result($requiredGate, $label);
        if ($result === null) {
            return true;
        }

        if ($result !== '통과') {
            return true;
        }
    }

    foreach ([
        '| 새 설치 또는 업데이트 적용 | TODO |',
        '| `php .tools/bin/reconcile-assets.php` | TODO |',
        '| `php .tools/bin/ops-status.php` | TODO |',
        '| /admin/assets/reconciliation | TODO |',
        '| /admin/operations | TODO |',
        '| 인증 smoke | TODO |',
        '| 퀴즈 E2E smoke | TODO |',
        '| 자산/쿠폰/유료 접근권 mutation smoke | TODO |',
        '| 개인정보 export/cleanup smoke | TODO |',
        '| CKEditor asset/fallback browser smoke | TODO |',
        '| CKEditor upload/save browser smoke | TODO |',
        '| 성능 수동 점검 | TODO |',
        '미실행',
        '환경 미준비',
    ] as $marker) {
        if (str_contains($gateAndLimitText, $marker)) {
            return true;
        }
    }

    return false;
}

function sr_release_verification_record_has_unresolved_required_gate(string $content): bool
{
    if (sr_release_verification_record_required_gate_rows_have_unresolved($content)) {
        return true;
    }

    return sr_release_verification_record_gate_missing_flag($content) === '있음';
}

function sr_release_verification_record_check_required_gate_details(string $file, string $content): void
{
    $requiredGate = sr_release_verification_record_section($content, '릴리스 후보 필수 설치 DB 게이트');
    if ($requiredGate === '') {
        return;
    }

    foreach (sr_release_verification_record_required_gate_labels() as $label) {
        $row = sr_release_verification_record_required_gate_row($requiredGate, $label);
        if ($row === null) {
            continue;
        }

        if ($row['result'] === '통과') {
            continue;
        }

        foreach (['environment', 'memo'] as $field) {
            if ($row[$field] === '' || $row[$field] === 'TODO' || str_contains($row[$field], 'TODO')) {
                sr_release_verification_record_error(
                    'Unresolved installed DB gate must record concrete ' . $field . ' in ' . $file . ': ' . $label
                );
            }
        }
    }
}

function sr_release_verification_record_check_missing_flag_consistency(string $file, string $content): void
{
    $flag = sr_release_verification_record_gate_missing_flag($content);
    if ($flag === '') {
        sr_release_verification_record_error('Installed DB gate missing flag is absent or invalid in ' . $file);
        return;
    }

    $hasUnresolvedGate = sr_release_verification_record_required_gate_rows_have_unresolved($content);
    if ($hasUnresolvedGate && $flag !== '있음') {
        sr_release_verification_record_error('Installed DB gate missing flag must be 있음 while a required gate is unresolved in ' . $file);
    }

    if (!$hasUnresolvedGate && $flag !== '없음') {
        sr_release_verification_record_error('Installed DB gate missing flag must be 없음 when all required gates passed in ' . $file);
    }
}

function sr_release_verification_record_check_required_gate_rows(string $file, string $content): void
{
    $requiredGate = sr_release_verification_record_section($content, '릴리스 후보 필수 설치 DB 게이트');
    if ($requiredGate === '') {
        sr_release_verification_record_error('Verification record required installed DB gate section is missing: ' . $file);
        return;
    }

    foreach (sr_release_verification_record_required_gate_labels() as $label) {
        if (!str_contains($requiredGate, '| ' . $label . ' |')) {
            sr_release_verification_record_error('Verification record required installed DB gate row is missing in ' . $file . ': ' . $label);
        }
    }
}

function sr_release_verification_record_is_release_candidate(string $file, string $content): bool
{
    if (str_contains($file, '/release-verification-')) {
        return true;
    }

    if (str_contains($content, '이번 기록은 1.0 릴리스 후보 판정이 아니라')) {
        return false;
    }

    return str_contains($content, '릴리스 후보');
}

function sr_release_verification_record_should_reject_final_pass(string $file, string $content): bool
{
    $finalVerdict = sr_release_verification_record_final_verdict($content);
    $isReleaseCandidate = sr_release_verification_record_is_release_candidate($file, $content);
    $hasUnresolvedRequiredGate = sr_release_verification_record_has_unresolved_required_gate($content);

    if ($isReleaseCandidate && $hasUnresolvedRequiredGate && $finalVerdict === '통과') {
        return true;
    }

    return !$isReleaseCandidate
        && str_contains($content, '이번 기록은 1.0 릴리스 후보 판정이 아니라')
        && $finalVerdict === '통과';
}

function sr_release_verification_record_fixture(string $requiredGateResult, string $gateMissingFlag, string $verdict, bool $nonReleaseCandidate = false): string
{
    $intro = $nonReleaseCandidate
        ? '이번 기록은 1.0 릴리스 후보 판정이 아니라 개선 묶음 점검이다.'
        : '이번 기록은 1.0 릴리스 후보 판정이다.';

    return '# 릴리스 검증 기록 - fixture

' . $intro . '

## 대상

| 항목 | 값 |
| --- | --- |
| 실행 날짜 | 2026-06-11 |

## 범위

검증 대상:

- fixture

## 정적 점검

| 점검 | 결과 | 메모 |
| --- | --- | --- |
| `php .tools/bin/check.php` | 통과 | fixture |

## 릴리스 후보 필수 설치 DB 게이트

| 게이트 | 결과 | 환경 | 메모 |
| --- | --- | --- | --- |
| 새 설치 또는 업데이트 적용 | ' . $requiredGateResult . ' | fixture | fixture |
| `php .tools/bin/reconcile-assets.php` | ' . $requiredGateResult . ' | fixture | fixture |
| `php .tools/bin/ops-status.php` | ' . $requiredGateResult . ' | fixture | fixture |
| /admin/assets/reconciliation | ' . $requiredGateResult . ' | fixture | fixture |
| /admin/operations | ' . $requiredGateResult . ' | fixture | fixture |
| 인증 smoke | ' . $requiredGateResult . ' | fixture | fixture |
| 퀴즈 E2E smoke | ' . $requiredGateResult . ' | fixture | fixture |
| 자산/쿠폰/유료 접근권 mutation smoke | ' . $requiredGateResult . ' | fixture | fixture |
| 개인정보 export/cleanup smoke | ' . $requiredGateResult . ' | fixture | fixture |
| CKEditor asset/fallback browser smoke | ' . $requiredGateResult . ' | fixture | fixture |
| CKEditor upload/save browser smoke | ' . $requiredGateResult . ' | fixture | fixture |
| 성능 수동 점검 | ' . $requiredGateResult . ' | fixture | fixture |

## 실패와 제한

| 항목 | 분류 | 판정 | 후속 |
| --- | --- | --- | --- |
| fixture | 기존 보완 항목 | fixture | fixture |

## 리스크별 릴리스 판정 연결

| 리스크 | 연결된 검증 증거 | 이번 판정 | 후속 |
| --- | --- | --- | --- |
| R-01 자산/쿠폰/유료 접근권 | fixture | 조건부 | fixture |
| R-02 HTML sanitizer/CKEditor | fixture | 조건부 | fixture |
| R-03 공유호스팅 queue/cron/배치 | fixture | 조건부 | fixture |
| R-04 개인정보 export/cleanup 계약 | fixture | 조건부 | fixture |
| R-05 넓은 번들 모듈 표면 | fixture | 조건부 | fixture |
| R-06 커스텀 요청/보안 contract | fixture | 조건부 | fixture |
| R-07 외부 의존성/vendored asset | fixture | 조건부 | fixture |
| R-08 배포 보호 | fixture | 조건부 | fixture |
| R-09 문서/Wiki 지연 | fixture | 조건부 | fixture |
| R-10 국내 CMS 대비 신뢰 증거 | fixture | 조건부 | fixture |
| R-11 성능/캐시 기준 | fixture | 조건부 | fixture |

## 판정

최종 판정:

- ' . $verdict . '

릴리스 후보 필수 설치 DB 게이트 미실행 여부:

- ' . $gateMissingFlag . '
';
}

function sr_release_verification_record_self_test(): void
{
    $missingGateRowFixture = str_replace(
        '| 성능 수동 점검 | 통과 | fixture | fixture |' . "\n",
        '',
        sr_release_verification_record_fixture('통과', '없음', '통과')
    );
    $cases = [
        'release candidate unresolved gate final pass rejected' => [
            'file' => 'docs/records/release-verification-fixture.md',
            'content' => sr_release_verification_record_fixture('TODO', '있음', '통과'),
            'reject' => true,
        ],
        'release candidate unresolved gate conditional allowed' => [
            'file' => 'docs/records/release-verification-fixture.md',
            'content' => sr_release_verification_record_fixture('TODO', '있음', '조건부 통과'),
            'reject' => false,
        ],
        'release candidate manual required gate final pass rejected' => [
            'file' => 'docs/records/release-verification-fixture.md',
            'content' => sr_release_verification_record_fixture('수동 확인 필요', '있음', '통과'),
            'reject' => true,
        ],
        'release candidate failed gate final pass rejected' => [
            'file' => 'docs/records/release-verification-fixture.md',
            'content' => sr_release_verification_record_fixture('실패', '있음', '통과'),
            'reject' => true,
        ],
        'release candidate resolved gate final pass allowed' => [
            'file' => 'docs/records/release-verification-fixture.md',
            'content' => sr_release_verification_record_fixture('통과', '없음', '통과'),
            'reject' => false,
        ],
        'non release improvement final pass rejected' => [
            'file' => 'docs/records/improvement-fixture-verification.md',
            'content' => sr_release_verification_record_fixture('통과', '없음', '통과', true),
            'reject' => true,
        ],
        'release candidate missing required gate row rejected' => [
            'file' => 'docs/records/release-verification-fixture.md',
            'content' => $missingGateRowFixture,
            'reject' => true,
        ],
    ];

    foreach ($cases as $label => $case) {
        $actual = sr_release_verification_record_should_reject_final_pass((string) $case['file'], (string) $case['content']);
        if ($actual !== (bool) $case['reject']) {
            sr_release_verification_record_error('Release verification record self-test failed: ' . $label);
        }
    }

    $flagMismatchFixture = str_replace(
        '릴리스 후보 필수 설치 DB 게이트 미실행 여부:' . "\n\n" . '- 있음',
        '릴리스 후보 필수 설치 DB 게이트 미실행 여부:' . "\n\n" . '- 없음',
        sr_release_verification_record_fixture('TODO', '있음', '조건부 통과')
    );
    $beforeCount = count($GLOBALS['errors']);
    sr_release_verification_record_check_missing_flag_consistency('docs/records/release-verification-fixture.md', $flagMismatchFixture);
    if (count($GLOBALS['errors']) !== $beforeCount + 1) {
        sr_release_verification_record_error('Release verification record self-test failed: missing flag mismatch check');
    }
    array_pop($GLOBALS['errors']);
}

sr_release_verification_record_self_test();

$template = sr_release_verification_record_read('docs/release-verification-template.md');
foreach ([
    '릴리스 후보 필수 설치 DB 게이트 미실행 여부',
    '통과 / 조건부 통과 / 실패 / 판정 보류',
    '실행하지 못한 경우 최종 판정을 `조건부 통과` 또는 `판정 보류`로 낮춘다',
    '릴리스 후보 판정에서는 미설치 install-mode smoke만으로 통과 처리하지 않는다',
] as $marker) {
    if ($template !== '' && !str_contains($template, $marker)) {
        sr_release_verification_record_error('Release verification template marker is missing: ' . $marker);
    }
}

$records = glob('docs/records/*verification*.md');
if (!is_array($records) || $records === []) {
    sr_release_verification_record_error('No verification records found under docs/records.');
    $records = [];
}

sort($records, SORT_STRING);
foreach ($records as $record) {
    $content = sr_release_verification_record_read($record);
    if ($content === '') {
        continue;
    }

    foreach ([
        '## 대상',
        '## 범위',
        '## 정적 점검',
        '## 실패와 제한',
        '## 리스크별 릴리스 판정 연결',
        '## 판정',
    ] as $section) {
        if (!str_contains($content, $section)) {
            sr_release_verification_record_error('Verification record section is missing in ' . $record . ': ' . $section);
        }
    }

    $finalVerdict = sr_release_verification_record_final_verdict($content);
    if ($finalVerdict === '') {
        sr_release_verification_record_error('Verification record final verdict is missing or invalid: ' . $record);
    }

    if (sr_release_verification_record_should_reject_final_pass($record, $content)
        && sr_release_verification_record_is_release_candidate($record, $content)
    ) {
        sr_release_verification_record_error('Release candidate record must not be final pass while required installed DB gate is unresolved: ' . $record);
    }

    if (sr_release_verification_record_should_reject_final_pass($record, $content)
        && !sr_release_verification_record_is_release_candidate($record, $content)
    ) {
        sr_release_verification_record_error('Non-release-candidate improvement record must not claim final pass: ' . $record);
    }

    sr_release_verification_record_check_required_gate_rows($record, $content);
    sr_release_verification_record_check_required_gate_details($record, $content);
    sr_release_verification_record_check_missing_flag_consistency($record, $content);

    foreach ([
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
    ] as $riskMarker) {
        if (!str_contains($content, $riskMarker)) {
            sr_release_verification_record_error('Verification record risk row is missing in ' . $record . ': ' . $riskMarker);
        }
    }
}

foreach ([
    'docs/verification-status.md' => [
        '릴리스 후보 필수 설치 DB 게이트',
        '필수 설치 DB 게이트의 행을 생략할 수 없다',
        '조건부 통과',
        '판정 보류',
    ],
    'docs/contribution-guide.md' => [
        'release-verification-template.md',
        '검증 명령',
    ],
    'docs/records/improvement-hardening-verification-2026-06-11.md' => [
        '대상 범위',
        '단일 hash 대신 각 작업 단위 commit',
        '포트는 실행 시점별 가용 포트',
        'SR_SMOKE_BASE_URL=http://127.0.0.1:<port>',
        'check-release-verification-records.php',
        'self-test',
        '조건부 통과',
        '핵심 인덱스 marker',
        'config-mode',
        'config-owner-group',
        '개선 기록의 기존 상태와 `module-status.md` 현재 상태 일치',
        'check-htmlpurifier-vendor-integrity.php',
        'check-browser-qa.php',
        'npm --prefix .tools/browser-qa run test:ckeditor',
        'ckeditor-browser-smoke.spec.js',
        '브라우저 asset 로딩/fallback smoke',
        '쿠폰 접근권 부분 실패 rollback',
        '/admin/operations',
        'smoke-asset-idempotency-http.php',
        'dedupe row count',
        '실행 계획/인덱스 상태',
        '/config/config.php',
        '/storage/installed.lock',
        'dev-router 보호 규칙',
    ],
] as $file => $markers) {
    $contents = sr_release_verification_record_read($file);
    foreach ($markers as $marker) {
        if ($contents !== '' && !str_contains($contents, $marker)) {
            sr_release_verification_record_error('Release verification record policy marker is missing in ' . $file . ': ' . $marker);
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "release verification record checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "release verification record checks completed.\n";
