<?php

declare(strict_types=1);

return array (
  'auth_logs' => 
  array (
    'enabled' => true,
    'auto_scope' => 'public',
    'cutoff_key' => 'auth_logs',
    'count_sql' => 'SELECT COUNT(*) AS count_value FROM sr_member_auth_logs WHERE created_at < :cutoff',
    'count_params' => 
    array (
      'cutoff' => 'auth_logs',
    ),
    'delete_sql' => 'DELETE FROM sr_member_auth_logs WHERE created_at < :cutoff',
    'delete_limited_sql' => 'DELETE FROM sr_member_auth_logs WHERE created_at < :cutoff ORDER BY id ASC LIMIT {limit}',
    'delete_params' => 
    array (
      'cutoff' => 'auth_logs',
    ),
    'table_checks' => 
    array (
      0 => 'SELECT 1 FROM sr_member_auth_logs LIMIT 1',
    ),
  ),
  'password_resets' => 
  array (
    'enabled' => true,
    'auto_scope' => 'public',
    'cutoff_key' => 'used_tokens',
    'count_sql' => 'SELECT COUNT(*) AS count_value FROM sr_member_password_resets WHERE used_at IS NOT NULL AND used_at < :cutoff',
    'count_params' => 
    array (
      'cutoff' => 'used_tokens',
    ),
    'delete_sql' => 'DELETE FROM sr_member_password_resets WHERE used_at IS NOT NULL AND used_at < :cutoff',
    'delete_limited_sql' => 'DELETE FROM sr_member_password_resets WHERE used_at IS NOT NULL AND used_at < :cutoff ORDER BY id ASC LIMIT {limit}',
    'delete_params' => 
    array (
      'cutoff' => 'used_tokens',
    ),
    'table_checks' => 
    array (
      0 => 'SELECT 1 FROM sr_member_password_resets LIMIT 1',
    ),
  ),
  'email_verifications' => 
  array (
    'enabled' => true,
    'auto_scope' => 'public',
    'cutoff_key' => 'used_tokens',
    'count_sql' => 'SELECT COUNT(*) AS count_value FROM sr_member_email_verifications WHERE verified_at IS NOT NULL AND verified_at < :cutoff',
    'count_params' => 
    array (
      'cutoff' => 'used_tokens',
    ),
    'delete_sql' => 'DELETE FROM sr_member_email_verifications WHERE verified_at IS NOT NULL AND verified_at < :cutoff',
    'delete_limited_sql' => 'DELETE FROM sr_member_email_verifications WHERE verified_at IS NOT NULL AND verified_at < :cutoff ORDER BY id ASC LIMIT {limit}',
    'delete_params' => 
    array (
      'cutoff' => 'used_tokens',
    ),
    'table_checks' => 
    array (
      0 => 'SELECT 1 FROM sr_member_email_verifications LIMIT 1',
    ),
  ),
  'sessions' => 
  array (
    'enabled' => true,
    'auto_scope' => 'public',
    'cutoff_key' => 'sessions',
    'count_sql' => 'SELECT COUNT(*) AS count_value
             FROM sr_member_sessions
             WHERE (revoked_at IS NOT NULL AND revoked_at < :revoked_cutoff)
                OR expires_at < :expired_cutoff',
    'count_params' => 
    array (
      'revoked_cutoff' => 'sessions',
      'expired_cutoff' => 'sessions',
    ),
    'delete_sql' => 'DELETE FROM sr_member_sessions
             WHERE (revoked_at IS NOT NULL AND revoked_at < :revoked_cutoff)
                OR expires_at < :expired_cutoff',
    'delete_limited_sql' => 'DELETE FROM sr_member_sessions
             WHERE (revoked_at IS NOT NULL AND revoked_at < :revoked_cutoff)
                OR expires_at < :expired_cutoff
             ORDER BY id ASC
             LIMIT {limit}',
    'delete_params' => 
    array (
      'revoked_cutoff' => 'sessions',
      'expired_cutoff' => 'sessions',
    ),
    'table_checks' => 
    array (
      0 => 'SELECT 1 FROM sr_member_sessions LIMIT 1',
    ),
  ),
);
