<?php

declare(strict_types=1);

function sr_admin_update_files(string $directory): array
{
    return sr_schema_update_files($directory);
}

function sr_admin_update_checksum(string $path): string
{
    return sr_schema_update_checksum($path);
}

function sr_admin_update_statement_count(string $path): int
{
    return sr_schema_update_statement_count($path);
}

function sr_admin_update_path_is_allowed(array $update): bool
{
    return sr_schema_update_path_is_allowed($update);
}

function sr_admin_acquire_update_lock(PDO $pdo): bool
{
    return sr_schema_update_lock_acquire($pdo);
}

function sr_admin_release_update_lock(PDO $pdo): void
{
    sr_schema_update_lock_release($pdo);
}

function sr_admin_applied_schema_versions(PDO $pdo): array
{
    return sr_applied_schema_version_map($pdo);
}

function sr_admin_schema_versions(PDO $pdo): array
{
    return sr_schema_version_rows($pdo);
}

function sr_admin_pending_updates(PDO $pdo): array
{
    return sr_pending_schema_updates($pdo);
}

function sr_admin_apply_update(PDO $pdo, array $update): void
{
    sr_apply_schema_update($pdo, $update);
}

function sr_admin_previous_update_failure(): ?array
{
    return sr_previous_schema_update_failure();
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

    $pendingUpdates = sr_pending_schema_updates($pdo);
    $backupConfirmed = ($_POST['backup_confirmed'] ?? '') === '1';

    if ($errors === [] && $intent === 'sync_file_only_versions') {
        $syncedModules = sr_sync_file_only_module_versions(
            $pdo,
            sr_module_pending_update_counts($pendingUpdates)
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
        if (!sr_schema_update_lock_acquire($pdo)) {
            $errors[] = '다른 업데이트가 실행 중입니다. 잠시 후 다시 시도하세요.';
            sr_write_operational_marker('update-failed.json', [
                'stage' => 'acquire_update_lock',
                'message' => 'Schema update lock could not be acquired.',
            ]);
        } else {
            try {
                $pendingUpdates = sr_pending_schema_updates($pdo);
                foreach ($pendingUpdates as $update) {
                    try {
                        sr_admin_audit_schema_update($pdo, $account, $update, 'schema.update.started', 'success', 'Schema update started.');

                        sr_apply_schema_update($pdo, $update);
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
                sr_schema_update_lock_release($pdo);
            }
        }
    }

    if ($errors === []) {
        sr_clear_operational_marker('update-failed.json');
        $syncedModules = sr_sync_file_only_module_versions(
            $pdo,
            sr_module_pending_update_counts(sr_pending_schema_updates($pdo))
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
    return sr_module_version_drifts($pdo, $pendingUpdateCounts);
}

function sr_admin_file_only_module_version_drifts(array $moduleVersionDrifts): array
{
    return sr_file_only_module_version_drifts($moduleVersionDrifts);
}
