SET @schema_has_community_posts_summary_feed_candidate = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_posts'
      AND COLUMN_NAME = 'summary_feed_candidate'
);
SET @schema_sql = IF(
    @schema_has_community_posts_summary_feed_candidate = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_posts ADD COLUMN summary_feed_candidate TINYINT(1) NOT NULL DEFAULT 1 AFTER hidden_before_status',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_community_posts_home_status_id = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_posts'
      AND INDEX_NAME = 'idx_sr_community_posts_summary_status_id'
);
SET @schema_sql = IF(
    @schema_has_community_posts_home_status_id = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_posts ADD KEY idx_sr_community_posts_summary_status_id (summary_feed_candidate, status, id)',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_community_posts_home_status_view_id = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_posts'
      AND INDEX_NAME = 'idx_sr_community_posts_summary_status_view_id'
);
SET @schema_sql = IF(
    @schema_has_community_posts_home_status_view_id = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_posts ADD KEY idx_sr_community_posts_summary_status_view_id (summary_feed_candidate, status, view_count, id)',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE {{SR_TABLE_PREFIX}}community_posts p
LEFT JOIN {{SR_TABLE_PREFIX}}community_board_settings home_setting
  ON home_setting.board_id = p.board_id
 AND home_setting.setting_key = 'summary_feed_enabled'
SET p.summary_feed_candidate = IF(COALESCE(home_setting.setting_value, '1') IN ('0', 'false', 'no', 'off'), 0, 1);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.038',
    updated_at = NOW()
WHERE module_key = 'community';
