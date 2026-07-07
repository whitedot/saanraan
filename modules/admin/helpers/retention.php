<?php

declare(strict_types=1);

require_once SR_ROOT . '/core/helpers/module-source.php';

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
        'banner_clicks_days' => 180,
        'notifications_days' => 365,
        'module_backups_days' => 180,
        'auto_cleanup_enabled' => 1,
        'auto_cleanup_interval_hours' => 24,
        'auto_cleanup_batch_size' => 200,
    ];
}

function sr_admin_retention_day_value_keys(): array
{
    return [
        'auth_logs_days',
        'audit_logs_days',
        'used_tokens_days',
        'sessions_days',
        'banner_clicks_days',
        'notifications_days',
        'module_backups_days',
    ];
}

function sr_admin_retention_setting_keys(): array
{
    return [
        'auth_logs_days' => 'admin.retention.auth_logs_days',
        'audit_logs_days' => 'admin.retention.audit_logs_days',
        'used_tokens_days' => 'admin.retention.used_tokens_days',
        'sessions_days' => 'admin.retention.sessions_days',
        'banner_clicks_days' => 'admin.retention.banner_clicks_days',
        'notifications_days' => 'admin.retention.notifications_days',
        'module_backups_days' => 'admin.retention.module_backups_days',
        'auto_cleanup_enabled' => 'admin.retention.auto_cleanup_enabled',
        'auto_cleanup_interval_hours' => 'admin.retention.auto_cleanup_interval_hours',
        'auto_cleanup_batch_size' => 'admin.retention.auto_cleanup_batch_size',
    ];
}

function sr_admin_retention_int_setting_value(mixed $value, int $default, int $min, int $max): int
{
    if (is_int($value) && $value >= $min && $value <= $max) {
        return $value;
    }

    if (is_string($value) && ctype_digit($value) && (int) $value >= $min && (int) $value <= $max) {
        return (int) $value;
    }

    return $default;
}

function sr_admin_retention_values(PDO $pdo): array
{
    $values = sr_admin_retention_default_values();
    foreach (sr_admin_retention_setting_keys() as $valueKey => $settingKey) {
        $value = sr_site_setting($pdo, $settingKey, $values[$valueKey]);
        if (in_array($valueKey, sr_admin_retention_day_value_keys(), true)) {
            $values[$valueKey] = sr_admin_retention_int_setting_value($value, $values[$valueKey], 1, 3650);
        } elseif ($valueKey === 'auto_cleanup_enabled') {
            $values[$valueKey] = in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true) ? 1 : 0;
        } elseif ($valueKey === 'auto_cleanup_interval_hours') {
            $values[$valueKey] = sr_admin_retention_int_setting_value($value, $values[$valueKey], 1, 720);
        } elseif ($valueKey === 'auto_cleanup_batch_size') {
            $values[$valueKey] = sr_admin_retention_int_setting_value($value, $values[$valueKey], 1, 5000);
        }
    }

    return $values;
}

function sr_admin_retention_post_values(array $currentValues): array
{
    $values = $currentValues;
    foreach (sr_admin_retention_day_value_keys() as $key) {
        if (array_key_exists($key, $_POST)) {
            $values[$key] = sr_admin_post_int_in_range($key, 1, 3650, 5) ?? 0;
        }
    }
    $values['auto_cleanup_enabled'] = ($_POST['auto_cleanup_enabled'] ?? '') === '1' ? 1 : 0;
    $values['auto_cleanup_interval_hours'] = sr_admin_post_int_in_range('auto_cleanup_interval_hours', 1, 720, 5) ?? 0;
    $values['auto_cleanup_batch_size'] = sr_admin_post_int_in_range('auto_cleanup_batch_size', 1, 5000, 5) ?? 0;

    return $values;
}

