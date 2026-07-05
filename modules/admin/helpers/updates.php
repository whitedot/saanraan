<?php

declare(strict_types=1);

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
        $markdownTransition = sr_admin_markdown_editor_transition_after_update($pdo, $account);
        if ($appliedUpdates === []) {
            $notice = $syncedModules === [] ? '' : '파일 전용 업데이트 버전을 반영했습니다.';
        } else {
            $notice = $syncedModules === [] ? '업데이트를 적용했습니다.' : '업데이트를 적용하고 모듈 설치 버전을 반영했습니다.';
        }
        if ((string) ($markdownTransition['notice'] ?? '') !== '') {
            $notice .= ($notice !== '' ? ' ' : '') . (string) $markdownTransition['notice'];
        }
    }

    return [
        'errors' => $errors,
        'notice' => $notice,
        'applied_updates' => $appliedUpdates,
    ];
}

function sr_admin_markdown_editor_transition_after_update(PDO $pdo, array $account): array
{
    if (!sr_admin_markdown_editor_usage_detected($pdo)) {
        return ['notice' => ''];
    }

    $moduleKey = 'markdown_editor';
    $module = sr_module_record_entry($pdo, $moduleKey);
    if (is_array($module) && (string) ($module['status'] ?? '') === 'enabled') {
        return ['notice' => ''];
    }

    try {
        if (is_array($module)) {
            sr_update_module_status($pdo, $moduleKey, 'enabled');
            $action = 'enabled';
        } else {
            sr_install_module($pdo, $moduleKey, 'enabled', true);
            $action = 'installed';
        }

        sr_audit_log($pdo, [
            'actor_account_id' => (int) ($account['id'] ?? 0),
            'actor_type' => 'admin',
            'event_type' => 'markdown_editor.transition.' . $action,
            'target_type' => 'module',
            'target_id' => $moduleKey,
            'result' => 'success',
            'message' => 'Markdown editor plugin activated for existing markdown usage.',
            'metadata' => [
                'detected_usage' => true,
            ],
        ]);
        sr_clear_operational_marker('markdown-editor-transition.json');

        return ['notice' => '기존 Markdown 사용 흔적이 있어 Markdown Editor 플러그인을 자동 활성화했습니다.'];
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'markdown_editor_transition_failed');
        sr_write_operational_marker('markdown-editor-transition.json', [
            'stage' => 'activate_markdown_editor',
            'message' => sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500)),
        ]);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) ($account['id'] ?? 0),
            'actor_type' => 'admin',
            'event_type' => 'markdown_editor.transition.failed',
            'target_type' => 'module',
            'target_id' => $moduleKey,
            'result' => 'failure',
            'message' => 'Markdown editor plugin activation failed during update.',
            'metadata' => [
                'reason' => sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500)),
            ],
        ]);

        return ['notice' => '기존 Markdown 사용 흔적이 있지만 Markdown Editor 자동 활성화에 실패했습니다. 모듈 목록과 운영 marker를 확인하세요.'];
    }
}

function sr_admin_markdown_editor_usage_detected(PDO $pdo): bool
{
    $checks = [
        ["SELECT 1 FROM sr_module_settings s INNER JOIN sr_modules m ON m.id = s.module_id WHERE s.setting_key IN ('editor', 'post_editor', 'popup_layer_editor') AND s.setting_value = 'markdown' LIMIT 1", []],
        ["SELECT 1 FROM sr_community_board_settings WHERE setting_key = 'post_editor' AND setting_value = 'markdown' LIMIT 1", []],
        ["SELECT 1 FROM sr_content_items WHERE body_format = 'markdown' LIMIT 1", []],
        ["SELECT 1 FROM sr_content_item_versions WHERE body_format = 'markdown' LIMIT 1", []],
        ["SELECT 1 FROM sr_community_posts WHERE body_format = 'markdown' LIMIT 1", []],
        ["SELECT 1 FROM sr_popup_layers WHERE body_format = 'markdown' LIMIT 1", []],
        ["SELECT 1 FROM sr_notifications WHERE body_format = 'markdown' LIMIT 1", []],
    ];

    foreach ($checks as $check) {
        try {
            $stmt = $pdo->prepare((string) $check[0]);
            $stmt->execute($check[1]);
            if ($stmt->fetchColumn() !== false) {
                return true;
            }
        } catch (Throwable) {
            continue;
        }
    }

    return false;
}
