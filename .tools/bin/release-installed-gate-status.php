#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

require_once $root . '/core/helpers/runtime.php';

$args = array_slice($argv, 1);
$runReadonly = in_array('--run-readonly', $args, true);
$runBrowserQa = in_array('--run-browser-qa', $args, true);
$runAuthSmoke = in_array('--run-auth-smoke', $args, true);
$runQuizSmoke = in_array('--run-quiz-smoke', $args, true);
$runAssetSmoke = in_array('--run-asset-smoke', $args, true);
$runPrivacyFixtures = in_array('--run-privacy-fixtures', $args, true);
$runPerformanceFixtures = in_array('--run-performance-fixtures', $args, true);
$baseUrl = rtrim((string) (getenv('SR_SMOKE_BASE_URL') ?: ''), '/');
$browserQaBaseUrl = rtrim((string) (getenv('SR_BROWSER_QA_BASE_URL') ?: $baseUrl), '/');
$allowMutationSmoke = getenv('SR_SMOKE_ALLOW_MUTATION') === '1';
$smokeIdentifier = (string) (getenv('SR_SMOKE_IDENTIFIER') ?: '');
$smokePassword = (string) (getenv('SR_SMOKE_PASSWORD') ?: '');
$adminIdentifier = (string) (getenv('SR_SMOKE_ADMIN_IDENTIFIER') ?: '');
$adminPassword = (string) (getenv('SR_SMOKE_ADMIN_PASSWORD') ?: '');
$assetDedupeTable = (string) (getenv('SR_SMOKE_EXPECT_DEDUPE_TABLE') ?: '');
$assetDedupeKey = (string) (getenv('SR_SMOKE_EXPECT_DEDUPE_KEY') ?: '');
$accountSmokeCredentialStatus = sr_release_gate_status_pair_status($smokeIdentifier, $smokePassword);
$adminSmokeCredentialStatus = sr_release_gate_status_pair_status($adminIdentifier, $adminPassword);
$assetDedupeExpectationStatus = sr_release_gate_status_pair_status($assetDedupeTable, $assetDedupeKey);
$configPath = $root . '/config/config.php';
$lockPath = $root . '/storage/installed.lock';
$configExists = is_file($configPath);
$configReadable = is_readable($configPath);
$lockExists = is_file($lockPath);
$isInstalled = sr_is_installed();

function sr_release_gate_status_line(string $gate, string $result, string $environment, string $memo): string
{
    return 'gate'
        . "\t" . $gate
        . "\tresult=" . sr_release_gate_status_single_line($result)
        . "\tenvironment=" . sr_release_gate_status_single_line($environment)
        . "\tmemo=" . sr_release_gate_status_single_line($memo);
}

function sr_release_gate_status_single_line(string $value): string
{
    $normalized = preg_replace('/\s+/', ' ', trim($value));
    $normalized = is_string($normalized) ? $normalized : '';
    return $normalized === '' ? '-' : substr($normalized, 0, 220);
}

function sr_release_gate_status_file_mode(string $path): string
{
    if (!file_exists($path)) {
        return '-';
    }

    $mode = fileperms($path);
    if ($mode === false) {
        return 'unknown';
    }

    return sprintf('%04o', $mode & 0777);
}

function sr_release_gate_status_user_name(int $id): string
{
    if (function_exists('posix_getpwuid')) {
        $info = posix_getpwuid($id);
        if (is_array($info) && is_string($info['name'] ?? null) && $info['name'] !== '') {
            return $info['name'];
        }
    }

    return (string) $id;
}

function sr_release_gate_status_group_name(int $id): string
{
    if (function_exists('posix_getgrgid')) {
        $info = posix_getgrgid($id);
        if (is_array($info) && is_string($info['name'] ?? null) && $info['name'] !== '') {
            return $info['name'];
        }
    }

    return (string) $id;
}

function sr_release_gate_status_file_owner_group(string $path): string
{
    if (!file_exists($path)) {
        return '-';
    }

    $owner = fileowner($path);
    $group = filegroup($path);
    if ($owner === false || $group === false) {
        return 'unknown';
    }

    return sr_release_gate_status_user_name($owner) . ':' . sr_release_gate_status_group_name($group);
}

