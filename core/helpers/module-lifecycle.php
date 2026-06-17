<?php

declare(strict_types=1);

require_once SR_ROOT . '/core/helpers/schema-updates.php';
require_once SR_ROOT . '/core/helpers/module-metadata.php';
require_once SR_ROOT . '/core/helpers/module-source.php';

function sr_module_pending_update_counts(array $pendingUpdates): array
{
    $counts = [];
    foreach ($pendingUpdates as $update) {
        if ((string) ($update['scope'] ?? '') !== 'module') {
            continue;
        }

        $moduleKey = (string) ($update['module_key'] ?? '');
        if (!sr_is_safe_module_key($moduleKey)) {
            continue;
        }

        $counts[$moduleKey] = (int) ($counts[$moduleKey] ?? 0) + 1;
    }

    return $counts;
}

function sr_sync_module_version(PDO $pdo, string $moduleKey, string $newVersion): void
{
    if (!sr_is_safe_module_key($moduleKey) || preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $newVersion) !== 1) {
        throw new InvalidArgumentException('Module version is invalid.');
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_modules
         SET version = :version, updated_at = :updated_at
         WHERE module_key = :module_key'
    );
    $stmt->execute([
        'version' => $newVersion,
        'updated_at' => sr_now(),
        'module_key' => $moduleKey,
    ]);
}

function sr_sync_file_only_module_versions(PDO $pdo, array $pendingUpdateCounts): array
{
    $synced = [];
    $stmt = $pdo->query('SELECT module_key, version, status FROM sr_modules ORDER BY module_key ASC');
    foreach ($stmt->fetchAll() as $module) {
        $moduleKey = (string) ($module['module_key'] ?? '');
        $installedVersion = (string) ($module['version'] ?? '');
        if (!sr_is_safe_module_key($moduleKey) || (int) ($pendingUpdateCounts[$moduleKey] ?? 0) > 0) {
            continue;
        }

        $metadata = sr_module_metadata($moduleKey);
        $codeVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
        if (
            preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $codeVersion) !== 1
            || strcmp($codeVersion, $installedVersion) <= 0
            || !sr_module_contract_is_loadable($moduleKey)
            || sr_module_requirement_errors($pdo, $moduleKey, $metadata, (string) ($module['status'] ?? 'enabled')) !== []
        ) {
            continue;
        }

        sr_sync_module_version($pdo, $moduleKey, $codeVersion);
        $synced[] = [
            'module_key' => $moduleKey,
            'before_version' => $installedVersion,
            'after_version' => $codeVersion,
        ];
    }

    return $synced;
}

function sr_module_route_conflict_errors(PDO $pdo, string $candidateModuleKey): array
{
    if (!sr_is_safe_module_key($candidateModuleKey)) {
        return ['모듈 키가 올바르지 않습니다.'];
    }

    $candidateRoutes = sr_module_route_map($candidateModuleKey);
    if ($candidateRoutes['errors'] !== []) {
        return $candidateRoutes['errors'];
    }

    $candidateRouteMap = $candidateRoutes['routes'];
    if ($candidateRouteMap === []) {
        return [];
    }

    $errors = [];
    foreach (sr_enabled_module_contract_files($pdo, 'paths.php', [$candidateModuleKey]) as $moduleKey => $pathsFile) {
        $paths = sr_load_module_contract_file($moduleKey, $pathsFile);
        if (!is_array($paths)) {
            continue;
        }

        foreach ($paths as $route => $actionRelativePath) {
            $route = (string) $route;
            $actionRelativePath = (string) $actionRelativePath;
            if (!sr_is_valid_module_route($route)) {
                continue;
            }

            foreach (array_keys($candidateRouteMap) as $candidateRoute) {
                if (!sr_module_routes_conflict((string) $candidateRoute, $route)) {
                    continue;
                }

                $errors[] = $candidateModuleKey . ' 모듈 주소 경로가 ' . $moduleKey . ' 모듈과 충돌합니다: ' . (string) $candidateRoute . ' / ' . $route;
            }
        }
    }

    return array_values(array_unique($errors));
}

