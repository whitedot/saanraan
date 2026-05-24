UPDATE sr_modules
SET version = '2026.05.014',
    updated_at = NOW()
WHERE module_key = 'community';
