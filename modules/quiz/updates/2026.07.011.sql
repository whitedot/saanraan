UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.011',
    updated_at = NOW()
WHERE module_key = 'quiz';
