#!/usr/bin/env php
<?php

declare(strict_types=1);

function sr_privacy_http_smoke_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return is_string($value) && $value !== '' ? $value : $default;
}

function sr_privacy_http_smoke_requires_public_mutation_override(string $baseUrl): bool
{
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

function sr_privacy_http_smoke_usage(): string
{
    return "Usage: SR_SMOKE_ALLOW_MUTATION=1 SR_SMOKE_BASE_URL=http://127.0.0.1:8080 SR_SMOKE_IDENTIFIER=privacy_smoke SR_SMOKE_PASSWORD=password php .tools/bin/smoke-privacy-export-cleanup.php\n"
        . "Optional: SR_SMOKE_WITHDRAW_CONFIRM_TEXT=탈퇴 SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1\n"
        . "This smoke validates privacy export JSON structure and withdraws/anonymizes the disposable account. Run only against local or staging disposable data.\n";
}

function sr_privacy_http_smoke_fail(string $message, int $exitCode = 1): never
{
    fwrite(STDERR, "saanraan privacy export/cleanup HTTP smoke failed:\n- " . $message . "\n");
    exit($exitCode);
}

function sr_privacy_http_smoke_url(string $baseUrl, string $path): string
{
    return $baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path);
}

function sr_privacy_http_smoke_cookie_header(array $cookies): string
{
    $pairs = [];
    foreach ($cookies as $name => $value) {
        $pairs[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $value);
    }

    return implode('; ', $pairs);
}

function sr_privacy_http_smoke_store_cookies(array $headers, array &$cookies): void
{
    foreach ($headers as $header) {
        if (preg_match('/\ASet-Cookie:\s*([^=;\s]+)=([^;]*)/i', (string) $header, $matches) === 1) {
            $cookies[(string) $matches[1]] = urldecode((string) $matches[2]);
        }
    }
}

function sr_privacy_http_smoke_header_value(array $headers, string $name): string
{
    foreach ($headers as $header) {
        if (stripos((string) $header, $name . ':') === 0) {
            return trim(substr((string) $header, strlen($name) + 1));
        }
    }

    return '';
}

function sr_privacy_http_smoke_request(string $baseUrl, string $method, string $path, array $postData, array &$cookies): array
{
    $headers = ["User-Agent: Saanraan-Privacy-Smoke"];
    if ($cookies !== []) {
        $headers[] = 'Cookie: ' . sr_privacy_http_smoke_cookie_header($cookies);
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
    $body = file_get_contents(sr_privacy_http_smoke_url($baseUrl, $path), false, $context);
    restore_error_handler();

    $responseHeaders = function_exists('http_get_last_response_headers')
        ? http_get_last_response_headers()
        : ($http_response_header ?? []);
    $responseHeaders = is_array($responseHeaders) ? $responseHeaders : [];
    sr_privacy_http_smoke_store_cookies($responseHeaders, $cookies);

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
        'location' => sr_privacy_http_smoke_header_value($responseHeaders, 'Location'),
    ];
}

function sr_privacy_http_smoke_hidden_value(string $body, string $field): string
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

function sr_privacy_http_smoke_login(string $baseUrl, string $identifier, string $password, array &$cookies): array
{
    $form = sr_privacy_http_smoke_request($baseUrl, 'GET', '/login', [], $cookies);
    if ((int) $form['status'] !== 200) {
        sr_privacy_http_smoke_fail('Login form returned HTTP ' . (string) $form['status'] . '.', 2);
    }

    $csrf = sr_privacy_http_smoke_hidden_value((string) $form['body'], 'csrf_token');
    if ($csrf === '') {
        sr_privacy_http_smoke_fail('Login CSRF token not found.', 2);
    }

    return sr_privacy_http_smoke_request($baseUrl, 'POST', '/login', [
        'csrf_token' => $csrf,
        'identifier' => $identifier,
        'password' => $password,
        'next' => '/account',
    ], $cookies);
}

$baseUrl = rtrim(sr_privacy_http_smoke_env('SR_SMOKE_BASE_URL'), '/');
$identifier = sr_privacy_http_smoke_env('SR_SMOKE_IDENTIFIER');
$password = sr_privacy_http_smoke_env('SR_SMOKE_PASSWORD');
$confirmText = sr_privacy_http_smoke_env('SR_SMOKE_WITHDRAW_CONFIRM_TEXT', '탈퇴');

if ($baseUrl === '' || !preg_match('#\Ahttps?://#', $baseUrl) || $identifier === '' || $password === '') {
    fwrite(STDERR, sr_privacy_http_smoke_usage());
    exit(2);
}
if (getenv('SR_SMOKE_ALLOW_MUTATION') !== '1') {
    fwrite(STDERR, "saanraan privacy export/cleanup HTTP smoke refused to run because it withdraws/anonymizes an account. Set SR_SMOKE_ALLOW_MUTATION=1 only on local or staging disposable data.\n");
    fwrite(STDERR, sr_privacy_http_smoke_usage());
    exit(2);
}
if (sr_privacy_http_smoke_requires_public_mutation_override($baseUrl) && getenv('SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL') !== '1') {
    fwrite(STDERR, "saanraan privacy export/cleanup HTTP smoke refused to run against a public-looking base URL. Set SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1 only for staging disposable data.\n");
    fwrite(STDERR, sr_privacy_http_smoke_usage());
    exit(2);
}

