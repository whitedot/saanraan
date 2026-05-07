#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('TOY_ROOT', $root);

require_once $root . '/core/version.php';
require_once $root . '/core/helpers/runtime.php';
require_once $root . '/core/helpers/output.php';
require_once $root . '/core/helpers/settings.php';

$moduleDir = (string) ($argv[1] ?? '');
$moduleKey = (string) ($argv[2] ?? '');
$errors = [];

function toy_external_module_error(array &$errors, string $message): void
{
    $errors[] = $message;
}

function toy_external_module_load_contract_file(string $path, string $contractFile, array &$errors): mixed
{
    try {
        return include $path;
    } catch (Throwable $exception) {
        toy_external_module_error($errors, $contractFile . ' cannot be loaded: ' . $exception->getMessage());
        return null;
    }
}

function toy_external_module_path_is_safe(string $path): bool
{
    if ($path === '' || $path[0] !== '/' || str_contains($path, '\\') || str_contains($path, '..')) {
        return false;
    }

    return preg_match('/[\x00-\x20\x7f?#]/', $path) !== 1;
}

function toy_external_module_url_is_safe(string $url): bool
{
    if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
        return false;
    }

    if (str_starts_with($url, '/')) {
        if (str_starts_with($url, '//') || str_contains($url, '\\') || str_contains($url, '..')) {
            return false;
        }

        return preg_match('/[\x00-\x20\x7f]/', $url) !== 1;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    $host = (string) parse_url($url, PHP_URL_HOST);

    return in_array($scheme, ['http', 'https'], true) && $host !== '';
}

function toy_external_module_route_key_is_valid(string $routeKey): bool
{
    $parts = explode(' ', $routeKey, 2);
    if (count($parts) !== 2) {
        return false;
    }

    return in_array($parts[0], ['GET', 'POST'], true) && toy_external_module_path_is_safe($parts[1]);
}

function toy_external_module_array_string(mixed $value): string
{
    return is_string($value) ? trim($value) : '';
}

function toy_external_module_validate_paths(string $moduleDir, mixed $paths, array &$errors): array
{
    if ($paths === null) {
        return [];
    }

    if (!is_array($paths)) {
        toy_external_module_error($errors, 'paths.php must return an array.');
        return [];
    }

    foreach ($paths as $routeKey => $actionRelativePath) {
        if (!is_string($routeKey) || !toy_external_module_route_key_is_valid($routeKey)) {
            toy_external_module_error($errors, 'paths.php route key is invalid: ' . (is_string($routeKey) ? $routeKey : '(non-string key)'));
            continue;
        }

        if (!is_string($actionRelativePath) || !toy_is_safe_module_action($actionRelativePath)) {
            toy_external_module_error($errors, 'paths.php action path is invalid for ' . $routeKey . '.');
            continue;
        }

        $actionFile = $moduleDir . '/' . $actionRelativePath;
        $realActionFile = realpath($actionFile);
        if (!is_string($realActionFile) || !is_file($realActionFile) || strpos($realActionFile, $moduleDir . DIRECTORY_SEPARATOR) !== 0) {
            toy_external_module_error($errors, 'paths.php action file is missing for ' . $routeKey . ': ' . $actionRelativePath);
        }
    }

    return $paths;
}

