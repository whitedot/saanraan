<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/point/helpers.php';

if (sr_request_method() === 'GET' && sr_request_path() === '/admin/points') {
    sr_redirect('/admin/points/balances');
}

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$allowedTransactionTypes = ['adjustment', 'grant', 'use', 'refund', 'expire'];
$allowedReferenceTypes = ['', 'order', 'payment', 'refund', 'support_ticket', 'event', 'migration'];
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$pointAdminPage = isset($pointAdminPage) ? (string) $pointAdminPage : 'balances';
if (!in_array($pointAdminPage, ['balances', 'transactions'], true)) {
    $pointAdminPage = 'balances';
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
    $reason = sr_point_clean_text(sr_post_string('reason', 255), 255);
    $referenceType = sr_point_clean_key(sr_post_string('reference_type', 60), 60);
    $referenceId = sr_point_clean_reference_id(sr_post_string('reference_id', 120), 120);
    if (!in_array($referenceType, $allowedReferenceTypes, true)) {
        $referenceType = '';
    }

    if ($targetAccountId <= 0) {
        $errors[] = sr_t('point::action.admin.member_hash_required');
    }

    if (preg_match('/\A-?\d+\z/', $amountInput) !== 1) {
        $errors[] = sr_t('point::action.admin.amount_integer');
    }

    $amount = (int) $amountInput;
    if ($amount === 0) {
        $errors[] = sr_t('point::action.admin.amount_nonzero');
    }

    if (!in_array($transactionType, $allowedTransactionTypes, true)) {
        $errors[] = sr_t('point::action.admin.transaction_type_invalid');
    } elseif (!sr_point_transaction_type_allows_amount($transactionType, $amount)) {
        $errors[] = sr_t('point::action.admin.amount_sign_invalid');
    }

    if ($reason === '') {
        $errors[] = sr_t('point::action.admin.reason_required');
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $targetAccountId]);
        if (!is_array($stmt->fetch())) {
            $errors[] = sr_t('point::action.admin.member_not_found');
        }
    }

    if ($errors === [] && $transactionType === 'refund' && $referenceType === 'refund' && preg_match('/\Apoint_transaction:([0-9]+)\z/', $referenceId, $matches) === 1) {
        $stmt = $pdo->prepare('SELECT transaction_type FROM sr_point_transactions WHERE id = :id AND account_id = :account_id LIMIT 1');
        $stmt->execute([
            'id' => (int) $matches[1],
            'account_id' => $targetAccountId,
        ]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            $errors[] = sr_t('point::action.admin.refund_original_not_found');
        } elseif ((string) ($row['transaction_type'] ?? '') === 'refund') {
            $errors[] = sr_t('point::action.admin.refund_again_disallowed');
        }
    }

    if ($errors === []) {
        try {
            $transactionId = sr_point_create_transaction($pdo, [
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

            sr_admin_flash_result(sr_admin_action_result([], sr_t('point::action.admin.transaction_saved')));
            $redirectIdentifier = sr_admin_member_public_hash($runtimeConfig, $targetAccountId);
            $redirectPath = $pointAdminPage === 'transactions' ? '/admin/points/transactions' : '/admin/points/balances';
            sr_redirect($redirectPath . '?account_identifier=' . rawurlencode($redirectIdentifier));
        } catch (Throwable $exception) {
            if ($exception->getMessage() === 'Point balance cannot be negative.') {
                $errors[] = sr_t('point::action.admin.balance_negative');
            } elseif ($exception->getMessage() === 'Point transaction amount sign is invalid for type.') {
                $errors[] = sr_t('point::action.admin.amount_sign_mismatch');
            } else {
                $errors[] = sr_t('point::action.admin.transaction_save_failed');
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
        $selectedBalance = sr_point_balance($pdo, $accountIdFilter);
    }
}

$balances = [];
$stmt = $pdo->query(
    'SELECT b.account_id, b.balance, b.updated_at, a.email, a.display_name, a.status
     FROM sr_point_balances b
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
         FROM sr_point_transactions t
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
         FROM sr_point_transactions t
         INNER JOIN sr_member_accounts a ON a.id = t.account_id
         ORDER BY t.id DESC
         LIMIT 100'
    );
}
foreach ($stmt->fetchAll() as $row) {
    $transactions[] = sr_admin_member_row_with_public_hash($runtimeConfig, $row);
}

include SR_ROOT . '/modules/point/views/admin-points.php';
