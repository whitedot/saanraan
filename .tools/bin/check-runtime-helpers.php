#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);

require_once $root . '/core/helpers/runtime.php';

$errors = [];

function sr_runtime_helper_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_runtime_helper_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_runtime_helper_error($message);
    }
}

function sr_runtime_helper_server(array $server): void
{
    $_SERVER = $server;
}

$proxyConfig = [
    'security' => [
        'trusted_proxies' => [
            '10.0.0.0/8',
            '10.0.0.0/8',
            '203.0.113.10',
            '2001:db8::/32',
            '',
            'not-an-ip',
        ],
    ],
];

sr_runtime_helper_assert(
    sr_trusted_proxy_entries($proxyConfig) === ['10.0.0.0/8', '203.0.113.10', '2001:db8::/32'],
    'Trusted proxy entries should keep valid unique IP/CIDR values.'
);
sr_runtime_helper_assert(
    count(sr_trusted_proxy_config_errors($proxyConfig)) === 2,
    'Trusted proxy config errors should count invalid entries.'
);
sr_runtime_helper_assert(
    sr_ip_matches_trusted_proxy('10.2.3.4', '10.0.0.0/8'),
    'IPv4 CIDR trusted proxy match failed.'
);
sr_runtime_helper_assert(
    !sr_ip_matches_trusted_proxy('11.2.3.4', '10.0.0.0/8'),
    'IPv4 CIDR trusted proxy mismatch failed.'
);
sr_runtime_helper_assert(
    sr_ip_matches_trusted_proxy('2001:db8::1234', '2001:db8::/32'),
    'IPv6 CIDR trusted proxy match failed.'
);
sr_runtime_helper_assert(
    sr_ip_matches_trusted_proxy('203.0.113.10', '203.0.113.10'),
    'Exact trusted proxy match failed.'
);

sr_runtime_helper_server([
    'REMOTE_ADDR' => '10.0.0.10',
    'HTTP_X_FORWARDED_PROTO' => 'https',
]);
sr_runtime_helper_assert(
    sr_is_https_request($proxyConfig),
    'Trusted X-Forwarded-Proto=https should be treated as HTTPS.'
);

sr_runtime_helper_server([
    'REMOTE_ADDR' => '198.51.100.1',
    'HTTP_X_FORWARDED_PROTO' => 'https',
]);
sr_runtime_helper_assert(
    !sr_is_https_request($proxyConfig),
    'Untrusted X-Forwarded-Proto should not be treated as HTTPS.'
);

sr_runtime_helper_server([
    'REMOTE_ADDR' => '10.0.0.10',
    'HTTP_X_FORWARDED_FOR' => '198.51.100.25, 10.0.0.8',
]);
sr_set_runtime_config($proxyConfig);
sr_runtime_helper_assert(
    sr_forwarded_client_ip($proxyConfig) === '198.51.100.25',
    'Forwarded client IP should select the nearest untrusted address.'
);
sr_runtime_helper_assert(
    sr_client_ip() === '198.51.100.25',
    'Client IP should use trusted forwarded address.'
);
sr_runtime_helper_server([
    'HTTP_USER_AGENT' => "Saanraan\tTest\r\nInjected: value",
]);
sr_runtime_helper_assert(
    sr_client_user_agent() === 'SaanraanTestInjected: value',
    'Client user agent should remove control characters before logging.'
);

sr_runtime_helper_assert(
    sr_http_host_is_valid('example.com'),
    'Normal host should be valid.'
);
sr_runtime_helper_assert(
    sr_http_host_is_valid('example.com:8443'),
    'Host with valid port should be valid.'
);
sr_runtime_helper_assert(
    sr_http_host_is_valid('[2001:db8::1]:8443'),
    'Bracketed IPv6 host with valid port should be valid.'
);
sr_runtime_helper_assert(
    !sr_http_host_is_valid('example.com:99999'),
    'Host with invalid port should be rejected.'
);
sr_runtime_helper_assert(
    !sr_http_host_is_valid('example.com/path'),
    'Host with slash should be rejected.'
);
sr_runtime_helper_assert(
    !sr_http_host_is_valid('example.com\\evil.test'),
    'Host with backslash should be rejected.'
);
sr_runtime_helper_assert(
    !sr_http_host_is_valid('user@example.com'),
    'Host with userinfo separator should be rejected.'
);
sr_runtime_helper_server([
    'HTTP_HOST' => 'example.com\\evil.test',
    'SCRIPT_NAME' => '/index.php',
]);
sr_runtime_helper_assert(
    sr_current_base_url() === '',
    'Invalid HTTP_HOST should not produce a current base URL.'
);