function toy_external_module_validate_admin_menu(mixed $adminMenu, ?array $paths, array &$errors): void
{
    if ($adminMenu === null) {
        return;
    }

    if (!is_array($adminMenu)) {
        toy_external_module_error($errors, 'admin-menu.php must return an array.');
        return;
    }

    if ($paths === null) {
        toy_external_module_error($errors, 'paths.php is required when admin-menu.php exists.');
        return;
    }

    foreach ($adminMenu as $index => $item) {
        if (!is_array($item)) {
            toy_external_module_error($errors, 'admin-menu.php item must be an array at index ' . (string) $index . '.');
            continue;
        }

        $label = toy_external_module_array_string($item['label'] ?? null);
        $path = toy_external_module_array_string($item['path'] ?? null);
        if ($label === '') {
            toy_external_module_error($errors, 'admin-menu.php item label is required at index ' . (string) $index . '.');
        }

        if ($path === '' || !str_starts_with($path, '/admin/') || !toy_external_module_path_is_safe($path)) {
            toy_external_module_error($errors, 'admin-menu.php item path must be /admin/... at index ' . (string) $index . '.');
            continue;
        }

        if (!array_key_exists('GET ' . $path, $paths)) {
            toy_external_module_error($errors, 'admin-menu.php path is missing from paths.php: ' . $path);
        }

        if (array_key_exists('order', $item) && !is_int($item['order'])) {
            toy_external_module_error($errors, 'admin-menu.php item order must be an integer at index ' . (string) $index . '.');
        }
    }
}

function toy_external_module_validate_menu_links(mixed $menuLinks, array &$errors): void
{
    if ($menuLinks === null) {
        return;
    }

    if (!is_array($menuLinks)) {
        toy_external_module_error($errors, 'menu-links.php must return an array.');
        return;
    }

    foreach ($menuLinks as $index => $item) {
        if (!is_array($item)) {
            toy_external_module_error($errors, 'menu-links.php item must be an array at index ' . (string) $index . '.');
            continue;
        }

        if (toy_external_module_array_string($item['label'] ?? null) === '') {
            toy_external_module_error($errors, 'menu-links.php item label is required at index ' . (string) $index . '.');
        }

        $url = toy_external_module_array_string($item['url'] ?? null);
        if (!toy_external_module_url_is_safe($url)) {
            toy_external_module_error($errors, 'menu-links.php item url is invalid at index ' . (string) $index . '.');
        }
    }
}

function toy_external_module_validate_output_slots(mixed $outputSlots, array &$errors): void
{
    if ($outputSlots !== null && !is_callable($outputSlots)) {
        toy_external_module_error($errors, 'output-slots.php must return a callable.');
    }
}

function toy_external_module_validate_extension_points(mixed $extensionPoints, array &$errors): void
{
    if ($extensionPoints === null) {
        return;
    }

    if (!is_array($extensionPoints)) {
        toy_external_module_error($errors, 'extension-points.php must return an array.');
        return;
    }

    foreach ($extensionPoints as $index => $point) {
        if (!is_array($point)) {
            toy_external_module_error($errors, 'extension-points.php point must be an array at index ' . (string) $index . '.');
            continue;
        }

        $pointKey = toy_external_module_array_string($point['point_key'] ?? null);
        if (preg_match('/\A[a-z0-9][a-z0-9_.-]{0,119}\z/', $pointKey) !== 1) {
            toy_external_module_error($errors, 'extension-points.php point_key is invalid at index ' . (string) $index . '.');
        }

        if (toy_external_module_array_string($point['label'] ?? null) === '') {
            toy_external_module_error($errors, 'extension-points.php label is required at index ' . (string) $index . '.');
        }

        if (isset($point['slots'])) {
            if (!is_array($point['slots'])) {
                toy_external_module_error($errors, 'extension-points.php slots must be an array at index ' . (string) $index . '.');
                continue;
            }

            foreach ($point['slots'] as $slotIndex => $slot) {
                if (!is_array($slot)) {
                    toy_external_module_error($errors, 'extension-points.php slot must be an array at index ' . (string) $index . '.' . (string) $slotIndex . '.');
                    continue;
                }

                $slotKey = toy_external_module_array_string($slot['slot_key'] ?? null);
                if (preg_match('/\A[a-z0-9][a-z0-9_.-]{0,79}\z/', $slotKey) !== 1) {
                    toy_external_module_error($errors, 'extension-points.php slot_key is invalid at index ' . (string) $index . '.' . (string) $slotIndex . '.');
                }
            }
        }
    }
}

