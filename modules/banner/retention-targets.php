<?php

declare(strict_types=1);

return array (
  'banner_clicks' =>
  array (
    'enabled' => true,
    'auto_scope' => 'public',
    'cutoff_key' => 'banner_clicks',
    'count_sql' => 'SELECT COUNT(*) AS count_value FROM sr_banner_clicks WHERE clicked_at < :cutoff',
    'count_params' =>
    array (
      'cutoff' => 'banner_clicks',
    ),
    'delete_sql' => 'DELETE FROM sr_banner_clicks WHERE clicked_at < :cutoff',
    'delete_limited_sql' => 'DELETE FROM sr_banner_clicks WHERE clicked_at < :cutoff ORDER BY id ASC LIMIT {limit}',
    'delete_params' =>
    array (
      'cutoff' => 'banner_clicks',
    ),
    'table_checks' =>
    array (
      0 => 'SELECT 1 FROM sr_banner_clicks LIMIT 1',
    ),
  ),
);
