DELETE FROM {{SR_TABLE_PREFIX}}community_link_refs;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.009',
    updated_at = NOW()
WHERE module_key = 'community';
