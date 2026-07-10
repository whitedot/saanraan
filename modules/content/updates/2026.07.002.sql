INSERT INTO {{SR_TABLE_PREFIX}}module_settings
    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT m.id, 'plain_text_auto_link_new_tab', '0', 'bool', NOW(), NOW()
FROM {{SR_TABLE_PREFIX}}modules AS m
WHERE m.module_key = 'content'
ON DUPLICATE KEY UPDATE
    setting_value = {{SR_TABLE_PREFIX}}module_settings.setting_value;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.002',
    updated_at = NOW()
WHERE module_key = 'content';
