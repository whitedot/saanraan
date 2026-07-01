#!/usr/bin/env php
<?php

declare(strict_types=1);

function sr_mfa_smoke_argument(array $argv, int $index, string $environmentKey, string $default = ''): string
{
    $argument = (string) ($argv[$index] ?? '');
    if ($argument !== '') {
        return $argument;
    }

    $environmentValue = getenv($environmentKey);
    if (is_string($environmentValue) && $environmentValue !== '') {
        return $environmentValue;
    }

    return $default;
}

function sr_mfa_smoke_usage(): string
{
    return "Usage: php .tools/bin/smoke-member-mfa.php http://127.0.0.1:8080 login@example.com password 123456 [/ui-kit]\n"
        . "Env: SR_SMOKE_ALLOW_MUTATION=1 SR_SMOKE_BASE_URL SR_SMOKE_IDENTIFIER SR_SMOKE_PASSWORD SR_SMOKE_MFA_CODE SR_SMOKE_NEXT_PATH SR_SMOKE_EXPECT_TEXT\n";
}

function sr_mfa_smoke_requires_public_mutation_override(string $baseUrl): bool
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

function sr_mfa_smoke_url(string $baseUrl, string $path): string
{
    return $baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path);
}

function sr_mfa_smoke_cookie_header(array $cookies): string
{
    $pairs = [];
    foreach ($cookies as $name => $value) {
        $pairs[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $value);
    }

    return implode('; ', $pairs);
}

function sr_mfa_smoke_store_cookies(array $headers, array &$cookies): void
{
    foreach ($headers as $header) {
        if (preg_match('/\ASet-Cookie:\s*([^=;\s]+)=([^;]*)/i', (string) $header, $matches) === 1) {
            $cookies[(string) $matches[1]] = urldecode((string) $matches[2]);
        }
    }
}

function sr_mfa_smoke_request(string $baseUrl, string $method, string $path, array $postData, array &$cookies): array
{
    $headers = ["User-Agent: Saanraan-Member-MFA-Smoke"];
    if ($cookies !== []) {
        $headers[] = 'Cookie: ' . sr_mfa_smoke_cookie_header($cookies);
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
            'timeout' => 10,
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
    $body = file_get_contents(sr_mfa_smoke_url($baseUrl, $path), false, $context);
    restore_error_handler();
    $responseHeaders = function_exists('http_get_last_response_headers')
        ? http_get_last_response_headers()
        : ($http_response_header ?? []);
    sr_mfa_smoke_store_cookies($responseHeaders, $cookies);

    $status = 0;
    $location = '';
    foreach ($responseHeaders as $header) {
        if (preg_match('#\AHTTP/\S+\s+(\d{3})#', (string) $header, $matches) === 1) {
            $status = (int) $matches[1];
        }
        if (preg_match('#\ALocation:\s*(.+)\z#i', (string) $header, $matches) === 1) {
            $location = trim((string) $matches[1]);
        }
    }

    return [
        'status' => $status,
        'body' => is_string($body) ? $body : '',
        'location' => $location,
    ];
}

function sr_mfa_smoke_location_path(string $location): string
{
    $path = parse_url($location, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return $location;
    }

    $query = parse_url($location, PHP_URL_QUERY);
    return is_string($query) && $query !== '' ? $path . '?' . $query : $path;
}

function sr_mfa_smoke_csrf(array $response, string $label): string
{
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', (string) $response['body'], $matches) === 1) {
        return html_entity_decode((string) $matches[1], ENT_QUOTES, 'UTF-8');
    }

    throw new RuntimeException($label . ' CSRF token not found.');
}

function sr_mfa_smoke_assert_status(array &$errors, string $label, array $response, array $allowedStatuses): void
{
    $status = (int) $response['status'];
    if (!in_array($status, $allowedStatuses, true)) {
        $excerpt = trim(preg_replace('/\s+/', ' ', strip_tags((string) $response['body'])) ?? '');
        if (strlen($excerpt) > 300) {
            $excerpt = substr($excerpt, 0, 300) . '...';
        }
        $errors[] = $label . ' returned unexpected status ' . (string) $status . ($excerpt !== '' ? ': ' . $excerpt : '') . '.';
    }
    if (str_contains((string) $response['body'], 'Fatal error') || str_contains((string) $response['body'], 'Stack trace')) {
        $errors[] = $label . ' rendered a PHP failure page.';
    }
}

function sr_mfa_smoke_assert_body_contains(array &$errors, string $label, array $response, string $needle): void
{
    if (!str_contains((string) $response['body'], $needle)) {
        $errors[] = $label . ' did not contain expected text "' . $needle . '".';
    }
}

function sr_mfa_smoke_assert_location_path(array &$errors, string $label, array $response, string $expectedPath): void
{
    $locationPath = sr_mfa_smoke_location_path((string) ($response['location'] ?? ''));
    if ($locationPath !== $expectedPath) {
        $errors[] = $label . ' redirected to "' . $locationPath . '" instead of "' . $expectedPath . '".';
    }
}

