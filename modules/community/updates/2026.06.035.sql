SET @schema_has_community_posts_status_view_id = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_posts'
      AND INDEX_NAME = 'idx_sr_community_posts_status_view_id'
);
SET @schema_sql = IF(
    @schema_has_community_posts_status_view_id = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_posts ADD KEY idx_sr_community_posts_status_view_id (status, view_count, id)',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.035',
    updated_at = NOW()
WHERE module_key = 'community';
