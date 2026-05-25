UPDATE {{SR_TABLE_PREFIX}}community_member_nicknames
SET nickname = TRIM(nickname)
WHERE nickname <> TRIM(nickname);

DELETE FROM {{SR_TABLE_PREFIX}}community_member_nicknames
WHERE nickname = '';

UPDATE {{SR_TABLE_PREFIX}}community_member_nicknames n
INNER JOIN (
    SELECT d.id,
           CONCAT('회원중복정리', d.id, '_', d.account_id, '_', SUBSTRING(MD5(CONCAT(d.id, ''-'', d.account_id, ''-'', d.nickname)), 1, 12)) AS new_nickname
    FROM {{SR_TABLE_PREFIX}}community_member_nicknames d
    INNER JOIN (
        SELECT nickname, MIN(id) AS keep_id
        FROM {{SR_TABLE_PREFIX}}community_member_nicknames
        WHERE nickname <> ''
        GROUP BY nickname
        HAVING COUNT(*) > 1
    ) duplicated ON duplicated.nickname = d.nickname
    WHERE d.id <> duplicated.keep_id
) replacements ON replacements.id = n.id
SET n.nickname = replacements.new_nickname,
    n.updated_at = NOW();

SET @schema_has_unique_nickname = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_member_nicknames'
      AND INDEX_NAME = 'uq_sr_community_member_nicknames_nickname'
);

SET @schema_sql = IF(
    @schema_has_unique_nickname = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_member_nicknames ADD UNIQUE KEY uq_sr_community_member_nicknames_nickname (nickname)',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.05.018',
    updated_at = NOW()
WHERE module_key = 'community';
