<?php

declare(strict_types=1);

return [
    'coupon_legal_issues' => [
        'enabled' => true,
        'auto_scope' => 'admin',
        'operation' => 'anonymize',
        'cutoff_key' => 'commerce_records',
        'count_sql' => "SELECT COUNT(*) AS count_value
         FROM sr_coupon_issues i
         INNER JOIN sr_member_accounts a ON a.id = i.account_id
         WHERE i.account_id > 0
           AND a.status IN ('withdrawn', 'anonymized')
           AND i.issued_at < :cutoff",
        'count_params' => ['cutoff' => 'commerce_records'],
        'delete_sql' => "UPDATE sr_coupon_issues
         SET account_id = 0,
             issued_reason = '',
             issued_by_account_id = NULL,
             claim_snapshot_json = NULL
         WHERE account_id > 0
           AND account_id IN (SELECT id FROM sr_member_accounts WHERE status IN ('withdrawn', 'anonymized'))
           AND issued_at < :cutoff",
        'delete_limited_sql' => "UPDATE sr_coupon_issues
         SET account_id = 0,
             issued_reason = '',
             issued_by_account_id = NULL,
             claim_snapshot_json = NULL
         WHERE id IN (
            SELECT id FROM (
                SELECT i.id
                FROM sr_coupon_issues i
                INNER JOIN sr_member_accounts a ON a.id = i.account_id
                WHERE i.account_id > 0
                  AND a.status IN ('withdrawn', 'anonymized')
                  AND i.issued_at < :cutoff
                ORDER BY i.id ASC
                LIMIT {limit}
            ) sr_retention_target
         )",
        'delete_params' => ['cutoff' => 'commerce_records'],
        'table_checks' => [
            'SELECT 1 FROM sr_member_accounts LIMIT 1',
            'SELECT 1 FROM sr_coupon_issues LIMIT 1',
        ],
    ],
    'coupon_legal_redemptions' => [
        'enabled' => true,
        'auto_scope' => 'admin',
        'operation' => 'anonymize',
        'cutoff_key' => 'commerce_records',
        'count_sql' => "SELECT COUNT(*) AS count_value
         FROM sr_coupon_redemptions r
         INNER JOIN sr_member_accounts a ON a.id = r.account_id
         WHERE r.account_id > 0
           AND a.status IN ('withdrawn', 'anonymized')
           AND COALESCE(r.refunded_at, r.redeemed_at, r.created_at) < :cutoff",
        'count_params' => ['cutoff' => 'commerce_records'],
        'delete_sql' => "UPDATE sr_coupon_redemptions
         SET account_id = 0,
             refunded_by_account_id = NULL,
             refund_note = '',
             target_snapshot_json = NULL
         WHERE account_id > 0
           AND account_id IN (SELECT id FROM sr_member_accounts WHERE status IN ('withdrawn', 'anonymized'))
           AND COALESCE(refunded_at, redeemed_at, created_at) < :cutoff",
        'delete_limited_sql' => "UPDATE sr_coupon_redemptions
         SET account_id = 0,
             refunded_by_account_id = NULL,
             refund_note = '',
             target_snapshot_json = NULL
         WHERE id IN (
            SELECT id FROM (
                SELECT r.id
                FROM sr_coupon_redemptions r
                INNER JOIN sr_member_accounts a ON a.id = r.account_id
                WHERE r.account_id > 0
                  AND a.status IN ('withdrawn', 'anonymized')
                  AND COALESCE(r.refunded_at, r.redeemed_at, r.created_at) < :cutoff
                ORDER BY r.id ASC
                LIMIT {limit}
            ) sr_retention_target
         )",
        'delete_params' => ['cutoff' => 'commerce_records'],
        'table_checks' => [
            'SELECT 1 FROM sr_member_accounts LIMIT 1',
            'SELECT 1 FROM sr_coupon_redemptions LIMIT 1',
        ],
    ],
    'coupon_legal_claim_logs' => [
        'enabled' => true,
        'auto_scope' => 'admin',
        'operation' => 'anonymize',
        'cutoff_key' => 'commerce_records',
        'count_sql' => "SELECT COUNT(*) AS count_value
         FROM sr_coupon_claim_logs l
         INNER JOIN sr_member_accounts a ON a.id = l.account_id
         WHERE l.account_id > 0
           AND a.status IN ('withdrawn', 'anonymized')
           AND l.status NOT IN ('reserved', 'processing')
           AND COALESCE(l.issued_at, l.updated_at, l.created_at) < :cutoff",
        'count_params' => ['cutoff' => 'commerce_records'],
        'delete_sql' => "UPDATE sr_coupon_claim_logs
         SET account_id = 0,
             occupying_account_id = NULL,
             source_context_json = '{}',
             failure_message = ''
         WHERE account_id > 0
           AND account_id IN (SELECT id FROM sr_member_accounts WHERE status IN ('withdrawn', 'anonymized'))
           AND status NOT IN ('reserved', 'processing')
           AND COALESCE(issued_at, updated_at, created_at) < :cutoff",
        'delete_limited_sql' => "UPDATE sr_coupon_claim_logs
         SET account_id = 0,
             occupying_account_id = NULL,
             source_context_json = '{}',
             failure_message = ''
         WHERE id IN (
            SELECT id FROM (
                SELECT l.id
                FROM sr_coupon_claim_logs l
                INNER JOIN sr_member_accounts a ON a.id = l.account_id
                WHERE l.account_id > 0
                  AND a.status IN ('withdrawn', 'anonymized')
                  AND l.status NOT IN ('reserved', 'processing')
                  AND COALESCE(l.issued_at, l.updated_at, l.created_at) < :cutoff
                ORDER BY l.id ASC
                LIMIT {limit}
            ) sr_retention_target
         )",
        'delete_params' => ['cutoff' => 'commerce_records'],
        'table_checks' => [
            'SELECT 1 FROM sr_member_accounts LIMIT 1',
            'SELECT 1 FROM sr_coupon_claim_logs LIMIT 1',
        ],
    ],
    'coupon_dispute_notes' => [
        'enabled' => true,
        'auto_scope' => 'admin',
        'operation' => 'anonymize',
        'cutoff_key' => 'commerce_disputes',
        'count_sql' => "SELECT COUNT(*) AS count_value
         FROM sr_coupon_redemptions r
         INNER JOIN sr_member_accounts a ON a.id = r.account_id
         WHERE r.account_id > 0
           AND a.status IN ('withdrawn', 'anonymized')
           AND r.refund_note <> ''
           AND COALESCE(r.refunded_at, r.created_at) < :cutoff",
        'count_params' => ['cutoff' => 'commerce_disputes'],
        'delete_sql' => "UPDATE sr_coupon_redemptions
         SET refund_note = ''
         WHERE account_id > 0
           AND account_id IN (SELECT id FROM sr_member_accounts WHERE status IN ('withdrawn', 'anonymized'))
           AND refund_note <> ''
           AND COALESCE(refunded_at, created_at) < :cutoff",
        'delete_limited_sql' => "UPDATE sr_coupon_redemptions
         SET refund_note = ''
         WHERE id IN (
            SELECT id FROM (
                SELECT r.id
                FROM sr_coupon_redemptions r
                INNER JOIN sr_member_accounts a ON a.id = r.account_id
                WHERE r.account_id > 0
                  AND a.status IN ('withdrawn', 'anonymized')
                  AND r.refund_note <> ''
                  AND COALESCE(r.refunded_at, r.created_at) < :cutoff
                ORDER BY r.id ASC
                LIMIT {limit}
            ) sr_retention_target
         )",
        'delete_params' => ['cutoff' => 'commerce_disputes'],
        'table_checks' => [
            'SELECT 1 FROM sr_member_accounts LIMIT 1',
            'SELECT 1 FROM sr_coupon_redemptions LIMIT 1',
        ],
    ],
];