$cookies = [];
$login = sr_privacy_http_smoke_login($baseUrl, $identifier, $password, $cookies);
if (!in_array((int) $login['status'], [302, 303], true)) {
    sr_privacy_http_smoke_fail('Login submit returned HTTP ' . (string) $login['status'] . '.', 2);
}

$account = sr_privacy_http_smoke_request($baseUrl, 'GET', '/account', [], $cookies);
if ((int) $account['status'] !== 200) {
    sr_privacy_http_smoke_fail('Account screen returned HTTP ' . (string) $account['status'] . '.', 2);
}
$exportCsrf = sr_privacy_http_smoke_hidden_value((string) $account['body'], 'csrf_token');
if ($exportCsrf === '') {
    sr_privacy_http_smoke_fail('Account privacy export CSRF token not found.', 2);
}

$export = sr_privacy_http_smoke_request($baseUrl, 'POST', '/account/privacy-export', [
    'csrf_token' => $exportCsrf,
    'current_password' => $password,
], $cookies);
if ((int) $export['status'] !== 200) {
    sr_privacy_http_smoke_fail('Privacy export returned HTTP ' . (string) $export['status'] . '.', 1);
}
$decodedExport = json_decode((string) $export['body'], true);
if (!is_array($decodedExport)) {
    sr_privacy_http_smoke_fail('Privacy export response was not valid JSON: ' . json_last_error_msg(), 1);
}
foreach (['exported_at', 'account_id', 'privacy_requests', 'module_exports'] as $requiredKey) {
    if (!array_key_exists($requiredKey, $decodedExport)) {
        sr_privacy_http_smoke_fail('Privacy export JSON missing key: ' . $requiredKey, 1);
    }
}
if (!is_string($decodedExport['exported_at'] ?? null) || strtotime((string) $decodedExport['exported_at']) === false) {
    sr_privacy_http_smoke_fail('Privacy export JSON exported_at was not a parseable timestamp.', 1);
}
if (!is_int($decodedExport['account_id'] ?? null) || (int) $decodedExport['account_id'] < 1) {
    sr_privacy_http_smoke_fail('Privacy export JSON account_id was not a positive integer.', 1);
}
if (!is_array($decodedExport['privacy_requests'] ?? null)) {
    sr_privacy_http_smoke_fail('Privacy export JSON privacy_requests was not an array.', 1);
}
if (!is_array($decodedExport['module_exports'] ?? null) || !array_key_exists('member', $decodedExport['module_exports'])) {
    sr_privacy_http_smoke_fail('Privacy export JSON missing member module export.', 1);
}
$memberExport = $decodedExport['module_exports']['member'];
if (!is_array($memberExport) || $memberExport === []) {
    sr_privacy_http_smoke_fail('Privacy export JSON member module export was empty or invalid.', 1);
}

$withdraw = sr_privacy_http_smoke_request($baseUrl, 'GET', '/account/withdraw', [], $cookies);
if ((int) $withdraw['status'] !== 200) {
    sr_privacy_http_smoke_fail('Withdraw screen returned HTTP ' . (string) $withdraw['status'] . '.', 2);
}
$withdrawCsrf = sr_privacy_http_smoke_hidden_value((string) $withdraw['body'], 'csrf_token');
if ($withdrawCsrf === '') {
    sr_privacy_http_smoke_fail('Withdraw CSRF token not found.', 2);
}

$withdrawSubmit = sr_privacy_http_smoke_request($baseUrl, 'POST', '/account/withdraw', [
    'csrf_token' => $withdrawCsrf,
    'password' => $password,
    'confirm_text' => $confirmText,
    'refund_bank' => 'Privacy Smoke Bank',
    'refund_account_holder' => 'Privacy Smoke',
    'refund_account_number' => '000-0000-0000',
], $cookies);
if (!in_array((int) $withdrawSubmit['status'], [302, 303], true)) {
    sr_privacy_http_smoke_fail('Withdraw submit returned HTTP ' . (string) $withdrawSubmit['status'] . '.', 1);
}
$locationPath = parse_url((string) $withdrawSubmit['location'], PHP_URL_PATH);
if ($locationPath !== '/login') {
    sr_privacy_http_smoke_fail('Withdraw submit redirected to unexpected location: ' . (string) $withdrawSubmit['location'], 1);
}

$postWithdrawAccount = sr_privacy_http_smoke_request($baseUrl, 'GET', '/account', [], $cookies);
if ((int) $postWithdrawAccount['status'] === 200) {
    sr_privacy_http_smoke_fail('Withdrawn account could still access the account screen with the existing session.', 1);
}

$postWithdrawCookies = [];
$secondLogin = sr_privacy_http_smoke_login($baseUrl, $identifier, $password, $postWithdrawCookies);
if (in_array((int) $secondLogin['status'], [302, 303], true)) {
    sr_privacy_http_smoke_fail('Withdrawn account could still log in with the original credentials.', 1);
}

echo "privacy-export-cleanup-http-smoke-version: 1\n";
echo "base-url: " . $baseUrl . "\n";
echo "export-status: " . (string) $export['status'] . "\n";
echo "export-json: ok\n";
echo "exported-at: ok\n";
echo "export-account-id: " . (string) $decodedExport['account_id'] . "\n";
echo "privacy-requests-array: yes\n";
echo "module-exports-member: yes\n";
echo "withdraw-status: " . (string) $withdrawSubmit['status'] . "\n";
echo "withdraw-location: /login\n";
echo "post-withdraw-account-blocked: yes\n";
echo "post-withdraw-login-blocked: yes\n";
echo "saanraan privacy export/cleanup HTTP smoke completed.\n";
