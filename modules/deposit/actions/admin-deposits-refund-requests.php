<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/deposit/helpers.php';

$account = sr_member_require_login($pdo);
$permissionPath = '/admin/deposits/refund-requests';
sr_admin_require_permission($pdo, (int) $account['id'], $permissionPath, 'view');
$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

$allowedStatusFilters = ['pending', 'completed', 'rejected', 'canceled'];
$statusFilter = sr_admin_get_allowed_array('status', $allowedStatusFilters, 20);
$searchField = sr_get_string('field', 20);
if (!in_array($searchField, ['all', 'member', 'bank', 'note', 'request'], true)) {
    $searchField = 'all';
}
$searchKeyword = sr_deposit_clean_text(sr_get_string('q', 120), 120);
$requestListRedirectParams = [];
if ($statusFilter !== []) {
    $requestListRedirectParams['status'] = $statusFilter;
}
if ($searchField !== 'all') {
    $requestListRedirectParams['field'] = $searchField;
}
if ($searchKeyword !== '') {
    $requestListRedirectParams['q'] = $searchKeyword;
}
$requestListRedirectParams = sr_admin_normalize_query_params($requestListRedirectParams);
$requestListRedirectPath = '/admin/deposits/refund-requests'
    . ($requestListRedirectParams === [] ? '' : '?' . http_build_query($requestListRedirectParams, '', '&', PHP_QUERY_RFC3986));
