#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_asset_idempotency_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_asset_idempotency_file(string $file): string
{
    $content = is_file($file) ? file_get_contents($file) : false;
    if (!is_string($content)) {
        sr_asset_idempotency_error('required file is missing or unreadable: ' . $file);
        return '';
    }

    return $content;
}

function sr_asset_idempotency_contains(string $file, array $markers): void
{
    $content = sr_asset_idempotency_file($file);
    foreach ($markers as $marker) {
        if (!str_contains($content, $marker)) {
            sr_asset_idempotency_error($file . ' is missing marker: ' . $marker);
        }
    }
}

function sr_asset_idempotency_function_body(string $file, string $functionName): string
{
    $source = sr_asset_idempotency_file($file);
    if ($source === '') {
        return '';
    }

    $pattern = '/function\s+' . preg_quote($functionName, '/') . '\s*\(/';
    if (preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE) !== 1) {
        sr_asset_idempotency_error($file . ' is missing function: ' . $functionName);
        return '';
    }

    $start = (int) $matches[0][1];
    $braceStart = strpos($source, '{', $start);
    if ($braceStart === false) {
        sr_asset_idempotency_error($file . ' function body is unreadable: ' . $functionName);
        return '';
    }

    $depth = 0;
    $length = strlen($source);
    for ($index = $braceStart; $index < $length; $index++) {
        $char = $source[$index];
        if ($char === '{') {
            $depth++;
        } elseif ($char === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($source, $braceStart, $index - $braceStart + 1);
            }
        }
    }

    sr_asset_idempotency_error($file . ' function body is not closed: ' . $functionName);
    return '';
}

function sr_asset_idempotency_order(string $label, string $content, string $first, string $second): void
{
    $firstPosition = strpos($content, $first);
    $secondPosition = strpos($content, $second);
    if ($firstPosition === false || $secondPosition === false || $firstPosition >= $secondPosition) {
        sr_asset_idempotency_error($label . ' must keep order: ' . $first . ' before ' . $second);
    }
}

function sr_asset_idempotency_fixture_insert_placeholder(PDO $pdo, string $tableName, string $dedupeKey, string $status): bool
{
    $stmt = $pdo->prepare(
        'INSERT OR IGNORE INTO ' . $tableName . ' (dedupe_key, log_status, transaction_id)
         VALUES (:dedupe_key, :log_status, 0)'
    );
    $stmt->execute([
        'dedupe_key' => $dedupeKey,
        'log_status' => $status,
    ]);

    return $stmt->rowCount() > 0;
}

function sr_asset_idempotency_fixture_complete_placeholder(PDO $pdo, string $tableName, string $dedupeKey, string $completedStatus): void
{
    $stmt = $pdo->prepare(
        'UPDATE ' . $tableName . '
         SET transaction_id = 101,
             log_status = :log_status
         WHERE dedupe_key = :dedupe_key'
    );
    $stmt->execute([
        'dedupe_key' => $dedupeKey,
        'log_status' => $completedStatus,
    ]);
}

function sr_asset_idempotency_fixture_delete_pending(PDO $pdo, string $tableName, string $dedupeKey, string $pendingStatus): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM ' . $tableName . '
         WHERE dedupe_key = :dedupe_key
           AND log_status = :log_status'
    );
    $stmt->execute([
        'dedupe_key' => $dedupeKey,
        'log_status' => $pendingStatus,
    ]);
}

function sr_asset_idempotency_fixture_count(PDO $pdo, string $tableName, string $dedupeKey): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) AS row_count FROM ' . $tableName . ' WHERE dedupe_key = :dedupe_key');
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? (int) ($row['row_count'] ?? 0) : 0;
}

function sr_asset_idempotency_fixture_status(PDO $pdo, string $tableName, string $dedupeKey): string
{
    $stmt = $pdo->prepare('SELECT log_status FROM ' . $tableName . ' WHERE dedupe_key = :dedupe_key LIMIT 1');
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? (string) ($row['log_status'] ?? '') : '';
}

