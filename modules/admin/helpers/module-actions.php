<?php

declare(strict_types=1);

function sr_admin_handle_modules_post(
    PDO $pdo,
    array $account,
    bool $canManageModuleSources,
    array $requiredModules,
    array $allowedStatuses,
    array $allowedInstallStatuses,
    bool $moduleUploadAvailable,
    bool $moduleSourcesEnabled
): array {
    $errors = [];
    $notice = '';
    $intent = sr_post_string('intent', 40);
    $moduleKey = sr_post_string('module_key', 60);
    $status = '';
    $module = null;
    $metadata = [];
    $existingModule = null;
    $installSql = '';

    if ($intent === 'upload_module_zip') {
        $moduleKey = trim(sr_post_string('upload_module_key', 60));
    }

    if (!in_array($intent, ['upload_module_zip', 'sync_module_version', 'status', 'install'], true)) {
        $errors[] = '지원하지 않는 모듈 관리 요청입니다.';
    }

    if ($intent !== 'upload_module_zip' && !sr_is_safe_module_key($moduleKey)) {
        $errors[] = '모듈 키가 올바르지 않습니다.';
    }

    $sourceWriteIntents = ['upload_module_zip', 'sync_module_version'];

    if ($intent === 'upload_module_zip') {
        if (!$canManageModuleSources) {
            $errors[] = '모듈 소스 반영은 소유자 권한이 필요합니다.';
        }

        if (!$moduleSourcesEnabled) {
            $errors[] = '현재 환경에서는 모듈 소스 반영 기능이 비활성화되어 있습니다. 소유자가 admin.module_sources_enabled 설정을 참/거짓 유형의 참 값으로 저장해야 합니다.';
        }

        if (!$moduleUploadAvailable) {
            $errors[] = 'PHP ZipArchive 확장이 없어 모듈 zip을 처리할 수 없습니다.';
        }
    } elseif ($intent === 'sync_module_version') {
        if (!$canManageModuleSources) {
            $errors[] = '파일 전용 업데이트 반영은 소유자 권한이 필요합니다.';
        }

        if (!$moduleSourcesEnabled) {
            $errors[] = '현재 환경에서는 모듈 소스 반영 기능이 비활성화되어 있습니다. 소유자가 admin.module_sources_enabled 설정을 참/거짓 유형의 참 값으로 저장해야 합니다.';
        }
    } elseif ($intent === 'status') {
        $status = sr_post_string('status', 30);
        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = '모듈 상태 값이 올바르지 않습니다.';
        }

        if (in_array($moduleKey, $requiredModules, true) && $status !== 'enabled') {
            $errors[] = '기본 모듈은 비활성화할 수 없습니다.';
        }
    } elseif ($intent === 'install') {
        $status = sr_post_string('status', 30);
        if (!in_array($status, $allowedInstallStatuses, true)) {
            $errors[] = '설치 후 상태 값이 올바르지 않습니다.';
        }
    }

    if ($errors === [] && in_array($intent, $sourceWriteIntents, true)) {
        foreach (sr_admin_module_source_reauth_errors($pdo, $account, $intent) as $reauthError) {
            $errors[] = $reauthError;
        }
    }

    if ($errors === [] && $intent === 'install') {
        $moduleDir = SR_ROOT . '/modules/' . $moduleKey;
        $realModulesDir = realpath(SR_ROOT . '/modules');
        $realModuleDir = realpath($moduleDir);
        $installSql = $moduleDir . '/install.sql';
        $metadata = sr_module_metadata($moduleKey);

        if ($realModulesDir === false || $realModuleDir === false || strpos($realModuleDir, $realModulesDir . DIRECTORY_SEPARATOR) !== 0) {
            $errors[] = '설치할 모듈 디렉터리를 찾을 수 없습니다.';
        }

        if ($errors === [] && $metadata === []) {
            $errors[] = '모듈 메타데이터를 찾을 수 없습니다.';
        }

        if ($errors === [] && !is_file($installSql)) {
            $errors[] = '모듈 설치 SQL 파일을 찾을 수 없습니다.';
        }

        if ($errors === []) {
            foreach (sr_admin_module_metadata_errors($metadata) as $metadataError) {
                $errors[] = $metadataError;
            }

            foreach (sr_module_contract_file_errors($moduleDir, $metadata) as $metadataError) {
                $errors[] = $metadataError;
            }
        }

        if ($errors === []) {
            foreach (sr_module_requirement_errors($pdo, $moduleKey, $metadata, $status) as $requirementError) {
                $errors[] = $requirementError;
            }
        }

        if ($errors === [] && $status === 'enabled') {
            foreach (sr_admin_module_route_conflict_errors($pdo, $moduleKey) as $routeError) {
                $errors[] = $routeError;
            }
        }

        if ($errors === []) {
            $stmt = $pdo->prepare('SELECT id, status FROM sr_modules WHERE module_key = :module_key LIMIT 1');
            $stmt->execute(['module_key' => $moduleKey]);
            $existingModule = $stmt->fetch();
            if (is_array($existingModule) && !in_array((string) $existingModule['status'], ['failed', 'installing'], true)) {
                $errors[] = '이미 설치된 모듈입니다.';
            }
        }
    } elseif ($errors === [] && in_array($intent, ['status', 'sync_module_version'], true)) {
        $stmt = $pdo->prepare('SELECT id, status FROM sr_modules WHERE module_key = :module_key LIMIT 1');
        $stmt->execute(['module_key' => $moduleKey]);
        $module = $stmt->fetch();

        if (!is_array($module)) {
            $errors[] = '모듈을 찾을 수 없습니다.';
        }

        if ($errors === [] && $intent === 'status' && in_array((string) $module['status'], ['failed', 'installing'], true)) {
            $errors[] = '설치가 완료되지 않은 모듈은 재설치를 먼저 실행하세요.';
        }

        if ($errors === [] && $intent === 'status' && $status === 'enabled') {
            $metadata = sr_module_metadata($moduleKey);
            if ($metadata === []) {
                $errors[] = '모듈 메타데이터를 찾을 수 없습니다.';
            }

            if ($errors === []) {
                foreach (sr_admin_module_metadata_errors($metadata) as $metadataError) {
                    $errors[] = $metadataError;
                }

                foreach (sr_module_contract_file_errors(SR_ROOT . '/modules/' . $moduleKey, $metadata) as $metadataError) {
                    $errors[] = $metadataError;
                }
            }

            if ($errors === []) {
                foreach (sr_module_requirement_errors($pdo, $moduleKey, $metadata, $status) as $requirementError) {
                    $errors[] = $requirementError;
                }
            }

            if ($errors === [] && $status === 'enabled') {
                foreach (sr_admin_module_code_older_errors($pdo, $moduleKey) as $versionError) {
                    $errors[] = $versionError;
                }
            }

            if ($errors === []) {
                foreach (sr_admin_module_route_conflict_errors($pdo, $moduleKey) as $routeError) {
                    $errors[] = $routeError;
                }
            }
        }

        if ($errors === [] && $intent === 'sync_module_version') {
            $metadata = sr_module_metadata($moduleKey);
            if ($metadata === []) {
                $errors[] = '모듈 메타데이터를 찾을 수 없습니다.';
            }

            if ($errors === []) {
                foreach (sr_admin_module_metadata_errors($metadata) as $metadataError) {
                    $errors[] = $metadataError;
                }

                foreach (sr_module_contract_file_errors(SR_ROOT . '/modules/' . $moduleKey, $metadata) as $metadataError) {
                    $errors[] = $metadataError;
                }
            }

            if ($errors === []) {
                $codeVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
                $pendingCounts = sr_admin_module_pending_update_counts(sr_admin_pending_updates($pdo));
                foreach (sr_module_requirement_errors($pdo, $moduleKey, $metadata, (string) ($module['status'] ?? 'enabled')) as $requirementError) {
                    $errors[] = $requirementError;
                }

                if ($errors === [] && (int) ($pendingCounts[$moduleKey] ?? 0) > 0) {
                    $errors[] = '미적용 SQL이 있는 모듈은 업데이트 화면에서 먼저 DB 업데이트를 실행하세요.';
                } elseif ($errors === [] && strcmp($codeVersion, (string) $module['version']) <= 0) {
                    $errors[] = '설치 버전에 반영할 새 코드 버전이 없습니다.';
                }
            }
        }
    }

    if ($errors === [] && $intent === 'upload_module_zip') {
        $extractDir = '';
        $sourceType = 'upload';
        try {
            $upload = $_FILES['module_zip'] ?? null;
            if (!is_array($upload)) {
                throw new RuntimeException('업로드할 zip 파일을 선택하세요.');
            }

            $source = sr_admin_extract_module_upload($upload, $moduleKey);
            $extractDir = (string) ($source['extract_dir'] ?? '');
            $uploadStats = is_array($source['upload'] ?? null) ? $source['upload'] : [];
            $moduleKey = (string) $source['module_key'];
            $metadata = is_array($source['metadata']) ? $source['metadata'] : [];
            $moduleVersion = (string) ($metadata['version'] ?? '');
            $replaceConfirmed = ($_POST['confirm_file_replace'] ?? '') === '1';
            foreach (sr_admin_module_replace_errors($moduleKey, $replaceConfirmed) as $replaceError) {
                throw new RuntimeException($replaceError);
            }

            $allowDowngrade = ($_POST['allow_downgrade'] ?? '') === '1';
            foreach (sr_admin_module_upload_version_errors($pdo, $moduleKey, $metadata, $allowDowngrade) as $versionError) {
                throw new RuntimeException($versionError);
            }

            $result = sr_admin_install_module_source_files($moduleKey, (string) $source['source_dir']);

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'module.source.uploaded',
                'target_type' => 'module',
                'target_id' => $moduleKey,
                'result' => 'success',
                'message' => 'Module source zip uploaded.',
                'metadata' => [
                    'version' => $moduleVersion,
                    'source' => $sourceType,
                    'replace_confirmed' => $replaceConfirmed,
                    'allow_downgrade' => $allowDowngrade,
                    'upload_filename' => (string) ($uploadStats['filename'] ?? ''),
                    'upload_size' => (int) ($uploadStats['size'] ?? 0),
                    'upload_checksum' => (string) ($uploadStats['checksum'] ?? ''),
                    'zip_entry_count' => (int) ($uploadStats['entry_count'] ?? 0),
                    'zip_uncompressed_bytes' => (int) ($uploadStats['uncompressed_bytes'] ?? 0),
                    'backup_dir' => str_replace(SR_ROOT . '/', '', (string) ($result['backup_dir'] ?? '')),
                ],
            ]);

            $notice = $moduleKey . ' 모듈 파일을 반영했습니다. 새 모듈이면 아래 목록에서 설치하고, 기존 모듈이면 업데이트 화면에서 미적용 SQL을 확인하세요.';
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'module_source_upload_failed');
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'module.source.uploaded',
                'target_type' => 'module',
                'target_id' => $moduleKey,
                'result' => 'failure',
                'message' => 'Module source zip upload failed.',
                'metadata' => [
                    'source' => $sourceType,
                    'reason' => sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500)),
                ],
            ]);
            $errors[] = sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500));
        } finally {
            if ($extractDir !== '') {
                try {
                    sr_admin_remove_directory($extractDir);
                } catch (Throwable $ignored) {
                }
            }
        }
    } elseif ($errors === [] && $intent === 'install') {
        try {
            $now = sr_now();
            $moduleName = is_string($metadata['name'] ?? null) && (string) $metadata['name'] !== ''
                ? (string) $metadata['name']
                : $moduleKey;
            $moduleVersion = is_string($metadata['version'] ?? null) && (string) $metadata['version'] !== ''
                ? (string) $metadata['version']
                : '2026.04.001';

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
                    'is_bundled' => 0,
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

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'module.installed',
                'target_type' => 'module',
                'target_id' => $moduleKey,
                'result' => 'success',
                'message' => 'Module installed.',
                'metadata' => [
                    'status' => $status,
                    'version' => $moduleVersion,
                ],
            ]);

            $notice = '모듈을 설치했습니다.';
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

            sr_log_exception($exception, 'module_install_failed');
            $errors[] = '모듈 설치 중 오류가 발생했습니다.';
        }
    } elseif ($errors === [] && $intent === 'sync_module_version') {
        $metadata = sr_module_metadata($moduleKey);
        $codeVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
        $beforeVersion = (string) $module['version'];
        sr_admin_sync_module_version($pdo, $moduleKey, $codeVersion);

        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'module.version.synced',
            'target_type' => 'module',
            'target_id' => $moduleKey,
            'result' => 'success',
            'message' => 'Module installed version synced to code version.',
            'metadata' => [
                'before_version' => $beforeVersion,
                'after_version' => $codeVersion,
            ],
        ]);

        $notice = '파일 전용 업데이트 버전을 반영했습니다.';
    } elseif ($errors === [] && $intent === 'status') {
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

        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'module.status.updated',
            'target_type' => 'module',
            'target_id' => $moduleKey,
            'result' => 'success',
            'message' => 'Module status updated.',
            'metadata' => [
                'before_status' => (string) $module['status'],
                'after_status' => $status,
            ],
        ]);

        $notice = '모듈 상태를 저장했습니다.';
    } elseif ($errors === []) {
        $errors[] = '요청한 작업을 처리할 수 없습니다.';
    }

    return sr_admin_action_result($errors, $notice);
}

