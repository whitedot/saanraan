UPDATE sr_module_settings s
INNER JOIN sr_modules m ON m.id = s.module_id
LEFT JOIN sr_site_settings c ON c.setting_key = 'site.default_currency'
SET s.setting_value = COALESCE(NULLIF(c.setting_value, ''), 'KRW'),
    s.value_type = 'string',
    s.updated_at = NOW()
WHERE m.module_key = 'community'
  AND s.setting_key IN (
    'write_charge_settlement_currency',
    'comment_charge_settlement_currency',
    'paid_read_settlement_currency',
    'paid_attachment_download_settlement_currency'
  );

INSERT INTO sr_module_settings
    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT m.id, v.setting_key, COALESCE(NULLIF(c.setting_value, ''), 'KRW'), 'string', NOW(), NOW()
FROM sr_modules m
JOIN (
    SELECT 'write_charge_settlement_currency' AS setting_key
    UNION ALL SELECT 'comment_charge_settlement_currency'
    UNION ALL SELECT 'paid_read_settlement_currency'
    UNION ALL SELECT 'paid_attachment_download_settlement_currency'
) v
LEFT JOIN sr_site_settings c ON c.setting_key = 'site.default_currency'
LEFT JOIN sr_module_settings existing
  ON existing.module_id = m.id
 AND existing.setting_key = v.setting_key
WHERE m.module_key = 'community'
  AND existing.id IS NULL;

UPDATE sr_modules
SET version = '2026.06.021',
    updated_at = NOW()
WHERE module_key = 'community';
