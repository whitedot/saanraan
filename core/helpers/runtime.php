<?php

declare(strict_types=1);

function sr_set_runtime_config(array $config): void
{
    $GLOBALS['sr_runtime_config'] = $config;
}

function sr_runtime_config(): array
{
    $config = $GLOBALS['sr_runtime_config'] ?? [];
    return is_array($config) ? $config : [];
}

function sr_start_session(?array $config = null, ?PDO $pdo = null): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $config = is_array($config) ? $config : sr_runtime_config();
        $cookiePath = sr_base_path();
        $cookiePath = $cookiePath === '' ? '/' : $cookiePath;
        $cookieSecure = sr_session_cookie_secure($config);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_name('sr_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'httponly' => true,
            'secure' => $cookieSecure,
            'samesite' => 'Lax',
        ]);
        sr_register_session_handler($config, $pdo);
        session_start();
    }
}

function sr_session_cookie_secure(array $config): bool
{
    $security = isset($config['security']) && is_array($config['security']) ? $config['security'] : [];
    if (!empty($security['force_https'])) {
        return true;
    }

    return sr_is_https_request($config);
}

function sr_register_session_handler(array $config, ?PDO $pdo): void
{
    if (!$pdo instanceof PDO) {
        return;
    }

    $session = isset($config['session']) && is_array($config['session']) ? $config['session'] : [];
    if ((string) ($session['handler'] ?? 'database') !== 'database') {
        return;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_sessions LIMIT 1');
    } catch (Throwable $exception) {
        return;
    }

    $lifetime = (int) ($session['lifetime_seconds'] ?? 86400);
    $lifetime = max(300, min(2592000, $lifetime));
    ini_set('session.gc_maxlifetime', (string) $lifetime);

    $handler = new SrDatabaseSessionHandler($pdo, $lifetime);
    session_set_save_handler($handler, true);
    $GLOBALS['sr_session_handler'] = $handler;
}

function sr_send_security_headers(?array $config = null): void
{
    if (headers_sent()) {
        return;
    }

    $contentSecurityPolicy = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://cdn.ckeditor.com",
        "connect-src 'self' https://cdn.jsdelivr.net",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.ckeditor.com",
        "font-src 'self' https://cdn.jsdelivr.net data:",
        "img-src 'self' https: data:",
        "frame-src 'self' https://www.youtube-nocookie.com https://www.youtube.com",
        "base-uri 'self'",
        "frame-ancestors 'self'",
        "form-action 'self'",
    ];

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: no-referrer');
    header('Content-Security-Policy: ' . implode('; ', $contentSecurityPolicy));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    $security = is_array($config) && isset($config['security']) && is_array($config['security']) ? $config['security'] : [];
    if ((sr_is_https_request($config) || !empty($security['force_https'])) && (empty($config) || (string) ($config['env'] ?? 'production') === 'production')) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function sr_is_https_request(?array $config = null): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }

    $config = is_array($config) ? $config : sr_runtime_config();
    if (!sr_request_from_trusted_proxy($config)) {
        return false;
    }

    $forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    if ($forwardedProto === 'https') {
        return true;
    }

    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on';
}

function sr_current_base_url(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (!sr_http_host_is_valid($host)) {
        return '';
    }

    return (sr_is_https_request() ? 'https://' : 'http://') . $host . sr_base_path();
}

function sr_http_host_is_valid(string $host): bool
{
    if ($host === '' || strlen($host) > 255 || preg_match('/[\x00-\x1F\x7F\/\\\\@]/', $host) === 1) {
        return false;
    }

    if (preg_match('/\A\[([0-9A-Fa-f:.]+)\](?::([0-9]{1,5}))?\z/', $host, $matches) === 1) {
        return filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            && sr_http_port_is_valid((string) ($matches[2] ?? ''));
    }

    if (substr_count($host, ':') > 1) {
        return false;
    }

    $hostname = $host;
    $port = '';
    if (strpos($host, ':') !== false) {
        [$hostname, $port] = explode(':', $host, 2);
    }

    return sr_http_hostname_is_valid($hostname) && sr_http_port_is_valid($port);
}

function sr_http_hostname_is_valid(string $hostname): bool
{
    $hostname = strtolower($hostname);
    if ($hostname === 'localhost' || filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        return true;
    }

    if ($hostname === '' || strlen($hostname) > 253 || preg_match('/\A[a-z0-9.-]+\z/', $hostname) !== 1) {
        return false;
    }

    foreach (explode('.', $hostname) as $label) {
        if ($label === '' || strlen($label) > 63 || $label[0] === '-' || substr($label, -1) === '-') {
            return false;
        }
    }

    return true;
}

function sr_http_port_is_valid(string $port): bool
{
    if ($port === '') {
        return true;
    }

    if (preg_match('/\A[0-9]{1,5}\z/', $port) !== 1) {
        return false;
    }

    $portNumber = (int) $port;
    return $portNumber >= 1 && $portNumber <= 65535;
}

function sr_is_local_host(string $baseUrl): bool
{
    $host = parse_url($baseUrl, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return false;
    }

    return in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true);
}

