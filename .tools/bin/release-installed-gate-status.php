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
$allowedArgs = [
    '--markdown-table',
    '--json',
    '--fail-on-unresolved',
    '--run-http-smoke',
    '--run-update-smoke',
    '--run-readonly',
    '--run-admin-readonly',
    '--run-browser-qa',
    '--run-auth-smoke',
    '--run-quiz-smoke',
    '--run-asset-smoke',
    '--run-privacy-smoke',
    '--run-ckeditor-upload-save-smoke',
    '--run-privacy-fixtures',
    '--run-performance-fixtures',
    '--help',
    '-h',
];
$runHttpSmoke = in_array('--run-http-smoke', $args, true);
$runUpdateSmoke = in_array('--run-update-smoke', $args, true);
$runReadonly = in_array('--run-readonly', $args, true);
$runAdminReadonly = in_array('--run-admin-readonly', $args, true);
$runBrowserQa = in_array('--run-browser-qa', $args, true);
$runAuthSmoke = in_array('--run-auth-smoke', $args, true);
$runQuizSmoke = in_array('--run-quiz-smoke', $args, true);
$runAssetSmoke = in_array('--run-asset-smoke', $args, true);
$runPrivacySmoke = in_array('--run-privacy-smoke', $args, true);
$runCkeditorUploadSaveSmoke = in_array('--run-ckeditor-upload-save-smoke', $args, true);
$runPrivacyFixtures = in_array('--run-privacy-fixtures', $args, true);
$runPerformanceFixtures = in_array('--run-performance-fixtures', $args, true);
$markdownTable = in_array('--markdown-table', $args, true);
$jsonOutput = in_array('--json', $args, true);
$failOnUnresolved = in_array('--fail-on-unresolved', $args, true);
$showHelp = in_array('--help', $args, true) || in_array('-h', $args, true);

$unknownArgs = array_values(array_diff($args, $allowedArgs));
if ($unknownArgs !== []) {
    fwrite(STDERR, 'Unknown release-installed-gate-status option: ' . implode(', ', $unknownArgs) . "\n");
    fwrite(STDERR, "Run php .tools/bin/release-installed-gate-status.php --help for supported options.\n");
    exit(2);
}

if ($showHelp) {
    echo rtrim(sr_release_gate_status_help(), "\n") . "\n";
    exit(0);
}

if ($markdownTable && $jsonOutput) {
    fwrite(STDERR, "release-installed-gate-status output options are mutually exclusive: --markdown-table, --json\n");
    fwrite(STDERR, "Run php .tools/bin/release-installed-gate-status.php --help for supported options.\n");
    exit(2);
}

$baseUrl = rtrim((string) (getenv('SR_SMOKE_BASE_URL') ?: ''), '/');
$browserQaBaseUrl = rtrim((string) (getenv('SR_BROWSER_QA_BASE_URL') ?: $baseUrl), '/');
$allowMutationSmoke = getenv('SR_SMOKE_ALLOW_MUTATION') === '1';
$allowPublicMutationUrl = getenv('SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL') === '1';
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

function sr_release_gate_status_help(): string
{
    return <<<'TEXT'
release-installed-gate-status-version: 1
Usage:
  php .tools/bin/release-installed-gate-status.php [options]

Options:
  --markdown-table            Print only the installed DB gate table as Markdown.
  --json                      Print metadata, gates, summary, and unresolved count as JSON.
  --fail-on-unresolved        Exit 1 when any required installed DB gate is not passed.
  --run-http-smoke            Execute the basic non-mutating HTTP smoke.
  --run-update-smoke          Execute existing-install update apply smoke.
  --run-readonly              Execute installed DB read-only CLI gates.
  --run-admin-readonly        Execute authenticated read-only admin screen smoke.
  --run-browser-qa            Execute CKEditor asset/fallback browser smoke.
  --run-auth-smoke            Execute authenticated community smoke.
  --run-quiz-smoke            Execute quiz E2E smoke.
  --run-asset-smoke           Execute asset idempotency HTTP smoke.
  --run-privacy-smoke         Execute privacy export/cleanup HTTP smoke.
  --run-ckeditor-upload-save-smoke
                              Execute CKEditor upload/save HTTP smoke.
  --run-privacy-fixtures      Record SQLite privacy contract fixtures as partial evidence.
  --run-performance-fixtures  Record static/runtime performance fixtures as partial evidence.
  --help                      Show this help.

Output:
  --markdown-table and --json are mutually exclusive.

Environment:
  SR_SMOKE_BASE_URL                 Local/staging base URL for HTTP and manual gates.
  SR_BROWSER_QA_BASE_URL            Optional browser QA base URL override.
  SR_SMOKE_IDENTIFIER               Disposable account identifier.
  SR_SMOKE_PASSWORD                 Disposable account password.
  SR_SMOKE_ADMIN_IDENTIFIER         Local/staging administrator identifier.
  SR_SMOKE_ADMIN_PASSWORD           Local/staging administrator password.
  SR_SMOKE_UPDATE_MODULE_KEY        Optional update smoke module key; default coupon.
  SR_SMOKE_UPDATE_VERSION           Optional update smoke version; default 2026.05.003.
  SR_SMOKE_ALLOW_MUTATION=1         Required before mutation smoke can run.
  SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1
                                    Required before mutation smoke can run against
                                    non-local, non-private, non-test base URLs.
  SR_SMOKE_FORM_PATH                Disposable paid target form path for asset smoke.
  SR_SMOKE_EXPECT_DEDUPE_TABLE      Dedupe table for asset smoke row count evidence.
  SR_SMOKE_EXPECT_DEDUPE_KEY        Dedupe key for asset smoke row count evidence.
  SR_SMOKE_WITHDRAW_CONFIRM_TEXT    Optional privacy withdrawal confirmation text.
Handoff:
  php .tools/bin/release-installed-gate-status.php --run-readonly --fail-on-unresolved
      Rerun read-only installed DB gates as the web-server user or a local/staging-only
      execution user when config/config.php is 0600 and owned by the web-server account.
  SR_SMOKE_BASE_URL=https://staging.example.test \
  SR_SMOKE_ADMIN_IDENTIFIER=<admin> \
  SR_SMOKE_ADMIN_PASSWORD=<password> \
  php .tools/bin/release-installed-gate-status.php --json --fail-on-unresolved
      Record structured gate evidence after local/staging HTTP and administrator
      smoke prerequisites are prepared.

Safety:
  Do not run mutation smoke against production data. If config/config.php is not readable
  by the current CLI user, keep the file permissions tight and rerun as the web-server
  user or a local/staging-only execution user.
TEXT;
}

