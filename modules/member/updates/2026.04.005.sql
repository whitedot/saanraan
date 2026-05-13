SET @schema_has_member_auth_logs_account_event_created = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sr_member_auth_logs'
      AND INDEX_NAME = 'idx_sr_member_auth_logs_account_event_created'
);

SET @schema_sql = IF(
    @schema_has_member_auth_logs_account_event_created = 0,
    'ALTER TABLE sr_member_auth_logs ADD KEY idx_sr_member_auth_logs_account_event_created (account_id, event_type, created_at)',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_member_auth_logs_ip_event_created = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sr_member_auth_logs'
      AND INDEX_NAME = 'idx_sr_member_auth_logs_ip_event_created'
);

SET @schema_sql = IF(
    @schema_has_member_auth_logs_ip_event_created = 0,
    'ALTER TABLE sr_member_auth_logs ADD KEY idx_sr_member_auth_logs_ip_event_created (ip_address, event_type, created_at)',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE sr_modules
SET version = '2026.04.005',
    updated_at = NOW()
WHERE module_key = 'member';
