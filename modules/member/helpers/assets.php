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
        if (preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $moduleKey) === 1) {
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
        try {
            $label = trim((string) $labelFunction($pdo));
            if ($label !== '') {
                return $label;
            }
        } catch (Throwable) {
        }
    }

    $label = trim((string) ($contract['label'] ?? ''));
    return $label !== '' ? $label : $moduleKey;
}

function sr_member_asset_contract_unit_label(PDO $pdo, array $contract): string
{
    $unitFunction = (string) ($contract['unit_function'] ?? '');
    if ($unitFunction !== '' && function_exists($unitFunction)) {
        try {
            return (string) $unitFunction($pdo);
        } catch (Throwable) {
        }
    }

    return (string) ($contract['unit_label'] ?? '');
}

function sr_member_asset_contract_available(PDO $pdo, array $contract): bool
{
    $availableFunction = (string) ($contract['available_function'] ?? '');
    if ($availableFunction === '') {
        return true;
    }
    if (!function_exists($availableFunction)) {
        return false;
    }

    return (bool) $availableFunction($pdo);
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
        if ($pdo instanceof PDO && !sr_member_asset_contract_available($pdo, $contract)) {
            continue;
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
    $purchasePower = is_array($contract['purchase_power'] ?? null) ? $contract['purchase_power'] : [];
    $assetUnits = (int) ($purchasePower['asset_units'] ?? 1);
    $settlementUnits = (int) ($purchasePower['settlement_units'] ?? 1);
    $settlementCurrencyValue = trim((string) ($purchasePower['settlement_currency'] ?? ''));
    if ($settlementCurrencyValue === '') {
        try {
            $settlementCurrencyValue = function_exists('sr_site_default_currency') ? sr_site_default_currency($pdo) : 'KRW';
        } catch (Throwable) {
            $settlementCurrencyValue = 'KRW';
        }
    }
    $settlementCurrency = function_exists('sr_normalize_currency_code')
        ? sr_normalize_currency_code($settlementCurrencyValue)
        : strtoupper(trim($settlementCurrencyValue));

    if ($assetUnits < 1) {
        $assetUnits = 1;
    }
    if ($settlementUnits < 1) {
        $settlementUnits = 1;
    }
    if (function_exists('sr_currency_is_known') && !sr_currency_is_known($settlementCurrency)) {
        throw new InvalidArgumentException('Unknown asset purchase power settlement currency.');
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
        'snapshot_schema_version' => 'asset_settlement_snapshot_v1',
        'rounding_policy_version' => 'asset_settlement_rounding_v1',
        'asset_label' => (string) ($asset['label'] ?? ''),
        'asset_unit_label' => (string) ($asset['unit_label'] ?? ''),
    ];
}

function sr_member_asset_int_gcd(int $left, int $right): int
{
    $left = abs($left);
    $right = abs($right);
    while ($right !== 0) {
        $tmp = $left % $right;
        $left = $right;
        $right = $tmp;
    }

    return max(1, $left);
}

function sr_member_asset_int_lcm(int $left, int $right): int
{
    $left = max(1, abs($left));
    $right = max(1, abs($right));

    return intdiv($left, sr_member_asset_int_gcd($left, $right)) * $right;
}

function sr_member_asset_ceil_division(int $numerator, int $denominator): int
{
    $denominator = max(1, $denominator);
    if ($numerator <= 0) {
        return 0;
    }

    return intdiv($numerator + $denominator - 1, $denominator);
}

function sr_member_asset_mod_inverse(int $value, int $mod): ?int
{
    $mod = abs($mod);
    if ($mod <= 1) {
        return 0;
    }

    $value %= $mod;
    if ($value < 0) {
        $value += $mod;
    }

    $oldR = $value;
    $r = $mod;
    $oldS = 1;
    $s = 0;
    while ($r !== 0) {
        $quotient = intdiv($oldR, $r);
        [$oldR, $r] = [$r, $oldR - ($quotient * $r)];
        [$oldS, $s] = [$s, $oldS - ($quotient * $s)];
    }
    if ($oldR !== 1) {
        return null;
    }

    $inverse = $oldS % $mod;
    return $inverse < 0 ? $inverse + $mod : $inverse;
}

function sr_member_asset_largest_congruence_value(int $max, int $lower, int $coefficient, int $target, int $mod): ?int
{
    if ($max < $lower) {
        return null;
    }
    if ($mod <= 1) {
        return $max;
    }

    $gcd = sr_member_asset_int_gcd($coefficient, $mod);
    if ($target % $gcd !== 0) {
        return null;
    }

    $reducedMod = intdiv($mod, $gcd);
    if ($reducedMod <= 1) {
        return $max;
    }

    $inverse = sr_member_asset_mod_inverse(intdiv($coefficient, $gcd), $reducedMod);
    if ($inverse === null) {
        return null;
    }

    $residue = ((intdiv($target, $gcd) % $reducedMod) * $inverse) % $reducedMod;
    if ($residue < 0) {
        $residue += $reducedMod;
    }
    if ($residue > $max) {
        return null;
    }

    $value = $residue + (intdiv($max - $residue, $reducedMod) * $reducedMod);
    return $value >= $lower ? $value : null;
}

function sr_member_asset_settlement_step(int $assetUnits, int $settlementUnits): int
{
    $assetUnits = max(1, $assetUnits);
    $settlementUnits = max(1, $settlementUnits);

    return max(1, intdiv($settlementUnits, sr_member_asset_int_gcd($assetUnits, $settlementUnits)));
}

function sr_member_asset_settlement_plan(PDO $pdo, array $assets, callable $balanceFunction, array $assetModules, int $settlementAmount, string $settlementCurrency): array
{
    $settlementAmount = max(0, $settlementAmount);
    $settlementCurrency = function_exists('sr_normalize_currency_code') ? sr_normalize_currency_code($settlementCurrency) : strtoupper(trim($settlementCurrency));
    $minUnit = function_exists('sr_currency_min_unit') ? sr_currency_min_unit($settlementCurrency) : 1;
    if ($minUnit < 1) {
        return ['ok' => false, 'allocations' => [], 'settlement_amount' => $settlementAmount, 'settlement_currency' => $settlementCurrency, 'message' => 'Unknown settlement currency.'];
    }
    if ($settlementAmount < 1) {
        return ['ok' => true, 'allocations' => [], 'settlement_amount' => 0, 'settlement_currency' => $settlementCurrency, 'message' => ''];
    }

    $candidates = [];
    $denominator = 1;
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
        if (function_exists('sr_currency_min_unit') && sr_currency_min_unit($assetCurrency) < 1) {
            return ['ok' => false, 'allocations' => [], 'settlement_amount' => $settlementAmount, 'settlement_currency' => $settlementCurrency, 'message' => 'Unknown asset settlement currency.'];
        }
        if ($assetCurrency !== $settlementCurrency) {
            return ['ok' => false, 'allocations' => [], 'settlement_amount' => $settlementAmount, 'settlement_currency' => $settlementCurrency, 'message' => 'Asset settlement currency does not match price currency.'];
        }

        $balance = max(0, (int) $balanceFunction($pdo, $assetModule));
        if ($balance < 1) {
            continue;
        }

        $denominator = sr_member_asset_int_lcm($denominator, $assetUnits);
        $candidates[] = [
            'asset_module' => $assetModule,
            'asset' => $asset,
            'asset_units' => $assetUnits,
            'settlement_units' => $settlementUnits,
            'balance' => $balance,
        ];
    }

    if ($candidates === []) {
        return [
            'ok' => false,
            'allocations' => [],
            'settlement_amount' => $settlementAmount,
            'settlement_currency' => $settlementCurrency,
            'remaining_settlement_amount' => $settlementAmount,
            'message' => 'Settlement amount cannot be covered exactly.',
        ];
    }

    foreach ($candidates as $index => $candidate) {
        $candidates[$index]['unit_numerator'] = (int) $candidate['settlement_units'] * intdiv($denominator, (int) $candidate['asset_units']);
    }

    $candidateCount = count($candidates);
    $suffixCapacity = array_fill(0, $candidateCount + 1, 0);
    $suffixGcd = array_fill(0, $candidateCount + 1, 0);
    for ($index = $candidateCount - 1; $index >= 0; $index--) {
        $unitNumerator = max(1, (int) ($candidates[$index]['unit_numerator'] ?? 1));
        $suffixCapacity[$index] = $suffixCapacity[$index + 1] + ((int) ($candidates[$index]['balance'] ?? 0) * $unitNumerator);
        $suffixGcd[$index] = $suffixGcd[$index + 1] > 0 ? sr_member_asset_int_gcd($unitNumerator, $suffixGcd[$index + 1]) : $unitNumerator;
    }

    $targetNumerator = $settlementAmount * $denominator;
    $solveFrom = static function (int $index, int $remainingNumerator) use (&$solveFrom, $candidates, $candidateCount, $suffixCapacity, $suffixGcd): ?array {
        if ($remainingNumerator === 0) {
            return [];
        }
        if ($remainingNumerator < 0 || $index >= $candidateCount) {
            return null;
        }

        $unitNumerator = max(1, (int) ($candidates[$index]['unit_numerator'] ?? 1));
        $balance = max(0, (int) ($candidates[$index]['balance'] ?? 0));
        $maxAmount = min($balance, intdiv($remainingNumerator, $unitNumerator));
        if ($index === $candidateCount - 1) {
            if ($remainingNumerator % $unitNumerator !== 0) {
                return null;
            }
            $amount = intdiv($remainingNumerator, $unitNumerator);
            if ($amount < 0 || $amount > $maxAmount) {
                return null;
            }

            return $amount > 0 ? [['index' => $index, 'amount' => $amount]] : [];
        }

        if ($index === $candidateCount - 2) {
            $rightUnit = max(1, (int) ($candidates[$index + 1]['unit_numerator'] ?? 1));
            $rightBalance = max(0, (int) ($candidates[$index + 1]['balance'] ?? 0));
            $lowerAmount = sr_member_asset_ceil_division($remainingNumerator - ($rightBalance * $rightUnit), $unitNumerator);
            $amount = sr_member_asset_largest_congruence_value($maxAmount, $lowerAmount, $unitNumerator, $remainingNumerator, $rightUnit);
            if ($amount === null) {
                return null;
            }

            $rightRemaining = $remainingNumerator - ($amount * $unitNumerator);
            if ($rightRemaining < 0 || $rightRemaining % $rightUnit !== 0) {
                return null;
            }
            $rightAmount = intdiv($rightRemaining, $rightUnit);
            if ($rightAmount < 0 || $rightAmount > $rightBalance) {
                return null;
            }

            $result = [];
            if ($amount > 0) {
                $result[] = ['index' => $index, 'amount' => $amount];
            }
            if ($rightAmount > 0) {
                $result[] = ['index' => $index + 1, 'amount' => $rightAmount];
            }

            return $result;
        }

        $lowerAmount = sr_member_asset_ceil_division($remainingNumerator - $suffixCapacity[$index + 1], $unitNumerator);
        $nextGcd = max(1, (int) ($suffixGcd[$index + 1] ?? 1));
        $amount = sr_member_asset_largest_congruence_value($maxAmount, $lowerAmount, $unitNumerator, $remainingNumerator, $nextGcd);
        $step = intdiv($nextGcd, sr_member_asset_int_gcd($unitNumerator, $nextGcd));
        $step = max(1, $step);
        while ($amount !== null && $amount >= $lowerAmount) {
            $tail = $solveFrom($index + 1, $remainingNumerator - ($amount * $unitNumerator));
            if ($tail !== null) {
                return $amount > 0 ? array_merge([['index' => $index, 'amount' => $amount]], $tail) : $tail;
            }
            $amount -= $step;
        }

        return null;
    };

    $solution = $solveFrom(0, $targetNumerator);
    if ($solution === null) {
        $coveredNumerator = 0;
        foreach ($candidates as $candidate) {
            $unitNumerator = max(1, (int) ($candidate['unit_numerator'] ?? 1));
            $amount = min((int) ($candidate['balance'] ?? 0), intdiv($targetNumerator - $coveredNumerator, $unitNumerator));
            if ($amount > 0) {
                $coveredNumerator += $amount * $unitNumerator;
            }
            if ($coveredNumerator >= $targetNumerator) {
                break;
            }
        }
        $remainingSettlementAmount = max(0, $settlementAmount - intdiv($coveredNumerator, $denominator));

        return [
            'ok' => false,
            'allocations' => [],
            'settlement_amount' => $settlementAmount,
            'settlement_currency' => $settlementCurrency,
            'remaining_settlement_amount' => $remainingSettlementAmount,
            'message' => 'Settlement amount cannot be covered exactly.',
        ];
    }

    $allocations = [];
    $cumulativeNumerator = 0;
    $assignedSettlementAmount = 0;
    foreach ($solution as $solutionRow) {
        $candidate = $candidates[(int) ($solutionRow['index'] ?? 0)] ?? null;
        if (!is_array($candidate)) {
            continue;
        }

        $assetAmount = (int) ($solutionRow['amount'] ?? 0);
        if ($assetAmount < 1) {
            continue;
        }

        $unitNumerator = max(1, (int) ($candidate['unit_numerator'] ?? 1));
        $allocationNumerator = $assetAmount * $unitNumerator;
        $cumulativeNumerator += $allocationNumerator;
        $coveredSettlementAmount = intdiv($cumulativeNumerator, $denominator);
        $settlementUse = $coveredSettlementAmount - $assignedSettlementAmount;
        $assignedSettlementAmount = $coveredSettlementAmount;

        $asset = is_array($candidate['asset'] ?? null) ? $candidate['asset'] : [];
        $snapshot = sr_member_asset_purchase_power_snapshot($asset, $settlementCurrency, $pdo);
        $snapshot['balance_snapshot'] = max(0, (int) ($candidate['balance'] ?? 0));
        $snapshot['settlement_step'] = sr_member_asset_settlement_step((int) ($candidate['asset_units'] ?? 1), (int) ($candidate['settlement_units'] ?? 1));
        $snapshot['settlement_numerator'] = $allocationNumerator;
        $snapshot['settlement_denominator'] = $denominator;
        $snapshot['cumulative_settlement_numerator'] = $cumulativeNumerator;
        $snapshot['fractional_carry_numerator'] = $cumulativeNumerator % $denominator;
        $snapshot['fractional_carry_denominator'] = $denominator;
        $allocations[] = [
            'asset_module' => (string) ($candidate['asset_module'] ?? ''),
            'amount' => $assetAmount,
            'asset_amount' => $assetAmount,
            'settlement_amount' => $settlementUse,
            'settlement_currency' => $settlementCurrency,
            'purchase_power_snapshot' => $snapshot,
        ];
    }

    return [
        'ok' => $assignedSettlementAmount === $settlementAmount,
        'allocations' => $assignedSettlementAmount === $settlementAmount ? $allocations : [],
        'settlement_amount' => $settlementAmount,
        'settlement_currency' => $settlementCurrency,
        'remaining_settlement_amount' => max(0, $settlementAmount - $assignedSettlementAmount),
        'message' => $assignedSettlementAmount === $settlementAmount ? '' : 'Settlement amount cannot be covered exactly.',
    ];
}