function sr_release_gate_status_line(string $gate, string $result, string $environment, string $memo): string
{
    return 'gate'
        . "\t" . $gate
        . "\tresult=" . sr_release_gate_status_single_line($result)
        . "\tenvironment=" . sr_release_gate_status_single_line($environment)
        . "\tmemo=" . sr_release_gate_status_single_line($memo);
}

function sr_release_gate_status_markdown_cell(string $value): string
{
    $value = sr_release_gate_status_single_line($value);
    return str_replace(['\\', '|'], ['\\\\', '\\|'], $value);
}

function sr_release_gate_status_markdown_table(array $gates): string
{
    $lines = [
        '| 게이트 | 결과 | 환경 | 메모 |',
        '| --- | --- | --- | --- |',
    ];

    foreach ($gates as $gate) {
        $lines[] = '| '
            . sr_release_gate_status_markdown_cell((string) ($gate['gate'] ?? '')) . ' | '
            . sr_release_gate_status_markdown_cell((string) ($gate['result'] ?? '')) . ' | '
            . sr_release_gate_status_markdown_cell((string) ($gate['environment'] ?? '')) . ' | '
            . sr_release_gate_status_markdown_cell((string) ($gate['memo'] ?? '')) . ' |';
    }

    return implode("\n", $lines) . "\n";
}

function sr_release_gate_status_result_summary(array $gates): string
{
    $order = ['통과', '부분 확인', '수동 확인 필요', '미실행', '환경 미준비', '실패'];
    $counts = [];
    foreach ($order as $result) {
        $counts[$result] = 0;
    }

    foreach ($gates as $gate) {
        $result = (string) ($gate['result'] ?? '');
        if ($result === '') {
            $result = '-';
        }

        if (!array_key_exists($result, $counts)) {
            $counts[$result] = 0;
        }

        $counts[$result]++;
    }

    $parts = [];
    foreach ($counts as $result => $count) {
        $parts[] = $result . '=' . (string) $count;
    }

    return implode(', ', $parts);
}

function sr_release_gate_status_result_counts(array $gates): array
{
    $order = ['통과', '부분 확인', '수동 확인 필요', '미실행', '환경 미준비', '실패'];
    $counts = [];
    foreach ($order as $result) {
        $counts[$result] = 0;
    }

    foreach ($gates as $gate) {
        $result = (string) ($gate['result'] ?? '');
        if ($result === '') {
            $result = '-';
        }

        if (!array_key_exists($result, $counts)) {
            $counts[$result] = 0;
        }

        $counts[$result]++;
    }

    return $counts;
}

function sr_release_gate_status_json(array $metadata, array $gates, int $unresolved): string
{
    $payload = [
        'version' => 1,
        'metadata' => $metadata,
        'gates' => $gates,
        'result_counts' => sr_release_gate_status_result_counts($gates),
        'result_summary' => sr_release_gate_status_result_summary($gates),
        'unresolved_gates' => $unresolved,
    ];
    $payload = sr_release_gate_status_json_safe_value($payload);
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    return is_string($json) ? $json . "\n" : '';
}

function sr_release_gate_status_json_safe_value(mixed $value): mixed
{
    if (is_string($value)) {
        return sr_release_gate_status_utf8_clean($value);
    }

    if (!is_array($value)) {
        return $value;
    }

    $safe = [];
    foreach ($value as $key => $item) {
        $safeKey = is_string($key) ? sr_release_gate_status_utf8_clean($key) : $key;
        $safe[$safeKey] = sr_release_gate_status_json_safe_value($item);
    }

    return $safe;
}

function sr_release_gate_status_exit_code(bool $failOnUnresolved, int $unresolved): int
{
    return $failOnUnresolved && $unresolved > 0 ? 1 : 0;
}

function sr_release_gate_status_single_line(string $value): string
{
    $normalized = preg_replace('/\s+/', ' ', trim($value));
    $normalized = is_string($normalized) ? $normalized : '';
    $normalized = sr_release_gate_status_mask_url_userinfo_in_text($normalized);
    $normalized = sr_release_gate_status_utf8_clean($normalized);
    return $normalized === '' ? '-' : sr_release_gate_status_utf8_truncate($normalized, 220);
}

function sr_release_gate_status_utf8_clean(string $value): string
{
    if ($value === '' || preg_match('//u', $value) === 1) {
        return $value;
    }

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if (is_string($converted) && preg_match('//u', $converted) === 1) {
            return $converted;
        }
    }

    $asciiOnly = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $value);
    return is_string($asciiOnly) ? $asciiOnly : '';
}

function sr_release_gate_status_utf8_truncate(string $value, int $maxChars): string
{
    if ($maxChars < 1 || $value === '') {
        return '';
    }

    if (preg_match_all('/./us', $value, $matches) !== false && isset($matches[0])) {
        return implode('', array_slice($matches[0], 0, $maxChars));
    }

    return substr($value, 0, $maxChars);
}

function sr_release_gate_status_mask_url_userinfo_in_text(string $value): string
{
    $masked = preg_replace_callback(
        '~\bhttps?://[^\s<>"\']+~',
        static function (array $matches): string {
            return sr_release_gate_status_mask_url_userinfo((string) ($matches[0] ?? ''));
        },
        $value
    );

    return is_string($masked) ? $masked : $value;
}

