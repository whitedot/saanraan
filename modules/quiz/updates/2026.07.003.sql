ALTER TABLE {{SR_TABLE_PREFIX}}quiz_comments
    ADD COLUMN extra_values_json LONGTEXT NULL AFTER body_text;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.003',
    updated_at = NOW()
WHERE module_key = 'quiz';
