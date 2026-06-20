INSERT INTO sr_module_settings
    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT m.id, v.setting_key, v.setting_value, v.value_type, NOW(), NOW()
FROM sr_modules m
INNER JOIN (
    SELECT 'thumbnail_enabled' AS setting_key, '1' AS setting_value, 'bool' AS value_type
    UNION ALL SELECT 'thumbnail_criterion', 'width', 'string'
    UNION ALL SELECT 'thumbnail_min_width', '320', 'int'
    UNION ALL SELECT 'thumbnail_min_bytes', '102400', 'int'
) v
WHERE m.module_key = 'community'
ON DUPLICATE KEY UPDATE
    setting_value = sr_module_settings.setting_value,
    updated_at = sr_module_settings.updated_at;

UPDATE sr_modules
SET version = '2026.06.030',
    updated_at = NOW()
WHERE module_key = 'community';
