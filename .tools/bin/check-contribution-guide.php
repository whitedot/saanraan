#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_contribution_check_contains(string $file, array $markers, array &$errors): void
{
    if (!is_file($file)) {
        $errors[] = 'Required contribution document is missing: ' . $file;
        return;
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        $errors[] = 'Required contribution document cannot be read: ' . $file;
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $errors[] = 'Contribution guide marker missing in ' . $file . ': ' . $marker;
        }
    }
}

sr_contribution_check_contains('CONTRIBUTING.md', [
    'docs/contribution-guide.md',
    'php .tools/bin/check.php',
    '모듈 경계',
], $errors);

sr_contribution_check_contains('docs/contribution-guide.md', [
    'positioning.md',
    '1.0-scope.md',
    'module-guide.md',
    'module-status.md',
    'verification-status.md',
    'security-model.md',
    'security-checklist.md',
    'database-access-policy.md',
    'operational-status.md',
    'dependency-policy.md',
    'smoke-test.md',
    'release-process.md',
    'release-verification-template.md',
    'php .tools/bin/check.php',
    'php .tools/bin/smoke-http.php',
    'php .tools/bin/release-installed-gate-status.php --run-readonly --fail-on-unresolved',
    'php .tools/bin/release-installed-gate-status.php --json --fail-on-unresolved',
    '권한을 넓히지 말고',
    'SR_SMOKE_ADMIN_IDENTIFIER=<admin>',
    'SR_SMOKE_ADMIN_PASSWORD=<password>',
    '고위험 변경',
    '기능 변경 완결성 확인',
    '자연스러운 공통 기능 소유자가 없는 경우 새 공통 모듈이나 코어 기능을 만들지 않는다',
    '없어야 하는 버튼, 링크, 문구, fallback, 중복 asset',
    '정확한 동일성이나 완전한 완료로 판정하지 않는다',
    '문서 갱신 기준',
], $errors);

sr_contribution_check_contains('README.md', [
    'CONTRIBUTING.md',
    '기여',
], $errors);

sr_contribution_check_contains('docs/README.md', [
    'contribution-guide.md',
    '기여자 작업 기준',
], $errors);

if ($errors !== []) {
    fwrite(STDERR, "contribution guide checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "contribution guide checks completed.\n";
