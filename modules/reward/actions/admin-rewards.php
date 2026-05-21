<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/reward/helpers.php';

if (sr_request_method() === 'GET' && sr_request_path() === '/admin/rewards') {
    sr_redirect('/admin/rewards/balances');
}

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$allowedTransactionTypes = ['adjustment', 'grant', 'use', 'refund', 'expire'];
$allowedReferenceTypes = ['', 'order', 'payment', 'refund', 'support_ticket', 'event', 'migration'];
$errors = [];
$notice = '';
$rewardAdminPage = isset($rewardAdminPage) ? (string) $rewardAdminPage : 'balances';
if (!in_array($rewardAdminPage, ['balances', 'transactions'], true)) {
    $rewardAdminPage = 'balances';
}
$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$submittedAccountId = 0;

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $targetAccountIdentifier = sr_post_string('account_identifier', 80);
    if ($targetAccountIdentifier === '') {
        $targetAccountIdentifier = sr_post_string('account_id', 80);
    }
    $targetAccountId = sr_admin_member_account_id_from_identifier($pdo, $runtimeConfig, $targetAccountIdentifier);
    $submittedAccountId = $targetAccountId;
    $amountInput = sr_post_string('amount', 30);
    $transactionType = sr_post_string('transaction_type', 40);
    $reason = sr_reward_clean_text(sr_post_string('reason', 255), 255);
    $referenceType = sr_reward_clean_key(sr_post_string('reference_type', 60), 60);
    $referenceId = sr_reward_clean_reference_id(sr_post_string('reference_id', 120), 120);
    if (!in_array($referenceType, $allowedReferenceTypes, true)) {
        $referenceType = '';
    }

    if ($targetAccountId <= 0) {
        $errors[] = '회원 공개 해시를 입력하세요.';
    }

    if (preg_match('/\A-?\d+\z/', $amountInput) !== 1) {
        $errors[] = '적립금 금액은 정수로 입력하세요.';
    }

    $amount = (int) $amountInput;
    if ($amount === 0) {
        $errors[] = '적립금 금액은 0일 수 없습니다.';
    }

    if (!in_array($transactionType, $allowedTransactionTypes, true)) {
        $errors[] = '거래 유형이 올바르지 않습니다.';
    } elseif (!sr_reward_transaction_type_allows_amount($transactionType, $amount)) {
        $errors[] = '지급과 환불은 양수, 사용과 만료는 음수로 입력하세요. 조정은 양수와 음수를 모두 사용할 수 있습니다.';
    }

    if ($reason === '') {
        $errors[] = '사유를 입력하세요.';
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $targetAccountId]);
        if (!is_array($stmt->fetch())) {
            $errors[] = '회원을 찾을 수 없습니다.';
        }
    }

    if ($errors === []) {
        try {
            $transactionId = sr_reward_create_transaction($pdo, [
                'account_id' => $targetAccountId,
                'amount' => $amount,
                'transaction_type' => $transactionType,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by_account_id' => (int) $account['id'],
            ]);

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'reward.transaction.created',
                'target_type' => 'member_account',
                'target_id' => (string) $targetAccountId,
                'result' => 'success',
                'message' => 'Reward transaction created.',
                'metadata' => [
                    'transaction_id' => $transactionId,
                    'amount' => $amount,
                    'transaction_type' => $transactionType,
                ],
            ]);

            $notice = '적립금 거래를 저장했습니다.';
        } catch (Throwable $exception) {
            if ($exception->getMessage() === 'Reward balance cannot be negative.') {
                $errors[] = '적립금 잔액은 음수가 될 수 없습니다.';
            } elseif ($exception->getMessage() === 'Reward transaction amount sign is invalid for type.') {
                $errors[] = '거래 유형과 적립금 금액의 부호가 맞지 않습니다.';
            } else {
                $errors[] = '적립금 거래 저장 중 오류가 발생했습니다.';
            }
        }
    }
}

$accountLookupFilter = sr_admin_member_account_lookup_filter($pdo, $runtimeConfig);
$accountIdentifierFilter = (string) $accountLookupFilter['keyword'];
$accountIdFilter = (int) $accountLookupFilter['account_id'];
if ($accountIdFilter <= 0 && $submittedAccountId > 0) {
    $accountIdFilter = $submittedAccountId;
}
$selectedAccount = null;
$selectedBalance = null;
if ($accountIdFilter > 0) {
    $stmt = $pdo->prepare('SELECT id, email, display_name, status FROM sr_member_accounts WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $accountIdFilter]);
    $row = $stmt->fetch();
    if (is_array($row)) {
        $selectedAccount = sr_admin_member_row_with_public_hash($runtimeConfig, $row);
        $accountIdentifierFilter = (string) $selectedAccount['account_public_hash'];
        $selectedBalance = sr_reward_balance($pdo, $accountIdFilter);
    }
}

$balances = [];
$stmt = $pdo->query(
    'SELECT b.account_id, b.balance, b.updated_at, a.email, a.display_name, a.status
     FROM sr_reward_balances b
     INNER JOIN sr_member_accounts a ON a.id = b.account_id
     ORDER BY b.updated_at DESC
     LIMIT 50'
);
foreach ($stmt->fetchAll() as $row) {
    $balances[] = sr_admin_member_row_with_public_hash($runtimeConfig, $row);
}

$transactions = [];
if ($accountIdFilter > 0) {
    $stmt = $pdo->prepare(
        'SELECT t.id, t.account_id, t.amount, t.balance_after, t.transaction_type, t.reason, t.reference_type, t.reference_id, t.created_by_account_id, t.created_at,
                a.email, a.display_name
         FROM sr_reward_transactions t
         INNER JOIN sr_member_accounts a ON a.id = t.account_id
         WHERE t.account_id = :account_id
         ORDER BY t.id DESC
         LIMIT 100'
    );
    $stmt->execute(['account_id' => $accountIdFilter]);
} else {
    $stmt = $pdo->query(
        'SELECT t.id, t.account_id, t.amount, t.balance_after, t.transaction_type, t.reason, t.reference_type, t.reference_id, t.created_by_account_id, t.created_at,
                a.email, a.display_name
         FROM sr_reward_transactions t
         INNER JOIN sr_member_accounts a ON a.id = t.account_id
         ORDER BY t.id DESC
         LIMIT 100'
    );
}
foreach ($stmt->fetchAll() as $row) {
    $transactions[] = sr_admin_member_row_with_public_hash($runtimeConfig, $row);
}

include SR_ROOT . '/modules/reward/views/admin-rewards.php';
