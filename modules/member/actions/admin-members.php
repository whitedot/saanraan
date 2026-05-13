<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);

$allowedStatuses = sr_admin_member_allowed_statuses();
$errors = [];
$notice = '';

if (sr_request_method() === 'POST') {
    sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);
    sr_require_csrf();

    $postResult = sr_admin_handle_members_post($pdo, $account, $allowedStatuses);
    $errors = $postResult['errors'];
    $notice = (string) $postResult['notice'];
}

$statusFilter = sr_admin_member_status_filter($allowedStatuses);
$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$members = sr_admin_member_rows_with_public_hash($runtimeConfig, sr_admin_members($pdo, $statusFilter));

include SR_ROOT . '/modules/member/views/admin-members.php';