function sr_asset_idempotency_check_claim_fixture(PDO $pdo, string $tableName, string $label): void
{
    $pdo->exec('CREATE TABLE ' . $tableName . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, dedupe_key TEXT NOT NULL UNIQUE, log_status TEXT NOT NULL, transaction_id INTEGER NOT NULL DEFAULT 0)');

    $pendingStatus = 'pending';
    $completedStatus = 'completed';
    $completedKey = $label . ':completed';
    if (!sr_asset_idempotency_fixture_insert_placeholder($pdo, $tableName, $completedKey, $pendingStatus)) {
        sr_asset_idempotency_error($label . ' fixture should insert the first pending placeholder.');
    }
    if (sr_asset_idempotency_fixture_insert_placeholder($pdo, $tableName, $completedKey, $pendingStatus)) {
        sr_asset_idempotency_error($label . ' fixture should ignore a duplicate pending placeholder.');
    }
    sr_asset_idempotency_fixture_complete_placeholder($pdo, $tableName, $completedKey, $completedStatus);
    if (sr_asset_idempotency_fixture_status($pdo, $tableName, $completedKey) !== $completedStatus) {
        sr_asset_idempotency_error($label . ' fixture should complete the claimed placeholder.');
    }
    if (sr_asset_idempotency_fixture_insert_placeholder($pdo, $tableName, $completedKey, $pendingStatus)) {
        sr_asset_idempotency_error($label . ' fixture should keep completed claims sticky.');
    }
    sr_asset_idempotency_fixture_delete_pending($pdo, $tableName, $completedKey, $pendingStatus);
    if (sr_asset_idempotency_fixture_count($pdo, $tableName, $completedKey) !== 1) {
        sr_asset_idempotency_error($label . ' fixture should not delete completed claims with pending cleanup.');
    }

    $retryKey = $label . ':retry';
    if (!sr_asset_idempotency_fixture_insert_placeholder($pdo, $tableName, $retryKey, $pendingStatus)) {
        sr_asset_idempotency_error($label . ' retry fixture should insert the first pending placeholder.');
    }
    sr_asset_idempotency_fixture_delete_pending($pdo, $tableName, $retryKey, $pendingStatus);
    if (sr_asset_idempotency_fixture_count($pdo, $tableName, $retryKey) !== 0) {
        sr_asset_idempotency_error($label . ' retry fixture should remove pending claims after failure cleanup.');
    }
    if (!sr_asset_idempotency_fixture_insert_placeholder($pdo, $tableName, $retryKey, $pendingStatus)) {
        sr_asset_idempotency_error($label . ' retry fixture should allow a new claim after pending cleanup.');
    }

    $rollbackKey = $label . ':rollback';
    $pdo->beginTransaction();
    if (!sr_asset_idempotency_fixture_insert_placeholder($pdo, $tableName, $rollbackKey, $pendingStatus)) {
        sr_asset_idempotency_error($label . ' rollback fixture should insert the transactional pending placeholder.');
    }
    $pdo->rollBack();
    if (sr_asset_idempotency_fixture_count($pdo, $tableName, $rollbackKey) !== 0) {
        sr_asset_idempotency_error($label . ' rollback fixture should leave no claim row after transaction rollback.');
    }
    if (!sr_asset_idempotency_fixture_insert_placeholder($pdo, $tableName, $rollbackKey, $pendingStatus)) {
        sr_asset_idempotency_error($label . ' rollback fixture should allow a new claim after rollback.');
    }

    $committedKey = $label . ':committed';
    $pdo->beginTransaction();
    if (!sr_asset_idempotency_fixture_insert_placeholder($pdo, $tableName, $committedKey, $pendingStatus)) {
        sr_asset_idempotency_error($label . ' committed fixture should insert the transactional pending placeholder.');
    }
    sr_asset_idempotency_fixture_complete_placeholder($pdo, $tableName, $committedKey, $completedStatus);
    $pdo->commit();
    if (sr_asset_idempotency_fixture_status($pdo, $tableName, $committedKey) !== $completedStatus) {
        sr_asset_idempotency_error($label . ' committed fixture should keep the completed claim after commit.');
    }
    if (sr_asset_idempotency_fixture_insert_placeholder($pdo, $tableName, $committedKey, $pendingStatus)) {
        sr_asset_idempotency_error($label . ' committed fixture should absorb duplicate claims after commit.');
    }
}

