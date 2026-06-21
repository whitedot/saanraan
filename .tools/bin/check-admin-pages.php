#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';

$errors = [];

function sr_check_admin_pages_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_check_admin_pages_module_dirs(string $root): array
{
    $dirs = [];
    foreach (new DirectoryIterator($root . '/modules') as $entry) {
        if ($entry->isDot() || !$entry->isDir()) {
            continue;
        }

        $dirs[$entry->getFilename()] = $entry->getPathname();
    }

    ksort($dirs, SORT_STRING);
    return $dirs;
}

function sr_check_admin_pages_load_array(string $file, string $label): array
{
    if (!is_file($file)) {
        sr_check_admin_pages_error($label . ' file is missing: ' . $file);
        return [];
    }

    $value = include $file;
    if (!is_array($value)) {
        sr_check_admin_pages_error($label . ' file must return an array: ' . $file);
        return [];
    }

    return $value;
}

function sr_check_admin_pages_route_path(string $route): array
{
    if (preg_match('/\A(GET|POST) (\/.*)\z/', $route, $matches) !== 1) {
        return ['', ''];
    }

    return [(string) $matches[1], (string) $matches[2]];
}

function sr_check_admin_pages_menu_items(array $menu): array
{
    $rawItems = isset($menu['items']) && is_array($menu['items']) ? $menu['items'] : $menu;
    $items = [];
    foreach ($rawItems as $item) {
        if (!is_array($item)) {
            continue;
        }

        $path = trim((string) ($item['path'] ?? ''));
        $label = trim((string) ($item['label'] ?? ''));
        if ($path === '' && $label === '') {
            continue;
        }

        $items[] = [
            'label' => $label,
            'path' => $path,
        ];
    }

    return $items;
}

function sr_check_admin_pages_action_content(string $moduleDir, string $actionRelativePath): string
{
    $actionFile = $moduleDir . '/' . $actionRelativePath;
    $pending = [$actionFile];
    $seen = [];
    $content = '';

    while ($pending !== []) {
        $file = array_shift($pending);
        $file = is_string($file) ? $file : '';
        if ($file === '' || isset($seen[$file])) {
            continue;
        }
        $seen[$file] = true;

        $fileContent = is_file($file) ? file_get_contents($file) : false;
        if (!is_string($fileContent)) {
            continue;
        }

        $content .= "\n" . $fileContent;
        preg_match_all(
            "#include\s+SR_ROOT\s*\.\s*'(/modules/[a-z0-9_]+/(?:views|actions)/[a-z0-9_\-/]+\.php)'#",
            $fileContent,
            $matches
        );

        foreach ($matches[1] as $includePath) {
            $pending[] = SR_ROOT . (string) $includePath;
        }
    }

    return $content;
}

function sr_check_admin_pages_has_layout(string $content): bool
{
    return str_contains($content, "modules/admin/views/layout-header.php")
        && str_contains($content, "modules/admin/views/layout-footer.php");
}

function sr_check_admin_pages_has_admin_guard(string $content): bool
{
    return str_contains($content, 'sr_admin_require_permission(')
        || str_contains($content, 'sr_admin_require_owner(')
        || str_contains($content, 'sr_admin_require_role(');
}

function sr_check_admin_pages_mentions_path(string $content, string $path): bool
{
    return str_contains($content, "'" . $path . "'") || str_contains($content, '"' . $path . '"');
}

$moduleDirs = sr_check_admin_pages_module_dirs($root);
$routesByModule = [];
$allAdminGetRoutes = [];
$allAdminPostRoutes = [];

foreach ($moduleDirs as $moduleKey => $moduleDir) {
    $pathsFile = $moduleDir . '/paths.php';
    if (!is_file($pathsFile)) {
        continue;
    }

    $paths = sr_check_admin_pages_load_array($pathsFile, 'paths');
    foreach ($paths as $route => $actionRelativePath) {
        [$method, $path] = sr_check_admin_pages_route_path((string) $route);
        if ($method === '' || $path === '') {
            sr_check_admin_pages_error('Route key format is invalid: ' . $pathsFile . ' ' . (string) $route);
            continue;
        }

        if (!is_string($actionRelativePath) || preg_match('/\Aactions\/[a-z0-9_\-\/]+\.php\z/', $actionRelativePath) !== 1) {
            sr_check_admin_pages_error('Route action path is invalid: ' . $pathsFile . ' ' . (string) $route);
            continue;
        }

        $actionFile = $moduleDir . '/' . $actionRelativePath;
        if (!is_file($actionFile)) {
            sr_check_admin_pages_error('Route action file is missing: ' . $pathsFile . ' ' . (string) $route . ' -> ' . $actionRelativePath);
            continue;
        }

        $routesByModule[$moduleKey][$method . ' ' . $path] = $actionRelativePath;
        if ($method === 'GET' && ($path === '/admin' || str_starts_with($path, '/admin/'))) {
            $allAdminGetRoutes[$path] = [
                'module_key' => $moduleKey,
                'module_dir' => $moduleDir,
                'action' => $actionRelativePath,
            ];
        }
        if ($method === 'POST' && ($path === '/admin' || str_starts_with($path, '/admin/'))) {
            $allAdminPostRoutes[$path] = true;
        }
    }
}

