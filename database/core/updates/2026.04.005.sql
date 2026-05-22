SET @schema_has_site_settings_public = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}site_settings'
      AND COLUMN_NAME = 'is_public'
);

SET @schema_sql = IF(
    @schema_has_site_settings_public > 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}site_settings DROP COLUMN is_public',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;
