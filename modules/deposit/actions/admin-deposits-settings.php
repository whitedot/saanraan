<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/deposit/helpers.php';

$account = sr_member_require_login($pdo);
$permissionPath = '/admin/deposits/settings';
sr_admin_require_permission($pdo, (int) $account['id'], $permissionPath, 'view');

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_deposit_settings($pdo);
$memberGroups = sr_member_groups($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], $permissionPath, 'edit');

    $refundRequestsEnabled = sr_post_string('refund_requests_enabled', 1) === '1';
    $postedGroupKeys = $_POST['refund_allowed_group_keys'] ?? [];
    $allowedGroupKeys = sr_deposit_normalize_group_keys(is_array($postedGroupKeys) ? $postedGroupKeys : []);
    if ($refundRequestsEnabled && $allowedGroupKeys === []) {
        $errors[] = '환불 신청을 사용하려면 환불 신청 허용 대상을 선택하세요.';
    }
    $enabledGroupKeys = [];
    foreach ($memberGroups as $group) {
        if ((string) ($group['status'] ?? '') === 'enabled') {
            $enabledGroupKeys[(string) $group['group_key']] = true;
        }
    }
    foreach ($allowedGroupKeys as $groupKey) {
        if ($groupKey === sr_deposit_refund_all_members_key()) {
            continue;
        }
        if (!isset($enabledGroupKeys[$groupKey])) {
            $errors[] = '환불 신청 가능 회원 그룹 선택값이 올바르지 않습니다.';
            break;
        }
    }

    if ($errors === []) {
        try {
            sr_deposit_save_settings($pdo, [
                'refund_requests_enabled' => $refundRequestsEnabled,
                'refund_allowed_group_keys' => $allowedGroupKeys,
            ]);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'deposit.settings.updated',
                'target_type' => 'module',
                'target_id' => 'deposit',
                'result' => 'success',
                'message' => 'Deposit settings updated.',
                'metadata' => [
                    'refund_requests_enabled' => $refundRequestsEnabled,
                    'refund_allowed_group_keys' => $allowedGroupKeys,
                ],
            ]);
            sr_admin_flash_result(sr_admin_action_result([], '예치금 환경설정을 저장했습니다.'));
            sr_redirect('/admin/deposits/settings');
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'deposit_settings_save_failed');
            $errors[] = '예치금 환경설정 저장 중 오류가 발생했습니다.';
        }
    }

    $settings['refund_requests_enabled'] = $refundRequestsEnabled;
    $settings['refund_allowed_group_keys'] = $allowedGroupKeys;
}

$adminPageTitle = '예치금 환경설정';

include SR_ROOT . '/modules/deposit/views/admin-settings.php';