function sr_admin_module_route_conflict_errors(PDO $pdo, string $candidateModuleKey): array
{
    if (!sr_is_safe_module_key($candidateModuleKey)) {
        return ['모듈 키가 올바르지 않습니다.'];
    }

    $candidateRoutes = sr_admin_module_route_map($candidateModuleKey);
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

                $errors[] = $candidateModuleKey . ' 모듈 route가 ' . $moduleKey . ' 모듈과 충돌합니다: ' . (string) $candidateRoute . ' / ' . $route;
            }
        }
    }

    return array_values(array_unique($errors));
}

function sr_admin_module_route_map(string $moduleKey): array
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
            $errors[] = $moduleKey . ' 모듈 route 형식이 올바르지 않습니다: ' . $route;
            continue;
        }

        if (!sr_is_safe_module_action($actionRelativePath)) {
            $errors[] = $moduleKey . ' 모듈 action 경로가 올바르지 않습니다: ' . $route;
            continue;
        }

        if (!is_file($moduleDir . '/' . $actionRelativePath)) {
            $errors[] = $moduleKey . ' 모듈 action 파일을 찾을 수 없습니다: ' . $route;
            continue;
        }

        $routes[$route] = $actionRelativePath;
    }

    return ['routes' => $routes, 'errors' => array_values(array_unique($errors))];
}

