SET @schema_has_content_file_download_logs = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_file_download_logs'
);

SET @schema_has_content_file_download_logs_content_title_snapshot = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_file_download_logs'
      AND COLUMN_NAME = 'content_title_snapshot'
);
SET @schema_sql = IF(
    @schema_has_content_file_download_logs = 1
      AND @schema_has_content_file_download_logs_content_title_snapshot = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}content_file_download_logs ADD COLUMN content_title_snapshot VARCHAR(160) NOT NULL DEFAULT '''' AFTER content_id',
    'DO 0'
);
PREPARE stmt FROM @schema_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_content_file_download_logs_content_slug_snapshot = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_file_download_logs'
      AND COLUMN_NAME = 'content_slug_snapshot'
);
SET @schema_sql = IF(
    @schema_has_content_file_download_logs = 1
      AND @schema_has_content_file_download_logs_content_slug_snapshot = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}content_file_download_logs ADD COLUMN content_slug_snapshot VARCHAR(160) NOT NULL DEFAULT '''' AFTER content_title_snapshot',
    'DO 0'
);
PREPARE stmt FROM @schema_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_content_file_download_logs_file_title_snapshot = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_file_download_logs'
      AND COLUMN_NAME = 'file_title_snapshot'
);
SET @schema_sql = IF(
    @schema_has_content_file_download_logs = 1
      AND @schema_has_content_file_download_logs_file_title_snapshot = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}content_file_download_logs ADD COLUMN file_title_snapshot VARCHAR(160) NOT NULL DEFAULT '''' AFTER file_id',
    'DO 0'
);
PREPARE stmt FROM @schema_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_content_file_download_logs_file_original_name_snapshot = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_file_download_logs'
      AND COLUMN_NAME = 'file_original_name_snapshot'
);
SET @schema_sql = IF(
    @schema_has_content_file_download_logs = 1
      AND @schema_has_content_file_download_logs_file_original_name_snapshot = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}content_file_download_logs ADD COLUMN file_original_name_snapshot VARCHAR(160) NOT NULL DEFAULT '''' AFTER file_title_snapshot',
    'DO 0'
);
PREPARE stmt FROM @schema_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @schema_has_content_items = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_items'
);
SET @schema_has_content_files = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_files'
);
SET @schema_sql = IF(
    @schema_has_content_file_download_logs = 1
      AND @schema_has_content_items = 1
      AND @schema_has_content_files = 1,
    'UPDATE {{SR_TABLE_PREFIX}}content_file_download_logs d LEFT JOIN {{SR_TABLE_PREFIX}}content_items p ON p.id = d.content_id LEFT JOIN {{SR_TABLE_PREFIX}}content_files f ON f.id = d.file_id SET d.content_title_snapshot = LEFT(COALESCE(NULLIF(p.title, ''''), d.content_title_snapshot), 160), d.content_slug_snapshot = LEFT(COALESCE(NULLIF(p.slug, ''''), d.content_slug_snapshot), 160), d.file_title_snapshot = LEFT(COALESCE(NULLIF(f.title, ''''), d.file_title_snapshot), 160), d.file_original_name_snapshot = LEFT(COALESCE(NULLIF(f.original_name, ''''), d.file_original_name_snapshot), 160) WHERE d.content_title_snapshot = '''' OR d.content_slug_snapshot = '''' OR d.file_title_snapshot = '''' OR d.file_original_name_snapshot = ''''',
    'DO 0'
);
PREPARE stmt FROM @schema_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.005',
    updated_at = NOW()
WHERE module_key = 'content';
