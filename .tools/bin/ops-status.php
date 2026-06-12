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
        echo sr_admin_operational_status_cli_row_line($row) . "\n";
    }
    echo sr_admin_operational_status_cli_summary_line(sr_admin_operational_status_summary($rows)) . "\n";

    echo "ops status completed.\n";
} catch (Throwable $exception) {
    sr_log_exception($exception, 'ops_status_cli');
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
