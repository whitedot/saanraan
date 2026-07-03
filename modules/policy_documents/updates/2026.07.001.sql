ALTER TABLE sr_policy_document_versions
    ADD COLUMN append_previous_versions TINYINT(1) NOT NULL DEFAULT 0 AFTER body_hash;

ALTER TABLE sr_policy_document_versions
    DROP INDEX uq_sr_policy_document_versions_key;

ALTER TABLE sr_policy_document_versions
    DROP COLUMN version_key;

UPDATE sr_modules
SET version = '2026.07.001',
    updated_at = NOW()
WHERE module_key = 'policy_documents';
