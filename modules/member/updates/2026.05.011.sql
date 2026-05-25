SET @schema_member_profile_column_name = CONCAT('nick', 'name');

SET @schema_has_member_profiles_legacy_column = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}member_profiles'
      AND COLUMN_NAME = @schema_member_profile_column_name
);

SET @schema_sql = IF(
    @schema_has_member_profiles_legacy_column > 0,
    CONCAT('ALTER TABLE {{SR_TABLE_PREFIX}}member_profiles DROP COLUMN ', @schema_member_profile_column_name),
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

DELETE s
FROM sr_module_settings s
INNER JOIN sr_modules m ON m.id = s.module_id
WHERE m.module_key = 'member'
  AND s.setting_key IN (
      CONCAT('profile_', @schema_member_profile_column_name, '_enabled'),
      CONCAT('profile_', @schema_member_profile_column_name, '_required')
  );

UPDATE sr_modules
SET version = '2026.05.011',
    updated_at = NOW()
WHERE module_key = 'member';
