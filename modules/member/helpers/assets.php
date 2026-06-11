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
        $transactionLookupFunction = (string) ($contract['transaction_lookup_function'] ?? '');
        if (!function_exists($balanceFunction) || !function_exists($transactionFunction)) {
            continue;
        }
        if ($transactionLookupFunction !== '' && !function_exists($transactionLookupFunction)) {
            $transactionLookupFunction = '';
        }

        $assets[$moduleKey] = [
            'label' => $pdo instanceof PDO ? sr_member_asset_contract_label($pdo, $moduleKey, $contract) : (string) ($contract['label'] ?? $moduleKey),
            'unit_label' => $pdo instanceof PDO ? sr_member_asset_contract_unit_label($pdo, $contract) : (string) ($contract['unit_label'] ?? ''),
            'module_key' => $moduleKey,
            'balance_function' => $balanceFunction,
            'transaction_function' => $transactionFunction,
            'transaction_lookup_function' => $transactionLookupFunction,
            'transaction_table' => (string) ($contract['transaction_table'] ?? ''),
            'use_type' => (string) ($contract['use_type'] ?? 'use'),
            'credit_type' => (string) ($contract['credit_type'] ?? 'grant'),
            'refund_type' => (string) ($contract['refund_type'] ?? 'refund'),
            'deduction_order' => (int) ($contract['deduction_order'] ?? 100),
            'purchase_power' => sr_member_asset_purchase_power_from_contract($pdo, $contract),
        ];
    }

    uasort($assets, static function (array $left, array $right): int {
        $orderCompare = ((int) ($left['deduction_order'] ?? 100)) <=> ((int) ($right['deduction_order'] ?? 100));
        return $orderCompare !== 0 ? $orderCompare : strcmp((string) ($left['module_key'] ?? ''), (string) ($right['module_key'] ?? ''));
    });

    return $assets;
}

function sr_member_asset_purchase_power_from_contract(?PDO $pdo, array $contract): array
{
    $defaultCurrency = function_exists('sr_site_default_currency') ? sr_site_default_currency($pdo) : 'KRW';
    $purchasePower = is_array($contract['purchase_power'] ?? null) ? $contract['purchase_power'] : [];
    $assetUnits = (int) ($purchasePower['asset_units'] ?? 1);
    $settlementUnits = (int) ($purchasePower['settlement_units'] ?? 1);
    $settlementCurrency = function_exists('sr_normalize_currency_code')
        ? sr_normalize_currency_code((string) ($purchasePower['settlement_currency'] ?? $defaultCurrency))
        : strtoupper(trim((string) ($purchasePower['settlement_currency'] ?? $defaultCurrency)));

    if ($assetUnits < 1) {
        $assetUnits = 1;
    }
    if ($settlementUnits < 1) {
        $settlementUnits = 1;
    }
    if (function_exists('sr_currency_is_known') && !sr_currency_is_known($settlementCurrency)) {
        $settlementCurrency = $defaultCurrency;
    }

    return [
        'asset_units' => $assetUnits,
        'settlement_units' => $settlementUnits,
        'settlement_currency' => $settlementCurrency,
    ];
}

function sr_member_asset_purchase_power_snapshot(array $asset, string $settlementCurrency, ?PDO $pdo = null): array
{
    $purchasePower = is_array($asset['purchase_power'] ?? null) ? $asset['purchase_power'] : sr_member_asset_purchase_power_from_contract($pdo, []);
    $currency = function_exists('sr_normalize_currency_code') ? sr_normalize_currency_code($settlementCurrency) : strtoupper(trim($settlementCurrency));
    $minUnit = function_exists('sr_currency_min_unit') ? sr_currency_min_unit($currency) : 1;

    return [
        'asset_units' => max(1, (int) ($purchasePower['asset_units'] ?? 1)),
        'settlement_units' => max(1, (int) ($purchasePower['settlement_units'] ?? 1)),
        'settlement_currency' => $currency,
        'currency_min_unit' => $minUnit > 0 ? $minUnit : 1,
        'policy_version' => 'asset_settlement_v1',
        'asset_label' => (string) ($asset['label'] ?? ''),
        'asset_unit_label' => (string) ($asset['unit_label'] ?? ''),
    ];
}

