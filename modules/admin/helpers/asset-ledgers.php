<?php

declare(strict_types=1);

function sr_admin_asset_ledger_table(string $table, string $suffix): string
{
    if (preg_match('/\Asr_[a-z0-9_]+_' . preg_quote($suffix, '/') . '\z/', $table) !== 1) {
        throw new InvalidArgumentException('Invalid asset ledger table.');
    }

    return $table;
}

function sr_admin_asset_selected_account(PDO $pdo, array $config, int $accountId, callable $balanceCallback): array
{
    if ($accountId <= 0) {
        return ['account' => null, 'balance' => null, 'identifier' => ''];
    }

    $stmt = $pdo->prepare('SELECT id, email, display_name, status FROM sr_member_accounts WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $accountId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return ['account' => null, 'balance' => null, 'identifier' => ''];
    }

    $account = sr_admin_member_row_with_public_hash($config, $row);
    return [
        'account' => $account,
        'balance' => (int) $balanceCallback($pdo, $accountId),
        'identifier' => (string) $account['account_public_hash'],
    ];
}

function sr_admin_asset_balance_rows(PDO $pdo, array $config, string $balanceTable, array $sort, array $pagination): array
{
    $balanceTable = sr_admin_asset_ledger_table($balanceTable, 'balances');
    $stmt = $pdo->query(
        'SELECT b.account_id, b.balance, b.updated_at, a.email, a.display_name, a.status
         FROM ' . $balanceTable . ' b
         INNER JOIN sr_member_accounts a ON a.id = b.account_id
         ' . sr_admin_sort_order_sql(sr_admin_asset_balance_sort_options(), $sort, sr_admin_asset_balance_default_sort()) . '
         LIMIT ' . (int) $pagination['per_page'] . ' OFFSET ' . sr_admin_pagination_offset($pagination)
    );

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = sr_admin_member_row_with_public_hash($config, $row);
    }

    return $rows;
}

function sr_admin_asset_balance_count(PDO $pdo, string $balanceTable): int
{
    $balanceTable = sr_admin_asset_ledger_table($balanceTable, 'balances');
    $stmt = $pdo->query(
        'SELECT COUNT(*) AS count_value
         FROM ' . $balanceTable . ' b
         INNER JOIN sr_member_accounts a ON a.id = b.account_id'
    );
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_admin_asset_transaction_count(PDO $pdo, string $transactionTable, int $accountId = 0): int
{
    $transactionTable = sr_admin_asset_ledger_table($transactionTable, 'transactions');
    if ($accountId > 0) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS count_value
             FROM ' . $transactionTable . ' t
             INNER JOIN sr_member_accounts a ON a.id = t.account_id
             WHERE t.account_id = :account_id'
        );
        $stmt->execute(['account_id' => $accountId]);
    } else {
        $stmt = $pdo->query(
            'SELECT COUNT(*) AS count_value
             FROM ' . $transactionTable . ' t
             INNER JOIN sr_member_accounts a ON a.id = t.account_id'
        );
    }
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_admin_asset_transaction_rows(PDO $pdo, array $config, string $transactionTable, array $sort, array $pagination, int $accountId = 0): array
{
    $transactionTable = sr_admin_asset_ledger_table($transactionTable, 'transactions');
    $sql = 'SELECT t.id, t.account_id, t.amount, t.balance_after, t.transaction_type, t.reason, t.reference_type, t.reference_id, t.created_by_account_id, t.created_at,
                   a.email, a.display_name
            FROM ' . $transactionTable . ' t
            INNER JOIN sr_member_accounts a ON a.id = t.account_id';
    if ($accountId > 0) {
        $stmt = $pdo->prepare(
            $sql . '
             WHERE t.account_id = :account_id
             ' . sr_admin_sort_order_sql(sr_admin_asset_transaction_sort_options(), $sort, sr_admin_asset_transaction_default_sort()) . '
             LIMIT :limit_value OFFSET :offset_value'
        );
        $stmt->bindValue('account_id', $accountId, PDO::PARAM_INT);
        $stmt->bindValue('limit_value', (int) $pagination['per_page'], PDO::PARAM_INT);
        $stmt->bindValue('offset_value', sr_admin_pagination_offset($pagination), PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->query(
            $sql . '
             ' . sr_admin_sort_order_sql(sr_admin_asset_transaction_sort_options(), $sort, sr_admin_asset_transaction_default_sort()) . '
             LIMIT ' . (int) $pagination['per_page'] . ' OFFSET ' . sr_admin_pagination_offset($pagination)
        );
    }

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = sr_admin_member_row_with_public_hash($config, $row);
    }

    return $rows;
}
