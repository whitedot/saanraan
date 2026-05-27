<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/asset_exchange/helpers.php';

$account = sr_member_require_login($pdo);
$errors = [];
$notice = '';
$assets = sr_asset_exchange_assets($pdo);
$policies = sr_asset_exchange_policies($pdo, true);
$selectedPolicy = null;
$quote = null;

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $policyId = (int) sr_post_string('policy_id', 30);
    $amount = sr_asset_exchange_int_string(sr_post_string('amount', 30));
    $selectedPolicy = sr_asset_exchange_policy($pdo, $policyId);
    if (!is_array($selectedPolicy)) {
        $errors[] = '환전 정책을 선택하세요.';
    } else {
        try {
            $quote = sr_asset_exchange_quote($pdo, $selectedPolicy, (int) $account['id'], $amount);
            $logId = sr_asset_exchange_execute($pdo, $selectedPolicy, (int) $account['id'], $amount, (int) $account['id']);
            $notice = '환전이 완료되었습니다.';
            $quote['log_id'] = $logId;
            $selectedPolicy = null;
        } catch (Throwable $exception) {
            $errors[] = $exception instanceof InvalidArgumentException || $exception instanceof RuntimeException
                ? $exception->getMessage()
                : '환전 처리에 실패했습니다.';
        }
    }
} else {
    $policyId = (int) ($_GET['policy_id'] ?? 0);
    if ($policyId > 0) {
        $selectedPolicy = sr_asset_exchange_policy($pdo, $policyId);
        $amount = sr_asset_exchange_int_string($_GET['amount'] ?? 0);
        if (is_array($selectedPolicy) && $amount > 0) {
            try {
                $quote = sr_asset_exchange_quote($pdo, $selectedPolicy, (int) $account['id'], $amount);
            } catch (Throwable $exception) {
                $errors[] = $exception instanceof InvalidArgumentException ? $exception->getMessage() : '환전 예상 금액을 계산할 수 없습니다.';
            }
        }
    }
}

$balances = [];
foreach ($assets as $moduleKey => $asset) {
    $balanceFunction = (string) $asset['balance_function'];
    $balances[$moduleKey] = $balanceFunction($pdo, (int) $account['id']);
}

$stmt = $pdo->prepare(
    'SELECT *
     FROM sr_asset_exchange_logs
     WHERE account_id = :account_id
     ORDER BY id DESC
     LIMIT 50'
);
$stmt->execute(['account_id' => (int) $account['id']]);
$logs = $stmt->fetchAll();

include SR_ROOT . '/modules/asset_exchange/views/account-asset-exchange.php';
