SET @schema_has_sessions_session_id_hash = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}sessions'
      AND COLUMN_NAME = 'session_id_hash'
);
SET @schema_sql = IF(
    @schema_has_sessions_session_id_hash = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}sessions ADD COLUMN session_id_hash CHAR(64) NULL',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_sessions_session_id = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}sessions'
      AND COLUMN_NAME = 'session_id'
);
SET @schema_sql = IF(
    @schema_has_sessions_session_id > 0,
    'UPDATE {{SR_TABLE_PREFIX}}sessions SET session_id_hash = SHA2(session_id, 256) WHERE (session_id_hash IS NULL OR session_id_hash = '''') AND session_id IS NOT NULL AND session_id <> ''''',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

DELETE FROM {{SR_TABLE_PREFIX}}sessions
WHERE session_id_hash IS NULL
   OR session_id_hash = '';

SET @schema_sessions_session_id_hash_needs_not_null = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}sessions'
      AND COLUMN_NAME = 'session_id_hash'
      AND (IS_NULLABLE <> 'NO' OR COLUMN_TYPE <> 'char(64)')
);
SET @schema_sql = IF(
    @schema_sessions_session_id_hash_needs_not_null > 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}sessions MODIFY COLUMN session_id_hash CHAR(64) NOT NULL',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_sessions_session_id_index = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}sessions'
      AND INDEX_NAME = 'uq_sr_sessions_session_id'
);
SET @schema_sql = IF(
    @schema_has_sessions_session_id_index > 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}sessions DROP INDEX uq_sr_sessions_session_id',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_sessions_session_id = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}sessions'
      AND COLUMN_NAME = 'session_id'
);
SET @schema_sql = IF(
    @schema_has_sessions_session_id > 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}sessions DROP COLUMN session_id',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_sessions_session_id_hash_index = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}sessions'
      AND INDEX_NAME = 'uq_sr_sessions_session_id_hash'
);
SET @schema_sql = IF(
    @schema_has_sessions_session_id_hash_index = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}sessions ADD UNIQUE KEY uq_sr_sessions_session_id_hash (session_id_hash)',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;
