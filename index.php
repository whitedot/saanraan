<?php

declare(strict_types=1);

define('TOY_ROOT', __DIR__);

if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (
        is_string($requestPath)
        && (
            str_starts_with($requestPath, '/assets/')
            || preg_match('#\A/modules/[a-z][a-z0-9_]{1,39}/assets/#', $requestPath) === 1
        )
    ) {
        $staticPath = realpath(TOY_ROOT . $requestPath);
        if (is_string($staticPath) && str_starts_with($staticPath, TOY_ROOT . DIRECTORY_SEPARATOR) && is_file($staticPath)) {
            return false;
        }
    }
}

require TOY_ROOT . '/core/helpers.php';
toy_send_security_headers();

set_exception_handler(function (Throwable $exception): void {
    toy_render_error(500, '서버 오류가 발생했습니다.', $exception);
});

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

$method = toy_request_method();
$path = toy_request_path();

if (!toy_is_installed()) {
    toy_start_session();
    include TOY_ROOT . '/core/actions/install.php';
    exit;
}

$config = toy_load_config();
toy_set_runtime_config($config);
toy_apply_runtime_config($config);
toy_send_security_headers($config);

try {
    $pdo = toy_db($config);
    $site = toy_load_site($pdo);
    toy_apply_site_runtime_settings($site);
    toy_start_session($config, $pdo);
    toy_set_locale(toy_resolve_locale($pdo, $site));
} catch (Throwable $exception) {
    toy_render_error(500, 'DB 연결 또는 사이트 설정을 확인할 수 없습니다.', $exception);
    exit;
}

if (
    $site !== null
    && $site['status'] === 'maintenance'
    && $path !== '/login'
    && $path !== '/logout'
    && strpos($path, '/admin') !== 0
) {
    toy_render_error(503, '현재 점검 중입니다.');
    exit;
}

if ($path === '/') {
    include TOY_ROOT . '/core/views/home.php';
    exit;
}

$routeKey = $method . ' ' . $path;
$routeMatches = [];

foreach (toy_enabled_module_contract_files($pdo, 'paths.php') as $moduleKey => $pathsFile) {
    $moduleDir = TOY_ROOT . '/modules/' . $moduleKey;

    $paths = include $pathsFile;
    if (!is_array($paths)) {
        continue;
    }

    if (!isset($paths[$routeKey])) {
        continue;
    }

    $actionRelativePath = (string) $paths[$routeKey];
    if (!toy_is_safe_module_action($actionRelativePath)) {
        toy_render_error(500, '모듈 action 경로가 올바르지 않습니다.');
        exit;
    }

    $actionFile = $moduleDir . '/' . $actionRelativePath;
    $realModuleDir = realpath($moduleDir);
    $realActionFile = realpath($actionFile);

    if ($realModuleDir === false || $realActionFile === false || strpos($realActionFile, $realModuleDir . DIRECTORY_SEPARATOR) !== 0) {
        toy_render_error(404, '요청한 화면을 찾을 수 없습니다.');
        exit;
    }

    $routeMatches[] = [
        'module_key' => $moduleKey,
        'action_file' => $realActionFile,
    ];
}

if (count($routeMatches) > 1) {
    $conflicts = array_map(static function (array $match): string {
        return (string) $match['module_key'];
    }, $routeMatches);

    toy_log_exception(
        new RuntimeException('Route conflict: ' . $routeKey . ' -> ' . implode(', ', $conflicts)),
        'module_route_conflict'
    );
    toy_render_error(500, '모듈 요청 경로가 중복되었습니다.');
    exit;
}

if (count($routeMatches) === 1) {
    include $routeMatches[0]['action_file'];
    exit;
}

toy_render_error(404, '요청한 화면을 찾을 수 없습니다.');
