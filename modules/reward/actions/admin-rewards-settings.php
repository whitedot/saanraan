<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/reward/helpers.php';

$account = sr_member_require_login($pdo);
$permissionPath = '/admin/rewards/settings';
sr_admin_require_permission($pdo, (int) $account['id'], $permissionPath, 'view');

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_reward_settings($pdo);
$memberGroups = sr_member_groups($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], $permissionPath, 'edit');

    $withdrawalRequestsEnabled = sr_post_string('withdrawal_requests_enabled', 1) === '1';
    $postedGroupKeys = $_POST['withdrawal_allowed_group_keys'] ?? [];
    $allowedGroupKeys = sr_reward_normalize_group_keys(is_array($postedGroupKeys) ? $postedGroupKeys : []);
    if ($withdrawalRequestsEnabled && $allowedGroupKeys === []) {
        $errors[] = '출금 신청을 사용하려면 출금 신청 허용 대상을 선택하세요.';
    }
    $enabledGroupKeys = [];
    foreach ($memberGroups as $group) {
        if ((string) ($group['status'] ?? '') === 'enabled') {
            $enabledGroupKeys[(string) $group['group_key']] = true;
        }
    }
    foreach ($allowedGroupKeys as $groupKey) {
        if ($groupKey === sr_reward_withdrawal_all_members_key()) {
            continue;
        }
        if (!isset($enabledGroupKeys[$groupKey])) {
            $errors[] = '출금 가능 회원 그룹 선택값이 올바르지 않습니다.';
            break;
        }
    }

    if ($errors === []) {
        try {
            sr_reward_save_settings($pdo, [
                'withdrawal_requests_enabled' => $withdrawalRequestsEnabled,
                'withdrawal_allowed_group_keys' => $allowedGroupKeys,
            ]);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'reward.settings.updated',
                'target_type' => 'module',
                'target_id' => 'reward',
                'result' => 'success',
                'message' => 'Reward settings updated.',
                'metadata' => [
                    'withdrawal_requests_enabled' => $withdrawalRequestsEnabled,
                    'withdrawal_allowed_group_keys' => $allowedGroupKeys,
                ],
            ]);
            sr_admin_flash_result(sr_admin_action_result([], '적립금 환경설정을 저장했습니다.'));
            sr_redirect('/admin/rewards/settings');
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'reward_settings_save_failed');
            $errors[] = '적립금 환경설정 저장 중 오류가 발생했습니다.';
        }
    }

    sr_admin_flash_result(sr_admin_action_result($errors, ''));
    sr_redirect($permissionPath);
}

$adminPageTitle = '적립금 환경설정';

include SR_ROOT . '/modules/reward/views/admin-settings.php';
