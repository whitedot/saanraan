#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_installed_gate_status_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_installed_gate_status_read(string $file): string
{
    if (!is_file($file)) {
        sr_installed_gate_status_error('Installed gate status required file is missing: ' . $file);
        return '';
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        sr_installed_gate_status_error('Installed gate status required file cannot be read: ' . $file);
        return '';
    }

    return $contents;
}

function sr_installed_gate_status_require_markers(string $file, array $markers): void
{
    $contents = sr_installed_gate_status_read($file);
    if ($contents === '') {
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            sr_installed_gate_status_error('Installed gate status marker missing in ' . $file . ': ' . $marker);
        }
    }
}

function sr_installed_gate_status_exec(array $command): string
{
    $result = sr_installed_gate_status_exec_result($command);
    if ($result['exit_code'] !== 0) {
        sr_installed_gate_status_error('Installed gate status command failed: ' . implode(' ', $command) . "\n" . $result['output']);
        return '';
    }

    return $result['output'];
}

function sr_installed_gate_status_exec_result(array $command): array
{
    $parts = [];
    foreach ($command as $part) {
        $parts[] = escapeshellarg($part);
    }

    $output = [];
    exec(implode(' ', $parts) . ' 2>&1', $output, $exitCode);
    return [
        'exit_code' => $exitCode,
        'output' => implode("\n", $output),
    ];
}

function sr_installed_gate_status_available_port(): int
{
    $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if (!is_resource($socket)) {
        sr_installed_gate_status_error('Installed gate status cannot allocate a local fixture port: ' . $errstr);
        return 0;
    }

    $name = stream_socket_get_name($socket, false);
    fclose($socket);
    if (!is_string($name) || preg_match('/:(\d+)\z/', $name, $matches) !== 1) {
        sr_installed_gate_status_error('Installed gate status cannot parse allocated fixture port.');
        return 0;
    }

    return (int) $matches[1];
}

function sr_installed_gate_status_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        if (is_file($path)) {
            @unlink($path);
        }
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if (!is_string($entry) || $entry === '.' || $entry === '..') {
            continue;
        }
        sr_installed_gate_status_remove_tree($path . DIRECTORY_SEPARATOR . $entry);
    }
    @rmdir($path);
}

function sr_installed_gate_status_http_get(string $url): string
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 1,
            'ignore_errors' => true,
        ],
    ]);

    set_error_handler(static function (): bool {
        return true;
    });
    $body = file_get_contents($url, false, $context);
    restore_error_handler();

    return is_string($body) ? $body : '';
}

function sr_installed_gate_status_wait_for_fixture_server(string $baseUrl): bool
{
    $deadline = microtime(true) + 5.0;
    do {
        if (trim(sr_installed_gate_status_http_get($baseUrl . '/__ready')) === 'ok') {
            return true;
        }
        usleep(100000);
    } while (microtime(true) < $deadline);

    return false;
}

function sr_installed_gate_status_admin_readonly_mock_router(): string
{
    return <<<'PHP'
<?php
declare(strict_types=1);

$path = (string) (parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$isAdmin = static function (): bool {
    return str_contains((string) ($_SERVER['HTTP_COOKIE'] ?? ''), 'sr_admin_readonly_mock=admin');
};

if ($path === '/__ready') {
    echo 'ok';
    return;
}

if ($path === '/login' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<form><input type="hidden" name="csrf_token" value="login-csrf"></form>';
    return;
}

if ($path === '/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((string) ($_POST['identifier'] ?? '') !== 'admin' || (string) ($_POST['password'] ?? '') !== '12341234' || (string) ($_POST['csrf_token'] ?? '') !== 'login-csrf') {
        http_response_code(401);
        echo 'bad login';
        return;
    }
    header('Set-Cookie: sr_admin_readonly_mock=admin; Path=/');
    header('Location: /admin', true, 302);
    return;
}

if ($path === '/admin/assets/reconciliation' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$isAdmin()) {
        header('Location: /login', true, 302);
        return;
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo '<main><h1>자산 원장 정합성</h1><p>read-only fixture</p></main>';
    return;
}

if ($path === '/admin/operations' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$isAdmin()) {
        header('Location: /login', true, 302);
        return;
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo '<main><h1>운영 상태</h1><p>read-only fixture</p></main>';
    return;
}

http_response_code(404);
echo 'not found';
PHP;
}

function sr_installed_gate_status_check_admin_readonly_mock_fixture(): void
{
    $port = sr_installed_gate_status_available_port();
    if ($port < 1) {
        return;
    }

    $tmpRoot = sys_get_temp_dir() . '/sr-admin-readonly-fixture-' . bin2hex(random_bytes(6));
    if (!@mkdir($tmpRoot, 0700, true) && !is_dir($tmpRoot)) {
        sr_installed_gate_status_error('Installed gate status cannot create admin read-only fixture directory.');
        return;
    }

    $router = $tmpRoot . '/router.php';
    file_put_contents($router, sr_installed_gate_status_admin_readonly_mock_router());

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . (string) $port, $router], $descriptorSpec, $pipes, $tmpRoot);
    if (!is_resource($process)) {
        sr_installed_gate_status_error('Installed gate status cannot start admin read-only fixture server.');
        sr_installed_gate_status_remove_tree($tmpRoot);
        return;
    }

    try {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                stream_set_blocking($pipe, false);
            }
        }
        $baseUrl = 'http://127.0.0.1:' . (string) $port;
        if (!sr_installed_gate_status_wait_for_fixture_server($baseUrl)) {
            sr_installed_gate_status_error('Installed gate status admin read-only fixture server did not become ready.');
            return;
        }

        $output = sr_installed_gate_status_exec([
            'env',
            'SR_SMOKE_BASE_URL=' . $baseUrl,
            'SR_SMOKE_ADMIN_IDENTIFIER=admin',
            'SR_SMOKE_ADMIN_PASSWORD=12341234',
            PHP_BINARY,
            '.tools/bin/release-installed-gate-status.php',
            '--run-admin-readonly',
        ]);
        foreach ([
            'run-admin-readonly: yes',
            "gate\t/admin/assets/reconciliation\tresult=통과\tenvironment=" . $baseUrl . "\tmemo=admin read-only smoke GET /admin/assets/reconciliation HTTP 200; expected text found",
            "gate\t/admin/operations\tresult=통과\tenvironment=" . $baseUrl . "\tmemo=admin read-only smoke GET /admin/operations HTTP 200; expected text found",
        ] as $marker) {
            if ($output !== '' && !str_contains($output, $marker)) {
                sr_installed_gate_status_error('Installed gate status admin read-only mock fixture output marker missing: ' . $marker);
            }
        }
    } finally {
        proc_terminate($process);
        proc_close($process);
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        sr_installed_gate_status_remove_tree($tmpRoot);
    }
}

function sr_installed_gate_status_asset_smoke_mock_router(string $stateFile): string
{
    $stateExport = var_export($stateFile, true);

    return <<<PHP
<?php
declare(strict_types=1);

\$stateFile = {$stateExport};
\$state = is_file(\$stateFile) ? json_decode((string) file_get_contents(\$stateFile), true) : [];
\$state = is_array(\$state) ? \$state : [];
\$path = (string) (parse_url((string) \$_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

\$isLoggedIn = static function (): bool {
    return str_contains((string) (\$_SERVER['HTTP_COOKIE'] ?? ''), 'sr_asset_mock=member');
};
\$saveState = static function (array \$state) use (\$stateFile): void {
    \$handle = fopen(\$stateFile, 'c+');
    if (!is_resource(\$handle)) {
        return;
    }
    flock(\$handle, LOCK_EX);
    ftruncate(\$handle, 0);
    fwrite(\$handle, json_encode(\$state, JSON_UNESCAPED_SLASHES));
    fflush(\$handle);
    flock(\$handle, LOCK_UN);
    fclose(\$handle);
};

if (\$path === '/__ready') {
    echo 'ok';
    return;
}

if (\$path === '/login' && \$_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<form><input type="hidden" name="csrf_token" value="login-csrf"></form>';
    return;
}

if (\$path === '/login' && \$_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((string) (\$_POST['identifier'] ?? '') !== 'asset_member' || (string) (\$_POST['password'] ?? '') !== '12341234' || (string) (\$_POST['csrf_token'] ?? '') !== 'login-csrf') {
        http_response_code(401);
        echo 'bad login';
        return;
    }
    header('Set-Cookie: sr_asset_mock=member; Path=/');
    header('Location: /paid/form', true, 302);
    return;
}

if (\$path === '/paid/form' && \$_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!\$isLoggedIn()) {
        header('Location: /login', true, 302);
        return;
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo '<form method="post"><input type="hidden" name="csrf_token" value="paid-csrf"><input type="hidden" name="asset_request_token" value="asset-token"></form>';
    return;
}

if (\$path === '/paid/form' && \$_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\$isLoggedIn() || (string) (\$_POST['csrf_token'] ?? '') !== 'paid-csrf' || (string) (\$_POST['asset_request_token'] ?? '') !== 'asset-token' || (string) (\$_POST['asset_confirm'] ?? '') !== '1') {
        http_response_code(400);
        echo 'bad paid submit';
        return;
    }
    \$state['post_count'] = (int) (\$state['post_count'] ?? 0) + 1;
    \$saveState(\$state);
    header('Location: /paid/form?ok=1', true, 302);
    return;
}

http_response_code(404);
echo 'not found';
PHP;
}

function sr_installed_gate_status_check_asset_smoke_mock_fixture(): void
{
    $port = sr_installed_gate_status_available_port();
    if ($port < 1) {
        return;
    }

    $tmpRoot = sys_get_temp_dir() . '/sr-asset-smoke-fixture-' . bin2hex(random_bytes(6));
    if (!@mkdir($tmpRoot, 0700, true) && !is_dir($tmpRoot)) {
        sr_installed_gate_status_error('Installed gate status cannot create asset smoke fixture directory.');
        return;
    }

    $router = $tmpRoot . '/router.php';
    $stateFile = $tmpRoot . '/state.json';
    file_put_contents($router, sr_installed_gate_status_asset_smoke_mock_router($stateFile));

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . (string) $port, $router], $descriptorSpec, $pipes, $tmpRoot);
    if (!is_resource($process)) {
        sr_installed_gate_status_error('Installed gate status cannot start asset smoke fixture server.');
        sr_installed_gate_status_remove_tree($tmpRoot);
        return;
    }

    try {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                stream_set_blocking($pipe, false);
            }
        }
        $baseUrl = 'http://127.0.0.1:' . (string) $port;
        if (!sr_installed_gate_status_wait_for_fixture_server($baseUrl)) {
            sr_installed_gate_status_error('Installed gate status asset smoke fixture server did not become ready.');
            return;
        }

        $result = sr_installed_gate_status_exec_result([
            'env',
            'SR_SMOKE_ALLOW_MUTATION=1',
            'SR_SMOKE_BASE_URL=' . $baseUrl,
            'SR_SMOKE_IDENTIFIER=asset_member',
            'SR_SMOKE_PASSWORD=12341234',
            'SR_SMOKE_FORM_PATH=/paid/form',
            'SR_SMOKE_POST_PATH=/paid/form',
            'SR_SMOKE_EXTRA_POST=asset_confirm=1',
            'SR_SMOKE_SUCCESS_STATUSES=302',
            'SR_SMOKE_PARALLEL_REQUESTS=4',
            PHP_BINARY,
            '.tools/bin/smoke-asset-idempotency-http.php',
        ]);
        if ((int) $result['exit_code'] !== 0) {
            sr_installed_gate_status_error('Installed gate status asset smoke mock fixture failed: ' . (string) $result['output']);
            return;
        }
        foreach ([
            'asset-idempotency-http-smoke-version: 1',
            'form-path: /paid/form',
            'post-path: /paid/form',
            'parallel-requests: 4',
            'success-statuses: 302',
            'success-count: 4',
            'status-counts: {"302":4}',
            'saanraan asset idempotency HTTP smoke completed.',
        ] as $marker) {
            if (!str_contains((string) $result['output'], $marker)) {
                sr_installed_gate_status_error('Installed gate status asset smoke mock fixture output marker missing: ' . $marker);
            }
        }

        $state = is_file($stateFile) ? json_decode((string) file_get_contents($stateFile), true) : [];
        if (!is_array($state) || (int) ($state['post_count'] ?? 0) !== 4) {
            sr_installed_gate_status_error('Installed gate status asset smoke mock fixture did not receive all parallel POST requests.');
        }
    } finally {
        proc_terminate($process);
        proc_close($process);
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        sr_installed_gate_status_remove_tree($tmpRoot);
    }
}

