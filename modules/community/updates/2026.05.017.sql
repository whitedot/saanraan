CREATE TABLE IF NOT EXISTS sr_community_member_nicknames (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    nickname VARCHAR(80) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_member_nicknames_account (account_id),
    KEY idx_sr_community_member_nicknames_nickname (nickname)
);

SET @schema_has_member_profiles_nickname = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}member_profiles'
      AND COLUMN_NAME = 'nickname'
);

SET @schema_sql = IF(
    @schema_has_member_profiles_nickname > 0,
    'INSERT INTO {{SR_TABLE_PREFIX}}community_member_nicknames
        (account_id, nickname, created_at, updated_at)
     SELECT p.account_id,
            p.nickname,
            COALESCE(p.created_at, NOW()),
            COALESCE(p.updated_at, NOW())
     FROM {{SR_TABLE_PREFIX}}member_profiles p
     INNER JOIN {{SR_TABLE_PREFIX}}member_accounts a ON a.id = p.account_id
     LEFT JOIN {{SR_TABLE_PREFIX}}community_member_nicknames n ON n.account_id = p.account_id
     WHERE p.nickname <> ''''
       AND a.status NOT IN (''withdrawn'', ''anonymized'')
       AND n.account_id IS NULL',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_sql = IF(
    @schema_has_member_profiles_nickname > 0,
    'UPDATE {{SR_TABLE_PREFIX}}member_profiles
     SET nickname = ''''
     WHERE nickname <> ''''',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_sql = IF(
    @schema_has_member_profiles_nickname > 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}member_profiles DROP COLUMN nickname',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

DELETE n
FROM sr_community_member_nicknames n
INNER JOIN sr_member_accounts a ON a.id = n.account_id
WHERE a.status IN ('withdrawn', 'anonymized');

DELETE s
FROM sr_module_settings s
INNER JOIN sr_modules m ON m.id = s.module_id
WHERE m.module_key = 'member'
  AND s.setting_key IN ('profile_nickname_enabled', 'profile_nickname_required');

UPDATE sr_modules
SET version = '2026.05.017',
    updated_at = NOW()
WHERE module_key = 'community';
