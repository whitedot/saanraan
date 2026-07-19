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

function sr_public_data_cache_root(string $root = ''): string
{
    $root = $root !== '' ? $root : (string) SR_ROOT;

    return rtrim($root, '/\\') . '/storage/cache/public-data';
}

function sr_public_data_cache_log_failure(string $context, string $message): void
{
    $exception = new RuntimeException($message);
    if (function_exists('sr_log_exception')) {
        sr_log_exception($exception, $context);
        return;
    }

    error_log('[saanraan] ' . $context . ': ' . $message);
}

function sr_public_data_cache_unlink(string $path, string $context): bool
{
    if (!is_file($path)) {
        return true;
    }
    if (@unlink($path)) {
        return true;
    }

    sr_public_data_cache_log_failure($context, 'Cache file could not be deleted: ' . $path);
    return false;
}

function sr_public_data_cache_hash(string $cacheKey, string $payloadSchema): string
{
    return hash('sha256', $payloadSchema . "\n" . $cacheKey);
}

function sr_public_data_cache_namespace_generation_path(string $namespace, string $root = ''): string
{
    $namespace = sr_public_data_cache_namespace($namespace);
    if ($namespace === '') {
        return '';
    }

    return sr_public_data_cache_root($root) . '/' . $namespace . '/.generation';
}

function sr_public_data_cache_entry_generation_path(
    string $namespace,
    string $cacheKey,
    string $payloadSchema,
    string $root = ''
): string {
    $namespace = sr_public_data_cache_namespace($namespace);
    $payloadSchema = sr_public_data_cache_schema($payloadSchema);
    if ($namespace === '' || $payloadSchema === '') {
        return '';
    }

    $identityHash = sr_public_data_cache_hash($cacheKey, $payloadSchema);

    return sr_public_data_cache_root($root) . '/' . $namespace . '/.generations/' . $identityHash . '.generation';
}

function sr_public_data_cache_generation_marker(string $path): string
{
    static $reportedInvalidPaths = [];

    if ($path === '') {
        return '';
    }
    if (!file_exists($path) && !is_link($path)) {
        return 'initial';
    }

    $contents = is_file($path) ? @file_get_contents($path) : false;
    $generation = is_string($contents) ? strtolower(trim($contents)) : '';
    if (preg_match('/\A[a-f0-9]{32}\z/', $generation) === 1) {
        return $generation;
    }

    if (!isset($reportedInvalidPaths[$path])) {
        sr_public_data_cache_log_failure('public_data_cache_generation_invalid', 'Cache generation marker is unreadable or invalid: ' . $path);
        $reportedInvalidPaths[$path] = true;
    }

    return '';
}

function sr_public_data_cache_generation(string $namespace, string $cacheKey, string $payloadSchema): string
{
    $namespace = sr_public_data_cache_namespace($namespace);
    $payloadSchema = sr_public_data_cache_schema($payloadSchema);
    if ($namespace === '' || $payloadSchema === '') {
        return '';
    }

    $namespaceGeneration = sr_public_data_cache_generation_marker(
        sr_public_data_cache_namespace_generation_path($namespace)
    );
    $entryGeneration = sr_public_data_cache_generation_marker(
        sr_public_data_cache_entry_generation_path($namespace, $cacheKey, $payloadSchema)
    );
    if ($namespaceGeneration === '' || $entryGeneration === '') {
        return '';
    }

    return hash('sha256', $namespaceGeneration . "\n" . $entryGeneration);
}

function sr_public_data_cache_new_generation(): string
{
    try {
        return bin2hex(random_bytes(16));
    } catch (Throwable $exception) {
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'public_data_cache_generation_create_failed');
        } else {
            error_log('[saanraan] public_data_cache_generation_create_failed');
        }

        return '';
    }
}

function sr_public_data_cache_rotate_generation(string $path, string $context): bool
{
    $generation = sr_public_data_cache_new_generation();
    if ($path === '' || $generation === '') {
        return false;
    }

    try {
        $written = sr_write_file_atomically($path, $generation . "\n");
    } catch (Throwable $exception) {
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, $context);
        } else {
            error_log('[saanraan] ' . $context . ': ' . $exception->getMessage());
        }
        return false;
    }
    if (!$written) {
        sr_public_data_cache_log_failure($context, 'Cache generation marker could not be written: ' . $path);
        return false;
    }

    return true;
}

function sr_public_data_cache_versioned_hash(string $cacheKey, string $payloadSchema, string $generation): string
{
    return hash('sha256', $generation . "\n" . $payloadSchema . "\n" . $cacheKey);
}

function sr_public_data_cache_legacy_path(string $namespace, string $cacheKey, string $payloadSchema): string
{
    $namespace = sr_public_data_cache_namespace($namespace);
    $payloadSchema = sr_public_data_cache_schema($payloadSchema);
    if ($namespace === '' || $payloadSchema === '') {
        return '';
    }

    $hash = sr_public_data_cache_hash($cacheKey, $payloadSchema);

    return sr_public_data_cache_root() . '/' . $namespace . '/' . substr($hash, 0, 2) . '/' . $hash . '.json';
}