function sr_installed_gate_status_ckeditor_smoke_mock_router(string $stateFile): string
{
    $stateExport = var_export($stateFile, true);

    return <<<PHP
<?php
declare(strict_types=1);

\$stateFile = {$stateExport};
\$uploadToken = '0123456789abcdef0123456789abcdef';
\$tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
\$state = is_file(\$stateFile) ? json_decode((string) file_get_contents(\$stateFile), true) : [];
\$state = is_array(\$state) ? \$state : [];
\$state += ['uploads' => [], 'contents' => [], 'next_upload' => 1, 'next_content_id' => 123];
\$path = (string) (parse_url((string) \$_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
\$query = [];
parse_str((string) (parse_url((string) \$_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? ''), \$query);

\$isAdmin = static function (): bool {
    return str_contains((string) (\$_SERVER['HTTP_COOKIE'] ?? ''), 'sr_mock=admin');
};
\$saveState = static function (array \$state) use (\$stateFile): void {
    file_put_contents(\$stateFile, json_encode(\$state, JSON_UNESCAPED_SLASHES));
};
\$sendImage = static function () use (\$tinyPng): void {
    header('Content-Type: image/png');
    echo is_string(\$tinyPng) ? \$tinyPng : '';
};
\$notFound = static function (): void {
    http_response_code(404);
    echo 'not found';
};

if (\$path === '/__ready') {
    echo 'ok';
    return;
}

if (\$path === '/login' && \$_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<form><input type="hidden" name="csrf_token" value="login-csrf"></form>';
    return;
}

if (\$path === '/login' && \$_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Set-Cookie: sr_mock=admin; Path=/');
    header('Location: /admin/content/new', true, 302);
    return;
}

if (\$path === '/admin/content/new' && \$_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!\$isAdmin()) {
        http_response_code(403);
        echo 'forbidden';
        return;
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo '<form><input type="hidden" name="csrf_token" value="save-csrf"><textarea name="body_text" data-sr-editor="ckeditor" data-sr-editor-upload-url="/admin/content/body-files/upload" data-sr-editor-upload-field="upload" data-sr-editor-upload-csrf="upload-csrf" data-sr-editor-upload-token="' . \$uploadToken . '"></textarea></form>';
    return;
}

if (\$path === '/admin/content/body-files/upload' && \$_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\$isAdmin() || (string) (\$_POST['csrf_token'] ?? '') !== 'upload-csrf' || (string) (\$_POST['upload_token'] ?? '') !== \$uploadToken || !isset(\$_FILES['upload'])) {
        http_response_code(400);
        echo json_encode(['error' => ['message' => 'bad upload']]);
        return;
    }
    \$index = (int) (\$state['next_upload'] ?? 1);
    \$fileName = 'mock-' . (string) \$index . '.png';
    \$state['next_upload'] = \$index + 1;
    \$state['uploads'][\$fileName] = ['finalized' => false];
    \$saveState(\$state);
    header('Content-Type: application/json');
    echo json_encode(['url' => '/content/body-file?tmp=' . \$uploadToken . '&file=' . \$fileName, 'width' => 1, 'height' => 1], JSON_UNESCAPED_SLASHES);
    return;
}

if (\$path === '/content/body-file' && \$_SERVER['REQUEST_METHOD'] === 'GET') {
    \$requestedFile = (string) (\$query['file'] ?? '');
    if ((string) (\$query['tmp'] ?? '') === \$uploadToken && \$requestedFile !== '') {
        \$upload = is_array(\$state['uploads'][\$requestedFile] ?? null) ? \$state['uploads'][\$requestedFile] : null;
        if (!is_array(\$upload) || !empty(\$upload['finalized']) || !\$isAdmin()) {
            \$notFound();
            return;
        }
        \$sendImage();
        return;
    }
    \$contentId = (string) (\$query['content_id'] ?? '');
    \$content = is_array(\$state['contents'][\$contentId] ?? null) ? \$state['contents'][\$contentId] : null;
    if (is_array(\$content) && \$requestedFile !== '' && (string) (\$content['file'] ?? '') === \$requestedFile) {
        if ((string) (\$content['status'] ?? '') !== 'published' && !\$isAdmin()) {
            \$notFound();
            return;
        }
        \$sendImage();
        return;
    }
    \$notFound();
    return;
}

if (\$path === '/admin/content/save' && \$_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\$isAdmin() || (string) (\$_POST['csrf_token'] ?? '') !== 'save-csrf') {
        http_response_code(400);
        echo 'bad save';
        return;
    }
    \$body = (string) (\$_POST['body_text'] ?? '');
    \$marker = preg_match('/sr-ckeditor-smoke-[A-Za-z0-9-]+/', \$body, \$matches) === 1 ? (string) \$matches[0] : 'sr-ckeditor-smoke-missing';
    \$fileName = preg_match('/file=([^"&]+)/', \$body, \$fileMatches) === 1 ? html_entity_decode((string) \$fileMatches[1], ENT_QUOTES, 'UTF-8') : 'mock-1.png';
    \$contentId = (int) (\$state['next_content_id'] ?? 123);
    \$state['next_content_id'] = \$contentId + 1;
    if (is_array(\$state['uploads'][\$fileName] ?? null)) {
        \$state['uploads'][\$fileName]['finalized'] = true;
    }
    \$state['contents'][(string) \$contentId] = [
        'slug' => (string) (\$_POST['slug'] ?? ''),
        'marker' => \$marker,
        'status' => (string) (\$_POST['status'] ?? 'published'),
        'file' => \$fileName,
    ];
    \$saveState(\$state);
    header('Location: /admin/content', true, 302);
    return;
}

if (preg_match('#\\A/content/([^/]+)\\z#', \$path, \$matches) === 1 && \$_SERVER['REQUEST_METHOD'] === 'GET') {
    foreach (\$state['contents'] as \$contentId => \$content) {
        if (!is_array(\$content) || (string) \$matches[1] !== (string) (\$content['slug'] ?? '')) {
            continue;
        }
        if ((string) (\$content['status'] ?? '') !== 'published' && !\$isAdmin()) {
            \$notFound();
            return;
        }
        header('Content-Type: text/html; charset=UTF-8');
        echo '<article><div class="content-body"><h1>' . htmlspecialchars((string) \$content['marker'], ENT_QUOTES, 'UTF-8') . '</h1><p><img src="/content/body-file?content_id=' . htmlspecialchars((string) \$contentId, ENT_QUOTES, 'UTF-8') . '&amp;file=' . htmlspecialchars((string) \$content['file'], ENT_QUOTES, 'UTF-8') . '" alt="mock"></p><p><a>blocked link</a></p></div></article><script src="/assets/mock-layout.js" defer></script>';
        return;
    }
    \$notFound();
    return;
}

\$notFound();
PHP;
}

function sr_installed_gate_status_check_ckeditor_smoke_mock_fixture(): void
{
    $port = sr_installed_gate_status_available_port();
    if ($port < 1) {
        return;
    }

    $tmpRoot = sys_get_temp_dir() . '/sr-ckeditor-smoke-fixture-' . bin2hex(random_bytes(6));
    if (!@mkdir($tmpRoot, 0700, true) && !is_dir($tmpRoot)) {
        sr_installed_gate_status_error('Installed gate status cannot create CKEditor smoke fixture directory.');
        return;
    }

    $router = $tmpRoot . '/router.php';
    $stateFile = $tmpRoot . '/state.json';
    file_put_contents($router, sr_installed_gate_status_ckeditor_smoke_mock_router($stateFile));

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . (string) $port, $router], $descriptorSpec, $pipes, $tmpRoot);
    if (!is_resource($process)) {
        sr_installed_gate_status_error('Installed gate status cannot start CKEditor smoke fixture server.');
        sr_installed_gate_status_remove_tree($tmpRoot);
        return;
    }

    try {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                stream_set_blocking($pipe, false);
            }
        }
        $baseUrl = 'http://127.0.0.1:' . (string) $port;
        if (!sr_installed_gate_status_wait_for_fixture_server($baseUrl)) {
            sr_installed_gate_status_error('Installed gate status CKEditor smoke fixture server did not become ready.');
            return;
        }

        $result = sr_installed_gate_status_exec_result([
            'env',
            'SR_SMOKE_ALLOW_MUTATION=1',
            'SR_SMOKE_BASE_URL=' . $baseUrl,
            'SR_SMOKE_ADMIN_IDENTIFIER=admin',
            'SR_SMOKE_ADMIN_PASSWORD=12341234',
            PHP_BINARY,
            '.tools/bin/smoke-ckeditor-upload-save.php',
        ]);
        if ((int) $result['exit_code'] !== 0) {
            sr_installed_gate_status_error('Installed gate status CKEditor smoke mock fixture failed: ' . (string) $result['output']);
            return;
        }
        foreach ([
            'ckeditor-upload-save-http-smoke-version: 1',
            'temporary-image-admin-access: yes',
            'temporary-image-guest-blocked: yes',
            'finalized-image-url: yes',
            'saved-image-guest-access: yes',
            'temporary-image-finalized: yes',
            'blocked-html-removed: yes',
            'draft-preview-admin-access: yes',
            'draft-page-guest-blocked: yes',
            'draft-finalized-image-url: yes',
            'draft-image-admin-access: yes',
            'draft-image-guest-blocked: yes',
            'draft-temporary-image-finalized: yes',
            'saanraan CKEditor upload/save HTTP smoke completed.',
        ] as $marker) {
            if (!str_contains((string) $result['output'], $marker)) {
                sr_installed_gate_status_error('Installed gate status CKEditor smoke mock fixture output marker missing: ' . $marker);
            }
        }
    } finally {
        proc_terminate($process);
        proc_close($process);
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        sr_installed_gate_status_remove_tree($tmpRoot);
    }
}

