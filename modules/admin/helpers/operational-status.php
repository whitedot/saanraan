<?php

declare(strict_types=1);

function sr_admin_operational_status_checks(?PDO $pdo = null): array
{
    $checks = sr_admin_operational_status_core_checks();
    if ($pdo === null || !function_exists('sr_enabled_module_contract_files') || !function_exists('sr_load_module_contract_file')) {
        return $checks;
    }

    foreach (sr_enabled_module_contract_files($pdo, 'operational-status.php') as $moduleKey => $contractFile) {
        $contractChecks = sr_load_module_contract_file($moduleKey, $contractFile);
        if (!is_array($contractChecks)) {
            continue;
        }

        foreach ($contractChecks as $check) {
            if (is_array($check)) {
                $checks[] = $check;
            }
        }
    }

    return $checks;
}

function sr_admin_operational_status_core_checks(): array
{
    return [];
}

function sr_admin_operational_status_rows(PDO $pdo, bool $applyAcknowledgements = false): array
{
    $rows = [];
    foreach (sr_admin_operational_status_checks($pdo) as $check) {
        $rows[] = sr_admin_operational_status_row($pdo, $check);
    }

    return $applyAcknowledgements ? sr_admin_operational_status_apply_acknowledgements($pdo, $rows) : $rows;
}

function sr_admin_operational_status_row(PDO $pdo, array $check): array
{
    $label = (string) ($check['label'] ?? '');
    $moduleKey = (string) ($check['module'] ?? '');
    $table = (string) ($check['table'] ?? '');
    $where = (string) ($check['where'] ?? '1 = 1');
    $ageColumn = (string) ($check['age_column'] ?? 'updated_at');

    $row = [
        'label' => $label,
        'title' => (string) ($check['title'] ?? $label),
        'module' => $moduleKey,
        'table' => $table,
        'count' => 0,
        'oldest_at' => '',
        'age_seconds' => null,
        'delay_tolerance' => (string) ($check['delay_tolerance'] ?? ''),
        'warn_after_seconds' => max(0, (int) ($check['warn_after_seconds'] ?? 0)),
        'status' => 'ok',
        'status_label' => '정상',
        'message' => '',
        'targets' => [],
        'followup' => (string) ($check['followup'] ?? ''),
        'action_url' => sr_admin_operational_status_action_url($check),
        'action_label' => sr_admin_operational_status_single_line((string) ($check['action_label'] ?? '처리 화면')),
    ];

    if ($moduleKey !== '' && !sr_module_enabled($pdo, $moduleKey)) {
        $row['status'] = 'skipped';
        $row['status_label'] = '건너뜀';
        $row['message'] = '모듈이 비활성화되어 있습니다.';
        return $row;
    }

    if (!sr_admin_operational_status_safe_identifier($table) || !sr_admin_operational_status_safe_identifier($ageColumn) || !sr_admin_operational_status_safe_where($where)) {
        $row['status'] = 'error';
        $row['status_label'] = '오류';
        $row['message'] = '점검 기준의 SQL 조건이 안전하지 않습니다.';
        return $row;
    }

    try {
        $whereSql = sr_admin_operational_status_where_sql($pdo, $where);
        $stmt = $pdo->query(
            'SELECT COUNT(*) AS item_count, MIN(' . $ageColumn . ') AS oldest_at
             FROM ' . $table . '
             WHERE ' . $whereSql
        );
        $result = $stmt ? $stmt->fetch() : false;
        if (!is_array($result)) {
            $row['status'] = 'error';
            $row['status_label'] = '오류';
            $row['message'] = '점검 결과를 읽을 수 없습니다.';
            return $row;
        }

        $row['count'] = (int) ($result['item_count'] ?? 0);
        $row['oldest_at'] = trim((string) ($result['oldest_at'] ?? ''));
        if ((int) $row['count'] > 0) {
            $row['status'] = 'warning';
            $row['status_label'] = '확인 필요';
            $row['message'] = '처리되지 않은 항목이 있습니다.';
            $row['age_seconds'] = sr_admin_operational_status_age_seconds((string) $row['oldest_at']);
            $row['targets'] = sr_admin_operational_status_targets($pdo, $check);
            if ((int) $row['warn_after_seconds'] === 0 || (is_int($row['age_seconds']) && $row['age_seconds'] >= (int) $row['warn_after_seconds'])) {
                $row['status'] = 'overdue';
                $row['status_label'] = '지연 초과';
                $row['message'] = '허용 지연 기준을 넘긴 항목이 있습니다.';
            }
        }
    } catch (Throwable $exception) {
        $row['status'] = 'skipped';
        $row['status_label'] = '건너뜀';
        $row['message'] = sr_admin_operational_status_single_line($exception->getMessage());
    }

    return $row;
}

