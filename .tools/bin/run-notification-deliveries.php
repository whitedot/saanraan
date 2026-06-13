#!/usr/bin/env php
<?php

declare(strict_types=1);

define('SR_ROOT', dirname(__DIR__, 2));
chdir(SR_ROOT);

require_once SR_ROOT . '/core/helpers.php';

if (!sr_is_installed()) {
    fwrite(STDERR, "saanraan is not installed.\n");
    exit(2);
}

try {
    $config = sr_load_config();
    sr_set_runtime_config($config);
    sr_apply_runtime_config($config);
    $pdo = sr_db($config);
    if (!sr_module_enabled($pdo, 'notification')) {
        fwrite(STDERR, "notification module is not enabled.\n");
        exit(2);
    }

    require_once SR_ROOT . '/modules/notification/helpers.php';
    $site = sr_load_site($pdo);
    $settings = sr_notification_settings($pdo);
    $batchSize = (int) ($settings['delivery_cli_batch_size'] ?? 20);
    $result = sr_notification_run_delivery_batch($pdo, is_array($site) ? $site : [], $batchSize, 'cli:' . getmypid());

    echo 'claimed=' . (string) (int) ($result['claimed'] ?? 0)
        . ' sent=' . (string) (int) ($result['sent'] ?? 0)
        . ' retry=' . (string) (int) ($result['failed'] ?? 0)
        . ' dead=' . (string) (int) ($result['dead'] ?? 0)
        . "\n";
} catch (Throwable $exception) {
    sr_log_exception($exception, 'notification_delivery_cli');
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
