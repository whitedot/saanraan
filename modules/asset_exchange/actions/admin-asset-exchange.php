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
$editPolicy = null;

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/asset-exchange', 'edit');

    $policyInput = [
        'id' => sr_post_string('id', 30),
        'from_module_key' => sr_post_string('from_module_key', 40),
        'to_module_key' => sr_post_string('to_module_key', 40),
        'status' => sr_post_string('status', 20),
        'rate_numerator' => sr_post_string('rate_numerator', 30),
        'rate_denominator' => sr_post_string('rate_denominator', 30),
        'min_amount' => sr_post_string('min_amount', 30),
        'max_amount' => sr_post_string('max_amount', 30),
        'rounding_mode' => sr_post_string('rounding_mode', 20),
        'fee_trigger' => sr_post_string('fee_trigger', 20),
        'fee_basis' => sr_post_string('fee_basis', 20),
        'fee_rate_numerator' => sr_post_string('fee_rate_numerator', 30),
        'fee_rate_denominator' => sr_post_string('fee_rate_denominator', 30),
        'fee_fixed_amount' => sr_post_string('fee_fixed_amount', 30),
        'fee_min_amount' => sr_post_string('fee_min_amount', 30),
        'fee_max_amount' => sr_post_string('fee_max_amount', 30),
        'sort_order' => sr_post_string('sort_order', 30),
    ];

    try {
        $policyId = sr_asset_exchange_save_policy($pdo, $policyInput);

        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'asset_exchange.policy.saved',
            'target_type' => 'asset_exchange_policy',
            'target_id' => (string) $policyId,
            'result' => 'success',
            'message' => 'Asset exchange policy saved.',
            'metadata' => [
                'policy_id' => $policyId,
            ],
        ]);

        sr_admin_flash_result(sr_admin_action_result([], '환전 정책을 저장했습니다.'));
        sr_redirect('/admin/asset-exchange');
    } catch (Throwable $exception) {
        $message = $exception instanceof InvalidArgumentException ? $exception->getMessage() : '환전 정책 저장에 실패했습니다.';
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'asset_exchange.policy.saved',
            'target_type' => 'asset_exchange_policy',
            'target_id' => (string) ($policyInput['id'] ?? ''),
            'result' => 'failure',
            'message' => 'Asset exchange policy save failed.',
            'metadata' => [
                'reason' => sr_asset_exchange_clean_text($message, 255),
                'from_module_key' => (string) ($policyInput['from_module_key'] ?? ''),
                'to_module_key' => (string) ($policyInput['to_module_key'] ?? ''),
                'status' => (string) ($policyInput['status'] ?? ''),
            ],
        ]);
        $errors[] = $message;
    }
}

$editPolicyId = (int) ($_GET['edit'] ?? 0);
if ($editPolicyId > 0) {
    $editPolicy = sr_asset_exchange_policy($pdo, $editPolicyId);
}
$policies = sr_asset_exchange_policies($pdo);

include SR_ROOT . '/modules/asset_exchange/views/admin-asset-exchange.php';