function sr_member_asset_settlement_exchange_suggestion(PDO $pdo, array $assets, callable $balanceFunction, array $assetModules, int $accountId, int $settlementAmount, string $settlementCurrency): array
{
    if ($accountId < 1 || $settlementAmount < 1 || $assetModules === [] || !sr_module_enabled($pdo, 'asset_exchange')) {
        return [];
    }

    $exchangeHelper = SR_ROOT . '/modules/asset_exchange/helpers.php';
    if (!is_file($exchangeHelper)) {
        return [];
    }
    require_once $exchangeHelper;
    if (!function_exists('sr_asset_exchange_enabled') || !sr_asset_exchange_enabled($pdo)) {
        return [];
    }

    $initialPlan = sr_member_asset_settlement_plan($pdo, $assets, $balanceFunction, $assetModules, $settlementAmount, $settlementCurrency);
    if (!empty($initialPlan['ok'])) {
        return [];
    }
    $planMessage = (string) ($initialPlan['message'] ?? '');
    if (str_contains($planMessage, 'currency')) {
        return [];
    }

    $selected = array_fill_keys(array_map('strval', $assetModules), true);
    $baseBalances = [];
    foreach ($assetModules as $assetModule) {
        $assetModule = (string) $assetModule;
        $baseBalances[$assetModule] = max(0, (int) $balanceFunction($pdo, $assetModule));
    }

    $bestSuggestion = [];
    foreach (sr_asset_exchange_policies($pdo, true) as $policy) {
        $fromModuleKey = (string) ($policy['from_module_key'] ?? '');
        $toModuleKey = (string) ($policy['to_module_key'] ?? '');
        if ($fromModuleKey === $toModuleKey || !isset($selected[$fromModuleKey], $selected[$toModuleKey], $assets[$fromModuleKey], $assets[$toModuleKey])) {
            continue;
        }

        $fromBalance = (int) ($baseBalances[$fromModuleKey] ?? 0);
        $minAmount = max(1, (int) ($policy['min_amount'] ?? 1));
        $maxAmount = (int) ($policy['max_amount'] ?? 0);
        $requestMax = $maxAmount > 0 ? min($fromBalance, $maxAmount) : $fromBalance;
        if ($requestMax < $minAmount) {
            continue;
        }

        foreach (sr_member_asset_settlement_exchange_probe_amounts($assets, $policy, $baseBalances, $settlementAmount, $settlementCurrency, $minAmount, $requestMax) as $requestAmount) {
            try {
                $quote = sr_asset_exchange_quote($pdo, $policy, $accountId, $requestAmount);
            } catch (Throwable) {
                continue;
            }

            $overlayBalances = $baseBalances;
            $overlayBalances[$fromModuleKey] = max(0, (int) ($overlayBalances[$fromModuleKey] ?? 0) - $requestAmount);
            $overlayBalances[$toModuleKey] = max(0, (int) ($overlayBalances[$toModuleKey] ?? 0) + (int) ($quote['deposit_amount'] ?? 0));
            $overlayPlan = sr_member_asset_settlement_plan(
                $pdo,
                $assets,
                static function (PDO $pdo, string $assetModule) use ($overlayBalances): int {
                    return (int) ($overlayBalances[$assetModule] ?? 0);
                },
                $assetModules,
                $settlementAmount,
                $settlementCurrency
            );
            if (empty($overlayPlan['ok'])) {
                continue;
            }

            $suggestion = [
                'policy' => $policy,
                'policy_id' => (int) ($policy['id'] ?? 0),
                'from_module_key' => $fromModuleKey,
                'to_module_key' => $toModuleKey,
                'request_amount' => $requestAmount,
                'deposit_amount' => (int) ($quote['deposit_amount'] ?? 0),
                'deposit_before_fee' => (int) ($quote['deposit_before_fee'] ?? 0),
                'fee_amount' => (int) ($quote['fee_amount'] ?? 0),
                'quote' => $quote,
                'allocations' => (array) ($overlayPlan['allocations'] ?? []),
            ];
            if ($bestSuggestion === [] || $requestAmount < (int) ($bestSuggestion['request_amount'] ?? PHP_INT_MAX)) {
                $bestSuggestion = $suggestion;
            }
            break;
        }
    }

    return $bestSuggestion;
}

