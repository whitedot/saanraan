#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
define('SR_ROOT', $root);

require_once $root . '/core/helpers.php';

$errors = [];

function sr_security_baseline_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_security_baseline_read(string $file): string
{
    if (!is_file($file)) {
        sr_security_baseline_error('Required file is missing: ' . $file);
        return '';
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        sr_security_baseline_error('Required file cannot be read: ' . $file);
        return '';
    }

    return $contents;
}

function sr_security_baseline_require_markers(string $file, array $markers): void
{
    $contents = sr_security_baseline_read($file);
    if ($contents === '') {
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            sr_security_baseline_error('Security baseline marker missing in ' . $file . ': ' . $marker);
        }
    }
}

function sr_security_baseline_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_security_baseline_error($message);
    }
}

function sr_security_baseline_check_runtime_fixtures(): void
{
    $config = ['app_key' => str_repeat('a', 64)];

    $hash = sr_hmac_hash('security-fixture', $config);
    sr_security_baseline_assert(
        preg_match('/\A[a-f0-9]{64}\z/', $hash) === 1 && $hash !== hash('sha256', 'security-fixture'),
        'HMAC fixture should produce a keyed 64-character hex hash.'
    );

    try {
        sr_hmac_hash('security-fixture', []);
        sr_security_baseline_error('HMAC fixture should fail without app_key.');
    } catch (RuntimeException $exception) {
        sr_security_baseline_assert($exception->getMessage() === 'app_key is required.', 'HMAC missing app_key error changed.');
    }

    $created = sr_download_token_create($config, 'fixture.download', 'content_file:12', 300, 1000);
    sr_security_baseline_assert(
        isset($created['token'], $created['token_hash'], $created['expires_at'])
            && preg_match('/\A[a-f0-9]{64}\z/', (string) $created['token']) === 1
            && preg_match('/\A[a-f0-9]{64}\z/', (string) $created['token_hash']) === 1,
        'Download token fixture should create token and token_hash.'
    );
    sr_security_baseline_assert(
        sr_download_token_verify($config, (string) $created['token'], (string) $created['token_hash'], 'fixture.download', 'content_file:12', (int) $created['expires_at'], 1001),
        'Download token fixture should verify the original purpose and subject.'
    );
    sr_security_baseline_assert(
        !sr_download_token_verify($config, (string) $created['token'], (string) $created['token_hash'], 'fixture.other', 'content_file:12', (int) $created['expires_at'], 1001),
        'Download token fixture should reject a changed purpose.'
    );
    sr_security_baseline_assert(
        !sr_download_token_verify($config, (string) $created['token'], (string) $created['token_hash'], 'fixture.download', 'content_file:99', (int) $created['expires_at'], 1001),
        'Download token fixture should reject a changed subject.'
    );
    sr_security_baseline_assert(
        !sr_download_token_verify($config, (string) $created['token'], (string) $created['token_hash'], 'fixture.download', 'content_file:12', (int) $created['expires_at'], ((int) $created['expires_at']) + 1),
        'Download token fixture should reject an expired token.'
    );

    $line = sr_log_sensitive_text_sanitize('password=plain token=abc Authorization: Bearer secret-token api_key=xyz');
    sr_security_baseline_assert(
        str_contains($line, 'password=[masked]')
            && str_contains($line, 'token=[masked]')
            && str_contains($line, 'Authorization: [masked]')
            && str_contains($line, 'api_key=[masked]')
            && !str_contains($line, 'plain')
            && !str_contains($line, 'secret-token')
            && !str_contains($line, 'xyz'),
        'Sensitive text fixture should mask password/token/authorization/api key values.'
    );

    $metadata = sr_audit_metadata_sanitize([
        'nested' => [
            'client_secret' => 'secret-value',
            'note' => 'Bearer bearer-value',
        ],
        'plain' => 'ok',
    ]);
    sr_security_baseline_assert(
        is_array($metadata)
            && (($metadata['nested']['client_secret'] ?? '') === '[masked]')
            && (($metadata['nested']['note'] ?? '') === 'Bearer [masked]')
            && (($metadata['plain'] ?? '') === 'ok'),
        'Audit metadata fixture should mask nested secret keys and bearer values.'
    );

    sr_security_baseline_assert(
        sr_is_safe_relative_url('/login?next=%2Faccount'),
        'Safe relative redirect fixture should accept normal absolute-path URLs.'
    );
    sr_security_baseline_assert(
        !sr_is_safe_relative_url('//evil.test/path'),
        'Safe relative redirect fixture should reject scheme-relative URLs.'
    );
    sr_security_baseline_assert(
        !sr_is_safe_relative_url('/\\evil.test'),
        'Safe relative redirect fixture should reject backslash URLs.'
    );
    sr_security_baseline_assert(
        !sr_is_safe_relative_url("/account\r\nLocation: https://evil.test"),
        'Safe relative redirect fixture should reject control characters.'
    );
    sr_security_baseline_assert(
        !sr_is_safe_relative_url('https://example.com/account'),
        'Safe relative redirect fixture should reject absolute HTTP URLs.'
    );

    sr_security_baseline_assert(
        sr_is_http_url('https://example.com/path?x=1'),
        'HTTP URL fixture should accept a normal HTTPS URL.'
    );
    sr_security_baseline_assert(
        !sr_is_http_url('https://user@example.com/path'),
        'HTTP URL fixture should reject userinfo URLs.'
    );
    sr_security_baseline_assert(
        !sr_is_http_url("https://example.com/path\r\nHeader: injected"),
        'HTTP URL fixture should reject control characters.'
    );
    sr_security_baseline_assert(
        !sr_is_public_http_url('https://127.0.0.1/internal'),
        'Public HTTP URL fixture should reject loopback hosts.'
    );
    sr_security_baseline_assert(
        !sr_is_public_http_url('https://169.254.169.254/latest/meta-data'),
        'Public HTTP URL fixture should reject link-local hosts.'
    );
}

