SET @schema_has_sessions_session_id_hash = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sr_sessions'
      AND COLUMN_NAME = 'session_id_hash'
);

SET @schema_sql = IF(
    @schema_has_sessions_session_id_hash = 0,
    'ALTER TABLE sr_sessions ADD COLUMN session_id_hash CHAR(64) NULL AFTER session_id',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE sr_sessions
SET session_id_hash = SHA2(session_id, 256)
WHERE (session_id_hash IS NULL OR session_id_hash = '')
  AND session_id IS NOT NULL
  AND session_id <> '';

ALTER TABLE sr_sessions MODIFY session_id VARCHAR(128) NULL;

UPDATE sr_sessions
SET session_id = NULL
WHERE session_id IS NOT NULL;

SET @schema_has_sessions_session_id_hash_index = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sr_sessions'
      AND INDEX_NAME = 'uq_sr_sessions_session_id_hash'
);

SET @schema_sql = IF(
    @schema_has_sessions_session_id_hash_index = 0,
    'ALTER TABLE sr_sessions ADD UNIQUE KEY uq_sr_sessions_session_id_hash (session_id_hash)',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;
