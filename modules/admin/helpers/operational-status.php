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

function sr_admin_operational_status_rows(PDO $pdo): array
{
    $rows = [];
    foreach (sr_admin_operational_status_checks($pdo) as $check) {
        $rows[] = sr_admin_operational_status_row($pdo, $check);
    }

    return $rows;
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
    foreach ($rows as $row) {
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
