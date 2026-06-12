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
$baseUrl = rtrim((string) (getenv('SR_SMOKE_BASE_URL') ?: ''), '/');
$browserQaBaseUrl = rtrim((string) (getenv('SR_BROWSER_QA_BASE_URL') ?: $baseUrl), '/');
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
$gates[] = [
    'gate' => '/admin/assets/reconciliation',
    'result' => $baseUrl === '' ? '미실행' : '수동 확인 필요',
    'environment' => $baseUrl === '' ? 'base URL missing' : $baseUrl,
    'memo' => $baseUrl === '' ? 'set SR_SMOKE_BASE_URL and use an administrator session to verify the read-only screen' : 'administrator session required',
];
$gates[] = [
    'gate' => '/admin/operations',
    'result' => $baseUrl === '' ? '미실행' : '수동 확인 필요',
    'environment' => $baseUrl === '' ? 'base URL missing' : $baseUrl,
    'memo' => $baseUrl === '' ? 'set SR_SMOKE_BASE_URL and use an administrator session to verify the read-only screen' : 'administrator session required',
];
$gates[] = [
    'gate' => '인증 smoke',
    'result' => '미실행',
    'environment' => 'local/staging test account',
    'memo' => 'requires SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD; do not run against production',
];
$gates[] = [
    'gate' => '자산/쿠폰/유료 접근권 mutation smoke',
    'result' => '미실행',
    'environment' => 'local/staging dummy data',
    'memo' => 'requires disposable data because it creates or changes financial-like records',
];
$gates[] = [
    'gate' => '개인정보 export/cleanup smoke',
    'result' => '미실행',
    'environment' => 'local/staging dummy account',
    'memo' => 'requires disposable account data because cleanup mutates records',
];
$gates[] = sr_release_gate_status_browser_qa_gate($browserQaBaseUrl, $runBrowserQa);
$gates[] = [
    'gate' => 'CKEditor upload/save browser smoke',
    'result' => '미실행',
    'environment' => 'browser + installed DB',
    'memo' => 'requires browser session, installed DB, upload adapter, saved HTML sanitizer, and body image access checks',
];
$gates[] = [
    'gate' => '성능 수동 점검',
    'result' => '미실행',
    'environment' => 'installed DB with data',
    'memo' => 'requires representative data for slow list, sitemap, and privacy export checks',
];

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
echo 'run-readonly: ' . ($runReadonly ? 'yes' : 'no') . "\n";
echo 'run-browser-qa: ' . ($runBrowserQa ? 'yes' : 'no') . "\n";
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