function sr_admin_operational_status_targets(PDO $pdo, array $check): array
{
    $sql = trim((string) ($check['target_sql'] ?? ''));
    if ($sql === '' || !sr_admin_operational_status_safe_select($sql)) {
        return [];
    }

    try {
        $stmt = $pdo->query(sr_admin_operational_status_where_sql($pdo, $sql));
        if (!$stmt) {
            return [];
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }

    $targets = [];
    $fallbackPrefix = trim((string) ($check['target_fallback_prefix'] ?? ''));
    $format = trim((string) ($check['target_format'] ?? ''));
    $valueLabels = isset($check['target_value_labels']) && is_array($check['target_value_labels']) ? $check['target_value_labels'] : [];
    foreach ($rows as $row) {
        $row = sr_admin_operational_status_apply_target_value_labels($row, $valueLabels);
        $label = $format !== ''
            ? sr_admin_operational_status_format_target($row, $format)
            : sr_admin_operational_status_single_line((string) ($row['target_label'] ?? ''));
        $fallback = sr_admin_operational_status_single_line((string) ($row['target_fallback'] ?? ''));
        if ($label === '' && $fallback !== '') {
            $label = ($fallbackPrefix !== '' ? $fallbackPrefix . ' #' : '#') . $fallback;
        }
        if ($label !== '' && !in_array($label, $targets, true)) {
            $targets[] = $label;
        }
    }

    return $targets;
}

function sr_admin_operational_status_apply_target_value_labels(array $row, array $valueLabels): array
{
    foreach ($valueLabels as $key => $labels) {
        if (!is_string($key) || !is_array($labels) || !array_key_exists($key, $row)) {
            continue;
        }

        $value = (string) $row[$key];
        if ($value !== '' && array_key_exists($value, $labels)) {
            $row[$key] = sr_admin_operational_status_single_line((string) $labels[$value]);
        }
    }

    return $row;
}

function sr_admin_operational_status_action_url(array $check): string
{
    $url = trim((string) ($check['action_url'] ?? ''));
    if ($url === '') {
        return '';
    }

    if (function_exists('sr_is_safe_relative_url')) {
        return sr_is_safe_relative_url($url) ? $url : '';
    }

    if ($url[0] !== '/' || str_starts_with($url, '//') || strpos($url, '\\') !== false) {
        return '';
    }

    return preg_match('/[\x00-\x1F\x7F]/', $url) === 1 ? '' : $url;
}

function sr_admin_operational_status_format_target(array $row, string $format): string
{
    $hasValue = false;
    $label = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', static function (array $matches) use ($row, &$hasValue): string {
        $key = (string) ($matches[1] ?? '');
        $value = sr_admin_operational_status_single_line((string) ($row[$key] ?? ''));
        if ($value !== '') {
            $hasValue = true;
        }

        return $value;
    }, $format);

    if (!$hasValue) {
        return '';
    }

    return sr_admin_operational_status_single_line((string) $label);
}

function sr_admin_operational_status_age_seconds(string $value): ?int
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return max(0, time() - $timestamp);
}

function sr_admin_operational_status_safe_identifier(string $value): bool
{
    return preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]*\z/', $value) === 1;
}

