<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/asset_exchange/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/asset-exchange', 'view');

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$assets = sr_asset_exchange_assets($pdo);
$settings = sr_asset_exchange_settings($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/asset-exchange', 'edit');

    $postedSettings = $settings;
    foreach (sr_asset_exchange_relative_value_setting_keys() as $settingKey) {
        $postedSettings[$settingKey] = sr_post_string($settingKey, 30);
    }

    try {
        $beforeSettings = sr_asset_exchange_settings($pdo);
        sr_asset_exchange_save_settings($pdo, $postedSettings);
        $settings = sr_asset_exchange_settings($pdo);

        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'asset_exchange.relative_values.updated',
            'target_type' => 'module',
            'target_id' => 'asset_exchange',
            'result' => 'success',
            'message' => 'Asset exchange relative values updated.',
            'metadata' => [
                'before' => [
                    'relative_value_point' => (string) ($beforeSettings['relative_value_point'] ?? '1'),
                    'relative_value_reward' => (string) ($beforeSettings['relative_value_reward'] ?? '1'),
                    'relative_value_deposit' => (string) ($beforeSettings['relative_value_deposit'] ?? '1'),
                ],
                'after' => [
                    'relative_value_point' => (string) ($settings['relative_value_point'] ?? '1'),
                    'relative_value_reward' => (string) ($settings['relative_value_reward'] ?? '1'),
                    'relative_value_deposit' => (string) ($settings['relative_value_deposit'] ?? '1'),
                ],
            ],
        ]);

        sr_admin_flash_result(sr_admin_action_result([], '환전 기준값을 저장했습니다.'));
        sr_redirect('/admin/asset-exchange');
    } catch (Throwable $exception) {
        $message = $exception instanceof InvalidArgumentException ? $exception->getMessage() : '환전 기준값 저장에 실패했습니다.';
        if (!$exception instanceof InvalidArgumentException) {
            sr_log_exception($exception, 'asset_exchange_relative_values_save_failed');
        }
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'asset_exchange.relative_values.updated',
            'target_type' => 'module',
            'target_id' => 'asset_exchange',
            'result' => 'failure',
            'message' => 'Asset exchange relative value update failed.',
            'metadata' => [
                'reason' => sr_asset_exchange_clean_text($message, 255),
            ],
        ]);

        sr_admin_flash_result(sr_admin_action_result([$message], ''));
        sr_redirect('/admin/asset-exchange');
    }
}

$relativeValues = sr_asset_exchange_relative_values_from_settings($settings);
$policyPreviews = sr_asset_exchange_canonical_policy_rows_from_settings($settings);

include SR_ROOT . '/modules/asset_exchange/views/admin-asset-exchange.php';
