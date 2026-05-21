<?php

declare(strict_types=1);

function sr_admin_retention_cutoff(int $days): string
{
    return date('Y-m-d H:i:s', time() - ($days * 86400));
}

function sr_admin_retention_default_values(): array
{
    return [
        'auth_logs_days' => 180,
        'audit_logs_days' => 365,
        'used_tokens_days' => 30,
        'sessions_days' => 30,
        'notifications_days' => 365,
        'module_backups_days' => 180,
    ];
}

function sr_admin_retention_setting_keys(): array
{
    return [
        'auth_logs_days' => 'admin.retention.auth_logs_days',
        'audit_logs_days' => 'admin.retention.audit_logs_days',
        'used_tokens_days' => 'admin.retention.used_tokens_days',
        'sessions_days' => 'admin.retention.sessions_days',
        'notifications_days' => 'admin.retention.notifications_days',
        'module_backups_days' => 'admin.retention.module_backups_days',
    ];
}

function sr_admin_retention_values(PDO $pdo): array
{
    $values = sr_admin_retention_default_values();
    foreach (sr_admin_retention_setting_keys() as $valueKey => $settingKey) {
        $value = sr_site_setting($pdo, $settingKey, $values[$valueKey]);
        if (is_int($value) && $value >= 1 && $value <= 3650) {
            $values[$valueKey] = $value;
        } elseif (is_string($value) && ctype_digit($value) && (int) $value >= 1 && (int) $value <= 3650) {
            $values[$valueKey] = (int) $value;
        }
    }

    return $values;
}

function sr_admin_retention_post_values(array $currentValues): array
{
    $values = $currentValues;
    foreach (array_keys(sr_admin_retention_default_values()) as $key) {
        if (array_key_exists($key, $_POST)) {
            $values[$key] = sr_admin_post_int_in_range($key, 1, 3650, 5) ?? 0;
        }
    }

    return $values;
}

function sr_admin_validate_retention_values(array $values): array
{
    $errors = [];
    foreach ($values as $days) {
        if ($days < 1 || $days > 3650) {
            $errors[] = '보관 기간은 1일부터 3650일 사이로 입력하세요.';
            break;
        }
    }

    return $errors;
}

function sr_admin_validate_retention_cleanup(): array
{
    $errors = [];
    $cleanupConfirmed = ($_POST['cleanup_confirmed'] ?? '') === '1';
    $cleanupPhrase = sr_post_string('cleanup_phrase', 20);
    if (!$cleanupConfirmed || $cleanupPhrase !== 'DELETE') {
        $errors[] = '정리 실행 전 확인 체크와 DELETE 입력이 필요합니다.';
    }

    return $errors;
}

function sr_admin_save_retention_values(PDO $pdo, array $values): void
{
    $settings = [];
    foreach (sr_admin_retention_setting_keys() as $valueKey => $settingKey) {
        $settings[$settingKey] = [
            'value' => (string) $values[$valueKey],
            'type' => 'int',
        ];
    }

    sr_save_site_settings($pdo, $settings);
}

function sr_admin_retention_count(PDO $pdo, string $sql, array $params): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return is_array($row) ? (int) $row['count_value'] : 0;
}

function sr_admin_retention_notification_tables_exist(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM sr_notifications LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_notification_deliveries LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_notification_reads LIMIT 1');
        return true;
    } catch (PDOException $exception) {
        return false;
    }
}

function sr_admin_retention_runtime_sessions_table_exists(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM sr_sessions LIMIT 1');
        return true;
    } catch (PDOException $exception) {
        return false;
    }
}

function sr_admin_retention_rate_limits_table_exists(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM sr_rate_limits LIMIT 1');
        return true;
    } catch (PDOException $exception) {
        return false;
    }
}

