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
$assetExchangePostedSettings = [];
$assetExchangePostedPolicies = [];

if (sr_request_method() === 'GET' && session_status() === PHP_SESSION_ACTIVE) {
    $assetExchangeFormValues = $_SESSION['sr_asset_exchange_policy_form_values'] ?? null;
    unset($_SESSION['sr_asset_exchange_policy_form_values']);
    if (is_array($assetExchangeFormValues)) {
        $assetExchangePostedSettings = isset($assetExchangeFormValues['settings']) && is_array($assetExchangeFormValues['settings'])
            ? $assetExchangeFormValues['settings']
            : [];
        $assetExchangePostedPolicies = isset($assetExchangeFormValues['policies']) && is_array($assetExchangeFormValues['policies'])
            ? $assetExchangeFormValues['policies']
            : [];
        if ($assetExchangePostedSettings !== []) {
            $settings = sr_asset_exchange_normalize_settings(array_merge($settings, $assetExchangePostedSettings));
        }
    }
}

$assetExchangePolicySnapshot = static function (?array $policy): array {
    if (!is_array($policy)) {
        return [];
    }

    $maxAmount = $policy['max_amount'] ?? null;
    $feeMinAmount = $policy['fee_min_amount'] ?? null;
    $feeMaxAmount = $policy['fee_max_amount'] ?? null;

    return [
        'id' => (string) ((int) ($policy['id'] ?? 0)),
        'from_module_key' => (string) ($policy['from_module_key'] ?? ''),
        'to_module_key' => (string) ($policy['to_module_key'] ?? ''),
        'status' => (string) ($policy['status'] ?? 'disabled'),
        'rate_numerator' => (string) ((int) ($policy['rate_numerator'] ?? 1)),
        'rate_denominator' => (string) ((int) ($policy['rate_denominator'] ?? 1)),
        'min_amount' => (string) ((int) ($policy['min_amount'] ?? 1)),
        'max_amount' => $maxAmount === null ? '' : (string) ((int) $maxAmount),
        'rounding_mode' => (string) ($policy['rounding_mode'] ?? 'floor'),
        'fee_trigger' => (string) ($policy['fee_trigger'] ?? 'none'),
        'fee_basis' => (string) ($policy['fee_basis'] ?? 'to_amount'),
        'fee_rate_numerator' => (string) ((int) ($policy['fee_rate_numerator'] ?? 0)),
        'fee_fixed_amount' => (string) ((int) ($policy['fee_fixed_amount'] ?? 0)),
        'fee_min_amount' => $feeMinAmount === null ? '' : (string) ((int) $feeMinAmount),
        'fee_max_amount' => $feeMaxAmount === null ? '' : (string) ((int) $feeMaxAmount),
    ];
};
$assetExchangePolicyDirectionLabel = static function (string $fromModuleKey, string $toModuleKey) use ($assets): string {
    return sr_asset_exchange_asset_label($assets, $fromModuleKey) . ' -> ' . sr_asset_exchange_asset_label($assets, $toModuleKey);
};
$assetExchangeFlashPostedForm = static function (array $postedSettings, array $postedPolicies): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION['sr_asset_exchange_policy_form_values'] = [
        'settings' => $postedSettings,
        'policies' => $postedPolicies,
    ];
};

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/asset-exchange', 'edit');

    $intent = sr_post_string('intent', 40);

    if ($intent === 'save_relative_values') {
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

            sr_admin_flash_result(sr_admin_action_result([], '환산 기준을 저장하고 환전 정책 비율에 반영했습니다.'));
            sr_redirect('/admin/asset-exchange');
        } catch (Throwable $exception) {
            $message = $exception instanceof InvalidArgumentException ? $exception->getMessage() : '환산 기준 저장에 실패했습니다.';
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
                'message' => 'Asset exchange relative values update failed.',
                'metadata' => [
                    'reason' => sr_asset_exchange_clean_text($message, 255),
                ],
            ]);

            sr_admin_flash_result(sr_admin_action_result([$message], ''));
            sr_redirect('/admin/asset-exchange');
        }
    }

    if ($intent === 'save_all') {
        $postedSettings = $settings;

        $postedPolicyRows = $_POST['policies'] ?? [];
        if (!is_array($postedPolicyRows)) {
            sr_admin_flash_result(sr_admin_action_result(['환전 정책 입력값이 올바르지 않습니다.'], ''));
            sr_redirect('/admin/asset-exchange');
        }

        $postedPolicies = [];
        foreach (sr_asset_exchange_policy_slots($pdo, $assets) as $slot) {
            $fromModuleKey = (string) ($slot['from_module_key'] ?? '');
            $toModuleKey = (string) ($slot['to_module_key'] ?? '');
            $slotKey = sr_asset_exchange_policy_slot_key($fromModuleKey, $toModuleKey);
            $postedRow = $postedPolicyRows[$slotKey] ?? null;
            if (!is_array($postedRow)) {
                sr_admin_flash_result(sr_admin_action_result(['모든 환전 방향의 정책 값을 제출하세요.'], ''));
                sr_redirect('/admin/asset-exchange');
            }

            $postedPolicies[$slotKey] = [
                'id' => (string) ($postedRow['id'] ?? ''),
                'from_module_key' => $fromModuleKey,
                'to_module_key' => $toModuleKey,
                'status' => (string) ($postedRow['status'] ?? 'disabled'),
                'rate_numerator' => (string) ($postedRow['rate_numerator'] ?? ''),
                'rate_denominator' => (string) ($postedRow['rate_denominator'] ?? ''),
                'min_amount' => (string) ($postedRow['min_amount'] ?? ''),
                'max_amount' => (string) ($postedRow['max_amount'] ?? ''),
                'rounding_mode' => (string) ($postedRow['rounding_mode'] ?? 'floor'),
                'fee_trigger' => (string) ($postedRow['fee_trigger'] ?? 'none'),
                'fee_basis' => (string) ($postedRow['fee_basis'] ?? 'to_amount'),
                'fee_type' => (string) ($postedRow['fee_type'] ?? 'rate'),
                'fee_rate_numerator' => (string) ($postedRow['fee_rate_numerator'] ?? ''),
                'fee_fixed_amount' => (string) ($postedRow['fee_fixed_amount'] ?? ''),
                'fee_min_amount' => (string) ($postedRow['fee_min_amount'] ?? ''),
                'fee_max_amount' => (string) ($postedRow['fee_max_amount'] ?? ''),
            ];
        }

        try {
            $beforeSettings = sr_asset_exchange_settings($pdo);
            $beforePolicies = [];
            foreach ($postedPolicies as $slotKey => $postedPolicy) {
                $beforePolicies[$slotKey] = $assetExchangePolicySnapshot(sr_asset_exchange_policy_by_slot(
                    $pdo,
                    (string) $postedPolicy['from_module_key'],
                    (string) $postedPolicy['to_module_key']
                ));
            }

            $startedTransaction = !$pdo->inTransaction();
            if ($startedTransaction) {
                $pdo->beginTransaction();
            }

            try {
                sr_asset_exchange_save_settings($pdo, $postedSettings, false);
                sr_asset_exchange_remove_noncanonical_policies($pdo);

                $now = sr_now();
                $pdo->exec(
                    "UPDATE sr_asset_exchange_policies
                     SET status = 'disabled', updated_at = " . $pdo->quote($now) . "
                     WHERE from_module_key IN ('point', 'reward', 'deposit')
                       AND to_module_key IN ('point', 'reward', 'deposit')
                       AND from_module_key <> to_module_key"
                );

                $savedPolicyIds = [];
                foreach ($postedPolicies as $slotKey => $postedPolicy) {
                    try {
                        $savedPolicyIds[$slotKey] = sr_asset_exchange_save_policy($pdo, $postedPolicy);
                    } catch (InvalidArgumentException $exception) {
                        $policyLabel = $assetExchangePolicyDirectionLabel(
                            (string) ($postedPolicy['from_module_key'] ?? ''),
                            (string) ($postedPolicy['to_module_key'] ?? '')
                        );
                        throw new InvalidArgumentException($policyLabel . ' 정책: ' . $exception->getMessage(), 0, $exception);
                    }
                }

                if ($startedTransaction) {
                    $pdo->commit();
                }
            } catch (Throwable $exception) {
                if ($startedTransaction && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            sr_clear_module_settings_cache('asset_exchange');
            $settings = sr_asset_exchange_settings($pdo);
            $afterPolicies = [];
            foreach ($savedPolicyIds as $slotKey => $policyId) {
                $afterPolicies[$slotKey] = $assetExchangePolicySnapshot(sr_asset_exchange_policy($pdo, (int) $policyId));
            }

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'asset_exchange.policies.updated',
                'target_type' => 'module',
                'target_id' => 'asset_exchange',
                'result' => 'success',
                'message' => 'Asset exchange policies updated.',
                'metadata' => [
                    'relative_values' => [
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
                    'policies' => [
                        'before' => $beforePolicies,
                        'after' => $afterPolicies,
                    ],
                ],
            ]);

            sr_admin_flash_result(sr_admin_action_result([], '환전 정책을 저장했습니다.'));
            sr_redirect('/admin/asset-exchange');
        } catch (Throwable $exception) {
            sr_clear_module_settings_cache('asset_exchange');
            $message = $exception instanceof InvalidArgumentException ? $exception->getMessage() : '환전 정책 저장에 실패했습니다.';
            if (!$exception instanceof InvalidArgumentException) {
                sr_log_exception($exception, 'asset_exchange_policies_save_failed');
            }
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'asset_exchange.policies.updated',
                'target_type' => 'module',
                'target_id' => 'asset_exchange',
                'result' => 'failure',
                'message' => 'Asset exchange policies update failed.',
                'metadata' => [
                    'reason' => sr_asset_exchange_clean_text($message, 255),
                ],
            ]);

            $assetExchangeFlashPostedForm($postedSettings, $postedPolicies);
            sr_admin_flash_result(sr_admin_action_result([$message], ''));
            sr_redirect('/admin/asset-exchange');
        }
    }

    if ($intent === 'save_policy') {
        $fromModuleKey = sr_asset_exchange_clean_module_key(sr_post_string('from_module_key', 40));
        $toModuleKey = sr_asset_exchange_clean_module_key(sr_post_string('to_module_key', 40));
        $postedPolicy = [
            'id' => sr_post_string('id', 30),
            'from_module_key' => $fromModuleKey,
            'to_module_key' => $toModuleKey,
            'status' => sr_post_string('status', 20),
            'rate_numerator' => sr_post_string('rate_numerator', 30),
            'rate_denominator' => sr_post_string('rate_denominator', 30),
            'min_amount' => sr_post_string('min_amount', 30),
            'max_amount' => sr_post_string('max_amount', 30),
            'rounding_mode' => sr_post_string('rounding_mode', 20),
            'fee_trigger' => sr_post_string('fee_trigger', 20),
            'fee_basis' => sr_post_string('fee_basis', 20),
            'fee_type' => sr_post_string('fee_type', 20),
            'fee_rate_numerator' => sr_post_string('fee_rate_numerator', 30),
            'fee_fixed_amount' => sr_post_string('fee_fixed_amount', 30),
            'fee_min_amount' => sr_post_string('fee_min_amount', 30),
            'fee_max_amount' => sr_post_string('fee_max_amount', 30),
        ];

        try {
            $beforePolicy = sr_asset_exchange_policy_by_slot($pdo, $fromModuleKey, $toModuleKey);
            $policyId = sr_asset_exchange_save_policy($pdo, $postedPolicy);
            $afterPolicy = sr_asset_exchange_policy($pdo, $policyId);

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'asset_exchange.policy.updated',
                'target_type' => 'asset_exchange_policy',
                'target_id' => $fromModuleKey . '>' . $toModuleKey,
                'result' => 'success',
                'message' => 'Asset exchange policy updated.',
                'metadata' => [
                    'before' => $assetExchangePolicySnapshot($beforePolicy),
                    'after' => $assetExchangePolicySnapshot($afterPolicy),
                ],
            ]);

            sr_admin_flash_result(sr_admin_action_result([], '환전 정책을 저장했습니다.'));
            sr_redirect('/admin/asset-exchange');
        } catch (Throwable $exception) {
            $message = $exception instanceof InvalidArgumentException ? $exception->getMessage() : '환전 정책 저장에 실패했습니다.';
            if (!$exception instanceof InvalidArgumentException) {
                sr_log_exception($exception, 'asset_exchange_policy_save_failed');
            }
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'asset_exchange.policy.updated',
                'target_type' => 'asset_exchange_policy',
                'target_id' => $fromModuleKey . '>' . $toModuleKey,
                'result' => 'failure',
                'message' => 'Asset exchange policy update failed.',
                'metadata' => [
                    'reason' => sr_asset_exchange_clean_text($message, 255),
                ],
            ]);

            sr_admin_flash_result(sr_admin_action_result([$message], ''));
            sr_redirect('/admin/asset-exchange');
        }
    }

    sr_admin_flash_result(sr_admin_action_result(['알 수 없는 환전 정책 저장 요청입니다.'], ''));
    sr_redirect('/admin/asset-exchange');
}

try {
    $relativeValues = sr_asset_exchange_relative_values_from_settings($settings);
} catch (InvalidArgumentException) {
    $relativeValues = [];
    foreach (sr_asset_exchange_relative_value_setting_keys() as $moduleKey => $settingKey) {
        $relativeValues[$moduleKey] = (string) ($settings[$settingKey] ?? '');
    }
}
$policySlots = sr_asset_exchange_policy_slots($pdo, $assets);

include SR_ROOT . '/modules/asset_exchange/views/admin-asset-exchange.php';
