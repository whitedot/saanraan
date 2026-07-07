<?php

declare(strict_types=1);

define('SR_ROOT', __DIR__);

require SR_ROOT . '/core/request-bootstrap.php';

if (sr_request_bootstrap_cli_static_asset(SR_ROOT)) {
    return false;
}

require SR_ROOT . '/core/helpers.php';
sr_send_security_headers();
sr_request_bootstrap_error_handlers();

$method = sr_request_method();
$path = sr_request_path();
$isFaviconRequest = in_array($method, ['GET', 'HEAD'], true) && $path === '/favicon.ico';
$isInstallPreviewRequest = in_array($method, ['GET', 'HEAD'], true)
    && $path === '/'
    && sr_get_string('sr_install_preview', 1) === '1';

if ($isFaviconRequest && !sr_is_installed()) {
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    http_response_code(404);
    header('Content-Type: image/x-icon');
    header('Content-Length: 0');
    exit;
}

if (!sr_is_installed()) {
    sr_start_session();
    include SR_ROOT . '/core/actions/install.php';
    exit;
}

$config = sr_request_bootstrap_config();
try {
    [$pdo, $site] = sr_request_bootstrap_site($config);
} catch (Throwable $exception) {
    sr_render_error(500, 'DB 연결 또는 사이트 설정을 확인할 수 없습니다.', $exception);
    exit;
}

sr_request_bootstrap_retention_cleanup($pdo, $method, $path);
sr_request_bootstrap_notification_runner($pdo, $site, $method, $path);

if ($isInstallPreviewRequest) {
    require_once SR_ROOT . '/modules/member/helpers.php';
    require_once SR_ROOT . '/modules/admin/helpers.php';

    $account = sr_member_current_account($pdo);
    if ($account === null) {
        sr_redirect('/login?next=' . rawurlencode('/?sr_install_preview=1'));
    }
    if (!sr_admin_is_owner($pdo, (int) $account['id'])) {
        sr_render_error(403, '설치 화면 미리보기는 소유자만 접근할 수 있습니다.');
    }

    $srInstallPreviewMode = true;
    include SR_ROOT . '/core/actions/install.php';
    exit;
}

if ($isFaviconRequest) {
    $faviconUrl = '';
    if (sr_module_enabled($pdo, 'logo_manager') && is_file(SR_ROOT . '/modules/logo_manager/helpers.php')) {
        require_once SR_ROOT . '/modules/logo_manager/helpers.php';
        $faviconLogo = sr_logo_manager_active_logo($pdo, 'public.favicon');
        if (is_array($faviconLogo)) {
            $faviconVariants = sr_logo_manager_icon_variants_by_logo($pdo, (int) ($faviconLogo['id'] ?? 0));
            foreach ($faviconVariants as $faviconVariant) {
                if ((string) ($faviconVariant['purpose'] ?? '') !== 'favicon') {
                    continue;
                }

                $faviconUrl = sr_logo_manager_icon_variant_url($faviconVariant);
                if ($faviconUrl !== '') {
                    break;
                }
            }
            if ($faviconUrl === '') {
                $faviconUrl = sr_logo_manager_logo_url($faviconLogo);
            }
            if ($faviconUrl !== '' && sr_is_safe_relative_url($faviconUrl)) {
                $faviconUrl = sr_logo_manager_url_with_cache_version($faviconUrl, sr_logo_manager_favicon_cache_version($pdo));
            } else {
                $faviconUrl = '';
            }
        }
    }

    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    if ($faviconUrl !== '') {
        header('Location: ' . sr_url($faviconUrl), true, 302);
    } else {
        http_response_code(404);
        header('Content-Type: image/x-icon');
        header('Content-Length: 0');
    }
    exit;
}

