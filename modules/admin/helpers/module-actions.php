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
    $closeModuleSourcesAfterRequest = false;

    if ($intent === 'upload_module_zip') {
        $moduleKey = strtolower(trim(sr_post_string('upload_module_key', 40)));
    }

    if (!in_array($intent, ['enable_module_source_writes', 'disable_module_source_writes', 'upload_module_zip', 'sync_module_version', 'status', 'install'], true)) {
        $errors[] = '지원하지 않는 모듈 관리 요청입니다.';
    }

    if (!in_array($intent, ['enable_module_source_writes', 'disable_module_source_writes', 'upload_module_zip'], true) && !sr_is_safe_module_key($moduleKey)) {
        $errors[] = '모듈 키가 올바르지 않습니다.';
    }

    $sourceWriteIntents = ['enable_module_source_writes', 'upload_module_zip', 'sync_module_version'];
    if (in_array($intent, ['upload_module_zip', 'sync_module_version'], true)) {
        $closeModuleSourcesAfterRequest = true;
    }

    if ($intent === 'enable_module_source_writes') {
        if (!$canManageModuleSources) {
            $errors[] = '모듈 소스 반영은 매니저 권한이 필요합니다.';
        }

        if ($moduleSourcesEnabled) {
            $errors[] = '모듈 파일 반영은 이미 일시 허용되어 있습니다.';
        }
    } elseif ($intent === 'disable_module_source_writes') {
        if (!$canManageModuleSources) {
            $errors[] = '모듈 소스 반영은 매니저 권한이 필요합니다.';
        }
    } elseif ($intent === 'upload_module_zip') {
        if (!$canManageModuleSources) {
            $errors[] = '모듈 소스 반영은 매니저 권한이 필요합니다.';
        }

        if (!$moduleSourcesEnabled) {
            $errors[] = '모듈 zip 업로드는 매니저 재인증 요청에서만 일시 허용됩니다.';
        }

        if (!$moduleUploadAvailable) {
            $errors[] = 'PHP ZipArchive 확장이 없어 모듈 zip을 처리할 수 없습니다.';
        }
    } elseif ($intent === 'sync_module_version') {
        if (!$canManageModuleSources) {
            $errors[] = '파일 전용 업데이트 반영은 매니저 권한이 필요합니다.';
        }

        if (!$moduleSourcesEnabled) {
            $errors[] = '파일 전용 업데이트 반영은 현재 환경에서 비활성화되어 있습니다.';
        }
    } elseif ($intent === 'status') {
        $status = sr_post_string('status', 30);
        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = '모듈 상태 값이 올바르지 않습니다.';
        }

        if (in_array($moduleKey, $requiredModules, true) && $status !== 'enabled') {
            $errors[] = '기본 모듈은 비활성화할 수 없습니다.';
        }

        if ($status !== 'enabled') {
            $errors = array_merge($errors, sr_module_disable_errors($pdo, $moduleKey));
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

    if ($errors === [] && in_array($intent, ['status', 'sync_module_version'], true)) {
        $stmt = $pdo->prepare('SELECT id, version, status FROM sr_modules WHERE module_key = :module_key LIMIT 1');
        $stmt->execute(['module_key' => $moduleKey]);
        $module = $stmt->fetch();

        if (!is_array($module)) {
            $errors[] = '모듈을 찾을 수 없습니다.';
        }

        if ($errors === [] && $intent === 'status' && in_array((string) $module['status'], ['failed', 'installing'], true)) {
            $errors[] = '설치가 완료되지 않은 모듈은 재설치를 먼저 실행하세요.';
        }
    }

    if ($errors === [] && in_array($intent, ['install', 'status'], true) && $status === 'enabled') {
        try {
            foreach (sr_admin_prepare_module_foundations($pdo, $account, $moduleKey) as $preparedFoundation) {
                $notice .= ($notice !== '' ? ' ' : '') . $preparedFoundation['module_key'] . ' 기반 모듈이 함께 준비되었습니다.';
            }
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'module_foundation_prepare_failed');
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'module.foundation.prepare_failed',
                'target_type' => 'module',
                'target_id' => $moduleKey,
                'result' => 'failure',
                'message' => 'Foundation module preparation failed.',
                'metadata' => [
                    'requested_module_key' => $moduleKey,
                    'reason' => sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500)),
                ],
            ]);
            $errors[] = '필요 기반 모듈을 준비하지 못했습니다. 모듈 파일과 업데이트 상태를 확인하세요.';
        }
    }

    if ($errors === [] && $intent === 'status' && $status === 'enabled') {
        $metadata = sr_module_metadata($moduleKey);
        if ($metadata === []) {
            $errors[] = '모듈 메타데이터를 찾을 수 없습니다.';
        } else {
            $errors = array_merge(
                $errors,
                sr_module_metadata_errors($metadata),
                sr_module_contract_file_errors(SR_ROOT . '/modules/' . $moduleKey, $metadata),
                sr_module_requirement_errors($pdo, $moduleKey, $metadata, $status),
                sr_module_code_older_errors($pdo, $moduleKey),
                sr_module_route_conflict_errors($pdo, $moduleKey)
            );
        }
    }

    if ($errors === [] && $intent === 'sync_module_version') {
        $metadata = sr_module_metadata($moduleKey);
        if ($metadata === []) {
            $errors[] = '모듈 메타데이터를 찾을 수 없습니다.';
        } else {
            $errors = array_merge(
                $errors,
                sr_module_metadata_errors($metadata),
                sr_module_contract_file_errors(SR_ROOT . '/modules/' . $moduleKey, $metadata),
                sr_module_requirement_errors($pdo, $moduleKey, $metadata, (string) ($module['status'] ?? 'enabled'))
            );
            $codeVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
            $pendingCounts = sr_module_pending_update_counts(sr_pending_schema_updates($pdo));

            if ($errors === [] && (int) ($pendingCounts[$moduleKey] ?? 0) > 0) {
                $errors[] = '미적용 SQL이 있는 모듈은 업데이트 화면에서 먼저 데이터베이스 업데이트를 실행하세요.';
            } elseif ($errors === [] && strcmp($codeVersion, (string) $module['version']) <= 0) {
                $errors[] = '설치 버전에 반영할 새 코드 버전이 없습니다.';
            }
        }
    }

    $errors = array_values(array_unique($errors));

    if ($errors !== [] && $intent === 'upload_module_zip') {
        $uploadFailureReasons = array_map(
            static fn($error): string => sr_log_sensitive_text_sanitize(sr_log_line_value((string) $error, 500)),
            $errors
        );
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'module.source.uploaded',
            'target_type' => 'module',
            'target_id' => $moduleKey,
            'result' => 'failure',
            'message' => 'Module source zip upload failed.',
            'metadata' => [
                'source' => 'upload',
                'stage' => 'preflight',
                'reason' => implode('; ', $uploadFailureReasons),
                'validation_errors' => $uploadFailureReasons,
                'module_sources_enabled' => $moduleSourcesEnabled,
                'zip_upload_available' => $moduleUploadAvailable,
            ],
        ]);
    }

    if ($errors === [] && $intent === 'enable_module_source_writes') {
        sr_save_site_setting($pdo, 'admin.module_sources_enabled', '1', 'bool');
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'module.source.enabled',
            'target_type' => 'module_source',
            'target_id' => 'admin.module_sources_enabled',
            'result' => 'success',
            'message' => 'Module source writes enabled temporarily.',
            'metadata' => [
                'zip_upload_available' => $moduleUploadAvailable,
            ],
        ]);
        $notice = '모듈 파일 반영을 일시 허용했습니다. 작업이 끝나면 자동 또는 수동으로 다시 닫힙니다.';
    } elseif ($errors === [] && $intent === 'disable_module_source_writes') {
        sr_save_site_setting($pdo, 'admin.module_sources_enabled', '0', 'bool');
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'module.source.disabled',
            'target_type' => 'module_source',
            'target_id' => 'admin.module_sources_enabled',
            'result' => 'success',
            'message' => 'Module source writes disabled.',
        ]);
        $notice = '모듈 파일 반영 허용을 닫았습니다.';
    } elseif ($errors === [] && $intent === 'upload_module_zip') {
        $extractDir = '';
        $sourceType = 'upload';
        try {
            $upload = $_FILES['module_zip'] ?? null;
            if (!is_array($upload)) {
                throw new RuntimeException('업로드할 zip 파일을 선택하세요.');
            }

            $source = sr_extract_module_upload($upload, $moduleKey);
            $extractDir = (string) ($source['extract_dir'] ?? '');
            $uploadStats = is_array($source['upload'] ?? null) ? $source['upload'] : [];
            $moduleKey = (string) $source['module_key'];
            $metadata = is_array($source['metadata']) ? $source['metadata'] : [];
            $moduleVersion = (string) ($metadata['version'] ?? '');
            $replaceConfirmed = ($_POST['confirm_file_replace'] ?? '') === '1';
            foreach (sr_module_replace_errors($moduleKey, $replaceConfirmed) as $replaceError) {
                throw new RuntimeException($replaceError);
            }

            $allowDowngrade = ($_POST['allow_downgrade'] ?? '') === '1';
            foreach (sr_module_upload_version_errors($pdo, $moduleKey, $metadata, $allowDowngrade) as $versionError) {
                throw new RuntimeException($versionError);
            }

            foreach (sr_module_source_route_conflict_errors($pdo, $moduleKey, (string) $source['source_dir']) as $routeConflictError) {
                throw new RuntimeException($routeConflictError);
            }

            $result = sr_install_module_source_files($moduleKey, (string) $source['source_dir']);

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
                    sr_remove_directory($extractDir);
                } catch (Throwable $ignored) {
                }
            }
        }
    } elseif ($errors === [] && $intent === 'install') {
        try {
            $installedModule = sr_install_module($pdo, $moduleKey, $status);

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'module.installed',
                'target_type' => 'module',
                'target_id' => $moduleKey,
                'result' => 'success',
                'message' => 'Module installed.',
                'metadata' => [
                    'status' => (string) $installedModule['status'],
                    'version' => (string) $installedModule['version'],
                ],
            ]);

            $notice .= ($notice !== '' ? ' ' : '') . '모듈을 설치했습니다.';
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'module_install_failed');
            $errors[] = '모듈 설치 중 오류가 발생했습니다.';
        }
    } elseif ($errors === [] && $intent === 'sync_module_version') {
        $metadata = sr_module_metadata($moduleKey);
        $codeVersion = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
        $beforeVersion = (string) $module['version'];
        sr_sync_module_version($pdo, $moduleKey, $codeVersion);

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
        $statusChange = sr_update_module_status($pdo, $moduleKey, $status);

        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'module.status.updated',
            'target_type' => 'module',
            'target_id' => $moduleKey,
            'result' => 'success',
            'message' => 'Module status updated.',
            'metadata' => [
                'before_status' => (string) $statusChange['before_status'],
                'after_status' => (string) $statusChange['after_status'],
            ],
        ]);

        $notice = '모듈 상태를 저장했습니다.';
    } elseif ($errors === []) {
        $errors[] = '요청한 작업을 처리할 수 없습니다.';
    }

    if ($closeModuleSourcesAfterRequest) {
        sr_save_site_setting($pdo, 'admin.module_sources_enabled', '0', 'bool');
    }

    return sr_admin_action_result($errors, $notice);
}

