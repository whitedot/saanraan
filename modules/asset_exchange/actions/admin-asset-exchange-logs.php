<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/asset_exchange/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/asset-exchange/logs', 'view');

$assets = sr_asset_exchange_assets($pdo);
$countRow = $pdo->query('SELECT COUNT(*) AS count_value FROM sr_asset_exchange_logs')->fetch();
$pagination = sr_admin_pagination_from_total($pdo, is_array($countRow) ? (int) ($countRow['count_value'] ?? 0) : 0);
$stmt = $pdo->query(
    'SELECT l.*, a.email, a.display_name, a.status AS account_status
     FROM sr_asset_exchange_logs l
     INNER JOIN sr_member_accounts a ON a.id = l.account_id
     ORDER BY l.id DESC
     LIMIT ' . (int) $pagination['per_page'] . ' OFFSET ' . sr_admin_pagination_offset($pagination)
);
$logs = $stmt->fetchAll();

include SR_ROOT . '/modules/asset_exchange/views/admin-asset-exchange-logs.php';