function sr_member_asset_settlement_exchange_probe_amounts(array $assets, array $policy, array $baseBalances, int $settlementAmount, string $settlementCurrency, int $minAmount, int $requestMax): array
{
    $amounts = [];
    $addRange = static function (int $center, int $radius) use (&$amounts, $minAmount, $requestMax): void {
        $from = max($minAmount, $center - $radius);
        $to = min($requestMax, $center + $radius);
        for ($amount = $from; $amount <= $to; $amount++) {
            $amounts[$amount] = true;
        }
    };

    $addRange($minAmount, 80);
    $addRange($requestMax, 20);

    $toModuleKey = (string) ($policy['to_module_key'] ?? '');
    $toAsset = is_array($assets[$toModuleKey] ?? null) ? $assets[$toModuleKey] : [];
    $purchasePower = is_array($toAsset['purchase_power'] ?? null) ? $toAsset['purchase_power'] : [];
    $assetUnits = max(1, (int) ($purchasePower['asset_units'] ?? 1));
    $settlementUnits = max(1, (int) ($purchasePower['settlement_units'] ?? 1));
    $assetCurrency = function_exists('sr_normalize_currency_code')
        ? sr_normalize_currency_code((string) ($purchasePower['settlement_currency'] ?? $settlementCurrency))
        : strtoupper(trim((string) ($purchasePower['settlement_currency'] ?? $settlementCurrency)));
    if ($assetCurrency === $settlementCurrency) {
        $targetAssetAmount = sr_member_asset_ceil_division($settlementAmount * $assetUnits, $settlementUnits);
        $neededDeposit = max(1, $targetAssetAmount - (int) ($baseBalances[$toModuleKey] ?? 0));
        $rateNumerator = max(1, (int) ($policy['rate_numerator'] ?? 1));
        $rateDenominator = max(1, (int) ($policy['rate_denominator'] ?? 1));
        $estimatedRequest = sr_member_asset_ceil_division($neededDeposit * $rateDenominator, $rateNumerator);
        $addRange($estimatedRequest, 160);
    }

    $result = array_keys($amounts);
    sort($result);

    return array_slice($result, 0, 400);
}

