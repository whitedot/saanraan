#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

function sr_auth_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_auth_runtime_read(string $path): string
{
    global $root;

    $fullPath = $root . '/' . $path;
    $content = is_file($fullPath) ? file_get_contents($fullPath) : false;
    if (!is_string($content)) {
        sr_auth_runtime_error('Cannot read file: ' . $path);
        return '';
    }

    return $content;
}

function sr_auth_runtime_require(string $path, string $pattern, string $message): void
{
    $content = sr_auth_runtime_read($path);
    if ($content === '') {
        return;
    }

    if (preg_match($pattern, $content) !== 1) {
        sr_auth_runtime_error($message . ': ' . $path);
    }
}

function sr_auth_runtime_forbid(string $path, string $pattern, string $message): void
{
    $content = sr_auth_runtime_read($path);
    if ($content === '') {
        return;
    }

    if (preg_match($pattern, $content) === 1) {
        sr_auth_runtime_error($message . ': ' . $path);
    }
}

foreach ([
    'database/core/install.sql',
] as $path) {
    sr_auth_runtime_require($path, '/CREATE TABLE IF NOT EXISTS sr_sessions\b/', 'Runtime session table is missing');
    sr_auth_runtime_require($path, '/CREATE TABLE IF NOT EXISTS sr_rate_limits\b/', 'Rate limit table is missing');
    sr_auth_runtime_require($path, '/session_id_hash CHAR\(64\) NOT NULL/', 'Runtime session hash column is missing');
    sr_auth_runtime_require($path, '/UNIQUE KEY uq_sr_sessions_session_id_hash/', 'Runtime session hash unique key is missing');
    sr_auth_runtime_require($path, '/UNIQUE KEY uq_sr_rate_limits_key/', 'Rate limit unique key is missing');
}

sr_auth_runtime_require('database/core/updates/2026.04.006.sql', '/CREATE TABLE IF NOT EXISTS sr_sessions\b/', 'Runtime session table is missing from base update');
sr_auth_runtime_require('database/core/updates/2026.04.006.sql', '/CREATE TABLE IF NOT EXISTS sr_rate_limits\b/', 'Rate limit table is missing from base update');
sr_auth_runtime_require('database/core/updates/2026.04.007.sql', '/session_id_hash CHAR\(64\)/', 'Runtime session hash migration column is missing');
sr_auth_runtime_require('database/core/updates/2026.04.007.sql', '/SHA2\(session_id, 256\)/', 'Runtime session hash migration does not hash existing session ids');
sr_auth_runtime_require('database/core/updates/2026.04.007.sql', '/SET session_id = NULL/', 'Runtime session hash migration does not clear raw session ids');
sr_auth_runtime_require('database/core/updates/2026.04.007.sql', '/uq_sr_sessions_session_id_hash/', 'Runtime session hash migration unique key is missing');

sr_auth_runtime_require('core/actions/install.php', "/'secrets'\\s*=>\\s*\\[/", 'Install config secrets block is missing');
sr_auth_runtime_require('core/actions/install.php', "/'app_key_env'\\s*=>\\s*'SR_APP_KEY'/", 'Install config app key env is missing');
sr_auth_runtime_require('core/actions/install.php', "/'security'\\s*=>\\s*\\[/", 'Install config security block is missing');
sr_auth_runtime_require('core/actions/install.php', "/'trusted_proxies'\\s*=>\\s*\\[\\]/", 'Install config trusted proxies default is missing');
sr_auth_runtime_require('core/actions/install.php', "/'session'\\s*=>\\s*\\[/", 'Install config session block is missing');
sr_auth_runtime_require('core/actions/install.php', "/'handler'\\s*=>\\s*'database'/", 'Install config database session handler is missing');
sr_auth_runtime_require('core/actions/install.php', "/'mail'\\s*=>\\s*\\[/", 'Install config mail block is missing');
sr_auth_runtime_require('core/actions/install.php', '/\'message\'\s*=>\s*sr_log_sensitive_text_sanitize\(sr_log_line_value\(\$exception->getMessage\(\), 500\)\)/', 'Install failure marker message should be normalized and secret-masked');
sr_auth_runtime_require('core/actions/install.php', '/sr_log_sensitive_text_sanitize\(sr_log_line_value\(\(string\) \(\$decodedPreviousInstallFailure\[\'message\'\] \?\? \'\'\), 500\)\)/', 'Previous install failure marker message should be secret-masked before display');
sr_auth_runtime_require('core/actions/install.php', '/\$errors\[\]\s*=\s*sr_log_sensitive_text_sanitize\(sr_log_line_value\(\$exception->getMessage\(\), 500\)\)/', 'Install debug exception message should be secret-masked before display');
sr_auth_runtime_require('core/actions/install.php', "/sr_post_string_without_truncation\\('admin_password', 255\\)/", 'Install admin password should reject overlong raw input instead of truncating it');
sr_auth_runtime_require('core/actions/install.php', "/sr_post_string_without_truncation\\('admin_password_confirm', 255\\)/", 'Install admin password confirmation should reject overlong raw input instead of truncating it');
sr_auth_runtime_require('core/actions/install.php', '/\$adminPassword === null \|\| \$adminPasswordConfirm === null/', 'Install admin password overlong check is missing');
sr_auth_runtime_require('core/views/error.php', '/sr_log_sensitive_text_sanitize\(sr_log_line_value\(\$exception->getMessage\(\), 1000\)\)/', 'Debug error view exception message should be secret-masked before display');

