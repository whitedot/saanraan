ALTER TABLE sr_quiz_sets
    ADD COLUMN secret_comments_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER comments_enabled;
