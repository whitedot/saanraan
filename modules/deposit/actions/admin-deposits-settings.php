<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/member/helpers/groups.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers/asset-group-policies.php';
require_once SR_ROOT . '/modules/deposit/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/deposits/settings', 'view');

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_deposit_settings($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/deposits/settings', 'edit');

    $postedSettings = [
        'manual_adjust_group_policies_json' => sr_post_string('manual_adjust_group_policies_json', 20000),
    ];
    $settings = array_merge($settings, $postedSettings);

    try {
        $manualAdjustGroupPolicies = sr_admin_asset_group_policies_from_json((string) $postedSettings['manual_adjust_group_policies_json']);
        $postedSettings['manual_adjust_group_policies_json'] = sr_admin_asset_group_policy_json_from_value($manualAdjustGroupPolicies);
        $settings['manual_adjust_group_policies_json'] = $postedSettings['manual_adjust_group_policies_json'];
        $errors = array_merge($errors, sr_admin_asset_group_policy_validation_errors($pdo, $manualAdjustGroupPolicies, '예치금'));
    } catch (InvalidArgumentException) {
        $errors[] = '수동 조정 회원 그룹 정책 JSON이 올바르지 않습니다.';
    }

    if ($errors === []) {
        try {
            sr_deposit_save_settings($pdo, $postedSettings);
            $settings = sr_deposit_settings($pdo);

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'deposit.settings.updated',
                'target_type' => 'module',
                'target_id' => 'deposit',
                'result' => 'success',
                'message' => 'Deposit settings updated.',
                'metadata' => [
                    'manual_adjust_group_policies_json' => (string) $settings['manual_adjust_group_policies_json'],
                ],
            ]);

            sr_admin_flash_result(sr_admin_action_result([], '예치금 환경설정을 저장했습니다.'));
            sr_redirect('/admin/deposits/settings');
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'deposit_settings_save_failed');
            $errors[] = '예치금 환경설정 저장 중 오류가 발생했습니다.';
        }
    }
}

include SR_ROOT . '/modules/deposit/views/admin-settings.php';
