#!/usr/bin/env php
<?php

declare(strict_types=1);

define('SR_ROOT', dirname(__DIR__, 2));
chdir(SR_ROOT);

require_once SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers/operational-status.php';

if (!sr_is_installed()) {
    fwrite(STDERR, "saanraan is not installed.\n");
    exit(2);
}

try {
    $config = sr_load_config();
    sr_set_runtime_config($config);
    sr_apply_runtime_config($config);
    $pdo = sr_db($config);

    $rows = sr_admin_operational_status_rows($pdo);
    foreach ($rows as $row) {
        sr_ops_status_print_row($row);
    }
    sr_ops_status_print_summary(sr_admin_operational_status_summary($rows));

    echo "ops status completed.\n";
} catch (Throwable $exception) {
    sr_log_exception($exception, 'ops_status_cli');
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

function sr_ops_status_print_row(array $row): void
{
    $label = (string) ($row['label'] ?? '');
    $status = (string) ($row['status'] ?? 'error');
    if ($status === 'skipped' || $status === 'error') {
        echo $label . "\t" . $status . "\t" . sr_ops_status_single_line((string) ($row['message'] ?? '')) . "\n";
        return;
    }

    echo $label
        . "\tstatus=" . $status
        . "\tcount=" . (int) ($row['count'] ?? 0)
        . "\tallowed_delay=" . sr_ops_status_value((string) ($row['delay_tolerance'] ?? ''))
        . "\toldest_at=" . sr_ops_status_value((string) ($row['oldest_at'] ?? ''))
        . "\n";
}

function sr_ops_status_print_summary(array $summary): void
{
    echo 'summary'
        . "\tok=" . (int) ($summary['ok'] ?? 0)
        . "\twarning=" . (int) ($summary['warning'] ?? 0)
        . "\toverdue=" . (int) ($summary['overdue'] ?? 0)
        . "\tskipped=" . (int) ($summary['skipped'] ?? 0)
        . "\terror=" . (int) ($summary['error'] ?? 0)
        . "\ttotal_count=" . (int) ($summary['total_count'] ?? 0)
        . "\n";
}

function sr_ops_status_value(string $value): string
{
    $value = trim($value);
    return $value === '' ? '-' : sr_ops_status_single_line($value);
}

function sr_ops_status_single_line(string $value): string
{
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return substr($value, 0, 180);
}
