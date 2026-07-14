#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);

require_once $root . '/core/helpers/runtime.php';
require_once $root . '/core/helpers/settings.php';

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

class SrRuntimeCountingPdo extends PDO
{
    public int $queryCount = 0;

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->queryCount++;
        if ($fetchMode === null) {
            return parent::query($query);
        }

        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
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

$moduleFixturePdo = new SrRuntimeCountingPdo('sqlite::memory:');
$moduleFixturePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
sr_runtime_helper_assert(
    sr_enabled_module_keys($moduleFixturePdo) === [] && sr_installed_module_keys($moduleFixturePdo) === [] && !sr_module_enabled($moduleFixturePdo, 'reaction'),
    'Module registry helpers should fail closed when sr_modules is absent.'
);
$moduleFixturePdo->exec(
    "CREATE TABLE sr_modules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module_key TEXT NOT NULL,
        status TEXT NOT NULL
    )"
);
$moduleFixturePdo->exec("INSERT INTO sr_modules (module_key, status) VALUES ('reaction', 'enabled'), ('coupon', 'disabled'), ('bad-key', 'enabled')");
sr_clear_module_registry_cache();
$moduleFixturePdo->queryCount = 0;
sr_runtime_helper_assert(
    sr_enabled_module_keys($moduleFixturePdo) === ['reaction'] && sr_installed_module_keys($moduleFixturePdo) === ['reaction', 'coupon'] && sr_module_enabled($moduleFixturePdo, 'reaction') && !sr_module_enabled($moduleFixturePdo, 'coupon'),
    'Module registry helpers should read installed and enabled safe module keys.'
);
sr_runtime_helper_assert($moduleFixturePdo->queryCount === 2, 'Module registry helpers must query enabled and installed module lists only once per request cache token.');
$moduleFixturePdo->exec("UPDATE sr_modules SET status = 'enabled' WHERE module_key = 'coupon'");
sr_runtime_helper_assert(!sr_module_enabled($moduleFixturePdo, 'coupon'), 'Module registry cache must remain stable until explicitly invalidated.');
sr_clear_module_registry_cache();
sr_runtime_helper_assert(sr_module_enabled($moduleFixturePdo, 'coupon'), 'Module registry cache invalidation must expose status changes in the same request.');
$moduleFixturePdo->exec("UPDATE sr_modules SET status = 'disabled' WHERE module_key = 'coupon'");
sr_clear_module_registry_cache();
sr_runtime_helper_assert(
    sr_enabled_module_asset_paths($moduleFixturePdo, ['reaction' => '/modules/reaction/assets/module.css', 'coupon' => '/modules/coupon/assets/module.css'])
        === ['/modules/reaction/assets/module.css'],
    'Enabled module asset helper should only return assets for enabled modules.'
);
sr_runtime_helper_assert(
    sr_enabled_module_asset_paths(null, ['reaction' => '/modules/reaction/assets/module.css']) === [],
    'Enabled module asset helper should fail closed without a PDO.'
);