function sr_is_http_url(string $url): bool
{
    if ($url === '' || strpos($url, '\\') !== false || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
        return false;
    }

    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    if (parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
        return false;
    }

    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || !sr_url_host_is_valid($host)) {
        return false;
    }

    $port = parse_url($url, PHP_URL_PORT);
    if ($port !== null && !sr_http_port_is_valid((string) $port)) {
        return false;
    }

    return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
}

function sr_is_site_base_url(string $url): bool
{
    if (!sr_is_http_url($url)) {
        return false;
    }

    return parse_url($url, PHP_URL_QUERY) === null && parse_url($url, PHP_URL_FRAGMENT) === null;
}

function sr_url_host_is_valid(string $host): bool
{
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        return true;
    }

    return sr_http_hostname_is_valid($host);
}

function sr_is_public_http_url(string $url): bool
{
    if (!sr_is_http_url($url)) {
        return false;
    }

    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return false;
    }

    return sr_is_public_network_host($host);
}

function sr_is_public_network_host(string $host): bool
{
    $host = strtolower(trim($host, '[]'));
    if ($host === '' || $host === 'localhost') {
        return false;
    }

    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return sr_ip_is_public_network_address($host);
    }

    if (preg_match('/\A[a-z0-9.-]+\z/', $host) !== 1) {
        return false;
    }

    return sr_public_network_addresses_are_allowed(sr_dns_ip_addresses($host));
}

function sr_dns_ip_addresses(string $host): array
{
    $addresses = [];

    set_error_handler(static function (): bool {
        return true;
    });

    try {
        $ipv4Addresses = gethostbynamel($host);
        if (is_array($ipv4Addresses)) {
            foreach ($ipv4Addresses as $address) {
                if (is_string($address) && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                    $addresses[$address] = $address;
                }
            }
        }

        if (function_exists('dns_get_record')) {
            $records = dns_get_record($host, DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    $address = is_array($record) ? (string) ($record['ipv6'] ?? '') : '';
                    if ($address !== '' && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                        $addresses[$address] = $address;
                    }
                }
            }
        }
    } finally {
        restore_error_handler();
    }

    return array_values($addresses);
}

function sr_public_network_addresses_are_allowed(array $addresses): bool
{
    if ($addresses === []) {
        return false;
    }

    foreach ($addresses as $address) {
        if (!is_string($address)) {
            return false;
        }

        if (!sr_ip_is_public_network_address($address)) {
            return false;
        }
    }

    return true;
}

function sr_ip_is_public_network_address(string $address): bool
{
    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return false;
    }

    foreach (sr_non_public_network_ranges() as $range) {
        if (sr_ip_matches_trusted_proxy($address, $range)) {
            return false;
        }
    }

    return true;
}

function sr_non_public_network_ranges(): array
{
    return [
        '0.0.0.0/8',
        '100.64.0.0/10',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.88.99.0/24',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '::/128',
        '::1/128',
        '::ffff:0:0/96',
        '64:ff9b::/96',
        '100::/64',
        '2001:db8::/32',
        'fc00::/7',
        'fe80::/10',
        'ff00::/8',
    ];
}

function sr_request_from_trusted_proxy(?array $config = null): bool
{
    $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (filter_var($remoteAddress, FILTER_VALIDATE_IP) === false) {
        return false;
    }

    $config = is_array($config) ? $config : sr_runtime_config();
    foreach (sr_trusted_proxy_entries($config) as $trustedProxy) {
        if (sr_ip_matches_trusted_proxy($remoteAddress, $trustedProxy)) {
            return true;
        }
    }

    return false;
}

function sr_trusted_proxy_entries(array $config): array
{
    $security = isset($config['security']) && is_array($config['security']) ? $config['security'] : [];
    $trustedProxies = isset($security['trusted_proxies']) && is_array($security['trusted_proxies']) ? $security['trusted_proxies'] : [];
    $entries = [];

    foreach ($trustedProxies as $trustedProxy) {
        if (!is_string($trustedProxy)) {
            continue;
        }

        $trustedProxy = trim($trustedProxy);
        if (sr_trusted_proxy_entry_is_valid($trustedProxy)) {
            $entries[] = $trustedProxy;
        }
    }

    return array_values(array_unique($entries));
}

function sr_trusted_proxy_config_errors(array $config): array
{
    $security = isset($config['security']) && is_array($config['security']) ? $config['security'] : [];
    if (!array_key_exists('trusted_proxies', $security)) {
        return [];
    }

    if (!is_array($security['trusted_proxies'])) {
        return ['trusted_proxies must be an array.'];
    }

    $errors = [];
    foreach ($security['trusted_proxies'] as $trustedProxy) {
        if (!is_string($trustedProxy) || !sr_trusted_proxy_entry_is_valid(trim($trustedProxy))) {
            $errors[] = 'Invalid trusted proxy entry.';
        }
    }

    return $errors;
}

