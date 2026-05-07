SET @schema_has_member_auth_logs_ip_created = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'toy_member_auth_logs'
      AND INDEX_NAME = 'idx_toy_member_auth_logs_ip_created'
);

SET @schema_sql = IF(
    @schema_has_member_auth_logs_ip_created = 0,
    'ALTER TABLE toy_member_auth_logs ADD KEY idx_toy_member_auth_logs_ip_created (ip_address, created_at)',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE toy_modules
SET version = '2026.04.004',
    updated_at = NOW()
WHERE module_key = 'member';
