UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.003',
    updated_at = NOW()
WHERE module_key = 'content';
