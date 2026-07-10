INSERT INTO {{SR_TABLE_PREFIX}}module_settings
    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT m.id, 'internal_embed_enabled', COALESCE(legacy.setting_value, '1'), 'bool', NOW(), NOW()
FROM {{SR_TABLE_PREFIX}}modules AS m
LEFT JOIN {{SR_TABLE_PREFIX}}module_settings AS legacy
    ON legacy.module_id = m.id
   AND legacy.setting_key = 'embed_enabled'
WHERE m.module_key = 'survey'
ON DUPLICATE KEY UPDATE
    setting_value = {{SR_TABLE_PREFIX}}module_settings.setting_value;

DELETE ms
FROM {{SR_TABLE_PREFIX}}module_settings AS ms
INNER JOIN {{SR_TABLE_PREFIX}}modules AS m ON m.id = ms.module_id
WHERE m.module_key = 'survey'
  AND ms.setting_key = 'embed_enabled';

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.002',
    updated_at = NOW()
WHERE module_key = 'survey';