function sr_member_asset_settlement_step(int $assetUnits, int $settlementUnits): int
{
    $assetUnits = max(1, $assetUnits);
    $settlementUnits = max(1, $settlementUnits);
    $a = $assetUnits;
    $b = $settlementUnits;
    while ($b !== 0) {
        $tmp = $a % $b;
        $a = $b;
        $b = $tmp;
    }

    return max(1, intdiv($settlementUnits, max(1, $a)));
}

function sr_member_asset_settlement_plan(PDO $pdo, array $assets, callable $balanceFunction, array $assetModules, int $settlementAmount, string $settlementCurrency): array
{
    $settlementAmount = max(0, $settlementAmount);
    $settlementCurrency = function_exists('sr_normalize_currency_code') ? sr_normalize_currency_code($settlementCurrency) : strtoupper(trim($settlementCurrency));
    $minUnit = function_exists('sr_currency_min_unit') ? sr_currency_min_unit($settlementCurrency) : 1;
    if ($settlementAmount < 1) {
        return ['ok' => true, 'allocations' => [], 'settlement_amount' => 0, 'settlement_currency' => $settlementCurrency, 'message' => ''];
    }
    if ($minUnit < 1) {
        return ['ok' => false, 'allocations' => [], 'settlement_amount' => $settlementAmount, 'settlement_currency' => $settlementCurrency, 'message' => 'Unknown settlement currency.'];
    }

    $remaining = $settlementAmount;
    $allocations = [];
    foreach ($assetModules as $assetModule) {
        $assetModule = (string) $assetModule;
        if (!isset($assets[$assetModule])) {
            continue;
        }

        $asset = $assets[$assetModule];
        $purchasePower = is_array($asset['purchase_power'] ?? null) ? $asset['purchase_power'] : [];
        $assetUnits = max(1, (int) ($purchasePower['asset_units'] ?? 1));
        $settlementUnits = max(1, (int) ($purchasePower['settlement_units'] ?? 1));
        $assetCurrency = function_exists('sr_normalize_currency_code')
            ? sr_normalize_currency_code((string) ($purchasePower['settlement_currency'] ?? $settlementCurrency))
            : strtoupper(trim((string) ($purchasePower['settlement_currency'] ?? $settlementCurrency)));
        if ($assetCurrency !== $settlementCurrency) {
            return ['ok' => false, 'allocations' => [], 'settlement_amount' => $settlementAmount, 'settlement_currency' => $settlementCurrency, 'message' => 'Asset settlement currency does not match price currency.'];
        }

        $balance = max(0, (int) $balanceFunction($pdo, $assetModule));
        if ($balance < 1) {
            continue;
        }

        $maxSettlement = intdiv($balance * $settlementUnits, $assetUnits);
        if ($maxSettlement < 1) {
            continue;
        }

        $settlementStep = sr_member_asset_settlement_step($assetUnits, $settlementUnits);
        $settlementUse = min($remaining, $maxSettlement);
        $settlementUse -= $settlementUse % $settlementStep;
        if ($settlementUse < 1) {
            continue;
        }

        $assetAmountNumerator = $settlementUse * $assetUnits;
        if ($assetAmountNumerator % $settlementUnits !== 0) {
            continue;
        }
        $assetAmount = intdiv($assetAmountNumerator, $settlementUnits);
        if ($assetAmount < 1 || $assetAmount > $balance) {
            continue;
        }

        $snapshot = sr_member_asset_purchase_power_snapshot($asset, $settlementCurrency, $pdo);
        $snapshot['balance_snapshot'] = $balance;
        $snapshot['settlement_step'] = $settlementStep;
        $allocations[] = [
            'asset_module' => $assetModule,
            'amount' => $assetAmount,
            'asset_amount' => $assetAmount,
            'settlement_amount' => $settlementUse,
            'settlement_currency' => $settlementCurrency,
            'purchase_power_snapshot' => $snapshot,
        ];
        $remaining -= $settlementUse;
        if ($remaining === 0) {
            break;
        }
    }

    return [
        'ok' => $remaining === 0,
        'allocations' => $remaining === 0 ? $allocations : [],
        'settlement_amount' => $settlementAmount,
        'settlement_currency' => $settlementCurrency,
        'remaining_settlement_amount' => $remaining,
        'message' => $remaining === 0 ? '' : 'Settlement amount cannot be covered exactly.',
    ];
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