function sr_member_asset_settlement_execute_exchange_suggestion(PDO $pdo, array $suggestion, int $accountId): int
{
    if ($accountId < 1 || !is_array($suggestion['policy'] ?? null)) {
        throw new RuntimeException('자동 환전 제안이 올바르지 않습니다.');
    }

    $exchangeHelper = SR_ROOT . '/modules/asset_exchange/helpers.php';
    if (!is_file($exchangeHelper)) {
        throw new RuntimeException('환전 helper를 찾을 수 없습니다.');
    }
    require_once $exchangeHelper;

    return sr_asset_exchange_execute($pdo, (array) $suggestion['policy'], $accountId, max(1, (int) ($suggestion['request_amount'] ?? 0)), null);
}

function sr_member_asset_settlement_exchange_message(PDO $pdo, array $assets, array $suggestion, string $baseMessage): string
{
    if ($suggestion === []) {
        return $baseMessage;
    }

    $fromModuleKey = (string) ($suggestion['from_module_key'] ?? '');
    $toModuleKey = (string) ($suggestion['to_module_key'] ?? '');
    $fromAsset = is_array($assets[$fromModuleKey] ?? null) ? $assets[$fromModuleKey] : [];
    $toAsset = is_array($assets[$toModuleKey] ?? null) ? $assets[$toModuleKey] : [];
    $fromText = sr_member_asset_amount_text((int) ($suggestion['request_amount'] ?? 0), (string) ($fromAsset['unit_label'] ?? ''));
    $toText = sr_member_asset_amount_text((int) ($suggestion['deposit_amount'] ?? 0), (string) ($toAsset['unit_label'] ?? ''));
    $feeAmount = (int) ($suggestion['fee_amount'] ?? 0);
    $feeText = $feeAmount > 0 ? ' 수수료 ' . sr_member_asset_amount_text($feeAmount, (string) ($toAsset['unit_label'] ?? '')) . '이 포함됩니다.' : '';

    return trim($baseMessage . ' ' . (string) ($fromAsset['label'] ?? $fromModuleKey) . ' ' . $fromText . '을(를) '
        . (string) ($toAsset['label'] ?? $toModuleKey) . ' ' . $toText . '으로 자동 환전한 뒤 결제합니다.' . $feeText);
}