function sr_installed_gate_status_privacy_smoke_mock_router(string $stateFile): string
{
    $stateExport = var_export($stateFile, true);

    return <<<PHP
<?php
declare(strict_types=1);

\$stateFile = {$stateExport};
\$state = is_file(\$stateFile) ? json_decode((string) file_get_contents(\$stateFile), true) : [];
\$state = is_array(\$state) ? \$state : [];
\$path = (string) (parse_url((string) \$_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

\$isLoggedIn = static function (): bool {
    return str_contains((string) (\$_SERVER['HTTP_COOKIE'] ?? ''), 'sr_privacy_mock=member');
};
\$saveState = static function (array \$state) use (\$stateFile): void {
    file_put_contents(\$stateFile, json_encode(\$state, JSON_UNESCAPED_SLASHES));
};
\$accountForm = static function (string \$csrf): void {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<form method="post" action="/account/privacy-export"><input type="hidden" name="csrf_token" value="' . \$csrf . '"></form>';
};

if (\$path === '/__ready') {
    echo 'ok';
    return;
}

if (\$path === '/login' && \$_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<form><input type="hidden" name="csrf_token" value="login-csrf"></form>';
    return;
}

if (\$path === '/login' && \$_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty(\$state['withdrawn'])) {
        http_response_code(403);
        echo 'withdrawn';
        return;
    }
    if ((string) (\$_POST['identifier'] ?? '') !== 'privacy_member' || (string) (\$_POST['password'] ?? '') !== '12341234' || (string) (\$_POST['csrf_token'] ?? '') !== 'login-csrf') {
        http_response_code(401);
        echo 'bad login';
        return;
    }
    header('Set-Cookie: sr_privacy_mock=member; Path=/');
    header('Location: /account', true, 302);
    return;
}

if (\$path === '/account' && \$_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!\$isLoggedIn()) {
        header('Location: /login', true, 302);
        return;
    }
    \$accountForm('account-csrf');
    return;
}

if (\$path === '/account/privacy-export' && \$_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\$isLoggedIn() || (string) (\$_POST['csrf_token'] ?? '') !== 'account-csrf' || (string) (\$_POST['current_password'] ?? '') !== '12341234') {
        http_response_code(400);
        echo 'bad export';
        return;
    }
    header('Content-Type: application/json');
    echo json_encode([
        'exported_at' => '2026-06-12T00:00:00+00:00',
        'account_id' => 77,
        'privacy_requests' => [],
        'module_exports' => ['member' => ['account' => ['identifier' => 'privacy_member']]],
    ], JSON_UNESCAPED_SLASHES);
    return;
}

if (\$path === '/account/withdraw' && \$_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!\$isLoggedIn()) {
        header('Location: /login', true, 302);
        return;
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo '<form method="post"><input type="hidden" name="csrf_token" value="withdraw-csrf"></form>';
    return;
}

if (\$path === '/account/withdraw' && \$_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\$isLoggedIn() || (string) (\$_POST['csrf_token'] ?? '') !== 'withdraw-csrf' || (string) (\$_POST['password'] ?? '') !== '12341234' || (string) (\$_POST['confirm_text'] ?? '') !== '탈퇴') {
        http_response_code(400);
        echo 'bad withdraw';
        return;
    }
    \$state['withdrawn'] = true;
    \$saveState(\$state);
    header('Set-Cookie: sr_privacy_mock=deleted; Path=/; Max-Age=0');
    header('Location: /login', true, 302);
    return;
}

http_response_code(404);
echo 'not found';
PHP;
}

function sr_installed_gate_status_check_privacy_smoke_mock_fixture(): void
{
    $port = sr_installed_gate_status_available_port();
    if ($port < 1) {
        return;
    }

    $tmpRoot = sys_get_temp_dir() . '/sr-privacy-smoke-fixture-' . bin2hex(random_bytes(6));
    if (!@mkdir($tmpRoot, 0700, true) && !is_dir($tmpRoot)) {
        sr_installed_gate_status_error('Installed gate status cannot create privacy smoke fixture directory.');
        return;
    }

    $router = $tmpRoot . '/router.php';
    $stateFile = $tmpRoot . '/state.json';
    file_put_contents($router, sr_installed_gate_status_privacy_smoke_mock_router($stateFile));

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . (string) $port, $router], $descriptorSpec, $pipes, $tmpRoot);
    if (!is_resource($process)) {
        sr_installed_gate_status_error('Installed gate status cannot start privacy smoke fixture server.');
        sr_installed_gate_status_remove_tree($tmpRoot);
        return;
    }

    try {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                stream_set_blocking($pipe, false);
            }
        }
        $baseUrl = 'http://127.0.0.1:' . (string) $port;
        if (!sr_installed_gate_status_wait_for_fixture_server($baseUrl)) {
            sr_installed_gate_status_error('Installed gate status privacy smoke fixture server did not become ready.');
            return;
        }

        $result = sr_installed_gate_status_exec_result([
            'env',
            'SR_SMOKE_ALLOW_MUTATION=1',
            'SR_SMOKE_BASE_URL=' . $baseUrl,
            'SR_SMOKE_IDENTIFIER=privacy_member',
            'SR_SMOKE_PASSWORD=12341234',
            PHP_BINARY,
            '.tools/bin/smoke-privacy-export-cleanup.php',
        ]);
        if ((int) $result['exit_code'] !== 0) {
            sr_installed_gate_status_error('Installed gate status privacy smoke mock fixture failed: ' . (string) $result['output']);
            return;
        }
        foreach ([
            'privacy-export-cleanup-http-smoke-version: 1',
            'export-json: ok',
            'exported-at: ok',
            'export-account-id: 77',
            'privacy-requests-array: yes',
            'module-exports-member: yes',
            'withdraw-location: /login',
            'post-withdraw-account-blocked: yes',
            'post-withdraw-login-blocked: yes',
            'saanraan privacy export/cleanup HTTP smoke completed.',
        ] as $marker) {
            if (!str_contains((string) $result['output'], $marker)) {
                sr_installed_gate_status_error('Installed gate status privacy smoke mock fixture output marker missing: ' . $marker);
            }
        }
    } finally {
        proc_terminate($process);
        proc_close($process);
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        sr_installed_gate_status_remove_tree($tmpRoot);
    }
}

function sr_installed_gate_status_assert_unresolved_count(string $label, string $output): void
{
    if ($output === '') {
        return;
    }

    if (preg_match_all('/^gate\t[^\n]*\tresult=([^\t\n]+)/m', $output, $matches) === false) {
        sr_installed_gate_status_error('Installed gate status output cannot be parsed for gate rows: ' . $label);
        return;
    }

    $results = $matches[1] ?? [];
    if (count($results) !== 14) {
        sr_installed_gate_status_error('Installed gate status output must contain 14 gate rows for ' . $label . ', got ' . (string) count($results));
    }

    $expectedUnresolved = 0;
    foreach ($results as $result) {
        if ($result !== '통과') {
            $expectedUnresolved++;
        }
    }

    if (preg_match('/^unresolved-gates: (\d+)$/m', $output, $countMatches) !== 1) {
        sr_installed_gate_status_error('Installed gate status output missing unresolved-gates count: ' . $label);
        return;
    }

    if ((int) $countMatches[1] !== $expectedUnresolved) {
        sr_installed_gate_status_error('Installed gate status unresolved-gates mismatch for ' . $label . ': expected ' . (string) $expectedUnresolved . ', got ' . $countMatches[1]);
    }
}

