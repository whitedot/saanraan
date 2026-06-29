UPDATE {{SR_TABLE_PREFIX}}module_settings legacy_settings
INNER JOIN {{SR_TABLE_PREFIX}}modules admin_module
    ON admin_module.id = legacy_settings.module_id
   AND admin_module.module_key = 'admin'
LEFT JOIN {{SR_TABLE_PREFIX}}module_settings theme_settings
    ON theme_settings.module_id = legacy_settings.module_id
   AND theme_settings.setting_key = 'admin_theme_key'
SET legacy_settings.setting_key = 'admin_theme_key',
    legacy_settings.updated_at = NOW()
WHERE legacy_settings.setting_key = CONCAT('admin_', 'skin_key')
  AND theme_settings.id IS NULL;

DELETE legacy_settings
FROM {{SR_TABLE_PREFIX}}module_settings legacy_settings
INNER JOIN {{SR_TABLE_PREFIX}}modules admin_module
    ON admin_module.id = legacy_settings.module_id
   AND admin_module.module_key = 'admin'
WHERE legacy_settings.setting_key = CONCAT('admin_', 'skin_key');

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.002',
    updated_at = NOW()
WHERE module_key = 'admin';