function sr_asset_idempotency_open_sqlite_file(string $path): PDO
{
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA busy_timeout = 0');

    return $pdo;
}

function sr_asset_idempotency_check_cross_connection_fixture(string $tableName, string $label): void
{
    $path = tempnam(sys_get_temp_dir(), 'sr_asset_idempotency_');
    if (!is_string($path) || $path === '') {
        sr_asset_idempotency_error($label . ' cross-connection fixture could not create a temporary database.');
        return;
    }

    try {
        $first = sr_asset_idempotency_open_sqlite_file($path);
        $second = sr_asset_idempotency_open_sqlite_file($path);
        $first->exec('CREATE TABLE ' . $tableName . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, dedupe_key TEXT NOT NULL UNIQUE, log_status TEXT NOT NULL, transaction_id INTEGER NOT NULL DEFAULT 0)');

        $pendingKey = $label . ':cross-pending';
        $completedKey = $label . ':cross-completed';
        if (!sr_asset_idempotency_fixture_insert_placeholder($first, $tableName, $pendingKey, 'pending')) {
            sr_asset_idempotency_error($label . ' cross-connection fixture should insert the first pending claim.');
        }
        if (sr_asset_idempotency_fixture_insert_placeholder($second, $tableName, $pendingKey, 'pending')) {
            sr_asset_idempotency_error($label . ' cross-connection fixture should absorb a duplicate pending claim from another connection.');
        }
        if (sr_asset_idempotency_fixture_count($second, $tableName, $pendingKey) !== 1) {
            sr_asset_idempotency_error($label . ' cross-connection fixture should expose exactly one pending claim to another connection.');
        }

        if (!sr_asset_idempotency_fixture_insert_placeholder($first, $tableName, $completedKey, 'pending')) {
            sr_asset_idempotency_error($label . ' cross-connection fixture should insert the first completed claim placeholder.');
        }
        sr_asset_idempotency_fixture_complete_placeholder($first, $tableName, $completedKey, 'completed');
        if (sr_asset_idempotency_fixture_insert_placeholder($second, $tableName, $completedKey, 'pending')) {
            sr_asset_idempotency_error($label . ' cross-connection fixture should absorb a duplicate completed claim from another connection.');
        }
        if (sr_asset_idempotency_fixture_status($second, $tableName, $completedKey) !== 'completed') {
            sr_asset_idempotency_error($label . ' cross-connection fixture should keep completed claims visible across connections.');
        }
    } finally {
        unset($first, $second);
        if (is_file($path)) {
            unlink($path);
        }
    }
}

