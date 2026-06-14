ALTER TABLE sr_survey_forms
    ADD COLUMN cover_image_url VARCHAR(255) NOT NULL DEFAULT '' AFTER description;
