ALTER TABLE {{SR_TABLE_PREFIX}}community_comments
    ADD COLUMN is_secret TINYINT(1) NOT NULL DEFAULT 0 AFTER body_text;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.012',
    updated_at = NOW()
WHERE module_key = 'community';
