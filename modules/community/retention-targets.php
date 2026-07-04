<?php

declare(strict_types=1);

return array (
  'community_asset_pending_logs' =>
  array (
    'enabled' => true,
    'auto_scope' => 'admin',
    'cutoff_key' => 'sessions',
    'count_sql' => 'SELECT COUNT(*) AS count_value
             FROM sr_community_asset_logs
             WHERE log_status = \'pending\'
               AND created_at < :cutoff',
    'count_params' =>
    array (
      'cutoff' => 'sessions',
    ),
    'delete_sql' => 'DELETE FROM sr_community_asset_logs
             WHERE log_status = \'pending\'
               AND created_at < :cutoff',
    'delete_limited_sql' => 'DELETE FROM sr_community_asset_logs
             WHERE log_status = \'pending\'
               AND created_at < :cutoff
             ORDER BY id ASC
             LIMIT {limit}',
    'delete_params' =>
    array (
      'cutoff' => 'sessions',
    ),
    'table_checks' =>
    array (
      0 => 'SELECT log_status FROM sr_community_asset_logs LIMIT 1',
    ),
  ),
);