function sr_release_gate_status_mask_url_userinfo(string $url): string
{
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    if (!is_array($parts) || !isset($parts['host']) || (!isset($parts['user']) && !isset($parts['pass']))) {
        return $url;
    }

    $masked = '';
    if (isset($parts['scheme'])) {
        $masked .= (string) $parts['scheme'] . '://';
    } else {
        $masked .= '//';
    }

    $masked .= '***';
    if (isset($parts['pass'])) {
        $masked .= ':***';
    }
    $masked .= '@' . (string) $parts['host'];

    if (isset($parts['port'])) {
        $masked .= ':' . (string) $parts['port'];
    }
    if (isset($parts['path'])) {
        $masked .= (string) $parts['path'];
    }
    if (isset($parts['query'])) {
        $masked .= '?' . (string) $parts['query'];
    }
    if (isset($parts['fragment'])) {
        $masked .= '#' . (string) $parts['fragment'];
    }

    return $masked;
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

function sr_release_gate_status_url(string $baseUrl, string $path): string
{
    return $baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path);
}

function sr_release_gate_status_cookie_header(array $cookies): string
{
    $pairs = [];
    foreach ($cookies as $name => $value) {
        $pairs[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $value);
    }

    return implode('; ', $pairs);
}

function sr_release_gate_status_store_cookies(array $headers, array &$cookies): void
{
    foreach ($headers as $header) {
        if (preg_match('/\ASet-Cookie:\s*([^=;\s]+)=([^;]*)/i', (string) $header, $matches) === 1) {
            $cookies[(string) $matches[1]] = urldecode((string) $matches[2]);
        }
    }
}

function sr_release_gate_status_header_value(array $headers, string $name): string
{
    foreach ($headers as $header) {
        if (stripos((string) $header, $name . ':') === 0) {
            return trim(substr((string) $header, strlen($name) + 1));
        }
    }

    return '';
}

function sr_release_gate_status_http_request(string $baseUrl, string $method, string $path, array $postData, array &$cookies): array
{
    $headers = ["User-Agent: Saanraan-Installed-Gate-Status"];
    if ($cookies !== []) {
        $headers[] = 'Cookie: ' . sr_release_gate_status_cookie_header($cookies);
    }

    $content = '';
    if ($method === 'POST') {
        $content = http_build_query($postData);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $headers[] = 'Content-Length: ' . strlen($content);
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'timeout' => 15,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $content,
        ],
    ]);

    set_error_handler(static function (): bool {
        return true;
    });
    $body = file_get_contents(sr_release_gate_status_url($baseUrl, $path), false, $context);
    restore_error_handler();

    $responseHeaders = function_exists('http_get_last_response_headers')
        ? http_get_last_response_headers()
        : ($http_response_header ?? []);
    $responseHeaders = is_array($responseHeaders) ? $responseHeaders : [];
    sr_release_gate_status_store_cookies($responseHeaders, $cookies);

    $status = 0;
    foreach ($responseHeaders as $header) {
        if (preg_match('#\AHTTP/\S+\s+(\d{3})#', (string) $header, $matches) === 1) {
            $status = (int) $matches[1];
        }
    }

    return [
        'status' => $status,
        'body' => is_string($body) ? $body : '',
        'headers' => $responseHeaders,
        'location' => sr_release_gate_status_header_value($responseHeaders, 'Location'),
    ];
}

function sr_release_gate_status_hidden_value(string $body, string $field): string
{
    $quoted = preg_quote($field, '/');
    if (preg_match_all('/<input\b[^>]*>/i', $body, $matches) === false) {
        return '';
    }

    foreach ($matches[0] as $input) {
        if (preg_match('/\bname="' . $quoted . '"/i', (string) $input) !== 1) {
            continue;
        }
        if (preg_match('/\bvalue="([^"]*)"/i', (string) $input, $valueMatch) === 1) {
            return html_entity_decode((string) $valueMatch[1], ENT_QUOTES, 'UTF-8');
        }
    }

    return '';
}

function sr_release_gate_status_admin_login(string $baseUrl, string $identifier, string $password, array &$cookies): array
{
    $form = sr_release_gate_status_http_request($baseUrl, 'GET', '/login', [], $cookies);
    if ((int) $form['status'] !== 200) {
        return [
            'ok' => false,
            'memo' => 'login form returned HTTP ' . (string) $form['status'],
        ];
    }

    $csrf = sr_release_gate_status_hidden_value((string) $form['body'], 'csrf_token');
    if ($csrf === '') {
        return [
            'ok' => false,
            'memo' => 'login CSRF token not found',
        ];
    }

    $login = sr_release_gate_status_http_request($baseUrl, 'POST', '/login', [
        'csrf_token' => $csrf,
        'identifier' => $identifier,
        'password' => $password,
        'next' => '/admin',
    ], $cookies);
    if (!in_array((int) $login['status'], [302, 303], true)) {
        return [
            'ok' => false,
            'memo' => 'login submit returned HTTP ' . (string) $login['status'],
        ];
    }

    return [
        'ok' => true,
        'memo' => 'login submit returned HTTP ' . (string) $login['status'],
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

function sr_release_gate_status_base_url_requires_public_mutation_override(string $baseUrl): bool
{
    if ($baseUrl === '') {
        return false;
    }

    $host = parse_url($baseUrl, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return true;
    }

    $host = strtolower(trim($host, '[]'));
    if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
        return false;
    }

    if (preg_match('/\A127\./', $host) === 1 || preg_match('/\A10\./', $host) === 1 || preg_match('/\A192\.168\./', $host) === 1) {
        return false;
    }

    if (preg_match('/\A172\.(1[6-9]|2[0-9]|3[0-1])\./', $host) === 1) {
        return false;
    }

    foreach (['.localhost', '.local', '.test', '.invalid'] as $suffix) {
        if (str_ends_with($host, $suffix)) {
            return false;
        }
    }

    return true;
}

function sr_release_gate_status_readonly_command_gate(string $gate, string $commandLabel, bool $canRun, bool $runReadonly, string $skipReason, array $commandArgs = []): array
{
    $displayCommand = trim($commandLabel . ' ' . implode(' ', $commandArgs));
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
            'memo' => 'read-only command available; rerun with --run-readonly to execute ' . $displayCommand,
        ];
    }

    $command = array_merge([PHP_BINARY, $commandLabel], $commandArgs);
    $result = sr_release_gate_status_command($command);
    $exitCode = (int) $result['exit_code'];
    return [
        'gate' => $gate,
        'result' => $exitCode === 0 ? '통과' : '실패',
        'environment' => 'current CLI',
        'memo' => $displayCommand . ' exit ' . (string) $exitCode . '; ' . sr_release_gate_status_single_line((string) $result['output']),
    ];
}

