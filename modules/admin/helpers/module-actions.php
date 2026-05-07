<?php

declare(strict_types=1);

function toy_admin_handle_modules_post(
    PDO $pdo,
    array $account,
    bool $canManageAdvancedModuleSettings,
    bool $canManageModuleSources,
    array $requiredModules,
    array $allowedStatuses,
    array $allowedSettingTypes,
    array $allowedInstallStatuses,
    bool $moduleUploadAvailable,
    bool $moduleSourcesEnabled
): array {
    $errors = [];
    $notice = '';
    $intent = toy_post_string('intent', 40);
    $moduleKey = toy_post_string('module_key', 60);
    $status = '';
    $module = null;
    $metadata = [];
    $existingModule = null;
    $installSql = '';

    if ($intent === 'upload_module_zip') {
        $moduleKey = trim(toy_post_string('upload_module_key', 60));
    }

    if ($intent !== 'upload_module_zip' && !toy_is_safe_module_key($moduleKey)) {
        $errors[] = '모듈 키가 올바르지 않습니다.';
    }

    $sourceWriteIntents = ['upload_module_zip', 'download_registry_module', 'download_repository_archive', 'sync_module_version'];

    if (in_array($intent, ['upload_module_zip', 'download_registry_module', 'download_repository_archive'], true)) {
        if (!$canManageModuleSources) {
            $errors[] = '모듈 소스 반영은 owner 권한이 필요합니다.';
        }

        if (!$moduleSourcesEnabled) {
            $errors[] = '현재 환경에서는 모듈 소스 반영 기능이 비활성화되어 있습니다. owner가 admin.module_sources_enabled 설정을 bool true로 저장해야 합니다.';
        }

        if (!$moduleUploadAvailable) {
            $errors[] = 'PHP ZipArchive 확장이 없어 모듈 zip을 처리할 수 없습니다.';
        }
    } elseif ($intent === 'sync_module_version') {
        if (!$canManageModuleSources) {
            $errors[] = '파일 전용 업데이트 반영은 owner 권한이 필요합니다.';
        }

        if (!$moduleSourcesEnabled) {
            $errors[] = '현재 환경에서는 모듈 소스 반영 기능이 비활성화되어 있습니다. owner가 admin.module_sources_enabled 설정을 bool true로 저장해야 합니다.';
        }
    } elseif ($intent === 'status') {
        $status = toy_post_string('status', 30);
        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = '모듈 상태 값이 올바르지 않습니다.';
        }

        if (in_array($moduleKey, $requiredModules, true) && $status !== 'enabled') {
            $errors[] = '기본 모듈은 비활성화할 수 없습니다.';
        }
    } elseif ($intent === 'install') {
        $status = toy_post_string('status', 30);
        if (!in_array($status, $allowedInstallStatuses, true)) {
            $errors[] = '설치 후 상태 값이 올바르지 않습니다.';
        }
    }

    if ($errors === [] && in_array($intent, $sourceWriteIntents, true)) {
        foreach (toy_admin_module_source_reauth_errors($pdo, $account, $intent) as $reauthError) {
            $errors[] = $reauthError;
        }
    }

    if ($errors === [] && $intent === 'install') {
        $moduleDir = TOY_ROOT . '/modules/' . $moduleKey;
        $realModulesDir = realpath(TOY_ROOT . '/modules');
        $realModuleDir = realpath($moduleDir);
        $installSql = $moduleDir . '/install.sql';
        $metadata = toy_module_metadata($moduleKey);

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
            foreach (toy_module_requirement_errors($pdo, $moduleKey, $metadata, $status) as $requirementError) {
                $errors[] = $requirementError;
            }
        }

        if ($errors === []) {
            $stmt = $pdo->prepare('SELECT id, status FROM toy_modules WHERE module_key = :module_key LIMIT 1');
            $stmt->execute(['module_key' => $moduleKey]);
            $existingModule = $stmt->fetch();
            if (is_array($existingModule) && !in_array((string) $existingModule['status'], ['failed', 'installing'], true)) {
                $errors[] = '이미 설치된 모듈입니다.';
            }
        }
    } elseif ($errors === [] && in_array($intent, ['status', 'module_setting', 'delete_module_setting', 'sync_module_version'], true)) {
        $stmt = $pdo->prepare('SELECT id, status FROM toy_modules WHERE module_key = :module_key LIMIT 1');
        $stmt->execute(['module_key' => $moduleKey]);
        $module = $stmt->fetch();

        if (!is_array($module)) {
            $errors[] = '모듈을 찾을 수 없습니다.';
        }

        if ($errors === [] && $intent === 'status' && in_array((string) $module['status'], ['failed', 'installing'], true)) {
            $errors[] = '설치가 완료되지 않은 모듈은 재설치를 먼저 실행하세요.';
        }

        if ($errors === [] && $intent === 'status' && $status === 'enabled') {
            $metadata = toy_module_metadata($moduleKey);
            foreach (toy_module_requirement_errors($pdo, $moduleKey, $metadata, $status) as $requirementError) {
                $errors[] = $requirementError;
            }
        }

        if ($errors === [] && $intent === 'sync_module_version') {
            $metadata = toy_module_metadata($moduleKey);
            $codeVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
            $pendingCounts = toy_admin_module_pending_update_counts(toy_admin_pending_updates($pdo));
            if (preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $codeVersion) !== 1) {
                $errors[] = '코드 버전 형식이 올바르지 않습니다.';
            } elseif ((int) ($pendingCounts[$moduleKey] ?? 0) > 0) {
                $errors[] = '미적용 SQL이 있는 모듈은 업데이트 화면에서 먼저 DB 업데이트를 실행하세요.';
            } elseif (strcmp($codeVersion, (string) $module['version']) <= 0) {
                $errors[] = '설치 버전에 반영할 새 코드 버전이 없습니다.';
            }
        }
    }

    if ($errors === [] && in_array($intent, ['upload_module_zip', 'download_registry_module', 'download_repository_archive'], true)) {
        $extractDir = '';
        $downloadedZip = '';
        $sourceType = $intent === 'download_repository_archive' ? 'repository' : ($intent === 'download_registry_module' ? 'registry' : 'upload');
        $repositoryRef = '';
        try {
            if ($intent === 'download_registry_module' || $intent === 'download_repository_archive') {
                $registryEntry = toy_admin_module_registry_entry($moduleKey);
                if (!is_array($registryEntry)) {
                    throw new RuntimeException('registry에서 모듈을 찾을 수 없습니다.');
                }

                if ($intent === 'download_repository_archive') {
                    $repositoryRef = toy_post_string('repository_ref', 120);
                    if (
                        !toy_admin_repository_archive_unchecked_enabled($pdo, toy_runtime_config())
                        && toy_admin_repository_archive_expected_checksum($registryEntry, $repositoryRef) === ''
                    ) {
                        throw new RuntimeException('checksum이 registry에 등록되지 않은 repository archive는 현재 설정에서 반영할 수 없습니다.');
                    }
                    $upload = toy_admin_download_registry_repository_archive($registryEntry, $repositoryRef);
                } else {
                    $upload = toy_admin_download_registry_module_zip($registryEntry);
                }
                $downloadedZip = (string) ($upload['tmp_name'] ?? '');
            } else {
                $upload = $_FILES['module_zip'] ?? null;
                if (!is_array($upload)) {
                    throw new RuntimeException('업로드할 zip 파일을 선택하세요.');
                }
            }

            $source = toy_admin_extract_module_upload($upload, $moduleKey);
            $extractDir = (string) ($source['extract_dir'] ?? '');
            $uploadStats = is_array($source['upload'] ?? null) ? $source['upload'] : [];
            $moduleKey = (string) $source['module_key'];
            $metadata = is_array($source['metadata']) ? $source['metadata'] : [];
            $moduleVersion = (string) ($metadata['version'] ?? '');
            $replaceConfirmed = ($_POST['confirm_file_replace'] ?? '') === '1';
            foreach (toy_admin_module_replace_errors($moduleKey, $replaceConfirmed) as $replaceError) {
                throw new RuntimeException($replaceError);
            }

            $allowDowngrade = ($_POST['allow_downgrade'] ?? '') === '1';
            foreach (toy_admin_module_upload_version_errors($pdo, $moduleKey, $metadata, $allowDowngrade) as $versionError) {
                throw new RuntimeException($versionError);
            }

            $result = toy_admin_install_module_source_files($moduleKey, (string) $source['source_dir']);

            toy_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => $intent === 'upload_module_zip' ? 'module.source.uploaded' : 'module.source.downloaded',
                'target_type' => 'module',
                'target_id' => $moduleKey,
                'result' => 'success',
                'message' => $intent === 'upload_module_zip' ? 'Module source zip uploaded.' : 'Module source zip downloaded.',
                'metadata' => [
                    'version' => $moduleVersion,
                    'source' => $sourceType,
                    'replace_confirmed' => $replaceConfirmed,
                    'allow_downgrade' => $allowDowngrade,
                    'upload_filename' => (string) ($uploadStats['filename'] ?? ''),
                    'upload_size' => (int) ($uploadStats['size'] ?? 0),
                    'upload_checksum' => (string) ($uploadStats['checksum'] ?? ''),
                    'registry_zip_url' => (string) ($upload['registry_zip_url'] ?? ''),
                    'repository' => (string) ($upload['repository'] ?? ''),
                    'repository_ref' => (string) ($upload['repository_ref'] ?? ''),
                    'repository_archive_url' => (string) ($upload['repository_archive_url'] ?? ''),
                    'repository_archive_checksum' => (string) ($upload['repository_archive_checksum'] ?? ''),
                    'zip_entry_count' => (int) ($uploadStats['entry_count'] ?? 0),
                    'zip_uncompressed_bytes' => (int) ($uploadStats['uncompressed_bytes'] ?? 0),
                    'backup_dir' => str_replace(TOY_ROOT . '/', '', (string) ($result['backup_dir'] ?? '')),
                ],
            ]);

            $notice = $moduleKey . ' 모듈 파일을 반영했습니다. 새 모듈이면 아래 목록에서 설치하고, 기존 모듈이면 업데이트 화면에서 미적용 SQL을 확인하세요.';
        } catch (Throwable $exception) {
            toy_log_exception($exception, 'module_source_upload_failed');
            toy_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => $intent === 'upload_module_zip' ? 'module.source.uploaded' : 'module.source.downloaded',
                'target_type' => 'module',
                'target_id' => $moduleKey,
                'result' => 'failure',
                'message' => $intent === 'upload_module_zip' ? 'Module source zip upload failed.' : 'Module source zip download failed.',
                'metadata' => [
                    'source' => $sourceType,
                    'repository_ref' => $repositoryRef,
                    'reason' => toy_log_sensitive_text_sanitize(toy_log_line_value($exception->getMessage(), 500)),
                ],
            ]);
            $errors[] = toy_log_sensitive_text_sanitize(toy_log_line_value($exception->getMessage(), 500));
        } finally {
            if ($extractDir !== '') {
                try {
                    toy_admin_remove_directory($extractDir);
                } catch (Throwable $ignored) {
                }
            }
            if ($downloadedZip !== '' && is_file($downloadedZip)) {
                unlink($downloadedZip);
            }
        }
    } elseif ($errors === [] && $intent === 'install') {
        try {
            $now = toy_now();
            $moduleName = is_string($metadata['name'] ?? null) && (string) $metadata['name'] !== ''
                ? (string) $metadata['name']
                : $moduleKey;
            $moduleVersion = is_string($metadata['version'] ?? null) && (string) $metadata['version'] !== ''
                ? (string) $metadata['version']
                : '2026.04.001';

            if (is_array($existingModule)) {
                $stmt = $pdo->prepare(
                    "UPDATE toy_modules
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
                    "INSERT INTO toy_modules (module_key, name, version, status, is_bundled, installed_at, updated_at)
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

            toy_execute_sql_file($pdo, $installSql);

            toy_record_installed_module_schema_versions($pdo, $moduleKey, $moduleVersion);

            $completedAt = toy_now();
            $stmt = $pdo->prepare(
                'UPDATE toy_modules
                 SET status = :status, installed_at = :installed_at, updated_at = :updated_at
                 WHERE module_key = :module_key'
            );
            $stmt->execute([
                'status' => $status,
                'installed_at' => $completedAt,
                'updated_at' => $completedAt,
                'module_key' => $moduleKey,
            ]);

            toy_audit_log($pdo, [
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
                    "UPDATE toy_modules
                     SET status = 'failed', updated_at = :updated_at
                     WHERE module_key = :module_key AND status = 'installing'"
                );
                $stmt->execute([
                    'updated_at' => toy_now(),
                    'module_key' => $moduleKey,
                ]);
            } catch (Throwable $ignored) {
            }

            toy_log_exception($exception, 'module_install_failed');
            $errors[] = '모듈 설치 중 오류가 발생했습니다.';
        }
    } elseif ($errors === [] && $intent === 'module_setting') {
        if (!$canManageAdvancedModuleSettings) {
            $errors[] = '고급 모듈 설정은 owner 권한이 필요합니다.';
        }

        $settingKey = toy_post_string('setting_key', 120);
        $settingValue = toy_post_string('setting_value', 5000);
        $valueType = toy_post_string('value_type', 20);

        if (preg_match('/\A[a-z][a-z0-9_.-]{1,119}\z/', $settingKey) !== 1) {
            $errors[] = '설정 key 형식이 올바르지 않습니다.';
        }

        if (!in_array($valueType, $allowedSettingTypes, true)) {
            $errors[] = '설정 타입이 올바르지 않습니다.';
        }

        foreach (toy_admin_setting_value_type_errors($settingValue, $valueType) as $valueError) {
            $errors[] = $valueError;
        }

        if ($errors === [] && toy_admin_setting_value_is_secret($settingKey)) {
            foreach (toy_admin_module_setting_reauth_errors($pdo, $account, $moduleKey, $settingKey, 'save') as $reauthError) {
                $errors[] = $reauthError;
            }
        }

        if ($errors === []) {
            $settingValue = toy_admin_normalize_setting_value($settingValue, $valueType);
            $stmt = $pdo->prepare(
                'INSERT INTO toy_module_settings
                    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
                 VALUES
                    (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    value_type = VALUES(value_type),
                    updated_at = VALUES(updated_at)'
            );
            $stmt->execute([
                'module_id' => (int) $module['id'],
                'setting_key' => $settingKey,
                'setting_value' => $settingValue,
                'value_type' => $valueType,
                'created_at' => toy_now(),
                'updated_at' => toy_now(),
            ]);
            toy_clear_module_settings_cache($moduleKey);

            toy_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'module.setting.saved',
                'target_type' => 'module_setting',
                'target_id' => $moduleKey . ':' . $settingKey,
                'result' => 'success',
                'message' => 'Module setting saved.',
                'metadata' => [
                    'module_key' => $moduleKey,
                    'setting_key' => $settingKey,
                    'value_type' => $valueType,
                ],
            ]);

            $notice = '모듈 설정 항목을 저장했습니다.';
        }
    } elseif ($errors === [] && $intent === 'delete_module_setting') {
        if (!$canManageAdvancedModuleSettings) {
            $errors[] = '고급 모듈 설정은 owner 권한이 필요합니다.';
        }

        $settingKey = toy_post_string('setting_key', 120);
        if (preg_match('/\A[a-z][a-z0-9_.-]{1,119}\z/', $settingKey) !== 1) {
            $errors[] = '설정 key 형식이 올바르지 않습니다.';
        }

        if ($errors === [] && toy_admin_setting_value_is_secret($settingKey)) {
            foreach (toy_admin_module_setting_reauth_errors($pdo, $account, $moduleKey, $settingKey, 'delete') as $reauthError) {
                $errors[] = $reauthError;
            }
        }

        if ($errors === []) {
            $stmt = $pdo->prepare('DELETE FROM toy_module_settings WHERE module_id = :module_id AND setting_key = :setting_key');
            $stmt->execute([
                'module_id' => (int) $module['id'],
                'setting_key' => $settingKey,
            ]);
            toy_clear_module_settings_cache($moduleKey);

            toy_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'module.setting.deleted',
                'target_type' => 'module_setting',
                'target_id' => $moduleKey . ':' . $settingKey,
                'result' => 'success',
                'message' => 'Module setting deleted.',
                'metadata' => [
                    'module_key' => $moduleKey,
                    'setting_key' => $settingKey,
                ],
            ]);

            $notice = '모듈 설정 항목을 삭제했습니다.';
        }
    } elseif ($errors === [] && $intent === 'sync_module_version') {
        $metadata = toy_module_metadata($moduleKey);
        $codeVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
        $beforeVersion = (string) $module['version'];
        toy_admin_sync_module_version($pdo, $moduleKey, $codeVersion);

        toy_audit_log($pdo, [
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
            'UPDATE toy_modules
             SET status = :status, updated_at = :updated_at
             WHERE module_key = :module_key'
        );
        $stmt->execute([
            'status' => $status,
            'updated_at' => toy_now(),
            'module_key' => $moduleKey,
        ]);

        toy_audit_log($pdo, [
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

    return toy_admin_action_result($errors, $notice);
}

function toy_admin_module_setting_reauth_errors(PDO $pdo, array $account, string $moduleKey, string $settingKey, string $action): array
{
    $password = toy_post_string('owner_password', 255);
    $accountId = (int) ($account['id'] ?? 0);
    $targetId = $moduleKey . ':' . $settingKey . ':' . $action;
    if ($accountId < 1) {
        return ['owner 재인증 계정을 확인할 수 없습니다.'];
    }

    $throttle = toy_member_reauth_throttle_status($pdo, $accountId);
    if (!empty($throttle['limited'])) {
        toy_member_log_auth($pdo, $accountId, 'reauth_blocked', 'failure');
        toy_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'admin',
            'event_type' => 'module.setting.reauth_blocked',
            'target_type' => 'module_setting',
            'target_id' => $targetId,
            'result' => 'failure',
            'message' => 'Sensitive module setting reauthentication blocked by throttle.',
        ]);
        return ['재인증 시도가 많습니다. 잠시 후 다시 시도하세요.'];
    }

    if ($password === '' || !password_verify($password, (string) ($account['password_hash'] ?? ''))) {
        toy_member_log_auth($pdo, $accountId, 'module_setting_reauth', 'failure');
        toy_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'admin',
            'event_type' => 'module.setting.reauth_failed',
            'target_type' => 'module_setting',
            'target_id' => $targetId,
            'result' => 'failure',
            'message' => 'Sensitive module setting reauthentication failed.',
        ]);
        return ['민감한 모듈 설정 변경 전 owner 비밀번호를 다시 입력하세요.'];
    }

    toy_member_log_auth($pdo, $accountId, 'module_setting_reauth', 'success');
    return [];
}

