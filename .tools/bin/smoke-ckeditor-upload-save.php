#!/usr/bin/env php
<?php

declare(strict_types=1);

function sr_ckeditor_smoke_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return is_string($value) && $value !== '' ? $value : $default;
}

function sr_ckeditor_smoke_requires_public_mutation_override(string $baseUrl): bool
{
    $host = parse_url($baseUrl, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return true;
    }

    $host = strtolower(trim($host, '[]'));
    if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
        return false;
    }
    if (preg_match('/\A127\./', $host) === 1 || preg_match('/\A10\./', $host) === 1 || preg_match('/\A192\.168\./', $host) === 1) {
        return false;
    }
    if (preg_match('/\A172\.(1[6-9]|2[0-9]|3[0-1])\./', $host) === 1) {
        return false;
    }
    foreach (['.localhost', '.local', '.test', '.invalid'] as $suffix) {
        if (str_ends_with($host, $suffix)) {
            return false;
        }
    }

    return true;
}

function sr_ckeditor_smoke_usage(): string
{
    return "Usage: SR_SMOKE_ALLOW_MUTATION=1 SR_SMOKE_BASE_URL=http://127.0.0.1:8080 SR_SMOKE_ADMIN_IDENTIFIER=admin SR_SMOKE_ADMIN_PASSWORD=password php .tools/bin/smoke-ckeditor-upload-save.php\n"
        . "Optional: SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1\n"
        . "This smoke uploads CKEditor body images and creates disposable published and draft content items. Run only against local or staging disposable data.\n";
}

function sr_ckeditor_smoke_fail(string $message, int $exitCode = 1): never
{
    fwrite(STDERR, "saanraan CKEditor upload/save HTTP smoke failed:\n- " . $message . "\n");
    exit($exitCode);
}

function sr_ckeditor_smoke_url(string $baseUrl, string $path): string
{
    return $baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path);
}

function sr_ckeditor_smoke_path_from_url(string $baseUrl, string $url): string
{
    $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if ($url === '') {
        return '';
    }

    if (str_starts_with($url, '?')) {
        return '/' . $url;
    }

    $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
    $query = (string) (parse_url($url, PHP_URL_QUERY) ?? '');
    if ($path === '' && str_starts_with($url, '/')) {
        $parts = explode('?', $url, 2);
        $path = (string) ($parts[0] ?? '');
        $query = (string) ($parts[1] ?? '');
    }

    $basePath = rtrim((string) (parse_url($baseUrl, PHP_URL_PATH) ?? ''), '/');
    if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
        $path = substr($path, strlen($basePath));
    }

    return $path !== '' ? $path . ($query !== '' ? '?' . $query : '') : $url;
}

function sr_ckeditor_smoke_cookie_header(array $cookies): string
{
    $pairs = [];
    foreach ($cookies as $name => $value) {
        $pairs[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $value);
    }

    return implode('; ', $pairs);
}

function sr_ckeditor_smoke_store_cookies(array $headers, array &$cookies): void
{
    foreach ($headers as $header) {
        if (preg_match('/\ASet-Cookie:\s*([^=;\s]+)=([^;]*)/i', (string) $header, $matches) === 1) {
            $cookies[(string) $matches[1]] = urldecode((string) $matches[2]);
        }
    }
}

function sr_ckeditor_smoke_header_value(array $headers, string $name): string
{
    foreach ($headers as $header) {
        if (stripos((string) $header, $name . ':') === 0) {
            return trim(substr((string) $header, strlen($name) + 1));
        }
    }

    return '';
}

function sr_ckeditor_smoke_status(array $headers): int
{
    $status = 0;
    foreach ($headers as $header) {
        if (preg_match('#\AHTTP/\S+\s+(\d{3})#', (string) $header, $matches) === 1) {
            $status = (int) $matches[1];
        }
    }

    return $status;
}

