SET @schema_has_admin_menu_overrides_icon_name = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}admin_menu_overrides'
      AND COLUMN_NAME = 'icon_name'
);

SET @schema_sql = IF(
    @schema_has_admin_menu_overrides_icon_name = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}admin_menu_overrides ADD COLUMN icon_name VARCHAR(40) NOT NULL DEFAULT '''' AFTER is_hidden',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.001',
    updated_at = NOW()
WHERE module_key = 'admin';
