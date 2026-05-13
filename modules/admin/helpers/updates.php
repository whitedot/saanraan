<?php

declare(strict_types=1);

function sr_admin_update_files(string $directory): array
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
            'checksum' => sr_admin_update_checksum($path),
        ];
    }

    return $updates;
}

function sr_admin_update_checksum(string $path): string
{
    if (!is_file($path)) {
        return '';
    }

    $checksum = hash_file('sha256', $path);
    return is_string($checksum) ? $checksum : '';
}

function sr_admin_update_statement_count(string $path): int
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

function sr_admin_update_path_is_allowed(array $update): bool
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

function sr_admin_acquire_update_lock(PDO $pdo): bool
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

function sr_admin_release_update_lock(PDO $pdo): void
{
    try {
        $stmt = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $stmt->execute(['lock_name' => 'saanraan_schema_updates']);
    } catch (Throwable $ignored) {
    }
}

function sr_admin_applied_schema_versions(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT scope, module_key, version FROM sr_schema_versions');
    $applied = [];

    foreach ($stmt->fetchAll() as $row) {
        $key = (string) $row['scope'] . '|' . (string) $row['module_key'] . '|' . (string) $row['version'];
        $applied[$key] = true;
    }

    return $applied;
}

function sr_admin_schema_versions(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT scope, module_key, version, applied_at
         FROM sr_schema_versions
         ORDER BY scope ASC, module_key ASC, version ASC'
    );

    return $stmt->fetchAll();
}

function sr_admin_pending_updates(PDO $pdo): array
{
    $applied = sr_admin_applied_schema_versions($pdo);
    $pending = [];

    foreach (sr_admin_update_files(SR_ROOT . '/database/core/updates') as $update) {
        $key = 'core||' . $update['version'];
        if (!isset($applied[$key])) {
            $pending[] = [
                'scope' => 'core',
                'module_key' => '',
                'label' => 'core',
                'version' => $update['version'],
                'path' => $update['path'],
                'checksum' => $update['checksum'],
                'statements' => sr_admin_update_statement_count((string) $update['path']),
            ];
        }
    }

    $stmt = $pdo->query('SELECT module_key FROM sr_modules ORDER BY module_key ASC');
    foreach ($stmt->fetchAll() as $module) {
        $moduleKey = (string) $module['module_key'];
        if (!sr_is_safe_module_key($moduleKey)) {
            continue;
        }

        foreach (sr_admin_update_files(SR_ROOT . '/modules/' . $moduleKey . '/updates') as $update) {
            $key = 'module|' . $moduleKey . '|' . $update['version'];
            if (!isset($applied[$key])) {
                $pending[] = [
                    'scope' => 'module',
                    'module_key' => $moduleKey,
                    'label' => $moduleKey,
                    'version' => $update['version'],
                    'path' => $update['path'],
                    'checksum' => $update['checksum'],
                    'statements' => sr_admin_update_statement_count((string) $update['path']),
                ];
            }
        }
    }

    return $pending;
}

function sr_admin_apply_update(PDO $pdo, array $update): void
{
    if (!sr_admin_update_path_is_allowed($update)) {
        throw new RuntimeException('Schema update path is invalid.');
    }

    $expectedChecksum = (string) ($update['checksum'] ?? '');
    if ($expectedChecksum !== '' && !hash_equals($expectedChecksum, sr_admin_update_checksum((string) $update['path']))) {
        throw new RuntimeException('Schema update checksum changed.');
    }

    sr_execute_sql_file($pdo, (string) $update['path']);
    sr_record_schema_version($pdo, (string) $update['scope'], (string) $update['module_key'], (string) $update['version']);
}

function sr_admin_previous_update_failure(): ?array
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

function sr_admin_audit_schema_update(PDO $pdo, array $account, array $update, string $eventType, string $result, string $message, array $metadata = []): void
{
    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => $eventType,
        'target_type' => (string) $update['scope'],
        'target_id' => (string) $update['label'] . ':' . (string) $update['version'],
        'result' => $result,
        'message' => $message,
        'metadata' => array_merge([
            'checksum' => (string) ($update['checksum'] ?? ''),
        ], $metadata),
    ]);
}

function sr_admin_audit_module_version_sync(PDO $pdo, array $account, array $syncedModule, string $message): void
{
    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => 'module.version.synced',
        'target_type' => 'module',
        'target_id' => (string) $syncedModule['module_key'],
        'result' => 'success',
        'message' => $message,
        'metadata' => [
            'before_version' => (string) $syncedModule['before_version'],
            'after_version' => (string) $syncedModule['after_version'],
        ],
    ]);
}

