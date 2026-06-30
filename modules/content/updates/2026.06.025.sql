SET @sr_content_asset_settlement_default_currency_raw = (
    SELECT setting_value
    FROM {{SR_TABLE_PREFIX}}site_settings
    WHERE setting_key = 'site.default_currency'
    LIMIT 1
);

SET @sr_content_asset_settlement_default_currency = CASE
    WHEN UPPER(TRIM(COALESCE(@sr_content_asset_settlement_default_currency_raw, ''))) IN ('KRW', 'USD')
        THEN UPPER(TRIM(@sr_content_asset_settlement_default_currency_raw))
    ELSE 'KRW'
END;

UPDATE {{SR_TABLE_PREFIX}}content_items
SET asset_access_settlement_currency = @sr_content_asset_settlement_default_currency
WHERE asset_access_settlement_currency IN ('', 'KRW');

UPDATE {{SR_TABLE_PREFIX}}content_items
SET asset_action_settlement_currency = @sr_content_asset_settlement_default_currency
WHERE asset_action_settlement_currency IN ('', 'KRW');

UPDATE {{SR_TABLE_PREFIX}}content_revisions
SET asset_access_settlement_currency = @sr_content_asset_settlement_default_currency
WHERE asset_access_settlement_currency IN ('', 'KRW');

UPDATE {{SR_TABLE_PREFIX}}content_revisions
SET asset_action_settlement_currency = @sr_content_asset_settlement_default_currency
WHERE asset_action_settlement_currency IN ('', 'KRW');

UPDATE {{SR_TABLE_PREFIX}}content_files
SET asset_download_settlement_currency = @sr_content_asset_settlement_default_currency
WHERE asset_download_settlement_currency IN ('', 'KRW');

UPDATE {{SR_TABLE_PREFIX}}content_asset_access_logs
SET settlement_currency = @sr_content_asset_settlement_default_currency,
    purchase_power_snapshot_json = CONCAT(
        '{"legacy_assumption":"legacy 1:1 assumed","asset_units":1,"settlement_units":1,"settlement_currency":"',
        @sr_content_asset_settlement_default_currency,
        '","currency_min_unit":1,"snapshot_schema_version":"asset_settlement_snapshot_v1","rounding_policy_version":"asset_settlement_rounding_v1"}'
    ),
    snapshot_schema_version = 'asset_settlement_snapshot_v1',
    rounding_policy_version = 'asset_settlement_rounding_v1'
WHERE settlement_currency IN ('', 'KRW')
  AND purchase_power_snapshot_json LIKE '%legacy 1:1 assumed%';

UPDATE {{SR_TABLE_PREFIX}}content_asset_action_logs
SET settlement_currency = @sr_content_asset_settlement_default_currency,
    purchase_power_snapshot_json = CONCAT(
        '{"legacy_assumption":"legacy 1:1 assumed","asset_units":1,"settlement_units":1,"settlement_currency":"',
        @sr_content_asset_settlement_default_currency,
        '","currency_min_unit":1,"snapshot_schema_version":"asset_settlement_snapshot_v1","rounding_policy_version":"asset_settlement_rounding_v1"}'
    ),
    snapshot_schema_version = 'asset_settlement_snapshot_v1',
    rounding_policy_version = 'asset_settlement_rounding_v1'
WHERE settlement_currency IN ('', 'KRW')
  AND purchase_power_snapshot_json LIKE '%legacy 1:1 assumed%';

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.025',
    updated_at = NOW()
WHERE module_key = 'content';
