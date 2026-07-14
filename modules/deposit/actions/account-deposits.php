<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/deposit/helpers.php';
if (sr_module_enabled($pdo, 'identity_verification') && is_file(SR_ROOT . '/modules/identity_verification/helpers.php')) {
    require_once SR_ROOT . '/modules/identity_verification/helpers.php';
}

$account = sr_member_require_login($pdo);
$depositDisplayName = sr_deposit_display_name($pdo);
$depositUnitLabel = sr_deposit_unit_label($pdo);
$depositAmountLabel = static function (int $amount) use ($depositUnitLabel): string {
    return number_format($amount) . $depositUnitLabel;
};
$depositFlash = isset($_SESSION['sr_deposit_flash']) && is_array($_SESSION['sr_deposit_flash']) ? $_SESSION['sr_deposit_flash'] : [];
unset($_SESSION['sr_deposit_flash']);
$errors = isset($depositFlash['errors']) && is_array($depositFlash['errors']) ? array_values(array_map('strval', $depositFlash['errors'])) : [];
$notice = (string) ($depositFlash['notice'] ?? '');
$depositRefundFormValues = isset($depositFlash['values']) && is_array($depositFlash['values']) ? $depositFlash['values'] : [];
$depositSettings = sr_deposit_settings($pdo);
$depositIdentityPurpose = 'deposit.refund_request';
$depositIdentityRequired = !empty($depositSettings['identity_refund_required']);
$depositIdentitySatisfied = $depositIdentityRequired
    && function_exists('sr_identity_verification_session_result')
    && sr_identity_verification_session_result($pdo, $depositIdentityPurpose, (int) $account['id']) !== null;
$depositIdentityAvailable = function_exists('sr_identity_verification_available')
    && sr_identity_verification_available($pdo, $depositIdentityPurpose);
