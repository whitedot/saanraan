<?php

declare(strict_types=1);

return array (
  'notifications' =>
  array (
    'enabled' => true,
    'auto_scope' => 'public',
    'cutoff_key' => 'notifications',
    'count_sql' => 'SELECT COUNT(*) AS count_value FROM sr_notifications WHERE created_at < :cutoff',
    'count_params' =>
    array (
      'cutoff' => 'notifications',
    ),
    'delete_sql' => 'DELETE FROM sr_notifications WHERE created_at < :cutoff',
    'delete_limited_sql' => 'DELETE FROM sr_notifications
             WHERE created_at < :cutoff
               AND NOT EXISTS (
                    SELECT 1 FROM sr_notification_deliveries d WHERE d.notification_id = sr_notifications.id
               )
               AND NOT EXISTS (
                    SELECT 1 FROM sr_notification_reads r WHERE r.notification_id = sr_notifications.id
               )
             ORDER BY id ASC
             LIMIT {limit}',
    'delete_params' =>
    array (
      'cutoff' => 'notifications',
    ),
    'table_checks' =>
    array (
      0 => 'SELECT 1 FROM sr_notifications LIMIT 1',
      1 => 'SELECT 1 FROM sr_notification_deliveries LIMIT 1',
      2 => 'SELECT 1 FROM sr_notification_reads LIMIT 1',
    ),
  ),
  'notification_deliveries' =>
  array (
    'enabled' => true,
    'auto_scope' => 'public',
    'cutoff_key' => 'notifications',
    'count_sql' => 'SELECT COUNT(*) AS count_value
             FROM sr_notification_deliveries d
             INNER JOIN sr_notifications n ON n.id = d.notification_id
             WHERE n.created_at < :cutoff',
    'count_params' =>
    array (
      'cutoff' => 'notifications',
    ),
    'delete_sql' => 'DELETE d
             FROM sr_notification_deliveries d
             INNER JOIN sr_notifications n ON n.id = d.notification_id
             WHERE n.created_at < :cutoff',
    'delete_limited_sql' => 'DELETE FROM sr_notification_deliveries
             WHERE notification_id IN (
                SELECT id FROM sr_notifications WHERE created_at < :cutoff
             )
             LIMIT {limit}',
    'delete_params' =>
    array (
      'cutoff' => 'notifications',
    ),
    'table_checks' =>
    array (
      0 => 'SELECT 1 FROM sr_notifications LIMIT 1',
      1 => 'SELECT 1 FROM sr_notification_deliveries LIMIT 1',
      2 => 'SELECT 1 FROM sr_notification_reads LIMIT 1',
    ),
  ),
  'notification_reads' =>
  array (
    'enabled' => true,
    'auto_scope' => 'public',
    'cutoff_key' => 'notifications',
    'count_sql' => 'SELECT COUNT(*) AS count_value
             FROM sr_notification_reads r
             INNER JOIN sr_notifications n ON n.id = r.notification_id
             WHERE n.created_at < :cutoff',
    'count_params' =>
    array (
      'cutoff' => 'notifications',
    ),
    'delete_sql' => 'DELETE r
             FROM sr_notification_reads r
             INNER JOIN sr_notifications n ON n.id = r.notification_id
             WHERE n.created_at < :cutoff',
    'delete_limited_sql' => 'DELETE FROM sr_notification_reads
             WHERE notification_id IN (
                SELECT id FROM sr_notifications WHERE created_at < :cutoff
             )
             LIMIT {limit}',
    'delete_params' =>
    array (
      'cutoff' => 'notifications',
    ),
    'table_checks' =>
    array (
      0 => 'SELECT 1 FROM sr_notifications LIMIT 1',
      1 => 'SELECT 1 FROM sr_notification_deliveries LIMIT 1',
      2 => 'SELECT 1 FROM sr_notification_reads LIMIT 1',
    ),
  ),
  'admin_notification_reads' =>
  array (
    'enabled' => true,
    'auto_scope' => 'admin',
    'cutoff_key' => 'notifications',
    'count_sql' => 'SELECT COUNT(*) AS count_value
             FROM sr_admin_notification_reads r
             INNER JOIN sr_admin_notifications n ON n.id = r.notification_id
             WHERE n.status <> \'open\'
               AND COALESCE(n.archived_at, n.processed_at, n.updated_at, n.created_at) < :cutoff',
    'count_params' =>
    array (
      'cutoff' => 'notifications',
    ),
    'delete_sql' => 'DELETE r
             FROM sr_admin_notification_reads r
             INNER JOIN sr_admin_notifications n ON n.id = r.notification_id
             WHERE n.status <> \'open\'
               AND COALESCE(n.archived_at, n.processed_at, n.updated_at, n.created_at) < :cutoff',
    'delete_limited_sql' => 'DELETE FROM sr_admin_notification_reads
             WHERE notification_id IN (
                SELECT id FROM sr_admin_notifications
                WHERE status <> \'open\'
                  AND COALESCE(archived_at, processed_at, updated_at, created_at) < :cutoff
             )
             LIMIT {limit}',
    'delete_params' =>
    array (
      'cutoff' => 'notifications',
    ),
    'table_checks' =>
    array (
      0 => 'SELECT 1 FROM sr_admin_notifications LIMIT 1',
      1 => 'SELECT 1 FROM sr_admin_notification_reads LIMIT 1',
    ),
  ),
  'admin_notifications' =>
  array (
    'enabled' => true,
    'auto_scope' => 'admin',
    'cutoff_key' => 'notifications',
    'count_sql' => 'SELECT COUNT(*) AS count_value
             FROM sr_admin_notifications
             WHERE status <> \'open\'
               AND COALESCE(archived_at, processed_at, updated_at, created_at) < :cutoff',
    'count_params' =>
    array (
      'cutoff' => 'notifications',
    ),
    'delete_sql' => 'DELETE FROM sr_admin_notifications
             WHERE status <> \'open\'
               AND COALESCE(archived_at, processed_at, updated_at, created_at) < :cutoff',
    'delete_limited_sql' => 'DELETE FROM sr_admin_notifications
             WHERE status <> \'open\'
               AND COALESCE(archived_at, processed_at, updated_at, created_at) < :cutoff
               AND NOT EXISTS (
                    SELECT 1 FROM sr_admin_notification_reads r WHERE r.notification_id = sr_admin_notifications.id
               )
             ORDER BY id ASC
             LIMIT {limit}',
    'delete_params' =>
    array (
      'cutoff' => 'notifications',
    ),
    'table_checks' =>
    array (
      0 => 'SELECT 1 FROM sr_admin_notifications LIMIT 1',
      1 => 'SELECT 1 FROM sr_admin_notification_reads LIMIT 1',
    ),
  ),
);
