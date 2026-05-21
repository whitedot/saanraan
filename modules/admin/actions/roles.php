<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner']);

$allowedRoles = sr_admin_allowed_roles();
$allowedActions = sr_admin_role_actions();
$allowedStatuses = sr_admin_member_allowed_statuses();
$errors = [];
$notice = '';
$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $postResult = sr_admin_handle_roles_post($pdo, $account, $allowedRoles, $allowedActions);
    $statusFilter = sr_admin_member_status_filter($allowedStatuses);
    $roleFilter = sr_admin_role_filter($allowedRoles);
    $searchFilter = sr_admin_member_search_filter($pdo, $runtimeConfig);
    sr_admin_flash_result($postResult);
    sr_redirect(sr_admin_role_filter_url($statusFilter, $roleFilter, $searchFilter));
}

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$statusFilter = sr_admin_member_status_filter($allowedStatuses);
$roleFilter = sr_admin_role_filter($allowedRoles);
$searchFilter = sr_admin_member_search_filter($pdo, $runtimeConfig);
$hasRoleFilters = sr_admin_role_filter_has_conditions($statusFilter, $roleFilter, $searchFilter);
$accounts = $hasRoleFilters
    ? sr_admin_member_rows_with_public_hash($runtimeConfig, sr_admin_role_accounts($pdo, $statusFilter, $searchFilter, $roleFilter))
    : [];

include SR_ROOT . '/modules/admin/views/roles.php';
