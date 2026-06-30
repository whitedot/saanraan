SET @sr_community_asset_settlement_default_currency_raw = (
    SELECT setting_value
    FROM {{SR_TABLE_PREFIX}}site_settings
    WHERE setting_key = 'site.default_currency'
    LIMIT 1
);

SET @sr_community_asset_settlement_default_currency = CASE
    WHEN UPPER(TRIM(COALESCE(@sr_community_asset_settlement_default_currency_raw, ''))) IN ('KRW', 'USD')
        THEN UPPER(TRIM(@sr_community_asset_settlement_default_currency_raw))
    ELSE 'KRW'
END;

UPDATE {{SR_TABLE_PREFIX}}module_settings s
INNER JOIN {{SR_TABLE_PREFIX}}modules m ON m.id = s.module_id
SET s.setting_value = @sr_community_asset_settlement_default_currency,
    s.value_type = 'string',
    s.updated_at = NOW()
WHERE m.module_key = 'community'
  AND s.setting_key IN (
    'write_charge_settlement_currency',
    'message_charge_settlement_currency',
    'comment_charge_settlement_currency',
    'paid_read_settlement_currency',
    'paid_attachment_download_settlement_currency'
  )
  AND s.setting_value IN ('', 'KRW');

UPDATE {{SR_TABLE_PREFIX}}community_asset_logs
SET settlement_currency = @sr_community_asset_settlement_default_currency,
    purchase_power_snapshot_json = CONCAT(
        '{"legacy_assumption":"legacy 1:1 assumed","asset_units":1,"settlement_units":1,"settlement_currency":"',
        @sr_community_asset_settlement_default_currency,
        '","currency_min_unit":1,"snapshot_schema_version":"asset_settlement_snapshot_v1","rounding_policy_version":"asset_settlement_rounding_v1"}'
    ),
    snapshot_schema_version = 'asset_settlement_snapshot_v1',
    rounding_policy_version = 'asset_settlement_rounding_v1'
WHERE settlement_currency IN ('', 'KRW')
  AND purchase_power_snapshot_json LIKE '%legacy 1:1 assumed%';

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.043',
    updated_at = NOW()
WHERE module_key = 'community';