function toy_admin_module_source_reauth_errors(PDO $pdo, array $account, string $intent): array
{
    $password = toy_post_string('owner_password', 255);
    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId < 1) {
        return ['owner 재인증 계정을 확인할 수 없습니다.'];
    }

    $throttle = toy_member_reauth_throttle_status($pdo, $accountId);
    if (!empty($throttle['limited'])) {
        toy_member_log_auth($pdo, $accountId, 'reauth_blocked', 'failure');
        toy_audit_log($pdo, [
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
        toy_member_log_auth($pdo, $accountId, 'module_source_reauth', 'failure');
        toy_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'admin',
            'event_type' => 'module.source.reauth_failed',
            'target_type' => 'module_source',
            'target_id' => $intent,
            'result' => 'failure',
            'message' => 'Module source write reauthentication failed.',
        ]);
        return ['모듈 소스 반영 전 owner 비밀번호를 다시 입력하세요.'];
    }

    toy_member_log_auth($pdo, $accountId, 'module_source_reauth', 'success');
    return [];
}

function toy_admin_load_module_management_view_data(PDO $pdo): array
{
    $modules = [];
    $pendingUpdateCounts = toy_admin_module_pending_update_counts(toy_admin_pending_updates($pdo));
    $stmt = $pdo->query('SELECT id, module_key, name, version, status, is_bundled, installed_at, updated_at FROM toy_modules ORDER BY id ASC');
    $installedModuleKeys = [];
    foreach ($stmt->fetchAll() as $row) {
        $installedModuleKeys[(string) $row['module_key']] = true;
        $metadata = toy_module_metadata((string) $row['module_key']);
        $row['code_name'] = is_string($metadata['name'] ?? null) ? (string) $metadata['name'] : '';
        $row['code_version'] = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
        $row['code_type'] = toy_module_type((string) $row['module_key']);
        $row['description'] = is_string($metadata['description'] ?? null) ? (string) $metadata['description'] : '';
        $toycoreMetadata = is_array($metadata['toycore'] ?? null) ? $metadata['toycore'] : [];
        $toycoreTestedWith = $toycoreMetadata['tested_with'] ?? [];
        $row['toycore_min_version'] = is_string($toycoreMetadata['min_version'] ?? null) ? (string) $toycoreMetadata['min_version'] : '';
        $row['toycore_tested_with'] = is_array($toycoreTestedWith)
            ? implode(', ', array_map('strval', $toycoreTestedWith))
            : (is_string($toycoreTestedWith) ? $toycoreTestedWith : '');
        $row['toycore_module_contract'] = is_string($toycoreMetadata['module_contract'] ?? null) ? (string) $toycoreMetadata['module_contract'] : '';
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
        $modules[] = $row;
    }

    $installableModules = [];
    $moduleDirectories = glob(TOY_ROOT . '/modules/*', GLOB_ONLYDIR);
    if (is_array($moduleDirectories)) {
        sort($moduleDirectories, SORT_STRING);
        foreach ($moduleDirectories as $moduleDirectory) {
            $moduleKey = basename($moduleDirectory);
            if (!toy_is_safe_module_key($moduleKey) || isset($installedModuleKeys[$moduleKey])) {
                continue;
            }

            $metadata = toy_module_metadata($moduleKey);
            if ($metadata === [] || !is_file($moduleDirectory . '/install.sql')) {
                continue;
            }
            $toycoreMetadata = is_array($metadata['toycore'] ?? null) ? $metadata['toycore'] : [];
            $toycoreTestedWith = $toycoreMetadata['tested_with'] ?? [];

            $installableModules[] = [
                'module_key' => $moduleKey,
                'name' => is_string($metadata['name'] ?? null) ? (string) $metadata['name'] : $moduleKey,
                'version' => is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '',
                'type' => toy_module_type($moduleKey),
                'description' => is_string($metadata['description'] ?? null) ? (string) $metadata['description'] : '',
                'toycore_min_version' => is_string($toycoreMetadata['min_version'] ?? null) ? (string) $toycoreMetadata['min_version'] : '',
                'toycore_tested_with' => is_array($toycoreTestedWith)
                    ? implode(', ', array_map('strval', $toycoreTestedWith))
                    : (is_string($toycoreTestedWith) ? $toycoreTestedWith : ''),
                'toycore_module_contract' => is_string($toycoreMetadata['module_contract'] ?? null) ? (string) $toycoreMetadata['module_contract'] : '',
            ];
        }
    }

    $registryModules = [];
    $runtimeConfig = toy_runtime_config();
    foreach (toy_admin_module_registry_entries() as $entry) {
        $moduleKey = (string) $entry['module_key'];
        $entry['installed'] = isset($installedModuleKeys[$moduleKey]);
        $entry['contract_ready'] = toy_admin_registry_entry_contract_ready($entry);
        $entry['download_ready'] = toy_admin_registry_entry_download_ready($entry);
        $entry['repository_ready'] = toy_admin_registry_entry_repository_ready($entry);
        $entry['repository_archive_ready'] = toy_admin_repository_archive_ready($entry, $runtimeConfig);
        $entry['repository_archive_production'] = toy_admin_runtime_is_production($runtimeConfig);
        $entry['repository_archive_refs'] = toy_admin_repository_archive_registered_refs($entry);
        $registeredRefs = array_keys((array) $entry['repository_archive_refs']);
        $entry['default_ref'] = $registeredRefs !== []
            ? (string) $registeredRefs[0]
            : (string) ($entry['latest_version'] !== '' ? 'v' . $entry['latest_version'] : 'main');
        $registryModules[] = $entry;
    }

    $moduleSettings = [];
    $stmt = $pdo->query(
        'SELECT m.module_key, s.setting_key, s.setting_value, s.value_type, s.updated_at
         FROM toy_module_settings s
         INNER JOIN toy_modules m ON m.id = s.module_id
         ORDER BY m.module_key ASC, s.setting_key ASC'
    );
    foreach ($stmt->fetchAll() as $row) {
        $moduleSettings[] = $row;
    }

    return [
        'modules' => $modules,
        'installable_modules' => $installableModules,
        'registry_modules' => $registryModules,
        'module_settings' => $moduleSettings,
    ];
}