function sr_admin_prepare_module_foundations(PDO $pdo, array $account, string $moduleKey): array
{
    $prepared = [];
    foreach (sr_module_foundation_dependencies($moduleKey) as $foundationModuleKey) {
        $foundation = sr_module_record_entry($pdo, $foundationModuleKey);
        if (is_array($foundation) && (string) ($foundation['status'] ?? '') === 'enabled') {
            continue;
        }

        if (is_array($foundation)) {
            $metadata = sr_module_metadata($foundationModuleKey);
            $moduleDirectory = SR_ROOT . '/modules/' . $foundationModuleKey;
            $metadataErrors = $metadata === [] ? ['모듈 메타데이터를 찾을 수 없습니다.'] : array_merge(
                sr_module_metadata_errors($metadata),
                sr_module_contract_file_errors($moduleDirectory, $metadata),
                sr_module_requirement_errors($pdo, $foundationModuleKey, $metadata, 'enabled'),
                sr_module_code_older_errors($pdo, $foundationModuleKey),
                sr_module_route_conflict_errors($pdo, $foundationModuleKey)
            );
            if ($metadataErrors !== []) {
                throw new RuntimeException(implode(' ', array_values(array_unique($metadataErrors))));
            }

            $statusChange = sr_update_module_status($pdo, $foundationModuleKey, 'enabled');
            $prepared[] = ['module_key' => $foundationModuleKey, 'action' => 'enabled'];
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'module.foundation.enabled',
                'target_type' => 'module',
                'target_id' => $foundationModuleKey,
                'result' => 'success',
                'message' => 'Foundation module enabled automatically.',
                'metadata' => [
                    'requested_module_key' => $moduleKey,
                    'before_status' => (string) $statusChange['before_status'],
                    'after_status' => (string) $statusChange['after_status'],
                ],
            ]);
            continue;
        }

        $installedModule = sr_install_module($pdo, $foundationModuleKey, 'enabled');
        $prepared[] = ['module_key' => $foundationModuleKey, 'action' => 'installed'];
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'module.foundation.installed',
            'target_type' => 'module',
            'target_id' => $foundationModuleKey,
            'result' => 'success',
            'message' => 'Foundation module installed automatically.',
            'metadata' => [
                'requested_module_key' => $moduleKey,
                'status' => (string) $installedModule['status'],
                'version' => (string) $installedModule['version'],
            ],
        ]);
    }

    return $prepared;
}

