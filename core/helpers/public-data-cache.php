<?php

declare(strict_types=1);

function sr_public_data_cache_namespace(string $namespace): string
{
    $namespace = strtolower(trim($namespace));

    return preg_match('/\A[a-z][a-z0-9_-]{1,59}\z/', $namespace) === 1 ? $namespace : '';
}

function sr_public_data_cache_schema(string $schema): string
{
    $schema = strtolower(trim($schema));

    return preg_match('/\A[a-z][a-z0-9_.-]{1,79}\z/', $schema) === 1 ? $schema : '';
}

function sr_public_data_cache_root(): string
{
    return rtrim((string) SR_ROOT, '/\\') . '/storage/cache/public-data';
}

function sr_public_data_cache_hash(string $cacheKey, string $payloadSchema): string
{
    return hash('sha256', $payloadSchema . "\n" . $cacheKey);
}

function sr_public_data_cache_path(string $namespace, string $cacheKey, string $payloadSchema): string
{
    $namespace = sr_public_data_cache_namespace($namespace);
    $payloadSchema = sr_public_data_cache_schema($payloadSchema);
    if ($namespace === '' || $payloadSchema === '') {
        return '';
    }

    $hash = sr_public_data_cache_hash($cacheKey, $payloadSchema);

    return sr_public_data_cache_root() . '/' . $namespace . '/' . substr($hash, 0, 2) . '/' . $hash . '.json';
}

function sr_public_data_cache_read(string $namespace, string $cacheKey, string $payloadSchema): ?array
{
    $namespace = sr_public_data_cache_namespace($namespace);
    $payloadSchema = sr_public_data_cache_schema($payloadSchema);
    if ($namespace === '' || $payloadSchema === '') {
        return null;
    }

    $hash = sr_public_data_cache_hash($cacheKey, $payloadSchema);
    $memory = $GLOBALS['sr_public_data_cache_memory'] ?? [];
    if (is_array($memory) && isset($memory[$namespace][$hash]) && is_array($memory[$namespace][$hash])) {
        return $memory[$namespace][$hash];
    }

    $path = sr_public_data_cache_path($namespace, $cacheKey, $payloadSchema);
    if ($path === '' || !is_file($path) || !is_readable($path)) {
        return null;
    }

    $json = file_get_contents($path);
    $record = is_string($json) ? json_decode($json, true) : null;
    if (!is_array($record)
        || (string) ($record['schema_version'] ?? '') !== 'sr_public_data_file_cache_v1'
        || (string) ($record['namespace'] ?? '') !== $namespace
        || (string) ($record['cache_hash'] ?? '') !== $hash
        || (string) ($record['payload_schema'] ?? '') !== $payloadSchema
        || !is_array($record['payload'] ?? null)
    ) {
        return null;
    }

    $GLOBALS['sr_public_data_cache_memory'][$namespace][$hash] = $record['payload'];

    return $record['payload'];
}

function sr_public_data_cache_write(string $namespace, string $cacheKey, string $payloadSchema, array $payload): bool
{
    $namespace = sr_public_data_cache_namespace($namespace);
    $payloadSchema = sr_public_data_cache_schema($payloadSchema);
    if ($namespace === '' || $payloadSchema === '') {
        return false;
    }

    $hash = sr_public_data_cache_hash($cacheKey, $payloadSchema);
    $record = [
        'schema_version' => 'sr_public_data_file_cache_v1',
        'namespace' => $namespace,
        'cache_hash' => $hash,
        'payload_schema' => $payloadSchema,
        'payload' => $payload,
        'generated_at' => function_exists('sr_now') ? sr_now() : date('Y-m-d H:i:s'),
    ];
    $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $path = sr_public_data_cache_path($namespace, $cacheKey, $payloadSchema);
    if (!is_string($json) || $path === '' || !sr_write_file_atomically($path, $json . "\n")) {
        return false;
    }

    $GLOBALS['sr_public_data_cache_memory'][$namespace][$hash] = $payload;

    return true;
}

function sr_public_data_cache_forget(string $namespace, string $cacheKey, string $payloadSchema): void
{
    $namespace = sr_public_data_cache_namespace($namespace);
    $payloadSchema = sr_public_data_cache_schema($payloadSchema);
    if ($namespace === '' || $payloadSchema === '') {
        return;
    }

    $hash = sr_public_data_cache_hash($cacheKey, $payloadSchema);
    unset($GLOBALS['sr_public_data_cache_memory'][$namespace][$hash]);
    $path = sr_public_data_cache_path($namespace, $cacheKey, $payloadSchema);
    if ($path !== '' && is_file($path)) {
        @unlink($path);
    }
}

function sr_public_data_cache_clear_namespace(string $namespace): void
{
    $namespace = sr_public_data_cache_namespace($namespace);
    if ($namespace === '') {
        return;
    }

    unset($GLOBALS['sr_public_data_cache_memory'][$namespace]);
    $directory = sr_public_data_cache_root() . '/' . $namespace;
    foreach (glob($directory . '/*/*.json') ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
