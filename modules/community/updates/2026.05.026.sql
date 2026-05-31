UPDATE sr_modules
SET version = '2026.05.026',
    updated_at = CURRENT_TIMESTAMP
WHERE module_key = 'community';
