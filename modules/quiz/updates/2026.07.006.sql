ALTER TABLE {{SR_TABLE_PREFIX}}quiz_sets
    ADD COLUMN comment_editor_key VARCHAR(40) NOT NULL DEFAULT 'inherit' AFTER reaction_comment_preset_key;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.006',
    updated_at = NOW()
WHERE module_key = 'quiz';
