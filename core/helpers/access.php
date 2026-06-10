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
    if (!sr_site_member_only_enabled($site) || !sr_module_enabled($pdo, 'member')) {
        return;
    }

    require_once SR_ROOT . '/modules/member/helpers.php';
    if (sr_member_current_account($pdo) !== null) {
        return;
    }

    $decision = sr_site_member_only_route_decision($method, $path, $routeMatch);
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

function sr_site_member_only_route_decision(string $method, string $path, ?array $routeMatch = null): string
{
    if (sr_site_member_only_public_auth_route($method, $path)) {
        return 'allow';
    }

    if (sr_site_member_only_public_system_route($method, $path)) {
        return 'allow';
    }

    if ($method === 'GET' && $path === '/') {
        return 'redirect';
    }

    if ($method === 'GET' && $path === '/ui-kit') {
        return 'redirect';
    }

    if ($method === 'GET' && sr_site_member_only_direct_public_route($path)) {
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

    if (sr_site_member_only_direct_protected_file_route($method, $path)) {
        return 'forbid';
    }

    if ($method === 'GET' && $path === '/banner/click') {
        return 'forbid';
    }

    if (in_array($moduleKey, ['content', 'community', 'quiz', 'survey'], true)) {
        if (sr_site_member_only_public_screen_route($moduleKey, $method, $path, $matchedRoute)) {
            return in_array($method, ['GET', 'HEAD'], true) ? 'redirect' : 'forbid';
        }

        if (sr_site_member_only_module_public_path($moduleKey, $path)) {
            return 'forbid';
        }
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
    return $method === 'GET' && in_array($path, ['/robots.txt', '/sitemap.xml'], true);
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

function sr_site_member_only_direct_protected_file_route(string $method, string $path): bool
{
    if (!in_array($method, ['GET', 'POST'], true)) {
        return false;
    }

    return in_array($path, [
        '/content/download',
        '/content/cover-image',
        '/content/body-file',
        '/community/body-file',
        '/community/attachment',
    ], true);
}

function sr_site_member_only_public_screen_route(string $moduleKey, string $method, string $path, string $matchedRoute): bool
{
    if ($moduleKey === 'content') {
        return in_array($matchedRoute, ['GET /content', 'GET /content/group', 'GET /content/ui-kit', 'GET /content/*', 'POST /content/*'], true);
    }

    if ($moduleKey === 'community') {
        return in_array($matchedRoute, ['GET /community', 'GET /community/group', 'GET /community/board', 'GET /community/post', 'POST /community/post', 'GET /community/ui-kit'], true);
    }

    if ($moduleKey === 'quiz') {
        return in_array($matchedRoute, ['GET /quiz', 'GET /quiz/*', 'POST /quiz/*'], true);
    }

    if ($moduleKey === 'survey') {
        return in_array($matchedRoute, ['GET /survey', 'GET /survey/ui-kit', 'GET /survey/*', 'POST /survey/*'], true);
    }

    return false;
}

function sr_site_member_only_module_public_path(string $moduleKey, string $path): bool
{
    return $path === '/' . $moduleKey || str_starts_with($path, '/' . $moduleKey . '/');
}
