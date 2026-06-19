SET @schema_has_banner_content_type = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}banners'
      AND COLUMN_NAME = 'content_type'
);

SET @schema_sql = IF(
    @schema_has_banner_content_type = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}banners ADD COLUMN content_type VARCHAR(20) NOT NULL DEFAULT ''text'' AFTER title',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_banner_html_code = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}banners'
      AND COLUMN_NAME = 'html_code'
);

SET @schema_sql = IF(
    @schema_has_banner_html_code = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}banners ADD COLUMN html_code MEDIUMTEXT NULL AFTER body_text',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE {{SR_TABLE_PREFIX}}banners
SET content_type = 'image'
WHERE content_type = 'text'
  AND image_url <> '';

UPDATE sr_modules
SET version = '2026.06.001',
    updated_at = NOW()
WHERE module_key = 'banner';
