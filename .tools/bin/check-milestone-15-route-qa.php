#!/usr/bin/env php
<?php

declare(strict_types=1);

function m15_arg(array $argv, int $index, string $env, string $default = ''): string
{
    $arg = (string) ($argv[$index] ?? '');
    if ($arg !== '') {
        return $arg;
    }

    $value = getenv($env);
    return is_string($value) && $value !== '' ? $value : $default;
}

$baseUrl = rtrim(m15_arg($argv, 1, 'SR_M15_BASE_URL'), '/');
$adminIdentifier = m15_arg($argv, 2, 'SR_M15_ADMIN_IDENTIFIER', 'admin');
$adminPassword = m15_arg($argv, 3, 'SR_M15_ADMIN_PASSWORD', '12341234');

if ($baseUrl === '' || !preg_match('#\Ahttps?://#', $baseUrl)) {
    echo "milestone 15 route QA skipped: set SR_M15_BASE_URL or pass a base URL.\n";
    exit(0);
}

$errors = [];
$results = [];

function m15_url(string $baseUrl, string $path): string
{
    return $baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path);
}

function m15_cookie_header(array $cookies): string
{
    $pairs = [];
    foreach ($cookies as $name => $value) {
        $pairs[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $value);
    }

    return implode('; ', $pairs);
}

function m15_store_cookies(array $headers, array &$cookies): void
{
    foreach ($headers as $header) {
        if (preg_match('/\ASet-Cookie:\s*([^=;\s]+)=([^;]*)/i', (string) $header, $matches) === 1) {
            $cookies[(string) $matches[1]] = urldecode((string) $matches[2]);
        }
    }
}

function m15_request(string $baseUrl, string $method, string $path, array $postData = [], array &$cookies = []): array
{
    $headers = ['User-Agent: Saanraan-Milestone15-Route-QA'];
    if ($cookies !== []) {
        $headers[] = 'Cookie: ' . m15_cookie_header($cookies);
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
            'timeout' => 12,
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
    $body = file_get_contents(m15_url($baseUrl, $path), false, $context);
    restore_error_handler();

    $responseHeaders = function_exists('http_get_last_response_headers')
        ? http_get_last_response_headers()
        : ($http_response_header ?? []);
    m15_store_cookies($responseHeaders, $cookies);

    $status = 0;
    $location = '';
    foreach ($responseHeaders as $header) {
        if (preg_match('#\AHTTP/\S+\s+(\d{3})#', (string) $header, $matches) === 1) {
            $status = (int) $matches[1];
        }
        if (preg_match('#\ALocation:\s*(.+)\z#i', (string) $header, $matches) === 1) {
            $location = trim((string) $matches[1]);
        }
    }

    return [
        'status' => $status,
        'body' => is_string($body) ? $body : '',
        'location' => $location,
    ];
}

function m15_csrf(array $response): string
{
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', (string) $response['body'], $matches) === 1) {
        return html_entity_decode((string) $matches[1], ENT_QUOTES, 'UTF-8');
    }

    return '';
}

function m15_forbidden_body(array $response): array
{
    $body = (string) $response['body'];
    $needles = [
        'Fatal error',
        'Stack trace',
        'Parse error',
        'Warning:',
        '<?php',
        'CREATE TABLE',
        'password_hash VARCHAR',
        'ref: refs/',
    ];

    $found = [];
    foreach ($needles as $needle) {
        if (str_contains($body, $needle)) {
            $found[] = $needle;
        }
    }

    return $found;
}

function m15_check(string $issue, string $label, array $response, array $allowed, array &$results, array &$errors): void
{
    $status = (int) $response['status'];
    $ok = in_array($status, $allowed, true);
    $forbidden = m15_forbidden_body($response);
    $results[$issue]['checks'] = ($results[$issue]['checks'] ?? 0) + 1;

    if (!$ok || $forbidden !== []) {
        $results[$issue]['failures'][] = $label . ' status=' . (string) $status . ($forbidden !== [] ? ' forbidden=' . implode(',', $forbidden) : '');
        $errors[] = '#' . $issue . ' ' . $label . ' failed.';
        return;
    }

    $results[$issue]['passed'] = ($results[$issue]['passed'] ?? 0) + 1;
}

function m15_login(string $baseUrl, string $identifier, string $password, array &$errors): array
{
    $cookies = [];
    $form = m15_request($baseUrl, 'GET', '/login', [], $cookies);
    $csrf = m15_csrf($form);
    if ((int) $form['status'] !== 200 || $csrf === '') {
        $errors[] = 'admin login form is unavailable.';
        return $cookies;
    }

    $submit = m15_request($baseUrl, 'POST', '/login', [
        'csrf_token' => $csrf,
        'identifier' => $identifier,
        'password' => $password,
        'next' => '/admin',
    ], $cookies);

    if ((int) $submit['status'] !== 302) {
        $errors[] = 'admin login submit returned status ' . (string) $submit['status'] . '.';
    }

    return $cookies;
}

$adminCookies = m15_login($baseUrl, $adminIdentifier, $adminPassword, $errors);
$guestCookies = [];

