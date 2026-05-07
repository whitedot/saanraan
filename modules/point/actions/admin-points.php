<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/admin/helpers.php';
require_once TOY_ROOT . '/modules/point/helpers.php';

$account = toy_member_require_login($pdo);
toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$allowedTransactionTypes = ['adjustment', 'grant', 'use', 'refund', 'expire'];
$errors = [];
$notice = '';

if (toy_request_method() === 'POST') {
    toy_require_csrf();

    $targetAccountId = (int) toy_post_string('account_id', 20);
    $amountInput = toy_post_string('amount', 30);
    $transactionType = toy_post_string('transaction_type', 40);
    $reason = toy_point_clean_text(toy_post_string('reason', 255), 255);
    $referenceType = toy_point_clean_key(toy_post_string('reference_type', 60), 60);
    $referenceId = toy_point_clean_reference_id(toy_post_string('reference_id', 120), 120);

    if ($targetAccountId <= 0) {
        $errors[] = '회원을 선택하세요.';
    }

    if (preg_match('/\A-?\d+\z/', $amountInput) !== 1) {
        $errors[] = '포인트 수량은 정수로 입력하세요.';
    }

    $amount = (int) $amountInput;
    if ($amount === 0) {
        $errors[] = '포인트 수량은 0일 수 없습니다.';
    }

    if (!in_array($transactionType, $allowedTransactionTypes, true)) {
        $errors[] = '거래 유형이 올바르지 않습니다.';
    } elseif (!toy_point_transaction_type_allows_amount($transactionType, $amount)) {
        $errors[] = '지급과 환불은 양수, 사용과 만료는 음수로 입력하세요. 조정은 양수와 음수를 모두 사용할 수 있습니다.';
    }

    if ($reason === '') {
        $errors[] = '사유를 입력하세요.';
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT id FROM toy_member_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $targetAccountId]);
        if (!is_array($stmt->fetch())) {
            $errors[] = '회원을 찾을 수 없습니다.';
        }
    }

    if ($errors === []) {
        try {
            $transactionId = toy_point_create_transaction($pdo, [
                'account_id' => $targetAccountId,
                'amount' => $amount,
                'transaction_type' => $transactionType,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by_account_id' => (int) $account['id'],
            ]);

            toy_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'point.transaction.created',
                'target_type' => 'member_account',
                'target_id' => (string) $targetAccountId,
                'result' => 'success',
                'message' => 'Point transaction created.',
                'metadata' => [
                    'transaction_id' => $transactionId,
                    'amount' => $amount,
                    'transaction_type' => $transactionType,
                ],
            ]);

            $notice = '포인트 거래를 저장했습니다.';
        } catch (Throwable $exception) {
            if ($exception->getMessage() === 'Point balance cannot be negative.') {
                $errors[] = '포인트 잔액은 음수가 될 수 없습니다.';
            } elseif ($exception->getMessage() === 'Point transaction amount sign is invalid for type.') {
                $errors[] = '거래 유형과 포인트 수량의 부호가 맞지 않습니다.';
            } else {
                $errors[] = '포인트 거래 저장 중 오류가 발생했습니다.';
            }
        }
    }
}

$accountIdFilter = (int) toy_get_string('account_id', 20);
$selectedAccount = null;
$selectedBalance = null;
if ($accountIdFilter > 0) {
    $stmt = $pdo->prepare('SELECT id, email, display_name, status FROM toy_member_accounts WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $accountIdFilter]);
    $row = $stmt->fetch();
    if (is_array($row)) {
        $selectedAccount = $row;
        $selectedBalance = toy_point_balance($pdo, $accountIdFilter);
    }
}

$balances = [];
$stmt = $pdo->query(
    'SELECT b.account_id, b.balance, b.updated_at, a.email, a.display_name, a.status
     FROM toy_point_balances b
     INNER JOIN toy_member_accounts a ON a.id = b.account_id
     ORDER BY b.updated_at DESC
     LIMIT 50'
);
foreach ($stmt->fetchAll() as $row) {
    $balances[] = $row;
}

$transactions = [];
if ($accountIdFilter > 0) {
    $stmt = $pdo->prepare(
        'SELECT t.id, t.account_id, t.amount, t.balance_after, t.transaction_type, t.reason, t.reference_type, t.reference_id, t.created_by_account_id, t.created_at,
                a.email, a.display_name
         FROM toy_point_transactions t
         INNER JOIN toy_member_accounts a ON a.id = t.account_id
         WHERE t.account_id = :account_id
         ORDER BY t.id DESC
         LIMIT 100'
    );
    $stmt->execute(['account_id' => $accountIdFilter]);
} else {
    $stmt = $pdo->query(
        'SELECT t.id, t.account_id, t.amount, t.balance_after, t.transaction_type, t.reason, t.reference_type, t.reference_id, t.created_by_account_id, t.created_at,
                a.email, a.display_name
         FROM toy_point_transactions t
         INNER JOIN toy_member_accounts a ON a.id = t.account_id
         ORDER BY t.id DESC
         LIMIT 100'
    );
}
foreach ($stmt->fetchAll() as $row) {
    $transactions[] = $row;
}

include TOY_ROOT . '/modules/point/views/admin-points.php';
