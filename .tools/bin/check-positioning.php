#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_positioning_read(string $file, array &$errors): string
{
    if (!is_file($file)) {
        $errors[] = 'Required positioning document is missing: ' . $file;
        return '';
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        $errors[] = 'Required positioning document cannot be read: ' . $file;
        return '';
    }

    return $contents;
}

function sr_positioning_require_markers(string $file, array $markers, array &$errors): void
{
    $contents = sr_positioning_read($file, $errors);
    if ($contents === '') {
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $errors[] = 'Positioning marker missing in ' . $file . ': ' . $marker;
        }
    }
}

function sr_positioning_forbid_markers(string $file, array $markers, array &$errors): void
{
    $contents = sr_positioning_read($file, $errors);
    if ($contents === '') {
        return;
    }

    foreach ($markers as $marker) {
        if (str_contains($contents, $marker)) {
            $errors[] = 'Overclaim positioning marker found in ' . $file . ': ' . $marker;
        }
    }
}

sr_positioning_require_markers('README.md', [
    '사용 판단 기준',
    'docs/operator-feature-list.md',
    'docs/module-status.md',
    'docs/verification-status.md',
    'docs/risk-register.md',
    'docs/positioning.md',
    '검증 증거 수준',
], $errors);

sr_positioning_require_markers('docs/operator-feature-list.md', [
    '운영 신뢰성을 보증하는 검증 기록은 아니다',
    'module-status.md',
    'verification-status.md',
    'risk-register.md',
    'stable-candidate',
    'beta',
], $errors);

sr_positioning_require_markers('docs/positioning.md', [
    '그누보드',
    '라이믹스',
    '정면으로 대체하려는 프로젝트가 아니다',
    '산란이 현재 정면 경쟁 대상으로 삼지 않는 범위',
    '이 차별점은 대형 생태계보다 뛰어나다는 뜻이 아니다',
    '낮춰야 할 기대',
    '선택 번들 모듈이 많다는 사실만으로 운영 성숙도를 주장하지 않는다',
    '자산, 쿠폰, 유료 접근권 흐름은 자동 점검과 smoke 기록 없이 신뢰 대상으로 표현하지 않는다',
    '공유호스팅 환경에서 실시간 처리를 보장한다고 설명하지 않는다',
    '피해야 할 문구',
    '그누보드/라이믹스 대체 CMS',
    '한국형 올인원 CMS',
    '포인트, 쿠폰, 커뮤니티, LMS, 개인정보까지 모두 완성된 플랫폼',
    '개선 우선순위',
], $errors);

sr_positioning_require_markers('docs/risk-register.md', [
    'R-10',
    '국내 CMS 대비 신뢰 증거',
    'positioning.md',
    '검증 기록 누적',
    '사용 판단 기준',
], $errors);

sr_positioning_require_markers('docs/verification-status.md', [
    'README와 특장점 문서에서 기능을 나열할 때는',
    '통과는 운영 검증의 시작점이지 전체 보증이 아니다',
], $errors);

sr_positioning_require_markers('docs/release-verification-template.md', [
    'R-10 국내 CMS 대비 신뢰 증거',
    'positioning',
    '사용 판단 기준',
], $errors);

sr_positioning_forbid_markers('README.md', [
    '그누보드/라이믹스 대체 CMS',
    '한국형 올인원 CMS',
    '모두 완성된 플랫폼',
    '출시 가능한 오픈소스 CMS',
    'production-ready',
    '대규모 트래픽 보장',
    '실시간 처리를 보장',
], $errors);

sr_positioning_forbid_markers('docs/operator-feature-list.md', [
    '그누보드/라이믹스 대체 CMS',
    '한국형 올인원 CMS',
    '모두 완성된 플랫폼',
    '출시 가능한 오픈소스 CMS',
    'production-ready',
    '대규모 트래픽 보장',
    '실시간 처리를 보장',
], $errors);

if ($errors !== []) {
    fwrite(STDERR, "positioning checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "positioning checks completed.\n";
