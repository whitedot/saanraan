<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/asset_exchange/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/asset-exchange/settings', 'view');

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_asset_exchange_settings($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/asset-exchange/settings', 'edit');

    $postedSettings = [
        'policy_default_status' => sr_post_string('policy_default_status', 20),
        'policy_default_rate_ratio' => sr_post_string('policy_default_rate_ratio', 80),
        'policy_default_min_amount' => sr_post_string('policy_default_min_amount', 30),
        'policy_default_max_amount' => sr_post_string('policy_default_max_amount', 30),
        'policy_default_rounding_mode' => sr_post_string('policy_default_rounding_mode', 20),
        'policy_default_fee_trigger' => sr_post_string('policy_default_fee_trigger', 20),
        'policy_default_fee_basis' => sr_post_string('policy_default_fee_basis', 20),
        'policy_default_fee_type' => sr_post_string('policy_default_fee_type', 20),
        'policy_default_fee_rate_numerator' => sr_post_string('policy_default_fee_rate_numerator', 30),
        'policy_default_fee_fixed_amount' => sr_post_string('policy_default_fee_fixed_amount', 30),
        'policy_default_fee_min_amount' => sr_post_string('policy_default_fee_min_amount', 30),
        'policy_default_fee_max_amount' => sr_post_string('policy_default_fee_max_amount', 30),
        'policy_default_sort_order' => sr_post_string('policy_default_sort_order', 30),
    ];
    $settings = array_merge($settings, $postedSettings);

    try {
        $beforeSettings = sr_asset_exchange_settings($pdo);
        sr_asset_exchange_save_settings($pdo, $postedSettings);
        $settings = sr_asset_exchange_settings($pdo);

        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'asset_exchange.settings.updated',
            'target_type' => 'module',
            'target_id' => 'asset_exchange',
            'result' => 'success',
            'message' => 'Asset exchange settings updated.',
            'metadata' => [
                'before' => $beforeSettings,
                'after' => $settings,
                'policy_update_applied' => false,
            ],
        ]);

        sr_admin_flash_result(sr_admin_action_result([], '환전 환경설정을 저장했습니다. 기존 정책은 변경하지 않았습니다.'));
        sr_redirect('/admin/asset-exchange/settings');
    } catch (Throwable $exception) {
        $message = $exception instanceof InvalidArgumentException ? $exception->getMessage() : '환전 환경설정 저장에 실패했습니다.';
        if (!$exception instanceof InvalidArgumentException) {
            sr_log_exception($exception, 'asset_exchange_settings_save_failed');
        }
        $errors[] = $message;
    }
}

include SR_ROOT . '/modules/asset_exchange/views/admin-asset-exchange-settings.php';
