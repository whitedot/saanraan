<?php

declare(strict_types=1);

return [
    'deposit_legal_transactions' => [
        'enabled' => true,
        'auto_scope' => 'admin',
        'operation' => 'anonymize',
        'cutoff_key' => 'commerce_records',
        'count_sql' => "SELECT COUNT(*) AS count_value
         FROM sr_deposit_transactions t
         INNER JOIN sr_member_accounts a ON a.id = t.account_id
         WHERE t.account_id > 0
           AND a.status IN ('withdrawn', 'anonymized')
           AND t.created_at < :cutoff",
        'count_params' => ['cutoff' => 'commerce_records'],
        'delete_sql' => "UPDATE sr_deposit_transactions
         SET account_id = 0,
             created_by_account_id = NULL,
             reference_id = CASE WHEN reference_type = 'member.withdrawal' THEN 'anonymous' ELSE reference_id END
         WHERE account_id > 0
           AND account_id IN (SELECT id FROM sr_member_accounts WHERE status IN ('withdrawn', 'anonymized'))
           AND created_at < :cutoff",
        'delete_limited_sql' => "UPDATE sr_deposit_transactions
         SET account_id = 0,
             created_by_account_id = NULL,
             reference_id = CASE WHEN reference_type = 'member.withdrawal' THEN 'anonymous' ELSE reference_id END
         WHERE id IN (
            SELECT id FROM (
                SELECT t.id
                FROM sr_deposit_transactions t
                INNER JOIN sr_member_accounts a ON a.id = t.account_id
                WHERE t.account_id > 0
                  AND a.status IN ('withdrawn', 'anonymized')
                  AND t.created_at < :cutoff
                ORDER BY t.id ASC
                LIMIT {limit}
            ) sr_retention_target
         )",
        'delete_params' => ['cutoff' => 'commerce_records'],
        'table_checks' => [
            'SELECT 1 FROM sr_member_accounts LIMIT 1',
            'SELECT 1 FROM sr_deposit_transactions LIMIT 1',
        ],
    ],
    'deposit_legal_refund_requests' => [
        'enabled' => true,
        'auto_scope' => 'admin',
        'operation' => 'anonymize',
        'cutoff_key' => 'commerce_records',
        'count_sql' => "SELECT COUNT(*) AS count_value
         FROM sr_deposit_refund_requests r
         INNER JOIN sr_member_accounts a ON a.id = r.account_id
         WHERE r.account_id > 0
           AND a.status IN ('withdrawn', 'anonymized')
           AND r.status <> 'pending'
           AND COALESCE(r.processed_at, r.updated_at, r.requested_at) < :cutoff",
        'count_params' => ['cutoff' => 'commerce_records'],
        'delete_sql' => "UPDATE sr_deposit_refund_requests
         SET account_id = 0,
             bank_name = '',
             bank_account_number = '',
             bank_account_holder = '',
             requester_note = '',
             admin_note = '',
             processed_by_account_id = NULL
         WHERE account_id > 0
           AND account_id IN (SELECT id FROM sr_member_accounts WHERE status IN ('withdrawn', 'anonymized'))
           AND status <> 'pending'
           AND COALESCE(processed_at, updated_at, requested_at) < :cutoff",
        'delete_limited_sql' => "UPDATE sr_deposit_refund_requests
         SET account_id = 0,
             bank_name = '',
             bank_account_number = '',
             bank_account_holder = '',
             requester_note = '',
             admin_note = '',
             processed_by_account_id = NULL
         WHERE id IN (
            SELECT id FROM (
                SELECT r.id
                FROM sr_deposit_refund_requests r
                INNER JOIN sr_member_accounts a ON a.id = r.account_id
                WHERE r.account_id > 0
                  AND a.status IN ('withdrawn', 'anonymized')
                  AND r.status <> 'pending'
                  AND COALESCE(r.processed_at, r.updated_at, r.requested_at) < :cutoff
                ORDER BY r.id ASC
                LIMIT {limit}
            ) sr_retention_target
         )",
        'delete_params' => ['cutoff' => 'commerce_records'],
        'table_checks' => [
            'SELECT 1 FROM sr_member_accounts LIMIT 1',
            'SELECT 1 FROM sr_deposit_refund_requests LIMIT 1',
        ],
    ],
];
