<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/member/helpers/groups.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers/asset-group-policies.php';
require_once SR_ROOT . '/modules/deposit/helpers.php';

if (sr_request_method() === 'GET' && sr_request_path() === '/admin/deposits') {
    sr_redirect('/admin/deposits/balances');
}

$account = sr_member_require_login($pdo);

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
    $groupPolicySnapshot = null;
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

    if ($errors === [] && $transactionType !== 'refund') {
        try {
            $settings = sr_deposit_settings($pdo);
            $manualAdjustGroupPolicies = sr_admin_asset_group_policies_from_json((string) ($settings['manual_adjust_group_policies_json'] ?? ''));
            $groupPolicySnapshot = sr_admin_asset_group_policy_apply($pdo, $targetAccountId, $baseAmount, $manualAdjustGroupPolicies);
            $amount = (int) ($groupPolicySnapshot['final_amount'] ?? $baseAmount);
            if ($amount === 0) {
                $errors[] = '회원 그룹 정책 적용 결과가 0입니다. 수동 조정 거래는 0으로 저장할 수 없습니다.';
            } elseif (!sr_deposit_transaction_type_allows_amount($transactionType, $amount)) {
                $errors[] = sr_t('deposit::action.admin.amount_sign_invalid');
            }
        } catch (InvalidArgumentException) {
            $errors[] = '수동 조정 회원 그룹 정책 설정이 올바르지 않습니다. 예치금 환경설정을 확인하세요.';
        }
    }

    if ($errors === [] && $transactionType === 'refund' && $referenceType === 'refund' && preg_match('/\Adeposit_transaction:([0-9]+)\z/', $referenceId, $matches) === 1) {
        $stmt = $pdo->prepare('SELECT transaction_type FROM sr_deposit_transactions WHERE id = :id AND account_id = :account_id LIMIT 1');
        $stmt->execute([
            'id' => (int) $matches[1],
            'account_id' => $targetAccountId,
        ]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            $errors[] = sr_t('deposit::action.admin.refund_original_not_found');
        } elseif ((string) ($row['transaction_type'] ?? '') === 'refund') {
            $errors[] = sr_t('deposit::action.admin.refund_again_disallowed');
        }
    }

    if ($errors === []) {
        try {
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
                    'group_policy_snapshot' => $groupPolicySnapshot,
                ],
            ]);

            sr_admin_flash_result(sr_admin_action_result([], sr_t('deposit::action.admin.transaction_saved')));
            $redirectIdentifier = sr_admin_member_public_hash($runtimeConfig, $targetAccountId);
            $redirectPath = $depositAdminPage === 'transactions' ? '/admin/deposits/transactions' : '/admin/deposits/balances';
            sr_redirect($redirectPath . '?account_identifier=' . rawurlencode($redirectIdentifier));
        } catch (Throwable $exception) {
            if ($exception->getMessage() === 'Deposit balance cannot be negative.') {
                $errors[] = sr_t('deposit::action.admin.balance_negative');
            } elseif ($exception->getMessage() === 'Deposit transaction amount sign is invalid for type.') {
                $errors[] = sr_t('deposit::action.admin.amount_sign_mismatch');
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
    $stmt = $pdo->prepare('SELECT id, email, display_name, status FROM sr_member_accounts WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $accountIdFilter]);
    $row = $stmt->fetch();
    if (is_array($row)) {
        $selectedAccount = sr_admin_member_row_with_public_hash($runtimeConfig, $row);
        $accountIdentifierFilter = (string) $selectedAccount['account_public_hash'];
        $selectedBalance = sr_deposit_balance($pdo, $accountIdFilter);
    }
}

$balances = [];
$balanceSort = sr_admin_sort_from_request(sr_admin_asset_balance_sort_options(), sr_admin_asset_balance_default_sort());
$balancePagination = sr_admin_pagination_from_total($pdo, 0);
if ($depositAdminPage === 'balances') {
    $stmt = $pdo->query(
        'SELECT COUNT(*) AS count_value
         FROM sr_deposit_balances b
         INNER JOIN sr_member_accounts a ON a.id = b.account_id'
    );
    $balanceCountRow = $stmt->fetch();
    $balancePagination = sr_admin_pagination_from_total($pdo, is_array($balanceCountRow) ? (int) ($balanceCountRow['count_value'] ?? 0) : 0);
    $stmt = $pdo->query(
        'SELECT b.account_id, b.balance, b.updated_at, a.email, a.display_name, a.status
         FROM sr_deposit_balances b
         INNER JOIN sr_member_accounts a ON a.id = b.account_id
         ' . sr_admin_sort_order_sql(sr_admin_asset_balance_sort_options(), $balanceSort, sr_admin_asset_balance_default_sort()) . '
         LIMIT ' . (int) $balancePagination['per_page'] . ' OFFSET ' . sr_admin_pagination_offset($balancePagination)
    );
    foreach ($stmt->fetchAll() as $row) {
        $balances[] = sr_admin_member_row_with_public_hash($runtimeConfig, $row);
    }
}

$transactions = [];
$transactionSort = sr_admin_sort_from_request(sr_admin_asset_transaction_sort_options(), sr_admin_asset_transaction_default_sort());
$transactionPagination = sr_admin_pagination_from_total($pdo, 0);
if ($depositAdminPage === 'transactions') {
    if ($accountIdFilter > 0) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS count_value
             FROM sr_deposit_transactions t
             INNER JOIN sr_member_accounts a ON a.id = t.account_id
             WHERE t.account_id = :account_id'
        );
        $stmt->execute(['account_id' => $accountIdFilter]);
        $transactionCountRow = $stmt->fetch();
        $transactionPagination = sr_admin_pagination_from_total($pdo, is_array($transactionCountRow) ? (int) ($transactionCountRow['count_value'] ?? 0) : 0);
        $stmt = $pdo->prepare(
            'SELECT t.id, t.account_id, t.amount, t.balance_after, t.transaction_type, t.reason, t.reference_type, t.reference_id, t.created_by_account_id, t.created_at,
                    a.email, a.display_name
             FROM sr_deposit_transactions t
             INNER JOIN sr_member_accounts a ON a.id = t.account_id
             WHERE t.account_id = :account_id
             ' . sr_admin_sort_order_sql(sr_admin_asset_transaction_sort_options(), $transactionSort, sr_admin_asset_transaction_default_sort()) . '
             LIMIT :limit_value OFFSET :offset_value'
        );
        $stmt->bindValue('account_id', $accountIdFilter, PDO::PARAM_INT);
        $stmt->bindValue('limit_value', (int) $transactionPagination['per_page'], PDO::PARAM_INT);
        $stmt->bindValue('offset_value', sr_admin_pagination_offset($transactionPagination), PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->query(
            'SELECT COUNT(*) AS count_value
             FROM sr_deposit_transactions t
             INNER JOIN sr_member_accounts a ON a.id = t.account_id'
        );
        $transactionCountRow = $stmt->fetch();
        $transactionPagination = sr_admin_pagination_from_total($pdo, is_array($transactionCountRow) ? (int) ($transactionCountRow['count_value'] ?? 0) : 0);
        $stmt = $pdo->query(
            'SELECT t.id, t.account_id, t.amount, t.balance_after, t.transaction_type, t.reason, t.reference_type, t.reference_id, t.created_by_account_id, t.created_at,
                    a.email, a.display_name
             FROM sr_deposit_transactions t
             INNER JOIN sr_member_accounts a ON a.id = t.account_id
             ' . sr_admin_sort_order_sql(sr_admin_asset_transaction_sort_options(), $transactionSort, sr_admin_asset_transaction_default_sort()) . '
             LIMIT ' . (int) $transactionPagination['per_page'] . ' OFFSET ' . sr_admin_pagination_offset($transactionPagination)
        );
    }
    foreach ($stmt->fetchAll() as $row) {
        $transactions[] = sr_admin_member_row_with_public_hash($runtimeConfig, $row);
    }
}

include SR_ROOT . '/modules/deposit/views/admin-deposits.php';