function sr_ckeditor_smoke_request(string $baseUrl, string $method, string $path, array $postData, array &$cookies): array
{
    $headers = ["User-Agent: Saanraan-CKEditor-Smoke"];
    if ($cookies !== []) {
        $headers[] = 'Cookie: ' . sr_ckeditor_smoke_cookie_header($cookies);
    }

    $content = '';
    if ($method === 'POST') {
        $content = http_build_query($postData);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $headers[] = 'Content-Length: ' . strlen($content);
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'timeout' => 20,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $content,
        ],
    ]);

    set_error_handler(static function (): bool {
        return true;
    });
    $body = file_get_contents(sr_ckeditor_smoke_url($baseUrl, $path), false, $context);
    restore_error_handler();

    $responseHeaders = function_exists('http_get_last_response_headers')
        ? http_get_last_response_headers()
        : ($http_response_header ?? []);
    $responseHeaders = is_array($responseHeaders) ? $responseHeaders : [];
    sr_ckeditor_smoke_store_cookies($responseHeaders, $cookies);

    return [
        'status' => sr_ckeditor_smoke_status($responseHeaders),
        'body' => is_string($body) ? $body : '',
        'headers' => $responseHeaders,
        'location' => sr_ckeditor_smoke_header_value($responseHeaders, 'Location'),
    ];
}

function sr_ckeditor_smoke_multipart_request(string $baseUrl, string $path, array $fields, string $fileField, string $fileName, string $fileContent, string $contentType, array &$cookies): array
{
    $boundary = '----saanraanCkeditorSmoke' . bin2hex(random_bytes(8));
    $body = '';
    foreach ($fields as $name => $value) {
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="' . addcslashes((string) $name, "\"\\") . "\"\r\n\r\n";
        $body .= (string) $value . "\r\n";
    }
    $body .= '--' . $boundary . "\r\n";
    $body .= 'Content-Disposition: form-data; name="' . addcslashes($fileField, "\"\\") . '"; filename="' . addcslashes($fileName, "\"\\") . "\"\r\n";
    $body .= 'Content-Type: ' . $contentType . "\r\n\r\n";
    $body .= $fileContent . "\r\n";
    $body .= '--' . $boundary . "--\r\n";

    $headers = [
        'User-Agent: Saanraan-CKEditor-Smoke',
        'Content-Type: multipart/form-data; boundary=' . $boundary,
        'Content-Length: ' . strlen($body),
    ];
    if ($cookies !== []) {
        $headers[] = 'Cookie: ' . sr_ckeditor_smoke_cookie_header($cookies);
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'timeout' => 20,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $body,
        ],
    ]);

    set_error_handler(static function (): bool {
        return true;
    });
    $responseBody = file_get_contents(sr_ckeditor_smoke_url($baseUrl, $path), false, $context);
    restore_error_handler();

    $responseHeaders = function_exists('http_get_last_response_headers')
        ? http_get_last_response_headers()
        : ($http_response_header ?? []);
    $responseHeaders = is_array($responseHeaders) ? $responseHeaders : [];
    sr_ckeditor_smoke_store_cookies($responseHeaders, $cookies);

    return [
        'status' => sr_ckeditor_smoke_status($responseHeaders),
        'body' => is_string($responseBody) ? $responseBody : '',
        'headers' => $responseHeaders,
        'location' => sr_ckeditor_smoke_header_value($responseHeaders, 'Location'),
    ];
}

function sr_ckeditor_smoke_hidden_value(string $body, string $field): string
{
    $quoted = preg_quote($field, '/');
    if (preg_match_all('/<input\b[^>]*>/i', $body, $matches) === false) {
        return '';
    }

    foreach ($matches[0] as $input) {
        if (preg_match('/\bname="' . $quoted . '"/i', (string) $input) !== 1) {
            continue;
        }
        if (preg_match('/\bvalue="([^"]*)"/i', (string) $input, $valueMatch) === 1) {
            return html_entity_decode((string) $valueMatch[1], ENT_QUOTES, 'UTF-8');
        }
    }

    return '';
}

function sr_ckeditor_smoke_textarea_tag(string $body, string $name): string
{
    $quoted = preg_quote($name, '/');
    if (preg_match('/<textarea\b[^>]*\bname="' . $quoted . '"[^>]*>/i', $body, $matches) === 1) {
        return (string) $matches[0];
    }

    return '';
}

function sr_ckeditor_smoke_attr(string $tag, string $name): string
{
    $quoted = preg_quote($name, '/');
    if (preg_match('/\b' . $quoted . '="([^"]*)"/i', $tag, $matches) === 1) {
        return html_entity_decode((string) $matches[1], ENT_QUOTES, 'UTF-8');
    }

    return '';
}