function sr_admin_module_source_reauth_errors(PDO $pdo, array $account, string $intent): array
{
    $password = sr_post_string('owner_password', 255);
    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId < 1) {
        return ['소유자 재인증 계정을 확인할 수 없습니다.'];
    }

    $throttle = sr_member_reauth_throttle_status($pdo, $accountId);
    if (!empty($throttle['limited'])) {
        sr_member_log_auth($pdo, $accountId, 'reauth_blocked', 'failure');
        sr_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'admin',
            'event_type' => 'module.source.reauth_blocked',
            'target_type' => 'module_source',
            'target_id' => $intent,
            'result' => 'failure',
            'message' => 'Module source write reauthentication blocked by throttle.',
        ]);
        return ['재인증 시도가 많습니다. 잠시 후 다시 시도하세요.'];
    }

    if ($password === '' || !password_verify($password, (string) ($account['password_hash'] ?? ''))) {
        sr_member_log_auth($pdo, $accountId, 'module_source_reauth', 'failure');
        sr_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'admin',
            'event_type' => 'module.source.reauth_failed',
            'target_type' => 'module_source',
            'target_id' => $intent,
            'result' => 'failure',
            'message' => 'Module source write reauthentication failed.',
        ]);
        return ['모듈 소스 반영 전 소유자 비밀번호를 다시 입력하세요.'];
    }

    sr_member_log_auth($pdo, $accountId, 'module_source_reauth', 'success');
    return [];
}

