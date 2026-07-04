#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

$errors = [];

function sr_public_call_order_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_public_call_order_files(string $directory): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $file): bool {
                return $file->isDir()
                    ? !in_array($file->getFilename(), ['vendor', 'node_modules'], true)
                    : true;
            }
        )
    );
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = str_replace('\\', '/', $file->getPathname());
        }
    }
    sort($files);

    return $files;
}

function sr_public_call_order_source(string $file): string
{
    $source = file_get_contents($file);
    return is_string($source) ? $source : '';
}

function sr_public_call_order_check_routes(): void
{
    foreach (glob(SR_ROOT . '/modules/*/{paths,member-only-routes}.php', GLOB_BRACE) ?: [] as $file) {
        $routes = require $file;
        if (!is_array($routes)) {
            continue;
        }

        $wildcards = [];
        foreach (array_keys($routes) as $route) {
            $route = (string) $route;
            if (preg_match('/\A(GET|POST|PUT|PATCH|DELETE)\s+(.+)\z/', $route, $matches) !== 1) {
                continue;
            }
            $method = (string) $matches[1];
            $path = (string) $matches[2];
            foreach ($wildcards[$method] ?? [] as $wildcard) {
                if (str_starts_with($path, $wildcard['prefix']) && $path !== $wildcard['path']) {
                    sr_public_call_order_error('Route wildcard shadows later concrete route in ' . sr_public_call_order_relative($file) . ': ' . $wildcard['route'] . ' before ' . $route);
                }
            }
            if (str_ends_with($path, '/*')) {
                $wildcards[$method][] = [
                    'route' => $route,
                    'path' => $path,
                    'prefix' => substr($path, 0, -1),
                ];
            }
        }
    }
}

function sr_public_call_order_check_layout_pairs(): void
{
    foreach (array_merge(sr_public_call_order_files(SR_ROOT . '/core'), sr_public_call_order_files(SR_ROOT . '/layouts'), sr_public_call_order_files(SR_ROOT . '/modules')) as $file) {
        $source = sr_public_call_order_source($file);
        $beginCount = substr_count($source, 'sr_public_layout_begin(');
        $endCount = substr_count($source, 'sr_public_layout_end(');
        if ($beginCount !== $endCount) {
            sr_public_call_order_error('Public layout begin/end count mismatch in ' . sr_public_call_order_relative($file) . ': begin=' . (string) $beginCount . ' end=' . (string) $endCount);
        }
    }
}

function sr_public_call_order_check_output_slot_assets(): void
{
    foreach (array_merge(sr_public_call_order_files(SR_ROOT . '/core/views'), sr_public_call_order_files(SR_ROOT . '/layouts'), sr_public_call_order_files(SR_ROOT . '/modules')) as $file) {
        $source = sr_public_call_order_source($file);
        if (!str_contains($source, 'sr_public_layout_begin(') || !str_contains($source, 'sr_render_output_slot(')) {
            continue;
        }
        if (!str_contains($source, "'output_slots'") && !str_contains($source, 'sr_public_layout_context_with_output_slot_assets(')) {
            sr_public_call_order_error('Public view renders output slots without predeclared output_slots assets: ' . sr_public_call_order_relative($file));
        }
    }

    $popupContract = sr_public_call_order_source(SR_ROOT . '/modules/popup_layer/output-slots.php');
    if (!str_contains($popupContract, "'scripts' => ['/modules/popup_layer/assets/saanraan-popup-layer.js']")) {
        sr_public_call_order_error('Popup layer output slot contract must declare its public script asset.');
    }
    if (!str_contains($popupContract, 'sr_popup_layer_render($pdo, $context, false)')) {
        sr_public_call_order_error('Popup layer output slot renderer must not append late script tags in slot HTML.');
    }
}

function sr_public_call_order_check_embed_stylesheets(): void
{
    $pairs = [
        'sr_content_body_html(' => 'sr_content_body_embed_stylesheets(',
        'sr_community_post_body_html(' => 'sr_community_post_body_embed_stylesheets(',
    ];
    foreach (sr_public_call_order_files(SR_ROOT . '/modules') as $file) {
        $relative = sr_public_call_order_relative($file);
        if (str_contains($relative, '/helpers')) {
            continue;
        }
        $source = sr_public_call_order_source($file);
        foreach ($pairs as $bodyCall => $assetCall) {
            $bodyPos = strpos($source, $bodyCall);
            if ($bodyPos === false) {
                continue;
            }
            $assetPos = strpos($source, $assetCall);
            if ($assetPos === false || $assetPos > $bodyPos) {
                sr_public_call_order_error('Body embed stylesheet precompute must appear before body render in ' . $relative);
            }
        }
    }
}

function sr_public_call_order_check_public_post_csrf(): void
{
    $mutationPattern = '/\b(?:INSERT|UPDATE|DELETE)\b|sr_[a-z0-9_]*(?:create|update|delete|save|claim|scrap|reaction|submit|respond)[a-z0-9_]*\s*\(/i';
    foreach (sr_public_call_order_files(SR_ROOT . '/modules') as $file) {
        $relative = sr_public_call_order_relative($file);
        if (!str_contains($relative, '/actions/') || str_starts_with($relative, 'modules/admin/') || str_contains($relative, '/actions/admin-')) {
            continue;
        }
        $source = sr_public_call_order_source($file);
        if (!str_contains($source, "sr_request_method() === 'POST'") && !str_contains($source, 'sr_request_method() === "POST"')) {
            continue;
        }
        if (preg_match($mutationPattern, $source, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            continue;
        }
        $csrfPos = strpos($source, 'sr_require_csrf(');
        $mutationPos = (int) $matches[0][1];
        if ($csrfPos === false || $csrfPos > $mutationPos) {
            sr_public_call_order_error('Public POST mutation should require CSRF before state changes: ' . $relative);
        }
    }
}

function sr_public_call_order_relative(string $file): string
{
    $file = str_replace('\\', '/', $file);
    $root = rtrim(str_replace('\\', '/', SR_ROOT), '/') . '/';
    return str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
}

sr_public_call_order_check_routes();
sr_public_call_order_check_layout_pairs();
sr_public_call_order_check_output_slot_assets();
sr_public_call_order_check_embed_stylesheets();
sr_public_call_order_check_public_post_csrf();

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo "public call order checks completed.\n";