function sr_trusted_proxy_entry_is_valid(string $trustedProxy): bool
{
    if ($trustedProxy === '') {
        return false;
    }

    if (strpos($trustedProxy, '/') === false) {
        return filter_var($trustedProxy, FILTER_VALIDATE_IP) !== false;
    }

    [$network, $prefixLength] = explode('/', $trustedProxy, 2);
    if (filter_var($network, FILTER_VALIDATE_IP) === false || !ctype_digit($prefixLength)) {
        return false;
    }

    $packedNetwork = inet_pton($network);
    if ($packedNetwork === false) {
        return false;
    }

    $prefix = (int) $prefixLength;
    return $prefix >= 0 && $prefix <= strlen($packedNetwork) * 8;
}

function sr_ip_matches_trusted_proxy(string $ipAddress, string $trustedProxy): bool
{
    $trustedProxy = trim($trustedProxy);
    if ($trustedProxy === '') {
        return false;
    }

    if (strpos($trustedProxy, '/') === false) {
        return hash_equals($ipAddress, $trustedProxy);
    }

    [$network, $prefixLength] = explode('/', $trustedProxy, 2);
    if (filter_var($network, FILTER_VALIDATE_IP) === false || !ctype_digit($prefixLength)) {
        return false;
    }

    $packedIp = inet_pton($ipAddress);
    $packedNetwork = inet_pton($network);
    if ($packedIp === false || $packedNetwork === false || strlen($packedIp) !== strlen($packedNetwork)) {
        return false;
    }

    $prefix = (int) $prefixLength;
    $maxPrefix = strlen($packedIp) * 8;
    if ($prefix < 0 || $prefix > $maxPrefix) {
        return false;
    }

    $fullBytes = intdiv($prefix, 8);
    $remainingBits = $prefix % 8;
    if ($fullBytes > 0 && substr($packedIp, 0, $fullBytes) !== substr($packedNetwork, 0, $fullBytes)) {
        return false;
    }

    if ($remainingBits === 0) {
        return true;
    }

    $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
    return (ord($packedIp[$fullBytes]) & $mask) === (ord($packedNetwork[$fullBytes]) & $mask);
}

function sr_forwarded_client_ip(?array $config = null): string
{
    if (!sr_request_from_trusted_proxy($config)) {
        return '';
    }

    $forwardedFor = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($forwardedFor === '') {
        return '';
    }

    $config = is_array($config) ? $config : sr_runtime_config();
    $candidates = [];
    foreach (explode(',', $forwardedFor) as $part) {
        $candidate = trim($part);
        if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
            $candidates[] = $candidate;
        }
    }

    if ($candidates === []) {
        return '';
    }

    for ($index = count($candidates) - 1; $index >= 0; $index--) {
        $candidate = $candidates[$index];
        if (!sr_ip_is_trusted_proxy($candidate, $config)) {
            return $candidate;
        }
    }

    return $candidates[0];
}

function sr_ip_is_trusted_proxy(string $ipAddress, array $config): bool
{
    foreach (sr_trusted_proxy_entries($config) as $trustedProxy) {
        if (sr_ip_matches_trusted_proxy($ipAddress, $trustedProxy)) {
            return true;
        }
    }

    return false;
}

function sr_request_method(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function sr_request_path(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return '/';
    }

    $path = '/' . trim($path, '/');
    $basePath = sr_base_path();
    if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
        $path = substr($path, strlen($basePath));
        $path = is_string($path) && $path !== '' ? $path : '/';
    }

    return $path === '/' ? '/' : $path;
}

function sr_base_path(): string
{
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if ($scriptName !== '' && preg_match('/[\x00-\x1F\x7F]/', $scriptName) !== 1) {
        $basePath = str_replace('\\', '/', dirname($scriptName));
        $basePath = '/' . trim($basePath, '/');
        if ($basePath !== '/') {
            return $basePath;
        }
    }

    $site = $GLOBALS['sr_runtime_site'] ?? [];
    $baseUrl = is_array($site) ? (string) ($site['base_url'] ?? '') : '';
    $baseUrlPath = $baseUrl !== '' ? parse_url($baseUrl, PHP_URL_PATH) : '';
    if (is_string($baseUrlPath) && $baseUrlPath !== '' && preg_match('#\A/[^\x00-\x1F\x7F\\\\]*\z#', $baseUrlPath) === 1) {
        $basePath = '/' . trim($baseUrlPath, '/');
        return $basePath === '/' ? '' : $basePath;
    }

    return '';
}

function sr_is_installed(): bool
{
    $configFile = SR_ROOT . '/config/config.php';

    return is_file($configFile) && is_readable($configFile) && is_file(SR_ROOT . '/storage/installed.lock');
}

function sr_is_safe_table_prefix(string $prefix): bool
{
    return preg_match('/\A[a-z][a-z0-9]{0,20}_\z/', $prefix) === 1;
}

function sr_table_prefix(array $config): string
{
    $db = $config['db'] ?? [];
    if (!is_array($db)) {
        return 'sr_';
    }

    $prefix = (string) ($db['table_prefix'] ?? 'sr_');
    return sr_is_safe_table_prefix($prefix) ? $prefix : 'sr_';
}

