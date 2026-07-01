INSERT INTO {{SR_TABLE_PREFIX}}module_settings
    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT id, 'multi_asset_payment_enabled', '1', 'bool', NOW(), NOW()
FROM {{SR_TABLE_PREFIX}}modules
WHERE module_key = 'content'
ON DUPLICATE KEY UPDATE
    setting_value = {{SR_TABLE_PREFIX}}module_settings.setting_value;

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.030',
    updated_at = NOW()
WHERE module_key = 'content';
