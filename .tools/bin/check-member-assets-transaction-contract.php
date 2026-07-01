#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
define('SR_ROOT', $root);

require_once $root . '/core/helpers.php';

$errors = [];

function sr_member_assets_contract_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_member_assets_contract_function_body(string $file, string $functionName): string
{
    $source = file_get_contents($file);
    if (!is_string($source)) {
        sr_member_assets_contract_error('cannot read helper file: ' . $file);
        return '';
    }

    $pattern = '/function\s+' . preg_quote($functionName, '/') . '\s*\(/';
    if (preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE) !== 1) {
        sr_member_assets_contract_error('transaction function not found in helper file: ' . $functionName . ' in ' . $file);
        return '';
    }

    $start = (int) $matches[0][1];
    $braceStart = strpos($source, '{', $start);
    if ($braceStart === false) {
        sr_member_assets_contract_error('transaction function body is not readable: ' . $functionName . ' in ' . $file);
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

    sr_member_assets_contract_error('transaction function body is not closed: ' . $functionName . ' in ' . $file);
    return '';
}

function sr_member_assets_contract_check_contains(string $file, array $markers): void
{
    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        sr_member_assets_contract_error('cannot read required document: ' . $file);
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            sr_member_assets_contract_error($file . ' must document member asset transaction contract marker: ' . $marker);
        }
    }
}

function sr_member_assets_contract_helper_path(string $moduleKey, array $contract): string
{
    $helpers = (string) ($contract['helpers'] ?? '');
    if ($helpers === '' || preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helpers) !== 1) {
        return '';
    }

    $path = SR_ROOT . '/modules/' . $moduleKey . '/' . $helpers;
    return is_file($path) ? $path : '';
}

foreach (glob('modules/*/member-assets.php') ?: [] as $contractFile) {
    $moduleKey = basename(dirname($contractFile));
    if (preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $moduleKey) !== 1) {
        sr_member_assets_contract_error('member-assets.php module directory must match module_key format: ' . $contractFile);
        continue;
    }

    $contract = require $contractFile;
    if (!is_array($contract)) {
        sr_member_assets_contract_error('member-assets.php must return an array: ' . $contractFile);
        continue;
    }

    $transactionFunction = (string) ($contract['transaction_function'] ?? '');
    $lookupFunction = (string) ($contract['transaction_lookup_function'] ?? '');
    $transactionTable = (string) ($contract['transaction_table'] ?? '');
    $helperPath = sr_member_assets_contract_helper_path($moduleKey, $contract);

    if ($transactionFunction === '') {
        sr_member_assets_contract_error('transaction_function is required: ' . $contractFile);
        continue;
    }
    if ($transactionTable === '') {
        sr_member_assets_contract_error('transaction_table is required for reconciliation: ' . $contractFile);
    }
    if ($lookupFunction === '') {
        sr_member_assets_contract_error('transaction_lookup_function is required for recovery lookup: ' . $contractFile);
    }
    if ($helperPath === '') {
        sr_member_assets_contract_error('valid helpers path is required: ' . $contractFile);
        continue;
    }

    require_once $helperPath;
    if (!function_exists($transactionFunction)) {
        sr_member_assets_contract_error('transaction_function callable does not exist: ' . $transactionFunction . ' in ' . $contractFile);
        continue;
    }
    if ($lookupFunction !== '' && !function_exists($lookupFunction)) {
        sr_member_assets_contract_error('transaction_lookup_function callable does not exist: ' . $lookupFunction . ' in ' . $contractFile);
    }

    $body = sr_member_assets_contract_function_body($helperPath, $transactionFunction);
    if ($body === '') {
        continue;
    }

    foreach ([
        '$startedTransaction = !$pdo->inTransaction();',
        'if ($startedTransaction) {' . "\n" . '        $pdo->beginTransaction();',
        'if ($startedTransaction) {' . "\n" . '            $pdo->commit();',
        'if ($startedTransaction && $pdo->inTransaction()) {' . "\n" . '            $pdo->rollBack();',
    ] as $marker) {
        if (!str_contains($body, $marker)) {
            sr_member_assets_contract_error($transactionFunction . ' must preserve outer PDO transaction participation marker: ' . $marker);
        }
    }

    foreach (['new PDO', 'sr_db('] as $forbiddenMarker) {
        if (str_contains($body, $forbiddenMarker)) {
            sr_member_assets_contract_error($transactionFunction . ' must not open a separate DB connection: ' . $forbiddenMarker);
        }
    }
}

sr_member_assets_contract_check_contains('docs/module-guide.md', [
    '`transaction_function`은 호출자가 이미 시작한 같은 PDO transaction에 동참해야 하며',
    '`member-assets.php`',
    '`transaction_lookup_function`',
]);

sr_member_assets_contract_check_contains('docs/verification-status.md', [
    'check-member-assets-transaction-contract.php',
    'transaction_function',
]);

sr_member_assets_contract_check_contains('docs/risk-register.md', [
    'check-member-assets-transaction-contract.php',
    'R-01',
]);

if ($errors !== []) {
    fwrite(STDERR, "member asset transaction contract checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "member asset transaction contract checks completed.\n";