function sr_prefix_sql_identifiers(string $sql, string $prefix): string
{
    if ($prefix === 'sr_') {
        return $sql;
    }

    if (!sr_is_safe_table_prefix($prefix)) {
        $prefix = 'sr_';
    }

    $rewritten = '';
    $length = strlen($sql);
    $index = 0;
    while ($index < $length) {
        $char = $sql[$index];
        if ($char === '\'' || $char === '"') {
            $quote = $char;
            $rewritten .= $char;
            $index++;
            while ($index < $length) {
                $quotedChar = $sql[$index];
                $rewritten .= $quotedChar;
                if ($quotedChar === '\\' && $index + 1 < $length) {
                    $index++;
                    $rewritten .= $sql[$index];
                    $index++;
                    continue;
                }
                if ($quotedChar === $quote) {
                    if ($index + 1 < $length && $sql[$index + 1] === $quote) {
                        $index++;
                        $rewritten .= $sql[$index];
                        $index++;
                        continue;
                    }
                    $index++;
                    break;
                }
                $index++;
            }
            continue;
        }

        if (
            substr($sql, $index, 3) === 'sr_'
            && ($index === 0 || !preg_match('/[A-Za-z0-9_@]/', $sql[$index - 1]))
            && preg_match('/\Asr_([A-Za-z0-9_]+)/', substr($sql, $index), $matches) === 1
        ) {
            $before = substr($sql, 0, $index);
            if (preg_match('/(?:\bPREPARE|\bEXECUTE)\s+$/i', $before) === 1) {
                $rewritten .= (string) $matches[0];
            } else {
                $rewritten .= $prefix . (string) $matches[1];
            }
            $index += strlen((string) $matches[0]);
            continue;
        }

        $rewritten .= $char;
        $index++;
    }

    return $rewritten;
}

function sr_load_config(): array
{
    $configFile = SR_ROOT . '/config/config.php';
    if (!is_file($configFile)) {
        throw new RuntimeException('Config file does not exist.');
    }
    if (!is_readable($configFile)) {
        throw new RuntimeException('Config file is not readable.');
    }

    $config = include $configFile;
    if (!is_array($config)) {
        throw new RuntimeException('Config file must return an array.');
    }

    return $config;
}

function sr_apply_runtime_config(array $config): void
{
    $debug = !empty($config['debug']);
    ini_set('display_errors', $debug ? '1' : '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);

    $logDir = SR_ROOT . '/storage/logs';
    if ((is_dir($logDir) || mkdir($logDir, 0755, true)) && is_writable($logDir)) {
        ini_set('error_log', $logDir . '/error.log');
    }

    if (!empty($config['timezone']) && is_string($config['timezone'])) {
        date_default_timezone_set($config['timezone']);
    }
}

function sr_apply_site_runtime_settings(?array $site): void
{
    $GLOBALS['sr_runtime_site'] = is_array($site) ? $site : [];

    $timezone = is_array($site) ? (string) ($site['timezone'] ?? '') : '';
    if ($timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)) {
        date_default_timezone_set($timezone);
    }
}

class SrDatabaseSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private int $lifetimeSeconds;
    private bool $useSessionIdHash;
    private string $lockName = '';
    private bool $lockAcquired = false;

    public function __construct(PDO $pdo, int $lifetimeSeconds)
    {
        $this->pdo = $pdo;
        $this->lifetimeSeconds = $lifetimeSeconds;
        $this->useSessionIdHash = $this->sessionIdHashColumnExists();
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        if ($this->lockAcquired && $this->lockName !== '') {
            try {
                $stmt = $this->pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
                $stmt->execute(['lock_name' => $this->lockName]);
            } catch (Throwable $ignored) {
            }
        }

        $this->lockName = '';
        $this->lockAcquired = false;
        return true;
    }

    public function read(string $id): string|false
    {
        $this->acquireLock($id);
        if (!$this->lockAcquired) {
            return '';
        }

        $this->refreshSessionIdHashSupport();

        try {
            $stmt = $this->pdo->prepare(
                'SELECT payload
                 FROM sr_sessions
                 WHERE ' . $this->sessionIdColumn() . ' = :session_id
                   AND expires_at >= :now
                 LIMIT 1'
            );
            $stmt->execute([
                'session_id' => $this->sessionIdValue($id),
                'now' => sr_now(),
            ]);
            $session = $stmt->fetch();
        } catch (Throwable $exception) {
            return '';
        }

        if (!is_array($session)) {
            return '';
        }

        return (string) ($session['payload'] ?? '');
    }

    public function write(string $id, string $data): bool
    {
        if (!$this->lockAcquired) {
            return false;
        }

        $this->refreshSessionIdHashSupport();
        $now = sr_now();
        $expiresAt = date('Y-m-d H:i:s', time() + $this->lifetimeSeconds);

        try {
            if ($this->useSessionIdHash) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO sr_sessions
                        (session_id_hash, payload, ip_address, user_agent, expires_at, created_at, updated_at)
                     VALUES
                        (:session_id, :payload, :ip_address, :user_agent, :expires_at, :created_at, :updated_at)
                     ON DUPLICATE KEY UPDATE
                        payload = VALUES(payload),
                        ip_address = VALUES(ip_address),
                        user_agent = VALUES(user_agent),
                        expires_at = VALUES(expires_at),
                        updated_at = VALUES(updated_at)'
                );
            } else {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO sr_sessions
                        (session_id, payload, ip_address, user_agent, expires_at, created_at, updated_at)
                     VALUES
                        (:session_id, :payload, :ip_address, :user_agent, :expires_at, :created_at, :updated_at)
                     ON DUPLICATE KEY UPDATE
                        payload = VALUES(payload),
                        ip_address = VALUES(ip_address),
                        user_agent = VALUES(user_agent),
                        expires_at = VALUES(expires_at),
                        updated_at = VALUES(updated_at)'
                );
            }
            $stmt->execute([
                'session_id' => $this->sessionIdValue($id),
                'payload' => $data,
                'ip_address' => sr_client_ip(),
                'user_agent' => sr_client_user_agent(),
                'expires_at' => $expiresAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable $exception) {
            return false;
        }

        return true;
    }

    public function destroy(string $id): bool
    {
        if (!$this->lockAcquired) {
            return false;
        }

        $this->refreshSessionIdHashSupport();
        try {
            $stmt = $this->pdo->prepare('DELETE FROM sr_sessions WHERE ' . $this->sessionIdColumn() . ' = :session_id');
            $stmt->execute(['session_id' => $this->sessionIdValue($id)]);
        } catch (Throwable $exception) {
            return false;
        }

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM sr_sessions WHERE expires_at < :now');
            $stmt->execute(['now' => sr_now()]);
        } catch (Throwable $exception) {
            return false;
        }

        return $stmt->rowCount();
    }

    private function acquireLock(string $id): void
    {
        if ($this->lockAcquired) {
            return;
        }

        $this->lockName = 'sr_session_' . hash('sha256', $id);
        try {
            $stmt = $this->pdo->prepare('SELECT GET_LOCK(:lock_name, 5) AS lock_acquired');
            $stmt->execute(['lock_name' => $this->lockName]);
            $row = $stmt->fetch();
            $this->lockAcquired = is_array($row) && (string) ($row['lock_acquired'] ?? '') === '1';
        } catch (Throwable $ignored) {
            $this->lockAcquired = false;
        }
    }

    private function sessionIdHashColumnExists(): bool
    {
        try {
            $this->pdo->query('SELECT session_id_hash FROM sr_sessions LIMIT 1');
            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }

    private function refreshSessionIdHashSupport(): void
    {
        if (!$this->useSessionIdHash && $this->sessionIdHashColumnExists()) {
            $this->useSessionIdHash = true;
        }
    }

    private function sessionIdColumn(): string
    {
        return $this->useSessionIdHash ? 'session_id_hash' : 'session_id';
    }

    private function sessionIdValue(string $id): string
    {
        return $this->useSessionIdHash ? hash('sha256', $id) : $id;
    }
}

class SrPrefixedPDO extends PDO
{
    private string $srTablePrefix;

    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null, string $tablePrefix = 'sr_')
    {
        $this->srTablePrefix = sr_is_safe_table_prefix($tablePrefix) ? $tablePrefix : 'sr_';
        parent::__construct($dsn, $username, $password, $options);
    }

    public function srTablePrefix(): string
    {
        return $this->srTablePrefix;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(sr_prefix_sql_identifiers($query, $this->srTablePrefix), $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $query = sr_prefix_sql_identifiers($query, $this->srTablePrefix);
        if ($fetchMode === null) {
            return parent::query($query);
        }

        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function exec(string $statement): int|false
    {
        return parent::exec(sr_prefix_sql_identifiers($statement, $this->srTablePrefix));
    }
}

function sr_db(array $config): PDO
{
    $db = $config['db'] ?? [];
    if (!is_array($db)) {
        throw new RuntimeException('DB config is invalid.');
    }

    $host = (string) ($db['host'] ?? 'localhost');
    $name = (string) ($db['name'] ?? '');
    $user = (string) ($db['user'] ?? '');
    $password = sr_db_env_or_value($db, 'password', 'password_env');
    $charset = (string) ($db['charset'] ?? 'utf8mb4');
    $tablePrefix = sr_table_prefix($config);

    $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=' . $charset;
    return new SrPrefixedPDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ], $tablePrefix);
}

function sr_db_env_or_value(array $settings, string $valueKey, string $envKey): string
{
    $envName = trim((string) ($settings[$envKey] ?? ''));
    if ($envName !== '' && preg_match('/\A[A-Z][A-Z0-9_]{1,80}\z/', $envName) === 1) {
        $envValue = getenv($envName);
        if (is_string($envValue)) {
            return $envValue;
        }
    }

    return (string) ($settings[$valueKey] ?? '');
}

function sr_client_ip(): string
{
    $forwardedIp = sr_forwarded_client_ip();
    if ($forwardedIp !== '') {
        return $forwardedIp;
    }

    $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
        return '';
    }

    return $ipAddress;
}