$issueRoutes = [
    '175' => ['/admin'],
    '176' => ['/admin/settings', '/admin/homepage'],
    '177' => ['/admin/modules'],
    '178' => ['/admin/updates'],
    '179' => ['/admin/retention'],
    '180' => ['/admin/menu'],
    '181' => ['/admin/roles'],
    '182' => ['/admin/audit-logs'],
    '183' => ['/admin/members', '/admin/members/new', '/admin/members/search?q=admin', '/admin/member-settings', '/admin/member-groups', '/admin/member-groups/new', '/admin/member-group-rules', '/admin/member-group-assignments', '/admin/privacy-requests'],
    '184' => ['/admin/content', '/admin/content/new', '/admin/content/series', '/admin/content/settings', '/admin/content/files', '/admin/content/link-card-targets?target=community_post&q=test', '/admin/content/file-downloads', '/admin/content/asset-policy-sets', '/admin/content-groups', '/admin/content-groups/new'],
    '185' => ['/admin/community/settings', '/admin/community/asset-policy-sets', '/admin/community/levels', '/admin/community/boards', '/admin/community/boards/new', '/admin/community/board-groups', '/admin/community/board-groups/new', '/admin/community/posts', '/admin/community/comments', '/admin/community/reports', '/admin/community/series'],
    '186' => ['/admin/points', '/admin/points/settings', '/admin/points/adjust', '/admin/points/balances', '/admin/points/reference-search?q=qa', '/admin/points/transactions', '/admin/rewards', '/admin/rewards/settings', '/admin/rewards/adjust', '/admin/rewards/balances', '/admin/rewards/reference-search?q=qa', '/admin/rewards/withdrawal-requests', '/admin/rewards/transactions', '/admin/deposits', '/admin/deposits/settings', '/admin/deposits/adjust', '/admin/deposits/balances', '/admin/deposits/reference-search?q=qa', '/admin/deposits/refund-requests', '/admin/deposits/transactions', '/admin/asset-exchange', '/admin/asset-exchange/settings', '/admin/asset-exchange/logs'],
    '187' => ['/admin/coupons', '/admin/coupons/issues', '/admin/coupons/redemptions', '/admin/coupons/target-search?target_type=content&q=qa', '/admin/coupons/member-search?q=admin'],
    '188' => ['/admin/notifications', '/admin/notifications/new', '/admin/notification-deliveries', '/admin/notifications/settings'],
    '189' => ['/admin/banners', '/admin/banners/new', '/admin/banners/settings', '/admin/popup-layers', '/admin/popup-layers/new', '/admin/popup-layers/settings', '/admin/site-menus', '/admin/logo-manager', '/admin/seo', '/admin/ckeditor/settings'],
    '190' => ['/admin/ui-kit', '/admin', '/admin/members', '/admin/content', '/admin/community/posts'],
    '191' => ['/', '/robots.txt', '/sitemap.xml', '/assets/saanraan.css', '/content/example', '/community', '/community/board?key=free'],
    '192' => ['/login', '/register', '/password/reset', '/password/reset/confirm?token=bad', '/email/verify?token=bad', '/email/verified', '/member/avatar?account=missing'],
    '193' => ['/account', '/account/withdraw', '/account/privacy-requests'],
    '194' => ['/content/example', '/content/group?key=example', '/content/download?id=1'],
    '195' => ['/community', '/community/board?key=free', '/community/post?id=1', '/community/write?key=free', '/community/edit?id=1', '/community/attachment?id=1', '/community/link-card-targets?target=content&q=test'],
    '196' => ['/community/series', '/community/scraps', '/community/messages', '/community/message?id=1', '/community/message/write'],
    '197' => ['/account/points', '/account/rewards', '/account/deposits', '/account/asset-exchange'],
    '198' => ['/account/coupons', '/content/example', '/community/post?id=1'],
    '199' => ['/account/notifications'],
    '200' => ['/', '/banner/image?id=1', '/banner/click?id=1', '/logo-manager/image?id=1', '/seo/image?id=1', '/robots.txt', '/sitemap.xml'],
    '201' => ['/admin/ckeditor/settings', '/admin/content/new', '/community/write?key=free'],
    '202' => ['/register', '/community/write?key=free', '/admin/community/reports', '/admin/points/transactions', '/admin/coupons', '/account/privacy-requests', '/admin/audit-logs'],
];

foreach ($issueRoutes as $issue => $paths) {
    foreach ($paths as $path) {
        $issue = (string) $issue;
        $cookies = str_starts_with($path, '/admin') || str_starts_with($path, '/account') || str_starts_with($path, '/community/write') || str_starts_with($path, '/community/edit') || str_starts_with($path, '/community/series') || str_starts_with($path, '/community/scraps') || str_starts_with($path, '/community/message')
            ? $adminCookies
            : $guestCookies;

        $allowed = [200, 302, 400, 403, 404];
        $response = m15_request($baseUrl, 'GET', $path, [], $cookies);
        m15_check($issue, 'GET ' . $path, $response, $allowed, $results, $errors);
    }
}