function sr_admin_module_code_older_errors(PDO $pdo, string $moduleKey): array
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
        return ['모듈 코드 버전이 설치 버전보다 낮습니다. 파일을 현재 설치 버전 이상으로 다시 배치한 뒤 활성화하세요.'];
    }

    return [];
}

function sr_admin_module_lifecycle_state(array $module): array
{
    $status = (string) ($module['status'] ?? '');
    $metadataErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : [];
    $pendingUpdateCount = (int) ($module['pending_update_count'] ?? 0);
    $versionState = (string) ($module['version_state'] ?? 'unknown');

    if (in_array($status, ['failed', 'installing'], true)) {
        return [
            'state' => 'install_incomplete',
            'label' => '설치 미완료',
            'action' => '재설치 필요',
        ];
    }

    if ($metadataErrors !== []) {
        return [
            'state' => 'contract_error',
            'label' => '계약 오류',
            'action' => '모듈 파일 확인',
        ];
    }

    if ($versionState === 'code_older') {
        return [
            'state' => 'code_older',
            'label' => '코드 버전 낮음',
            'action' => '파일 재배치 필요',
        ];
    }

    if ($pendingUpdateCount > 0) {
        return [
            'state' => 'sql_pending',
            'label' => 'SQL 적용 필요',
            'action' => '/admin/updates에서 업데이트',
        ];
    }

    if ($versionState === 'code_newer') {
        return [
            'state' => 'file_only_update',
            'label' => '파일 전용 업데이트 가능',
            'action' => '설치 버전 반영',
        ];
    }

    if ($status === 'enabled') {
        return [
            'state' => 'enabled_current',
            'label' => '활성 최신',
            'action' => '-',
        ];
    }

    if ($status === 'disabled') {
        return [
            'state' => 'disabled_current',
            'label' => '비활성 최신',
            'action' => '활성화 가능',
        ];
    }

    return [
        'state' => 'unknown',
        'label' => '상태 확인 필요',
        'action' => '모듈 상태 확인',
    ];
}