function sr_client_user_agent(): string
{
    $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $userAgent = preg_replace('/[\x00-\x1F\x7F]/', '', $userAgent);
    $userAgent = is_string($userAgent) ? $userAgent : '';

    if (function_exists('mb_substr')) {
        return mb_substr($userAgent, 0, 500);
    }

    return substr($userAgent, 0, 500);
}

function sr_now(): string
{
    return date('Y-m-d H:i:s');
}

function sr_normalize_identifier(string $value): string
{
    return strtolower(trim($value));
}

function sr_send_mail(?array $site, string $to, string $subject, string $body): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $config = sr_runtime_config();
    $mailConfig = isset($config['mail']) && is_array($config['mail']) ? $config['mail'] : [];
    $transport = (string) ($mailConfig['transport'] ?? 'php_mail');
    if ($transport === 'smtp') {
        return sr_send_smtp_mail($site, $mailConfig, $to, $subject, $body);
    }
    if ($transport === 'http_api') {
        return sr_send_http_api_mail($mailConfig, $to, $subject, $body);
    }

    if (!function_exists('mail')) {
        return false;
    }

    $from = sr_mail_from_address($site, $mailConfig);
    $headers = [
        'From: ' . $from,
        'Content-Type: text/plain; charset=UTF-8',
    ];

    return mail($to, sr_mail_header_encode($subject), str_replace(["\r\n", "\r"], "\n", $body), implode("\r\n", $headers));
}

function sr_mail_from_address(?array $site, array $mailConfig): string
{
    $from = (string) ($mailConfig['from_email'] ?? '');
    if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return $from;
    }

    $baseUrl = is_array($site) ? (string) ($site['base_url'] ?? '') : '';
    $host = parse_url($baseUrl, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    if (!sr_http_host_is_valid($host)) {
        $host = 'localhost';
    }
    $host = preg_replace('/:\d+\z/', '', trim($host, '[]'));
    $host = is_string($host) && $host !== '' ? $host : 'localhost';

    return 'no-reply@' . preg_replace('/[^A-Za-z0-9.-]/', '', $host);
}

function sr_send_smtp_mail(?array $site, array $mailConfig, string $to, string $subject, string $body): bool
{
    $host = (string) ($mailConfig['host'] ?? '');
    $port = (int) ($mailConfig['port'] ?? 587);
    if ($host === '' || $port < 1 || $port > 65535) {
        return false;
    }

    $encryption = strtolower((string) ($mailConfig['encryption'] ?? 'tls'));
    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $timeout = max(3, min(30, (int) ($mailConfig['timeout_seconds'] ?? 10)));
    $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!is_resource($socket)) {
        return false;
    }

    stream_set_timeout($socket, $timeout);
    $from = sr_mail_from_address($site, $mailConfig);
    $fromName = trim((string) ($mailConfig['from_name'] ?? (is_array($site) ? (string) ($site['name'] ?? '') : '')));
    $headers = [
        'From: ' . ($fromName !== '' ? sr_mail_header_encode($fromName) . ' <' . $from . '>' : $from),
        'To: ' . $to,
        'Subject: ' . sr_mail_header_encode($subject),
        'Date: ' . date(DATE_RFC2822),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    $message = implode("\r\n", $headers) . "\r\n\r\n" . str_replace(["\r\n", "\r"], "\n", $body);

    try {
        if (!sr_smtp_expect($socket, [220])) {
            fclose($socket);
            return false;
        }

        $serverName = sr_smtp_server_name();
        if (!sr_smtp_command($socket, 'EHLO ' . $serverName, [250])) {
            fclose($socket);
            return false;
        }

        if ($encryption === 'tls') {
            if (!sr_smtp_command($socket, 'STARTTLS', [220])) {
                fclose($socket);
                return false;
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return false;
            }
            if (!sr_smtp_command($socket, 'EHLO ' . $serverName, [250])) {
                fclose($socket);
                return false;
            }
        }

        $username = (string) ($mailConfig['username'] ?? '');
        $password = (string) ($mailConfig['password'] ?? '');
        if ($username !== '') {
            if (
                !sr_smtp_command($socket, 'AUTH LOGIN', [334])
                || !sr_smtp_command($socket, base64_encode($username), [334])
                || !sr_smtp_command($socket, base64_encode($password), [235])
            ) {
                fclose($socket);
                return false;
            }
        }

        $ok = sr_smtp_command($socket, 'MAIL FROM:<' . $from . '>', [250])
            && sr_smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251])
            && sr_smtp_command($socket, 'DATA', [354])
            && sr_smtp_command($socket, sr_smtp_dot_stuff($message) . "\r\n.", [250])
            && sr_smtp_command($socket, 'QUIT', [221]);
        fclose($socket);
        return $ok;
    } catch (Throwable $exception) {
        if (is_resource($socket)) {
            fclose($socket);
        }
        return false;
    }
}

