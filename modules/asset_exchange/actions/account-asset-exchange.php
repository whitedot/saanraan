<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/asset_exchange/helpers.php';

$account = sr_member_require_login($pdo);
$flash = isset($_SESSION['sr_asset_exchange_flash']) && is_array($_SESSION['sr_asset_exchange_flash'])
    ? $_SESSION['sr_asset_exchange_flash']
    : [];
unset($_SESSION['sr_asset_exchange_flash']);
$errors = isset($flash['errors']) && is_array($flash['errors']) ? array_values(array_map('strval', $flash['errors'])) : [];
$notice = (string) ($flash['notice'] ?? '');
$assets = sr_asset_exchange_assets($pdo);
$policies = sr_asset_exchange_policies($pdo, true);
$availablePolicies = sr_asset_exchange_available_policies($policies, $assets);
$selectedPolicy = null;
$quote = null;

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $policyId = (int) sr_post_string('policy_id', 30);
    $amount = sr_asset_exchange_int_string(sr_post_string('amount', 30));
    $submitToken = sr_post_string('exchange_submit_token', 80);
    $validTokens = isset($_SESSION['sr_asset_exchange_submit_tokens']) && is_array($_SESSION['sr_asset_exchange_submit_tokens'])
        ? $_SESSION['sr_asset_exchange_submit_tokens']
        : [];
    $selectedPolicy = sr_asset_exchange_policy($pdo, $policyId);
    if ($submitToken === '' || !isset($validTokens[$submitToken])) {
        $errors[] = '이미 처리했거나 만료된 환전 요청입니다. 예상 금액을 다시 확인하세요.';
    } elseif (!is_array($selectedPolicy)) {
        $errors[] = '환전 정책을 선택하세요.';
    } else {
        unset($validTokens[$submitToken]);
        $_SESSION['sr_asset_exchange_submit_tokens'] = $validTokens;
        if (sr_asset_exchange_execute_rate_limited($pdo, (int) $account['id'])) {
            $errors[] = '환전 요청이 너무 많습니다. 잠시 후 다시 시도하세요.';
        } else {
            sr_asset_exchange_record_execute_attempt($pdo, (int) $account['id']);
            try {
                $logId = sr_asset_exchange_execute($pdo, $selectedPolicy, (int) $account['id'], $amount, (int) $account['id']);
                $_SESSION['sr_asset_exchange_flash'] = [
                    'notice' => '환전이 완료되었습니다.',
                    'errors' => [],
                    'log_id' => $logId,
                ];
                sr_redirect('/account/asset-exchange');
            } catch (Throwable $exception) {
                $message = $exception instanceof InvalidArgumentException || $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : '환전 처리에 실패했습니다.';
                $errors[] = $message;
                try {
                    sr_asset_exchange_record_failure($pdo, $selectedPolicy, (int) $account['id'], $amount, $message, (int) $account['id']);
                } catch (Throwable $logException) {
                    sr_log_exception($logException, 'asset_exchange_failure_log_failed');
                }
            }
        }
    }

    if ($errors !== []) {
        $_SESSION['sr_asset_exchange_flash'] = [
            'notice' => '',
            'errors' => $errors,
        ];
        sr_redirect('/account/asset-exchange');
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

$exchangeSubmitToken = '';
if (is_array($selectedPolicy) && is_array($quote)) {
    $exchangeSubmitToken = bin2hex(random_bytes(16));
    $validTokens = isset($_SESSION['sr_asset_exchange_submit_tokens']) && is_array($_SESSION['sr_asset_exchange_submit_tokens'])
        ? $_SESSION['sr_asset_exchange_submit_tokens']
        : [];
    $validTokens[$exchangeSubmitToken] = time();
    if (count($validTokens) > 10) {
        asort($validTokens);
        $validTokens = array_slice($validTokens, -10, null, true);
    }
    $_SESSION['sr_asset_exchange_submit_tokens'] = $validTokens;
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