function sr_admin_module_source_reauth_errors(PDO $pdo, array $account, string $intent): array
{
    $password = sr_post_string('owner_password', 255);
    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId < 1) {
        return ['매니저 재인증 계정을 확인할 수 없습니다.'];
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
        return ['모듈 소스 반영 전 매니저 비밀번호를 다시 입력하세요.'];
    }

    sr_member_log_auth($pdo, $accountId, 'module_source_reauth', 'success');
    return [];
}

function sr_admin_module_lifecycle_state(array $module): array
{
    return sr_module_lifecycle_state($module);
}

function sr_admin_load_module_management_view_data(PDO $pdo): array
{
    $modules = [];
    $pendingUpdateCounts = sr_module_pending_update_counts(sr_pending_schema_updates($pdo));
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
        $row['is_foundation'] = sr_module_is_foundation((string) $row['module_key']);
        $row['foundation_dependents'] = sr_enabled_asset_modules_requiring_foundation($pdo, (string) $row['module_key']);
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
        if (!sr_admin_module_should_hide_in_management($row)) {
            $modules[] = $row;
        }
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

            $installableModule = [
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
            $installableModule['is_foundation'] = sr_module_is_foundation($moduleKey);
            if (!sr_admin_module_should_hide_in_management($installableModule)) {
                $installableModules[] = $installableModule;
            }
        }
    }

    return [
        'modules' => $modules,
        'installable_modules' => $installableModules,
        'show_foundation_modules' => sr_admin_show_foundation_modules(),
    ];
}

function sr_admin_module_should_hide_in_management(array $module): bool
{
    if (sr_admin_show_foundation_modules()) {
        return false;
    }

    return !empty($module['is_foundation']);
}

function sr_admin_show_foundation_modules(): bool
{
    return (string) ($_GET['show_foundations'] ?? '') === '1';
}
