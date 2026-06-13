ALTER TABLE {{SR_TABLE_PREFIX}}community_posts
    ADD COLUMN extra_values_json TEXT NULL AFTER guest_user_agent_hash;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.023',
    updated_at = NOW()
WHERE module_key = 'community';
