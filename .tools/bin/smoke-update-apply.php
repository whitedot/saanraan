#!/usr/bin/env php
<?php

declare(strict_types=1);

define('SR_ROOT', dirname(__DIR__, 2));

require SR_ROOT . '/core/helpers.php';

$baseUrl = rtrim((string) ($argv[1] ?? getenv('SR_SMOKE_BASE_URL') ?: ''), '/');
$adminIdentifier = (string) ($argv[2] ?? getenv('SR_SMOKE_ADMIN_IDENTIFIER') ?: '');
$adminPassword = (string) ($argv[3] ?? getenv('SR_SMOKE_ADMIN_PASSWORD') ?: '');
$moduleKey = (string) (getenv('SR_SMOKE_UPDATE_MODULE_KEY') ?: 'coupon');
$version = (string) (getenv('SR_SMOKE_UPDATE_VERSION') ?: '2026.05.003');
$allowMutation = getenv('SR_SMOKE_ALLOW_MUTATION') === '1';
$allowPublicMutationUrl = getenv('SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL') === '1';

if ($baseUrl === '' || $adminIdentifier === '' || $adminPassword === '') {
    fwrite(STDERR, "Usage: SR_SMOKE_ALLOW_MUTATION=1 php .tools/bin/smoke-update-apply.php <base-url> <admin-identifier> <admin-password>\n");
    fwrite(STDERR, "Env: SR_SMOKE_BASE_URL SR_SMOKE_ADMIN_IDENTIFIER SR_SMOKE_ADMIN_PASSWORD SR_SMOKE_UPDATE_MODULE_KEY SR_SMOKE_UPDATE_VERSION\n");
    exit(2);
}

if (!$allowMutation) {
    fwrite(STDERR, "saanraan update apply smoke refused to run because it mutates schema version rows. Set SR_SMOKE_ALLOW_MUTATION=1 only on local or staging disposable data.\n");
    exit(2);
}

if (sr_update_smoke_base_url_requires_public_mutation_override($baseUrl) && !$allowPublicMutationUrl) {
    fwrite(STDERR, "saanraan update apply smoke refused to run against a public-looking URL. Set SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1 only for disposable staging data.\n");
    exit(2);
}

if (!sr_is_safe_module_key($moduleKey) || preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $version) !== 1) {
    fwrite(STDERR, "Update smoke module/version is invalid.\n");
    exit(2);
}

$config = sr_load_config();
sr_set_runtime_config($config);
$pdo = sr_db($config);

$updatePath = SR_ROOT . '/modules/' . $moduleKey . '/updates/' . $version . '.sql';
if (!is_file($updatePath)) {
    fwrite(STDERR, "Update smoke target file is missing: modules/" . $moduleKey . "/updates/" . $version . ".sql\n");
    exit(1);
}

$module = sr_update_smoke_row($pdo, 'SELECT module_key, version, status FROM sr_modules WHERE module_key = :module_key LIMIT 1', [
    'module_key' => $moduleKey,
]);
if ($module === null) {
    fwrite(STDERR, "Update smoke target module is not installed: " . $moduleKey . "\n");
    exit(1);
}

$schemaVersion = sr_update_smoke_row(
    $pdo,
    'SELECT version FROM sr_schema_versions WHERE scope = :scope AND module_key = :module_key AND version = :version LIMIT 1',
    ['scope' => 'module', 'module_key' => $moduleKey, 'version' => $version]
);
if ($schemaVersion === null) {
    fwrite(STDERR, "Update smoke target version is already pending or was never recorded: " . $moduleKey . ' ' . $version . "\n");
    exit(1);
}

$cookieFile = tempnam(sys_get_temp_dir(), 'sr-update-smoke-cookie-');
if (!is_string($cookieFile)) {
    fwrite(STDERR, "Could not create cookie jar.\n");
    exit(1);
}

register_shutdown_function(static function () use ($cookieFile): void {
    if (is_file($cookieFile)) {
        @unlink($cookieFile);
    }
});

echo "update-apply-smoke-version: 1\n";
echo "base-url: " . $baseUrl . "\n";
echo "target-update: " . $moduleKey . ' ' . $version . "\n";

$loginToken = sr_update_smoke_csrf($baseUrl, $cookieFile, '/login');
sr_update_smoke_request($baseUrl, $cookieFile, 'POST', '/login', [
    'identifier' => $adminIdentifier,
    'password' => $adminPassword,
    'next' => '/admin/updates',
    'csrf_token' => $loginToken,
]);

$adminUpdates = sr_update_smoke_request($baseUrl, $cookieFile, 'GET', '/admin/updates');
if ((int) $adminUpdates['status'] !== 200 || strpos((string) $adminUpdates['body'], 'csrf_token') === false) {
    fwrite(STDERR, "Admin updates screen was not available after login.\n");
    exit(1);
}

$pendingBefore = sr_pending_schema_updates($pdo);
$targetWasPendingBefore = sr_update_smoke_pending_contains($pendingBefore, $moduleKey, $version);
if ($targetWasPendingBefore) {
    fwrite(STDERR, "Update smoke target was pending before setup; use a disposable clean installed DB.\n");
    exit(1);
}