function sr_member_utf8_codepoint(string $char): int
{
    $bytes = array_values(unpack('C*', $char) ?: []);
    $count = count($bytes);
    if ($count === 0) {
        return 0;
    }
    if ($bytes[0] < 0x80) {
        return $bytes[0];
    }
    if ($count >= 2 && ($bytes[0] & 0xE0) === 0xC0) {
        return (($bytes[0] & 0x1F) << 6) | ($bytes[1] & 0x3F);
    }
    if ($count >= 3 && ($bytes[0] & 0xF0) === 0xE0) {
        return (($bytes[0] & 0x0F) << 12) | (($bytes[1] & 0x3F) << 6) | ($bytes[2] & 0x3F);
    }
    if ($count >= 4 && ($bytes[0] & 0xF8) === 0xF0) {
        return (($bytes[0] & 0x07) << 18) | (($bytes[1] & 0x3F) << 12) | (($bytes[2] & 0x3F) << 6) | ($bytes[3] & 0x3F);
    }

    return 0;
}

function sr_member_korean_subject_particle(string $label): string
{
    $label = trim($label);
    if ($label === '' || preg_match('/(.)\z/u', $label, $matches) !== 1) {
        return '이';
    }

    $codepoint = sr_member_utf8_codepoint((string) $matches[1]);
    if ($codepoint >= 0xAC00 && $codepoint <= 0xD7A3) {
        return (($codepoint - 0xAC00) % 28) > 0 ? '이' : '가';
    }

    return '이';
}

