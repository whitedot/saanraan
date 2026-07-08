<?php

declare(strict_types=1);

return [
    'point_legal_transactions' => [
        'enabled' => true,
        'auto_scope' => 'admin',
        'operation' => 'anonymize',
        'cutoff_key' => 'commerce_records',
        'count_sql' => "SELECT COUNT(*) AS count_value
         FROM sr_point_transactions t
         INNER JOIN sr_member_accounts a ON a.id = t.account_id
         WHERE t.account_id > 0
           AND a.status IN ('withdrawn', 'anonymized')
           AND t.created_at < :cutoff",
        'count_params' => [
            'cutoff' => 'commerce_records',
        ],
        'delete_sql' => "UPDATE sr_point_transactions
         SET account_id = 0,
             created_by_account_id = NULL,
             reason = '',
             reference_id = CASE WHEN reference_type = 'member.withdrawal' THEN 'anonymous' ELSE reference_id END
         WHERE account_id > 0
           AND account_id IN (SELECT id FROM sr_member_accounts WHERE status IN ('withdrawn', 'anonymized'))
           AND created_at < :cutoff",
        'delete_limited_sql' => "UPDATE sr_point_transactions
         SET account_id = 0,
             created_by_account_id = NULL,
             reason = '',
             reference_id = CASE WHEN reference_type = 'member.withdrawal' THEN 'anonymous' ELSE reference_id END
         WHERE id IN (
            SELECT id FROM (
                SELECT t.id
                FROM sr_point_transactions t
                INNER JOIN sr_member_accounts a ON a.id = t.account_id
                WHERE t.account_id > 0
                  AND a.status IN ('withdrawn', 'anonymized')
                  AND t.created_at < :cutoff
                ORDER BY t.id ASC
                LIMIT {limit}
            ) sr_retention_target
         )",
        'delete_params' => [
            'cutoff' => 'commerce_records',
        ],
        'table_checks' => [
            'SELECT 1 FROM sr_member_accounts LIMIT 1',
            'SELECT 1 FROM sr_point_transactions LIMIT 1',
        ],
    ],
    'point_legal_expiration_consumptions' => [
        'enabled' => true,
        'auto_scope' => 'admin',
        'operation' => 'anonymize',
        'cutoff_key' => 'commerce_records',
        'count_sql' => "SELECT COUNT(*) AS count_value
         FROM sr_point_expiration_consumptions c
         INNER JOIN sr_member_accounts a ON a.id = c.account_id
         WHERE c.account_id > 0
           AND a.status IN ('withdrawn', 'anonymized')
           AND c.created_at < :cutoff",
        'count_params' => [
            'cutoff' => 'commerce_records',
        ],
        'delete_sql' => "UPDATE sr_point_expiration_consumptions
         SET account_id = 0
         WHERE account_id > 0
           AND account_id IN (SELECT id FROM sr_member_accounts WHERE status IN ('withdrawn', 'anonymized'))
           AND created_at < :cutoff",
        'delete_limited_sql' => "UPDATE sr_point_expiration_consumptions
         SET account_id = 0
         WHERE id IN (
            SELECT id FROM (
                SELECT c.id
                FROM sr_point_expiration_consumptions c
                INNER JOIN sr_member_accounts a ON a.id = c.account_id
                WHERE c.account_id > 0
                  AND a.status IN ('withdrawn', 'anonymized')
                  AND c.created_at < :cutoff
                ORDER BY c.id ASC
                LIMIT {limit}
            ) sr_retention_target
         )",
        'delete_params' => [
            'cutoff' => 'commerce_records',
        ],
        'table_checks' => [
            'SELECT 1 FROM sr_member_accounts LIMIT 1',
            'SELECT 1 FROM sr_point_expiration_consumptions LIMIT 1',
        ],
    ],
];
