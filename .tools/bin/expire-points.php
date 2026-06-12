#!/usr/bin/env php
<?php

declare(strict_types=1);

define('SR_ROOT', dirname(__DIR__, 2));
chdir(SR_ROOT);

require_once SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/point/helpers.php';

if (!sr_is_installed()) {
    fwrite(STDERR, "saanraan is not installed.\n");
    exit(2);
}

$dryRun = in_array('--dry-run', array_slice($argv, 1), true);
$limit = 200;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument !== '--dry-run') {
        $limit = (int) $argument;
        break;
    }
}
$limit = max(1, min(1000, $limit));

try {
    $config = sr_load_config();
    sr_set_runtime_config($config);
    sr_apply_runtime_config($config);
    $pdo = sr_db($config);

    if (!sr_module_enabled($pdo, 'point')) {
        fwrite(STDERR, "point module is not enabled.\n");
        exit(2);
    }

    if ($dryRun) {
        $preview = sr_point_expire_due_preview_transactions($pdo, $limit);
        echo 'dry_run=yes' . "\n";
        echo 'due_count=' . (int) $preview['due_count'] . "\n";
        echo 'due_amount=' . (int) $preview['due_amount'] . "\n";
        exit(0);
    }

    $result = sr_point_expire_due_transactions($pdo, $limit);
    echo 'dry_run=no' . "\n";
    echo 'expired_count=' . (int) $result['expired_count'] . "\n";
    echo 'expired_amount=' . (int) $result['expired_amount'] . "\n";
} catch (Throwable $exception) {
    sr_log_exception($exception, 'point_expiration_cli');
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
