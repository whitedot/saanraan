<?php

declare(strict_types=1);

return [
    'community_asset_pending_logs' => [
        'enabled' => true,
        'auto_scope' => 'admin',
        'cutoff_key' => 'sessions',
        'count_sql' => "SELECT COUNT(*) AS count_value
         FROM sr_community_asset_logs
         WHERE log_status = 'pending'
           AND created_at < :cutoff",
        'count_params' => ['cutoff' => 'sessions'],
        'delete_sql' => "DELETE FROM sr_community_asset_logs
         WHERE log_status = 'pending'
           AND created_at < :cutoff",
        'delete_limited_sql' => "DELETE FROM sr_community_asset_logs
         WHERE log_status = 'pending'
           AND created_at < :cutoff
         ORDER BY id ASC
         LIMIT {limit}",
        'delete_params' => ['cutoff' => 'sessions'],
        'table_checks' => [
            'SELECT log_status FROM sr_community_asset_logs LIMIT 1',
        ],
    ],
    'community_legal_asset_logs' => [
        'enabled' => true,
        'auto_scope' => 'admin',
        'operation' => 'anonymize',
        'cutoff_key' => 'commerce_records',
        'count_sql' => "SELECT COUNT(*) AS count_value
         FROM sr_community_asset_logs l
         INNER JOIN sr_member_accounts a ON a.id = l.account_id
         WHERE l.account_id > 0
           AND a.status IN ('withdrawn', 'anonymized')
           AND l.log_status = 'completed'
           AND l.created_at < :cutoff",
        'count_params' => ['cutoff' => 'commerce_records'],
        'delete_sql' => "UPDATE sr_community_asset_logs
         SET account_id = 0,
             dedupe_key = CONCAT('anonymized:', id)
         WHERE id IN (
            SELECT id FROM (
                SELECT l.id
                FROM sr_community_asset_logs l
                INNER JOIN sr_member_accounts a ON a.id = l.account_id
                WHERE l.account_id > 0
                  AND a.status IN ('withdrawn', 'anonymized')
                  AND l.log_status = 'completed'
                  AND l.created_at < :cutoff
            ) sr_retention_target
         )",
        'delete_limited_sql' => "UPDATE sr_community_asset_logs
         SET account_id = 0,
             dedupe_key = CONCAT('anonymized:', id)
         WHERE id IN (
            SELECT id FROM (
                SELECT l.id
                FROM sr_community_asset_logs l
                INNER JOIN sr_member_accounts a ON a.id = l.account_id
                WHERE l.account_id > 0
                  AND a.status IN ('withdrawn', 'anonymized')
                  AND l.log_status = 'completed'
                  AND l.created_at < :cutoff
                ORDER BY l.id ASC
                LIMIT {limit}
            ) sr_retention_target
         )",
        'delete_params' => ['cutoff' => 'commerce_records'],
        'table_checks' => [
            'SELECT account_id, log_status, dedupe_key FROM sr_community_asset_logs LIMIT 1',
        ],
    ],
    'community_legal_publisher_reward_logs' => [
        'enabled' => true,
        'auto_scope' => 'admin',
        'operation' => 'anonymize',
        'cutoff_key' => 'commerce_records',
        'count_sql' => "SELECT COUNT(*) AS count_value
         FROM sr_community_publisher_reward_logs l
         LEFT JOIN sr_member_accounts downloader ON downloader.id = l.downloader_account_id
         LEFT JOIN sr_member_accounts publisher ON publisher.id = l.publisher_account_id
         WHERE l.status <> 'pending'
           AND l.created_at < :cutoff
           AND ((l.downloader_account_id > 0 AND downloader.status IN ('withdrawn', 'anonymized'))
                OR (l.publisher_account_id > 0 AND publisher.status IN ('withdrawn', 'anonymized')))",
        'count_params' => ['cutoff' => 'commerce_records'],
        'delete_sql' => "UPDATE sr_community_publisher_reward_logs
         SET downloader_account_id = CASE WHEN downloader_account_id IN (SELECT id FROM sr_member_accounts WHERE status IN ('withdrawn', 'anonymized')) THEN 0 ELSE downloader_account_id END,
             publisher_account_id = CASE WHEN publisher_account_id IN (SELECT id FROM sr_member_accounts WHERE status IN ('withdrawn', 'anonymized')) THEN 0 ELSE publisher_account_id END,
             failure_message = NULL,
             dedupe_key = CONCAT('anonymized:', id)
         WHERE id IN (
            SELECT id FROM (
                SELECT l.id
                FROM sr_community_publisher_reward_logs l
                LEFT JOIN sr_member_accounts downloader ON downloader.id = l.downloader_account_id
                LEFT JOIN sr_member_accounts publisher ON publisher.id = l.publisher_account_id
                WHERE l.status <> 'pending'
                  AND l.created_at < :cutoff
                  AND ((l.downloader_account_id > 0 AND downloader.status IN ('withdrawn', 'anonymized'))
                       OR (l.publisher_account_id > 0 AND publisher.status IN ('withdrawn', 'anonymized')))
            ) sr_retention_target
         )",
        'delete_limited_sql' => "UPDATE sr_community_publisher_reward_logs
         SET downloader_account_id = CASE WHEN downloader_account_id IN (SELECT id FROM sr_member_accounts WHERE status IN ('withdrawn', 'anonymized')) THEN 0 ELSE downloader_account_id END,
             publisher_account_id = CASE WHEN publisher_account_id IN (SELECT id FROM sr_member_accounts WHERE status IN ('withdrawn', 'anonymized')) THEN 0 ELSE publisher_account_id END,
             failure_message = NULL,
             dedupe_key = CONCAT('anonymized:', id)
         WHERE id IN (
            SELECT id FROM (
                SELECT l.id
                FROM sr_community_publisher_reward_logs l
                LEFT JOIN sr_member_accounts downloader ON downloader.id = l.downloader_account_id
                LEFT JOIN sr_member_accounts publisher ON publisher.id = l.publisher_account_id
                WHERE l.status <> 'pending'
                  AND l.created_at < :cutoff
                  AND ((l.downloader_account_id > 0 AND downloader.status IN ('withdrawn', 'anonymized'))
                       OR (l.publisher_account_id > 0 AND publisher.status IN ('withdrawn', 'anonymized')))
                ORDER BY l.id ASC
                LIMIT {limit}
            ) sr_retention_target
         )",
        'delete_params' => ['cutoff' => 'commerce_records'],
        'table_checks' => [
            'SELECT downloader_account_id, publisher_account_id, dedupe_key FROM sr_community_publisher_reward_logs LIMIT 1',
        ],
    ],
    'community_legal_post_read_payment_logs' => [
        'enabled' => true,
        'auto_scope' => 'admin',
        'operation' => 'anonymize',
        'cutoff_key' => 'commerce_records',
        'count_sql' => "SELECT COUNT(*) AS count_value
         FROM sr_community_post_read_payment_logs l
         LEFT JOIN sr_member_accounts a ON a.id = l.account_id
         WHERE (l.account_id IS NULL OR (l.account_id > 0 AND a.status IN ('withdrawn', 'anonymized')))
           AND l.created_at < :cutoff
           AND (l.account_id IS NOT NULL OR l.coupon_dedupe_key <> '' OR l.refund_note <> '' OR l.refunded_by_account_id IS NOT NULL OR l.payment_dedupe_key NOT LIKE 'anonymized:%')",
        'count_params' => ['cutoff' => 'commerce_records'],
        'delete_sql' => "UPDATE sr_community_post_read_payment_logs
         SET account_id = NULL,
             coupon_dedupe_key = '',
             payment_dedupe_key = CONCAT('anonymized:', id),
             refund_note = '',
             refunded_by_account_id = NULL
         WHERE id IN (
            SELECT id FROM (
                SELECT l.id
                FROM sr_community_post_read_payment_logs l
                LEFT JOIN sr_member_accounts a ON a.id = l.account_id
                WHERE (l.account_id IS NULL OR (l.account_id > 0 AND a.status IN ('withdrawn', 'anonymized')))
                  AND l.created_at < :cutoff
                  AND (l.account_id IS NOT NULL OR l.coupon_dedupe_key <> '' OR l.refund_note <> '' OR l.refunded_by_account_id IS NOT NULL OR l.payment_dedupe_key NOT LIKE 'anonymized:%')
            ) sr_retention_target
         )",
        'delete_limited_sql' => "UPDATE sr_community_post_read_payment_logs
         SET account_id = NULL,
             coupon_dedupe_key = '',
             payment_dedupe_key = CONCAT('anonymized:', id),
             refund_note = '',
             refunded_by_account_id = NULL
         WHERE id IN (
            SELECT id FROM (
                SELECT l.id
                FROM sr_community_post_read_payment_logs l
                LEFT JOIN sr_member_accounts a ON a.id = l.account_id
                WHERE (l.account_id IS NULL OR (l.account_id > 0 AND a.status IN ('withdrawn', 'anonymized')))
                  AND l.created_at < :cutoff
                  AND (l.account_id IS NOT NULL OR l.coupon_dedupe_key <> '' OR l.refund_note <> '' OR l.refunded_by_account_id IS NOT NULL OR l.payment_dedupe_key NOT LIKE 'anonymized:%')
                ORDER BY l.id ASC
                LIMIT {limit}
            ) sr_retention_target
         )",
        'delete_params' => ['cutoff' => 'commerce_records'],
        'table_checks' => [
            'SELECT account_id, coupon_dedupe_key, payment_dedupe_key FROM sr_community_post_read_payment_logs LIMIT 1',
        ],
    ],
    'community_legal_attachment_download_logs' => [
        'enabled' => true,
        'auto_scope' => 'admin',
        'operation' => 'anonymize',
        'cutoff_key' => 'commerce_records',
        'count_sql' => "SELECT COUNT(*) AS count_value
         FROM sr_community_attachment_download_logs l
         LEFT JOIN sr_member_accounts a ON a.id = l.account_id
         WHERE (l.account_id IS NULL OR (l.account_id > 0 AND a.status IN ('withdrawn', 'anonymized')))
           AND l.created_at < :cutoff
           AND (l.account_id IS NOT NULL OR l.coupon_dedupe_key <> '' OR l.refund_note <> '' OR l.refunded_by_account_id IS NOT NULL)",
        'count_params' => ['cutoff' => 'commerce_records'],
        'delete_sql' => "UPDATE sr_community_attachment_download_logs
         SET account_id = NULL,
             coupon_dedupe_key = '',
             refund_note = '',
             refunded_by_account_id = NULL
         WHERE id IN (
            SELECT id FROM (
                SELECT l.id
                FROM sr_community_attachment_download_logs l
                LEFT JOIN sr_member_accounts a ON a.id = l.account_id
                WHERE (l.account_id IS NULL OR (l.account_id > 0 AND a.status IN ('withdrawn', 'anonymized')))
                  AND l.created_at < :cutoff
                  AND (l.account_id IS NOT NULL OR l.coupon_dedupe_key <> '' OR l.refund_note <> '' OR l.refunded_by_account_id IS NOT NULL)
            ) sr_retention_target
         )",
        'delete_limited_sql' => "UPDATE sr_community_attachment_download_logs
         SET account_id = NULL,
             coupon_dedupe_key = '',
             refund_note = '',
             refunded_by_account_id = NULL
         WHERE id IN (
            SELECT id FROM (
                SELECT l.id
                FROM sr_community_attachment_download_logs l
                LEFT JOIN sr_member_accounts a ON a.id = l.account_id
                WHERE (l.account_id IS NULL OR (l.account_id > 0 AND a.status IN ('withdrawn', 'anonymized')))
                  AND l.created_at < :cutoff
                  AND (l.account_id IS NOT NULL OR l.coupon_dedupe_key <> '' OR l.refund_note <> '' OR l.refunded_by_account_id IS NOT NULL)
                ORDER BY l.id ASC
                LIMIT {limit}
            ) sr_retention_target
         )",
        'delete_params' => ['cutoff' => 'commerce_records'],
        'table_checks' => [
            'SELECT account_id, coupon_dedupe_key FROM sr_community_attachment_download_logs LIMIT 1',
        ],
    ],
];