function sr_installed_gate_status_assert_result_summary(string $label, string $output): void
{
    if ($output === '') {
        return;
    }

    if (preg_match_all('/^gate\t[^\n]*\tresult=([^\t\n]+)/m', $output, $matches) === false) {
        sr_installed_gate_status_error('Installed gate status output cannot be parsed for summary: ' . $label);
        return;
    }

    $expected = [
        '통과' => 0,
        '부분 확인' => 0,
        '수동 확인 필요' => 0,
        '미실행' => 0,
        '환경 미준비' => 0,
        '실패' => 0,
    ];
    foreach (($matches[1] ?? []) as $result) {
        if (!array_key_exists($result, $expected)) {
            $expected[$result] = 0;
        }

        $expected[$result]++;
    }

    if (preg_match('/^gate-result-summary: (.+)$/m', $output, $summaryMatches) !== 1) {
        sr_installed_gate_status_error('Installed gate status output missing gate-result-summary: ' . $label);
        return;
    }

    $actual = [];
    foreach (explode(',', (string) $summaryMatches[1]) as $part) {
        $pieces = explode('=', trim($part), 2);
        if (count($pieces) !== 2) {
            continue;
        }

        $actual[trim($pieces[0])] = (int) trim($pieces[1]);
    }

    foreach ($expected as $result => $count) {
        if (($actual[$result] ?? null) !== $count) {
            sr_installed_gate_status_error(
                'Installed gate status result summary mismatch for ' . $label . ': ' . $result
                . ' expected ' . (string) $count . ', got ' . (array_key_exists($result, $actual) ? (string) $actual[$result] : 'missing')
            );
        }
    }
}

function sr_installed_gate_status_assert_markdown_table(string $label, string $output): void
{
    if ($output === '') {
        return;
    }

    if (!str_starts_with($output, "| 게이트 | 결과 | 환경 | 메모 |\n| --- | --- | --- | --- |\n")) {
        sr_installed_gate_status_error('Installed gate status markdown table header is invalid: ' . $label);
    }

    if (preg_match_all('/^\|\s*(.*?)\s*\|\s*([^|\n]+?)\s*\|\s*([^|\n]*?)\s*\|\s*([^|\n]*?)\s*\|$/m', $output, $matches) === false) {
        sr_installed_gate_status_error('Installed gate status markdown table cannot be parsed: ' . $label);
        return;
    }

    $labels = [];
    foreach (($matches[1] ?? []) as $rawLabel) {
        $labelText = trim((string) $rawLabel);
        if ($labelText === '게이트' || $labelText === '---') {
            continue;
        }

        $labels[] = $labelText;
    }

    $expectedLabels = [
        '새 설치 또는 업데이트 적용',
        '`php .tools/bin/reconcile-assets.php`',
        '`php .tools/bin/ops-status.php`',
        '`php .tools/bin/expire-points.php --dry-run`',
        '/admin/assets/reconciliation',
        '/admin/operations',
        '기본 HTTP smoke',
        '인증 smoke',
        '퀴즈 E2E smoke',
        '자산/쿠폰/유료 접근권 mutation smoke',
        '개인정보 export/cleanup smoke',
        'CKEditor asset/fallback browser smoke',
        'CKEditor upload/save browser smoke',
        '성능 수동 점검',
    ];

    if ($labels !== $expectedLabels) {
        sr_installed_gate_status_error('Installed gate status markdown rows must match template order for ' . $label);
    }
}

function sr_installed_gate_status_assert_json(string $label, string $output): void
{
    if ($output === '') {
        return;
    }

    $decoded = json_decode($output, true);
    if (!is_array($decoded)) {
        sr_installed_gate_status_error('Installed gate status JSON output is invalid: ' . $label);
        return;
    }

    if (($decoded['version'] ?? null) !== 1) {
        sr_installed_gate_status_error('Installed gate status JSON version mismatch: ' . $label);
    }

    $metadata = $decoded['metadata'] ?? null;
    if (!is_array($metadata)) {
        sr_installed_gate_status_error('Installed gate status JSON metadata is missing: ' . $label);
        return;
    }

    foreach ([
        'config_readable' => 'no',
        'config_mode' => '0600',
        'config_owner_group' => 'www-data:www-data',
        'sr_is_installed' => 'no',
        'run_http_smoke' => 'no',
        'run_readonly' => 'no',
        'run_admin_readonly' => 'no',
        'run_privacy_smoke' => 'no',
        'run_ckeditor_upload_save_smoke' => 'no',
        'mutation_smoke_allowed' => 'no',
        'public_mutation_url_allowed' => 'no',
    ] as $key => $expectedValue) {
        if (($metadata[$key] ?? null) !== $expectedValue) {
            sr_installed_gate_status_error('Installed gate status JSON metadata mismatch for ' . $label . ': ' . $key);
        }
    }

    $gates = $decoded['gates'] ?? null;
    if (!is_array($gates) || count($gates) !== 14) {
        sr_installed_gate_status_error('Installed gate status JSON gates must contain 14 rows: ' . $label);
        return;
    }

    $counts = [
        '통과' => 0,
        '부분 확인' => 0,
        '수동 확인 필요' => 0,
        '미실행' => 0,
        '환경 미준비' => 0,
        '실패' => 0,
    ];
    foreach ($gates as $gate) {
        if (!is_array($gate)) {
            sr_installed_gate_status_error('Installed gate status JSON gate row is not an object: ' . $label);
            continue;
        }

        foreach (['gate', 'result', 'environment', 'memo'] as $field) {
            if (!is_string($gate[$field] ?? null) || $gate[$field] === '') {
                sr_installed_gate_status_error('Installed gate status JSON gate row missing field ' . $field . ': ' . $label);
            }
        }

        $result = (string) ($gate['result'] ?? '');
        if (!array_key_exists($result, $counts)) {
            $counts[$result] = 0;
        }

        $counts[$result]++;
    }

    $jsonCounts = $decoded['result_counts'] ?? null;
    if (!is_array($jsonCounts)) {
        sr_installed_gate_status_error('Installed gate status JSON result_counts is missing: ' . $label);
        return;
    }

    foreach ($counts as $result => $count) {
        if (($jsonCounts[$result] ?? null) !== $count) {
            sr_installed_gate_status_error('Installed gate status JSON result_counts mismatch for ' . $label . ': ' . $result);
        }
    }

    if (($decoded['result_summary'] ?? '') !== '통과=0, 부분 확인=0, 수동 확인 필요=0, 미실행=10, 환경 미준비=4, 실패=0') {
        sr_installed_gate_status_error('Installed gate status JSON result_summary mismatch: ' . $label);
    }

    if (($decoded['unresolved_gates'] ?? null) !== 14) {
        sr_installed_gate_status_error('Installed gate status JSON unresolved_gates mismatch: ' . $label);
    }
}

function sr_installed_gate_status_assert_json_decodable(string $label, string $output): void
{
    if ($output === '') {
        return;
    }

    $decoded = json_decode($output, true);
    if (!is_array($decoded)) {
        sr_installed_gate_status_error('Installed gate status JSON output is invalid: ' . $label . ': ' . json_last_error_msg());
        return;
    }

    if (!is_array($decoded['metadata'] ?? null) || !is_array($decoded['gates'] ?? null)) {
        sr_installed_gate_status_error('Installed gate status JSON output is missing expected structure: ' . $label);
    }
}

$output = sr_installed_gate_status_exec([PHP_BINARY, '.tools/bin/release-installed-gate-status.php']);
sr_installed_gate_status_assert_unresolved_count('default output', $output);
sr_installed_gate_status_assert_result_summary('default output', $output);
foreach ([
    'release-installed-gate-status-version: 1',
    'installed-lock:',
    'config-readable:',
    'config-mode:',
    'config-owner-group:',
    'sr-is-installed:',
    'browser-qa-base-url:',
    'account-smoke-credentials: missing',
    'admin-smoke-credentials: missing',
    'asset-dedupe-expectation: missing',
    'run-http-smoke: no',
    'run-update-smoke: no',
    'run-readonly: no',
    'run-admin-readonly: no',
    'run-browser-qa: no',
    'run-auth-smoke: no',
    'run-quiz-smoke: no',
    'run-asset-smoke: no',
    'run-privacy-smoke: no',
    'run-ckeditor-upload-save-smoke: no',
    'run-privacy-fixtures: no',
    'run-performance-fixtures: no',
    'mutation-smoke-allowed: no',
    'public-mutation-url-allowed: no',
    "gate\t새 설치 또는 업데이트 적용\t",
    "gate\t`php .tools/bin/reconcile-assets.php`\t",
    "gate\t`php .tools/bin/ops-status.php`\t",
    "gate\t`php .tools/bin/expire-points.php --dry-run`\t",
    "gate\t/admin/assets/reconciliation\t",
    "gate\t/admin/operations\t",
    "gate\t기본 HTTP smoke\t",
    "gate\t인증 smoke\t",
    "gate\t퀴즈 E2E smoke\t",
    "gate\t자산/쿠폰/유료 접근권 mutation smoke\t",
    "gate\t개인정보 export/cleanup smoke\t",
    "gate\tCKEditor asset/fallback browser smoke\t",
    "gate\tCKEditor upload/save browser smoke\t",
    "gate\t성능 수동 점검\t",
    'gate-result-summary: 통과=0, 부분 확인=0, 수동 확인 필요=0, 미실행=10, 환경 미준비=4, 실패=0',
    'unresolved-gates:',
    'release installed gate status completed.',
] as $marker) {
    if ($output !== '' && !str_contains($output, $marker)) {
        sr_installed_gate_status_error('Installed gate status output marker missing: ' . $marker);
    }
}

