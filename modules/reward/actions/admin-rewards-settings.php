<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/member/helpers/groups.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers/asset-group-policies.php';
require_once SR_ROOT . '/modules/reward/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/rewards/settings', 'view');

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_reward_settings($pdo);
$memberGroups = sr_member_groups($pdo);
$manualAdjustGroupPolicies = sr_admin_asset_group_policies_from_json((string) ($settings['manual_adjust_group_policies_json'] ?? ''));

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/rewards/settings', 'edit');

    $manualAdjustGroupPolicies = sr_admin_asset_group_policies_from_post('manual_adjust_group_policies');
    $postedSettings = [
        'manual_adjust_group_policies_json' => sr_admin_asset_group_policy_json_from_value($manualAdjustGroupPolicies),
    ];
    $settings = array_merge($settings, $postedSettings);
    $errors = array_merge($errors, sr_admin_asset_group_policy_validation_errors($pdo, $manualAdjustGroupPolicies, '적립금', false, [], ['fixed', 'multiplier', 'delta']));

    if ($errors === []) {
        try {
            sr_reward_save_settings($pdo, $postedSettings);
            $settings = sr_reward_settings($pdo);

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'reward.settings.updated',
                'target_type' => 'module',
                'target_id' => 'reward',
                'result' => 'success',
                'message' => 'Reward settings updated.',
                'metadata' => [
                    'manual_adjust_group_policies_json' => (string) $settings['manual_adjust_group_policies_json'],
                ],
            ]);

            sr_admin_flash_result(sr_admin_action_result([], '적립금 환경설정을 저장했습니다.'));
            sr_redirect('/admin/rewards/settings');
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'reward_settings_save_failed');
            $errors[] = '적립금 환경설정 저장 중 오류가 발생했습니다.';
        }
    }
}

include SR_ROOT . '/modules/reward/views/admin-settings.php';
