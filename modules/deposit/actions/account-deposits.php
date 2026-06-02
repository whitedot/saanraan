<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/deposit/helpers.php';

$account = sr_member_require_login($pdo);
$errors = [];
$notice = '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $intent = sr_post_string('intent', 40);

    if ($intent === 'refund_request') {
        $amountInput = sr_post_string('amount', 30);
        if (preg_match('/\A\d+\z/', $amountInput) !== 1) {
            $errors[] = '환불 신청 금액은 양의 정수로 입력하세요.';
        }
        $amount = (int) $amountInput;
        if ($amount < sr_deposit_refund_min_amount()) {
            $errors[] = '환불 신청 금액은 최소 ' . number_format(sr_deposit_refund_min_amount()) . '원 이상이어야 합니다.';
        }
        if ($amount > sr_deposit_refund_max_amount()) {
            $errors[] = '환불 신청 금액은 최대 ' . number_format(sr_deposit_refund_max_amount()) . '원을 초과할 수 없습니다.';
        }

        if ($errors === []) {
            try {
                $requestId = sr_deposit_create_refund_request($pdo, (int) $account['id'], [
                    'amount' => $amount,
                    'bank_name' => sr_post_string('bank_name', 80),
                    'bank_account_number' => sr_post_string('bank_account_number', 80),
                    'bank_account_holder' => sr_post_string('bank_account_holder', 80),
                    'requester_note' => sr_post_string('requester_note', 255),
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
                $_SESSION['sr_deposit_flash'] = ['notice' => '예치금 환불 신청을 접수했습니다.', 'errors' => []];
                sr_redirect('/account/deposits');
            } catch (Throwable $exception) {
                if ($exception->getMessage() === 'Deposit refund amount exceeds available balance.') {
                    $errors[] = '신청 가능 예치금 잔액을 초과했습니다.';
                } elseif ($exception->getMessage() === 'Deposit refund bank fields are required.') {
                    $errors[] = '은행명, 계좌번호, 예금주를 모두 입력하세요.';
                } elseif ($exception->getMessage() === 'Deposit refund requests are disabled.') {
                    $errors[] = '현재 예치금 환불 신청을 받지 않습니다.';
                } elseif ($exception->getMessage() === 'Deposit refund account is not in an allowed group.') {
                    $errors[] = '예치금 환불 신청 대상이 아닙니다.';
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
            $_SESSION['sr_deposit_flash'] = ['notice' => '예치금 환불 신청을 취소했습니다.', 'errors' => []];
            sr_redirect('/account/deposits');
        } catch (Throwable $exception) {
            $errors[] = '취소할 수 있는 환불 신청을 찾지 못했습니다.';
        }
    } else {
        $errors[] = '요청 유형이 올바르지 않습니다.';
    }
}

if (isset($_SESSION['sr_deposit_flash']) && is_array($_SESSION['sr_deposit_flash'])) {
    $notice = (string) ($_SESSION['sr_deposit_flash']['notice'] ?? '');
    $flashErrors = $_SESSION['sr_deposit_flash']['errors'] ?? [];
    if (is_array($flashErrors)) {
        foreach ($flashErrors as $flashError) {
            $errors[] = (string) $flashError;
        }
    }
    unset($_SESSION['sr_deposit_flash']);
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