function sr_security_baseline_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        if (is_file($path)) {
            @unlink($path);
        }
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if (!is_string($entry) || $entry === '.' || $entry === '..') {
            continue;
        }
        sr_security_baseline_remove_tree($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}

function sr_security_baseline_check_exception_log_fallback_fixture(): void
{
    if (!function_exists('exec')) {
        return;
    }

    $phpPathOutput = [];
    exec('command -v php 2>/dev/null', $phpPathOutput, $phpStatus);
    if ($phpStatus !== 0 || $phpPathOutput === []) {
        return;
    }

    $phpPath = trim((string) $phpPathOutput[0]);
    if ($phpPath === '') {
        return;
    }

    $fixtureRoot = sys_get_temp_dir() . '/sr_security_log_fixture_' . bin2hex(random_bytes(6));
    $logDir = $fixtureRoot . '/storage/logs';
    $errorLog = $fixtureRoot . '/fallback-error.log';
    if (!mkdir($logDir, 0755, true) || !@chmod($logDir, 0555)) {
        sr_security_baseline_error('Exception log fallback fixture could not create temporary log directory.');
        return;
    }

    $opsPath = SR_ROOT . '/core/helpers/ops.php';
    $code = <<<'PHP'
define('SR_ROOT', $argv[1]);
function sr_now(): string
{
    return '2026-06-12 12:00:00';
}
require $argv[2];
ini_set('log_errors', '1');
ini_set('error_log', $argv[3]);
sr_log_exception(new RuntimeException('password=plain token=abc Authorization: Bearer secret'), 'fixture secret=xyz');
PHP;

    try {
        $command = escapeshellarg($phpPath)
            . ' -d display_errors=0 -r ' . escapeshellarg($code)
            . ' ' . escapeshellarg($fixtureRoot)
            . ' ' . escapeshellarg($opsPath)
            . ' ' . escapeshellarg($errorLog)
            . ' 2>&1';
        $output = [];
        exec($command, $output, $status);
        if ($status !== 0) {
            sr_security_baseline_error('Exception log fallback fixture subprocess failed: ' . implode("\n", $output));
            return;
        }

        $fallbackLog = is_file($errorLog) ? file_get_contents($errorLog) : false;
        if (!is_string($fallbackLog)) {
            sr_security_baseline_error('Exception log fallback fixture should write fallback error_log output.');
            return;
        }

        sr_security_baseline_assert(str_contains($fallbackLog, 'failed to write exception log'), 'Exception log fallback fixture should record fallback failure context.');
        sr_security_baseline_assert(str_contains($fallbackLog, 'password=[masked]'), 'Exception log fallback fixture should mask password values.');
        sr_security_baseline_assert(str_contains($fallbackLog, 'token=[masked]'), 'Exception log fallback fixture should mask token values.');
        sr_security_baseline_assert(str_contains($fallbackLog, 'Authorization: [masked]'), 'Exception log fallback fixture should mask authorization values.');
        sr_security_baseline_assert(!str_contains($fallbackLog, 'plain'), 'Exception log fallback fixture should not leak password value.');
        sr_security_baseline_assert(!str_contains($fallbackLog, 'Bearer secret'), 'Exception log fallback fixture should not leak bearer value.');
        sr_security_baseline_assert(!str_contains($fallbackLog, 'secret=xyz'), 'Exception log fallback fixture should not leak secret-like context value.');
    } finally {
        @chmod($logDir, 0755);
        sr_security_baseline_remove_tree($fixtureRoot);
    }
}