function sr_release_gate_status_browser_qa_gate(string $baseUrl, bool $runBrowserQa): array
{
    $displayBaseUrl = sr_release_gate_status_mask_url_userinfo($baseUrl);

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
            'environment' => $displayBaseUrl,
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
        'environment' => $displayBaseUrl,
        'memo' => 'npm --prefix .tools/browser-qa run test:ckeditor exit ' . (string) $exitCode . '; ' . sr_release_gate_status_single_line((string) $result['output']),
    ];
}

function sr_release_gate_status_http_smoke_gate(string $baseUrl, bool $runHttpSmoke): array
{
    $displayBaseUrl = sr_release_gate_status_mask_url_userinfo($baseUrl);

    if ($baseUrl === '') {
        return [
            'gate' => '기본 HTTP smoke',
            'result' => '미실행',
            'environment' => 'base URL missing',
            'memo' => 'set SR_SMOKE_BASE_URL and run with --run-http-smoke to verify routes, security headers, and protected paths',
        ];
    }

    if (!$runHttpSmoke) {
        return [
            'gate' => '기본 HTTP smoke',
            'result' => '수동 확인 필요',
            'environment' => $displayBaseUrl,
            'memo' => 'basic non-mutating HTTP smoke is available; rerun with --run-http-smoke to execute smoke-http.php',
        ];
    }

    $result = sr_release_gate_status_command([PHP_BINARY, '.tools/bin/smoke-http.php']);
    $exitCode = (int) $result['exit_code'];

    return [
        'gate' => '기본 HTTP smoke',
        'result' => $exitCode === 0 ? '통과' : '실패',
        'environment' => $displayBaseUrl,
        'memo' => 'smoke-http.php exit ' . (string) $exitCode . '; ' . sr_release_gate_status_single_line((string) $result['output']),
    ];
}

function sr_release_gate_status_update_smoke_gate(string $baseUrl, string $adminSmokeCredentialStatus, bool $runUpdateSmoke, bool $allowMutationSmoke, bool $allowPublicMutationUrl, bool $isInstalled, string $unavailableReason): array
{
    $displayBaseUrl = sr_release_gate_status_mask_url_userinfo($baseUrl);

    if (!$isInstalled) {
        return [
            'gate' => '새 설치 또는 업데이트 적용',
            'result' => '환경 미준비',
            'environment' => 'current tree',
            'memo' => $unavailableReason,
        ];
    }

    if ($baseUrl === '') {
        return [
            'gate' => '새 설치 또는 업데이트 적용',
            'result' => '수동 확인 필요',
            'environment' => 'installed current tree',
            'memo' => 'installed lock and readable config are present; set SR_SMOKE_BASE_URL, administrator credentials, SR_SMOKE_ALLOW_MUTATION=1, and rerun with --run-update-smoke to verify update apply flow',
        ];
    }

    if ($adminSmokeCredentialStatus !== 'configured') {
        return [
            'gate' => '새 설치 또는 업데이트 적용',
            'result' => '수동 확인 필요',
            'environment' => $displayBaseUrl,
            'memo' => 'administrator credentials are required to run update apply smoke',
        ];
    }

    if (!$runUpdateSmoke) {
        return [
            'gate' => '새 설치 또는 업데이트 적용',
            'result' => '수동 확인 필요',
            'environment' => $displayBaseUrl,
            'memo' => 'existing-install update apply smoke is available; rerun with --run-update-smoke and SR_SMOKE_ALLOW_MUTATION=1 on disposable local/staging data',
        ];
    }

    if (!$allowMutationSmoke) {
        return [
            'gate' => '새 설치 또는 업데이트 적용',
            'result' => '미실행',
            'environment' => $displayBaseUrl,
            'memo' => 'mutation smoke requested but blocked; set SR_SMOKE_ALLOW_MUTATION=1 only on local/staging disposable data',
        ];
    }

    if (sr_release_gate_status_base_url_requires_public_mutation_override($baseUrl) && !$allowPublicMutationUrl) {
        return [
            'gate' => '새 설치 또는 업데이트 적용',
            'result' => '미실행',
            'environment' => $displayBaseUrl,
            'memo' => 'public-looking mutation URL blocked; set SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1 only for disposable staging data',
        ];
    }

    $result = sr_release_gate_status_command([PHP_BINARY, '.tools/bin/smoke-update-apply.php']);
    $exitCode = (int) $result['exit_code'];

    return [
        'gate' => '새 설치 또는 업데이트 적용',
        'result' => $exitCode === 0 ? '통과' : '실패',
        'environment' => $displayBaseUrl,
        'memo' => 'smoke-update-apply.php exit ' . (string) $exitCode . '; ' . sr_release_gate_status_single_line((string) $result['output']),
    ];
}