sr_runtime_helper_assert(
    sr_session_cookie_secure(['security' => ['force_https' => true]]) === true,
    'force_https should force Secure session cookies.'
);
sr_runtime_helper_assert(
    sr_prefix_sql_identifiers("SELECT * FROM sr_modules WHERE note = 'sr_modules' AND name = \"sr_site_settings\" AND marker = @sr_marker", 'custom_')
        === "SELECT * FROM custom_modules WHERE note = 'sr_modules' AND name = \"sr_site_settings\" AND marker = @sr_marker",
    'SQL prefix rewriting should not rewrite string literals or user variables.'
);
sr_runtime_helper_assert(
    sr_prefix_sql_identifiers('SELECT * FROM `sr_modules` INNER JOIN sr_site_settings s ON s.setting_key = sr_modules.module_key', 'custom_')
        === 'SELECT * FROM `custom_modules` INNER JOIN custom_site_settings s ON s.setting_key = custom_modules.module_key',
    'SQL prefix rewriting should rewrite bare and backtick-quoted identifiers.'
);
sr_runtime_helper_assert(
    sr_mail_http_api_endpoint_is_allowed('https://93.184.216.34/mail'),
    'Public HTTPS mail API endpoint should be allowed.'
);
sr_runtime_helper_assert(
    !sr_mail_http_api_endpoint_is_allowed('http://93.184.216.34/mail'),
    'HTTP mail API endpoint should be rejected.'
);
sr_runtime_helper_assert(
    !sr_mail_http_api_endpoint_is_allowed('https://127.0.0.1/mail'),
    'Local mail API endpoint should be rejected.'
);
sr_runtime_helper_assert(
    !sr_mail_http_api_endpoint_is_allowed('https://169.254.169.254/latest/meta-data'),
    'Link-local mail API endpoint should be rejected.'
);
sr_runtime_helper_assert(
    sr_public_network_addresses_are_allowed(['93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946']),
    'Public IPv4 and IPv6 DNS addresses should be allowed.'
);
sr_runtime_helper_assert(
    !sr_public_network_addresses_are_allowed(['93.184.216.34', 'fd00::1']),
    'A private IPv6 DNS address should reject the public network host.'
);
sr_runtime_helper_assert(
    !sr_public_network_addresses_are_allowed(['93.184.216.34', '::1']),
    'A loopback IPv6 DNS address should reject the public network host.'
);
sr_runtime_helper_assert(
    !sr_is_public_network_host('100.64.0.1'),
    'Carrier-grade NAT IPv4 addresses should not be treated as public network hosts.'
);
sr_runtime_helper_assert(
    !sr_is_public_network_host('192.0.2.1'),
    'Documentation IPv4 addresses should not be treated as public network hosts.'
);
sr_runtime_helper_assert(
    !sr_is_public_network_host('224.0.0.1'),
    'Multicast IPv4 addresses should not be treated as public network hosts.'
);
sr_runtime_helper_assert(
    !sr_is_public_network_host('2001:db8::1'),
    'Documentation IPv6 addresses should not be treated as public network hosts.'
);
sr_set_runtime_config(['app_key' => 'runtime-helper-test-key']);
sr_runtime_helper_assert(
    sr_rate_limit_key('member.login.ip', '203.0.113.10') === hash_hmac('sha256', 'member.login.ip|203.0.113.10', 'runtime-helper-test-key'),
    'Rate limit keys should use the app key HMAC when runtime config is available.'
);
sr_runtime_helper_assert(
    sr_rate_limit_hash('203.0.113.10') === hash_hmac('sha256', '203.0.113.10', 'runtime-helper-test-key'),
    'Rate limit subject hashes should use the app key HMAC when runtime config is available.'
);
sr_set_runtime_config([]);
sr_runtime_helper_assert(
    !sr_is_http_url('https://user@example.com/path'),
    'HTTP URL with userinfo should be rejected.'
);
sr_runtime_helper_assert(
    !sr_is_http_url('https://example.com\\evil.test/path'),
    'HTTP URL with backslash should be rejected.'
);
sr_runtime_helper_assert(
    sr_is_site_base_url('https://example.com/base'),
    'Site base URL with path should be allowed.'
);
sr_runtime_helper_assert(
    !sr_is_site_base_url('https://example.com/base?token=1'),
    'Site base URL with query should be rejected.'
);
sr_runtime_helper_assert(
    !sr_is_site_base_url('https://example.com/base#fragment'),
    'Site base URL with fragment should be rejected.'
);
sr_runtime_helper_assert(
    sr_mail_header_encode("Hello\r\nBcc: bad@example.com") === 'HelloBcc: bad@example.com',
    'Mail header encoder should remove CRLF from ASCII values.'
);
sr_runtime_helper_assert(
    strpos(sr_mail_header_encode("인증\r\nBcc: bad@example.com"), "\n") === false,
    'Mail header encoder should remove CRLF from encoded values.'
);
sr_runtime_helper_assert(
    sr_mail_header_encode("Hello\t\0Bcc: bad@example.com") === 'HelloBcc: bad@example.com',
    'Mail header encoder should remove non-newline control characters from ASCII values.'
);
sr_runtime_helper_server([
    'SERVER_NAME' => "example.com\r\nQUIT",
]);
sr_runtime_helper_assert(
    sr_smtp_server_name() === 'localhost',
    'SMTP server name should reject command injection characters.'
);
sr_runtime_helper_server([
    'SERVER_NAME' => 'example.com',
]);
sr_runtime_helper_assert(
    sr_smtp_server_name() === 'example.com',
    'SMTP server name should keep valid hostnames.'
);

putenv('SR_TEST_APP_KEY=env-secret');
sr_runtime_helper_assert(
    sr_app_key(['app_key' => 'file-secret', 'secrets' => ['app_key_env' => 'SR_TEST_APP_KEY']]) === 'env-secret',
    'App key environment override failed.'
);
putenv('SR_TEST_APP_KEY');
sr_runtime_helper_assert(
    sr_app_key(['app_key' => 'file-secret', 'secrets' => ['app_key_env' => 'SR_TEST_APP_KEY']]) === 'file-secret',
    'App key file fallback failed.'
);

if ($errors !== []) {
    fwrite(STDERR, "runtime helper checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "runtime helper checks completed.\n";
