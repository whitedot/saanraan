<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

function sr_url(string $path): string
{
    if (!sr_is_safe_relative_url($path)) {
        return sr_base_path() === '' ? '/' : sr_base_path() . '/';
    }

    $basePath = sr_base_path();
    if ($basePath === '' || $path === $basePath || str_starts_with($path, $basePath . '/')) {
        return $path;
    }

    return $basePath . $path;
}

function sr_canonical_url(?array $site, ?string $path = null): string
{
    $path = $path ?? sr_request_path();
    if (!sr_is_safe_relative_url($path)) {
        $path = '/';
    }

    return sr_absolute_url($site, $path);
}

function sr_is_safe_relative_url(string $url): bool
{
    if ($url === '' || $url[0] !== '/' || str_starts_with($url, '//')) {
        return false;
    }

    if (strpos($url, '\\') !== false) {
        return false;
    }

    return preg_match('/[\x00-\x1F\x7F]/', $url) !== 1;
}

function sr_seo_tags(array $seo = [], ?array $site = null): string
{
    $title = (string) ($seo['title'] ?? sr_site_display_name($site));
    $description = (string) ($seo['description'] ?? '');
    $canonical = (string) ($seo['canonical'] ?? sr_canonical_url($site));
    if (sr_is_safe_relative_url($canonical)) {
        $canonical = sr_absolute_url($site, $canonical);
    } elseif (!sr_is_http_url($canonical)) {
        $canonical = '';
    }

    $robots = (string) ($seo['robots'] ?? 'index, follow');
    $og = isset($seo['og']) && is_array($seo['og']) ? $seo['og'] : [];

    $tags = [];
    $tags[] = '<title>' . sr_e($title) . '</title>';

    if ($description !== '') {
        $tags[] = '<meta name="description" content="' . sr_e($description) . '">';
    }

    if ($canonical !== '') {
        $tags[] = '<link rel="canonical" href="' . sr_e($canonical) . '">';
    }

    if ($robots !== '') {
        $tags[] = '<meta name="robots" content="' . sr_e($robots) . '">';
    }

    $ogTitle = (string) ($og['title'] ?? $title);
    $ogDescription = (string) ($og['description'] ?? $description);
    $ogType = (string) ($og['type'] ?? 'website');
    $ogImage = (string) ($og['image'] ?? '');
    if (sr_is_safe_relative_url($ogImage)) {
        $ogImage = sr_absolute_url($site, $ogImage);
    } elseif ($ogImage !== '' && !sr_is_http_url($ogImage)) {
        $ogImage = '';
    }

    if ($ogTitle !== '') {
        $tags[] = '<meta property="og:title" content="' . sr_e($ogTitle) . '">';
    }

    if ($ogDescription !== '') {
        $tags[] = '<meta property="og:description" content="' . sr_e($ogDescription) . '">';
    }

    if ($canonical !== '') {
        $tags[] = '<meta property="og:url" content="' . sr_e($canonical) . '">';
    }

    if ($ogType !== '') {
        $tags[] = '<meta property="og:type" content="' . sr_e($ogType) . '">';
    }

    if ($ogImage !== '') {
        $tags[] = '<meta property="og:image" content="' . sr_e($ogImage) . '">';
    }

    return implode("\n    ", $tags);
}

function sr_redirect(string $url): void
{
    if (!sr_is_safe_relative_url($url)) {
        sr_render_error(500, sr_t('error.redirect_invalid'));
    }

    sr_enforce_request_contract('before_redirect');

    header('Location: ' . sr_url($url), true, 302);
    sr_finish_response();
}

function sr_redirect_external(string $url): void
{
    if (!sr_is_public_http_url($url)) {
        sr_render_error(500, sr_t('error.external_redirect_invalid'));
    }

    sr_enforce_request_contract('before_redirect');

    header('Location: ' . $url, true, 302);
    sr_finish_response();
}

function sr_redirect_trusted_external(string $url, array $allowedOrigins = []): void
{
    if (!sr_trusted_external_redirect_url_is_allowed($url, $allowedOrigins)) {
        sr_render_error(500, sr_t('error.external_redirect_invalid'));
    }

    sr_enforce_request_contract('before_redirect');

    header('Location: ' . $url, true, 302);
    sr_finish_response();
}

