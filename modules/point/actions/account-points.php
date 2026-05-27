<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/point/helpers.php';

$account = sr_member_require_login($pdo);
$balance = sr_point_balance($pdo, (int) $account['id']);
$pointDisplayName = sr_point_display_name($pdo);
$pointUnitLabel = sr_point_unit_label($pdo);
$stmt = $pdo->prepare(
    'SELECT id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_at
     FROM sr_point_transactions
     WHERE account_id = :account_id
     ORDER BY id DESC
     LIMIT 100'
);
$stmt->execute(['account_id' => (int) $account['id']]);
$transactions = $stmt->fetchAll();

include SR_ROOT . '/modules/point/views/account-points.php';
