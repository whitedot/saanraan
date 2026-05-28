SET @schema_has_content_file_links = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_file_links'
);
SET @schema_sql = IF(
    @schema_has_content_file_links = 0,
    'CREATE TABLE {{SR_TABLE_PREFIX}}content_file_links (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        content_id BIGINT UNSIGNED NOT NULL,
        file_id BIGINT UNSIGNED NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        status VARCHAR(30) NOT NULL DEFAULT ''active'',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_sr_content_file_links_content_file (content_id, file_id),
        KEY idx_sr_content_file_links_file_status (file_id, status),
        KEY idx_sr_content_file_links_content_status (content_id, status, sort_order, id)
    )',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_content_files_content_id_default = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}content_files'
      AND COLUMN_NAME = 'content_id'
      AND COLUMN_DEFAULT = '0'
);
SET @schema_sql = IF(
    @schema_content_files_content_id_default = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}content_files MODIFY content_id BIGINT UNSIGNED NOT NULL DEFAULT 0',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

INSERT IGNORE INTO sr_content_file_links
    (content_id, file_id, sort_order, status, created_at, updated_at)
SELECT content_id,
       id,
       0,
       'active',
       created_at,
       updated_at
FROM sr_content_files
WHERE content_id > 0;

INSERT IGNORE INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id,
       '/admin/content/files',
       action_key,
       NOW()
FROM sr_admin_account_permissions
WHERE menu_path = '/admin/content';

UPDATE sr_modules
SET version = '2026.05.014',
    updated_at = NOW()
WHERE module_key = 'content';
