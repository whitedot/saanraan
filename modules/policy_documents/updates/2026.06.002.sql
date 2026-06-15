SET @schema_has_policy_documents_type = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}policy_documents'
      AND COLUMN_NAME = 'document_type'
);

SET @schema_sql = IF(
    @schema_has_policy_documents_type > 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}policy_documents DROP COLUMN document_type',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE sr_modules
SET version = '2026.06.002',
    updated_at = NOW()
WHERE module_key = 'policy_documents';