if ($method === 'GET' && $path === '/manifest.webmanifest') {
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: no-cache');
    echo json_encode(sr_pwa_manifest_payload($pdo, $site), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($method === 'GET' && ($path === '/site/og-image' || $path === '/seo/image')) {
    $storageReference = sr_get_string('file', 180);
    $storage = sr_site_og_image_storage_reference($storageReference);
    if (!is_array($storage)) {
        sr_render_error(404, '요청한 이미지를 찾을 수 없습니다.');
    }

    $driver = (string) $storage['driver'];
    $key = (string) $storage['key'];
    if ($driver === 's3') {
        $url = sr_storage_public_url('s3', $key);
        if ($url === '') {
            $url = sr_storage_signed_url('s3', $key, 300);
        }
        if ($url === '') {
            sr_render_error(404, '요청한 이미지를 찾을 수 없습니다.');
        }

        header('Cache-Control: private, max-age=300');
        sr_redirect_trusted_external($url);
    }

    $imagePath = sr_storage_local_path($key);
    if (!is_string($imagePath)) {
        sr_render_error(404, '요청한 이미지를 찾을 수 없습니다.');
    }

    $mimeType = sr_upload_detect_mime($imagePath);
    $sizeBytes = filesize($imagePath);
    if (!sr_image_mime_is_allowed($mimeType) || !is_int($sizeBytes)) {
        sr_render_error(404, '요청한 이미지를 찾을 수 없습니다.');
    }

    sr_send_file_headers($mimeType, $sizeBytes, 'public, max-age=31536000, immutable');
    readfile($imagePath);
    sr_finish_response();
}

if ($method === 'GET' && $path === '/service-worker.js') {
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-cache');
    header('Service-Worker-Allowed: ' . sr_url('/'));
    echo sr_pwa_service_worker_source();
    exit;
}

if (
    $site !== null
    && $site['status'] === 'maintenance'
    && $path !== '/login'
    && $path !== '/login/mfa'
    && $path !== '/logout'
    && strpos($path, '/admin') !== 0
) {
    sr_render_error(503, '현재 점검 중입니다.');
    exit;
}

sr_site_member_only_guard($pdo, $site, $method, $path);

if ($path === '/') {
    $homePath = is_array($site) ? (string) ($site['home_path'] ?? '/') : '/';
    if ($homePath !== '/' && sr_site_home_path_is_available($pdo, $homePath)) {
        sr_redirect($homePath);
    }

    include SR_ROOT . '/core/views/home.php';
    exit;
}

if ($method === 'GET' && $path === '/ui-kit') {
    sr_site_member_only_guard($pdo, $site, $method, $path, [
        'module_key' => 'core',
        'route' => 'GET /ui-kit',
    ]);

    $uiKitFile = sr_public_layout_optional_view_file(sr_public_layout_key($site, $pdo), 'ui_kit', $pdo);
    if ($uiKitFile === null) {
        sr_render_error(404, '요청한 화면을 찾을 수 없습니다.');
        exit;
    }

    include $uiKitFile;
    exit;
}

if ($method === 'GET' && in_array($path, ['/content/ui-kit', '/community/ui-kit', '/quiz/ui-kit', '/survey/ui-kit'], true)) {
    $uiKitPathParts = explode('/', trim($path, '/'));
    $uiKitModuleKey = (string) ($uiKitPathParts[0] ?? '');
    if (!sr_module_enabled($pdo, $uiKitModuleKey)) {
        sr_render_error(404, '요청한 화면을 찾을 수 없습니다.');
        exit;
    }

    sr_site_member_only_guard($pdo, $site, $method, $path, [
        'module_key' => $uiKitModuleKey,
        'route' => 'GET ' . $path,
    ]);

    $uiKitActionFile = SR_ROOT . '/modules/' . $uiKitModuleKey . '/actions/ui-kit.php';
    if (!is_file($uiKitActionFile)) {
        sr_render_error(404, '요청한 화면을 찾을 수 없습니다.');
        exit;
    }

    include $uiKitActionFile;
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
    sr_site_member_only_guard($pdo, $site, $method, $path, $routeMatches[0]);
    sr_start_request_contract($method, $path, (string) $routeMatches[0]['module_key'], (string) $routeMatches[0]['action_file']);
    include $routeMatches[0]['action_file'];
    sr_enforce_request_contract('after_action');
    exit;
}

sr_render_error(404, '요청한 화면을 찾을 수 없습니다.');