function sr_public_data_cache_path(
    string $namespace,
    string $cacheKey,
    string $payloadSchema,
    string $generation = ''
): string
{
    $namespace = sr_public_data_cache_namespace($namespace);
    $payloadSchema = sr_public_data_cache_schema($payloadSchema);
    if ($namespace === '' || $payloadSchema === '') {
        return '';
    }

    $generation = $generation !== '' ? $generation : sr_public_data_cache_generation($namespace, $cacheKey, $payloadSchema);
    if (preg_match('/\A[a-f0-9]{64}\z/', $generation) !== 1) {
        return '';
    }
    $hash = sr_public_data_cache_versioned_hash($cacheKey, $payloadSchema, $generation);

    return sr_public_data_cache_root() . '/' . $namespace . '/' . substr($hash, 0, 2) . '/' . $hash . '.json';
}

function sr_public_data_cache_read(
    string $namespace,
    string $cacheKey,
    string $payloadSchema,
    string $generation = ''
): ?array
{
    $namespace = sr_public_data_cache_namespace($namespace);
    $payloadSchema = sr_public_data_cache_schema($payloadSchema);
    if ($namespace === '' || $payloadSchema === '') {
        return null;
    }

    $currentGeneration = sr_public_data_cache_generation($namespace, $cacheKey, $payloadSchema);
    $generation = $generation !== '' ? $generation : $currentGeneration;
    if ($currentGeneration === '' || !hash_equals($currentGeneration, $generation)) {
        return null;
    }
    $hash = sr_public_data_cache_versioned_hash($cacheKey, $payloadSchema, $generation);
    $memory = $GLOBALS['sr_public_data_cache_memory'] ?? [];
    if (is_array($memory) && isset($memory[$namespace][$hash]) && is_array($memory[$namespace][$hash])) {
        if (!hash_equals(sr_public_data_cache_generation($namespace, $cacheKey, $payloadSchema), $generation)) {
            return null;
        }
        return $memory[$namespace][$hash];
    }

    $path = sr_public_data_cache_path($namespace, $cacheKey, $payloadSchema, $generation);
    if ($path === '' || !is_file($path) || !is_readable($path)) {
        return null;
    }

    $json = @file_get_contents($path);
    $record = is_string($json) ? json_decode($json, true) : null;
    if (!is_array($record)
        || (string) ($record['schema_version'] ?? '') !== 'sr_public_data_file_cache_v2'
        || (string) ($record['namespace'] ?? '') !== $namespace
        || (string) ($record['cache_hash'] ?? '') !== $hash
        || (string) ($record['payload_schema'] ?? '') !== $payloadSchema
        || (string) ($record['generation'] ?? '') !== $generation
        || !is_array($record['payload'] ?? null)
    ) {
        sr_public_data_cache_unlink($path, 'public_data_cache_invalid_record_delete_failed');
        return null;
    }
    if (!hash_equals(sr_public_data_cache_generation($namespace, $cacheKey, $payloadSchema), $generation)) {
        return null;
    }

    sr_public_data_cache_remember($namespace, $hash, $record['payload']);

    return $record['payload'];
}

function sr_public_data_cache_write(
    string $namespace,
    string $cacheKey,
    string $payloadSchema,
    array $payload,
    string $generation = ''
): bool
{
    $namespace = sr_public_data_cache_namespace($namespace);
    $payloadSchema = sr_public_data_cache_schema($payloadSchema);
    if ($namespace === '' || $payloadSchema === '') {
        return false;
    }

    $currentGeneration = sr_public_data_cache_generation($namespace, $cacheKey, $payloadSchema);
    $generation = $generation !== '' ? $generation : $currentGeneration;
    if ($currentGeneration === '' || !hash_equals($currentGeneration, $generation)) {
        return false;
    }
    $hash = sr_public_data_cache_versioned_hash($cacheKey, $payloadSchema, $generation);
    $record = [
        'schema_version' => 'sr_public_data_file_cache_v2',
        'namespace' => $namespace,
        'cache_hash' => $hash,
        'payload_schema' => $payloadSchema,
        'generation' => $generation,
        'payload' => $payload,
        'generated_at' => function_exists('sr_now') ? sr_now() : date('Y-m-d H:i:s'),
    ];
    $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $path = sr_public_data_cache_path($namespace, $cacheKey, $payloadSchema, $generation);
    if (!is_string($json) || $path === '') {
        return false;
    }
    try {
        $written = sr_write_file_atomically($path, $json . "\n");
    } catch (Throwable $exception) {
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'public_data_cache_write_failed');
        } else {
            error_log('[saanraan] public_data_cache_write_failed: ' . $exception->getMessage());
        }
        return false;
    }
    if (!$written) {
        sr_public_data_cache_log_failure('public_data_cache_write_failed', 'Cache payload could not be written: ' . $path);
        return false;
    }

    sr_public_data_cache_remember($namespace, $hash, $payload);
    sr_public_data_cache_unlink(
        sr_public_data_cache_legacy_path($namespace, $cacheKey, $payloadSchema),
        'public_data_cache_legacy_cleanup_failed'
    );
    return true;
}

