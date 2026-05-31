UPDATE sr_modules
SET version = '2026.05.004',
    updated_at = CURRENT_TIMESTAMP
WHERE module_key = 'deposit';
