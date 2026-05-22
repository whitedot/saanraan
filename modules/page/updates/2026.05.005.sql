ALTER TABLE sr_pages
    ADD COLUMN layout_key VARCHAR(80) NOT NULL DEFAULT '' AFTER status;

ALTER TABLE sr_page_revisions
    ADD COLUMN layout_key VARCHAR(80) NOT NULL DEFAULT '' AFTER status;

UPDATE sr_modules
SET version = '2026.05.005',
    updated_at = NOW()
WHERE module_key = 'page';
