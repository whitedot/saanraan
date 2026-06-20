UPDATE sr_modules
SET version = '2026.06.029',
    updated_at = NOW()
WHERE module_key = 'community';
