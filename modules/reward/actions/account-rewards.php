<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/reward/helpers.php';
if (sr_module_enabled($pdo, 'identity_verification') && is_file(SR_ROOT . '/modules/identity_verification/helpers.php')) {
    require_once SR_ROOT . '/modules/identity_verification/helpers.php';
}

$account = sr_member_require_login($pdo);
sr_member_group_evaluate_account($pdo, (int) $account['id']);
if (sr_reward_usage_enabled($pdo)) {
    sr_reward_expire_due_account_transactions($pdo, (int) $account['id']);
}
$rewardDisplayName = sr_reward_display_name($pdo);
$rewardUnitLabel = sr_reward_unit_label($pdo);
$rewardAmountLabel = static function (int $amount) use ($rewardUnitLabel): string {
    return number_format($amount) . $rewardUnitLabel;
};
$rewardFlash = isset($_SESSION['sr_reward_flash']) && is_array($_SESSION['sr_reward_flash']) ? $_SESSION['sr_reward_flash'] : [];
unset($_SESSION['sr_reward_flash']);
$errors = isset($rewardFlash['errors']) && is_array($rewardFlash['errors']) ? array_values(array_map('strval', $rewardFlash['errors'])) : [];
$notice = (string) ($rewardFlash['notice'] ?? '');
$rewardWithdrawalFormValues = isset($rewardFlash['values']) && is_array($rewardFlash['values']) ? $rewardFlash['values'] : [];
$rewardSettings = sr_reward_settings($pdo);
$rewardIdentityPurpose = 'reward.withdrawal_request';
$rewardIdentityRequired = !empty($rewardSettings['identity_withdrawal_required']);
$rewardIdentitySatisfied = $rewardIdentityRequired
    && function_exists('sr_identity_verification_session_result')
    && sr_identity_verification_session_result($pdo, $rewardIdentityPurpose, (int) $account['id']) !== null;
$rewardIdentityAvailable = function_exists('sr_identity_verification_available')
    && sr_identity_verification_available($pdo, $rewardIdentityPurpose);
