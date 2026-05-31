UPDATE sr_modules
SET version = '2026.05.019',
    updated_at = CURRENT_TIMESTAMP
WHERE module_key = 'content';
