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
    foreach ([
        'policy_default_status' => 20,
        'policy_default_min_amount' => 30,
        'policy_default_max_amount' => 30,
        'policy_default_rounding_mode' => 20,
        'policy_default_fee_trigger' => 20,
        'policy_default_fee_basis' => 20,
        'policy_default_fee_type' => 20,
        'policy_default_fee_rate_numerator' => 30,
        'policy_default_fee_fixed_amount' => 30,
        'policy_default_fee_min_amount' => 30,
        'policy_default_fee_max_amount' => 30,
    ] as $settingKey => $maxLength) {
        $postedSettings[$settingKey] = sr_post_string($settingKey, $maxLength);
    }

    try {
        $beforeSettings = sr_asset_exchange_settings($pdo);
        sr_asset_exchange_save_settings($pdo, $postedSettings);
        $settings = sr_asset_exchange_settings($pdo);

        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'asset_exchange.policies.updated',
            'target_type' => 'module',
            'target_id' => 'asset_exchange',
            'result' => 'success',
            'message' => 'Asset exchange policy settings updated.',
            'metadata' => [
                'before' => [
                    'relative_value_point' => (string) ($beforeSettings['relative_value_point'] ?? '1'),
                    'relative_value_reward' => (string) ($beforeSettings['relative_value_reward'] ?? '1'),
                    'relative_value_deposit' => (string) ($beforeSettings['relative_value_deposit'] ?? '1'),
                    'policy_default_status' => (string) ($beforeSettings['policy_default_status'] ?? 'disabled'),
                    'policy_default_min_amount' => (string) ($beforeSettings['policy_default_min_amount'] ?? '1'),
                    'policy_default_max_amount' => (string) ($beforeSettings['policy_default_max_amount'] ?? ''),
                    'policy_default_rounding_mode' => (string) ($beforeSettings['policy_default_rounding_mode'] ?? 'floor'),
                    'policy_default_fee_trigger' => (string) ($beforeSettings['policy_default_fee_trigger'] ?? 'none'),
                    'policy_default_fee_basis' => (string) ($beforeSettings['policy_default_fee_basis'] ?? 'to_amount'),
                    'policy_default_fee_type' => (string) ($beforeSettings['policy_default_fee_type'] ?? 'rate'),
                    'policy_default_fee_rate_numerator' => (string) ($beforeSettings['policy_default_fee_rate_numerator'] ?? '0'),
                    'policy_default_fee_fixed_amount' => (string) ($beforeSettings['policy_default_fee_fixed_amount'] ?? '0'),
                    'policy_default_fee_min_amount' => (string) ($beforeSettings['policy_default_fee_min_amount'] ?? ''),
                    'policy_default_fee_max_amount' => (string) ($beforeSettings['policy_default_fee_max_amount'] ?? ''),
                ],
                'after' => [
                    'relative_value_point' => (string) ($settings['relative_value_point'] ?? '1'),
                    'relative_value_reward' => (string) ($settings['relative_value_reward'] ?? '1'),
                    'relative_value_deposit' => (string) ($settings['relative_value_deposit'] ?? '1'),
                    'policy_default_status' => (string) ($settings['policy_default_status'] ?? 'disabled'),
                    'policy_default_min_amount' => (string) ($settings['policy_default_min_amount'] ?? '1'),
                    'policy_default_max_amount' => (string) ($settings['policy_default_max_amount'] ?? ''),
                    'policy_default_rounding_mode' => (string) ($settings['policy_default_rounding_mode'] ?? 'floor'),
                    'policy_default_fee_trigger' => (string) ($settings['policy_default_fee_trigger'] ?? 'none'),
                    'policy_default_fee_basis' => (string) ($settings['policy_default_fee_basis'] ?? 'to_amount'),
                    'policy_default_fee_type' => (string) ($settings['policy_default_fee_type'] ?? 'rate'),
                    'policy_default_fee_rate_numerator' => (string) ($settings['policy_default_fee_rate_numerator'] ?? '0'),
                    'policy_default_fee_fixed_amount' => (string) ($settings['policy_default_fee_fixed_amount'] ?? '0'),
                    'policy_default_fee_min_amount' => (string) ($settings['policy_default_fee_min_amount'] ?? ''),
                    'policy_default_fee_max_amount' => (string) ($settings['policy_default_fee_max_amount'] ?? ''),
                ],
            ],
        ]);

        sr_admin_flash_result(sr_admin_action_result([], '환전 정책을 저장하고 파생 정책에 반영했습니다.'));
        sr_redirect('/admin/asset-exchange');
    } catch (Throwable $exception) {
        $message = $exception instanceof InvalidArgumentException ? $exception->getMessage() : '환전 정책 저장에 실패했습니다.';
        if (!$exception instanceof InvalidArgumentException) {
            sr_log_exception($exception, 'asset_exchange_policy_settings_save_failed');
        }
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'asset_exchange.policies.updated',
            'target_type' => 'module',
            'target_id' => 'asset_exchange',
            'result' => 'failure',
            'message' => 'Asset exchange policy settings update failed.',
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
