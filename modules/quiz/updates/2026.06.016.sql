SET @schema_has_quiz_sets_view_count = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}quiz_sets'
      AND COLUMN_NAME = 'view_count'
);
SET @schema_sql = IF(
    @schema_has_quiz_sets_view_count = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}quiz_sets ADD COLUMN view_count BIGINT UNSIGNED NOT NULL DEFAULT 0',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_quiz_sets_view_count_index = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}quiz_sets'
      AND INDEX_NAME = 'idx_sr_quiz_sets_view_count'
);
SET @schema_sql = IF(
    @schema_has_quiz_sets_view_count_index = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}quiz_sets ADD KEY idx_sr_quiz_sets_view_count (view_count, id)',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE sr_modules
SET version = '2026.06.016',
    updated_at = NOW()
WHERE module_key = 'quiz';