function sr_release_gate_status_ckeditor_upload_save_gate(string $baseUrl, string $adminSmokeCredentialStatus, bool $runCkeditorUploadSaveSmoke, bool $allowMutationSmoke, bool $allowPublicMutationUrl): array
{
    $displayBaseUrl = sr_release_gate_status_mask_url_userinfo($baseUrl);

    if ($baseUrl === '') {
        return [
            'gate' => 'CKEditor upload/save browser smoke',
            'result' => '미실행',
            'environment' => 'base URL missing',
            'memo' => 'set SR_SMOKE_BASE_URL, administrator credentials, SR_SMOKE_ALLOW_MUTATION=1, and run with --run-ckeditor-upload-save-smoke for local/staging browser smoke',
        ];
    }

    if ($adminSmokeCredentialStatus !== 'configured') {
        $credentialMemo = $adminSmokeCredentialStatus === 'incomplete'
            ? 'SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD must be provided together'
            : 'requires SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD';

        return [
            'gate' => 'CKEditor upload/save browser smoke',
            'result' => '미실행',
            'environment' => $displayBaseUrl,
            'memo' => $credentialMemo,
        ];
    }

    if (!$allowMutationSmoke) {
        return [
            'gate' => 'CKEditor upload/save browser smoke',
            'result' => '미실행',
            'environment' => $displayBaseUrl,
            'memo' => 'upload/save browser smoke creates or updates content; set SR_SMOKE_ALLOW_MUTATION=1 only for local/staging disposable data',
        ];
    }

    if (sr_release_gate_status_base_url_requires_public_mutation_override($baseUrl) && !$allowPublicMutationUrl) {
        return [
            'gate' => 'CKEditor upload/save browser smoke',
            'result' => '미실행',
            'environment' => $displayBaseUrl,
            'memo' => 'public-looking base URL requires SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1 in addition to SR_SMOKE_ALLOW_MUTATION=1; use only local/staging disposable data',
        ];
    }

    if (!$runCkeditorUploadSaveSmoke) {
        return [
            'gate' => 'CKEditor upload/save browser smoke',
            'result' => '수동 확인 필요',
            'environment' => $displayBaseUrl,
            'memo' => 'CKEditor upload/save smoke is configured; rerun with --run-ckeditor-upload-save-smoke to execute smoke-ckeditor-upload-save.php',
        ];
    }

    $result = sr_release_gate_status_command([PHP_BINARY, '.tools/bin/smoke-ckeditor-upload-save.php']);
    $exitCode = (int) $result['exit_code'];

    return [
        'gate' => 'CKEditor upload/save browser smoke',
        'result' => $exitCode === 0 ? '통과' : '실패',
        'environment' => $displayBaseUrl,
        'memo' => 'smoke-ckeditor-upload-save.php exit ' . (string) $exitCode . '; ' . sr_release_gate_status_single_line((string) $result['output']),
    ];
}

function sr_release_gate_status_auth_smoke_gate(string $baseUrl, string $accountSmokeCredentialStatus, bool $runAuthSmoke, bool $allowMutationSmoke, bool $allowPublicMutationUrl): array
{
    $displayBaseUrl = sr_release_gate_status_mask_url_userinfo($baseUrl);

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
            'environment' => $displayBaseUrl,
            'memo' => $credentialMemo,
        ];
    }

    if (!$allowMutationSmoke) {
        return [
            'gate' => '인증 smoke',
            'result' => '미실행',
            'environment' => $displayBaseUrl,
            'memo' => 'authenticated smoke creates data; set SR_SMOKE_ALLOW_MUTATION=1 only for local/staging disposable data',
        ];
    }

    if (sr_release_gate_status_base_url_requires_public_mutation_override($baseUrl) && !$allowPublicMutationUrl) {
        return [
            'gate' => '인증 smoke',
            'result' => '미실행',
            'environment' => $displayBaseUrl,
            'memo' => 'public-looking base URL requires SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1 in addition to SR_SMOKE_ALLOW_MUTATION=1; use only local/staging disposable data',
        ];
    }

    if (!$runAuthSmoke) {
        return [
            'gate' => '인증 smoke',
            'result' => '수동 확인 필요',
            'environment' => $displayBaseUrl,
            'memo' => 'authenticated smoke is configured; rerun with --run-auth-smoke to execute smoke-community-auth.php',
        ];
    }

    $result = sr_release_gate_status_command([PHP_BINARY, '.tools/bin/smoke-community-auth.php']);
    $exitCode = (int) $result['exit_code'];

    return [
        'gate' => '인증 smoke',
        'result' => $exitCode === 0 ? '통과' : '실패',
        'environment' => $displayBaseUrl,
        'memo' => 'smoke-community-auth.php exit ' . (string) $exitCode . '; ' . sr_release_gate_status_single_line((string) $result['output']),
    ];
}

function sr_release_gate_status_quiz_smoke_gate(string $baseUrl, string $adminSmokeCredentialStatus, bool $runQuizSmoke, bool $allowMutationSmoke, bool $allowPublicMutationUrl): array
{
    $displayBaseUrl = sr_release_gate_status_mask_url_userinfo($baseUrl);

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
            'environment' => $displayBaseUrl,
            'memo' => $credentialMemo,
        ];
    }

    if (!$allowMutationSmoke) {
        return [
            'gate' => '퀴즈 E2E smoke',
            'result' => '미실행',
            'environment' => $displayBaseUrl,
            'memo' => 'quiz E2E smoke creates quiz and attempt data; set SR_SMOKE_ALLOW_MUTATION=1 only for local/staging disposable data',
        ];
    }

    if (sr_release_gate_status_base_url_requires_public_mutation_override($baseUrl) && !$allowPublicMutationUrl) {
        return [
            'gate' => '퀴즈 E2E smoke',
            'result' => '미실행',
            'environment' => $displayBaseUrl,
            'memo' => 'public-looking base URL requires SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1 in addition to SR_SMOKE_ALLOW_MUTATION=1; use only local/staging disposable data',
        ];
    }

    if (!$runQuizSmoke) {
        return [
            'gate' => '퀴즈 E2E smoke',
            'result' => '수동 확인 필요',
            'environment' => $displayBaseUrl,
            'memo' => 'quiz E2E smoke is configured; rerun with --run-quiz-smoke to execute smoke-quiz-e2e.php',
        ];
    }

    $result = sr_release_gate_status_command([PHP_BINARY, '.tools/bin/smoke-quiz-e2e.php']);
    $exitCode = (int) $result['exit_code'];

    return [
        'gate' => '퀴즈 E2E smoke',
        'result' => $exitCode === 0 ? '통과' : '실패',
        'environment' => $displayBaseUrl,
        'memo' => 'smoke-quiz-e2e.php exit ' . (string) $exitCode . '; ' . sr_release_gate_status_single_line((string) $result['output']),
    ];
}

