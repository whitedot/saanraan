INSERT INTO sr_module_settings
    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT m.id,
       'layout_key',
       CASE
           WHEN s.setting_value = 'basic' OR s.setting_value IS NULL OR s.setting_value = '' THEN 'community.basic'
           WHEN s.setting_value LIKE 'community.%' THEN s.setting_value
           ELSE CONCAT('community.', s.setting_value)
       END,
       'string',
       NOW(),
       NOW()
FROM sr_modules m
LEFT JOIN sr_module_settings s ON s.module_id = m.id AND s.setting_key = 'theme_key'
WHERE m.module_key = 'community'
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    value_type = VALUES(value_type),
    updated_at = VALUES(updated_at);

UPDATE sr_modules
SET version = '2026.05.010',
    updated_at = NOW()
WHERE module_key = 'community';
