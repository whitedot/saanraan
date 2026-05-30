UPDATE sr_modules
SET version = '2026.05.016',
    updated_at = NOW()
WHERE module_key = 'content';
