INSERT INTO {{SR_TABLE_PREFIX}}module_settings
    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT m.id,
       'level_max_value',
       '10',
       'int',
       NOW(),
       NOW()
FROM {{SR_TABLE_PREFIX}}modules m
LEFT JOIN {{SR_TABLE_PREFIX}}module_settings s
    ON s.module_id = m.id
   AND s.setting_key = 'level_max_value'
WHERE m.module_key = 'community'
  AND s.id IS NULL;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.05.023',
    updated_at = NOW()
WHERE module_key = 'community';