function sr_admin_retention_target_definitions(bool $hasNotificationTables, bool $hasSessionsTable, bool $hasRuntimeSessionsTable = false, bool $hasRateLimitsTable = false): array
{
    return [
        'auth_logs' => [
            'enabled' => true,
            'cutoff_key' => 'auth_logs',
            'count_sql' => 'SELECT COUNT(*) AS count_value FROM sr_member_auth_logs WHERE created_at < :cutoff',
            'count_params' => [
                'cutoff' => 'auth_logs',
            ],
            'delete_sql' => 'DELETE FROM sr_member_auth_logs WHERE created_at < :cutoff',
            'delete_params' => [
                'cutoff' => 'auth_logs',
            ],
        ],
        'audit_logs' => [
            'enabled' => true,
            'cutoff_key' => 'audit_logs',
            'count_sql' => 'SELECT COUNT(*) AS count_value FROM sr_audit_logs WHERE created_at < :cutoff',
            'count_params' => [
                'cutoff' => 'audit_logs',
            ],
            'delete_sql' => 'DELETE FROM sr_audit_logs WHERE created_at < :cutoff',
            'delete_params' => [
                'cutoff' => 'audit_logs',
            ],
        ],
        'password_resets' => [
            'enabled' => true,
            'cutoff_key' => 'used_tokens',
            'count_sql' => 'SELECT COUNT(*) AS count_value FROM sr_member_password_resets WHERE used_at IS NOT NULL AND used_at < :cutoff',
            'count_params' => [
                'cutoff' => 'used_tokens',
            ],
            'delete_sql' => 'DELETE FROM sr_member_password_resets WHERE used_at IS NOT NULL AND used_at < :cutoff',
            'delete_params' => [
                'cutoff' => 'used_tokens',
            ],
        ],
        'email_verifications' => [
            'enabled' => true,
            'cutoff_key' => 'used_tokens',
            'count_sql' => 'SELECT COUNT(*) AS count_value FROM sr_member_email_verifications WHERE verified_at IS NOT NULL AND verified_at < :cutoff',
            'count_params' => [
                'cutoff' => 'used_tokens',
            ],
            'delete_sql' => 'DELETE FROM sr_member_email_verifications WHERE verified_at IS NOT NULL AND verified_at < :cutoff',
            'delete_params' => [
                'cutoff' => 'used_tokens',
            ],
        ],
        'sessions' => [
            'enabled' => $hasSessionsTable,
            'cutoff_key' => 'sessions',
            'count_sql' => 'SELECT COUNT(*) AS count_value
             FROM sr_member_sessions
             WHERE (revoked_at IS NOT NULL AND revoked_at < :revoked_cutoff)
                OR expires_at < :expired_cutoff',
            'count_params' => [
                'revoked_cutoff' => 'sessions',
                'expired_cutoff' => 'sessions',
            ],
            'delete_sql' => 'DELETE FROM sr_member_sessions
             WHERE (revoked_at IS NOT NULL AND revoked_at < :revoked_cutoff)
                OR expires_at < :expired_cutoff',
            'delete_params' => [
                'revoked_cutoff' => 'sessions',
                'expired_cutoff' => 'sessions',
            ],
        ],
        'runtime_sessions' => [
            'enabled' => $hasRuntimeSessionsTable,
            'cutoff_key' => 'sessions',
            'count_sql' => 'SELECT COUNT(*) AS count_value
             FROM sr_sessions
             WHERE expires_at < :expired_cutoff',
            'count_params' => [
                'expired_cutoff' => 'sessions',
            ],
            'delete_sql' => 'DELETE FROM sr_sessions
             WHERE expires_at < :expired_cutoff',
            'delete_params' => [
                'expired_cutoff' => 'sessions',
            ],
        ],
        'rate_limits' => [
            'enabled' => $hasRateLimitsTable,
            'cutoff_key' => 'sessions',
            'count_sql' => 'SELECT COUNT(*) AS count_value
             FROM sr_rate_limits
             WHERE expires_at < :expired_cutoff',
            'count_params' => [
                'expired_cutoff' => 'sessions',
            ],
            'delete_sql' => 'DELETE FROM sr_rate_limits
             WHERE expires_at < :expired_cutoff',
            'delete_params' => [
                'expired_cutoff' => 'sessions',
            ],
        ],
        'notifications' => [
            'enabled' => $hasNotificationTables,
            'cutoff_key' => 'notifications',
            'count_sql' => 'SELECT COUNT(*) AS count_value FROM sr_notifications WHERE created_at < :cutoff',
            'count_params' => [
                'cutoff' => 'notifications',
            ],
            'delete_sql' => 'DELETE FROM sr_notifications WHERE created_at < :cutoff',
            'delete_params' => [
                'cutoff' => 'notifications',
            ],
        ],
        'notification_deliveries' => [
            'enabled' => $hasNotificationTables,
            'cutoff_key' => 'notifications',
            'count_sql' => 'SELECT COUNT(*) AS count_value
             FROM sr_notification_deliveries d
             INNER JOIN sr_notifications n ON n.id = d.notification_id
             WHERE n.created_at < :cutoff',
            'count_params' => [
                'cutoff' => 'notifications',
            ],
            'delete_sql' => 'DELETE d
             FROM sr_notification_deliveries d
             INNER JOIN sr_notifications n ON n.id = d.notification_id
             WHERE n.created_at < :cutoff',
            'delete_params' => [
                'cutoff' => 'notifications',
            ],
        ],
        'notification_reads' => [
            'enabled' => $hasNotificationTables,
            'cutoff_key' => 'notifications',
            'count_sql' => 'SELECT COUNT(*) AS count_value
             FROM sr_notification_reads r
             INNER JOIN sr_notifications n ON n.id = r.notification_id
             WHERE n.created_at < :cutoff',
            'count_params' => [
                'cutoff' => 'notifications',
            ],
            'delete_sql' => 'DELETE r
             FROM sr_notification_reads r
             INNER JOIN sr_notifications n ON n.id = r.notification_id
             WHERE n.created_at < :cutoff',
            'delete_params' => [
                'cutoff' => 'notifications',
            ],
        ],
        'module_backups' => [
            'enabled' => true,
            'cutoff_key' => 'module_backups',
            'count_callback' => 'sr_admin_retention_module_backup_count',
            'delete_callback' => 'sr_admin_retention_delete_module_backups',
        ],
    ];
}