function sr_member_asset_amount_text(int $amount, string $unitLabel): string
{
    $unitLabel = trim($unitLabel);
    return number_format(max(0, $amount)) . $unitLabel;
}

function sr_member_asset_settlement_shortage(PDO $pdo, array $assets, callable $balanceFunction, array $assetModules, int $settlementAmount, string $settlementCurrency): array
{
    $settlementAmount = max(0, $settlementAmount);
    $settlementCurrency = function_exists('sr_normalize_currency_code') ? sr_normalize_currency_code($settlementCurrency) : strtoupper(trim($settlementCurrency));
    if ($settlementAmount < 1) {
        return [];
    }

    $plan = sr_member_asset_settlement_plan($pdo, $assets, $balanceFunction, $assetModules, $settlementAmount, $settlementCurrency);
    if (!empty($plan['ok'])) {
        return [];
    }
    $planMessage = (string) ($plan['message'] ?? '');
    if (str_contains($planMessage, 'currency')) {
        return [];
    }

    $remaining = $settlementAmount;
    $shortage = [];
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
            continue;
        }

        $balance = max(0, (int) $balanceFunction($pdo, $assetModule));
        $maxSettlement = intdiv($balance * $settlementUnits, $assetUnits);
        $settlementStep = sr_member_asset_settlement_step($assetUnits, $settlementUnits);
        $settlementUse = min($remaining, $maxSettlement);
        $settlementUse -= $settlementUse % $settlementStep;
        if ($settlementUse < 0) {
            $settlementUse = 0;
        }

        $uncoveredSettlement = $remaining - $settlementUse;
        if ($uncoveredSettlement > 0) {
            $shortageAmount = intdiv(($uncoveredSettlement * $assetUnits) + $settlementUnits - 1, $settlementUnits);
            $shortage = [
                'asset_module' => $assetModule,
                'asset_label' => (string) ($asset['label'] ?? $assetModule),
                'asset_unit_label' => (string) ($asset['unit_label'] ?? ''),
                'amount' => max(1, $shortageAmount),
                'balance' => $balance,
                'settlement_amount' => $uncoveredSettlement,
                'settlement_currency' => $settlementCurrency,
            ];
        }

        $remaining = $uncoveredSettlement;
        if ($remaining <= 0) {
            return [];
        }
    }

    return $shortage;
}

