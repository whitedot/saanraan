<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/asset_ledger/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/assets/reconciliation', 'view');

$reconciliationResults = sr_asset_reconcile_all($pdo, 50, true);

include SR_ROOT . '/modules/asset_ledger/views/admin-reconciliation.php';
