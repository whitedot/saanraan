ALTER TABLE sr_community_asset_logs
    ADD COLUMN settlement_amount BIGINT NOT NULL DEFAULT 0 AFTER amount,
    ADD COLUMN settlement_currency CHAR(3) NOT NULL DEFAULT 'KRW' AFTER settlement_amount,
    ADD COLUMN purchase_power_snapshot_json TEXT NULL AFTER settlement_currency;

INSERT IGNORE INTO sr_module_settings
    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT m.id, v.setting_key, 'KRW', 'string', NOW(), NOW()
FROM sr_modules m
JOIN (
    SELECT 'write_charge_settlement_currency' AS setting_key
    UNION ALL SELECT 'comment_charge_settlement_currency'
    UNION ALL SELECT 'paid_read_settlement_currency'
    UNION ALL SELECT 'paid_attachment_download_settlement_currency'
) v
WHERE m.module_key = 'community';

UPDATE sr_community_asset_logs
SET settlement_amount = amount,
    settlement_currency = 'KRW',
    purchase_power_snapshot_json = '{"legacy_assumption":"legacy 1:1 assumed","asset_units":1,"settlement_units":1,"settlement_currency":"KRW","currency_min_unit":1,"policy_version":"asset_settlement_v1"}'
WHERE settlement_amount = 0
  AND amount <> 0;

UPDATE sr_modules
SET version = '2026.06.018',
    updated_at = NOW()
WHERE module_key = 'community';
