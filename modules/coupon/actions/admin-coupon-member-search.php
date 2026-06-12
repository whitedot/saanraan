<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/coupons', 'view');

$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$field = sr_get_string('field', 30);
$keyword = sr_get_string('q', 120);
$limitInput = sr_get_string('limit', 10);
$limit = preg_match('/\A[1-9][0-9]*\z/', $limitInput) === 1 ? (int) $limitInput : 20;

sr_json_response([
    'items' => sr_admin_member_search_rows($pdo, $runtimeConfig, $field, $keyword, $limit),
]);