function sr_ckeditor_smoke_first_content_body_file_url(string $body): string
{
    if (preg_match_all('/<img\b[^>]*>/i', $body, $matches) === false) {
        return '';
    }

    foreach ($matches[0] as $imageTag) {
        $src = sr_ckeditor_smoke_attr((string) $imageTag, 'src');
        if ($src !== '' && str_contains(html_entity_decode($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), '/content/body-file?') && str_contains($src, 'content_id=')) {
            return $src;
        }
    }

    return '';
}

function sr_ckeditor_smoke_visible_content_body(string $body, string $marker): string
{
    if ($marker !== '' && preg_match_all('/<div\b[^>]*class="[^"]*\bcontent-body\b[^"]*"[^>]*>.*?<\/div>/is', $body, $matches) !== false) {
        foreach ($matches[0] as $contentBody) {
            if (str_contains((string) $contentBody, $marker)) {
                return (string) $contentBody;
            }
        }
    }

    return $body;
}

function sr_ckeditor_smoke_admin_login(string $baseUrl, string $identifier, string $password, array &$cookies): void
{
    $form = sr_ckeditor_smoke_request($baseUrl, 'GET', '/login', [], $cookies);
    if ((int) $form['status'] !== 200) {
        sr_ckeditor_smoke_fail('Login form returned HTTP ' . (string) $form['status'] . '.', 2);
    }

    $csrf = sr_ckeditor_smoke_hidden_value((string) $form['body'], 'csrf_token');
    if ($csrf === '') {
        sr_ckeditor_smoke_fail('Login CSRF token not found.', 2);
    }

    $login = sr_ckeditor_smoke_request($baseUrl, 'POST', '/login', [
        'csrf_token' => $csrf,
        'identifier' => $identifier,
        'password' => $password,
        'next' => '/admin/content/new',
    ], $cookies);
    if (!in_array((int) $login['status'], [302, 303], true)) {
        sr_ckeditor_smoke_fail('Login submit returned HTTP ' . (string) $login['status'] . '.', 2);
    }
}

function sr_ckeditor_smoke_content_save_payload(string $csrf, string $title, string $slug, string $summary, string $bodyHtml, string $status, array $overrides = []): array
{
    return array_merge([
        'csrf_token' => $csrf,
        'content_id' => '0',
        'content_group_scope' => 'here_only',
        'content_group_id' => '0',
        'series_id' => '0',
        'series_episode_label' => '',
        'series_sort_order' => '0',
        'source_status' => 'content',
        'source_layout_key' => 'content',
        'title' => $title,
        'slug' => $slug,
        'summary' => $summary,
        'cover_image_url' => '',
        'body_text' => $bodyHtml,
        'body_format' => 'html',
        'status' => $status,
        'scheduled_publish_at' => '',
        'layout_key' => '',
        'asset_access_enabled' => '0',
        'asset_module' => '',
        'asset_access_amount' => '0',
        'asset_charge_policy' => 'once',
        'asset_action_enabled' => '0',
        'asset_action_module' => '',
        'asset_action_amount' => '0',
        'asset_action_direction' => 'grant',
        'asset_action_label' => '',
        'banner_before_content_id' => '0',
        'banner_after_content_id' => '0',
        'popup_layer_id' => '0',
        'source_banner_before_content_id' => 'content',
        'source_banner_after_content_id' => 'content',
        'source_popup_layer_id' => 'content',
        'seo_title' => '',
        'seo_description' => '',
    ], $overrides);
}

function sr_ckeditor_smoke_runtime_context(string $adminIdentifier): ?array
{
    $root = dirname(__DIR__, 2);
    $configPath = $root . '/config/config.php';
    $helpersPath = $root . '/core/helpers.php';
    $memberHelpersPath = $root . '/modules/member/helpers.php';
    $contentHelpersPath = $root . '/modules/content/helpers.php';
    if (!is_readable($configPath) || !is_readable($helpersPath) || !is_readable($memberHelpersPath) || !is_readable($contentHelpersPath)) {
        return null;
    }

    if (!defined('SR_ROOT')) {
        define('SR_ROOT', $root);
    }
    require_once $helpersPath;
    require_once $memberHelpersPath;
    require_once $contentHelpersPath;

    try {
        $config = sr_load_config();
        sr_set_runtime_config($config);
        if (!sr_is_installed($config)) {
            return null;
        }
        $pdo = sr_db($config);
        $account = sr_member_find_by_identifier($pdo, $config, $adminIdentifier, true);
        if (!is_array($account)) {
            return null;
        }

        return [
            'config' => $config,
            'pdo' => $pdo,
            'admin_account_id' => (int) ($account['id'] ?? 0),
        ];
    } catch (Throwable) {
        return null;
    }
}

