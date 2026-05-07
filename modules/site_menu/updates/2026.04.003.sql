UPDATE toy_modules
SET version = '2026.04.003',
    updated_at = NOW()
WHERE module_key = 'site_menu';
