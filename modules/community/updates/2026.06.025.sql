SET @schema_has_community_consents_policy_document_key = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}community_submission_consents'
      AND COLUMN_NAME = 'policy_document_key_snapshot'
);

SET @schema_sql = IF(
    @schema_has_community_consents_policy_document_key = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}community_submission_consents
        ADD COLUMN policy_document_key_snapshot VARCHAR(80) NOT NULL DEFAULT '''' AFTER account_id,
        ADD COLUMN policy_version_key_snapshot VARCHAR(40) NOT NULL DEFAULT '''' AFTER policy_document_key_snapshot,
        ADD COLUMN policy_document_version_id BIGINT UNSIGNED NULL AFTER policy_version_key_snapshot,
        ADD COLUMN consent_body_hash CHAR(64) NOT NULL DEFAULT '''' AFTER consent_body_snapshot',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE sr_modules
SET version = '2026.06.025',
    updated_at = NOW()
WHERE module_key = 'community';