$baseUrl = rtrim(sr_mfa_smoke_argument($argv, 1, 'SR_SMOKE_BASE_URL'), '/');
$identifier = sr_mfa_smoke_argument($argv, 2, 'SR_SMOKE_IDENTIFIER');
$password = sr_mfa_smoke_argument($argv, 3, 'SR_SMOKE_PASSWORD');
$mfaCode = sr_mfa_smoke_argument($argv, 4, 'SR_SMOKE_MFA_CODE');
$nextPath = sr_mfa_smoke_argument($argv, 5, 'SR_SMOKE_NEXT_PATH', '/ui-kit');
$expectText = sr_mfa_smoke_argument($argv, 6, 'SR_SMOKE_EXPECT_TEXT', $nextPath === '/ui-kit' ? 'Public UI-KIT' : '');

if (
    $baseUrl === ''
    || !preg_match('#\Ahttps?://#', $baseUrl)
    || $identifier === ''
    || $password === ''
    || $mfaCode === ''
    || !str_starts_with($nextPath, '/')
    || str_starts_with($nextPath, '//')
    || preg_match('/[\x00-\x1F\x7F]/', $nextPath) === 1
) {
    fwrite(STDERR, sr_mfa_smoke_usage());
    exit(2);
}

if (getenv('SR_SMOKE_ALLOW_MUTATION') !== '1') {
    fwrite(STDERR, "saanraan member MFA smoke refused to run because it mutates login session, auth logs, and MFA last-used state. Set SR_SMOKE_ALLOW_MUTATION=1 only on local or staging disposable data.\n");
    fwrite(STDERR, sr_mfa_smoke_usage());
    exit(2);
}
if (sr_mfa_smoke_requires_public_mutation_override($baseUrl) && getenv('SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL') !== '1') {
    fwrite(STDERR, "saanraan member MFA smoke refused to run against a public-looking base URL. Set SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1 only for staging disposable data.\n");
    fwrite(STDERR, sr_mfa_smoke_usage());
    exit(2);
}

$cookies = [];
$errors = [];

try {
    $protectedEntry = sr_mfa_smoke_request($baseUrl, 'GET', $nextPath, [], $cookies);
    sr_mfa_smoke_assert_status($errors, 'member-only protected entry before login', $protectedEntry, [302]);
    $protectedLocation = sr_mfa_smoke_location_path((string) $protectedEntry['location']);
    if (!str_starts_with($protectedLocation, '/login?next=')) {
        $errors[] = 'member-only protected entry did not redirect to login with next path.';
    }

    $loginForm = sr_mfa_smoke_request($baseUrl, 'GET', '/login?next=' . rawurlencode($nextPath), [], $cookies);
    sr_mfa_smoke_assert_status($errors, 'login form', $loginForm, [200]);
    $loginCsrf = sr_mfa_smoke_csrf($loginForm, 'login form');

    $loginSubmit = sr_mfa_smoke_request($baseUrl, 'POST', '/login', [
        'csrf_token' => $loginCsrf,
        'identifier' => $identifier,
        'password' => $password,
        'next' => $nextPath,
    ], $cookies);
    sr_mfa_smoke_assert_status($errors, 'login submit', $loginSubmit, [302]);
    sr_mfa_smoke_assert_location_path($errors, 'login submit', $loginSubmit, '/login/mfa');

    $challengeProtectedEntry = sr_mfa_smoke_request($baseUrl, 'GET', $nextPath, [], $cookies);
    sr_mfa_smoke_assert_status($errors, 'member-only protected entry during MFA challenge', $challengeProtectedEntry, [302]);
    if ($expectText !== '' && str_contains((string) $challengeProtectedEntry['body'], $expectText)) {
        $errors[] = 'member-only protected entry rendered protected content before MFA completion.';
    }

    $mfaForm = sr_mfa_smoke_request($baseUrl, 'GET', '/login/mfa', [], $cookies);
    sr_mfa_smoke_assert_status($errors, 'MFA form', $mfaForm, [200]);
    sr_mfa_smoke_assert_body_contains($errors, 'MFA form', $mfaForm, 'name="code"');
    $mfaCsrf = sr_mfa_smoke_csrf($mfaForm, 'MFA form');

    $mfaSubmit = sr_mfa_smoke_request($baseUrl, 'POST', '/login/mfa', [
        'csrf_token' => $mfaCsrf,
        'code' => $mfaCode,
    ], $cookies);
    sr_mfa_smoke_assert_status($errors, 'MFA submit', $mfaSubmit, [302]);
    sr_mfa_smoke_assert_location_path($errors, 'MFA submit', $mfaSubmit, $nextPath);

    $protectedAfterMfa = sr_mfa_smoke_request($baseUrl, 'GET', $nextPath, [], $cookies);
    sr_mfa_smoke_assert_status($errors, 'member-only protected entry after MFA', $protectedAfterMfa, [200]);
    if ($expectText !== '') {
        sr_mfa_smoke_assert_body_contains($errors, 'member-only protected entry after MFA', $protectedAfterMfa, $expectText);
    }

    $mfaAfterLogin = sr_mfa_smoke_request($baseUrl, 'GET', '/login/mfa', [], $cookies);
    sr_mfa_smoke_assert_status($errors, 'MFA route after completed login', $mfaAfterLogin, [302]);
    sr_mfa_smoke_assert_location_path($errors, 'MFA route after completed login', $mfaAfterLogin, '/');
} catch (Throwable $exception) {
    $errors[] = $exception->getMessage();
}

if ($errors !== []) {
    fwrite(STDERR, "saanraan member MFA smoke checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "saanraan member MFA smoke checks completed.\n";