function sr_ckeditor_smoke_content_id(PDO $pdo, string $slug): int
{
    if (!function_exists('sr_content_by_slug')) {
        return 0;
    }
    $page = sr_content_by_slug($pdo, $slug);
    return is_array($page) ? (int) ($page['id'] ?? 0) : 0;
}

function sr_ckeditor_smoke_prepare_content_editor(?array $runtimeContext): void
{
    if (!is_array($runtimeContext) || !isset($runtimeContext['pdo']) || !$runtimeContext['pdo'] instanceof PDO || !function_exists('sr_content_save_settings')) {
        return;
    }

    $pdo = $runtimeContext['pdo'];
    $settings = function_exists('sr_content_settings') ? sr_content_settings($pdo) : [];
    $settings['editor'] = 'ckeditor';
    $settings['editor_toolbar_preset'] = (string) ($settings['editor_toolbar_preset'] ?? 'content_basic');
    sr_content_save_settings($pdo, $settings);
}

$baseUrl = rtrim(sr_ckeditor_smoke_env('SR_SMOKE_BASE_URL'), '/');
$adminIdentifier = sr_ckeditor_smoke_env('SR_SMOKE_ADMIN_IDENTIFIER');
$adminPassword = sr_ckeditor_smoke_env('SR_SMOKE_ADMIN_PASSWORD');

if ($baseUrl === '' || !preg_match('#\Ahttps?://#', $baseUrl) || $adminIdentifier === '' || $adminPassword === '') {
    fwrite(STDERR, sr_ckeditor_smoke_usage());
    exit(2);
}
if (getenv('SR_SMOKE_ALLOW_MUTATION') !== '1') {
    fwrite(STDERR, "saanraan CKEditor upload/save HTTP smoke refused to run because it creates content and uploads files. Set SR_SMOKE_ALLOW_MUTATION=1 only on local or staging disposable data.\n");
    fwrite(STDERR, sr_ckeditor_smoke_usage());
    exit(2);
}
if (sr_ckeditor_smoke_requires_public_mutation_override($baseUrl) && getenv('SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL') !== '1') {
    fwrite(STDERR, "saanraan CKEditor upload/save HTTP smoke refused to run against a public-looking base URL. Set SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1 only for staging disposable data.\n");
    fwrite(STDERR, sr_ckeditor_smoke_usage());
    exit(2);
}

$cookies = [];
sr_ckeditor_smoke_admin_login($baseUrl, $adminIdentifier, $adminPassword, $cookies);
$runtimeContext = sr_ckeditor_smoke_runtime_context($adminIdentifier);
sr_ckeditor_smoke_prepare_content_editor($runtimeContext);

$form = sr_ckeditor_smoke_request($baseUrl, 'GET', '/admin/content/new', [], $cookies);
if ((int) $form['status'] !== 200) {
    sr_ckeditor_smoke_fail('Content creation form returned HTTP ' . (string) $form['status'] . '.', 2);
}

$formBody = (string) $form['body'];
$csrf = sr_ckeditor_smoke_hidden_value($formBody, 'csrf_token');
if ($csrf === '') {
    sr_ckeditor_smoke_fail('Content form CSRF token not found.', 2);
}

$textarea = sr_ckeditor_smoke_textarea_tag($formBody, 'body_text');
if ($textarea === '') {
    sr_ckeditor_smoke_fail('Content body textarea not found.', 2);
}
if (sr_ckeditor_smoke_attr($textarea, 'data-sr-editor') !== 'ckeditor') {
    sr_ckeditor_smoke_fail('Content body textarea is not configured for CKEditor.', 2);
}

$uploadPath = sr_ckeditor_smoke_attr($textarea, 'data-sr-editor-upload-url');
$uploadField = sr_ckeditor_smoke_attr($textarea, 'data-sr-editor-upload-field');
$uploadCsrf = sr_ckeditor_smoke_attr($textarea, 'data-sr-editor-upload-csrf');
$uploadToken = sr_ckeditor_smoke_attr($textarea, 'data-sr-editor-upload-token');
if ($uploadPath === '' || $uploadField === '' || $uploadCsrf === '' || $uploadToken === '') {
    sr_ckeditor_smoke_fail('CKEditor upload textarea attributes are incomplete.', 2);
}

$tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
if (!is_string($tinyPng)) {
    sr_ckeditor_smoke_fail('Fixture image could not be decoded.', 2);
}

$upload = sr_ckeditor_smoke_multipart_request($baseUrl, $uploadPath, [
    'csrf_token' => $uploadCsrf,
    'upload_token' => $uploadToken,
], $uploadField, 'ckeditor-smoke.png', $tinyPng, 'image/png', $cookies);
if ((int) $upload['status'] !== 200) {
    sr_ckeditor_smoke_fail('CKEditor body image upload returned HTTP ' . (string) $upload['status'] . '.', 1);
}
$uploadJson = json_decode((string) $upload['body'], true);
if (!is_array($uploadJson) || !is_string($uploadJson['url'] ?? null) || (string) $uploadJson['url'] === '') {
    sr_ckeditor_smoke_fail('CKEditor body image upload did not return a usable JSON url.', 1);
}
$uploadedUrl = (string) $uploadJson['url'];
$uploadedPath = sr_ckeditor_smoke_path_from_url($baseUrl, $uploadedUrl);
if ($uploadedPath === '') {
    sr_ckeditor_smoke_fail('CKEditor body image upload returned an unusable proxy URL.', 1);
}

$temporaryAdminImage = sr_ckeditor_smoke_request($baseUrl, 'GET', $uploadedPath, [], $cookies);
if ((int) $temporaryAdminImage['status'] !== 200) {
    sr_ckeditor_smoke_fail('Temporary body image was not accessible to the editing administrator: HTTP ' . (string) $temporaryAdminImage['status'] . '.', 1);
}
$guestCookies = [];
$temporaryGuestImage = sr_ckeditor_smoke_request($baseUrl, 'GET', $uploadedPath, [], $guestCookies);
if ((int) $temporaryGuestImage['status'] !== 404) {
    sr_ckeditor_smoke_fail('Temporary body image was accessible without an administrator session: HTTP ' . (string) $temporaryGuestImage['status'] . '.', 1);
}

$stamp = gmdate('YmdHis') . '-' . bin2hex(random_bytes(3));
$slug = 'ckeditor-smoke-' . strtolower($stamp);
$payloadMarker = 'sr-ckeditor-smoke-' . $stamp;
$bodyHtml = '<h2>' . htmlspecialchars($payloadMarker, ENT_QUOTES, 'UTF-8') . '</h2>'
    . '<p><strong>CKEditor smoke body</strong></p>'
    . '<p><img src="' . htmlspecialchars($uploadedUrl, ENT_QUOTES, 'UTF-8') . '" alt="CKEditor smoke image"></p>'
    . '<script>alert("xss")</script><p><a href="javascript:alert(1)">blocked link</a></p>';

$save = sr_ckeditor_smoke_request(
    $baseUrl,
    'POST',
    '/admin/content/save',
    sr_ckeditor_smoke_content_save_payload($csrf, 'CKEditor Smoke ' . $stamp, $slug, 'CKEditor upload/save smoke', $bodyHtml, 'published'),
    $cookies
);
if (!in_array((int) $save['status'], [302, 303], true)) {
    sr_ckeditor_smoke_fail('Content save returned HTTP ' . (string) $save['status'] . '.', 1);
}

$public = sr_ckeditor_smoke_request($baseUrl, 'GET', '/content/' . $slug, [], $cookies);
if ((int) $public['status'] !== 200) {
    sr_ckeditor_smoke_fail('Saved content public page returned HTTP ' . (string) $public['status'] . '.', 1);
}
$publicBody = (string) $public['body'];
if (!str_contains($publicBody, $payloadMarker)) {
    sr_ckeditor_smoke_fail('Saved content body marker was not visible on the public page.', 1);
}
$visibleContentBody = sr_ckeditor_smoke_visible_content_body($publicBody, $payloadMarker);
if (stripos($visibleContentBody, '<script') !== false || stripos($visibleContentBody, 'javascript:alert') !== false) {
    sr_ckeditor_smoke_fail('Saved content public page still contains blocked script or javascript URL payload.', 1);
}

