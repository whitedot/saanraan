#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_installed_gate_status_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_installed_gate_status_read(string $file): string
{
    if (!is_file($file)) {
        sr_installed_gate_status_error('Installed gate status required file is missing: ' . $file);
        return '';
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        sr_installed_gate_status_error('Installed gate status required file cannot be read: ' . $file);
        return '';
    }

    return $contents;
}

function sr_installed_gate_status_require_markers(string $file, array $markers): void
{
    $contents = sr_installed_gate_status_read($file);
    if ($contents === '') {
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            sr_installed_gate_status_error('Installed gate status marker missing in ' . $file . ': ' . $marker);
        }
    }
}

function sr_installed_gate_status_exec(array $command): string
{
    $result = sr_installed_gate_status_exec_result($command);
    if ($result['exit_code'] !== 0) {
        sr_installed_gate_status_error('Installed gate status command failed: ' . implode(' ', $command) . "\n" . $result['output']);
        return '';
    }

    return $result['output'];
}

function sr_installed_gate_status_exec_result(array $command): array
{
    $parts = [];
    foreach ($command as $part) {
        $parts[] = escapeshellarg($part);
    }

    $output = [];
    exec(implode(' ', $parts) . ' 2>&1', $output, $exitCode);
    return [
        'exit_code' => $exitCode,
        'output' => implode("\n", $output),
    ];
}

function sr_installed_gate_status_assert_unresolved_count(string $label, string $output): void
{
    if ($output === '') {
        return;
    }

    if (preg_match_all('/^gate\t[^\n]*\tresult=([^\t\n]+)/m', $output, $matches) === false) {
        sr_installed_gate_status_error('Installed gate status output cannot be parsed for gate rows: ' . $label);
        return;
    }

    $results = $matches[1] ?? [];
    if (count($results) !== 13) {
        sr_installed_gate_status_error('Installed gate status output must contain 13 gate rows for ' . $label . ', got ' . (string) count($results));
    }

    $expectedUnresolved = 0;
    foreach ($results as $result) {
        if ($result !== '통과') {
            $expectedUnresolved++;
        }
    }

    if (preg_match('/^unresolved-gates: (\d+)$/m', $output, $countMatches) !== 1) {
        sr_installed_gate_status_error('Installed gate status output missing unresolved-gates count: ' . $label);
        return;
    }

    if ((int) $countMatches[1] !== $expectedUnresolved) {
        sr_installed_gate_status_error('Installed gate status unresolved-gates mismatch for ' . $label . ': expected ' . (string) $expectedUnresolved . ', got ' . $countMatches[1]);
    }
}

function sr_installed_gate_status_assert_result_summary(string $label, string $output): void
{
    if ($output === '') {
        return;
    }

    if (preg_match_all('/^gate\t[^\n]*\tresult=([^\t\n]+)/m', $output, $matches) === false) {
        sr_installed_gate_status_error('Installed gate status output cannot be parsed for summary: ' . $label);
        return;
    }

    $expected = [
        '통과' => 0,
        '부분 확인' => 0,
        '수동 확인 필요' => 0,
        '미실행' => 0,
        '환경 미준비' => 0,
        '실패' => 0,
    ];
    foreach (($matches[1] ?? []) as $result) {
        if (!array_key_exists($result, $expected)) {
            $expected[$result] = 0;
        }

        $expected[$result]++;
    }

    if (preg_match('/^gate-result-summary: (.+)$/m', $output, $summaryMatches) !== 1) {
        sr_installed_gate_status_error('Installed gate status output missing gate-result-summary: ' . $label);
        return;
    }

    $actual = [];
    foreach (explode(',', (string) $summaryMatches[1]) as $part) {
        $pieces = explode('=', trim($part), 2);
        if (count($pieces) !== 2) {
            continue;
        }

        $actual[trim($pieces[0])] = (int) trim($pieces[1]);
    }

    foreach ($expected as $result => $count) {
        if (($actual[$result] ?? null) !== $count) {
            sr_installed_gate_status_error(
                'Installed gate status result summary mismatch for ' . $label . ': ' . $result
                . ' expected ' . (string) $count . ', got ' . (array_key_exists($result, $actual) ? (string) $actual[$result] : 'missing')
            );
        }
    }
}

