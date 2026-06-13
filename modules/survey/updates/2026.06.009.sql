ALTER TABLE sr_survey_forms
    ADD COLUMN skin_key VARCHAR(40) NOT NULL DEFAULT '' AFTER description;
