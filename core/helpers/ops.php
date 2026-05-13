<?php

declare(strict_types=1);

function sr_fetch_http_response(string $url): ?array
{
    if (!sr_is_public_http_url($url)) {
        return null;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 3,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'header' => "User-Agent: Saanraan-Install-Check\r\n",
        ],
    ]);

    set_error_handler(static function (): bool {
        return true;
    });
    $body = file_get_contents($url, false, $context);
    restore_error_handler();

    $responseHeaders = function_exists('http_get_last_response_headers')
        ? http_get_last_response_headers()
        : ($http_response_header ?? []);
    if ($body === false || empty($responseHeaders) || !is_array($responseHeaders)) {
        return null;
    }

    foreach ($responseHeaders as $header) {
        if (preg_match('/\AHTTP\/\S+\s+(\d{3})\b/', (string) $header, $matches) === 1) {
            return [
                'status' => (int) $matches[1],
                'body' => $body,
            ];
        }
    }

    return null;
}

function sr_internal_access_check_urls(string $baseUrl): array
{
    $baseUrl = rtrim($baseUrl, '/');
    if ($baseUrl === '') {
        return [];
    }

    $checks = [
        '/AGENTS.md' => '/# AGENTS\.md/',
        '/database/core/install.sql' => '/CREATE TABLE IF NOT EXISTS sr_site_settings/',
        '/modules/member/install.sql' => '/CREATE TABLE IF NOT EXISTS sr_member_accounts/',
        '/docs/deployment-protection.md' => '/# 배포 보호 기준/',
        '/.git/HEAD' => '/\A(?:ref: refs\/|[a-f0-9]{40})/',
    ];

    $urls = [];
    foreach ($checks as $path => $pattern) {
        $urls[] = [
            'url' => $baseUrl . $path,
            'pattern' => $pattern,
        ];
    }

    return $urls;
}

function sr_public_internal_access_findings(string $baseUrl): array
{
    if (!sr_is_public_http_url($baseUrl)) {
        return [];
    }

    $findings = [];
    foreach (sr_internal_access_check_urls($baseUrl) as $check) {
        $response = sr_fetch_http_response((string) $check['url']);
        if (
            is_array($response)
            && (int) $response['status'] >= 200
            && (int) $response['status'] < 400
            && preg_match((string) $check['pattern'], (string) $response['body']) === 1
        ) {
            $findings[] = [
                'url' => (string) $check['url'],
                'status' => (int) $response['status'],
            ];
        }
    }

    return $findings;
}

function sr_write_config(array $config): void
{
    $configDir = SR_ROOT . '/config';
    if (!is_dir($configDir) && !mkdir($configDir, 0755, true)) {
        throw new RuntimeException('config directory cannot be created.');
    }

    $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
    $target = $configDir . '/config.php';
    try {
        $suffix = bin2hex(random_bytes(6));
    } catch (Throwable $exception) {
        $suffix = str_replace('.', '', uniqid('', true));
    }
    $temporary = $configDir . '/config-' . $suffix . '.tmp.php';

    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        throw new RuntimeException('config file cannot be written.');
    }

    if (!rename($temporary, $target)) {
        if (is_file($temporary)) {
            unlink($temporary);
        }
        throw new RuntimeException('config file cannot be moved into place.');
    }
}

function sr_start_request_contract(string $method, string $path, string $moduleKey, string $actionFile): void
{
    $GLOBALS['sr_request_contract'] = [
        'method' => strtoupper($method),
        'path' => $path,
        'module_key' => $moduleKey,
        'action_file' => $actionFile,
        'is_admin' => $path === '/admin' || str_starts_with($path, '/admin/'),
        'csrf_checked' => false,
        'auth_checked' => false,
        'role_checked' => false,
        'exit_reason' => null,
        'resolved_stage' => null,
    ];

    if (empty($GLOBALS['sr_request_contract_shutdown_registered'])) {
        $GLOBALS['sr_request_contract_shutdown_registered'] = true;
        register_shutdown_function(static function (): void {
            $contract = $GLOBALS['sr_request_contract'] ?? null;
            if (!is_array($contract) || ($contract['exit_reason'] ?? null) !== null) {
                return;
            }

            $path = is_string($contract['path'] ?? null) ? (string) $contract['path'] : '';
            $actionFile = is_string($contract['action_file'] ?? null) ? (string) $contract['action_file'] : '';
            error_log('[saanraan] request contract unresolved at shutdown: ' . $path . ' ' . $actionFile);
        });
    }
}