function sr_public_data_cache_remember(string $namespace, string $hash, array $payload): void
{
    $memory = $GLOBALS['sr_public_data_cache_memory'] ?? [];
    if (!is_array($memory)) {
        $memory = [];
    }
    if (!isset($memory[$namespace]) || !is_array($memory[$namespace])) {
        $memory[$namespace] = [];
    }

    $memory[$namespace][$hash] = $payload;
    $GLOBALS['sr_public_data_cache_memory'] = $memory;
}

function sr_public_data_cache_forget(string $namespace, string $cacheKey, string $payloadSchema): bool
{
    $namespace = sr_public_data_cache_namespace($namespace);
    $payloadSchema = sr_public_data_cache_schema($payloadSchema);
    if ($namespace === '' || $payloadSchema === '') {
        return false;
    }

    $namespaceGeneration = sr_public_data_cache_generation_marker(
        sr_public_data_cache_namespace_generation_path($namespace)
    );
    if ($namespaceGeneration === '') {
        return sr_public_data_cache_clear_namespace($namespace);
    }

    $generation = sr_public_data_cache_generation($namespace, $cacheKey, $payloadSchema);
    $hash = sr_public_data_cache_versioned_hash($cacheKey, $payloadSchema, $generation);
    $path = sr_public_data_cache_path($namespace, $cacheKey, $payloadSchema, $generation);
    $legacyPath = sr_public_data_cache_legacy_path($namespace, $cacheKey, $payloadSchema);
    $generationPath = sr_public_data_cache_entry_generation_path($namespace, $cacheKey, $payloadSchema);
    if (!sr_public_data_cache_rotate_generation($generationPath, 'public_data_cache_entry_invalidation_failed')) {
        $memory = $GLOBALS['sr_public_data_cache_memory'] ?? [];
        if (is_array($memory)) {
            unset($memory[$namespace]);
            $GLOBALS['sr_public_data_cache_memory'] = $memory;
        }
        sr_public_data_cache_unlink($path, 'public_data_cache_entry_fallback_cleanup_failed');
        sr_public_data_cache_unlink($legacyPath, 'public_data_cache_legacy_fallback_cleanup_failed');
        return false;
    }

    $memory = $GLOBALS['sr_public_data_cache_memory'] ?? [];
    if (is_array($memory)) {
        unset($memory[$namespace][$hash]);
        $GLOBALS['sr_public_data_cache_memory'] = $memory;
    }

    sr_public_data_cache_unlink($path, 'public_data_cache_entry_cleanup_failed');
    sr_public_data_cache_unlink($legacyPath, 'public_data_cache_legacy_cleanup_failed');
    return true;
}

function sr_public_data_cache_clear_namespace(string $namespace): bool
{
    $namespace = sr_public_data_cache_namespace($namespace);
    if ($namespace === '') {
        return false;
    }

    $directory = sr_public_data_cache_root() . '/' . $namespace;
    $oldPaths = glob($directory . '/*/*.json') ?: [];
    if (!sr_public_data_cache_rotate_generation(
        sr_public_data_cache_namespace_generation_path($namespace),
        'public_data_cache_namespace_invalidation_failed'
    )) {
        $memory = $GLOBALS['sr_public_data_cache_memory'] ?? [];
        if (is_array($memory)) {
            unset($memory[$namespace]);
            $GLOBALS['sr_public_data_cache_memory'] = $memory;
        }
        foreach ($oldPaths as $path) {
            sr_public_data_cache_unlink($path, 'public_data_cache_namespace_fallback_cleanup_failed');
        }
        return false;
    }

    $memory = $GLOBALS['sr_public_data_cache_memory'] ?? [];
    if (is_array($memory)) {
        unset($memory[$namespace]);
        $GLOBALS['sr_public_data_cache_memory'] = $memory;
    }
    foreach ($oldPaths as $path) {
        sr_public_data_cache_unlink($path, 'public_data_cache_namespace_cleanup_failed');
    }

    return true;
}

function sr_public_data_cache_clear_all(string $root = ''): int
{
    $cacheRoot = sr_public_data_cache_root($root);
    $deleted = 0;
    foreach (glob($cacheRoot . '/*/*/*.json') ?: [] as $path) {
        if (is_file($path) && sr_public_data_cache_unlink($path, 'public_data_cache_reset_payload_delete_failed')) {
            $deleted++;
        }
    }
    foreach (glob($cacheRoot . '/*/.generation') ?: [] as $path) {
        sr_public_data_cache_unlink($path, 'public_data_cache_reset_generation_delete_failed');
    }
    foreach (glob($cacheRoot . '/*/.generations/*.generation') ?: [] as $path) {
        sr_public_data_cache_unlink($path, 'public_data_cache_reset_entry_generation_delete_failed');
    }

    if ($root === '' || rtrim($root, '/\\') === rtrim((string) SR_ROOT, '/\\')) {
        $GLOBALS['sr_public_data_cache_memory'] = [];
    }

    return $deleted;
}