$depositIdentityStartUrl = $depositIdentityAvailable && function_exists('sr_identity_verification_start_url')
    ? sr_identity_verification_start_url($depositIdentityPurpose, '/account/deposits#deposit-refund-request')
    : '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $intent = sr_post_string('intent', 40);
    $depositRefundFormValues = $intent === 'refund_request' ? [
        'amount' => sr_post_string('amount', 30),
        'bank_name' => sr_post_string('bank_name', 80),
        'bank_account_number' => sr_post_string('bank_account_number', 80),
        'bank_account_holder' => sr_post_string('bank_account_holder', 80),
        'requester_note' => sr_post_string('requester_note', 255),
    ] : [];

    if ($intent === 'refund_request') {
        if ($depositIdentityRequired && !$depositIdentitySatisfied) {
            $errors[] = $depositIdentityStartUrl !== ''
                ? $depositDisplayName . ' 환불 신청 전 본인확인을 완료해 주세요.'
                : '본인확인 기능이 준비되지 않아 환불 신청을 진행할 수 없습니다.';
        }
        $amountInput = (string) ($depositRefundFormValues['amount'] ?? '');
        if (preg_match('/\A\d+\z/', $amountInput) !== 1) {
            $errors[] = '환불 신청 금액은 양의 정수로 입력하세요.';
        }
        $amount = (int) $amountInput;
        if ($amount < sr_deposit_refund_min_amount()) {
            $errors[] = '환불 신청 금액은 최소 ' . $depositAmountLabel(sr_deposit_refund_min_amount()) . ' 이상이어야 합니다.';
        }
        if ($amount > sr_deposit_refund_max_amount()) {
            $errors[] = '환불 신청 금액은 최대 ' . $depositAmountLabel(sr_deposit_refund_max_amount()) . '을 초과할 수 없습니다.';
        }

        if ($errors === []) {
            try {
                $requestId = sr_deposit_create_refund_request($pdo, (int) $account['id'], [
                    'amount' => $amount,
                    'bank_name' => (string) ($depositRefundFormValues['bank_name'] ?? ''),
                    'bank_account_number' => (string) ($depositRefundFormValues['bank_account_number'] ?? ''),
                    'bank_account_holder' => (string) ($depositRefundFormValues['bank_account_holder'] ?? ''),
                    'requester_note' => (string) ($depositRefundFormValues['requester_note'] ?? ''),
                ]);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'member',
                    'event_type' => 'deposit.refund_request.created',
                    'target_type' => 'deposit_refund_request',
                    'target_id' => (string) $requestId,
                    'result' => 'success',
                    'message' => 'Deposit refund request created.',
                    'metadata' => ['amount' => $amount],
                ]);
                if (function_exists('sr_identity_verification_consume_session_result')) {
                    sr_identity_verification_consume_session_result($pdo, $depositIdentityPurpose, (int) $account['id']);
                }
                $_SESSION['sr_deposit_flash'] = ['notice' => $depositDisplayName . ' 환불 신청을 접수했습니다.', 'errors' => []];
                sr_redirect('/account/deposits');
            } catch (Throwable $exception) {
                if ($exception->getMessage() === 'Deposit refund amount exceeds available balance.') {
                    $errors[] = '신청 가능 ' . $depositDisplayName . ' 잔액을 초과했습니다.';
                } elseif ($exception->getMessage() === 'Deposit refund bank fields are required.') {
                    $errors[] = '은행명, 계좌번호, 예금주를 모두 입력하세요.';
                } elseif ($exception->getMessage() === 'Deposit usage is disabled.') {
                    $errors[] = '현재 ' . $depositDisplayName . '을 사용하지 않습니다.';
                } elseif ($exception->getMessage() === 'Deposit refund requests are disabled.') {
                    $errors[] = '현재 ' . $depositDisplayName . ' 환불 신청을 받지 않습니다.';
                } elseif ($exception->getMessage() === 'Deposit refund account is not in an allowed group.') {
                    $errors[] = $depositDisplayName . ' 환불 신청 대상이 아닙니다.';
                } else {
                    sr_log_exception($exception, 'deposit_refund_request_create');
                    $errors[] = '환불 신청 접수 중 오류가 발생했습니다.';
                }
            }
        }
    } elseif ($intent === 'cancel_refund_request') {
        $requestId = (int) sr_post_string('request_id', 20);
        try {
            sr_deposit_cancel_refund_request($pdo, $requestId, (int) $account['id']);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'member',
                'event_type' => 'deposit.refund_request.canceled',
                'target_type' => 'deposit_refund_request',
                'target_id' => (string) $requestId,
                'result' => 'success',
                'message' => 'Deposit refund request canceled.',
            ]);
            $_SESSION['sr_deposit_flash'] = ['notice' => $depositDisplayName . ' 환불 신청을 취소했습니다.', 'errors' => []];
            sr_redirect('/account/deposits');
        } catch (Throwable $exception) {
            $errors[] = '취소할 수 있는 환불 신청을 찾지 못했습니다.';
        }
    } else {
        $errors[] = '요청 유형이 올바르지 않습니다.';
    }
    if ($errors !== []) {
        $_SESSION['sr_deposit_flash'] = ['notice' => '', 'errors' => $errors, 'values' => $depositRefundFormValues];
        sr_redirect('/account/deposits' . ($intent === 'refund_request' ? '#deposit-refund-request' : ''));
    }
}

$balance = sr_deposit_balance($pdo, (int) $account['id']);
$pendingRefundAmount = sr_deposit_pending_refund_amount($pdo, (int) $account['id']);
$availableRefundAmount = max(0, $balance - $pendingRefundAmount);
$refundRequestsEnabled = sr_deposit_refund_requests_enabled($pdo);
$canRequestRefund = sr_deposit_account_can_request_refund($pdo, (int) $account['id']);
$refundRequests = sr_deposit_refund_requests_for_account($pdo, (int) $account['id']);
$stmt = $pdo->prepare(
    'SELECT id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_at
     FROM sr_deposit_transactions
     WHERE account_id = :account_id
     ORDER BY id DESC
     LIMIT 100'
);
$stmt->execute(['account_id' => (int) $account['id']]);
$transactions = $stmt->fetchAll();

include SR_ROOT . '/modules/deposit/views/account-deposits.php';
