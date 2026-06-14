<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/deposit/helpers.php';

$account = sr_member_require_login($pdo);

if (sr_request_method() === 'GET' && sr_request_path() === '/admin/deposits') {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/deposits/balances', 'view');
    sr_redirect('/admin/deposits/balances');
}

$allowedTransactionTypes = ['adjustment', 'deposit', 'use', 'refund', 'withdraw'];
$allowedReferenceTypes = ['', 'order', 'payment', 'refund', 'support_ticket', 'event', 'migration'];
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$depositAdminPage = isset($depositAdminPage) ? (string) $depositAdminPage : 'balances';
if (!in_array($depositAdminPage, ['balances', 'transactions'], true)) {
    $depositAdminPage = 'balances';
}
$depositPermissionPath = $depositAdminPage === 'transactions' ? '/admin/deposits/transactions' : '/admin/deposits/balances';
sr_admin_require_permission($pdo, (int) $account['id'], $depositPermissionPath, 'view');
$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$submittedAccountId = 0;

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], $depositPermissionPath, 'edit');

    $targetAccountIdentifier = sr_post_string('account_identifier', 80);
    if ($targetAccountIdentifier === '') {
        $targetAccountIdentifier = sr_post_string('account_id', 80);
    }
    $targetAccountId = sr_admin_member_account_id_from_identifier($pdo, $runtimeConfig, $targetAccountIdentifier);
    $submittedAccountId = $targetAccountId;
    $amountInput = sr_post_string('amount', 30);
    $transactionType = sr_post_string('transaction_type', 40);
    $reason = sr_deposit_clean_text(sr_post_string('reason', 255), 255);
    $referenceType = sr_deposit_clean_key(sr_post_string('reference_type', 60), 60);
    $referenceId = sr_deposit_clean_reference_id(sr_post_string('reference_id', 120), 120);
    $approvalIdentifier = $targetAccountIdentifier;
    $approvalNote = $reason;
    $approvalAccountId = 0;
    if (!in_array($referenceType, $allowedReferenceTypes, true)) {
        $referenceType = '';
    }

    if ($targetAccountId <= 0) {
        $errors[] = sr_t('deposit::action.admin.member_hash_required');
    }

    if (preg_match('/\A-?\d+\z/', $amountInput) !== 1) {
        $errors[] = sr_t('deposit::action.admin.amount_integer');
    }

    $amount = (int) $amountInput;
    $baseAmount = $amount;
    if ($amount === 0) {
        $errors[] = sr_t('deposit::action.admin.amount_nonzero');
    }

    if (!in_array($transactionType, $allowedTransactionTypes, true)) {
        $errors[] = sr_t('deposit::action.admin.transaction_type_invalid');
    } elseif (!sr_deposit_transaction_type_allows_amount($transactionType, $amount)) {
        $errors[] = sr_t('deposit::action.admin.amount_sign_invalid');
    }

    if ($reason === '') {
        $errors[] = sr_t('deposit::action.admin.reason_required');
    }

    if ($referenceType !== '' && $referenceId === '') {
        $errors[] = sr_t('deposit::action.admin.reference_id_required');
    }
    if ($referenceType === '' && $referenceId !== '') {
        $errors[] = sr_t('deposit::action.admin.reference_type_required');
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $targetAccountId]);
        if (!is_array($stmt->fetch())) {
            $errors[] = sr_t('deposit::action.admin.member_not_found');
        }
    }

    if ($errors === [] && $transactionType === 'refund' && ($referenceType !== 'refund' || preg_match('/\Adeposit_transaction:([0-9]+)\z/', $referenceId, $matches) !== 1)) {
        $errors[] = sr_t('deposit::action.admin.refund_reference_required');
    }

    if ($errors === [] && $transactionType === 'refund' && $referenceType === 'refund' && preg_match('/\Adeposit_transaction:([0-9]+)\z/', $referenceId, $matches) === 1) {
        $stmt = $pdo->prepare('SELECT amount, transaction_type FROM sr_deposit_transactions WHERE id = :id AND account_id = :account_id LIMIT 1');
        $stmt->execute([
            'id' => (int) $matches[1],
            'account_id' => $targetAccountId,
        ]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            $errors[] = sr_t('deposit::action.admin.refund_original_not_found');
        } elseif ((string) ($row['transaction_type'] ?? '') === 'refund') {
            $errors[] = sr_t('deposit::action.admin.refund_again_disallowed');
        } elseif ((int) ($row['amount'] ?? 0) >= 0) {
            $errors[] = sr_t('deposit::action.admin.refund_original_not_negative');
        } else {
            $refundedStmt = $pdo->prepare(
                'SELECT COALESCE(SUM(amount), 0) AS refunded_amount
                 FROM sr_deposit_transactions
                 WHERE account_id = :account_id
                   AND transaction_type = \'refund\'
                   AND reference_type = \'refund\'
                   AND reference_id = :reference_id
                   AND amount > 0'
            );
            $refundedStmt->execute([
                'account_id' => $targetAccountId,
                'reference_id' => $referenceId,
            ]);
            $refundedRow = $refundedStmt->fetch();
            $refundableAmount = abs((int) ($row['amount'] ?? 0)) - (is_array($refundedRow) ? max(0, (int) ($refundedRow['refunded_amount'] ?? 0)) : 0);
            if ($amount > max(0, $refundableAmount)) {
                $errors[] = sr_t('deposit::action.admin.refund_amount_exceeds_remaining');
            }
        }
    }

    if ($errors === []) {
        $limitResult = sr_deposit_validate_admin_adjustment_limit($pdo, $runtimeConfig, (int) $account['id'], $depositPermissionPath, $amount, $approvalIdentifier, $approvalNote);
        if ($limitResult['error'] !== null) {
            $errors[] = (string) $limitResult['error'];
        }
        $approvalAccountId = (int) ($limitResult['approval_account_id'] ?? 0);
    }

    if ($errors === []) {
        try {
            $startedRefundTransaction = $transactionType === 'refund' && !$pdo->inTransaction();
            if ($startedRefundTransaction) {
                $pdo->beginTransaction();
            }
            if ($transactionType === 'refund' && $referenceType === 'refund' && preg_match('/\Adeposit_transaction:([0-9]+)\z/', $referenceId, $matches) === 1) {
                $stmt = $pdo->prepare(
                    'SELECT amount, transaction_type
                     FROM sr_deposit_transactions
                     WHERE id = :id
                       AND account_id = :account_id
                     LIMIT 1
                     FOR UPDATE'
                );
                $stmt->execute([
                    'id' => (int) $matches[1],
                    'account_id' => $targetAccountId,
                ]);
                $row = $stmt->fetch();
                if (!is_array($row)) {
                    throw new RuntimeException('Deposit refund original transaction not found.');
                }
                if ((string) ($row['transaction_type'] ?? '') === 'refund') {
                    throw new RuntimeException('Deposit refund transaction cannot be refunded.');
                }
                if ((int) ($row['amount'] ?? 0) >= 0) {
                    throw new RuntimeException('Deposit refund original transaction must be negative.');
                }
                $refundedStmt = $pdo->prepare(
                    'SELECT amount
                     FROM sr_deposit_transactions
                     WHERE account_id = :account_id
                       AND transaction_type = \'refund\'
                       AND reference_type = \'refund\'
                       AND reference_id = :reference_id
                       AND amount > 0
                     FOR UPDATE'
                );
                $refundedStmt->execute([
                    'account_id' => $targetAccountId,
                    'reference_id' => $referenceId,
                ]);
                $refundedAmount = 0;
                foreach ($refundedStmt->fetchAll() as $refundedRow) {
                    $refundedAmount += max(0, (int) ($refundedRow['amount'] ?? 0));
                }
                if ($amount > max(0, abs((int) ($row['amount'] ?? 0)) - $refundedAmount)) {
                    throw new RuntimeException('Deposit refund amount exceeds remaining reference amount.');
                }
            }

            $transactionId = sr_deposit_create_transaction($pdo, [
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
                'event_type' => 'deposit.transaction.created',
                'target_type' => 'member_account',
                'target_id' => (string) $targetAccountId,
                'result' => 'success',
                'message' => 'Deposit transaction created.',
                'metadata' => [
                    'transaction_id' => $transactionId,
                    'base_amount' => $baseAmount,
                    'amount' => $amount,
                    'transaction_type' => $transactionType,
                    'approval_account_id' => $approvalAccountId,
                    'approval_note' => $approvalAccountId > 0 ? $approvalNote : '',
                ],
            ]);

            if ($startedRefundTransaction) {
                $pdo->commit();
            }

            sr_admin_flash_result(sr_admin_action_result([], sr_t('deposit::action.admin.transaction_saved')));
            $redirectIdentifier = sr_admin_member_public_hash($runtimeConfig, $targetAccountId);
            $redirectPath = $depositAdminPage === 'transactions' ? '/admin/deposits/transactions' : '/admin/deposits/balances';
            sr_redirect($redirectPath . '?account_identifier=' . rawurlencode($redirectIdentifier));
        } catch (Throwable $exception) {
            if (isset($startedRefundTransaction) && $startedRefundTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception->getMessage() === 'Deposit balance cannot be negative.') {
                $errors[] = sr_t('deposit::action.admin.balance_negative');
            } elseif ($exception->getMessage() === 'Deposit transaction amount sign is invalid for type.') {
                $errors[] = sr_t('deposit::action.admin.amount_sign_mismatch');
            } elseif ($exception->getMessage() === 'Deposit refund reference is required.') {
                $errors[] = sr_t('deposit::action.admin.refund_reference_required');
            } elseif ($exception->getMessage() === 'Deposit refund original transaction not found.') {
                $errors[] = sr_t('deposit::action.admin.refund_original_not_found');
            } elseif ($exception->getMessage() === 'Deposit refund transaction cannot be refunded.') {
                $errors[] = sr_t('deposit::action.admin.refund_again_disallowed');
            } elseif ($exception->getMessage() === 'Deposit refund original transaction must be negative.') {
                $errors[] = sr_t('deposit::action.admin.refund_original_not_negative');
            } elseif ($exception->getMessage() === 'Deposit refund amount exceeds remaining reference amount.') {
                $errors[] = sr_t('deposit::action.admin.refund_amount_exceeds_remaining');
            } else {
                $errors[] = sr_t('deposit::action.admin.transaction_save_failed');
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
    $selectedAccountData = sr_admin_asset_selected_account($pdo, $runtimeConfig, $accountIdFilter, 'sr_deposit_balance');
    if (is_array($selectedAccountData['account'])) {
        $selectedAccount = $selectedAccountData['account'];
        $accountIdentifierFilter = (string) $selectedAccountData['identifier'];
        $selectedBalance = (int) $selectedAccountData['balance'];
    }
}

$balances = [];
$balanceSort = sr_admin_sort_from_request(sr_admin_asset_balance_sort_options(), sr_admin_asset_balance_default_sort());
$balancePagination = sr_admin_pagination_from_total($pdo, 0);
if ($depositAdminPage === 'balances') {
    $balancePagination = sr_admin_pagination_from_total($pdo, sr_admin_asset_balance_count($pdo, 'sr_deposit_balances'));
    $balances = sr_admin_asset_balance_rows($pdo, $runtimeConfig, 'sr_deposit_balances', $balanceSort, $balancePagination);
}

$transactions = [];
$transactionSort = sr_admin_sort_from_request(sr_admin_asset_transaction_sort_options(), sr_admin_asset_transaction_default_sort());
$transactionPagination = sr_admin_pagination_from_total($pdo, 0);
if ($depositAdminPage === 'transactions') {
    $transactionPagination = sr_admin_pagination_from_total($pdo, sr_admin_asset_transaction_count($pdo, 'sr_deposit_transactions', $accountIdFilter));
    $transactions = sr_admin_asset_transaction_rows($pdo, $runtimeConfig, 'sr_deposit_transactions', $transactionSort, $transactionPagination, $accountIdFilter);
}

include SR_ROOT . '/modules/deposit/views/admin-deposits.php';