function sr_trusted_external_redirect_url_is_allowed(string $url, array $allowedOrigins = []): bool
{
    if (!sr_is_http_url($url)) {
        return false;
    }

    $origins = $allowedOrigins === [] ? sr_trusted_external_redirect_default_origins() : $allowedOrigins;
    foreach ($origins as $origin) {
        if (!is_string($origin) || $origin === '') {
            continue;
        }

        if (sr_http_url_origins_match($url, $origin)) {
            return true;
        }
    }

    return false;
}

function sr_trusted_external_redirect_default_origins(?array $config = null): array
{
    $config = $config ?? sr_runtime_config();
    $storage = isset($config['storage']) && is_array($config['storage']) ? $config['storage'] : [];
    $s3 = isset($storage['s3']) && is_array($storage['s3']) ? $storage['s3'] : [];

    $origins = [];
    $publicBaseUrl = trim((string) ($s3['public_base_url'] ?? ''));
    if ($publicBaseUrl !== '' && sr_is_http_url($publicBaseUrl)) {
        $origins[] = $publicBaseUrl;
    }

    $bucket = trim((string) ($s3['bucket'] ?? ''));
    $region = trim((string) ($s3['region'] ?? 'us-east-1'));
    $region = $region === '' ? 'us-east-1' : $region;
    $endpoint = rtrim(trim((string) ($s3['endpoint'] ?? '')), '/');
    if (preg_match('/\A[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]\z/', $bucket) !== 1 || str_contains($bucket, '..')) {
        return array_values(array_unique($origins));
    }

    if ($endpoint === '') {
        $host = $region === 'us-east-1'
            ? $bucket . '.s3.amazonaws.com'
            : $bucket . '.s3.' . $region . '.amazonaws.com';
        $origins[] = 'https://' . $host;

        return array_values(array_unique($origins));
    }

    if (!sr_is_http_url($endpoint)) {
        return array_values(array_unique($origins));
    }

    if (!empty($s3['path_style'])) {
        $origins[] = $endpoint;

        return array_values(array_unique($origins));
    }

    $scheme = strtolower((string) parse_url($endpoint, PHP_URL_SCHEME));
    $host = parse_url($endpoint, PHP_URL_HOST);
    if (!is_string($host) || $scheme === '') {
        return array_values(array_unique($origins));
    }

    $port = parse_url($endpoint, PHP_URL_PORT);
    $hostPart = $bucket . '.' . strtolower($host);
    if (is_int($port)) {
        $hostPart .= ':' . (string) $port;
    }
    $origins[] = $scheme . '://' . $hostPart;

    return array_values(array_unique($origins));
}

function sr_http_url_origins_match(string $url, string $origin): bool
{
    $urlOrigin = sr_http_url_origin_parts($url);
    $allowedOrigin = sr_http_url_origin_parts($origin);

    return $urlOrigin !== null
        && $allowedOrigin !== null
        && $urlOrigin === $allowedOrigin;
}

function sr_http_url_origin_parts(string $url): ?array
{
    if (!sr_is_http_url($url)) {
        return null;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return null;
    }

    $port = parse_url($url, PHP_URL_PORT);
    $port = is_int($port) ? $port : ($scheme === 'http' ? 80 : 443);

    return [
        'scheme' => $scheme,
        'host' => strtolower(trim($host, '[]')),
        'port' => $port,
    ];
}

function sr_finish_response(): void
{
    sr_enforce_request_contract('before_response_end');
    exit;
}

