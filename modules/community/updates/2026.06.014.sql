ALTER TABLE sr_community_posts
    ADD COLUMN is_secret TINYINT(1) NOT NULL DEFAULT 0 AFTER og_image_attachment_id;