function sr_release_gate_status_asset_smoke_gate(string $baseUrl, string $accountSmokeCredentialStatus, string $assetDedupeExpectationStatus, bool $runAssetSmoke, bool $allowMutationSmoke, bool $allowPublicMutationUrl): array
{
    $formPath = (string) (getenv('SR_SMOKE_FORM_PATH') ?: '');
    $displayBaseUrl = sr_release_gate_status_mask_url_userinfo($baseUrl);

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
            'environment' => $displayBaseUrl,
            'memo' => $credentialMemo,
        ];
    }

    if ($formPath === '') {
        return [
            'gate' => '자산/쿠폰/유료 접근권 mutation smoke',
            'result' => '미실행',
            'environment' => $displayBaseUrl,
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
            'environment' => $displayBaseUrl,
            'memo' => $dedupeMemo,
        ];
    }

    if (!$allowMutationSmoke) {
        return [
            'gate' => '자산/쿠폰/유료 접근권 mutation smoke',
            'result' => '미실행',
            'environment' => $displayBaseUrl,
            'memo' => 'asset idempotency smoke creates financial-like records; set SR_SMOKE_ALLOW_MUTATION=1 only for local/staging disposable data',
        ];
    }

    if (sr_release_gate_status_base_url_requires_public_mutation_override($baseUrl) && !$allowPublicMutationUrl) {
        return [
            'gate' => '자산/쿠폰/유료 접근권 mutation smoke',
            'result' => '미실행',
            'environment' => $displayBaseUrl,
            'memo' => 'public-looking base URL requires SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1 in addition to SR_SMOKE_ALLOW_MUTATION=1; use only local/staging disposable data',
        ];
    }

    if (!$runAssetSmoke) {
        return [
            'gate' => '자산/쿠폰/유료 접근권 mutation smoke',
            'result' => '수동 확인 필요',
            'environment' => $displayBaseUrl,
            'memo' => 'asset idempotency smoke is configured; rerun with --run-asset-smoke to execute smoke-asset-idempotency-http.php',
        ];
    }

    $result = sr_release_gate_status_command([PHP_BINARY, '.tools/bin/smoke-asset-idempotency-http.php']);
    $exitCode = (int) $result['exit_code'];

    return [
        'gate' => '자산/쿠폰/유료 접근권 mutation smoke',
        'result' => $exitCode === 0 ? '통과' : '실패',
        'environment' => $displayBaseUrl,
        'memo' => 'smoke-asset-idempotency-http.php exit ' . (string) $exitCode . '; ' . sr_release_gate_status_single_line((string) $result['output']),
    ];
}

function sr_release_gate_status_admin_readonly_gate(string $gate, string $path, string $expectedText, string $baseUrl, string $adminSmokeCredentialStatus, bool $runAdminReadonly, string $memo): array
{
    $displayBaseUrl = sr_release_gate_status_mask_url_userinfo($baseUrl);

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
            'environment' => $displayBaseUrl,
            'memo' => $credentialMemo . '; ' . $memo,
        ];
    }

    if (!$runAdminReadonly) {
        return [
            'gate' => $gate,
            'result' => '수동 확인 필요',
            'environment' => $displayBaseUrl,
            'memo' => 'administrator session configured; rerun with --run-admin-readonly to verify ' . $path . '; ' . $memo,
        ];
    }

    $cookies = [];
    $login = sr_release_gate_status_admin_login(
        $baseUrl,
        (string) (getenv('SR_SMOKE_ADMIN_IDENTIFIER') ?: ''),
        (string) (getenv('SR_SMOKE_ADMIN_PASSWORD') ?: ''),
        $cookies
    );
    if (($login['ok'] ?? false) !== true) {
        return [
            'gate' => $gate,
            'result' => '실패',
            'environment' => $displayBaseUrl,
            'memo' => 'admin read-only smoke login failed; ' . sr_release_gate_status_single_line((string) ($login['memo'] ?? '')),
        ];
    }

    $screen = sr_release_gate_status_http_request($baseUrl, 'GET', $path, [], $cookies);
    $status = (int) ($screen['status'] ?? 0);
    $body = (string) ($screen['body'] ?? '');
    $hasExpectedText = $expectedText === '' || str_contains($body, $expectedText);

    return [
        'gate' => $gate,
        'result' => $status === 200 && $hasExpectedText ? '통과' : '실패',
        'environment' => $displayBaseUrl,
        'memo' => 'admin read-only smoke GET ' . $path . ' HTTP ' . (string) $status
            . '; expected text ' . ($hasExpectedText ? 'found' : 'missing')
            . '; ' . $memo,
    ];
}

