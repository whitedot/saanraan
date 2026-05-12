<?php

declare(strict_types=1);

function toy_storage_default_driver(?array $config = null): string
{
    $config = is_array($config) ? $config : toy_runtime_config();
    $storage = isset($config['storage']) && is_array($config['storage']) ? $config['storage'] : [];
    $driver = strtolower(trim((string) ($storage['default'] ?? 'local')));

    return in_array($driver, ['local', 's3'], true) ? $driver : 'local';
}

function toy_storage_key_is_safe(string $key): bool
{
    if ($key === '' || strlen($key) > 512 || str_contains($key, "\0") || str_starts_with($key, '/') || str_contains($key, '//')) {
        return false;
    }

    foreach (explode('/', $key) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..' || preg_match('/\A[A-Za-z0-9._=-]{1,160}\z/', $segment) !== 1) {
            return false;
        }
    }

    return true;
}

function toy_storage_reference(string $driver, string $key): string
{
    $driver = strtolower(trim($driver));
    if (!in_array($driver, ['local', 's3'], true) || !toy_storage_key_is_safe($key)) {
        throw new InvalidArgumentException('Storage reference is invalid.');
    }

    return $driver . ':' . $key;
}

function toy_storage_parse_reference(string $reference, string $fallbackDriver = 'local'): ?array
{
    $reference = trim($reference);
    $fallbackDriver = in_array($fallbackDriver, ['local', 's3'], true) ? $fallbackDriver : 'local';
    if (preg_match('/\A(local|s3):(.+)\z/', $reference, $matches) === 1) {
        $driver = (string) $matches[1];
        $key = (string) $matches[2];
    } else {
        $driver = $fallbackDriver;
        $key = $reference;
    }

    if (!toy_storage_key_is_safe($key)) {
        return null;
    }

    return [
        'driver' => $driver,
        'key' => $key,
    ];
}

function toy_storage_put_file(string $sourcePath, string $key, array $options = []): array
{
    if (!is_file($sourcePath) || !toy_storage_key_is_safe($key)) {
        throw new RuntimeException('저장할 파일 또는 저장 key가 올바르지 않습니다.');
    }

    $config = isset($options['config']) && is_array($options['config']) ? $options['config'] : toy_runtime_config();
    $driver = strtolower(trim((string) ($options['driver'] ?? toy_storage_default_driver($config))));
    if ($driver === 's3') {
        return toy_storage_s3_put_file($config, $sourcePath, $key, $options);
    }

    return toy_storage_local_put_file($sourcePath, $key, $options);
}

function toy_storage_delete(string $driver, string $key, ?array $config = null): bool
{
    if (!toy_storage_key_is_safe($key)) {
        return false;
    }

    $driver = strtolower(trim($driver));
    if ($driver === 's3') {
        return toy_storage_s3_delete($config ?? toy_runtime_config(), $key);
    }

    $path = toy_storage_local_path($key);
    return is_string($path) && is_file($path) ? @unlink($path) : true;
}

function toy_storage_head(string $driver, string $key, ?array $config = null): ?array
{
    if (!toy_storage_key_is_safe($key)) {
        return null;
    }

    $driver = strtolower(trim($driver));
    if ($driver === 's3') {
        return toy_storage_s3_head($config ?? toy_runtime_config(), $key);
    }

    $path = toy_storage_local_path($key);
    if (!is_string($path) || !is_file($path)) {
        return null;
    }

    return [
        'content_length' => (int) filesize($path),
        'content_type' => toy_upload_detect_mime($path),
        'metadata' => [
            'sha256' => hash_file('sha256', $path) ?: '',
        ],
    ];
}

function toy_storage_public_url(string $driver, string $key, ?array $config = null): string
{
    if ($driver !== 's3' || !toy_storage_key_is_safe($key)) {
        return '';
    }

    $s3 = toy_storage_s3_config($config ?? toy_runtime_config());
    $baseUrl = rtrim((string) ($s3['public_base_url'] ?? ''), '/');
    if ($baseUrl === '' || !toy_is_http_url($baseUrl)) {
        return '';
    }

    return $baseUrl . '/' . toy_storage_uri_encode($key);
}