function sr_csrf_token(): string
{
    if (empty($_SESSION['sr_csrf_token']) || !is_string($_SESSION['sr_csrf_token'])) {
        $_SESSION['sr_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['sr_csrf_token'];
}

function sr_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . sr_e(sr_csrf_token()) . '">';
}

function sr_require_csrf(): void
{
    sr_request_contract_mark('csrf_checked');

    $expected = $_SESSION['sr_csrf_token'] ?? '';
    $actual = $_POST['csrf_token'] ?? '';

    if (!is_string($expected) || !is_string($actual) || $expected === '' || !hash_equals($expected, $actual)) {
        sr_request_contract_guard_blocked('csrf');
        sr_render_error(400, sr_t('error.csrf_invalid'));
    }
}

function sr_post_string(string $key, int $maxLength): string
{
    $value = $_POST[$key] ?? '';
    if (is_array($value)) {
        return '';
    }

    $value = trim((string) $value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function sr_post_string_without_truncation(string $key, int $maxLength): ?string
{
    $value = $_POST[$key] ?? '';
    if (is_array($value)) {
        return null;
    }

    $value = trim((string) $value);
    return strlen($value) <= $maxLength ? $value : null;
}

function sr_get_string(string $key, int $maxLength): string
{
    $value = $_GET[$key] ?? '';
    if (is_array($value)) {
        return '';
    }

    $value = trim((string) $value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function sr_get_string_without_truncation(string $key, int $maxLength): ?string
{
    $value = $_GET[$key] ?? '';
    if (is_array($value)) {
        return null;
    }

    $value = trim((string) $value);
    return strlen($value) <= $maxLength ? $value : null;
}

function sr_send_download_headers(string $contentType, string $filename, string $disposition = 'attachment', ?int $contentLength = null, string $cacheControl = 'no-store, no-cache, must-revalidate'): void
{
    header('Content-Type: ' . sr_download_content_type($contentType));
    header('Content-Disposition: ' . sr_download_content_disposition($filename, $disposition));
    if ($contentLength !== null && $contentLength > 0) {
        header('Content-Length: ' . (string) $contentLength);
    }
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: ' . sr_download_cache_control($cacheControl));
    header('Pragma: no-cache');
}

function sr_send_file_headers(string $contentType, ?int $contentLength = null, string $cacheControl = 'private, max-age=300', array $headers = []): void
{
    header('Content-Type: ' . sr_download_content_type($contentType));
    if ($contentLength !== null && $contentLength > 0) {
        header('Content-Length: ' . (string) $contentLength);
    }
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: ' . sr_download_cache_control($cacheControl));
    foreach ($headers as $header) {
        if (is_string($header) && sr_response_header_is_allowed($header)) {
            header($header);
        }
    }
}

function sr_download_content_type(string $contentType): string
{
    $contentType = trim($contentType);
    if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9.+-]*\/[A-Za-z0-9][A-Za-z0-9.+-]*(?:;\s*charset=[A-Za-z0-9._-]+)?\z/', $contentType) !== 1) {
        return 'application/octet-stream';
    }

    return $contentType;
}

function sr_download_filename(string $filename): string
{
    $filename = str_replace(['\\', '/'], '-', $filename);
    $filename = preg_replace('/[\x00-\x1F\x7F]+/', '-', $filename);
    $filename = is_string($filename) ? $filename : '';
    $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename);
    $filename = is_string($filename) ? preg_replace('/-+/', '-', $filename) : '';
    $filename = is_string($filename) ? trim($filename, '.-_') : '';

    if ($filename === '') {
        return 'download.bin';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($filename, 0, 120);
    }

    return substr($filename, 0, 120);
}

function sr_download_content_disposition(string $filename, string $disposition = 'attachment'): string
{
    $disposition = strtolower(trim($disposition));
    if (!in_array($disposition, ['attachment', 'inline'], true)) {
        $disposition = 'attachment';
    }

    return $disposition . '; filename="' . sr_download_filename($filename) . '"';
}

function sr_download_cache_control(string $cacheControl): string
{
    $cacheControl = trim($cacheControl);
    if ($cacheControl === '' || preg_match('/[\x00-\x1F\x7F]/', $cacheControl) === 1) {
        return 'no-store, no-cache, must-revalidate';
    }

    if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9\s,=_\-;]*\z/', $cacheControl) !== 1) {
        return 'no-store, no-cache, must-revalidate';
    }

    return $cacheControl;
}

function sr_absolute_url(?array $site, string $path): string
{
    if (!sr_is_safe_relative_url($path)) {
        $path = '/';
    }

    $baseUrl = is_array($site) ? rtrim((string) ($site['base_url'] ?? ''), '/') : '';
    if ($baseUrl === '' || !sr_is_site_base_url($baseUrl)) {
        return sr_url($path);
    }

    return $baseUrl . '/' . ltrim($path, '/');
}