function sr_release_gate_status_command(array $command): array
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

function sr_release_gate_status_pair_status(string $first, string $second): string
{
    if ($first !== '' && $second !== '') {
        return 'configured';
    }

    if ($first !== '' || $second !== '') {
        return 'incomplete';
    }

    return 'missing';
}

function sr_release_gate_status_readonly_command_gate(string $gate, string $commandLabel, bool $canRun, bool $runReadonly, string $skipReason): array
{
    if (!$canRun) {
        return [
            'gate' => $gate,
            'result' => '환경 미준비',
            'environment' => 'current CLI',
            'memo' => $skipReason,
        ];
    }

    if (!$runReadonly) {
        return [
            'gate' => $gate,
            'result' => '미실행',
            'environment' => 'current CLI',
            'memo' => 'read-only command available; rerun with --run-readonly to execute ' . $commandLabel,
        ];
    }

    $result = sr_release_gate_status_command([PHP_BINARY, $commandLabel]);
    $exitCode = (int) $result['exit_code'];
    return [
        'gate' => $gate,
        'result' => $exitCode === 0 ? '통과' : '실패',
        'environment' => 'current CLI',
        'memo' => $commandLabel . ' exit ' . (string) $exitCode . '; ' . sr_release_gate_status_single_line((string) $result['output']),
    ];
}

function sr_release_gate_status_browser_qa_gate(string $baseUrl, bool $runBrowserQa): array
{
    if ($baseUrl === '') {
        return [
            'gate' => 'CKEditor asset/fallback browser smoke',
            'result' => '미실행',
            'environment' => 'base URL missing',
            'memo' => 'set SR_BROWSER_QA_BASE_URL or SR_SMOKE_BASE_URL and run with --run-browser-qa',
        ];
    }

    if (!$runBrowserQa) {
        return [
            'gate' => 'CKEditor asset/fallback browser smoke',
            'result' => '수동 확인 필요',
            'environment' => $baseUrl,
            'memo' => 'browser QA available; rerun with --run-browser-qa to execute npm --prefix .tools/browser-qa run test:ckeditor',
        ];
    }

    putenv('SR_BROWSER_QA_BASE_URL=' . $baseUrl);
    $_ENV['SR_BROWSER_QA_BASE_URL'] = $baseUrl;
    $result = sr_release_gate_status_command(['npm', '--prefix', '.tools/browser-qa', 'run', 'test:ckeditor']);
    $exitCode = (int) $result['exit_code'];

    return [
        'gate' => 'CKEditor asset/fallback browser smoke',
        'result' => $exitCode === 0 ? '통과' : '실패',
        'environment' => $baseUrl,
        'memo' => 'npm --prefix .tools/browser-qa run test:ckeditor exit ' . (string) $exitCode . '; ' . sr_release_gate_status_single_line((string) $result['output']),
    ];
}

function sr_release_gate_status_auth_smoke_gate(string $baseUrl, string $accountSmokeCredentialStatus, bool $runAuthSmoke, bool $allowMutationSmoke): array
{
    if ($baseUrl === '') {
        return [
            'gate' => '인증 smoke',
            'result' => '미실행',
            'environment' => 'base URL missing',
            'memo' => 'set SR_SMOKE_BASE_URL for local/staging authenticated smoke; do not run against production',
        ];
    }

    if ($accountSmokeCredentialStatus !== 'configured') {
        $credentialMemo = $accountSmokeCredentialStatus === 'incomplete'
            ? 'SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD must be provided together for a local/staging test account'
            : 'requires SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD for a local/staging test account';

        return [
            'gate' => '인증 smoke',
            'result' => '미실행',
            'environment' => $baseUrl,
            'memo' => $credentialMemo,
        ];
    }

    if (!$allowMutationSmoke) {
        return [
            'gate' => '인증 smoke',
            'result' => '미실행',
            'environment' => $baseUrl,
            'memo' => 'authenticated smoke creates data; set SR_SMOKE_ALLOW_MUTATION=1 only for local/staging disposable data',
        ];
    }

    if (!$runAuthSmoke) {
        return [
            'gate' => '인증 smoke',
            'result' => '수동 확인 필요',
            'environment' => $baseUrl,
            'memo' => 'authenticated smoke is configured; rerun with --run-auth-smoke to execute smoke-community-auth.php',
        ];
    }

    $result = sr_release_gate_status_command([PHP_BINARY, '.tools/bin/smoke-community-auth.php']);
    $exitCode = (int) $result['exit_code'];

    return [
        'gate' => '인증 smoke',
        'result' => $exitCode === 0 ? '통과' : '실패',
        'environment' => $baseUrl,
        'memo' => 'smoke-community-auth.php exit ' . (string) $exitCode . '; ' . sr_release_gate_status_single_line((string) $result['output']),
    ];
}