$requestBatchLimit = 100;
$requestBatchIncludesPending = $statusFilter === [] || in_array('pending', $statusFilter, true);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], $permissionPath, 'edit');

    $requestId = (int) sr_post_string('request_id', 20);
    $intent = sr_post_string('intent', 20);
    $adminNote = sr_deposit_clean_text(sr_post_string('admin_note', 255), 255);
    $isBatchIntent = in_array($intent, ['batch_complete', 'batch_reject'], true);

    if (!$isBatchIntent && $requestId <= 0) {
        $errors[] = '환불 신청 번호가 올바르지 않습니다.';
    }
    if (!in_array($intent, ['complete', 'reject', 'batch_complete', 'batch_reject'], true)) {
        $errors[] = '처리 유형이 올바르지 않습니다.';
    }
    if ($adminNote === '') {
        $errors[] = '처리 메모를 입력하세요.';
    }

    if ($isBatchIntent && $errors === []) {
        $batchPendingCount = $requestBatchIncludesPending ? sr_deposit_admin_refund_request_count($pdo, 'pending', $searchField, $searchKeyword) : 0;
        if ($batchPendingCount < 1) {
            $errors[] = '현재 필터와 검색 조건에 맞는 대기 환불 신청이 없습니다.';
        } elseif ($batchPendingCount > $requestBatchLimit) {
            $errors[] = '일괄처리는 한 번에 ' . number_format($requestBatchLimit) . '건 이하로 검색 조건을 좁혀서 실행하세요.';
        }
    }

    if ($isBatchIntent && $errors === []) {
        $requestIds = $requestBatchIncludesPending ? sr_deposit_admin_refund_request_pending_ids($pdo, $searchField, $searchKeyword, $requestBatchLimit + 1) : [];
        if ($requestIds === []) {
            $errors[] = '현재 필터와 검색 조건에 맞는 대기 환불 신청이 없습니다.';
        } elseif (count($requestIds) > $requestBatchLimit) {
            $errors[] = '일괄처리는 한 번에 ' . number_format($requestBatchLimit) . '건 이하로 검색 조건을 좁혀서 실행하세요.';
        } else {
            $processedCount = 0;
            $batchErrors = [];
            foreach ($requestIds as $batchRequestId) {
                try {
                    if ($intent === 'batch_complete') {
                        $transactionId = sr_deposit_complete_refund_request($pdo, $batchRequestId, (int) $account['id'], $adminNote);
                        sr_audit_log($pdo, [
                            'actor_account_id' => (int) $account['id'],
                            'actor_type' => 'admin',
                            'event_type' => 'deposit.refund_request.completed',
                            'target_type' => 'deposit_refund_request',
                            'target_id' => (string) $batchRequestId,
                            'result' => 'success',
                            'message' => 'Deposit refund request completed in batch.',
                            'metadata' => ['transaction_id' => $transactionId, 'batch' => true],
                        ]);
                    } else {
                        sr_deposit_reject_refund_request($pdo, $batchRequestId, (int) $account['id'], $adminNote);
                        sr_audit_log($pdo, [
                            'actor_account_id' => (int) $account['id'],
                            'actor_type' => 'admin',
                            'event_type' => 'deposit.refund_request.rejected',
                            'target_type' => 'deposit_refund_request',
                            'target_id' => (string) $batchRequestId,
                            'result' => 'success',
                            'message' => 'Deposit refund request rejected in batch.',
                            'metadata' => ['batch' => true],
                        ]);
                    }
                    $processedCount++;
                } catch (Throwable $exception) {
                    if ($exception->getMessage() === 'Deposit balance cannot be negative.') {
                        $batchErrors[] = '환불 신청 #' . (string) $batchRequestId . ' 처리 중 잔액이 부족합니다.';
                    } elseif ($exception->getMessage() === 'Deposit refund request is not pending.') {
                        $batchErrors[] = '환불 신청 #' . (string) $batchRequestId . '은 이미 처리되어 건너뛰었습니다.';
                    } else {
                        sr_log_exception($exception, 'deposit_refund_request_batch_admin');
                        $batchErrors[] = '환불 신청 #' . (string) $batchRequestId . ' 처리 중 오류가 발생했습니다.';
                    }
                }
            }

            if ($processedCount > 0) {
                $notice = $intent === 'batch_complete'
                    ? '예치금 환불 신청 ' . number_format($processedCount) . '건을 일괄 완료 처리했습니다.'
                    : '예치금 환불 신청 ' . number_format($processedCount) . '건을 일괄 거부했습니다.';
            }
            if ($processedCount < 1 && $batchErrors === []) {
                $batchErrors[] = '처리된 환불 신청이 없습니다.';
            }
            sr_admin_flash_result(sr_admin_action_result($batchErrors, $notice));
            sr_redirect($requestListRedirectPath);
        }
    } elseif ($errors === []) {
        try {
            if ($intent === 'complete') {
                $transactionId = sr_deposit_complete_refund_request($pdo, $requestId, (int) $account['id'], $adminNote);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'deposit.refund_request.completed',
                    'target_type' => 'deposit_refund_request',
                    'target_id' => (string) $requestId,
                    'result' => 'success',
                    'message' => 'Deposit refund request completed.',
                    'metadata' => ['transaction_id' => $transactionId],
                ]);
                $notice = '예치금 환불 신청을 완료 처리했습니다.';
            } else {
                sr_deposit_reject_refund_request($pdo, $requestId, (int) $account['id'], $adminNote);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'deposit.refund_request.rejected',
                    'target_type' => 'deposit_refund_request',
                    'target_id' => (string) $requestId,
                    'result' => 'success',
                    'message' => 'Deposit refund request rejected.',
                ]);
                $notice = '예치금 환불 신청을 거부했습니다.';
            }
            sr_admin_flash_result(sr_admin_action_result([], $notice));
            sr_redirect($requestListRedirectPath);
        } catch (Throwable $exception) {
            if ($exception->getMessage() === 'Deposit balance cannot be negative.') {
                $errors[] = '환불 처리 중 잔액이 부족합니다.';
            } else {
                sr_log_exception($exception, 'deposit_refund_request_admin');
                $errors[] = '환불 신청 처리 중 오류가 발생했습니다.';
            }
        }
    }
}

$pagination = sr_admin_pagination_from_total($pdo, sr_deposit_admin_refund_request_count($pdo, $statusFilter, $searchField, $searchKeyword));
$requests = sr_deposit_admin_refund_request_rows($pdo, $runtimeConfig, $statusFilter, $pagination, $searchField, $searchKeyword);
$requestBatchPendingCount = $requestBatchIncludesPending ? sr_deposit_admin_refund_request_count($pdo, 'pending', $searchField, $searchKeyword) : 0;
$adminPageTitle = '예치금 환불 신청';

include SR_ROOT . '/modules/deposit/views/admin-refund-requests.php';