function sr_asset_idempotency_check_parallel_claim_fixture(string $tableName, string $label): void
{
    if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
        return;
    }

    $path = tempnam(sys_get_temp_dir(), 'sr_asset_parallel_');
    if (!is_string($path) || $path === '') {
        sr_asset_idempotency_error($label . ' parallel fixture could not create a temporary database.');
        return;
    }

    $resultDir = sys_get_temp_dir() . '/sr_asset_parallel_' . bin2hex(random_bytes(6));
    if (!mkdir($resultDir, 0700)) {
        sr_asset_idempotency_error($label . ' parallel fixture could not create a result directory.');
        if (is_file($path)) {
            unlink($path);
        }
        return;
    }

    $children = [];
    $childCount = 6;
    $dedupeKey = $label . ':parallel';

    try {
        $setup = sr_asset_idempotency_open_sqlite_file($path);
        $setup->exec('CREATE TABLE ' . $tableName . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, dedupe_key TEXT NOT NULL UNIQUE, log_status TEXT NOT NULL, transaction_id INTEGER NOT NULL DEFAULT 0)');
        unset($setup);

        for ($index = 0; $index < $childCount; $index++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                sr_asset_idempotency_error($label . ' parallel fixture could not fork child process.');
                continue;
            }

            if ($pid === 0) {
                $exitCode = 0;
                try {
                    $childPdo = sr_asset_idempotency_open_sqlite_file($path);
                    $childPdo->exec('PRAGMA busy_timeout = 5000');
                    $inserted = sr_asset_idempotency_fixture_insert_placeholder($childPdo, $tableName, $dedupeKey, 'pending') ? 1 : 0;
                    file_put_contents($resultDir . '/child-' . getmypid() . '.txt', (string) $inserted);
                } catch (Throwable $exception) {
                    file_put_contents($resultDir . '/child-' . getmypid() . '.txt', 'error:' . $exception->getMessage());
                    $exitCode = 1;
                }
                exit($exitCode);
            }

            $children[] = $pid;
        }

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            if (!pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
                sr_asset_idempotency_error($label . ' parallel fixture child failed: ' . (string) $pid);
            }
        }

        $insertedCount = 0;
        $resultFiles = glob($resultDir . '/child-*.txt') ?: [];
        foreach ($resultFiles as $resultFile) {
            $result = trim((string) file_get_contents($resultFile));
            if ($result === '1') {
                $insertedCount++;
            } elseif ($result !== '0') {
                sr_asset_idempotency_error($label . ' parallel fixture child returned unexpected result: ' . $result);
            }
        }

        if (count($resultFiles) !== count($children)) {
            sr_asset_idempotency_error($label . ' parallel fixture should collect one result per child.');
        }
        if ($insertedCount !== 1) {
            sr_asset_idempotency_error($label . ' parallel fixture should allow exactly one successful claim, got ' . (string) $insertedCount . '.');
        }

        $verify = sr_asset_idempotency_open_sqlite_file($path);
        if (sr_asset_idempotency_fixture_count($verify, $tableName, $dedupeKey) !== 1) {
            sr_asset_idempotency_error($label . ' parallel fixture should leave exactly one claim row.');
        }
    } finally {
        foreach ($children as $pid) {
            $status = 0;
            pcntl_waitpid($pid, $status, WNOHANG);
        }
        foreach (glob($resultDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($resultDir)) {
            rmdir($resultDir);
        }
        if (is_file($path)) {
            unlink($path);
        }
    }
}

foreach ([
    'modules/content/install.sql' => [
        'dedupe_key VARCHAR(160) NOT NULL',
        'UNIQUE KEY uq_sr_content_asset_access_dedupe (dedupe_key)',
        'UNIQUE KEY uq_sr_content_asset_action_dedupe (dedupe_key)',
        "log_status VARCHAR(20) NOT NULL DEFAULT 'completed'",
    ],
    'modules/community/install.sql' => [
        'dedupe_key VARCHAR(160) NOT NULL',
        'UNIQUE KEY uq_sr_community_asset_logs_dedupe (dedupe_key)',
        "log_status VARCHAR(20) NOT NULL DEFAULT 'completed'",
    ],
] as $file => $markers) {
    sr_asset_idempotency_contains($file, $markers);
}

foreach ([
    'modules/content/helpers/assets.php' => [
        'INSERT IGNORE INTO sr_content_asset_access_logs',
        'INSERT IGNORE INTO sr_content_asset_action_logs',
        'sr_content_asset_log_status_pending()',
        'sr_content_asset_log_status_completed()',
        'sr_content_delete_asset_access_placeholder',
        'sr_content_delete_asset_action_placeholder',
        'sr_content_asset_confirmation_request_token_valid',
    ],
    'modules/community/helpers/assets.php' => [
        '$insertVerb = \'INSERT IGNORE\';',
        '$insertVerb = \'INSERT OR IGNORE\';',
        '$insertVerb . \' INTO sr_community_asset_logs',
        'sr_community_asset_log_status_pending()',
        'sr_community_asset_log_status_completed()',
        'sr_community_delete_asset_log_placeholder',
        'sr_community_asset_confirmation_request_token_valid',
    ],
] as $file => $markers) {
    sr_asset_idempotency_contains($file, $markers);
}

