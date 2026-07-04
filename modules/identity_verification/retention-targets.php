<?php

declare(strict_types=1);

return [
    'identity_verification_closed_attempts' => [
        'enabled' => true,
        'auto_scope' => 'public',
        'cutoff_key' => 'used_tokens',
        'count_sql' => "SELECT COUNT(*) AS count_value
             FROM sr_identity_verification_attempts
             WHERE status IN ('failed', 'expired', 'canceled')
               AND COALESCE(failed_at, updated_at, created_at) < :cutoff",
        'count_params' => [
            'cutoff' => 'used_tokens',
        ],
        'delete_sql' => "DELETE FROM sr_identity_verification_attempts
             WHERE status IN ('failed', 'expired', 'canceled')
               AND COALESCE(failed_at, updated_at, created_at) < :cutoff",
        'delete_limited_sql' => "DELETE FROM sr_identity_verification_attempts
             WHERE status IN ('failed', 'expired', 'canceled')
               AND COALESCE(failed_at, updated_at, created_at) < :cutoff
             ORDER BY id ASC
             LIMIT {limit}",
        'delete_params' => [
            'cutoff' => 'used_tokens',
        ],
        'table_checks' => [
            'SELECT 1 FROM sr_identity_verification_attempts LIMIT 1',
        ],
    ],
    'identity_verification_expired_results' => [
        'enabled' => true,
        'auto_scope' => 'public',
        'cutoff_key' => 'used_tokens',
        'count_sql' => "SELECT COUNT(*) AS count_value
             FROM sr_identity_verification_results
             WHERE expires_at IS NOT NULL
               AND expires_at < :cutoff
               AND NOT EXISTS (
                    SELECT 1 FROM sr_identity_verification_links l
                    WHERE l.result_id = sr_identity_verification_results.id
                      AND l.revoked_at IS NULL
               )",
        'count_params' => [
            'cutoff' => 'used_tokens',
        ],
        'delete_sql' => "DELETE FROM sr_identity_verification_results
             WHERE expires_at IS NOT NULL
               AND expires_at < :cutoff
               AND NOT EXISTS (
                    SELECT 1 FROM sr_identity_verification_links l
                    WHERE l.result_id = sr_identity_verification_results.id
                      AND l.revoked_at IS NULL
               )",
        'delete_limited_sql' => "DELETE FROM sr_identity_verification_results
             WHERE expires_at IS NOT NULL
               AND expires_at < :cutoff
               AND NOT EXISTS (
                    SELECT 1 FROM sr_identity_verification_links l
                    WHERE l.result_id = sr_identity_verification_results.id
                      AND l.revoked_at IS NULL
               )
             ORDER BY id ASC
             LIMIT {limit}",
        'delete_params' => [
            'cutoff' => 'used_tokens',
        ],
        'table_checks' => [
            'SELECT 1 FROM sr_identity_verification_results LIMIT 1',
            'SELECT 1 FROM sr_identity_verification_links LIMIT 1',
        ],
    ],
];
