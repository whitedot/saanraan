UPDATE sr_modules
SET version = '2026.05.015',
    updated_at = NOW()
WHERE module_key = 'content';
