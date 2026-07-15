ALTER TABLE {{SR_TABLE_PREFIX}}content_comments
    ADD COLUMN extra_values_json LONGTEXT NULL AFTER body_text;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.004',
    updated_at = NOW()
WHERE module_key = 'content';
