SET @schema_has_community_posts = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_posts'
);
SET @schema_has_community_posts_author_public_name_snapshot = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_posts'
      AND COLUMN_NAME = 'author_public_name_snapshot'
);
SET @schema_sql = IF(
    @schema_has_community_posts = 1
      AND @schema_has_community_posts_author_public_name_snapshot = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_posts ADD COLUMN author_public_name_snapshot VARCHAR(120) NOT NULL DEFAULT '''' AFTER author_account_id',
    'DO 0'
);
PREPARE stmt FROM @schema_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_community_comments = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_comments'
);
SET @schema_has_community_comments_author_public_name_snapshot = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_comments'
      AND COLUMN_NAME = 'author_public_name_snapshot'
);
SET @schema_sql = IF(
    @schema_has_community_comments = 1
      AND @schema_has_community_comments_author_public_name_snapshot = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_comments ADD COLUMN author_public_name_snapshot VARCHAR(120) NOT NULL DEFAULT '''' AFTER author_account_id',
    'DO 0'
);
PREPARE stmt FROM @schema_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @member_nickname_enabled = (
    SELECT COALESCE(MAX(CASE WHEN s.setting_value IN ('1', 'true', 'on', 'yes') THEN 1 ELSE 0 END), 1)
    FROM {{SR_TABLE_PREFIX}}modules m
    LEFT JOIN {{SR_TABLE_PREFIX}}module_settings s
        ON s.module_id = m.id
       AND s.setting_key = 'nickname_enabled'
    WHERE m.module_key = 'member'
);

SET @schema_has_member_nicknames = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}member_nicknames'
);
SET @schema_sql = IF(
    @schema_has_community_posts = 1
      AND @schema_has_member_nicknames = 1,
    'UPDATE {{SR_TABLE_PREFIX}}community_posts p LEFT JOIN {{SR_TABLE_PREFIX}}member_accounts a ON a.id = p.author_account_id LEFT JOIN {{SR_TABLE_PREFIX}}member_nicknames n ON n.account_id = a.id SET p.author_public_name_snapshot = LEFT(TRIM(COALESCE(IF(@member_nickname_enabled = 1, NULLIF(n.nickname, ''''), NULL), NULLIF(a.display_name, ''''), ''회원'')), 120) WHERE p.author_public_name_snapshot = ''''',
    IF(
        @schema_has_community_posts = 1,
        'UPDATE {{SR_TABLE_PREFIX}}community_posts p LEFT JOIN {{SR_TABLE_PREFIX}}member_accounts a ON a.id = p.author_account_id SET p.author_public_name_snapshot = LEFT(TRIM(COALESCE(NULLIF(a.display_name, ''''), ''회원'')), 120) WHERE p.author_public_name_snapshot = ''''',
        'DO 0'
    )
);
PREPARE stmt FROM @schema_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_sql = IF(
    @schema_has_community_comments = 1
      AND @schema_has_member_nicknames = 1,
    'UPDATE {{SR_TABLE_PREFIX}}community_comments c LEFT JOIN {{SR_TABLE_PREFIX}}member_accounts a ON a.id = c.author_account_id LEFT JOIN {{SR_TABLE_PREFIX}}member_nicknames n ON n.account_id = a.id SET c.author_public_name_snapshot = LEFT(TRIM(COALESCE(IF(@member_nickname_enabled = 1, NULLIF(n.nickname, ''''), NULL), NULLIF(a.display_name, ''''), ''회원'')), 120) WHERE c.author_public_name_snapshot = ''''',
    IF(
        @schema_has_community_comments = 1,
        'UPDATE {{SR_TABLE_PREFIX}}community_comments c LEFT JOIN {{SR_TABLE_PREFIX}}member_accounts a ON a.id = c.author_account_id SET c.author_public_name_snapshot = LEFT(TRIM(COALESCE(NULLIF(a.display_name, ''''), ''회원'')), 120) WHERE c.author_public_name_snapshot = ''''',
        'DO 0'
    )
);
PREPARE stmt FROM @schema_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.005',
    updated_at = NOW()
WHERE module_key = 'community';
