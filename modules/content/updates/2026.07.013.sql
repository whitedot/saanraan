UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.013',
    updated_at = NOW()
WHERE module_key = 'content';
