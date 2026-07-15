ALTER TABLE {{SR_TABLE_PREFIX}}community_comments
    ADD COLUMN extra_values_json LONGTEXT NULL AFTER body_text;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.007',
    updated_at = NOW()
WHERE module_key = 'community';
