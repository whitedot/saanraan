SET @schema_has_member_auth_logs_ip_created = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}member_auth_logs'
      AND INDEX_NAME = 'idx_sr_member_auth_logs_ip_created'
);

SET @schema_sql = IF(
    @schema_has_member_auth_logs_ip_created = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}member_auth_logs ADD KEY idx_sr_member_auth_logs_ip_created (ip_address, created_at)',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE sr_modules
SET version = '2026.04.004',
    updated_at = NOW()
WHERE module_key = 'member';