function toy_external_module_validate_sitemap(mixed $sitemap, array &$errors): void
{
    if ($sitemap === null || is_callable($sitemap)) {
        return;
    }

    if (!is_array($sitemap)) {
        toy_external_module_error($errors, 'sitemap.php must return an array or callable.');
        return;
    }

    foreach ($sitemap as $index => $entry) {
        if (!is_array($entry)) {
            toy_external_module_error($errors, 'sitemap.php item must be an array at index ' . (string) $index . '.');
            continue;
        }

        $loc = toy_external_module_array_string($entry['loc'] ?? null);
        if (!toy_external_module_url_is_safe($loc)) {
            toy_external_module_error($errors, 'sitemap.php item loc is invalid at index ' . (string) $index . '.');
        }
    }
}

if ($moduleDir === '' || $moduleKey === '') {
    fwrite(STDERR, "Usage: php .tools/bin/check-external-module.php <module-dir> <module-key>\n");
    exit(1);
}

if (!toy_is_safe_module_key($moduleKey)) {
    toy_external_module_error($errors, 'Module key is invalid: ' . $moduleKey);
}

$realModuleDir = realpath($moduleDir);
if (!is_string($realModuleDir) || !is_dir($realModuleDir)) {
    toy_external_module_error($errors, 'Module directory does not exist: ' . $moduleDir);
} else {
    $moduleFile = $realModuleDir . '/module.php';
    if (!is_file($moduleFile)) {
        toy_external_module_error($errors, 'module.php is missing.');
    }

    if (!is_file($realModuleDir . '/install.sql')) {
        toy_external_module_error($errors, 'install.sql is missing.');
    }

    $metadata = [];
    if (is_file($moduleFile)) {
        $loaded = include $moduleFile;
        if (!is_array($loaded)) {
            toy_external_module_error($errors, 'module.php must return an array.');
        } else {
            $metadata = $loaded;
        }
    }

    $name = is_string($metadata['name'] ?? null) ? (string) $metadata['name'] : '';
    if ($name === '') {
        toy_external_module_error($errors, 'module.php name is required.');
    }

    $version = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
    if ($version === '' || preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $version) !== 1) {
        toy_external_module_error($errors, 'module.php version must use YYYY.MM.NNN.');
    }

    $type = (string) ($metadata['type'] ?? 'module');
    if (!in_array($type, ['module', 'plugin'], true)) {
        toy_external_module_error($errors, 'module.php type must be module or plugin.');
    }

    foreach (toy_module_contract_errors($metadata) as $error) {
        toy_external_module_error($errors, $error);
    }

    $contracts = [];
    foreach (['paths.php', 'admin-menu.php', 'output-slots.php', 'extension-points.php', 'menu-links.php', 'sitemap.php', 'privacy-export.php'] as $contractFile) {
        $path = $realModuleDir . '/' . $contractFile;
        if (is_file($path)) {
            $loaded = toy_external_module_load_contract_file($path, $contractFile, $errors);
            $contracts[$contractFile] = $loaded;
            if (!is_array($loaded) && !is_callable($loaded)) {
                toy_external_module_error($errors, $contractFile . ' must return an array or callable.');
            }
        }
    }

    $paths = toy_external_module_validate_paths($realModuleDir, $contracts['paths.php'] ?? null, $errors);
    toy_external_module_validate_admin_menu($contracts['admin-menu.php'] ?? null, is_file($realModuleDir . '/paths.php') ? $paths : null, $errors);
    toy_external_module_validate_menu_links($contracts['menu-links.php'] ?? null, $errors);
    toy_external_module_validate_output_slots($contracts['output-slots.php'] ?? null, $errors);
    toy_external_module_validate_extension_points($contracts['extension-points.php'] ?? null, $errors);
    toy_external_module_validate_sitemap($contracts['sitemap.php'] ?? null, $errors);

    $phpFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($realModuleDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($phpFiles as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname());
        $output = [];
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            toy_external_module_error($errors, 'PHP lint failed: ' . $file->getPathname());
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "toycore external module checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "toycore external module checks completed for " . $moduleKey . ".\n";
