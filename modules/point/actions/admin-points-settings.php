<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/member/helpers/groups.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers/asset-group-policies.php';
require_once SR_ROOT . '/modules/point/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/points/settings', 'view');

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_point_settings($pdo);
$memberGroups = sr_member_groups($pdo);
$manualAdjustGroupPolicies = sr_admin_asset_group_policies_from_json((string) ($settings['manual_adjust_group_policies_json'] ?? ''));

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/points/settings', 'edit');

    $postedSettings = [
        'display_name' => sr_point_clean_text(sr_post_string('display_name', 80), 40),
        'unit_label' => sr_point_clean_text(sr_post_string('unit_label', 40), 20),
    ];
    $settings = array_merge($settings, $postedSettings);

    if ($postedSettings['display_name'] === '') {
        $errors[] = sr_t('point::action.admin.settings.display_name_required');
    }

    $manualAdjustGroupPolicies = sr_admin_asset_group_policies_from_post('manual_adjust_group_policies');
    $postedSettings['manual_adjust_group_policies_json'] = sr_admin_asset_group_policy_json_from_value($manualAdjustGroupPolicies);
    $settings['manual_adjust_group_policies_json'] = $postedSettings['manual_adjust_group_policies_json'];
    $errors = array_merge($errors, sr_admin_asset_group_policy_validation_errors($pdo, $manualAdjustGroupPolicies, $settings['display_name']));

    if ($errors === []) {
        try {
            sr_point_save_settings($pdo, $postedSettings);
            $settings = sr_point_settings($pdo);

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'point.settings.updated',
                'target_type' => 'module',
                'target_id' => 'point',
                'result' => 'success',
                'message' => 'Point settings updated.',
                'metadata' => [
                    'display_name' => (string) $settings['display_name'],
                    'unit_label' => (string) $settings['unit_label'],
                    'manual_adjust_group_policies_json' => (string) $settings['manual_adjust_group_policies_json'],
                ],
            ]);

            sr_admin_flash_result(sr_admin_action_result([], sr_t('point::action.admin.settings.saved')));
            sr_redirect('/admin/points/settings');
        } catch (Throwable $exception) {
            if ($exception->getMessage() === 'Point display name is required.') {
                $errors[] = sr_t('point::action.admin.settings.display_name_required');
            } else {
                sr_log_exception($exception, 'point_settings_save_failed');
                $errors[] = sr_t('point::action.admin.settings.save_failed');
            }
        }
    }
}

include SR_ROOT . '/modules/point/views/admin-settings.php';
