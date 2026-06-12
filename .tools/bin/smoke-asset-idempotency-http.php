#!/usr/bin/env php
<?php

declare(strict_types=1);

define('SR_ROOT', dirname(__DIR__, 2));

function sr_asset_http_smoke_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return is_string($value) && $value !== '' ? $value : $default;
}

function sr_asset_http_smoke_bool(string $key): bool
{
    return in_array(strtolower(sr_asset_http_smoke_env($key)), ['1', 'true', 'yes', 'on'], true);
}

function sr_asset_http_smoke_usage(): string
{
    return "Usage: SR_SMOKE_ALLOW_MUTATION=1 SR_SMOKE_BASE_URL=http://127.0.0.1:8080 SR_SMOKE_IDENTIFIER=writer@example.com SR_SMOKE_PASSWORD=password SR_SMOKE_FORM_PATH=/content/view?slug=paid SR_SMOKE_POST_PATH=/content/view?slug=paid SR_SMOKE_EXTRA_POST='asset_confirm=1' php .tools/bin/smoke-asset-idempotency-http.php\n"
        . "Optional: SR_SMOKE_PARALLEL_REQUESTS=6 SR_SMOKE_TOKEN_FIELD=asset_request_token SR_SMOKE_SUCCESS_STATUSES=200,302,303 SR_SMOKE_EXPECT_DEDUPE_TABLE=sr_content_asset_access_logs SR_SMOKE_EXPECT_DEDUPE_KEY='...' SR_SMOKE_EXPECT_DEDUPE_COLUMN=dedupe_key SR_SMOKE_EXPECT_DEDUPE_FRESH=1\n"
        . "This smoke performs mutating duplicate POST requests. Run only against local or staging disposable data.\n";
}

function sr_asset_http_smoke_fail(string $message, int $exitCode = 1): never
{
    fwrite(STDERR, "saanraan asset idempotency HTTP smoke failed:\n- " . $message . "\n");
    exit($exitCode);
}

function sr_asset_http_smoke_url(string $baseUrl, string $path): string
{
    return $baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path);
}

function sr_asset_http_smoke_header_value(array $headers, string $name): string
{
    foreach ($headers as $header) {
        if (stripos((string) $header, $name . ':') === 0) {
            return trim(substr((string) $header, strlen($name) + 1));
        }
    }

    return '';
}

function sr_asset_http_smoke_cookie_header(array $cookies): string
{
    $pairs = [];
    foreach ($cookies as $name => $value) {
        $pairs[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $value);
    }

    return implode('; ', $pairs);
}

function sr_asset_http_smoke_store_cookies(array $headers, array &$cookies): void
{
    foreach ($headers as $header) {
        if (preg_match('/\ASet-Cookie:\s*([^=;\s]+)=([^;]*)/i', (string) $header, $matches) === 1) {
            $cookies[(string) $matches[1]] = urldecode((string) $matches[2]);
        }
    }
}

function sr_asset_http_smoke_request(string $baseUrl, string $method, string $path, array $postData, array &$cookies): array
{
    $headers = ["User-Agent: Saanraan-Asset-Idempotency-Smoke"];
    if ($cookies !== []) {
        $headers[] = 'Cookie: ' . sr_asset_http_smoke_cookie_header($cookies);
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
    $body = file_get_contents(sr_asset_http_smoke_url($baseUrl, $path), false, $context);
    restore_error_handler();

    $responseHeaders = function_exists('http_get_last_response_headers')
        ? http_get_last_response_headers()
        : ($http_response_header ?? []);
    sr_asset_http_smoke_store_cookies($responseHeaders, $cookies);

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
        'location' => sr_asset_http_smoke_header_value($responseHeaders, 'Location'),
    ];
}

function sr_asset_http_smoke_hidden_value(string $body, string $field): string
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

function sr_asset_http_smoke_login(string $baseUrl, string $identifier, string $password, array &$cookies): void
{
    $form = sr_asset_http_smoke_request($baseUrl, 'GET', '/login', [], $cookies);
    if ((int) $form['status'] !== 200) {
        sr_asset_http_smoke_fail('Login form returned HTTP ' . (string) $form['status'] . '.', 2);
    }

    $csrf = sr_asset_http_smoke_hidden_value((string) $form['body'], 'csrf_token');
    if ($csrf === '') {
        sr_asset_http_smoke_fail('Login CSRF token not found.', 2);
    }

    $login = sr_asset_http_smoke_request($baseUrl, 'POST', '/login', [
        'csrf_token' => $csrf,
        'identifier' => $identifier,
        'password' => $password,
        'next' => '/',
    ], $cookies);
    if (!in_array((int) $login['status'], [302, 303], true)) {
        sr_asset_http_smoke_fail('Login submit returned HTTP ' . (string) $login['status'] . '.', 2);
    }
}

