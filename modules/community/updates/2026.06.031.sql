INSERT INTO sr_module_settings
    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT m.id, v.setting_key, v.setting_value, v.value_type, NOW(), NOW()
FROM sr_modules m
INNER JOIN (
    SELECT 'message_charge_enabled' AS setting_key, '0' AS setting_value, 'bool' AS value_type
    UNION ALL SELECT 'message_charge_asset_module', '', 'string'
    UNION ALL SELECT 'message_charge_amount', '0', 'int'
    UNION ALL SELECT 'message_charge_settlement_currency', 'KRW', 'string'
    UNION ALL SELECT 'message_charge_amounts_json', '', 'json'
    UNION ALL SELECT 'message_charge_group_policies_json', '', 'json'
    UNION ALL SELECT 'message_charge_policy_set_id', '0', 'int'
) v
WHERE m.module_key = 'community'
ON DUPLICATE KEY UPDATE
    setting_value = sr_module_settings.setting_value,
    updated_at = sr_module_settings.updated_at;

UPDATE sr_modules
SET version = '2026.06.031',
    updated_at = NOW()
WHERE module_key = 'community';