$savedImageUrl = sr_ckeditor_smoke_first_content_body_file_url($publicBody);
if ($savedImageUrl === '') {
    sr_ckeditor_smoke_fail('Saved content public page did not expose a finalized content body image URL.', 1);
}
$savedImagePath = sr_ckeditor_smoke_path_from_url($baseUrl, $savedImageUrl);
$savedGuestCookies = [];
$savedGuestImage = sr_ckeditor_smoke_request($baseUrl, 'GET', $savedImagePath, [], $savedGuestCookies);
if ((int) $savedGuestImage['status'] !== 200) {
    sr_ckeditor_smoke_fail('Saved public content body image was not accessible without a session: HTTP ' . (string) $savedGuestImage['status'] . '.', 1);
}

$finalizedTmpImage = sr_ckeditor_smoke_request($baseUrl, 'GET', $uploadedPath, [], $cookies);
if ((int) $finalizedTmpImage['status'] !== 404) {
    sr_ckeditor_smoke_fail('Temporary body image URL still resolved after content save: HTTP ' . (string) $finalizedTmpImage['status'] . '.', 1);
}

$paidImageAccessChecked = false;
if (is_array($runtimeContext) && isset($runtimeContext['pdo']) && $runtimeContext['pdo'] instanceof PDO && (int) ($runtimeContext['admin_account_id'] ?? 0) > 0) {
    $paidUpload = sr_ckeditor_smoke_multipart_request($baseUrl, $uploadPath, [
        'csrf_token' => $uploadCsrf,
        'upload_token' => $uploadToken,
    ], $uploadField, 'ckeditor-smoke-paid.png', $tinyPng, 'image/png', $cookies);
    if ((int) $paidUpload['status'] !== 200) {
        sr_ckeditor_smoke_fail('Paid CKEditor body image upload returned HTTP ' . (string) $paidUpload['status'] . '.', 1);
    }
    $paidUploadJson = json_decode((string) $paidUpload['body'], true);
    if (!is_array($paidUploadJson) || !is_string($paidUploadJson['url'] ?? null) || (string) $paidUploadJson['url'] === '') {
        sr_ckeditor_smoke_fail('Paid CKEditor body image upload did not return a usable JSON url.', 1);
    }

    $paidUploadedUrl = (string) $paidUploadJson['url'];
    $paidSlug = 'ckeditor-smoke-paid-' . strtolower($stamp);
    $paidPayloadMarker = 'sr-ckeditor-smoke-paid-' . $stamp;
    $paidBodyHtml = '<h2>' . htmlspecialchars($paidPayloadMarker, ENT_QUOTES, 'UTF-8') . '</h2>'
        . '<p><img src="' . htmlspecialchars($paidUploadedUrl, ENT_QUOTES, 'UTF-8') . '" alt="CKEditor paid smoke image"></p>';
    $paidSave = sr_ckeditor_smoke_request(
        $baseUrl,
        'POST',
        '/admin/content/save',
        sr_ckeditor_smoke_content_save_payload($csrf, 'CKEditor Paid Smoke ' . $stamp, $paidSlug, 'CKEditor paid body image smoke', $paidBodyHtml, 'published', [
            'asset_access_enabled' => '1',
            'asset_module' => 'point',
            'asset_access_amount' => '1',
            'asset_access_amounts' => ['point' => '1'],
            'source_asset_access_enabled' => 'content',
            'source_asset_module' => 'content',
            'source_asset_access_amount' => 'content',
            'source_asset_access_amounts_json' => 'content',
            'asset_charge_policy' => 'once',
            'source_asset_charge_policy' => 'content',
        ]),
        $cookies
    );
    if (!in_array((int) $paidSave['status'], [302, 303], true)) {
        sr_ckeditor_smoke_fail('Paid content save returned HTTP ' . (string) $paidSave['status'] . '.', 1);
    }

    $pdo = $runtimeContext['pdo'];
    $paidContentId = sr_ckeditor_smoke_content_id($pdo, $paidSlug);
    if ($paidContentId < 1) {
        sr_ckeditor_smoke_fail('Paid content row was not found after save.', 1);
    }
    $paidPage = sr_content_by_slug($pdo, $paidSlug);
    $paidSavedImageUrl = is_array($paidPage) ? sr_ckeditor_smoke_first_content_body_file_url((string) ($paidPage['body_text'] ?? '')) : '';
    if ($paidSavedImageUrl === '') {
        sr_ckeditor_smoke_fail('Paid content did not store a finalized body image URL.', 1);
    }
    $paidSavedImagePath = sr_ckeditor_smoke_path_from_url($baseUrl, $paidSavedImageUrl);

    $paidGuestImageCookies = [];
    $paidGuestImage = sr_ckeditor_smoke_request($baseUrl, 'GET', $paidSavedImagePath, [], $paidGuestImageCookies);
    if ((int) $paidGuestImage['status'] !== 404) {
        sr_ckeditor_smoke_fail('Paid content body image was accessible without a session before access grant: HTTP ' . (string) $paidGuestImage['status'] . '.', 1);
    }

    $paidAdminImageBeforeGrant = sr_ckeditor_smoke_request($baseUrl, 'GET', $paidSavedImagePath, [], $cookies);
    if ((int) $paidAdminImageBeforeGrant['status'] !== 404) {
        sr_ckeditor_smoke_fail('Paid content body image was accessible to a logged-in account without access entitlement: HTTP ' . (string) $paidAdminImageBeforeGrant['status'] . '.', 1);
    }

    sr_content_grant_access_entitlement($pdo, (int) $runtimeContext['admin_account_id'], $paidContentId, 'content', $paidContentId, 'view', 'smoke', 'point', 'once', 'ckeditor-paid-body-image-smoke:' . $paidSlug);
    $paidAdminImageAfterGrant = sr_ckeditor_smoke_request($baseUrl, 'GET', $paidSavedImagePath, [], $cookies);
    if ((int) $paidAdminImageAfterGrant['status'] !== 200) {
        sr_ckeditor_smoke_fail('Paid content body image was not accessible after access entitlement grant: HTTP ' . (string) $paidAdminImageAfterGrant['status'] . '.', 1);
    }
    $paidImageAccessChecked = true;
}

