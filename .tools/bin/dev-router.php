<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

if (
    is_string($requestPath)
    && (
        str_starts_with($requestPath, '/assets/')
        || preg_match('#\A/modules/[a-z][a-z0-9_]{1,39}/assets/#', $requestPath) === 1
    )
) {
    $staticPath = realpath($root . $requestPath);
    if (is_string($staticPath) && str_starts_with($staticPath, $root . DIRECTORY_SEPARATOR) && is_file($staticPath)) {
        $extension = strtolower(pathinfo($staticPath, PATHINFO_EXTENSION));
        $contentTypes = [
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
        ];
        header('Content-Type: ' . ($contentTypes[$extension] ?? 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($staticPath));
        readfile($staticPath);
        return true;
    }
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require $root . '/index.php';
