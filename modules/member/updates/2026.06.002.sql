SET @schema_has_member_consents_policy_document_key = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = '{{SR_TABLE_PREFIX}}member_consents'
      AND COLUMN_NAME = 'policy_document_key_snapshot'
);

SET @schema_sql = IF(
    @schema_has_member_consents_policy_document_key = 0,
    'ALTER TABLE {{SR_TABLE_PREFIX}}member_consents
        ADD COLUMN policy_document_key_snapshot VARCHAR(80) NOT NULL DEFAULT '''' AFTER consent_version,
        ADD COLUMN policy_version_key_snapshot VARCHAR(40) NOT NULL DEFAULT '''' AFTER policy_document_key_snapshot,
        ADD COLUMN policy_document_version_id BIGINT UNSIGNED NULL AFTER policy_version_key_snapshot,
        ADD COLUMN consent_title_snapshot VARCHAR(190) NOT NULL DEFAULT '''' AFTER policy_document_version_id,
        ADD COLUMN consent_body_hash CHAR(64) NOT NULL DEFAULT '''' AFTER consent_title_snapshot,
        ADD COLUMN consent_required TINYINT(1) NOT NULL DEFAULT 0 AFTER consent_body_hash',
    'DO 0'
);
PREPARE schema_stmt FROM @schema_sql;
EXECUTE schema_stmt;
DEALLOCATE PREPARE schema_stmt;

UPDATE sr_modules
SET version = '2026.06.002',
    updated_at = NOW()
WHERE module_key = 'member';
