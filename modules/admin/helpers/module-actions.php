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

    if ($intent === 'upload_module_zip') {
        $moduleKey = strtolower(trim(sr_post_string('upload_module_key', 60)));
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

        if (!$moduleUploadAvailable) {
            $errors[] = 'PHP ZipArchive 확장이 없어 모듈 zip을 처리할 수 없습니다.';
        }
    } elseif ($intent === 'sync_module_version') {
        if (!$canManageModuleSources) {
            $errors[] = '파일 전용 업데이트 반영은 소유자 권한이 필요합니다.';
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
                $errors[] = '미적용 SQL이 있는 모듈은 업데이트 화면에서 먼저 DB 업데이트를 실행하세요.';
            } elseif ($errors === [] && strcmp($codeVersion, (string) $module['version']) <= 0) {
                $errors[] = '설치 버전에 반영할 새 코드 버전이 없습니다.';
            }
        }
    }

    $errors = array_values(array_unique($errors));

    if ($errors === [] && $intent === 'upload_module_zip') {
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
            sr_save_site_setting($pdo, 'admin.module_sources_enabled', '0', 'bool');
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

            $notice = '모듈을 설치했습니다.';
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

    return sr_admin_action_result($errors, $notice);
}

function sr_admin_module_route_conflict_errors(PDO $pdo, string $candidateModuleKey): array
{
    return sr_module_route_conflict_errors($pdo, $candidateModuleKey);
}

function sr_admin_module_route_map(string $moduleKey): array
{
    return sr_module_route_map($moduleKey);
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
    return sr_module_code_older_errors($pdo, $moduleKey);
}

function sr_admin_module_lifecycle_state(array $module): array
{
    return sr_module_lifecycle_state($module);
}

function sr_admin_load_module_management_view_data(PDO $pdo): array
{
    return sr_load_module_management_view_data($pdo);
}
