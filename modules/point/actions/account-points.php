<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/point/helpers.php';

$account = sr_member_require_login($pdo);
$balance = sr_point_balance($pdo, (int) $account['id']);
$pointDisplayName = sr_point_display_name($pdo);
$pointUnitLabel = sr_point_unit_label($pdo);
$pointTransactionPerPage = 20;
$pointTransactionPageInput = sr_get_string('page', 20);
$pointTransactionPage = preg_match('/\A[1-9][0-9]*\z/', $pointTransactionPageInput) === 1 ? (int) $pointTransactionPageInput : 1;
$stmt = $pdo->prepare('SELECT COUNT(*) FROM sr_point_transactions WHERE account_id = :account_id');
$stmt->execute(['account_id' => (int) $account['id']]);
$pointTransactionCount = (int) $stmt->fetchColumn();
$pointTransactionTotalPages = max(1, (int) ceil($pointTransactionCount / $pointTransactionPerPage));
$pointTransactionPage = min(max(1, $pointTransactionPage), $pointTransactionTotalPages);
$pointTransactionPagination = ['page' => $pointTransactionPage, 'total_pages' => $pointTransactionTotalPages];
$stmt = $pdo->prepare(
    'SELECT id, amount, balance_after, transaction_type, reason, reference_type, reference_id, expires_at, expires_remaining, expired_at, created_at
     FROM sr_point_transactions
     WHERE account_id = :account_id
     ORDER BY id DESC
     LIMIT :limit OFFSET :offset'
);
$stmt->bindValue(':account_id', (int) $account['id'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $pointTransactionPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($pointTransactionPage - 1) * $pointTransactionPerPage, PDO::PARAM_INT);
$stmt->execute();
$transactions = $stmt->fetchAll();

include SR_ROOT . '/modules/point/views/account-points.php';