function sr_module_route_map(string $moduleKey): array
{
    $moduleDir = SR_ROOT . '/modules/' . $moduleKey;
    $pathsFile = $moduleDir . '/paths.php';
    if (!is_file($pathsFile)) {
        return ['routes' => [], 'errors' => []];
    }

    $paths = sr_load_module_contract_file($moduleKey, $pathsFile);
    if (!is_array($paths)) {
        return ['routes' => [], 'errors' => [$moduleKey . ' 모듈의 paths.php는 배열을 반환해야 합니다.']];
    }

    $routes = [];
    $errors = [];
    foreach ($paths as $route => $actionRelativePath) {
        $route = (string) $route;
        $actionRelativePath = (string) $actionRelativePath;
        if (!sr_is_valid_module_route($route)) {
            $errors[] = $moduleKey . ' 모듈 주소 경로 형식이 올바르지 않습니다: ' . $route;
            continue;
        }

        if (!sr_is_safe_module_action($actionRelativePath)) {
            $errors[] = $moduleKey . ' 모듈 실행 파일 경로가 올바르지 않습니다: ' . $route;
            continue;
        }

        if (!is_file($moduleDir . '/' . $actionRelativePath)) {
            $errors[] = $moduleKey . ' 모듈 실행 파일을 찾을 수 없습니다: ' . $route;
            continue;
        }

        $routes[$route] = $actionRelativePath;
    }

    return ['routes' => $routes, 'errors' => array_values(array_unique($errors))];
}

function sr_module_code_older_errors(PDO $pdo, string $moduleKey): array
{
    $module = sr_module_record_entry($pdo, $moduleKey);
    $metadata = sr_module_metadata($moduleKey);
    $installedVersion = is_array($module) ? (string) ($module['version'] ?? '') : '';
    $codeVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';

    if (
        preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $installedVersion) === 1
        && preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $codeVersion) === 1
        && strcmp($codeVersion, $installedVersion) < 0
    ) {
        return [sr_t('module.lifecycle.error.code_older')];
    }

    return [];
}

function sr_module_lifecycle_state(array $module): array
{
    $status = (string) ($module['status'] ?? '');
    $metadataErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : [];
    $pendingUpdateCount = (int) ($module['pending_update_count'] ?? 0);
    $versionState = (string) ($module['version_state'] ?? 'unknown');

    if (in_array($status, ['failed', 'installing'], true)) {
        return [
            'state' => 'install_incomplete',
            'label' => sr_t('module.lifecycle.install_incomplete.label'),
            'action' => sr_t('module.lifecycle.install_incomplete.action'),
        ];
    }

    if ($metadataErrors !== []) {
        return [
            'state' => 'contract_error',
            'label' => sr_t('module.lifecycle.contract_error.label'),
            'action' => sr_t('module.lifecycle.contract_error.action'),
        ];
    }

    if ($versionState === 'code_older') {
        return [
            'state' => 'code_older',
            'label' => sr_t('module.lifecycle.code_older.label'),
            'action' => sr_t('module.lifecycle.code_older.action'),
        ];
    }

    if ($pendingUpdateCount > 0) {
        return [
            'state' => 'sql_pending',
            'label' => sr_t('module.lifecycle.sql_pending.label'),
            'action' => sr_t('module.lifecycle.sql_pending.action'),
        ];
    }

    if ($versionState === 'code_newer') {
        return [
            'state' => 'file_only_update',
            'label' => sr_t('module.lifecycle.file_only_update.label'),
            'action' => sr_t('module.lifecycle.file_only_update.action'),
        ];
    }

    if ($status === 'enabled') {
        return [
            'state' => 'enabled_current',
            'label' => sr_t('module.lifecycle.enabled_current.label'),
            'action' => '-',
        ];
    }

    if ($status === 'disabled') {
        return [
            'state' => 'disabled_current',
            'label' => sr_t('module.lifecycle.disabled_current.label'),
            'action' => sr_t('module.lifecycle.disabled_current.action'),
        ];
    }

    return [
        'state' => 'unknown',
        'label' => sr_t('module.lifecycle.unknown.label'),
        'action' => sr_t('module.lifecycle.unknown.action'),
    ];
}

