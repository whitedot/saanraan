<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/audit-logs', 'view');

$filters = sr_admin_audit_log_filters();
$auditLogTotal = sr_admin_audit_log_count($pdo, $filters);
$auditPagination = sr_admin_pagination_from_total($pdo, $auditLogTotal);
$logs = sr_admin_audit_logs($pdo, $filters, (int) $auditPagination['per_page'], sr_admin_pagination_offset($auditPagination));

include SR_ROOT . '/modules/admin/views/audit-logs.php';
