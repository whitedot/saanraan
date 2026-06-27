SET @schema_has_community_posts_summary_feed_candidate = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_posts'
      AND COLUMN_NAME = 'summary_feed_candidate'
);
SET @schema_has_community_posts_home_feed_candidate = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_posts'
      AND COLUMN_NAME = 'home_feed_candidate'
);
SET @schema_sql = IF(
    @schema_has_community_posts_summary_feed_candidate = 0 AND @schema_has_community_posts_home_feed_candidate > 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_posts CHANGE COLUMN home_feed_candidate summary_feed_candidate TINYINT(1) NOT NULL DEFAULT 1',
    IF(
        @schema_has_community_posts_summary_feed_candidate = 0,
        'ALTER TABLE {{SR_TABLE_PREFIX}}community_posts ADD COLUMN summary_feed_candidate TINYINT(1) NOT NULL DEFAULT 1 AFTER hidden_before_status',
        'DO 0'
    )
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_community_posts_home_status_id = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_posts'
      AND INDEX_NAME = 'idx_sr_community_posts_home_status_id'
);
SET @schema_sql = IF(
    @schema_has_community_posts_home_status_id > 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_posts DROP INDEX idx_sr_community_posts_home_status_id',
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
      AND INDEX_NAME = 'idx_sr_community_posts_home_status_view_id'
);
SET @schema_sql = IF(
    @schema_has_community_posts_home_status_view_id > 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_posts DROP INDEX idx_sr_community_posts_home_status_view_id',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_community_posts_summary_status_id = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_posts'
      AND INDEX_NAME = 'idx_sr_community_posts_summary_status_id'
);
SET @schema_sql = IF(
    @schema_has_community_posts_summary_status_id = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_posts ADD KEY idx_sr_community_posts_summary_status_id (summary_feed_candidate, status, id)',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_community_posts_summary_status_view_id = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_posts'
      AND INDEX_NAME = 'idx_sr_community_posts_summary_status_view_id'
);
SET @schema_sql = IF(
    @schema_has_community_posts_summary_status_view_id = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_posts ADD KEY idx_sr_community_posts_summary_status_view_id (summary_feed_candidate, status, view_count, id)',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

INSERT INTO {{SR_TABLE_PREFIX}}community_board_settings
    (board_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT legacy_setting.board_id,
       'summary_feed_enabled',
       legacy_setting.setting_value,
       legacy_setting.value_type,
       NOW(),
       NOW()
FROM {{SR_TABLE_PREFIX}}community_board_settings legacy_setting
LEFT JOIN {{SR_TABLE_PREFIX}}community_board_settings summary_setting
  ON summary_setting.board_id = legacy_setting.board_id
 AND summary_setting.setting_key = 'summary_feed_enabled'
WHERE legacy_setting.setting_key = 'home_feed_enabled'
  AND summary_setting.board_id IS NULL;

INSERT INTO {{SR_TABLE_PREFIX}}community_board_setting_sources
    (board_id, setting_key, source, created_at, updated_at)
SELECT legacy_source.board_id,
       'summary_feed_enabled',
       legacy_source.source,
       NOW(),
       NOW()
FROM {{SR_TABLE_PREFIX}}community_board_setting_sources legacy_source
LEFT JOIN {{SR_TABLE_PREFIX}}community_board_setting_sources summary_source
  ON summary_source.board_id = legacy_source.board_id
 AND summary_source.setting_key = 'summary_feed_enabled'
WHERE legacy_source.setting_key = 'home_feed_enabled'
  AND summary_source.board_id IS NULL;

UPDATE {{SR_TABLE_PREFIX}}community_posts p
LEFT JOIN {{SR_TABLE_PREFIX}}community_board_settings summary_setting
  ON summary_setting.board_id = p.board_id
 AND summary_setting.setting_key = 'summary_feed_enabled'
LEFT JOIN {{SR_TABLE_PREFIX}}community_board_settings legacy_setting
  ON legacy_setting.board_id = p.board_id
 AND legacy_setting.setting_key = 'home_feed_enabled'
SET p.summary_feed_candidate = IF(COALESCE(summary_setting.setting_value, legacy_setting.setting_value, '1') IN ('0', 'false', 'no', 'off'), 0, 1);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.039',
    updated_at = NOW()
WHERE module_key = 'community';
