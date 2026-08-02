<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

function sr_public_layout_member_action_rows(PDO $pdo, int $accountId): array
{
    if ($accountId <= 0) {
        return [];
    }

    $rows = [];

    foreach (sr_enabled_module_contract_files($pdo, 'member-action-rows.php') as $moduleKey => $contractFile) {
        $contract = sr_load_module_contract_file($moduleKey, $contractFile);
        $provider = is_callable($contract) ? $contract : ($contract['rows_function'] ?? null);
        if (!is_callable($provider)) {
            continue;
        }

        try {
            $moduleRows = $provider($pdo, $accountId);
        } catch (Throwable $exception) {
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'public_member_action_rows_' . $moduleKey);
            }
            continue;
        }

        if (!is_array($moduleRows)) {
            continue;
        }

        foreach ($moduleRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            if ($label === '' || $value === '' || !sr_is_safe_relative_url($url)) {
                continue;
            }

            $rows[] = [
                'label' => $label,
                'value' => $value,
                'url' => sr_url($url),
            ];
        }
    }

    return $rows;
}

function sr_public_layout_member_asset_rows(PDO $pdo, int $accountId): array
{
    if ($accountId <= 0) {
        return [];
    }

    $rows = [];

    foreach (sr_enabled_module_contract_files($pdo, 'member-assets.php') as $moduleKey => $contractFile) {
        $contract = sr_load_module_contract_file($moduleKey, $contractFile);
        if (!is_array($contract)) {
            continue;
        }

        $summaryUrl = trim((string) ($contract['summary_url'] ?? ''));
        if (!sr_is_safe_relative_url($summaryUrl)) {
            continue;
        }

        $helperPath = sr_public_layout_member_asset_helper_path((string) $moduleKey, $contract);
        if ($helperPath !== '') {
            require_once $helperPath;
        }

        $label = trim((string) ($contract['label'] ?? $moduleKey));
        $unit = (string) ($contract['unit_label'] ?? '');
        $balance = 0;
        try {
            $availableFunction = (string) ($contract['available_function'] ?? '');
            if ($availableFunction !== '' && (!function_exists($availableFunction) || !$availableFunction($pdo))) {
                continue;
            }

            $labelFunction = (string) ($contract['label_function'] ?? '');
            if ($labelFunction !== '' && function_exists($labelFunction)) {
                $resolvedLabel = trim((string) $labelFunction($pdo));
                if ($resolvedLabel !== '') {
                    $label = $resolvedLabel;
                }
            }

            $unitFunction = (string) ($contract['unit_function'] ?? '');
            if ($unitFunction !== '' && function_exists($unitFunction)) {
                $unit = (string) $unitFunction($pdo);
            }

            $balanceFunction = (string) ($contract['balance_function'] ?? '');
            if ($balanceFunction !== '' && function_exists($balanceFunction)) {
                $balance = (int) $balanceFunction($pdo, $accountId);
            } else {
                continue;
            }
        } catch (Throwable $exception) {
            $balance = 0;
        }

        $rows[] = [
            'label' => $label !== '' ? $label : (string) $moduleKey,
            'value' => number_format($balance) . $unit,
            'url' => sr_url($summaryUrl),
            'icon' => (string) ($contract['summary_icon'] ?? 'account_balance_wallet'),
        ];
    }

    foreach (sr_enabled_module_contract_files($pdo, 'member-summary-rows.php') as $moduleKey => $contractFile) {
        $contract = sr_load_module_contract_file($moduleKey, $contractFile);
        $provider = is_callable($contract) ? $contract : ($contract['rows_function'] ?? null);
        if (!is_callable($provider)) {
            continue;
        }

        try {
            $moduleRows = $provider($pdo, $accountId);
        } catch (Throwable $exception) {
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'public_member_summary_rows_' . $moduleKey);
            }
            continue;
        }

        if (!is_array($moduleRows)) {
            continue;
        }

        foreach ($moduleRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            if ($label === '' || $value === '' || !sr_is_safe_relative_url($url)) {
                continue;
            }

            $icon = trim((string) ($row['icon'] ?? ''));
            $rows[] = [
                'label' => $label,
                'value' => $value,
                'url' => sr_url($url),
                'icon' => $icon !== '' ? $icon : 'account_balance_wallet',
            ];
        }
    }

    return $rows;
}

function sr_public_layout_member_asset_helper_path(string $moduleKey, array $contract): string
{
    if (!sr_is_safe_module_key($moduleKey)) {
        return '';
    }

    $helpers = (string) ($contract['helpers'] ?? '');
    if ($helpers === '' || preg_match('/\Ahelpers(?:\/[a-z0-9_-]+)?\.php\z/', $helpers) !== 1) {
        return '';
    }

    $path = SR_ROOT . '/modules/' . $moduleKey . '/' . $helpers;
    return is_file($path) ? $path : '';
}
