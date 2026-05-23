ALTER TABLE sr_content_items
    ADD COLUMN layout_key VARCHAR(80) NOT NULL DEFAULT '' AFTER status;

ALTER TABLE sr_content_revisions
    ADD COLUMN layout_key VARCHAR(80) NOT NULL DEFAULT '' AFTER status;

UPDATE sr_modules
SET version = '2026.05.005',
    updated_at = NOW()
WHERE module_key = 'content';