sr_security_baseline_require_markers('docs/security-baseline-evidence.md', [
    '세션 쿠키',
    'DB 세션',
    '회원 세션',
    'CSRF',
    '요청 contract',
    '응답 종료',
    '다운로드 헤더',
    '파일 streaming 헤더',
    'JS 값 주입',
    'rate limit',
    '민감 token 입력',
    '토큰 hash/HMAC',
    '민감정보 마스킹',
    '로그 파일 쓰기 실패',
    '보안 헤더',
    '내부 URL/redirect',
    '.tools/bin/check-security-baseline.php',
]);

sr_security_baseline_require_markers('core/helpers/runtime.php', [
    "ini_set('session.use_strict_mode', '1')",
    "ini_set('session.use_only_cookies', '1')",
    "ini_set('session.cookie_httponly', '1')",
    "ini_set('session.cookie_samesite', 'Lax')",
    "'httponly' => true",
    "'samesite' => 'Lax'",
    'class SrDatabaseSessionHandler implements SessionHandlerInterface',
    'session_id_hash',
    "hash('sha256', \$id)",
    'function sr_send_security_headers(?array $config = null): void',
    'X-Content-Type-Options: nosniff',
    'X-Frame-Options: SAMEORIGIN',
    'Content-Security-Policy:',
    'Strict-Transport-Security:',
    'function sr_rate_limit_count(',
    'function sr_rate_limit_increment(',
    'function sr_rate_limit_key(',
    'function sr_rate_limit_hash(',
    "hash_hmac('sha256', \$value, \$appKey)",
    'function sr_hmac_hash(string $value, array $config): string',
    "throw new RuntimeException('app_key is required.')",
    'function sr_is_public_http_url(string $url): bool',
    'function sr_ip_is_public_network_address(string $address): bool',
]);

sr_security_baseline_require_markers('core/helpers/output.php', [
    'function sr_redirect(string $url): void',
    'sr_is_safe_relative_url($url)',
    "header('Location: ' . sr_url(\$url), true, 302)",
    'function sr_redirect_external(string $url): void',
    'sr_is_public_http_url($url)',
    'function sr_redirect_trusted_external(string $url): void',
    'function sr_finish_response(): void',
    'sr_enforce_request_contract(\'before_response_end\')',
    'function sr_csrf_token(): string',
    'bin2hex(random_bytes(32))',
    'function sr_require_csrf(): void',
    "sr_request_contract_mark('csrf_checked')",
    'hash_equals($expected, $actual)',
    "sr_request_contract_guard_blocked('csrf')",
    'function sr_json_response(mixed $payload',
    'function sr_js_json_encode(mixed $value): string',
    'function sr_send_download_headers(string $contentType, string $filename, string $disposition = \'attachment\', ?int $contentLength = null',
    'function sr_send_file_headers(string $contentType, ?int $contentLength = null, string $cacheControl = \'private, max-age=300\', array $headers = []): void',
    'function sr_download_content_disposition(string $filename, string $disposition = \'attachment\'): string',
    'function sr_download_cache_control(string $cacheControl): string',
    'JSON_HEX_TAG',
    'JSON_INVALID_UTF8_SUBSTITUTE',
    'function sr_post_string_without_truncation(string $key, int $maxLength): ?string',
    'function sr_get_string_without_truncation(string $key, int $maxLength): ?string',
]);

sr_security_baseline_require_markers('core/helpers/ops.php', [
    'function sr_start_request_contract(string $method, string $path, string $moduleKey, string $actionFile): void',
    'register_shutdown_function',
    'function sr_enforce_request_contract(string $stage): void',
    'POST action did not call sr_require_csrf().',
    'Admin action did not call sr_member_require_login().',
    'Admin action did not call sr_admin_require_permission().',
    'function sr_fail_request_contract(string $message, string $stage, array $contract): void',
    'function sr_render_error(int $statusCode, string $message, ?Throwable $exception = null): void',
    'function sr_log_sensitive_text_sanitize(string $value): string',
    'function sr_audit_metadata_sanitize(mixed $value, string $key = \'\'): mixed',
    'password|token|secret|credential|bearer|authorization',
    '@file_put_contents($logDir . \'/error.log\', $line, FILE_APPEND | LOCK_EX)',
    'failed to write exception log',
]);

sr_security_baseline_require_markers('modules/member/helpers/sessions.php', [
    'function sr_member_login(PDO $pdo, array $account): bool',
    'session_regenerate_id(true)',
    "\$_SESSION['sr_csrf_token'] = bin2hex(random_bytes(32))",
    'function sr_member_rotate_current_session(PDO $pdo, int $accountId): bool',
    'sr_member_revoke_current_session($pdo)',
]);

