<?php

declare(strict_types=1);

function sr_request_bootstrap_cli_static_asset(string $root): bool
{
    if (PHP_SAPI !== 'cli-server') {
        return false;
    }

    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (
        !is_string($requestPath)
        || (
            !str_starts_with($requestPath, '/assets/')
            && preg_match('#\A/modules/[a-z][a-z0-9_]{1,39}/assets/#', $requestPath) !== 1
            && !in_array($requestPath, ['/modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js', '/modules/ckeditor/vendor/ckeditor5/ckeditor5.css'], true)
        )
    ) {
        return false;
    }

    $staticPath = realpath($root . $requestPath);
    return is_string($staticPath) && str_starts_with($staticPath, $root . DIRECTORY_SEPARATOR) && is_file($staticPath);
}

function sr_request_bootstrap_error_handlers(): void
{
    set_exception_handler(function (Throwable $exception): void {
        sr_render_error(500, '서버 오류가 발생했습니다.', $exception);
    });

    set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    });
}

function sr_request_bootstrap_config(): array
{
    $config = sr_load_config();
    sr_set_runtime_config($config);
    sr_apply_runtime_config($config);
    sr_send_security_headers($config);

    return $config;
}

function sr_request_bootstrap_site(array $config): array
{
    $pdo = sr_db($config);
    $site = sr_load_site($pdo);
    sr_apply_site_runtime_settings($site);
    sr_start_session($config, $pdo);
    sr_set_locale(sr_resolve_locale($pdo, $site));

    return [$pdo, $site];
}

function sr_request_bootstrap_retention_cleanup(PDO $pdo, string $method, string $path): void
{
    $autoCleanupScope = null;
    if ($method === 'GET') {
        $autoCleanupScope = $path === '/admin' || str_starts_with($path, '/admin/') ? 'admin' : 'public';
    }

    if ($autoCleanupScope === null) {
        return;
    }

    $autoCleanupEnabled = sr_site_setting($pdo, 'admin.retention.auto_cleanup_enabled', true);
    $autoCleanupEnabled = in_array($autoCleanupEnabled, [true, 1, '1', 'true', 'yes', 'on'], true);
    $autoCleanupLastAt = (string) sr_site_setting($pdo, 'admin.retention.last_auto_cleanup_at.' . $autoCleanupScope, '');
    $autoCleanupLastTime = $autoCleanupLastAt === '' ? false : strtotime($autoCleanupLastAt);
    $autoCleanupInterval = sr_site_setting($pdo, 'admin.retention.auto_cleanup_interval_hours', 24);
    $autoCleanupIntervalHours = is_int($autoCleanupInterval) ? $autoCleanupInterval : (ctype_digit((string) $autoCleanupInterval) ? (int) $autoCleanupInterval : 24);
    $autoCleanupDue = $autoCleanupLastTime === false || time() - $autoCleanupLastTime >= max(1, $autoCleanupIntervalHours) * 3600;

    if (!$autoCleanupEnabled || !$autoCleanupDue || !sr_module_enabled($pdo, 'admin')) {
        return;
    }

    require_once SR_ROOT . '/modules/member/helpers.php';
    require_once SR_ROOT . '/modules/admin/helpers.php';
    sr_admin_retention_maybe_run_auto_cleanup($pdo, $autoCleanupScope);
}

function sr_request_bootstrap_notification_runner(PDO $pdo, ?array $site, string $method, string $path): void
{
    if (!in_array($method, ['GET', 'POST'], true) || !sr_module_enabled($pdo, 'notification') || !is_file(SR_ROOT . '/modules/notification/helpers.php')) {
        return;
    }

    require_once SR_ROOT . '/modules/notification/helpers.php';
    sr_notification_register_web_delivery_runner($pdo, is_array($site) ? $site : [], $method, $path);
}
