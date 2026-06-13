ALTER TABLE sr_quiz_sets
    ADD COLUMN reaction_preset_key VARCHAR(80) NOT NULL DEFAULT '' AFTER secret_comments_enabled,
    ADD COLUMN reaction_comment_preset_key VARCHAR(80) NOT NULL DEFAULT '' AFTER reaction_preset_key;