function toy_storage_signed_url(string $driver, string $key, int $ttlSeconds = 300, array $options = [], ?array $config = null): string
{
    if ($driver !== 's3' || !toy_storage_key_is_safe($key)) {
        return '';
    }

    return toy_storage_s3_presigned_url($config ?? toy_runtime_config(), $key, $ttlSeconds, $options);
}

function toy_storage_local_put_file(string $sourcePath, string $key, array $options = []): array
{
    $targetPath = TOY_ROOT . '/storage/' . str_replace('/', DIRECTORY_SEPARATOR, $key);
    $directory = dirname($targetPath);
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('로컬 저장 디렉터리를 만들 수 없습니다.');
    }

    $targetPath = toy_upload_safe_target_path($directory, basename($key));
    toy_upload_assert_target_path_writable($targetPath, !empty($options['overwrite']));
    if (!copy($sourcePath, $targetPath)) {
        throw new RuntimeException('로컬 저장소에 파일을 저장할 수 없습니다.');
    }

    return [
        'driver' => 'local',
        'key' => $key,
        'path' => $targetPath,
        'url' => '',
    ];
}

function toy_storage_local_path(string $key): ?string
{
    if (!toy_storage_key_is_safe($key)) {
        return null;
    }

    $storageRoot = realpath(TOY_ROOT . '/storage');
    if (!is_string($storageRoot) || !is_dir($storageRoot)) {
        return null;
    }

    $candidate = $storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
    $realPath = realpath($candidate);
    if (!is_string($realPath) || !is_file($realPath)) {
        return null;
    }

    $storagePrefix = rtrim($storageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return str_starts_with($realPath, $storagePrefix) ? $realPath : null;
}

function toy_storage_s3_config(array $config): array
{
    $storage = isset($config['storage']) && is_array($config['storage']) ? $config['storage'] : [];
    $s3 = isset($storage['s3']) && is_array($storage['s3']) ? $storage['s3'] : [];
    $accessKey = toy_storage_env_or_value($s3, 'access_key', 'access_key_env');
    $secretKey = toy_storage_env_or_value($s3, 'secret_key', 'secret_key_env');

    return [
        'bucket' => trim((string) ($s3['bucket'] ?? '')),
        'region' => trim((string) ($s3['region'] ?? 'us-east-1')),
        'endpoint' => rtrim(trim((string) ($s3['endpoint'] ?? '')), '/'),
        'public_base_url' => trim((string) ($s3['public_base_url'] ?? '')),
        'path_style' => !empty($s3['path_style']),
        'access_key' => $accessKey,
        'secret_key' => $secretKey,
    ];
}

function toy_storage_env_or_value(array $settings, string $valueKey, string $envKey): string
{
    $envName = trim((string) ($settings[$envKey] ?? ''));
    if ($envName !== '' && preg_match('/\A[A-Z][A-Z0-9_]{1,80}\z/', $envName) === 1) {
        $envValue = getenv($envName);
        if (is_string($envValue) && $envValue !== '') {
            return $envValue;
        }
    }

    return (string) ($settings[$valueKey] ?? '');
}

function toy_storage_s3_ready(array $config): bool
{
    try {
        $s3 = toy_storage_s3_config($config);
        return toy_storage_s3_bucket_is_valid($s3['bucket'])
            && $s3['region'] !== ''
            && $s3['access_key'] !== ''
            && $s3['secret_key'] !== ''
            && toy_storage_s3_config_errors($config) === [];
    } catch (Throwable $exception) {
        return false;
    }
}

function toy_storage_s3_config_errors(array $config): array
{
    $errors = [];
    $s3 = toy_storage_s3_config($config);
    $env = (string) ($config['env'] ?? 'production');

    if ($s3['bucket'] !== '' && !toy_storage_s3_bucket_is_valid($s3['bucket'])) {
        $errors[] = 'S3 bucket 형식이 올바르지 않습니다.';
    }

    foreach (['endpoint', 'public_base_url'] as $key) {
        $url = (string) $s3[$key];
        if ($url === '') {
            continue;
        }

        if (!toy_is_http_url($url)) {
            $errors[] = 'S3 ' . $key . ' URL이 올바르지 않습니다.';
            continue;
        }

        if ($env === 'production' && strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            $errors[] = '운영 환경의 S3 ' . $key . ' URL은 HTTPS여야 합니다.';
        }
    }

    return $errors;
}

function toy_storage_s3_put_file(array $config, string $sourcePath, string $key, array $options = []): array
{
    $contentType = (string) ($options['content_type'] ?? 'application/octet-stream');
    $body = file_get_contents($sourcePath);
    if (!is_string($body)) {
        throw new RuntimeException('S3에 저장할 파일을 읽을 수 없습니다.');
    }

    $headers = [
        'content-type' => toy_download_content_type($contentType),
        'x-amz-meta-sha256' => hash_file('sha256', $sourcePath) ?: '',
    ];
    $response = toy_storage_s3_request($config, 'PUT', $key, [], $headers, $body);
    if ($response['status'] < 200 || $response['status'] >= 300) {
        throw new RuntimeException('S3 저장에 실패했습니다.');
    }

    return [
        'driver' => 's3',
        'key' => $key,
        'path' => '',
        'url' => toy_storage_public_url('s3', $key, $config),
    ];
}

function toy_storage_s3_head(array $config, string $key): ?array
{
    try {
        $response = toy_storage_s3_request($config, 'HEAD', $key, [], [], '');
    } catch (Throwable $exception) {
        return null;
    }

    if ($response['status'] < 200 || $response['status'] >= 300) {
        return null;
    }

    $headers = $response['headers'];
    return [
        'content_length' => (int) ($headers['content-length'] ?? 0),
        'content_type' => (string) ($headers['content-type'] ?? ''),
        'metadata' => [
            'sha256' => (string) ($headers['x-amz-meta-sha256'] ?? ''),
        ],
    ];
}

function toy_storage_s3_delete(array $config, string $key): bool
{
    try {
        $response = toy_storage_s3_request($config, 'DELETE', $key, [], [], '');
    } catch (Throwable $exception) {
        return false;
    }

    return $response['status'] >= 200 && $response['status'] < 300;
}

function toy_storage_s3_presigned_url(array $config, string $key, int $ttlSeconds, array $options = []): string
{
    $s3 = toy_storage_s3_config($config);
    toy_storage_s3_assert_config($s3);
    $urlParts = toy_storage_s3_object_url_parts($s3, $key);
    $now = gmdate('Ymd\THis\Z');
    $date = substr($now, 0, 8);
    $ttlSeconds = max(60, min(3600, $ttlSeconds));
    $scope = $date . '/' . $s3['region'] . '/s3/aws4_request';

    $query = [
        'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential' => $s3['access_key'] . '/' . $scope,
        'X-Amz-Date' => $now,
        'X-Amz-Expires' => (string) $ttlSeconds,
        'X-Amz-SignedHeaders' => 'host',
    ];
    foreach (['response-content-type', 'response-content-disposition'] as $keyName) {
        if (isset($options[$keyName]) && is_string($options[$keyName]) && $options[$keyName] !== '') {
            $query[$keyName] = $options[$keyName];
        }
    }

    $canonicalRequest = toy_storage_s3_canonical_request('GET', $urlParts['path'], $query, ['host' => $urlParts['host']], 'host', 'UNSIGNED-PAYLOAD');
    $signature = toy_storage_s3_signature($s3['secret_key'], $s3['region'], $date, $now, $canonicalRequest);
    $query['X-Amz-Signature'] = $signature;

    return $urlParts['base_url'] . '?' . toy_storage_s3_canonical_query($query);
}

function toy_storage_s3_request(array $config, string $method, string $key, array $query, array $headers, string $body): array
{
    $s3 = toy_storage_s3_config($config);
    toy_storage_s3_assert_config($s3);
    $urlParts = toy_storage_s3_object_url_parts($s3, $key);
    $payloadHash = hash('sha256', $body);
    $now = gmdate('Ymd\THis\Z');
    $date = substr($now, 0, 8);
    $headers = array_change_key_case($headers, CASE_LOWER);
    $headers['host'] = $urlParts['host'];
    $headers['x-amz-content-sha256'] = $payloadHash;
    $headers['x-amz-date'] = $now;
    ksort($headers, SORT_STRING);

    $signedHeaders = implode(';', array_keys($headers));
    $canonicalRequest = toy_storage_s3_canonical_request($method, $urlParts['path'], $query, $headers, $signedHeaders, $payloadHash);
    $credentialScope = $date . '/' . $s3['region'] . '/s3/aws4_request';
    $signature = toy_storage_s3_signature($s3['secret_key'], $s3['region'], $date, $now, $canonicalRequest);
    $headers['authorization'] = 'AWS4-HMAC-SHA256 Credential=' . $s3['access_key'] . '/' . $credentialScope . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

    $url = $urlParts['base_url'] . ($query === [] ? '' : '?' . toy_storage_s3_canonical_query($query));
    return toy_storage_http_request($method, $url, $headers, $body);
}

function toy_storage_s3_assert_config(array $s3): void
{
    if (!toy_storage_s3_bucket_is_valid($s3['bucket']) || $s3['region'] === '' || $s3['access_key'] === '' || $s3['secret_key'] === '') {
        throw new RuntimeException('S3 저장소 설정을 확인하세요.');
    }
}

function toy_storage_s3_bucket_is_valid(string $bucket): bool
{
    return preg_match('/\A[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]\z/', $bucket) === 1 && !str_contains($bucket, '..');
}

function toy_storage_s3_object_url_parts(array $s3, string $key): array
{
    $encodedKey = toy_storage_uri_encode($key);
    $endpoint = (string) $s3['endpoint'];
    $bucket = (string) $s3['bucket'];

    if ($endpoint === '') {
        $host = $s3['region'] === 'us-east-1'
            ? $bucket . '.s3.amazonaws.com'
            : $bucket . '.s3.' . $s3['region'] . '.amazonaws.com';
        return [
            'host' => $host,
            'path' => '/' . $encodedKey,
            'base_url' => 'https://' . $host . '/' . $encodedKey,
        ];
    }

    if (!toy_is_http_url($endpoint)) {
        throw new RuntimeException('S3 endpoint URL이 올바르지 않습니다.');
    }

    $scheme = (string) parse_url($endpoint, PHP_URL_SCHEME);
    $host = (string) parse_url($endpoint, PHP_URL_HOST);
    $port = parse_url($endpoint, PHP_URL_PORT);
    $basePath = rtrim((string) parse_url($endpoint, PHP_URL_PATH), '/');
    $portPart = is_int($port) ? ':' . (string) $port : '';
    if (!empty($s3['path_style'])) {
        $path = $basePath . '/' . $bucket . '/' . $encodedKey;
        $urlHost = $host . $portPart;
    } else {
        $path = $basePath . '/' . $encodedKey;
        $urlHost = $bucket . '.' . $host . $portPart;
    }

    return [
        'host' => $urlHost,
        'path' => $path === '' ? '/' : $path,
        'base_url' => $scheme . '://' . $urlHost . ($path === '' ? '/' : $path),
    ];
}

function toy_storage_s3_canonical_request(string $method, string $path, array $query, array $headers, string $signedHeaders, string $payloadHash): string
{
    $canonicalHeaders = '';
    ksort($headers, SORT_STRING);
    foreach ($headers as $name => $value) {
        $canonicalHeaders .= strtolower((string) $name) . ':' . trim(preg_replace('/\s+/', ' ', (string) $value) ?? '') . "\n";
    }

    return strtoupper($method) . "\n"
        . ($path === '' ? '/' : $path) . "\n"
        . toy_storage_s3_canonical_query($query) . "\n"
        . $canonicalHeaders . "\n"
        . $signedHeaders . "\n"
        . $payloadHash;
}

function toy_storage_s3_canonical_query(array $query): string
{
    ksort($query, SORT_STRING);
    $pairs = [];
    foreach ($query as $key => $value) {
        $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
    }

    return implode('&', $pairs);
}

function toy_storage_s3_signature(string $secretKey, string $region, string $date, string $amzDate, string $canonicalRequest): string
{
    $scope = $date . '/' . $region . '/s3/aws4_request';
    $stringToSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
    $dateKey = hash_hmac('sha256', $date, 'AWS4' . $secretKey, true);
    $dateRegionKey = hash_hmac('sha256', $region, $dateKey, true);
    $dateRegionServiceKey = hash_hmac('sha256', 's3', $dateRegionKey, true);
    $signingKey = hash_hmac('sha256', 'aws4_request', $dateRegionServiceKey, true);

    return hash_hmac('sha256', $stringToSign, $signingKey);
}

function toy_storage_uri_encode(string $value): string
{
    return str_replace('%2F', '/', rawurlencode($value));
}

function toy_storage_http_request(string $method, string $url, array $headers, string $body): array
{
    $headerLines = [];
    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }

    if (function_exists('curl_init')) {
        return toy_storage_http_request_with_curl($method, $url, $headerLines, $body);
    }

    return toy_storage_http_request_with_stream($method, $url, $headerLines, $body);
}