function sr_admin_operational_status_where_sql(PDO $pdo, string $where): string
{
    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable) {
        $driver = '';
    }

    if ($driver === 'sqlite') {
        return preg_replace('/\bNOW\(\)/i', 'CURRENT_TIMESTAMP', $where) ?? $where;
    }

    return $where;
}

function sr_admin_operational_status_safe_where(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return false;
    }

    if (preg_match('/(?:;|--|\/\*|\*\/)/', $value) === 1) {
        return false;
    }

    return preg_match('/\b(?:ALTER|CREATE|DELETE|DROP|GRANT|INSERT|LOAD|OUTFILE|REPLACE|REVOKE|TRUNCATE|UPDATE)\b/i', $value) !== 1;
}

function sr_admin_operational_status_safe_select(string $value): bool
{
    $value = trim($value);
    if ($value === '' || preg_match('/\ASELECT\b/i', $value) !== 1) {
        return false;
    }

    if (preg_match('/(?:;|--|\/\*|\*\/)/', $value) === 1) {
        return false;
    }

    return preg_match('/\b(?:ALTER|CREATE|DELETE|DROP|GRANT|INSERT|LOAD|OUTFILE|REPLACE|REVOKE|TRUNCATE|UPDATE)\b/i', $value) !== 1;
}

function sr_admin_operational_status_single_line(string $value): string
{
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return substr(trim($value), 0, 180);
}

function sr_admin_operational_status_summary(array $rows): array
{
    $summary = [
        'ok' => 0,
        'warning' => 0,
        'overdue' => 0,
        'acknowledged' => 0,
        'skipped' => 0,
        'error' => 0,
        'total_count' => 0,
    ];

    foreach ($rows as $row) {
        $status = (string) ($row['status'] ?? 'error');
        if (!array_key_exists($status, $summary)) {
            $status = 'error';
        }
        $summary[$status]++;
        $summary['total_count'] += (int) ($row['count'] ?? 0);
    }

    return $summary;
}

function sr_admin_operational_status_acknowledgement_setting_key(): string
{
    return 'admin.operational_status.acknowledged';
}

function sr_admin_operational_status_fingerprint(array $row): string
{
    return hash('sha256', implode('|', [
        (string) ($row['label'] ?? ''),
        (string) ($row['status'] ?? ''),
        (string) (int) ($row['count'] ?? 0),
        (string) ($row['oldest_at'] ?? ''),
    ]));
}

function sr_admin_operational_status_acknowledgements(PDO $pdo): array
{
    try {
        $value = sr_site_setting($pdo, sr_admin_operational_status_acknowledgement_setting_key(), []);
    } catch (Throwable) {
        return [];
    }

    return is_array($value) ? $value : [];
}

function sr_admin_operational_status_save_acknowledgements(PDO $pdo, array $acknowledgements): void
{
    $encoded = json_encode($acknowledgements, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($encoded)) {
        throw new RuntimeException('운영 점검 확인 상태를 저장할 수 없습니다.');
    }

    $key = sr_admin_operational_status_acknowledgement_setting_key();
    $now = sr_now();
    $driver = '';
    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable) {
    }

    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_site_settings
                (setting_key, setting_value, value_type, created_at, updated_at)
             VALUES
                (:setting_key, :setting_value, :value_type, :created_at, :updated_at)
             ON CONFLICT(setting_key) DO UPDATE SET
                setting_value = excluded.setting_value,
                value_type = excluded.value_type,
                updated_at = excluded.updated_at'
        );
        $stmt->execute([
            'setting_key' => $key,
            'setting_value' => $encoded,
            'value_type' => 'json',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        sr_clear_site_settings_cache();
        return;
    }

    sr_save_site_setting($pdo, $key, $encoded, 'json');
}

