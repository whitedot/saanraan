ALTER TABLE {{SR_TABLE_PREFIX}}content_items
    ADD COLUMN cover_image_url VARCHAR(255) NOT NULL DEFAULT '' AFTER summary;

ALTER TABLE {{SR_TABLE_PREFIX}}content_revisions
    ADD COLUMN cover_image_url VARCHAR(255) NOT NULL DEFAULT '' AFTER summary;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.010',
    updated_at = NOW()
WHERE module_key = 'content';
