DELETE s
FROM sr_module_settings s
INNER JOIN sr_modules m ON m.id = s.module_id
WHERE m.module_key = 'point'
  AND s.setting_key = 'manual_adjust_group_policies_json';

UPDATE sr_modules
SET version = '2026.05.002',
    updated_at = NOW()
WHERE module_key = 'point';