sr_auth_runtime_require('core/helpers/ops.php', "/\\\$temporary\\s*=\\s*\\\$configDir\\s*\\.\\s*'\\/config-'\\s*\\.\\s*\\\$suffix\\s*\\.\\s*'\\.tmp\\.php'/", 'Config writer temporary file should keep a PHP extension');
sr_auth_runtime_require('core/helpers/ops.php', '/bin2hex\(random_bytes\(6\)\)/', 'Config writer temporary file should use a random suffix');
sr_auth_runtime_require('core/helpers/ops.php', '/function sr_fetch_http_response\(string \$url\): \?array\s*\{\s*if \(!sr_is_public_http_url\(\$url\)\)/', 'Install exposure HTTP fetch should reject non-public URLs');
sr_auth_runtime_require('core/helpers/ops.php', "/'follow_location'\\s*=>\\s*0/", 'Install exposure HTTP fetch should not follow redirects');
sr_auth_runtime_require('core/helpers/ops.php', "/'max_redirects'\\s*=>\\s*0/", 'Install exposure HTTP fetch should disable redirects');
sr_auth_runtime_require('core/helpers/ops.php', '/function sr_public_internal_access_findings\(string \$baseUrl\): array\s*\{\s*if \(!sr_is_public_http_url\(\$baseUrl\)\)/', 'Install exposure checks should reject non-public base URLs');
sr_auth_runtime_require('core/helpers/ops.php', '/function sr_log_line_value\(string \$value, int \$maxLength = 1000\): string/', 'Log line value sanitizer is missing');
sr_auth_runtime_require('core/helpers/ops.php', '/preg_replace\(\'\/\[\\\\x00-\\\\x1F\\\\x7F\]\+\/\', \' \', \$value\)/', 'Log line sanitizer should remove control characters');
sr_auth_runtime_require('core/helpers/ops.php', '/sr_log_line_value\(\$exception->getMessage\(\), 1000\)/', 'Exception messages should be normalized before file logging');

