<?php

declare(strict_types=1);

define('SR_ROOT', __DIR__);

if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (
        is_string($requestPath)
        && (
            str_starts_with($requestPath, '/assets/')
            || preg_match('#\A/modules/[a-z][a-z0-9_]{1,39}/assets/#', $requestPath) === 1
        )
    ) {
        $staticPath = realpath(SR_ROOT . $requestPath);
        if (is_string($staticPath) && str_starts_with($staticPath, SR_ROOT . DIRECTORY_SEPARATOR) && is_file($staticPath)) {
            return false;
        }
    }
}

require SR_ROOT . '/core/helpers.php';
sr_send_security_headers();

set_exception_handler(function (Throwable $exception): void {
    sr_render_error(500, '서버 오류가 발생했습니다.', $exception);
});

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

$method = sr_request_method();
$path = sr_request_path();

if (!sr_is_installed()) {
    sr_start_session();
    include SR_ROOT . '/core/actions/install.php';
    exit;
}

$config = sr_load_config();
sr_set_runtime_config($config);
sr_apply_runtime_config($config);
sr_send_security_headers($config);

try {
    $pdo = sr_db($config);
    $site = sr_load_site($pdo);
    sr_apply_site_runtime_settings($site);
    sr_start_session($config, $pdo);
    sr_set_locale(sr_resolve_locale($pdo, $site));
} catch (Throwable $exception) {
    sr_render_error(500, 'DB 연결 또는 사이트 설정을 확인할 수 없습니다.', $exception);
    exit;
}

$autoCleanupScope = null;
if ($method === 'GET') {
    $autoCleanupScope = $path === '/admin' || str_starts_with($path, '/admin/') ? 'admin' : 'public';
}

if ($autoCleanupScope !== null) {
    $autoCleanupEnabled = sr_site_setting($pdo, 'admin.retention.auto_cleanup_enabled', true);
    $autoCleanupEnabled = in_array($autoCleanupEnabled, [true, 1, '1', 'true', 'yes', 'on'], true);
    $autoCleanupLastAt = (string) sr_site_setting($pdo, 'admin.retention.last_auto_cleanup_at.' . $autoCleanupScope, '');
    $autoCleanupLastTime = $autoCleanupLastAt === '' ? false : strtotime($autoCleanupLastAt);
    $autoCleanupInterval = sr_site_setting($pdo, 'admin.retention.auto_cleanup_interval_hours', 24);
    $autoCleanupIntervalHours = is_int($autoCleanupInterval) ? $autoCleanupInterval : (ctype_digit((string) $autoCleanupInterval) ? (int) $autoCleanupInterval : 24);
    $autoCleanupDue = $autoCleanupLastTime === false || time() - $autoCleanupLastTime >= max(1, $autoCleanupIntervalHours) * 3600;
    if ($autoCleanupEnabled && $autoCleanupDue && sr_module_enabled($pdo, 'admin')) {
        require_once SR_ROOT . '/modules/member/helpers.php';
        require_once SR_ROOT . '/modules/admin/helpers.php';
        sr_admin_retention_maybe_run_auto_cleanup($pdo, $autoCleanupScope);
    }
}

if (
    $site !== null
    && $site['status'] === 'maintenance'
    && $path !== '/login'
    && $path !== '/logout'
    && strpos($path, '/admin') !== 0
) {
    sr_render_error(503, '현재 점검 중입니다.');
    exit;
}

if ($path === '/') {
    $homePath = is_array($site) ? (string) ($site['home_path'] ?? '/') : '/';
    if ($homePath !== '/' && sr_site_home_path_is_available($pdo, $homePath)) {
        sr_redirect($homePath);
    }

    include SR_ROOT . '/core/views/home.php';
    exit;
}

if ($method === 'GET' && $path === '/ui-kit') {
    $uiKitFile = sr_public_layout_optional_view_file(sr_public_layout_key($site, $pdo), 'ui_kit', $pdo);
    if ($uiKitFile === null) {
        sr_render_error(404, '요청한 화면을 찾을 수 없습니다.');
        exit;
    }

    include $uiKitFile;
    exit;
}

$routeKey = $method . ' ' . $path;
$routeMatches = [];

foreach (sr_enabled_module_contract_files($pdo, 'paths.php') as $moduleKey => $pathsFile) {
    $moduleDir = SR_ROOT . '/modules/' . $moduleKey;

    $paths = sr_load_module_contract_file($moduleKey, $pathsFile);
    if (!is_array($paths)) {
        continue;
    }

    $matchedActionRelativePath = isset($paths[$routeKey]) ? (string) $paths[$routeKey] : null;
    $matchedRoute = $matchedActionRelativePath === null ? '' : $routeKey;
    if ($matchedActionRelativePath === null) {
        foreach ($paths as $route => $actionRelativePath) {
            $route = (string) $route;
            if (!sr_module_route_matches_request($route, $routeKey)) {
                continue;
            }

            $matchedActionRelativePath = (string) $actionRelativePath;
            $matchedRoute = $route;
            break;
        }
    }

    if ($matchedActionRelativePath === null) {
        continue;
    }

    $actionRelativePath = $matchedActionRelativePath;
    if (!sr_is_safe_module_action($actionRelativePath)) {
        sr_render_error(500, '모듈 action 경로가 올바르지 않습니다.');
        exit;
    }

    $actionFile = $moduleDir . '/' . $actionRelativePath;
    $realModuleDir = realpath($moduleDir);
    $realActionFile = realpath($actionFile);

    if ($realModuleDir === false || $realActionFile === false || strpos($realActionFile, $realModuleDir . DIRECTORY_SEPARATOR) !== 0) {
        sr_render_error(404, '요청한 화면을 찾을 수 없습니다.');
        exit;
    }

    $routeMatches[] = [
        'module_key' => $moduleKey,
        'route' => $matchedRoute,
        'action_file' => $realActionFile,
    ];
}

if (count($routeMatches) > 1) {
    $conflicts = array_map(static function (array $match): string {
        return (string) $match['module_key'];
    }, $routeMatches);

    sr_log_exception(
        new RuntimeException('Route conflict: ' . $routeKey . ' -> ' . implode(', ', $conflicts)),
        'module_route_conflict'
    );
    sr_render_error(500, '모듈 요청 경로가 중복되었습니다.');
    exit;
}

if (count($routeMatches) === 1) {
    sr_start_request_contract($method, $path, (string) $routeMatches[0]['module_key'], (string) $routeMatches[0]['action_file']);
    include $routeMatches[0]['action_file'];
    sr_enforce_request_contract('after_action');
    exit;
}

sr_render_error(404, '요청한 화면을 찾을 수 없습니다.');
