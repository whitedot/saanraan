SET @schema_has_site_menu_items_menu_url_unique = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}site_menu_items'
      AND INDEX_NAME = 'uq_sr_site_menu_items_menu_url'
);

SET @schema_sql = IF(
    @schema_has_site_menu_items_menu_url_unique > 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}site_menu_items DROP INDEX uq_sr_site_menu_items_menu_url',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.002',
    updated_at = NOW()
WHERE module_key = 'site_menu';