function sr_send_http_api_mail(array $mailConfig, string $to, string $subject, string $body): bool
{
    $endpoint = (string) ($mailConfig['endpoint'] ?? '');
    if ($endpoint === '' || !sr_mail_http_api_endpoint_is_allowed($endpoint)) {
        return false;
    }

    $fromEmail = (string) ($mailConfig['from_email'] ?? '');
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $payload = json_encode([
        'from_email' => $fromEmail,
        'from_name' => (string) ($mailConfig['from_name'] ?? ''),
        'to' => $to,
        'subject' => $subject,
        'text' => $body,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload)) {
        return false;
    }

    $headers = [
        'Content-Type: application/json',
        'User-Agent: Saanraan-Mail/1.0',
    ];
    $bearerToken = (string) ($mailConfig['bearer_token'] ?? '');
    if (preg_match('/[\x00-\x1F\x7F]/', $bearerToken) === 1) {
        return false;
    }
    if ($bearerToken !== '') {
        $headers[] = 'Authorization: Bearer ' . $bearerToken;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'timeout' => max(3, min(30, (int) ($mailConfig['timeout_seconds'] ?? 10))),
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $payload,
        ],
    ]);

    set_error_handler(static function (): bool {
        return true;
    });
    $response = file_get_contents($endpoint, false, $context);
    restore_error_handler();
    $responseHeaders = function_exists('http_get_last_response_headers')
        ? http_get_last_response_headers()
        : ($http_response_header ?? []);
    if ($response === false || empty($responseHeaders) || !is_array($responseHeaders)) {
        return false;
    }

    foreach ($responseHeaders as $header) {
        if (preg_match('/\AHTTP\/\S+\s+(\d{3})\b/', (string) $header, $matches) === 1) {
            $status = (int) $matches[1];
            return $status >= 200 && $status < 300;
        }
    }

    return false;
}

function sr_mail_http_api_endpoint_is_allowed(string $endpoint): bool
{
    if (!sr_is_public_http_url($endpoint)) {
        return false;
    }

    return strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) === 'https';
}

function sr_mail_header_encode(string $value): string
{
    $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
    $value = is_string($value) ? $value : '';
    if (preg_match('/^[\x20-\x7E]*$/', $value) === 1) {
        return $value;
    }

    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function sr_smtp_server_name(): string
{
    $serverName = (string) ($_SERVER['SERVER_NAME'] ?? '');
    if (sr_http_hostname_is_valid($serverName)) {
        return $serverName;
    }

    return 'localhost';
}

function sr_smtp_command($socket, string $command, array $expectedCodes): bool
{
    fwrite($socket, $command . "\r\n");
    return sr_smtp_expect($socket, $expectedCodes);
}

function sr_smtp_expect($socket, array $expectedCodes): bool
{
    $lastCode = 0;
    while (($line = fgets($socket, 515)) !== false) {
        if (preg_match('/\A(\d{3})([\s-])/', $line, $matches) !== 1) {
            continue;
        }
        $lastCode = (int) $matches[1];
        if ((string) $matches[2] === ' ') {
            break;
        }
    }

    return in_array($lastCode, $expectedCodes, true);
}

function sr_smtp_dot_stuff(string $message): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $message);
    $lines = explode("\n", $normalized);
    foreach ($lines as $index => $line) {
        if (str_starts_with($line, '.')) {
            $lines[$index] = '.' . $line;
        }
    }

    return implode("\r\n", $lines);
}

function sr_rate_limit_count(PDO $pdo, string $bucket, string $subject, int $windowSeconds): int
{
    if (!sr_rate_limit_input_is_valid($bucket, $subject, $windowSeconds)) {
        return 0;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT attempt_count
             FROM sr_rate_limits
             WHERE rate_key = :rate_key
               AND expires_at >= :now
             LIMIT 1'
        );
        $stmt->execute([
            'rate_key' => sr_rate_limit_key($bucket, $subject),
            'now' => sr_now(),
        ]);
        $row = $stmt->fetch();
    } catch (Throwable $exception) {
        return 0;
    }

    return is_array($row) ? (int) ($row['attempt_count'] ?? 0) : 0;
}

