UPDATE sr_modules
SET name = '설문·여론조사',
    version = '2026.06.014',
    updated_at = NOW()
WHERE module_key = 'survey';
