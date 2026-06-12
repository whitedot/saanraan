#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_request_contract_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_request_contract_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_request_contract_runtime_error($message);
    }
}

function sr_request_contract_runtime_php_string(string $value): string
{
    return var_export($value, true);
}

function sr_request_contract_runtime_run(string $name, string $body): array
{
    $root = dirname(__DIR__, 2);
    $script = tempnam(sys_get_temp_dir(), 'sr-request-contract-');
    if (!is_string($script)) {
        sr_request_contract_runtime_error('Cannot create temporary fixture script: ' . $name);
        return [
            'exit_code' => 1,
            'stdout' => '',
            'stderr' => '',
            'contract' => null,
        ];
    }

    $source = "<?php\n"
        . "declare(strict_types=1);\n"
        . "define('SR_ROOT', " . sr_request_contract_runtime_php_string($root) . ");\n"
        . "chdir(SR_ROOT);\n"
        . "require_once SR_ROOT . '/core/helpers.php';\n"
        . "register_shutdown_function(static function (): void {\n"
        . "    echo \"\\n__SR_CONTRACT__\" . json_encode(\$GLOBALS['sr_request_contract'] ?? null, JSON_UNESCAPED_SLASHES) . \"__END__\\n\";\n"
        . "});\n"
        . $body
        . "\n";

    if (file_put_contents($script, $source) === false) {
        unlink($script);
        sr_request_contract_runtime_error('Cannot write temporary fixture script: ' . $name);
        return [
            'exit_code' => 1,
            'stdout' => '',
            'stderr' => '',
            'contract' => null,
        ];
    }

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open([PHP_BINARY, $script], $descriptorSpec, $pipes, $root);
    if (!is_resource($process)) {
        unlink($script);
        sr_request_contract_runtime_error('Cannot start fixture process: ' . $name);
        return [
            'exit_code' => 1,
            'stdout' => '',
            'stderr' => '',
            'contract' => null,
        ];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    unlink($script);

    $stdout = is_string($stdout) ? $stdout : '';
    $stderr = is_string($stderr) ? $stderr : '';
    $contract = null;
    if (preg_match('/__SR_CONTRACT__(.*?)__END__/s', $stdout, $matches) === 1) {
        $decoded = json_decode($matches[1], true);
        $contract = is_array($decoded) ? $decoded : null;
    }

    return [
        'exit_code' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'contract' => $contract,
    ];
}

function sr_request_contract_runtime_expect_contract(array $result, string $field, mixed $expected, string $message): void
{
    $contract = $result['contract'] ?? null;
    sr_request_contract_runtime_assert(is_array($contract), $message . ' Contract was not emitted.');
    if (!is_array($contract)) {
        return;
    }

    sr_request_contract_runtime_assert(($contract[$field] ?? null) === $expected, $message);
}

$csrfTokenResult = sr_request_contract_runtime_run('csrf token generation', <<<'PHP'
$_SESSION = [];
$first = sr_csrf_token();
$second = sr_csrf_token();
if ($first !== $second || preg_match('/\A[a-f0-9]{64}\z/', $first) !== 1) {
    fwrite(STDERR, "CSRF token is not stable 64-character hex.\n");
    exit(1);
}
PHP);
sr_request_contract_runtime_assert($csrfTokenResult['exit_code'] === 0, 'CSRF token generation fixture should pass.');

$validCsrfResult = sr_request_contract_runtime_run('valid csrf post', <<<'PHP'
$_SESSION = ['sr_csrf_token' => str_repeat('a', 64)];
$_POST = ['csrf_token' => str_repeat('a', 64)];
sr_start_request_contract('POST', '/fixture', 'fixture', 'fixture-post.php');
sr_require_csrf();
sr_enforce_request_contract('fixture_end');
PHP);
sr_request_contract_runtime_assert($validCsrfResult['exit_code'] === 0, 'Valid CSRF POST fixture should not fail.');
sr_request_contract_runtime_expect_contract($validCsrfResult, 'csrf_checked', true, 'Valid CSRF POST should mark csrf_checked.');
sr_request_contract_runtime_expect_contract($validCsrfResult, 'exit_reason', 'completed', 'Valid CSRF POST should complete the request contract.');
sr_request_contract_runtime_expect_contract($validCsrfResult, 'resolved_stage', 'fixture_end', 'Valid CSRF POST should resolve at the fixture stage.');

$invalidCsrfResult = sr_request_contract_runtime_run('invalid csrf post', <<<'PHP'
$_SESSION = ['sr_csrf_token' => str_repeat('b', 64)];
$_POST = ['csrf_token' => str_repeat('c', 64)];
sr_start_request_contract('POST', '/fixture', 'fixture', 'fixture-post.php');
sr_require_csrf();
PHP);
sr_request_contract_runtime_expect_contract($invalidCsrfResult, 'csrf_checked', true, 'Invalid CSRF POST should still mark that the guard ran.');
sr_request_contract_runtime_expect_contract($invalidCsrfResult, 'exit_reason', 'guard_blocked', 'Invalid CSRF POST should be treated as a guard block.');
sr_request_contract_runtime_expect_contract($invalidCsrfResult, 'blocked_guard', 'csrf', 'Invalid CSRF POST should identify the csrf guard.');

$missingCsrfResult = sr_request_contract_runtime_run('missing csrf call', <<<'PHP'
sr_start_request_contract('POST', '/fixture', 'fixture', 'fixture-post.php');
sr_enforce_request_contract('fixture_end');
PHP);
sr_request_contract_runtime_assert($missingCsrfResult['exit_code'] === 1, 'POST without sr_require_csrf() should exit with a contract violation.');
sr_request_contract_runtime_assert(
    str_contains((string) $missingCsrfResult['stdout'], "Internal error\n"),
    'POST without sr_require_csrf() should render a generic internal error.'
);
sr_request_contract_runtime_assert(
    str_contains((string) $missingCsrfResult['stderr'], 'POST action did not call sr_require_csrf().'),
    'POST without sr_require_csrf() should log the missing CSRF contract.'
);
sr_request_contract_runtime_expect_contract($missingCsrfResult, 'exit_reason', 'violation', 'POST without CSRF should set violation exit_reason.');

$publicGetResult = sr_request_contract_runtime_run('public get', <<<'PHP'
sr_start_request_contract('GET', '/fixture', 'fixture', 'fixture-get.php');
sr_enforce_request_contract('fixture_end');
PHP);
sr_request_contract_runtime_assert($publicGetResult['exit_code'] === 0, 'Public GET fixture should pass without guards.');
sr_request_contract_runtime_expect_contract($publicGetResult, 'exit_reason', 'completed', 'Public GET should complete the request contract.');

$adminMissingGuardsResult = sr_request_contract_runtime_run('admin missing guards', <<<'PHP'
sr_start_request_contract('GET', '/admin/fixture', 'admin', 'admin-fixture.php');
sr_enforce_request_contract('fixture_end');
PHP);
sr_request_contract_runtime_assert($adminMissingGuardsResult['exit_code'] === 1, 'Admin GET without auth/role guards should exit with a contract violation.');
sr_request_contract_runtime_assert(
    str_contains((string) $adminMissingGuardsResult['stderr'], 'Admin action did not call sr_member_require_login().')
        && str_contains((string) $adminMissingGuardsResult['stderr'], 'Admin action did not call sr_admin_require_permission().'),
    'Admin GET without guards should log missing auth and role contracts.'
);
sr_request_contract_runtime_expect_contract($adminMissingGuardsResult, 'exit_reason', 'violation', 'Admin GET without guards should set violation exit_reason.');

$adminCompleteResult = sr_request_contract_runtime_run('admin complete guards', <<<'PHP'
sr_start_request_contract('GET', '/admin/fixture', 'admin', 'admin-fixture.php');
sr_request_contract_mark('auth_checked');
sr_request_contract_mark('role_checked');
sr_enforce_request_contract('fixture_end');
PHP);
sr_request_contract_runtime_assert($adminCompleteResult['exit_code'] === 0, 'Admin GET with auth and role marks should pass.');
sr_request_contract_runtime_expect_contract($adminCompleteResult, 'auth_checked', true, 'Admin GET should mark auth_checked.');
sr_request_contract_runtime_expect_contract($adminCompleteResult, 'role_checked', true, 'Admin GET should mark role_checked.');
sr_request_contract_runtime_expect_contract($adminCompleteResult, 'exit_reason', 'completed', 'Admin GET with guards should complete the request contract.');

$publicRedirectResult = sr_request_contract_runtime_run('public redirect complete', <<<'PHP'
sr_start_request_contract('GET', '/fixture', 'fixture', 'fixture-redirect.php');
sr_redirect('/target?x=1');
PHP);
sr_request_contract_runtime_assert($publicRedirectResult['exit_code'] === 0, 'Public redirect fixture should exit cleanly.');
sr_request_contract_runtime_expect_contract($publicRedirectResult, 'exit_reason', 'completed', 'Public redirect should complete the request contract.');
sr_request_contract_runtime_expect_contract($publicRedirectResult, 'resolved_stage', 'before_response_end', 'Public redirect should finish through sr_finish_response().');

$adminRedirectMissingGuardsResult = sr_request_contract_runtime_run('admin redirect missing guards', <<<'PHP'
sr_start_request_contract('GET', '/admin/fixture', 'admin', 'admin-redirect.php');
sr_redirect('/admin');
PHP);
sr_request_contract_runtime_assert($adminRedirectMissingGuardsResult['exit_code'] === 1, 'Admin redirect without guards should exit with a contract violation.');
sr_request_contract_runtime_assert(
    str_contains((string) $adminRedirectMissingGuardsResult['stderr'], 'Admin action did not call sr_member_require_login().')
        && str_contains((string) $adminRedirectMissingGuardsResult['stderr'], 'Admin action did not call sr_admin_require_permission().'),
    'Admin redirect without guards should log missing auth and role contracts before redirect.'
);
sr_request_contract_runtime_expect_contract($adminRedirectMissingGuardsResult, 'exit_reason', 'violation', 'Admin redirect without guards should set violation exit_reason.');
sr_request_contract_runtime_expect_contract($adminRedirectMissingGuardsResult, 'resolved_stage', 'before_redirect', 'Admin redirect without guards should be blocked before redirect.');

$adminFinishMissingGuardsResult = sr_request_contract_runtime_run('admin finish missing guards', <<<'PHP'
sr_start_request_contract('GET', '/admin/fixture', 'admin', 'admin-finish.php');
sr_finish_response();
PHP);
sr_request_contract_runtime_assert($adminFinishMissingGuardsResult['exit_code'] === 1, 'Admin finish_response without guards should exit with a contract violation.');
sr_request_contract_runtime_expect_contract($adminFinishMissingGuardsResult, 'exit_reason', 'violation', 'Admin finish_response without guards should set violation exit_reason.');
sr_request_contract_runtime_expect_contract($adminFinishMissingGuardsResult, 'resolved_stage', 'before_response_end', 'Admin finish_response without guards should be blocked before response end.');

if ($errors !== []) {
    fwrite(STDERR, "request contract runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "request contract runtime checks completed.\n";
