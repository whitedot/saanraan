<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/deposit/helpers.php';

$account = sr_member_require_login($pdo);
$balance = sr_deposit_balance($pdo, (int) $account['id']);
$stmt = $pdo->prepare(
    'SELECT id, amount, balance_after, transaction_type, reason, reference_type, reference_id, created_at
     FROM sr_deposit_transactions
     WHERE account_id = :account_id
     ORDER BY id DESC
     LIMIT 100'
);
$stmt->execute(['account_id' => (int) $account['id']]);
$transactions = $stmt->fetchAll();

include SR_ROOT . '/modules/deposit/views/account-deposits.php';