sr_auth_runtime_require('core/helpers/runtime.php', '/class SrDatabaseSessionHandler implements SessionHandlerInterface/', 'Database session handler is missing');
sr_auth_runtime_require('core/helpers/runtime.php', '/function sr_is_https_request\(\?array \$config = null\): bool/', 'Proxy-aware HTTPS helper signature is missing');
sr_auth_runtime_require('core/helpers/runtime.php', '/HTTP_X_FORWARDED_PROTO/', 'Forwarded proto handling is missing');
sr_auth_runtime_require('core/helpers/runtime.php', '/function sr_forwarded_client_ip\(\?array \$config = null\): string/', 'Forwarded client IP helper is missing');
sr_auth_runtime_require('core/helpers/runtime.php', '/function sr_trusted_proxy_entries\(array \$config\): array/', 'Trusted proxy entry helper is missing');
sr_auth_runtime_require('core/helpers/runtime.php', '/function sr_trusted_proxy_config_errors\(array \$config\): array/', 'Trusted proxy config validation is missing');
sr_auth_runtime_require('core/helpers/runtime.php', '/function sr_send_smtp_mail\(/', 'SMTP mail transport helper is missing');
sr_auth_runtime_require('core/helpers/runtime.php', '/function sr_smtp_server_name\(\): string/', 'SMTP server name sanitizer is missing');
sr_auth_runtime_require('core/helpers/runtime.php', '/function sr_send_http_api_mail\(/', 'HTTP API mail transport helper is missing');
sr_auth_runtime_require('core/helpers/runtime.php', "/'from_email'\\s*=>\\s*\\\$fromEmail/", 'HTTP API mail payload from email is missing');
sr_auth_runtime_require('core/helpers/runtime.php', '/preg_match\([^;]+\$bearerToken\)/', 'HTTP API bearer token control character guard is missing');
sr_auth_runtime_require('core/helpers/runtime.php', '/function sr_mail_http_api_endpoint_is_allowed\(/', 'HTTP API mail endpoint validation helper is missing');
sr_auth_runtime_require('core/helpers/runtime.php', '/sr_mail_http_api_endpoint_is_allowed\(\$endpoint\)/', 'HTTP API mail transport does not validate endpoint scope');
sr_auth_runtime_require('core/helpers/runtime.php', "/'follow_location'\\s*=>\\s*0/", 'HTTP API mail transport should not follow redirects after endpoint validation');
sr_auth_runtime_require('core/helpers/runtime.php', "/'max_redirects'\\s*=>\\s*0/", 'HTTP API mail transport should disable redirects after endpoint validation');
sr_auth_runtime_require('core/helpers/runtime.php', '/function sr_ip_is_public_network_address\(/', 'Explicit public network IP helper is missing');
sr_auth_runtime_require('core/helpers/runtime.php', "/'100\\.64\\.0\\.0\\/10'/", 'CGNAT range should be rejected for outbound public URL checks');
sr_auth_runtime_require('core/helpers/runtime.php', "/'224\\.0\\.0\\.0\\/4'/", 'Multicast range should be rejected for outbound public URL checks');
sr_auth_runtime_require('core/helpers/runtime.php', '/function sr_rate_limit_count\(/', 'Rate limit count helper is missing');
sr_auth_runtime_require('core/helpers/runtime.php', '/function sr_rate_limit_increment\(/', 'Rate limit increment helper is missing');
sr_auth_runtime_require('core/helpers/runtime.php', '/function sr_app_key\(array \$config\): string/', 'App key resolver is missing');
sr_auth_runtime_require('core/helpers/runtime.php', '/session_id_hash/', 'Database session handler should use hashed session ids when available');
sr_auth_runtime_require('core/helpers/runtime.php', '/hash\(\'sha256\', \$id\)/', 'Database session handler should hash runtime session ids');
sr_auth_runtime_require('core/helpers/runtime.php', '/refreshSessionIdHashSupport/', 'Database session handler should refresh hash-column support after updates');
sr_auth_runtime_require('core/helpers/runtime.php', '/if \(!\$this->lockAcquired\) \{\s*return false;\s*\}/', 'Database session handler should fail closed when session lock is not acquired');

sr_auth_runtime_require('modules/member/helpers/throttle.php', '/sr_rate_limit_count\(/', 'Member throttle does not use rate limit counters');
sr_auth_runtime_require('modules/member/helpers/throttle.php', '/sr_member_auth_log_count\(/', 'Member throttle fallback is missing');
sr_auth_runtime_require('modules/member/helpers/accounts.php', '/sr_member_record_auth_rate_limits\(/', 'Auth log rate limit recording hook is missing');

sr_auth_runtime_require('modules/admin/helpers/retention.php', "/'runtime_sessions'\\s*=>\\s*\\[/", 'Runtime sessions retention target is missing');
sr_auth_runtime_require('modules/admin/helpers/retention.php', "/'rate_limits'\\s*=>\\s*\\[/", 'Rate limits retention target is missing');
sr_auth_runtime_forbid('modules/admin/helpers/dashboard.php', '/sr_admin_dashboard_auth_runtime_summary|sr_admin_dashboard_sensitive_setting_summary|sr_admin_dashboard_mail_transport_ready/', 'Removed dashboard diagnostics helpers should not remain');
sr_auth_runtime_forbid('modules/admin/views/dashboard.php', '/인증 런타임|고위험 설정|install_protection|sensitive_settings|auth_runtime|data-admin-dashboard-section="modules"/', 'Removed dashboard diagnostics should not be rendered');
sr_auth_runtime_require('docs/deployment-protection.md', '/로드밸런서와 클라우드 런타임/', 'Cloud runtime deployment documentation is missing');
sr_auth_runtime_require('docs/deployment-protection.md', '/http_api/', 'HTTP API mail documentation is missing');

if ($errors !== []) {
    fwrite(STDERR, "auth runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "auth runtime checks completed.\n";