function sr_release_gate_status_privacy_gate(string $baseUrl, string $accountSmokeCredentialStatus, bool $allowMutationSmoke, bool $allowPublicMutationUrl, bool $runPrivacySmoke, bool $runPrivacyFixtures): array
{
    $displayBaseUrl = sr_release_gate_status_mask_url_userinfo($baseUrl);

    if ($baseUrl !== '' && $accountSmokeCredentialStatus === 'configured' && $allowMutationSmoke && !$runPrivacySmoke && !$runPrivacyFixtures) {
        if (sr_release_gate_status_base_url_requires_public_mutation_override($baseUrl) && !$allowPublicMutationUrl) {
            return [
                'gate' => '개인정보 export/cleanup smoke',
                'result' => '미실행',
                'environment' => $displayBaseUrl,
                'memo' => 'public-looking base URL requires SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1 in addition to SR_SMOKE_ALLOW_MUTATION=1; use only local/staging disposable data',
            ];
        }

        return [
            'gate' => '개인정보 export/cleanup smoke',
            'result' => '수동 확인 필요',
            'environment' => $displayBaseUrl,
            'memo' => 'privacy smoke is configured; rerun with --run-privacy-smoke to execute smoke-privacy-export-cleanup.php',
        ];
    }

    if (!$runPrivacySmoke && !$runPrivacyFixtures) {
        if ($baseUrl === '') {
            $memo = 'set SR_SMOKE_BASE_URL, disposable account credentials, and SR_SMOKE_ALLOW_MUTATION=1 for installed DB smoke; use --run-privacy-fixtures only for SQLite contract fixtures';
        } elseif ($accountSmokeCredentialStatus === 'incomplete') {
            $memo = 'SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD must be provided together for disposable account data';
        } elseif ($accountSmokeCredentialStatus === 'missing') {
            $memo = 'requires SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD for disposable account data';
        } else {
            $memo = 'privacy cleanup can mutate data; set SR_SMOKE_ALLOW_MUTATION=1 only for local/staging disposable data';
        }

        return [
            'gate' => '개인정보 export/cleanup smoke',
            'result' => '미실행',
            'environment' => $baseUrl === '' ? 'base URL missing' : $displayBaseUrl,
            'memo' => $memo,
        ];
    }

    if ($runPrivacySmoke) {
        if ($baseUrl === '') {
            return [
                'gate' => '개인정보 export/cleanup smoke',
                'result' => '미실행',
                'environment' => 'base URL missing',
                'memo' => 'set SR_SMOKE_BASE_URL to execute smoke-privacy-export-cleanup.php',
            ];
        }
        if ($accountSmokeCredentialStatus !== 'configured') {
            $credentialMemo = $accountSmokeCredentialStatus === 'incomplete'
                ? 'SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD must be provided together for disposable account data'
                : 'requires SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD for disposable account data';

            return [
                'gate' => '개인정보 export/cleanup smoke',
                'result' => '미실행',
                'environment' => $displayBaseUrl,
                'memo' => $credentialMemo,
            ];
        }
        if (!$allowMutationSmoke) {
            return [
                'gate' => '개인정보 export/cleanup smoke',
                'result' => '미실행',
                'environment' => $displayBaseUrl,
                'memo' => 'privacy export/cleanup smoke withdraws/anonymizes an account; set SR_SMOKE_ALLOW_MUTATION=1 only for local/staging disposable data',
            ];
        }
        if (sr_release_gate_status_base_url_requires_public_mutation_override($baseUrl) && !$allowPublicMutationUrl) {
            return [
                'gate' => '개인정보 export/cleanup smoke',
                'result' => '미실행',
                'environment' => $displayBaseUrl,
                'memo' => 'public-looking base URL requires SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1 in addition to SR_SMOKE_ALLOW_MUTATION=1; use only local/staging disposable data',
            ];
        }

        $result = sr_release_gate_status_command([PHP_BINARY, '.tools/bin/smoke-privacy-export-cleanup.php']);
        $exitCode = (int) $result['exit_code'];
        return [
            'gate' => '개인정보 export/cleanup smoke',
            'result' => $exitCode === 0 ? '통과' : '실패',
            'environment' => $displayBaseUrl,
            'memo' => 'smoke-privacy-export-cleanup.php exit ' . (string) $exitCode . '; ' . sr_release_gate_status_single_line((string) $result['output']),
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

function sr_release_gate_status_performance_gate(string $baseUrl, bool $runPerformanceFixtures): array
{
    $displayBaseUrl = sr_release_gate_status_mask_url_userinfo($baseUrl);

    if (!$runPerformanceFixtures) {
        if ($baseUrl === '') {
            $memo = 'set SR_SMOKE_BASE_URL after representative local/staging data is prepared; use --run-performance-fixtures only for static/runtime fixtures';
        } else {
            $memo = 'manually verify slow admin lists, sitemap, privacy export bounds, and query plans; use --run-performance-fixtures only for static/runtime fixtures';
        }

        return [
            'gate' => '성능 수동 점검',
            'result' => '미실행',
            'environment' => $baseUrl === '' ? 'base URL missing' : $displayBaseUrl,
            'memo' => $memo,
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
$gates[] = sr_release_gate_status_update_smoke_gate($baseUrl, $adminSmokeCredentialStatus, $runUpdateSmoke, $allowMutationSmoke, $allowPublicMutationUrl, $isInstalled, $unavailableReason);
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
$gates[] = sr_release_gate_status_readonly_command_gate(
    '`php .tools/bin/expire-points.php --dry-run`',
    '.tools/bin/expire-points.php',
    $canRunInstalledCli,
    $runReadonly,
    $unavailableReason,
    ['--dry-run']
);
$gates[] = sr_release_gate_status_admin_readonly_gate(
    '/admin/assets/reconciliation',
    '/admin/assets/reconciliation',
    '포인트/금액 정합성 점검',
    $baseUrl,
    $adminSmokeCredentialStatus,
    $runAdminReadonly,
    'verify the read-only reconciliation screen and compare it with reconcile-assets.php output'
);
$gates[] = sr_release_gate_status_admin_readonly_gate(
    '/admin/operations',
    '/admin/operations',
    '운영 상태',
    $baseUrl,
    $adminSmokeCredentialStatus,
    $runAdminReadonly,
    'verify the read-only operations screen, allowed delays, and overdue markers'
);
$gates[] = sr_release_gate_status_http_smoke_gate($baseUrl, $runHttpSmoke);
$gates[] = sr_release_gate_status_auth_smoke_gate($baseUrl, $accountSmokeCredentialStatus, $runAuthSmoke, $allowMutationSmoke, $allowPublicMutationUrl);
$gates[] = sr_release_gate_status_quiz_smoke_gate($baseUrl, $adminSmokeCredentialStatus, $runQuizSmoke, $allowMutationSmoke, $allowPublicMutationUrl);
$gates[] = sr_release_gate_status_asset_smoke_gate($baseUrl, $accountSmokeCredentialStatus, $assetDedupeExpectationStatus, $runAssetSmoke, $allowMutationSmoke, $allowPublicMutationUrl);
$gates[] = sr_release_gate_status_privacy_gate($baseUrl, $accountSmokeCredentialStatus, $allowMutationSmoke, $allowPublicMutationUrl, $runPrivacySmoke, $runPrivacyFixtures);
$gates[] = sr_release_gate_status_browser_qa_gate($browserQaBaseUrl, $runBrowserQa);
$gates[] = sr_release_gate_status_ckeditor_upload_save_gate($baseUrl, $adminSmokeCredentialStatus, $runCkeditorUploadSaveSmoke, $allowMutationSmoke, $allowPublicMutationUrl);
$gates[] = sr_release_gate_status_performance_gate($baseUrl, $runPerformanceFixtures);

$unresolved = 0;
foreach ($gates as $gate) {
    if (($gate['result'] ?? '') !== '통과') {
        $unresolved++;
    }
}

$metadata = [
    'php_version' => PHP_VERSION,
    'installed_lock' => $lockExists ? 'present' : 'missing',
    'config_file' => $configExists ? 'present' : 'missing',
    'config_readable' => $configReadable ? 'yes' : 'no',
    'config_mode' => sr_release_gate_status_file_mode($configPath),
    'config_owner_group' => sr_release_gate_status_file_owner_group($configPath),
    'sr_is_installed' => $isInstalled ? 'yes' : 'no',
    'base_url' => $baseUrl === '' ? '-' : sr_release_gate_status_mask_url_userinfo($baseUrl),
    'browser_qa_base_url' => $browserQaBaseUrl === '' ? '-' : sr_release_gate_status_mask_url_userinfo($browserQaBaseUrl),
    'account_smoke_credentials' => $accountSmokeCredentialStatus,
    'admin_smoke_credentials' => $adminSmokeCredentialStatus,
    'asset_dedupe_expectation' => $assetDedupeExpectationStatus,
    'run_http_smoke' => $runHttpSmoke ? 'yes' : 'no',
    'run_update_smoke' => $runUpdateSmoke ? 'yes' : 'no',
    'run_readonly' => $runReadonly ? 'yes' : 'no',
    'run_admin_readonly' => $runAdminReadonly ? 'yes' : 'no',
    'run_browser_qa' => $runBrowserQa ? 'yes' : 'no',
    'run_auth_smoke' => $runAuthSmoke ? 'yes' : 'no',
    'run_quiz_smoke' => $runQuizSmoke ? 'yes' : 'no',
    'run_asset_smoke' => $runAssetSmoke ? 'yes' : 'no',
    'run_privacy_smoke' => $runPrivacySmoke ? 'yes' : 'no',
    'run_ckeditor_upload_save_smoke' => $runCkeditorUploadSaveSmoke ? 'yes' : 'no',
    'run_privacy_fixtures' => $runPrivacyFixtures ? 'yes' : 'no',
    'run_performance_fixtures' => $runPerformanceFixtures ? 'yes' : 'no',
    'mutation_smoke_allowed' => $allowMutationSmoke ? 'yes' : 'no',
    'public_mutation_url_allowed' => $allowPublicMutationUrl ? 'yes' : 'no',
];
$metadataOutputKeys = [
    'php_version' => 'php-version',
    'installed_lock' => 'installed-lock',
    'config_file' => 'config-file',
    'config_readable' => 'config-readable',
    'config_mode' => 'config-mode',
    'config_owner_group' => 'config-owner-group',
    'sr_is_installed' => 'sr-is-installed',
    'base_url' => 'base-url',
    'browser_qa_base_url' => 'browser-qa-base-url',
    'account_smoke_credentials' => 'account-smoke-credentials',
    'admin_smoke_credentials' => 'admin-smoke-credentials',
    'asset_dedupe_expectation' => 'asset-dedupe-expectation',
    'run_http_smoke' => 'run-http-smoke',
    'run_update_smoke' => 'run-update-smoke',
    'run_readonly' => 'run-readonly',
    'run_admin_readonly' => 'run-admin-readonly',
    'run_browser_qa' => 'run-browser-qa',
    'run_auth_smoke' => 'run-auth-smoke',
    'run_quiz_smoke' => 'run-quiz-smoke',
    'run_asset_smoke' => 'run-asset-smoke',
    'run_privacy_smoke' => 'run-privacy-smoke',
    'run_ckeditor_upload_save_smoke' => 'run-ckeditor-upload-save-smoke',
    'run_privacy_fixtures' => 'run-privacy-fixtures',
    'run_performance_fixtures' => 'run-performance-fixtures',
    'mutation_smoke_allowed' => 'mutation-smoke-allowed',
    'public_mutation_url_allowed' => 'public-mutation-url-allowed',
];

if ($markdownTable) {
    echo sr_release_gate_status_markdown_table($gates);
    exit(sr_release_gate_status_exit_code($failOnUnresolved, $unresolved));
}

if ($jsonOutput) {
    $json = sr_release_gate_status_json($metadata, $gates, $unresolved);
    if ($json === '') {
        fwrite(STDERR, 'release-installed-gate-status JSON encoding failed: ' . json_last_error_msg() . "\n");
        exit(1);
    }

    echo $json;
    exit(sr_release_gate_status_exit_code($failOnUnresolved, $unresolved));
}

echo "release-installed-gate-status-version: 1\n";
foreach ($metadata as $key => $value) {
    echo ($metadataOutputKeys[$key] ?? str_replace('_', '-', $key)) . ': ' . $value . "\n";
}
foreach ($gates as $gate) {
    echo sr_release_gate_status_line(
        (string) ($gate['gate'] ?? ''),
        (string) ($gate['result'] ?? ''),
        (string) ($gate['environment'] ?? ''),
        (string) ($gate['memo'] ?? '')
    ) . "\n";
}
echo 'gate-result-summary: ' . sr_release_gate_status_result_summary($gates) . "\n";
echo 'unresolved-gates: ' . (string) $unresolved . "\n";
echo "release installed gate status completed.\n";
exit(sr_release_gate_status_exit_code($failOnUnresolved, $unresolved));
