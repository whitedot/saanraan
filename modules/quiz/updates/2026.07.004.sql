ALTER TABLE {{SR_TABLE_PREFIX}}quiz_sets
    ADD COLUMN comment_extra_fields_json LONGTEXT NULL AFTER reaction_comment_preset_key;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.004',
    updated_at = NOW()
WHERE module_key = 'quiz';
