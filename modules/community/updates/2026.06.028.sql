ALTER TABLE sr_community_posts
    ADD COLUMN reaction_preset_key VARCHAR(80) NOT NULL DEFAULT '' AFTER body_format,
    ADD COLUMN reaction_comment_preset_key VARCHAR(80) NOT NULL DEFAULT '' AFTER reaction_preset_key;

UPDATE sr_modules
SET version = '2026.06.028',
    updated_at = NOW()
WHERE module_key = 'community';