function sr_asset_http_smoke_parse_extra_post(string $encoded): array
{
    $result = [];
    if ($encoded === '') {
        return $result;
    }

    parse_str($encoded, $result);
    if (!is_array($result)) {
        return [];
    }

    foreach ($result as $key => $value) {
        if (!is_string($key) || is_array($value)) {
            unset($result[$key]);
        }
    }

    return array_map(static fn ($value): string => (string) $value, $result);
}

function sr_asset_http_smoke_parse_statuses(string $encoded): array
{
    $statuses = [];
    foreach (explode(',', $encoded) as $status) {
        $status = trim($status);
        if ($status === '') {
            continue;
        }
        if (!preg_match('/\A[1-5][0-9]{2}\z/', $status)) {
            sr_asset_http_smoke_fail('SR_SMOKE_SUCCESS_STATUSES contains an invalid HTTP status: ' . $status, 2);
        }
        $statuses[(int) $status] = true;
    }

    if ($statuses === []) {
        sr_asset_http_smoke_fail('SR_SMOKE_SUCCESS_STATUSES must contain at least one HTTP status.', 2);
    }

    return $statuses;
}

function sr_asset_http_smoke_parallel_posts(string $baseUrl, string $path, array $postData, array $cookies, int $requestCount): array
{
    if (!function_exists('curl_multi_init')) {
        sr_asset_http_smoke_fail('curl extension with curl_multi support is required for parallel HTTP smoke.', 2);
    }

    $multi = curl_multi_init();
    if ($multi === false) {
        sr_asset_http_smoke_fail('Could not initialize curl_multi.', 2);
    }

    $handles = [];
    $cookieHeader = sr_asset_http_smoke_cookie_header($cookies);
    $body = http_build_query($postData);

    for ($index = 0; $index < $requestCount; $index++) {
        $ch = curl_init(sr_asset_http_smoke_url($baseUrl, $path));
        if ($ch === false) {
            continue;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => array_values(array_filter([
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: Saanraan-Asset-Idempotency-Smoke',
                $cookieHeader !== '' ? 'Cookie: ' . $cookieHeader : '',
            ])),
        ]);
        curl_multi_add_handle($multi, $ch);
        $handles[] = $ch;
    }

    do {
        $status = curl_multi_exec($multi, $running);
        if ($running > 0) {
            curl_multi_select($multi, 1.0);
        }
    } while ($running > 0 && $status === CURLM_OK);

    $responses = [];
    foreach ($handles as $ch) {
        $raw = curl_multi_getcontent($ch);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responses[] = [
            'status' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            'body' => is_string($raw) ? substr($raw, $headerSize) : '',
            'error' => curl_error($ch),
        ];
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }
    curl_multi_close($multi);

    return $responses;
}

function sr_asset_http_smoke_dedupe_count(string $table, string $column, string $dedupeKey): ?int
{
    if ($table === '' || $dedupeKey === '') {
        return null;
    }
    if (!preg_match('/\Asr_[a-z0-9_]+\z/', $table) || !preg_match('/\A[a-z0-9_]+\z/', $column)) {
        sr_asset_http_smoke_fail('Dedupe table or column contains unsafe characters.', 2);
    }

    require_once SR_ROOT . '/core/helpers.php';
    $config = sr_load_config();
    sr_set_runtime_config($config);
    $pdo = sr_db($config);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' = :dedupe_key');
    $stmt->execute(['dedupe_key' => $dedupeKey]);

    return (int) $stmt->fetchColumn();
}

$baseUrl = rtrim(sr_asset_http_smoke_env('SR_SMOKE_BASE_URL'), '/');
$identifier = sr_asset_http_smoke_env('SR_SMOKE_IDENTIFIER');
$password = sr_asset_http_smoke_env('SR_SMOKE_PASSWORD');
$formPath = sr_asset_http_smoke_env('SR_SMOKE_FORM_PATH');
$postPath = sr_asset_http_smoke_env('SR_SMOKE_POST_PATH', $formPath);
$tokenField = sr_asset_http_smoke_env('SR_SMOKE_TOKEN_FIELD', 'asset_request_token');
$extraPost = sr_asset_http_smoke_parse_extra_post(sr_asset_http_smoke_env('SR_SMOKE_EXTRA_POST'));
$requestCount = max(2, min(12, (int) sr_asset_http_smoke_env('SR_SMOKE_PARALLEL_REQUESTS', '6')));
$successStatuses = sr_asset_http_smoke_parse_statuses(sr_asset_http_smoke_env('SR_SMOKE_SUCCESS_STATUSES', '200,201,204,302,303'));
$expectedTable = sr_asset_http_smoke_env('SR_SMOKE_EXPECT_DEDUPE_TABLE');
$expectedColumn = sr_asset_http_smoke_env('SR_SMOKE_EXPECT_DEDUPE_COLUMN', 'dedupe_key');
$expectedDedupeKey = sr_asset_http_smoke_env('SR_SMOKE_EXPECT_DEDUPE_KEY');
$expectFreshDedupe = sr_asset_http_smoke_bool('SR_SMOKE_EXPECT_DEDUPE_FRESH') || sr_asset_http_smoke_env('SR_SMOKE_EXPECT_DEDUPE_FRESH', '1') === '1';

