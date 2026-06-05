DELETE FROM {{SR_TABLE_PREFIX}}content_link_refs;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.008',
    updated_at = NOW()
WHERE module_key = 'content';
