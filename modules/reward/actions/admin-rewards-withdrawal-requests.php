<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/reward/helpers.php';

$account = sr_member_require_login($pdo);
$permissionPath = '/admin/rewards/withdrawal-requests';
sr_admin_require_permission($pdo, (int) $account['id'], $permissionPath, 'view');
$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], $permissionPath, 'edit');

    $requestId = (int) sr_post_string('request_id', 20);
    $intent = sr_post_string('intent', 20);
    $adminNote = sr_reward_clean_text(sr_post_string('admin_note', 255), 255);

    if ($requestId <= 0) {
        $errors[] = '출금 신청 번호가 올바르지 않습니다.';
    }
    if (!in_array($intent, ['complete', 'reject'], true)) {
        $errors[] = '처리 유형이 올바르지 않습니다.';
    }
    if ($adminNote === '') {
        $errors[] = '처리 메모를 입력하세요.';
    }

    if ($errors === []) {
        try {
            if ($intent === 'complete') {
                $transactionId = sr_reward_complete_withdrawal_request($pdo, $requestId, (int) $account['id'], $adminNote);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'reward.withdrawal_request.completed',
                    'target_type' => 'reward_withdrawal_request',
                    'target_id' => (string) $requestId,
                    'result' => 'success',
                    'message' => 'Reward withdrawal request completed.',
                    'metadata' => ['transaction_id' => $transactionId],
                ]);
                $notice = '적립금 출금 신청을 완료 처리했습니다.';
            } else {
                sr_reward_reject_withdrawal_request($pdo, $requestId, (int) $account['id'], $adminNote);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'reward.withdrawal_request.rejected',
                    'target_type' => 'reward_withdrawal_request',
                    'target_id' => (string) $requestId,
                    'result' => 'success',
                    'message' => 'Reward withdrawal request rejected.',
                ]);
                $notice = '적립금 출금 신청을 반려했습니다.';
            }
            sr_admin_flash_result(sr_admin_action_result([], $notice));
            sr_redirect('/admin/rewards/withdrawal-requests');
        } catch (Throwable $exception) {
            if ($exception->getMessage() === 'Reward balance cannot be negative.') {
                $errors[] = '출금 처리 중 잔액이 부족합니다.';
            } else {
                sr_log_exception($exception, 'reward_withdrawal_request_admin');
                $errors[] = '출금 신청 처리 중 오류가 발생했습니다.';
            }
        }
    }
}

$statusFilter = sr_get_string('status', 20);
if (!in_array($statusFilter, ['', 'pending', 'completed', 'rejected', 'canceled'], true)) {
    $statusFilter = '';
}
$pagination = sr_admin_pagination_from_total($pdo, sr_reward_admin_withdrawal_request_count($pdo, $statusFilter));
$requests = sr_reward_admin_withdrawal_request_rows($pdo, $runtimeConfig, $statusFilter, $pagination);
$adminPageTitle = '적립금 출금 신청';

include SR_ROOT . '/modules/reward/views/admin-withdrawal-requests.php';
