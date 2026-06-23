SET @schema_has_member_accounts_login_id_hash = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}member_accounts'
      AND COLUMN_NAME = 'login_id_hash'
);

SET @schema_sql = IF(
    @schema_has_member_accounts_login_id_hash = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}member_accounts ADD COLUMN login_id_hash CHAR(64) NULL AFTER account_identifier_hash',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_member_login_id_hash_unique = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}member_accounts'
      AND INDEX_NAME = 'uq_sr_member_login_id_hash'
);

SET @schema_sql = IF(
    @schema_has_member_login_id_hash_unique = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}member_accounts ADD UNIQUE KEY uq_sr_member_login_id_hash (login_id_hash)',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE sr_modules
SET version = '2026.05.005',
    updated_at = NOW()
WHERE module_key = 'member';
