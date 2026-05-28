<?php

declare(strict_types=1);

function sr_member_asset_contract_module_keys(?PDO $pdo, string $contractFile): array
{
    if ($pdo instanceof PDO) {
        return array_keys(sr_enabled_module_contract_files($pdo, $contractFile));
    }

    $moduleKeys = [];
    foreach (glob(SR_ROOT . '/modules/*/' . $contractFile) ?: [] as $file) {
        $moduleKey = basename(dirname($file));
        if (preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $moduleKey) === 1) {
            $moduleKeys[] = $moduleKey;
        }
    }

    sort($moduleKeys);
    return $moduleKeys;
}

function sr_member_asset_contract_helper_path(string $moduleKey, array $contract): string
{
    $helpers = (string) ($contract['helpers'] ?? '');
    if ($helpers === '' || preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helpers) !== 1) {
        return '';
    }

    $path = SR_ROOT . '/modules/' . $moduleKey . '/' . $helpers;
    return is_file($path) ? $path : '';
}

function sr_member_asset_contract_label(PDO $pdo, string $moduleKey, array $contract): string
{
    $labelFunction = (string) ($contract['label_function'] ?? '');
    if ($labelFunction !== '' && function_exists($labelFunction)) {
        return (string) $labelFunction($pdo);
    }

    $label = trim((string) ($contract['label'] ?? ''));
    return $label !== '' ? $label : $moduleKey;
}

function sr_member_asset_contract_unit_label(PDO $pdo, array $contract): string
{
    $unitFunction = (string) ($contract['unit_function'] ?? '');
    if ($unitFunction !== '' && function_exists($unitFunction)) {
        return (string) $unitFunction($pdo);
    }

    return (string) ($contract['unit_label'] ?? '');
}

function sr_member_asset_contracts(?PDO $pdo, string $contractFile): array
{
    $contracts = [];
    foreach (sr_member_asset_contract_module_keys($pdo, $contractFile) as $moduleKey) {
        $file = SR_ROOT . '/modules/' . $moduleKey . '/' . $contractFile;
        $contract = is_file($file) ? require $file : null;
        if (!is_array($contract)) {
            continue;
        }

        $helperPath = sr_member_asset_contract_helper_path($moduleKey, $contract);
        if ($helperPath !== '') {
            require_once $helperPath;
        }

        $contracts[$moduleKey] = $contract;
    }

    return $contracts;
}

function sr_member_ledger_asset_definitions(?PDO $pdo = null): array
{
    $assets = [];
    foreach (sr_member_asset_contracts($pdo, 'member-assets.php') as $moduleKey => $contract) {
        $balanceFunction = (string) ($contract['balance_function'] ?? '');
        $transactionFunction = (string) ($contract['transaction_function'] ?? '');
        if (!function_exists($balanceFunction) || !function_exists($transactionFunction)) {
            continue;
        }

        $assets[$moduleKey] = [
            'label' => $pdo instanceof PDO ? sr_member_asset_contract_label($pdo, $moduleKey, $contract) : (string) ($contract['label'] ?? $moduleKey),
            'unit_label' => $pdo instanceof PDO ? sr_member_asset_contract_unit_label($pdo, $contract) : (string) ($contract['unit_label'] ?? ''),
            'module_key' => $moduleKey,
            'balance_function' => $balanceFunction,
            'transaction_function' => $transactionFunction,
            'transaction_table' => (string) ($contract['transaction_table'] ?? ''),
            'use_type' => (string) ($contract['use_type'] ?? 'use'),
            'credit_type' => (string) ($contract['credit_type'] ?? 'grant'),
            'refund_type' => (string) ($contract['refund_type'] ?? 'refund'),
            'deduction_order' => (int) ($contract['deduction_order'] ?? 100),
        ];
    }

    uasort($assets, static function (array $left, array $right): int {
        $orderCompare = ((int) ($left['deduction_order'] ?? 100)) <=> ((int) ($right['deduction_order'] ?? 100));
        return $orderCompare !== 0 ? $orderCompare : strcmp((string) ($left['module_key'] ?? ''), (string) ($right['module_key'] ?? ''));
    });

    return $assets;
}

function sr_member_withdrawal_asset_contract_definitions(PDO $pdo): array
{
    $assets = [];
    foreach (sr_member_asset_contracts($pdo, 'member-withdrawal-assets.php') as $moduleKey => $contract) {
        $balanceFunction = (string) ($contract['balance_function'] ?? '');
        if (!function_exists($balanceFunction)) {
            continue;
        }

        $processFunction = (string) ($contract['process_function'] ?? '');
        $transactionFunction = (string) ($contract['transaction_function'] ?? '');
        if ($processFunction !== '' && !function_exists($processFunction)) {
            continue;
        }
        if ($processFunction === '' && !function_exists($transactionFunction)) {
            continue;
        }

        $assets[$moduleKey] = [
            'asset_key' => $moduleKey,
            'label' => sr_member_asset_contract_label($pdo, $moduleKey, $contract),
            'unit_label' => sr_member_asset_contract_unit_label($pdo, $contract),
            'ledger_label' => (string) ($contract['ledger_label'] ?? $moduleKey),
            'balance_table' => (string) ($contract['balance_table'] ?? ''),
            'transaction_table' => (string) ($contract['transaction_table'] ?? ''),
            'balance_function' => $balanceFunction,
            'transaction_function' => $transactionFunction,
            'process_function' => $processFunction,
            'transaction_type' => (string) ($contract['transaction_type'] ?? 'expire'),
            'process_label' => (string) ($contract['process_label'] ?? '소멸'),
            'ledger_process_label' => (string) ($contract['ledger_process_label'] ?? 'expire'),
            'sort_order' => (int) ($contract['sort_order'] ?? 100),
        ];
    }

    uasort($assets, static function (array $left, array $right): int {
        $orderCompare = ((int) ($left['sort_order'] ?? 100)) <=> ((int) ($right['sort_order'] ?? 100));
        return $orderCompare !== 0 ? $orderCompare : strcmp((string) ($left['asset_key'] ?? ''), (string) ($right['asset_key'] ?? ''));
    });

    return $assets;
}
