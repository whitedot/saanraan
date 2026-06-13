ALTER TABLE sr_survey_forms
    ADD COLUMN reaction_preset_key VARCHAR(80) NOT NULL DEFAULT '' AFTER secret_comments_enabled,
    ADD COLUMN reaction_comment_preset_key VARCHAR(80) NOT NULL DEFAULT '' AFTER reaction_preset_key;
