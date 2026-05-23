ALTER TABLE sr_content_items
    ADD COLUMN banner_before_content_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN banner_after_content_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER banner_before_content_id,
    ADD COLUMN popup_layer_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER banner_after_content_id;

ALTER TABLE sr_content_revisions
    ADD COLUMN banner_before_content_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN banner_after_content_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER banner_before_content_id,
    ADD COLUMN popup_layer_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER banner_after_content_id;

UPDATE sr_modules
SET version = '2026.05.002'
WHERE module_key = 'content';