function sr_installed_gate_status_assert_markdown_table(string $label, string $output): void
{
    if ($output === '') {
        return;
    }

    if (!str_starts_with($output, "| 게이트 | 결과 | 환경 | 메모 |\n| --- | --- | --- | --- |\n")) {
        sr_installed_gate_status_error('Installed gate status markdown table header is invalid: ' . $label);
    }

    if (preg_match_all('/^\|\s*(.*?)\s*\|\s*([^|\n]+?)\s*\|\s*([^|\n]*?)\s*\|\s*([^|\n]*?)\s*\|$/m', $output, $matches) === false) {
        sr_installed_gate_status_error('Installed gate status markdown table cannot be parsed: ' . $label);
        return;
    }

    $labels = [];
    foreach (($matches[1] ?? []) as $rawLabel) {
        $labelText = trim((string) $rawLabel);
        if ($labelText === '게이트' || $labelText === '---') {
            continue;
        }

        $labels[] = $labelText;
    }

    $expectedLabels = [
        '새 설치 또는 업데이트 적용',
        '`php .tools/bin/reconcile-assets.php`',
        '`php .tools/bin/ops-status.php`',
        '`php .tools/bin/expire-points.php --dry-run`',
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

    if ($labels !== $expectedLabels) {
        sr_installed_gate_status_error('Installed gate status markdown rows must match template order for ' . $label);
    }
}

function sr_installed_gate_status_assert_json(string $label, string $output): void
{
    if ($output === '') {
        return;
    }

    $decoded = json_decode($output, true);
    if (!is_array($decoded)) {
        sr_installed_gate_status_error('Installed gate status JSON output is invalid: ' . $label);
        return;
    }

    if (($decoded['version'] ?? null) !== 1) {
        sr_installed_gate_status_error('Installed gate status JSON version mismatch: ' . $label);
    }

    $metadata = $decoded['metadata'] ?? null;
    if (!is_array($metadata)) {
        sr_installed_gate_status_error('Installed gate status JSON metadata is missing: ' . $label);
        return;
    }

    foreach ([
        'config_readable' => 'no',
        'config_mode' => '0600',
        'config_owner_group' => 'www-data:www-data',
        'sr_is_installed' => 'no',
        'run_readonly' => 'no',
        'mutation_smoke_allowed' => 'no',
    ] as $key => $expectedValue) {
        if (($metadata[$key] ?? null) !== $expectedValue) {
            sr_installed_gate_status_error('Installed gate status JSON metadata mismatch for ' . $label . ': ' . $key);
        }
    }

    $gates = $decoded['gates'] ?? null;
    if (!is_array($gates) || count($gates) !== 13) {
        sr_installed_gate_status_error('Installed gate status JSON gates must contain 13 rows: ' . $label);
        return;
    }

    $counts = [
        '통과' => 0,
        '부분 확인' => 0,
        '수동 확인 필요' => 0,
        '미실행' => 0,
        '환경 미준비' => 0,
        '실패' => 0,
    ];
    foreach ($gates as $gate) {
        if (!is_array($gate)) {
            sr_installed_gate_status_error('Installed gate status JSON gate row is not an object: ' . $label);
            continue;
        }

        foreach (['gate', 'result', 'environment', 'memo'] as $field) {
            if (!is_string($gate[$field] ?? null) || $gate[$field] === '') {
                sr_installed_gate_status_error('Installed gate status JSON gate row missing field ' . $field . ': ' . $label);
            }
        }

        $result = (string) ($gate['result'] ?? '');
        if (!array_key_exists($result, $counts)) {
            $counts[$result] = 0;
        }

        $counts[$result]++;
    }

    $jsonCounts = $decoded['result_counts'] ?? null;
    if (!is_array($jsonCounts)) {
        sr_installed_gate_status_error('Installed gate status JSON result_counts is missing: ' . $label);
        return;
    }

    foreach ($counts as $result => $count) {
        if (($jsonCounts[$result] ?? null) !== $count) {
            sr_installed_gate_status_error('Installed gate status JSON result_counts mismatch for ' . $label . ': ' . $result);
        }
    }

    if (($decoded['result_summary'] ?? '') !== '통과=0, 부분 확인=0, 수동 확인 필요=0, 미실행=9, 환경 미준비=4, 실패=0') {
        sr_installed_gate_status_error('Installed gate status JSON result_summary mismatch: ' . $label);
    }

    if (($decoded['unresolved_gates'] ?? null) !== 13) {
        sr_installed_gate_status_error('Installed gate status JSON unresolved_gates mismatch: ' . $label);
    }
}

$output = sr_installed_gate_status_exec([PHP_BINARY, '.tools/bin/release-installed-gate-status.php']);
sr_installed_gate_status_assert_unresolved_count('default output', $output);
sr_installed_gate_status_assert_result_summary('default output', $output);
foreach ([
    'release-installed-gate-status-version: 1',
    'installed-lock:',
    'config-readable:',
    'config-mode:',
    'config-owner-group:',
    'sr-is-installed:',
    'browser-qa-base-url:',
    'account-smoke-credentials: missing',
    'admin-smoke-credentials: missing',
    'asset-dedupe-expectation: missing',
    'run-readonly: no',
    'run-browser-qa: no',
    'run-auth-smoke: no',
    'run-quiz-smoke: no',
    'run-asset-smoke: no',
    'run-privacy-fixtures: no',
    'run-performance-fixtures: no',
    'performance-review-ready: no',
    'mutation-smoke-allowed: no',
    "gate\t새 설치 또는 업데이트 적용\t",
    "gate\t`php .tools/bin/reconcile-assets.php`\t",
    "gate\t`php .tools/bin/ops-status.php`\t",
    "gate\t`php .tools/bin/expire-points.php --dry-run`\t",
    "gate\t/admin/assets/reconciliation\t",
    "gate\t/admin/operations\t",
    "gate\t인증 smoke\t",
    "gate\t퀴즈 E2E smoke\t",
    "gate\t자산/쿠폰/유료 접근권 mutation smoke\t",
    "gate\t개인정보 export/cleanup smoke\t",
    "gate\tCKEditor asset/fallback browser smoke\t",
    "gate\tCKEditor upload/save browser smoke\t",
    "gate\t성능 수동 점검\t",
    'gate-result-summary: 통과=0, 부분 확인=0, 수동 확인 필요=0, 미실행=9, 환경 미준비=4, 실패=0',
    'unresolved-gates:',
    'release installed gate status completed.',
] as $marker) {
    if ($output !== '' && !str_contains($output, $marker)) {
        sr_installed_gate_status_error('Installed gate status output marker missing: ' . $marker);
    }
}

$markdownOutput = sr_installed_gate_status_exec([PHP_BINARY, '.tools/bin/release-installed-gate-status.php', '--markdown-table']);
sr_installed_gate_status_assert_markdown_table('default markdown output', $markdownOutput);
foreach ([
    '| 새 설치 또는 업데이트 적용 | 환경 미준비 | current tree | config/config.php is not readable by current user |',
    '| /admin/assets/reconciliation | 미실행 | base URL missing | set SR_SMOKE_BASE_URL and use an administrator session to verify the read-only screen |',
    '| 성능 수동 점검 | 미실행 | base URL missing | set SR_SMOKE_BASE_URL and SR_PERFORMANCE_REVIEW_READY=1 after representative local/staging data is prepared; use --run-performance-fixtures only for static/runtime fixtures |',
] as $marker) {
    if ($markdownOutput !== '' && !str_contains($markdownOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status markdown output marker missing: ' . $marker);
    }
}

$jsonOutput = sr_installed_gate_status_exec([PHP_BINARY, '.tools/bin/release-installed-gate-status.php', '--json']);
sr_installed_gate_status_assert_json('default JSON output', $jsonOutput);

$failOutput = sr_installed_gate_status_exec_result([PHP_BINARY, '.tools/bin/release-installed-gate-status.php', '--fail-on-unresolved']);
if ((int) $failOutput['exit_code'] !== 1) {
    sr_installed_gate_status_error('Installed gate status --fail-on-unresolved must exit 1 while gates are unresolved.');
}
foreach ([
    'gate-result-summary: 통과=0, 부분 확인=0, 수동 확인 필요=0, 미실행=9, 환경 미준비=4, 실패=0',
    'unresolved-gates: 13',
] as $marker) {
    if (!str_contains((string) $failOutput['output'], $marker)) {
        sr_installed_gate_status_error('Installed gate status --fail-on-unresolved output marker missing: ' . $marker);
    }
}

$jsonFailOutput = sr_installed_gate_status_exec_result([PHP_BINARY, '.tools/bin/release-installed-gate-status.php', '--json', '--fail-on-unresolved']);
if ((int) $jsonFailOutput['exit_code'] !== 1) {
    sr_installed_gate_status_error('Installed gate status --json --fail-on-unresolved must exit 1 while gates are unresolved.');
}
sr_installed_gate_status_assert_json('JSON fail-on-unresolved output', (string) $jsonFailOutput['output']);

$unknownOptionOutput = sr_installed_gate_status_exec_result([PHP_BINARY, '.tools/bin/release-installed-gate-status.php', '--unknown-option']);
if ((int) $unknownOptionOutput['exit_code'] !== 2) {
    sr_installed_gate_status_error('Installed gate status unknown option must exit 2.');
}
foreach ([
    'Unknown release-installed-gate-status option: --unknown-option',
    'release-installed-gate-status.php --help',
] as $marker) {
    if (!str_contains((string) $unknownOptionOutput['output'], $marker)) {
        sr_installed_gate_status_error('Installed gate status unknown option output marker missing: ' . $marker);
    }
}

$helpOutput = sr_installed_gate_status_exec([PHP_BINARY, '.tools/bin/release-installed-gate-status.php', '--help']);
foreach ([
    'Usage:',
    '--markdown-table',
    '--json',
    '--fail-on-unresolved',
    '--run-readonly',
    '--run-browser-qa',
    '--run-auth-smoke',
    '--run-quiz-smoke',
    '--run-asset-smoke',
    '--run-privacy-fixtures',
    '--run-performance-fixtures',
    'SR_SMOKE_BASE_URL',
    'SR_BROWSER_QA_BASE_URL',
    'SR_SMOKE_IDENTIFIER',
    'SR_SMOKE_PASSWORD',
    'SR_SMOKE_ADMIN_IDENTIFIER',
    'SR_SMOKE_ADMIN_PASSWORD',
    'SR_SMOKE_ALLOW_MUTATION=1',
    'SR_SMOKE_FORM_PATH',
    'SR_SMOKE_EXPECT_DEDUPE_TABLE',
    'SR_SMOKE_EXPECT_DEDUPE_KEY',
    'SR_PERFORMANCE_REVIEW_READY=1',
    'Do not run mutation smoke against production data',
    'config/config.php is not readable',
    'web-server',
    'local/staging-only execution user',
] as $marker) {
    if ($helpOutput !== '' && !str_contains($helpOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status help output marker missing: ' . $marker);
    }
}

$baseOnlyOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
sr_installed_gate_status_assert_unresolved_count('base-url-only output', $baseOnlyOutput);
sr_installed_gate_status_assert_result_summary('base-url-only output', $baseOnlyOutput);
foreach ([
    'base-url: http://127.0.0.1:1',
    'admin-smoke-credentials: missing',
    "gate\t/admin/assets/reconciliation\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=requires SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD",
    "gate\t/admin/operations\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=requires SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD",
    "gate\t퀴즈 E2E smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=requires SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD",
    "gate\tCKEditor upload/save browser smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=requires SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD",
    "gate\t개인정보 export/cleanup smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=requires SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD for disposable account data",
    "gate\t성능 수동 점검\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=requires SR_PERFORMANCE_REVIEW_READY=1 after representative local/staging data is prepared",
] as $marker) {
    if ($baseOnlyOutput !== '' && !str_contains($baseOnlyOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status base-url-only output marker missing: ' . $marker);
    }
}

$adminIncompleteIdentifierOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_ADMIN_IDENTIFIER=admin',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'admin-smoke-credentials: incomplete',
    "gate\t/admin/assets/reconciliation\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD must be provided together",
    "gate\t/admin/operations\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD must be provided together",
    "gate\t퀴즈 E2E smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD must be provided together",
    "gate\tCKEditor upload/save browser smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD must be provided together",
] as $marker) {
    if ($adminIncompleteIdentifierOutput !== '' && !str_contains($adminIncompleteIdentifierOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status admin-identifier-only output marker missing: ' . $marker);
    }
}

$adminIncompletePasswordOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_ADMIN_PASSWORD=12341234',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'admin-smoke-credentials: incomplete',
    "gate\t/admin/assets/reconciliation\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD must be provided together",
    "gate\t/admin/operations\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD must be provided together",
    "gate\t퀴즈 E2E smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD must be provided together",
    "gate\tCKEditor upload/save browser smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD must be provided together",
] as $marker) {
    if ($adminIncompletePasswordOutput !== '' && !str_contains($adminIncompletePasswordOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status admin-password-only output marker missing: ' . $marker);
    }
}

$adminConfiguredOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_ADMIN_IDENTIFIER=admin',
    'SR_SMOKE_ADMIN_PASSWORD=12341234',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
sr_installed_gate_status_assert_unresolved_count('admin-configured output', $adminConfiguredOutput);
sr_installed_gate_status_assert_result_summary('admin-configured output', $adminConfiguredOutput);
foreach ([
    'admin-smoke-credentials: configured',
    "gate\t/admin/assets/reconciliation\tresult=수동 확인 필요\tenvironment=http://127.0.0.1:1\tmemo=administrator session configured",
    "gate\t/admin/operations\tresult=수동 확인 필요\tenvironment=http://127.0.0.1:1\tmemo=administrator session configured",
    "gate\t퀴즈 E2E smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=quiz E2E smoke creates quiz and attempt data; set SR_SMOKE_ALLOW_MUTATION=1",
    "gate\tCKEditor upload/save browser smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=upload/save browser smoke creates or updates content; set SR_SMOKE_ALLOW_MUTATION=1",
] as $marker) {
    if ($adminConfiguredOutput !== '' && !str_contains($adminConfiguredOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status admin-configured output marker missing: ' . $marker);
    }
}

$accountIncompleteIdentifierOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'account-smoke-credentials: incomplete',
    "gate\t인증 smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD must be provided together",
    "gate\t자산/쿠폰/유료 접근권 mutation smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD must be provided together",
    "gate\t개인정보 export/cleanup smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD must be provided together",
] as $marker) {
    if ($accountIncompleteIdentifierOutput !== '' && !str_contains($accountIncompleteIdentifierOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status account-identifier-only output marker missing: ' . $marker);
    }
}

$accountIncompletePasswordOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_PASSWORD=12341234',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'account-smoke-credentials: incomplete',
    "gate\t인증 smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD must be provided together",
    "gate\t자산/쿠폰/유료 접근권 mutation smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD must be provided together",
    "gate\t개인정보 export/cleanup smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD must be provided together",
] as $marker) {
    if ($accountIncompletePasswordOutput !== '' && !str_contains($accountIncompletePasswordOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status account-password-only output marker missing: ' . $marker);
    }
}

$assetMissingFormOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'account-smoke-credentials: configured',
    'asset-dedupe-expectation: missing',
    "gate\t자산/쿠폰/유료 접근권 mutation smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=requires SR_SMOKE_FORM_PATH for disposable paid target data",
    "gate\t개인정보 export/cleanup smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=privacy cleanup can mutate data; set SR_SMOKE_ALLOW_MUTATION=1",
] as $marker) {
    if ($assetMissingFormOutput !== '' && !str_contains($assetMissingFormOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status asset missing form output marker missing: ' . $marker);
    }
}

$assetDedupeMissingOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    'SR_SMOKE_FORM_PATH=/paid/form',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'asset-dedupe-expectation: missing',
    "gate\t자산/쿠폰/유료 접근권 mutation smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=requires SR_SMOKE_EXPECT_DEDUPE_TABLE and SR_SMOKE_EXPECT_DEDUPE_KEY",
] as $marker) {
    if ($assetDedupeMissingOutput !== '' && !str_contains($assetDedupeMissingOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status asset dedupe missing output marker missing: ' . $marker);
    }
}

$assetDedupeIncompleteTableOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    'SR_SMOKE_FORM_PATH=/paid/form',
    'SR_SMOKE_EXPECT_DEDUPE_TABLE=sr_content_asset_access_logs',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'asset-dedupe-expectation: incomplete',
    "gate\t자산/쿠폰/유료 접근권 mutation smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_EXPECT_DEDUPE_TABLE and SR_SMOKE_EXPECT_DEDUPE_KEY must be provided together",
] as $marker) {
    if ($assetDedupeIncompleteTableOutput !== '' && !str_contains($assetDedupeIncompleteTableOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status asset dedupe-table-only output marker missing: ' . $marker);
    }
}

$assetDedupeIncompleteKeyOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    'SR_SMOKE_FORM_PATH=/paid/form',
    'SR_SMOKE_EXPECT_DEDUPE_KEY=fixture:dedupe',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'asset-dedupe-expectation: incomplete',
    "gate\t자산/쿠폰/유료 접근권 mutation smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_EXPECT_DEDUPE_TABLE and SR_SMOKE_EXPECT_DEDUPE_KEY must be provided together",
] as $marker) {
    if ($assetDedupeIncompleteKeyOutput !== '' && !str_contains($assetDedupeIncompleteKeyOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status asset dedupe-key-only output marker missing: ' . $marker);
    }
}

$authMutationBlockedOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
    '--run-auth-smoke',
]);
foreach ([
    'run-auth-smoke: yes',
    'mutation-smoke-allowed: no',
    "gate\t인증 smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=authenticated smoke creates data; set SR_SMOKE_ALLOW_MUTATION=1",
] as $marker) {
    if ($authMutationBlockedOutput !== '' && !str_contains($authMutationBlockedOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status auth mutation guard output marker missing: ' . $marker);
    }
}

$assetMutationBlockedOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    'SR_SMOKE_FORM_PATH=/paid/form',
    'SR_SMOKE_EXPECT_DEDUPE_TABLE=sr_content_asset_access_logs',
    'SR_SMOKE_EXPECT_DEDUPE_KEY=fixture:dedupe',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
    '--run-asset-smoke',
]);
foreach ([
    'run-asset-smoke: yes',
    'mutation-smoke-allowed: no',
    "gate\t자산/쿠폰/유료 접근권 mutation smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=asset idempotency smoke creates financial-like records; set SR_SMOKE_ALLOW_MUTATION=1",
] as $marker) {
    if ($assetMutationBlockedOutput !== '' && !str_contains($assetMutationBlockedOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status asset mutation guard output marker missing: ' . $marker);
    }
}

$quizMutationBlockedOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_ADMIN_IDENTIFIER=admin',
    'SR_SMOKE_ADMIN_PASSWORD=12341234',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
    '--run-quiz-smoke',
]);
foreach ([
    'run-quiz-smoke: yes',
    'mutation-smoke-allowed: no',
    "gate\t퀴즈 E2E smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=quiz E2E smoke creates quiz and attempt data; set SR_SMOKE_ALLOW_MUTATION=1",
] as $marker) {
    if ($quizMutationBlockedOutput !== '' && !str_contains($quizMutationBlockedOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status quiz mutation guard output marker missing: ' . $marker);
    }
}

$authReadyOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    'SR_SMOKE_ALLOW_MUTATION=1',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
sr_installed_gate_status_assert_unresolved_count('auth-ready output', $authReadyOutput);
sr_installed_gate_status_assert_result_summary('auth-ready output', $authReadyOutput);
foreach ([
    'mutation-smoke-allowed: yes',
    "gate\t인증 smoke\tresult=수동 확인 필요\tenvironment=http://127.0.0.1:1\tmemo=authenticated smoke is configured; rerun with --run-auth-smoke",
    "gate\t개인정보 export/cleanup smoke\tresult=수동 확인 필요\tenvironment=http://127.0.0.1:1\tmemo=disposable account and mutation guard configured; manually verify installed DB export and cleanup smoke",
] as $marker) {
    if ($authReadyOutput !== '' && !str_contains($authReadyOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status auth ready output marker missing: ' . $marker);
    }
}

$quizReadyOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_ADMIN_IDENTIFIER=admin',
    'SR_SMOKE_ADMIN_PASSWORD=12341234',
    'SR_SMOKE_ALLOW_MUTATION=1',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
sr_installed_gate_status_assert_unresolved_count('quiz-ready output', $quizReadyOutput);
sr_installed_gate_status_assert_result_summary('quiz-ready output', $quizReadyOutput);
foreach ([
    'mutation-smoke-allowed: yes',
    "gate\t퀴즈 E2E smoke\tresult=수동 확인 필요\tenvironment=http://127.0.0.1:1\tmemo=quiz E2E smoke is configured; rerun with --run-quiz-smoke",
    "gate\tCKEditor upload/save browser smoke\tresult=수동 확인 필요\tenvironment=http://127.0.0.1:1\tmemo=administrator session and mutation guard configured; manually verify upload adapter, saved HTML sanitizer, and body image access checks",
] as $marker) {
    if ($quizReadyOutput !== '' && !str_contains($quizReadyOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status quiz ready output marker missing: ' . $marker);
    }
}

$assetReadyOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    'SR_SMOKE_FORM_PATH=/paid/form',
    'SR_SMOKE_EXPECT_DEDUPE_TABLE=sr_content_asset_access_logs',
    'SR_SMOKE_EXPECT_DEDUPE_KEY=fixture:dedupe',
    'SR_SMOKE_ALLOW_MUTATION=1',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
sr_installed_gate_status_assert_unresolved_count('asset-ready output', $assetReadyOutput);
sr_installed_gate_status_assert_result_summary('asset-ready output', $assetReadyOutput);
foreach ([
    'mutation-smoke-allowed: yes',
    'asset-dedupe-expectation: configured',
    "gate\t자산/쿠폰/유료 접근권 mutation smoke\tresult=수동 확인 필요\tenvironment=http://127.0.0.1:1\tmemo=asset idempotency smoke is configured; rerun with --run-asset-smoke",
] as $marker) {
    if ($assetReadyOutput !== '' && !str_contains($assetReadyOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status asset ready output marker missing: ' . $marker);
    }
}

$browserQaFailureOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
    '--run-browser-qa',
]);
foreach ([
    'run-browser-qa: yes',
    "gate\tCKEditor asset/fallback browser smoke\tresult=실패\tenvironment=http://127.0.0.1:1\tmemo=npm --prefix .tools/browser-qa run test:ckeditor exit",
] as $marker) {
    if ($browserQaFailureOutput !== '' && !str_contains($browserQaFailureOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status browser QA failure output marker missing: ' . $marker);
    }
}

$performanceReviewReadyOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_PERFORMANCE_REVIEW_READY=1',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'performance-review-ready: yes',
    "gate\t성능 수동 점검\tresult=수동 확인 필요\tenvironment=http://127.0.0.1:1\tmemo=representative data is marked ready; manually verify slow admin lists, sitemap, privacy export bounds, and query plans",
] as $marker) {
    if ($performanceReviewReadyOutput !== '' && !str_contains($performanceReviewReadyOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status performance ready output marker missing: ' . $marker);
    }
}

$fixtureOutput = sr_installed_gate_status_exec([
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
    '--run-privacy-fixtures',
    '--run-performance-fixtures',
]);
sr_installed_gate_status_assert_unresolved_count('fixture output', $fixtureOutput);
sr_installed_gate_status_assert_result_summary('fixture output', $fixtureOutput);
foreach ([
    'run-privacy-fixtures: yes',
    'run-performance-fixtures: yes',
    "gate\t개인정보 export/cleanup smoke\tresult=부분 확인\tenvironment=SQLite contract fixtures",
    "gate\t성능 수동 점검\tresult=부분 확인\tenvironment=static and SQLite runtime fixtures",
    'installed DB smoke still required',
    'installed DB performance review still required',
    'fixture exits: policy=0, baseline=0, pagination=0, board-copy=0, survey-export=0',
    'privacy export runtime checks completed.',
    'privacy cleanup runtime checks completed.',
] as $marker) {
    if ($fixtureOutput !== '' && !str_contains($fixtureOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status fixture output marker missing: ' . $marker);
    }
}

sr_installed_gate_status_require_markers('.tools/bin/release-installed-gate-status.php', [
    'release-installed-gate-status-version: 1',
    '$allowedArgs',
    'Unknown release-installed-gate-status option',
    'unresolved-gates',
    'gate-result-summary',
    'sr_release_gate_status_result_summary',
    'sr_release_gate_status_json',
    'sr_release_gate_status_result_counts',
    'sr_release_gate_status_exit_code',
    '--json',
    '--fail-on-unresolved',
    '--help',
    '--markdown-table',
    '--run-readonly',
    '--run-browser-qa',
    '--run-auth-smoke',
    '--run-quiz-smoke',
    '--run-asset-smoke',
    '--run-privacy-fixtures',
    '--run-performance-fixtures',
    'SR_SMOKE_ALLOW_MUTATION',
    'SR_SMOKE_ADMIN_IDENTIFIER',
    'SR_SMOKE_ADMIN_PASSWORD',
    'SR_SMOKE_IDENTIFIER',
    'SR_SMOKE_PASSWORD',
    'SR_SMOKE_EXPECT_DEDUPE_TABLE',
    'SR_SMOKE_EXPECT_DEDUPE_KEY',
    'SR_PERFORMANCE_REVIEW_READY',
    'performance_review_ready',
    'asset_dedupe_expectation',
    'dedupe row count evidence',
    'incomplete',
    'config/config.php is not readable by current user',
    'sr_release_gate_status_pair_status',
    'sr_release_gate_status_admin_readonly_gate',
    'sr_release_gate_status_ckeditor_upload_save_gate',
    'sr_release_gate_status_file_mode',
    'sr_release_gate_status_file_owner_group',
    'expire-points.php',
    '--dry-run',
    'array_merge([PHP_BINARY, $commandLabel], $commandArgs)',
    'set SR_SMOKE_BASE_URL and use an administrator session',
    'SR_BROWSER_QA_BASE_URL',
    'npm --prefix .tools/browser-qa run test:ckeditor',
    'smoke-community-auth.php',
    'smoke-quiz-e2e.php',
    'smoke-asset-idempotency-http.php',
    'check-privacy-export-runtime.php',
    'check-privacy-cleanup-runtime.php',
    'sr_release_gate_status_privacy_gate($baseUrl, $accountSmokeCredentialStatus, $allowMutationSmoke, $runPrivacyFixtures)',
    'privacy cleanup can mutate data',
    'disposable account and mutation guard configured',
    'check-performance-policy.php',
    'check-performance-baseline.php',
    'check-admin-pagination-runtime.php',
    'check-community-board-copy-limits.php',
    'check-survey-export-runtime.php',
    'sr_release_gate_status_performance_gate($baseUrl, $performanceReviewReady, $runPerformanceFixtures)',
    'representative data is marked ready',
    'installed DB performance review still required',
    'installed DB smoke still required',
    'upload adapter, saved HTML sanitizer',
    'upload/save browser smoke creates or updates content',
    'administrator session and mutation guard configured',
    'do not run against production',
]);

sr_installed_gate_status_require_markers('docs/release-verification-template.md', [
    'php .tools/bin/release-installed-gate-status.php',
    'php .tools/bin/release-installed-gate-status.php --markdown-table',
    'php .tools/bin/release-installed-gate-status.php --json',
    '--fail-on-unresolved',
    'php .tools/bin/release-installed-gate-status.php --run-readonly',
    'php .tools/bin/expire-points.php --dry-run',
    '설치 DB 게이트 상태표',
]);

sr_installed_gate_status_require_markers('docs/verification-status.md', [
    'php .tools/bin/release-installed-gate-status.php',
    '설치 DB 게이트 상태표',
    'gate-result-summary',
    'unresolved-gates',
]);

sr_installed_gate_status_require_markers('docs/smoke-test.md', [
    'php .tools/bin/release-installed-gate-status.php',
    'php .tools/bin/release-installed-gate-status.php --help',
    '알 수 없는 옵션',
    'exit 2',
    'php .tools/bin/release-installed-gate-status.php --json',
    '--fail-on-unresolved',
    '--run-readonly',
    '--run-browser-qa',
    '--run-auth-smoke',
    '--run-quiz-smoke',
    '--run-asset-smoke',
    'SR_SMOKE_ALLOW_MUTATION=1',
    'SR_SMOKE_EXPECT_DEDUPE_TABLE',
    'SR_SMOKE_EXPECT_DEDUPE_KEY',
    '--run-privacy-fixtures',
    '--run-performance-fixtures',
    '부분 확인',
    '대체하지 않는다',
]);

sr_installed_gate_status_require_markers('docs/records/improvement-hardening-verification-2026-06-11.md', [
    'php .tools/bin/release-installed-gate-status.php',
    'release-installed-gate-status-version: 1',
]);

if ($errors !== []) {
    fwrite(STDERR, "installed gate status checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "installed gate status checks completed.\n";
