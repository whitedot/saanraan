DELETE s
FROM sr_module_settings s
INNER JOIN sr_modules m ON m.id = s.module_id
WHERE m.module_key = 'community'
  AND s.setting_key = 'theme_key';

UPDATE sr_modules
SET version = '2026.06.026',
    updated_at = NOW()
WHERE module_key = 'community';
