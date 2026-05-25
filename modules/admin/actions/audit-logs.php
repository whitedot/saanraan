<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/audit-logs', 'view');

$filters = sr_admin_audit_log_filters();
$adminSettings = sr_admin_settings($pdo);
$listPaginationPerPage = sr_admin_list_pagination_per_page($adminSettings);
$auditLogTotal = sr_admin_audit_log_count($pdo, $filters);
$auditPagination = sr_admin_pagination_meta($auditLogTotal, $listPaginationPerPage, sr_admin_page_number_from_request());
$logs = sr_admin_audit_logs($pdo, $filters, $listPaginationPerPage, ((int) $auditPagination['page'] - 1) * $listPaginationPerPage);

include SR_ROOT . '/modules/admin/views/audit-logs.php';
