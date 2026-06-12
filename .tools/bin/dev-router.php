<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

if (
    is_string($requestPath)
    && (
        preg_match('#\A/(?:config|core|database|docs|examples|storage|\.git|\.tools|\.claude)(?:/|\z)#', $requestPath) === 1
        || (preg_match('#\A/modules/#', $requestPath) === 1
            && preg_match('#\A/modules/[a-z][a-z0-9_]{1,39}/assets/#', $requestPath) !== 1
            && !in_array($requestPath, ['/modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js', '/modules/ckeditor/vendor/ckeditor5/ckeditor5.css'], true))
        || preg_match('#\A/(?:AGENTS\.md|README\.md|LICENSE|\.gitignore|\.htaccess|\.env(?:\..*)?)\z#', $requestPath) === 1
    )
) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Forbidden\n";
    return true;
}

if (
    is_string($requestPath)
    && (
        str_starts_with($requestPath, '/assets/')
        || preg_match('#\A/modules/[a-z][a-z0-9_]{1,39}/assets/#', $requestPath) === 1
        || in_array($requestPath, ['/modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js', '/modules/ckeditor/vendor/ckeditor5/ckeditor5.css'], true)
    )
) {
    if (preg_match('#\.(?:php[0-9]?|phtml|phar|sql)\z#i', $requestPath) === 1) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Forbidden\n";
        return true;
    }

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