function sr_admin_load_module_management_view_data(PDO $pdo): array
{
    $modules = [];
    $pendingUpdateCounts = sr_admin_module_pending_update_counts(sr_admin_pending_updates($pdo));
    $stmt = $pdo->query('SELECT id, module_key, name, version, status, is_bundled, installed_at, updated_at FROM sr_modules ORDER BY id ASC');
    $installedModuleKeys = [];
    foreach ($stmt->fetchAll() as $row) {
        $installedModuleKeys[(string) $row['module_key']] = true;
        $metadata = sr_module_metadata((string) $row['module_key']);
        $moduleDirectory = SR_ROOT . '/modules/' . (string) $row['module_key'];
        $metadataErrors = $metadata === [] ? ['module.php 파일을 읽을 수 없습니다.'] : array_merge(
            sr_module_metadata_errors($metadata),
            sr_module_contract_file_errors($moduleDirectory, $metadata)
        );
        $row['code_name'] = is_string($metadata['name'] ?? null) ? (string) $metadata['name'] : '';
        $row['code_version'] = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
        $row['code_type'] = sr_module_type((string) $row['module_key']);
        $row['description'] = is_string($metadata['description'] ?? null) ? (string) $metadata['description'] : '';
        $saanraanMetadata = is_array($metadata['saanraan'] ?? null) ? $metadata['saanraan'] : [];
        $saanraanTestedWith = $saanraanMetadata['tested_with'] ?? [];
        $row['saanraan_min_version'] = is_string($saanraanMetadata['min_version'] ?? null) ? (string) $saanraanMetadata['min_version'] : '';
        $row['saanraan_tested_with'] = is_array($saanraanTestedWith)
            ? implode(', ', array_map('strval', $saanraanTestedWith))
            : (is_string($saanraanTestedWith) ? $saanraanTestedWith : '');
        $row['saanraan_module_contract'] = is_string($saanraanMetadata['module_contract'] ?? null) ? (string) $saanraanMetadata['module_contract'] : '';
        $row['metadata_errors'] = $metadataErrors;
        $row['pending_update_count'] = (int) ($pendingUpdateCounts[(string) $row['module_key']] ?? 0);
        $row['version_state'] = 'unknown';
        if ((string) $row['code_version'] !== '' && (string) $row['version'] !== '') {
            $comparison = strcmp((string) $row['code_version'], (string) $row['version']);
            if ($comparison > 0) {
                $row['version_state'] = 'code_newer';
            } elseif ($comparison < 0) {
                $row['version_state'] = 'code_older';
            } else {
                $row['version_state'] = 'same';
            }
        }
        $lifecycle = sr_admin_module_lifecycle_state($row);
        $row['lifecycle_state'] = (string) $lifecycle['state'];
        $row['lifecycle_label'] = (string) $lifecycle['label'];
        $row['lifecycle_action'] = (string) $lifecycle['action'];
        $modules[] = $row;
    }

    $installableModules = [];
    $moduleDirectories = glob(SR_ROOT . '/modules/*', GLOB_ONLYDIR);
    if (is_array($moduleDirectories)) {
        sort($moduleDirectories, SORT_STRING);
        foreach ($moduleDirectories as $moduleDirectory) {
            $moduleKey = basename($moduleDirectory);
            if (!sr_is_safe_module_key($moduleKey) || isset($installedModuleKeys[$moduleKey])) {
                continue;
            }

            $metadata = sr_module_metadata($moduleKey);
            if ($metadata === [] && !is_file($moduleDirectory . '/module.php')) {
                continue;
            }
            $missingInstallSql = !is_file($moduleDirectory . '/install.sql');
            $saanraanMetadata = is_array($metadata['saanraan'] ?? null) ? $metadata['saanraan'] : [];
            $saanraanTestedWith = $saanraanMetadata['tested_with'] ?? [];
            $metadataErrors = $metadata === []
                ? ['module.php 파일을 읽을 수 없습니다.']
                : array_merge(
                    sr_module_metadata_errors($metadata),
                    sr_module_contract_file_errors($moduleDirectory, $metadata)
                );
            if ($missingInstallSql) {
                $metadataErrors[] = 'install.sql 파일이 필요합니다.';
            }

            $installableModules[] = [
                'module_key' => $moduleKey,
                'name' => is_string($metadata['name'] ?? null) ? (string) $metadata['name'] : $moduleKey,
                'version' => is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '',
                'type' => sr_module_type($moduleKey),
                'description' => is_string($metadata['description'] ?? null) ? (string) $metadata['description'] : '',
                'saanraan_min_version' => is_string($saanraanMetadata['min_version'] ?? null) ? (string) $saanraanMetadata['min_version'] : '',
                'saanraan_tested_with' => is_array($saanraanTestedWith)
                    ? implode(', ', array_map('strval', $saanraanTestedWith))
                    : (is_string($saanraanTestedWith) ? $saanraanTestedWith : ''),
                'saanraan_module_contract' => is_string($saanraanMetadata['module_contract'] ?? null) ? (string) $saanraanMetadata['module_contract'] : '',
                'metadata_errors' => $metadataErrors,
                'lifecycle_state' => $metadataErrors === [] ? 'not_installed' : 'install_blocked',
                'lifecycle_label' => $metadataErrors === [] ? '미설치' : '설치 차단',
                'lifecycle_action' => $metadataErrors === [] ? '설치 가능' : '모듈 파일 확인',
            ];
        }
    }

    return [
        'modules' => $modules,
        'installable_modules' => $installableModules,
    ];
}