$draftUpload = sr_ckeditor_smoke_multipart_request($baseUrl, $uploadPath, [
    'csrf_token' => $uploadCsrf,
    'upload_token' => $uploadToken,
], $uploadField, 'ckeditor-smoke-draft.png', $tinyPng, 'image/png', $cookies);
if ((int) $draftUpload['status'] !== 200) {
    sr_ckeditor_smoke_fail('Draft CKEditor body image upload returned HTTP ' . (string) $draftUpload['status'] . '.', 1);
}
$draftUploadJson = json_decode((string) $draftUpload['body'], true);
if (!is_array($draftUploadJson) || !is_string($draftUploadJson['url'] ?? null) || (string) $draftUploadJson['url'] === '') {
    sr_ckeditor_smoke_fail('Draft CKEditor body image upload did not return a usable JSON url.', 1);
}
$draftUploadedUrl = (string) $draftUploadJson['url'];
$draftUploadedPath = sr_ckeditor_smoke_path_from_url($baseUrl, $draftUploadedUrl);
if ($draftUploadedPath === '') {
    sr_ckeditor_smoke_fail('Draft CKEditor body image upload returned an unusable proxy URL.', 1);
}

$draftTemporaryAdminImage = sr_ckeditor_smoke_request($baseUrl, 'GET', $draftUploadedPath, [], $cookies);
if ((int) $draftTemporaryAdminImage['status'] !== 200) {
    sr_ckeditor_smoke_fail('Draft temporary body image was not accessible to the editing administrator: HTTP ' . (string) $draftTemporaryAdminImage['status'] . '.', 1);
}
$draftTemporaryGuestCookies = [];
$draftTemporaryGuestImage = sr_ckeditor_smoke_request($baseUrl, 'GET', $draftUploadedPath, [], $draftTemporaryGuestCookies);
if ((int) $draftTemporaryGuestImage['status'] !== 404) {
    sr_ckeditor_smoke_fail('Draft temporary body image was accessible without an administrator session: HTTP ' . (string) $draftTemporaryGuestImage['status'] . '.', 1);
}

$draftSlug = 'ckeditor-smoke-draft-' . strtolower($stamp);
$draftPayloadMarker = 'sr-ckeditor-smoke-draft-' . $stamp;
$draftBodyHtml = '<h2>' . htmlspecialchars($draftPayloadMarker, ENT_QUOTES, 'UTF-8') . '</h2>'
    . '<p><img src="' . htmlspecialchars($draftUploadedUrl, ENT_QUOTES, 'UTF-8') . '" alt="CKEditor draft smoke image"></p>';
