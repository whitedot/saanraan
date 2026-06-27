DROP TABLE IF EXISTS {{SR_TABLE_PREFIX}}community_feed_cache;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.040',
    updated_at = NOW()
WHERE module_key = 'community';
