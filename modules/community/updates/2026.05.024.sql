UPDATE sr_modules
SET version = '2026.05.024',
    updated_at = NOW()
WHERE module_key = 'community';