$guestGuardPaths = [
    '175' => ['/admin'],
    '176' => ['/admin/settings', '/admin/homepage'],
    '177' => ['/admin/modules'],
    '178' => ['/admin/updates'],
    '179' => ['/admin/retention'],
    '180' => ['/admin/menu'],
    '181' => ['/admin/roles'],
    '182' => ['/admin/audit-logs'],
    '183' => ['/admin/members', '/admin/privacy-requests'],
    '184' => ['/admin/content'],
    '185' => ['/admin/community/boards'],
    '186' => ['/admin/points/adjust'],
    '187' => ['/admin/coupons'],
    '188' => ['/admin/notifications'],
    '189' => ['/admin/banners'],
    '190' => ['/admin/ui-kit'],
];

foreach ($guestGuardPaths as $issue => $paths) {
    foreach ($paths as $path) {
        $issue = (string) $issue;
        $cookies = [];
        $response = m15_request($baseUrl, 'GET', $path, [], $cookies);
        m15_check($issue, 'guest guard GET ' . $path, $response, [302, 403], $results, $errors);
    }
}

$postGuardPaths = [
    '176' => ['/admin/settings', '/admin/homepage', '/admin/color-scheme'],
    '177' => ['/admin/modules'],
    '178' => ['/admin/updates'],
    '179' => ['/admin/retention'],
    '180' => ['/admin/menu'],
    '181' => ['/admin/roles'],
    '183' => ['/admin/members/save', '/admin/member-groups/save', '/admin/member-group-rules/save'],
    '184' => ['/admin/content/save', '/admin/content/delete', '/admin/content/series', '/admin/content/files', '/admin/content/file-downloads', '/admin/content/asset-policy-sets'],
    '185' => ['/admin/community/settings', '/admin/community/boards/create', '/admin/community/boards/update', '/admin/community/posts', '/admin/community/comments', '/admin/community/reports', '/admin/community/series'],
    '186' => ['/admin/points/adjust', '/admin/rewards/adjust', '/admin/deposits/adjust', '/admin/asset-exchange'],
    '187' => ['/admin/coupons', '/admin/coupons/issues', '/admin/coupons/redemptions'],
    '188' => ['/admin/notifications/create', '/admin/notifications/delete', '/admin/notification-deliveries/status'],
    '189' => ['/admin/banners/save', '/admin/popup-layers/save', '/admin/site-menus', '/admin/logo-manager', '/admin/seo', '/admin/ckeditor/settings'],
    '192' => ['/login', '/register', '/password/reset'],
    '193' => ['/account', '/account/withdraw', '/account/privacy-requests'],
    '194' => ['/content/comment', '/content/action', '/content/download'],
    '195' => ['/community/write?key=free', '/community/edit', '/community/delete', '/community/comment', '/community/report', '/community/skin-action'],
    '196' => ['/community/series', '/community/scrap', '/community/message/write', '/community/message/delete'],
    '197' => ['/account/rewards', '/account/deposits', '/account/asset-exchange'],
    '199' => ['/account/notifications'],
    '202' => ['/register', '/community/report', '/admin/privacy-requests/export'],
];

foreach ($postGuardPaths as $issue => $paths) {
    foreach ($paths as $path) {
        $issue = (string) $issue;
        $cookies = str_starts_with($path, '/admin') || str_starts_with($path, '/account') || str_starts_with($path, '/community')
            ? $adminCookies
            : $guestCookies;
        $response = m15_request($baseUrl, 'POST', $path, ['m15_probe' => '1'], $cookies);
        m15_check($issue, 'CSRF/validation POST ' . $path, $response, [200, 302, 400, 403, 404, 405], $results, $errors);
    }
}

$protectedPaths = [
    '/database/core/install.sql',
    '/modules/member/install.sql',
    '/modules/community/install.sql',
    '/modules/community/module.php',
    '/core/helpers.php',
    '/config/.gitignore',
    '/storage/.gitignore',
    '/docs/deployment-protection.md',
    '/examples/sample_module/module.php',
    '/AGENTS.md',
    '/README.md',
    '/.tools/bin/check.php',
    '/.git/HEAD',
];

foreach ($protectedPaths as $path) {
    $cookies = [];
    $response = m15_request($baseUrl, 'GET', $path, [], $cookies);
    m15_check('191', 'protected path ' . $path, $response, [403, 404], $results, $errors);
}

foreach (array_keys($issueRoutes) as $issue) {
    $passed = (int) ($results[$issue]['passed'] ?? 0);
    $checks = (int) ($results[$issue]['checks'] ?? 0);
    $failures = $results[$issue]['failures'] ?? [];
    if ($failures === []) {
        echo '[ok] #' . $issue . ' route/security probes ' . (string) $passed . '/' . (string) $checks . "\n";
        continue;
    }

    echo '[fail] #' . $issue . ' route/security probes ' . (string) $passed . '/' . (string) $checks . "\n";
    foreach ($failures as $failure) {
        echo '  - ' . $failure . "\n";
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Milestone 15 route QA failed: " . (string) count($errors) . " failing probes.\n");
    exit(1);
}

echo "Milestone 15 route QA completed.\n";
