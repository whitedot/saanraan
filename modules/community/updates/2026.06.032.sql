SET @schema_has_community_posts_status_id = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_posts'
      AND INDEX_NAME = 'idx_sr_community_posts_status_id'
);
SET @schema_sql = IF(
    @schema_has_community_posts_status_id = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_posts ADD KEY idx_sr_community_posts_status_id (status, id)',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE sr_modules
SET version = '2026.06.032',
    updated_at = NOW()
WHERE module_key = 'community';