function sr_release_gate_status_quiz_smoke_gate(string $baseUrl, string $adminSmokeCredentialStatus, bool $runQuizSmoke, bool $allowMutationSmoke): array
{
    if ($baseUrl === '') {
        return [
            'gate' => '퀴즈 E2E smoke',
            'result' => '미실행',
            'environment' => 'base URL missing',
            'memo' => 'set SR_SMOKE_BASE_URL for local/staging quiz E2E smoke; do not run against production',
        ];
    }

    if ($adminSmokeCredentialStatus !== 'configured') {
        $credentialMemo = $adminSmokeCredentialStatus === 'incomplete'
            ? 'SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD must be provided together for quiz E2E administrator session'
            : 'requires SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD for quiz E2E administrator session';

        return [
            'gate' => '퀴즈 E2E smoke',
            'result' => '미실행',
            'environment' => $baseUrl,
            'memo' => $credentialMemo,
        ];
    }

    if (!$allowMutationSmoke) {
        return [
            'gate' => '퀴즈 E2E smoke',
            'result' => '미실행',
            'environment' => $baseUrl,
            'memo' => 'quiz E2E smoke creates quiz and attempt data; set SR_SMOKE_ALLOW_MUTATION=1 only for local/staging disposable data',
        ];
    }

    if (!$runQuizSmoke) {
        return [
            'gate' => '퀴즈 E2E smoke',
            'result' => '수동 확인 필요',
            'environment' => $baseUrl,
            'memo' => 'quiz E2E smoke is configured; rerun with --run-quiz-smoke to execute smoke-quiz-e2e.php',
        ];
    }

    $result = sr_release_gate_status_command([PHP_BINARY, '.tools/bin/smoke-quiz-e2e.php']);
    $exitCode = (int) $result['exit_code'];

    return [
        'gate' => '퀴즈 E2E smoke',
        'result' => $exitCode === 0 ? '통과' : '실패',
        'environment' => $baseUrl,
        'memo' => 'smoke-quiz-e2e.php exit ' . (string) $exitCode . '; ' . sr_release_gate_status_single_line((string) $result['output']),
    ];
}