function sr_request_contract_mark(string $key): void
{
    if (!isset($GLOBALS['sr_request_contract']) || !is_array($GLOBALS['sr_request_contract'])) {
        return;
    }

    if (!in_array($key, ['csrf_checked', 'auth_checked', 'role_checked'], true)) {
        return;
    }

    $GLOBALS['sr_request_contract'][$key] = true;
}

function sr_request_contract_guard_blocked(string $guard): void
{
    if (!isset($GLOBALS['sr_request_contract']) || !is_array($GLOBALS['sr_request_contract'])) {
        return;
    }

    $GLOBALS['sr_request_contract']['exit_reason'] = 'guard_blocked';
    $GLOBALS['sr_request_contract']['blocked_guard'] = $guard;
}

function sr_enforce_request_contract(string $stage): void
{
    if (!isset($GLOBALS['sr_request_contract']) || !is_array($GLOBALS['sr_request_contract'])) {
        return;
    }

    $contract = $GLOBALS['sr_request_contract'];
    if (($contract['exit_reason'] ?? null) === 'guard_blocked') {
        $GLOBALS['sr_request_contract']['resolved_stage'] = $stage;
        return;
    }

    $violations = [];
    if ((string) ($contract['method'] ?? '') === 'POST' && empty($contract['csrf_checked'])) {
        $violations[] = 'POST action did not call sr_require_csrf().';
    }

    if (!empty($contract['is_admin']) && empty($contract['auth_checked'])) {
        $violations[] = 'Admin action did not call sr_member_require_login().';
    }

    if (!empty($contract['is_admin']) && empty($contract['role_checked'])) {
        $violations[] = 'Admin action did not call sr_admin_require_role().';
    }

    if ($violations !== []) {
        $GLOBALS['sr_request_contract']['exit_reason'] = 'violation';
        $GLOBALS['sr_request_contract']['resolved_stage'] = $stage;
        sr_fail_request_contract(implode(' ', $violations), $stage, $contract);
    }

    $GLOBALS['sr_request_contract']['exit_reason'] = 'completed';
    $GLOBALS['sr_request_contract']['resolved_stage'] = $stage;
}

function sr_fail_request_contract(string $message, string $stage, array $contract): void
{
    $path = is_string($contract['path'] ?? null) ? (string) $contract['path'] : '';
    $actionFile = is_string($contract['action_file'] ?? null) ? (string) $contract['action_file'] : '';
    error_log('[saanraan] request contract violation: ' . $message . ' at ' . $stage . ' ' . $path . ' ' . $actionFile);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }

    echo "Internal error\n";
    exit(1);
}

function sr_render_error(int $statusCode, string $message, ?Throwable $exception = null): void
{
    sr_enforce_request_contract('before_error');

    http_response_code($statusCode);
    if ($exception instanceof Throwable) {
        sr_log_exception($exception, 'render_error_' . $statusCode);
    }

    $config = [];
    if (is_file(SR_ROOT . '/config/config.php')) {
        try {
            $config = sr_load_config();
        } catch (Throwable $ignored) {
            $config = [];
        }
    }

    $debug = !empty($config['debug']);
    $pageTitle = (string) $statusCode;
    include SR_ROOT . '/core/views/error.php';
    sr_finish_response();
}

function sr_log_exception(Throwable $exception, string $context): void
{
    $logDir = SR_ROOT . '/storage/logs';
    if (!is_dir($logDir) && !mkdir($logDir, 0755, true)) {
        return;
    }

    $line = sprintf(
        "[%s] %s %s: %s in %s:%d\n",
        sr_now(),
        sr_log_sensitive_text_sanitize(sr_log_line_value($context, 120)),
        sr_log_line_value(get_class($exception), 120),
        sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 1000)),
        sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getFile(), 500)),
        $exception->getLine()
    );

    file_put_contents($logDir . '/error.log', $line, FILE_APPEND | LOCK_EX);
}

function sr_log_line_value(string $value, int $maxLength = 1000): string
{
    $normalized = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value);
    $normalized = is_string($normalized) ? trim($normalized) : '';
    $maxLength = max(1, $maxLength);

    if (function_exists('mb_substr')) {
        return mb_substr($normalized, 0, $maxLength);
    }

    return substr($normalized, 0, $maxLength);
}

