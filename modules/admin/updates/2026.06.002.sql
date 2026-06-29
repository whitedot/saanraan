UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.002',
    updated_at = NOW()
WHERE module_key = 'admin';
