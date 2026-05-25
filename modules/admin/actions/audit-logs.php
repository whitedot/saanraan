<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/audit-logs', 'view');

$filters = sr_admin_audit_log_filters();
$adminSettings = sr_admin_settings($pdo);
$listPaginationPerPage = sr_admin_list_pagination_per_page($adminSettings);
$auditLogPage = sr_admin_audit_log_page_number(sr_get_string('page', 20));
$auditLogTotal = sr_admin_audit_log_count($pdo, $filters);
$auditLogTotalPages = max(1, (int) ceil($auditLogTotal / $listPaginationPerPage));
if ($auditLogPage > $auditLogTotalPages) {
    $auditLogPage = $auditLogTotalPages;
}
$logs = sr_admin_audit_logs($pdo, $filters, $listPaginationPerPage, ($auditLogPage - 1) * $listPaginationPerPage);
$auditPagination = [
    'page' => $auditLogPage,
    'per_page' => $listPaginationPerPage,
    'total' => $auditLogTotal,
    'total_pages' => $auditLogTotalPages,
];

include SR_ROOT . '/modules/admin/views/audit-logs.php';
