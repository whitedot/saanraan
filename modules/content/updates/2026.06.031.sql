SET @sr_content_items_editor_key_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_items'
      AND COLUMN_NAME = 'editor_key'
);

SET @sr_content_items_editor_key_sql = IF(
    @sr_content_items_editor_key_exists = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}content_items ADD COLUMN editor_key VARCHAR(40) NOT NULL DEFAULT ''textarea'' AFTER body_format',
    'DO 0'
);
PREPARE sr_content_items_editor_key_stmt FROM @sr_content_items_editor_key_sql;
EXECUTE sr_content_items_editor_key_stmt;
DEALLOCATE PREPARE sr_content_items_editor_key_stmt;

SET @sr_content_revisions_editor_key_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_revisions'
      AND COLUMN_NAME = 'editor_key'
);

SET @sr_content_revisions_editor_key_sql = IF(
    @sr_content_revisions_editor_key_exists = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}content_revisions ADD COLUMN editor_key VARCHAR(40) NOT NULL DEFAULT ''textarea'' AFTER body_format',
    'DO 0'
);
PREPARE sr_content_revisions_editor_key_stmt FROM @sr_content_revisions_editor_key_sql;
EXECUTE sr_content_revisions_editor_key_stmt;
DEALLOCATE PREPARE sr_content_revisions_editor_key_stmt;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.031',
    updated_at = NOW()
WHERE module_key = 'content';
