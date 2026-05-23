<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/reward/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/rewards/transactions', 'view');

$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$allowedReferenceTypes = ['', 'order', 'payment', 'refund', 'support_ticket', 'event', 'migration'];

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'items' => sr_admin_asset_reference_search($pdo, $runtimeConfig, [
        'table' => 'sr_reward_transactions',
        'allowed_types' => $allowedReferenceTypes,
        'type' => sr_reward_clean_key(sr_get_string('reference_type', 60), 60),
        'keyword' => sr_get_string('q', 120),
        'limit' => 20,
    ]),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
sr_finish_response();
