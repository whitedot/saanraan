ALTER TABLE sr_notifications
    ADD COLUMN body_format VARCHAR(20) NOT NULL DEFAULT 'plain' AFTER body_text;
