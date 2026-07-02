#!/usr/bin/env php
<?php

declare(strict_types=1);

define('SR_ROOT', dirname(__DIR__, 2));
chdir(SR_ROOT);

require_once SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/point/helpers.php';

$args = array_slice($argv, 1);
if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
    echo "Usage: php .tools/bin/expire-points.php [--dry-run] [limit]\n";
    echo "Expires due point grants up to limit. Use --dry-run to preview due_count and due_amount without mutation.\n";
    echo "Exit codes: 0 success, 1 runtime failure, 2 environment/configuration or argument issue.\n";
    exit(0);
}

if (!sr_is_installed()) {
    fwrite(STDERR, "saanraan is not installed.\n");
    exit(2);
}

$dryRun = in_array('--dry-run', $args, true);
$limit = 200;
foreach ($args as $argument) {
    if ($argument !== '--dry-run') {
        if (!ctype_digit($argument)) {
            fwrite(STDERR, "Unknown option or invalid limit: " . $argument . "\n");
            fwrite(STDERR, "Run php .tools/bin/expire-points.php --help for usage.\n");
            exit(2);
        }
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
