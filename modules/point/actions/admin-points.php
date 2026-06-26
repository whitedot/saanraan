<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/point/helpers.php';

$account = sr_member_require_login($pdo);

if (sr_request_method() === 'GET' && sr_request_path() === '/admin/points') {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/points/settings', 'view');
    sr_redirect('/admin/points/settings');
}

$allowedTransactionTypes = ['adjustment', 'grant', 'use', 'refund', 'expire'];
$allowedReferenceTypes = ['', 'order', 'payment', 'refund', 'support_ticket', 'event', 'migration'];
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$pointAdminPage = isset($pointAdminPage) ? (string) $pointAdminPage : 'balances';
if (!in_array($pointAdminPage, ['balances', 'transactions'], true)) {
    $pointAdminPage = 'balances';
}
$pointPermissionPath = $pointAdminPage === 'transactions' ? '/admin/points/transactions' : '/admin/points/balances';
sr_admin_require_permission($pdo, (int) $account['id'], $pointPermissionPath, 'view');
$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$submittedAccountId = 0;
$pointDisplayName = sr_point_display_name($pdo);
$pointUnitLabel = sr_point_unit_label($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], $pointPermissionPath, 'edit');

    $intent = sr_post_string('intent', 40);
    if ($intent === 'expire_due') {
        if (sr_point_default_expiration_days($pdo) <= 0) {
            $errors[] = sr_t('point::action.admin.settings.expiration_disabled');
        } elseif (sr_post_string('expire_confirmed', 1) !== '1') {
            $errors[] = sr_t('point::action.admin.settings.expiration_confirm_required');
        }

        if ($errors === []) {
            try {
                $expirationResult = sr_point_expire_due_transactions($pdo, 1000);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'point.expiration.run',
                    'target_type' => 'module',
                    'target_id' => 'point',
                    'result' => 'success',
                    'message' => 'Point expiration run.',
                    'metadata' => [
                        'expired_count' => (int) $expirationResult['expired_count'],
                        'expired_amount' => (int) $expirationResult['expired_amount'],
                    ],
                ]);

                sr_admin_flash_result(sr_admin_action_result([], sprintf(
                    sr_t('point::action.admin.settings.expiration_run'),
                    number_format((int) $expirationResult['expired_count']),
                    number_format((int) $expirationResult['expired_amount'])
                )));
                sr_redirect('/admin/points/balances');
            } catch (Throwable $exception) {
                sr_log_exception($exception, 'point_manual_expiration_failed');
                $errors[] = sr_t('point::action.admin.settings.expiration_failed');
            }
        }

        sr_admin_flash_result(sr_admin_action_result($errors, ''));
        sr_redirect('/admin/points/balances');
    }

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
    $refundExpirationPolicy = sr_point_normalize_refund_expiration_policy(sr_post_string('refund_expiration_policy', 40));
    $approvalIdentifier = $targetAccountIdentifier;
    $approvalNote = $reason;
    $approvalAccountId = 0;
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
    $baseAmount = $amount;
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

    if ($referenceType !== '' && $referenceId === '') {
        $errors[] = sr_t('point::action.admin.reference_id_required');
    }
    if ($referenceType === '' && $referenceId !== '') {
        $errors[] = sr_t('point::action.admin.reference_type_required');
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('SELECT id FROM sr_member_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $targetAccountId]);
        if (!is_array($stmt->fetch())) {
            $errors[] = sr_t('point::action.admin.member_not_found');
        }
    }

    if ($errors === [] && $transactionType === 'refund' && ($referenceType !== 'refund' || preg_match('/\Apoint_transaction:([0-9]+)\z/', $referenceId, $matches) !== 1)) {
        $errors[] = sr_t('point::action.admin.refund_reference_required');
    }

    if ($errors === [] && $transactionType === 'refund' && $referenceType === 'refund' && preg_match('/\Apoint_transaction:([0-9]+)\z/', $referenceId, $matches) === 1) {
        $stmt = $pdo->prepare('SELECT amount, transaction_type FROM sr_point_transactions WHERE id = :id AND account_id = :account_id LIMIT 1');
        $stmt->execute([
            'id' => (int) $matches[1],
            'account_id' => $targetAccountId,
        ]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            $errors[] = sr_t('point::action.admin.refund_original_not_found');
        } elseif ((string) ($row['transaction_type'] ?? '') === 'refund') {
            $errors[] = sr_t('point::action.admin.refund_again_disallowed');
        } elseif ((int) ($row['amount'] ?? 0) >= 0) {
            $errors[] = sr_t('point::action.admin.refund_original_not_negative');
        } else {
            $refundableAmount = abs((int) ($row['amount'] ?? 0)) - sr_point_refunded_amount_for_reference($pdo, $targetAccountId, $referenceId);
            if ($amount > max(0, $refundableAmount)) {
                $errors[] = sr_t('point::action.admin.refund_amount_exceeds_remaining');
            }
        }
    }

    if ($errors === []) {
        $limitResult = sr_point_validate_admin_adjustment_limit($pdo, $runtimeConfig, (int) $account['id'], $pointPermissionPath, $amount, $approvalIdentifier, $approvalNote);
        if ($limitResult['error'] !== null) {
            $errors[] = (string) $limitResult['error'];
        }
        $approvalAccountId = (int) ($limitResult['approval_account_id'] ?? 0);
    }

    if ($errors === []) {
        try {
            $transactionData = [
                'account_id' => $targetAccountId,
                'amount' => $amount,
                'transaction_type' => $transactionType,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'refund_expiration_policy' => $refundExpirationPolicy,
                'created_by_account_id' => (int) $account['id'],
            ];
            $transactionIds = $transactionType === 'refund'
                ? sr_point_create_refund_transactions($pdo, $transactionData)
                : [sr_point_create_transaction($pdo, $transactionData)];
            $transactionId = (int) ($transactionIds[0] ?? 0);

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
                    'transaction_ids' => $transactionIds,
                    'base_amount' => $baseAmount,
                    'amount' => $amount,
                    'transaction_type' => $transactionType,
                    'refund_expiration_policy' => $transactionType === 'refund' ? $refundExpirationPolicy : '',
                    'approval_account_id' => $approvalAccountId,
                    'approval_note' => $approvalNote,
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
            } elseif ($exception->getMessage() === 'Point refund reference is required.') {
                $errors[] = sr_t('point::action.admin.refund_reference_required');
            } elseif ($exception->getMessage() === 'Point refund original transaction not found.') {
                $errors[] = sr_t('point::action.admin.refund_original_not_found');
            } elseif ($exception->getMessage() === 'Point refund transaction cannot be refunded.') {
                $errors[] = sr_t('point::action.admin.refund_again_disallowed');
            } elseif ($exception->getMessage() === 'Point refund original transaction must be negative.') {
                $errors[] = sr_t('point::action.admin.refund_original_not_negative');
            } elseif ($exception->getMessage() === 'Point refund amount exceeds remaining reference amount.') {
                $errors[] = sr_t('point::action.admin.refund_amount_exceeds_remaining');
            } else {
                $errors[] = sr_t('point::action.admin.transaction_save_failed');
            }
        }
    }

    $redirectPath = $pointAdminPage === 'transactions' ? '/admin/points/transactions' : '/admin/points/balances';
    $redirectQuery = $targetAccountIdentifier !== '' ? '?account_identifier=' . rawurlencode($targetAccountIdentifier) : '';
    sr_admin_flash_result(sr_admin_action_result($errors, ''));
    sr_redirect($redirectPath . $redirectQuery);
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
    $selectedAccountData = sr_admin_asset_selected_account($pdo, $runtimeConfig, $accountIdFilter, 'sr_point_balance');
    if (is_array($selectedAccountData['account'])) {
        $selectedAccount = $selectedAccountData['account'];
        $accountIdentifierFilter = (string) $selectedAccountData['identifier'];
        $selectedBalance = (int) $selectedAccountData['balance'];
    }
}

$balances = [];
$balanceSort = sr_admin_sort_from_request(sr_admin_asset_balance_sort_options(), sr_admin_asset_balance_default_sort());
$balancePagination = sr_admin_pagination_from_total($pdo, 0);
if ($pointAdminPage === 'balances') {
    $balancePagination = sr_admin_pagination_from_total($pdo, sr_admin_asset_balance_count($pdo, 'sr_point_balances'));
    $balances = sr_admin_asset_balance_rows($pdo, $runtimeConfig, 'sr_point_balances', $balanceSort, $balancePagination);
}

$transactions = [];
$transactionSort = sr_admin_sort_from_request(sr_admin_asset_transaction_sort_options(), sr_admin_asset_transaction_default_sort());
$transactionPagination = sr_admin_pagination_from_total($pdo, 0);
if ($pointAdminPage === 'transactions') {
    $transactionPagination = sr_admin_pagination_from_total($pdo, sr_admin_asset_transaction_count($pdo, 'sr_point_transactions', $accountIdFilter));
    $transactions = sr_point_admin_transaction_rows($pdo, $runtimeConfig, $transactionSort, $transactionPagination, $accountIdFilter);
}

include SR_ROOT . '/modules/point/views/admin-points.php';
