UPDATE sr_module_settings s
INNER JOIN sr_modules m ON m.id = s.module_id
SET s.setting_value = 'both',
    s.value_type = 'string',
    s.updated_at = NOW()
WHERE m.module_key = 'member'
  AND s.setting_key = 'login_identifier';

UPDATE sr_modules
SET version = '2026.05.006',
    updated_at = NOW()
WHERE module_key = 'member';