function sr_release_gate_status_asset_smoke_gate(string $baseUrl, string $accountSmokeCredentialStatus, string $assetDedupeExpectationStatus, bool $runAssetSmoke, bool $allowMutationSmoke): array
{
    $formPath = (string) (getenv('SR_SMOKE_FORM_PATH') ?: '');

    if ($baseUrl === '') {
        return [
            'gate' => '자산/쿠폰/유료 접근권 mutation smoke',
            'result' => '미실행',
            'environment' => 'base URL missing',
            'memo' => 'set SR_SMOKE_BASE_URL for local/staging asset idempotency smoke; do not run against production',
        ];
    }

    if ($accountSmokeCredentialStatus !== 'configured') {
        $credentialMemo = $accountSmokeCredentialStatus === 'incomplete'
            ? 'SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD must be provided together for disposable paid target data'
            : 'requires SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD for disposable paid target data';

        return [
            'gate' => '자산/쿠폰/유료 접근권 mutation smoke',
            'result' => '미실행',
            'environment' => $baseUrl,
            'memo' => $credentialMemo,
        ];
    }

    if ($formPath === '') {
        return [
            'gate' => '자산/쿠폰/유료 접근권 mutation smoke',
            'result' => '미실행',
            'environment' => $baseUrl,
            'memo' => 'requires SR_SMOKE_FORM_PATH for disposable paid target data',
        ];
    }

    if ($assetDedupeExpectationStatus !== 'configured') {
        $dedupeMemo = $assetDedupeExpectationStatus === 'incomplete'
            ? 'SR_SMOKE_EXPECT_DEDUPE_TABLE and SR_SMOKE_EXPECT_DEDUPE_KEY must be provided together for dedupe row count evidence'
            : 'requires SR_SMOKE_EXPECT_DEDUPE_TABLE and SR_SMOKE_EXPECT_DEDUPE_KEY for dedupe row count evidence';

        return [
            'gate' => '자산/쿠폰/유료 접근권 mutation smoke',
            'result' => '미실행',
            'environment' => $baseUrl,
            'memo' => $dedupeMemo,
        ];
    }

    if (!$allowMutationSmoke) {
        return [
            'gate' => '자산/쿠폰/유료 접근권 mutation smoke',
            'result' => '미실행',
            'environment' => $baseUrl,
            'memo' => 'asset idempotency smoke creates financial-like records; set SR_SMOKE_ALLOW_MUTATION=1 only for local/staging disposable data',
        ];
    }

    if (!$runAssetSmoke) {
        return [
            'gate' => '자산/쿠폰/유료 접근권 mutation smoke',
            'result' => '수동 확인 필요',
            'environment' => $baseUrl,
            'memo' => 'asset idempotency smoke is configured; rerun with --run-asset-smoke to execute smoke-asset-idempotency-http.php',
        ];
    }

    $result = sr_release_gate_status_command([PHP_BINARY, '.tools/bin/smoke-asset-idempotency-http.php']);
    $exitCode = (int) $result['exit_code'];

    return [
        'gate' => '자산/쿠폰/유료 접근권 mutation smoke',
        'result' => $exitCode === 0 ? '통과' : '실패',
        'environment' => $baseUrl,
        'memo' => 'smoke-asset-idempotency-http.php exit ' . (string) $exitCode . '; ' . sr_release_gate_status_single_line((string) $result['output']),
    ];
}

function sr_release_gate_status_admin_readonly_gate(string $gate, string $baseUrl, string $adminSmokeCredentialStatus, string $memo): array
{
    if ($baseUrl === '') {
        return [
            'gate' => $gate,
            'result' => '미실행',
            'environment' => 'base URL missing',
            'memo' => 'set SR_SMOKE_BASE_URL and use an administrator session to verify the read-only screen',
        ];
    }

    if ($adminSmokeCredentialStatus !== 'configured') {
        $credentialMemo = $adminSmokeCredentialStatus === 'incomplete'
            ? 'SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD must be provided together for administrator session'
            : 'requires SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD for administrator session';

        return [
            'gate' => $gate,
            'result' => '미실행',
            'environment' => $baseUrl,
            'memo' => $credentialMemo . '; ' . $memo,
        ];
    }

    return [
        'gate' => $gate,
        'result' => '수동 확인 필요',
        'environment' => $baseUrl,
        'memo' => 'administrator session configured; ' . $memo,
    ];
}

function sr_release_gate_status_privacy_gate(bool $runPrivacyFixtures): array
{
    if (!$runPrivacyFixtures) {
        return [
            'gate' => '개인정보 export/cleanup smoke',
            'result' => '미실행',
            'environment' => 'local/staging dummy account',
            'memo' => 'requires disposable account data for installed DB smoke; use --run-privacy-fixtures only for SQLite contract fixtures',
        ];
    }

    $exportResult = sr_release_gate_status_command([PHP_BINARY, '.tools/bin/check-privacy-export-runtime.php']);
    $cleanupResult = sr_release_gate_status_command([PHP_BINARY, '.tools/bin/check-privacy-cleanup-runtime.php']);
    $exportExitCode = (int) $exportResult['exit_code'];
    $cleanupExitCode = (int) $cleanupResult['exit_code'];
    $passed = $exportExitCode === 0 && $cleanupExitCode === 0;

    return [
        'gate' => '개인정보 export/cleanup smoke',
        'result' => $passed ? '부분 확인' : '실패',
        'environment' => 'SQLite contract fixtures',
        'memo' => 'installed DB smoke still required; export fixture exit ' . (string) $exportExitCode
            . ', cleanup fixture exit ' . (string) $cleanupExitCode
            . '; ' . sr_release_gate_status_single_line((string) $exportResult['output'] . ' ' . (string) $cleanupResult['output']),
    ];
}

