#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_security_policy_check_contains(string $file, array $markers, array &$errors): void
{
    if (!is_file($file)) {
        $errors[] = 'Required security policy document is missing: ' . $file;
        return;
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        $errors[] = 'Required security policy document cannot be read: ' . $file;
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $errors[] = 'Security policy marker missing in ' . $file . ': ' . $marker;
        }
    }
}

sr_security_policy_check_contains('SECURITY.md', [
    'kimminsup@gmail.com',
    'docs/security-response-policy.md',
    'Authentication',
    'Rich text sanitization',
    'Privacy export',
], $errors);

sr_security_policy_check_contains('docs/security-response-policy.md', [
    'security-model.md',
    'security-checklist.md',
    'Critical',
    'High',
    'Medium',
    'Low',
    'php .tools/bin/check.php',
    'php .tools/bin/smoke-http.php',
    'php .tools/bin/check-rich-text-sanitizer.php',
    'php .tools/bin/reconcile-assets.php',
    'php .tools/bin/ops-status.php',
    '릴리스 검증 기록 템플릿',
    '비밀번호',
    'token',
], $errors);

sr_security_policy_check_contains('README.md', [
    'SECURITY.md',
    '보안 제보',
], $errors);

sr_security_policy_check_contains('docs/README.md', [
    'security-response-policy.md',
    '보안 제보와 처리 기준',
], $errors);

if ($errors !== []) {
    fwrite(STDERR, "security policy checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "security policy checks completed.\n";

