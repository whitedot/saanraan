<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/asset_ledger/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/assets/reconciliation', 'view');

$maxRows = 50;
$postedMaxRows = (string) ($_GET['max_rows'] ?? '');
if (preg_match('/\A[1-9][0-9]{0,2}\z/', $postedMaxRows) === 1) {
    $maxRows = max(1, min(500, (int) $postedMaxRows));
}

$reconciliationResults = sr_asset_reconcile_all($pdo, $maxRows, true);

include SR_ROOT . '/modules/asset_ledger/views/admin-reconciliation.php';
