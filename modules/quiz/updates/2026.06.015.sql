ALTER TABLE sr_quiz_sets
    ADD COLUMN cover_image_url VARCHAR(255) NOT NULL DEFAULT '' AFTER description;