function sr_admin_retention_cleanup_target_keys(): array
{
    return [
        'auth_logs',
        'audit_logs',
        'password_resets',
        'email_verifications',
        'sessions',
        'runtime_sessions',
        'rate_limits',
        'notification_deliveries',
        'notification_reads',
        'notifications',
        'module_backups',
    ];
}

function sr_admin_retention_query_params(array $paramCutoffKeys, array $cutoffs): array
{
    $params = [];
    foreach ($paramCutoffKeys as $paramName => $cutoffKey) {
        $params[$paramName] = $cutoffs[$cutoffKey];
    }

    return $params;
}

function sr_admin_retention_module_backup_dirs(string $cutoff): array
{
    $backupRoot = SR_ROOT . '/storage/module-backups';
    if (!is_dir($backupRoot)) {
        return [];
    }

    $cutoffTime = strtotime($cutoff);
    if ($cutoffTime === false) {
        return [];
    }

    $directories = glob($backupRoot . '/*', GLOB_ONLYDIR);
    if (!is_array($directories)) {
        return [];
    }

    $oldDirectories = [];
    foreach ($directories as $directory) {
        $modifiedAt = filemtime($directory);
        if ($modifiedAt !== false && $modifiedAt < $cutoffTime) {
            $oldDirectories[] = $directory;
        }
    }

    sort($oldDirectories, SORT_STRING);
    return $oldDirectories;
}

function sr_admin_retention_module_backup_count(string $cutoff): int
{
    return count(sr_admin_retention_module_backup_dirs($cutoff));
}

function sr_admin_retention_delete_module_backups(string $cutoff): int
{
    $deletedCount = 0;
    foreach (sr_admin_retention_module_backup_dirs($cutoff) as $directory) {
        sr_admin_remove_directory($directory);
        $deletedCount++;
    }

    return $deletedCount;
}

function sr_admin_retention_delete_count(PDO $pdo, string $sql, array $params): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function sr_admin_retention_delete_target(PDO $pdo, array $target, array $cutoffs): int
{
    $deleteCallback = (string) ($target['delete_callback'] ?? '');
    if ($deleteCallback !== '') {
        return $deleteCallback($cutoffs[$target['cutoff_key']]);
    }

    return sr_admin_retention_delete_count(
        $pdo,
        (string) $target['delete_sql'],
        sr_admin_retention_query_params($target['delete_params'], $cutoffs)
    );
}

function sr_admin_retention_preview_cutoffs(array $values): array
{
    return [
        'auth_logs' => sr_admin_retention_cutoff($values['auth_logs_days']),
        'audit_logs' => sr_admin_retention_cutoff($values['audit_logs_days']),
        'used_tokens' => sr_admin_retention_cutoff($values['used_tokens_days']),
        'sessions' => sr_admin_retention_cutoff($values['sessions_days']),
        'notifications' => sr_admin_retention_cutoff($values['notifications_days']),
        'module_backups' => sr_admin_retention_cutoff($values['module_backups_days']),
    ];
}