function sr_admin_handle_updates_post(PDO $pdo, array $account): array
{
    $errors = [];
    $notice = '';
    $appliedUpdates = [];
    $intent = sr_post_string('intent', 40);
    if ($intent === '') {
        $intent = 'apply_updates';
    }

    if (!in_array($intent, ['apply_updates', 'sync_file_only_versions'], true)) {
        $errors[] = '업데이트 요청이 올바르지 않습니다.';
    }

    $pendingUpdates = sr_admin_pending_updates($pdo);
    $backupConfirmed = ($_POST['backup_confirmed'] ?? '') === '1';

    if ($errors === [] && $intent === 'sync_file_only_versions') {
        $syncedModules = sr_admin_sync_file_only_module_versions(
            $pdo,
            sr_admin_module_pending_update_counts($pendingUpdates)
        );
        foreach ($syncedModules as $syncedModule) {
            sr_admin_audit_module_version_sync($pdo, $account, $syncedModule, 'Module installed version synced from updates screen.');
        }

        return [
            'errors' => [],
            'notice' => $syncedModules === [] ? '반영할 파일 전용 업데이트가 없습니다.' : '파일 전용 업데이트 버전을 반영했습니다.',
            'applied_updates' => [],
        ];
    }

    if ($errors === [] && $pendingUpdates !== [] && !$backupConfirmed) {
        $errors[] = '업데이트 전 백업 확인이 필요합니다.';
    }

    if ($errors === [] && $pendingUpdates !== []) {
        if (!sr_admin_acquire_update_lock($pdo)) {
            $errors[] = '다른 업데이트가 실행 중입니다. 잠시 후 다시 시도하세요.';
            sr_write_operational_marker('update-failed.json', [
                'stage' => 'acquire_update_lock',
                'message' => 'Schema update lock could not be acquired.',
            ]);
        } else {
            try {
                $pendingUpdates = sr_admin_pending_updates($pdo);
                foreach ($pendingUpdates as $update) {
                    try {
                        sr_admin_audit_schema_update($pdo, $account, $update, 'schema.update.started', 'success', 'Schema update started.');

                        sr_admin_apply_update($pdo, $update);
                        $appliedUpdates[] = $update;

                        sr_admin_audit_schema_update($pdo, $account, $update, 'schema.update.completed', 'success', 'Schema update completed.');
                    } catch (Throwable $exception) {
                        sr_admin_audit_schema_update(
                            $pdo,
                            $account,
                            $update,
                            'schema.update.failed',
                            'failure',
                            'Schema update failed.',
                            ['error' => sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500))]
                        );
                        $errors[] = (string) $update['label'] . ' ' . (string) $update['version'] . ' 업데이트 중 오류가 발생했습니다.';
                        sr_write_operational_marker('update-failed.json', [
                            'stage' => 'apply_update',
                            'scope' => (string) $update['scope'],
                            'module_key' => (string) $update['module_key'],
                            'version' => (string) $update['version'],
                            'checksum' => (string) ($update['checksum'] ?? ''),
                            'message' => sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500)),
                        ]);
                        break;
                    }
                }
            } finally {
                sr_admin_release_update_lock($pdo);
            }
        }
    }

    if ($errors === []) {
        sr_clear_operational_marker('update-failed.json');
        $syncedModules = sr_admin_sync_file_only_module_versions(
            $pdo,
            sr_admin_module_pending_update_counts(sr_admin_pending_updates($pdo))
        );
        foreach ($syncedModules as $syncedModule) {
            sr_admin_audit_module_version_sync($pdo, $account, $syncedModule, 'Module installed version synced after schema updates.');
        }
        if ($appliedUpdates === []) {
            $notice = $syncedModules === [] ? '' : '파일 전용 업데이트 버전을 반영했습니다.';
        } else {
            $notice = $syncedModules === [] ? '업데이트를 적용했습니다.' : '업데이트를 적용하고 모듈 설치 버전을 반영했습니다.';
        }
    }

    return [
        'errors' => $errors,
        'notice' => $notice,
        'applied_updates' => $appliedUpdates,
    ];
}

function sr_admin_module_version_drifts(PDO $pdo, array $pendingUpdateCounts): array
{
    $moduleVersionDrifts = [];
    $stmt = $pdo->query('SELECT module_key, version FROM sr_modules ORDER BY module_key ASC');
    foreach ($stmt->fetchAll() as $module) {
        $moduleKey = (string) ($module['module_key'] ?? '');
        if (!sr_is_safe_module_key($moduleKey)) {
            continue;
        }

        $metadata = sr_module_metadata($moduleKey);
        $codeVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
        $installedVersion = (string) ($module['version'] ?? '');
        if ($codeVersion === '' || $installedVersion === '' || $codeVersion === $installedVersion) {
            continue;
        }

        $moduleVersionDrifts[] = [
            'module_key' => $moduleKey,
            'installed_version' => $installedVersion,
            'code_version' => $codeVersion,
            'pending_update_count' => (int) ($pendingUpdateCounts[$moduleKey] ?? 0),
            'state' => strcmp($codeVersion, $installedVersion) > 0 ? 'code_newer' : 'code_older',
        ];
    }

    return $moduleVersionDrifts;
}

function sr_admin_file_only_module_version_drifts(array $moduleVersionDrifts): array
{
    $fileOnlyDrifts = [];
    foreach ($moduleVersionDrifts as $drift) {
        if ((int) ($drift['pending_update_count'] ?? 0) > 0 || (string) ($drift['state'] ?? '') !== 'code_newer') {
            continue;
        }

        $fileOnlyDrifts[] = $drift;
    }

    return $fileOnlyDrifts;
}