foreach ([
    'modules/content/actions/view.php',
    'modules/content/actions/download.php',
    'modules/community/actions/view.php',
    'modules/community/actions/attachment.php',
] as $assetAction) {
    sr_asset_idempotency_contains($assetAction, [
        "sr_post_string_without_truncation('asset_request_token', 32) ?? ''",
    ]);
    if (str_contains(sr_asset_idempotency_file($assetAction), "sr_post_string('asset_request_token'")) {
        sr_asset_idempotency_error($assetAction . ' must reject overlong asset_request_token instead of truncating it.');
    }
}

sr_asset_idempotency_contains('modules/asset_exchange/actions/account-asset-exchange.php', [
    "sr_post_string_without_truncation('exchange_submit_token', 32) ?? ''",
]);
if (str_contains(sr_asset_idempotency_file('modules/asset_exchange/actions/account-asset-exchange.php'), "sr_post_string('exchange_submit_token'")) {
    sr_asset_idempotency_error('Asset exchange account action must reject overlong exchange_submit_token instead of truncating it.');
}

$contentHelpers = 'modules/content/helpers/assets.php';
foreach ([
    'sr_content_insert_asset_access_placeholder' => [
        'INSERT IGNORE INTO sr_content_asset_access_logs',
        'transaction_id, reference_type',
        'sr_content_asset_log_status_pending()',
        'return $stmt->rowCount() > 0;',
    ],
    'sr_content_update_asset_access_transaction' => [
        'SET transaction_id = :transaction_id,',
        'log_status = :log_status',
        'sr_content_asset_log_status_completed()',
    ],
    'sr_content_delete_asset_access_placeholder' => [
        'DELETE FROM sr_content_asset_access_logs',
        'AND log_status = :log_status',
        'sr_content_asset_log_status_pending()',
    ],
    'sr_content_insert_asset_action_placeholder' => [
        'INSERT IGNORE INTO sr_content_asset_action_logs',
        'sr_content_asset_log_status_pending()',
        'return $stmt->rowCount() > 0;',
    ],
    'sr_content_update_asset_action_transaction' => [
        'SET transaction_id = :transaction_id,',
        'log_status = :log_status',
        'sr_content_asset_log_status_completed()',
    ],
    'sr_content_delete_asset_action_placeholder' => [
        'DELETE FROM sr_content_asset_action_logs',
        'AND log_status = :log_status',
        'sr_content_asset_log_status_pending()',
    ],
] as $functionName => $markers) {
    $body = sr_asset_idempotency_function_body($contentHelpers, $functionName);
    foreach ($markers as $marker) {
        if (!str_contains($body, $marker)) {
            sr_asset_idempotency_error($contentHelpers . ' function ' . $functionName . ' is missing marker: ' . $marker);
        }
    }
}

foreach ([
    'sr_content_charge_view_access_once' => [
        ['sr_content_insert_asset_access_placeholder(', 'sr_content_create_asset_transaction('],
        ['sr_content_create_asset_transaction(', 'sr_content_update_asset_access_transaction('],
        ['rollBack()', 'sr_content_asset_is_retryable_transaction_exception($exception)'],
    ],
    'sr_content_charge_file_download_once' => [
        ['sr_content_insert_asset_access_placeholder(', 'sr_content_create_asset_transaction('],
        ['sr_content_create_asset_transaction(', 'sr_content_update_asset_access_transaction('],
        ['rollBack()', 'sr_content_asset_is_retryable_transaction_exception($exception)'],
    ],
    'sr_content_run_asset_action_once' => [
        ['sr_content_insert_asset_action_placeholder(', 'sr_content_create_asset_transaction('],
        ['sr_content_create_asset_transaction(', 'sr_content_update_asset_action_transaction('],
        ['rollBack()', 'sr_content_asset_is_retryable_transaction_exception($exception)'],
    ],
] as $functionName => $orders) {
    $body = sr_asset_idempotency_function_body($contentHelpers, $functionName);
    foreach ($orders as $order) {
        sr_asset_idempotency_order($contentHelpers . ' function ' . $functionName, $body, $order[0], $order[1]);
    }
}