$pdo->prepare(
    'DELETE FROM sr_schema_versions
     WHERE scope = :scope AND module_key = :module_key AND version = :version'
)->execute(['scope' => 'module', 'module_key' => $moduleKey, 'version' => $version]);

$pendingAfterSetup = sr_pending_schema_updates($pdo);
if (!sr_update_smoke_pending_contains($pendingAfterSetup, $moduleKey, $version)) {
    fwrite(STDERR, "Update smoke could not create pending update state.\n");
    exit(1);
}

echo "pending-created: yes\n";

$updateToken = sr_update_smoke_csrf($baseUrl, $cookieFile, '/admin/updates');
sr_update_smoke_request($baseUrl, $cookieFile, 'POST', '/admin/updates', [
    'intent' => 'apply_updates',
    'backup_confirmed' => '1',
    'csrf_token' => $updateToken,
]);

$pendingAfterApply = sr_pending_schema_updates($pdo);
if (sr_update_smoke_pending_contains($pendingAfterApply, $moduleKey, $version)) {
    fwrite(STDERR, "Update smoke target is still pending after apply.\n");
    exit(1);
}

$schemaVersionAfter = sr_update_smoke_row(
    $pdo,
    'SELECT version FROM sr_schema_versions WHERE scope = :scope AND module_key = :module_key AND version = :version LIMIT 1',
    ['scope' => 'module', 'module_key' => $moduleKey, 'version' => $version]
);
if ($schemaVersionAfter === null) {
    fwrite(STDERR, "Update smoke target version was not recorded after apply.\n");
    exit(1);
}

$auditCompleted = sr_update_smoke_row(
    $pdo,
    "SELECT id FROM sr_audit_logs
     WHERE event_type = 'schema.update.completed'
       AND target_type = 'module'
       AND target_id = :target_id
     ORDER BY id DESC
     LIMIT 1",
    ['target_id' => $moduleKey . ':' . $version]
);
if ($auditCompleted === null) {
    fwrite(STDERR, "Update smoke did not find schema.update.completed audit log.\n");
    exit(1);
}

$moduleAfter = sr_update_smoke_row($pdo, 'SELECT version FROM sr_modules WHERE module_key = :module_key LIMIT 1', [
    'module_key' => $moduleKey,
]);
$codeMetadata = sr_module_metadata($moduleKey);
$codeVersion = is_string($codeMetadata['version'] ?? null) ? (string) $codeMetadata['version'] : '';
if ($codeVersion !== '' && is_array($moduleAfter) && (string) ($moduleAfter['version'] ?? '') !== $codeVersion) {
    fwrite(STDERR, "Update smoke module version was not synced to code version after apply.\n");
    exit(1);
}

echo "pending-cleared: yes\n";
echo "schema-version-recorded: yes\n";
echo "audit-completed: yes\n";
echo "module-version-synced: yes\n";
echo "saanraan update apply smoke completed.\n";

function sr_update_smoke_base_url_requires_public_mutation_override(string $baseUrl): bool
{
    $host = strtolower((string) (parse_url($baseUrl, PHP_URL_HOST) ?: ''));
    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
        return false;
    }

    if (str_ends_with($host, '.test') || str_ends_with($host, '.localhost')) {
        return false;
    }

    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $long = ip2long($host);
        if ($long !== false) {
            $privateRanges = [
                ['10.0.0.0', '10.255.255.255'],
                ['172.16.0.0', '172.31.255.255'],
                ['192.168.0.0', '192.168.255.255'],
            ];
            foreach ($privateRanges as $range) {
                $start = ip2long($range[0]);
                $end = ip2long($range[1]);
                if ($start !== false && $end !== false && $long >= $start && $long <= $end) {
                    return false;
                }
            }
        }
    }

    return true;
}

function sr_update_smoke_row(PDO $pdo, string $sql, array $params = []): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function sr_update_smoke_pending_contains(array $pendingUpdates, string $moduleKey, string $version): bool
{
    foreach ($pendingUpdates as $update) {
        if (
            (string) ($update['scope'] ?? '') === 'module'
            && (string) ($update['module_key'] ?? '') === $moduleKey
            && (string) ($update['version'] ?? '') === $version
        ) {
            return true;
        }
    }

    return false;
}

function sr_update_smoke_request(string $baseUrl, string $cookieFile, string $method, string $path, array $data = []): array
{
    $ch = curl_init($baseUrl . $path);
    if ($ch === false) {
        throw new RuntimeException('Could not initialize cURL.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 8,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_HEADER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'saanraan-update-apply-smoke/1.0',
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    if (!is_string($body)) {
        throw new RuntimeException($method . ' ' . $path . ' failed: ' . $error);
    }
    if ($status >= 400) {
        throw new RuntimeException($method . ' ' . $path . ' returned HTTP ' . $status . ' at ' . $effectiveUrl);
    }

    return [
        'status' => $status,
        'body' => $body,
        'url' => $effectiveUrl,
    ];
}

function sr_update_smoke_csrf(string $baseUrl, string $cookieFile, string $path): string
{
    $response = sr_update_smoke_request($baseUrl, $cookieFile, 'GET', $path);
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', (string) $response['body'], $matches) !== 1) {
        throw new RuntimeException('CSRF token not found on ' . $path);
    }

    return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
}
