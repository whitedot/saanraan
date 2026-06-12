<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/operations', 'view');

$operationStatusRows = sr_admin_operational_status_rows($pdo);
$operationStatusSummary = sr_admin_operational_status_summary($operationStatusRows);
$operationStatusCheckedAt = sr_now();

include SR_ROOT . '/modules/admin/views/operations.php';
