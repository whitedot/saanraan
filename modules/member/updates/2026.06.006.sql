SET @schema_has_member_profiles_is_adult = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}member_profiles'
      AND COLUMN_NAME = 'is_adult'
);

SET @schema_sql = IF(
    @schema_has_member_profiles_is_adult = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}member_profiles ADD COLUMN is_adult TINYINT(1) NULL AFTER birth_date',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.006',
    updated_at = NOW()
WHERE module_key = 'member';