$rewardIdentityStartUrl = $rewardIdentityAvailable && function_exists('sr_identity_verification_start_url')
    ? sr_identity_verification_start_url($rewardIdentityPurpose, '/account/rewards#reward-withdrawal-request')
    : '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $intent = sr_post_string('intent', 40);
    $rewardWithdrawalFormValues = $intent === 'withdrawal_request' ? [
        'amount' => sr_post_string('amount', 30),
        'bank_name' => sr_post_string('bank_name', 80),
        'bank_account_number' => sr_post_string('bank_account_number', 80),
        'bank_account_holder' => sr_post_string('bank_account_holder', 80),
        'requester_note' => sr_post_string('requester_note', 255),
    ] : [];

    if ($intent === 'withdrawal_request') {
        if (!sr_reward_withdrawal_requests_enabled($pdo)) {
            $errors[] = '현재 ' . $rewardDisplayName . ' 출금 신청을 받지 않습니다.';
        } elseif (!sr_reward_account_can_request_withdrawal($pdo, (int) $account['id'])) {
            $errors[] = $rewardDisplayName . ' 출금 신청이 가능한 회원 그룹에 속해 있지 않습니다.';
        }
        if ($rewardIdentityRequired && !$rewardIdentitySatisfied) {
            $errors[] = $rewardIdentityStartUrl !== ''
                ? $rewardDisplayName . ' 출금 신청 전 본인확인을 완료해 주세요.'
                : '본인확인 기능이 준비되지 않아 출금 신청을 진행할 수 없습니다.';
        }
        $amountInput = (string) ($rewardWithdrawalFormValues['amount'] ?? '');
        if (preg_match('/\A\d+\z/', $amountInput) !== 1) {
            $errors[] = '출금 신청 금액은 양의 정수로 입력하세요.';
        }
        $amount = (int) $amountInput;
        if ($amount < sr_reward_withdrawal_min_amount()) {
            $errors[] = '출금 신청 금액은 최소 ' . $rewardAmountLabel(sr_reward_withdrawal_min_amount()) . ' 이상이어야 합니다.';
        }
        if ($amount > sr_reward_withdrawal_max_amount()) {
            $errors[] = '출금 신청 금액은 최대 ' . $rewardAmountLabel(sr_reward_withdrawal_max_amount()) . '을 초과할 수 없습니다.';
        }

        if ($errors === []) {
            try {
                $requestId = sr_reward_create_withdrawal_request($pdo, (int) $account['id'], [
                    'amount' => $amount,
                    'bank_name' => (string) ($rewardWithdrawalFormValues['bank_name'] ?? ''),
                    'bank_account_number' => (string) ($rewardWithdrawalFormValues['bank_account_number'] ?? ''),
                    'bank_account_holder' => (string) ($rewardWithdrawalFormValues['bank_account_holder'] ?? ''),
                    'requester_note' => (string) ($rewardWithdrawalFormValues['requester_note'] ?? ''),
                ]);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'member',
                    'event_type' => 'reward.withdrawal_request.created',
                    'target_type' => 'reward_withdrawal_request',
                    'target_id' => (string) $requestId,
                    'result' => 'success',
                    'message' => 'Reward withdrawal request created.',
                    'metadata' => ['amount' => $amount],
                ]);
                if (function_exists('sr_identity_verification_consume_session_result')) {
                    sr_identity_verification_consume_session_result($pdo, $rewardIdentityPurpose, (int) $account['id']);
                }
                $_SESSION['sr_reward_flash'] = ['notice' => $rewardDisplayName . ' 출금 신청을 접수했습니다.', 'errors' => []];
                sr_redirect('/account/rewards');
            } catch (Throwable $exception) {
                if ($exception->getMessage() === 'Reward withdrawal amount exceeds available balance.') {
                    $errors[] = '신청 가능 ' . $rewardDisplayName . ' 잔액을 초과했습니다.';
                } elseif ($exception->getMessage() === 'Reward withdrawal bank fields are required.') {
                    $errors[] = '은행명, 계좌번호, 예금주를 모두 입력하세요.';
                } elseif ($exception->getMessage() === 'Reward usage is disabled.') {
                    $errors[] = '현재 ' . $rewardDisplayName . '을 사용하지 않습니다.';
                } elseif ($exception->getMessage() === 'Reward withdrawal requests are disabled.') {
                    $errors[] = '현재 ' . $rewardDisplayName . ' 출금 신청을 받지 않습니다.';
                } elseif ($exception->getMessage() === 'Reward withdrawal account is not in an allowed group.') {
                    $errors[] = $rewardDisplayName . ' 출금 신청이 가능한 회원 그룹에 속해 있지 않습니다.';
                } else {
                    sr_log_exception($exception, 'reward_withdrawal_request_create');
                    $errors[] = '출금 신청 접수 중 오류가 발생했습니다.';
                }
            }
        }
    } elseif ($intent === 'cancel_withdrawal_request') {
        $requestId = (int) sr_post_string('request_id', 20);
        try {
            sr_reward_cancel_withdrawal_request($pdo, $requestId, (int) $account['id']);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'member',
                'event_type' => 'reward.withdrawal_request.canceled',
                'target_type' => 'reward_withdrawal_request',
                'target_id' => (string) $requestId,
                'result' => 'success',
                'message' => 'Reward withdrawal request canceled.',
            ]);
            $_SESSION['sr_reward_flash'] = ['notice' => $rewardDisplayName . ' 출금 신청을 취소했습니다.', 'errors' => []];
            sr_redirect('/account/rewards');
        } catch (Throwable $exception) {
            $errors[] = '취소할 수 있는 출금 신청을 찾지 못했습니다.';
        }
    } else {
        $errors[] = '요청 유형이 올바르지 않습니다.';
    }
    if ($errors !== []) {
        $_SESSION['sr_reward_flash'] = ['notice' => '', 'errors' => $errors, 'values' => $rewardWithdrawalFormValues];
        sr_redirect('/account/rewards' . ($intent === 'withdrawal_request' ? '#reward-withdrawal-request' : ''));
    }
}

$balance = sr_reward_balance($pdo, (int) $account['id']);
$pendingWithdrawalAmount = sr_reward_pending_withdrawal_amount($pdo, (int) $account['id']);
$availableWithdrawalAmount = max(0, $balance - $pendingWithdrawalAmount);
$withdrawalAllowedGroupKeys = sr_reward_withdrawal_allowed_group_keys($pdo);
$withdrawalRequestsEnabled = sr_reward_withdrawal_requests_enabled($pdo);
$canRequestWithdrawal = sr_reward_account_can_request_withdrawal($pdo, (int) $account['id']);
$withdrawalRequests = sr_reward_withdrawal_requests_for_account($pdo, (int) $account['id']);
$stmt = $pdo->prepare(
    'SELECT id, amount, balance_after, transaction_type, reason, reference_type, reference_id, expires_at, expires_remaining, expired_at, created_at
     FROM sr_reward_transactions
     WHERE account_id = :account_id
     ORDER BY id DESC
     LIMIT 100'
);
$stmt->execute(['account_id' => (int) $account['id']]);
$transactions = $stmt->fetchAll();

include SR_ROOT . '/modules/reward/views/account-rewards.php';
