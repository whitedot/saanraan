<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_owner($pdo, (int) $account['id']);

$permissionOptions = sr_admin_permission_options($pdo);
$permissionActions = sr_admin_permission_actions();
$errors = [];
$notice = '';
$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $postResult = sr_admin_handle_permissions_post($pdo, $account);
    sr_admin_flash_result($postResult);
    sr_redirect('/admin/roles');
}

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$accountPagination = sr_admin_pagination_from_total($pdo, sr_admin_permission_account_count($pdo, '', [], 'any'));
$accountSortOptions = sr_admin_permission_account_sort_options();
$accountDefaultSort = sr_admin_permission_account_default_sort();
$accountSort = sr_admin_sort_from_request($accountSortOptions, $accountDefaultSort);
$ownerCount = sr_admin_owner_count($pdo);
$accounts = sr_admin_member_rows_with_public_hash(
    $runtimeConfig,
    sr_admin_permission_accounts($pdo, '', [], 'any', (int) $accountPagination['per_page'], sr_admin_pagination_offset($accountPagination), $accountSort)
);

include SR_ROOT . '/modules/admin/views/roles.php';
