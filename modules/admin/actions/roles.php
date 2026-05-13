<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner']);

$allowedRoles = sr_admin_allowed_roles();
$allowedActions = sr_admin_role_actions();
$errors = [];
$notice = '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $postResult = sr_admin_handle_roles_post($pdo, $account, $allowedRoles, $allowedActions);
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
}

$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$accounts = sr_admin_member_rows_with_public_hash($runtimeConfig, sr_admin_role_accounts($pdo));

include SR_ROOT . '/modules/admin/views/roles.php';
