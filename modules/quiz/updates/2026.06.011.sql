ALTER TABLE sr_quiz_sets
    ADD COLUMN theme_key VARCHAR(40) NOT NULL DEFAULT '' AFTER description,
    ADD COLUMN skin_key VARCHAR(40) NOT NULL DEFAULT '' AFTER theme_key;