function sr_admin_operational_status_apply_acknowledgements(PDO $pdo, array $rows): array
{
    $acknowledgements = sr_admin_operational_status_acknowledgements($pdo);
    if ($acknowledgements === []) {
        return $rows;
    }

    foreach ($rows as $index => $row) {
        $status = (string) ($row['status'] ?? '');
        if (!in_array($status, ['warning', 'overdue'], true)) {
            continue;
        }

        $label = (string) ($row['label'] ?? '');
        $acknowledgement = isset($acknowledgements[$label]) && is_array($acknowledgements[$label])
            ? $acknowledgements[$label]
            : [];
        if ((string) ($acknowledgement['fingerprint'] ?? '') !== sr_admin_operational_status_fingerprint($row)) {
            continue;
        }

        $disposition = (string) ($acknowledgement['disposition'] ?? 'acknowledged');
        $rows[$index]['original_status'] = $status;
        $rows[$index]['original_status_label'] = (string) ($row['status_label'] ?? '');
        if ($disposition === 'treated_ok') {
            $rows[$index]['status'] = 'ok';
            $rows[$index]['status_label'] = '정상 취급';
            $rows[$index]['message'] = '확인된 항목을 운영자가 정상으로 취급했습니다. 대상이나 건수가 바뀌면 다시 알립니다.';
        } else {
            $rows[$index]['status'] = 'acknowledged';
            $rows[$index]['status_label'] = '확인됨';
            $rows[$index]['message'] = '남아 있는 항목은 운영자가 확인했습니다. 대상이나 건수가 바뀌면 다시 알립니다.';
        }
        $rows[$index]['acknowledged_at'] = (string) ($acknowledgement['acknowledged_at'] ?? '');
        $rows[$index]['acknowledged_by'] = (int) ($acknowledgement['account_id'] ?? 0);
        $rows[$index]['disposition'] = $disposition === 'treated_ok' ? 'treated_ok' : 'acknowledged';
    }

    return $rows;
}

function sr_admin_operational_status_acknowledge_current(PDO $pdo, string $label, int $accountId): array
{
    $label = sr_admin_operational_status_single_line($label);
    if ($label === '') {
        return sr_admin_action_result(['확인할 운영 점검 항목을 선택해 주세요.']);
    }

    $rows = sr_admin_operational_status_rows($pdo, false);
    $targetRow = null;
    $activeLabels = [];
    foreach ($rows as $row) {
        $rowLabel = (string) ($row['label'] ?? '');
        if ($rowLabel !== '') {
            $activeLabels[$rowLabel] = true;
        }
        if ($rowLabel === $label) {
            $targetRow = $row;
        }
    }

    if (!is_array($targetRow)) {
        return sr_admin_action_result(['운영 점검 항목을 찾을 수 없습니다.']);
    }

    $status = (string) ($targetRow['status'] ?? '');
    if (!in_array($status, ['warning', 'overdue'], true)) {
        return sr_admin_action_result(['현재 상태에서는 확인 처리할 필요가 없습니다.']);
    }

    $acknowledgements = sr_admin_operational_status_acknowledgements($pdo);
    $acknowledgements = array_intersect_key($acknowledgements, $activeLabels);
    $acknowledgements[$label] = [
        'fingerprint' => sr_admin_operational_status_fingerprint($targetRow),
        'disposition' => 'acknowledged',
        'acknowledged_at' => sr_now(),
        'account_id' => $accountId,
    ];
    sr_admin_operational_status_save_acknowledgements($pdo, $acknowledgements);

    sr_audit_log($pdo, [
        'actor_account_id' => $accountId,
        'actor_type' => 'admin',
        'event_type' => 'admin.operational_status.acknowledged',
        'target_type' => 'operational_status',
        'target_id' => $label,
        'result' => 'success',
        'message' => 'Operational status signal acknowledged.',
        'metadata' => [
            'label' => $label,
            'status' => $status,
            'count' => (int) ($targetRow['count'] ?? 0),
            'oldest_at' => (string) ($targetRow['oldest_at'] ?? ''),
        ],
    ]);

    return sr_admin_action_result([], '운영 점검 항목을 확인됨으로 표시했습니다.');
}

