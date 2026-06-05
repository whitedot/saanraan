ALTER TABLE sr_community_posts
    ADD COLUMN seo_title VARCHAR(160) NOT NULL DEFAULT '' AFTER body_format,
    ADD COLUMN seo_description VARCHAR(255) NOT NULL DEFAULT '' AFTER seo_title,
    ADD COLUMN og_title VARCHAR(160) NOT NULL DEFAULT '' AFTER seo_description,
    ADD COLUMN og_description VARCHAR(255) NOT NULL DEFAULT '' AFTER og_title,
    ADD COLUMN og_image_attachment_id BIGINT UNSIGNED NULL AFTER og_description,
    ADD KEY idx_sr_community_posts_og_image_attachment (og_image_attachment_id);

UPDATE sr_modules
SET version = '2026.06.010',
    updated_at = NOW()
WHERE module_key = 'community';
