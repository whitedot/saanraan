<?php

declare(strict_types=1);

return [
    'asset_exchange_legal_logs' => [
        'enabled' => true,
        'auto_scope' => 'admin',
        'operation' => 'anonymize',
        'cutoff_key' => 'commerce_records',
        'count_sql' => "SELECT COUNT(*) AS count_value
         FROM sr_asset_exchange_logs l
         INNER JOIN sr_member_accounts a ON a.id = l.account_id
         WHERE l.account_id > 0
           AND a.status IN ('withdrawn', 'anonymized')
           AND l.created_at < :cutoff",
        'count_params' => ['cutoff' => 'commerce_records'],
        'delete_sql' => "UPDATE sr_asset_exchange_logs
         SET account_id = 0,
             created_by_account_id = NULL,
             failure_reason = ''
         WHERE account_id > 0
           AND account_id IN (SELECT id FROM sr_member_accounts WHERE status IN ('withdrawn', 'anonymized'))
           AND created_at < :cutoff",
        'delete_limited_sql' => "UPDATE sr_asset_exchange_logs
         SET account_id = 0,
             created_by_account_id = NULL,
             failure_reason = ''
         WHERE id IN (
            SELECT id FROM (
                SELECT l.id
                FROM sr_asset_exchange_logs l
                INNER JOIN sr_member_accounts a ON a.id = l.account_id
                WHERE l.account_id > 0
                  AND a.status IN ('withdrawn', 'anonymized')
                  AND l.created_at < :cutoff
                ORDER BY l.id ASC
                LIMIT {limit}
            ) sr_retention_target
         )",
        'delete_params' => ['cutoff' => 'commerce_records'],
        'table_checks' => [
            'SELECT 1 FROM sr_member_accounts LIMIT 1',
            'SELECT 1 FROM sr_asset_exchange_logs LIMIT 1',
        ],
    ],
    'asset_exchange_dispute_notes' => [
        'enabled' => true,
        'auto_scope' => 'admin',
        'operation' => 'anonymize',
        'cutoff_key' => 'commerce_disputes',
        'count_sql' => "SELECT COUNT(*) AS count_value
         FROM sr_asset_exchange_logs l
         INNER JOIN sr_member_accounts a ON a.id = l.account_id
         WHERE l.account_id > 0
           AND a.status IN ('withdrawn', 'anonymized')
           AND l.failure_reason <> ''
           AND l.created_at < :cutoff",
        'count_params' => ['cutoff' => 'commerce_disputes'],
        'delete_sql' => "UPDATE sr_asset_exchange_logs
         SET failure_reason = ''
         WHERE account_id > 0
           AND account_id IN (SELECT id FROM sr_member_accounts WHERE status IN ('withdrawn', 'anonymized'))
           AND failure_reason <> ''
           AND created_at < :cutoff",
        'delete_limited_sql' => "UPDATE sr_asset_exchange_logs
         SET failure_reason = ''
         WHERE id IN (
            SELECT id FROM (
                SELECT l.id
                FROM sr_asset_exchange_logs l
                INNER JOIN sr_member_accounts a ON a.id = l.account_id
                WHERE l.account_id > 0
                  AND a.status IN ('withdrawn', 'anonymized')
                  AND l.failure_reason <> ''
                  AND l.created_at < :cutoff
                ORDER BY l.id ASC
                LIMIT {limit}
            ) sr_retention_target
         )",
        'delete_params' => ['cutoff' => 'commerce_disputes'],
        'table_checks' => [
            'SELECT 1 FROM sr_member_accounts LIMIT 1',
            'SELECT 1 FROM sr_asset_exchange_logs LIMIT 1',
        ],
    ],
];
