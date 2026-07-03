SET @schema_has_policy_document_versions_key_index = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}policy_document_versions'
      AND INDEX_NAME = 'uq_sr_policy_document_versions_key'
);
SET @schema_sql = IF(
    @schema_has_policy_document_versions_key_index > 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}policy_document_versions DROP INDEX uq_sr_policy_document_versions_key',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

SET @schema_has_policy_document_versions_version_key = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}policy_document_versions'
      AND COLUMN_NAME = 'version_key'
);
SET @schema_sql = IF(
    @schema_has_policy_document_versions_version_key > 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}policy_document_versions DROP COLUMN version_key',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE sr_modules
SET version = '2026.07.002',
    updated_at = NOW()
WHERE module_key = 'policy_documents';
