#!/usr/bin/env php
<?php

declare(strict_types=1);

define('SR_ROOT', dirname(__DIR__, 2));
chdir(SR_ROOT);

require_once SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers/operational-status.php';

$args = array_slice($argv, 1);
if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
    echo "Usage: php .tools/bin/ops-status.php [--help]\n";
    echo "Prints read-only operational delay/failure rows and a summary for the installed site.\n";
    echo "Exit codes: 0 success, 1 runtime failure, 2 environment/configuration issue.\n";
    exit(0);
}
foreach ($args as $arg) {
    fwrite(STDERR, "Unknown option: " . $arg . "\n");
    fwrite(STDERR, "Run php .tools/bin/ops-status.php --help for usage.\n");
    exit(2);
}

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
        echo sr_admin_operational_status_cli_row_line($row) . "\n";
    }
    echo sr_admin_operational_status_cli_summary_line(sr_admin_operational_status_summary($rows)) . "\n";

    echo "ops status completed.\n";
} catch (Throwable $exception) {
    sr_log_exception($exception, 'ops_status_cli');
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