$settingsFixturePdos = [];
foreach (['alpha', 'beta'] as $fixtureName) {
    $settingsPdo = new PDO('sqlite::memory:');
    $settingsPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $settingsPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $settingsPdo->exec('CREATE TABLE sr_site_settings (setting_key TEXT, setting_value TEXT, value_type TEXT)');
    $settingsPdo->exec('CREATE TABLE sr_modules (id INTEGER PRIMARY KEY, module_key TEXT)');
    $settingsPdo->exec('CREATE TABLE sr_module_settings (module_id INTEGER, setting_key TEXT, setting_value TEXT, value_type TEXT)');
    $settingsPdo->prepare('INSERT INTO sr_site_settings (setting_key, setting_value, value_type) VALUES (:setting_key, :setting_value, :value_type)')->execute([
        'setting_key' => 'site.name',
        'setting_value' => $fixtureName,
        'value_type' => 'string',
    ]);
    $settingsPdo->exec("INSERT INTO sr_modules (id, module_key) VALUES (1, 'member')");
    $settingsPdo->prepare('INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type) VALUES (1, :setting_key, :setting_value, :value_type)')->execute([
        'setting_key' => 'fixture.name',
        'setting_value' => $fixtureName,
        'value_type' => 'string',
    ]);
    $settingsFixturePdos[$fixtureName] = $settingsPdo;
}
sr_clear_site_settings_cache();
sr_clear_module_settings_cache();
sr_runtime_helper_assert(
    sr_site_setting($settingsFixturePdos['alpha'], 'site.name') === 'alpha'
        && sr_site_setting($settingsFixturePdos['beta'], 'site.name') === 'beta',
    'Site setting request cache must remain scoped to its PDO connection.'
);
sr_runtime_helper_assert(
    sr_module_setting($settingsFixturePdos['alpha'], 'member', 'fixture.name') === 'alpha'
        && sr_module_setting($settingsFixturePdos['beta'], 'member', 'fixture.name') === 'beta',
    'Module setting request cache must remain scoped to its PDO connection.'
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
$ratePdo = new PDO('sqlite::memory:');
$ratePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ratePdo->exec(
    'CREATE TABLE sr_rate_limits (
        rate_key TEXT NOT NULL PRIMARY KEY,
        bucket TEXT NOT NULL,
        subject_hash TEXT NOT NULL,
        attempt_count INTEGER NOT NULL DEFAULT 0,
        expires_at TEXT NOT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )'
);
sr_rate_limit_increment($ratePdo, 'member.login.ip', '203.0.113.10', 60);
sr_rate_limit_increment($ratePdo, 'member.login.ip', '203.0.113.10', 60);
sr_runtime_helper_assert(
    sr_rate_limit_count($ratePdo, 'member.login.ip', '203.0.113.10', 60) === 2,
    'Rate limit count should increment within the active window.'
);
sr_runtime_helper_assert(
    sr_rate_limit_count($ratePdo, '', '203.0.113.10', 60) === PHP_INT_MAX,
    'Invalid rate limit count inputs must fail closed.'
);
$missingRateTablePdo = new PDO('sqlite::memory:');
$missingRateTablePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
sr_runtime_helper_assert(
    sr_rate_limit_count($missingRateTablePdo, 'member.login.ip', '203.0.113.10', 60) === PHP_INT_MAX,
    'Rate limit storage read failures must fail closed.'
);
$rateRow = $ratePdo->query('SELECT rate_key, subject_hash FROM sr_rate_limits LIMIT 1')->fetch(PDO::FETCH_ASSOC);
sr_runtime_helper_assert(
    is_array($rateRow)
        && ($rateRow['rate_key'] ?? '') === hash_hmac('sha256', 'member.login.ip|203.0.113.10', 'runtime-helper-test-key')
        && ($rateRow['subject_hash'] ?? '') === hash_hmac('sha256', '203.0.113.10', 'runtime-helper-test-key'),
    'Rate limit table should store HMAC key and subject hash, not raw subject values.'
);
$ratePdo->exec("UPDATE sr_rate_limits SET expires_at = '2000-01-01 00:00:00'");
sr_runtime_helper_assert(
    sr_rate_limit_count($ratePdo, 'member.login.ip', '203.0.113.10', 60) === 0,
    'Expired rate limit rows should not count.'
);
sr_rate_limit_increment($ratePdo, 'member.login.ip', '203.0.113.10', 60);
sr_runtime_helper_assert(
    sr_rate_limit_count($ratePdo, 'member.login.ip', '203.0.113.10', 60) === 1,
    'Expired rate limit rows should reset to one on the next increment.'
);
sr_rate_limit_increment($ratePdo, '', '203.0.113.10', 60);
sr_rate_limit_increment($ratePdo, 'member.login.ip', '', 60);
sr_runtime_helper_assert(
    (int) $ratePdo->query('SELECT COUNT(*) FROM sr_rate_limits')->fetchColumn() === 1,
    'Invalid rate limit input should not create rows.'
);
$secretConfigA = ['app_key' => 'runtime-secret-helper-key-a'];
$secretConfigB = ['app_key' => 'runtime-secret-helper-key-b'];
sr_set_runtime_config($secretConfigA);
sr_runtime_helper_assert(
    sr_secret_at_rest_crypto_available(),
    'Secret-at-rest crypto should be available when app_key is configured.'
);
$secretCiphertext = sr_secret_at_rest_encrypt('totp-secret-fixture', 'member.mfa.totp');
sr_runtime_helper_assert(
    str_starts_with($secretCiphertext, 'sr2:sodium:') || str_starts_with($secretCiphertext, 'sr2:openssl:'),
    'Secret-at-rest ciphertext should use the app-key-bound sr2 envelope.'
);
sr_runtime_helper_assert(
    sr_secret_at_rest_decrypt($secretCiphertext, 'member.mfa.totp') === 'totp-secret-fixture',
    'Secret-at-rest ciphertext should decrypt with the original app key and purpose.'
);
sr_runtime_helper_assert(
    sr_secret_at_rest_decrypt($secretCiphertext, 'member.mfa.totp', $secretConfigB) === null,
    'Secret-at-rest ciphertext should not decrypt after app_key changes.'
);
sr_runtime_helper_assert(
    sr_secret_at_rest_decrypt($secretCiphertext, 'member.mfa.recovery') === null,
    'Secret-at-rest ciphertext should not decrypt with a different purpose.'
);
$secretFingerprintA = sr_secret_at_rest_fingerprint('totp-secret-fixture', 'member.mfa.totp', $secretConfigA);
$secretFingerprintB = sr_secret_at_rest_fingerprint('totp-secret-fixture', 'member.mfa.totp', $secretConfigB);
sr_runtime_helper_assert(
    $secretFingerprintA !== '' && $secretFingerprintA !== $secretFingerprintB,
    'Secret-at-rest fingerprints should be app-key-bound and non-empty.'
);
$secretMissingAppKeyFailed = false;
try {
    sr_secret_at_rest_encrypt('totp-secret-fixture', 'member.mfa.totp', ['app_key' => '']);
} catch (RuntimeException $exception) {
    $secretMissingAppKeyFailed = $exception->getMessage() === 'app_key is required.';
}
sr_runtime_helper_assert(
    $secretMissingAppKeyFailed,
    'Secret-at-rest encryption should fail closed without app_key.'
);
$secretFingerprintMissingAppKeyFailed = false;
try {
    sr_secret_at_rest_fingerprint('totp-secret-fixture', 'member.mfa.totp', ['app_key' => '']);
} catch (RuntimeException $exception) {
    $secretFingerprintMissingAppKeyFailed = $exception->getMessage() === 'app_key is required.';
}
sr_runtime_helper_assert(
    $secretFingerprintMissingAppKeyFailed,
    'Secret-at-rest fingerprint should fail closed without app_key.'
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

sr_runtime_helper_assert(
    sr_module_route_matches_request('GET /fixture/*', 'GET /fixture/child'),
    'Wildcard module route should match child paths.'
);
sr_runtime_helper_assert(
    !sr_module_route_matches_request('GET /fixture/*', 'GET /fixture/'),
    'Wildcard module route should not match its bare prefix path.'
);
sr_runtime_helper_assert(
    sr_module_routes_conflict('GET /fixture/*', 'GET /fixture/child'),
    'Route conflict detection should match wildcard child path behavior.'
);
sr_runtime_helper_assert(
    !sr_module_routes_conflict('GET /fixture/*', 'GET /fixture/'),
    'Route conflict detection should not reject paths the wildcard route cannot match.'
);
sr_runtime_helper_assert(
    sr_module_routes_conflict('GET /fixture/*', 'GET /fixture/child/*'),
    'Nested wildcard module routes should conflict when their request spaces overlap.'
);

if ($errors !== []) {
    fwrite(STDERR, "runtime helper checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "runtime helper checks completed.\n";