function sr_module_version_drifts(PDO $pdo, array $pendingUpdateCounts): array
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

function sr_file_only_module_version_drifts(array $moduleVersionDrifts): array
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

function sr_foundation_module_keys(): array
{
    return ['asset_ledger'];
}

function sr_asset_module_keys(): array
{
    return ['point', 'reward', 'deposit'];
}

function sr_module_foundation_dependencies(string $moduleKey): array
{
    return in_array($moduleKey, sr_asset_module_keys(), true) ? ['asset_ledger'] : [];
}

function sr_module_is_foundation(string $moduleKey): bool
{
    return in_array($moduleKey, sr_foundation_module_keys(), true);
}

function sr_enabled_asset_modules_requiring_foundation(PDO $pdo, string $foundationModuleKey): array
{
    if ($foundationModuleKey !== 'asset_ledger') {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count(sr_asset_module_keys()), '?'));
    $stmt = $pdo->prepare(
        "SELECT module_key FROM sr_modules
         WHERE module_key IN (" . $placeholders . ")
           AND status = 'enabled'
         ORDER BY module_key ASC"
    );
    $stmt->execute(sr_asset_module_keys());

    $moduleKeys = [];
    foreach ($stmt->fetchAll() as $row) {
        $moduleKey = (string) ($row['module_key'] ?? '');
        if (sr_is_safe_module_key($moduleKey)) {
            $moduleKeys[] = $moduleKey;
        }
    }

    return $moduleKeys;
}

function sr_module_disable_errors(PDO $pdo, string $moduleKey): array
{
    if (!sr_module_is_foundation($moduleKey)) {
        return [];
    }

    $dependentModules = sr_enabled_asset_modules_requiring_foundation($pdo, $moduleKey);
    if ($dependentModules === []) {
        return [];
    }

    return [
        $moduleKey . ' 기반 모듈은 활성 자산 모듈(' . implode(', ', $dependentModules) . ')이 사용하는 동안 비활성화할 수 없습니다.',
    ];
}