function sr_release_gate_status_performance_gate(bool $runPerformanceFixtures): array
{
    if (!$runPerformanceFixtures) {
        return [
            'gate' => '성능 수동 점검',
            'result' => '미실행',
            'environment' => 'installed DB with data',
            'memo' => 'requires representative data for slow list, sitemap, and privacy export checks; use --run-performance-fixtures only for static/runtime fixtures',
        ];
    }

    $commands = [
        'policy' => '.tools/bin/check-performance-policy.php',
        'baseline' => '.tools/bin/check-performance-baseline.php',
        'pagination' => '.tools/bin/check-admin-pagination-runtime.php',
        'board-copy' => '.tools/bin/check-community-board-copy-limits.php',
        'survey-export' => '.tools/bin/check-survey-export-runtime.php',
    ];
    $exitCodes = [];
    $outputs = [];
    foreach ($commands as $label => $command) {
        $result = sr_release_gate_status_command([PHP_BINARY, $command]);
        $exitCodes[$label] = (int) $result['exit_code'];
        $outputs[] = $command . ' exit ' . (string) $exitCodes[$label] . ' ' . (string) $result['output'];
    }
    $passed = !in_array(false, array_map(static fn (int $code): bool => $code === 0, $exitCodes), true);
    $summary = [];
    foreach ($exitCodes as $label => $exitCode) {
        $summary[] = $label . '=' . (string) $exitCode;
    }
    $memo = 'installed DB performance review still required; fixture exits: ' . implode(', ', $summary);
    if (!$passed) {
        $memo .= '; ' . sr_release_gate_status_single_line(implode(' ', $outputs));
    }

    return [
        'gate' => '성능 수동 점검',
        'result' => $passed ? '부분 확인' : '실패',
        'environment' => 'static and SQLite runtime fixtures',
        'memo' => $memo,
    ];
}

$unavailableReason = '';
if (!$configExists) {
    $unavailableReason = 'config/config.php missing';
} elseif (!$configReadable) {
    $unavailableReason = 'config/config.php is not readable by current user';
} elseif (!$lockExists) {
    $unavailableReason = 'storage/installed.lock missing';
} elseif (!$isInstalled) {
    $unavailableReason = 'sr_is_installed() returned false';
}
$canRunInstalledCli = $unavailableReason === '';