function sr_rate_limit_increment(PDO $pdo, string $bucket, string $subject, int $windowSeconds): void
{
    if (!sr_rate_limit_input_is_valid($bucket, $subject, $windowSeconds)) {
        return;
    }

    $now = sr_now();
    $expiresAt = date('Y-m-d H:i:s', time() + max(60, min(86400, $windowSeconds)));
    try {
        if (random_int(1, 100) === 1) {
            sr_rate_limit_collect_garbage($pdo);
        }

        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT INTO sr_rate_limits
                (rate_key, bucket, subject_hash, attempt_count, expires_at, created_at, updated_at)
             VALUES
                (:rate_key, :bucket, :subject_hash, 1, :expires_at, :created_at, :updated_at)
             ON CONFLICT(rate_key) DO UPDATE SET
                attempt_count = CASE WHEN expires_at < excluded.updated_at THEN 1 ELSE attempt_count + 1 END,
                expires_at = CASE WHEN expires_at < excluded.updated_at THEN excluded.expires_at ELSE expires_at END,
                updated_at = excluded.updated_at'
            : 'INSERT INTO sr_rate_limits
                (rate_key, bucket, subject_hash, attempt_count, expires_at, created_at, updated_at)
             VALUES
                (:rate_key, :bucket, :subject_hash, 1, :expires_at, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                attempt_count = IF(expires_at < VALUES(updated_at), 1, attempt_count + 1),
                expires_at = IF(expires_at < VALUES(updated_at), VALUES(expires_at), expires_at),
                updated_at = VALUES(updated_at)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'rate_key' => sr_rate_limit_key($bucket, $subject),
            'bucket' => $bucket,
            'subject_hash' => sr_rate_limit_hash($subject),
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    } catch (Throwable $ignored) {
    }
}

function sr_rate_limit_collect_garbage(PDO $pdo): void
{
    try {
        $stmt = $pdo->prepare('DELETE FROM sr_rate_limits WHERE expires_at < :now');
        $stmt->execute(['now' => sr_now()]);
    } catch (Throwable $ignored) {
    }
}

function sr_rate_limit_input_is_valid(string $bucket, string $subject, int $windowSeconds): bool
{
    return $subject !== ''
        && $windowSeconds > 0
        && preg_match('/\A[a-z0-9][a-z0-9_.:-]{1,119}\z/', $bucket) === 1;
}

function sr_rate_limit_key(string $bucket, string $subject): string
{
    return sr_rate_limit_hash($bucket . '|' . $subject);
}

function sr_rate_limit_hash(string $value): string
{
    $appKey = sr_app_key(sr_runtime_config());
    if ($appKey !== '') {
        return hash_hmac('sha256', $value, $appKey);
    }

    return hash('sha256', $value);
}

function sr_hmac_hash(string $value, array $config): string
{
    $appKey = sr_app_key($config);
    if ($appKey === '') {
        throw new RuntimeException('app_key is required.');
    }

    return hash_hmac('sha256', $value, $appKey);
}

function sr_secret_at_rest_crypto_available(?array $config = null): bool
{
    $config = is_array($config) ? $config : sr_runtime_config();
    return sr_app_key($config) !== ''
        && (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt') || function_exists('openssl_encrypt'));
}

function sr_secret_at_rest_key(string $purpose, ?array $config = null): string
{
    $config = is_array($config) ? $config : sr_runtime_config();
    $appKey = sr_app_key($config);
    if ($appKey === '') {
        throw new RuntimeException('app_key is required.');
    }

    return hash_hmac('sha256', 'saanraan-secret-at-rest|' . $purpose, $appKey, true);
}

function sr_secret_at_rest_aad(string $purpose): string
{
    return 'saanraan:secret-at-rest:' . $purpose;
}

function sr_secret_at_rest_encrypt(string $plaintext, string $purpose, ?array $config = null): string
{
    if ($plaintext === '') {
        return '';
    }

    $key = sr_secret_at_rest_key($purpose, $config);
    $aad = sr_secret_at_rest_aad($purpose);

    if (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $aad, $nonce, $key);
        return 'sr2:sodium:' . base64_encode($nonce . $ciphertext);
    }

    if (function_exists('openssl_encrypt')) {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);
        if (is_string($ciphertext) && $tag !== '') {
            return 'sr2:openssl:' . base64_encode($iv . $tag . $ciphertext);
        }
    }

    throw new RuntimeException('Secret encryption is unavailable.');
}

function sr_secret_at_rest_decrypt(string $ciphertext, string $purpose, ?array $config = null): ?string
{
    if ($ciphertext === '') {
        return null;
    }

    $key = sr_secret_at_rest_key($purpose, $config);
    $aad = sr_secret_at_rest_aad($purpose);

    if (str_starts_with($ciphertext, 'sr2:sodium:') && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')) {
        $raw = base64_decode(substr($ciphertext, strlen('sr2:sodium:')), true);
        if (!is_string($raw) || strlen($raw) <= SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES) {
            return null;
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $encrypted = substr($raw, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($encrypted, $aad, $nonce, $key);
        return is_string($plain) ? $plain : null;
    }

    if (str_starts_with($ciphertext, 'sr2:openssl:') && function_exists('openssl_decrypt')) {
        $raw = base64_decode(substr($ciphertext, strlen('sr2:openssl:')), true);
        if (!is_string($raw) || strlen($raw) <= 28) {
            return null;
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $encrypted = substr($raw, 28);
        $plain = openssl_decrypt($encrypted, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
        return is_string($plain) ? $plain : null;
    }

    return null;
}

function sr_secret_at_rest_fingerprint(string $plaintext, string $purpose, ?array $config = null): string
{
    if ($plaintext === '') {
        return '';
    }

    $key = sr_secret_at_rest_key('fingerprint|' . $purpose, $config);
    return hash_hmac('sha256', $plaintext, $key);
}

function sr_app_key(array $config): string
{
    $secrets = isset($config['secrets']) && is_array($config['secrets']) ? $config['secrets'] : [];
    $envName = (string) ($secrets['app_key_env'] ?? '');
    if ($envName !== '' && preg_match('/\A[A-Z][A-Z0-9_]{1,80}\z/', $envName) === 1) {
        $envValue = getenv($envName);
        if (is_string($envValue) && $envValue !== '') {
            return $envValue;
        }
    }

    return (string) ($config['app_key'] ?? '');
}