$communityHelpers = 'modules/community/helpers/assets.php';
foreach ([
    'sr_community_insert_asset_log_placeholder' => [
        '$insertVerb = \'INSERT IGNORE\';',
        '$insertVerb = \'INSERT OR IGNORE\';',
        '$insertVerb . \' INTO sr_community_asset_logs',
        'sr_community_asset_log_status_pending()',
        'return $stmt->rowCount() > 0;',
    ],
    'sr_community_update_asset_log_transaction' => [
        'SET transaction_id = :transaction_id,',
        'log_status = :log_status',
        'sr_community_asset_log_status_completed()',
    ],
    'sr_community_delete_asset_log_placeholder' => [
        'DELETE FROM sr_community_asset_logs',
        'AND log_status = :log_status',
        'sr_community_asset_log_status_pending()',
    ],
] as $functionName => $markers) {
    $body = sr_asset_idempotency_function_body($communityHelpers, $functionName);
    foreach ($markers as $marker) {
        if (!str_contains($body, $marker)) {
            sr_asset_idempotency_error($communityHelpers . ' function ' . $functionName . ' is missing marker: ' . $marker);
        }
    }
}

$communityRunBody = sr_asset_idempotency_function_body($communityHelpers, 'sr_community_run_asset_event_once');
foreach ([
    ['sr_community_insert_asset_log_placeholder(', 'sr_community_create_asset_transaction('],
    ['sr_community_create_asset_transaction(', 'sr_community_update_asset_log_transaction('],
    ['rollBack()', 'sr_community_asset_is_retryable_transaction_exception($exception)'],
] as $order) {
    sr_asset_idempotency_order($communityHelpers . ' function sr_community_run_asset_event_once', $communityRunBody, $order[0], $order[1]);
}

sr_asset_idempotency_contains('docs/verification-status.md', [
    'pending log placeholder',
    'check-asset-idempotency.php',
    'smoke-asset-idempotency-http.php',
    'SR_SMOKE_ALLOW_MUTATION=1',
    '두 PDO 연결 fixture',
    '병렬 프로세스 fixture',
]);
sr_asset_idempotency_contains('docs/smoke-test.md', [
    'pending placeholder',
    '중복 POST',
    'smoke-asset-idempotency-http.php',
    'SR_SMOKE_ALLOW_MUTATION=1',
    'SR_SMOKE_EXPECT_DEDUPE_TABLE',
]);

sr_asset_idempotency_contains('.tools/bin/smoke-asset-idempotency-http.php', [
    'SR_SMOKE_ALLOW_MUTATION',
    'SR_SMOKE_FORM_PATH',
    'SR_SMOKE_POST_PATH',
    'SR_SMOKE_EXPECT_DEDUPE_TABLE',
    'SR_SMOKE_EXPECT_DEDUPE_KEY',
    'curl_multi_init',
    'asset_request_token',
    'This smoke performs mutating duplicate POST requests',
    'Run only against local or staging disposable data',
]);

if (class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    sr_asset_idempotency_check_claim_fixture($pdo, 'sr_fixture_content_asset_access_logs', 'content access');
    sr_asset_idempotency_check_claim_fixture($pdo, 'sr_fixture_content_asset_action_logs', 'content action');
    sr_asset_idempotency_check_claim_fixture($pdo, 'sr_fixture_community_asset_logs', 'community asset');
    sr_asset_idempotency_check_cross_connection_fixture('sr_fixture_content_asset_access_logs', 'content access');
    sr_asset_idempotency_check_cross_connection_fixture('sr_fixture_content_asset_action_logs', 'content action');
    sr_asset_idempotency_check_cross_connection_fixture('sr_fixture_community_asset_logs', 'community asset');
    sr_asset_idempotency_check_parallel_claim_fixture('sr_fixture_content_asset_access_logs', 'content access');
    sr_asset_idempotency_check_parallel_claim_fixture('sr_fixture_content_asset_action_logs', 'content action');
    sr_asset_idempotency_check_parallel_claim_fixture('sr_fixture_community_asset_logs', 'community asset');
} else {
    sr_asset_idempotency_error('PDO sqlite driver is required for asset idempotency claim fixture.');
}

if ($errors !== []) {
    fwrite(STDERR, "asset idempotency checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "asset idempotency checks completed.\n";