$gates = [];
$gates[] = [
    'gate' => '새 설치 또는 업데이트 적용',
    'result' => $isInstalled ? '수동 확인 필요' : '환경 미준비',
    'environment' => $isInstalled ? 'installed current tree' : 'current tree',
    'memo' => $isInstalled ? 'installed lock and readable config are present; verify pending update state manually' : $unavailableReason,
];
$gates[] = sr_release_gate_status_readonly_command_gate(
    '`php .tools/bin/reconcile-assets.php`',
    '.tools/bin/reconcile-assets.php',
    $canRunInstalledCli,
    $runReadonly,
    $unavailableReason
);
$gates[] = sr_release_gate_status_readonly_command_gate(
    '`php .tools/bin/ops-status.php`',
    '.tools/bin/ops-status.php',
    $canRunInstalledCli,
    $runReadonly,
    $unavailableReason
);
$gates[] = sr_release_gate_status_admin_readonly_gate(
    '/admin/assets/reconciliation',
    $baseUrl,
    $adminSmokeCredentialStatus,
    'verify the read-only reconciliation screen and compare it with reconcile-assets.php output'
);
$gates[] = sr_release_gate_status_admin_readonly_gate(
    '/admin/operations',
    $baseUrl,
    $adminSmokeCredentialStatus,
    'verify the read-only operations screen, allowed delays, and overdue markers'
);
$gates[] = sr_release_gate_status_auth_smoke_gate($baseUrl, $accountSmokeCredentialStatus, $runAuthSmoke, $allowMutationSmoke);
$gates[] = sr_release_gate_status_quiz_smoke_gate($baseUrl, $adminSmokeCredentialStatus, $runQuizSmoke, $allowMutationSmoke);
$gates[] = sr_release_gate_status_asset_smoke_gate($baseUrl, $accountSmokeCredentialStatus, $assetDedupeExpectationStatus, $runAssetSmoke, $allowMutationSmoke);
$gates[] = sr_release_gate_status_privacy_gate($runPrivacyFixtures);
$gates[] = sr_release_gate_status_browser_qa_gate($browserQaBaseUrl, $runBrowserQa);
$gates[] = [
    'gate' => 'CKEditor upload/save browser smoke',
    'result' => '미실행',
    'environment' => 'browser + installed DB',
    'memo' => 'requires browser session, installed DB, upload adapter, saved HTML sanitizer, and body image access checks',
];
$gates[] = sr_release_gate_status_performance_gate($runPerformanceFixtures);

$unresolved = 0;
foreach ($gates as $gate) {
    if (($gate['result'] ?? '') !== '통과') {
        $unresolved++;
    }
}

echo "release-installed-gate-status-version: 1\n";
echo 'php-version: ' . PHP_VERSION . "\n";
echo 'installed-lock: ' . ($lockExists ? 'present' : 'missing') . "\n";
echo 'config-file: ' . ($configExists ? 'present' : 'missing') . "\n";
echo 'config-readable: ' . ($configReadable ? 'yes' : 'no') . "\n";
echo 'config-mode: ' . sr_release_gate_status_file_mode($configPath) . "\n";
echo 'config-owner-group: ' . sr_release_gate_status_file_owner_group($configPath) . "\n";
echo 'sr-is-installed: ' . ($isInstalled ? 'yes' : 'no') . "\n";
echo 'base-url: ' . ($baseUrl === '' ? '-' : $baseUrl) . "\n";
echo 'browser-qa-base-url: ' . ($browserQaBaseUrl === '' ? '-' : $browserQaBaseUrl) . "\n";
echo 'account-smoke-credentials: ' . $accountSmokeCredentialStatus . "\n";
echo 'admin-smoke-credentials: ' . $adminSmokeCredentialStatus . "\n";
echo 'asset-dedupe-expectation: ' . $assetDedupeExpectationStatus . "\n";
echo 'run-readonly: ' . ($runReadonly ? 'yes' : 'no') . "\n";
echo 'run-browser-qa: ' . ($runBrowserQa ? 'yes' : 'no') . "\n";
echo 'run-auth-smoke: ' . ($runAuthSmoke ? 'yes' : 'no') . "\n";
echo 'run-quiz-smoke: ' . ($runQuizSmoke ? 'yes' : 'no') . "\n";
echo 'run-asset-smoke: ' . ($runAssetSmoke ? 'yes' : 'no') . "\n";
echo 'run-privacy-fixtures: ' . ($runPrivacyFixtures ? 'yes' : 'no') . "\n";
echo 'run-performance-fixtures: ' . ($runPerformanceFixtures ? 'yes' : 'no') . "\n";
echo 'mutation-smoke-allowed: ' . ($allowMutationSmoke ? 'yes' : 'no') . "\n";
foreach ($gates as $gate) {
    echo sr_release_gate_status_line(
        (string) ($gate['gate'] ?? ''),
        (string) ($gate['result'] ?? ''),
        (string) ($gate['environment'] ?? ''),
        (string) ($gate['memo'] ?? '')
    ) . "\n";
}
echo 'unresolved-gates: ' . (string) $unresolved . "\n";
echo "release installed gate status completed.\n";
