UPDATE sr_modules
SET version = '2026.04.006',
    updated_at = NOW()
WHERE module_key = 'member';