function sr_admin_operational_status_treat_acknowledged_as_ok_current(PDO $pdo, string $label, int $accountId): array
{
    $label = sr_admin_operational_status_single_line($label);
    if ($label === '') {
        return sr_admin_action_result(['정상으로 취급할 운영 점검 항목을 선택해 주세요.']);
    }

    $rows = sr_admin_operational_status_rows($pdo, false);
    $targetRow = null;
    $activeLabels = [];
    foreach ($rows as $row) {
        $rowLabel = (string) ($row['label'] ?? '');
        if ($rowLabel !== '') {
            $activeLabels[$rowLabel] = true;
        }
        if ($rowLabel === $label) {
            $targetRow = $row;
        }
    }

    if (!is_array($targetRow)) {
        return sr_admin_action_result(['운영 점검 항목을 찾을 수 없습니다.']);
    }

    $status = (string) ($targetRow['status'] ?? '');
    if (!in_array($status, ['warning', 'overdue'], true)) {
        return sr_admin_action_result(['현재 상태에서는 정상으로 취급할 필요가 없습니다.']);
    }

    $acknowledgements = sr_admin_operational_status_acknowledgements($pdo);
    $acknowledgements = array_intersect_key($acknowledgements, $activeLabels);
    $acknowledgement = isset($acknowledgements[$label]) && is_array($acknowledgements[$label])
        ? $acknowledgements[$label]
        : [];
    if ((string) ($acknowledgement['fingerprint'] ?? '') !== sr_admin_operational_status_fingerprint($targetRow)
        || (string) ($acknowledgement['disposition'] ?? 'acknowledged') !== 'acknowledged'
    ) {
        return sr_admin_action_result(['확인됨 상태인 항목만 정상으로 취급할 수 있습니다.']);
    }

    $acknowledgements[$label]['disposition'] = 'treated_ok';
    $acknowledgements[$label]['treated_ok_at'] = sr_now();
    $acknowledgements[$label]['treated_ok_by'] = $accountId;
    sr_admin_operational_status_save_acknowledgements($pdo, $acknowledgements);

    sr_audit_log($pdo, [
        'actor_account_id' => $accountId,
        'actor_type' => 'admin',
        'event_type' => 'admin.operational_status.treated_ok',
        'target_type' => 'operational_status',
        'target_id' => $label,
        'result' => 'success',
        'message' => 'Operational status signal treated as normal.',
        'metadata' => [
            'label' => $label,
            'status' => $status,
            'count' => (int) ($targetRow['count'] ?? 0),
            'oldest_at' => (string) ($targetRow['oldest_at'] ?? ''),
        ],
    ]);

    return sr_admin_action_result([], '확인된 운영 점검 항목을 정상으로 취급했습니다.');
}

function sr_admin_operational_status_cli_row_line(array $row): string
{
    $label = (string) ($row['label'] ?? '');
    $status = (string) ($row['status'] ?? 'error');
    if ($status === 'skipped' || $status === 'error') {
        return $label . "\t" . $status . "\t" . sr_admin_operational_status_single_line((string) ($row['message'] ?? ''));
    }

    return $label
        . "\tstatus=" . $status
        . "\tcount=" . (int) ($row['count'] ?? 0)
        . "\tallowed_delay=" . sr_admin_operational_status_cli_value((string) ($row['delay_tolerance'] ?? ''))
        . "\toldest_at=" . sr_admin_operational_status_cli_value((string) ($row['oldest_at'] ?? ''));
}

function sr_admin_operational_status_cli_summary_line(array $summary): string
{
    return 'summary'
        . "\tok=" . (int) ($summary['ok'] ?? 0)
        . "\twarning=" . (int) ($summary['warning'] ?? 0)
        . "\toverdue=" . (int) ($summary['overdue'] ?? 0)
        . "\tskipped=" . (int) ($summary['skipped'] ?? 0)
        . "\terror=" . (int) ($summary['error'] ?? 0)
        . "\ttotal_count=" . (int) ($summary['total_count'] ?? 0);
}

function sr_admin_operational_status_cli_value(string $value): string
{
    $value = trim($value);
    return $value === '' ? '-' : sr_admin_operational_status_single_line($value);
}
