UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.008',
    updated_at = NOW()
WHERE module_key = 'community';