sr_security_baseline_require_markers('modules/member/helpers/throttle.php', [
    'function sr_member_login_throttle_status(PDO $pdo, ?int $accountId): array',
    "sr_rate_limit_count(\$pdo, 'member.login.account'",
    "sr_rate_limit_count(\$pdo, 'member.login.ip'",
    'function sr_member_record_auth_rate_limits(PDO $pdo, ?int $accountId, string $eventType, string $result): void',
    "sr_rate_limit_increment(\$pdo, 'member.login.account'",
    "sr_rate_limit_increment(\$pdo, 'member.login.ip'",
]);

sr_security_baseline_require_markers('core/helpers/upload.php', [
    'function sr_download_token_create(array $config, string $purpose, string $subject, int $ttlSeconds, ?int $now = null): array',
    'bin2hex(random_bytes(32))',
    'function sr_download_token_hash(array $config, string $token, string $purpose, string $subject, int $expiresAt): string',
    'sr_hmac_hash(\'download-token|\' . $payload, $config)',
    'function sr_download_token_verify(array $config, string $token, string $expectedHash, string $purpose, string $subject, int $expiresAt, ?int $now = null): bool',
    'hash_equals($expectedHash, $actualHash)',
]);

sr_security_baseline_require_markers('.tools/bin/check-auth-runtime.php', [
    'Runtime session table is missing',
    'session_id_hash',
    'trusted proxy',
    'Rate limit count helper is missing',
    'App key resolver is missing',
]);

sr_security_baseline_require_markers('.tools/bin/check-member-auth-policy.php', [
    'sr_member_auth_policy_check_input_helper_fixtures',
    'GET no-truncation helper should reject overlong token values instead of trimming them for lookup.',
    'POST no-truncation helper should reject overlong password values instead of trimming them.',
    "sr_member_auth_policy_forbid_markers('modules/member/actions/password-reset.php'",
    "sr_member_auth_policy_forbid_markers('modules/member/actions/email-verify.php'",
]);

sr_security_baseline_require_markers('.tools/bin/check-notification-runtime.php', [
    'notification read action must reject overlong read tokens instead of truncating them.',
    'notification read action must not use truncating token lookup.',
]);

sr_security_baseline_require_markers('.tools/bin/check-runtime-helpers.php', [
    'Rate limit count should increment within the active window.',
    'Rate limit table should store HMAC key and subject hash, not raw subject values.',
    'Expired rate limit rows should not count.',
    'Expired rate limit rows should reset to one on the next increment.',
    'Invalid rate limit input should not create rows.',
]);

sr_security_baseline_require_markers('.tools/bin/smoke-http.php', [
    'required_headers',
    'x-content-type-options',
    'x-frame-options',
    'referrer-policy',
    'content-security-policy',
    "default-src 'self'",
    'missing required response header',
]);

sr_security_baseline_require_markers('.tools/bin/check-admin-action-security.php', [
    'POST action must require CSRF',
    'Action must end through sr_redirect(), sr_render_error(), or sr_finish_response() instead of raw exit/die',
    'Action must use sr_redirect() instead of a direct Location header',
    'JSON action responses must use sr_json_response() instead of direct JSON headers or echo json_encode()',
    'Admin action must require login',
    'Admin action must require an admin permission or owner guard',
]);

sr_security_baseline_require_markers('.tools/bin/check-output-helpers.php', [
    'JS JSON helper should hex-escape script-breaking characters and substitute invalid UTF-8.',
    'Script context JSON should use sr_js_json_encode()',
]);

sr_security_baseline_require_markers('.tools/bin/check-request-contract-runtime.php', [
    'Valid CSRF POST should mark csrf_checked.',
    'Invalid CSRF POST should be treated as a guard block.',
    'POST without sr_require_csrf() should exit with a contract violation.',
    'Admin GET without auth/role guards should exit with a contract violation.',
    'Admin GET with auth and role marks should pass.',
    'Public redirect should finish through sr_finish_response().',
    'Admin redirect without guards should be blocked before redirect.',
    'Admin finish_response without guards should be blocked before response end.',
]);

sr_security_baseline_require_markers('docs/security-model.md', [
    'call-site contract',
    'semantic contract',
    'sr_require_csrf()',
    'sr_member_require_login()',
    'sr_admin_require_permission()',
    'sr_finish_response()',
]);

sr_security_baseline_require_markers('docs/security-checklist.md', [
    '세션 쿠키에 `HttpOnly`, `Secure`, `SameSite`를 적용하는가',
    '모든 상태 변경 요청에 CSRF 검증이 있는가',
    'action 파일이 `exit` 또는 `die`를 직접 호출하지 않는가',
    '비공개 다운로드는 짧은 만료 token이나 같은 수준의 서버 측 권한 검사를 통과하는가',
]);

sr_security_baseline_check_runtime_fixtures();
sr_security_baseline_check_exception_log_fallback_fixture();

if ($errors !== []) {
    fwrite(STDERR, "security baseline checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "security baseline checks completed.\n";
