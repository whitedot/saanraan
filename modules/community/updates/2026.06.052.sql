SET @sr_community_posts_body_format_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_posts'
      AND COLUMN_NAME = 'body_format'
);
SET @sr_community_posts_body_format_sql = IF(
    @sr_community_posts_body_format_exists > 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_posts DROP COLUMN body_format',
    'DO 0'
);
PREPARE sr_community_posts_body_format_stmt FROM @sr_community_posts_body_format_sql;
EXECUTE sr_community_posts_body_format_stmt;
DEALLOCATE PREPARE sr_community_posts_body_format_stmt;

DELETE FROM {{SR_TABLE_PREFIX}}community_board_group_settings
WHERE setting_key = 'post_editor';

DELETE FROM {{SR_TABLE_PREFIX}}community_board_setting_sources
WHERE setting_key = 'post_editor';