$draftSave = sr_ckeditor_smoke_request(
    $baseUrl,
    'POST',
    '/admin/content/save',
    sr_ckeditor_smoke_content_save_payload($csrf, 'CKEditor Draft Smoke ' . $stamp, $draftSlug, 'CKEditor draft body image smoke', $draftBodyHtml, 'draft'),
    $cookies
);
if (!in_array((int) $draftSave['status'], [302, 303], true)) {
    sr_ckeditor_smoke_fail('Draft content save returned HTTP ' . (string) $draftSave['status'] . '.', 1);
}

$draftPreview = sr_ckeditor_smoke_request($baseUrl, 'GET', '/content/' . $draftSlug, [], $cookies);
if ((int) $draftPreview['status'] !== 200) {
    sr_ckeditor_smoke_fail('Draft content admin preview returned HTTP ' . (string) $draftPreview['status'] . '.', 1);
}
$draftPreviewBody = (string) $draftPreview['body'];
if (!str_contains($draftPreviewBody, $draftPayloadMarker)) {
    sr_ckeditor_smoke_fail('Draft content body marker was not visible to the administrator preview.', 1);
}
$draftGuestPageCookies = [];
$draftGuestPage = sr_ckeditor_smoke_request($baseUrl, 'GET', '/content/' . $draftSlug, [], $draftGuestPageCookies);
if ((int) $draftGuestPage['status'] !== 404) {
    sr_ckeditor_smoke_fail('Draft content page was accessible without an administrator session: HTTP ' . (string) $draftGuestPage['status'] . '.', 1);
}

$draftSavedImageUrl = sr_ckeditor_smoke_first_content_body_file_url($draftPreviewBody);
if ($draftSavedImageUrl === '') {
    sr_ckeditor_smoke_fail('Draft content preview did not expose a finalized content body image URL.', 1);
}
$draftSavedImagePath = sr_ckeditor_smoke_path_from_url($baseUrl, $draftSavedImageUrl);
$draftSavedAdminImage = sr_ckeditor_smoke_request($baseUrl, 'GET', $draftSavedImagePath, [], $cookies);
if ((int) $draftSavedAdminImage['status'] !== 200) {
    sr_ckeditor_smoke_fail('Draft content body image was not accessible to the administrator: HTTP ' . (string) $draftSavedAdminImage['status'] . '.', 1);
}
$draftSavedGuestCookies = [];
$draftSavedGuestImage = sr_ckeditor_smoke_request($baseUrl, 'GET', $draftSavedImagePath, [], $draftSavedGuestCookies);
if ((int) $draftSavedGuestImage['status'] !== 404) {
    sr_ckeditor_smoke_fail('Draft content body image was accessible without an administrator session: HTTP ' . (string) $draftSavedGuestImage['status'] . '.', 1);
}

$draftFinalizedTmpImage = sr_ckeditor_smoke_request($baseUrl, 'GET', $draftUploadedPath, [], $cookies);
if ((int) $draftFinalizedTmpImage['status'] !== 404) {
    sr_ckeditor_smoke_fail('Draft temporary body image URL still resolved after content save: HTTP ' . (string) $draftFinalizedTmpImage['status'] . '.', 1);
}

echo "ckeditor-upload-save-http-smoke-version: 1\n";
echo "base-url: " . $baseUrl . "\n";
echo "paid-image-access-policy: " . ($paidImageAccessChecked ? 'yes' : 'skipped') . "\n";
echo "upload-status: " . (string) $upload['status'] . "\n";
echo "upload-json-url: yes\n";
echo "temporary-image-admin-access: yes\n";
echo "temporary-image-guest-blocked: yes\n";
echo "save-status: " . (string) $save['status'] . "\n";
echo "content-slug: " . $slug . "\n";
echo "public-status: " . (string) $public['status'] . "\n";
echo "public-body-marker: yes\n";
echo "finalized-image-url: yes\n";
echo "saved-image-guest-access: yes\n";
echo "temporary-image-finalized: yes\n";
echo "blocked-html-removed: yes\n";
echo "draft-upload-status: " . (string) $draftUpload['status'] . "\n";
echo "draft-save-status: " . (string) $draftSave['status'] . "\n";
echo "draft-content-slug: " . $draftSlug . "\n";
echo "draft-preview-admin-access: yes\n";
echo "draft-page-guest-blocked: yes\n";
echo "draft-finalized-image-url: yes\n";
echo "draft-image-admin-access: yes\n";
echo "draft-image-guest-blocked: yes\n";
echo "draft-temporary-image-finalized: yes\n";
echo "saanraan CKEditor upload/save HTTP smoke completed.\n";
