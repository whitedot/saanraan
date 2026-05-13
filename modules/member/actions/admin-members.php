<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/admin/helpers.php';

$account = toy_member_require_login($pdo);
toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);

$allowedStatuses = toy_admin_member_allowed_statuses();
$errors = [];
$notice = '';

if (toy_request_method() === 'POST') {
    toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);
    toy_require_csrf();

    $postResult = toy_admin_handle_members_post($pdo, $account, $allowedStatuses);
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
}

$statusFilter = toy_admin_member_status_filter($allowedStatuses);
$runtimeConfig = isset($config) && is_array($config) ? $config : toy_runtime_config();
$members = toy_admin_member_rows_with_public_hash($runtimeConfig, toy_admin_members($pdo, $statusFilter));

include TOY_ROOT . '/modules/member/views/admin-members.php';