function sr_log_sensitive_text_sanitize(string $value): string
{
    $sanitized = preg_replace('/\b(Authorization)\s*([:=])\s*(?:Bearer|Basic)\s+[^&\s;,"\']+/i', '$1$2 [masked]', $value) ?? $value;
    $sanitized = preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer [masked]', $sanitized) ?? $sanitized;
    $pattern = '/((?:^|[?&\s;,"\'\[\]{}])(?:password|token|secret|credential|bearer|authorization|api[._-]?key|access[._-]?key|private[._-]?key|client[._-]?secret|app[._-]?key)\s*[=:]\s*)([^&\s;,"\'\[\]\}]+)/i';

    return preg_replace($pattern, '$1[masked]', $sanitized) ?? $sanitized;
}

function sr_write_operational_marker(string $filename, array $data): void
{
    if (preg_match('/\A[a-z0-9_.-]+\.json\z/', $filename) !== 1) {
        return;
    }

    try {
        $storageDir = SR_ROOT . '/storage';
        if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true)) {
            return;
        }

        $payload = array_merge([
            'recorded_at' => sr_now(),
        ], $data);
        $encoded = json_encode(sr_audit_metadata_sanitize($payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            return;
        }

        file_put_contents($storageDir . '/' . $filename, $encoded . "\n", LOCK_EX);
    } catch (Throwable $ignored) {
    }
}

function sr_clear_operational_marker(string $filename): void
{
    if (preg_match('/\A[a-z0-9_.-]+\.json\z/', $filename) !== 1) {
        return;
    }

    try {
        $path = SR_ROOT . '/storage/' . $filename;
        if (is_file($path)) {
            unlink($path);
        }
    } catch (Throwable $ignored) {
    }
}

function sr_audit_log(PDO $pdo, array $data): void
{
    try {
        $metadata = $data['metadata'] ?? null;
        $metadataJson = null;
        if (is_array($metadata) && $metadata !== []) {
            $encoded = json_encode(sr_audit_metadata_sanitize($metadata), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $metadataJson = is_string($encoded) ? $encoded : null;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO sr_audit_logs
                (actor_account_id, actor_type, event_type, target_type, target_id, result, ip_address, user_agent, message, metadata_json, created_at)
             VALUES
                (:actor_account_id, :actor_type, :event_type, :target_type, :target_id, :result, :ip_address, :user_agent, :message, :metadata_json, :created_at)'
        );
        $stmt->execute([
            'actor_account_id' => isset($data['actor_account_id']) ? (int) $data['actor_account_id'] : null,
            'actor_type' => (string) ($data['actor_type'] ?? 'system'),
            'event_type' => (string) ($data['event_type'] ?? ''),
            'target_type' => (string) ($data['target_type'] ?? ''),
            'target_id' => (string) ($data['target_id'] ?? ''),
            'result' => (string) ($data['result'] ?? 'success'),
            'ip_address' => sr_client_ip(),
            'user_agent' => sr_client_user_agent(),
            'message' => sr_log_sensitive_text_sanitize(sr_log_line_value((string) ($data['message'] ?? ''), 1000)),
            'metadata_json' => $metadataJson,
            'created_at' => sr_now(),
        ]);
    } catch (Throwable $ignored) {
    }
}

function sr_audit_metadata_sanitize(mixed $value, string $key = ''): mixed
{
    if ($key !== '' && sr_audit_metadata_key_is_secret($key)) {
        return $value === '' ? '' : '[masked]';
    }

    if (is_string($value)) {
        return sr_log_sensitive_text_sanitize($value);
    }

    if (!is_array($value)) {
        return $value;
    }

    $sanitized = [];
    foreach ($value as $childKey => $childValue) {
        $sanitized[$childKey] = sr_audit_metadata_sanitize($childValue, is_string($childKey) ? $childKey : '');
    }

    return $sanitized;
}

function sr_audit_metadata_key_is_secret(string $key): bool
{
    return preg_match(
        '/(?:^|[._-])(?:password|token|secret|credential|bearer|authorization|api[._-]?key|access[._-]?key|private[._-]?key|client[._-]?secret|app[._-]?key)(?:$|[._-])/',
        strtolower($key)
    ) === 1;
}
