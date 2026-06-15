<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$thumbnailCacheRequest = is_string($requestPath)
    && (
        preg_match('#\A/storage/cache/thumbnails/[a-f0-9]{2}/[a-f0-9]{64}_[A-Za-z0-9_]+_[0-9]+\.(?:jpe?g|png|gif|webp)\z#i', $requestPath) === 1
        || preg_match('#\A/storage/cache/thumbnails/[a-z][a-z0-9_]{1,39}/[a-f0-9]{2}/[a-f0-9]{64}_[A-Za-z0-9_]+_[a-f0-9]{16,64}\.(?:jpe?g|png|gif|webp)\z#i', $requestPath) === 1
    );

if (
    is_string($requestPath)
    && !$thumbnailCacheRequest
    && (
        preg_match('#\A/(?:config|core|database|docs|examples|storage|\.git|\.tools|\.claude)(?:/|\z)#', $requestPath) === 1
        || (preg_match('#\A/modules/#', $requestPath) === 1
            && preg_match('#\A/modules/[a-z][a-z0-9_]{1,39}/assets/#', $requestPath) !== 1
            && preg_match('#\A/modules/[a-z][a-z0-9_]{1,39}/skins/[a-z][a-z0-9_]{0,39}/#', $requestPath) !== 1
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
        || $thumbnailCacheRequest
        || preg_match('#\A/modules/[a-z][a-z0-9_]{1,39}/assets/#', $requestPath) === 1
        || preg_match('#\A/modules/[a-z][a-z0-9_]{1,39}/skins/[a-z][a-z0-9_]{0,39}/#', $requestPath) === 1
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
