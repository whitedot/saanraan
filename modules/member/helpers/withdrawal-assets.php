<?php

declare(strict_types=1);

function sr_member_withdrawal_asset_definitions(): array
{
    return [
        'point' => [
            'label' => '포인트',
            'balance_table' => 'sr_point_balances',
            'transaction_table' => 'sr_point_transactions',
            'helper' => SR_ROOT . '/modules/point/helpers.php',
            'balance_function' => 'sr_point_balance',
            'transaction_function' => 'sr_point_create_transaction',
            'transaction_type' => 'expire',
            'process_label' => '소멸',
        ],
        'reward' => [
            'label' => '적립금',
            'balance_table' => 'sr_reward_balances',
            'transaction_table' => 'sr_reward_transactions',
            'helper' => SR_ROOT . '/modules/reward/helpers.php',
            'balance_function' => 'sr_reward_balance',
            'transaction_function' => 'sr_reward_create_transaction',
            'transaction_type' => 'expire',
            'process_label' => '소멸',
        ],
        'deposit' => [
            'label' => '예치금',
            'balance_table' => 'sr_deposit_balances',
            'transaction_table' => 'sr_deposit_transactions',
            'helper' => SR_ROOT . '/modules/deposit/helpers.php',
            'balance_function' => 'sr_deposit_balance',
            'transaction_function' => 'sr_deposit_create_transaction',
            'transaction_type' => 'withdraw',
            'process_label' => '환불',
        ],
    ];
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
    $helper = (string) ($definition['helper'] ?? '');
    if ($helper === '' || !is_file($helper)) {
        return false;
    }

    require_once $helper;
    $balanceFunction = (string) ($definition['balance_function'] ?? '');
    $transactionFunction = (string) ($definition['transaction_function'] ?? '');
    if (!function_exists($balanceFunction) || !function_exists($transactionFunction)) {
        return false;
    }

    return sr_member_withdrawal_asset_table_exists($pdo, (string) ($definition['balance_table'] ?? ''))
        && sr_member_withdrawal_asset_table_exists($pdo, (string) ($definition['transaction_table'] ?? ''));
}

function sr_member_withdrawal_asset_balances(PDO $pdo, int $accountId): array
{
    if ($accountId < 1) {
        return [];
    }

    $assets = [];
    foreach (sr_member_withdrawal_asset_definitions() as $assetKey => $definition) {
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
        $errors[] = '예치금 환불 은행을 입력하세요.';
    }
    if ((string) ($refundAccount['holder'] ?? '') === '') {
        $errors[] = '예치금 환불 예금주를 입력하세요.';
    }
    if ((string) ($refundAccount['number'] ?? '') === '') {
        $errors[] = '예치금 환불 계좌번호를 입력하세요.';
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
    foreach (sr_member_withdrawal_asset_definitions() as $assetKey => $definition) {
        if (!sr_member_withdrawal_asset_available($pdo, $definition)) {
            continue;
        }

        $balanceFunction = (string) $definition['balance_function'];
        $balance = (int) $balanceFunction($pdo, $accountId);
        if ($balance <= 0) {
            continue;
        }

        $reason = (string) $definition['label'] . ' 회원 탈퇴 ' . (string) $definition['process_label'];
        if ($assetKey === 'deposit') {
            $reason .= ' 요청: ' . sr_member_withdrawal_refund_account_summary($refundAccount);
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