function sr_member_asset_settlement_config_error_message(array $plan, string $suffix = ''): string
{
    $message = (string) ($plan['message'] ?? '');
    if ($message === '' || !str_contains($message, 'currency')) {
        return '';
    }

    if (str_contains($message, 'Unknown settlement currency')) {
        $result = '포인트/금액 정산 기준 통화 설정이 올바르지 않습니다.';
    } elseif (str_contains($message, 'Unknown asset settlement currency')) {
        $result = '포인트/금액 구매력 통화 설정이 올바르지 않습니다.';
    } else {
        $result = '가격/정책 통화와 포인트/금액 구매력 통화가 일치하지 않습니다.';
    }

    $suffix = trim($suffix);
    return $suffix !== '' ? $result . ' ' . $suffix : $result;
}

function sr_member_asset_balance_shortage_message(array $shortage, string $suffix, string $fallbackMessage): string
{
    $label = trim((string) ($shortage['asset_label'] ?? ''));
    $amount = (int) ($shortage['amount'] ?? 0);
    if ($label === '' || $amount < 1) {
        return $fallbackMessage;
    }

    $unitLabel = (string) ($shortage['asset_unit_label'] ?? '');
    return $label . sr_member_korean_subject_particle($label) . ' ' . sr_member_asset_amount_text($amount, $unitLabel) . ' 부족해 ' . $suffix;
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