function toy_storage_http_request_with_curl(string $method, string $url, array $headerLines, string $body): array
{
    $handle = curl_init($url);
    if ($handle === false) {
        throw new RuntimeException('HTTP 요청을 준비할 수 없습니다.');
    }

    curl_setopt($handle, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($handle, CURLOPT_HTTPHEADER, $headerLines);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_HEADER, true);
    curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($handle, CURLOPT_TIMEOUT, 30);
    if ($method !== 'HEAD') {
        curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
    } else {
        curl_setopt($handle, CURLOPT_NOBODY, true);
    }

    $response = curl_exec($handle);
    if (!is_string($response)) {
        curl_close($handle);
        throw new RuntimeException('HTTP 요청에 실패했습니다.');
    }

    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    curl_close($handle);

    return [
        'status' => $status,
        'headers' => toy_storage_parse_headers(substr($response, 0, $headerSize)),
        'body' => substr($response, $headerSize),
    ];
}

function toy_storage_http_request_with_stream(string $method, string $url, array $headerLines, string $body): array
{
    $context = stream_context_create([
        'http' => [
            'method' => strtoupper($method),
            'timeout' => 30,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'header' => implode("\r\n", $headerLines) . "\r\n",
            'content' => $method === 'HEAD' ? '' : $body,
        ],
    ]);

    set_error_handler(static function (): bool {
        return true;
    });
    $stream = fopen($url, 'r', false, $context);
    restore_error_handler();
    if (!is_resource($stream)) {
        throw new RuntimeException('HTTP 요청에 실패했습니다.');
    }

    $metadata = stream_get_meta_data($stream);
    $responseBody = stream_get_contents($stream);
    fclose($stream);
    $responseHeaders = is_array($metadata['wrapper_data'] ?? null) ? $metadata['wrapper_data'] : [];
    $status = 0;
    foreach ($responseHeaders as $header) {
        if (preg_match('/\AHTTP\/\S+\s+(\d{3})\b/', (string) $header, $matches) === 1) {
            $status = (int) $matches[1];
        }
    }

    return [
        'status' => $status,
        'headers' => toy_storage_parse_headers(implode("\r\n", $responseHeaders)),
        'body' => is_string($responseBody) ? $responseBody : '',
    ];
}

function toy_storage_parse_headers(string $rawHeaders): array
{
    $headers = [];
    foreach (preg_split('/\r\n|\n|\r/', $rawHeaders) ?: [] as $line) {
        if (!is_string($line) || strpos($line, ':') === false) {
            continue;
        }

        [$name, $value] = explode(':', $line, 2);
        $headers[strtolower(trim($name))] = trim($value);
    }

    return $headers;
}
