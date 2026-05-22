SET @schema_has_community_attachment_storage_driver = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_attachments'
      AND COLUMN_NAME = 'storage_driver'
);

SET @schema_sql = IF(
    @schema_has_community_attachment_storage_driver = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_attachments ADD COLUMN storage_driver VARCHAR(20) NOT NULL DEFAULT ''local'' AFTER storage_path',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_community_attachment_storage_key = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_attachments'
      AND COLUMN_NAME = 'storage_key'
);

SET @schema_sql = IF(
    @schema_has_community_attachment_storage_key = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_attachments ADD COLUMN storage_key VARCHAR(255) NOT NULL DEFAULT '''' AFTER storage_driver',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE sr_community_attachments
SET storage_driver = 'local',
    storage_key = CASE
        WHEN storage_key <> '' THEN storage_key
        WHEN storage_path LIKE 'storage/%' THEN SUBSTRING(storage_path, 9)
        ELSE storage_path
    END
WHERE storage_driver = ''
   OR storage_key = '';

SET @schema_has_community_attachment_storage_index = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_attachments'
      AND INDEX_NAME = 'idx_sr_community_attachments_storage'
);

SET @schema_sql = IF(
    @schema_has_community_attachment_storage_index = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_attachments ADD KEY idx_sr_community_attachments_storage (storage_driver, storage_key)',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE sr_modules
SET version = '2026.05.006',
    updated_at = NOW()
WHERE module_key = 'community';
