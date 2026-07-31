ALTER TABLE {{SR_TABLE_PREFIX}}content_items
    ADD COLUMN antispam_comment_mode VARCHAR(20) NOT NULL DEFAULT 'always' AFTER comment_editor_key;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.011',
    updated_at = NOW()
WHERE module_key = 'content';