function sr_admin_retention_preview_counts(PDO $pdo, array $previewCutoffs, bool $hasNotificationTables): array
{
    $previewCounts = [];
    $targets = sr_admin_retention_target_definitions(
        $hasNotificationTables,
        sr_member_sessions_table_exists($pdo),
        sr_admin_retention_runtime_sessions_table_exists($pdo),
        sr_admin_retention_rate_limits_table_exists($pdo)
    );

    foreach ($targets as $key => $target) {
        if (!$target['enabled']) {
            $previewCounts[$key] = 0;
            continue;
        }

        $countCallback = (string) ($target['count_callback'] ?? '');
        if ($countCallback !== '') {
            $previewCounts[$key] = $countCallback($previewCutoffs[$target['cutoff_key']]);
            continue;
        }

        $previewCounts[$key] = sr_admin_retention_count(
            $pdo,
            (string) $target['count_sql'],
            sr_admin_retention_query_params($target['count_params'], $previewCutoffs)
        );
    }

    return $previewCounts;
}

function sr_admin_retention_execute_cleanup(PDO $pdo, array $values, bool $hasNotificationTables): array
{
    $cutoffs = sr_admin_retention_preview_cutoffs($values);
    $targets = sr_admin_retention_target_definitions(
        $hasNotificationTables,
        sr_member_sessions_table_exists($pdo),
        sr_admin_retention_runtime_sessions_table_exists($pdo),
        sr_admin_retention_rate_limits_table_exists($pdo)
    );
    $deletedCounts = [];
    foreach (sr_admin_retention_cleanup_target_keys() as $key) {
        $target = $targets[$key];
        if (!$target['enabled']) {
            continue;
        }

        $deletedCounts[$key] = sr_admin_retention_delete_target($pdo, $target, $cutoffs);
    }

    return $deletedCounts;
}

function sr_admin_log_retention_cleanup(PDO $pdo, array $account, array $values, array $deletedCounts): void
{
    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => 'retention.cleanup.completed',
        'target_type' => 'retention',
        'target_id' => 'manual',
        'result' => 'success',
        'message' => 'Retention cleanup completed.',
        'metadata' => [
            'days' => $values,
            'deleted' => $deletedCounts,
        ],
    ]);
}

function sr_admin_log_retention_settings_update(PDO $pdo, array $account, array $beforeValues, array $afterValues): void
{
    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => 'retention.settings.updated',
        'target_type' => 'retention',
        'target_id' => 'settings',
        'result' => 'success',
        'message' => 'Retention settings updated.',
        'metadata' => [
            'before' => $beforeValues,
            'after' => $afterValues,
        ],
    ]);
}

function sr_admin_handle_retention_post(PDO $pdo, array $account, bool $hasNotificationTables, array $currentValues): array
{
    $intent = sr_post_string('intent', 40);
    $values = $currentValues;
    $errors = [];
    $deletedCounts = [];
    $notice = '';

    if ($intent === 'settings') {
        $values = sr_admin_retention_post_values($currentValues);
        $errors = sr_admin_validate_retention_values($values);

        if ($errors === []) {
            sr_admin_save_retention_values($pdo, $values);
            if ($values !== $currentValues) {
                sr_admin_log_retention_settings_update($pdo, $account, $currentValues, $values);
            }
            $notice = $values === $currentValues ? '보관 기간 변경 사항이 없습니다.' : '보관 기간을 저장했습니다.';
        }
    } elseif ($intent === 'cleanup') {
        $errors = sr_admin_validate_retention_values($values);
        foreach (sr_admin_validate_retention_cleanup() as $cleanupError) {
            $errors[] = $cleanupError;
        }

        if ($errors === []) {
            $deletedCounts = sr_admin_retention_execute_cleanup($pdo, $values, $hasNotificationTables);
            sr_admin_log_retention_cleanup($pdo, $account, $values, $deletedCounts);
            $notice = '보관 기간 정리를 실행했습니다.';
        }
    } else {
        $errors[] = '데이터 정리 요청 값이 올바르지 않습니다.';
    }

    return array_merge(sr_admin_action_result($errors, $notice), [
        'values' => $values,
        'deleted_counts' => $deletedCounts,
    ]);
}
