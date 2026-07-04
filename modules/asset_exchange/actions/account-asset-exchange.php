<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/asset_exchange/helpers.php';
if (sr_module_enabled($pdo, 'identity_verification') && is_file(SR_ROOT . '/modules/identity_verification/helpers.php')) {
    require_once SR_ROOT . '/modules/identity_verification/helpers.php';
}

$account = sr_member_require_login($pdo);
$flash = isset($_SESSION['sr_asset_exchange_flash']) && is_array($_SESSION['sr_asset_exchange_flash'])
    ? $_SESSION['sr_asset_exchange_flash']
    : [];
unset($_SESSION['sr_asset_exchange_flash']);
$errors = isset($flash['errors']) && is_array($flash['errors']) ? array_values(array_map('strval', $flash['errors'])) : [];
$notice = (string) ($flash['notice'] ?? '');
$assets = sr_asset_exchange_assets($pdo);
$assetExchangeSettings = sr_asset_exchange_settings($pdo);
$assetExchangeIdentityPurpose = 'asset.exchange';
$assetExchangeIdentityRequired = (string) ($assetExchangeSettings['identity_exchange_required'] ?? '0') === '1';
$assetExchangeIdentitySatisfied = $assetExchangeIdentityRequired
    && function_exists('sr_identity_verification_session_result')
    && sr_identity_verification_session_result($pdo, $assetExchangeIdentityPurpose, (int) $account['id']) !== null;
$assetExchangeIdentityAvailable = function_exists('sr_identity_verification_available')
    && sr_identity_verification_available($pdo, $assetExchangeIdentityPurpose);
$assetExchangeIdentityStartUrl = $assetExchangeIdentityAvailable && function_exists('sr_identity_verification_start_url')
    ? sr_identity_verification_start_url($assetExchangeIdentityPurpose, '/account/asset-exchange')
    : '';
$exchangeEnabled = sr_asset_exchange_enabled($pdo);
$policies = sr_asset_exchange_policies($pdo, true);
$availablePolicies = $exchangeEnabled ? sr_asset_exchange_available_policies($policies, $assets) : [];
$selectedPolicy = null;
$quote = null;

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $policyId = (int) sr_post_string('policy_id', 30);
    $amount = sr_asset_exchange_int_string(sr_post_string('amount', 30));
    $submitToken = sr_post_string_without_truncation('exchange_submit_token', 32) ?? '';
    if (!$exchangeEnabled) {
        $errors[] = '환전 기능이 현재 사용 중지되어 있습니다.';
    }
    if ($assetExchangeIdentityRequired && !$assetExchangeIdentitySatisfied) {
        $errors[] = $assetExchangeIdentityStartUrl !== ''
            ? '환전 신청 전 본인확인을 완료해 주세요.'
            : '본인확인 기능이 준비되지 않아 환전을 진행할 수 없습니다.';
    }
    if ($errors === []) {
        $selectedPolicy = sr_asset_exchange_policy($pdo, $policyId);
        if (!is_array($selectedPolicy)) {
            $errors[] = '환전 정책을 선택하세요.';
        } else {
            try {
                $quote = sr_asset_exchange_quote($pdo, $selectedPolicy, (int) $account['id'], $amount);
            } catch (Throwable $exception) {
                $quote = null;
                $errors[] = $exception instanceof InvalidArgumentException ? $exception->getMessage() : '환전 예상 금액을 계산할 수 없습니다.';
            }
        }
    }

    if (is_array($selectedPolicy) && is_array($quote ?? null)) {
        if ($submitToken === '' || !sr_asset_exchange_consume_submit_token($submitToken, (int) $selectedPolicy['id'], $amount, $quote)) {
            $errors[] = '이미 처리했거나 만료된 환전 요청입니다. 예상 금액을 다시 확인하세요.';
        }
    }

    if ($errors === [] && is_array($selectedPolicy)) {
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
                if (function_exists('sr_identity_verification_consume_session_result')) {
                    sr_identity_verification_consume_session_result($pdo, $assetExchangeIdentityPurpose, (int) $account['id']);
                }
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
    if ($policyId > 0 && !$exchangeEnabled) {
        $errors[] = '환전 기능이 현재 사용 중지되어 있습니다.';
    } elseif ($policyId > 0) {
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
    $exchangeSubmitToken = sr_asset_exchange_store_submit_token((int) $selectedPolicy['id'], (int) $quote['request_amount'], $quote);
} else {
    $_SESSION['sr_asset_exchange_submit_tokens'] = sr_asset_exchange_prune_submit_tokens(sr_asset_exchange_submit_tokens());
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
