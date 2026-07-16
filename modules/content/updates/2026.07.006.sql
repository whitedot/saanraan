ALTER TABLE {{SR_TABLE_PREFIX}}content_items
    ADD COLUMN show_title TINYINT(1) NOT NULL DEFAULT 1 AFTER layout_key;

ALTER TABLE {{SR_TABLE_PREFIX}}content_revisions
    ADD COLUMN show_title TINYINT(1) NOT NULL DEFAULT 1 AFTER layout_key;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.006',
    updated_at = NOW()
WHERE module_key = 'content';