if (!sr_asset_http_smoke_bool('SR_SMOKE_ALLOW_MUTATION')) {
    fwrite(STDERR, sr_asset_http_smoke_usage());
    exit(2);
}
if ($baseUrl === '' || !preg_match('#\Ahttps?://#', $baseUrl) || $identifier === '' || $password === '' || $formPath === '' || $postPath === '') {
    fwrite(STDERR, sr_asset_http_smoke_usage());
    exit(2);
}
if (($expectedTable === '') !== ($expectedDedupeKey === '')) {
    sr_asset_http_smoke_fail('SR_SMOKE_EXPECT_DEDUPE_TABLE and SR_SMOKE_EXPECT_DEDUPE_KEY must be provided together.', 2);
}

$cookies = [];
sr_asset_http_smoke_login($baseUrl, $identifier, $password, $cookies);

$form = sr_asset_http_smoke_request($baseUrl, 'GET', $formPath, [], $cookies);
if ((int) $form['status'] !== 200) {
    sr_asset_http_smoke_fail('Target form returned HTTP ' . (string) $form['status'] . '.', 2);
}

$csrf = sr_asset_http_smoke_hidden_value((string) $form['body'], 'csrf_token');
$requestToken = sr_asset_http_smoke_hidden_value((string) $form['body'], $tokenField);
if ($csrf === '') {
    sr_asset_http_smoke_fail('Target form CSRF token not found.', 2);
}
if ($requestToken === '') {
    sr_asset_http_smoke_fail('Target form token field not found: ' . $tokenField, 2);
}

$postData = array_merge($extraPost, [
    'csrf_token' => $csrf,
    $tokenField => $requestToken,
]);

$beforeCount = sr_asset_http_smoke_dedupe_count($expectedTable, $expectedColumn, $expectedDedupeKey);
$responses = sr_asset_http_smoke_parallel_posts($baseUrl, $postPath, $postData, $cookies, $requestCount);
$afterCount = sr_asset_http_smoke_dedupe_count($expectedTable, $expectedColumn, $expectedDedupeKey);

$statusCounts = [];
$unexpectedStatusCounts = [];
$failureBodies = 0;
$successCount = 0;
foreach ($responses as $response) {
    $statusCode = (int) ($response['status'] ?? 0);
    $status = (string) $statusCode;
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    if (isset($successStatuses[$statusCode])) {
        $successCount++;
    } else {
        $unexpectedStatusCounts[$status] = ($unexpectedStatusCounts[$status] ?? 0) + 1;
    }
    $body = (string) ($response['body'] ?? '');
    if (($response['error'] ?? '') !== '' || str_contains($body, 'Fatal error') || str_contains($body, 'Stack trace')) {
        $failureBodies++;
    }
}

if ($failureBodies > 0) {
    sr_asset_http_smoke_fail('One or more parallel POST responses contained a transport or PHP failure.');
}

if ($successCount < 1) {
    sr_asset_http_smoke_fail('Expected at least one successful POST response, got status counts ' . json_encode($statusCounts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '.');
}

if ($unexpectedStatusCounts !== []) {
    sr_asset_http_smoke_fail('All parallel POST responses must use allowed success statuses, got unexpected status counts ' . json_encode($unexpectedStatusCounts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '.');
}

if ($afterCount !== null) {
    if ($expectFreshDedupe && $beforeCount !== 0) {
        sr_asset_http_smoke_fail('Expected a fresh dedupe key before the smoke, got existing count ' . (string) $beforeCount . ' for ' . $expectedDedupeKey . '.');
    }
    if ($afterCount !== 1) {
        sr_asset_http_smoke_fail('Expected exactly one dedupe row for ' . $expectedDedupeKey . ', got ' . (string) $afterCount . '.');
    }
}

echo "asset-idempotency-http-smoke-version: 1\n";
echo "base-url: " . $baseUrl . "\n";
echo "form-path: " . $formPath . "\n";
echo "post-path: " . $postPath . "\n";
echo "parallel-requests: " . (string) $requestCount . "\n";
echo "success-statuses: " . implode(',', array_map('strval', array_keys($successStatuses))) . "\n";
echo "success-count: " . (string) $successCount . "\n";
echo "status-counts: " . json_encode($statusCounts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
if ($afterCount !== null) {
    echo "dedupe-table: " . $expectedTable . "\n";
    echo "dedupe-key: " . $expectedDedupeKey . "\n";
    echo "dedupe-fresh-required: " . ($expectFreshDedupe ? 'yes' : 'no') . "\n";
    echo "dedupe-count-before: " . (string) $beforeCount . "\n";
    echo "dedupe-count-after: " . (string) $afterCount . "\n";
}
echo "saanraan asset idempotency HTTP smoke completed.\n";
