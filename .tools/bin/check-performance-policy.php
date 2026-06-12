#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_performance_policy_check_contains(string $file, array $markers, array &$errors): void
{
    if (!is_file($file)) {
        $errors[] = 'Required performance policy document is missing: ' . $file;
        return;
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        $errors[] = 'Required performance policy document cannot be read: ' . $file;
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $errors[] = 'Performance policy marker missing in ' . $file . ': ' . $marker;
        }
    }
}

sr_performance_policy_check_contains('docs/performance-policy.md', [
    '공유호스팅',
    '요청 단위 메모리 캐시',
    'storage/cache/',
    'storage/cache/htmlpurifier',
    '로그인 상태 HTML 파일 캐시',
    '관리자 화면 HTML 캐시',
    '개인정보 export 결과 캐시',
    '자산 잔액/권리 상태 장기 캐시',
    '페이지네이션',
    '인덱스',
    '운영 상태 점검 기준',
    '보안 체크리스트',
    'php .tools/bin/check.php',
], $errors);

sr_performance_policy_check_contains('docs/README.md', [
    'performance-policy.md',
    '성능과 캐시 기준',
], $errors);

sr_performance_policy_check_contains('README.md', [
    'performance-policy.md',
    '성능',
], $errors);

sr_performance_policy_check_contains('docs/core-decisions.md', [
    '성능과 캐시 기준',
    'performance-policy.md',
], $errors);

sr_performance_policy_check_contains('docs/risk-register.md', [
    'performance-policy.md',
    'R-03',
], $errors);

if ($errors !== []) {
    fwrite(STDERR, "performance policy checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "performance policy checks completed.\n";