$markdownOutput = sr_installed_gate_status_exec([PHP_BINARY, '.tools/bin/release-installed-gate-status.php', '--markdown-table']);
sr_installed_gate_status_assert_markdown_table('default markdown output', $markdownOutput);
foreach ([
    '| 새 설치 또는 업데이트 적용 | 환경 미준비 | current tree | config/config.php is not readable by current user |',
    '| /admin/assets/reconciliation | 미실행 | base URL missing | set SR_SMOKE_BASE_URL and use an administrator session to verify the read-only screen |',
    '| 기본 HTTP smoke | 미실행 | base URL missing | set SR_SMOKE_BASE_URL and run with --run-http-smoke to verify routes, security headers, and protected paths |',
    '| 성능 수동 점검 | 미실행 | base URL missing | set SR_SMOKE_BASE_URL after representative local/staging data is prepared; use --run-performance-fixtures only for static/runtime fixtures |',
] as $marker) {
    if ($markdownOutput !== '' && !str_contains($markdownOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status markdown output marker missing: ' . $marker);
    }
}

$jsonOutput = sr_installed_gate_status_exec([PHP_BINARY, '.tools/bin/release-installed-gate-status.php', '--json']);
sr_installed_gate_status_assert_json('default JSON output', $jsonOutput);

$invalidUtf8JsonOutput = sr_installed_gate_status_exec([
    'bash',
    '-lc',
    'SR_SMOKE_BASE_URL="$(printf \'http://127.0.0.1:1/\\377\')" ' . escapeshellarg(PHP_BINARY) . ' .tools/bin/release-installed-gate-status.php --json',
]);
sr_installed_gate_status_assert_json_decodable('invalid UTF-8 environment JSON output', $invalidUtf8JsonOutput);

$failOutput = sr_installed_gate_status_exec_result([PHP_BINARY, '.tools/bin/release-installed-gate-status.php', '--fail-on-unresolved']);
if ((int) $failOutput['exit_code'] !== 1) {
    sr_installed_gate_status_error('Installed gate status --fail-on-unresolved must exit 1 while gates are unresolved.');
}
foreach ([
    'gate-result-summary: 통과=0, 부분 확인=0, 수동 확인 필요=0, 미실행=10, 환경 미준비=4, 실패=0',
    'unresolved-gates: 14',
] as $marker) {
    if (!str_contains((string) $failOutput['output'], $marker)) {
        sr_installed_gate_status_error('Installed gate status --fail-on-unresolved output marker missing: ' . $marker);
    }
}

$markdownFailOutput = sr_installed_gate_status_exec_result([PHP_BINARY, '.tools/bin/release-installed-gate-status.php', '--markdown-table', '--fail-on-unresolved']);
if ((int) $markdownFailOutput['exit_code'] !== 1) {
    sr_installed_gate_status_error('Installed gate status --markdown-table --fail-on-unresolved must exit 1 while gates are unresolved.');
}
sr_installed_gate_status_assert_markdown_table('markdown fail-on-unresolved output', (string) $markdownFailOutput['output']);

$jsonFailOutput = sr_installed_gate_status_exec_result([PHP_BINARY, '.tools/bin/release-installed-gate-status.php', '--json', '--fail-on-unresolved']);
if ((int) $jsonFailOutput['exit_code'] !== 1) {
    sr_installed_gate_status_error('Installed gate status --json --fail-on-unresolved must exit 1 while gates are unresolved.');
}
sr_installed_gate_status_assert_json('JSON fail-on-unresolved output', (string) $jsonFailOutput['output']);

$unknownOptionOutput = sr_installed_gate_status_exec_result([PHP_BINARY, '.tools/bin/release-installed-gate-status.php', '--unknown-option']);
if ((int) $unknownOptionOutput['exit_code'] !== 2) {
    sr_installed_gate_status_error('Installed gate status unknown option must exit 2.');
}
foreach ([
    'Unknown release-installed-gate-status option: --unknown-option',
    'release-installed-gate-status.php --help',
] as $marker) {
    if (!str_contains((string) $unknownOptionOutput['output'], $marker)) {
        sr_installed_gate_status_error('Installed gate status unknown option output marker missing: ' . $marker);
    }
}

$unknownWithHelpOutput = sr_installed_gate_status_exec_result([
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
    '--help',
    '--unknown-option',
]);
if ((int) $unknownWithHelpOutput['exit_code'] !== 2) {
    sr_installed_gate_status_error('Installed gate status unknown option with help must exit 2.');
}
foreach ([
    'Unknown release-installed-gate-status option: --unknown-option',
    'release-installed-gate-status.php --help',
] as $marker) {
    if (!str_contains((string) $unknownWithHelpOutput['output'], $marker)) {
        sr_installed_gate_status_error('Installed gate status unknown option with help output marker missing: ' . $marker);
    }
}

$conflictingOutputOption = sr_installed_gate_status_exec_result([
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
    '--markdown-table',
    '--json',
]);
if ((int) $conflictingOutputOption['exit_code'] !== 2) {
    sr_installed_gate_status_error('Installed gate status conflicting output options must exit 2.');
}
foreach ([
    'output options are mutually exclusive: --markdown-table, --json',
    'release-installed-gate-status.php --help',
] as $marker) {
    if (!str_contains((string) $conflictingOutputOption['output'], $marker)) {
        sr_installed_gate_status_error('Installed gate status conflicting output option marker missing: ' . $marker);
    }
}

$helpOutput = sr_installed_gate_status_exec([PHP_BINARY, '.tools/bin/release-installed-gate-status.php', '--help']);
foreach ([
    'Usage:',
    '--markdown-table',
    '--json',
    '--fail-on-unresolved',
    '--run-http-smoke',
    '--run-update-smoke',
    '--run-readonly',
    '--run-admin-readonly',
    '--run-browser-qa',
    '--run-auth-smoke',
    '--run-quiz-smoke',
    '--run-asset-smoke',
    '--run-privacy-smoke',
    '--run-ckeditor-upload-save-smoke',
    '--run-privacy-fixtures',
    '--run-performance-fixtures',
    '--markdown-table and --json are mutually exclusive',
    'SR_SMOKE_BASE_URL',
    'SR_BROWSER_QA_BASE_URL',
    'SR_SMOKE_IDENTIFIER',
    'SR_SMOKE_PASSWORD',
    'SR_SMOKE_ADMIN_IDENTIFIER',
    'SR_SMOKE_UPDATE_MODULE_KEY',
    'SR_SMOKE_UPDATE_VERSION',
    'SR_SMOKE_ADMIN_PASSWORD',
    'SR_SMOKE_ALLOW_MUTATION=1',
    'SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1',
    'SR_SMOKE_FORM_PATH',
    'SR_SMOKE_EXPECT_DEDUPE_TABLE',
    'SR_SMOKE_EXPECT_DEDUPE_KEY',
    'SR_SMOKE_WITHDRAW_CONFIRM_TEXT',
    'Handoff:',
    '--run-readonly --fail-on-unresolved',
    'config/config.php is 0600 and owned by the web-server account',
    'SR_SMOKE_BASE_URL=https://staging.example.test',
    'SR_SMOKE_ADMIN_IDENTIFIER=<admin>',
    'SR_SMOKE_ADMIN_PASSWORD=<password>',
    '--json --fail-on-unresolved',
    'Do not run mutation smoke against production data',
    'config/config.php is not readable',
    'web-server',
    'local/staging-only execution user',
] as $marker) {
    if ($helpOutput !== '' && !str_contains($helpOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status help output marker missing: ' . $marker);
    }
}

$baseOnlyOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
sr_installed_gate_status_assert_unresolved_count('base-url-only output', $baseOnlyOutput);
sr_installed_gate_status_assert_result_summary('base-url-only output', $baseOnlyOutput);
foreach ([
    'base-url: http://127.0.0.1:1',
    'admin-smoke-credentials: missing',
    "gate\t기본 HTTP smoke\tresult=수동 확인 필요\tenvironment=http://127.0.0.1:1\tmemo=basic non-mutating HTTP smoke is available; rerun with --run-http-smoke",
    "gate\t/admin/assets/reconciliation\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=requires SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD",
    "gate\t/admin/operations\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=requires SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD",
    "gate\t퀴즈 E2E smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=requires SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD",
    "gate\tCKEditor upload/save browser smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=requires SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD",
    "gate\t개인정보 export/cleanup smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=requires SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD for disposable account data",
    "gate\t성능 수동 점검\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=manually verify slow admin lists, sitemap, privacy export bounds, and query plans",
] as $marker) {
    if ($baseOnlyOutput !== '' && !str_contains($baseOnlyOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status base-url-only output marker missing: ' . $marker);
    }
}

$maskedUrlOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://rawuser:rawpass@127.0.0.1:1/with-auth',
    'SR_BROWSER_QA_BASE_URL=http://browseruser:browserpass@127.0.0.1:2/browser-auth',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'base-url: http://***:***@127.0.0.1:1/with-auth',
    'browser-qa-base-url: http://***:***@127.0.0.1:2/browser-auth',
    "gate\t기본 HTTP smoke\tresult=수동 확인 필요\tenvironment=http://***:***@127.0.0.1:1/with-auth",
    "gate\t/admin/assets/reconciliation\tresult=미실행\tenvironment=http://***:***@127.0.0.1:1/with-auth",
    "gate\tCKEditor asset/fallback browser smoke\tresult=수동 확인 필요\tenvironment=http://***:***@127.0.0.1:2/browser-auth",
] as $marker) {
    if ($maskedUrlOutput !== '' && !str_contains($maskedUrlOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status masked URL output marker missing: ' . $marker);
    }
}
foreach (['rawuser', 'rawpass', 'browseruser', 'browserpass'] as $secretMarker) {
    if ($maskedUrlOutput !== '' && str_contains($maskedUrlOutput, $secretMarker)) {
        sr_installed_gate_status_error('Installed gate status text output must mask URL userinfo: ' . $secretMarker);
    }
}

$maskedUrlMarkdownOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://rawuser:rawpass@127.0.0.1:1/with-auth',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
    '--markdown-table',
]);
foreach (['http://***:***@127.0.0.1:1/with-auth'] as $marker) {
    if ($maskedUrlMarkdownOutput !== '' && !str_contains($maskedUrlMarkdownOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status markdown output masked URL marker missing: ' . $marker);
    }
}
foreach (['rawuser', 'rawpass'] as $secretMarker) {
    if ($maskedUrlMarkdownOutput !== '' && str_contains($maskedUrlMarkdownOutput, $secretMarker)) {
        sr_installed_gate_status_error('Installed gate status markdown output must mask URL userinfo: ' . $secretMarker);
    }
}

$maskedUrlJsonOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://rawuser:rawpass@127.0.0.1:1/with-auth',
    'SR_BROWSER_QA_BASE_URL=http://browseruser:browserpass@127.0.0.1:2/browser-auth',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
    '--json',
]);
if ($maskedUrlJsonOutput !== '' && !is_array(json_decode($maskedUrlJsonOutput, true))) {
    sr_installed_gate_status_error('Installed gate status masked URL JSON output is invalid.');
}
foreach ([
    '"base_url": "http://***:***@127.0.0.1:1/with-auth"',
    '"browser_qa_base_url": "http://***:***@127.0.0.1:2/browser-auth"',
    '"environment": "http://***:***@127.0.0.1:1/with-auth"',
] as $marker) {
    if ($maskedUrlJsonOutput !== '' && !str_contains($maskedUrlJsonOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status JSON output masked URL marker missing: ' . $marker);
    }
}
foreach (['rawuser', 'rawpass', 'browseruser', 'browserpass'] as $secretMarker) {
    if ($maskedUrlJsonOutput !== '' && str_contains($maskedUrlJsonOutput, $secretMarker)) {
        sr_installed_gate_status_error('Installed gate status JSON output must mask URL userinfo: ' . $secretMarker);
    }
}

$adminIncompleteIdentifierOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_ADMIN_IDENTIFIER=admin',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'admin-smoke-credentials: incomplete',
    "gate\t/admin/assets/reconciliation\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD must be provided together",
    "gate\t/admin/operations\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD must be provided together",
    "gate\t퀴즈 E2E smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD must be provided together",
    "gate\tCKEditor upload/save browser smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD must be provided together",
] as $marker) {
    if ($adminIncompleteIdentifierOutput !== '' && !str_contains($adminIncompleteIdentifierOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status admin-identifier-only output marker missing: ' . $marker);
    }
}

$adminIncompletePasswordOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_ADMIN_PASSWORD=12341234',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'admin-smoke-credentials: incomplete',
    "gate\t/admin/assets/reconciliation\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD must be provided together",
    "gate\t/admin/operations\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD must be provided together",
    "gate\t퀴즈 E2E smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD must be provided together",
    "gate\tCKEditor upload/save browser smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_ADMIN_IDENTIFIER and SR_SMOKE_ADMIN_PASSWORD must be provided together",
] as $marker) {
    if ($adminIncompletePasswordOutput !== '' && !str_contains($adminIncompletePasswordOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status admin-password-only output marker missing: ' . $marker);
    }
}

$adminConfiguredOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_ADMIN_IDENTIFIER=admin',
    'SR_SMOKE_ADMIN_PASSWORD=12341234',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
sr_installed_gate_status_assert_unresolved_count('admin-configured output', $adminConfiguredOutput);
sr_installed_gate_status_assert_result_summary('admin-configured output', $adminConfiguredOutput);
foreach ([
    'admin-smoke-credentials: configured',
    'run-admin-readonly: no',
    "gate\t/admin/assets/reconciliation\tresult=수동 확인 필요\tenvironment=http://127.0.0.1:1\tmemo=administrator session configured; rerun with --run-admin-readonly",
    "gate\t/admin/operations\tresult=수동 확인 필요\tenvironment=http://127.0.0.1:1\tmemo=administrator session configured; rerun with --run-admin-readonly",
    "gate\t퀴즈 E2E smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=quiz E2E smoke creates quiz and attempt data; set SR_SMOKE_ALLOW_MUTATION=1",
    "gate\tCKEditor upload/save browser smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=upload/save browser smoke creates or updates content; set SR_SMOKE_ALLOW_MUTATION=1",
] as $marker) {
    if ($adminConfiguredOutput !== '' && !str_contains($adminConfiguredOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status admin-configured output marker missing: ' . $marker);
    }
}

$adminReadonlyRunOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_ADMIN_IDENTIFIER=admin',
    'SR_SMOKE_ADMIN_PASSWORD=12341234',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
    '--run-admin-readonly',
]);
sr_installed_gate_status_assert_unresolved_count('admin-readonly-run output', $adminReadonlyRunOutput);
sr_installed_gate_status_assert_result_summary('admin-readonly-run output', $adminReadonlyRunOutput);
foreach ([
    'run-admin-readonly: yes',
    "gate\t/admin/assets/reconciliation\tresult=실패\tenvironment=http://127.0.0.1:1\tmemo=admin read-only smoke login failed; login form returned HTTP 0",
    "gate\t/admin/operations\tresult=실패\tenvironment=http://127.0.0.1:1\tmemo=admin read-only smoke login failed; login form returned HTTP 0",
] as $marker) {
    if ($adminReadonlyRunOutput !== '' && !str_contains($adminReadonlyRunOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status admin-readonly-run output marker missing: ' . $marker);
    }
}

$accountIncompleteIdentifierOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'account-smoke-credentials: incomplete',
    "gate\t인증 smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD must be provided together",
    "gate\t자산/쿠폰/유료 접근권 mutation smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD must be provided together",
    "gate\t개인정보 export/cleanup smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD must be provided together",
] as $marker) {
    if ($accountIncompleteIdentifierOutput !== '' && !str_contains($accountIncompleteIdentifierOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status account-identifier-only output marker missing: ' . $marker);
    }
}

$accountIncompletePasswordOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_PASSWORD=12341234',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'account-smoke-credentials: incomplete',
    "gate\t인증 smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD must be provided together",
    "gate\t자산/쿠폰/유료 접근권 mutation smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD must be provided together",
    "gate\t개인정보 export/cleanup smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_IDENTIFIER and SR_SMOKE_PASSWORD must be provided together",
] as $marker) {
    if ($accountIncompletePasswordOutput !== '' && !str_contains($accountIncompletePasswordOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status account-password-only output marker missing: ' . $marker);
    }
}

$assetMissingFormOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'account-smoke-credentials: configured',
    'asset-dedupe-expectation: missing',
    "gate\t자산/쿠폰/유료 접근권 mutation smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=requires SR_SMOKE_FORM_PATH for disposable paid target data",
    "gate\t개인정보 export/cleanup smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=privacy cleanup can mutate data; set SR_SMOKE_ALLOW_MUTATION=1",
] as $marker) {
    if ($assetMissingFormOutput !== '' && !str_contains($assetMissingFormOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status asset missing form output marker missing: ' . $marker);
    }
}

$assetDedupeMissingOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    'SR_SMOKE_FORM_PATH=/paid/form',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'asset-dedupe-expectation: missing',
    "gate\t자산/쿠폰/유료 접근권 mutation smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=requires SR_SMOKE_EXPECT_DEDUPE_TABLE and SR_SMOKE_EXPECT_DEDUPE_KEY",
] as $marker) {
    if ($assetDedupeMissingOutput !== '' && !str_contains($assetDedupeMissingOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status asset dedupe missing output marker missing: ' . $marker);
    }
}

$assetDedupeIncompleteTableOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    'SR_SMOKE_FORM_PATH=/paid/form',
    'SR_SMOKE_EXPECT_DEDUPE_TABLE=sr_content_asset_access_logs',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'asset-dedupe-expectation: incomplete',
    "gate\t자산/쿠폰/유료 접근권 mutation smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_EXPECT_DEDUPE_TABLE and SR_SMOKE_EXPECT_DEDUPE_KEY must be provided together",
] as $marker) {
    if ($assetDedupeIncompleteTableOutput !== '' && !str_contains($assetDedupeIncompleteTableOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status asset dedupe-table-only output marker missing: ' . $marker);
    }
}

$assetDedupeIncompleteKeyOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    'SR_SMOKE_FORM_PATH=/paid/form',
    'SR_SMOKE_EXPECT_DEDUPE_KEY=fixture:dedupe',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'asset-dedupe-expectation: incomplete',
    "gate\t자산/쿠폰/유료 접근권 mutation smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=SR_SMOKE_EXPECT_DEDUPE_TABLE and SR_SMOKE_EXPECT_DEDUPE_KEY must be provided together",
] as $marker) {
    if ($assetDedupeIncompleteKeyOutput !== '' && !str_contains($assetDedupeIncompleteKeyOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status asset dedupe-key-only output marker missing: ' . $marker);
    }
}

$authMutationBlockedOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
    '--run-auth-smoke',
]);
foreach ([
    'run-auth-smoke: yes',
    'mutation-smoke-allowed: no',
    "gate\t인증 smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=authenticated smoke creates data; set SR_SMOKE_ALLOW_MUTATION=1",
] as $marker) {
    if ($authMutationBlockedOutput !== '' && !str_contains($authMutationBlockedOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status auth mutation guard output marker missing: ' . $marker);
    }
}

$assetMutationBlockedOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    'SR_SMOKE_FORM_PATH=/paid/form',
    'SR_SMOKE_EXPECT_DEDUPE_TABLE=sr_content_asset_access_logs',
    'SR_SMOKE_EXPECT_DEDUPE_KEY=fixture:dedupe',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
    '--run-asset-smoke',
]);
foreach ([
    'run-asset-smoke: yes',
    'mutation-smoke-allowed: no',
    "gate\t자산/쿠폰/유료 접근권 mutation smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=asset idempotency smoke creates financial-like records; set SR_SMOKE_ALLOW_MUTATION=1",
] as $marker) {
    if ($assetMutationBlockedOutput !== '' && !str_contains($assetMutationBlockedOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status asset mutation guard output marker missing: ' . $marker);
    }
}

$quizMutationBlockedOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_ADMIN_IDENTIFIER=admin',
    'SR_SMOKE_ADMIN_PASSWORD=12341234',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
    '--run-quiz-smoke',
]);
foreach ([
    'run-quiz-smoke: yes',
    'mutation-smoke-allowed: no',
    "gate\t퀴즈 E2E smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=quiz E2E smoke creates quiz and attempt data; set SR_SMOKE_ALLOW_MUTATION=1",
] as $marker) {
    if ($quizMutationBlockedOutput !== '' && !str_contains($quizMutationBlockedOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status quiz mutation guard output marker missing: ' . $marker);
    }
}

$authReadyOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    'SR_SMOKE_ALLOW_MUTATION=1',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
sr_installed_gate_status_assert_unresolved_count('auth-ready output', $authReadyOutput);
sr_installed_gate_status_assert_result_summary('auth-ready output', $authReadyOutput);
foreach ([
    'mutation-smoke-allowed: yes',
    "gate\t인증 smoke\tresult=수동 확인 필요\tenvironment=http://127.0.0.1:1\tmemo=authenticated smoke is configured; rerun with --run-auth-smoke",
    "gate\t개인정보 export/cleanup smoke\tresult=수동 확인 필요\tenvironment=http://127.0.0.1:1\tmemo=privacy smoke is configured; rerun with --run-privacy-smoke",
] as $marker) {
    if ($authReadyOutput !== '' && !str_contains($authReadyOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status auth ready output marker missing: ' . $marker);
    }
}

$privacyMutationBlockedOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
    '--run-privacy-smoke',
]);
foreach ([
    'run-privacy-smoke: yes',
    'mutation-smoke-allowed: no',
    "gate\t개인정보 export/cleanup smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=privacy export/cleanup smoke withdraws/anonymizes an account; set SR_SMOKE_ALLOW_MUTATION=1",
] as $marker) {
    if ($privacyMutationBlockedOutput !== '' && !str_contains($privacyMutationBlockedOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status privacy mutation guard output marker missing: ' . $marker);
    }
}

$privacyRunFailureOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    'SR_SMOKE_ALLOW_MUTATION=1',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
    '--run-privacy-smoke',
]);
foreach ([
    'run-privacy-smoke: yes',
    "gate\t개인정보 export/cleanup smoke\tresult=실패\tenvironment=http://127.0.0.1:1\tmemo=smoke-privacy-export-cleanup.php exit",
] as $marker) {
    if ($privacyRunFailureOutput !== '' && !str_contains($privacyRunFailureOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status privacy run failure output marker missing: ' . $marker);
    }
}

$privacyDirectMutationRefusal = sr_installed_gate_status_exec_result([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    PHP_BINARY,
    '.tools/bin/smoke-privacy-export-cleanup.php',
]);
if ((int) $privacyDirectMutationRefusal['exit_code'] !== 2) {
    sr_installed_gate_status_error('Privacy export/cleanup smoke must exit 2 without SR_SMOKE_ALLOW_MUTATION=1.');
}
foreach ([
    'refused to run because it withdraws/anonymizes an account',
    'SR_SMOKE_ALLOW_MUTATION=1',
] as $marker) {
    if (!str_contains((string) $privacyDirectMutationRefusal['output'], $marker)) {
        sr_installed_gate_status_error('Privacy export/cleanup direct mutation refusal marker missing: ' . $marker);
    }
}

$privacyDirectPublicRefusal = sr_installed_gate_status_exec_result([
    'env',
    'SR_SMOKE_BASE_URL=https://example.com',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    'SR_SMOKE_ALLOW_MUTATION=1',
    PHP_BINARY,
    '.tools/bin/smoke-privacy-export-cleanup.php',
]);
if ((int) $privacyDirectPublicRefusal['exit_code'] !== 2) {
    sr_installed_gate_status_error('Privacy export/cleanup smoke must exit 2 for public-looking URLs without SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1.');
}
foreach ([
    'refused to run against a public-looking base URL',
    'SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1',
] as $marker) {
    if (!str_contains((string) $privacyDirectPublicRefusal['output'], $marker)) {
        sr_installed_gate_status_error('Privacy export/cleanup direct public URL refusal marker missing: ' . $marker);
    }
}

$publicMutationBlockedOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=https://example.com',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    'SR_SMOKE_ADMIN_IDENTIFIER=admin',
    'SR_SMOKE_ADMIN_PASSWORD=12341234',
    'SR_SMOKE_FORM_PATH=/paid/form',
    'SR_SMOKE_EXPECT_DEDUPE_TABLE=sr_content_asset_access_logs',
    'SR_SMOKE_EXPECT_DEDUPE_KEY=fixture:dedupe',
    'SR_SMOKE_ALLOW_MUTATION=1',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'public-mutation-url-allowed: no',
    'public-looking base URL requires SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1',
] as $marker) {
    if ($publicMutationBlockedOutput !== '' && !str_contains($publicMutationBlockedOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status public mutation URL guard marker missing: ' . $marker);
    }
}

$publicMutationAllowedOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=https://example.com',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    'SR_SMOKE_ALLOW_MUTATION=1',
    'SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
foreach ([
    'public-mutation-url-allowed: yes',
    "gate\t인증 smoke\tresult=수동 확인 필요\tenvironment=https://example.com\tmemo=authenticated smoke is configured; rerun with --run-auth-smoke",
] as $marker) {
    if ($publicMutationAllowedOutput !== '' && !str_contains($publicMutationAllowedOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status public mutation URL override marker missing: ' . $marker);
    }
}

$quizReadyOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_ADMIN_IDENTIFIER=admin',
    'SR_SMOKE_ADMIN_PASSWORD=12341234',
    'SR_SMOKE_ALLOW_MUTATION=1',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
sr_installed_gate_status_assert_unresolved_count('quiz-ready output', $quizReadyOutput);
sr_installed_gate_status_assert_result_summary('quiz-ready output', $quizReadyOutput);
foreach ([
    'mutation-smoke-allowed: yes',
    "gate\t퀴즈 E2E smoke\tresult=수동 확인 필요\tenvironment=http://127.0.0.1:1\tmemo=quiz E2E smoke is configured; rerun with --run-quiz-smoke",
    "gate\tCKEditor upload/save browser smoke\tresult=수동 확인 필요\tenvironment=http://127.0.0.1:1\tmemo=CKEditor upload/save smoke is configured; rerun with --run-ckeditor-upload-save-smoke",
] as $marker) {
    if ($quizReadyOutput !== '' && !str_contains($quizReadyOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status quiz ready output marker missing: ' . $marker);
    }
}

$ckeditorMutationBlockedOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_ADMIN_IDENTIFIER=admin',
    'SR_SMOKE_ADMIN_PASSWORD=12341234',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
    '--run-ckeditor-upload-save-smoke',
]);
foreach ([
    'run-ckeditor-upload-save-smoke: yes',
    'mutation-smoke-allowed: no',
    "gate\tCKEditor upload/save browser smoke\tresult=미실행\tenvironment=http://127.0.0.1:1\tmemo=upload/save browser smoke creates or updates content; set SR_SMOKE_ALLOW_MUTATION=1",
] as $marker) {
    if ($ckeditorMutationBlockedOutput !== '' && !str_contains($ckeditorMutationBlockedOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status CKEditor mutation guard output marker missing: ' . $marker);
    }
}

$ckeditorRunFailureOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_ADMIN_IDENTIFIER=admin',
    'SR_SMOKE_ADMIN_PASSWORD=12341234',
    'SR_SMOKE_ALLOW_MUTATION=1',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
    '--run-ckeditor-upload-save-smoke',
]);
foreach ([
    'run-ckeditor-upload-save-smoke: yes',
    "gate\tCKEditor upload/save browser smoke\tresult=실패\tenvironment=http://127.0.0.1:1\tmemo=smoke-ckeditor-upload-save.php exit",
] as $marker) {
    if ($ckeditorRunFailureOutput !== '' && !str_contains($ckeditorRunFailureOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status CKEditor run failure output marker missing: ' . $marker);
    }
}

$ckeditorDirectMutationRefusal = sr_installed_gate_status_exec_result([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_ADMIN_IDENTIFIER=admin',
    'SR_SMOKE_ADMIN_PASSWORD=12341234',
    PHP_BINARY,
    '.tools/bin/smoke-ckeditor-upload-save.php',
]);
if ((int) $ckeditorDirectMutationRefusal['exit_code'] !== 2) {
    sr_installed_gate_status_error('CKEditor upload/save smoke must exit 2 without SR_SMOKE_ALLOW_MUTATION=1.');
}
foreach ([
    'refused to run because it creates content and uploads files',
    'SR_SMOKE_ALLOW_MUTATION=1',
] as $marker) {
    if (!str_contains((string) $ckeditorDirectMutationRefusal['output'], $marker)) {
        sr_installed_gate_status_error('CKEditor upload/save direct mutation refusal marker missing: ' . $marker);
    }
}

$ckeditorDirectPublicRefusal = sr_installed_gate_status_exec_result([
    'env',
    'SR_SMOKE_BASE_URL=https://example.com',
    'SR_SMOKE_ADMIN_IDENTIFIER=admin',
    'SR_SMOKE_ADMIN_PASSWORD=12341234',
    'SR_SMOKE_ALLOW_MUTATION=1',
    PHP_BINARY,
    '.tools/bin/smoke-ckeditor-upload-save.php',
]);
if ((int) $ckeditorDirectPublicRefusal['exit_code'] !== 2) {
    sr_installed_gate_status_error('CKEditor upload/save smoke must exit 2 for public-looking URLs without SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1.');
}
foreach ([
    'refused to run against a public-looking base URL',
    'SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1',
] as $marker) {
    if (!str_contains((string) $ckeditorDirectPublicRefusal['output'], $marker)) {
        sr_installed_gate_status_error('CKEditor upload/save direct public URL refusal marker missing: ' . $marker);
    }
}

$assetReadyOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    'SR_SMOKE_IDENTIFIER=member',
    'SR_SMOKE_PASSWORD=12341234',
    'SR_SMOKE_FORM_PATH=/paid/form',
    'SR_SMOKE_EXPECT_DEDUPE_TABLE=sr_content_asset_access_logs',
    'SR_SMOKE_EXPECT_DEDUPE_KEY=fixture:dedupe',
    'SR_SMOKE_ALLOW_MUTATION=1',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
]);
sr_installed_gate_status_assert_unresolved_count('asset-ready output', $assetReadyOutput);
sr_installed_gate_status_assert_result_summary('asset-ready output', $assetReadyOutput);
foreach ([
    'mutation-smoke-allowed: yes',
    'asset-dedupe-expectation: configured',
    "gate\t자산/쿠폰/유료 접근권 mutation smoke\tresult=수동 확인 필요\tenvironment=http://127.0.0.1:1\tmemo=asset idempotency smoke is configured; rerun with --run-asset-smoke",
] as $marker) {
    if ($assetReadyOutput !== '' && !str_contains($assetReadyOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status asset ready output marker missing: ' . $marker);
    }
}

$browserQaFailureOutput = sr_installed_gate_status_exec([
    'env',
    'SR_SMOKE_BASE_URL=http://127.0.0.1:1',
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
    '--run-browser-qa',
]);
foreach ([
    'run-browser-qa: yes',
    "gate\tCKEditor asset/fallback browser smoke\tresult=실패\tenvironment=http://127.0.0.1:1\tmemo=npm --prefix .tools/browser-qa run test:ckeditor exit",
] as $marker) {
    if ($browserQaFailureOutput !== '' && !str_contains($browserQaFailureOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status browser QA failure output marker missing: ' . $marker);
    }
}

$fixtureOutput = sr_installed_gate_status_exec([
    PHP_BINARY,
    '.tools/bin/release-installed-gate-status.php',
    '--run-privacy-fixtures',
    '--run-performance-fixtures',
]);
sr_installed_gate_status_assert_unresolved_count('fixture output', $fixtureOutput);
sr_installed_gate_status_assert_result_summary('fixture output', $fixtureOutput);
foreach ([
    'run-privacy-fixtures: yes',
    'run-performance-fixtures: yes',
    "gate\t개인정보 export/cleanup smoke\tresult=부분 확인\tenvironment=SQLite contract fixtures",
    "gate\t성능 수동 점검\tresult=부분 확인\tenvironment=static and SQLite runtime fixtures",
    'installed DB smoke still required',
    'installed DB performance review still required',
    'fixture exits: policy=0, baseline=0, pagination=0, board-copy=0, survey-export=0',
    'privacy export runtime checks completed.',
    'privacy cleanup runtime checks completed.',
] as $marker) {
    if ($fixtureOutput !== '' && !str_contains($fixtureOutput, $marker)) {
        sr_installed_gate_status_error('Installed gate status fixture output marker missing: ' . $marker);
    }
}

sr_installed_gate_status_require_markers('.tools/bin/release-installed-gate-status.php', [
    'release-installed-gate-status-version: 1',
    '$allowedArgs',
    'Unknown release-installed-gate-status option',
    'unresolved-gates',
    'gate-result-summary',
    'sr_release_gate_status_result_summary',
    'sr_release_gate_status_json',
    'sr_release_gate_status_result_counts',
    'sr_release_gate_status_exit_code',
    'release-installed-gate-status JSON encoding failed: ',
    'json_last_error_msg()',
    '--json',
    '--fail-on-unresolved',
    '--help',
    '--markdown-table',
    '--run-http-smoke',
    '--run-update-smoke',
    '--run-readonly',
    '--run-browser-qa',
    '--run-auth-smoke',
    '--run-quiz-smoke',
    '--run-asset-smoke',
    '--run-privacy-smoke',
    '--run-privacy-fixtures',
    '--run-performance-fixtures',
    'SR_SMOKE_ALLOW_MUTATION',
    'SR_SMOKE_ADMIN_IDENTIFIER',
    'SR_SMOKE_ADMIN_PASSWORD',
    'SR_SMOKE_UPDATE_MODULE_KEY',
    'SR_SMOKE_UPDATE_VERSION',
    'SR_SMOKE_IDENTIFIER',
    'SR_SMOKE_PASSWORD',
    'SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL',
    'SR_SMOKE_EXPECT_DEDUPE_TABLE',
    'SR_SMOKE_EXPECT_DEDUPE_KEY',
    'SR_SMOKE_WITHDRAW_CONFIRM_TEXT',
    'public_mutation_url_allowed',
    'asset_dedupe_expectation',
    'dedupe row count evidence',
    'incomplete',
    'config/config.php is not readable by current user',
    'sr_release_gate_status_pair_status',
    'sr_release_gate_status_base_url_requires_public_mutation_override',
    'public-looking base URL requires SR_SMOKE_ALLOW_PUBLIC_MUTATION_URL=1',
    'sr_release_gate_status_json_safe_value($payload)',
    'JSON_INVALID_UTF8_SUBSTITUTE',
    'sr_release_gate_status_mask_url_userinfo_in_text($normalized)',
    'sr_release_gate_status_utf8_clean($normalized)',
    'sr_release_gate_status_utf8_truncate($normalized, 220)',
    'preg_match_all(\'/./us\'',
    'sr_release_gate_status_mask_url_userinfo((string) ($matches[0] ?? \'\'))',
    'sr_release_gate_status_admin_readonly_gate',
    'sr_release_gate_status_update_smoke_gate',
    'sr_release_gate_status_ckeditor_upload_save_gate',
    'sr_release_gate_status_file_mode',
    'sr_release_gate_status_file_owner_group',
    'expire-points.php',
    '--dry-run',
    'array_merge([PHP_BINARY, $commandLabel], $commandArgs)',
    'set SR_SMOKE_BASE_URL and use an administrator session',
    'sr_release_gate_status_http_smoke_gate',
    'smoke-http.php',
    'smoke-update-apply.php',
    'basic non-mutating HTTP smoke',
    'SR_BROWSER_QA_BASE_URL',
    'npm --prefix .tools/browser-qa run test:ckeditor',
    'smoke-community-auth.php',
    'smoke-quiz-e2e.php',
    'smoke-asset-idempotency-http.php',
    'smoke-privacy-export-cleanup.php',
    'smoke-ckeditor-upload-save.php',
    '--run-update-smoke',
    '--run-ckeditor-upload-save-smoke',
    'check-privacy-export-runtime.php',
    'check-privacy-cleanup-runtime.php',
    'sr_release_gate_status_privacy_gate($baseUrl, $accountSmokeCredentialStatus, $allowMutationSmoke, $allowPublicMutationUrl, $runPrivacySmoke, $runPrivacyFixtures)',
    'privacy cleanup can mutate data',
    'privacy smoke is configured',
    'privacy export/cleanup smoke withdraws/anonymizes an account',
    'check-performance-policy.php',
    'check-performance-baseline.php',
    'check-admin-pagination-runtime.php',
    'check-community-board-copy-limits.php',
    'check-survey-export-runtime.php',
    'sr_release_gate_status_performance_gate($baseUrl, $runPerformanceFixtures)',
    'manually verify slow admin lists',
    'installed DB performance review still required',
    'installed DB smoke still required',
    'CKEditor upload/save smoke is configured',
    'upload/save browser smoke creates or updates content',
    'do not run against production',
]);

sr_installed_gate_status_require_markers('.tools/bin/smoke-update-apply.php', [
    'saanraan update apply smoke refused to run because it mutates schema version rows',
    'saanraan update apply smoke refused to run against a public-looking URL',
    'update-apply-smoke-version: 1',
    'target-update:',
    'pending-created: yes',
    'pending-cleared: yes',
    'schema-version-recorded: yes',
    'audit-completed: yes',
    'module-version-synced: yes',
    'saanraan update apply smoke completed.',
]);

sr_installed_gate_status_require_markers('.tools/bin/smoke-privacy-export-cleanup.php', [
    'exported_at was not a parseable timestamp',
    'account_id was not a positive integer',
    'privacy_requests was not an array',
    'member module export was empty or invalid',
    'privacy-requests-array',
    'post-withdraw-account-blocked',
    'post-withdraw-login-blocked',
]);

sr_installed_gate_status_require_markers('.tools/bin/smoke-ckeditor-upload-save.php', [
    'temporary-image-admin-access',
    'temporary-image-guest-blocked',
    'saved-image-guest-access',
    'temporary-image-finalized',
    'draft-preview-admin-access',
    'draft-page-guest-blocked',
    'draft-image-admin-access',
    'draft-image-guest-blocked',
    'draft-temporary-image-finalized',
    'Temporary body image was accessible without an administrator session',
    'Saved public content body image was not accessible without a session',
    'Temporary body image URL still resolved after content save',
    'Draft content page was accessible without an administrator session',
    'Draft content body image was accessible without an administrator session',
]);

sr_installed_gate_status_require_markers('docs/release-verification-template.md', [
    'php .tools/bin/release-installed-gate-status.php',
    'php .tools/bin/release-installed-gate-status.php --markdown-table',
    'php .tools/bin/release-installed-gate-status.php --json',
    '--fail-on-unresolved',
    '--run-http-smoke',
    '기본 HTTP smoke',
    'php .tools/bin/release-installed-gate-status.php --run-readonly',
    'php .tools/bin/release-installed-gate-status.php --run-readonly --fail-on-unresolved',
    'php .tools/bin/release-installed-gate-status.php --json --fail-on-unresolved',
    'SR_SMOKE_ADMIN_IDENTIFIER',
    'SR_SMOKE_ADMIN_PASSWORD',
    '권한을 넓히지 말고',
    'php .tools/bin/expire-points.php --dry-run',
    '설치 DB 게이트 상태표',
]);

sr_installed_gate_status_require_markers('docs/verification-status.md', [
    'php .tools/bin/release-installed-gate-status.php',
    '설치 DB 게이트 상태표',
    'gate-result-summary',
    'unresolved-gates',
    'php .tools/bin/release-installed-gate-status.php --run-readonly --fail-on-unresolved',
    'php .tools/bin/release-installed-gate-status.php --json --fail-on-unresolved',
    '권한을 넓히지 말고',
]);

sr_installed_gate_status_require_markers('docs/smoke-test.md', [
    'php .tools/bin/release-installed-gate-status.php',
    'php .tools/bin/release-installed-gate-status.php --help',
    '알 수 없는 옵션',
    'exit 2',
    '`--markdown-table`과 `--json`은 서로 배타적인 출력 형식',
    'URL userinfo를 metadata, gate 환경값, 실행 출력 요약에 남기기 전에 마스킹',
    'php .tools/bin/release-installed-gate-status.php --json',
    '--fail-on-unresolved',
    '--run-http-smoke',
    '--run-readonly',
    '--run-browser-qa',
    '--run-auth-smoke',
    '--run-quiz-smoke',
    '--run-asset-smoke',
    '--run-privacy-smoke',
    'SR_SMOKE_ALLOW_MUTATION=1',
    'SR_SMOKE_EXPECT_DEDUPE_TABLE',
    'SR_SMOKE_EXPECT_DEDUPE_KEY',
    '--run-privacy-fixtures',
    '--run-performance-fixtures',
    '부분 확인',
    '대체하지 않는다',
]);

sr_installed_gate_status_require_markers('docs/records/improvement-hardening-verification-2026-06-11.md', [
    'php .tools/bin/release-installed-gate-status.php',
    'release-installed-gate-status-version: 1',
]);

sr_installed_gate_status_check_admin_readonly_mock_fixture();
sr_installed_gate_status_check_asset_smoke_mock_fixture();
sr_installed_gate_status_check_ckeditor_smoke_mock_fixture();
sr_installed_gate_status_check_privacy_smoke_mock_fixture();

if ($errors !== []) {
    fwrite(STDERR, "installed gate status checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "installed gate status checks completed.\n";
