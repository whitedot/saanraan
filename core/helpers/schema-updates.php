<?php

declare(strict_types=1);

function sr_schema_update_files(string $directory): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $paths = glob($directory . '/*.sql');
    if ($paths === false) {
        return [];
    }

    sort($paths, SORT_STRING);

    $updates = [];
    foreach ($paths as $path) {
        $version = basename($path, '.sql');
        if (preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $version) !== 1) {
            continue;
        }

        $updates[] = [
            'version' => $version,
            'path' => $path,
            'checksum' => sr_schema_update_checksum($path),
        ];
    }

    return $updates;
}

function sr_schema_update_checksum(string $path): string
{
    if (!is_file($path)) {
        return '';
    }

    $checksum = hash_file('sha256', $path);
    return is_string($checksum) ? $checksum : '';
}

function sr_schema_update_statement_count(string $path): int
{
    if (!is_file($path)) {
        return 0;
    }

    $sql = file_get_contents($path);
    if (!is_string($sql)) {
        return 0;
    }

    return count(sr_split_sql_statements($sql));
}

function sr_schema_update_path_is_allowed(array $update): bool
{
    $scope = (string) ($update['scope'] ?? '');
    $moduleKey = (string) ($update['module_key'] ?? '');
    $version = (string) ($update['version'] ?? '');
    $path = (string) ($update['path'] ?? '');

    if (preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $version) !== 1 || !is_file($path)) {
        return false;
    }

    if ($scope === 'core') {
        $expectedDirectory = realpath(SR_ROOT . '/database/core/updates');
        $expectedModuleKey = '';
    } elseif ($scope === 'module' && sr_is_safe_module_key($moduleKey)) {
        $expectedDirectory = realpath(SR_ROOT . '/modules/' . $moduleKey . '/updates');
        $expectedModuleKey = $moduleKey;
    } else {
        return false;
    }

    if ($moduleKey !== $expectedModuleKey || $expectedDirectory === false) {
        return false;
    }

    $realPath = realpath($path);
    if ($realPath === false || strpos($realPath, $expectedDirectory . DIRECTORY_SEPARATOR) !== 0) {
        return false;
    }

    return basename($realPath) === $version . '.sql';
}

function sr_schema_update_lock_acquire(PDO $pdo): bool
{
    try {
        $stmt = $pdo->prepare('SELECT GET_LOCK(:lock_name, 10) AS lock_acquired');
        $stmt->execute(['lock_name' => 'saanraan_schema_updates']);
        $row = $stmt->fetch();
    } catch (Throwable $exception) {
        return false;
    }

    return is_array($row) && (string) ($row['lock_acquired'] ?? '') === '1';
}

function sr_schema_update_lock_release(PDO $pdo): void
{
    try {
        $stmt = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $stmt->execute(['lock_name' => 'saanraan_schema_updates']);
    } catch (Throwable $ignored) {
    }
}

function sr_applied_schema_version_map(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT scope, module_key, version FROM sr_schema_versions');
    $applied = [];

    foreach ($stmt->fetchAll() as $row) {
        $key = (string) $row['scope'] . '|' . (string) $row['module_key'] . '|' . (string) $row['version'];
        $applied[$key] = true;
    }

    return $applied;
}

function sr_schema_version_rows(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT scope, module_key, version, applied_at
         FROM sr_schema_versions
         ORDER BY scope ASC, module_key ASC, version ASC'
    );

    return $stmt->fetchAll();
}

function sr_pending_schema_updates(PDO $pdo): array
{
    $applied = sr_applied_schema_version_map($pdo);
    $pending = [];

    foreach (sr_schema_update_files(SR_ROOT . '/database/core/updates') as $update) {
        $key = 'core||' . $update['version'];
        if (!isset($applied[$key])) {
            $pending[] = [
                'scope' => 'core',
                'module_key' => '',
                'label' => 'core',
                'version' => $update['version'],
                'path' => $update['path'],
                'checksum' => $update['checksum'],
                'statements' => sr_schema_update_statement_count((string) $update['path']),
            ];
        }
    }

    $stmt = $pdo->query('SELECT module_key FROM sr_modules ORDER BY module_key ASC');
    foreach ($stmt->fetchAll() as $module) {
        $moduleKey = (string) $module['module_key'];
        if (!sr_is_safe_module_key($moduleKey)) {
            continue;
        }

        foreach (sr_schema_update_files(SR_ROOT . '/modules/' . $moduleKey . '/updates') as $update) {
            $key = 'module|' . $moduleKey . '|' . $update['version'];
            if (!isset($applied[$key])) {
                $pending[] = [
                    'scope' => 'module',
                    'module_key' => $moduleKey,
                    'label' => $moduleKey,
                    'version' => $update['version'],
                    'path' => $update['path'],
                    'checksum' => $update['checksum'],
                    'statements' => sr_schema_update_statement_count((string) $update['path']),
                ];
            }
        }
    }

    return $pending;
}

function sr_apply_schema_update(PDO $pdo, array $update): void
{
    if (!sr_schema_update_path_is_allowed($update)) {
        throw new RuntimeException('Schema update path is invalid.');
    }

    $expectedChecksum = (string) ($update['checksum'] ?? '');
    if ($expectedChecksum !== '' && !hash_equals($expectedChecksum, sr_schema_update_checksum((string) $update['path']))) {
        throw new RuntimeException('Schema update checksum changed.');
    }

    sr_execute_sql_file($pdo, (string) $update['path']);
    sr_record_schema_version($pdo, (string) $update['scope'], (string) $update['module_key'], (string) $update['version']);
}

function sr_previous_schema_update_failure(): ?array
{
    $path = SR_ROOT . '/storage/update-failed.json';
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $json = file_get_contents($path);
    $decoded = is_string($json) ? json_decode($json, true) : null;
    if (!is_array($decoded)) {
        return null;
    }

    return [
        'recorded_at' => (string) ($decoded['recorded_at'] ?? ''),
        'stage' => (string) ($decoded['stage'] ?? ''),
        'scope' => (string) ($decoded['scope'] ?? ''),
        'module_key' => (string) ($decoded['module_key'] ?? ''),
        'version' => (string) ($decoded['version'] ?? ''),
        'checksum' => (string) ($decoded['checksum'] ?? ''),
        'message' => sr_log_sensitive_text_sanitize(sr_log_line_value((string) ($decoded['message'] ?? ''), 500)),
    ];
}