$menuPageCount = 0;
foreach ($moduleDirs as $moduleKey => $moduleDir) {
    $menuFile = $moduleDir . '/admin-menu.php';
    if (!is_file($menuFile)) {
        continue;
    }

    $menu = sr_check_admin_pages_load_array($menuFile, 'admin-menu');
    foreach (sr_check_admin_pages_menu_items($menu) as $item) {
        $path = (string) $item['path'];
        $label = (string) $item['label'];
        if ($label === '') {
            sr_check_admin_pages_error('Admin menu item label is empty: ' . $menuFile . ' ' . $path);
        }
        if (preg_match('/\A\/admin(?:\/[a-z0-9][a-z0-9_-]*)*\z/', $path) !== 1) {
            sr_check_admin_pages_error('Admin menu item path is invalid: ' . $menuFile . ' ' . $path);
            continue;
        }

        $routeKey = 'GET ' . $path;
        if (!isset($routesByModule[$moduleKey][$routeKey])) {
            sr_check_admin_pages_error('Admin menu item must have a matching GET route in the same module: ' . $menuFile . ' ' . $path);
            continue;
        }

        $menuPageCount++;
        $content = sr_check_admin_pages_action_content($moduleDir, (string) $routesByModule[$moduleKey][$routeKey]);
        if (!sr_check_admin_pages_has_layout($content)) {
            sr_check_admin_pages_error('Admin menu page must render the shared admin layout: ' . $moduleKey . ' ' . $path);
        }
        if (!sr_check_admin_pages_has_admin_guard($content)) {
            sr_check_admin_pages_error('Admin menu page must require an admin guard: ' . $moduleKey . ' ' . $path);
        }
        if (!sr_check_admin_pages_mentions_path($content, $path)) {
            sr_check_admin_pages_error('Admin menu page guard must mention its menu path: ' . $moduleKey . ' ' . $path);
        }
    }
}

$builtinMenuPaths = [
    '/admin',
    '/admin/settings',
    '/admin/modules',
    '/admin/updates',
    '/admin/operations',
    '/admin/storage-cache',
    '/admin/retention',
    '/admin/menu',
    '/admin/roles',
    '/admin/audit-logs',
];
foreach ($builtinMenuPaths as $path) {
    if (!isset($allAdminGetRoutes[$path])) {
        sr_check_admin_pages_error('Built-in admin page route is missing: ' . $path);
        continue;
    }

    $route = $allAdminGetRoutes[$path];
    $content = sr_check_admin_pages_action_content((string) $route['module_dir'], (string) $route['action']);
    if (!sr_check_admin_pages_has_layout($content)) {
        sr_check_admin_pages_error('Built-in admin page must render the shared admin layout: ' . $path);
    }
}

if ($menuPageCount < 1) {
    sr_check_admin_pages_error('Admin page inventory must include at least one module menu page.');
}

foreach ($moduleDirs as $moduleDir) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($moduleDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
        if (
            !str_contains($relativePath, '/views/admin')
            && !str_contains($relativePath, '/actions/admin')
            && !str_starts_with($relativePath, 'modules/admin/views/')
        ) {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        if (!is_string($content)) {
            sr_check_admin_pages_error('Admin page source cannot be read: ' . $relativePath);
            continue;
        }

        if (preg_match_all(
            '/<form\b(?=[^>]*\bmethod\s*=\s*([\'"]?)post\1)[^>]*\baction\s*=\s*"<\?php\s+echo\s+sr_e\(sr_url\(\'([^\']+)\'\)\);\s*\?>"[^>]*>/is',
            $content,
            $matches,
            PREG_OFFSET_CAPTURE
        ) === false) {
            continue;
        }

        foreach ($matches[2] as [$formAction, $offset]) {
            $formAction = (string) $formAction;
            if (!str_starts_with($formAction, '/admin')) {
                continue;
            }
            if (isset($allAdminPostRoutes[$formAction])) {
                continue;
            }

            $line = substr_count(substr($content, 0, (int) $offset), "\n") + 1;
            sr_check_admin_pages_error('Admin POST form action must have a matching POST route: ' . $relativePath . ':' . (string) $line . ' ' . $formAction);
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "admin page checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo 'admin page checks completed. menu_pages=' . (string) $menuPageCount . ' admin_get_routes=' . (string) count($allAdminGetRoutes) . "\n";
