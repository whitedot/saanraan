<?php

declare(strict_types=1);

function sr_site_member_only_enabled(?array $site): bool
{
    return is_array($site) && !empty($site['member_only_enabled']);
}

function sr_site_member_only_current_request_next_path(): string
{
    $path = sr_request_path();
    $query = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
    if (is_string($query) && $query !== '') {
        $path .= '?' . $query;
    }

    return sr_member_safe_next_path($path);
}

function sr_site_member_only_guard(PDO $pdo, ?array $site, string $method, string $path, ?array $routeMatch = null): void
{
    if (!sr_site_member_only_enabled($site)) {
        return;
    }

    if (!sr_module_enabled($pdo, 'member')) {
        sr_render_error(503, '회원 전용 모드를 사용하려면 회원 모듈이 활성화되어 있어야 합니다.');
        exit;
    }

    require_once SR_ROOT . '/modules/member/helpers.php';
    if (sr_member_current_account($pdo) !== null) {
        return;
    }

    $decision = sr_site_member_only_route_decision($pdo, $method, $path, $routeMatch);
    if ($decision === 'allow') {
        return;
    }

    sr_request_contract_guard_blocked('auth');
    if ($decision === 'forbid' || !in_array($method, ['GET', 'HEAD'], true)) {
        sr_render_error(403, '로그인이 필요한 요청입니다.');
        exit;
    }

    sr_redirect('/login?next=' . rawurlencode(sr_site_member_only_current_request_next_path()));
}

function sr_site_member_only_route_decision(PDO $pdo, string $method, string $path, ?array $routeMatch = null): string
{
    if (sr_site_member_only_public_auth_route($method, $path)) {
        return 'allow';
    }

    if (sr_site_member_only_public_system_route($method, $path)) {
        return 'allow';
    }

    if (in_array($method, ['GET', 'HEAD'], true) && $path === '/') {
        return 'redirect';
    }

    if (in_array($method, ['GET', 'HEAD'], true) && $path === '/ui-kit') {
        return 'redirect';
    }

    if (in_array($method, ['GET', 'HEAD'], true) && sr_site_member_only_direct_public_route($path)) {
        return 'allow';
    }

    if ($routeMatch === null) {
        return 'allow';
    }

    $moduleKey = (string) ($routeMatch['module_key'] ?? '');
    $matchedRoute = (string) ($routeMatch['route'] ?? '');

    if ($moduleKey === 'admin' || $path === '/admin' || str_starts_with($path, '/admin/')) {
        return 'allow';
    }

    if ($path === '/account' || str_starts_with($path, '/account/')) {
        return 'allow';
    }

    if ($moduleKey === 'member') {
        return 'allow';
    }

    if ($method === 'GET' && $path === '/banner/click') {
        return 'forbid';
    }

    $moduleDecision = sr_site_member_only_module_route_decision($pdo, $moduleKey, $method, $path, $matchedRoute);
    if ($moduleDecision !== '') {
        return $moduleDecision;
    }

    return 'allow';
}

function sr_site_member_only_public_auth_route(string $method, string $path): bool
{
    $routes = [
        'GET /login' => true,
        'POST /login' => true,
        'GET /register' => true,
        'POST /register' => true,
        'GET /password/reset' => true,
        'POST /password/reset' => true,
        'GET /password/reset/confirm' => true,
        'POST /password/reset/confirm' => true,
        'GET /email/verify' => true,
        'GET /email/verified' => true,
        'POST /logout' => true,
    ];

    return isset($routes[$method . ' ' . $path]);
}

function sr_site_member_only_public_system_route(string $method, string $path): bool
{
    return in_array($method, ['GET', 'HEAD'], true) && in_array($path, ['/robots.txt', '/sitemap.xml'], true);
}

function sr_site_member_only_direct_public_route(string $path): bool
{
    return in_array($path, [
        '/member/avatar',
        '/logo-manager/image',
        '/seo/image',
        '/banner/image',
        '/popup-layer/body-file',
    ], true);
}

function sr_site_member_only_module_route_decision(PDO $pdo, string $moduleKey, string $method, string $path, string $matchedRoute): string
{
    if (!sr_is_safe_module_key($moduleKey)) {
        return '';
    }

    $contractFiles = sr_enabled_module_contract_files($pdo, 'member-only-routes.php');
    if (!isset($contractFiles[$moduleKey])) {
        return '';
    }

    $contract = sr_load_module_contract_file($moduleKey, $contractFiles[$moduleKey]);
    if (!is_array($contract)) {
        return '';
    }

    $routeKey = $method . ' ' . $path;
    $protectedRoutes = sr_site_member_only_contract_string_list($contract['protected_routes'] ?? []);
    if (in_array($routeKey, $protectedRoutes, true)) {
        return 'forbid';
    }

    $publicRoutes = sr_site_member_only_contract_string_list($contract['public_routes'] ?? []);
    if (in_array($matchedRoute, $publicRoutes, true) || in_array($routeKey, $publicRoutes, true)) {
        return in_array($method, ['GET', 'HEAD'], true) ? 'redirect' : 'forbid';
    }

    foreach (sr_site_member_only_contract_string_list($contract['public_path_prefixes'] ?? []) as $prefix) {
        if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix . '/'))) {
            return in_array($method, ['GET', 'HEAD'], true) ? 'redirect' : 'forbid';
        }
    }

    return '';
}

function sr_site_member_only_contract_string_list(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    $strings = [];
    foreach ($value as $item) {
        if (is_string($item) && $item !== '') {
            $strings[] = $item;
        }
    }

    return array_values(array_unique($strings));
}
