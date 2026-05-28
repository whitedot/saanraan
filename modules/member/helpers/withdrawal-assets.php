<?php

declare(strict_types=1);

function sr_member_withdrawal_asset_definitions(PDO $pdo): array
{
    require_once SR_ROOT . '/modules/member/helpers/assets.php';

    return sr_member_withdrawal_asset_contract_definitions($pdo);
}

function sr_member_withdrawal_asset_table_exists(PDO $pdo, string $tableName): bool
{
    if (preg_match('/\Asr_[a-z0-9_]{1,120}\z/', $tableName) !== 1) {
        return false;
    }

    try {
        $pdo->query('SELECT 1 FROM ' . $tableName . ' LIMIT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

function sr_member_withdrawal_asset_available(PDO $pdo, array $definition): bool
{
    $balanceFunction = (string) ($definition['balance_function'] ?? '');
    if (!function_exists($balanceFunction)) {
        return false;
    }

    $processFunction = (string) ($definition['process_function'] ?? '');
    if ($processFunction !== '') {
        return function_exists($processFunction);
    }

    $transactionFunction = (string) ($definition['transaction_function'] ?? '');
    return function_exists($transactionFunction)
        && sr_member_withdrawal_asset_table_exists($pdo, (string) ($definition['balance_table'] ?? ''))
        && sr_member_withdrawal_asset_table_exists($pdo, (string) ($definition['transaction_table'] ?? ''));
}

function sr_member_withdrawal_asset_balances(PDO $pdo, int $accountId): array
{
    if ($accountId < 1) {
        return [];
    }

    $assets = [];
    foreach (sr_member_withdrawal_asset_definitions($pdo) as $assetKey => $definition) {
        if (!sr_member_withdrawal_asset_available($pdo, $definition)) {
            continue;
        }

        $balanceFunction = (string) $definition['balance_function'];
        $balance = (int) $balanceFunction($pdo, $accountId);
        if ($balance <= 0) {
            continue;
        }

        $assets[$assetKey] = [
            'asset_key' => $assetKey,
            'label' => (string) $definition['label'],
            'balance' => $balance,
            'process_label' => (string) $definition['process_label'],
        ];
    }

    return $assets;
}

function sr_member_clean_withdrawal_refund_value(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function sr_member_withdrawal_refund_account_values(): array
{
    return [
        'bank' => sr_member_clean_withdrawal_refund_value(sr_post_string('refund_bank', 80), 80),
        'holder' => sr_member_clean_withdrawal_refund_value(sr_post_string('refund_account_holder', 80), 80),
        'number' => sr_member_clean_withdrawal_refund_value(sr_post_string('refund_account_number', 80), 80),
    ];
}

function sr_member_withdrawal_refund_account_errors(array $refundAccount): array
{
    $errors = [];
    if ((string) ($refundAccount['bank'] ?? '') === '') {
        $errors[] = sr_t('member::action.withdraw.refund_bank_required');
    }
    if ((string) ($refundAccount['holder'] ?? '') === '') {
        $errors[] = sr_t('member::action.withdraw.refund_holder_required');
    }
    if ((string) ($refundAccount['number'] ?? '') === '') {
        $errors[] = sr_t('member::action.withdraw.refund_number_required');
    }

    return $errors;
}

function sr_member_withdrawal_refund_account_summary(array $refundAccount): string
{
    $summary = trim((string) ($refundAccount['bank'] ?? '') . ' / ' . (string) ($refundAccount['holder'] ?? '') . ' / ' . (string) ($refundAccount['number'] ?? ''));

    return sr_member_clean_withdrawal_refund_value($summary, 160);
}

function sr_member_process_asset_withdrawal(PDO $pdo, int $accountId, array $refundAccount): array
{
    $processedAssets = [];
    foreach (sr_member_withdrawal_asset_definitions($pdo) as $assetKey => $definition) {
        if (!sr_member_withdrawal_asset_available($pdo, $definition)) {
            continue;
        }

        $balanceFunction = (string) $definition['balance_function'];
        $balance = (int) $balanceFunction($pdo, $accountId);
        if ($balance <= 0) {
            continue;
        }

        $processFunction = (string) ($definition['process_function'] ?? '');
        if ($processFunction !== '') {
            $processResult = $processFunction($pdo, $accountId);
            if ((int) ($processResult['amount'] ?? 0) > 0) {
                $processedAssets[$assetKey] = $processResult;
            }
            continue;
        }

        $reason = 'member.withdrawal.' . (string) $definition['ledger_label'] . '.' . (string) $definition['ledger_process_label'];
        if ($assetKey === 'deposit') {
            $reason .= ' refund=' . sr_member_withdrawal_refund_account_summary($refundAccount);
        }

        $transactionFunction = (string) $definition['transaction_function'];
        $transactionId = (int) $transactionFunction($pdo, [
            'account_id' => $accountId,
            'amount' => -$balance,
            'transaction_type' => (string) $definition['transaction_type'],
            'reason' => $reason,
            'reference_type' => 'member.withdrawal',
            'reference_id' => (string) $accountId,
            'created_by_account_id' => $accountId,
        ]);

        $processedAssets[$assetKey] = [
            'label' => (string) $definition['label'],
            'amount' => $balance,
            'transaction_id' => $transactionId,
            'process' => (string) $definition['process_label'],
        ];
    }

    return $processedAssets;
}