function sr_install_module(PDO $pdo, string $moduleKey, string $status, bool $isBundled = false): array
{
    if (!sr_is_safe_module_key($moduleKey)) {
        throw new InvalidArgumentException('Module key is invalid.');
    }

    $moduleDir = SR_ROOT . '/modules/' . $moduleKey;
    $realModulesDir = realpath(SR_ROOT . '/modules');
    $realModuleDir = realpath($moduleDir);
    $installSql = $moduleDir . '/install.sql';
    $metadata = sr_module_metadata($moduleKey);

    if ($realModulesDir === false || $realModuleDir === false || strpos($realModuleDir, $realModulesDir . DIRECTORY_SEPARATOR) !== 0) {
        throw new RuntimeException('설치할 모듈 디렉터리를 찾을 수 없습니다.');
    }

    if ($metadata === []) {
        throw new RuntimeException('모듈 메타데이터를 찾을 수 없습니다.');
    }

    if (!is_file($installSql)) {
        throw new RuntimeException('모듈 설치 SQL 파일을 찾을 수 없습니다.');
    }

    $metadataErrors = array_merge(
        sr_module_metadata_errors($metadata),
        sr_module_contract_file_errors($moduleDir, $metadata),
        sr_module_requirement_errors($pdo, $moduleKey, $metadata, $status)
    );
    if ($status === 'enabled') {
        $metadataErrors = array_merge($metadataErrors, sr_module_route_conflict_errors($pdo, $moduleKey));
    }
    if ($metadataErrors !== []) {
        throw new RuntimeException(implode(' ', array_values(array_unique($metadataErrors))));
    }

    $stmt = $pdo->prepare('SELECT id, status FROM sr_modules WHERE module_key = :module_key LIMIT 1');
    $stmt->execute(['module_key' => $moduleKey]);
    $existingModule = $stmt->fetch();
    if (is_array($existingModule) && !in_array((string) $existingModule['status'], ['failed', 'installing'], true)) {
        throw new RuntimeException('이미 설치된 모듈입니다.');
    }

    $now = sr_now();
    $moduleName = is_string($metadata['name'] ?? null) && (string) $metadata['name'] !== ''
        ? (string) $metadata['name']
        : $moduleKey;
    $moduleVersion = is_string($metadata['version'] ?? null) && (string) $metadata['version'] !== ''
        ? (string) $metadata['version']
        : '2026.04.001';

    try {
        if (is_array($existingModule)) {
            $stmt = $pdo->prepare(
                "UPDATE sr_modules
                 SET name = :name, version = :version, status = 'installing', updated_at = :updated_at
                 WHERE module_key = :module_key"
            );
            $stmt->execute([
                'name' => $moduleName,
                'version' => $moduleVersion,
                'updated_at' => $now,
                'module_key' => $moduleKey,
            ]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO sr_modules (module_key, name, version, status, is_bundled, installed_at, updated_at)
                 VALUES (:module_key, :name, :version, 'installing', :is_bundled, :installed_at, :updated_at)"
            );
            $stmt->execute([
                'module_key' => $moduleKey,
                'name' => $moduleName,
                'version' => $moduleVersion,
                'is_bundled' => $isBundled ? 1 : 0,
                'installed_at' => $now,
                'updated_at' => $now,
            ]);
        }

        sr_execute_sql_file($pdo, $installSql);
        sr_record_installed_module_schema_versions($pdo, $moduleKey, $moduleVersion);

        $completedAt = sr_now();
        $stmt = $pdo->prepare(
            'UPDATE sr_modules
             SET status = :status, installed_at = :installed_at, updated_at = :updated_at
             WHERE module_key = :module_key'
        );
        $stmt->execute([
            'status' => $status,
            'installed_at' => $completedAt,
            'updated_at' => $completedAt,
            'module_key' => $moduleKey,
        ]);

        if ($moduleKey === 'seo') {
            require_once SR_ROOT . '/modules/seo/helpers.php';
            sr_seo_install_default_title_suffix($pdo);
        }
    } catch (Throwable $exception) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE sr_modules
                 SET status = 'failed', updated_at = :updated_at
                 WHERE module_key = :module_key AND status = 'installing'"
            );
            $stmt->execute([
                'updated_at' => sr_now(),
                'module_key' => $moduleKey,
            ]);
        } catch (Throwable $ignored) {
        }

        throw $exception;
    }

    return [
        'module_key' => $moduleKey,
        'name' => $moduleName,
        'version' => $moduleVersion,
        'status' => $status,
    ];
}

function sr_update_module_status(PDO $pdo, string $moduleKey, string $status): array
{
    if (!sr_is_safe_module_key($moduleKey)) {
        throw new InvalidArgumentException('Module key is invalid.');
    }

    $stmt = $pdo->prepare('SELECT id, status FROM sr_modules WHERE module_key = :module_key LIMIT 1');
    $stmt->execute(['module_key' => $moduleKey]);
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('모듈을 찾을 수 없습니다.');
    }

    $beforeStatus = (string) $module['status'];
    if ($status === 'enabled') {
        $metadata = sr_module_metadata($moduleKey);
        $moduleDir = SR_ROOT . '/modules/' . $moduleKey;
        $metadataErrors = $metadata === []
            ? ['모듈 메타데이터를 찾을 수 없습니다.']
            : array_merge(
                sr_module_metadata_errors($metadata),
                sr_module_contract_file_errors($moduleDir, $metadata),
                sr_module_requirement_errors($pdo, $moduleKey, $metadata, 'enabled'),
                sr_module_route_conflict_errors($pdo, $moduleKey)
            );
        if ($metadataErrors !== []) {
            throw new RuntimeException(implode(' ', array_values(array_unique($metadataErrors))));
        }
    } else {
        $disableErrors = sr_module_disable_errors($pdo, $moduleKey);
        if ($disableErrors !== []) {
            throw new RuntimeException(implode(' ', $disableErrors));
        }
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_modules
         SET status = :status, updated_at = :updated_at
         WHERE module_key = :module_key'
    );
    $stmt->execute([
        'status' => $status,
        'updated_at' => sr_now(),
        'module_key' => $moduleKey,
    ]);

    return [
        'module_key' => $moduleKey,
        'before_status' => $beforeStatus,
        'after_status' => $status,
    ];
}