function sr_admin_validate_retention_values(array $values): array
{
    $errors = [];
    foreach (sr_admin_retention_day_value_keys() as $key) {
        $days = (int) ($values[$key] ?? 0);
        if ($days < 1 || $days > 3650) {
            $errors[] = '보관 기간은 1일부터 3650일 사이로 입력하세요.';
            break;
        }
    }

    if (!in_array((int) ($values['auto_cleanup_enabled'] ?? 0), [0, 1], true)) {
        $errors[] = '자동 정리 사용 값이 올바르지 않습니다.';
    }

    $intervalHours = (int) ($values['auto_cleanup_interval_hours'] ?? 0);
    if ($intervalHours < 1 || $intervalHours > 720) {
        $errors[] = '자동 정리 실행 간격은 1시간부터 720시간 사이로 입력하세요.';
    }

    $batchSize = (int) ($values['auto_cleanup_batch_size'] ?? 0);
    if ($batchSize < 1 || $batchSize > 5000) {
        $errors[] = '자동 정리 배치 크기는 1개부터 5000개 사이로 입력하세요.';
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
        $type = $valueKey === 'auto_cleanup_enabled' ? 'bool' : 'int';
        $settings[$settingKey] = [
            'value' => (string) $values[$valueKey],
            'type' => $type,
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

function sr_admin_retention_target_definitions(bool $hasNotificationTables, bool $hasSessionsTable, bool $hasRuntimeSessionsTable = false, bool $hasRateLimitsTable = false, bool $hasContentAssetTables = false, bool $hasCommunityAssetTables = false, bool $hasAdminNotificationTables = false, bool $hasBannerClickTables = false, ?PDO $pdo = null): array
{
    $targets = [
        'audit_logs' => [
            'enabled' => true,
            'auto_scope' => 'admin',
            'cutoff_key' => 'audit_logs',
            'count_sql' => 'SELECT COUNT(*) AS count_value FROM sr_audit_logs WHERE created_at < :cutoff',
            'count_params' => [
                'cutoff' => 'audit_logs',
            ],
            'delete_sql' => 'DELETE FROM sr_audit_logs WHERE created_at < :cutoff',
            'delete_limited_sql' => 'DELETE FROM sr_audit_logs WHERE created_at < :cutoff ORDER BY id ASC LIMIT {limit}',
            'delete_params' => [
                'cutoff' => 'audit_logs',
            ],
        ],
        'runtime_sessions' => [
            'enabled' => $hasRuntimeSessionsTable,
            'auto_scope' => 'public',
            'cutoff_key' => 'sessions',
            'count_sql' => 'SELECT COUNT(*) AS count_value
             FROM sr_sessions
             WHERE expires_at < :expired_cutoff',
            'count_params' => [
                'expired_cutoff' => 'sessions',
            ],
            'delete_sql' => 'DELETE FROM sr_sessions
             WHERE expires_at < :expired_cutoff',
            'delete_limited_sql' => 'DELETE FROM sr_sessions
             WHERE expires_at < :expired_cutoff
             ORDER BY id ASC
             LIMIT {limit}',
            'delete_params' => [
                'expired_cutoff' => 'sessions',
            ],
        ],
        'rate_limits' => [
            'enabled' => $hasRateLimitsTable,
            'auto_scope' => 'public',
            'cutoff_key' => 'sessions',
            'count_sql' => 'SELECT COUNT(*) AS count_value
             FROM sr_rate_limits
             WHERE expires_at < :expired_cutoff',
            'count_params' => [
                'expired_cutoff' => 'sessions',
            ],
            'delete_sql' => 'DELETE FROM sr_rate_limits
             WHERE expires_at < :expired_cutoff',
            'delete_limited_sql' => 'DELETE FROM sr_rate_limits
             WHERE expires_at < :expired_cutoff
             ORDER BY id ASC
             LIMIT {limit}',
            'delete_params' => [
                'expired_cutoff' => 'sessions',
            ],
        ],
        'module_upload_work_dirs' => [
            'enabled' => true,
            'auto_scope' => 'admin',
            'cutoff_key' => 'sessions',
            'count_callback' => 'sr_admin_retention_module_upload_work_dir_count',
            'delete_callback' => 'sr_admin_retention_delete_module_upload_work_dirs',
        ],
        'module_backups' => [
            'enabled' => true,
            'auto_scope' => 'admin',
            'cutoff_key' => 'module_backups',
            'count_callback' => 'sr_admin_retention_module_backup_count',
            'delete_callback' => 'sr_admin_retention_delete_module_backups',
        ],
    ];

    if ($pdo !== null) {
        $targets = array_merge($targets, sr_admin_retention_module_target_definitions($pdo));
    }

    return $targets;
}

function sr_admin_retention_module_target_definitions(PDO $pdo): array
{
    if (!function_exists('sr_installed_module_contract_files') || !function_exists('sr_load_module_contract_file')) {
        return [];
    }

    $targets = [];
    foreach (sr_installed_module_contract_files($pdo, 'retention-targets.php') as $moduleKey => $contractFile) {
        $contractTargets = sr_load_module_contract_file($moduleKey, $contractFile);
        if (!is_array($contractTargets)) {
            continue;
        }

        foreach ($contractTargets as $targetKey => $target) {
            if (!is_string($targetKey) || !is_array($target)) {
                continue;
            }

            $tableChecks = isset($target['table_checks']) && is_array($target['table_checks']) ? $target['table_checks'] : [];
            unset($target['table_checks']);
            $target['enabled'] = sr_admin_retention_table_checks_pass($pdo, $tableChecks);
            $targets[$targetKey] = $target;
        }
    }

    return $targets;
}

function sr_admin_retention_table_checks_pass(PDO $pdo, array $tableChecks): bool
{
    foreach ($tableChecks as $sql) {
        if (!is_string($sql) || trim($sql) === '') {
            return false;
        }

        try {
            $pdo->query($sql);
        } catch (Throwable) {
            return false;
        }
    }

    return true;
}

function sr_admin_retention_available_target_definitions(PDO $pdo): array
{
    return sr_admin_retention_target_definitions(
        false,
        false,
        sr_admin_retention_runtime_sessions_table_exists($pdo),
        sr_admin_retention_rate_limits_table_exists($pdo),
        false,
        false,
        false,
        false,
        $pdo
    );
}

function sr_admin_retention_cleanup_target_keys(?string $autoScope = null, ?PDO $pdo = null): array
{
    $targetKeys = [
        'auth_logs',
        'audit_logs',
        'password_resets',
        'email_verifications',
        'sessions',
        'runtime_sessions',
        'rate_limits',
        'content_asset_access_pending_logs',
        'content_asset_action_pending_logs',
        'community_asset_pending_logs',
        'module_upload_work_dirs',
        'banner_clicks',
        'notification_deliveries',
        'notification_reads',
        'notifications',
        'admin_notification_reads',
        'admin_notifications',
        'module_backups',
    ];

    $targets = null;
    if ($pdo !== null) {
        $targets = sr_admin_retention_available_target_definitions($pdo);
        foreach (array_keys($targets) as $targetKey) {
            if (!in_array($targetKey, $targetKeys, true)) {
                $targetKeys[] = $targetKey;
            }
        }
    }

    if ($autoScope === null) {
        return $targetKeys;
    }

    $scopedKeys = [];
    $targets = $targets ?? sr_admin_retention_target_definitions(true, true, true, true);
    foreach ($targetKeys as $targetKey) {
        if (isset($targets[$targetKey]) && (string) ($targets[$targetKey]['auto_scope'] ?? '') === $autoScope) {
            $scopedKeys[] = $targetKey;
        }
    }

    return $scopedKeys;
}

function sr_admin_retention_query_params(array $paramCutoffKeys, array $cutoffs): array
{
    $params = [];
    foreach ($paramCutoffKeys as $paramName => $cutoffKey) {
        $params[$paramName] = $cutoffs[$cutoffKey];
    }

    return $params;
}

function sr_admin_module_backup_root(): string
{
    return SR_ROOT . '/storage/module-backups';
}

function sr_admin_module_upload_work_root(): string
{
    return SR_ROOT . '/storage/module-upload';
}

function sr_admin_module_work_dir_is_safe(string $directory, string $root): bool
{
    if ($directory === '' || is_link($directory) || !is_dir($directory)) {
        return false;
    }

    $realRoot = realpath($root);
    $realDirectory = realpath($directory);
    if ($realRoot === false || $realDirectory === false) {
        return false;
    }

    return $realDirectory !== $realRoot && strpos($realDirectory, $realRoot . DIRECTORY_SEPARATOR) === 0;
}

function sr_admin_module_backup_dir_is_safe(string $directory): bool
{
    return sr_admin_module_work_dir_is_safe($directory, sr_admin_module_backup_root());
}

function sr_admin_module_upload_work_dir_is_safe(string $directory): bool
{
    return sr_admin_module_work_dir_is_safe($directory, sr_admin_module_upload_work_root())
        && preg_match('/\Aupload-\d{14}-[a-f0-9]{12}\z/', basename($directory)) === 1;
}

function sr_admin_module_work_dirs(string $root, callable $isSafe): array
{
    if (!is_dir($root)) {
        return [];
    }

    $directories = glob($root . '/*', GLOB_ONLYDIR);
    if (!is_array($directories)) {
        return [];
    }

    $safeDirectories = [];
    foreach ($directories as $directory) {
        if ($isSafe($directory)) {
            $safeDirectories[] = $directory;
        }
    }

    sort($safeDirectories, SORT_STRING);
    return $safeDirectories;
}

function sr_admin_module_backup_dirs(): array
{
    return sr_admin_module_work_dirs(sr_admin_module_backup_root(), 'sr_admin_module_backup_dir_is_safe');
}

function sr_admin_module_upload_work_dirs(): array
{
    return sr_admin_module_work_dirs(sr_admin_module_upload_work_root(), 'sr_admin_module_upload_work_dir_is_safe');
}

function sr_admin_retention_old_module_dirs(array $directories, string $cutoff): array
{
    $cutoffTime = strtotime($cutoff);
    if ($cutoffTime === false) {
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

function sr_admin_retention_module_backup_dirs(string $cutoff): array
{
    return sr_admin_retention_old_module_dirs(sr_admin_module_backup_dirs(), $cutoff);
}

function sr_admin_retention_module_upload_work_dirs(string $cutoff): array
{
    return sr_admin_retention_old_module_dirs(sr_admin_module_upload_work_dirs(), $cutoff);
}

function sr_admin_retention_module_backup_count(string $cutoff): int
{
    return count(sr_admin_retention_module_backup_dirs($cutoff));
}

function sr_admin_retention_module_upload_work_dir_count(string $cutoff): int
{
    return count(sr_admin_retention_module_upload_work_dirs($cutoff));
}

function sr_admin_retention_delete_module_directories(array $directories, ?int $limit = null): int
{
    $deletedCount = 0;
    $deleteLimit = $limit !== null ? max(0, $limit) : null;

    foreach ($directories as $directory) {
        if ($deleteLimit !== null && $deletedCount >= $deleteLimit) {
            break;
        }

        sr_remove_directory($directory);
        $deletedCount++;
    }

    return $deletedCount;
}

function sr_admin_retention_delete_module_backups(string $cutoff, ?int $limit = null): int
{
    return sr_admin_retention_delete_module_directories(sr_admin_retention_module_backup_dirs($cutoff), $limit);
}

function sr_admin_retention_delete_module_upload_work_dirs(string $cutoff, ?int $limit = null): int
{
    return sr_admin_retention_delete_module_directories(sr_admin_retention_module_upload_work_dirs($cutoff), $limit);
}

function sr_admin_retention_delete_count(PDO $pdo, string $sql, array $params): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function sr_admin_retention_limited_sql(array $target, ?int $limit): string
{
    if ($limit === null) {
        return (string) $target['delete_sql'];
    }

    $limitedSql = (string) ($target['delete_limited_sql'] ?? '');
    if ($limitedSql === '') {
        return (string) $target['delete_sql'];
    }

    return str_replace('{limit}', (string) max(1, $limit), $limitedSql);
}

function sr_admin_retention_delete_target(PDO $pdo, array $target, array $cutoffs, ?int $limit = null): int
{
    $deleteCallback = (string) ($target['delete_callback'] ?? '');
    if ($deleteCallback !== '') {
        return $deleteCallback($cutoffs[$target['cutoff_key']], $limit);
    }

    return sr_admin_retention_delete_count(
        $pdo,
        sr_admin_retention_limited_sql($target, $limit),
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
        'banner_clicks' => sr_admin_retention_cutoff($values['banner_clicks_days']),
        'notifications' => sr_admin_retention_cutoff($values['notifications_days']),
        'module_backups' => sr_admin_retention_cutoff($values['module_backups_days']),
    ];
}

function sr_admin_retention_preview_counts(PDO $pdo, array $previewCutoffs): array
{
    $previewCounts = [];
    $targets = sr_admin_retention_available_target_definitions($pdo);

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

function sr_admin_retention_execute_cleanup(PDO $pdo, array $values, ?int $limitPerTarget = null, ?string $autoScope = null): array
{
    $cutoffs = sr_admin_retention_preview_cutoffs($values);
    $targets = sr_admin_retention_available_target_definitions($pdo);
    $deletedCounts = [];
    foreach (sr_admin_retention_cleanup_target_keys($autoScope, $pdo) as $key) {
        if (!isset($targets[$key])) {
            continue;
        }

        $target = $targets[$key];
        if (!$target['enabled']) {
            continue;
        }

        $deletedCounts[$key] = sr_admin_retention_delete_target($pdo, $target, $cutoffs, $limitPerTarget);
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
            'days' => sr_admin_retention_auto_cleanup_days($values),
            'deleted' => $deletedCounts,
        ],
    ]);
}

function sr_admin_retention_auto_cleanup_scopes(): array
{
    return ['public', 'admin'];
}

function sr_admin_retention_auto_cleanup_scope_valid(string $autoScope): bool
{
    return in_array($autoScope, sr_admin_retention_auto_cleanup_scopes(), true);
}

function sr_admin_retention_last_auto_cleanup_setting_key(string $autoScope): string
{
    return 'admin.retention.last_auto_cleanup_at.' . $autoScope;
}

function sr_admin_retention_last_auto_cleanup_failure_setting_key(string $autoScope, string $field): string
{
    return 'admin.retention.last_auto_cleanup_failure_' . $field . '.' . $autoScope;
}

function sr_admin_retention_record_auto_cleanup_failure(PDO $pdo, string $autoScope, Throwable $exception): void
{
    if (!sr_admin_retention_auto_cleanup_scope_valid($autoScope)) {
        return;
    }

    $message = sr_clean_single_line($exception->getMessage(), 300);
    if ($message === '') {
        $message = 'unknown failure';
    }

    try {
        sr_save_site_settings($pdo, [
            sr_admin_retention_last_auto_cleanup_failure_setting_key($autoScope, 'at') => [
                'value' => sr_now(),
                'type' => 'string',
            ],
            sr_admin_retention_last_auto_cleanup_failure_setting_key($autoScope, 'message') => [
                'value' => $message,
                'type' => 'string',
            ],
        ]);
    } catch (Throwable $ignored) {
        return;
    }
}

function sr_admin_retention_auto_cleanup_runtime_status(PDO $pdo): array
{
    $rows = [];
    foreach (sr_admin_retention_auto_cleanup_scopes() as $autoScope) {
        $rows[$autoScope] = [
            'scope' => $autoScope,
            'last_success_at' => (string) sr_site_setting($pdo, sr_admin_retention_last_auto_cleanup_setting_key($autoScope), ''),
            'last_failure_at' => (string) sr_site_setting($pdo, sr_admin_retention_last_auto_cleanup_failure_setting_key($autoScope, 'at'), ''),
            'last_failure_message' => (string) sr_site_setting($pdo, sr_admin_retention_last_auto_cleanup_failure_setting_key($autoScope, 'message'), ''),
        ];
    }

    return $rows;
}

function sr_admin_retention_auto_cleanup_due(PDO $pdo, array $values, string $autoScope): bool
{
    if ((int) ($values['auto_cleanup_enabled'] ?? 0) !== 1) {
        return false;
    }

    if (!sr_admin_retention_auto_cleanup_scope_valid($autoScope)) {
        return false;
    }

    $lastCleanupAt = (string) sr_site_setting($pdo, sr_admin_retention_last_auto_cleanup_setting_key($autoScope), '');
    $lastCleanupTime = $lastCleanupAt === '' ? false : strtotime($lastCleanupAt);
    if ($lastCleanupTime === false) {
        return true;
    }

    $intervalSeconds = max(1, (int) ($values['auto_cleanup_interval_hours'] ?? 24)) * 3600;
    return time() - $lastCleanupTime >= $intervalSeconds;
}

function sr_admin_retention_auto_cleanup_lock_acquire(PDO $pdo, string $autoScope): bool
{
    if (!sr_admin_retention_auto_cleanup_scope_valid($autoScope)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare('SELECT GET_LOCK(:lock_name, 0) AS lock_acquired');
        $stmt->execute(['lock_name' => 'saanraan_retention_auto_cleanup_' . $autoScope]);
        $row = $stmt->fetch();
    } catch (Throwable $exception) {
        return false;
    }

    return is_array($row) && (string) ($row['lock_acquired'] ?? '') === '1';
}

function sr_admin_retention_auto_cleanup_lock_release(PDO $pdo, string $autoScope): void
{
    if (!sr_admin_retention_auto_cleanup_scope_valid($autoScope)) {
        return;
    }

    try {
        $stmt = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $stmt->execute(['lock_name' => 'saanraan_retention_auto_cleanup_' . $autoScope]);
    } catch (Throwable $ignored) {
    }
}

function sr_admin_retention_auto_cleanup_days(array $values): array
{
    $days = [];
    foreach (sr_admin_retention_day_value_keys() as $key) {
        $days[$key] = (int) ($values[$key] ?? 0);
    }

    return $days;
}

function sr_admin_retention_maybe_run_auto_cleanup(PDO $pdo, string $autoScope): void
{
    if (!sr_admin_retention_auto_cleanup_scope_valid($autoScope)) {
        return;
    }

    $values = sr_admin_retention_values($pdo);
    if (!sr_admin_retention_auto_cleanup_due($pdo, $values, $autoScope)) {
        return;
    }

    if (!sr_admin_retention_auto_cleanup_lock_acquire($pdo, $autoScope)) {
        return;
    }

    try {
        $values = sr_admin_retention_values($pdo);
        if (!sr_admin_retention_auto_cleanup_due($pdo, $values, $autoScope)) {
            return;
        }

        $now = sr_now();
        sr_save_site_setting($pdo, sr_admin_retention_last_auto_cleanup_setting_key($autoScope), $now, 'string');

        $batchSize = max(1, min(5000, (int) ($values['auto_cleanup_batch_size'] ?? 200)));
        $deletedCounts = sr_admin_retention_execute_cleanup(
            $pdo,
            $values,
            $batchSize,
            $autoScope
        );

        sr_audit_log($pdo, [
            'actor_account_id' => null,
            'actor_type' => 'system',
            'event_type' => 'retention.auto_cleanup.completed',
            'target_type' => 'retention',
            'target_id' => $autoScope,
            'result' => 'success',
            'message' => 'Retention auto cleanup completed.',
            'metadata' => [
                'days' => sr_admin_retention_auto_cleanup_days($values),
                'deleted' => $deletedCounts,
                'scope' => $autoScope,
                'interval_hours' => (int) ($values['auto_cleanup_interval_hours'] ?? 24),
                'batch_size' => $batchSize,
            ],
        ]);
    } catch (Throwable $exception) {
        sr_admin_retention_record_auto_cleanup_failure($pdo, $autoScope, $exception);
        sr_log_exception($exception, 'retention_auto_cleanup_failed');
    } finally {
        sr_admin_retention_auto_cleanup_lock_release($pdo, $autoScope);
    }
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

function sr_admin_handle_retention_post(PDO $pdo, array $account, array $currentValues): array
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
            $deletedCounts = sr_admin_retention_execute_cleanup($pdo, $values);
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
